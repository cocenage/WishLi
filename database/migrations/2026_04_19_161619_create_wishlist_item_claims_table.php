<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlist_item_claims', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wishlist_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['wishlist_item_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_item_claims');
    }
};