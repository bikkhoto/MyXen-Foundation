<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Transaction;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Merchant",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="business_name", type="string", example="Coffee Shop"),
 *     @OA\Property(property="business_type", type="string", example="retail"),
 *     @OA\Property(property="qr_code", type="string", example="MYXEN-ABC123XYZ"),
 *     @OA\Property(property="wallet_address", type="string"),
 *     @OA\Property(property="status", type="string", enum={"pending", "active", "suspended", "closed"}),
 *     @OA\Property(property="commission_rate", type="number", format="float", example=0.5)
 * )
 */
class MerchantController extends Controller
{
    /**
     * @OA\Post(
     *     path="/merchants/register",
     *     tags={"Merchants"},
     *     summary="Register as merchant",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"business_name"},
     *             @OA\Property(property="business_name", type="string"),
     *             @OA\Property(property="business_type", type="string"),
     *             @OA\Property(property="business_registration", type="string"),
     *             @OA\Property(property="wallet_address", type="string"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Merchant registered")
     * )
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'business_type' => 'nullable|string|max:100',
            'business_registration' => 'nullable|string|max:100',
            'wallet_address' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        // Check if user already has merchant profile
        if ($user->merchant) {
            return $this->error('You already have a merchant profile', 400);
        }

        $merchant = Merchant::create([
            'user_id' => $user->id,
            'business_name' => $validated['business_name'],
            'business_type' => $validated['business_type'] ?? null,
            'business_registration' => $validated['business_registration'] ?? null,
            'wallet_address' => $validated['wallet_address'] ?? $user->wallet?->solana_address,
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
        ]);

        // Update user role
        $user->update(['role' => 'merchant']);

        return $this->success($merchant, 'Merchant registered successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/merchants/profile",
     *     tags={"Merchants"},
     *     summary="Get merchant profile",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Merchant profile retrieved")
     * )
     */
    public function profile(Request $request)
    {
        $merchant = $request->user()->merchant;

        if (!$merchant) {
            return $this->error('Merchant profile not found', 404);
        }

        return $this->success($merchant);
    }

    /**
     * @OA\Put(
     *     path="/merchants/profile",
     *     tags={"Merchants"},
     *     summary="Update merchant profile",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="business_name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="logo_url", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Profile updated")
     * )
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:500',
            'logo_url' => 'sometimes|nullable|url|max:500',
        ]);

        $merchant = $request->user()->merchant;

        if (!$merchant) {
            return $this->error('Merchant profile not found', 404);
        }

        $merchant->update($validated);

        return $this->success($merchant, 'Profile updated successfully');
    }

    /**
     * @OA\Get(
     *     path="/merchants/qr-code",
     *     tags={"Merchants"},
     *     summary="Get merchant QR code",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="QR code retrieved")
     * )
     */
    public function getQrCode(Request $request)
    {
        $merchant = $request->user()->merchant;

        if (!$merchant) {
            return $this->error('Merchant profile not found', 404);
        }

        return $this->success([
            'qr_code' => $merchant->qr_code,
            'business_name' => $merchant->business_name,
            'wallet_address' => $merchant->wallet_address,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/merchants/qr-code/regenerate",
     *     tags={"Merchants"},
     *     summary="Regenerate QR code",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="QR code regenerated")
     * )
     */
    public function regenerateQrCode(Request $request)
    {
        $merchant = $request->user()->merchant;

        if (!$merchant) {
            return $this->error('Merchant profile not found', 404);
        }

        $merchant->regenerateQrCode();

        return $this->success([
            'qr_code' => $merchant->qr_code,
        ], 'QR code regenerated successfully');
    }

    /**
     * @OA\Post(
     *     path="/merchants/pay/{qr_code}",
     *     tags={"Merchants"},
     *     summary="Pay to merchant via QR code",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="qr_code", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "currency"},
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="currency", type="string", enum={"SOL", "MYXN"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payment initiated")
     * )
     */
    public function pay(Request $request, $qr_code)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.000000001',
            'currency' => 'required|in:SOL,MYXN',
        ]);

        $merchant = Merchant::where('qr_code', $qr_code)->firstOrFail();

        if (!$merchant->isActive()) {
            return $this->error('Merchant is not active', 400);
        }

        $user = $request->user();
        $wallet = $user->wallet;

        if (!$wallet->hasSufficientBalance($validated['amount'], $validated['currency'])) {
            return $this->error('Insufficient balance', 400);
        }

        // Calculate fee
        $fee = $validated['amount'] * ($merchant->commission_rate / 100);

        // Create payment transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'payment',
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'fee' => $fee,
            'status' => 'pending',
            'merchant_id' => $merchant->id,
            'to_address' => $merchant->wallet_address,
            'from_address' => $wallet->solana_address,
            'description' => "Payment to {$merchant->business_name}",
        ]);

        // Deduct from wallet
        $wallet->withdraw($validated['amount'] + $fee, $validated['currency']);

        return $this->success([
            'transaction' => $transaction,
            'merchant' => [
                'business_name' => $merchant->business_name,
            ],
        ], 'Payment initiated');
    }

    /**
     * @OA\Get(
     *     path="/merchants/transactions",
     *     tags={"Merchants"},
     *     summary="Get merchant transactions",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Transactions retrieved")
     * )
     */
    public function transactions(Request $request)
    {
        $merchant = $request->user()->merchant;

        if (!$merchant) {
            return $this->error('Merchant profile not found', 404);
        }

        $transactions = Transaction::where('merchant_id', $merchant->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->paginated($transactions);
    }

    /**
     * @OA\Get(
     *     path="/merchants/lookup/{qr_code}",
     *     tags={"Merchants"},
     *     summary="Lookup merchant by QR code",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="qr_code", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Merchant found")
     * )
     */
    public function lookup($qr_code)
    {
        $merchant = Merchant::where('qr_code', $qr_code)
            ->where('status', 'active')
            ->first();

        if (!$merchant) {
            return $this->error('Merchant not found', 404);
        }

        return $this->success([
            'business_name' => $merchant->business_name,
            'business_type' => $merchant->business_type,
            'logo_url' => $merchant->logo_url,
            'wallet_address' => $merchant->wallet_address,
        ]);
    }
}
