<style>
    .az-toast-region {
        position: fixed;
        top: 1.5rem;
        right: 1.5rem;
        z-index: 99999;
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
        width: min(390px, calc(100vw - 2rem));
        pointer-events: none;
    }

    .az-toast {
        --az-toast-color: #173f35;
        --az-toast-soft: #edf5f2;

        position: relative;
        display: grid;
        grid-template-columns: 44px minmax(0, 1fr) 34px;
        align-items: start;
        gap: 0.9rem;
        width: 100%;
        padding: 1rem 1rem 1rem 1.05rem;
        overflow: hidden;
        color: #173f35;
        background: rgba(255, 255, 255, 0.98);
        border: 1px solid rgba(23, 63, 53, 0.11);
        border-left: 4px solid var(--az-toast-color);
        border-radius: 14px;
        box-shadow:
            0 18px 45px rgba(18, 42, 36, 0.16),
            0 4px 12px rgba(18, 42, 36, 0.08);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        pointer-events: auto;
        animation: azToastEnter 0.35s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .az-toast::after {
        content: "";
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 3px;
        background: var(--az-toast-color);
        transform-origin: left center;
        animation: azToastProgress 6s linear forwards;
    }

    .az-toast.is-leaving {
        animation: azToastLeave 0.25s ease forwards;
    }

    .az-toast.is-leaving::after {
        animation-play-state: paused;
    }

    .az-toast__icon {
        display: grid;
        width: 44px;
        height: 44px;
        place-items: center;
        flex-shrink: 0;
        color: var(--az-toast-color);
        background: var(--az-toast-soft);
        border-radius: 50%;
    }

    .az-toast__icon .material-symbols-outlined {
        font-size: 24px;
        line-height: 1;
        font-variation-settings:
            "FILL" 1,
            "wght" 500,
            "GRAD" 0,
            "opsz" 24;
    }

    .az-toast__content {
        min-width: 0;
        padding-top: 0.1rem;
    }

    .az-toast__content strong {
        display: block;
        margin: 0 0 0.25rem;
        color: #173f35;
        font-family: inherit;
        font-size: 0.95rem;
        font-weight: 700;
        line-height: 1.3;
    }

    .az-toast__content p {
        margin: 0;
        color: #66736f;
        font-family: inherit;
        font-size: 0.875rem;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .az-toast__close {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        padding: 0;
        color: #77827f;
        background: transparent;
        border: 0;
        border-radius: 50%;
        cursor: pointer;
        transition:
            color 0.2s ease,
            background-color 0.2s ease,
            transform 0.2s ease;
    }

    .az-toast__close:hover {
        color: #173f35;
        background: #edf2f0;
        transform: scale(1.05);
    }

    .az-toast__close:focus-visible {
        outline: 2px solid var(--az-toast-color);
        outline-offset: 2px;
    }

    .az-toast__close .material-symbols-outlined {
        font-size: 20px;
        line-height: 1;
        font-variation-settings:
            "FILL" 0,
            "wght" 500,
            "GRAD" 0,
            "opsz" 20;
    }

    .az-toast--success {
        --az-toast-color: #237a57;
        --az-toast-soft: #e8f5ee;
    }

    .az-toast--error {
        --az-toast-color: #b83b3b;
        --az-toast-soft: #fbecec;
    }

    .az-toast--warning {
        --az-toast-color: #b7791f;
        --az-toast-soft: #fff4dd;
    }

    .az-toast--info {
        --az-toast-color: #356c8c;
        --az-toast-soft: #e9f3f8;
    }

    @keyframes azToastEnter {
        from {
            opacity: 0;
            transform: translate3d(45px, -6px, 0) scale(0.97);
        }

        to {
            opacity: 1;
            transform: translate3d(0, 0, 0) scale(1);
        }
    }

    @keyframes azToastLeave {
        from {
            opacity: 1;
            transform: translate3d(0, 0, 0) scale(1);
        }

        to {
            opacity: 0;
            transform: translate3d(45px, 0, 0) scale(0.96);
        }
    }

    @keyframes azToastProgress {
        from {
            transform: scaleX(1);
        }

        to {
            transform: scaleX(0);
        }
    }

    @media (max-width: 640px) {
        .az-toast-region {
            top: 1rem;
            right: 1rem;
            left: 1rem;
            width: auto;
        }

        .az-toast {
            grid-template-columns: 40px minmax(0, 1fr) 32px;
            gap: 0.75rem;
            padding: 0.9rem;
            border-radius: 13px;
        }

        .az-toast__icon {
            width: 40px;
            height: 40px;
        }

        .az-toast__content strong {
            font-size: 0.9rem;
        }

        .az-toast__content p {
            font-size: 0.825rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .az-toast,
        .az-toast.is-leaving,
        .az-toast::after {
            animation: none;
        }
    }
</style>

<div
    class="az-toast-region"
    data-az-toast-region
    aria-live="polite"
    aria-atomic="true"
>
    @if(session('success'))
        <div
            class="az-toast az-toast--success"
            data-az-toast
            role="status"
        >
            <div class="az-toast__icon" aria-hidden="true">
                <span class="material-symbols-outlined">check_circle</span>
            </div>

            <div class="az-toast__content">
                <strong>Completed</strong>
                <p>{{ session('success') }}</p>
            </div>

            <button
                type="button"
                class="az-toast__close"
                data-az-toast-close
                aria-label="Dismiss notification"
            >
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div
            class="az-toast az-toast--error"
            data-az-toast
            role="alert"
        >
            <div class="az-toast__icon" aria-hidden="true">
                <span class="material-symbols-outlined">error</span>
            </div>

            <div class="az-toast__content">
                <strong>Action failed</strong>
                <p>{{ session('error') }}</p>
            </div>

            <button
                type="button"
                class="az-toast__close"
                data-az-toast-close
                aria-label="Dismiss notification"
            >
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
    @endif

    @if(session('warning'))
        <div
            class="az-toast az-toast--warning"
            data-az-toast
            role="status"
        >
            <div class="az-toast__icon" aria-hidden="true">
                <span class="material-symbols-outlined">warning</span>
            </div>

            <div class="az-toast__content">
                <strong>Please note</strong>
                <p>{{ session('warning') }}</p>
            </div>

            <button
                type="button"
                class="az-toast__close"
                data-az-toast-close
                aria-label="Dismiss notification"
            >
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
    @endif

    @if(session('info'))
        <div
            class="az-toast az-toast--info"
            data-az-toast
            role="status"
        >
            <div class="az-toast__icon" aria-hidden="true">
                <span class="material-symbols-outlined">info</span>
            </div>

            <div class="az-toast__content">
                <strong>Information</strong>
                <p>{{ session('info') }}</p>
            </div>

            <button
                type="button"
                class="az-toast__close"
                data-az-toast-close
                aria-label="Dismiss notification"
            >
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div
            class="az-toast az-toast--error"
            data-az-toast
            role="alert"
        >
            <div class="az-toast__icon" aria-hidden="true">
                <span class="material-symbols-outlined">error</span>
            </div>

            <div class="az-toast__content">
                <strong>Please review the form</strong>
                <p>{{ $errors->first() }}</p>
            </div>

            <button
                type="button"
                class="az-toast__close"
                data-az-toast-close
                aria-label="Dismiss notification"
            >
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>
    @endif
</div>

<script>
    (() => {
        const initialiseAzariToasts = () => {
            document.querySelectorAll('[data-az-toast]').forEach((toast) => {
                if (toast.dataset.azToastReady === 'true') {
                    return;
                }

                toast.dataset.azToastReady = 'true';

                let dismissTimer = null;

                const dismissToast = () => {
                    if (toast.classList.contains('is-leaving')) {
                        return;
                    }

                    window.clearTimeout(dismissTimer);
                    toast.classList.add('is-leaving');

                    window.setTimeout(() => {
                        toast.remove();
                    }, 260);
                };

                const closeButton = toast.querySelector('[data-az-toast-close]');

                if (closeButton) {
                    closeButton.addEventListener('click', dismissToast);
                }

                toast.addEventListener('mouseenter', () => {
                    window.clearTimeout(dismissTimer);

                    const progressBar = window.getComputedStyle(toast, '::after');
                    void progressBar;
                    toast.style.setProperty('--az-toast-paused', '1');
                });

                toast.addEventListener('mouseleave', () => {
                    dismissTimer = window.setTimeout(dismissToast, 2500);
                });

                dismissTimer = window.setTimeout(dismissToast, 6000);
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initialiseAzariToasts);
        } else {
            initialiseAzariToasts();
        }

        document.addEventListener('livewire:navigated', initialiseAzariToasts);
    })();
</script>