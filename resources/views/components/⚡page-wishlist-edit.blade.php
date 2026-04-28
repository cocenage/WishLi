<?php

use App\Models\Wishlist;
use App\Services\Wishlist\WishlistAccessService;
use App\Services\Wishlist\WishlistService;
use Livewire\Component;

new class extends Component
{
    public Wishlist $wishlist;

    public string $title = '';
    public string $type = 'custom';
    public ?string $description = null;
    public ?string $event_date = null;

    public string $visibility = 'link';

    public bool $allow_item_addition = true;
    public bool $allow_multi_claim = false;
    public bool $hide_claimers = true;

    public string $emoji = '🎁';
    public string $color = 'yellow';

    public function mount(Wishlist $wishlist, WishlistAccessService $access): void
    {
        abort_unless($access->canManage($wishlist, auth()->user()), 403);

        $this->wishlist = $wishlist;

        $this->title = $wishlist->title;
        $this->type = $wishlist->type ?: 'custom';
        $this->description = $wishlist->description;
        $this->event_date = $wishlist->event_date?->format('Y-m-d');
        $this->visibility = $wishlist->visibility;
        $this->allow_item_addition = $wishlist->allow_item_addition;
        $this->allow_multi_claim = $wishlist->allow_multi_claim;
        $this->hide_claimers = $wishlist->hide_claimers;
        $this->emoji = $wishlist->emoji ?: '🎁';
        $this->color = $wishlist->color ?: 'yellow';
    }

    public function setColor(string $color): void
    {
        if (! in_array($color, ['yellow', 'peach', 'green', 'blue', 'beige'], true)) {
            return;
        }

        $this->color = $color;
        $this->dispatch('telegram-haptic-impact');
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

    public function save(WishlistService $wishlists): void
    {
        $this->normalizeFields();

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'event_date' => ['nullable', 'date'],
            'visibility' => ['required', 'in:private,link,invited,public'],
            'allow_item_addition' => ['boolean'],
            'allow_multi_claim' => ['boolean'],
            'hide_claimers' => ['boolean'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'color' => ['required', 'in:yellow,peach,green,blue,beige'],
        ]);

        $wishlists->update($this->wishlist, $validated);

        $this->dispatch('telegram-haptic-success');

        $this->redirect(
            route('page-wishlist-show', ['wishlist' => $this->wishlist->id]),
            navigate: true
        );
    }
};
?>

<div class="min-h-screen pb-[110px]">
    <div class="mx-auto w-full max-w-[430px] px-5 pt-5">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-[13px] font-medium uppercase tracking-[0.18em] text-[#8D887F]">
                    edit
                </div>

                <h1 class="mt-3 text-[58px] font-semibold leading-[0.84] tracking-[-0.09em] text-[#171717]">
                    Edit<br>Wishlist
                </h1>
            </div>

            <a
                href="{{ route('page-wishlist-show', ['wishlist' => $wishlist->id]) }}"
                class="flex h-[42px] w-[42px] items-center justify-center rounded-full bg-white/70 text-[18px] text-[#171717] shadow-sm"
            >
                ×
            </a>
        </div>

        <div class="mt-7 space-y-3">
            <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)] backdrop-blur-xl">
                <label class="text-[13px] text-[#77736B]">Название</label>

                <input
                    wire:model.defer="title"
                    type="text"
                    class="mt-3 h-[60px] w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 text-[18px] font-medium outline-none"
                >

                @error('title')
                    <div class="mt-2 text-[13px] text-red-500">{{ $message }}</div>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)] backdrop-blur-xl">
                    <label class="text-[13px] text-[#77736B]">Эмодзи</label>

                    <input
                        wire:model.defer="emoji"
                        type="text"
                        class="mt-3 h-[60px] w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 text-[26px] outline-none"
                    >
                </div>

                <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)] backdrop-blur-xl">
                    <label class="text-[13px] text-[#77736B]">Дата</label>

                    <input
                        wire:model.defer="event_date"
                        type="date"
                        class="mt-3 h-[60px] w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 text-[14px] outline-none"
                    >
                </div>
            </div>

            <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)] backdrop-blur-xl">
                <label class="text-[13px] text-[#77736B]">Описание</label>

                <textarea
                    wire:model.defer="description"
                    rows="4"
                    class="mt-3 w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 py-4 text-[15px] outline-none"
                ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)] backdrop-blur-xl">
                    <label class="text-[13px] text-[#77736B]">Тип</label>

                    <select
                        wire:model.defer="type"
                        class="mt-3 h-[60px] w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 text-[15px] outline-none"
                    >
                        <option value="birthday">День рождения</option>
                        <option value="new_year">Новый год</option>
                        <option value="wedding">Свадьба</option>
                        <option value="house">Новый дом</option>
                        <option value="custom">Другое</option>
                    </select>
                </div>

                <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)] backdrop-blur-xl">
                    <label class="text-[13px] text-[#77736B]">Доступ</label>

                    <select
                        wire:model.defer="visibility"
                        class="mt-3 h-[60px] w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 text-[15px] outline-none"
                    >
                        <option value="private">Приватный</option>
                        <option value="link">По ссылке</option>
                        <option value="invited">Приглашённые</option>
                        <option value="public">Публичный</option>
                    </select>
                </div>
            </div>

            <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)] backdrop-blur-xl">
                <div class="text-[13px] text-[#77736B]">Цвет</div>

                <div class="mt-4 flex gap-3 overflow-x-auto no-scrollbar">
                    @foreach([
                        'yellow' => 'bg-[#C9AE8D]',
                        'peach' => 'bg-[#D9AE7F]',
                        'green' => 'bg-[#8F9B8A]',
                        'blue' => 'bg-[#AEB8D6]',
                        'beige' => 'bg-[#C8B298]',
                    ] as $key => $class)
                        <button
                            type="button"
                            wire:click="setColor('{{ $key }}')"
                            class="h-[58px] w-[58px] shrink-0 rounded-[22px] {{ $class }} {{ $color === $key ? 'ring-2 ring-[#171717] ring-offset-2 ring-offset-white' : '' }}"
                        ></button>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[30px] bg-white/70 p-4 shadow-[0_12px_30px_rgba(0,0,0,0.04)] backdrop-blur-xl">
                <label class="flex min-h-[62px] items-center justify-between rounded-[22px] bg-[#F4F3EF] px-4">
                    <span class="text-[14px] font-medium">Другие могут добавлять подарки</span>
                    <input wire:model.defer="allow_item_addition" type="checkbox">
                </label>

                <label class="mt-2 flex min-h-[62px] items-center justify-between rounded-[22px] bg-[#F4F3EF] px-4">
                    <span class="text-[14px] font-medium">Несколько броней</span>
                    <input wire:model.defer="allow_multi_claim" type="checkbox">
                </label>

                <label class="mt-2 flex min-h-[62px] items-center justify-between rounded-[22px] bg-[#F4F3EF] px-4">
                    <span class="text-[14px] font-medium">Режим сюрприза</span>
                    <input wire:model.defer="hide_claimers" type="checkbox">
                </label>
            </div>
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 mx-auto w-full max-w-[430px] px-5 pb-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <button
            wire:click="save"
            class="flex h-[70px] w-full items-center justify-center rounded-[28px] bg-[#171717] text-[15px] font-medium text-white shadow-[0_18px_45px_rgba(0,0,0,0.18)]"
        >
            Сохранить
        </button>
    </div>
</div>