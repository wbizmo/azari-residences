import { test, expect } from '@playwright/test';

const baseURL = process.env.RESERVA_BASE_URL || 'http://127.0.0.1:8000';

function channel(value) {
    const normalized = value / 255;
    return normalized <= 0.04045
        ? normalized / 12.92
        : Math.pow((normalized + 0.055) / 1.055, 2.4);
}

function luminance([r, g, b]) {
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

function ratio(foreground, background) {
    const first = luminance(foreground);
    const second = luminance(background);
    const lighter = Math.max(first, second);
    const darker = Math.min(first, second);

    return (lighter + 0.05) / (darker + 0.05);
}

function parseRgb(value) {
    const match = String(value).match(/rgba?\((\d+)[, ]+(\d+)[, ]+(\d+)(?:[, /]+([\d.]+))?\)/i);

    if (!match) {
        throw new Error(`Unsupported computed color: ${value}`);
    }

    return [
        Number(match[1]),
        Number(match[2]),
        Number(match[3]),
        match[4] === undefined ? 1 : Number(match[4]),
    ];
}

async function effectiveColors(locator) {
    return locator.evaluate((element) => {
        const parse = (value) => {
            const match = String(value).match(/rgba?\((\d+)[, ]+(\d+)[, ]+(\d+)(?:[, /]+([\d.]+))?\)/i);
            if (!match) return null;
            return [
                Number(match[1]),
                Number(match[2]),
                Number(match[3]),
                match[4] === undefined ? 1 : Number(match[4]),
            ];
        };

        const foreground = getComputedStyle(element).color;
        let node = element;
        let background = null;

        while (node) {
            const candidate = parse(getComputedStyle(node).backgroundColor);
            if (candidate && candidate[3] >= 0.9) {
                background = getComputedStyle(node).backgroundColor;
                break;
            }
            node = node.parentElement;
        }

        return {
            foreground,
            background: background || 'rgb(255, 255, 255)',
        };
    });
}

async function expectReadable(locator, minimum = 4.5) {
    await expect(locator).toBeVisible();

    const colors = await effectiveColors(locator);
    const fg = parseRgb(colors.foreground);
    const bg = parseRgb(colors.background);
    const contrast = ratio(fg.slice(0, 3), bg.slice(0, 3));

    expect(
        contrast,
        `Expected contrast >= ${minimum}, got ${contrast.toFixed(2)} for ${colors.foreground} on ${colors.background}`
    ).toBeGreaterThanOrEqual(minimum);
}

async function expectVisibleFocus(locator) {
    await locator.focus();

    const focus = await locator.evaluate((element) => {
        const style = getComputedStyle(element);
        return {
            outlineStyle: style.outlineStyle,
            outlineWidth: Number.parseFloat(style.outlineWidth || '0'),
            boxShadow: style.boxShadow,
        };
    });

    expect(
        focus.outlineWidth > 0
            || (focus.outlineStyle !== 'none' && focus.outlineStyle !== '')
            || (focus.boxShadow && focus.boxShadow !== 'none')
    ).toBeTruthy();
}

test.beforeAll(async () => {
    await import('node:fs/promises').then(({ mkdir }) =>
        mkdir('playwright-artifacts', { recursive: true })
    );
});

test('public homepage has readable booking controls and keyboard focus', async ({ page }) => {
    await page.goto(baseURL, { waitUntil: 'networkidle' });

    await expect(page).toHaveTitle(/Reserva/i);
    await expectReadable(page.locator('.availability-submit').first(), 4.5);
    await expectVisibleFocus(page.locator('.availability-submit').first());

    await page.evaluate(() => window.scrollTo(0, Math.max(900, document.body.scrollHeight / 2)));
    await expectReadable(page.locator('.back-to-top').first(), 4.5);

    await page.screenshot({
        path: 'playwright-artifacts/homepage.png',
        fullPage: true,
    });
});

test('admin dashboard inverse surfaces remain readable', async ({ page }) => {
    await page.goto(`${baseURL}/azaridevadmin/login`, { waitUntil: 'networkidle' });
    await page.locator('#admin-email').fill('admin@example.test');
    await page.locator('#admin-password').fill('password');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForURL(/\/azaridevadmin(?:\?|$)/);

    await expectReadable(page.locator('.az-dashboard-hero .az-eyebrow').first(), 4.5);
    await expectReadable(page.locator('.az-admin-sidebar .az-nav-link').first(), 4.5);
    await expectVisibleFocus(page.locator('.az-admin-sidebar .az-nav-link').first());

    await page.screenshot({
        path: 'playwright-artifacts/admin-dashboard.png',
        fullPage: true,
    });
});

test('customer dashboard icons and inverse hero remain readable', async ({ page }) => {
    await page.goto(`${baseURL}/login`, { waitUntil: 'networkidle' });
    await page.locator('#email').fill('customer@example.test');
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.goto(`${baseURL}/account`, { waitUntil: 'networkidle' });

    await expectReadable(page.locator('.az-user-welcome .az-user-eyebrow').first(), 4.5);
    await expectReadable(page.locator('.az-user-summary-icon').first(), 3.0);
    await expectReadable(page.locator('.az-user-sidebar .az-user-nav-link').first(), 4.5);
    await expectVisibleFocus(page.locator('.az-user-sidebar .az-user-nav-link').first());

    await page.screenshot({
        path: 'playwright-artifacts/customer-dashboard.png',
        fullPage: true,
    });
});
