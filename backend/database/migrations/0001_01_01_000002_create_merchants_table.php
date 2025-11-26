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
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('business_name');
            $table->string('business_type')->nullable();
            $table->string('business_registration')->nullable();
            $table->string('qr_code')->unique();
            $table->string('wallet_address')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'closed'])->default('pending');
            $table->decimal('commission_rate', 5, 2)->default(0.50);
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('qr_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
