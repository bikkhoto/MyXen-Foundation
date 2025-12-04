/**
 * Polyfills for Node.js built-ins required by Solana libraries in the browser
 */

import { Buffer } from 'buffer';

// Make Buffer available globally
window.Buffer = Buffer;

// Add process.env if not available
if (typeof window.process === 'undefined') {
  (window as any).process = { env: {} };
}

export {};
