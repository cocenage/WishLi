@props([
    'model' => 'open',
    'maxWidth' => 'max-w-[768px]',
])

<div
    x-data="{ open: @entangle($attributes->wire('model')) }"
    x-cloak
>
    <div
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm"
        @click="open = false"
    ></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        class="fixed inset-x-0 bottom-0 z-50 mx-auto w-full {{ $maxWidth }}"
    >
        <div class="rounded-t-[32px] bg-white p-5 shadow-2xl">
            <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-[#dbe3ec]"></div>

            {{ $slot }}
        </div>
    </div>
</div>