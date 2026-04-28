<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WishlistItemClaim extends Model
{
    protected $fillable = [
        'wishlist_item_id',
        'user_id',
        'status',
        'comment',
    ];

    public function item()
    {
        return $this->belongsTo(WishlistItem::class, 'wishlist_item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}