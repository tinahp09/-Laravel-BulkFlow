<?php

declare(strict_types=1);

namespace BulkFlow\Notifications;

use BulkFlow\Run\ImportRun;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class ImportCompletionNotifier
{
    public function notify(ImportRun $run): void
    {
        $resolver = config('bulkflow.notify');

        if (! is_callable($resolver)) {
            return;
        }

        try {
            $notifiables = $resolver($run);

            if ($notifiables !== null) {
                Notification::send($notifiables, new ImportCompletedNotification($run));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
