<?php

declare(strict_types=1);

namespace BulkFlow\Schedule;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ScheduledExport extends Model
{
    use HasUuids;

    protected $table = 'bulkflow_scheduled_exports';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'active' => 'boolean',
        ];
    }
}
