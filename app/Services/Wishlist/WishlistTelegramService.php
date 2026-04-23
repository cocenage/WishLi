<?php

namespace App\Services\Wishlist;

use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Http;

class WishlistTelegramService
{
    public function sendMessage(string $text): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (! $token || ! $chatId) {
            return;
        }

        Http::asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }

    public function notifyWishlistShared(Wishlist $wishlist, User $targetUser): void
    {
        $this->sendMessage(
            "Тебя добавили в вишлист \"{$wishlist->title}\"."
        );
    }

    public function notifyItemClaimed(WishlistItem $item, User $claimer): void
    {
        $wishlist = $item->wishlist;

        $this->sendMessage(
            "{$claimer->name} выбрал подарок \"{$item->title}\" в вишлисте \"{$wishlist->title}\"."
        );
    }

    public function notifyItemUnclaimed(WishlistItem $item, User $claimer): void
    {
        $wishlist = $item->wishlist;

        $this->sendMessage(
            "{$claimer->name} отказался от подарка \"{$item->title}\" в вишлисте \"{$wishlist->title}\"."
        );
    }
}