document.querySelectorAll('[data-reserva-share]').forEach((button) => {
    button.addEventListener('click', async () => {
        const url = button.dataset.reservaShare || window.location.href;
        const title = button.dataset.shareTitle || document.title;

        try {
            if (navigator.share) {
                await navigator.share({ title, url });
                return;
            }

            await navigator.clipboard.writeText(url);
            const original = button.textContent;
            button.textContent = 'Link copied';
            window.setTimeout(() => {
                button.textContent = original;
            }, 1800);
        } catch (error) {
            if (error?.name !== 'AbortError') {
                window.dispatchEvent(new CustomEvent('reserva:share-failed'));
            }
        }
    });
});
