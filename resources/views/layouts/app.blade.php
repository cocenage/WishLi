<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title>{{ $title ?? 'Wishli' }}</title>

    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles

    <style>
        html,
        body {
            min-height: 100%;
            background: #F4F3EF;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden bg-[#F4F3EF] text-[#141414] antialiased">
    <div class="pointer-events-none fixed inset-0 -z-10">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_-10%,rgba(255,255,255,0.95),rgba(244,243,239,0.7)_38%,rgba(232,228,219,0.9)_100%)]"></div>
        <div class="absolute left-1/2 top-[-120px] h-[320px] w-[320px] -translate-x-1/2 rounded-full bg-white/70 blur-[90px]"></div>
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

            impact() {
                this.tg?.HapticFeedback?.impactOccurred?.('light');
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
                    `Мой вишлист: ${payload.title}`
                );
            });

            Livewire.on('telegram-haptic-success', () => {
                window.WishliTelegram.success();
            });

            Livewire.on('telegram-haptic-error', () => {
                window.WishliTelegram.error();
            });

            Livewire.on('telegram-haptic-impact', () => {
                window.WishliTelegram.impact();
            });
        });
    </script>
</body>
</html>