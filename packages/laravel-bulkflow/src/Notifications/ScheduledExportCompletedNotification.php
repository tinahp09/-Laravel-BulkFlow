<?php

declare(strict_types=1);

namespace BulkFlow\Notifications;

use BulkFlow\Schedule\ScheduledExport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ScheduledExportCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ScheduledExport $scheduledExport) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function scheduledExportName(): string
    {
        return $this->scheduledExport->name;
    }

    public function path(): string
    {
        return $this->scheduledExport->path;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'scheduled_export_id' => $this->scheduledExport->id,
            'name' => $this->scheduledExport->name,
            'disk' => $this->scheduledExport->disk,
            'path' => $this->scheduledExport->path,
            'format' => $this->scheduledExport->format,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('BulkFlow scheduled export completed')
            ->line(sprintf('Scheduled export %s is ready.', $this->scheduledExport->name))
            ->line(sprintf('Artifact: %s', $this->scheduledExport->path));
    }
}
