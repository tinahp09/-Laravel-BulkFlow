<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\BulkFlowManager;
use BulkFlow\Notifications\ImportCompletedNotification;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;
use Illuminate\Support\Facades\Notification;

final class ImportCompletionNotificationTest extends TestCase
{
    public function test_it_notifies_the_configured_recipient_when_an_import_completes(): void
    {
        Notification::fake();
        config()->set('bulkflow.notify', static fn () => Notification::route('mail', 'ops@example.test'));
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-notify-').'.csv';
        file_put_contents($path, "name,email\nAda,ada@example.test\n");

        $result = $this->app->make(BulkFlowManager::class)
            ->import(User::class)
            ->from($path)
            ->map(['name' => 'name', 'email' => 'email'])
            ->upsertBy(['email'])
            ->run();

        Notification::assertSentOnDemand(ImportCompletedNotification::class, static function (ImportCompletedNotification $notification, array $channels, object $notifiable) use ($result): bool {
            return $notifiable->routes['mail'] === 'ops@example.test'
                && $notification->toArray($notifiable)['run_id'] === $result->runId;
        });
    }
}
