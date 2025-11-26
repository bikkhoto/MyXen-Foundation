<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="MyXenPay API",
 *     version="1.0.0",
 *     description="MyXenPay Ecosystem Backend API - Merchant QR payments, wallet management, $MYXN token handling, Solana RPC integration, KYC, and more.",
 *     @OA\Contact(
 *         email="support@myxenpay.com",
 *         name="MyXenPay Support"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints for user authentication"
 * )
 * @OA\Tag(
 *     name="Wallet",
 *     description="API Endpoints for wallet management"
 * )
 * @OA\Tag(
 *     name="Transactions",
 *     description="API Endpoints for transactions"
 * )
 * @OA\Tag(
 *     name="Merchants",
 *     description="API Endpoints for merchant management"
 * )
 * @OA\Tag(
 *     name="KYC",
 *     description="API Endpoints for KYC verification"
 * )
 * @OA\Tag(
 *     name="University",
 *     description="API Endpoints for university ID system"
 * )
 * @OA\Tag(
 *     name="Vault",
 *     description="API Endpoints for vault/locker management"
 * )
 * @OA\Tag(
 *     name="Notifications",
 *     description="API Endpoints for notifications"
 * )
 * @OA\Tag(
 *     name="Admin",
 *     description="API Endpoints for admin panel"
 * )
 */
abstract class Controller
{
    /**
     * Success response helper.
     */
    protected function success($data = null, string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Error response helper.
     */
    protected function error(string $message = 'Error', int $code = 400, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    /**
     * Paginated response helper.
     */
    protected function paginated($paginator, string $message = 'Success')
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
