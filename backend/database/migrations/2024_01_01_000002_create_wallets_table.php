<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('address')->unique();
            $table->string('public_key')->nullable();
            $table->string('currency', 10)->default('MYXN');
            $table->decimal('balance', 20, 9)->default(0);
            $table->decimal('pending_balance', 20, 9)->default(0);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'currency']);
            $table->index(['address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
