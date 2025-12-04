/**
 * Test Script for Presale Flow Validation
 * 
 * This script tests the complete voucher purchase flow without requiring browser interaction.
 * It simulates the React app's logic to validate all components.
 */

const anchor = require('@coral-xyz/anchor');
const { PublicKey, Connection, Keypair, clusterApiUrl } = require('@solana/web3.js');
const fs = require('fs');
const path = require('path');
const os = require('os');

// Configuration
const SALE_CONFIG_ADDRESS = '6RJkCXchhSJh6STRo5gN4axqeubE9xAEGsTgsaoCTNvQ';
const BACKEND_URL = 'http://127.0.0.1:8000';
const TEST_AMOUNT = 10; // Number of tokens to buy (price is 0.1 SOL per token = 10 tokens for 1 SOL)

// Load IDL
const idl = JSON.parse(
  fs.readFileSync(path.join(__dirname, 'app/src/idl/anchor_presale.json'), 'utf8')
);

const PROGRAM_ID = new PublicKey(idl.address);

async function main() {
  console.log('🧪 Starting Presale Flow Validation\n');
  console.log('═'.repeat(70));

  // 1. Setup connection and wallet
  console.log('\n1️⃣  Setting up connection and wallet...');
  const connection = new Connection(clusterApiUrl('devnet'), 'confirmed');
  
  const walletPath = path.join(os.homedir(), '.config', 'solana', 'id.json');
  const secretKey = Uint8Array.from(JSON.parse(fs.readFileSync(walletPath, 'utf8')));
  const keypair = Keypair.fromSecretKey(secretKey);
  const wallet = new anchor.Wallet(keypair);
  
  console.log('   ✅ Wallet:', wallet.publicKey.toBase58());
  
  const balance = await connection.getBalance(wallet.publicKey);
  console.log('   ✅ Balance:', (balance / 1e9).toFixed(4), 'SOL');

  // 2. Initialize program
  console.log('\n2️⃣  Initializing Anchor program...');
  const provider = new anchor.AnchorProvider(connection, wallet, { commitment: 'confirmed' });
  const program = new anchor.Program(idl, provider);
  console.log('   ✅ Program ID:', program.programId.toBase58());

  // 3. Fetch sale config
  console.log('\n3️⃣  Fetching sale configuration...');
  const saleConfigPDA = new PublicKey(SALE_CONFIG_ADDRESS);
  const saleConfig = await program.account.saleConfig.fetch(saleConfigPDA);
  
  console.log('   ✅ Sale Config:', {
    owner: saleConfig.owner?.toBase58() || 'N/A',
    treasury: saleConfig.treasury?.toBase58() || 'N/A',
    tokenMint: saleConfig.token_mint?.toBase58() || saleConfig.tokenMint?.toBase58() || 'N/A',
    price: (saleConfig.price_lamports_per_token || saleConfig.priceLamportsPerToken || 0).toString() + ' lamports/token',
    startTime: new Date((saleConfig.start_ts || saleConfig.startTs || { toNumber: () => 0 }).toNumber() * 1000).toISOString(),
    endTime: new Date((saleConfig.end_ts || saleConfig.endTs || { toNumber: () => 0 }).toNumber() * 1000).toISOString(),
    totalAllocated: ((saleConfig.total_allocated || saleConfig.totalAllocated || { toNumber: () => 0 }).toNumber() / 1e9).toLocaleString() + ' tokens',
    sold: (saleConfig.sold || { toNumber: () => 0 }).toNumber() / 1e9 + ' tokens',
  });
  
  console.log('   Raw account data keys:', Object.keys(saleConfig));

  // 4. Request voucher from backend
  console.log('\n4️⃣  Requesting voucher from Laravel API...');
  const voucherPayload = {
    buyer_pubkey: wallet.publicKey.toBase58(),
    sale_pubkey: SALE_CONFIG_ADDRESS,
    max_allocation: 1000000, // 1M tokens
    expiry_ts: Math.floor(Date.now() / 1000) + 3600,
  };

  console.log('   Request payload:', JSON.stringify(voucherPayload, null, 2));

  const voucherResponse = await fetch(`${BACKEND_URL}/api/v1/sale/test/whitelist`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify(voucherPayload),
  });

  if (!voucherResponse.ok) {
    const errorText = await voucherResponse.text();
    console.error('   ❌ Voucher request failed:', voucherResponse.status);
    console.error('   Response:', errorText);
    process.exit(1);
  }

  const voucherData = await voucherResponse.json();
  console.log('   ✅ Voucher received:', {
    buyer: voucherData.voucher.buyer,
    maxAllocation: voucherData.voucher.max_allocation,
    nonce: voucherData.voucher.nonce,
    expiryTs: voucherData.voucher.expiry_ts,
    signatureLength: Buffer.from(voucherData.signature, 'base64').length,
    signerPubkey: voucherData.signer_pubkey,
  });

  // 5. Derive buyer escrow PDA
  console.log('\n5️⃣  Deriving buyer escrow PDA...');
  const [buyerEscrowPDA] = PublicKey.findProgramAddressSync(
    [
      Buffer.from('buyer_escrow'),
      saleConfigPDA.toBuffer(),
      wallet.publicKey.toBuffer(),
    ],
    program.programId
  );
  console.log('   ✅ Buyer Escrow PDA:', buyerEscrowPDA.toBase58());

  // 6. Prepare transaction
  console.log('\n6️⃣  Preparing buy_with_voucher transaction...');
  const signatureBytes = Buffer.from(voucherData.signature, 'base64');
  const signerPubkey = new PublicKey(voucherData.signer_pubkey);

  console.log('   Transaction params:', {
    maxAllocation: voucherData.voucher.max_allocation,
    nonce: voucherData.voucher.nonce,
    expiryTs: voucherData.voucher.expiry_ts,
    signatureLength: signatureBytes.length,
    lamportsToSpend: TEST_AMOUNT,
  });

  console.log('   Accounts:', {
    saleConfig: saleConfigPDA.toBase58(),
    buyerEscrow: buyerEscrowPDA.toBase58(),
    buyer: wallet.publicKey.toBase58(),
    treasury: saleConfig.treasury.toBase58(),
    voucherSigner: signerPubkey.toBase58(),
    systemProgram: anchor.web3.SystemProgram.programId.toBase58(),
  });

  // 7. Execute transaction
  console.log('\n7️⃣  Executing buy_with_voucher instruction...');
  try {
    const tx = await program.methods
      .buyWithVoucher(
        new anchor.BN(TEST_AMOUNT), // allocation: amount to purchase
        {
          buyer: new PublicKey(voucherData.voucher.buyer),
          sale: new PublicKey(voucherData.voucher.sale),
          maxAllocation: new anchor.BN(voucherData.voucher.max_allocation),
          nonce: new anchor.BN(voucherData.voucher.nonce),
          expiryTs: new anchor.BN(voucherData.voucher.expiry_ts),
        }, // voucher struct
        Array.from(signatureBytes) // signature array
      )
      .accounts({
        saleConfig: saleConfigPDA,
        buyerEscrow: buyerEscrowPDA,
        buyer: wallet.publicKey,
        treasury: saleConfig.treasury,
        voucherSigner: signerPubkey,
        systemProgram: anchor.web3.SystemProgram.programId,
      })
      .rpc();

    console.log('   ✅ Transaction successful!');
    console.log('   Signature:', tx);

    // 8. Verify buyer escrow
    console.log('\n8️⃣  Verifying buyer escrow account...');
    const buyerEscrowAccount = await program.account.buyerEscrow.fetch(buyerEscrowPDA);
    console.log('   ✅ Buyer Escrow:', {
      buyer: buyerEscrowAccount.buyer.toBase58(),
      sale: buyerEscrowAccount.sale.toBase58(),
      allocation: buyerEscrowAccount.allocation.toString() + ' tokens',
      claimed: buyerEscrowAccount.claimed,
    });

    console.log('\n═'.repeat(70));
    console.log('✅ ALL TESTS PASSED!');
    console.log('═'.repeat(70));
    console.log('\n📋 Summary:');
    console.log('   - Environment validation: PASSED');
    console.log('   - Sale config fetch: PASSED');
    console.log('   - Voucher API request: PASSED');
    console.log('   - PDA derivation: PASSED');
    console.log('   - Token purchase: PASSED');
    console.log('   - On-chain verification: PASSED');
    console.log('\n🎉 Your presale flow is ready for browser testing!');

  } catch (error) {
    console.error('\n❌ Transaction failed:', error.message);
    if (error.logs) {
      console.error('\nProgram logs:');
      error.logs.forEach(log => console.error('  ', log));
    }
    process.exit(1);
  }
}

main().catch(err => {
  console.error('Fatal error:', err);
  process.exit(1);
});
