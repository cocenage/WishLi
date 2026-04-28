<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlist_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wishlist_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('role', [
                'owner',
                'participant',
            ])->default('participant');

            $table->enum('status', [
                'pending',
                'accepted',
            ])->default('accepted');

            $table->timestamps();

            $table->unique(['wishlist_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_members');
    }
};