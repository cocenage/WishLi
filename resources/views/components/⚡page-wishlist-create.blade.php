<?php

use App\Models\Wishlist;
use App\Models\WishlistMember;
use Livewire\Component;

new class extends Component
{
    public string $title = '';
    public string $description = '';
    public ?string $event_date = null;
    public string $visibility = 'link';
    public bool $allow_item_addition = true;
    public bool $allow_multi_claim = true;
    public string $emoji = '🎁';

    public function save()
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'event_date' => ['nullable', 'date'],
            'visibility' => ['required', 'in:private,link,invited'],
            'emoji' => ['nullable', 'string', 'max:10'],
        ]);

        $wishlist = Wishlist::query()->create([
            'owner_id' => auth()->id(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?: null,
            'event_date' => $validated['event_date'] ?: null,
            'visibility' => $validated['visibility'],
            'allow_item_addition' => $this->allow_item_addition,
            'allow_multi_claim' => $this->allow_multi_claim,
            'emoji' => $validated['emoji'] ?: '🎁',
        ]);

        WishlistMember::query()->firstOrCreate([
            'wishlist_id' => $wishlist->id,
            'user_id' => auth()->id(),
        ], [
            'role' => 'owner',
            'status' => 'accepted',
        ]);

        return redirect()->route('page-wishlist-show', ['wishlist' => $wishlist->id]);
    }
};
?>

<div class="min-h-screen bg-[#f4f7fb] px-4 py-4 pb-28">
    <h1 class="text-2xl font-semibold text-[#1f2a37]">Создать вишлист</h1>

    <div class="mt-5 space-y-4 rounded-[28px] bg-white p-5 shadow-sm">
        <div>
            <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Эмодзи</label>
            <input
                wire:model.defer="emoji"
                type="text"
                class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm"
            >
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Название</label>
            <input
                wire:model.defer="title"
                type="text"
                class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm"
            >
            @error('title') <div class="mt-1 text-sm text-red-500">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Описание</label>
            <textarea
                wire:model.defer="description"
                rows="4"
                class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm"
            ></textarea>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Дата</label>
            <input
                wire:model.defer="event_date"
                type="date"
                class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm"
            >
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Видимость</label>
            <select
                wire:model.defer="visibility"
                class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm"
            >
                <option value="private">Только я</option>
                <option value="link">По ссылке</option>
                <option value="invited">Только приглашённые</option>
            </select>
        </div>

        <label class="flex items-center justify-between rounded-2xl bg-[#eef2f7] px-4 py-4">
            <span class="text-sm text-[#1f2a37]">Разрешить добавлять товары</span>
            <input wire:model.defer="allow_item_addition" type="checkbox">
        </label>

        <label class="flex items-center justify-between rounded-2xl bg-[#eef2f7] px-4 py-4">
            <span class="text-sm text-[#1f2a37]">Несколько человек могут выбрать один подарок</span>
            <input wire:model.defer="allow_multi_claim" type="checkbox">
        </label>
    </div>

    <div class="fixed inset-x-0 bottom-0 p-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <button
            wire:click="save"
            class="block w-full rounded-[20px] bg-[#1f2a37] px-5 py-4 text-center text-sm font-medium text-white shadow-lg"
        >
            Создать вишлист
        </button>
    </div>
</div>