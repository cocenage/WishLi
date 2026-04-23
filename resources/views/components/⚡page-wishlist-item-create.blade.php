<?php

use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public Wishlist $wishlist;

    public string $url = '';
    public string $title = '';
    public string $description = '';
    public string $store_name = '';
    public string $image_url = '';
    public ?string $price = null;
    public string $currency = '₽';
    public string $note = '';
    public string $priority = 'medium';
    public ?string $previewError = null;

    public function mount(Wishlist $wishlist): void
    {
        abort_unless(
            $wishlist->owner_id === auth()->id() || $wishlist->allow_item_addition,
            403
        );

        $this->wishlist = $wishlist;
    }

    public function fetchPreview(): void
    {
        $this->previewError = null;

        $this->validate([
            'url' => ['required', 'url'],
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 WishlistBot/1.0',
                ])
                ->get($this->url);

            if (! $response->successful()) {
                $this->previewError = 'Не удалось получить данные по ссылке. Заполни карточку вручную.';
                return;
            }

            $html = $response->body();

            $this->title = $this->extractMeta($html, 'property', 'og:title')
                ?: $this->extractMeta($html, 'name', 'twitter:title')
                ?: $this->extractTitle($html)
                ?: $this->title;

            $this->description = $this->extractMeta($html, 'property', 'og:description')
                ?: $this->extractMeta($html, 'name', 'description')
                ?: $this->description;

            $image = $this->extractMeta($html, 'property', 'og:image')
                ?: $this->extractMeta($html, 'name', 'twitter:image');

            if ($image) {
                $this->image_url = $this->makeAbsoluteUrl($image, $this->url);
            }

            $host = parse_url($this->url, PHP_URL_HOST);
            $this->store_name = $this->store_name ?: Str::of((string) $host)->replace('www.', '')->value();

            $price = $this->extractPrice($html);

            if ($price && ! $this->price) {
                $this->price = $price;
            }
        } catch (\Throwable $e) {
            report($e);
            $this->previewError = 'Не удалось получить данные по ссылке. Заполни карточку вручную.';
        }
    }

    protected function extractMeta(string $html, string $attr, string $value): ?string
    {
        $pattern = '/<meta[^>]*' . preg_quote($attr, '/') . '=["\']' . preg_quote($value, '/') . '["\'][^>]*content=["\']([^"\']+)["\']/i';

        if (preg_match($pattern, $html, $matches)) {
            return html_entity_decode(trim($matches[1]));
        }

        $patternReverse = '/<meta[^>]*content=["\']([^"\']+)["\'][^>]*' . preg_quote($attr, '/') . '=["\']' . preg_quote($value, '/') . '["\']/i';

        if (preg_match($patternReverse, $html, $matches)) {
            return html_entity_decode(trim($matches[1]));
        }

        return null;
    }

    protected function extractTitle(string $html): ?string
    {
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            return trim(html_entity_decode($matches[1]));
        }

        return null;
    }

    protected function extractPrice(string $html): ?string
    {
        $patterns = [
            '/"price"\s*:\s*"?(\\d+[\\.,]?\\d*)"?/i',
            '/itemprop=["\']price["\'][^>]*content=["\']([^"\']+)["\']/i',
            '/₽\s?(\d[\d\s.,]*)/u',
            '/(\d[\d\s.,]*)\s?₽/u',
            '/€\s?(\d[\d\s.,]*)/u',
            '/(\d[\d\s.,]*)\s?€/u',
            '/\$\s?(\d[\d\s.,]*)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return trim(str_replace([' ', ','], ['', '.'], $matches[1]));
            }
        }

        return null;
    }

    protected function makeAbsoluteUrl(string $path, string $baseUrl): string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $parsed = parse_url($baseUrl);

        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';

        if (Str::startsWith($path, '//')) {
            return "{$scheme}:{$path}";
        }

        if (Str::startsWith($path, '/')) {
            return "{$scheme}://{$host}{$path}";
        }

        return "{$scheme}://{$host}/{$path}";
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

        WishlistItem::query()->create([
            'wishlist_id' => $this->wishlist->id,
            'created_by' => auth()->id(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?: null,
            'url' => $validated['url'] ?: null,
            'store_name' => $validated['store_name'] ?: null,
            'image_url' => $validated['image_url'] ?: null,
            'price' => $validated['price'] ?: null,
            'currency' => $validated['currency'] ?: null,
            'note' => $validated['note'] ?: null,
            'priority' => $validated['priority'],
        ]);

        return redirect()->route('page-wishlist-show', ['wishlist' => $this->wishlist->id]);
    }
};
?>

<div class="min-h-screen bg-[#f4f7fb] px-4 py-4 pb-28">
    <h1 class="text-2xl font-semibold text-[#1f2a37]">Добавить подарок</h1>

    <div class="mt-5 space-y-4 rounded-[28px] bg-white p-5 shadow-sm">
        <div>
            <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Ссылка</label>
            <div class="flex gap-2">
                <input
                    wire:model.defer="url"
                    type="url"
                    class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm"
                    placeholder="Вставь ссылку на товар"
                >
                <button
                    wire:click="fetchPreview"
                    type="button"
                    class="rounded-2xl bg-[#1f2a37] px-4 py-3 text-sm font-medium text-white"
                >
                    Получить
                </button>
            </div>
            @error('url') <div class="mt-1 text-sm text-red-500">{{ $message }}</div> @enderror
        </div>

        @if($previewError)
            <div class="rounded-2xl bg-[#fef3c7] px-4 py-3 text-sm text-[#92400e]">
                {{ $previewError }}
            </div>
        @endif

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
            <textarea
                wire:model.defer="note"
                rows="3"
                class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm"
                placeholder="Например: хочу чёрный цвет, размер M"
            ></textarea>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-[#1f2a37]">Приоритет</label>
            <select wire:model.defer="priority" class="w-full rounded-2xl border-0 bg-[#eef2f7] px-4 py-3 text-sm">
                <option value="low">Низкий</option>
                <option value="medium">Средний</option>
                <option value="high">Высокий</option>
            </select>
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 p-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <button
            wire:click="save"
            class="block w-full rounded-[20px] bg-[#1f2a37] px-5 py-4 text-center text-sm font-medium text-white shadow-lg"
        >
            Сохранить подарок
        </button>
    </div>
</div>