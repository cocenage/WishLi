<?php

use App\Models\Wishlist;
use Livewire\Component;
use Illuminate\Support\Collection;

new class extends Component
{
    public string $tab = 'all';

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function getWishlistsProperty(): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        $owned = Wishlist::query()
            ->with(['owner', 'items', 'memberLinks'])
            ->where('owner_id', $user->id)
            ->latest()
            ->get();

        $shared = Wishlist::query()
            ->with(['owner', 'items', 'memberLinks'])
            ->whereHas('memberLinks', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status', 'accepted');
            })
            ->where('owner_id', '!=', $user->id)
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
            ->filter(fn ($item) => $item->claims()->exists() || $item->is_purchased)
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
        $color = $wishlist->color ?: null;

        return match ($color) {
            'yellow' => [
                'card' => 'bg-[linear-gradient(180deg,_#F7EE9B_0%,_#F2E56C_100%)]',
                'progress' => 'bg-[radial-gradient(circle_at_top_right,_rgba(0,0,0,0.55),_rgba(0,0,0,0.03)_58%),linear-gradient(180deg,_#F5E779_0%,_#EEDD59_100%)]',
                'track' => 'bg-[rgba(176,154,32,0.16)]',
            ],
            'peach' => [
                'card' => 'bg-[linear-gradient(180deg,_#F5D0B0_0%,_#F1BE90_100%)]',
                'progress' => 'bg-[radial-gradient(circle_at_top_right,_rgba(0,0,0,0.42),_rgba(0,0,0,0.03)_58%),linear-gradient(180deg,_#EFB57E_0%,_#E79E5D_100%)]',
                'track' => 'bg-[rgba(173,109,41,0.14)]',
            ],
            'green' => [
                'card' => 'bg-[linear-gradient(180deg,_#DFE8CC_0%,_#D0DCB6_100%)]',
                'progress' => 'bg-[radial-gradient(circle_at_top_right,_rgba(0,0,0,0.22),_rgba(0,0,0,0.02)_58%),linear-gradient(180deg,_#C8D7AB_0%,_#B7CA98_100%)]',
                'track' => 'bg-[rgba(91,119,53,0.12)]',
            ],
            'blue' => [
                'card' => 'bg-[linear-gradient(180deg,_#DCE0F2_0%,_#CFD6ED_100%)]',
                'progress' => 'bg-[radial-gradient(circle_at_top_right,_rgba(0,0,0,0.22),_rgba(0,0,0,0.02)_58%),linear-gradient(180deg,_#C2CBEB_0%,_#B2BFE2_100%)]',
                'track' => 'bg-[rgba(82,96,149,0.12)]',
            ],
            'beige' => [
                'card' => 'bg-[linear-gradient(180deg,_#EAE4DB_0%,_#DED6CB_100%)]',
                'progress' => 'bg-[radial-gradient(circle_at_top_right,_rgba(0,0,0,0.18),_rgba(0,0,0,0.02)_58%),linear-gradient(180deg,_#DDD4C7_0%,_#D1C8BB_100%)]',
                'track' => 'bg-[rgba(98,79,49,0.10)]',
            ],
            default => $wishlist->isUnavailable()
                ? [
                    'card' => 'bg-[linear-gradient(180deg,_#EAE4DB_0%,_#DED6CB_100%)]',
                    'progress' => 'bg-[radial-gradient(circle_at_top_right,_rgba(0,0,0,0.18),_rgba(0,0,0,0.02)_58%),linear-gradient(180deg,_#DDD4C7_0%,_#D1C8BB_100%)]',
                    'track' => 'bg-[rgba(98,79,49,0.10)]',
                ]
                : ($wishlist->owner_id === auth()->id()
                    ? [
                        'card' => 'bg-[linear-gradient(180deg,_#F7EE9B_0%,_#F2E56C_100%)]',
                        'progress' => 'bg-[radial-gradient(circle_at_top_right,_rgba(0,0,0,0.55),_rgba(0,0,0,0.03)_58%),linear-gradient(180deg,_#F5E779_0%,_#EEDD59_100%)]',
                        'track' => 'bg-[rgba(176,154,32,0.16)]',
                    ]
                    : [
                        'card' => 'bg-[linear-gradient(180deg,_#F5D0B0_0%,_#F1BE90_100%)]',
                        'progress' => 'bg-[radial-gradient(circle_at_top_right,_rgba(0,0,0,0.42),_rgba(0,0,0,0.03)_58%),linear-gradient(180deg,_#EFB57E_0%,_#E79E5D_100%)]',
                        'track' => 'bg-[rgba(173,109,41,0.14)]',
                    ]),
        };
    }
};
?>

<div class="min-h-screen bg-transparent pb-[110px] text-[#171717]">
    <div class="mx-auto w-full max-w-[860px] px-6 pt-7">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-[44px] w-[44px] items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" class="h-[30px] w-[30px] fill-[#111111]">
                        <path d="M12 18c0-1.1.9-2 2-2h36c1.1 0 2 .9 2 2 0 8.2-5.7 15.1-13.4 17 7.7 1.9 13.4 8.8 13.4 17 0 1.1-.9 2-2 2H14c-1.1 0-2-.9-2-2 0-8.2 5.7-15.1 13.4-17C17.7 33.1 12 26.2 12 18Z"/>
                    </svg>
                </div>

                <div class="text-[28px] font-medium tracking-[-0.04em] text-[#141414]">
                    Wishli
                </div>
            </div>

            <div class="flex items-center gap-5">
                <button type="button" class="text-[#232323]/90">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-[22px] w-[22px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 1-5.714 0m8.143-2.143A2.143 2.143 0 0 0 19 12.857V11a7 7 0 1 0-14 0v1.857a2.143 2.143 0 0 0 1.714 2.082l.857.171a23.95 23.95 0 0 0 8.858 0l.857-.171Z" />
                    </svg>
                </button>

                <div class="h-[54px] w-[54px] overflow-hidden rounded-full bg-white shadow-[0_8px_20px_rgba(0,0,0,0.06)]">
                    @if(auth()->user()?->telegram_avatar_path)
                        <img src="{{ auth()->user()->telegram_avatar_path }}" alt="" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center text-sm font-medium text-[#222]">
                            {{ mb_substr(auth()->user()?->name ?? 'U', 0, 1) }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-10">
            <h1 class="text-[34px] font-medium leading-none tracking-[-0.05em] text-[#1d1d1d]">
                Вишлисты ({{ $this->wishlists->count() }})
            </h1>
        </div>

        <div class="mt-7 flex gap-3 overflow-x-auto pb-1">
            <button
                wire:click="setTab('all')"
                class="inline-flex h-[68px] min-w-[165px] items-center justify-center gap-4 rounded-[24px] px-6 text-[18px] font-medium text-[#1d1d1d] shadow-[inset_0_1px_0_rgba(255,255,255,0.75)] backdrop-blur-[10px] {{ $tab === 'all' ? 'bg-[rgba(255,255,255,0.36)]' : 'bg-[rgba(255,255,255,0.22)]' }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-[21px] w-[21px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <rect x="3" y="3" width="7" height="7" rx="1.6"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1.6"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1.6"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="1.6"></rect>
                </svg>
                <span>Все</span>
            </button>

            <button
                wire:click="setTab('mine')"
                class="inline-flex h-[68px] min-w-[165px] items-center justify-center gap-4 rounded-[24px] px-6 text-[18px] font-medium text-[#1d1d1d] shadow-[inset_0_1px_0_rgba(255,255,255,0.75)] backdrop-blur-[10px] {{ $tab === 'mine' ? 'bg-[rgba(255,255,255,0.36)]' : 'bg-[rgba(255,255,255,0.22)]' }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-[21px] w-[21px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.118a7.5 7.5 0 0 1 15 0A17.933 17.933 0 0 1 12 21.75a17.933 17.933 0 0 1-7.5-1.632Z" />
                </svg>
                <span>Мои</span>
            </button>

            <button
                wire:click="setTab('shared')"
                class="inline-flex h-[68px] min-w-[190px] items-center justify-center gap-4 rounded-[24px] px-6 text-[18px] font-medium text-[#1d1d1d] shadow-[inset_0_1px_0_rgba(255,255,255,0.75)] backdrop-blur-[10px] {{ $tab === 'shared' ? 'bg-[rgba(255,255,255,0.36)]' : 'bg-[rgba(255,255,255,0.22)]' }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-[21px] w-[21px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.742-.479 3 3 0 0 0-4.682-2.72m.94 3.198v.001c0 .34-.03.672-.086.995M18 18.72a8.966 8.966 0 0 1-5.1-1.476M12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Zm-7.5 3.75a8.966 8.966 0 0 0 5.1-1.476m0 0A8.966 8.966 0 0 1 12 17.25c.845 0 1.664.117 2.442.337M6 18.72a9.094 9.094 0 0 1-3.742-.479 3 3 0 0 1 4.682-2.72M6 18.72v.001c0 .34.03.672.086.995" />
                </svg>
                <span>Общие</span>
            </button>
        </div>

        <div class="mt-6 space-y-5">
            @forelse($this->wishlists as $wishlist)
                @php
                    $total = $this->totalItems($wishlist);
                    $remaining = $this->remainingItems($wishlist);
                    $reserved = $this->reservedItems($wishlist);
                    $progress = $this->progressPercent($wishlist);
                    $palette = $this->palette($wishlist);
                @endphp

                <a
                    href="{{ route('page-wishlist-show', ['wishlist' => $wishlist->id]) }}"
                    class="block rounded-[34px] px-6 py-6 shadow-[0_10px_26px_rgba(0,0,0,0.035),inset_0_1px_0_rgba(255,255,255,0.32)] {{ $palette['card'] }}"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-[20px] font-medium leading-none tracking-[-0.03em] text-[#171717]">
                                {{ $wishlist->emoji ?: '🎁' }} {{ $wishlist->title }}
                            </div>

                            <div class="mt-3 text-[14px] text-[#1F1F1F]/70">
                                {{ $this->typeLabel($wishlist) }}
                            </div>
                        </div>

                        <button
                            type="button"
                            class="flex h-[58px] w-[58px] shrink-0 items-center justify-center rounded-[18px] bg-[rgba(255,255,255,0.18)] shadow-[inset_0_1px_0_rgba(255,255,255,0.45)] backdrop-blur-[6px]"
                            onclick="event.preventDefault(); event.stopPropagation();"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px] text-[#1d1d1d]" fill="currentColor" viewBox="0 0 24 24">
                                <circle cx="5" cy="12" r="1.8"/>
                                <circle cx="12" cy="12" r="1.8"/>
                                <circle cx="19" cy="12" r="1.8"/>
                            </svg>
                        </button>
                    </div>

                    <div class="mt-8 grid grid-cols-[1fr_1fr_1fr_160px] gap-4 max-[720px]:grid-cols-2">
                        <div>
                            <div class="text-[30px] font-medium leading-none tracking-[-0.05em]">
                                {{ $total }}
                            </div>
                            <div class="mt-2 text-[13px] text-[#1a1a1a]/76">
                                Товаров
                            </div>
                        </div>

                        <div>
                            <div class="text-[30px] font-medium leading-none tracking-[-0.05em]">
                                {{ $remaining }}
                            </div>
                            <div class="mt-2 text-[13px] text-[#1a1a1a]/76">
                                Осталось
                            </div>
                        </div>

                        <div>
                            <div class="text-[30px] font-medium leading-none tracking-[-0.05em]">
                                {{ $reserved }}
                            </div>
                            <div class="mt-2 text-[13px] text-[#1a1a1a]/76">
                                Зарезервировано
                            </div>
                        </div>

                        <div class="max-[720px]:col-span-2">
                            <div class="flex h-full min-h-[116px] flex-col justify-center rounded-[30px] px-7 py-5 text-[#161616] shadow-[inset_0_1px_0_rgba(255,255,255,0.3),0_10px_24px_rgba(0,0,0,0.045)] {{ $palette['progress'] }}">
                                <div class="text-[28px] font-medium leading-none tracking-[-0.05em]">
                                    {{ $progress }}%
                                </div>
                                <div class="mt-3 text-[14px] text-[#171717]/82">
                                    Прогресс
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5">
                        <div class="h-[12px] w-full rounded-full {{ $palette['track'] }}">
                            <div
                                class="h-[12px] rounded-full bg-[#111111]"
                                style="width: {{ $progress }}%;"
                            ></div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="rounded-[34px] bg-[rgba(255,255,255,0.20)] p-8 text-center shadow-[inset_0_1px_0_rgba(255,255,255,0.45)] backdrop-blur-[10px]">
                    <div class="text-[32px] font-medium leading-none text-[#1b1b1b]">
                        Пока пусто
                    </div>
                    <div class="mt-3 text-[15px] text-[#666]">
                        Создай первый вишлист
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-20 mx-auto w-full max-w-[860px] px-6 pb-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <a
            href="{{ route('page-wishlist-create') }}"
            class="flex h-[74px] items-center justify-center gap-4 rounded-[28px] bg-[rgba(255,255,255,0.18)] text-[20px] font-medium text-[#181818] shadow-[inset_0_1px_0_rgba(255,255,255,0.6),0_12px_28px_rgba(0,0,0,0.03)] backdrop-blur-[14px]"
        >
            <span class="text-[36px] leading-none">+</span>
            <span>Создать вишлист</span>
        </a>
    </div>
</div>