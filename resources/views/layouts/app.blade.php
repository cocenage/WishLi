<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="min-h-screen relative overflow-x-hidden bg-[#E7E3DD]">
    <!-- фон -->
    <div class="pointer-events-none fixed inset-0 -z-10">
        <!-- основной градиент -->
        <div class="absolute inset-0 bg-[linear-gradient(180deg,_#F2EEE7_0%,_#E7E3DD_60%,_#DED8CF_100%)]"></div>

        <!-- одно мягкое светлое пятно -->
        <div
            class="absolute top-[-120px] left-1/2 h-[320px] w-[320px] -translate-x-1/2 rounded-full bg-white/40 blur-[100px]">
        </div>
    </div>

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
                document.cookie = `tg_init_data=${encodeURIComponent(initData)}; path=/; SameSite=Lax; Secure`;
            }
        }
    </script>
</body>

</html>