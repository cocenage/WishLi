<?php

use App\Models\Wishlist;
use App\Models\WishlistItem;
use Livewire\Component;

new class extends Component
{
    public Wishlist $wishlist;
    public WishlistItem $item;

    public string $url = '';
    public string $title = '';
    public string $description = '';
    public string $store_name = '';
    public string $image_url = '';
    public ?string $price = null;
    public string $currency = '₽';
    public string $note = '';
    public string $priority = 'medium';
    public bool $is_hidden = false;
    public bool $is_purchased = false;

    public function mount(Wishlist $wishlist, WishlistItem $item): void
    {
        abort_unless($wishlist->id === $item->wishlist_id, 404);

        abort_unless(
            $wishlist->owner_id === auth()->id() || $item->created_by === auth()->id(),
            403
        );

        $this->wishlist = $wishlist;
        $this->item = $item;

        $this->url = (string) $item->url;
        $this->title = $item->title;
        $this->description = (string) $item->description;
        $this->store_name = (string) $item->store_name;
        $this->image_url = (string) $item->image_url;
        $this->price = $item->price ? (string) $item->price : null;
        $this->currency = $item->currency ?: '₽';
        $this->note = (string) $item->note;
        $this->priority = $item->priority ?: 'medium';
        $this->is_hidden = (bool) $item->is_hidden;
        $this->is_purchased = (bool) $item->is_purchased;
    }

    public function save()
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url'],
            'description' => ['nullable', 'string'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'url'],
            'price' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'max:10'],
            'note' => ['nullable', 'string', 'max:500'],
            'priority' => ['required', 'in:low,medium,high'],
        ]);

        $this->item->update([
            'title' => $validated['title'],
            'url' => $validated['url'] ?: null,
            'description' => $validated['description'] ?: null,
            'store_name' => $validated['store_name'] ?: null,
            'image_url' => $validated['image_url'] ?: null,
            'price' => $validated['price'] ?: null,
            'currency' => $validated['currency'] ?: null,
            'note' => $validated['note'] ?: null,
            'priority' => $validated['priority'],
            'is_hidden' => $this->is_hidden,
            'is_purchased' => $this->is_purchased,
        ]);

        return redirect()->route('page-wishlist-show', ['wishlist' => $this->wishlist->id]);
    }

    public function delete()
    {
        $this->item->delete();

        return redirect()->route('page-wishlist-show', ['wishlist' => $this->wishlist->id]);
    }
};
?>

<div class="min-h-screen bg-[#F3F0E8] px-4 py-4 pb-32 text-[#111111]">
    <div class="rounded-[32px] bg-white p-5">
        <div class="text-[12px] uppercase tracking-[0.18em] text-[#8B8B8B]">
            edit
        </div>

        <h1 class="mt-2 text-[44px] font-semibold leading-[0.9]">
            EDIT<br>ITEM
        </h1>
    </div>

    <div class="mt-4 space-y-3 rounded-[32px] bg-white p-5">
        @if($image_url)
            <div class="h-48 overflow-hidden rounded-[24px] bg-[#F3F0E8]">
                <img src="{{ $image_url }}" alt="" class="h-full w-full object-cover">
            </div>
        @endif

        <div>
            <label class="mb-2 block text-sm text-[#666666]">Title</label>
            <input wire:model.defer="title" type="text" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base">
            @error('title') <div class="mt-1 text-sm text-red-500">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm text-[#666666]">Link</label>
            <input wire:model.defer="url" type="url" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base">
        </div>

        <div>
            <label class="mb-2 block text-sm text-[#666666]">Description</label>
            <textarea wire:model.defer="description" rows="4" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base"></textarea>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mb-2 block text-sm text-[#666666]">Price</label>
                <input wire:model.defer="price" type="number" step="0.01" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base">
            </div>

            <div>
                <label class="mb-2 block text-sm text-[#666666]">Currency</label>
                <input wire:model.defer="currency" type="text" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base">
            </div>
        </div>

        <div>
            <label class="mb-2 block text-sm text-[#666666]">Store</label>
            <input wire:model.defer="store_name" type="text" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base">
        </div>

        <div>
            <label class="mb-2 block text-sm text-[#666666]">Image</label>
            <input wire:model.defer="image_url" type="url" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base">
        </div>

        <div>
            <label class="mb-2 block text-sm text-[#666666]">Note</label>
            <textarea wire:model.defer="note" rows="3" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base"></textarea>
        </div>

        <div>
            <label class="mb-2 block text-sm text-[#666666]">Priority</label>
            <select wire:model.defer="priority" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
            </select>
        </div>

        <label class="flex items-center justify-between rounded-[22px] bg-[#F3F0E8] px-4 py-4">
            <span class="text-sm">Hide item</span>
            <input wire:model.defer="is_hidden" type="checkbox">
        </label>

        <label class="flex items-center justify-between rounded-[22px] bg-[#F3F0E8] px-4 py-4">
            <span class="text-sm">Purchased</span>
            <input wire:model.defer="is_purchased" type="checkbox">
        </label>
    </div>

    <div class="mt-4 rounded-[32px] bg-white p-5">
        <button
            wire:click="delete"
            class="w-full rounded-[24px] bg-[#E8E3D8] px-4 py-4 text-sm font-medium text-[#111111]"
        >
            Delete item
        </button>
    </div>

    <div class="fixed inset-x-0 bottom-0 p-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <button
            wire:click="save"
            class="block w-full rounded-[24px] bg-[#111111] px-5 py-4 text-center text-sm font-medium text-white"
        >
            Save changes
        </button>
    </div>
</div>