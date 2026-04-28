<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wishlist_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('url', 2048)->nullable();
            $table->string('store_name')->nullable();

            $table->string('image_url', 2048)->nullable();
            $table->string('image_path')->nullable();

            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 10)->nullable();

            $table->string('category')->nullable();

            $table->enum('priority', [
                'low',
                'medium',
                'high',
                'dream',
            ])->default('medium');

            $table->enum('status', [
                'wanted',
                'postponed',
                'purchased',
                'hidden',
            ])->default('wanted');

            $table->text('note')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['wishlist_id', 'status']);
            $table->index(['wishlist_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};