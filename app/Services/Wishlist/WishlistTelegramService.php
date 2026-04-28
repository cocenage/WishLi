<?php

namespace App\Services\Wishlist;

use App\Models\NotificationLog;
use App\Models\User;
use App\Models\UserNotificationSetting;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Http;

class WishlistTelegramService
{
    public function sendToUser(
        ?User $user,
        string $type,
        string $text,
        ?string $url = null,
        ?string $dedupeKey = null,
        ?string $relatedType = null,
        ?int $relatedId = null,
        array $payload = [],
    ): void {
        if (! $user?->telegram_id) {
            return;
        }

        if (! $this->notificationAllowed($user, $type)) {
            return;
        }

        if ($dedupeKey && NotificationLog::query()->where('dedupe_key', $dedupeKey)->exists()) {
            return;
        }

        $token = config('services.telegram.bot_token');

        if (! $token) {
            return;
        }

        $log = NotificationLog::query()->create([
            'user_id' => $user->id,
            'channel' => 'telegram',
            'type' => $type,
            'dedupe_key' => $dedupeKey,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'text' => $text,
            'payload' => $payload,
        ]);

        $params = [
            'chat_id' => $user->telegram_id,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($url) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Открыть',
                            'url' => $url,
                        ],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $params);

            if (! $response->successful()) {
                $log->update([
                    'failed_at' => now(),
                    'error' => $response->body(),
                ]);

                return;
            }

            $log->update([
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            $log->update([
                'failed_at' => now(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notificationAllowed(User $user, string $type): bool
    {
        $settings = UserNotificationSetting::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'wishlist_joined' => true,
                'item_claimed' => true,
                'item_unclaimed' => true,
                'wishlist_updated' => true,
                'event_reminders' => true,
                'marketing' => false,
                'reminder_days' => [7, 3, 1],
            ]
        );

        return match ($type) {
            'wishlist_joined' => $settings->wishlist_joined,
            'item_claimed' => $settings->item_claimed,
            'item_unclaimed' => $settings->item_unclaimed,
            'wishlist_updated' => $settings->wishlist_updated,
            'event_reminder' => $settings->event_reminders,
            'marketing' => $settings->marketing,
            default => true,
        };
    }

    public function notifyJoinedWishlist(Wishlist $wishlist, User $joinedUser, ?string $url = null): void
    {
        $name = e($joinedUser->name);
        $title = e($wishlist->title);

        $this->sendToUser(
            user: $wishlist->owner,
            type: 'wishlist_joined',
            text: "👀 <b>{$name}</b> присоединился к вишлисту <b>{$title}</b>.",
            url: $url,
            relatedType: Wishlist::class,
            relatedId: $wishlist->id,
        );
    }

    public function notifyClaimed(WishlistItem $item, User $user, ?string $url = null): void
    {
        $item->loadMissing('wishlist.owner');

        $title = e($item->title);
        $wishlistTitle = e($item->wishlist->title);

        $text = $item->wishlist->hide_claimers
            ? "🎁 Кто-то зарезервировал подарок <b>{$title}</b> в вишлисте <b>{$wishlistTitle}</b>."
            : "🎁 <b>" . e($user->name) . "</b> зарезервировал подарок <b>{$title}</b> в вишлисте <b>{$wishlistTitle}</b>.";

        $this->sendToUser(
            user: $item->wishlist->owner,
            type: 'item_claimed',
            text: $text,
            url: $url,
            relatedType: WishlistItem::class,
            relatedId: $item->id,
        );
    }

    public function notifyUnclaimed(WishlistItem $item, User $user, ?string $url = null): void
    {
        $item->loadMissing('wishlist.owner');

        $title = e($item->title);
        $wishlistTitle = e($item->wishlist->title);

        $text = $item->wishlist->hide_claimers
            ? "↩️ Бронь подарка <b>{$title}</b> была отменена."
            : "↩️ <b>" . e($user->name) . "</b> отменил бронь подарка <b>{$title}</b>.";

        $this->sendToUser(
            user: $item->wishlist->owner,
            type: 'item_unclaimed',
            text: "{$text}\n\nВишлист: <b>{$wishlistTitle}</b>",
            url: $url,
            relatedType: WishlistItem::class,
            relatedId: $item->id,
        );
    }

    public function notifyEventReminder(Wishlist $wishlist, int $daysBefore, ?string $url = null): void
    {
        if (! $wishlist->event_date) {
            return;
        }

        $title = e($wishlist->title);
        $date = $wishlist->event_date->format('d.m.Y');

        $this->sendToUser(
            user: $wishlist->owner,
            type: 'event_reminder',
            text: "⏰ Напоминание: событие по вишлисту <b>{$title}</b> будет {$date}.\n\nОсталось дней: <b>{$daysBefore}</b>.",
            url: $url,
            dedupeKey: "wishlist:{$wishlist->id}:reminder:{$daysBefore}",
            relatedType: Wishlist::class,
            relatedId: $wishlist->id,
        );
    }
}