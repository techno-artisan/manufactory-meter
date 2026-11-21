<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_requests', function (Blueprint $table) {
            $table->id();
            $table->dateTime('occurred_at', 3)->index();
            $table->string('model');
            $table->unsignedBigInteger('input_tokens');
            $table->unsignedBigInteger('output_tokens');
            $table->unsignedBigInteger('cache_read_tokens')->default(0);
            $table->unsignedBigInteger('cache_creation_tokens')->default(0);
            $table->decimal('cost_usd', 20, 10)->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->string('session_id')->nullable();
            $table->string('prompt_id')->nullable();
            $table->string('app_version')->nullable();
            $table->longText('raw');
            $table->char('fingerprint', 64)->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_requests');
    }
};
