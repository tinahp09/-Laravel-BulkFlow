<?php

declare(strict_types=1);

namespace BulkFlow;

use BulkFlow\Authorization\ImportRunChannelAuthorizer;
use BulkFlow\Console\PruneRunsCommand;
use BulkFlow\Import\Profiles\ImportProfileRegistry;
use BulkFlow\Progress\BroadcastProgressPublisher;
use BulkFlow\Progress\NullProgressPublisher;
use BulkFlow\Progress\ProgressPublisher;
use BulkFlow\Schedule\RunScheduledExports;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

final class BulkFlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bulkflow.php', 'bulkflow');

        $this->app->singleton(ImportProfileRegistry::class, function (): ImportProfileRegistry {
            $profiles = array_map(
                fn (string $profileClass) => $this->app->make($profileClass),
                config('bulkflow.profiles', []),
            );

            return new ImportProfileRegistry($profiles);
        });

        $this->app->singleton(BulkFlowManager::class, fn (): BulkFlowManager => new BulkFlowManager(
            profiles: $this->app->make(ImportProfileRegistry::class),
        ));

        $this->app->singleton(ProgressPublisher::class, fn (): ProgressPublisher => config('bulkflow.progress.publisher') === 'null'
            ? new NullProgressPublisher
            : new BroadcastProgressPublisher);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        Broadcast::channel('bulkflow.imports.{runId}', function (mixed $user, string $runId): bool {
            return $this->app->make(ImportRunChannelAuthorizer::class)->allows($user, $runId);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([PruneRunsCommand::class, RunScheduledExports::class]);

            $this->app->booted(function (): void {
                $this->app->make(Schedule::class)
                    ->command('bulkflow:run-scheduled-exports')
                    ->everyMinute()
                    ->withoutOverlapping();
            });
        }

        $this->publishes([
            __DIR__.'/../config/bulkflow.php' => config_path('bulkflow.php'),
        ], 'bulkflow-config');
    }
}
