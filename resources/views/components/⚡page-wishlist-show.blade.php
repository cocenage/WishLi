<?php

use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistItemClaim;
use App\Models\WishlistMember;
use App\Services\Wishlist\WishlistAccessService;
use App\Services\Wishlist\WishlistInviteService;
use App\Services\Wishlist\WishlistItemService;
use App\Services\Wishlist\WishlistService;
use App\Services\Wishlist\WishlistTelegramService;
use Livewire\Component;

new class extends Component
{
    public Wishlist $wishlist;

    public ?int $selectedItemId = null;

    public bool $sheetOpen = false;
    public bool $claimFormOpen = false;

    public ?string $shareUrl = null;

    public string $search = '';
    public string $statusFilter = 'all';

    public string $claimStatus = 'reserved';
    public string $claimComment = '';

    public function mount(Wishlist $wishlist, WishlistAccessService $access): void
    {
        abort_unless($access->canView($wishlist, auth()->user()), 403);

        $this->wishlist = $wishlist;
        $this->reloadWishlist();
    }

    public function updatedSearch(): void
    {
        $this->reloadWishlist();
    }

    public function setStatusFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'free', 'reserved', 'purchased', 'hidden'], true)) {
            return;
        }

        $this->statusFilter = $filter;
        $this->reloadWishlist();
        $this->dispatch('telegram-haptic-impact');
    }

    protected function reloadWishlist(): void
    {
        $this->wishlist = $this->wishlist->fresh();

        $search = trim($this->search);
        $status = $this->statusFilter;
        $isOwner = $this->wishlist->owner_id === auth()->id();

        $this->wishlist->load([
            'owner',
            'items' => function ($query) use ($search, $status, $isOwner) {
                if (! $isOwner) {
                    $query->where('status', '!=', 'hidden');
                }

                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                            ->orWhere('store_name', 'like', "%{$search}%")
                            ->orWhere('category', 'like', "%{$search}%")
                            ->orWhere('note', 'like', "%{$search}%");
                    });
                }

                if ($status === 'free') {
                    $query->whereNotIn('status', ['purchased', 'hidden'])
                        ->whereDoesntHave('claims');
                }

                if ($status === 'reserved') {
                    $query->whereNotIn('status', ['purchased', 'hidden'])
                        ->whereHas('claims');
                }

                if ($status === 'purchased') {
                    $query->where('status', 'purchased');
                }

                if ($status === 'hidden') {
                    $query->where('status', 'hidden');
                }

                $query->with(['claims.user'])->latest();
            },
        ]);
    }

    public function openItem(int $itemId): void
    {
        $this->selectedItemId = $itemId;
        $this->sheetOpen = true;
        $this->claimFormOpen = false;
        $this->claimStatus = 'reserved';
        $this->claimComment = '';

        $this->dispatch('telegram-haptic-impact');
    }

    public function closeSheet(): void
    {
        $this->sheetOpen = false;
        $this->selectedItemId = null;
        $this->claimFormOpen = false;
        $this->claimStatus = 'reserved';
        $this->claimComment = '';
    }

    public function openClaimForm(): void
    {
        if (! $this->selectedItem) {
            return;
        }

        $existing = $this->selectedItem->claims->firstWhere('user_id', auth()->id());

        $this->claimStatus = $existing?->status ?: 'reserved';
        $this->claimComment = $existing?->comment ?: '';
        $this->claimFormOpen = true;
    }

    public function saveClaim(
        WishlistAccessService $access,
        WishlistItemService $items,
        WishlistTelegramService $telegram,
    ): void {
        if (! $this->selectedItem) {
            return;
        }

        abort_unless($access->canClaim($this->selectedItem, auth()->user()), 403);

        $validated = $this->validate([
            'claimStatus' => ['required', 'in:reserved,contribute,thinking,bought'],
            'claimComment' => ['nullable', 'string', 'max:255'],
        ]);

        $items->claim($this->selectedItem, auth()->user(), [
            'status' => $validated['claimStatus'],
            'comment' => $validated['claimComment'] ?: null,
        ]);

        $telegram->notifyClaimed(
            $this->selectedItem->fresh(['wishlist.owner']),
            auth()->user(),
            route('page-wishlist-show', ['wishlist' => $this->wishlist->id])
        );

        $itemId = $this->selectedItem->id;

        $this->reloadWishlist();

        $this->selectedItemId = $itemId;
        $this->claimFormOpen = false;

        $this->dispatch('telegram-haptic-success');
    }

    public function unclaimItem(
        int $itemId,
        WishlistItemService $items,
        WishlistTelegramService $telegram,
    ): void {
        $item = WishlistItem::query()
            ->with(['wishlist.owner'])
            ->findOrFail($itemId);

        $items->unclaim($item, auth()->user());

        $telegram->notifyUnclaimed(
            $item,
            auth()->user(),
            route('page-wishlist-show', ['wishlist' => $item->wishlist_id])
        );

        $this->reloadWishlist();

        $this->selectedItemId = $itemId;
        $this->claimFormOpen = false;
        $this->claimStatus = 'reserved';
        $this->claimComment = '';

        $this->dispatch('telegram-haptic-success');
    }

    public function generateInvite(WishlistInviteService $invites): void
    {
        abort_unless($this->wishlist->owner_id === auth()->id(), 403);

        $invite = $invites->getOrCreateActive($this->wishlist, auth()->user());

        $this->shareUrl = $invites->buildTelegramStartUrl($invite);

        $this->dispatch(
            'wishlist-share-ready',
            url: $this->shareUrl,
            title: $this->wishlist->title
        );
    }

    public function leaveWishlist(WishlistService $wishlists)
    {
        abort_if($this->wishlist->owner_id === auth()->id(), 403);

        $wishlists->leave($this->wishlist, auth()->user());

        return redirect()->route('page-wishlists');
    }

    public function closeWishlist(WishlistService $wishlists): void
    {
        abort_unless($this->wishlist->owner_id === auth()->id(), 403);

        $this->wishlist = $wishlists->close($this->wishlist);
        $this->reloadWishlist();

        $this->dispatch('telegram-haptic-success');
    }

    public function reopenWishlist(WishlistService $wishlists): void
    {
        abort_unless($this->wishlist->owner_id === auth()->id(), 403);

        $this->wishlist = $wishlists->reopen($this->wishlist);
        $this->reloadWishlist();

        $this->dispatch('telegram-haptic-success');
    }

    public function progress(): string
    {
        $total = $this->wishlist->items->count();

        if ($total === 0) {
            return '0/0';
        }

        $done = $this->wishlist->items
            ->filter(fn ($item) => $item->status === 'purchased' || $item->claims->count() > 0)
            ->count();

        return "{$done}/{$total}";
    }

    public function progressPercent(): int
    {
        $total = $this->wishlist->items->count();

        if ($total === 0) {
            return 0;
        }

        $done = $this->wishlist->items
            ->filter(fn ($item) => $item->status === 'purchased' || $item->claims->count() > 0)
            ->count();

        return (int) round(($done / $total) * 100);
    }

    public function statusLabel(WishlistItem $item): string
    {
        if ($item->status === 'hidden') {
            return 'Скрыто';
        }

        if ($item->status === 'purchased') {
            return 'Куплено';
        }

        if ($item->status === 'postponed') {
            return 'Отложено';
        }

        $count = $item->claims->count();

        if ($count === 0) {
            return 'Свободно';
        }

        return $count === 1 ? 'Зарезервировано' : "Броней: {$count}";
    }

    public function claimStatusLabel(?string $status): string
    {
        return match ($status) {
            'reserved' => 'Забронировал',
            'contribute' => 'Участвую',
            'thinking' => 'Думаю',
            'bought' => 'Уже купил',
            default => 'Участник',
        };
    }

    public function cardClass(WishlistItem $item): string
    {
        $isMine = $item->claims->where('user_id', auth()->id())->count() > 0;

        if ($item->status === 'purchased') {
            return 'bg-[#D6E7CF]';
        }

        if ($item->status === 'hidden') {
            return 'bg-[#D8D2C8] opacity-70';
        }

        if ($isMine) {
            return 'bg-[#171717] text-white';
        }

        if ($item->claims->count() > 0) {
            return 'bg-[#C9AE8D]';
        }

        return 'bg-white/80';
    }

    public function getSelectedItemProperty(): ?WishlistItem
    {
        if (! $this->selectedItemId) {
            return null;
        }

        return $this->wishlist->items->firstWhere('id', $this->selectedItemId);
    }

    public function getUserClaimProperty(): ?WishlistItemClaim
    {
        if (! $this->selectedItem) {
            return null;
        }

        return $this->selectedItem->claims->firstWhere('user_id', auth()->id());
    }
};
?>

<div
    x-data="{ sheetOpen: @entangle('sheetOpen') }"
    class="min-h-screen pb-[120px]"
>
    <div class="mx-auto w-full max-w-[430px] px-5 pt-5">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-[62px] font-semibold leading-[0.84] tracking-[-0.09em] text-[#171717]">
                    {{ $wishlist->emoji ?: '🎁' }}<br>
                    Wish
                </h1>

                <div class="mt-5 flex items-center gap-2 border-b border-[#D6D2C8] pb-3">
                    <span class="h-2 w-2 rounded-full bg-[#24D66B]"></span>
                    <span class="max-w-[210px] truncate text-[14px] text-[#393934]">
                        {{ $wishlist->owner?->name }}
                    </span>
                </div>
            </div>

            <a
                href="{{ route('page-wishlists') }}"
                class="flex h-[38px] w-[38px] items-center justify-center rounded-full border border-[#1B1B1B]/30 text-[17px]"
            >
                ←
            </a>
        </div>

        <div class="mt-6 overflow-hidden rounded-[30px] bg-[#C9AE8D] p-5 shadow-[0_14px_34px_rgba(0,0,0,0.06)]">
            <div class="min-h-[128px]">
                <div class="text-[34px] font-semibold leading-[0.86] tracking-[-0.08em] text-[#5A5348]/80">
                    {{ $wishlist->title }}
                </div>

                @if($wishlist->description)
                    <div class="mt-4 max-w-[310px] text-[14px] leading-[1.25] text-[#2D2924]/65">
                        {{ $wishlist->description }}
                    </div>
                @endif
            </div>

            <div class="mt-4 border-t border-[#171717]/10 pt-3">
                <div class="flex items-center justify-between text-[12px] text-[#2D2924]/65">
                    <div>{{ $this->progress() }} gifts</div>

                    @if($wishlist->event_date)
                        <div>{{ $wishlist->event_date->format('d.m.Y') }}</div>
                    @endif
                </div>

                <div class="mt-3 h-[7px] w-full rounded-full bg-[#171717]/10">
                    <div
                        class="h-[7px] rounded-full bg-[#171717]/70"
                        style="width: {{ $this->progressPercent() }}%;"
                    ></div>
                </div>
            </div>
        </div>

        <div class="mt-4 flex gap-2">
            @if($wishlist->owner_id === auth()->id() || $wishlist->allow_item_addition)
                <a
                    href="{{ route('page-wishlist-item-create', ['wishlist' => $wishlist->id]) }}"
                    class="flex h-[54px] flex-1 items-center justify-center rounded-[22px] bg-[#171717] text-[14px] font-medium text-white"
                >
                    Добавить
                </a>
            @endif

            @if($wishlist->owner_id === auth()->id())
                <button
                    wire:click="generateInvite"
                    class="flex h-[54px] items-center justify-center rounded-[22px] bg-white/70 px-5 text-[14px] font-medium"
                >
                    Share
                </button>

                <a
                    href="{{ route('page-wishlist-edit', ['wishlist' => $wishlist->id]) }}"
                    class="flex h-[54px] items-center justify-center rounded-[22px] bg-white/70 px-5 text-[14px] font-medium"
                >
                    Edit
                </a>
            @else
                <button
                    wire:click="leaveWishlist"
                    class="flex h-[54px] items-center justify-center rounded-[22px] bg-white/70 px-5 text-[14px] font-medium"
                >
                    Leave
                </button>
            @endif
        </div>

        @if($shareUrl)
            <div class="mt-3 rounded-[24px] bg-white/70 p-3">
                <div class="break-all text-[12px] text-[#77736B]">
                    {{ $shareUrl }}
                </div>
            </div>
        @endif

        <div class="mt-5 rounded-[28px] bg-white/65 p-3 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search gift"
                class="h-[48px] w-full rounded-[20px] border-0 bg-[#F4F3EF] px-4 text-[14px] outline-none placeholder:text-[#99938A]"
            >

            <div class="mt-3 flex gap-2 overflow-x-auto no-scrollbar">
                <button wire:click="setStatusFilter('all')" class="h-[36px] shrink-0 rounded-full px-4 text-[13px] {{ $statusFilter === 'all' ? 'bg-[#171717] text-white' : 'bg-[#ECE8DE]' }}">Все</button>
                <button wire:click="setStatusFilter('free')" class="h-[36px] shrink-0 rounded-full px-4 text-[13px] {{ $statusFilter === 'free' ? 'bg-[#171717] text-white' : 'bg-[#ECE8DE]' }}">Свободные</button>
                <button wire:click="setStatusFilter('reserved')" class="h-[36px] shrink-0 rounded-full px-4 text-[13px] {{ $statusFilter === 'reserved' ? 'bg-[#171717] text-white' : 'bg-[#ECE8DE]' }}">Занятые</button>
                <button wire:click="setStatusFilter('purchased')" class="h-[36px] shrink-0 rounded-full px-4 text-[13px] {{ $statusFilter === 'purchased' ? 'bg-[#171717] text-white' : 'bg-[#ECE8DE]' }}">Купленные</button>

                @if($wishlist->owner_id === auth()->id())
                    <button wire:click="setStatusFilter('hidden')" class="h-[36px] shrink-0 rounded-full px-4 text-[13px] {{ $statusFilter === 'hidden' ? 'bg-[#171717] text-white' : 'bg-[#ECE8DE]' }}">Скрытые</button>
                @endif
            </div>
        </div>

        <div class="mt-4 space-y-3">
            @forelse($wishlist->items as $item)
                <button
                    wire:click="openItem({{ $item->id }})"
                    class="block w-full overflow-hidden rounded-[28px] p-4 text-left shadow-[0_12px_30px_rgba(0,0,0,0.045)] {{ $this->cardClass($item) }}"
                >
                    <div class="flex gap-4">
                        <div class="h-[96px] w-[96px] shrink-0 overflow-hidden rounded-[22px] bg-black/5">
                            @if($item->image_url)
                                <img src="{{ $item->image_url }}" alt="" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-[32px]">
                                    🎁
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="max-h-[58px] overflow-hidden text-[27px] font-semibold leading-[0.88] tracking-[-0.07em]">
                                {{ $item->title }}
                            </div>

                            @if($item->price)
                                <div class="mt-2 text-[13px] opacity-65">
                                    {{ number_format((float) $item->price, 0, ',', ' ') }} {{ $item->currency }}
                                </div>
                            @endif

                            <div class="mt-3 text-[12px] opacity-65">
                                {{ $this->statusLabel($item) }}
                            </div>
                        </div>
                    </div>
                </button>
            @empty
                <div class="rounded-[30px] bg-white/65 p-8 text-center">
                    <div class="text-[38px] font-semibold leading-none tracking-[-0.07em]">
                        Empty
                    </div>

                    <div class="mt-3 text-[14px] text-[#77736B]">
                        Пока нет подарков
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    <div
        x-show="sheetOpen"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-black/35 backdrop-blur-sm"
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
        class="fixed inset-x-0 bottom-0 z-50 mx-auto w-full max-w-[430px]"
    >
        <div class="rounded-t-[36px] bg-[#F4F3EF] p-5 shadow-[0_-18px_50px_rgba(0,0,0,0.18)]">
            <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-[#CFC8BD]"></div>

            @if($this->selectedItem)
                <div class="h-[220px] overflow-hidden rounded-[28px] bg-white/70">
                    @if($this->selectedItem->image_url)
                        <img src="{{ $this->selectedItem->image_url }}" alt="" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center text-[54px]">
                            🎁
                        </div>
                    @endif
                </div>

                <div class="mt-5 text-[40px] font-semibold leading-[0.88] tracking-[-0.08em]">
                    {{ $this->selectedItem->title }}
                </div>

                @if($this->selectedItem->price)
                    <div class="mt-3 text-[15px] text-[#77736B]">
                        {{ number_format((float) $this->selectedItem->price, 0, ',', ' ') }} {{ $this->selectedItem->currency }}
                    </div>
                @endif

                @if($this->selectedItem->note)
                    <div class="mt-4 rounded-[24px] bg-white/70 p-4 text-[14px] leading-[1.35] text-[#5F5A52]">
                        {{ $this->selectedItem->note }}
                    </div>
                @endif

                <div class="mt-4 text-[13px] text-[#77736B]">
                    {{ $this->statusLabel($this->selectedItem) }}
                </div>

                @if(! $wishlist->hide_claimers && $this->selectedItem->claims->count())
                    <div class="mt-3 space-y-2">
                        @foreach($this->selectedItem->claims as $claim)
                            <div class="rounded-[22px] bg-white/70 px-4 py-3">
                                <div class="text-[14px] font-medium">
                                    {{ $claim->user?->name }}
                                </div>

                                <div class="mt-1 text-[12px] text-[#77736B]">
                                    {{ $this->claimStatusLabel($claim->status) }}
                                </div>

                                @if($claim->comment)
                                    <div class="mt-1 text-[12px] text-[#77736B]">
                                        {{ $claim->comment }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-6">
                    @if($wishlist->owner_id === auth()->id())
                        <div class="flex gap-2">
                            <a
                                href="{{ route('page-wishlist-item-edit', ['wishlist' => $wishlist->id, 'item' => $this->selectedItem->id]) }}"
                                class="flex h-[58px] flex-1 items-center justify-center rounded-[24px] bg-[#171717] text-[14px] font-medium text-white"
                            >
                                Изменить
                            </a>

                            @if($this->selectedItem->url)
                                <a
                                    href="{{ $this->selectedItem->url }}"
                                    target="_blank"
                                    class="flex h-[58px] items-center justify-center rounded-[24px] bg-white/70 px-5 text-[14px] font-medium"
                                >
                                    Open
                                </a>
                            @endif
                        </div>
                    @elseif($wishlist->is_closed)
                        <div class="rounded-[24px] bg-[#171717] px-4 py-4 text-[14px] text-white/80">
                            Вишлист закрыт
                        </div>
                    @else
                        @if(! $this->userClaim)
                            @if(! $claimFormOpen)
                                <div class="flex gap-2">
                                    <button
                                        wire:click="openClaimForm"
                                        class="flex h-[58px] flex-1 items-center justify-center rounded-[24px] bg-[#171717] text-[14px] font-medium text-white"
                                    >
                                        Забронировать
                                    </button>

                                    @if($this->selectedItem->url)
                                        <a
                                            href="{{ $this->selectedItem->url }}"
                                            target="_blank"
                                            class="flex h-[58px] items-center justify-center rounded-[24px] bg-white/70 px-5 text-[14px] font-medium"
                                        >
                                            Open
                                        </a>
                                    @endif
                                </div>
                            @else
                                <div class="space-y-3 rounded-[28px] bg-[#171717] p-4">
                                    <select
                                        wire:model.defer="claimStatus"
                                        class="h-[52px] w-full rounded-[20px] border-0 bg-white/10 px-4 text-[14px] text-white outline-none"
                                    >
                                        <option value="reserved">Забронировать</option>
                                        <option value="contribute">Хочу участвовать</option>
                                        <option value="thinking">Думаю</option>
                                        <option value="bought">Уже купил</option>
                                    </select>

                                    <textarea
                                        wire:model.defer="claimComment"
                                        rows="3"
                                        placeholder="Комментарий"
                                        class="w-full rounded-[20px] border-0 bg-white/10 px-4 py-3 text-[14px] text-white outline-none placeholder:text-white/40"
                                    ></textarea>

                                    <div class="flex gap-2">
                                        <button
                                            wire:click="saveClaim"
                                            class="flex h-[52px] flex-1 items-center justify-center rounded-[20px] bg-white text-[14px] font-medium text-[#171717]"
                                        >
                                            Сохранить
                                        </button>

                                        <button
                                            wire:click="$set('claimFormOpen', false)"
                                            class="flex h-[52px] items-center justify-center rounded-[20px] bg-white/10 px-5 text-[14px] font-medium text-white"
                                        >
                                            Назад
                                        </button>
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="flex gap-2">
                                <button
                                    wire:click="openClaimForm"
                                    class="flex h-[58px] flex-1 items-center justify-center rounded-[24px] bg-[#171717] text-[14px] font-medium text-white"
                                >
                                    Изменить бронь
                                </button>

                                <button
                                    wire:click="unclaimItem({{ $this->selectedItem->id }})"
                                    class="flex h-[58px] items-center justify-center rounded-[24px] bg-white/70 px-5 text-[14px] font-medium"
                                >
                                    Отмена
                                </button>
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>