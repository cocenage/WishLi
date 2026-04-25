<?php

namespace App\Services\Wishlist;

use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Http;

class WishlistTelegramService
{
    public function sendToUser(?User $user, string $text): void
    {
        if (! $user?->telegram_id) {
            return;
        }

        $token = config('services.telegram.bot_token');

        if (! $token) {
            return;
        }

        Http::asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $user->telegram_id,
            'text' => $text,
        ]);
    }

    public function notifyJoinedWishlist(Wishlist $wishlist, User $joinedUser): void
    {
        $this->sendToUser(
            $wishlist->owner,
            "{$joinedUser->name} joined wishlist \"{$wishlist->title}\"."
        );
    }

    public function notifyClaimed(WishlistItem $item, User $user): void
    {
        $this->sendToUser(
            $item->wishlist->owner,
            "{$user->name} joined gift \"{$item->title}\" in \"{$item->wishlist->title}\"."
        );
    }

    public function notifyUnclaimed(WishlistItem $item, User $user): void
    {
        $this->sendToUser(
            $item->wishlist->owner,
            "{$user->name} cancelled participation in \"{$item->title}\"."
        );
    }

    public function notifyWishlistClosingTomorrow(Wishlist $wishlist): void
    {
        $this->sendToUser(
            $wishlist->owner,
            "Wishlist \"{$wishlist->title}\" will close tomorrow."
        );
    }
}