<?php

use App\Http\Controllers\Services\Payments\Controllers\AdminPaymentsController;
use App\Http\Controllers\Services\Payments\Controllers\PaymentsController;
use App\Http\Controllers\Services\Sale\Controllers\VoucherController;
use App\Services\Admin\Controllers\AdminAuthController;
use App\Services\Admin\Controllers\AdminDashboardController;
use App\Services\Auth\Controllers\AuthController;
use App\Services\Notifications\Controllers\NotificationController;
use App\Services\Notifications\Controllers\TemplateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Auth Routes (v1)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/auth')->group(function () {
    // Public routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'profile']);
    });
});

/*
|--------------------------------------------------------------------------
| Payments Routes (v1)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/payments')->middleware('auth:sanctum')->group(function () {
    Route::post('/create-intent', [PaymentsController::class, 'createIntent']);
    Route::post('/execute', [PaymentsController::class, 'execute']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes (v1)
|--------------------------------------------------------------------------
*/

// Public admin auth routes
Route::prefix('v1/admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'loginAdmin']);
});

// Protected admin routes
Route::prefix('v1/admin')->middleware('auth:admin')->group(function () {
    // Admin authentication
    Route::post('/logout', [AdminAuthController::class, 'logoutAdmin']);
    Route::get('/me', [AdminAuthController::class, 'me']);

    // Admin registration (superadmin only)
    Route::post('/register', [AdminAuthController::class, 'registerAdmin'])
        ->middleware('admin.role:superadmin');

    // User management
    Route::get('/users', [AdminDashboardController::class, 'listUsers']);
    Route::get('/users/{id}', [AdminDashboardController::class, 'showUser']);

    // KYC management (admin and superadmin only)
    Route::prefix('kyc')->middleware('admin.role:admin,superadmin')->group(function () {
        Route::get('/pending', [AdminDashboardController::class, 'listPendingKyc']);
        Route::get('/{id}', [AdminDashboardController::class, 'showKycDocument']);
        Route::post('/{id}/approve', [AdminDashboardController::class, 'approveKyc']);
        Route::post('/{id}/reject', [AdminDashboardController::class, 'rejectKyc']);
    });

    // Payment logs
    Route::get('/payments/logs', [AdminDashboardController::class, 'listPaymentLogs']);

    // Dashboard statistics
    Route::get('/stats/summary', [AdminDashboardController::class, 'getDashboardStats']);

    // Admin payment management
    Route::prefix('payments')->group(function () {
        Route::get('/logs', [AdminPaymentsController::class, 'logs']);
        Route::post('/{id}/reconcile', [AdminPaymentsController::class, 'reconcile']);
        Route::post('/{id}/refund', [AdminPaymentsController::class, 'refund']);
    });

    // Notification management
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/{id}', [NotificationController::class, 'show']);
        Route::post('/{id}/resend', [NotificationController::class, 'resend']);
    });

    // Template management
    Route::prefix('templates')->group(function () {
        Route::get('/', [TemplateController::class, 'index']);
        Route::get('/{id}', [TemplateController::class, 'show']);
        Route::post('/', [TemplateController::class, 'store']);
        Route::put('/{id}', [TemplateController::class, 'update']);
        Route::delete('/{id}', [TemplateController::class, 'destroy']);
    });
});

/*
|--------------------------------------------------------------------------
| Notification Event Routes (v1)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/notifications')->group(function () {
    // Protected with API key for internal services
    Route::post('/events', [NotificationController::class, 'createEvent'])
        ->middleware('api.key');
});

/*
|--------------------------------------------------------------------------
| Sale/Voucher Routes (v1)
|--------------------------------------------------------------------------
| Routes for Solana presale voucher issuance and management
*/
Route::prefix('v1/sale')->group(function () {
    // Issue whitelist voucher (requires admin auth)
    // TODO: Add KYC verification middleware if needed: ->middleware('kyc.approved')
    Route::post('/whitelist', [VoucherController::class, 'issueWhitelistVoucher'])
        ->middleware('auth:admin');

    // TEST ENDPOINT: Issue voucher without auth (DEVELOPMENT ONLY - REMOVE IN PRODUCTION)
    Route::post('/test/whitelist', [VoucherController::class, 'issueWhitelistVoucher'])
        ->middleware('throttle:60,1');

    // Get vouchers for a specific buyer (public read)
    Route::get('/vouchers/{buyer_pubkey}', [VoucherController::class, 'getBuyerVouchers']);
});

/*
|--------------------------------------------------------------------------
| MYXN Token Routes (v1)
|--------------------------------------------------------------------------
| Routes for MYXN token operations and service wallets
*/
Route::prefix('v1/token')->group(function () {
    // Public token info
    Route::get('/info', [\App\Http\Controllers\Api\TokenController::class, 'info']);
    Route::get('/service-wallets', [\App\Http\Controllers\Api\TokenController::class, 'serviceWallets']);

    // Authenticated token operations
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/balance', [\App\Http\Controllers\Api\TokenController::class, 'balance']);
        Route::post('/transfer', [\App\Http\Controllers\Api\TokenController::class, 'transfer']);
        Route::post('/burn', [\App\Http\Controllers\Api\TokenController::class, 'burn']);
        Route::get('/transactions', [\App\Http\Controllers\Api\TokenController::class, 'transactions']);
        Route::get('/transactions/{id}', [\App\Http\Controllers\Api\TokenController::class, 'transactionDetails']);
    });
});

/*
|--------------------------------------------------------------------------
| Financial Programs Routes (v1)
|--------------------------------------------------------------------------
| Routes for MYXN financial programs (staking, lending, savings, rewards)
*/
Route::prefix('v1/programs')->group(function () {
    // Public program listings
    Route::get('/', [\App\Http\Controllers\Api\FinancialProgramController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\Api\FinancialProgramController::class, 'show']);

    // Authenticated program operations
    Route::middleware('auth:sanctum')->group(function () {
        // User participations
        Route::get('/my/participations', [\App\Http\Controllers\Api\FinancialProgramController::class, 'myParticipations']);
        Route::get('/my/participations/{id}', [\App\Http\Controllers\Api\FinancialProgramController::class, 'participationDetails']);

        // Program enrollment and management
        Route::post('/{id}/enroll', [\App\Http\Controllers\Api\FinancialProgramController::class, 'enroll']);
        Route::post('/participations/{id}/withdraw', [\App\Http\Controllers\Api\FinancialProgramController::class, 'withdraw']);
        Route::post('/participations/{id}/claim-rewards', [\App\Http\Controllers\Api\FinancialProgramController::class, 'claimRewards']);
    });
});
