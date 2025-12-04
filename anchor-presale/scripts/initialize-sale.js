/**
 * Initialize Sale Script
 * 
 * This script initializes a new token sale on the Anchor program.
 * It creates the sale configuration with pricing, timing, and allocation parameters.
 * 
 * Usage:
 *   node scripts/initialize-sale.js
 * 
 * Or with custom parameters:
 *   node scripts/initialize-sale.js --price 0.1 --start-days 0 --end-days 30 --allocation 1000000
 */

const anchor = require('@coral-xyz/anchor');
const { PublicKey, Keypair, SystemProgram, Connection, clusterApiUrl } = require('@solana/web3.js');
const fs = require('fs');
const path = require('path');
const os = require('os');

// Load the IDL
const idl = JSON.parse(
  fs.readFileSync(path.join(__dirname, '../target/idl/anchor_presale.json'), 'utf8')
);

const PROGRAM_ID = new PublicKey(idl.address || idl.metadata.address);

// Parse command line arguments
function parseArgs() {
  const args = process.argv.slice(2);
  const config = {
    priceSolPerToken: 0.1, // Default: 0.1 SOL per token
    startDaysFromNow: 0, // Default: starts immediately
    endDaysFromNow: 30, // Default: ends in 30 days
    allocationTokens: 1_000_000, // Default: 1M tokens
    tokenMint: null, // Will use default if not provided
    treasury: null, // Will use wallet if not provided
  };

  for (let i = 0; i < args.length; i++) {
    if (args[i] === '--price' && args[i + 1]) {
      config.priceSolPerToken = parseFloat(args[i + 1]);
      i++;
    } else if (args[i] === '--start-days' && args[i + 1]) {
      config.startDaysFromNow = parseInt(args[i + 1]);
      i++;
    } else if (args[i] === '--end-days' && args[i + 1]) {
      config.endDaysFromNow = parseInt(args[i + 1]);
      i++;
    } else if (args[i] === '--allocation' && args[i + 1]) {
      config.allocationTokens = parseInt(args[i + 1]);
      i++;
    } else if (args[i] === '--token-mint' && args[i + 1]) {
      config.tokenMint = new PublicKey(args[i + 1]);
      i++;
    } else if (args[i] === '--treasury' && args[i + 1]) {
      config.treasury = new PublicKey(args[i + 1]);
      i++;
    }
  }

  return config;
}

async function main() {
  console.log('🚀 Initializing Token Sale\n');
  console.log('═'.repeat(60));

  // Parse configuration
  const config = parseArgs();

  console.log('Configuration:');
  console.log(`  Price: ${config.priceSolPerToken} SOL per token`);
  console.log(`  Start: ${config.startDaysFromNow} days from now`);
  console.log(`  End: ${config.endDaysFromNow} days from now`);
  console.log(`  Allocation: ${config.allocationTokens.toLocaleString()} tokens`);
  if (config.tokenMint) {
    console.log(`  Token Mint: ${config.tokenMint.toBase58()}`);
  }
  if (config.treasury) {
    console.log(`  Treasury: ${config.treasury.toBase58()}`);
  }
  console.log('═'.repeat(60));
  console.log('');

  // Setup connection
  const connection = new Connection(clusterApiUrl('devnet'), 'confirmed');
  console.log('✅ Connected to Devnet\n');

  // Load wallet
  const walletPath = path.join(os.homedir(), '.config', 'solana', 'id.json');
  if (!fs.existsSync(walletPath)) {
    console.error('❌ Wallet not found at:', walletPath);
    console.error('Please generate a wallet with: solana-keygen new');
    process.exit(1);
  }

  const secretKey = Uint8Array.from(JSON.parse(fs.readFileSync(walletPath, 'utf8')));
  const keypair = Keypair.fromSecretKey(secretKey);
  const wallet = new anchor.Wallet(keypair);

  console.log('✅ Loaded wallet:', wallet.publicKey.toBase58());

  // Check balance
  const balance = await connection.getBalance(wallet.publicKey);
  console.log(`   Balance: ${(balance / 1e9).toFixed(4)} SOL`);

  if (balance < 0.1 * 1e9) {
    console.error('❌ Insufficient balance. Please airdrop some SOL:');
    console.error(`  solana airdrop 1 ${wallet.publicKey.toBase58()} --url devnet`);
    process.exit(1);
  }
  console.log('');

  // Initialize provider
  const provider = new anchor.AnchorProvider(connection, wallet, {
    commitment: 'confirmed',
  });
  anchor.setProvider(provider);

  // Initialize program
  const program = new anchor.Program(idl, provider);

  // Convert price: SOL per token -> lamports per token (assuming 9 decimals)
  const priceLamportsPerToken = new anchor.BN(config.priceSolPerToken * 1e9);

  // Calculate timestamps
  const now = Math.floor(Date.now() / 1000);
  const startTs = new anchor.BN(now + config.startDaysFromNow * 24 * 60 * 60);
  const endTs = new anchor.BN(now + config.endDaysFromNow * 24 * 60 * 60);

  // Set defaults
  const tokenMint = config.tokenMint || new PublicKey('So11111111111111111111111111111111111111112'); // Default to native SOL mint
  const treasury = config.treasury || wallet.publicKey;

  // Derive the sale config PDA (must include owner's public key)
  const [saleConfig] = PublicKey.findProgramAddressSync(
    [Buffer.from('sale_config'), wallet.publicKey.toBuffer()],
    program.programId
  );

  console.log('📝 Sale Config PDA:', saleConfig.toBase58());
  console.log('   Token Mint:', tokenMint.toBase58());
  console.log('   Treasury:', treasury.toBase58());
  console.log('   Owner:', wallet.publicKey.toBase58());
  console.log('');

  console.log('💰 Price:', priceLamportsPerToken.toString(), 'lamports per token');
  console.log('📅 Start Time:', new Date(startTs.toNumber() * 1000).toISOString());
  console.log('📅 End Time:', new Date(endTs.toNumber() * 1000).toISOString());
  console.log('🎯 Total Allocation:', config.allocationTokens.toLocaleString(), 'tokens');
  console.log('');

  // Call initialize_sale
  try {
    console.log('🔄 Sending transaction...');

    const tx = await program.methods
      .initializeSale(
        priceLamportsPerToken,
        startTs,
        endTs,
        new anchor.BN(config.allocationTokens * 1e9) // Convert to smallest units (9 decimals)
      )
      .accounts({
        saleConfig: saleConfig,
        owner: wallet.publicKey,
        tokenMint: tokenMint,
        treasury: treasury,
        systemProgram: SystemProgram.programId,
      })
      .rpc();

    console.log('✅ Transaction successful!');
    console.log('   Signature:', tx);
    console.log('');

    // Wait for confirmation
    console.log('⏳ Waiting for confirmation...');
    await connection.confirmTransaction(tx, 'confirmed');
    console.log('✅ Transaction confirmed!');
    console.log('');

    // Fetch and display the created sale config
    const saleConfigAccount = await program.account.saleConfig.fetch(saleConfig);
    console.log('📊 Sale Config Account:');
    console.log('   Owner:', saleConfigAccount.owner.toBase58());
    console.log('   Token Mint:', saleConfigAccount.token_mint.toBase58());
    console.log('   Treasury:', saleConfigAccount.treasury.toBase58());
    console.log('   Price:', saleConfigAccount.price_lamports_per_token.toString(), 'lamports per token');
    console.log('   Start Time:', new Date(saleConfigAccount.start_ts.toNumber() * 1000).toISOString());
    console.log('   End Time:', new Date(saleConfigAccount.end_ts.toNumber() * 1000).toISOString());
    console.log('   Total Allocation:', (saleConfigAccount.total_allocated.toNumber() / 1e9).toLocaleString(), 'tokens');
    console.log('   Sold Amount:', (saleConfigAccount.sold.toNumber() / 1e9).toLocaleString(), 'tokens');
    console.log('');

    // Save config to file
    const outputPath = path.join(__dirname, '../sale-config.json');
    const outputData = {
      saleConfigAddress: saleConfig.toBase58(),
      programId: program.programId.toBase58(),
      owner: wallet.publicKey.toBase58(),
      tokenMint: tokenMint.toBase58(),
      treasury: treasury.toBase58(),
      priceSolPerToken: config.priceSolPerToken,
      startTime: new Date(saleConfigAccount.start_ts.toNumber() * 1000).toISOString(),
      endTime: new Date(saleConfigAccount.end_ts.toNumber() * 1000).toISOString(),
      totalAllocation: config.allocationTokens,
      transactionSignature: tx,
      network: 'devnet',
    };

    fs.writeFileSync(outputPath, JSON.stringify(outputData, null, 2));
    console.log('💾 Configuration saved to:', outputPath);
    console.log('');

    console.log('═'.repeat(60));
    console.log('🎉 Sale initialization complete!');
    console.log('');
    console.log('📋 Next steps:');
    console.log('1. Copy the Sale Config Address above');
    console.log('2. Open the React app at http://localhost:3001');
    console.log('3. Enter the Sale Config Address in the app');
    console.log('4. Test the purchase flow with Phantom wallet');
    console.log('═'.repeat(60));

  } catch (error) {
    console.error('❌ Transaction failed:', error);
    if (error.logs) {
      console.error('Program logs:', error.logs);
    }
    process.exit(1);
  }
}

main().catch((err) => {
  console.error('Fatal error:', err);
  process.exit(1);
});
