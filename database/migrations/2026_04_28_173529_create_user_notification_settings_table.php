<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->boolean('wishlist_joined')->default(true);
            $table->boolean('item_claimed')->default(true);
            $table->boolean('item_unclaimed')->default(true);
            $table->boolean('wishlist_updated')->default(true);
            $table->boolean('event_reminders')->default(true);
            $table->boolean('marketing')->default(false);

            $table->json('reminder_days')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_settings');
    }
};