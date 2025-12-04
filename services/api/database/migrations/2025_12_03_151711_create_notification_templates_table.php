<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the notification_templates table for storing reusable templates
     * that can be used to generate notification content dynamically.
     */
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('event_type');
            $table->enum('channel', ['email', 'sms', 'telegram', 'push'])->default('email');
            $table->string('subject_template')->nullable();
            $table->text('body_template');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Note: created_by can reference either users or admins table
            // We're not adding a foreign key constraint to allow flexibility

            // Indexes for quick lookups
            $table->index('event_type');
            $table->index('channel');
            $table->index('is_active');
            $table->index(['event_type', 'channel', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
