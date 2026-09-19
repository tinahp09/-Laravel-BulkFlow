<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class
{
    public function up(): void
    {
        Schema::create('bulkflow_import_mapping_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('profile_key')->index();
            $table->string('owner_id')->index();
            $table->string('name');
            $table->json('mapping');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulkflow_import_mapping_templates');
    }
};
