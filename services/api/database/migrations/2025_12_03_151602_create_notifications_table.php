<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the notifications table for storing all notification records
     * across multiple channels (email, SMS, Telegram, push).
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event_type');
            $table->enum('channel', ['email', 'sms', 'telegram', 'push'])->default('email');
            $table->string('to');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'queued', 'sent', 'failed'])->default('pending');
            $table->integer('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            // Note: created_by can reference either users or admins table
            // We're not adding a foreign key constraint to allow flexibility

            // Indexes for filtering and performance
            $table->index('event_type');
            $table->index('status');
            $table->index('channel');
            $table->index(['event_type', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
