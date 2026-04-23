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

            $table->foreignId('wishlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('url')->nullable();
            $table->string('store_name')->nullable();

            $table->string('image_url')->nullable();

            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 10)->nullable();

            $table->string('note')->nullable();
            $table->string('priority')->default('medium'); // low, medium, high
            $table->boolean('is_hidden')->default(false);

            $table->boolean('is_purchased')->default(false)->after('is_hidden');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};