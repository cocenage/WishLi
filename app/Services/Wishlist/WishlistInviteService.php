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

    public function join(WishlistInvite $invite, User $user): void
    {
        WishlistMember::query()->firstOrCreate(
            [
                'wishlist_id' => $invite->wishlist_id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'participant',
                'status' => 'accepted',
            ]
        );
    }

    public function deactivate(WishlistInvite $invite): void
    {
        $invite->update([
            'is_active' => false,
        ]);
    }
}