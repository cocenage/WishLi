<?php

use App\Models\WishlistInvite;
use App\Models\WishlistMember;
use Livewire\Component;

new class extends Component
{
    public WishlistInvite $invite;

    public function mount(string $token): void
    {
        $this->invite = WishlistInvite::query()
            ->with('wishlist.owner')
            ->where('token', $token)
            ->firstOrFail();

        abort_unless($this->invite->is_active, 404);

        if ($this->invite->expires_at && $this->invite->expires_at->isPast()) {
            abort(404);
        }
    }

    public function typeLabel(): string
    {
        return match ($this->invite->wishlist->type) {
            'birthday' => 'День рождения',
            'new_year' => 'Новый год',
            'wedding' => 'Свадьба',
            'house' => 'Переезд',
            default => 'Вишлист',
        };
    }

    public function join()
    {
        WishlistMember::query()->firstOrCreate(
            [
                'wishlist_id' => $this->invite->wishlist_id,
                'user_id' => auth()->id(),
            ],
            [
                'role' => 'participant',
                'status' => 'accepted',
            ]
        );

        return redirect()->route('page-wishlist-show', ['wishlist' => $this->invite->wishlist_id]);
    }
};
?>

<div class="min-h-screen bg-[#f4f7fb] px-4 py-6 pb-28">
    <div class="rounded-[28px] bg-white p-6 shadow-sm">
        <div class="text-4xl">{{ $invite->wishlist->emoji ?: '🎁' }}</div>

        <h1 class="mt-4 text-2xl font-semibold text-[#1f2a37]">
            {{ $invite->wishlist->title }}
        </h1>

        <div class="mt-2">
            <span class="inline-flex rounded-full bg-[#eef2f7] px-2 py-1 text-[11px] font-medium text-[#1f2a37]">
                {{ $this->typeLabel() }}
            </span>
        </div>

        @if($invite->wishlist->description)
            <p class="mt-2 text-sm text-[#6b7280]">
                {{ $invite->wishlist->description }}
            </p>
        @endif

        <div class="mt-4 flex flex-wrap gap-2 text-xs text-[#6b7280]">
            <span>Владелец: {{ $invite->wishlist->owner?->name }}</span>

            @if($invite->wishlist->event_date)
                <span>•</span>
                <span>до {{ $invite->wishlist->event_date->format('d.m.Y') }}</span>
            @endif
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 p-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <button
            wire:click="join"
            class="block w-full rounded-[20px] bg-[#1f2a37] px-5 py-4 text-center text-sm font-medium text-white shadow-lg"
        >
            Присоединиться
        </button>
    </div>
</div>