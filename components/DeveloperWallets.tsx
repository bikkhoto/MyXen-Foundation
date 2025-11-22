'use client';

import { useEffect, useState } from 'react';
import { Connection, LAMPORTS_PER_SOL, PublicKey } from '@solana/web3.js';
import { RPC_ENDPOINT } from '@/lib/constants';

interface WalletInfo {
  name: string;
  address: string;
  balance: number | null;
  loading: boolean;
}

// Initial wallet configuration
const INITIAL_WALLETS: WalletInfo[] = [
  {
    name: 'Development Fund',
    address: 'DevWa11etAddressHere1111111111111111111',
    balance: null,
    loading: true,
  },
  {
    name: 'Community Treasury',
    address: 'ComTreasAddressHere2222222222222222222',
    balance: null,
    loading: true,
  },
];

export function DeveloperWallets() {
  const [wallets, setWallets] = useState<WalletInfo[]>(INITIAL_WALLETS);

  useEffect(() => {
    const fetchBalances = async () => {
      const connection = new Connection(RPC_ENDPOINT);
      
      const updatedWallets = await Promise.all(
        INITIAL_WALLETS.map(async (wallet) => {
          try {
            const publicKey = new PublicKey(wallet.address);
            const balance = await connection.getBalance(publicKey);
            return {
              ...wallet,
              balance: balance / LAMPORTS_PER_SOL,
              loading: false,
            };
          } catch (error) {
            console.error(`Error fetching balance for ${wallet.name}:`, error);
            return {
              ...wallet,
              balance: 0,
              loading: false,
            };
          }
        })
      );
      
      setWallets(updatedWallets);
    };

    fetchBalances();
  }, []);

  return (
    <section id="wallets" className="bg-gray-50 py-20 dark:bg-gray-900">
      <div className="container mx-auto px-4">
        <div className="mx-auto max-w-4xl">
          <div className="mb-12 text-center">
            <h2 className="mb-4 text-3xl font-bold md:text-4xl">
              Transparent Development
            </h2>
            <p className="text-gray-600 dark:text-gray-400">
              All developer wallets are publicly visible on-chain for complete transparency
            </p>
          </div>

          <div className="grid gap-6 md:grid-cols-2">
            {wallets.map((wallet) => (
              <div
                key={wallet.address}
                className="rounded-2xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-800 dark:bg-black"
              >
                <h3 className="mb-2 text-xl font-semibold">{wallet.name}</h3>
                
                <div className="mb-4">
                  <p className="text-sm text-gray-500 dark:text-gray-400">Address</p>
                  <p className="break-all font-mono text-xs text-gray-700 dark:text-gray-300">
                    {wallet.address}
                  </p>
                </div>

                <div className="flex items-center justify-between rounded-lg bg-gradient-to-r from-blue-50 to-purple-50 p-4 dark:from-blue-950/20 dark:to-purple-950/20">
                  <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Balance
                  </span>
                  {wallet.loading ? (
                    <span className="text-gray-500">Loading...</span>
                  ) : (
                    <span className="text-xl font-bold text-gray-900 dark:text-white">
                      {wallet.balance?.toFixed(4)} SOL
                    </span>
                  )}
                </div>

                <a
                  href={`https://explorer.solana.com/address/${wallet.address}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="mt-4 flex items-center justify-center gap-2 rounded-lg bg-gray-100 py-2 text-sm font-medium transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                >
                  View on Solana Explorer
                  <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                </a>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
