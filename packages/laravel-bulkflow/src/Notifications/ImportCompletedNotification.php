<?php

declare(strict_types=1);

namespace BulkFlow\Notifications;

use BulkFlow\Run\ImportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ImportCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ImportRun $run) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'run_id' => $this->run->id,
            'state' => $this->run->state,
            'processed_rows' => $this->run->processed_rows,
            'successful_rows' => $this->run->successful_rows,
            'failed_rows' => $this->run->failed_rows,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('BulkFlow import completed')
            ->line(sprintf('Import %s finished with state %s.', $this->run->id, $this->run->state))
            ->line(sprintf('%d successful, %d failed.', $this->run->successful_rows, $this->run->failed_rows));
    }
}
