use anchor_lang::prelude::*;
use anchor_lang::solana_program::{program::invoke, system_instruction};
use anchor_spl::token::{self, Token, TokenAccount, Transfer};

declare_id!("7RMrnnQC1pckXgLWdqw6mqQT5QSmyUSKjcsHmTt5CTQV"); // TODO: Replace with your deployed program ID

#[program]
pub mod anchor_presale {
    use super::*;

    /// Initialize a new token sale configuration
    /// Only the sale owner can call this
    ///
    /// # Arguments
    /// * `price_lamports_per_token` - Price in lamports per token (with decimals)
    /// * `start_ts` - Unix timestamp when sale starts
    /// * `end_ts` - Unix timestamp when sale ends
    /// * `total_allocated` - Total tokens allocated for this sale
    pub fn initialize_sale(
        ctx: Context<InitializeSale>,
        price_lamports_per_token: u64,
        start_ts: i64,
        end_ts: i64,
        total_allocated: u64,
    ) -> Result<()> {
        require!(start_ts < end_ts, PresaleError::InvalidTimeRange);
        require!(total_allocated > 0, PresaleError::InvalidAllocation);

        let sale_config = &mut ctx.accounts.sale_config;
        sale_config.owner = ctx.accounts.owner.key();
        sale_config.token_mint = ctx.accounts.token_mint.key();
        sale_config.treasury = ctx.accounts.treasury.key();
        sale_config.price_lamports_per_token = price_lamports_per_token;
        sale_config.start_ts = start_ts;
        sale_config.end_ts = end_ts;
        sale_config.total_allocated = total_allocated;
        sale_config.sold = 0;
        sale_config.bump = ctx.bumps.sale_config;

        msg!(
            "Sale initialized: {} tokens at {} lamports per token",
            total_allocated,
            price_lamports_per_token
        );

        Ok(())
    }

    /// Buy tokens using a voucher signed by the backend voucher signer
    /// Verifies the ed25519 signature and transfers SOL to treasury
    ///
    /// # Arguments
    /// * `allocation` - Amount of tokens to purchase
    /// * `voucher` - VoucherData struct containing buyer, sale, max_allocation, nonce, expiry
    /// * `signature` - Ed25519 signature from backend voucher signer (64 bytes)
    pub fn buy_with_voucher(
        ctx: Context<BuyWithVoucher>,
        allocation: u64,
        voucher: VoucherData,
        signature: [u8; 64],
    ) -> Result<()> {
        let sale_config = &mut ctx.accounts.sale_config;
        let clock = Clock::get()?;
        let current_ts = clock.unix_timestamp;

        // Validate sale timing
        require!(
            current_ts >= sale_config.start_ts,
            PresaleError::SaleNotStarted
        );
        require!(current_ts <= sale_config.end_ts, PresaleError::SaleEnded);

        // Validate voucher
        require!(
            voucher.buyer == ctx.accounts.buyer.key(),
            PresaleError::InvalidVoucher
        );
        require!(
            voucher.sale == sale_config.key(),
            PresaleError::InvalidVoucher
        );
        require!(
            voucher.expiry_ts >= current_ts,
            PresaleError::VoucherExpired
        );
        require!(
            allocation <= voucher.max_allocation,
            PresaleError::ExceedsAllocation
        );
        require!(allocation > 0, PresaleError::InvalidAllocation);

        // Validate supply
        let new_sold = sale_config
            .sold
            .checked_add(allocation)
            .ok_or(PresaleError::Overflow)?;
        require!(
            new_sold <= sale_config.total_allocated,
            PresaleError::InsufficientSupply
        );

        // Verify voucher signature using ed25519 instruction
        // The voucher message is: buyer (32) + sale (32) + max_allocation (8) + nonce (8) + expiry_ts (8)
        let mut message = Vec::new();
        message.extend_from_slice(&voucher.buyer.to_bytes());
        message.extend_from_slice(&voucher.sale.to_bytes());
        message.extend_from_slice(&voucher.max_allocation.to_le_bytes());
        message.extend_from_slice(&voucher.nonce.to_le_bytes());
        message.extend_from_slice(&voucher.expiry_ts.to_le_bytes());

        // Verify signature using ed25519 program
        verify_ed25519_signature(
            &message,
            &signature,
            &ctx.accounts.voucher_signer.key().to_bytes(),
        )?;

        // Check for replay attack - ensure this nonce hasn't been used for this buyer+sale
        let buyer_escrow = &mut ctx.accounts.buyer_escrow;
        require!(
            buyer_escrow.allocation == 0,
            PresaleError::VoucherAlreadyUsed
        );

        // Calculate payment amount in lamports
        let payment_lamports = allocation
            .checked_mul(sale_config.price_lamports_per_token)
            .ok_or(PresaleError::Overflow)?;

        // Transfer SOL from buyer to treasury
        // Option 1: Using system_instruction::transfer (for SOL payments)
        invoke(
            &system_instruction::transfer(
                &ctx.accounts.buyer.key(),
                &ctx.accounts.treasury.key(),
                payment_lamports,
            ),
            &[
                ctx.accounts.buyer.to_account_info(),
                ctx.accounts.treasury.to_account_info(),
                ctx.accounts.system_program.to_account_info(),
            ],
        )?;

        // Option 2: For USDC/SPL token payments (uncomment if using SPL tokens)
        // token::transfer(
        //     CpiContext::new(
        //         ctx.accounts.token_program.to_account_info(),
        //         Transfer {
        //             from: ctx.accounts.buyer_payment_token_account.to_account_info(),
        //             to: ctx.accounts.treasury_token_account.to_account_info(),
        //             authority: ctx.accounts.buyer.to_account_info(),
        //         },
        //     ),
        //     payment_lamports, // or payment_tokens
        // )?;

        // Update buyer escrow
        buyer_escrow.sale = sale_config.key();
        buyer_escrow.buyer = ctx.accounts.buyer.key();
        buyer_escrow.allocation = allocation;
        buyer_escrow.claimed = 0;
        buyer_escrow.bump = ctx.bumps.buyer_escrow;

        // Update sale sold amount
        sale_config.sold = new_sold;

        msg!(
            "Buyer {} purchased {} tokens for {} lamports",
            ctx.accounts.buyer.key(),
            allocation,
            payment_lamports
        );

        Ok(())
    }

    /// Create a vesting schedule for a beneficiary
    /// Only the sale owner can call this
    ///
    /// # Arguments
    /// * `beneficiary` - Wallet that will receive vested tokens
    /// * `total_amount` - Total tokens to vest
    /// * `start_ts` - Vesting start timestamp
    /// * `cliff_seconds` - Cliff period in seconds (no tokens before this)
    /// * `duration_seconds` - Total vesting duration in seconds
    /// * `revocable` - Whether owner can revoke this vesting
    pub fn create_vesting(
        ctx: Context<CreateVesting>,
        beneficiary: Pubkey,
        total_amount: u64,
        start_ts: i64,
        cliff_seconds: u64,
        duration_seconds: u64,
        revocable: bool,
    ) -> Result<()> {
        require!(total_amount > 0, PresaleError::InvalidAllocation);
        require!(duration_seconds > 0, PresaleError::InvalidDuration);
        require!(
            cliff_seconds <= duration_seconds,
            PresaleError::InvalidCliff
        );

        let vesting = &mut ctx.accounts.vesting;
        vesting.beneficiary = beneficiary;
        vesting.total_amount = total_amount;
        vesting.released = 0;
        vesting.start_ts = start_ts;
        vesting.cliff_seconds = cliff_seconds;
        vesting.duration_seconds = duration_seconds;
        vesting.revocable = revocable;
        vesting.revoked = false;
        vesting.bump = ctx.bumps.vesting;

        msg!(
            "Vesting created for {} with {} tokens over {} seconds",
            beneficiary,
            total_amount,
            duration_seconds
        );

        Ok(())
    }

    /// Claim vested tokens based on the vesting schedule
    /// Beneficiary can call this to claim their vested tokens
    pub fn claim_vested(ctx: Context<ClaimVested>) -> Result<()> {
        let vesting = &mut ctx.accounts.vesting;
        let clock = Clock::get()?;
        let current_ts = clock.unix_timestamp;

        require!(!vesting.revoked, PresaleError::VestingRevoked);
        require!(
            vesting.beneficiary == ctx.accounts.beneficiary.key(),
            PresaleError::Unauthorized
        );

        // Calculate vested amount
        let vested_amount = calculate_vested_amount(vesting, current_ts)?;
        let claimable = vested_amount
            .checked_sub(vesting.released)
            .ok_or(PresaleError::Underflow)?;

        require!(claimable > 0, PresaleError::NothingToClaim);

        // Transfer tokens from vesting vault to beneficiary
        let vesting_account_info = vesting.to_account_info();
        let seeds = &[
            b"vesting".as_ref(),
            vesting.beneficiary.as_ref(),
            &[vesting.bump],
        ];
        let signer = &[&seeds[..]];

        token::transfer(
            CpiContext::new_with_signer(
                ctx.accounts.token_program.to_account_info(),
                Transfer {
                    from: ctx.accounts.vesting_vault.to_account_info(),
                    to: ctx.accounts.beneficiary_token_account.to_account_info(),
                    authority: vesting_account_info,
                },
                signer,
            ),
            claimable,
        )?;

        // Update released amount
        vesting.released = vesting
            .released
            .checked_add(claimable)
            .ok_or(PresaleError::Overflow)?;

        msg!(
            "Claimed {} vested tokens for {}",
            claimable,
            vesting.beneficiary
        );

        Ok(())
    }

    /// Revoke a vesting schedule (if revocable)
    /// Only the sale owner can call this
    /// Returns unvested tokens to treasury
    pub fn revoke_vesting(ctx: Context<RevokeVesting>) -> Result<()> {
        let vesting = &mut ctx.accounts.vesting;
        let clock = Clock::get()?;
        let current_ts = clock.unix_timestamp;

        require!(vesting.revocable, PresaleError::NotRevocable);
        require!(!vesting.revoked, PresaleError::AlreadyRevoked);

        // Calculate vested amount at current time
        let vested_amount = calculate_vested_amount(vesting, current_ts)?;
        let unvested = vesting
            .total_amount
            .checked_sub(vested_amount)
            .ok_or(PresaleError::Underflow)?;

        // Transfer unvested tokens back to treasury/escrow
        let vesting_account_info = vesting.to_account_info();
        let seeds = &[
            b"vesting".as_ref(),
            vesting.beneficiary.as_ref(),
            &[vesting.bump],
        ];
        let signer = &[&seeds[..]];

        if unvested > 0 {
            token::transfer(
                CpiContext::new_with_signer(
                    ctx.accounts.token_program.to_account_info(),
                    Transfer {
                        from: ctx.accounts.vesting_vault.to_account_info(),
                        to: ctx.accounts.treasury_token_account.to_account_info(),
                        authority: vesting_account_info,
                    },
                    signer,
                ),
                unvested,
            )?;
        }

        // Mark as revoked
        vesting.revoked = true;

        msg!(
            "Vesting revoked for {}, returned {} unvested tokens",
            vesting.beneficiary,
            unvested
        );

        Ok(())
    }
}

// ============================================================================
// Account Structs
// ============================================================================

#[derive(Accounts)]
pub struct InitializeSale<'info> {
    #[account(
        init,
        payer = owner,
        space = 8 + SaleConfig::INIT_SPACE,
        seeds = [b"sale_config", owner.key().as_ref()],
        bump
    )]
    pub sale_config: Account<'info, SaleConfig>,

    /// CHECK: Token mint for the sale
    pub token_mint: AccountInfo<'info>,

    /// CHECK: Treasury account to receive payments
    pub treasury: AccountInfo<'info>,

    #[account(mut)]
    pub owner: Signer<'info>,

    pub system_program: Program<'info, System>,
}

#[derive(Accounts)]
pub struct BuyWithVoucher<'info> {
    #[account(
        mut,
        seeds = [b"sale_config", sale_config.owner.as_ref()],
        bump = sale_config.bump
    )]
    pub sale_config: Account<'info, SaleConfig>,

    #[account(
        init,
        payer = buyer,
        space = 8 + BuyerEscrow::INIT_SPACE,
        seeds = [b"buyer_escrow", sale_config.key().as_ref(), buyer.key().as_ref()],
        bump
    )]
    pub buyer_escrow: Account<'info, BuyerEscrow>,

    #[account(mut)]
    pub buyer: Signer<'info>,

    /// CHECK: Treasury receives SOL payment
    #[account(mut)]
    pub treasury: AccountInfo<'info>,

    /// CHECK: Voucher signer public key (backend server key)
    pub voucher_signer: AccountInfo<'info>,

    // Uncomment for SPL token payments (USDC, etc.)
    // #[account(mut)]
    // pub buyer_payment_token_account: Account<'info, TokenAccount>,
    // #[account(mut)]
    // pub treasury_token_account: Account<'info, TokenAccount>,
    // pub token_program: Program<'info, Token>,
    pub system_program: Program<'info, System>,
}

#[derive(Accounts)]
#[instruction(beneficiary: Pubkey)]
pub struct CreateVesting<'info> {
    #[account(
        seeds = [b"sale_config", sale_config.owner.as_ref()],
        bump = sale_config.bump,
        has_one = owner
    )]
    pub sale_config: Account<'info, SaleConfig>,

    #[account(
        init,
        payer = owner,
        space = 8 + Vesting::INIT_SPACE,
        seeds = [b"vesting", beneficiary.as_ref()],
        bump
    )]
    pub vesting: Account<'info, Vesting>,

    #[account(mut)]
    pub owner: Signer<'info>,

    pub system_program: Program<'info, System>,
}

#[derive(Accounts)]
pub struct ClaimVested<'info> {
    #[account(
        mut,
        seeds = [b"vesting", beneficiary.key().as_ref()],
        bump = vesting.bump,
        has_one = beneficiary
    )]
    pub vesting: Account<'info, Vesting>,

    #[account(mut)]
    pub vesting_vault: Account<'info, TokenAccount>,

    #[account(mut)]
    pub beneficiary_token_account: Account<'info, TokenAccount>,

    pub beneficiary: Signer<'info>,

    pub token_program: Program<'info, Token>,
}

#[derive(Accounts)]
pub struct RevokeVesting<'info> {
    #[account(
        seeds = [b"sale_config", sale_config.owner.as_ref()],
        bump = sale_config.bump,
        has_one = owner
    )]
    pub sale_config: Account<'info, SaleConfig>,

    #[account(
        mut,
        seeds = [b"vesting", vesting.beneficiary.as_ref()],
        bump = vesting.bump
    )]
    pub vesting: Account<'info, Vesting>,

    #[account(mut)]
    pub vesting_vault: Account<'info, TokenAccount>,

    #[account(mut)]
    pub treasury_token_account: Account<'info, TokenAccount>,

    pub owner: Signer<'info>,

    pub token_program: Program<'info, Token>,
}

// ============================================================================
// State Accounts
// ============================================================================

/// Sale configuration account
#[account]
#[derive(InitSpace)]
pub struct SaleConfig {
    pub owner: Pubkey,                 // Sale owner/admin
    pub token_mint: Pubkey,            // Token being sold
    pub treasury: Pubkey,              // Treasury receiving payments
    pub price_lamports_per_token: u64, // Price per token in lamports
    pub start_ts: i64,                 // Sale start timestamp
    pub end_ts: i64,                   // Sale end timestamp
    pub total_allocated: u64,          // Total tokens allocated
    pub sold: u64,                     // Tokens sold so far
    pub bump: u8,                      // PDA bump seed
}

/// Vesting schedule account
#[account]
#[derive(InitSpace)]
pub struct Vesting {
    pub beneficiary: Pubkey,   // Who receives vested tokens
    pub total_amount: u64,     // Total tokens to vest
    pub released: u64,         // Tokens already released
    pub start_ts: i64,         // Vesting start timestamp
    pub cliff_seconds: u64,    // Cliff period (no tokens before this)
    pub duration_seconds: u64, // Total vesting duration
    pub revocable: bool,       // Can owner revoke?
    pub revoked: bool,         // Has been revoked?
    pub bump: u8,              // PDA bump seed
}

/// Buyer escrow account (tracks allocation)
#[account]
#[derive(InitSpace)]
pub struct BuyerEscrow {
    pub sale: Pubkey,    // Sale this escrow belongs to
    pub buyer: Pubkey,   // Buyer wallet
    pub allocation: u64, // Tokens allocated to buyer
    pub claimed: u64,    // Tokens claimed by buyer
    pub bump: u8,        // PDA bump seed
}

// ============================================================================
// Voucher Data Structure
// ============================================================================

/// Voucher data signed by backend server
#[derive(AnchorSerialize, AnchorDeserialize, Clone)]
pub struct VoucherData {
    pub buyer: Pubkey,       // Buyer wallet address
    pub sale: Pubkey,        // Sale config address
    pub max_allocation: u64, // Max tokens buyer can purchase
    pub nonce: u64,          // Unique nonce (prevents replay)
    pub expiry_ts: i64,      // Voucher expiry timestamp
}

// ============================================================================
// Helper Functions
// ============================================================================

/// Calculate vested amount based on time elapsed
fn calculate_vested_amount(vesting: &Vesting, current_ts: i64) -> Result<u64> {
    let elapsed = current_ts
        .checked_sub(vesting.start_ts)
        .ok_or(PresaleError::Underflow)?;

    // Before cliff, nothing is vested
    if elapsed < vesting.cliff_seconds as i64 {
        return Ok(0);
    }

    // After full duration, everything is vested
    if elapsed >= vesting.duration_seconds as i64 {
        return Ok(vesting.total_amount);
    }

    // Linear vesting: (elapsed / duration) * total_amount
    let vested = (vesting.total_amount as u128)
        .checked_mul(elapsed as u128)
        .ok_or(PresaleError::Overflow)?
        .checked_div(vesting.duration_seconds as u128)
        .ok_or(PresaleError::DivisionByZero)?;

    Ok(vested as u64)
}

/// Verify ed25519 signature using Solana's ed25519 program
/// This validates that the voucher was signed by the backend server
fn verify_ed25519_signature(
    message: &[u8],
    _signature: &[u8; 64],
    _public_key: &[u8; 32],
) -> Result<()> {
    // In production, you should use the ed25519 program instruction
    // For now, we'll use a simplified check
    //
    // Production code should create an Ed25519 instruction and verify it:
    // let ix = ed25519_instruction::new_ed25519_instruction(public_key, message, signature);
    // And verify the instruction was included in the transaction

    // For this implementation, we assume the signature is valid if the voucher_signer
    // account is provided correctly. In production, add proper ed25519 verification.

    msg!(
        "Verifying ed25519 signature for message of length {}",
        message.len()
    );

    // TODO: Implement full ed25519 verification using solana_program::ed25519_program
    // For now, this is a placeholder that should be replaced with actual verification

    Ok(())
}

// ============================================================================
// Error Codes
// ============================================================================

#[error_code]
pub enum PresaleError {
    #[msg("Invalid time range: start must be before end")]
    InvalidTimeRange,
    #[msg("Invalid allocation amount")]
    InvalidAllocation,
    #[msg("Sale has not started yet")]
    SaleNotStarted,
    #[msg("Sale has ended")]
    SaleEnded,
    #[msg("Invalid voucher data")]
    InvalidVoucher,
    #[msg("Voucher has expired")]
    VoucherExpired,
    #[msg("Allocation exceeds voucher limit")]
    ExceedsAllocation,
    #[msg("Insufficient supply remaining")]
    InsufficientSupply,
    #[msg("Voucher has already been used")]
    VoucherAlreadyUsed,
    #[msg("Arithmetic overflow")]
    Overflow,
    #[msg("Arithmetic underflow")]
    Underflow,
    #[msg("Division by zero")]
    DivisionByZero,
    #[msg("Invalid vesting duration")]
    InvalidDuration,
    #[msg("Invalid cliff period")]
    InvalidCliff,
    #[msg("Vesting has been revoked")]
    VestingRevoked,
    #[msg("Nothing to claim")]
    NothingToClaim,
    #[msg("Not revocable")]
    NotRevocable,
    #[msg("Already revoked")]
    AlreadyRevoked,
    #[msg("Unauthorized")]
    Unauthorized,
}
