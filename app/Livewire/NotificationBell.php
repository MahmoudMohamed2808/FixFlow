<?php

namespace App\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationBell extends Component
{
    #[Computed]
    public function unreadCount(): int
    {
        $user = auth()->user();

        if ($user === null) {
            return 0;
        }

        return $user->unreadNotifications()->count();
    }

    #[Computed]
    public function latest(): mixed
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        return $user->notifications()->latest()->limit(8)->get();
    }

    public function markAsRead(string $id): void
    {
        $notification = auth()->user()?->notifications()->whereKey($id)->first();

        if ($notification !== null && $notification->read_at === null) {
            $notification->markAsRead();
        }

        unset($this->unreadCount, $this->latest);
    }

    public function markAllRead(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();

        unset($this->unreadCount, $this->latest);
    }

    public function render(): mixed
    {
        return view('livewire.notification-bell');
    }
}
