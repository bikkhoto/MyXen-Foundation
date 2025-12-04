<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Auth routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // Auth routes (protected)
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/profile', [AuthController::class, 'profile']);
    });

    // Wallet routes
    Route::prefix('wallet')->group(function () {
        Route::get('/', [WalletController::class, 'index']);
        Route::get('/{wallet}', [WalletController::class, 'show']);
        Route::post('/transfer', [WalletController::class, 'transfer']);
        Route::post('/withdraw', [WalletController::class, 'withdraw']);
        Route::get('/{wallet}/transactions', [WalletController::class, 'transactions']);
    });

    // Admin routes
    Route::middleware(['role:admin'])->prefix('admin')->group(function () {
        // TODO: Add admin endpoints
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Admin dashboard placeholder']);
        });
    });

    // Merchant routes
    Route::middleware(['role:merchant'])->prefix('merchant')->group(function () {
        // TODO: Add merchant endpoints
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Merchant dashboard placeholder']);
        });
    });

    // University routes
    Route::middleware(['role:university'])->prefix('university')->group(function () {
        // TODO: Add university endpoints
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'University dashboard placeholder']);
        });
    });
});
