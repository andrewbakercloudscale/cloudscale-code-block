/**
 * Login Security tab — end-to-end Playwright tests
 *
 * Run:  npx playwright test tests/login-security.spec.js --headed
 *
 * Requires:
 *   - Site:     https://andrewbaker.ninja
 *   - Test user: cs_devtools_test / TempTest2026! (admin, ID 164)
 *   - Admin:     Set ADMIN_USER / ADMIN_PASS below or via env vars
 *   - wp-2fa and wps-hide-login must be inactive
 */

const { test, expect, request } = require('@playwright/test');
const readline = require('readline');

const SITE        = process.env.WP_SITE        || 'https://andrewbaker.ninja';
const ADMIN_USER  = process.env.WP_ADMIN_USER  || 'andrew';
const ADMIN_PASS  = process.env.WP_ADMIN_PASS  || '';
const TEST_USER   = process.env.WP_TEST_USER   || 'cs_devtools_test';
const TEST_PASS   = process.env.WP_TEST_PASS   || 'TempTest2026!';

// Pi direct address — bypasses Cloudflare for instant redirect verification.
const PI_NGINX  = 'http://192.168.0.48:8082';
const SITE_HOST = new URL(SITE).hostname;

/**
 * Check the HTTP status of a path directly against Pi nginx (no Cloudflare cache).
 * Sends a fake wordpress_logged_in cookie to bypass the nginx FastCGI cache
 * (the cache is keyed on presence of that cookie, not its value).
 * Returns the response status code, e.g. 200, 302.
 */
async function checkDirectStatus(path) {
    const ctx = await request.newContext();
    const headers = {
        'Host':              SITE_HOST,
        'X-Forwarded-Proto': 'https',
        // Bypass nginx FastCGI cache (keyed on cookie presence).
        'Cookie':            'wordpress_logged_in_bypass=1',
    };
    // Warm-up: follow redirects first to establish the connection.
    // Without this, maxRedirects:0 may return a cached 200 from a prior
    // keep-alive connection that pre-dates the Hide Login redirect.
    await ctx.get(`${PI_NGINX}${path}`, { headers }).catch(() => null);
    // Now check without following redirects to read the actual status code.
    const resp = await ctx.get(`${PI_NGINX}${path}`, {
        headers,
        maxRedirects: 0,
    }).catch(() => null);
    const status = resp?.status() ?? 0;
    await ctx.dispose();
    return status;
}
const LOGIN_URL        = `${SITE}/wp-login.php`;
const SECURITY_TAB_URL = `${SITE}/wp-admin/tools.php?page=cloudscale-devtools&tab=login`;

/** Pause and ask the tester to type a value (2FA code, URL, etc.) */
async function askTester(prompt) {
    const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
    return new Promise(resolve => {
        rl.question(`\n>>> ${prompt}\n>>> `, answer => {
            rl.close();
            resolve(answer.trim());
        });
    });
}

/** Pre-set WP test cookie in context so "Cookies blocked" check passes. */
async function addWpTestCookie(ctx) {
    await ctx.addCookies([{
        name:     'wordpress_test_cookie',
        value:    'WP Cookie check',
        domain:   new URL(SITE).hostname,
        path:     '/',
        secure:   true,
        httpOnly: false,
        sameSite: 'Lax',
    }]);
}

/** Log in as a given user. */
async function wpLogin(page, user, pass) {
    await addWpTestCookie(page.context());
    await page.goto(LOGIN_URL, { waitUntil: 'domcontentloaded' });
    await page.fill('#user_login', user);
    await page.fill('#user_pass', pass);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
        page.click('#wp-submit'),
    ]);
    if (page.url().includes('wp-login.php')) {
        const err = await page.locator('#login_error').textContent().catch(() => 'unknown error');
        throw new Error(`Login failed: ${err.trim()}`);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Login Security tab renders correctly
// ─────────────────────────────────────────────────────────────────────────────
test('Login Security tab renders', async ({ page }) => {
    await wpLogin(page, ADMIN_USER, ADMIN_PASS);
    await page.goto(SECURITY_TAB_URL);

    // Tab is active (nav link or heading anywhere on page)
    await expect(page.locator('body')).toContainText(/Login Security/i);

    // Hide Login section — checkbox exists in DOM (may be visually hidden by custom toggle CSS)
    await expect(page.locator('#cs-hide-enabled')).toBeAttached();
    await expect(page.locator('#cs-login-slug')).toBeVisible();
    await expect(page.locator('#cs-hide-save')).toBeVisible();

    // 2FA method radios exist in DOM
    await expect(page.locator('input[name="cs_devtools_2fa_method"][value="off"]')).toBeAttached();
    await expect(page.locator('input[name="cs_devtools_2fa_method"][value="email"]')).toBeAttached();
    await expect(page.locator('input[name="cs_devtools_2fa_method"][value="totp"]')).toBeAttached();

    console.log('✅  Login Security tab renders correctly.');
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. Slug live-preview updates the link
// ─────────────────────────────────────────────────────────────────────────────
test('Slug live-preview updates URL link', async ({ page }) => {
    await wpLogin(page, ADMIN_USER, ADMIN_PASS);
    await page.goto(SECURITY_TAB_URL);

    const slugInput = page.locator('#cs-login-slug');
    await slugInput.fill('my-secret-login');
    const href = await page.locator('#cs-current-login-url').getAttribute('href');
    expect(href).toContain('my-secret-login');

    // Reset
    await slugInput.fill('');
    console.log('✅  Slug live-preview works.');
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. Hide Login — enable, set slug, save, verify redirect
// ─────────────────────────────────────────────────────────────────────────────
test('Hide Login enables custom slug', async ({ page }) => {
    test.setTimeout(120_000);
    await wpLogin(page, ADMIN_USER, ADMIN_PASS);
    await page.goto(SECURITY_TAB_URL);

    // Enable Hide Login — checkbox is visually hidden (custom toggle), set via JS.
    await page.locator('#cs-hide-enabled').waitFor({ state: 'attached', timeout: 10000 });
    await page.evaluate(() => {
        const cb = document.getElementById('cs-hide-enabled');
        if (cb && !cb.checked) { cb.checked = true; cb.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    await page.locator('#cs-login-slug').fill('my-test-login');
    await page.locator('#cs-hide-save').click();

    // "Saved" feedback should flash
    await expect(page.locator('#cs-hide-saved')).toBeVisible({ timeout: 5000 });
    console.log('✅  Hide Login saved.');

    // Verify redirect directly against Pi nginx (bypasses Cloudflare cache entirely).
    const directStatus = await checkDirectStatus('/wp-login.php');
    expect(directStatus).not.toBe(200);
    console.log(`✅  /wp-login.php direct status (bypassing CF): ${directStatus} (expected 302)`);

    // Verify custom slug serves login content using a fresh request context (no session).
    const freshCtx = await page.context().browser().newContext({ ignoreHTTPSErrors: true });
    const freshPage = await freshCtx.newPage();
    await freshPage.goto(`${SITE}/my-test-login/`);
    await expect(freshPage.locator('#loginform').first()).toBeVisible({ timeout: 8000 });
    console.log('✅  Custom slug serves login form.');
    await freshCtx.close();

    // Cleanup — navigate back to admin (session still valid) and disable Hide Login.
    await page.goto(SECURITY_TAB_URL);
    await expect(page.locator('#cs-login-slug')).toBeVisible({ timeout: 10000 });
    await page.locator('#cs-hide-enabled').waitFor({ state: 'attached', timeout: 10000 });
    await page.evaluate(() => {
        const cb = document.getElementById('cs-hide-enabled');
        if (cb && cb.checked) { cb.checked = false; cb.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    await page.locator('#cs-hide-save').click();
    await expect(page.locator('#cs-hide-saved')).toBeVisible({ timeout: 5000 });
    console.log('✅  Hide Login disabled after test.');
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. Email 2FA — enable for test user (as test user)
// ─────────────────────────────────────────────────────────────────────────────
test('Email 2FA — enable flow (test user)', async ({ page }) => {
    // First enable Email as the 2FA method (as admin)
    await wpLogin(page, ADMIN_USER, ADMIN_PASS);
    await page.goto(SECURITY_TAB_URL);
    await page.evaluate(() => {
        const radio = document.querySelector('input[name="cs_devtools_2fa_method"][value="email"]');
        if (radio && !radio.checked) { radio.checked = true; radio.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    await page.locator('#cs-2fa-save').click();
    await expect(page.locator('#cs-2fa-saved')).toBeVisible({ timeout: 5000 });
    console.log('✅  Email 2FA method selected and saved.');

    // Now act as the test user
    // Logout via wp-login.php?action=logout (WP will redirect to get the nonce first).
    await page.goto(`${SITE}/wp-login.php?action=logout`);
    // Confirm the "you want to log out?" page if it appears.
    const confirmBtn = page.locator('a[href*="action=logout"]');
    if (await confirmBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
        await confirmBtn.click();
    }
    await page.waitForTimeout(1000);
    await wpLogin(page, TEST_USER, TEST_PASS);
    await page.goto(`${SITE}/wp-admin/admin.php?page=cs-code-block&tab=login-security`);

    const enableBtn = page.locator('#cs-email-enable-btn');
    await expect(enableBtn).toBeVisible({ timeout: 5000 });

    await enableBtn.click();
    // Button should change to "Resend" or show pending message
    await expect(page.locator('#cs-email-pending-msg')).toBeVisible({ timeout: 10000 });
    const pendingText = await page.locator('#cs-email-pending-msg').textContent();
    console.log('📬  Pending message:', pendingText.trim());

    // Show any port warning
    const warnEl = page.locator('#cs-email-port-warn');
    if (await warnEl.isVisible()) {
        console.log('⚠️  Port warning:', await warnEl.textContent());
    }

    // Ask tester to retrieve the verification link from the email
    const verifyUrl = await askTester(
        'Paste the Email 2FA verification link from the email sent to cs_devtools_test (or press Enter to skip):'
    );

    if (verifyUrl) {
        await page.goto(verifyUrl);
        // Should land back in admin with badge now showing "Active"
        await page.goto(`${SITE}/wp-admin/admin.php?page=cs-code-block&tab=login-security`);
        const badge = page.locator('#cs-email-badge');
        await expect(badge).toBeVisible({ timeout: 5000 });
        const badgeText = await badge.textContent();
        console.log('🏷️  Email 2FA badge after verify:', badgeText.trim());
        expect(badgeText.toLowerCase()).toMatch(/active|verified|enabled/);
        console.log('✅  Email 2FA activated via callback link.');
    } else {
        console.log('⏭️  Skipped email verification (no URL provided).');
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. 2FA login intercept — email code challenge
// ─────────────────────────────────────────────────────────────────────────────
test('2FA login intercept — email code challenge', async ({ page }) => {
    // Make sure Email 2FA method is set
    await wpLogin(page, ADMIN_USER, ADMIN_PASS);
    await page.goto(SECURITY_TAB_URL);
    const methodRadio = page.locator('input[name="cs_devtools_2fa_method"][value="email"]');
    if (!(await methodRadio.isChecked())) {
        await page.evaluate(() => {
            const r = document.querySelector('input[name="cs_devtools_2fa_method"][value="email"]');
            if (r && !r.checked) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); }
        });
        await page.locator('#cs-2fa-save').click();
        await expect(page.locator('#cs-2fa-saved')).toBeVisible({ timeout: 5000 });
    }
    // Logout via wp-login.php?action=logout (WP will redirect to get the nonce first).
    await page.goto(`${SITE}/wp-login.php?action=logout`);
    // Confirm the "you want to log out?" page if it appears.
    const confirmBtn = page.locator('a[href*="action=logout"]');
    if (await confirmBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
        await confirmBtn.click();
    }
    await page.waitForTimeout(1000);

    // Try logging in as test user — should get 2FA challenge
    await page.goto(LOGIN_URL);
    await page.fill('#user_login', TEST_USER);
    await page.fill('#user_pass', TEST_PASS);
    await page.click('#wp-submit');

    // Should land on the 2FA challenge page, not wp-admin
    await page.waitForURL(/cs_devtools_2fa|cs_devtools_token/, { timeout: 10000 });
    console.log('✅  Intercepted at 2FA challenge page:', page.url());

    // Ask for the emailed code
    const code = await askTester('Enter the 6-digit code from the 2FA email sent to cs_devtools_test:');
    if (!code) {
        console.log('⏭️  Skipped 2FA code entry.');
        return;
    }

    await page.fill('input[name="cs_devtools_2fa_code"], #cs-2fa-code-input', code);
    await page.click('button[type="submit"], #cs-2fa-submit');
    await page.waitForURL(/wp-admin/, { timeout: 10000 });
    console.log('✅  Logged in via Email 2FA code.');
});

// ─────────────────────────────────────────────────────────────────────────────
// 6. TOTP setup wizard
// ─────────────────────────────────────────────────────────────────────────────
test('TOTP setup wizard', async ({ page }) => {
    // Switch 2FA method to TOTP (as admin)
    await wpLogin(page, ADMIN_USER, ADMIN_PASS);
    await page.goto(SECURITY_TAB_URL);
    await page.evaluate(() => {
        const radio = document.querySelector('input[name="cs_devtools_2fa_method"][value="totp"]');
        if (radio && !radio.checked) { radio.checked = true; radio.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    await page.locator('#cs-2fa-save').click();
    await expect(page.locator('#cs-2fa-saved')).toBeVisible({ timeout: 5000 });
    console.log('✅  TOTP method saved.');

    // Open wizard
    const setupBtn = page.locator('#cs-totp-setup-btn');
    if (!(await setupBtn.isVisible())) {
        console.log('ℹ️  TOTP already configured — check existing setup or disable first.');
        return;
    }
    await setupBtn.click();

    // Wizard should appear with QR loading → QR image or manual key
    await expect(page.locator('#cs-totp-wizard')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('#cs-totp-qr-loading')).toBeHidden({ timeout: 10000 });

    // Show manual secret
    const secretEl = page.locator('#cs-totp-secret-display');
    if (await secretEl.isVisible()) {
        const secret = await secretEl.textContent();
        console.log('🔑  TOTP secret (Base32):', secret.trim());

        // Ask tester to add to their authenticator and enter a code
        const totpCode = await askTester(
            `Add the TOTP secret "${secret.trim()}" to your authenticator app, then enter the 6-digit code:`
        );
        if (!totpCode) {
            // Cancel wizard
            await page.locator('#cs-totp-cancel-btn').click();
            console.log('⏭️  Skipped TOTP verification.');
            return;
        }

        await page.fill('#cs-totp-verify-code', totpCode);
        await page.locator('#cs-totp-verify-btn').click();
        await expect(page.locator('#cs-totp-verify-msg')).toBeVisible({ timeout: 5000 });
        const msg = await page.locator('#cs-totp-verify-msg').textContent();
        console.log('🏷️  TOTP verify result:', msg.trim());
        expect(msg).toMatch(/Activated|activated/i);
        console.log('✅  TOTP setup complete.');
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// 7. Cleanup — disable 2FA, reset method to Off
// ─────────────────────────────────────────────────────────────────────────────
test('Cleanup — reset 2FA method to Off', async ({ page }) => {
    await wpLogin(page, ADMIN_USER, ADMIN_PASS);
    await page.goto(SECURITY_TAB_URL);
    // Radio may be visually hidden by custom toggle CSS — set via JS.
    await page.evaluate(() => {
        const radio = document.querySelector('input[name="cs_devtools_2fa_method"][value="off"]');
        if (radio && !radio.checked) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
    await page.locator('#cs-2fa-save').click();
    await expect(page.locator('#cs-2fa-saved')).toBeVisible({ timeout: 5000 });
    console.log('✅  2FA method reset to Off.');
});
