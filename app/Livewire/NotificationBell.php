<?php

namespace App\Livewire;

use App\Models\AppNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

        // Cache unread count for 60 seconds per user
        $cacheKey = "notifications.unread.{$user->id}";
        
        return Cache::remember($cacheKey, 60, function () use ($user) {
            return AppNotification::query()
                ->forUser($user->id)
                ->unread()
                ->count();
        });
    }

    public function render(): View
    {
        return view('livewire.notification-bell');
    }
}
