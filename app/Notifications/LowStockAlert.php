<?php

namespace App\Notifications;

use App\Models\SparePart;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  Collection<int, SparePart>  $parts
     */
    public function __construct(public $parts) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('تنبيه: انخفاض مخزون قطع الغيار'))
            ->greeting(__('مرحبًا'))
            ->line(__('وصلت بعض قطع الغيار إلى أو انخفضت عن الحد الأدنى:'));

        foreach ($this->parts as $part) {
            $mail->line("{$part->name} ({$part->code}) — {$part->quantity_in_stock} / {$part->minimum_quantity}");
        }

        return $mail->action(__('عرض المخزون'), route('inventory.index'));
    }
}
