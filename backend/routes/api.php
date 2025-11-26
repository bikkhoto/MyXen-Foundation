<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\UniversityController;
use App\Http\Controllers\Api\VaultController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Public KYC requirements
Route::get('/kyc/requirements', [KycController::class, 'requirements']);

// Public vault interest rates
Route::get('/vault/interest-rates', [VaultController::class, 'interestRates']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });

    // Wallet
    Route::prefix('wallet')->group(function () {
        Route::get('/', [WalletController::class, 'show']);
        Route::get('/balance', [WalletController::class, 'balance']);
        Route::post('/link-solana', [WalletController::class, 'linkSolana']);
        Route::post('/transfer', [WalletController::class, 'transfer']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
    });

    // Transactions
    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::get('/stats', [TransactionController::class, 'stats']);
        Route::get('/reference/{reference}', [TransactionController::class, 'showByReference']);
        Route::get('/{id}', [TransactionController::class, 'show']);
    });

    // Merchants
    Route::prefix('merchants')->group(function () {
        Route::post('/register', [MerchantController::class, 'register']);
        Route::get('/profile', [MerchantController::class, 'profile']);
        Route::put('/profile', [MerchantController::class, 'updateProfile']);
        Route::get('/qr-code', [MerchantController::class, 'getQrCode']);
        Route::post('/qr-code/regenerate', [MerchantController::class, 'regenerateQrCode']);
        Route::get('/transactions', [MerchantController::class, 'transactions']);
        Route::get('/lookup/{qr_code}', [MerchantController::class, 'lookup']);
        Route::post('/pay/{qr_code}', [MerchantController::class, 'pay']);
    });

    // KYC
    Route::prefix('kyc')->group(function () {
        Route::get('/status', [KycController::class, 'status']);
        Route::get('/documents', [KycController::class, 'documents']);
        Route::post('/documents', [KycController::class, 'submitDocument']);
        Route::get('/documents/{id}', [KycController::class, 'showDocument']);
        Route::delete('/documents/{id}', [KycController::class, 'deleteDocument']);
    });

    // University ID
    Route::prefix('university')->group(function () {
        Route::get('/id', [UniversityController::class, 'show']);
        Route::post('/id', [UniversityController::class, 'register']);
        Route::put('/id', [UniversityController::class, 'update']);
        Route::get('/verify/{student_id}', [UniversityController::class, 'verify']);
        Route::get('/benefits', [UniversityController::class, 'benefits']);
    });

    // Vault
    Route::prefix('vault')->group(function () {
        Route::get('/', [VaultController::class, 'show']);
        Route::post('/deposit', [VaultController::class, 'deposit']);
        Route::post('/withdraw', [VaultController::class, 'withdraw']);
        Route::post('/lock', [VaultController::class, 'lock']);
        Route::put('/settings', [VaultController::class, 'updateSettings']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/{id}', [NotificationController::class, 'show']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/', [NotificationController::class, 'destroyAll']);
    });

    // Admin routes
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        
        // Users
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{id}', [AdminController::class, 'showUser']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        
        // Merchants
        Route::get('/merchants', [AdminController::class, 'merchants']);
        Route::put('/merchants/{id}', [AdminController::class, 'updateMerchant']);
        
        // Transactions
        Route::get('/transactions', [AdminController::class, 'transactions']);
        
        // KYC
        Route::get('/kyc/pending', [AdminController::class, 'pendingKyc']);
        Route::post('/kyc/{id}/approve', [AdminController::class, 'approveKyc']);
        Route::post('/kyc/{id}/reject', [AdminController::class, 'rejectKyc']);
        
        // University
        Route::get('/university/pending', [AdminController::class, 'pendingUniversityIds']);
        Route::post('/university/{id}/verify', [AdminController::class, 'verifyUniversityId']);
    });
});
