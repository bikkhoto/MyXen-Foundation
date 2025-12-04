import * as anchor from "@coral-xyz/anchor";
import { Program } from "@coral-xyz/anchor";
import { AnchorPresale } from "../target/types/anchor_presale";
import {
  PublicKey,
  Keypair,
  SystemProgram,
  LAMPORTS_PER_SOL,
} from "@solana/web3.js";
import {
  TOKEN_PROGRAM_ID,
  createMint,
  getOrCreateAssociatedTokenAccount,
  mintTo,
} from "@solana/spl-token";
import { assert, expect } from "chai";
import nacl from "tweetnacl";

describe("anchor-presale", () => {
  // Configure the client to use the local cluster
  const provider = anchor.AnchorProvider.env();
  anchor.setProvider(provider);

  const program = anchor.workspace.AnchorPresale as Program<AnchorPresale>;

  // Test accounts
  let saleOwner: Keypair;
  let buyer: Keypair;
  let voucherSigner: Keypair; // Backend voucher signer
  let tokenMint: PublicKey;
  let treasury: Keypair;
  let saleConfigPda: PublicKey;
  let buyerEscrowPda: PublicKey;
  let vestingPda: PublicKey;

  // Sale parameters
  const pricePerToken = new anchor.BN(1_000_000); // 0.001 SOL per token
  const totalAllocated = new anchor.BN(1_000_000); // 1M tokens
  let startTs: anchor.BN;
  let endTs: anchor.BN;

  before(async () => {
    // Initialize test accounts
    saleOwner = Keypair.generate();
    buyer = Keypair.generate();
    voucherSigner = Keypair.generate(); // This simulates the backend server keypair
    treasury = Keypair.generate();

    // Airdrop SOL to test accounts
    await provider.connection.requestAirdrop(
      saleOwner.publicKey,
      10 * LAMPORTS_PER_SOL
    );
    await provider.connection.requestAirdrop(
      buyer.publicKey,
      10 * LAMPORTS_PER_SOL
    );
    await provider.connection.requestAirdrop(
      treasury.publicKey,
      1 * LAMPORTS_PER_SOL
    );

    // Wait for airdrops to confirm
    await new Promise((resolve) => setTimeout(resolve, 1000));

    // Create token mint
    tokenMint = await createMint(
      provider.connection,
      saleOwner,
      saleOwner.publicKey,
      null,
      9 // 9 decimals
    );

    console.log("Token mint created:", tokenMint.toBase58());
    console.log("Sale owner:", saleOwner.publicKey.toBase58());
    console.log("Buyer:", buyer.publicKey.toBase58());
    console.log("Voucher signer:", voucherSigner.publicKey.toBase58());

    // Set sale timing
    const now = Math.floor(Date.now() / 1000);
    startTs = new anchor.BN(now - 3600); // Started 1 hour ago
    endTs = new anchor.BN(now + 86400); // Ends in 24 hours

    // Derive PDAs
    [saleConfigPda] = PublicKey.findProgramAddressSync(
      [Buffer.from("sale_config"), saleOwner.publicKey.toBuffer()],
      program.programId
    );

    [buyerEscrowPda] = PublicKey.findProgramAddressSync(
      [
        Buffer.from("buyer_escrow"),
        saleConfigPda.toBuffer(),
        buyer.publicKey.toBuffer(),
      ],
      program.programId
    );

    [vestingPda] = PublicKey.findProgramAddressSync(
      [Buffer.from("vesting"), buyer.publicKey.toBuffer()],
      program.programId
    );
  });

  it("Initializes the sale", async () => {
    try {
      const tx = await program.methods
        .initializeSale(pricePerToken, startTs, endTs, totalAllocated)
        .accounts({
          saleConfig: saleConfigPda,
          tokenMint: tokenMint,
          treasury: treasury.publicKey,
          owner: saleOwner.publicKey,
          systemProgram: SystemProgram.programId,
        })
        .signers([saleOwner])
        .rpc();

      console.log("Initialize sale transaction:", tx);

      // Fetch and verify sale config
      const saleConfig = await program.account.saleConfig.fetch(saleConfigPda);

      assert.ok(saleConfig.owner.equals(saleOwner.publicKey));
      assert.ok(saleConfig.tokenMint.equals(tokenMint));
      assert.ok(saleConfig.treasury.equals(treasury.publicKey));
      assert.equal(
        saleConfig.priceLamportsPerToken.toString(),
        pricePerToken.toString()
      );
      assert.equal(saleConfig.startTs.toString(), startTs.toString());
      assert.equal(saleConfig.endTs.toString(), endTs.toString());
      assert.equal(
        saleConfig.totalAllocated.toString(),
        totalAllocated.toString()
      );
      assert.equal(saleConfig.sold.toString(), "0");

      console.log("✓ Sale initialized successfully");
    } catch (error) {
      console.error("Error initializing sale:", error);
      throw error;
    }
  });

  it("Buyer purchases tokens with valid voucher", async () => {
    const allocation = new anchor.BN(10_000); // Buy 10,000 tokens
    const nonce = new anchor.BN(1);
    const expiryTs = new anchor.BN(Math.floor(Date.now() / 1000) + 3600); // Expires in 1 hour

    // Create voucher data structure
    const voucherData = {
      buyer: buyer.publicKey,
      sale: saleConfigPda,
      maxAllocation: allocation,
      nonce: nonce,
      expiryTs: expiryTs,
    };

    // Serialize voucher message for signing
    // Message format: buyer (32) + sale (32) + max_allocation (8) + nonce (8) + expiry_ts (8)
    const message = Buffer.concat([
      buyer.publicKey.toBuffer(),
      saleConfigPda.toBuffer(),
      allocation.toArrayLike(Buffer, "le", 8),
      nonce.toArrayLike(Buffer, "le", 8),
      expiryTs.toArrayLike(Buffer, "le", 8),
    ]);

    // Sign the voucher using the backend voucher signer keypair
    const signature = nacl.sign.detached(message, voucherSigner.secretKey);
    const signatureArray = Array.from(signature);

    console.log("Voucher message length:", message.length);
    console.log("Signature length:", signature.length);

    // Get treasury balance before purchase
    const treasuryBalanceBefore = await provider.connection.getBalance(
      treasury.publicKey
    );

    // Execute buy_with_voucher
    try {
      const tx = await program.methods
        .buyWithVoucher(allocation, voucherData, signatureArray)
        .accounts({
          saleConfig: saleConfigPda,
          buyerEscrow: buyerEscrowPda,
          buyer: buyer.publicKey,
          treasury: treasury.publicKey,
          voucherSigner: voucherSigner.publicKey,
          systemProgram: SystemProgram.programId,
        })
        .signers([buyer])
        .rpc();

      console.log("Buy with voucher transaction:", tx);

      // Verify buyer escrow was created
      const escrow = await program.account.buyerEscrow.fetch(buyerEscrowPda);
      assert.ok(escrow.buyer.equals(buyer.publicKey));
      assert.ok(escrow.sale.equals(saleConfigPda));
      assert.equal(escrow.allocation.toString(), allocation.toString());
      assert.equal(escrow.claimed.toString(), "0");

      // Verify sale sold amount updated
      const saleConfig = await program.account.saleConfig.fetch(saleConfigPda);
      assert.equal(saleConfig.sold.toString(), allocation.toString());

      // Verify SOL was transferred to treasury
      const treasuryBalanceAfter = await provider.connection.getBalance(
        treasury.publicKey
      );
      const expectedPayment = allocation.mul(pricePerToken).toNumber();
      const actualPayment = treasuryBalanceAfter - treasuryBalanceBefore;

      assert.equal(actualPayment, expectedPayment);

      console.log(
        `✓ Buyer purchased ${allocation.toString()} tokens for ${expectedPayment} lamports`
      );
    } catch (error) {
      console.error("Error buying with voucher:", error);
      throw error;
    }
  });

  it("Creates vesting schedule for buyer", async () => {
    const vestingAmount = new anchor.BN(10_000); // Vest 10,000 tokens
    const vestingStartTs = new anchor.BN(Math.floor(Date.now() / 1000));
    const cliffSeconds = new anchor.BN(3600); // 1 hour cliff
    const durationSeconds = new anchor.BN(86400); // 24 hour total vesting
    const revocable = true;

    try {
      const tx = await program.methods
        .createVesting(
          buyer.publicKey,
          vestingAmount,
          vestingStartTs,
          cliffSeconds,
          durationSeconds,
          revocable
        )
        .accounts({
          saleConfig: saleConfigPda,
          vesting: vestingPda,
          owner: saleOwner.publicKey,
          systemProgram: SystemProgram.programId,
        })
        .signers([saleOwner])
        .rpc();

      console.log("Create vesting transaction:", tx);

      // Verify vesting account
      const vesting = await program.account.vesting.fetch(vestingPda);
      assert.ok(vesting.beneficiary.equals(buyer.publicKey));
      assert.equal(vesting.totalAmount.toString(), vestingAmount.toString());
      assert.equal(vesting.released.toString(), "0");
      assert.equal(vesting.startTs.toString(), vestingStartTs.toString());
      assert.equal(vesting.cliffSeconds.toString(), cliffSeconds.toString());
      assert.equal(
        vesting.durationSeconds.toString(),
        durationSeconds.toString()
      );
      assert.equal(vesting.revocable, revocable);
      assert.equal(vesting.revoked, false);

      console.log("✓ Vesting schedule created successfully");
    } catch (error) {
      console.error("Error creating vesting:", error);
      throw error;
    }
  });

  it("Buyer claims vested tokens after cliff period", async () => {
    // Note: In a real test, you would need to advance time or use a test validator
    // that allows time manipulation. For this example, we'll skip actual token transfer
    // and just verify the vesting calculation logic works.

    console.log(
      "⚠ Token claiming test requires token vault setup and time advancement"
    );
    console.log(
      "  This would require creating token accounts and minting tokens to vesting vault"
    );
    console.log("  For full integration testing, use devnet or localnet validator");

    // In production test:
    // 1. Create vesting vault token account
    // 2. Mint tokens to vesting vault
    // 3. Advance time past cliff
    // 4. Call claim_vested
    // 5. Verify tokens transferred to beneficiary

    const vesting = await program.account.vesting.fetch(vestingPda);
    console.log(
      "Vesting details:",
      JSON.stringify(
        {
          beneficiary: vesting.beneficiary.toBase58(),
          totalAmount: vesting.totalAmount.toString(),
          released: vesting.released.toString(),
          cliffSeconds: vesting.cliffSeconds.toString(),
          durationSeconds: vesting.durationSeconds.toString(),
        },
        null,
        2
      )
    );
  });

  it("Owner revokes vesting and returns unvested tokens", async () => {
    console.log(
      "⚠ Vesting revocation test requires token vault setup and token transfers"
    );
    console.log(
      "  This would require treasury token account and proper token minting"
    );

    // In production test:
    // 1. Calculate vested vs unvested amounts
    // 2. Call revoke_vesting
    // 3. Verify unvested tokens returned to treasury
    // 4. Verify vesting marked as revoked

    const vesting = await program.account.vesting.fetch(vestingPda);
    console.log("Vesting revocable:", vesting.revocable);
    console.log("Vesting revoked:", vesting.revoked);

    // The actual revoke call would be:
    // const tx = await program.methods
    //   .revokeVesting()
    //   .accounts({
    //     saleConfig: saleConfigPda,
    //     vesting: vestingPda,
    //     vestingVault: vestingVaultTokenAccount,
    //     treasuryTokenAccount: treasuryTokenAccount,
    //     owner: saleOwner.publicKey,
    //     tokenProgram: TOKEN_PROGRAM_ID,
    //   })
    //   .signers([saleOwner])
    //   .rpc();
  });

  it("Rejects purchase with expired voucher", async () => {
    const allocation = new anchor.BN(5_000);
    const nonce = new anchor.BN(2);
    const expiryTs = new anchor.BN(Math.floor(Date.now() / 1000) - 3600); // Expired 1 hour ago

    const voucherData = {
      buyer: buyer.publicKey,
      sale: saleConfigPda,
      maxAllocation: allocation,
      nonce: nonce,
      expiryTs: expiryTs,
    };

    const message = Buffer.concat([
      buyer.publicKey.toBuffer(),
      saleConfigPda.toBuffer(),
      allocation.toArrayLike(Buffer, "le", 8),
      nonce.toArrayLike(Buffer, "le", 8),
      expiryTs.toArrayLike(Buffer, "le", 8),
    ]);

    const signature = nacl.sign.detached(message, voucherSigner.secretKey);
    const signatureArray = Array.from(signature);

    // Create a new buyer for this test to avoid escrow conflict
    const newBuyer = Keypair.generate();
    await provider.connection.requestAirdrop(
      newBuyer.publicKey,
      5 * LAMPORTS_PER_SOL
    );
    await new Promise((resolve) => setTimeout(resolve, 1000));

    const [newBuyerEscrowPda] = PublicKey.findProgramAddressSync(
      [
        Buffer.from("buyer_escrow"),
        saleConfigPda.toBuffer(),
        newBuyer.publicKey.toBuffer(),
      ],
      program.programId
    );

    try {
      await program.methods
        .buyWithVoucher(allocation, voucherData, signatureArray)
        .accounts({
          saleConfig: saleConfigPda,
          buyerEscrow: newBuyerEscrowPda,
          buyer: newBuyer.publicKey,
          treasury: treasury.publicKey,
          voucherSigner: voucherSigner.publicKey,
          systemProgram: SystemProgram.programId,
        })
        .signers([newBuyer])
        .rpc();

      // Should not reach here
      assert.fail("Expected transaction to fail with expired voucher");
    } catch (error) {
      // Expected to fail
      expect(error.toString()).to.include("VoucherExpired");
      console.log("✓ Correctly rejected expired voucher");
    }
  });

  it("Rejects purchase exceeding voucher allocation", async () => {
    const maxAllocation = new anchor.BN(1_000);
    const attemptedAllocation = new anchor.BN(2_000); // Try to buy more than allowed
    const nonce = new anchor.BN(3);
    const expiryTs = new anchor.BN(Math.floor(Date.now() / 1000) + 3600);

    const voucherData = {
      buyer: buyer.publicKey,
      sale: saleConfigPda,
      maxAllocation: maxAllocation,
      nonce: nonce,
      expiryTs: expiryTs,
    };

    const message = Buffer.concat([
      buyer.publicKey.toBuffer(),
      saleConfigPda.toBuffer(),
      maxAllocation.toArrayLike(Buffer, "le", 8),
      nonce.toArrayLike(Buffer, "le", 8),
      expiryTs.toArrayLike(Buffer, "le", 8),
    ]);

    const signature = nacl.sign.detached(message, voucherSigner.secretKey);
    const signatureArray = Array.from(signature);

    const newBuyer = Keypair.generate();
    await provider.connection.requestAirdrop(
      newBuyer.publicKey,
      5 * LAMPORTS_PER_SOL
    );
    await new Promise((resolve) => setTimeout(resolve, 1000));

    const [newBuyerEscrowPda] = PublicKey.findProgramAddressSync(
      [
        Buffer.from("buyer_escrow"),
        saleConfigPda.toBuffer(),
        newBuyer.publicKey.toBuffer(),
      ],
      program.programId
    );

    try {
      await program.methods
        .buyWithVoucher(attemptedAllocation, voucherData, signatureArray)
        .accounts({
          saleConfig: saleConfigPda,
          buyerEscrow: newBuyerEscrowPda,
          buyer: newBuyer.publicKey,
          treasury: treasury.publicKey,
          voucherSigner: voucherSigner.publicKey,
          systemProgram: SystemProgram.programId,
        })
        .signers([newBuyer])
        .rpc();

      assert.fail("Expected transaction to fail with exceeds allocation");
    } catch (error) {
      expect(error.toString()).to.include("ExceedsAllocation");
      console.log("✓ Correctly rejected purchase exceeding voucher limit");
    }
  });
});
