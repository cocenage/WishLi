<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WishlistInvite extends Model
{
    protected $fillable = [
        'wishlist_id',
        'created_by',
        'token',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
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
}