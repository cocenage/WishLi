<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title>{{ $title ?? config('app.name') }}</title>

    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="min-h-screen relative overflow-x-hidden bg-[#E7E3DD]">
    <!-- фон -->
    <div class="pointer-events-none fixed inset-0 -z-10">
        <div class="absolute inset-0 bg-[linear-gradient(180deg,_#F2EEE7_0%,_#E7E3DD_60%,_#DED8CF_100%)]"></div>

        <div class="absolute top-[-120px] left-1/2 h-[320px] w-[320px] -translate-x-1/2 rounded-full bg-white/40 blur-[100px]"></div>
    </div>

    {{ $slot }}

    @livewireScripts

    <script>
        window.WishliTelegram = {
            tg: window.Telegram?.WebApp,

            init() {
                const tg = this.tg;

                if (!tg) {
                    return;
                }

                tg.ready();
                tg.expand();

                document.documentElement.style.setProperty(
                    '--tg-height',
                    `${tg.viewportStableHeight || window.innerHeight}px`
                );

                tg.onEvent?.('viewportChanged', () => {
                    document.documentElement.style.setProperty(
                        '--tg-height',
                        `${tg.viewportStableHeight || window.innerHeight}px`
                    );
                });
            },

            success() {
                this.tg?.HapticFeedback?.notificationOccurred?.('success');
            },

            error() {
                this.tg?.HapticFeedback?.notificationOccurred?.('error');
            },

            share(url, text = '') {
                const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`;

                if (this.tg?.openTelegramLink) {
                    this.tg.openTelegramLink(shareUrl);
                    return;
                }

                window.open(shareUrl, '_blank');
            },

            close() {
                this.tg?.close?.();
            }
        };

        window.WishliTelegram.init();

        document.addEventListener('livewire:init', () => {
            Livewire.on('wishlist-share-ready', (event) => {
                const payload = Array.isArray(event) ? event[0] : event;

                window.WishliTelegram.share(
                    payload.url,
                    `Присоединяйся к моему вишлисту: ${payload.title}`
                );
            });
        });
    </script>
</body>

</html>