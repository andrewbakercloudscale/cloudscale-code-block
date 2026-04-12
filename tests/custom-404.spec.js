/**
 * Custom 404 page — end-to-end Playwright tests
 *
 * Run:  npx playwright test tests/custom-404.spec.js --headed
 *
 * Checks:
 *  - 404 page renders with game tabs
 *  - Canvas games (Runner, Snake etc.) work
 *  - Iframe games (Gamut Shift, Racer 3D) swap canvas for iframe
 *  - Mr. Do! tab is gone
 */

const { test, expect } = require('@playwright/test');

const SITE      = process.env.WP_SITE || 'https://your-wordpress-site.example.com';
const PAGE_404  = `${SITE}/this-page-does-not-exist-cs404test`;

test.describe('Custom 404 page', () => {

    test.beforeEach(async ({ page }) => {
        await page.goto(PAGE_404, { waitUntil: 'networkidle' });
    });

    test('renders 404 heading and game canvas', async ({ page }) => {
        await expect(page.locator('h1, .cs404-heading')).toContainText('404', { ignoreCase: true });
        const canvas = page.locator('#cs404-game');
        await expect(canvas).toBeVisible();
    });

    test('shows correct game tabs — no Mr. Do, yes Gamut Shift + Racer 3D', async ({ page }) => {
        const tabs = page.locator('.cs404-tab');
        await expect(tabs).toHaveCount(8); // runner, jetpack, racer, miner, asteroids, snake, gamutshift, racer3d

        const labels = await tabs.allInnerTexts();
        expect(labels.some(t => /Gamut Shift/i.test(t))).toBe(true);
        expect(labels.some(t => /Racer 3D/i.test(t))).toBe(true);
        expect(labels.some(t => /Mr\.?\s*Do/i.test(t))).toBe(false);
    });

    test('Runner tab — canvas visible, iframe hidden', async ({ page }) => {
        await page.locator('.cs404-tab[data-game="runner"]').click();
        await expect(page.locator('#cs404-game')).toBeVisible();
        const iframe = page.locator('#cs404-iframe');
        await expect(iframe).toBeHidden();
    });

    test('Gamut Shift tab — iframe visible, canvas hidden, src set', async ({ page }) => {
        await page.locator('.cs404-tab[data-game="gamutshift"]').click();

        const iframe = page.locator('#cs404-iframe');
        await expect(iframe).toBeVisible();

        const src = await iframe.getAttribute('src');
        expect(src).toContain('js13kgames.com');
        expect(src).toContain('gamut-shift');

        await expect(page.locator('#cs404-game')).toBeHidden();
    });

    test('Racer 3D tab — iframe visible, canvas hidden, src set', async ({ page }) => {
        await page.locator('.cs404-tab[data-game="racer3d"]').click();

        const iframe = page.locator('#cs404-iframe');
        await expect(iframe).toBeVisible();

        const src = await iframe.getAttribute('src');
        expect(src).toContain('js13kgames.com');
        expect(src).toContain('racer');

        await expect(page.locator('#cs404-game')).toBeHidden();
    });

    test('switching back from iframe to canvas game restores canvas', async ({ page }) => {
        // Go to iframe game
        await page.locator('.cs404-tab[data-game="gamutshift"]').click();
        await expect(page.locator('#cs404-iframe')).toBeVisible();
        await expect(page.locator('#cs404-game')).toBeHidden();

        // Switch back to canvas game
        await page.locator('.cs404-tab[data-game="snake"]').click();
        await expect(page.locator('#cs404-game')).toBeVisible();

        const iframe = page.locator('#cs404-iframe');
        await expect(iframe).toBeHidden();
        // iframe src should be cleared to stop the game loading
        const src = await iframe.getAttribute('src');
        expect(src === '' || src === null).toBe(true);
    });

    test('leaderboard panel updates on tab switch', async ({ page }) => {
        await page.locator('.cs404-tab[data-game="snake"]').click();
        const title = page.locator('#cs404-lb-title');
        await expect(title).toContainText('Snake');
    });

});
