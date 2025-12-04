/**
 * TypeScript types for Anchor Presale Program
 */

import { PublicKey } from '@solana/web3.js';
import { BN } from '@coral-xyz/anchor';

/**
 * Sale configuration account structure
 */
export interface SaleConfig {
  owner: PublicKey;
  token_mint: PublicKey;
  treasury: PublicKey;
  price_lamports_per_token: BN;
  start_ts: BN;
  end_ts: BN;
  total_allocated: BN;
  sold: BN;
  bump: number;
}

/**
 * Buyer escrow account structure
 */
export interface BuyerEscrow {
  buyer: PublicKey;
  sale_config: PublicKey;
  allocation: BN;
  bump: number;
}

/**
 * Voucher structure from backend
 */
export interface Voucher {
  buyer: string;
  sale: string;
  max_allocation: number;
  nonce: number;
  expiry_ts: number;
}

/**
 * Voucher response from Laravel API
 */
export interface VoucherResponse {
  success: boolean;
  voucher: Voucher;
  signature: string;
  signer_pubkey: string;
}

/**
 * Voucher request payload
 */
export interface VoucherRequest {
  buyer_pubkey: string;
  sale_pubkey: string;
  max_allocation: number;
  expiry_ts: number;
}
