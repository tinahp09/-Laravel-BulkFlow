<?php

declare(strict_types=1);

namespace BulkFlow\Failure;

use Illuminate\Database\Eloquent\Model;

final class RowFailureRecord extends Model
{
    protected $table = 'bulkflow_row_failures';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'payload' => 'array',
        ];
    }
}
