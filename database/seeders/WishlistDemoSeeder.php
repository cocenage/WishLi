<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistMember;
use Illuminate\Database\Seeder;

class WishlistDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->firstOrCreate(
            ['telegram_id' => 111111111],
            [
                'name' => 'Gregory',
                'telegram_username' => 'gregory',
                'is_active' => true,
            ]
        );

        $friend = User::query()->firstOrCreate(
            ['telegram_id' => 222222222],
            [
                'name' => 'Alex',
                'telegram_username' => 'alex',
                'is_active' => true,
            ]
        );

        $wishlist = Wishlist::query()->create([
            'owner_id' => $owner->id,
            'title' => 'День рождения',
            'description' => 'Что я хочу в подарок и для себя',
            'event_date' => now()->addDays(20)->toDateString(),
            'visibility' => 'link',
            'allow_item_addition' => true,
            'allow_multi_claim' => true,
            'emoji' => '🎉',
        ]);

        WishlistMember::query()->firstOrCreate([
            'wishlist_id' => $wishlist->id,
            'user_id' => $owner->id,
        ], [
            'role' => 'owner',
            'status' => 'accepted',
        ]);

        WishlistMember::query()->firstOrCreate([
            'wishlist_id' => $wishlist->id,
            'user_id' => $friend->id,
        ], [
            'role' => 'participant',
            'status' => 'accepted',
        ]);

        WishlistItem::query()->create([
            'wishlist_id' => $wishlist->id,
            'created_by' => $owner->id,
            'title' => 'Механическая клавиатура',
            'description' => 'Нужна компактная и тихая',
            'store_name' => 'amazon.it',
            'url' => 'https://amazon.it/example-keyboard',
            'image_url' => 'https://placehold.co/600x400',
            'price' => 120,
            'currency' => '€',
            'priority' => 'high',
        ]);

        WishlistItem::query()->create([
            'wishlist_id' => $wishlist->id,
            'created_by' => $owner->id,
            'title' => 'Настольная лампа',
            'description' => 'Минималистичная, тёплый свет',
            'store_name' => 'ozon.ru',
            'url' => 'https://ozon.ru/example-lamp',
            'image_url' => 'https://placehold.co/600x400',
            'price' => 3500,
            'currency' => '₽',
            'priority' => 'medium',
        ]);
    }
}