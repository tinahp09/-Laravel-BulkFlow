<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulkflow_import_runs', static function (Blueprint $table): void {
            $table->string('batch_id')->nullable()->index()->after('revision');
        });
    }

    public function down(): void
    {
        Schema::table('bulkflow_import_runs', static function (Blueprint $table): void {
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
