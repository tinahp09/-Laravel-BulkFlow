<?php

declare(strict_types=1);

namespace BulkFlow\Run;

use Illuminate\Database\Eloquent\Model;

final class ImportRun extends Model
{
    protected $table = 'bulkflow_import_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['definition' => 'array'];
    }
}
