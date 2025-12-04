<?php

namespace App\Http\Controllers\Services\Sale\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * VoucherController
 *
 * Issues signed vouchers for the Solana presale program.
 * Vouchers are signed using ed25519 with a secure server-side keypair.
 *
 * @package App\Http\Controllers\Services\Sale\Controllers
 */
class VoucherController extends Controller
{
    /**
     * Issue a presale voucher for a whitelisted buyer
     *
     * POST /v1/sale/whitelist
     *
     * Request body:
     * {
     *   "buyer_pubkey": "Base58 Solana wallet address",
     *   "sale_pubkey": "Base58 sale config PDA address",
     *   "max_allocation": 10000,
     *   "expiry_ts": 1735689600 (Unix timestamp)
     * }
     *
     * Response:
     * {
     *   "success": true,
     *   "voucher": {
     *     "buyer": "...",
     *     "sale": "...",
     *     "max_allocation": 10000,
     *     "nonce": 12345,
     *     "expiry_ts": 1735689600
     *   },
     *   "signature": "base64-encoded-signature",
     *   "signer_pubkey": "base58-encoded-pubkey"
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function issueWhitelistVoucher(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'buyer_pubkey' => 'required|string|size:44', // Base58 Solana pubkey is 44 chars
            'sale_pubkey' => 'required|string|size:44',
            'max_allocation' => 'required|integer|min:1',
            'expiry_ts' => 'required|integer|min:' . time(),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // TODO: Add KYC verification middleware to ensure user is KYC-approved
        // This should check the user's KYC status before issuing voucher
        // Example: EnsureKycAndAuth middleware

        $user = Auth::user();
        $adminId = $user?->id;

        try {
            // Generate unique nonce (using microseconds for uniqueness)
            $nonce = (int) (microtime(true) * 1000000);

            // Load voucher signer keypair
            $voucherSignerKeypair = $this->loadVoucherSignerKeypair();
            $signerSecretKey = $voucherSignerKeypair['secret_key'];
            $signerPublicKey = $voucherSignerKeypair['public_key'];

            // Construct voucher data
            $voucherData = [
                'buyer' => $request->buyer_pubkey,
                'sale' => $request->sale_pubkey,
                'max_allocation' => $request->max_allocation,
                'nonce' => $nonce,
                'expiry_ts' => $request->expiry_ts,
            ];

            // Create message to sign
            // Format: buyer (32 bytes) + sale (32 bytes) + max_allocation (8 bytes LE) + nonce (8 bytes LE) + expiry_ts (8 bytes LE)
            $message = $this->serializeVoucherMessage($voucherData);

            // Sign the message using libsodium ed25519
            $signature = sodium_crypto_sign_detached($message, $signerSecretKey);
            $signatureBase64 = base64_encode($signature);

            // Store voucher issuance in database
            DB::table('sale_vouchers')->insert([
                'sale_pubkey' => $request->sale_pubkey,
                'buyer_pubkey' => $request->buyer_pubkey,
                'max_allocation' => $request->max_allocation,
                'nonce' => $nonce,
                'expiry_ts' => $request->expiry_ts,
                'signature' => $signatureBase64,
                'issued_by' => $adminId,
                'issued_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('Voucher issued', [
                'buyer' => $request->buyer_pubkey,
                'sale' => $request->sale_pubkey,
                'nonce' => $nonce,
                'issued_by' => $adminId,
            ]);

            return response()->json([
                'success' => true,
                'voucher' => $voucherData,
                'signature' => $signatureBase64,
                'signer_pubkey' => $this->base58Encode($signerPublicKey),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to issue voucher', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to issue voucher: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all vouchers issued to a specific buyer
     *
     * GET /v1/sale/vouchers/{buyer_pubkey}
     *
     * @param string $buyerPubkey
     * @return JsonResponse
     */
    public function getBuyerVouchers(string $buyerPubkey): JsonResponse
    {
        try {
            $vouchers = DB::table('sale_vouchers')
                ->where('buyer_pubkey', $buyerPubkey)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'vouchers' => $vouchers,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vouchers: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Load the voucher signer keypair from secure storage
     *
     * The keypair should be stored in JSON format:
     * [secret_key_byte_0, secret_key_byte_1, ..., secret_key_byte_63]
     *
     * Location: ~/.myxen/keys/voucher-signer.json or path from VOUCHER_SIGNER_KEYPATH env
     *
     * @return array{secret_key: string, public_key: string}
     * @throws \Exception
     */
    private function loadVoucherSignerKeypair(): array
    {
        $home = $_SERVER['HOME'] ?? $_ENV['HOME'] ?? posix_getpwuid(posix_getuid())['dir'] ?? '/home/bikkhoto';
        $keypairPath = env('VOUCHER_SIGNER_KEYPATH', $home . '/.myxen/keys/voucher-signer.json');

        if (!file_exists($keypairPath)) {
            throw new \Exception("Voucher signer keypair not found at: {$keypairPath}");
        }

        $keypairJson = file_get_contents($keypairPath);
        $keypairArray = json_decode($keypairJson, true);

        if (!is_array($keypairArray) || count($keypairArray) !== 64) {
            throw new \Exception("Invalid keypair format. Expected array of 64 bytes.");
        }

        // Convert array to binary string
        $keypairBinary = '';
        foreach ($keypairArray as $byte) {
            $keypairBinary .= chr($byte);
        }

        // Extract secret key (first 32 bytes) and public key (last 32 bytes)
        // Note: Solana/ed25519 keypair is 64 bytes total: 32-byte secret + 32-byte public
        $secretKey = substr($keypairBinary, 0, 64); // Full 64-byte secret key for libsodium
        $publicKey = substr($keypairBinary, 32, 32); // Public key portion

        return [
            'secret_key' => $secretKey,
            'public_key' => $publicKey,
        ];
    }

    /**
     * Serialize voucher data into the message format expected by the Solana program
     *
     * Format: buyer (32) + sale (32) + max_allocation (8 LE) + nonce (8 LE) + expiry_ts (8 LE)
     * Total: 88 bytes
     *
     * @param array $voucherData
     * @return string Binary message
     * @throws \Exception
     */
    private function serializeVoucherMessage(array $voucherData): string
    {
        // Decode base58 public keys to binary (32 bytes each)
        $buyerBytes = $this->base58Decode($voucherData['buyer']);
        $saleBytes = $this->base58Decode($voucherData['sale']);

        if (strlen($buyerBytes) !== 32 || strlen($saleBytes) !== 32) {
            throw new \Exception("Invalid public key length after decoding");
        }

        // Pack integers as little-endian 64-bit unsigned
        $maxAllocationBytes = pack('P', $voucherData['max_allocation']); // P = unsigned 64-bit LE
        $nonceBytes = pack('P', $voucherData['nonce']);
        $expiryTsBytes = pack('q', $voucherData['expiry_ts']); // q = signed 64-bit LE

        // Concatenate all parts
        $message = $buyerBytes . $saleBytes . $maxAllocationBytes . $nonceBytes . $expiryTsBytes;

        if (strlen($message) !== 88) {
            throw new \Exception("Invalid message length: " . strlen($message) . " (expected 88 bytes)");
        }

        return $message;
    }

    /**
     * Decode base58 string to binary
     *
     * @param string $base58
     * @return string Binary representation
     */
    private function base58Decode(string $base58): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $base = strlen($alphabet);
        $decoded = gmp_init(0);

        for ($i = 0; $i < strlen($base58); $i++) {
            $char = $base58[$i];
            $value = strpos($alphabet, $char);
            if ($value === false) {
                throw new \Exception("Invalid base58 character: {$char}");
            }
            $decoded = gmp_add(gmp_mul($decoded, $base), $value);
        }

        // Convert to binary
        $hex = gmp_strval($decoded, 16);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        $binary = hex2bin($hex);

        // Pad to 32 bytes if needed
        while (strlen($binary) < 32) {
            $binary = "\x00" . $binary;
        }

        return $binary;
    }

    /**
     * Encode binary data to base58
     *
     * @param string $binary
     * @return string Base58 encoded string
     */
    private function base58Encode(string $binary): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $base = strlen($alphabet);

        $num = gmp_init(bin2hex($binary), 16);
        $encoded = '';

        while (gmp_cmp($num, 0) > 0) {
            $remainder = gmp_intval(gmp_mod($num, $base));
            $encoded = $alphabet[$remainder] . $encoded;
            $num = gmp_div($num, $base);
        }

        // Add leading zeros
        for ($i = 0; $i < strlen($binary) && $binary[$i] === "\x00"; $i++) {
            $encoded = '1' . $encoded;
        }

        return $encoded;
    }
}

