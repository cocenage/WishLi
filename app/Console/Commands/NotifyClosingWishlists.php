<?php

namespace App\Console\Commands;

use App\Models\Wishlist;
use App\Services\Wishlist\WishlistTelegramService;
use Illuminate\Console\Command;

class NotifyClosingWishlists extends Command
{
    protected $signature = 'wishlists:notify-closing';
    protected $description = 'Notify owners that their wishlist will close tomorrow';

    public function handle(WishlistTelegramService $telegram): int
    {
        $wishlists = Wishlist::query()
            ->with('owner')
            ->where('is_closed', false)
            ->whereDate('event_date', now()->addDay()->toDateString())
            ->get();

        foreach ($wishlists as $wishlist) {
            $telegram->notifyWishlistClosingTomorrow($wishlist);
        }

        $this->info('Closing notifications sent.');

        return self::SUCCESS;
    }
}