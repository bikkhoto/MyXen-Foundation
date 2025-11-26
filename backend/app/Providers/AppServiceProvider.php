<?php

namespace App\Providers;

use App\Services\Blockchain\SolanaRpcService;
use App\Services\Blockchain\SolanaServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Solana service to container
        $this->app->bind(SolanaServiceInterface::class, SolanaRpcService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
