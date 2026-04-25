<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();

            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->date('event_date')->nullable();

            $table->enum('visibility', ['private', 'link', 'invited'])->default('link');
            $table->boolean('allow_item_addition')->default(true);
            $table->boolean('allow_multi_claim')->default(true);

            $table->string('cover_image')->nullable();
            $table->string('color')->nullable();
            $table->string('emoji')->nullable();

            $table->string('type')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->boolean('hide_claimers')->default(false);

            $table->boolean('is_archived')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};