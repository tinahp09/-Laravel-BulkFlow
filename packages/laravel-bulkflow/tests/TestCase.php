<?php

declare(strict_types=1);

namespace BulkFlow\Tests;

use BulkFlow\BulkFlowServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [BulkFlowServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('queue.batching.database', 'testing');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
        });

        Schema::create('bulkflow_import_runs', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('state');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedBigInteger('revision')->default(0);
            $table->string('batch_id')->nullable()->index();
            $table->json('definition')->nullable();
            $table->uuid('parent_run_id')->nullable();
            $table->timestamps();
        });

        Schema::create('bulkflow_row_failures', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->unsignedInteger('row_number');
            $table->string('type');
            $table->json('errors');
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('bulkflow_scheduled_exports', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('model_class');
            $table->json('columns');
            $table->string('format');
            $table->string('disk')->nullable();
            $table->string('path');
            $table->string('expression');
            $table->string('recipient')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('job_batches', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
    }
}
