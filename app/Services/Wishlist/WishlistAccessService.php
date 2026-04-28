<?php

namespace App\Services\Wishlist;

use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;

class WishlistAccessService
{
    public function canView(Wishlist $wishlist, ?User $user): bool
    {
        if ($wishlist->visibility === 'public') {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($wishlist->owner_id === $user->id) {
            return true;
        }

        if ($wishlist->visibility === 'link') {
            return true;
        }

        return $wishlist->memberLinks()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->exists();
    }

    public function canManage(Wishlist $wishlist, User $user): bool
    {
        return $wishlist->owner_id === $user->id || $user->isAdmin();
    }

    public function canAddItem(Wishlist $wishlist, User $user): bool
    {
        if ($wishlist->isUnavailable()) {
            return false;
        }

        if ($this->canManage($wishlist, $user)) {
            return true;
        }

        return $wishlist->allow_item_addition
            && $this->canView($wishlist, $user);
    }

    public function canEditItem(WishlistItem $item, User $user): bool
    {
        return $item->wishlist->owner_id === $user->id
            || $item->created_by === $user->id
            || $user->isAdmin();
    }

    public function canClaim(WishlistItem $item, User $user): bool
    {
        $wishlist = $item->wishlist;

        if ($wishlist->isUnavailable()) {
            return false;
        }

        if ($wishlist->owner_id === $user->id) {
            return false;
        }

        if ($item->status === 'hidden' || $item->status === 'purchased') {
            return false;
        }

        if (! $this->canView($wishlist, $user)) {
            return false;
        }

        if ($wishlist->allow_multi_claim) {
            return true;
        }

        return ! $item->claims()
            ->where('user_id', '!=', $user->id)
            ->exists();
    }
}