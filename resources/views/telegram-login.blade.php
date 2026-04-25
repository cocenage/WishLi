<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход через Telegram</title>

    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-[#E7E3DD] flex items-center justify-center px-5">
    <div class="w-full max-w-[420px] rounded-[32px] bg-white/80 p-6 text-center shadow-sm">
        <div class="text-[22px] font-semibold text-[#1F1F1F]">
            Входим через Telegram
        </div>

        <div id="status" class="mt-3 text-[15px] text-[#6B6B6B]">
            Проверяем данные приложения...
        </div>

        <a
            href="{{ route('dev-login') }}"
            class="mt-6 hidden rounded-[18px] bg-black px-5 py-3 text-white"
            id="dev-login"
        >
            Dev login
        </a>
    </div>

    <script>
        const statusEl = document.getElementById('status');
        const devLogin = document.getElementById('dev-login');

        async function authTelegram() {
            const tg = window.Telegram?.WebApp;

            if (!tg) {
                statusEl.textContent = 'Открой приложение через Telegram.';

                @if (app()->environment('local'))
                    devLogin.classList.remove('hidden');
                    devLogin.classList.add('inline-block');
                @endif

                return;
            }

            tg.ready();
            tg.expand();

            const initData = tg.initData || '';

            if (!initData) {
                statusEl.textContent = 'Telegram не передал initData. Открой приложение именно из бота.';
                return;
            }

            try {
                const response = await fetch('{{ route('telegram.auth') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Telegram-Init-Data': initData,
                    },
                    body: JSON.stringify({
                        init_data: initData,
                    }),
                });

                const data = await response.json();

                if (!response.ok || !data.ok) {
                    statusEl.textContent = data.message || 'Ошибка авторизации.';
                    return;
                }

                window.location.href = data.redirect || '{{ route('page-wishlists') }}';
            } catch (e) {
                statusEl.textContent = 'Ошибка соединения с сервером.';
            }
        }

        authTelegram();
    </script>
</body>
</html>