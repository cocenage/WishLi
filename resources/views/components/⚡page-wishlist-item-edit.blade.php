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

<div class="min-h-screen bg-[#f4f7fb] px-4 py-4 pb-32">
    <h1 class="text-2xl font-semibold text-[#1f2a37]">Редактировать подарок</h1>

    <div class="mt-5 space-y-4 rounded-[28px] bg-white p-5 shadow-sm">
        @if($image_url)
            <div class="h-48 overflow-hidden rounded-[24px] bg-[#eef2f7]">
                <img src="{{ $image_url }}" alt="" class="h-full w-full object-cover">
            </div>
        @endif

        <div>
            <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Название</label>
            <input wire:model.defer="title" type="text" class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm">
            @error('title') <div class="mt-1 text-sm text-red-500">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Ссылка</label>
            <input wire:model.defer="url" type="url" class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm">
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Описание</label>
            <textarea wire:model.defer="description" rows="4" class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm"></textarea>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Цена</label>
                <input wire:model.defer="price" type="number" step="0.01" class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm">
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Валюта</label>
                <input wire:model.defer="currency" type="text" class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm">
            </div>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Магазин</label>
            <input wire:model.defer="store_name" type="text" class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm">
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Картинка</label>
            <input wire:model.defer="image_url" type="url" class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm">
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Заметка</label>
            <textarea wire:model.defer="note" rows="3" class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm"></textarea>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Приоритет</label>
            <select wire:model.defer="priority" class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm">
                <option value="low">Низкий</option>
                <option value="medium">Средний</option>
                <option value="high">Высокий</option>
            </select>
        </div>

        <label class="flex items-center justify-between rounded-2xl bg-[#eef2f7] px-4 py-4">
            <span class="text-sm text-[#1f2a37]">Скрыть товар</span>
            <input wire:model.defer="is_hidden" type="checkbox">
        </label>
    </div>

    <div class="mt-4 rounded-[28px] bg-white p-5 shadow-sm">
        <button
            wire:click="delete"
            class="w-full rounded-2xl bg-[#fee2e2] px-4 py-3 text-sm font-medium text-[#991b1b]"
        >
            Удалить подарок
        </button>
    </div>

    <div class="fixed inset-x-0 bottom-0 p-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <button
            wire:click="save"
            class="block w-full rounded-[20px] bg-[#1f2a37] px-5 py-4 text-center text-sm font-medium text-white shadow-lg"
        >
            Сохранить изменения
        </button>
    </div>
</div>