<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulkflow_scheduled_exports', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('model_class');
            $table->json('columns');
            $table->string('format');
            $table->string('disk')->nullable();
            $table->string('path');
            $table->string('expression');
            $table->string('recipient')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulkflow_scheduled_exports');
    }
};
