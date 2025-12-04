<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the sale_vouchers table for tracking issued vouchers
     * for the Solana presale program.
     */
    public function up(): void
    {
        Schema::create('sale_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('sale_pubkey', 44); // Solana public key (base58)
            $table->string('buyer_pubkey', 44); // Buyer's wallet address
            $table->unsignedBigInteger('max_allocation'); // Maximum tokens buyer can purchase
            $table->unsignedBigInteger('nonce')->unique(); // Unique nonce for replay protection
            $table->bigInteger('expiry_ts'); // Unix timestamp when voucher expires
            $table->text('signature'); // Ed25519 signature (base64)
            $table->unsignedBigInteger('issued_by')->nullable(); // Admin ID who issued voucher
            $table->timestamp('issued_at')->useCurrent(); // When voucher was issued
            $table->timestamps();

            // Indexes for query performance
            $table->index('buyer_pubkey');
            $table->index('sale_pubkey');
            $table->index('expiry_ts');
            $table->index('nonce');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_vouchers');
    }
};
