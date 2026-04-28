<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>Wishli</title>

    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-[#F4F3EF] px-5 text-[#141414]">
    <div class="mx-auto flex min-h-screen w-full max-w-[430px] items-center justify-center">
        <div class="w-full rounded-[38px] bg-white/70 p-7 text-center shadow-[0_18px_50px_rgba(0,0,0,0.06)] backdrop-blur-xl">
            <div class="mx-auto flex h-[74px] w-[74px] items-center justify-center rounded-full bg-[#171717] text-[34px] text-white">
                🎁
            </div>

            <div class="mt-6 text-[58px] font-semibold leading-none tracking-[-0.08em]">
                Wishli
            </div>

            <div id="status" class="mx-auto mt-4 max-w-[280px] text-[15px] leading-[1.35] text-[#6C6A64]">
                Входим через Telegram...
            </div>

            @if(app()->environment('local'))
                <a
                    href="{{ route('dev-login') }}"
                    id="dev-login"
                    class="mt-6 hidden h-[58px] items-center justify-center rounded-full bg-[#171717] px-7 text-[15px] font-medium text-white"
                >
                    Dev login
                </a>
            @endif
        </div>
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
                    devLogin.classList.add('inline-flex');
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