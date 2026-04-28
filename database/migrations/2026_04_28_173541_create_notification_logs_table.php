<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('channel')->default('telegram');
            $table->string('type');

            $table->string('dedupe_key')->nullable()->unique();

            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            $table->text('text');
            $table->json('payload')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};