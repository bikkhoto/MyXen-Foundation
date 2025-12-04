/**
 * BuyTokens Component
 * 
 * React component for purchasing tokens in the Solana presale.
 * Integrates with Phantom wallet and Laravel backend for voucher-based purchases.
 * 
 * Flow:
 * 1. Connect Phantom wallet
 * 2. Request voucher from Laravel backend
 * 3. Call Anchor program buy_with_voucher instruction
 * 4. Display transaction signature on success
 */

import React, { useState, useMemo, useCallback } from 'react';
import { useConnection, useWallet } from '@solana/wallet-adapter-react';
import { WalletMultiButton } from '@solana/wallet-adapter-react-ui';
import { Program, AnchorProvider, web3, BN, utils } from '@coral-xyz/anchor';
import { PublicKey, SystemProgram, Transaction } from '@solana/web3.js';

// Import your IDL (generated from anchor build)
import idl from '../target/idl/anchor_presale.json';

// Program ID (update after deployment)
const PROGRAM_ID = new PublicKey(idl.metadata.address);

// Laravel backend URL
const BACKEND_URL = process.env.REACT_APP_BACKEND_URL || 'http://localhost:8000';

interface Voucher {
  buyer: string;
  sale: string;
  max_allocation: number;
  nonce: number;
  expiry_ts: number;
}

interface VoucherResponse {
  success: boolean;
  voucher: Voucher;
  signature: string;
  signer_pubkey: string;
}

export default function BuyTokens() {
  const { connection } = useConnection();
  const wallet = useWallet();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [amount, setAmount] = useState<number>(1000);
  const [salePubkey, setSalePubkey] = useState<string>('');

  // Initialize Anchor provider and program
  const provider = useMemo(() => {
    if (!wallet.publicKey || !wallet.signTransaction) return null;

    return new AnchorProvider(
      connection,
      wallet as any,
      { commitment: 'confirmed' }
    );
  }, [connection, wallet]);

  const program = useMemo(() => {
    if (!provider) return null;
    return new Program(idl as any, PROGRAM_ID, provider);
  }, [provider]);

  /**
   * Request voucher from Laravel backend
   */
  const requestVoucher = async (buyerPubkey: string, salePubkey: string, maxAllocation: number): Promise<VoucherResponse> => {
    const response = await fetch(`${BACKEND_URL}/api/v1/sale/whitelist`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        // TODO: Add authentication header (e.g., Bearer token)
        // 'Authorization': `Bearer ${authToken}`,
      },
      body: JSON.stringify({
        buyer_pubkey: buyerPubkey,
        sale_pubkey: salePubkey,
        max_allocation: maxAllocation,
        expiry_ts: Math.floor(Date.now() / 1000) + 3600, // 1 hour expiry
      }),
    });

    if (!response.ok) {
      const errorData = await response.json();
      throw new Error(errorData.message || 'Failed to request voucher');
    }

    return await response.json();
  };

  /**
   * Purchase tokens using voucher
   */
  const handlePurchase = useCallback(async () => {
    if (!wallet.publicKey || !program) {
      setError('Please connect your wallet');
      return;
    }

    if (!salePubkey) {
      setError('Please enter sale public key');
      return;
    }

    try {
      setLoading(true);
      setError(null);
      setSuccess(null);

      // Request voucher from backend
      console.log('Requesting voucher from backend...');
      const voucherResponse = await requestVoucher(
        wallet.publicKey.toString(),
        salePubkey,
        amount
      );

      console.log('Voucher received:', voucherResponse);

      // Parse voucher data
      const { voucher, signature: voucherSig, signer_pubkey } = voucherResponse;

      // Derive PDAs
      const [saleConfigPDA] = await PublicKey.findProgramAddress(
        [
          Buffer.from('sale_config'),
          new PublicKey(salePubkey).toBuffer(),
        ],
        program.programId
      );

      const [buyerEscrowPDA] = await PublicKey.findProgramAddress(
        [
          Buffer.from('buyer_escrow'),
          saleConfigPDA.toBuffer(),
          wallet.publicKey.toBuffer(),
        ],
        program.programId
      );

      // Fetch sale config to get treasury
      const saleConfig = await program.account.saleConfig.fetch(saleConfigPDA);
      const treasury = saleConfig.treasury as PublicKey;

      // Convert signature from base64 to bytes
      const signatureBytes = Buffer.from(voucherSig, 'base64');
      if (signatureBytes.length !== 64) {
        throw new Error('Invalid signature length');
      }

      // Convert signer pubkey from base58 to PublicKey
      const signerPubkey = new PublicKey(signer_pubkey);

      console.log('Calling buy_with_voucher instruction...');

      // Call buy_with_voucher instruction
      const tx = await program.methods
        .buyWithVoucher(
          new BN(voucher.max_allocation),
          new BN(voucher.nonce),
          new BN(voucher.expiry_ts),
          Array.from(signatureBytes),
          new BN(amount) // lamports to spend
        )
        .accounts({
          saleConfig: saleConfigPDA,
          buyerEscrow: buyerEscrowPDA,
          buyer: wallet.publicKey,
          treasury,
          voucherSigner: signerPubkey,
          systemProgram: SystemProgram.programId,
        })
        .rpc();

      console.log('Transaction signature:', tx);
      setSuccess(`Tokens purchased successfully! Transaction: ${tx}`);
    } catch (err: any) {
      console.error('Purchase error:', err);
      setError(err.message || 'Failed to purchase tokens');
    } finally {
      setLoading(false);
    }
  }, [wallet, program, salePubkey, amount]);

  return (
    <div className="buy-tokens-container" style={styles.container}>
      <h1 style={styles.title}>Presale Token Purchase</h1>

      {/* Wallet Connect Button */}
      <div style={styles.walletSection}>
        <WalletMultiButton />
      </div>

      {/* Purchase Form */}
      {wallet.publicKey && (
        <div style={styles.form}>
          <div style={styles.inputGroup}>
            <label style={styles.label}>Sale Public Key</label>
            <input
              type="text"
              value={salePubkey}
              onChange={(e) => setSalePubkey(e.target.value)}
              placeholder="Enter sale config public key"
              style={styles.input}
            />
          </div>

          <div style={styles.inputGroup}>
            <label style={styles.label}>Amount (tokens)</label>
            <input
              type="number"
              value={amount}
              onChange={(e) => setAmount(Number(e.target.value))}
              min="1"
              style={styles.input}
            />
          </div>

          <button
            onClick={handlePurchase}
            disabled={loading || !salePubkey}
            style={loading ? { ...styles.button, ...styles.buttonDisabled } : styles.button}
          >
            {loading ? 'Processing...' : 'Purchase Tokens'}
          </button>
        </div>
      )}

      {/* Status Messages */}
      {error && (
        <div style={styles.errorBox}>
          <strong>Error:</strong> {error}
        </div>
      )}

      {success && (
        <div style={styles.successBox}>
          <strong>Success!</strong> {success}
        </div>
      )}

      {/* Instructions */}
      <div style={styles.instructions}>
        <h3>How to Purchase:</h3>
        <ol>
          <li>Connect your Phantom wallet</li>
          <li>Enter the sale configuration public key</li>
          <li>Enter the amount of tokens you want to purchase</li>
          <li>Click "Purchase Tokens" to request a voucher and complete the transaction</li>
        </ol>
        <p><strong>Note:</strong> You must be whitelisted to purchase tokens. The backend will verify your eligibility.</p>
      </div>
    </div>
  );
}

// Basic styles (can be replaced with CSS-in-JS or Tailwind)
const styles = {
  container: {
    maxWidth: '600px',
    margin: '50px auto',
    padding: '30px',
    backgroundColor: '#f9f9f9',
    borderRadius: '10px',
    boxShadow: '0 4px 6px rgba(0, 0, 0, 0.1)',
  },
  title: {
    textAlign: 'center' as const,
    color: '#333',
    marginBottom: '30px',
  },
  walletSection: {
    display: 'flex',
    justifyContent: 'center',
    marginBottom: '30px',
  },
  form: {
    marginBottom: '20px',
  },
  inputGroup: {
    marginBottom: '20px',
  },
  label: {
    display: 'block',
    fontWeight: 'bold',
    marginBottom: '8px',
    color: '#555',
  },
  input: {
    width: '100%',
    padding: '12px',
    fontSize: '16px',
    border: '1px solid #ddd',
    borderRadius: '6px',
    boxSizing: 'border-box' as const,
  },
  button: {
    width: '100%',
    padding: '14px',
    fontSize: '18px',
    fontWeight: 'bold',
    color: 'white',
    backgroundColor: '#512da8',
    border: 'none',
    borderRadius: '6px',
    cursor: 'pointer',
    transition: 'background-color 0.3s',
  },
  buttonDisabled: {
    backgroundColor: '#9e9e9e',
    cursor: 'not-allowed',
  },
  errorBox: {
    padding: '15px',
    backgroundColor: '#ffebee',
    color: '#c62828',
    borderRadius: '6px',
    marginBottom: '20px',
    border: '1px solid #ef5350',
  },
  successBox: {
    padding: '15px',
    backgroundColor: '#e8f5e9',
    color: '#2e7d32',
    borderRadius: '6px',
    marginBottom: '20px',
    border: '1px solid #66bb6a',
  },
  instructions: {
    marginTop: '30px',
    padding: '20px',
    backgroundColor: '#e3f2fd',
    borderRadius: '6px',
    border: '1px solid #90caf9',
    color: '#1565c0',
  },
};
