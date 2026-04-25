<?php

use App\Models\Wishlist;
use App\Models\WishlistMember;
use Livewire\Component;

new class extends Component
{
    public string $title = '';
    public string $type = 'birthday';
    public ?string $description = null;
    public ?string $event_date = null;
    public string $visibility = 'link';
    public bool $allow_item_addition = true;
    public bool $allow_multi_claim = true;
    public bool $hide_claimers = false;
    public string $emoji = '🎁';
    public string $color = 'yellow';

    public function setColor(string $color): void
    {
        if (! in_array($color, ['yellow', 'peach', 'green', 'blue', 'beige'], true)) {
            return;
        }

        $this->color = $color;
    }

    protected function emptyToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    protected function normalizeFields(): void
    {
        $this->title = trim($this->title);
        $this->description = $this->emptyToNull($this->description);
        $this->event_date = $this->emptyToNull($this->event_date);
        $this->emoji = $this->emptyToNull($this->emoji) ?: '🎁';
    }

    public function save()
    {
        $this->normalizeFields();

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'event_date' => ['nullable', 'date'],
            'visibility' => ['required', 'in:private,link,invited'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'color' => ['required', 'in:yellow,peach,green,blue,beige'],
        ]);

        $wishlist = Wishlist::query()->create([
            'owner_id' => auth()->id(),
            'title' => $validated['title'],
            'type' => $validated['type'] ?: 'birthday',
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'] ?? null,
            'visibility' => $validated['visibility'],
            'allow_item_addition' => (bool) $this->allow_item_addition,
            'allow_multi_claim' => (bool) $this->allow_multi_claim,
            'hide_claimers' => (bool) $this->hide_claimers,
            'emoji' => $validated['emoji'] ?: '🎁',
            'color' => $validated['color'],
            'is_archived' => false,
            'is_closed' => false,
        ]);

        WishlistMember::query()->firstOrCreate(
            [
                'wishlist_id' => $wishlist->id,
                'user_id' => auth()->id(),
            ],
            [
                'role' => 'owner',
                'status' => 'accepted',
            ]
        );

        return redirect()->route('page-wishlist-show', ['wishlist' => $wishlist->id]);
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

            <a
                href="{{ route('page-wishlists') }}"
                class="inline-flex h-[52px] items-center rounded-[22px] bg-[rgba(255,255,255,0.22)] px-5 text-[16px] font-medium text-[#1d1d1d] shadow-[inset_0_1px_0_rgba(255,255,255,0.7)] backdrop-blur-[10px]"
            >
                Назад
            </a>
        </div>

        <div class="mt-10">
            <div class="text-[13px] uppercase tracking-[0.18em] text-[#8F8A84]">
                create
            </div>

            <h1 class="mt-3 text-[36px] font-medium leading-none tracking-[-0.05em] text-[#1d1d1d]">
                Новый вишлист
            </h1>
        </div>

        <div class="mt-7 space-y-4">
            <div class="rounded-[32px] bg-[rgba(255,255,255,0.20)] p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.5)] backdrop-blur-[10px]">
                <label class="mb-3 block text-[14px] text-[#5E5A55]">Название</label>
                <input
                    wire:model.defer="title"
                    type="text"
                    placeholder="Например, День рождения"
                    class="h-[68px] w-full rounded-[24px] border-0 bg-[rgba(255,255,255,0.28)] px-5 text-[18px] text-[#181818] placeholder:text-[#8C8781] shadow-[inset_0_1px_0_rgba(255,255,255,0.65)] outline-none"
                >
                @error('title')
                    <div class="mt-2 text-sm text-red-500">{{ $message }}</div>
                @enderror
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-[32px] bg-[rgba(255,255,255,0.20)] p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.5)] backdrop-blur-[10px]">
                    <label class="mb-3 block text-[14px] text-[#5E5A55]">Эмодзи</label>
                    <input
                        wire:model.defer="emoji"
                        type="text"
                        class="h-[68px] w-full rounded-[24px] border-0 bg-[rgba(255,255,255,0.28)] px-5 text-[18px] text-[#181818] placeholder:text-[#8C8781] shadow-[inset_0_1px_0_rgba(255,255,255,0.65)] outline-none"
                    >
                    @error('emoji')
                        <div class="mt-2 text-sm text-red-500">{{ $message }}</div>
                    @enderror
                </div>

                <div class="rounded-[32px] bg-[rgba(255,255,255,0.20)] p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.5)] backdrop-blur-[10px]">
                    <label class="mb-3 block text-[14px] text-[#5E5A55]">Дата</label>
                    <input
                        wire:model.defer="event_date"
                        type="date"
                        class="h-[68px] w-full rounded-[24px] border-0 bg-[rgba(255,255,255,0.28)] px-5 text-[18px] text-[#181818] shadow-[inset_0_1px_0_rgba(255,255,255,0.65)] outline-none"
                    >
                    @error('event_date')
                        <div class="mt-2 text-sm text-red-500">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="rounded-[32px] bg-[rgba(255,255,255,0.20)] p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.5)] backdrop-blur-[10px]">
                <label class="mb-3 block text-[14px] text-[#5E5A55]">Описание</label>
                <textarea
                    wire:model.defer="description"
                    rows="4"
                    placeholder="Коротко опиши, для чего этот вишлист"
                    class="w-full rounded-[24px] border-0 bg-[rgba(255,255,255,0.28)] px-5 py-4 text-[17px] text-[#181818] placeholder:text-[#8C8781] shadow-[inset_0_1px_0_rgba(255,255,255,0.65)] outline-none"
                ></textarea>
                @error('description')
                    <div class="mt-2 text-sm text-red-500">{{ $message }}</div>
                @enderror
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-[32px] bg-[rgba(255,255,255,0.20)] p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.5)] backdrop-blur-[10px]">
                    <label class="mb-3 block text-[14px] text-[#5E5A55]">Тип</label>
                    <select
                        wire:model.defer="type"
                        class="h-[68px] w-full rounded-[24px] border-0 bg-[rgba(255,255,255,0.28)] px-5 text-[18px] text-[#181818] shadow-[inset_0_1px_0_rgba(255,255,255,0.65)] outline-none"
                    >
                        <option value="birthday">День рождения</option>
                        <option value="new_year">Новый год</option>
                        <option value="wedding">Свадьба</option>
                        <option value="house">Новый дом</option>
                    </select>
                    @error('type')
                        <div class="mt-2 text-sm text-red-500">{{ $message }}</div>
                    @enderror
                </div>

                <div class="rounded-[32px] bg-[rgba(255,255,255,0.20)] p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.5)] backdrop-blur-[10px]">
                    <label class="mb-3 block text-[14px] text-[#5E5A55]">Видимость</label>
                    <select
                        wire:model.defer="visibility"
                        class="h-[68px] w-full rounded-[24px] border-0 bg-[rgba(255,255,255,0.28)] px-5 text-[18px] text-[#181818] shadow-[inset_0_1px_0_rgba(255,255,255,0.65)] outline-none"
                    >
                        <option value="private">Приватный</option>
                        <option value="link">По ссылке</option>
                        <option value="invited">Только приглашённые</option>
                    </select>
                    @error('visibility')
                        <div class="mt-2 text-sm text-red-500">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="rounded-[32px] bg-[rgba(255,255,255,0.20)] p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.5)] backdrop-blur-[10px]">
                <label class="mb-4 block text-[14px] text-[#5E5A55]">Цвет</label>

                <div class="flex flex-wrap gap-3">
                    <button
                        type="button"
                        wire:click="setColor('yellow')"
                        class="h-[66px] w-[66px] rounded-[22px] bg-[linear-gradient(180deg,_#F7EE9B_0%,_#F2E56C_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.45),0_8px_20px_rgba(0,0,0,0.03)] {{ $color === 'yellow' ? 'ring-2 ring-[#171717]' : '' }}"
                    ></button>

                    <button
                        type="button"
                        wire:click="setColor('peach')"
                        class="h-[66px] w-[66px] rounded-[22px] bg-[linear-gradient(180deg,_#F5D0B0_0%,_#F1BE90_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.45),0_8px_20px_rgba(0,0,0,0.03)] {{ $color === 'peach' ? 'ring-2 ring-[#171717]' : '' }}"
                    ></button>

                    <button
                        type="button"
                        wire:click="setColor('green')"
                        class="h-[66px] w-[66px] rounded-[22px] bg-[linear-gradient(180deg,_#DFE8CC_0%,_#D0DCB6_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.45),0_8px_20px_rgba(0,0,0,0.03)] {{ $color === 'green' ? 'ring-2 ring-[#171717]' : '' }}"
                    ></button>

                    <button
                        type="button"
                        wire:click="setColor('blue')"
                        class="h-[66px] w-[66px] rounded-[22px] bg-[linear-gradient(180deg,_#DCE0F2_0%,_#CFD6ED_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.45),0_8px_20px_rgba(0,0,0,0.03)] {{ $color === 'blue' ? 'ring-2 ring-[#171717]' : '' }}"
                    ></button>

                    <button
                        type="button"
                        wire:click="setColor('beige')"
                        class="h-[66px] w-[66px] rounded-[22px] bg-[linear-gradient(180deg,_#EAE4DB_0%,_#DED6CB_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.45),0_8px_20px_rgba(0,0,0,0.03)] {{ $color === 'beige' ? 'ring-2 ring-[#171717]' : '' }}"
                    ></button>
                </div>

                @error('color')
                    <div class="mt-2 text-sm text-red-500">{{ $message }}</div>
                @enderror
            </div>

            <div class="rounded-[32px] bg-[rgba(255,255,255,0.20)] p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.5)] backdrop-blur-[10px]">
                <div class="space-y-3">
                    <label class="flex min-h-[72px] items-center justify-between rounded-[24px] bg-[rgba(255,255,255,0.20)] px-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.55)]">
                        <span class="text-[17px] text-[#1c1c1c]">Разрешить добавление подарков</span>
                        <input wire:model.defer="allow_item_addition" type="checkbox" class="h-5 w-5">
                    </label>

                    <label class="flex min-h-[72px] items-center justify-between rounded-[24px] bg-[rgba(255,255,255,0.20)] px-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.55)]">
                        <span class="text-[17px] text-[#1c1c1c]">Разрешить несколько броней</span>
                        <input wire:model.defer="allow_multi_claim" type="checkbox" class="h-5 w-5">
                    </label>

                    <label class="flex min-h-[72px] items-center justify-between rounded-[24px] bg-[rgba(255,255,255,0.20)] px-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.55)]">
                        <span class="text-[17px] text-[#1c1c1c]">Скрывать участников</span>
                        <input wire:model.defer="hide_claimers" type="checkbox" class="h-5 w-5">
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-20 mx-auto w-full max-w-[860px] px-6 pb-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <button
            wire:click="save"
            class="flex h-[74px] w-full items-center justify-center gap-4 rounded-[28px] bg-[rgba(255,255,255,0.20)] text-[20px] font-medium text-[#181818] shadow-[inset_0_1px_0_rgba(255,255,255,0.6),0_12px_28px_rgba(0,0,0,0.03)] backdrop-blur-[14px]"
        >
            <span class="text-[34px] leading-none">+</span>
            <span>Создать вишлист</span>
        </button>
    </div>
</div>