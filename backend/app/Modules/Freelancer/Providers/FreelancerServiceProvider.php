<?php

namespace App\Modules\Freelancer\Providers;

use Illuminate\Support\ServiceProvider;

class FreelancerServiceProvider extends ServiceProvider
{
    /**
     * Register module services.
     */
    public function register(): void
    {
        // TODO: Bind module interfaces to implementations
    }

    /**
     * Bootstrap module services.
     */
    public function boot(): void
    {
        // TODO: Register module routes, views, migrations
        // $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        // $this->loadMigrationsFrom(__DIR__ . '/../Migrations');
    }
}
