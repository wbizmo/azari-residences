<div data-conversation-poll
    data-poll-endpoint="{{ $pollUrl }}"
    data-current-url="{{ request()->url() }}"
    data-latest-id="{{ (int) $lastKnown }}">
    <p role="status" aria-live="polite" data-poll-announcement>
        Replies are checked automatically while this conversation is open.
    </p>
    <a class="az-user-button az-user-button--light" href="{{ request()->url() }}"
        data-poll-new-link hidden>View new replies</a>
</div>
<script>
(() => {
    'use strict';
    document.querySelectorAll('[data-conversation-poll]').forEach(root => {
        if (root.dataset.pollStarted === 'yes') return;
        root.dataset.pollStarted = 'yes';
        const status = root.querySelector('[data-poll-announcement]');
        const link = root.querySelector('[data-poll-new-link]');
        let latest = Number(root.dataset.latestId) || 0;
        let fetching = false;
        let failures = 0;

        const check = async () => {
            if (document.hidden || fetching || !root.isConnected) return;
            fetching = true;
            try {
                const endpoint = new URL(root.dataset.pollEndpoint, location.origin);
                if (endpoint.origin !== location.origin) return;
                const response = await fetch(endpoint, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {'Accept': 'application/json'}
                });
                if (!response.ok) throw new Error('Unable to check for replies.');
                const result = await response.json();
                if (!Number.isSafeInteger(result.latest_id)
                    || !Number.isSafeInteger(result.last_page)) {
                    throw new Error('Unexpected conversation status.');
                }
                if (result.latest_id > latest) {
                    const destination = new URL(root.dataset.currentUrl, location.origin);
                    destination.searchParams.set('page', String(Math.max(1, result.last_page)));
                    link.href = destination.href;
                    link.hidden = false;
                    status.textContent = 'New conversation replies are available. Your current draft was not changed.';
                } else if (result.closed) {
                    status.textContent = 'This conversation has been closed.';
                } else if (!link.hidden) {
                    // Keep the alert visible until the user chooses to refresh.
                } else {
                    status.textContent = 'Conversation up to date.';
                }
                failures = 0;
            } catch {
                failures++;
                if (failures >= 2) {
                    status.textContent = 'Automatic updates are unavailable. Refresh manually to check for replies.';
                }
            } finally {
                fetching = false;
            }
        };
        const interval = window.setInterval(check, 25000);
        window.addEventListener('pagehide', () => window.clearInterval(interval), {once: true});
    });
})();
</script>
