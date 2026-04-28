<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'image_path',
        'price',
        'currency',
        'category',
        'priority',
        'status',
        'note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function wishlist()
    {
        return $this->belongsTo(Wishlist::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function claims()
    {
        return $this->hasMany(WishlistItemClaim::class);
    }

    public function activeClaims()
    {
        return $this->hasMany(WishlistItemClaim::class)
            ->whereIn('status', ['reserved', 'contribute', 'thinking', 'bought']);
    }

    public function isHidden(): bool
    {
        return $this->status === 'hidden';
    }

    public function isPurchased(): bool
    {
        return $this->status === 'purchased' || $this->claims()->where('status', 'bought')->exists();
    }

    public function isReserved(): bool
    {
        return $this->claims()->exists();
    }
}