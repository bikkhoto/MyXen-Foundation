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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('solana_address')->nullable();
            $table->decimal('balance', 20, 9)->default(0);
            $table->decimal('myxn_balance', 20, 9)->default(0);
            $table->enum('status', ['active', 'frozen', 'closed'])->default('active');
            $table->timestamps();
            
            $table->index('solana_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
