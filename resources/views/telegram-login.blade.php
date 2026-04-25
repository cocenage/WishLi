<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Wishli</title>

    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-[#E7E3DD] flex items-center justify-center px-5">
    <div class="w-full max-w-[420px] rounded-[32px] bg-white/70 p-6 text-center shadow-sm backdrop-blur">
        <div class="text-[30px] font-semibold tracking-[-0.05em] text-[#171717]">
            Wishli
        </div>

        <div id="status" class="mt-3 text-[15px] text-[#666]">
            Входим через Telegram...
        </div>

        @if(app()->environment('local'))
            <a
                href="{{ route('dev-login') }}"
                id="dev-login"
                class="mt-6 hidden rounded-[22px] bg-black px-6 py-4 text-white"
            >
                Dev login
            </a>
        @endif
    </div>

    <script>
        const statusEl = document.getElementById('status');
        const devLogin = document.getElementById('dev-login');

        async function authTelegram() {
            const tg = window.Telegram?.WebApp;

            if (!tg) {
                statusEl.textContent = 'Открой приложение через Telegram.';

                if (devLogin) {
                    devLogin.classList.remove('hidden');
                    devLogin.classList.add('inline-block');
                }

                return;
            }

            tg.ready();
            tg.expand();

            const initData = tg.initData || '';
            const startParam = tg.initDataUnsafe?.start_param || '';

            if (!initData) {
                statusEl.textContent = 'Telegram не передал initData. Открой Mini App из бота.';
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
                        start_param: startParam,
                    }),
                });

                const data = await response.json();

                if (!response.ok || !data.ok) {
                    statusEl.textContent = data.message || 'Ошибка авторизации.';
                    return;
                }

                tg.HapticFeedback?.notificationOccurred?.('success');

                window.location.href = data.redirect;
            } catch (e) {
                statusEl.textContent = 'Ошибка соединения с сервером.';
            }
        }

        authTelegram();
    </script>
</body>
</html>