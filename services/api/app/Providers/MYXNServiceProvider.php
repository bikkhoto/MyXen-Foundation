<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MYXN\TracingService;
use App\Services\MYXN\ServiceWalletManager;
use App\Services\MYXN\MYXNTokenService;
use App\Services\MYXN\FinancialProgramService;
use App\Services\Payments\SolanaWorkerClient;

class MYXNServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register TracingService as singleton
        $this->app->singleton(TracingService::class, function ($app) {
            return new TracingService();
        });

        // Register ServiceWalletManager as singleton
        $this->app->singleton(ServiceWalletManager::class, function ($app) {
            return new ServiceWalletManager(
                $app->make(TracingService::class)
            );
        });

        // Register MYXNTokenService as singleton
        $this->app->singleton(MYXNTokenService::class, function ($app) {
            return new MYXNTokenService(
                $app->make(SolanaWorkerClient::class),
                $app->make(TracingService::class),
                $app->make(ServiceWalletManager::class)
            );
        });

        // Register FinancialProgramService as singleton
        $this->app->singleton(FinancialProgramService::class, function ($app) {
            return new FinancialProgramService(
                $app->make(MYXNTokenService::class),
                $app->make(TracingService::class)
            );
        });

        // Register aliases for easier resolution
        $this->app->alias(TracingService::class, 'myxn.tracing');
        $this->app->alias(ServiceWalletManager::class, 'myxn.wallets');
        $this->app->alias(MYXNTokenService::class, 'myxn.token');
        $this->app->alias(FinancialProgramService::class, 'myxn.programs');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/myxn.php' => config_path('myxn.php'),
        ], 'myxn-config');

        // Initialize tracing if enabled
        if (config('myxn.tracing.enabled', false)) {
            $this->initializeTracing();
        }
    }

    /**
     * Initialize OpenTelemetry tracing
     */
    protected function initializeTracing(): void
    {
        // Register shutdown function to flush traces
        register_shutdown_function(function () {
            try {
                $tracing = app(TracingService::class);
                $tracing->flush();
            } catch (\Exception $e) {
                // Silently fail on shutdown
            }
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            TracingService::class,
            ServiceWalletManager::class,
            MYXNTokenService::class,
            FinancialProgramService::class,
            'myxn.tracing',
            'myxn.wallets',
            'myxn.token',
            'myxn.programs',
        ];
    }
}
