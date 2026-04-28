<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $fillable = [
        'owner_id',
        'title',
        'description',
        'type',
        'event_date',
        'visibility',
        'allow_item_addition',
        'allow_multi_claim',
        'hide_claimers',
        'cover_image',
        'emoji',
        'color',
        'is_closed',
        'is_archived',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'allow_item_addition' => 'boolean',
            'allow_multi_claim' => 'boolean',
            'hide_claimers' => 'boolean',
            'is_closed' => 'boolean',
            'is_archived' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function items()
    {
        return $this->hasMany(WishlistItem::class)->orderBy('sort_order')->latest();
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'wishlist_members')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function memberLinks()
    {
        return $this->hasMany(WishlistMember::class);
    }

    public function invites()
    {
        return $this->hasMany(WishlistInvite::class);
    }

    public function isUnavailable(): bool
    {
        return $this->is_closed || $this->is_archived;
    }

    public function isExpired(): bool
    {
        return $this->event_date && $this->event_date->isPast();
    }
}