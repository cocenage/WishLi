<?php

namespace App\Services\Wishlist;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LinkPreviewService
{
    public function extract(string $url): array
    {
        $result = [
            'url' => $url,
            'title' => null,
            'description' => null,
            'store_name' => null,
            'image_url' => null,
            'price' => null,
            'currency' => null,
        ];

        $response = Http::timeout(10)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 WishlistBot/1.0',
            ])
            ->get($url);

        if (! $response->successful()) {
            return $this->fillStoreName($result, $url);
        }

        $html = $response->body();

        $result['title'] = $this->extractMeta($html, 'property', 'og:title')
            ?: $this->extractMeta($html, 'name', 'twitter:title')
            ?: $this->extractTitle($html);

        $result['description'] = $this->extractMeta($html, 'property', 'og:description')
            ?: $this->extractMeta($html, 'name', 'description');

        $image = $this->extractMeta($html, 'property', 'og:image')
            ?: $this->extractMeta($html, 'name', 'twitter:image');

        if ($image) {
            $result['image_url'] = $this->makeAbsoluteUrl($image, $url);
        }

        $result['price'] = $this->extractPrice($html);
        $result['currency'] = $this->detectCurrency($html);

        return $this->fillStoreName($result, $url);
    }

    protected function fillStoreName(array $result, string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);

        $result['store_name'] = Str::of((string) $host)
            ->replace('www.', '')
            ->value();

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
}