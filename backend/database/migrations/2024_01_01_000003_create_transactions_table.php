<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['deposit', 'withdrawal', 'transfer', 'payment', 'refund', 'fee']);
            $table->enum('direction', ['in', 'out']);
            $table->decimal('amount', 20, 9);
            $table->decimal('fee', 20, 9)->default(0);
            $table->string('currency', 10)->default('MYXN');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('reference')->nullable();
            $table->string('blockchain_tx')->nullable();
            $table->string('to_address')->nullable();
            $table->string('from_address')->nullable();
            $table->foreignId('related_transaction_id')->nullable()->constrained('transactions');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['wallet_id', 'status']);
            $table->index(['user_id', 'type']);
            $table->index(['blockchain_tx']);
            $table->index(['reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
