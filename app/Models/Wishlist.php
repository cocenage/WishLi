<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Wishlist extends Model
{
    protected $fillable = [
        'owner_id',
        'title',
        'description',
        'event_date',
        'visibility',
        'allow_item_addition',
        'allow_multi_claim',
        'cover_image',
        'color',
        'emoji',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'allow_item_addition' => 'boolean',
            'allow_multi_claim' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function memberLinks(): HasMany
    {
        return $this->hasMany(WishlistMember::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'wishlist_members')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function getItemsCountAttribute(): int
    {
        return $this->items->count();
    }

    public function getParticipantsCountAttribute(): int
    {
        return $this->memberLinks()
            ->where('status', 'accepted')
            ->count();
    }

    public function getClaimsCountAttribute(): int
    {
        return WishlistItemClaim::query()
            ->whereHas('item', fn($query) => $query->where('wishlist_id', $this->id))
            ->count();
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user && $this->owner_id === $user->id;
    }

    public function hasParticipant(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->memberLinks()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->exists();
    }

    public function invites(): HasMany
    {
        return $this->hasMany(WishlistInvite::class);
    }

    public function scopeOwnedBy($query, int $userId)
{
    return $query->where('owner_id', $userId);
}

public function scopeSharedWith($query, int $userId)
{
    return $query->whereHas('memberLinks', function ($q) use ($userId) {
        $q->where('user_id', $userId)
            ->where('status', 'accepted');
    })->where('owner_id', '!=', $userId);
}
}