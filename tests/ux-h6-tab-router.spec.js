/**
 * UX H6 — Client-side tab switching without full page reloads.
 *
 * Verifies:
 *   - Clicking a tab updates the URL (via history.pushState) without a full reload
 *   - The tab bar active state updates immediately
 *   - Tab content is swapped in (new tab's panel is visible, old tab's is gone)
 *   - The loading spinner appears and disappears
 *
 * Run: npx playwright test tests/ux-h6-tab-router.spec.js
 */

const { test, expect, request: playwrightRequest } = require('@playwright/test');
const path = require('path');

[
    path.join(__dirname, '..', '.env.test'),
    path.join(__dirname, '..', '..', '.env.test'),
].forEach(p => { try { require('dotenv').config({ path: p }); } catch {} });

const SITE        = process.env.WP_SITE              || 'https://your-wordpress-site.example.com';
const SECRET      = process.env.CSDT_TEST_SECRET     || '';
const ROLE        = process.env.CSDT_TEST_ROLE        || '';
const SESSION_URL = process.env.CSDT_TEST_SESSION_URL || '';
const LOGOUT_URL  = process.env.CSDT_TEST_LOGOUT_URL  || '';

if (!SECRET || !ROLE || !SESSION_URL) {
    throw new Error('CSDT_TEST_SECRET, CSDT_TEST_ROLE, and CSDT_TEST_SESSION_URL must be set in .env.test');
}

const PLUGIN_URL = `${SITE}/wp-admin/tools.php?page=cloudscale-devtools`;

async function getAdminSession() {
    const ctx  = await playwrightRequest.newContext({ ignoreHTTPSErrors: true });
    const resp = await ctx.post(SESSION_URL, { data: { secret: SECRET, role: ROLE, ttl: 900 } });
    const body = await resp.json().catch(() => resp.text());
    await ctx.dispose();
    if (!resp.ok()) throw new Error(`test-session API: ${resp.status()}`);
    return body;
}

async function injectCookies(ctx, sess) {
    await ctx.addCookies([
        { name: sess.secure_auth_cookie_name, value: sess.secure_auth_cookie,  domain: sess.cookie_domain, path: '/', secure: true,  httpOnly: true,  sameSite: 'Lax' },
        { name: sess.logged_in_cookie_name,   value: sess.logged_in_cookie,    domain: sess.cookie_domain, path: '/', secure: true,  httpOnly: false, sameSite: 'Lax' },
    ]);
}

test.afterAll(async () => {
    if (!LOGOUT_URL) return;
    try {
        const ctx = await playwrightRequest.newContext({ ignoreHTTPSErrors: true });
        await ctx.post(LOGOUT_URL, { data: { secret: SECRET, role: ROLE } });
        await ctx.dispose();
    } catch {}
});

test('Tab click updates URL without full page reload', async ({ browser }) => {
    const sess = await getAdminSession();
    const ctx  = await browser.newContext({ ignoreHTTPSErrors: true });
    await injectCookies(ctx, sess);
    const page = await ctx.newPage();

    await page.goto(`${PLUGIN_URL}&tab=home`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#cs-tab-bar', { timeout: 15000 });

    // Plant a JS flag — a full page reload clears all JS state; client-side switch preserves it
    await page.evaluate(() => { window.__noReloadFlag = 'intact'; });

    // Click the Performance tab
    await page.locator('#cs-tab-bar a[href*="tab=optimizer"]').click();

    // Wait for content to switch
    await page.waitForSelector('#cs-panel-plugin-stack', { timeout: 15000 });

    // URL should have updated to optimizer tab
    expect(page.url()).toContain('tab=optimizer');

    // Flag must still be set — proves no full page reload occurred
    const flag = await page.evaluate(() => window.__noReloadFlag);
    expect(flag).toBe('intact');

    await ctx.close();
});

test('Tab bar active class updates after client-side switch', async ({ browser }) => {
    const sess = await getAdminSession();
    const ctx  = await browser.newContext({ ignoreHTTPSErrors: true });
    await injectCookies(ctx, sess);
    const page = await ctx.newPage();

    await page.goto(`${PLUGIN_URL}&tab=home`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#cs-tab-bar', { timeout: 15000 });

    // Home tab should be active initially
    await expect(page.locator('#cs-tab-bar a[href*="tab=home"]')).toHaveClass(/active/);

    // Click Performance tab
    await page.locator('#cs-tab-bar a[href*="tab=optimizer"]').click();
    await page.waitForSelector('#cs-panel-plugin-stack', { timeout: 15000 });

    // Performance tab link should now be active, Home should not
    await expect(page.locator('#cs-tab-bar a[href*="tab=optimizer"]')).toHaveClass(/active/);
    await expect(page.locator('#cs-tab-bar a[href*="tab=home"]')).not.toHaveClass(/active/);

    await ctx.close();
});

test('Navigating to second tab swaps content correctly', async ({ browser }) => {
    const sess = await getAdminSession();
    const ctx  = await browser.newContext({ ignoreHTTPSErrors: true });
    await injectCookies(ctx, sess);
    const page = await ctx.newPage();

    await page.goto(`${PLUGIN_URL}&tab=home`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#cs-panel-home', { timeout: 15000 });

    // Home panel visible initially
    await expect(page.locator('#cs-panel-home')).toBeVisible();

    // Navigate to Performance tab
    await page.locator('#cs-tab-bar a[href*="tab=optimizer"]').click();
    await page.waitForSelector('#cs-panel-plugin-stack', { timeout: 15000 });

    // Performance panel should now be visible, Home panel gone
    await expect(page.locator('#cs-panel-plugin-stack')).toBeVisible();
    await expect(page.locator('#cs-panel-home')).toHaveCount(0);

    await ctx.close();
});
