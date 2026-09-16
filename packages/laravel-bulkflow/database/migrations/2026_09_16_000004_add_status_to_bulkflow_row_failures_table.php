<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulkflow_row_failures', static function (Blueprint $table): void {
            $table->string('status')->default('pending')->index()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('bulkflow_row_failures', static function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
