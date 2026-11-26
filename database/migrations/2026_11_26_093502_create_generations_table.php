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
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pixelforge_id')->nullable()->unique();
            $table->uuid('idempotency_key')->unique();
            $table->string('status')->default('queued')->index();
            $table->text('prompt');
            $table->string('size', 16)->default('1024x1024');
            $table->string('quality', 16)->default('medium');
            $table->json('reference_images')->nullable();
            $table->json('metadata')->nullable();
            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->decimal('estimated_cost', 16, 8)->default('0.00000000');
            $table->decimal('actual_cost', 16, 8)->nullable();
            $table->decimal('provider_cost', 16, 8)->nullable();
            $table->json('rates')->nullable();
            $table->json('usage')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
