(() => {
    if (window.__AZARI_PWA_INITIALISED__) return;
    window.__AZARI_PWA_INITIALISED__ = true;

    let deferredPrompt = null;

    // Ensure PWA metadata exists even on layouts that do not have it yet.
    if (!document.querySelector('link[rel="manifest"]')) {
        const manifest = document.createElement('link');
        manifest.rel = 'manifest';
        manifest.href = '/manifest.webmanifest';
        document.head.appendChild(manifest);
    }

    if (!document.querySelector('meta[name="theme-color"]')) {
        const theme = document.createElement('meta');
        theme.name = 'theme-color';
        theme.content = '#0c2b24';
        document.head.appendChild(theme);
    }

    if (!document.querySelector('link[rel="apple-touch-icon"]')) {
        const icon = document.createElement('link');
        icon.rel = 'apple-touch-icon';
        icon.href = '/public/images/azari-favicon.png';
        document.head.appendChild(icon);
    }

    const standalone = () =>
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true;

    const notify = message => {
        let toast = document.getElementById('azari-pwa-toast');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'azari-pwa-toast';

            Object.assign(toast.style, {
                position: 'fixed',
                left: '50%',
                bottom: '28px',
                transform: 'translateX(-50%) translateY(20px)',
                maxWidth: 'min(92vw, 460px)',
                padding: '14px 18px',
                borderRadius: '14px',
                background: '#0c2b24',
                color: '#fff',
                fontFamily: 'inherit',
                fontSize: '14px',
                lineHeight: '1.5',
                boxShadow: '0 16px 45px rgba(0,0,0,.24)',
                zIndex: '999999',
                opacity: '0',
                transition: 'opacity .2s ease, transform .2s ease'
            });

            document.body.appendChild(toast);
        }

        toast.textContent = message;
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(-50%) translateY(0)';

        clearTimeout(window.__AZARI_PWA_TOAST_TIMEOUT__);

        window.__AZARI_PWA_TOAST_TIMEOUT__ = setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(-50%) translateY(20px)';
        }, 5000);
    };

    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        deferredPrompt = event;
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;

        document.querySelectorAll('[data-azari-pwa-install]').forEach(button => {
            const label = button.querySelector('[data-pwa-label]');
            if (label) label.textContent = 'App installed';
        });

        notify('Azari Hotels & Residences has been installed.');
    });

    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-azari-pwa-install]');

        if (!button) return;

        event.preventDefault();

        if (standalone()) {
            notify('The Azari app is already installed on this device.');
            return;
        }

        if (deferredPrompt) {
            deferredPrompt.prompt();

            try {
                await deferredPrompt.userChoice;
            } catch (_) {}

            deferredPrompt = null;
            return;
        }

        if (/Android/i.test(navigator.userAgent)) {
            notify(
                'Open your browser menu and choose "Install app" or "Add to Home screen".'
            );
            return;
        }

        notify('Use your browser installation option to install the Azari app.');
    });

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker
                .register('/service-worker.js', { scope: '/' })
                .catch(error => {
                    console.error('Azari PWA registration failed:', error);
                });
        });
    }
})();
