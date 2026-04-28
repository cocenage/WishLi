<?php

namespace App\Services\Wishlist;

use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistMember;

class WishlistService
{
    public function create(User $user, array $data): Wishlist
    {
        $wishlist = Wishlist::query()->create([
            'owner_id' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'custom',
            'event_date' => $data['event_date'] ?? null,
            'visibility' => $data['visibility'] ?? 'link',
            'allow_item_addition' => (bool) ($data['allow_item_addition'] ?? true),
            'allow_multi_claim' => (bool) ($data['allow_multi_claim'] ?? false),
            'hide_claimers' => (bool) ($data['hide_claimers'] ?? true),
            'emoji' => $data['emoji'] ?? '🎁',
            'color' => $data['color'] ?? 'yellow',
            'is_archived' => false,
            'is_closed' => false,
        ]);

        WishlistMember::query()->firstOrCreate(
            [
                'wishlist_id' => $wishlist->id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'owner',
                'status' => 'accepted',
            ]
        );

        return $wishlist;
    }

    public function update(Wishlist $wishlist, array $data): Wishlist
    {
        $wishlist->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'custom',
            'event_date' => $data['event_date'] ?? null,
            'visibility' => $data['visibility'] ?? 'link',
            'allow_item_addition' => (bool) ($data['allow_item_addition'] ?? true),
            'allow_multi_claim' => (bool) ($data['allow_multi_claim'] ?? false),
            'hide_claimers' => (bool) ($data['hide_claimers'] ?? true),
            'emoji' => $data['emoji'] ?? '🎁',
            'color' => $data['color'] ?? 'yellow',
        ]);

        return $wishlist->fresh();
    }

    public function close(Wishlist $wishlist): Wishlist
    {
        $wishlist->update([
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        return $wishlist->fresh();
    }

    public function reopen(Wishlist $wishlist): Wishlist
    {
        $wishlist->update([
            'is_closed' => false,
            'closed_at' => null,
        ]);

        return $wishlist->fresh();
    }

    public function leave(Wishlist $wishlist, User $user): void
    {
        if ($wishlist->owner_id === $user->id) {
            return;
        }

        WishlistMember::query()
            ->where('wishlist_id', $wishlist->id)
            ->where('user_id', $user->id)
            ->delete();
    }
}