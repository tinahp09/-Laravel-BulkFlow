<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\BulkFlowManager;
use BulkFlow\Queue\ProcessImport;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class StorageImportTest extends TestCase
{
    public function test_it_imports_a_csv_materialized_from_a_laravel_disk(): void
    {
        Storage::fake('imports');
        Storage::disk('imports')->put('uploads/users.csv', "name,email\nAda,ada@example.test\n");

        $result = $this->app->make(BulkFlowManager::class)
            ->import(User::class)
            ->fromDisk('imports', 'uploads/users.csv')
            ->map(['name' => 'name', 'email' => 'email'])
            ->upsertBy(['email'])
            ->run();

        self::assertSame(1, $result->successfulRows);
        self::assertDatabaseHas('users', ['email' => 'ada@example.test']);
    }

    public function test_it_imports_a_laravel_uploaded_file_from_private_storage(): void
    {
        $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nAda,ada@example.test\n");

        $result = $this->app->make(BulkFlowManager::class)
            ->import(User::class)
            ->from($file)
            ->map(['name' => 'name', 'email' => 'email'])
            ->upsertBy(['email'])
            ->run();

        self::assertSame(1, $result->successfulRows);
        self::assertDatabaseHas('users', ['email' => 'ada@example.test']);
    }

    public function test_it_materializes_an_uploaded_file_before_queue_dispatch(): void
    {
        Queue::fake();
        $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nAda,ada@example.test\n");

        $this->app->make(BulkFlowManager::class)
            ->import(User::class)
            ->from($file)
            ->map(['name' => 'name', 'email' => 'email'])
            ->upsertBy(['email'])
            ->queue();

        Queue::assertPushed(ProcessImport::class, static fn (ProcessImport $job): bool => str_starts_with($job->definition->source, storage_path('app/bulkflow-inputs/')));
    }

    public function test_it_rejects_an_uploaded_file_larger_than_the_configured_limit(): void
    {
        config()->set('bulkflow.input.max_bytes', 1_024);
        $file = UploadedFile::fake()->create('users.csv', 2);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('configured maximum size');

        $this->app->make(BulkFlowManager::class)->import(User::class)->from($file);
    }

    public function test_it_rejects_an_uploaded_file_with_an_unsupported_extension(): void
    {
        $file = UploadedFile::fake()->create('users.pdf', 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('extension [pdf] is not allowed');

        $this->app->make(BulkFlowManager::class)->import(User::class)->from($file);
    }
}
