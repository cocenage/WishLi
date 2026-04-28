<?php

use App\Models\WishlistInvite;
use App\Services\Wishlist\WishlistInviteService;
use App\Services\Wishlist\WishlistTelegramService;
use Livewire\Component;

new class extends Component
{
    public WishlistInvite $invite;

    public function mount(string $token): void
    {
        $this->invite = WishlistInvite::query()
            ->with(['wishlist.owner', 'wishlist.items.claims'])
            ->where('token', $token)
            ->firstOrFail();

        abort_unless($this->invite->is_active, 404);

        if ($this->invite->expires_at && $this->invite->expires_at->isPast()) {
            abort(404);
        }
    }

    public function join(
        WishlistInviteService $invites,
        WishlistTelegramService $telegram,
    ): void {
        $wasCreated = $invites->join($this->invite, auth()->user());

        if ($wasCreated) {
            $telegram->notifyJoinedWishlist(
                $this->invite->wishlist,
                auth()->user(),
                route('page-wishlist-show', ['wishlist' => $this->invite->wishlist_id])
            );
        }

        $this->dispatch('telegram-haptic-success');

        $this->redirect(
            route('page-wishlist-show', ['wishlist' => $this->invite->wishlist_id]),
            navigate: true
        );
    }
};
?>

<div class="min-h-screen pb-[110px]">
    <div class="mx-auto w-full max-w-[430px] px-5 pt-5">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-[13px] font-medium uppercase tracking-[0.18em] text-[#8D887F]">
                    invite
                </div>

                <h1 class="mt-3 text-[58px] font-semibold leading-[0.84] tracking-[-0.09em] text-[#171717]">
                    Join<br>Wishlist
                </h1>
            </div>

            <a
                href="{{ route('page-wishlists') }}"
                class="flex h-[42px] w-[42px] items-center justify-center rounded-full bg-white/70 text-[18px] text-[#171717] shadow-sm"
            >
                ×
            </a>
        </div>

        <div class="mt-7 overflow-hidden rounded-[30px] bg-[#C9AE8D] p-5 shadow-[0_14px_34px_rgba(0,0,0,0.06)]">
            <div class="min-h-[150px]">
                <div class="text-[36px] font-semibold leading-[0.86] tracking-[-0.08em] text-[#5A5348]/80">
                    {{ $invite->wishlist->emoji ?: '🎁' }}
                    {{ $invite->wishlist->title }}
                </div>

                <div class="mt-4 text-[14px] text-[#2D2924]/65">
                    {{ $invite->wishlist->owner?->name }}
                </div>

                @if($invite->wishlist->event_date)
                    <div class="mt-1 text-[12px] text-[#2D2924]/55">
                        {{ $invite->wishlist->event_date->format('d.m.Y') }}
                    </div>
                @endif

                @if($invite->wishlist->description)
                    <div class="mt-4 text-[14px] leading-[1.3] text-[#2D2924]/65">
                        {{ $invite->wishlist->description }}
                    </div>
                @endif
            </div>

            @if($invite->wishlist->items->count())
                <div class="mt-5 flex gap-2">
                    @foreach($invite->wishlist->items->take(4) as $item)
                        <div class="h-[72px] w-[72px] overflow-hidden rounded-[20px] bg-white/25">
                            @if($item->image_url)
                                <img src="{{ $item->image_url }}" alt="" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-[24px]">
                                    🎁
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="mt-4 rounded-[28px] bg-white/70 p-5 text-[14px] leading-[1.4] text-[#77736B] shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
            После входа ты сможешь смотреть подарки и бронировать их, чтобы никто не купил одно и то же.
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 mx-auto w-full max-w-[430px] px-5 pb-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <button
            wire:click="join"
            class="flex h-[70px] w-full items-center justify-center rounded-[28px] bg-[#171717] text-[15px] font-medium text-white shadow-[0_18px_45px_rgba(0,0,0,0.18)]"
        >
            Присоединиться
        </button>
    </div>
</div>