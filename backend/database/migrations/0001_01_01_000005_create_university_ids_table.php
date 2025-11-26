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
        Schema::create('university_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('university_name');
            $table->string('student_id')->nullable();
            $table->string('faculty')->nullable();
            $table->string('department')->nullable();
            $table->year('enrollment_year')->nullable();
            $table->year('graduation_year')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected', 'expired'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->string('id_card_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'university_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('university_ids');
    }
};
