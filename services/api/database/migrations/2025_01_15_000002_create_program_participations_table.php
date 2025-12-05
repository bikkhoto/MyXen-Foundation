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
        Schema::create('program_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('financial_program_id')->constrained()->onDelete('cascade');
            $table->foreignId('wallet_id')->nullable()->constrained()->onDelete('set null');

            // Participation details
            $table->decimal('amount', 20, 9); // Amount staked/deposited (9 decimals for MYXN)
            $table->decimal('initial_amount', 20, 9); // Original amount (before any rewards)

            // Status tracking
            $table->enum('status', [
                'pending',      // Waiting for blockchain confirmation
                'active',       // Currently participating
                'matured',      // Lock period completed
                'withdrawn',    // User has withdrawn
                'cancelled',    // Cancelled before activation
                'liquidated'    // Force-liquidated (lending)
            ])->default('pending')->index();

            // Rewards tracking
            $table->decimal('rewards_earned', 20, 9)->default(0);
            $table->decimal('rewards_claimed', 20, 9)->default(0);
            $table->decimal('pending_rewards', 20, 9)->default(0);
            $table->timestamp('last_reward_at')->nullable();

            // Dates
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('maturity_at')->nullable(); // When lock period ends
            $table->timestamp('withdrawn_at')->nullable();

            // Blockchain tracking
            $table->string('enrollment_tx_hash')->nullable();
            $table->string('withdrawal_tx_hash')->nullable();
            $table->string('solana_wallet_address')->nullable(); // User's Solana wallet

            // Tracing
            $table->string('trace_id')->nullable()->index();
            $table->string('span_id')->nullable();

            // Additional data
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['financial_program_id', 'status']);
            $table->unique(['user_id', 'financial_program_id', 'status'], 'unique_active_participation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_participations');
    }
};
