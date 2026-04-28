<?php

namespace App\Console\Commands;

use App\Models\Wishlist;
use App\Services\Wishlist\WishlistTelegramService;
use Illuminate\Console\Command;

class NotifyWishlistReminders extends Command
{
    protected $signature = 'wishlists:notify-reminders';

    protected $description = 'Send wishlist event reminders';

    public function handle(WishlistTelegramService $telegram): int
    {
        $days = [30, 14, 7, 3, 1];

        foreach ($days as $day) {
            $wishlists = Wishlist::query()
                ->with(['owner.notificationSettings'])
                ->where('is_closed', false)
                ->where('is_archived', false)
                ->whereDate('event_date', now()->addDays($day)->toDateString())
                ->get();

            foreach ($wishlists as $wishlist) {
                $telegram->notifyEventReminder(
                    wishlist: $wishlist,
                    daysBefore: $day,
                    url: route('page-wishlist-show', ['wishlist' => $wishlist->id])
                );
            }
        }

        $this->info('Wishlist reminders processed.');

        return self::SUCCESS;
    }
}