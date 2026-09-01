@auth
    <script>
        (() => {
            if (window.__gestaoEduPresenceHeartbeatReady) {
                return;
            }

            window.__gestaoEduPresenceHeartbeatReady = true;

            const endpoint = @json(route('presence.heartbeat'));
            const csrfToken = @json(csrf_token());
            let intervalId = null;

            const ping = async () => {
                if (document.visibilityState !== 'visible') {
                    return;
                }

                try {
                    await fetch(endpoint, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                        },
                        body: '{}',
                    });
                } catch (error) {
                    //
                }
            };

            const stop = () => {
                if (intervalId !== null) {
                    clearInterval(intervalId);
                    intervalId = null;
                }
            };

            const start = () => {
                if (intervalId !== null) {
                    return;
                }

                intervalId = setInterval(ping, {{ (int) config('performance.heartbeat_interval_seconds', 120) * 1000 }});
            };

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    start();
                    ping();
                } else {
                    stop();
                }
            });

            ping();
            start();
        })();
    </script>
@endauth
