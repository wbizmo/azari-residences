/* Consent-based encrypted offline stay summaries.
 * Never cache authenticated pages, identity files, prices or payment status.
 */
(() => {
    'use strict';
    const KEY = 'resavar:offline-stays:v1';
    const OFFLINE_LIFETIME_MS = 90 * 24 * 60 * 60 * 1000;
    const encoder = new TextEncoder();
    const decoder = new TextDecoder();
    const capable = Boolean(window.crypto?.subtle && window.localStorage);

    const toBase64 = (bytes) => btoa(Array.from(bytes, b => String.fromCharCode(b)).join(''));
    const fromBase64 = (value) => Uint8Array.from(atob(value), c => c.charCodeAt(0));

    const derive = async (passphrase, salt) => {
        const keyMaterial = await crypto.subtle.importKey(
            'raw', encoder.encode(passphrase), 'PBKDF2', false, ['deriveKey']
        );
        return crypto.subtle.deriveKey(
            {name: 'PBKDF2', salt, iterations: 310000, hash: 'SHA-256'},
            keyMaterial,
            {name: 'AES-GCM', length: 256},
            false,
            ['encrypt', 'decrypt']
        );
    };

    const validRecord = (item) => item &&
        typeof item.id === 'string' && item.id.length <= 100 &&
        typeof item.property === 'string' && item.property.length <= 200 &&
        /^\d{4}-\d{2}-\d{2}$/.test(item.check_in) &&
        /^\d{4}-\d{2}-\d{2}$/.test(item.check_out) &&
        typeof item.address === 'string' && item.address.length <= 250;

    const load = async (passphrase) => {
        const raw = localStorage.getItem(KEY);
        if (!raw) return [];

        try {
            if (raw.length > 150000) throw new Error('Local snapshot is too large');
            const snapshot = JSON.parse(raw);
            if (snapshot.version !== 1) throw new Error('Unsupported snapshot');
            if (Number.isFinite(snapshot.expires_at) && Date.now() >= snapshot.expires_at) {
                localStorage.removeItem(KEY);
                return [];
            }
            const salt = fromBase64(snapshot.salt);
            const iv = fromBase64(snapshot.iv);
            if (salt.length !== 16 || iv.length !== 12) throw new Error('Invalid snapshot');
            const key = await derive(passphrase, salt);
            const contents = await crypto.subtle.decrypt(
                {name: 'AES-GCM', iv}, key, fromBase64(snapshot.ciphertext)
            );
            const records = JSON.parse(decoder.decode(contents));
            if (!Array.isArray(records) || records.length > 50 ||
                !records.every(validRecord)) throw new Error('Invalid snapshot records');

            const thirtyDaysAgo = Date.now() - 30 * 24 * 60 * 60 * 1000;
            const active = records.filter(record =>
                Date.parse(record.check_out + 'T00:00:00Z') >= thirtyDaysAgo
            );
            if (!active.length) localStorage.removeItem(KEY);
            return active;
        } catch {
            throw new Error('Cannot unlock saved stays. Check your passphrase or clear this device’s saved copy.');
        }
    };

    const save = async (passphrase, records) => {
        const salt = crypto.getRandomValues(new Uint8Array(16));
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const key = await derive(passphrase, salt);
        const ciphertext = await crypto.subtle.encrypt(
            {name: 'AES-GCM', iv}, key, encoder.encode(JSON.stringify(records))
        );
        localStorage.setItem(KEY, JSON.stringify({
            version: 1,
            salt: toBase64(salt),
            iv: toBase64(iv),
            expires_at: Date.now() + OFFLINE_LIFETIME_MS,
            ciphertext: toBase64(new Uint8Array(ciphertext))
        }));
    };

    const saveForm = document.querySelector('[data-offline-save]');
    if (saveForm) {
        const feedback = document.querySelector('[data-offline-save-feedback]');
        const submit = saveForm.querySelector('button[type="submit"]');
        if (!capable) {
            submit.disabled = true;
            feedback.textContent = 'Encrypted offline storage is not supported on this browser.';
        } else {
            saveForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                const pin = saveForm.querySelector('input[name="offline_passphrase"]').value;
                if (pin.length < 12) {
                    feedback.textContent = 'Use a passphrase of at least 12 characters.';
                    return;
                }

                submit.disabled = true;
                feedback.textContent = 'Encrypting this stay on your device…';
                try {
                    const record = JSON.parse(document.querySelector('[data-offline-trip-record]').textContent);
                    if (!validRecord(record)) throw new Error('Stay summary is unavailable.');
                    const records = await load(pin);
                    const remaining = records.filter(item => item.id !== record.id);
                    remaining.push(record);
                    await save(pin, remaining);
                    feedback.textContent = 'Encrypted stay saved on this device. Open the offline screen with the same passphrase.';
                    saveForm.reset();
                } catch (error) {
                    feedback.textContent = error.message;
                } finally {
                    submit.disabled = false;
                }
            });
        }
    }

    const clearOnline = document.querySelector('[data-offline-clear-online]');
    if (clearOnline) {
        clearOnline.addEventListener('click', () => {
            const feedback = document.querySelector('[data-offline-clear-feedback]');
            if (!capable) {
                feedback.textContent = 'Offline storage is not available in this browser.';
                return;
            }
            if (!window.confirm('Permanently delete all encrypted offline stays saved on this browser?')) return;
            localStorage.removeItem(KEY);
            feedback.textContent = 'Offline copies were deleted from this browser.';
        });
    }

    const openForm = document.querySelector('[data-offline-open]');
    if (openForm) {
        const feedback = document.querySelector('[data-offline-open-feedback]');
        const list = document.querySelector('[data-offline-stays]');
        const clear = document.querySelector('[data-offline-clear]');
        if (!capable) {
            feedback.textContent = 'Encrypted offline storage is not supported on this browser.';
            openForm.querySelector('button[type="submit"]').disabled = true;
        } else {
            openForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                list.replaceChildren();
                const pin = openForm.querySelector('input[name="offline_passphrase"]').value;
                if (!pin) return;
                try {
                    const records = await load(pin);
                    if (!records.length) {
                        feedback.textContent = 'No saved stays are available on this device.';
                    } else {
                        feedback.textContent = records.length + ' locally saved ' +
                            (records.length === 1 ? 'stay' : 'stays') + '. These details may be outdated.';
                        for (const record of records) {
                            const article = document.createElement('article');
                            const title = document.createElement('h3');
                            title.textContent = record.property;
                            const dates = document.createElement('p');
                            dates.textContent = 'Arrival: ' + record.check_in + ' · Departure: ' + record.check_out;
                            const location = document.createElement('p');
                            location.textContent = 'Address: ' + record.address;
                            article.append(title, dates, location);
                            list.append(article);
                        }
                    }
                } catch (error) {
                    feedback.textContent = error.message;
                } finally {
                    openForm.reset();
                }
            });

            clear.addEventListener('click', () => {
                if (!window.confirm('Permanently remove all encrypted offline stays from this browser?')) return;
                localStorage.removeItem(KEY);
                list.replaceChildren();
                feedback.textContent = 'All locally saved stays have been cleared.';
            });
        }
    }
})();
