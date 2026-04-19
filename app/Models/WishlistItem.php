<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WishlistItem extends Model
{
    protected $fillable = [
        'wishlist_id',
        'created_by',
        'title',
        'description',
        'url',
        'store_name',
        'image_url',
        'price',
        'currency',
        'note',
        'priority',
        'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_hidden' => 'boolean',
        ];
    }

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(WishlistItemClaim::class);
    }

    public function getClaimsCountAttribute(): int
    {
        return $this->claims->count();
    }

    public function isClaimedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->claims()->where('user_id', $user->id)->exists();
    }
}