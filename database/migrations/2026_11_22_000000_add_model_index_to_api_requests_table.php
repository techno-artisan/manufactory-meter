<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_requests', function (Blueprint $table) {
            $table->index('model');
        });
    }

    public function down(): void
    {
        Schema::table('api_requests', function (Blueprint $table) {
            $table->dropIndex(['model']);
        });
    }
};
