<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\Facades\BulkFlow;
use BulkFlow\Notifications\ScheduledExportCompletedNotification;
use BulkFlow\Schedule\RunScheduledExports;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

final class ScheduledExportTest extends TestCase
{
    public function test_due_definition_writes_its_artifact_and_notifies_recipient(): void
    {
        Storage::fake('exports');
        Notification::fake();
        User::query()->create(['name' => 'Ada', 'email' => 'ada@example.test']);

        BulkFlow::scheduledExport(User::class)
            ->named('daily-users')
            ->columns(['name' => 'Name', 'email' => 'Email'])
            ->asCsv()
            ->storeOnDisk('exports', 'reports/users.csv')
            ->cron('* * * * *')
            ->notifyTo('ops@example.test')
            ->save();

        $this->artisan(RunScheduledExports::class)->assertSuccessful();

        Storage::disk('exports')->assertExists('reports/users.csv');
        self::assertSame("Name,Email\nAda,ada@example.test\n", Storage::disk('exports')->get('reports/users.csv'));
        Notification::assertSentOnDemand(ScheduledExportCompletedNotification::class, static function ($notification, array $channels, object $notifiable): bool {
            return $notifiable->routes['mail'] === 'ops@example.test'
                && $notification->scheduledExportName() === 'daily-users'
                && $notification->path() === 'reports/users.csv';
        });
    }
}
