<?php

use App\Models\WishlistInvite;
use App\Models\WishlistMember;
use App\Services\Wishlist\WishlistTelegramService;
use Livewire\Component;

new class extends Component
{
    public WishlistInvite $invite;

    public function mount(string $token): void
    {
        $this->invite = WishlistInvite::query()
            ->with(['wishlist.owner', 'wishlist.items'])
            ->where('token', $token)
            ->firstOrFail();

        abort_unless($this->invite->is_active, 404);

        if ($this->invite->expires_at && $this->invite->expires_at->isPast()) {
            abort(404);
        }
    }

    public function join(WishlistTelegramService $telegram): void
    {
        $member = WishlistMember::query()->firstOrCreate(
            [
                'wishlist_id' => $this->invite->wishlist_id,
                'user_id' => auth()->id(),
            ],
            [
                'role' => 'participant',
                'status' => 'accepted',
            ]
        );

        if ($member->wasRecentlyCreated) {
            $telegram->notifyJoinedWishlist($this->invite->wishlist, auth()->user());
        }

        $this->redirect(route('page-wishlist-show', ['wishlist' => $this->invite->wishlist_id]), navigate: true);
    }
};
?>

<div class="min-h-screen bg-[#F3F0E8] px-4 py-6 pb-28 text-[#111111]">
    <div class="rounded-[32px] bg-white p-5">
        <div class="text-[12px] uppercase tracking-[0.18em] text-[#8B8B8B]">
            invite
        </div>

        <div class="mt-2 text-[44px] font-semibold leading-[0.9]">
            {{ $invite->wishlist->title }}
        </div>

        <div class="mt-3 text-sm text-[#666666]">
            {{ $invite->wishlist->owner?->name }}
        </div>

        @if($invite->wishlist->event_date)
            <div class="mt-1 text-xs text-[#8A8A8A]">
                {{ $invite->wishlist->event_date->format('d.m.Y') }}
            </div>
        @endif

        @if($invite->wishlist->description)
            <div class="mt-4 text-sm text-[#666666]">
                {{ $invite->wishlist->description }}
            </div>
        @endif

        @if($invite->wishlist->items->count())
            <div class="mt-5 flex gap-2">
                @foreach($invite->wishlist->items->take(3) as $item)
                    <div class="h-20 w-20 overflow-hidden rounded-[20px] bg-[#ECE7DD]">
                        @if($item->image_url)
                            <img src="{{ $item->image_url }}" alt="" class="h-full w-full object-cover">
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="fixed inset-x-0 bottom-0 p-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <button
            wire:click="join"
            class="block w-full rounded-[24px] bg-[#111111] px-5 py-4 text-center text-sm font-medium text-white"
        >
            Join wishlist
        </button>
    </div>
</div>