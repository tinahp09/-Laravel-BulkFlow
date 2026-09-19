<?php

declare(strict_types=1);

namespace BulkFlow\Facades;

use BulkFlow\BulkFlowManager;
use Illuminate\Support\Facades\Facade;

/** @method static \BulkFlow\Import\ImportBuilder import(string $modelClass) */
/** @method static \BulkFlow\Export\ExportBuilder export(iterable $source) */
/** @method static \BulkFlow\Schedule\ScheduledExportBuilder scheduledExport(string $modelClass) */
/** @method static \BulkFlow\Import\Profiles\ImportProfileRegistry profiles() */
final class BulkFlow extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BulkFlowManager::class;
    }
}
