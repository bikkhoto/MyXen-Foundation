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
        Schema::create('token_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('wallet_id')->nullable()->constrained()->onDelete('set null');

            // Transaction type
            $table->enum('type', [
                'transfer',
                'mint',
                'burn',
                'stake',
                'unstake',
                'reward',
                'platform_fee',
                'distribution',
                'lending_deposit',
                'lending_withdraw',
                'savings_deposit',
                'savings_withdraw'
            ])->index();

            // Transaction details
            $table->string('from_address')->nullable()->index();
            $table->string('to_address')->nullable()->index();
            $table->decimal('amount', 20, 9); // 9 decimals for MYXN
            $table->decimal('fee_amount', 20, 9)->default(0);

            // Service wallet tracking (for distributions)
            $table->enum('service_wallet', ['treasury', 'burn', 'charity', 'hr', 'marketing'])->nullable();

            // Status
            $table->enum('status', [
                'pending',
                'processing',
                'confirmed',
                'failed',
                'cancelled'
            ])->default('pending')->index();

            // Blockchain data
            $table->string('tx_hash')->nullable()->unique();
            $table->string('block_hash')->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->unsignedInteger('confirmations')->default(0);

            // Network info
            $table->string('network')->default('mainnet-beta'); // solana network
            $table->string('token_mint')->nullable();

            // Tracing
            $table->string('trace_id')->nullable()->index();
            $table->string('span_id')->nullable();
            $table->string('parent_span_id')->nullable();

            // Error handling
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);

            // Related entities
            $table->unsignedBigInteger('program_participation_id')->nullable();
            $table->string('reference_type')->nullable(); // polymorphic reference
            $table->unsignedBigInteger('reference_id')->nullable();

            // Additional data
            $table->json('meta')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'type', 'status']);
            $table->index(['created_at', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_transactions');
    }
};
