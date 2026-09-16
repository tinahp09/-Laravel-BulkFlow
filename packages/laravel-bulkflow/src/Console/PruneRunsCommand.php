<?php

declare(strict_types=1);

namespace BulkFlow\Console;

use BulkFlow\Failure\RowFailureRecord;
use BulkFlow\Run\ImportRun;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class PruneRunsCommand extends Command
{
    protected $signature = 'bulkflow:prune-runs {--before= : ISO-8601 date before which runs are removed} {--dry-run : Report expired runs without removing them}';

    protected $description = 'Remove expired BulkFlow runs and their failed rows.';

    public function handle(): int
    {
        $beforeOption = (string) $this->option('before');

        if (trim($beforeOption) === '') {
            $this->error('The --before option is required.');

            return self::FAILURE;
        }

        $before = Carbon::parse($beforeOption);
        $ids = ImportRun::query()->where('created_at', '<', $before)->pluck('id');

        if ((bool) $this->option('dry-run')) {
            $this->info(sprintf('Would prune %d import runs.', $ids->count()));

            return self::SUCCESS;
        }

        RowFailureRecord::query()->whereIn('run_id', $ids)->delete();
        $count = ImportRun::query()->whereIn('id', $ids)->delete();
        $this->info(sprintf('Pruned %d import runs.', $count));

        return self::SUCCESS;
    }
}
