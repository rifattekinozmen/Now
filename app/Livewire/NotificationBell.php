<?php

namespace App\Livewire;

use App\Models\AppNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationBell extends Component
{
    #[Computed]
    public function unreadCount(): int
    {
        $user = Auth::user();
        if (! $user) {
            return 0;
        }

        return AppNotification::query()
            ->forUser($user->id)
            ->unread()
            ->count();
    }

    public function render(): View
    {
        return view('livewire.notification-bell');
    }
}
