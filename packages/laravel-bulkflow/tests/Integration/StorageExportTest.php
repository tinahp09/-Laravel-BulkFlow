<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\BulkFlowManager;
use BulkFlow\Tests\TestCase;
use DateTimeImmutable;
use Illuminate\Support\Facades\Storage;

final class StorageExportTest extends TestCase
{
    public function test_it_stores_an_export_on_a_laravel_disk(): void
    {
        Storage::fake('exports');

        $this->app->make(BulkFlowManager::class)
            ->export([['name' => 'ندا']])
            ->columns(['name' => 'نام'])
            ->asCsv()
            ->storeOnDisk('exports', 'reports/users.csv');

        Storage::disk('exports')->assertExists('reports/users.csv');
    }

    public function test_disk_export_can_request_a_private_temporary_url(): void
    {
        Storage::fake('exports');
        Storage::disk('exports')->buildTemporaryUrlsUsing(fn (string $path, $expiration): string => 'https://download.example.test/'.rawurlencode($path).'?until='.$expiration->getTimestamp());

        $file = $this->app->make(BulkFlowManager::class)
            ->export([['email' => 'ada@example.test']])
            ->columns(['email' => 'Email'])
            ->storeOnDisk('exports', 'private/users.csv');

        self::assertSame(
            'https://download.example.test/private%2Fusers.csv?until=1798761600',
            $file->temporaryUrl(new DateTimeImmutable('2027-01-01 00:00:00 UTC')),
        );
    }
}
