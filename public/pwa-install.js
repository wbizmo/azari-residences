(() => {
  'use strict';

  if (window.__AZARI_CANONICAL_PWA_INSTALL_V9__) return;
  window.__AZARI_CANONICAL_PWA_INSTALL_V9__ = true;

  const APP_NAME = 'Azari Residences';
  const MODAL_ID = 'azari-pwa-install-modal';
  const STORAGE_KEY = `azari:pwa-installed:v9:${location.hostname}`;
  const INSTALL_SELECTOR = '[data-azari-pwa-install]';
  const LABEL_SELECTOR = '[data-pwa-label]';

  let deferredPrompt = null;
  let previousFocus = null;
  let bodyOverflow = '';
  const preloaderZ = new Map();

  const ua = navigator.userAgent || '';
  const platform = navigator.userAgentData?.platform || navigator.platform || '';

  const isIPadDesktopUA =
    platform === 'MacIntel' && navigator.maxTouchPoints > 1;

  const isIOS =
    /iPhone|iPad|iPod/i.test(ua) || isIPadDesktopUA;

  const isMac =
    !isIPadDesktopUA &&
    (/Macintosh|MacIntel|MacPPC|Mac68K/i.test(platform) ||
     /Macintosh/i.test(ua));

  function browserName() {
    if (/CriOS/i.test(ua)) return 'Chrome';
    if (/EdgiOS/i.test(ua)) return 'Edge';
    if (/FxiOS/i.test(ua)) return 'Firefox';
    if (/OPiOS/i.test(ua)) return 'Opera';
    if (/Edg\//i.test(ua)) return 'Edge';
    if (/Chrome\//i.test(ua) && !/Edg\//i.test(ua)) return 'Chrome';
    if (/Firefox\//i.test(ua)) return 'Firefox';
    if (/Safari\//i.test(ua) && !/Chrome\//i.test(ua)) return 'Safari';
    return 'Browser';
  }

  function installedContext() {
    const modes = [
      'standalone',
      'fullscreen',
      'minimal-ui',
      'window-controls-overlay'
    ];

    const displayMode = modes.some((mode) => {
      try {
        return window.matchMedia(`(display-mode: ${mode})`).matches;
      } catch (_) {
        return false;
      }
    });

    return displayMode || window.navigator.standalone === true;
  }

  function storedInstalled() {
    try {
      return localStorage.getItem(STORAGE_KEY) === '1';
    } catch (_) {
      return false;
    }
  }

  function isKnownInstalled() {
    return installedContext() || storedInstalled();
  }

  function rememberInstalled() {
    try {
      localStorage.setItem(STORAGE_KEY, '1');
    } catch (_) {}

    refreshInstallButtons();
  }

  function forgetInstalled() {
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch (_) {}

    refreshInstallButtons();
  }

  function installButtons() {
    return Array.from(document.querySelectorAll(INSTALL_SELECTOR));
  }

  function refreshInstallButtons() {
    const installed = isKnownInstalled();

    installButtons().forEach((button) => {
      const label = button.querySelector(LABEL_SELECTOR);

      if (installed) {
        button.dataset.azariInstalled = 'true';
        button.setAttribute(
          'aria-label',
          `${APP_NAME} is already installed`
        );
        button.setAttribute('aria-disabled', 'true');

        if (label) {
          label.textContent = 'Azari App Installed';
        }

        const icon = button.querySelector(
          '.material-symbols-outlined'
        );

        if (icon) {
          icon.textContent = 'check_circle';
        }
      } else {
        delete button.dataset.azariInstalled;

        button.removeAttribute('aria-disabled');
        button.setAttribute(
          'aria-label',
          'Download the Azari App'
        );

        if (label) {
          label.textContent = 'Download the Azari App';
        }

        const icon = button.querySelector(
          '.material-symbols-outlined'
        );

        if (icon) {
          icon.textContent = 'download';
        }
      }
    });
  }

  function icon(kind) {
    const common =
      'viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"';

    if (kind === 'share') {
      return `
        <svg ${common}>
          <path d="M12 15.5V3"/>
          <path d="M8 7l4-4 4 4"/>
          <path d="M7 10H5.8A2.8 2.8 0 0 0 3 12.8v5.4A2.8 2.8 0 0 0 5.8 21h12.4a2.8 2.8 0 0 0 2.8-2.8v-5.4a2.8 2.8 0 0 0-2.8-2.8H17"/>
        </svg>
      `;
    }

    if (kind === 'home') {
      return `
        <svg ${common}>
          <rect x="5" y="2.5" width="14" height="19" rx="2.6"/>
          <path d="M9 12h6"/>
          <path d="M12 9v6"/>
        </svg>
      `;
    }

    if (kind === 'dock') {
      return `
        <svg ${common}>
          <rect x="3" y="3" width="18" height="13" rx="2"/>
          <path d="M7 21h10"/>
          <path d="M8 18.5h8"/>
        </svg>
      `;
    }

    if (kind === 'menu') {
      return `
        <svg ${common}>
          <path d="M5 7h14"/>
          <path d="M5 12h14"/>
          <path d="M5 17h14"/>
        </svg>
      `;
    }

    if (kind === 'check') {
      return `
        <svg ${common}>
          <circle cx="12" cy="12" r="9"/>
          <path d="M7.5 12.3l3 3 6-6.3"/>
        </svg>
      `;
    }

    return `
      <svg ${common}>
        <path d="M6 6l12 12"/>
        <path d="M18 6L6 18"/>
      </svg>
    `;
  }

  function stepsForDevice() {
    const browser = browserName();

    if (isIOS) {
      if (browser === 'Chrome') {
        return {
          eyebrow: 'Chrome on iPhone / iPad',
          title: 'Install Azari Residences',
          description:
            'Add Azari to your Home Screen for quick access and an app-style experience.',
          steps: [
            [
              'share',
              'Tap the Share button',
              'Use Chrome’s Share control beside the address bar.'
            ],
            [
              'home',
              'Choose “Add to Home Screen”',
              'Scroll the action list if the option is not immediately visible.'
            ],
            [
              'check',
              'Confirm and tap “Add”',
              'If “Open as Web App” is shown, keep it enabled.'
            ]
          ],
          note:
            'Apple requires the final Add to Home Screen action to be completed from the browser menu.'
        };
      }

      return {
        eyebrow: `${browser} on iPhone / iPad`,
        title: 'Install Azari Residences',
        description:
          'Add Azari to your Home Screen for quick access and an app-style full-screen experience.',
        steps: [
          [
            'share',
            'Open the browser Share menu',
            browser === 'Safari'
              ? 'Tap Safari’s Share button for this page.'
              : 'Use the browser’s Share control for this page.'
          ],
          [
            'home',
            'Choose “Add to Home Screen”',
            'Scroll the action list if the option is not immediately visible.'
          ],
          [
            'check',
            'Confirm and tap “Add”',
            'If “Open as Web App” is shown, keep it enabled.'
          ]
        ],
        note:
          browser === 'Safari'
            ? 'Apple keeps the final Add to Home Screen confirmation inside Safari.'
            : 'If Add to Home Screen is unavailable in this browser, open the page in Safari.'
      };
    }

    if (isMac && browser === 'Safari') {
      return {
        eyebrow: 'Safari on Mac',
        title: 'Add Azari Residences to your Mac',
        description:
          'Save Azari as a standalone web app in your Dock and Applications.',
        steps: [
          [
            'share',
            'Open Safari’s Share menu',
            'You can also use File in the macOS menu bar.'
          ],
          [
            'dock',
            'Choose “Add to Dock”',
            'Safari will prepare Azari as a web app.'
          ],
          [
            'check',
            'Confirm the name and click “Add”',
            'Azari will be available from the Dock, Applications and Spotlight.'
          ]
        ],
        note:
          'Safari on Mac uses “Add to Dock” rather than the iPhone “Add to Home Screen” wording.'
      };
    }

    if (isMac) {
      return {
        eyebrow: `${browser} on Mac`,
        title: 'Install Azari Residences',
        description:
          'Install Azari as a standalone app on your Mac.',
        steps: [
          [
            'dock',
            'Look for the Install option',
            'Chrome and Edge normally show an install control in the address bar when the app is installable.'
          ],
          [
            'menu',
            'Or open the browser menu',
            'Choose the option to install Azari Residences as an app.'
          ],
          [
            'check',
            'Confirm installation',
            'Azari will open separately from ordinary browser tabs.'
          ]
        ],
        note:
          'When a native browser install prompt is available, Azari opens it automatically.'
      };
    }

    return {
      eyebrow: `${browserName()} on this device`,
      title: 'Install Azari Residences',
      description:
        'Install Azari from your browser for quicker access.',
      steps: [
        [
          'menu',
          'Open your browser menu',
          'Look for Install app, Install Azari Residences, or Add to Home Screen.'
        ],
        [
          'dock',
          'Choose the install action',
          'The wording varies by browser and device.'
        ],
        [
          'check',
          'Confirm installation',
          'Azari will then be available like an app on your device.'
        ]
      ],
      note:
        'If your browser exposes a native install prompt, the Download button uses it automatically.'
    };
  }

  function elevatePreloader() {
    const selectors = [
      '#preloader',
      '.preloader',
      '[data-preloader]',
      '#page-loader',
      '.page-loader',
      '#app-loader',
      '.app-loader',
      '.loading-screen',
      '.preloader-overlay'
    ];

    selectors.forEach((selector) => {
      document.querySelectorAll(selector).forEach((element) => {
        if (!(element instanceof HTMLElement)) {
          return;
        }

        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();

        if (
          style.display === 'none' ||
          style.visibility === 'hidden' ||
          Number(style.opacity || 1) <= 0 ||
          rect.width < window.innerWidth * 0.6 ||
          rect.height < window.innerHeight * 0.6
        ) {
          return;
        }

        if (!preloaderZ.has(element)) {
          preloaderZ.set(
            element,
            element.style.zIndex || ''
          );
        }

        element.style.zIndex = '2147483646';
      });
    });
  }

  function restorePreloader() {
    preloaderZ.forEach((z, element) => {
      if (element?.style) {
        element.style.zIndex = z;
      }
    });

    preloaderZ.clear();
  }

  function styles() {
    return `
      #${MODAL_ID} {
        --az-pwa-green: #0c2b24;
        --az-pwa-green-2: #143d34;
        --az-pwa-paper: #fffdf9;
        --az-pwa-ivory: #f8f5ef;
        --az-pwa-line: #e7e1d7;
        --az-pwa-brass: #b58a4a;
        --az-pwa-muted: #6e7a75;
        position: fixed;
        inset: 0;
        z-index: 2147483000;
        display: grid;
        place-items: center;
        padding:
          max(12px, env(safe-area-inset-top))
          max(12px, env(safe-area-inset-right))
          max(12px, env(safe-area-inset-bottom))
          max(12px, env(safe-area-inset-left));
        pointer-events: none;
        font-family: inherit;
      }

      #${MODAL_ID},
      #${MODAL_ID} *,
      #${MODAL_ID} *::before,
      #${MODAL_ID} *::after {
        box-sizing: border-box;
      }

      #${MODAL_ID} .az-pwa-backdrop {
        position: absolute;
        inset: 0;
        opacity: 0;
        background: rgba(4, 18, 14, .68);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        transition: opacity .2s ease;
      }

      #${MODAL_ID} .az-pwa-dialog {
        position: relative;
        width: min(640px, calc(100vw - 28px));
        max-height: min(760px, calc(100dvh - 28px));
        overflow: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        opacity: 0;
        transform: translateY(10px) scale(.985);
        background:
          radial-gradient(
            circle at 100% 0,
            rgba(181, 138, 74, .12),
            transparent 30%
          ),
          var(--az-pwa-paper);
        color: #18231f;
        border: 1px solid rgba(255, 255, 255, .75);
        border-radius: 26px;
        box-shadow: 0 34px 110px rgba(4, 25, 19, .40);
        transition:
          opacity .2s ease,
          transform .22s ease;
      }

      #${MODAL_ID}.is-open {
        pointer-events: auto;
      }

      #${MODAL_ID}.is-open .az-pwa-backdrop {
        opacity: 1;
      }

      #${MODAL_ID}.is-open .az-pwa-dialog {
        opacity: 1;
        transform: translateY(0) scale(1);
      }

      #${MODAL_ID} .az-pwa-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 44px;
        gap: 16px;
        padding: 27px 27px 18px;
      }

      #${MODAL_ID} .az-pwa-eyebrow {
        display: inline-flex;
        margin-bottom: 9px;
        padding: 7px 10px;
        border-radius: 999px;
        color: var(--az-pwa-green-2);
        background: #edf3f0;
        border: 1px solid #dfeae5;
        font-size: 10px;
        font-weight: 800;
        line-height: 1;
        letter-spacing: .12em;
        text-transform: uppercase;
      }

      #${MODAL_ID} h2 {
        margin: 0;
        color: var(--az-pwa-green);
        font-size: clamp(27px, 5vw, 40px);
        line-height: 1.06;
        letter-spacing: -.025em;
      }

      #${MODAL_ID} .az-pwa-description {
        margin: 10px 0 0;
        color: var(--az-pwa-muted);
        font-size: 14px;
        line-height: 1.6;
      }

      #${MODAL_ID} .az-pwa-close {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        padding: 0;
        color: var(--az-pwa-green-2);
        background: var(--az-pwa-ivory);
        border: 1px solid var(--az-pwa-line);
        border-radius: 14px;
        cursor: pointer;
      }

      #${MODAL_ID} svg {
        width: 23px;
        height: 23px;
      }

      #${MODAL_ID} .az-pwa-body {
        padding: 0 27px 27px;
      }

      #${MODAL_ID} .az-pwa-steps {
        display: grid;
        gap: 10px;
      }

      #${MODAL_ID} .az-pwa-step {
        display: grid;
        grid-template-columns: 50px minmax(0, 1fr);
        gap: 14px;
        align-items: center;
        padding: 14px;
        background: var(--az-pwa-ivory);
        border: 1px solid var(--az-pwa-line);
        border-radius: 18px;
      }

      #${MODAL_ID} .az-pwa-step-icon {
        width: 50px;
        height: 50px;
        display: grid;
        place-items: center;
        color: var(--az-pwa-green-2);
        background: var(--az-pwa-paper);
        border: 1px solid var(--az-pwa-line);
        border-radius: 15px;
      }

      #${MODAL_ID} .az-pwa-step-number {
        display: block;
        margin-bottom: 3px;
        color: var(--az-pwa-brass);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
      }

      #${MODAL_ID} .az-pwa-step strong {
        display: block;
        color: var(--az-pwa-green);
        font-size: 14px;
        line-height: 1.35;
      }

      #${MODAL_ID} .az-pwa-step p {
        margin: 4px 0 0;
        color: var(--az-pwa-muted);
        font-size: 12px;
        line-height: 1.45;
      }

      #${MODAL_ID} .az-pwa-installed {
        display: grid;
        grid-template-columns: 56px minmax(0, 1fr);
        gap: 15px;
        align-items: center;
        padding: 18px;
        color: var(--az-pwa-green-2);
        background: #edf5f1;
        border: 1px solid #d8e9e1;
        border-radius: 20px;
      }

      #${MODAL_ID} .az-pwa-installed-icon {
        width: 56px;
        height: 56px;
        display: grid;
        place-items: center;
        background: var(--az-pwa-paper);
        border: 1px solid #d8e9e1;
        border-radius: 18px;
      }

      #${MODAL_ID} .az-pwa-installed strong {
        display: block;
        color: var(--az-pwa-green);
        font-size: 15px;
      }

      #${MODAL_ID} .az-pwa-installed p {
        margin: 5px 0 0;
        color: var(--az-pwa-muted);
        font-size: 12px;
        line-height: 1.5;
      }

      #${MODAL_ID} .az-pwa-note {
        margin-top: 12px;
        padding: 12px 14px;
        color: #665f4d;
        background: #fbf5e9;
        border: 1px solid #eadbbd;
        border-radius: 15px;
        font-size: 11px;
        line-height: 1.5;
      }

      #${MODAL_ID} .az-pwa-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 16px;
      }

      #${MODAL_ID} .az-pwa-action {
        min-height: 50px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 16px;
        border-radius: 15px;
        font: inherit;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
      }

      #${MODAL_ID} .az-pwa-primary {
        color: white;
        background:
          linear-gradient(
            180deg,
            var(--az-pwa-green-2),
            var(--az-pwa-green)
          );
        border: 1px solid var(--az-pwa-green);
      }

      #${MODAL_ID} .az-pwa-secondary {
        color: var(--az-pwa-green-2);
        background: var(--az-pwa-paper);
        border: 1px solid var(--az-pwa-line);
      }

      #${MODAL_ID} button:focus-visible {
        outline: 3px solid rgba(181, 138, 74, .45);
        outline-offset: 2px;
      }

      @media (max-width: 560px) {
        #${MODAL_ID} .az-pwa-dialog {
          width: min(100%, 520px);
          max-height: calc(100dvh - 20px);
          border-radius: 22px;
        }

        #${MODAL_ID} .az-pwa-head {
          grid-template-columns: minmax(0, 1fr) 40px;
          gap: 10px;
          padding: 20px 18px 14px;
        }

        #${MODAL_ID} .az-pwa-close {
          width: 40px;
          height: 40px;
        }

        #${MODAL_ID} .az-pwa-body {
          padding: 0 18px 20px;
        }

        #${MODAL_ID} .az-pwa-step {
          grid-template-columns: 44px minmax(0, 1fr);
          gap: 11px;
          padding: 11px;
          border-radius: 16px;
        }

        #${MODAL_ID} .az-pwa-step-icon {
          width: 44px;
          height: 44px;
        }

        #${MODAL_ID} .az-pwa-step strong {
          font-size: 13px;
        }

        #${MODAL_ID} .az-pwa-step p {
          font-size: 11px;
        }

        #${MODAL_ID} .az-pwa-actions {
          grid-template-columns: 1fr;
        }
      }

      @media (max-height: 620px) and (orientation: landscape) {
        #${MODAL_ID} .az-pwa-dialog {
          width: min(780px, calc(100vw - 22px));
          max-height: calc(100dvh - 18px);
        }

        #${MODAL_ID} .az-pwa-steps {
          grid-template-columns:
            repeat(3, minmax(0, 1fr));
        }

        #${MODAL_ID} .az-pwa-step {
          grid-template-columns: 1fr;
          align-items: start;
        }
      }

      @media (prefers-reduced-motion: reduce) {
        #${MODAL_ID} .az-pwa-backdrop,
        #${MODAL_ID} .az-pwa-dialog {
          transition: none;
        }
      }
    `;
  }

  function closeModal() {
    const root = document.getElementById(MODAL_ID);

    if (!root) {
      return;
    }

    root.classList.remove('is-open');

    document.body.style.overflow = bodyOverflow;

    restorePreloader();

    const focus = previousFocus;

    setTimeout(() => {
      root.remove();

      focus?.focus?.({
        preventScroll: true
      });
    }, 210);
  }

  function openModal(installed = false) {
    document.getElementById(MODAL_ID)?.remove();

    previousFocus =
      document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;

    bodyOverflow =
      document.body.style.overflow || '';

    const content = installed
      ? {
          eyebrow: 'Azari App',
          title:
            'Azari Residences is already installed',
          description:
            'There is no need to install another copy.'
        }
      : stepsForDevice();

    const root = document.createElement('div');

    root.id = MODAL_ID;

    const body = installed
      ? `
        <div class="az-pwa-installed">
          <div class="az-pwa-installed-icon">
            ${icon('check')}
          </div>

          <div>
            <strong>
              Already installed on this device
            </strong>

            <p>
              Open Azari from your Home Screen,
              Dock, Applications, or installed-app list.
            </p>
          </div>
        </div>

        <div class="az-pwa-note">
          If you intentionally removed the app,
          use “I removed it” to make the Download
          button available again.
        </div>

        <div class="az-pwa-actions">
          <button
            class="az-pwa-action az-pwa-primary"
            type="button"
            data-az-pwa-close
          >
            Got it
          </button>

          <button
            class="az-pwa-action az-pwa-secondary"
            type="button"
            data-az-pwa-reset
          >
            I removed it
          </button>
        </div>
      `
      : `
        <div class="az-pwa-steps">
          ${content.steps
            .map(
              (step, index) => `
                <div class="az-pwa-step">
                  <div class="az-pwa-step-icon">
                    ${icon(step[0])}
                  </div>

                  <div>
                    <span class="az-pwa-step-number">
                      Step ${index + 1}
                    </span>

                    <strong>
                      ${step[1]}
                    </strong>

                    <p>
                      ${step[2]}
                    </p>
                  </div>
                </div>
              `
            )
            .join('')}
        </div>

        <div class="az-pwa-note">
          ${content.note}
        </div>

        <div class="az-pwa-actions">
          <button
            class="az-pwa-action az-pwa-primary"
            type="button"
            data-az-pwa-close
          >
            Got it
          </button>

          ${
            isIOS ||
            (isMac && browserName() === 'Safari')
              ? `
                <button
                  class="az-pwa-action az-pwa-secondary"
                  type="button"
                  data-az-pwa-confirm
                >
                  I’ve installed Azari
                </button>
              `
              : `
                <button
                  class="az-pwa-action az-pwa-secondary"
                  type="button"
                  data-az-pwa-close
                >
                  Close
                </button>
              `
          }
        </div>
      `;

    root.innerHTML = `
      <style>
        ${styles()}
      </style>

      <div
        class="az-pwa-backdrop"
        data-az-pwa-close
      ></div>

      <section
        class="az-pwa-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="az-pwa-title"
        aria-describedby="az-pwa-description"
      >
        <div class="az-pwa-head">
          <div>
            <div class="az-pwa-eyebrow">
              ${content.eyebrow}
            </div>

            <h2 id="az-pwa-title">
              ${content.title}
            </h2>

            <p
              class="az-pwa-description"
              id="az-pwa-description"
            >
              ${content.description}
            </p>
          </div>

          <button
            class="az-pwa-close"
            type="button"
            aria-label="Close"
            data-az-pwa-close
          >
            ${icon('close')}
          </button>
        </div>

        <div class="az-pwa-body">
          ${body}
        </div>
      </section>
    `;

    document.body.appendChild(root);

    document.body.style.overflow = 'hidden';

    elevatePreloader();

    root
      .querySelectorAll('[data-az-pwa-close]')
      .forEach((element) => {
        element.addEventListener(
          'click',
          closeModal
        );
      });

    root
      .querySelector('[data-az-pwa-confirm]')
      ?.addEventListener('click', () => {
        rememberInstalled();
        closeModal();
      });

    root
      .querySelector('[data-az-pwa-reset]')
      ?.addEventListener('click', () => {
        forgetInstalled();
        closeModal();
      });

    requestAnimationFrame(() => {
      root.classList.add('is-open');

      root
        .querySelector('.az-pwa-close')
        ?.focus({
          preventScroll: true
        });
    });
  }

  async function handleInstallClick(event) {
    const button =
      event.target.closest(INSTALL_SELECTOR);

    if (!button) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    if (isKnownInstalled()) {
      openModal(true);
      return;
    }

    if (
      isIOS ||
      (isMac && browserName() === 'Safari')
    ) {
      openModal(false);
      return;
    }

    if (deferredPrompt) {
      try {
        const prompt = deferredPrompt;

        deferredPrompt = null;

        await prompt.prompt();

        const choice = await prompt.userChoice;

        if (choice?.outcome === 'accepted') {
          rememberInstalled();
        }

        return;
      } catch (_) {
        deferredPrompt = null;
      }
    }

    openModal(false);
  }

  window.addEventListener(
    'beforeinstallprompt',
    (event) => {
      event.preventDefault();

      deferredPrompt = event;

      /*
       * If Chromium says the application is
       * currently installable, any previously
       * remembered installed state may be stale.
       */
      forgetInstalled();
    }
  );

  window.addEventListener(
    'appinstalled',
    () => {
      deferredPrompt = null;

      rememberInstalled();

      closeModal();
    }
  );

  document.addEventListener(
    'click',
    handleInstallClick,
    true
  );

  document.addEventListener(
    'keydown',
    (event) => {
      const root =
        document.getElementById(MODAL_ID);

      if (
        !root?.classList.contains('is-open')
      ) {
        return;
      }

      if (event.key === 'Escape') {
        event.preventDefault();

        closeModal();

        return;
      }

      if (event.key !== 'Tab') {
        return;
      }

      const focusables = Array.from(
        root.querySelectorAll(
          'button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'
        )
      ).filter(
        (element) =>
          element instanceof HTMLElement &&
          !element.hidden
      );

      if (!focusables.length) {
        return;
      }

      const first = focusables[0];
      const last =
        focusables[focusables.length - 1];

      if (
        event.shiftKey &&
        document.activeElement === first
      ) {
        event.preventDefault();

        last.focus();
      } else if (
        !event.shiftKey &&
        document.activeElement === last
      ) {
        event.preventDefault();

        first.focus();
      }
    }
  );

  function boot() {
    refreshInstallButtons();
  }

  if (document.readyState === 'loading') {
    document.addEventListener(
      'DOMContentLoaded',
      boot,
      {
        once: true
      }
    );
  } else {
    boot();
  }

  window.addEventListener(
    'pageshow',
    refreshInstallButtons
  );
})();