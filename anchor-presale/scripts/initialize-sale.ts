/**
 * Initialize Sale Script
 * 
 * This script initializes a new token sale on the Anchor program.
 * It creates the sale configuration with pricing, timing, and allocation parameters.
 * 
 * Usage:
 *   ts-node scripts/initialize-sale.ts
 * 
 * Or with custom parameters:
 *   ts-node scripts/initialize-sale.ts --price 0.1 --start-days 0 --end-days 30 --allocation 1000000
 */

import * as anchor from '@coral-xyz/anchor';
import { Program, AnchorProvider, Wallet, BN } from '@coral-xyz/anchor';
import { PublicKey, Keypair, SystemProgram, Connection, clusterApiUrl } from '@solana/web3.js';
import * as fs from 'fs';
import * as path from 'path';

// Load IDL
const idlPath = path.join(__dirname, '../target/idl/anchor_presale.json');
const idl = JSON.parse(fs.readFileSync(idlPath, 'utf-8'));

// Program ID
const PROGRAM_ID = new PublicKey(idl.address);

// Configuration (can be overridden by command line args)
interface SaleConfig {
  priceSolPerToken: number;      // Price in SOL per token (e.g., 0.1 SOL per token)
  startDaysFromNow: number;      // Days from now when sale starts (0 = now)
  endDaysFromNow: number;        // Days from now when sale ends (e.g., 30 days)
  totalAllocatedTokens: number;  // Total tokens allocated for sale (e.g., 1,000,000)
  tokenMint?: string;            // Optional: Token mint address (use a placeholder if tokens not minted yet)
  treasury?: string;             // Optional: Treasury wallet address (defaults to payer)
}

const DEFAULT_CONFIG: SaleConfig = {
  priceSolPerToken: 0.1,         // 0.1 SOL per token
  startDaysFromNow: 0,           // Start immediately
  endDaysFromNow: 30,            // End in 30 days
  totalAllocatedTokens: 1000000, // 1 million tokens
};

/**
 * Parse command line arguments
 */
function parseArgs(): SaleConfig {
  const args = process.argv.slice(2);
  const config = { ...DEFAULT_CONFIG };

  for (let i = 0; i < args.length; i += 2) {
    const key = args[i];
    const value = args[i + 1];

    switch (key) {
      case '--price':
        config.priceSolPerToken = parseFloat(value);
        break;
      case '--start-days':
        config.startDaysFromNow = parseInt(value);
        break;
      case '--end-days':
        config.endDaysFromNow = parseInt(value);
        break;
      case '--allocation':
        config.totalAllocatedTokens = parseInt(value);
        break;
      case '--token-mint':
        config.tokenMint = value;
        break;
      case '--treasury':
        config.treasury = value;
        break;
      case '--help':
        printHelp();
        process.exit(0);
    }
  }

  return config;
}

/**
 * Print help message
 */
function printHelp() {
  console.log(`
Initialize Sale Script

Usage:
  ts-node scripts/initialize-sale.ts [OPTIONS]

Options:
  --price <number>        Price in SOL per token (default: 0.1)
  --start-days <number>   Days from now when sale starts (default: 0)
  --end-days <number>     Days from now when sale ends (default: 30)
  --allocation <number>   Total tokens allocated for sale (default: 1000000)
  --token-mint <pubkey>   Token mint address (default: placeholder)
  --treasury <pubkey>     Treasury wallet address (default: payer wallet)
  --help                  Show this help message

Examples:
  # Initialize with defaults
  ts-node scripts/initialize-sale.ts

  # Custom pricing and duration
  ts-node scripts/initialize-sale.ts --price 0.05 --end-days 60

  # Full configuration
  ts-node scripts/initialize-sale.ts --price 0.2 --start-days 1 --end-days 45 --allocation 2000000
  `);
}

/**
 * Load wallet from filesystem
 */
function loadWallet(): Keypair {
  const walletPath = path.join(process.env.HOME!, '.config/solana/id.json');
  
  if (!fs.existsSync(walletPath)) {
    console.error('❌ Wallet not found at:', walletPath);
    console.error('Please create a Solana wallet first:');
    console.error('  solana-keygen new');
    process.exit(1);
  }

  const walletData = JSON.parse(fs.readFileSync(walletPath, 'utf-8'));
  return Keypair.fromSecretKey(new Uint8Array(walletData));
}

/**
 * Initialize the sale
 */
async function initializeSale() {
  console.log('🚀 Initializing Token Sale...\n');

  // Parse configuration
  const config = parseArgs();

  // Setup connection and wallet
  const connection = new Connection(
    process.env.ANCHOR_PROVIDER_URL || clusterApiUrl('devnet'),
    'confirmed'
  );
  const wallet = loadWallet();
  const provider = new AnchorProvider(connection, new Wallet(wallet), {
    commitment: 'confirmed',
  });

  console.log('📋 Configuration:');
  console.log('  Network:', provider.connection.rpcEndpoint);
  console.log('  Wallet:', wallet.publicKey.toBase58());
  console.log('  Program ID:', PROGRAM_ID.toBase58());
  console.log('');

  // Check wallet balance
  const balance = await connection.getBalance(wallet.publicKey);
  console.log('💰 Wallet Balance:', balance / 1e9, 'SOL');
  
  if (balance < 0.1 * 1e9) {
    console.error('❌ Insufficient balance. Please airdrop some SOL:');
    console.error(`  solana airdrop 1 ${wallet.publicKey.toBase58()} --url devnet`);
    process.exit(1);
  }
  console.log('');

  // Initialize program
  const program = new Program(idl, provider) as Program<typeof idl>;

  // Convert price: SOL per token -> lamports per token (assuming 9 decimals)
  // If token has 9 decimals and costs 0.1 SOL, that's 0.1 * 1e9 lamports per 1 * 1e9 smallest units
  // So price_lamports_per_token = 0.1 * 1e9 / 1e9 = 0.1 lamports per smallest unit
  // Actually, we want: lamports per full token (with decimals)
  const priceLamportsPerToken = new BN(config.priceSolPerToken * 1e9);

  // Calculate timestamps
  const now = Math.floor(Date.now() / 1000);
  const startTs = new BN(now + config.startDaysFromNow * 24 * 60 * 60);
  const endTs = new BN(now + config.endDaysFromNow * 24 * 60 * 60);

  // Total allocated (assuming 9 decimals, multiply by 1e9)
  const totalAllocated = new BN(config.totalAllocatedTokens).mul(new BN(1e9));

  // Token mint and treasury
  const tokenMint = config.tokenMint 
    ? new PublicKey(config.tokenMint)
    : Keypair.generate().publicKey; // Placeholder if not minted yet

  const treasury = config.treasury
    ? new PublicKey(config.treasury)
    : wallet.publicKey;

  // Derive sale config PDA
  const [saleConfigPDA, bump] = await PublicKey.findProgramAddress(
    [Buffer.from('sale_config'), wallet.publicKey.toBuffer()],
    program.programId
  );

  console.log('📊 Sale Parameters:');
  console.log('  Price:', config.priceSolPerToken, 'SOL per token');
  console.log('  Price (lamports):', priceLamportsPerToken.toString());
  console.log('  Start:', new Date(startTs.toNumber() * 1000).toLocaleString());
  console.log('  End:', new Date(endTs.toNumber() * 1000).toLocaleString());
  console.log('  Total Allocation:', config.totalAllocatedTokens.toLocaleString(), 'tokens');
  console.log('  Token Mint:', tokenMint.toBase58());
  console.log('  Treasury:', treasury.toBase58());
  console.log('  Sale Config PDA:', saleConfigPDA.toBase58());
  console.log('');

  try {
    console.log('⏳ Sending transaction...');

    // Call initialize_sale instruction
    const tx = await program.methods
      .initializeSale(
        priceLamportsPerToken,
        startTs,
        endTs,
        totalAllocated
      )
      .accounts({
        saleConfig: saleConfigPDA,
        tokenMint,
        treasury,
        owner: wallet.publicKey,
        systemProgram: SystemProgram.programId,
      })
      .rpc();

    console.log('✅ Sale initialized successfully!');
    console.log('');
    console.log('📝 Transaction Details:');
    console.log('  Signature:', tx);
    console.log('  Explorer:', `https://explorer.solana.com/tx/${tx}?cluster=devnet`);
    console.log('');
    console.log('🔑 Important Information:');
    console.log('  Sale Config Address:', saleConfigPDA.toBase58());
    console.log('  Token Mint:', tokenMint.toBase58());
    console.log('  Treasury:', treasury.toBase58());
    console.log('');
    console.log('💡 Next Steps:');
    console.log('  1. Update your React app with the Sale Config address');
    console.log('  2. Test the purchase flow at http://localhost:3001');
    console.log('  3. Ensure your Laravel backend is running on port 8000');
    console.log('');

    // Save config to file for reference
    const outputPath = path.join(__dirname, '../sale-config.json');
    fs.writeFileSync(outputPath, JSON.stringify({
      saleConfigAddress: saleConfigPDA.toBase58(),
      tokenMint: tokenMint.toBase58(),
      treasury: treasury.toBase58(),
      owner: wallet.publicKey.toBase58(),
      pricePerToken: config.priceSolPerToken,
      startTime: new Date(startTs.toNumber() * 1000).toISOString(),
      endTime: new Date(endTs.toNumber() * 1000).toISOString(),
      totalAllocation: config.totalAllocatedTokens,
      transactionSignature: tx,
      programId: PROGRAM_ID.toBase58(),
    }, null, 2));

    console.log('💾 Sale configuration saved to:', outputPath);

  } catch (error: any) {
    console.error('❌ Error initializing sale:');
    console.error(error);
    
    if (error.logs) {
      console.error('\n📋 Transaction Logs:');
      error.logs.forEach((log: string) => console.error('  ', log));
    }
    
    process.exit(1);
  }
}

// Run the script
initializeSale()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
