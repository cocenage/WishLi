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
            'image_path' => $data['image_path'] ?? null,
            'price' => $data['price'] ?? null,
            'currency' => $data['currency'] ?? null,
            'category' => $data['category'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => $data['status'] ?? 'wanted',
            'note' => $data['note'] ?? null,
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
            'image_path' => $data['image_path'] ?? $item->image_path,
            'price' => $data['price'] ?? null,
            'currency' => $data['currency'] ?? null,
            'category' => $data['category'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => $data['status'] ?? 'wanted',
            'note' => $data['note'] ?? null,
        ]);

        return $item->fresh();
    }

    public function claim(WishlistItem $item, User $user, array $data = []): WishlistItemClaim
    {
        return WishlistItemClaim::query()->updateOrCreate(
            [
                'wishlist_item_id' => $item->id,
                'user_id' => $user->id,
            ],
            [
                'status' => $data['status'] ?? 'reserved',
                'comment' => $data['comment'] ?? null,
            ]
        );
    }

    public function unclaim(WishlistItem $item, User $user): void
    {
        WishlistItemClaim::query()
            ->where('wishlist_item_id', $item->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    public function markPurchased(WishlistItem $item): WishlistItem
    {
        $item->update([
            'status' => 'purchased',
        ]);

        return $item->fresh();
    }
}