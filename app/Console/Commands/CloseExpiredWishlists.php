<?php

namespace App\Console\Commands;

use App\Models\Wishlist;
use Illuminate\Console\Command;

class CloseExpiredWishlists extends Command
{
    protected $signature = 'wishlists:close-expired';

    protected $description = 'Close wishlists with expired event date';

    public function handle(): int
    {
        $count = Wishlist::query()
            ->where('is_closed', false)
            ->whereNotNull('event_date')
            ->whereDate('event_date', '<', now()->toDateString())
            ->update([
                'is_closed' => true,
                'closed_at' => now(),
            ]);

        $this->info("Closed {$count} wishlist(s).");

        return self::SUCCESS;
    }
}