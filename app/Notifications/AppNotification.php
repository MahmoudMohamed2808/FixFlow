<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Whether this instance should also be sent by mail.
     * Concrete notifications decide based on action criticality.
     */
    abstract protected function wantsMail(): bool;

    abstract public function title(): string;

    abstract public function body(): string;

    abstract public function url(): string;

    /**
     * Machine-readable action slug, e.g. work_order.assigned.
     */
    abstract public function action(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function payload(): array;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($this->wantsMail()) {
            return ['database', 'mail'];
        }

        return ['database'];
    }

    /**
     * Route mail through a dedicated queue.
     *
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'mail' => 'mail',
            'database' => 'default',
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting(__('مرحبًا :name', ['name' => $notifiable->name ?? '']))
            ->line($this->body())
            ->action(__('عرض التفاصيل'), $this->url());
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return array_merge([
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
            'action' => $this->action(),
        ], $this->payload());
    }
}
