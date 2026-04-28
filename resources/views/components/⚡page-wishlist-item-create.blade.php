<?php

use App\Models\Wishlist;
use App\Services\Wishlist\LinkPreviewService;
use App\Services\Wishlist\WishlistAccessService;
use App\Services\Wishlist\WishlistItemService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Wishlist $wishlist;

    public ?string $url = null;
    public string $title = '';
    public ?string $description = null;
    public ?string $store_name = null;
    public ?string $image_url = null;
    public $image_file = null;

    public ?string $price = null;
    public string $currency = '₽';
    public ?string $category = null;
    public ?string $note = null;
    public string $priority = 'medium';
    public string $status = 'wanted';

    public ?string $previewError = null;

    public function mount(Wishlist $wishlist, WishlistAccessService $access): void
    {
        abort_unless($access->canAddItem($wishlist, auth()->user()), 403);

        $this->wishlist = $wishlist;
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
        $this->url = $this->emptyToNull($this->url);
        $this->title = trim($this->title);
        $this->description = $this->emptyToNull($this->description);
        $this->store_name = $this->emptyToNull($this->store_name);
        $this->image_url = $this->emptyToNull($this->image_url);
        $this->price = $this->emptyToNull($this->price);
        $this->currency = $this->emptyToNull($this->currency) ?: '₽';
        $this->category = $this->emptyToNull($this->category);
        $this->note = $this->emptyToNull($this->note);
    }

    public function fetchPreview(LinkPreviewService $preview): void
    {
        $this->normalizeFields();
        $this->previewError = null;

        $this->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        try {
            $data = $preview->extract($this->url);

            $this->title = $data['title'] ?: $this->title;
            $this->description = $data['description'] ?: $this->description;
            $this->store_name = $data['store_name'] ?: $this->store_name;
            $this->image_url = $data['image_url'] ?: $this->image_url;
            $this->price = $data['price'] ?: $this->price;
            $this->currency = $data['currency'] ?: $this->currency;

            $this->dispatch('telegram-haptic-success');
        } catch (\Throwable $e) {
            report($e);

            $this->previewError = 'Не удалось получить данные по ссылке. Заполни карточку вручную.';
            $this->dispatch('telegram-haptic-error');
        }
    }

    public function save(WishlistItemService $items): void
    {
        $this->normalizeFields();

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:2000'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'image_file' => ['nullable', 'image', 'max:4096'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'category' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'priority' => ['required', 'in:low,medium,high,dream'],
            'status' => ['required', 'in:wanted,postponed,purchased,hidden'],
        ]);

        if ($this->image_file) {
            $path = $this->image_file->store('wishlist-items', 'public');

            $validated['image_path'] = $path;
            $validated['image_url'] = Storage::url($path);
        }

        $items->create($this->wishlist, auth()->user(), $validated);

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
                    create
                </div>

                <h1 class="mt-3 text-[58px] font-semibold leading-[0.84] tracking-[-0.09em] text-[#171717]">
                    New<br>Gift
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
            <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
                <label class="text-[13px] text-[#77736B]">Ссылка</label>

                <div class="mt-3 flex gap-2">
                    <input
                        wire:model.defer="url"
                        type="text"
                        placeholder="https://..."
                        class="h-[58px] min-w-0 flex-1 rounded-[22px] border-0 bg-[#F4F3EF] px-4 text-[14px] outline-none"
                    >

                    <button
                        wire:click="fetchPreview"
                        type="button"
                        class="h-[58px] rounded-[22px] bg-[#171717] px-5 text-[13px] font-medium text-white"
                    >
                        Fetch
                    </button>
                </div>

                @error('url')
                    <div class="mt-2 text-[13px] text-red-500">{{ $message }}</div>
                @enderror

                @if($previewError)
                    <div class="mt-3 rounded-[20px] bg-[#F0DED5] px-4 py-3 text-[13px] text-[#6B3328]">
                        {{ $previewError }}
                    </div>
                @endif
            </div>

            @if($image_file || $image_url)
                <div class="h-[240px] overflow-hidden rounded-[30px] bg-white/70 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
                    @if($image_file)
                        <img src="{{ $image_file->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
                    @elseif($image_url)
                        <img src="{{ $image_url }}" alt="" class="h-full w-full object-cover">
                    @endif
                </div>
            @endif

            <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
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

            <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
                <label class="text-[13px] text-[#77736B]">Описание</label>

                <textarea
                    wire:model.defer="description"
                    rows="4"
                    class="mt-3 w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 py-4 text-[15px] outline-none"
                ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
                    <label class="text-[13px] text-[#77736B]">Цена</label>

                    <input
                        wire:model.defer="price"
                        type="text"
                        class="mt-3 h-[60px] w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 text-[15px] outline-none"
                    >
                </div>

                <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
                    <label class="text-[13px] text-[#77736B]">Валюта</label>

                    <input
                        wire:model.defer="currency"
                        type="text"
                        class="mt-3 h-[60px] w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 text-[15px] outline-none"
                    >
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
                    <label class="text-[13px] text-[#77736B]">Магазин</label>

                    <input
                        wire:model.defer="store_name"
                        type="text"
                        class="mt-3 h-[60px] w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 text-[15px] outline-none"
                    >
                </div>

                <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
                    <label class="text-[13px] text-[#77736B]">Категория</label>

                    <input
                        wire:model.defer="category"
                        type="text"
                        class="mt-3 h-[60px] w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 text-[15px] outline-none"
                    >
                </div>
            </div>

            <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
                <label class="text-[13px] text-[#77736B]">Картинка по ссылке</label>

                <input
                    wire:model.defer="image_url"
                    type="text"
                    class="mt-3 h-[60px] w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 text-[14px] outline-none"
                >

                <label class="mt-3 block rounded-[22px] bg-[#F4F3EF] px-4 py-4 text-[13px] text-[#77736B]">
                    <span>Загрузить файл</span>
                    <input
                        wire:model="image_file"
                        type="file"
                        accept="image/*"
                        class="mt-3 block w-full text-[13px]"
                    >
                </label>
            </div>

            <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
                <label class="text-[13px] text-[#77736B]">Заметка</label>

                <textarea
                    wire:model.defer="note"
                    rows="3"
                    class="mt-3 w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 py-4 text-[15px] outline-none"
                ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
                    <label class="text-[13px] text-[#77736B]">Приоритет</label>

                    <select
                        wire:model.defer="priority"
                        class="mt-3 h-[60px] w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 text-[15px] outline-none"
                    >
                        <option value="low">Низкий</option>
                        <option value="medium">Средний</option>
                        <option value="high">Высокий</option>
                        <option value="dream">Очень хочу</option>
                    </select>
                </div>

                <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
                    <label class="text-[13px] text-[#77736B]">Статус</label>

                    <select
                        wire:model.defer="status"
                        class="mt-3 h-[60px] w-full rounded-[22px] border-0 bg-[#F4F3EF] px-4 text-[15px] outline-none"
                    >
                        <option value="wanted">Хочу</option>
                        <option value="postponed">Отложено</option>
                        <option value="purchased">Куплено</option>
                        <option value="hidden">Скрыто</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 mx-auto w-full max-w-[430px] px-5 pb-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <button
            wire:click="save"
            class="flex h-[70px] w-full items-center justify-center rounded-[28px] bg-[#171717] text-[15px] font-medium text-white shadow-[0_18px_45px_rgba(0,0,0,0.18)]"
        >
            Сохранить подарок
        </button>
    </div>
</div>