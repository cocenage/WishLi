<?php

use App\Models\Wishlist;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public string $tab = 'all';

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['all', 'mine', 'shared'], true)) {
            return;
        }

        $this->tab = $tab;
        $this->dispatch('telegram-haptic-impact');
    }

    public function getWishlistsProperty(): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        $owned = Wishlist::query()
            ->with(['owner', 'items.claims', 'memberLinks'])
            ->where('owner_id', $user->id)
            ->where('is_archived', false)
            ->latest()
            ->get();

        $shared = Wishlist::query()
            ->with(['owner', 'items.claims', 'memberLinks'])
            ->whereHas('memberLinks', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status', 'accepted');
            })
            ->where('owner_id', '!=', $user->id)
            ->where('is_archived', false)
            ->latest()
            ->get();

        return match ($this->tab) {
            'mine' => $owned,
            'shared' => $shared,
            default => $owned->merge($shared)->unique('id')->values(),
        };
    }

    public function totalItems(Wishlist $wishlist): int
    {
        return $wishlist->items->count();
    }

    public function reservedItems(Wishlist $wishlist): int
    {
        return $wishlist->items
            ->filter(fn ($item) => $item->status === 'purchased' || $item->claims->count() > 0)
            ->count();
    }

    public function remainingItems(Wishlist $wishlist): int
    {
        return max(0, $this->totalItems($wishlist) - $this->reservedItems($wishlist));
    }

    public function progressPercent(Wishlist $wishlist): int
    {
        $total = $this->totalItems($wishlist);

        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->reservedItems($wishlist) / $total) * 100);
    }

    public function typeLabel(Wishlist $wishlist): string
    {
        return $wishlist->owner_id === auth()->id()
            ? 'Мой вишлист'
            : 'Общий вишлист';
    }

    public function palette(Wishlist $wishlist): array
    {
        return match ($wishlist->color) {
            'peach' => [
                'card' => 'bg-[#D9AE7F]',
                'soft' => 'bg-[#ECD3B8]',
            ],
            'green' => [
                'card' => 'bg-[#8F9B8A]',
                'soft' => 'bg-[#D8DED1]',
            ],
            'blue' => [
                'card' => 'bg-[#AEB8D6]',
                'soft' => 'bg-[#DDE2F1]',
            ],
            'beige' => [
                'card' => 'bg-[#C8B298]',
                'soft' => 'bg-[#E9DED0]',
            ],
            default => [
                'card' => 'bg-[#C9AE8D]',
                'soft' => 'bg-[#E8D6BF]',
            ],
        };
    }

    public function lastUpdateLabel(): string
    {
        return now()->translatedFormat('d M');
    }
};
?>

<div class="min-h-screen pb-[120px]">
    <div class="mx-auto w-full max-w-[430px] px-5 pt-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-[68px] font-semibold leading-[0.84] tracking-[-0.09em] text-[#171717]">
                    Hello
                </h1>

                <div class="mt-8 flex items-center gap-2 border-b border-[#D6D2C8] pb-3">
                    <span class="h-2 w-2 rounded-full bg-[#24D66B]"></span>
                    <span class="max-w-[190px] truncate text-[14px] text-[#393934]">
                        {{ auth()->user()?->name ?? 'Telegram User' }}
                    </span>
                </div>
            </div>

            <div class="flex flex-col items-end gap-4 pt-2">
                <a
                    href="{{ route('page-notification-settings') }}"
                    class="flex h-[30px] w-[30px] items-center justify-center rounded-full border border-[#1B1B1B]/30 text-[14px] text-[#171717]"
                >
                    ⌁
                </a>

                <div class="text-right text-[11px] leading-[1.05] text-[#77736B]">
                    <div>Last Update:</div>
                    <div>{{ $this->lastUpdateLabel() }}</div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-between">
            <div class="text-[18px] font-semibold tracking-[-0.04em]">
                Recents files <span class="text-[#88837A]">({{ $this->wishlists->count() }})</span>
            </div>

            <button
                type="button"
                wire:click="setTab('all')"
                class="text-[13px] text-[#292925]"
            >
                See all
            </button>
        </div>

        <div class="mt-4 flex gap-2 overflow-x-auto no-scrollbar">
            <button
                wire:click="setTab('all')"
                class="h-[38px] shrink-0 rounded-full px-5 text-[13px] font-medium {{ $tab === 'all' ? 'bg-[#171717] text-white' : 'bg-white/60 text-[#171717]' }}"
            >
                Все
            </button>

            <button
                wire:click="setTab('mine')"
                class="h-[38px] shrink-0 rounded-full px-5 text-[13px] font-medium {{ $tab === 'mine' ? 'bg-[#171717] text-white' : 'bg-white/60 text-[#171717]' }}"
            >
                Мои
            </button>

            <button
                wire:click="setTab('shared')"
                class="h-[38px] shrink-0 rounded-full px-5 text-[13px] font-medium {{ $tab === 'shared' ? 'bg-[#171717] text-white' : 'bg-white/60 text-[#171717]' }}"
            >
                Общие
            </button>
        </div>

        <div class="mt-4 space-y-3">
            @forelse($this->wishlists as $wishlist)
                @php
                    $total = $this->totalItems($wishlist);
                    $reserved = $this->reservedItems($wishlist);
                    $remaining = $this->remainingItems($wishlist);
                    $progress = $this->progressPercent($wishlist);
                    $palette = $this->palette($wishlist);
                @endphp

                <a
                    href="{{ route('page-wishlist-show', ['wishlist' => $wishlist->id]) }}"
                    class="block overflow-hidden rounded-[28px] {{ $palette['card'] }} p-5 shadow-[0_14px_34px_rgba(0,0,0,0.06)]"
                >
                    <div class="min-h-[116px]">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-[29px] font-semibold leading-[0.88] tracking-[-0.07em] text-[#5A5348]/80">
                                    {{ $wishlist->emoji ?: '🎁' }}
                                    {{ $wishlist->title }}
                                </div>

                                <div class="mt-3 text-[13px] text-[#2D2924]/65">
                                    {{ $this->typeLabel($wishlist) }}
                                </div>
                            </div>

                            @if($wishlist->is_closed)
                                <div class="rounded-full bg-[#171717]/15 px-3 py-1 text-[11px] text-[#171717]">
                                    closed
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 border-t border-[#171717]/10 pt-3">
                        <div class="flex items-center justify-between gap-3 text-[12px] text-[#2D2924]/65">
                            <div class="flex items-center gap-2">
                                <span>↗</span>
                                <span>{{ $total }} gifts</span>
                            </div>

                            <div>
                                {{ $reserved }} reserved · {{ $remaining }} free
                            </div>
                        </div>

                        <div class="mt-3 h-[7px] w-full rounded-full bg-[#171717]/10">
                            <div
                                class="h-[7px] rounded-full bg-[#171717]/70"
                                style="width: {{ $progress }}%;"
                            ></div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="rounded-[30px] bg-white/65 p-8 text-center shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
                    <div class="text-[38px] font-semibold leading-none tracking-[-0.07em]">
                        Empty
                    </div>

                    <div class="mt-3 text-[14px] text-[#77736B]">
                        Создай первый вишлист
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 mx-auto w-full max-w-[430px] px-5 pb-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <div class="flex h-[70px] items-center justify-between rounded-[28px] bg-[#171717] px-6 shadow-[0_18px_45px_rgba(0,0,0,0.18)]">
            <button
                type="button"
                wire:click="setTab('mine')"
                class="min-w-[82px] text-center text-[13px] font-medium text-white"
            >
                МОИ
            </button>

            <a
                href="{{ route('page-wishlist-create') }}"
                class="flex h-[46px] w-[46px] items-center justify-center rounded-full bg-white text-[24px] leading-none text-[#171717]"
            >
                +
            </a>

            <button
                type="button"
                wire:click="setTab('shared')"
                class="min-w-[82px] text-center text-[13px] font-medium text-white"
            >
                ОБЩИЕ
            </button>
        </div>
    </div>
</div>