<?php

namespace App\Services\Wishlist;

use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistItemClaim;

class WishlistItemService
{
    public function create(Wishlist $wishlist, User $user, array $data): WishlistItem
    {
        return WishlistItem::query()->create([
            'wishlist_id' => $wishlist->id,
            'created_by' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'url' => $data['url'] ?? null,
            'store_name' => $data['store_name'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'price' => $data['price'] ?? null,
            'currency' => $data['currency'] ?? null,
            'note' => $data['note'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'is_hidden' => (bool) ($data['is_hidden'] ?? false),
        ]);
    }

    public function update(WishlistItem $item, array $data): WishlistItem
    {
        $item->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'url' => $data['url'] ?? null,
            'store_name' => $data['store_name'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'price' => $data['price'] ?? null,
            'currency' => $data['currency'] ?? null,
            'note' => $data['note'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'is_hidden' => (bool) ($data['is_hidden'] ?? false),
        ]);

        return $item->fresh();
    }

    public function delete(WishlistItem $item): void
    {
        $item->delete();
    }

    public function claim(WishlistItem $item, User $user): void
    {
        if (! $item->wishlist->allow_multi_claim && $item->claims()->exists()) {
            return;
        }

        WishlistItemClaim::query()->firstOrCreate([
            'wishlist_item_id' => $item->id,
            'user_id' => $user->id,
        ]);
    }

    public function unclaim(WishlistItem $item, User $user): void
    {
        WishlistItemClaim::query()
            ->where('wishlist_item_id', $item->id)
            ->where('user_id', $user->id)
            ->delete();
    }
}