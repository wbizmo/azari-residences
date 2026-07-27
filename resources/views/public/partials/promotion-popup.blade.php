@if(isset($homepagePromotion) && $homepagePromotion)
@php
    $promotionFingerprint = sha1(implode('|', [
        (string) $homepagePromotion->id,
        (string) optional($homepagePromotion->updated_at)->timestamp,
        (string) $homepagePromotion->title,
        (string) $homepagePromotion->summary,
        (string) $homepagePromotion->body,
        (string) $homepagePromotion->cta_label,
        (string) $homepagePromotion->cta_url,
    ]));
@endphp

<div
    class="az-offer-modal"
    data-promotion-popup
    data-promotion-fingerprint="{{ $promotionFingerprint }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="az-offer-title"
    aria-describedby="az-offer-description"
    hidden
>
    <button
        type="button"
        class="az-offer-modal__backdrop"
        data-promotion-close
        aria-label="Close promotion"
    ></button>

    <article class="az-offer-card" data-promotion-dialog tabindex="-1">
        <div class="az-offer-card__visual">
            <img
                src="{{ asset('images/azari-promotions.png') }}"
                alt=""
                aria-hidden="true"
                loading="eager"
                decoding="async"
            >

            <div class="az-offer-card__visual-overlay" aria-hidden="true"></div>

            <span class="az-offer-card__badge">
                <span class="material-symbols-outlined" aria-hidden="true">redeem</span>
                Exclusive promotion
            </span>

            <button
                type="button"
                class="az-offer-card__close"
                data-promotion-close
                aria-label="Close promotion"
            >
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>

        <div class="az-offer-card__lower">
            <div class="az-offer-card__content" data-promotion-content>
                <p class="az-offer-card__eyebrow">A special invitation</p>

                <h2 id="az-offer-title">{{ $homepagePromotion->title }}</h2>

                @if(filled($homepagePromotion->summary))
                    <p class="az-offer-card__summary" id="az-offer-description">
                        {{ $homepagePromotion->summary }}
                    </p>
                @elseif(filled($homepagePromotion->body))
                    <p class="sr-only" id="az-offer-description">Promotion details</p>
                @else
                    <p class="sr-only" id="az-offer-description">Current homepage promotion</p>
                @endif

                @if(filled($homepagePromotion->body))
                    <div class="az-offer-card__copy">
                        {!! nl2br(e($homepagePromotion->body)) !!}
                    </div>
                @endif
            </div>

            <footer class="az-offer-card__actions">
                @if(filled($homepagePromotion->cta_label) && filled($homepagePromotion->cta_url))
                    <a
                        href="{{ $homepagePromotion->cta_url }}"
                        class="az-offer-card__cta"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <span>{{ $homepagePromotion->cta_label }}</span>
                        <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                    </a>
                @endif

                <button
                    type="button"
                    class="az-offer-card__later"
                    data-promotion-close
                >
                    Maybe later
                </button>
            </footer>
        </div>
    </article>
</div>

@push('head')
<style>
.az-offer-modal{
    position:fixed;
    z-index:9000;
    inset:0;
    display:grid;
    place-items:center;
    padding:clamp(18px,3vw,36px);
    opacity:0;
    visibility:hidden;
    pointer-events:none;
    transition:opacity .22s ease,visibility .22s ease;
}

.az-offer-modal.is-visible{
    opacity:1;
    visibility:visible;
    pointer-events:auto;
}

.az-offer-modal__backdrop{
    position:absolute;
    inset:0;
    width:100%;
    height:100%;
    margin:0;
    padding:0;
    border:0;
    border-radius:0;
    background:
        radial-gradient(circle at 50% 15%,rgba(185,149,90,.16),transparent 34rem),
        rgba(3,14,10,.84);
    -webkit-backdrop-filter:blur(14px) saturate(.8);
    backdrop-filter:blur(14px) saturate(.8);
    cursor:default;
}

.az-offer-card{
    position:relative;
    display:block;
    box-sizing:border-box;
    z-index:1;
    width:min(100%,680px);
    max-height:calc(100dvh - 56px);
    overflow:hidden;
    color:#18231f;
    background:#fffdf8;
    border:1px solid rgba(255,255,255,.25);
    border-radius:0;
    outline:none;
    box-shadow:
        0 42px 120px rgba(0,0,0,.5),
        0 0 0 1px rgba(185,149,90,.12);
    transform:translateY(18px) scale(.985);
    transition:transform .22s cubic-bezier(.2,.8,.2,1);
}

.az-offer-modal.is-visible .az-offer-card{
    transform:none;
}

.az-offer-card__visual{
    position:relative;
    display:block;
    box-sizing:border-box;
    width:100%;
    height:clamp(210px,32vw,285px);
    min-height:0;
    max-height:none;
    aspect-ratio:auto;
    overflow:hidden;
    background:#10281f;
}

.az-offer-card__visual img{
    display:block;
    width:100%!important;
    max-width:none!important;
    height:100%!important;
    object-fit:cover;
    object-position:center;
}

.az-offer-card__visual-overlay{
    position:absolute;
    inset:0;
    pointer-events:none;
    background:
        linear-gradient(180deg,rgba(3,15,11,.08),rgba(3,15,11,.08) 42%,rgba(3,15,11,.68) 100%),
        linear-gradient(90deg,rgba(3,15,11,.26),transparent 58%);
}

.az-offer-card__badge{
    position:absolute;
    left:24px;
    bottom:18px;
    z-index:2;
    display:inline-flex;
    align-items:center;
    gap:8px;
    min-height:36px;
    padding:8px 12px;
    color:#173d33;
    background:#f7e5bf;
    border:1px solid rgba(255,255,255,.7);
    border-radius:0;
    letter-spacing:.14em;
    text-transform:uppercase;
    font-size:.66rem;
    font-weight:850;
    box-shadow:0 12px 26px rgba(0,0,0,.2);
}

.az-offer-card__badge .material-symbols-outlined{
    color:#7c5822;
    font-size:18px;
}

.az-offer-card__close{
    position:absolute;
    top:16px;
    right:16px;
    z-index:3;
    display:grid;
    place-items:center;
    width:44px;
    height:44px;
    padding:0;
    color:#fff;
    background:#173d33;
    border:1px solid rgba(255,255,255,.55);
    border-radius:0;
    cursor:pointer;
    transition:background .16s ease,transform .16s ease;
}

.az-offer-card__close:hover{
    background:#255c4b;
    transform:translateY(-1px);
}

.az-offer-card__lower{
    box-sizing:border-box;
    width:100%;
    display:flex;
    flex-direction:column;
    min-height:0;
}

.az-offer-card__content{
    box-sizing:border-box;
    width:100%;
    padding:clamp(28px,5vw,46px) clamp(24px,6vw,52px) 22px;
    text-align:center;
    overflow:visible;
}

.az-offer-card.is-scrollable .az-offer-card__content{
    max-height:clamp(180px,34dvh,360px);
    overflow-y:auto;
    overscroll-behavior:contain;
    scrollbar-width:thin;
    scrollbar-color:#b9955a #eee8dd;
}

.az-offer-card.is-scrollable .az-offer-card__content::-webkit-scrollbar{
    width:9px;
}

.az-offer-card.is-scrollable .az-offer-card__content::-webkit-scrollbar-track{
    background:#eee8dd;
}

.az-offer-card.is-scrollable .az-offer-card__content::-webkit-scrollbar-thumb{
    background:#b9955a;
    border:2px solid #eee8dd;
}

.az-offer-card.is-scrollable .az-offer-card__content::-webkit-scrollbar-thumb:hover{
    background:#8f6c38;
}

.az-offer-card__eyebrow{
    margin:0 0 12px;
    color:#8f6c38;
    letter-spacing:.17em;
    text-transform:uppercase;
    font-size:.68rem;
    font-weight:850;
}

.az-offer-card__content h2{
    max-width:560px;
    margin:0 auto;
    color:#113329;
    font-family:var(--font-heading,Georgia,serif);
    font-size:clamp(2.25rem,5vw,4rem);
    font-weight:500;
    line-height:1;
    letter-spacing:-.045em;
    text-wrap:balance;
    overflow-wrap:anywhere;
}

.az-offer-card__summary{
    max-width:560px;
    margin:20px auto 0;
    color:#52615b;
    font-size:clamp(.98rem,1.45vw,1.08rem);
    line-height:1.7;
    text-wrap:pretty;
}

.az-offer-card__copy{
    max-width:560px;
    margin:16px auto 0;
    color:#69756f;
    font-size:.94rem;
    line-height:1.75;
    text-align:center;
}

.az-offer-card__actions{
    box-sizing:border-box;
    width:100%;
    display:grid;
    justify-items:center;
    gap:15px;
    padding:6px clamp(24px,6vw,52px) clamp(24px,4vw,32px);
    background:#fffdf8;
}

.az-offer-card__cta{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:11px;
    width:min(100%,420px);
    min-height:54px;
    padding:14px 24px;
    color:#fff;
    background:#173d33;
    border:1px solid #173d33;
    border-radius:0;
    font-size:.84rem;
    font-weight:850;
    text-align:center;
    text-decoration:none;
    transition:background .16s ease,transform .16s ease,box-shadow .16s ease;
}

.az-offer-card__cta:hover{
    color:#fff;
    background:#255c4b;
    transform:translateY(-2px);
    box-shadow:0 14px 30px rgba(23,61,51,.2);
}

.az-offer-card__cta .material-symbols-outlined{
    flex:none;
    font-size:19px;
    transition:transform .16s ease;
}

.az-offer-card__cta:hover .material-symbols-outlined{
    transform:translateX(4px);
}

.az-offer-card__later{
    width:auto;
    min-height:auto;
    margin:0;
    padding:2px 0 4px;
    color:#6c7872;
    background:transparent;
    border:0;
    border-bottom:1px solid currentColor;
    border-radius:0;
    font-size:.8rem;
    font-weight:750;
    cursor:pointer;
}

.az-offer-card__later:hover{
    color:#173d33;
}

.az-offer-card__close:focus-visible,
.az-offer-card__cta:focus-visible,
.az-offer-card__later:focus-visible{
    outline:2px solid #d8b87a;
    outline-offset:3px;
}

.azari-preloader,
.public-preloader,
[data-preloader],
.site-preloader,
.page-preloader{
    z-index:99999!important;
}

@media(max-width:640px){
    .az-offer-modal{
        align-items:end;
        padding:0;
    }

    .az-offer-card{
        width:100%;
        max-height:94dvh;
        border-left:0;
        border-right:0;
        border-bottom:0;
    }

    .az-offer-card__visual{
        width:100%;
        height:clamp(190px,48vw,235px);
        min-height:0;
        max-height:none;
        aspect-ratio:auto;
    }

    .az-offer-card__badge{
        left:18px;
        bottom:15px;
    }

    .az-offer-card__content{
        padding:27px 22px 20px;
        text-align:center;
    }

    .az-offer-card__content h2,
    .az-offer-card__summary,
    .az-offer-card__copy{
        text-align:center;
    }

    .az-offer-card__actions{
        padding:6px 22px 24px;
    }

    .az-offer-card__cta{
        width:100%;
    }
}

@media(max-height:760px) and (min-width:641px){
    .az-offer-card{
        max-height:calc(100dvh - 28px);
    }

    .az-offer-card__visual{
        width:100%;
        height:220px;
        min-height:0;
        max-height:none;
        aspect-ratio:auto;
    }

    .az-offer-card__content{
        padding-top:24px;
    }
}

@media(prefers-reduced-motion:reduce){
    .az-offer-modal,
    .az-offer-card,
    .az-offer-card__close,
    .az-offer-card__cta{
        transition:none;
    }
}
</style>
@endpush

@push('scripts')
<script>
(() => {
    const popup = document.querySelector('[data-promotion-popup]');
    if (!popup) return;

    const card = popup.querySelector('[data-promotion-dialog]');
    const content = popup.querySelector('[data-promotion-content]');
    const fingerprint = popup.dataset.promotionFingerprint || 'default';
    const storageKey = 'azari-homepage-promotion-dismissal';
    const lifetime = 24 * 60 * 60 * 1000;
    let lastFocused = null;
    let closeTimer = null;

    const readDismissal = () => {
        try {
            return JSON.parse(localStorage.getItem(storageKey) || 'null');
        } catch (_) {
            return null;
        }
    };

    const isDismissed = () => {
        const value = readDismissal();

        return Boolean(
            value &&
            value.fingerprint === fingerprint &&
            Number.isFinite(Number(value.dismissedAt)) &&
            Date.now() - Number(value.dismissedAt) < lifetime
        );
    };

    const rememberDismissal = () => {
        try {
            localStorage.setItem(storageKey, JSON.stringify({
                fingerprint,
                dismissedAt: Date.now(),
            }));
        } catch (_) {}
    };

    const updateOverflow = () => {
        if (!card || !content || popup.hidden) return;

        card.classList.remove('is-scrollable');

        requestAnimationFrame(() => {
            const viewportLimit = window.innerHeight - (window.innerWidth <= 640 ? 0 : 56);
            const needsNestedScroll = card.scrollHeight > viewportLimit + 2;
            card.classList.toggle('is-scrollable', needsNestedScroll);
        });
    };

    const getFocusable = () => {
        if (!card) return [];

        return [...card.querySelectorAll(
            'a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])'
        )].filter((element) => element.offsetParent !== null);
    };

    const open = () => {
        lastFocused = document.activeElement;
        popup.hidden = false;

        requestAnimationFrame(() => {
            updateOverflow();
            popup.classList.add('is-visible');
            document.body.classList.add('is-locked');
            card?.focus({ preventScroll:true });
        });
    };

    const close = () => {
        if (!popup.classList.contains('is-visible')) return;

        rememberDismissal();
        popup.classList.remove('is-visible');
        document.body.classList.remove('is-locked');

        clearTimeout(closeTimer);
        closeTimer = setTimeout(() => {
            popup.hidden = true;
            card?.classList.remove('is-scrollable');
            lastFocused?.focus?.();
        }, 230);
    };

    popup.querySelectorAll('[data-promotion-close]').forEach((element) => {
        element.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (!popup.classList.contains('is-visible')) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = getFocusable();
        if (!focusable.length) {
            event.preventDefault();
            card?.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    window.addEventListener('resize', updateOverflow, { passive:true });

    window.addEventListener('load', () => {
        if (!isDismissed()) {
            setTimeout(open, 650);
        }
    }, { once:true });
})();
</script>
@endpush
@endif
