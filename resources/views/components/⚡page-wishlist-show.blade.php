<?php

use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistItemClaim;
use App\Models\WishlistInvite;
use App\Models\WishlistMember;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public Wishlist $wishlist;

    public ?int $selectedItemId = null;
    public bool $sheetOpen = false;
    public ?string $shareUrl = null;

    public string $search = '';
    public string $priorityFilter = 'all';
    public string $sort = 'latest';

    public function mount(Wishlist $wishlist): void
    {
        $this->wishlist = $wishlist;
        $this->reloadWishlist();
    }

    public function updatedSearch(): void
    {
        $this->reloadWishlist();
    }

    public function updatedPriorityFilter(): void
    {
        $this->reloadWishlist();
    }

    public function updatedSort(): void
    {
        $this->reloadWishlist();
    }

    protected function reloadWishlist(): void
    {
        $this->wishlist = $this->wishlist->fresh();

        $search = $this->search;
        $priority = $this->priorityFilter;
        $sort = $this->sort;

        $this->wishlist->load([
            'owner',
            'memberLinks.user',
            'items' => function ($query) use ($search, $priority, $sort) {
                if ($this->wishlist->owner_id !== auth()->id()) {
                    $query->where('is_hidden', false);
                }

                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'like', '%' . $search . '%')
                            ->orWhere('description', 'like', '%' . $search . '%')
                            ->orWhere('note', 'like', '%' . $search . '%')
                            ->orWhere('store_name', 'like', '%' . $search . '%');
                    });
                }

                if ($priority !== 'all') {
                    $query->where('priority', $priority);
                }

                match ($sort) {
                    'price_asc' => $query->orderBy('price'),
                    'price_desc' => $query->orderByDesc('price'),
                    'priority' => $query->orderByRaw("
                        case
                            when priority = 'high' then 1
                            when priority = 'medium' then 2
                            else 3
                        end
                    "),
                    default => $query->latest(),
                };

                $query->with('claims.user');
            },
        ]);
    }

    public function openItem(int $itemId): void
    {
        $this->selectedItemId = $itemId;
        $this->sheetOpen = true;
    }

    public function closeSheet(): void
    {
        $this->sheetOpen = false;
        $this->selectedItemId = null;
    }

    public function claimItem(int $itemId): void
    {
        $item = WishlistItem::query()
            ->with('wishlist', 'claims')
            ->findOrFail($itemId);

        if (! $item->wishlist->allow_multi_claim && $item->claims()->exists()) {
            return;
        }

        WishlistItemClaim::query()->firstOrCreate([
            'wishlist_item_id' => $item->id,
            'user_id' => auth()->id(),
        ]);

        $this->reloadWishlist();
        $this->selectedItemId = $itemId;
    }

    public function unclaimItem(int $itemId): void
    {
        WishlistItemClaim::query()
            ->where('wishlist_item_id', $itemId)
            ->where('user_id', auth()->id())
            ->delete();

        $this->reloadWishlist();
        $this->selectedItemId = $itemId;
    }

    public function generateInvite(): void
    {
        abort_unless($this->wishlist->owner_id === auth()->id(), 403);

        $invite = $this->wishlist->invites()
            ->where('is_active', true)
            ->latest()
            ->first();

        if (! $invite) {
            $invite = $this->wishlist->invites()->create([
                'created_by' => auth()->id(),
                'token' => Str::random(40),
            ]);
        }

        $this->shareUrl = route('page-wishlist-invite', ['token' => $invite->token]);

        $this->dispatch('wishlist-share-ready', url: $this->shareUrl, title: $this->wishlist->title);
    }

    public function leaveWishlist()
    {
        abort_if($this->wishlist->owner_id === auth()->id(), 403);

        WishlistMember::query()
            ->where('wishlist_id', $this->wishlist->id)
            ->where('user_id', auth()->id())
            ->delete();

        return redirect()->route('page-wishlists');
    }

    public function getSelectedItemProperty(): ?WishlistItem
    {
        if (! $this->selectedItemId) {
            return null;
        }

        return $this->wishlist->items->firstWhere('id', $this->selectedItemId);
    }
};
?>

<div
    x-data="{ sheetOpen: @entangle('sheetOpen') }"
    class="min-h-screen bg-[#f4f7fb] pb-24"
>
    <div class="px-4 pt-4">
        <div class="rounded-[28px] bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-[#1f2a37]">
                        {{ $wishlist->emoji ?: '🎁' }} {{ $wishlist->title }}
                    </h1>

                    @if($wishlist->description)
                        <p class="mt-2 text-sm text-[#6b7280]">
                            {{ $wishlist->description }}
                        </p>
                    @endif

                    <div class="mt-3 flex flex-wrap gap-2 text-xs text-[#6b7280]">
                        <span>{{ $wishlist->items->count() }} товаров</span>
                        <span>•</span>
                        <span>{{ $wishlist->memberLinks->where('status', 'accepted')->count() }} участников</span>

                        @if($wishlist->event_date)
                            <span>•</span>
                            <span>до {{ $wishlist->event_date->format('d.m.Y') }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <a
                    href="{{ route('page-wishlist-item-create', ['wishlist' => $wishlist->id]) }}"
                    class="flex-1 rounded-2xl bg-[#1f2a37] px-4 py-3 text-center text-sm font-medium text-white"
                >
                    Добавить подарок
                </a>

                @if($wishlist->owner_id === auth()->id())
                    <button
                        type="button"
                        wire:click="generateInvite"
                        class="rounded-2xl bg-[#eef2f7] px-4 py-3 text-sm font-medium text-[#1f2a37]"
                    >
                        Поделиться
                    </button>

                    <a
                        href="{{ route('page-wishlist-edit', ['wishlist' => $wishlist->id]) }}"
                        class="rounded-2xl bg-[#eef2f7] px-4 py-3 text-sm font-medium text-[#1f2a37]"
                    >
                        Изм.
                    </a>
                @else
                    <button
                        wire:click="leaveWishlist"
                        class="rounded-2xl bg-[#eef2f7] px-4 py-3 text-sm font-medium text-[#1f2a37]"
                    >
                        Выйти
                    </button>
                @endif
            </div>

            @if($shareUrl)
                <div class="mt-3 break-all rounded-2xl bg-[#eef2f7] px-4 py-3 text-sm text-[#1f2a37]">
                    {{ $shareUrl }}
                </div>
            @endif
        </div>
    </div>

    <div class="mt-4 px-4">
        <div class="space-y-3 rounded-[24px] bg-white p-4 shadow-sm">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Поиск по подаркам"
                class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm"
            >

            <div class="grid grid-cols-2 gap-3">
                <select
                    wire:model.live="priorityFilter"
                    class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm"
                >
                    <option value="all">Все приоритеты</option>
                    <option value="high">Высокий</option>
                    <option value="medium">Средний</option>
                    <option value="low">Низкий</option>
                </select>

                <select
                    wire:model.live="sort"
                    class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm"
                >
                    <option value="latest">Сначала новые</option>
                    <option value="price_asc">Сначала дешевле</option>
                    <option value="price_desc">Сначала дороже</option>
                    <option value="priority">По приоритету</option>
                </select>
            </div>
        </div>
    </div>

    <div class="mt-5 space-y-3 px-4">
        @forelse($wishlist->items as $item)
            <button
                wire:click="openItem({{ $item->id }})"
                class="w-full rounded-[24px] bg-white p-4 text-left shadow-sm"
            >
                <div class="flex gap-4">
                    <div class="h-24 w-24 shrink-0 overflow-hidden rounded-[18px] bg-[#eef2f7]">
                        @if($item->image_url)
                            <img src="{{ $item->image_url }}" alt="" class="h-full w-full object-cover">
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2 class="line-clamp-2 text-sm font-semibold text-[#1f2a37]">
                            {{ $item->title }}
                        </h2>

                        @if($item->price)
                            <div class="mt-2 text-sm font-medium text-[#111827]">
                                {{ number_format((float) $item->price, 0, ',', ' ') }} {{ $item->currency }}
                            </div>
                        @endif

                        @if($item->store_name)
                            <div class="mt-2 text-xs text-[#6b7280]">
                                {{ $item->store_name }}
                            </div>
                        @endif

                        <div class="mt-2 flex items-center gap-2">
                            @if($item->priority === 'high')
                                <span class="inline-flex rounded-full bg-[#fee2e2] px-2 py-1 text-[11px] font-medium text-[#991b1b]">
                                    Очень хочу
                                </span>
                            @elseif($item->priority === 'medium')
                                <span class="inline-flex rounded-full bg-[#fef3c7] px-2 py-1 text-[11px] font-medium text-[#92400e]">
                                    Средний
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-[#e5e7eb] px-2 py-1 text-[11px] font-medium text-[#374151]">
                                    Низкий
                                </span>
                            @endif
                        </div>

                        <div class="mt-3 text-xs text-[#6b7280]">
                            Хотят подарить: {{ $item->claims->count() }}
                        </div>
                    </div>
                </div>
            </button>
        @empty
            <div class="rounded-[24px] bg-white p-8 text-center shadow-sm">
                <h3 class="text-base font-semibold text-[#1f2a37]">Товаров пока нет</h3>
                <p class="mt-2 text-sm text-[#6b7280]">Добавь первый подарок в этот вишлист.</p>
            </div>
        @endforelse
    </div>

    <div
        x-show="sheetOpen"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm"
        @click="$wire.closeSheet()"
    ></div>

    <div
        x-show="sheetOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        class="fixed inset-x-0 bottom-0 z-50 mx-auto w-full max-w-[768px]"
    >
        <div class="rounded-t-[32px] bg-white p-5 shadow-2xl">
            <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-[#dbe3ec]"></div>

            @if($this->selectedItem)
                <div class="h-52 overflow-hidden rounded-[24px] bg-[#eef2f7]">
                    @if($this->selectedItem->image_url)
                        <img src="{{ $this->selectedItem->image_url }}" alt="" class="h-full w-full object-cover">
                    @endif
                </div>

                <div class="mt-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-[#1f2a37]">
                            {{ $this->selectedItem->title }}
                        </h3>

                        @if($this->selectedItem->store_name)
                            <div class="mt-1 text-sm text-[#6b7280]">
                                {{ $this->selectedItem->store_name }}
                            </div>
                        @endif
                    </div>

                    @if($wishlist->owner_id === auth()->id() || $this->selectedItem->created_by === auth()->id())
                        <a
                            href="{{ route('page-wishlist-item-edit', ['wishlist' => $wishlist->id, 'item' => $this->selectedItem->id]) }}"
                            class="rounded-2xl bg-[#eef2f7] px-3 py-2 text-xs font-medium text-[#1f2a37]"
                        >
                            Изменить
                        </a>
                    @endif
                </div>

                @if($this->selectedItem->price)
                    <div class="mt-3 text-base font-medium text-[#111827]">
                        {{ number_format((float) $this->selectedItem->price, 0, ',', ' ') }} {{ $this->selectedItem->currency }}
                    </div>
                @endif

                @if($this->selectedItem->description)
                    <p class="mt-4 text-sm text-[#6b7280]">
                        {{ $this->selectedItem->description }}
                    </p>
                @endif

                @if($this->selectedItem->note)
                    <div class="mt-4 rounded-2xl bg-[#eef2f7] px-4 py-3 text-sm text-[#1f2a37]">
                        {{ $this->selectedItem->note }}
                    </div>
                @endif

                <div class="mt-4 text-sm text-[#6b7280]">
                    Уже хотят подарить: {{ $this->selectedItem->claims->count() }}
                </div>

                @if($this->selectedItem->claims->count())
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($this->selectedItem->claims as $claim)
                            <div class="rounded-full bg-[#eef2f7] px-3 py-1 text-xs text-[#1f2a37]">
                                {{ $claim->user?->name }}
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5 flex gap-2">
                    @if($this->selectedItem->claims->where('user_id', auth()->id())->count())
                        <button
                            wire:click="unclaimItem({{ $this->selectedItem->id }})"
                            class="flex-1 rounded-2xl bg-[#eef2f7] px-4 py-3 text-sm font-medium text-[#1f2a37]"
                        >
                            Ты участвуешь
                        </button>
                    @else
                        <button
                            wire:click="claimItem({{ $this->selectedItem->id }})"
                            class="flex-1 rounded-2xl bg-[#1f2a37] px-4 py-3 text-sm font-medium text-white"
                        >
                            Подарить
                        </button>
                    @endif

                    @if($this->selectedItem->url)
                        <a
                            href="{{ $this->selectedItem->url }}"
                            target="_blank"
                            class="rounded-2xl bg-[#eef2f7] px-4 py-3 text-sm font-medium text-[#1f2a37]"
                        >
                            Открыть
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('wishlist-share-ready', ({ url, title }) => {
            const text = encodeURIComponent(`Присоединяйся к моему вишлисту: ${title}`);
            const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${text}`;

            if (window.Telegram?.WebApp?.openTelegramLink) {
                window.Telegram.WebApp.openTelegramLink(shareUrl);
                return;
            }

            window.open(shareUrl, '_blank');
        });
    });
</script>