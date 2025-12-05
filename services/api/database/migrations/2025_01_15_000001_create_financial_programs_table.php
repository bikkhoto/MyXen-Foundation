<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('financial_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['staking', 'lending', 'savings', 'rewards'])->index();
            $table->text('description')->nullable();

            // APY and rates
            $table->decimal('apy_rate', 8, 4)->default(0); // Annual Percentage Yield
            $table->decimal('min_amount', 20, 9)->default(0); // Minimum participation amount (9 decimals for MYXN)
            $table->decimal('max_amount', 20, 9)->nullable(); // Maximum participation amount

            // Lock period configuration
            $table->integer('lock_period_days')->default(0); // 0 = flexible
            $table->integer('early_withdrawal_penalty')->default(0); // Percentage penalty

            // Program limits
            $table->decimal('total_pool_limit', 20, 9)->nullable(); // Maximum total pool size
            $table->decimal('current_pool_size', 20, 9)->default(0); // Current pool size
            $table->integer('max_participants')->nullable(); // Maximum number of participants
            $table->integer('current_participants')->default(0);

            // Reward distribution
            $table->enum('reward_frequency', ['daily', 'weekly', 'monthly', 'on_maturity'])->default('daily');
            $table->boolean('compound_enabled')->default(false);

            // Status and visibility
            $table->enum('status', ['active', 'paused', 'ended', 'coming_soon'])->default('coming_soon');
            $table->boolean('is_featured')->default(false);

            // Dates
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // Additional configuration
            $table->json('meta')->nullable(); // Extra configuration data

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['status', 'type']);
            $table->index('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_programs');
    }
};
