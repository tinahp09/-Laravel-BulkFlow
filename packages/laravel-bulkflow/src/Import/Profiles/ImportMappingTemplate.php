<?php

declare(strict_types=1);

namespace BulkFlow\Import\Profiles;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ImportMappingTemplate extends Model
{
    use HasUuids;

    protected $table = 'bulkflow_import_mapping_templates';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['mapping' => 'array'];
    }
}
