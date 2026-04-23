<?php

use App\Models\Wishlist;
use Illuminate\Support\Collection;
use Livewire\Component;

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

    public function claimsCount(Wishlist $wishlist): int
    {
        return \App\Models\WishlistItemClaim::query()
            ->whereHas('item', fn ($query) => $query->where('wishlist_id', $wishlist->id))
            ->count();
    }

    public function typeLabel(Wishlist $wishlist): string
    {
        return match ($wishlist->type) {
            'birthday' => 'День рождения',
            'new_year' => 'Новый год',
            'wedding' => 'Свадьба',
            'house' => 'Переезд',
            default => 'Вишлист',
        };
    }

    public function isUnavailable(Wishlist $wishlist): bool
    {
        return $wishlist->is_closed || ($wishlist->event_date && $wishlist->event_date->isPast());
    }
};
?>

<div class="min-h-screen bg-[#f4f7fb] pb-28">
    <div class="px-4 pt-4">
        <h1 class="text-2xl font-semibold text-[#1f2a37]">Вишлисты</h1>

        <div class="mt-4 flex gap-2 overflow-x-auto">
            <button
                wire:click="setTab('all')"
                class="whitespace-nowrap rounded-full px-4 py-2 text-sm {{ $tab === 'all' ? 'bg-[#1f2a37] text-white' : 'bg-white text-[#1f2a37]' }}"
            >
                Все вишлисты
            </button>

            <button
                wire:click="setTab('mine')"
                class="whitespace-nowrap rounded-full px-4 py-2 text-sm {{ $tab === 'mine' ? 'bg-[#1f2a37] text-white' : 'bg-white text-[#1f2a37]' }}"
            >
                Мои
            </button>

            <button
                wire:click="setTab('shared')"
                class="whitespace-nowrap rounded-full px-4 py-2 text-sm {{ $tab === 'shared' ? 'bg-[#1f2a37] text-white' : 'bg-white text-[#1f2a37]' }}"
            >
                Мне прислали
            </button>
        </div>
    </div>

    <div class="mt-5 space-y-3 px-4">
        @forelse($this->wishlists as $wishlist)
            <a
                href="{{ route('page-wishlist-show', ['wishlist' => $wishlist->id]) }}"
                class="block rounded-[24px] bg-white p-4 shadow-sm"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <div class="text-xl">{{ $wishlist->emoji ?: '🎁' }}</div>
                            <h2 class="truncate text-base font-semibold text-[#1f2a37]">
                                {{ $wishlist->title }}
                            </h2>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-2">
                            <span class="inline-flex rounded-full bg-[#eef2f7] px-2 py-1 text-[11px] font-medium text-[#1f2a37]">
                                {{ $this->typeLabel($wishlist) }}
                            </span>

                            @if($this->isUnavailable($wishlist))
                                <span class="inline-flex rounded-full bg-[#fee2e2] px-2 py-1 text-[11px] font-medium text-[#991b1b]">
                                    Закрыт
                                </span>
                            @elseif($wishlist->event_date && $wishlist->event_date->isFuture())
                                <span class="inline-flex rounded-full bg-[#dbeafe] px-2 py-1 text-[11px] font-medium text-[#1d4ed8]">
                                    Активный
                                </span>
                            @endif

                            @if($wishlist->is_archived)
                                <span class="inline-flex rounded-full bg-[#e5e7eb] px-2 py-1 text-[11px] font-medium text-[#374151]">
                                    Архив
                                </span>
                            @endif
                        </div>

                        @if($wishlist->description)
                            <p class="mt-2 line-clamp-2 text-sm text-[#6b7280]">
                                {{ $wishlist->description }}
                            </p>
                        @endif

                        <div class="mt-3 flex flex-wrap gap-2 text-xs text-[#6b7280]">
                            <span>{{ $wishlist->items->count() }} товаров</span>
                            <span>•</span>
                            <span>{{ $wishlist->memberLinks->where('status', 'accepted')->count() }} участников</span>
                            <span>•</span>
                            <span>{{ $this->claimsCount($wishlist) }} выборов</span>

                            @if($wishlist->event_date)
                                <span>•</span>
                                <span>до {{ $wishlist->event_date->format('d.m.Y') }}</span>
                            @endif
                        </div>

                        <div class="mt-2 text-xs text-[#94a3b8]">
                            @if($wishlist->owner_id === auth()->id())
                                Мой список
                            @else
                                {{ $wishlist->owner?->name }}
                            @endif
                        </div>
                    </div>

                    <div class="flex -space-x-2">
                        @foreach($wishlist->items->take(3) as $item)
                            <div class="h-10 w-10 overflow-hidden rounded-full border-2 border-white bg-[#eef2f7]">
                                @if($item->image_url)
                                    <img src="{{ $item->image_url }}" alt="" class="h-full w-full object-cover">
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </a>
        @empty
            <div class="rounded-[24px] bg-white p-8 text-center shadow-sm">
                <div class="text-4xl">🎁</div>
                <h3 class="mt-3 text-base font-semibold text-[#1f2a37]">Пока нет вишлистов</h3>
                <p class="mt-2 text-sm text-[#6b7280]">
                    Создай первый вишлист и отправь его друзьям.
                </p>
            </div>
        @endforelse
    </div>

    <div class="fixed inset-x-0 bottom-0 p-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <a
            href="{{ route('page-wishlist-create') }}"
            class="block rounded-[20px] bg-[#1f2a37] px-5 py-4 text-center text-sm font-medium text-white shadow-lg"
        >
            Создать вишлист
        </a>
    </div>
</div>