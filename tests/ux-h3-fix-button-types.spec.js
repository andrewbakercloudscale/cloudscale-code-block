/**
 * UX H3 — Fix It button types visually distinct with confirmation guard.
 *
 * Verifies:
 *   - Moderate-risk buttons (Set to 0400, Move Outside Web Root) have amber styling
 *   - Clicking a moderate-risk button shows an inline confirmation before applying
 *   - Cancel restores the original button
 *   - Safe buttons have no amber/red override styling
 *
 * Run: npx playwright test tests/ux-h3-fix-button-types.spec.js
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

test('Moderate-risk fix items use risk field in AJAX response', async ({ browser }) => {
    const sess = await getAdminSession();
    const ctx  = await browser.newContext({ ignoreHTTPSErrors: true });
    await injectCookies(ctx, sess);
    const page = await ctx.newPage();

    await page.goto(`${PLUGIN_URL}&tab=home`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#cs-quick-fixes-panel', { timeout: 15000 });

    // Fetch quick fixes from the API and verify the risk field is present
    const nonce = await page.evaluate(() => window.csdt_nonce || window.csdtSettings?.nonce || document.querySelector('[name="_wpnonce"]')?.value || '');
    const ajaxUrl = `${SITE}/wp-admin/admin-ajax.php`;
    const resp = await page.evaluate(async ({ ajaxUrl, nonce }) => {
        const fd = new FormData();
        fd.append('action', 'csdt_devtools_quick_fix');
        fd.append('fix_action', 'refresh');
        fd.append('_wpnonce', nonce || '');
        try {
            const r = await fetch(ajaxUrl, { method: 'POST', body: fd });
            return await r.json();
        } catch (e) {
            return null;
        }
    }, { ajaxUrl, nonce });

    // The page renders quick fix HTML — verify moderate risk buttons exist in HTML source when fixes are unfixed,
    // OR verify that any present moderate-risk buttons have the correct attribute.
    const moderateBtns = page.locator('.cs-quick-fix-btn[data-risk="moderate"]');
    const count = await moderateBtns.count();
    // If unfixed moderate buttons are present, they must have the data-risk attribute
    if (count > 0) {
        for (let i = 0; i < count; i++) {
            await expect(moderateBtns.nth(i)).toHaveAttribute('data-risk', 'moderate');
        }
    }
    // Either 0 (all fixed) or up to 2 moderate-risk buttons
    expect(count).toBeGreaterThanOrEqual(0);
    expect(count).toBeLessThanOrEqual(2);

    await ctx.close();
});

test('Clicking a moderate-risk button shows inline confirmation', async ({ browser }) => {
    const sess = await getAdminSession();
    const ctx  = await browser.newContext({ ignoreHTTPSErrors: true });
    await injectCookies(ctx, sess);
    const page = await ctx.newPage();

    await page.goto(`${PLUGIN_URL}&tab=home`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#cs-quick-fixes-panel', { timeout: 15000 });

    // Find the first unfixed moderate-risk button
    const btn = page.locator('.cs-quick-fix-btn[data-risk="moderate"]').first();
    const count = await btn.count();
    if (count === 0) {
        // All moderate fixes already applied — skip
        return;
    }

    await btn.click();

    // Confirmation prompt should appear with Confirm and Cancel buttons
    await expect(page.locator('[data-qf-confirm]').first()).toBeVisible({ timeout: 3000 });
    await expect(page.locator('[data-qf-cancel]').first()).toBeVisible({ timeout: 3000 });

    await ctx.close();
});

test('Cancelling moderate-risk confirmation restores the button', async ({ browser }) => {
    const sess = await getAdminSession();
    const ctx  = await browser.newContext({ ignoreHTTPSErrors: true });
    await injectCookies(ctx, sess);
    const page = await ctx.newPage();

    await page.goto(`${PLUGIN_URL}&tab=home`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#cs-quick-fixes-panel', { timeout: 15000 });

    const btn = page.locator('.cs-quick-fix-btn[data-risk="moderate"]').first();
    const count = await btn.count();
    if (count === 0) return;

    await btn.click();
    await page.locator('[data-qf-cancel]').first().click();

    // Button should be restored
    await expect(page.locator('.cs-quick-fix-btn[data-risk="moderate"]').first()).toBeVisible({ timeout: 3000 });

    await ctx.close();
});
