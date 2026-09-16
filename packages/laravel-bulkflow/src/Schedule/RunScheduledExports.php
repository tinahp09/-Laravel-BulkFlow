<?php

declare(strict_types=1);

namespace BulkFlow\Schedule;

use BulkFlow\Export\ExportBuilder;
use BulkFlow\Notifications\ScheduledExportCompletedNotification;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

final class RunScheduledExports extends Command
{
    protected $signature = 'bulkflow:run-scheduled-exports';

    protected $description = 'Write all due BulkFlow scheduled exports.';

    public function handle(): int
    {
        ScheduledExport::query()->where('active', true)->each(function (ScheduledExport $scheduledExport): void {
            if (! CronExpression::factory($scheduledExport->expression)->isDue()) {
                return;
            }

            /** @var class-string<Model> $modelClass */
            $modelClass = $scheduledExport->model_class;
            $export = (new ExportBuilder($modelClass::query()->cursor()))->columns($scheduledExport->columns);
            $export = $scheduledExport->format === 'xlsx' ? $export->asXlsx() : $export->asCsv();

            if ($scheduledExport->disk === null) {
                $export->store($scheduledExport->path);
            } else {
                $export->storeOnDisk($scheduledExport->disk, $scheduledExport->path);
            }

            if ($scheduledExport->recipient !== null) {
                Notification::route('mail', $scheduledExport->recipient)
                    ->notify(new ScheduledExportCompletedNotification($scheduledExport));
            }
        });

        return self::SUCCESS;
    }
}
