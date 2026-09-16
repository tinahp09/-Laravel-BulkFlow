<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\BulkFlowManager;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;
use DateTimeImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class S3CompatibleStorageTest extends TestCase
{
    public function test_it_imports_and_exports_through_an_s3_compatible_disk(): void
    {
        $endpoint = getenv('BULKFLOW_S3_ENDPOINT');

        if (! is_string($endpoint) || $endpoint === '') {
            self::markTestSkipped('Set BULKFLOW_S3_ENDPOINT to run the S3-compatible storage integration test.');
        }

        config()->set('filesystems.disks.bulkflow-s3', [
            'driver' => 's3',
            'key' => getenv('BULKFLOW_S3_KEY') ?: 'bulkflow-test',
            'secret' => getenv('BULKFLOW_S3_SECRET') ?: 'bulkflow-test-secret',
            'region' => 'us-east-1',
            'bucket' => getenv('BULKFLOW_S3_BUCKET') ?: 'bulkflow',
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
            'throw' => true,
        ]);

        $disk = Storage::disk('bulkflow-s3');
        $input = 'bulkflow-tests/'.Str::uuid().'/users.csv';
        $output = 'bulkflow-tests/'.Str::uuid().'/users.csv';

        try {
            $disk->put($input, "name,email\nAda,ada@example.test\n");

            $result = $this->app->make(BulkFlowManager::class)
                ->import(User::class)
                ->fromDisk('bulkflow-s3', $input)
                ->map(['name' => 'name', 'email' => 'email'])
                ->upsertBy(['email'])
                ->run();

            $file = $this->app->make(BulkFlowManager::class)
                ->export([['name' => 'Ada']])
                ->columns(['name' => 'Name'])
                ->asCsv()
                ->storeOnDisk('bulkflow-s3', $output);

            self::assertSame(1, $result->successfulRows);
            self::assertTrue($disk->exists($output));
            self::assertStringContainsString('/bulkflow/', $file->temporaryUrl(new DateTimeImmutable('+5 minutes')));
        } finally {
            $disk->delete([$input, $output]);
        }
    }
}
