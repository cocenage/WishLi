<?php

namespace App\Services\Wishlist;

use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistInvite;
use App\Models\WishlistMember;
use Illuminate\Support\Str;

class WishlistInviteService
{
    public function getOrCreateActive(Wishlist $wishlist, User $user): WishlistInvite
    {
        $invite = $wishlist->invites()
            ->where('is_active', true)
            ->latest()
            ->first();

        if ($invite) {
            return $invite;
        }

        return $wishlist->invites()->create([
            'created_by' => $user->id,
            'token' => Str::random(40),
        ]);
    }

    public function join(WishlistInvite $invite, User $user): bool
    {
        $member = WishlistMember::query()->firstOrCreate(
            [
                'wishlist_id' => $invite->wishlist_id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'participant',
                'status' => 'accepted',
            ]
        );

        return $member->wasRecentlyCreated;
    }

    public function buildTelegramStartUrl(WishlistInvite $invite): string
    {
        $botUsername = config('services.telegram.bot_username');

        if ($botUsername) {
            return "https://t.me/{$botUsername}/app?startapp=invite_{$invite->token}";
        }

        return route('page-wishlist-invite', ['token' => $invite->token]);
    }
}