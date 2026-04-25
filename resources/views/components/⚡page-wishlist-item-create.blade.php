<?php

use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
    public ?string $note = null;
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
        $this->note = $this->emptyToNull($this->note);
    }

    protected function buildRules(bool $forPreview = false): array
    {
        $rules = [
            'title' => $forPreview ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'max:10'],
            'note' => ['nullable', 'string', 'max:500'],
            'priority' => ['required', 'in:low,medium,high'],
            'image_file' => ['nullable', 'image', 'max:4096'],
        ];

        if ($this->url) {
            $rules['url'] = ['url', 'max:2048'];
        }

        if ($this->image_url) {
            $rules['image_url'] = ['url', 'max:2048'];
        }

        return $rules;
    }

    public function fetchPreview(): void
    {
        $this->normalizeFields();
        $this->previewError = null;

        if (! $this->url) {
            $this->previewError = 'Вставь ссылку, если хочешь подтянуть данные автоматически.';
            return;
        }

        $this->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        try {
            $data = $this->parseUrl($this->url);

            $this->title = $data['title'] ?: $this->title;
            $this->description = $data['description'] ?: $this->description;
            $this->store_name = $data['store_name'] ?: $this->store_name;
            $this->image_url = $data['image_url'] ?: $this->image_url;
            $this->price = $data['price'] ?: $this->price;
            $this->currency = $data['currency'] ?: $this->currency;
        } catch (\Throwable $e) {
            report($e);
            $this->previewError = 'Не удалось получить данные по ссылке. Заполни карточку вручную.';
        }
    }

    protected function parseUrl(string $url): array
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $host = Str::replaceFirst('www.', '', $host);

        if (Str::contains($host, 'ozon.')) {
            return $this->parseOzon($url);
        }

        if (Str::contains($host, 'wildberries.')) {
            return $this->parseWildberries($url);
        }

        if (Str::contains($host, 'market.yandex.') || Str::contains($host, 'yandex.market')) {
            return $this->parseYandexMarket($url);
        }

        return $this->parseGeneric($url);
    }

    protected function baseResult(string $url): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return [
            'url' => $url,
            'title' => null,
            'description' => null,
            'store_name' => Str::of($host)->replaceFirst('www.', '')->value(),
            'image_url' => null,
            'price' => null,
            'currency' => null,
        ];
    }

    protected function requestPage(string $url): ?string
    {
        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
            ])
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    protected function parseGeneric(string $url): array
    {
        $result = $this->baseResult($url);
        $html = $this->requestPage($url);

        if (! $html) {
            return $result;
        }

        $result['title'] = $this->extractMeta($html, 'property', 'og:title')
            ?: $this->extractMeta($html, 'name', 'twitter:title')
            ?: $this->extractJsonLdField($html, 'name')
            ?: $this->extractTitle($html);

        $result['description'] = $this->extractMeta($html, 'property', 'og:description')
            ?: $this->extractMeta($html, 'name', 'description')
            ?: $this->extractJsonLdField($html, 'description');

        $image = $this->extractMeta($html, 'property', 'og:image')
            ?: $this->extractMeta($html, 'name', 'twitter:image')
            ?: $this->extractJsonLdImage($html);

        if ($image) {
            $result['image_url'] = $this->makeAbsoluteUrl($image, $url);
        }

        $result['price'] = $this->extractJsonLdPrice($html)
            ?: $this->extractPrice($html);

        $result['currency'] = $this->extractJsonLdCurrency($html)
            ?: $this->detectCurrency($html);

        return $result;
    }

    protected function parseOzon(string $url): array
    {
        $result = $this->baseResult($url);
        $result['store_name'] = 'ozon.ru';

        $html = $this->requestPage($url);

        if (! $html) {
            return $result;
        }

        $result['title'] = $this->extractMeta($html, 'property', 'og:title')
            ?: $this->extractJsonLdField($html, 'name')
            ?: $this->extractTitle($html);

        $result['description'] = $this->extractMeta($html, 'property', 'og:description')
            ?: $this->extractJsonLdField($html, 'description');

        $image = $this->extractMeta($html, 'property', 'og:image')
            ?: $this->extractJsonLdImage($html);

        if ($image) {
            $result['image_url'] = $this->makeAbsoluteUrl($image, $url);
        }

        $result['price'] = $this->extractJsonLdPrice($html)
            ?: $this->extractPrice($html);

        $result['currency'] = $this->extractJsonLdCurrency($html)
            ?: '₽';

        return $result;
    }

    protected function parseWildberries(string $url): array
    {
        $result = $this->baseResult($url);
        $result['store_name'] = 'wildberries.ru';

        $html = $this->requestPage($url);

        if (! $html) {
            return $result;
        }

        $result['title'] = $this->extractMeta($html, 'property', 'og:title')
            ?: $this->extractJsonLdField($html, 'name')
            ?: $this->extractTitle($html);

        $result['description'] = $this->extractMeta($html, 'property', 'og:description')
            ?: $this->extractJsonLdField($html, 'description');

        $image = $this->extractMeta($html, 'property', 'og:image')
            ?: $this->extractJsonLdImage($html);

        if ($image) {
            $result['image_url'] = $this->makeAbsoluteUrl($image, $url);
        }

        $result['price'] = $this->extractJsonLdPrice($html)
            ?: $this->extractPrice($html);

        $result['currency'] = $this->extractJsonLdCurrency($html)
            ?: '₽';

        return $result;
    }

    protected function parseYandexMarket(string $url): array
    {
        $result = $this->baseResult($url);
        $result['store_name'] = 'market.yandex.ru';

        $html = $this->requestPage($url);

        if (! $html) {
            return $result;
        }

        $result['title'] = $this->extractMeta($html, 'property', 'og:title')
            ?: $this->extractJsonLdField($html, 'name')
            ?: $this->extractTitle($html);

        $result['description'] = $this->extractMeta($html, 'property', 'og:description')
            ?: $this->extractJsonLdField($html, 'description');

        $image = $this->extractMeta($html, 'property', 'og:image')
            ?: $this->extractJsonLdImage($html);

        if ($image) {
            $result['image_url'] = $this->makeAbsoluteUrl($image, $url);
        }

        $result['price'] = $this->extractJsonLdPrice($html)
            ?: $this->extractPrice($html);

        $result['currency'] = $this->extractJsonLdCurrency($html)
            ?: '₽';

        return $result;
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

    protected function extractJsonLdBlocks(string $html): array
    {
        preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches);

        return $matches[1] ?? [];
    }

    protected function extractJsonLdField(string $html, string $field): ?string
    {
        foreach ($this->extractJsonLdBlocks($html) as $block) {
            $decoded = json_decode(trim($block), true);

            if (! $decoded) {
                continue;
            }

            $value = $this->findJsonLdField($decoded, $field);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    protected function extractJsonLdImage(string $html): ?string
    {
        foreach ($this->extractJsonLdBlocks($html) as $block) {
            $decoded = json_decode(trim($block), true);

            if (! $decoded) {
                continue;
            }

            $image = $this->findJsonLdField($decoded, 'image');

            if (is_string($image) && trim($image) !== '') {
                return trim($image);
            }

            if (is_array($image)) {
                $first = collect($image)->first(fn ($item) => is_string($item) && trim($item) !== '');

                if ($first) {
                    return trim($first);
                }
            }
        }

        return null;
    }

    protected function extractJsonLdPrice(string $html): ?string
    {
        foreach ($this->extractJsonLdBlocks($html) as $block) {
            $decoded = json_decode(trim($block), true);

            if (! $decoded) {
                continue;
            }

            $price = $this->findJsonLdField($decoded, 'price');

            if (is_string($price) || is_numeric($price)) {
                return (string) $price;
            }
        }

        return null;
    }

    protected function extractJsonLdCurrency(string $html): ?string
    {
        foreach ($this->extractJsonLdBlocks($html) as $block) {
            $decoded = json_decode(trim($block), true);

            if (! $decoded) {
                continue;
            }

            $currency = $this->findJsonLdField($decoded, 'priceCurrency');

            if (! is_string($currency) || trim($currency) === '') {
                continue;
            }

            return match (Str::upper(trim($currency))) {
                'RUB' => '₽',
                'EUR' => '€',
                'USD' => '$',
                default => trim($currency),
            };
        }

        return null;
    }

    protected function findJsonLdField(mixed $data, string $field): mixed
    {
        if (is_array($data)) {
            if (array_key_exists($field, $data)) {
                return $data[$field];
            }

            foreach ($data as $item) {
                $found = $this->findJsonLdField($item, $field);

                if ($found !== null) {
                    return $found;
                }
            }
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
            '/(\d[\d\s.,]*)\s?\$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return trim(str_replace([' ', ','], ['', '.'], $matches[1]));
            }
        }

        return null;
    }

    protected function detectCurrency(string $html): ?string
    {
        if (str_contains($html, '₽')) {
            return '₽';
        }

        if (str_contains($html, '€')) {
            return '€';
        }

        if (str_contains($html, '$')) {
            return '$';
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

    public function save(): void
    {
        $this->normalizeFields();

        $validated = $this->validate($this->buildRules());

        $imageUrl = $validated['image_url'] ?? null;

        if ($this->image_file) {
            $path = $this->image_file->store('wishlist-items', 'public');
            $imageUrl = Storage::url($path);
        }

        WishlistItem::query()->create([
            'wishlist_id' => $this->wishlist->id,
            'created_by' => auth()->id(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'url' => $validated['url'] ?? null,
            'store_name' => $validated['store_name'] ?? null,
            'image_url' => $imageUrl,
            'price' => $validated['price'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'note' => $validated['note'] ?? null,
            'priority' => $validated['priority'],
        ]);

        $this->redirect(route('page-wishlist-show', ['wishlist' => $this->wishlist->id]), navigate: true);
    }
};
?>

<div class="min-h-screen bg-[#F3F0E8] px-4 py-4 pb-28 text-[#111111]">
    <div class="rounded-[32px] bg-white p-5">
        <div class="text-[12px] uppercase tracking-[0.18em] text-[#8B8B8B]">
            create
        </div>

        <h1 class="mt-2 text-[44px] font-semibold leading-[0.9]">
            NEW<br>ITEM
        </h1>
    </div>

    <div class="mt-4 space-y-3 rounded-[32px] bg-white p-5">
        <div>
            <label class="mb-2 block text-sm text-[#666666]">Link</label>
            <div class="flex gap-2">
                <input
                    wire:model.defer="url"
                    type="text"
                    class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base"
                    placeholder="Paste link or leave empty"
                >
                <button
                    wire:click="fetchPreview"
                    type="button"
                    class="rounded-[22px] bg-[#111111] px-4 py-4 text-sm font-medium text-white"
                >
                    Fetch
                </button>
            </div>
            @error('url') <div class="mt-1 text-sm text-red-500">{{ $message }}</div> @enderror
        </div>

        @if($previewError)
            <div class="rounded-[22px] bg-[#E8E3D8] px-4 py-4 text-sm text-[#111111]">
                {{ $previewError }}
            </div>
        @endif

        @if($image_file)
            <div class="h-48 overflow-hidden rounded-[24px] bg-[#F3F0E8]">
                <img src="{{ $image_file->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
            </div>
        @elseif($image_url)
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
            <label class="mb-2 block text-sm text-[#666666]">Description</label>
            <textarea wire:model.defer="description" rows="4" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base"></textarea>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mb-2 block text-sm text-[#666666]">Price</label>
                <input wire:model.defer="price" type="text" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base">
                @error('price') <div class="mt-1 text-sm text-red-500">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm text-[#666666]">Currency</label>
                <input wire:model.defer="currency" type="text" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base">
                @error('currency') <div class="mt-1 text-sm text-red-500">{{ $message }}</div> @enderror
            </div>
        </div>

        <div>
            <label class="mb-2 block text-sm text-[#666666]">Store</label>
            <input wire:model.defer="store_name" type="text" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base">
            @error('store_name') <div class="mt-1 text-sm text-red-500">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm text-[#666666]">Image link</label>
            <input wire:model.defer="image_url" type="text" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base" placeholder="Optional">
            @error('image_url') <div class="mt-1 text-sm text-red-500">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm text-[#666666]">Upload image</label>
            <input
                wire:model="image_file"
                type="file"
                accept="image/*"
                class="block w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-sm"
            >
            @error('image_file') <div class="mt-1 text-sm text-red-500">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm text-[#666666]">Note</label>
            <textarea wire:model.defer="note" rows="3" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base"></textarea>
            @error('note') <div class="mt-1 text-sm text-red-500">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm text-[#666666]">Priority</label>
            <select wire:model.defer="priority" class="w-full rounded-[22px] border-0 bg-[#F3F0E8] px-4 py-4 text-base">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
            </select>
            @error('priority') <div class="mt-1 text-sm text-red-500">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 p-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <button
            wire:click="save"
            class="block w-full rounded-[24px] bg-[#111111] px-5 py-4 text-center text-sm font-medium text-white"
        >
            Save item
        </button>
    </div>
</div>