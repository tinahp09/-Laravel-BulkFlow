<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bulkflow_import_runs', 'revision')) {
            return;
        }

        Schema::table('bulkflow_import_runs', static function (Blueprint $table): void {
            $table->unsignedBigInteger('revision')->default(0)->after('failed_rows');
        });
    }

    public function down(): void
    {
        // Retain revision during rollback because existing clients may depend on polling order.
    }
};
