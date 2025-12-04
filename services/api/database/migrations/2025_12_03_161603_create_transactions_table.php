<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the transactions table for tracking all wallet transactions.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->unsignedBigInteger('counterparty_wallet_id')->nullable();
            $table->decimal('amount', 30, 9);
            $table->enum('type', ['debit', 'credit']);
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('external_tx')->nullable();
            $table->string('reference')->unique()->nullable();
            $table->string('memo')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            $table->foreign('counterparty_wallet_id')->references('id')->on('wallets')->onDelete('set null');

            // Indexes
            $table->index('wallet_id');
            $table->index('counterparty_wallet_id');
            $table->index('status');
            $table->index('type');
            $table->index('external_tx');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
