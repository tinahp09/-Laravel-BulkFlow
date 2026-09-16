<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use BulkFlow\Facades\BulkFlow;
use BulkFlow\Run\ImportRun;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bulkflow:benchmark-import {--rows=100000} {--chunk=1000} {--queued} {--sync}', function (): int {
    $rows = (int) $this->option('rows');
    $chunk = (int) $this->option('chunk');
    $queued = (bool) $this->option('queued');
    $sync = (bool) $this->option('sync');

    if ($rows < 1 || $chunk < 1) {
        $this->error('--rows and --chunk must both be positive integers.');

        return Command::FAILURE;
    }

    if ($sync && ! $queued) {
        $this->error('--sync can only be used with --queued.');

        return Command::FAILURE;
    }

    $directory = storage_path('app/bulkflow-benchmarks');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $path = $directory.'/'.Str::uuid().'.csv';
    $handle = fopen($path, 'wb');

    if ($handle === false) {
        $this->error('Could not create benchmark source file.');

        return Command::FAILURE;
    }

    $startedAt = hrtime(true);
    $password = Hash::make(Str::random(64));

    if ($sync) {
        config()->set('queue.default', 'sync');
    }

    try {
        fputcsv($handle, ['name', 'email', 'password']);

        for ($row = 1; $row <= $rows; ++$row) {
            fputcsv($handle, ['Benchmark User '.$row, 'benchmark-'.$row.'@example.test', $password]);
        }
        fclose($handle);

        $import = BulkFlow::import(User::class)
            ->from($path)
            ->map(['name' => 'name', 'email' => 'email', 'password' => 'password'])
            ->upsertBy(['email'])
            ->chunkSize($chunk);

        $outcome = $queued ? $import->queue() : $import->run();
        $run = $outcome instanceof ImportRun ? $outcome->fresh() : ImportRun::query()->findOrFail($outcome->runId);

        $this->line(json_encode([
            'rows_requested' => $rows,
            'chunk_size' => $chunk,
            'queued' => $queued,
            'sync' => $sync,
            'run_id' => $run->id,
            'state' => $run->state,
            'processed_rows' => $run->processed_rows,
            'successful_rows' => $run->successful_rows,
            'failed_rows' => $run->failed_rows,
            'elapsed_seconds' => round((hrtime(true) - $startedAt) / 1_000_000_000, 3),
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'source_path' => $queued && ! $sync ? $path : null,
        ], JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ((! $queued || $sync) && is_file($path)) {
            unlink($path);
        }
    }
})->purpose('Run a reproducible BulkFlow CSV import benchmark.');

Artisan::command('bulkflow:demo-failed-import', function (): int {
    $path = tempnam(sys_get_temp_dir(), 'bulkflow-failure-demo-').'.csv';
    $password = Str::random(64);
    file_put_contents($path, "name,email,password\nAda,ada@example.test,{$password}\nInvalid,not-an-email,{$password}\n");

    try {
        $result = BulkFlow::import(User::class)
            ->from($path)
            ->map(['name' => 'name', 'email' => 'email', 'password' => 'password'])
            ->validate(['email' => ['required', 'email']])
            ->upsertBy(['email'])
            ->run();
        $run = ImportRun::query()->findOrFail($result->runId);

        $this->line(json_encode([
            'run_id' => $run->id,
            'state' => $run->state,
            'successful_rows' => $run->successful_rows,
            'failed_rows' => $run->failed_rows,
        ], JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    } finally {
        @unlink($path);
    }
})->purpose('Create a small import with one validation failure for the Vue demo.');
