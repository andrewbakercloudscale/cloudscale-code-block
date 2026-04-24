/**
 * Block Basic Auth toggle — focused debug test
 *
 * Run:  npx playwright test tests/block-basic-auth.spec.js --headed
 */

const { test, expect, request: playwrightRequest } = require('@playwright/test');
const path = require('path');

[ path.join(__dirname, '..', '.env.test'),
  path.join(__dirname, '..', '..', '.env.test'),
].forEach(p => { try { require('dotenv').config({ path: p }); } catch {} });

const SITE        = process.env.WP_SITE             || 'https://your-wordpress-site.example.com';
const SECRET      = process.env.CSDT_TEST_SECRET     || '';
const ROLE        = process.env.CSDT_TEST_ROLE        || '';
const SESSION_URL = process.env.CSDT_TEST_SESSION_URL || '';
const LOGOUT_URL  = process.env.CSDT_TEST_LOGOUT_URL  || '';
const LOGIN_TAB   = `${SITE}/wp-admin/tools.php?page=cloudscale-devtools&tab=login`;
const PI_DIRECT   = 'http://192.168.0.48:8082'; // bypass Cloudflare for AJAX
const SITE_HOST   = new URL(SITE).hostname;

async function getSession(ttl = 1200) {
    const ctx  = await playwrightRequest.newContext({ ignoreHTTPSErrors: true });
    const resp = await ctx.post(SESSION_URL, { data: { secret: SECRET, role: ROLE, ttl } });
    const body = await resp.json().catch(() => resp.text());
    await ctx.dispose();
    if (!resp.ok()) throw new Error(`Session API ${resp.status()}: ${JSON.stringify(body)}`);
    return body;
}

async function injectCookies(ctx, sess) {
    await ctx.addCookies([
        { name: sess.secure_auth_cookie_name, value: sess.secure_auth_cookie,
          domain: sess.cookie_domain, path: '/', secure: true, httpOnly: true, sameSite: 'Lax' },
        { name: sess.logged_in_cookie_name, value: sess.logged_in_cookie,
          domain: sess.cookie_domain, path: '/', secure: true, httpOnly: false, sameSite: 'Lax' },
    ]);
}

test.afterAll(async () => {
    if (!LOGOUT_URL) return;
    const ctx = await playwrightRequest.newContext({ ignoreHTTPSErrors: true });
    await ctx.post(LOGOUT_URL, { data: { secret: SECRET, role: ROLE } }).catch(() => {});
    await ctx.dispose();
});

test('Block Basic Auth toggle saves and persists', async ({ page }) => {
    const sess = await getSession();
    await injectCookies(page.context(), sess);

    // Route admin-ajax.php directly to Pi to bypass Cloudflare rate-limiting
    await page.route('**/admin-ajax.php', async route => {
        const req = route.request();
        const directUrl = `${PI_DIRECT}/wp-admin/admin-ajax.php`;
        console.log('  [route] intercepted:', req.method(), req.url(), '→', directUrl);
        try {
            const resp = await route.fetch({
                url:     directUrl,
                headers: { ...req.headers(), 'Host': SITE_HOST, 'X-Forwarded-Proto': 'https' },
            });
            console.log('  [route] Pi responded:', resp.status());
            await route.fulfill({ response: resp });
        } catch(e) {
            console.log('  [route] fetch error:', e.message);
            await route.continue();
        }
    });

    // Intercept the AJAX call to see what comes back
    const ajaxResponses = [];
    page.on('response', async resp => {
        if (resp.url().includes('admin-ajax.php')) {
            const body = await resp.text().catch(() => '(unreadable)');
            ajaxResponses.push({ status: resp.status(), body });
            console.log('  AJAX response status:', resp.status(), 'body:', body.slice(0, 100));
        }
    });

    // Log JS console output
    page.on('console', msg => {
        const text = msg.text();
        if (text.includes('[csdt-bba]') || msg.type() === 'error') {
            console.log(`  JS ${msg.type()}:`, text);
        }
    });

    await page.goto(LOGIN_TAB, { waitUntil: 'domcontentloaded' });

    // Check page URL and what plugin scripts are loaded
    console.log('  Page URL:', page.url());
    const allPluginScripts = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('script[src*="cloudscale"]')).map(s => s.src.split('?')[0].split('/').pop());
    });
    console.log('  Plugin scripts:', allPluginScripts);

    // Diagnose JS config
    const cfg = await page.evaluate(() => {
        return {
            defined:  typeof csdtTestAccounts !== 'undefined',
            ajaxUrl:  typeof csdtTestAccounts !== 'undefined' ? csdtTestAccounts.ajaxUrl : 'N/A',
            hasNonce: typeof csdtTestAccounts !== 'undefined' ? !!csdtTestAccounts.nonce : false,
            saveBtnExists: !!document.getElementById('cs-block-basic-auth-save'),
            toggleExists:  !!document.getElementById('cs-block-basic-auth-toggle'),
        };
    });
    console.log('  JS config:', JSON.stringify(cfg));

    // Confirm the toggle and save button are present
    const toggle  = page.locator('#cs-block-basic-auth-toggle');
    const saveBtn = page.locator('#cs-block-basic-auth-save');
    const hint    = page.locator('#cs-block-basic-auth-hint');

    await expect(toggle,  'toggle exists').toBeAttached();
    await expect(saveBtn, 'save button exists').toBeVisible();
    console.log('  Toggle checked:', await toggle.isChecked());

    // Attach dialog handler in case alert() fires
    page.on('dialog', async dialog => {
        console.log('  DIALOG:', dialog.message());
        await dialog.dismiss();
    });

    // Confirm the click handler is wired up by attaching a test listener
    await page.evaluate(() => {
        window.__saveClicked = false;
        const btn = document.getElementById('cs-block-basic-auth-save');
        if (btn) btn.addEventListener('click', () => { window.__saveClicked = true; }, true);
    });

    // Fire AJAX directly to test the PHP handler in isolation
    const directResult = await page.evaluate(async () => {
        const url   = csdtTestAccounts.ajaxUrl;
        const nonce = csdtTestAccounts.nonce;
        const body  = new FormData();
        body.append('action',  'csdt_toggle_block_basic_auth');
        body.append('nonce',   nonce);
        body.append('enabled', '0');
        const resp = await fetch(url, { method: 'POST', body });
        return resp.text();
    });
    console.log('  Direct AJAX result (first 120 chars):', directResult.slice(0, 120));

    // Set to unchecked (known state), save, confirm feedback
    // Catch uncaught JS exceptions
    page.on('pageerror', err => console.log('  PAGE ERROR:', err.message));

    await page.evaluate(() => { document.getElementById('cs-block-basic-auth-toggle').checked = false; });
    await saveBtn.click();
    const clicked = await page.evaluate(() => window.__saveClicked);
    console.log('  Save button click registered:', clicked);

    // "✓ Saved" appears briefly (~200ms after click) then clears after 2s — catch it immediately
    await expect(hint).toContainText('Saved', { timeout: 5000 });
    console.log('  AJAX calls made:', ajaxResponses.length);
    console.log('  Save (uncheck) confirmed');

    // Reload and verify it persisted as unchecked
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('#cs-block-basic-auth-toggle')).not.toBeChecked();
    console.log('  Persisted as unchecked after reload');

    // Now check it, save, confirm feedback
    await page.evaluate(() => { document.getElementById('cs-block-basic-auth-toggle').checked = true; });
    await page.locator('#cs-block-basic-auth-save').click();
    await expect(page.locator('#cs-block-basic-auth-hint')).toContainText('Saved', { timeout: 5000 });
    console.log('  Save (check) confirmed');

    // Reload and verify it persisted as checked
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('#cs-block-basic-auth-toggle')).toBeChecked();
    console.log('  Persisted as checked after reload');

    // Leave it unchecked when done (safe default)
    await page.evaluate(() => { document.getElementById('cs-block-basic-auth-toggle').checked = false; });
    await page.locator('#cs-block-basic-auth-save').click();
    await expect(page.locator('#cs-block-basic-auth-hint')).toContainText('Saved', { timeout: 5000 });
    console.log('  Restored to unchecked');
});
