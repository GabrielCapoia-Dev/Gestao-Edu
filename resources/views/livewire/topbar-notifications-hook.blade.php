@php
    $centralUrl = \App\Filament\Admin\Pages\CentralNotificacoes::getUrl();
@endphp

<style>
    .notif-wrapper {
        display: inline-flex;
        align-items: center;
    }

    .notif-bell-btn {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border: 2px solid #111827;
        border-radius: 50%;
        background-color: #ffffff;
        color: #111827;
        text-decoration: none;
        transition: background-color .18s ease, transform .18s ease, border-color .18s ease, color .18s ease;
    }

    .notif-bell-btn:hover {
        background-color: #f3f4f6;
        border-color: #000000;
        color: #000000;
        transform: translateY(-1px);
    }

    .notif-bell-btn svg {
        width: 20px;
        height: 20px;
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -7px;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
        border: 2px solid #17368d;
        border-radius: 999px;
        background: #ef4444;
        color: #fff;
        font-size: 10px;
        font-weight: 800;
        line-height: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
</style>

<div
    class="notif-wrapper"
    data-notification-topbar
    data-count-url="{{ route('notifications.unreadCount') }}"
>
    <a
        href="{{ $centralUrl }}"
        class="notif-bell-btn"
        aria-label="Abrir central de notificações"
        title="Central de notificações"
    >
        <x-heroicon-o-bell />
        <span class="notification-badge" data-notification-badge hidden>0</span>
    </a>

    <audio data-notification-topbar-sound preload="auto">
        <source src="{{ asset('sons/som-notificacao.MP3') }}" type="audio/mpeg">
    </audio>
</div>

<script>
    (() => {
        if (window.__gestaoEduNotificationTopbarReady) {
            return;
        }

        window.__gestaoEduNotificationTopbarReady = true;

        let lastChangeToken = null;
        let soundReady = false;

        const updateBadges = (count) => {
            document.querySelectorAll('[data-notification-badge]').forEach((badge) => {
                badge.hidden = count < 1;
                badge.textContent = count > 99 ? '99+' : String(count);
            });
        };

        const playSound = () => {
            const topbar = document.querySelector('[data-notification-topbar]');
            const audio = topbar?.querySelector('[data-notification-topbar-sound]');
            const now = Date.now();

            if (!audio || !soundReady) {
                return;
            }

            if (window.__gestaoEduSuppressNotificationSoundUntil && now < window.__gestaoEduSuppressNotificationSoundUntil) {
                return;
            }

            if (window.__gestaoEduLastNotificationSoundAt && now - window.__gestaoEduLastNotificationSoundAt < 3000) {
                return;
            }

            window.__gestaoEduLastNotificationSoundAt = now;
            audio.currentTime = 0;
            audio.play().catch(() => {});
        };

        const refresh = async () => {
            const topbar = document.querySelector('[data-notification-topbar]');

            if (!topbar || document.visibilityState !== 'visible') {
                return;
            }

            try {
                const response = await fetch(topbar.dataset.countUrl, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                updateBadges(Number(data.unread ?? 0));

                if (lastChangeToken === null) {
                    lastChangeToken = data.change_token ?? null;
                    return;
                }

                if (data.change_token && data.change_token !== lastChangeToken) {
                    lastChangeToken = data.change_token;
                    playSound();
                }
            } catch (error) {
                //
            }
        };

        window.addEventListener('gestaoedu:notifications-count', (event) => {
            updateBadges(Number(event.detail?.unread ?? 0));
        });

        window.addEventListener('pointerdown', () => {
            soundReady = true;
        }, { once: true });

        window.addEventListener('keydown', () => {
            soundReady = true;
        }, { once: true });

        refresh();
        setInterval(refresh, 10000);
    })();
</script>
