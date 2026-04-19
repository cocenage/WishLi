<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body>
    {{ $slot }}

    @livewireScripts
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        const tg = window.Telegram?.WebApp;

        if (tg) {
            tg.ready();
            tg.expand();

            const initData = tg.initData || '';

            if (initData) {
                document.cookie = `tg_init_data=${encodeURIComponent(initData)}; path=/; SameSite=Lax`;
            }
        }
    </script>
</body>

</html>