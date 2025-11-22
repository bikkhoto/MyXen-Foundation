'use client';

import { useEffect, useState } from 'react';
import { Connection } from '@solana/web3.js';
import Link from 'next/link';
import { RPC_ENDPOINT } from '@/lib/constants';

interface HealthStatus {
  rpc: string;
  connected: boolean;
  slot: number | null;
  blockTime: number | null;
  version: string | null;
  error: string | null;
}

export default function HealthPage() {
  const [status, setStatus] = useState<HealthStatus>({
    rpc: RPC_ENDPOINT,
    connected: false,
    slot: null,
    blockTime: null,
    version: null,
    error: null,
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    checkHealth();
  }, []);

  const checkHealth = async () => {
    setLoading(true);
    try {
      const connection = new Connection(RPC_ENDPOINT, 'confirmed');
      
      // Get current slot
      const slot = await connection.getSlot();
      
      // Get block time
      const blockTime = await connection.getBlockTime(slot);
      
      // Get version
      const version = await connection.getVersion();
      
      setStatus({
        rpc: RPC_ENDPOINT,
        connected: true,
        slot,
        blockTime,
        version: version['solana-core'],
        error: null,
      });
    } catch (error) {
      setStatus({
        rpc: RPC_ENDPOINT,
        connected: false,
        slot: null,
        blockTime: null,
        version: null,
        error: error instanceof Error ? error.message : 'Failed to connect to Solana RPC',
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-purple-900 via-blue-900 to-black text-white p-8">
      <div className="max-w-4xl mx-auto">
        <div className="flex items-center justify-between mb-8">
          <h1 className="text-4xl font-bold">DApp Health Status</h1>
          <Link 
            href="/"
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition"
          >
            ← Back to Home
          </Link>
        </div>

        <div className="bg-gray-800/50 backdrop-blur-sm rounded-xl p-8 border border-gray-700">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-2xl font-semibold">Solana Network Status</h2>
            <button
              onClick={checkHealth}
              disabled={loading}
              className="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 rounded-lg transition"
            >
              {loading ? 'Checking...' : 'Refresh'}
            </button>
          </div>

          <div className="space-y-4">
            <StatusRow 
              label="RPC Endpoint" 
              value={status.rpc}
              status={status.connected ? 'success' : 'error'}
            />
            
            <StatusRow 
              label="Connection Status" 
              value={status.connected ? 'Connected' : 'Disconnected'}
              status={status.connected ? 'success' : 'error'}
            />
            
            {status.connected ? (
              <>
                <StatusRow 
                  label="Current Slot" 
                  value={status.slot?.toLocaleString() || 'N/A'}
                  status="success"
                />
                
                <StatusRow 
                  label="Block Time" 
                  value={status.blockTime ? new Date(status.blockTime * 1000).toLocaleString() : 'N/A'}
                  status="success"
                />
                
                <StatusRow 
                  label="Solana Version" 
                  value={status.version || 'N/A'}
                  status="success"
                />
              </>
            ) : (
              <div className="bg-red-900/30 border border-red-500 rounded-lg p-4">
                <p className="text-red-300">
                  <strong>Error:</strong> {status.error || 'Unknown error'}
                </p>
              </div>
            )}
          </div>

          <div className="mt-8 pt-8 border-t border-gray-700">
            <h3 className="text-xl font-semibold mb-4">System Information</h3>
            <div className="space-y-2 text-sm text-gray-300">
              <p>• DApp Location: /dapp</p>
              <p>• Build Mode: Static Export (Next.js)</p>
              <p>• Deployment: cPanel public_html</p>
              <p>• Last Updated: {new Date().toLocaleString()}</p>
            </div>
          </div>

          <div className="mt-6 flex gap-4">
            <Link 
              href="/"
              className="px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg transition"
            >
              View DApp Home
            </Link>
            <a 
              href="http://myxenpay.finance"
              target="_blank"
              rel="noopener noreferrer"
              className="px-6 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg transition"
            >
              Visit Main Site →
            </a>
          </div>
        </div>
      </div>
    </div>
  );
}

function StatusRow({ label, value, status }: { label: string; value: string; status: 'success' | 'error' | 'warning' }) {
  const statusColors = {
    success: 'bg-green-500',
    error: 'bg-red-500',
    warning: 'bg-yellow-500',
  };

  return (
    <div className="flex items-center justify-between p-4 bg-gray-700/30 rounded-lg">
      <span className="font-medium">{label}</span>
      <div className="flex items-center gap-3">
        <span className="text-gray-300">{value}</span>
        <div className={`w-3 h-3 rounded-full ${statusColors[status]}`} />
      </div>
    </div>
  );
}
