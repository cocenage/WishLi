<?php

use Livewire\Component;
use App\Models\Wishlist;
use Illuminate\Support\Collection;

new class extends Component {
    public string $tab = 'all';

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function getWishlistsProperty(): Collection
    {
        $user = auth()->user();

        $owned = Wishlist::query()
            ->with(['owner', 'items'])
            ->where('owner_id', $user->id)
            ->latest()
            ->get();

        $shared = Wishlist::query()
            ->with(['owner', 'items'])
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
};
?>

<div class="min-h-screen bg-[#f4f7fb] pb-28">
    <div class="px-4 pt-4">
        <h1 class="text-2xl font-semibold text-[#1f2a37]">Вишлисты</h1>

        <div class="mt-4 flex gap-2 overflow-x-auto">
            <button wire:click="setTab('all')"
                class="rounded-full px-4 py-2 text-sm {{ $tab === 'all' ? 'bg-[#1f2a37] text-white' : 'bg-white text-[#1f2a37]' }}">
                Все
            </button>

            <button wire:click="setTab('mine')"
                class="rounded-full px-4 py-2 text-sm {{ $tab === 'mine' ? 'bg-[#1f2a37] text-white' : 'bg-white text-[#1f2a37]' }}">
                Мои
            </button>

            <button wire:click="setTab('shared')"
                class="rounded-full px-4 py-2 text-sm {{ $tab === 'shared' ? 'bg-[#1f2a37] text-white' : 'bg-white text-[#1f2a37]' }}">
                Мне прислали
            </button>
        </div>
    </div>

    <div class="mt-5 px-4 space-y-3">
        @forelse($this->wishlists as $wishlist)
            <a href="{{ route('wishlists.show', $wishlist) }}"
               class="block rounded-[24px] bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-[#1f2a37]">
                            {{ $wishlist->emoji }} {{ $wishlist->title }}
                        </h2>

                        @if($wishlist->description)
                            <p class="mt-1 text-sm text-[#6b7280] line-clamp-2">
                                {{ $wishlist->description }}
                            </p>
                        @endif

                        <div class="mt-3 flex flex-wrap gap-2 text-xs text-[#6b7280]">
                            <span>{{ $wishlist->items->count() }} товаров</span>
                            <span>•</span>
                            <span>{{ $wishlist->participants_count }} участников</span>

                            @if($wishlist->event_date)
                                <span>•</span>
                                <span>до {{ $wishlist->event_date->format('d.m.Y') }}</span>
                            @endif
                        </div>

                        <div class="mt-2 text-xs text-[#94a3b8]">
                            @if($wishlist->owner_id === auth()->id())
                                Мой список
                            @else
                                {{ $wishlist->owner->name }}
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
                <h3 class="text-base font-semibold text-[#1f2a37]">Пока пусто</h3>
                <p class="mt-2 text-sm text-[#6b7280]">
                    Создай первый вишлист или дождись приглашения.
                </p>
            </div>
        @endforelse
    </div>

    <div class="fixed inset-x-0 bottom-0 p-4">
        <a href="{{ route('wishlists.create') }}"
           class="block rounded-[20px] bg-[#1f2a37] px-5 py-4 text-center text-sm font-medium text-white shadow-lg">
            Создать вишлист
        </a>
    </div>
</div>