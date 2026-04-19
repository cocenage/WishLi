<?php

use Livewire\Component;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistItemClaim;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

new class extends Component {
    use AuthorizesRequests;

    public Wishlist $wishlist;
    public ?int $selectedItemId = null;
    public bool $sheetOpen = false;

    public function mount(Wishlist $wishlist): void
    {
        $this->wishlist = $wishlist->load([
            'owner',
            'items.claims.user',
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
        $item = WishlistItem::query()->findOrFail($itemId);

        WishlistItemClaim::query()->firstOrCreate([
            'wishlist_item_id' => $item->id,
            'user_id' => auth()->id(),
        ]);

        $this->wishlist->refresh()->load(['owner', 'items.claims.user']);
        $this->selectedItemId = $itemId;
    }

    public function unclaimItem(int $itemId): void
    {
        WishlistItemClaim::query()
            ->where('wishlist_item_id', $itemId)
            ->where('user_id', auth()->id())
            ->delete();

        $this->wishlist->refresh()->load(['owner', 'items.claims.user']);
        $this->selectedItemId = $itemId;
    }

    public function getSelectedItemProperty(): ?WishlistItem
    {
        if (!$this->selectedItemId) {
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
                        {{ $wishlist->emoji }} {{ $wishlist->title }}
                    </h1>

                    @if($wishlist->description)
                        <p class="mt-2 text-sm text-[#6b7280]">
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
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <a href="{{ route('wishlists.items.create', $wishlist) }}"
                   class="flex-1 rounded-2xl bg-[#1f2a37] px-4 py-3 text-center text-sm font-medium text-white">
                    Добавить подарок
                </a>

                <button
                    type="button"
                    class="rounded-2xl bg-[#eef2f7] px-4 py-3 text-sm font-medium text-[#1f2a37]"
                >
                    Поделиться
                </button>
            </div>
        </div>
    </div>

    <div class="mt-5 px-4 space-y-3">
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
                        <h2 class="text-sm font-semibold text-[#1f2a37] line-clamp-2">
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
        class="fixed inset-x-0 bottom-0 z-50 rounded-t-[32px] bg-white p-5"
    >
        @if($this->selectedItem)
            <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-[#dbe3ec]"></div>

            <div class="h-52 overflow-hidden rounded-[24px] bg-[#eef2f7]">
                @if($this->selectedItem->image_url)
                    <img src="{{ $this->selectedItem->image_url }}" alt="" class="h-full w-full object-cover">
                @endif
            </div>

            <h3 class="mt-4 text-lg font-semibold text-[#1f2a37]">
                {{ $this->selectedItem->title }}
            </h3>

            @if($this->selectedItem->price)
                <div class="mt-2 text-base font-medium text-[#111827]">
                    {{ number_format((float) $this->selectedItem->price, 0, ',', ' ') }} {{ $this->selectedItem->currency }}
                </div>
            @endif

            @if($this->selectedItem->description)
                <p class="mt-3 text-sm text-[#6b7280]">
                    {{ $this->selectedItem->description }}
                </p>
            @endif

            <div class="mt-4 text-sm text-[#6b7280]">
                Уже хотят подарить: {{ $this->selectedItem->claims->count() }}
            </div>

            @if($this->selectedItem->claims->count())
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($this->selectedItem->claims as $claim)
                        <div class="rounded-full bg-[#eef2f7] px-3 py-1 text-xs text-[#1f2a37]">
                            {{ $claim->user->name }}
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-5 flex gap-2">
                @if($this->selectedItem->isClaimedBy(auth()->user()))
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
                    <a href="{{ $this->selectedItem->url }}"
                       target="_blank"
                       class="rounded-2xl bg-[#eef2f7] px-4 py-3 text-sm font-medium text-[#1f2a37]">
                        Открыть
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>