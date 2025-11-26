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
        Schema::create('vaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name')->default('My Vault');
            $table->decimal('balance', 20, 9)->default(0);
            $table->decimal('myxn_balance', 20, 9)->default(0);
            $table->timestamp('lock_until')->nullable();
            $table->integer('auto_lock_days')->nullable();
            $table->decimal('interest_rate', 6, 4)->default(0);
            $table->enum('status', ['active', 'locked', 'closed'])->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vaults');
    }
};
