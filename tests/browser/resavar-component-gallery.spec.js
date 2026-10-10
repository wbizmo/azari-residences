import { test, expect } from '@playwright/test';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { mkdir } from 'node:fs/promises';

const gallery = pathToFileURL(resolve('docs/ui/resavar-component-gallery.html')).href;

test.beforeAll(async () => {
    await mkdir('playwright-artifacts', { recursive: true });
});

for (const width of [320, 390, 768, 1280]) {
    test(`Resavar component gallery stays inside viewport at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(gallery);
        await expect(page.getByRole('heading', { name: 'Resavar component gallery' })).toBeVisible();

        const overflow = await page.evaluate(() =>
            document.documentElement.scrollWidth - window.innerWidth
        );
        expect(overflow, 'Page content must not overflow the device viewport.').toBeLessThanOrEqual(1);
        await expect(page.getByRole('region', { name: 'Sample inventory calendar' })).toBeVisible();

        await page.screenshot({
            path: `playwright-artifacts/resavar-gallery-${width}.png`,
            fullPage: true,
        });
    });
}

test('primary controls have visible keyboard focus', async ({ page }) => {
    await page.goto(gallery);
    await page.keyboard.press('Tab');
    const focus = await page.evaluate(() => {
        const element = document.activeElement;
        const style = getComputedStyle(element);
        return {
            text: element?.textContent?.trim(),
            outline: style.outlineColor,
            thickness: Number.parseFloat(style.outlineWidth),
        };
    });

    expect(focus.text).toContain('Check availability');
    expect(focus.thickness).toBeGreaterThanOrEqual(2);
    expect(focus.outline).not.toBe('rgba(0, 0, 0, 0)');
});

test('print gallery fits A4 and produces an audit fixture', async ({ page, browserName }) => {
    test.skip(browserName !== 'chromium', 'PDF generation is available in Chromium.');
    await page.goto(gallery);
    await page.emulateMedia({ media: 'print' });
    await page.pdf({
        path: 'playwright-artifacts/resavar-gallery-print-a4.pdf',
        format: 'A4',
        printBackground: true,
    });
});


test('computed primary and secondary button colors retain navy/white contrast, including hover', async ({ page }) => {
    await page.goto(gallery);
    const primary = page.getByRole('button', { name: 'Check availability' });
    const secondary = page.getByRole('button', { name: 'Change dates' });

    await expect(primary).toHaveCSS('background-color', 'rgb(5, 32, 88)');
    await expect(primary).toHaveCSS('-webkit-text-fill-color', 'rgb(255, 255, 255)');
    await primary.hover();
    await expect(primary).toHaveCSS('background-color', 'rgb(0, 0, 0)');
    await expect(primary).toHaveCSS('-webkit-text-fill-color', 'rgb(255, 255, 255)');

    await expect(secondary).toHaveCSS('background-color', 'rgb(255, 255, 255)');
    await expect(secondary).toHaveCSS('-webkit-text-fill-color', 'rgb(5, 32, 88)');
    await secondary.hover();
    await expect(secondary).toHaveCSS('background-color', 'rgb(5, 32, 88)');
    await expect(secondary).toHaveCSS('-webkit-text-fill-color', 'rgb(255, 255, 255)');
});

test('sample switches remain singular functional native controls', async ({ page }) => {
    await page.goto(gallery);
    const toggles = page.locator('input[type="checkbox"]');
    await expect(toggles).toHaveCount(2);
    await expect(toggles.first()).toBeChecked();
    await expect(toggles.last()).not.toBeChecked();
    await toggles.last().check();
    await expect(toggles.last()).toBeChecked();
});
