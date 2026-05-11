@auth
    <script>
        (() => {
            if (window.__gestaoEduPresenceHeartbeatReady) {
                return;
            }

            window.__gestaoEduPresenceHeartbeatReady = true;

            const endpoint = @json(route('presence.heartbeat'));
            const csrfToken = @json(csrf_token());

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

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    ping();
                }
            });

            ping();
            setInterval(ping, 10000);
        })();
    </script>
@endauth
