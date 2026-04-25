<?php

use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistItemClaim;
use App\Models\WishlistMember;
use App\Services\Wishlist\WishlistTelegramService;
use Illuminate\Support\Str;
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
    public string $claimStatus = 'gifting';
    public string $claimComment = '';

    public function mount(Wishlist $wishlist): void
    {
        $this->wishlist = $wishlist;
        $this->reloadWishlist();
    }

    public function updatedSearch(): void
    {
        $this->reloadWishlist();
    }

    public function updatedStatusFilter(): void
    {
        $this->reloadWishlist();
    }

    protected function reloadWishlist(): void
    {
        $this->wishlist = $this->wishlist->fresh();

        $search = $this->search;
        $status = $this->statusFilter;

        $this->wishlist->load([
            'owner',
            'items' => function ($query) use ($search, $status) {
                if ($this->wishlist->owner_id !== auth()->id()) {
                    $query->where('is_hidden', false);
                }

                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'like', '%' . $search . '%')
                            ->orWhere('store_name', 'like', '%' . $search . '%')
                            ->orWhere('note', 'like', '%' . $search . '%');
                    });
                }

                if ($status === 'free') {
                    $query->where('is_purchased', false)
                        ->whereDoesntHave('claims');
                }

                if ($status === 'claimed') {
                    $query->where('is_purchased', false)
                        ->whereHas('claims');
                }

                if ($status === 'purchased') {
                    $query->where('is_purchased', true);
                }

                $query->with('claims.user')->latest();
            },
        ]);
    }

    public function openItem(int $itemId): void
    {
        $this->selectedItemId = $itemId;
        $this->sheetOpen = true;
        $this->claimFormOpen = false;
        $this->claimStatus = 'gifting';
        $this->claimComment = '';
    }

    public function closeSheet(): void
    {
        $this->sheetOpen = false;
        $this->selectedItemId = null;
        $this->claimFormOpen = false;
        $this->claimStatus = 'gifting';
        $this->claimComment = '';
    }

    public function openClaimForm(): void
    {
        if (! $this->selectedItem) {
            return;
        }

        $existing = $this->selectedItem->claims->firstWhere('user_id', auth()->id());

        $this->claimStatus = $existing?->status ?: 'gifting';
        $this->claimComment = $existing?->comment ?: '';
        $this->claimFormOpen = true;
    }

    public function saveClaim(WishlistTelegramService $telegram): void
    {
        if (! $this->selectedItem) {
            return;
        }

        if ($this->selectedItem->wishlist->owner_id === auth()->id()) {
            return;
        }

        if ($this->closed()) {
            return;
        }

        if (! $this->selectedItem->wishlist->allow_multi_claim) {
            $otherClaimExists = $this->selectedItem->claims
                ->where('user_id', '!=', auth()->id())
                ->count() > 0;

            if ($otherClaimExists) {
                return;
            }
        }

        $validated = $this->validate([
            'claimStatus' => ['required', 'in:gifting,contribute,thinking,bought'],
            'claimComment' => ['nullable', 'string', 'max:255'],
        ]);

        $claim = WishlistItemClaim::query()->updateOrCreate(
            [
                'wishlist_item_id' => $this->selectedItem->id,
                'user_id' => auth()->id(),
            ],
            [
                'status' => $validated['claimStatus'],
                'comment' => $validated['claimComment'] ?: null,
            ]
        );

        $telegram->notifyClaimed($this->selectedItem->fresh('wishlist.owner'), auth()->user());

        $this->reloadWishlist();
        $this->selectedItemId = $this->selectedItem->id;
        $this->claimFormOpen = false;
    }

    public function unclaimItem(int $itemId, WishlistTelegramService $telegram): void
    {
        $item = WishlistItem::query()->with('wishlist.owner')->findOrFail($itemId);

        WishlistItemClaim::query()
            ->where('wishlist_item_id', $itemId)
            ->where('user_id', auth()->id())
            ->delete();

        $telegram->notifyUnclaimed($item, auth()->user());

        $this->reloadWishlist();
        $this->selectedItemId = $itemId;
        $this->claimFormOpen = false;
        $this->claimStatus = 'gifting';
        $this->claimComment = '';
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

    $botUsername = config('services.telegram.bot_username');

    if ($botUsername) {
        $this->shareUrl = "https://t.me/{$botUsername}/app?startapp=invite_{$invite->token}";
    } else {
        $this->shareUrl = route('page-wishlist-invite', ['token' => $invite->token]);
    }

    $this->dispatch(
        'wishlist-share-ready',
        url: $this->shareUrl,
        title: $this->wishlist->title
    );
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

    public function closed(): bool
    {
        return (bool) $this->wishlist->is_closed
            || ($this->wishlist->event_date && $this->wishlist->event_date->isPast());
    }

    public function progress(): string
    {
        $total = $this->wishlist->items->count();

        if ($total === 0) {
            return '0/0';
        }

        $done = $this->wishlist->items
            ->filter(fn ($item) => $item->is_purchased || $item->claims->count() > 0)
            ->count();

        return $done . '/' . $total;
    }

    public function progressPercent(): int
    {
        $total = $this->wishlist->items->count();

        if ($total === 0) {
            return 0;
        }

        $done = $this->wishlist->items
            ->filter(fn ($item) => $item->is_purchased || $item->claims->count() > 0)
            ->count();

        return (int) round(($done / $total) * 100);
    }

    public function statusLabel(WishlistItem $item): string
    {
        if ($item->is_purchased) {
            return 'Purchased';
        }

        $count = $item->claims->count();

        if ($count === 0) {
            return 'Free';
        }

        if ($count === 1) {
            return '1 joined';
        }

        return $count . ' joined';
    }

    public function cardClass(WishlistItem $item): string
    {
        $isMine = $item->claims->where('user_id', auth()->id())->count() > 0;

        if ($item->is_purchased) {
            return 'bg-[#DDF4E4]';
        }

        if ($isMine) {
            return 'bg-[#111111] text-white';
        }

        if ($item->claims->count() > 0) {
            return 'bg-[#F3EE7A]';
        }

        return 'bg-white';
    }

    public function claimStatusLabel(?string $status): string
    {
        return match ($status) {
            'gifting' => 'Gift myself',
            'contribute' => 'Contribute',
            'thinking' => 'Thinking',
            'bought' => 'Already bought',
            default => 'Joined',
        };
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
    class="min-h-screen bg-[#F3F0E8] pb-24 text-[#111111]"
>
    <div class="px-4 pt-4">
        <div class="rounded-[32px] bg-white p-5">
            <div class="text-[12px] uppercase tracking-[0.18em] text-[#8B8B8B]">
                wishlist
            </div>

            <h1 class="mt-2 text-[44px] font-semibold leading-[0.9]">
                {{ $wishlist->title }}
            </h1>

            <div class="mt-3 flex items-center gap-2 text-sm text-[#666666]">
                <span>{{ $this->progress() }}</span>

                @if($wishlist->event_date)
                    <span>•</span>
                    <span>{{ $wishlist->event_date->format('d.m') }}</span>
                @endif

                @if($this->closed())
                    <span>•</span>
                    <span>Closed</span>
                @endif
            </div>

            <div class="mt-4 h-2 w-full rounded-full bg-[#ECE7DD]">
                <div
                    class="h-2 rounded-full bg-[#111111]"
                    style="width: {{ $this->progressPercent() }}%;"
                ></div>
            </div>

            <div class="mt-4 flex gap-2">
                <a
                    href="{{ route('page-wishlist-item-create', ['wishlist' => $wishlist->id]) }}"
                    class="flex-1 rounded-[24px] bg-[#111111] px-4 py-4 text-center text-sm font-medium text-white"
                >
                    Add gift
                </a>

                @if($wishlist->owner_id === auth()->id())
                    <button
                        type="button"
                        wire:click="generateInvite"
                        class="rounded-[24px] bg-[#ECE7DD] px-4 py-4 text-sm font-medium text-[#111111]"
                    >
                        Share
                    </button>

                    <a
                        href="{{ route('page-wishlist-edit', ['wishlist' => $wishlist->id]) }}"
                        class="rounded-[24px] bg-[#ECE7DD] px-4 py-4 text-sm font-medium text-[#111111]"
                    >
                        Edit
                    </a>
                @else
                    <button
                        wire:click="leaveWishlist"
                        class="rounded-[24px] bg-[#ECE7DD] px-4 py-4 text-sm font-medium text-[#111111]"
                    >
                        Leave
                    </button>
                @endif
            </div>

            @if($shareUrl)
                <div class="mt-3 flex gap-2">
                    <div class="min-w-0 flex-1 break-all rounded-[24px] bg-[#ECE7DD] px-4 py-3 text-sm text-[#111111]">
                        {{ $shareUrl }}
                    </div>

                    <button
                        type="button"
                        onclick="navigator.clipboard.writeText('{{ $shareUrl }}')"
                        class="rounded-[24px] bg-[#ECE7DD] px-4 py-3 text-sm font-medium text-[#111111]"
                    >
                        Copy
                    </button>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-4 px-4">
        <div class="rounded-[28px] bg-white p-4">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search"
                class="w-full rounded-[20px] border-0 bg-[#F3F0E8] px-4 py-3 text-sm"
            >

            <div class="mt-3 flex gap-2 overflow-x-auto">
                <button
                    wire:click="$set('statusFilter', 'all')"
                    class="whitespace-nowrap rounded-full px-4 py-2 text-sm {{ $statusFilter === 'all' ? 'bg-[#111111] text-white' : 'bg-[#ECE7DD] text-[#111111]' }}"
                >
                    All
                </button>

                <button
                    wire:click="$set('statusFilter', 'free')"
                    class="whitespace-nowrap rounded-full px-4 py-2 text-sm {{ $statusFilter === 'free' ? 'bg-[#111111] text-white' : 'bg-[#ECE7DD] text-[#111111]' }}"
                >
                    Free
                </button>

                <button
                    wire:click="$set('statusFilter', 'claimed')"
                    class="whitespace-nowrap rounded-full px-4 py-2 text-sm {{ $statusFilter === 'claimed' ? 'bg-[#111111] text-white' : 'bg-[#ECE7DD] text-[#111111]' }}"
                >
                    Joined
                </button>

                <button
                    wire:click="$set('statusFilter', 'purchased')"
                    class="whitespace-nowrap rounded-full px-4 py-2 text-sm {{ $statusFilter === 'purchased' ? 'bg-[#111111] text-white' : 'bg-[#ECE7DD] text-[#111111]' }}"
                >
                    Purchased
                </button>
            </div>
        </div>
    </div>

    <div class="mt-4 space-y-3 px-4">
        @forelse($wishlist->items as $item)
            <button
                wire:click="openItem({{ $item->id }})"
                class="w-full rounded-[30px] p-4 text-left {{ $this->cardClass($item) }}"
            >
                <div class="flex gap-4">
                    <div class="h-24 w-24 shrink-0 overflow-hidden rounded-[20px] bg-black/5">
                        @if($item->image_url)
                            <img src="{{ $item->image_url }}" alt="" class="h-full w-full object-cover">
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="line-clamp-2 text-[30px] font-semibold leading-[0.95]">
                            {{ $item->title }}
                        </div>

                        @if($item->price)
                            <div class="mt-2 text-sm {{ $item->claims->where('user_id', auth()->id())->count() > 0 && ! $item->is_purchased ? 'text-white/70' : 'text-[#666666]' }}">
                                {{ number_format((float) $item->price, 0, ',', ' ') }} {{ $item->currency }}
                            </div>
                        @endif

                        <div class="mt-3 text-xs {{ $item->claims->where('user_id', auth()->id())->count() > 0 && ! $item->is_purchased ? 'text-white/70' : 'text-[#777777]' }}">
                            {{ $this->statusLabel($item) }}
                        </div>
                    </div>
                </div>
            </button>
        @empty
            <div class="rounded-[30px] bg-white p-8 text-center">
                <div class="text-[34px] font-semibold leading-none">
                    Empty
                </div>
                <div class="mt-2 text-sm text-[#7A7A7A]">
                    Add first gift
                </div>
            </div>
        @endforelse
    </div>

    <div
        x-show="sheetOpen"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-black/30 backdrop-blur-sm"
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
        <div class="rounded-t-[36px] bg-white p-5 shadow-2xl">
            <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-[#DDD7CB]"></div>

            @if($this->selectedItem)
                <div class="h-56 overflow-hidden rounded-[24px] bg-[#F3F0E8]">
                    @if($this->selectedItem->image_url)
                        <img src="{{ $this->selectedItem->image_url }}" alt="" class="h-full w-full object-cover">
                    @endif
                </div>

                <div class="mt-4 text-[34px] font-semibold leading-[0.95]">
                    {{ $this->selectedItem->title }}
                </div>

                @if($this->selectedItem->price)
                    <div class="mt-2 text-sm text-[#666666]">
                        {{ number_format((float) $this->selectedItem->price, 0, ',', ' ') }} {{ $this->selectedItem->currency }}
                    </div>
                @endif

                @if($this->selectedItem->note)
                    <div class="mt-4 text-sm text-[#666666]">
                        {{ $this->selectedItem->note }}
                    </div>
                @endif

                <div class="mt-4 text-sm text-[#777777]">
                    {{ $this->statusLabel($this->selectedItem) }}
                </div>

                @if(! $wishlist->hide_claimers && $this->selectedItem->claims->count())
                    <div class="mt-3 space-y-2">
                        @foreach($this->selectedItem->claims as $claim)
                            <div class="rounded-[18px] bg-[#F3F0E8] px-4 py-3">
                                <div class="text-sm font-medium text-[#111111]">
                                    {{ $claim->user?->name }}
                                </div>

                                <div class="mt-1 text-xs text-[#777777]">
                                    {{ $this->claimStatusLabel($claim->status) }}
                                </div>

                                @if($claim->comment)
                                    <div class="mt-1 text-xs text-[#777777]">
                                        {{ $claim->comment }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-6">
                    @if($wishlist->owner_id === auth()->id())
                        <div class="rounded-[24px] bg-[#111111] px-4 py-4 text-sm text-white/70">
                            This is your item
                        </div>
                    @elseif($this->closed())
                        <div class="rounded-[24px] bg-[#111111] px-4 py-4 text-sm text-white/70">
                            Wishlist is closed
                        </div>
                    @else
                        @if(! $this->userClaim)
                            @if(! $claimFormOpen)
                                <div class="flex gap-2">
                                    <button
                                        wire:click="openClaimForm"
                                        class="flex-1 rounded-[24px] bg-[#111111] px-4 py-4 text-sm font-medium text-white"
                                    >
                                        Join gift
                                    </button>

                                    @if($this->selectedItem->url)
                                        <a
                                            href="{{ $this->selectedItem->url }}"
                                            target="_blank"
                                            class="rounded-[24px] bg-[#ECE7DD] px-4 py-4 text-sm font-medium text-[#111111]"
                                        >
                                            Open
                                        </a>
                                    @endif
                                </div>
                            @else
                                <div class="space-y-3 rounded-[24px] bg-[#111111] p-4">
                                    <select
                                        wire:model.defer="claimStatus"
                                        class="w-full rounded-[18px] border-0 bg-white/10 px-4 py-3 text-sm text-white"
                                    >
                                        <option value="gifting">Gift myself</option>
                                        <option value="contribute">Contribute</option>
                                        <option value="thinking">Thinking</option>
                                        <option value="bought">Already bought</option>
                                    </select>

                                    <textarea
                                        wire:model.defer="claimComment"
                                        rows="3"
                                        placeholder="Comment"
                                        class="w-full rounded-[18px] border-0 bg-white/10 px-4 py-3 text-sm text-white placeholder:text-white/40"
                                    ></textarea>

                                    <div class="flex gap-2">
                                        <button
                                            wire:click="saveClaim"
                                            class="flex-1 rounded-[18px] bg-[#F3EE7A] px-4 py-3 text-sm font-medium text-[#111111]"
                                        >
                                            Save
                                        </button>

                                        <button
                                            wire:click="$set('claimFormOpen', false)"
                                            class="rounded-[18px] bg-white/10 px-4 py-3 text-sm font-medium text-white"
                                        >
                                            Back
                                        </button>
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="flex gap-2">
                                <button
                                    wire:click="openClaimForm"
                                    class="flex-1 rounded-[24px] bg-[#111111] px-4 py-4 text-sm font-medium text-white"
                                >
                                    Edit
                                </button>

                                <button
                                    wire:click="unclaimItem({{ $this->selectedItem->id }})"
                                    class="rounded-[24px] bg-[#ECE7DD] px-4 py-4 text-sm font-medium text-[#111111]"
                                >
                                    Cancel
                                </button>

                                @if($this->selectedItem->url)
                                    <a
                                        href="{{ $this->selectedItem->url }}"
                                        target="_blank"
                                        class="rounded-[24px] bg-[#ECE7DD] px-4 py-4 text-sm font-medium text-[#111111]"
                                    >
                                        Open
                                    </a>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('wishlist-share-ready', ({ url, title }) => {
            const text = encodeURIComponent(`Join my wishlist: ${title}`);
            const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${text}`;

            if (window.Telegram?.WebApp?.openTelegramLink) {
                window.Telegram.WebApp.openTelegramLink(shareUrl);
                return;
            }

            window.open(shareUrl, '_blank');
        });
    });
</script>