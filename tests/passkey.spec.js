/**
 * Passkey (WebAuthn) end-to-end Playwright tests
 *
 * Uses Playwright's built-in WebAuthn virtual authenticator via CDP.
 * No real hardware key or biometrics required — runs fully automated.
 *
 * Run: npx playwright test tests/passkey.spec.js --headed
 *
 * Requires:
 *   - Site:   https://andrewbaker.ninja
 *   - Admin:  Set ADMIN_USER / ADMIN_PASS env vars (or edit defaults below)
 *   - Test user: cs_devtools_test / TempTest2026! (subscriber, ID 164)
 *   - global-setup.js resets cs_devtools_passkeys user meta before each suite run
 */

const { test, expect, request } = require('@playwright/test');

const SITE         = process.env.WP_SITE       || 'https://andrewbaker.ninja';
const ADMIN_USER   = process.env.WP_ADMIN_USER || 'admin';
const ADMIN_PASS   = process.env.WP_ADMIN_PASS || '';
const TEST_USER    = process.env.WP_TEST_USER  || 'cs_devtools_test';
const TEST_PASS    = process.env.WP_TEST_PASS  || 'TempTest2026!';
const LOGIN_URL    = `${SITE}/wp-login.php`;
const SECURITY_URL = `${SITE}/wp-admin/tools.php?page=cloudscale-devtools&tab=login`;

// ── Helpers ───────────────────────────────────────────────────────────────────

async function addWpTestCookie(ctx) {
    await ctx.addCookies([{
        name: 'wordpress_test_cookie', value: 'WP Cookie check',
        domain: new URL(SITE).hostname, path: '/',
        secure: true, httpOnly: false, sameSite: 'Lax',
    }]);
}

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
        const err = await page.locator('#login_error').textContent().catch(() => 'unknown');
        throw new Error(`Login failed: ${err.trim()}`);
    }
}

/**
 * Enables the CDP-based virtual WebAuthn authenticator on the page.
 * Returns { client, authenticatorId } for cleanup.
 */
async function enableVirtualAuthenticator(page) {
    const client = await page.context().newCDPSession(page);
    await client.send('WebAuthn.enable', { enableUI: false });
    const { authenticatorId } = await client.send('WebAuthn.addVirtualAuthenticator', {
        options: {
            protocol:            'ctap2',
            transport:           'internal',
            hasResidentKey:      true,
            hasUserVerification: true,
            isUserVerified:      true,
        },
    });
    return { client, authenticatorId };
}

async function disableVirtualAuthenticator(client, authenticatorId) {
    await client.send('WebAuthn.removeVirtualAuthenticator', { authenticatorId }).catch(() => {});
    await client.send('WebAuthn.disable').catch(() => {});
}

// ── Tests ─────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// 1. Passkey section renders on the Login Security tab
// ─────────────────────────────────────────────────────────────────────────────
test('Passkey section renders', async ({ page }) => {
    await wpLogin(page, ADMIN_USER, ADMIN_PASS);
    await page.goto(SECURITY_URL);

    await expect(page.locator('#cs-pk-row')).toBeVisible({ timeout: 8000 });
    await expect(page.locator('#cs-pk-add-btn')).toBeVisible();
    await expect(page.locator('#cs-pk-badge')).toBeVisible();

    console.log('✅  Passkey section renders.');
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. Add Passkey wizard opens and closes
// ─────────────────────────────────────────────────────────────────────────────
test('Add Passkey wizard opens and cancels', async ({ page }) => {
    await wpLogin(page, ADMIN_USER, ADMIN_PASS);
    await page.goto(SECURITY_URL);

    // Open wizard
    await page.locator('#cs-pk-add-btn').click();
    await expect(page.locator('#cs-pk-wizard')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('#cs-pk-name-input')).toBeVisible();
    await expect(page.locator('#cs-pk-register-btn')).toBeVisible();

    // Cancel
    await page.locator('#cs-pk-cancel-btn').click();
    await expect(page.locator('#cs-pk-wizard')).toBeHidden({ timeout: 3000 });
    await expect(page.locator('#cs-pk-add-btn')).toBeVisible();

    console.log('✅  Passkey wizard opens and cancels correctly.');
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. Register a passkey (virtual authenticator)
// ─────────────────────────────────────────────────────────────────────────────
test('Register a passkey via virtual authenticator', async ({ page }) => {
    test.setTimeout(60_000);

    // Enable virtual WebAuthn authenticator BEFORE navigating to the page.
    const { client, authenticatorId } = await enableVirtualAuthenticator(page);

    try {
        await wpLogin(page, TEST_USER, TEST_PASS);
        await page.goto(SECURITY_URL);

        // Open wizard
        await page.locator('#cs-pk-add-btn').click();
        await expect(page.locator('#cs-pk-wizard')).toBeVisible({ timeout: 5000 });

        // Fill name and register
        await page.locator('#cs-pk-name-input').fill('Test Device');
        await page.locator('#cs-pk-register-btn').click();

        // Should show success and reload — wait for badge update
        // (Either "success" status flashes OR page reloads showing "1 passkey" badge)
        await page.waitForFunction(
            () => {
                const badge = document.getElementById('cs-pk-badge');
                return badge && badge.textContent.includes('passkey');
            },
            { timeout: 15000 }
        );

        const badgeText = await page.locator('#cs-pk-badge').textContent();
        expect(badgeText).toMatch(/\d+\s*passkey/i);
        console.log(`✅  Passkey registered. Badge: "${badgeText.trim()}"`);

        // Passkey item should appear in the list
        await expect(page.locator('#cs-pk-list .cs-pk-item').first()).toBeVisible({ timeout: 5000 });
        const itemName = await page.locator('#cs-pk-list .cs-pk-item .cs-pk-name').first().textContent();
        expect(itemName.trim()).toBe('Test Device');
        console.log('✅  Passkey item displayed in list.');

    } finally {
        await disableVirtualAuthenticator(client, authenticatorId);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. Login with passkey (2FA intercept)
// ─────────────────────────────────────────────────────────────────────────────
test('Login with passkey — 2FA intercept', async ({ page }) => {
    test.setTimeout(60_000);

    // Virtual authenticator must be active when the login page triggers credentials.get().
    const { client, authenticatorId } = await enableVirtualAuthenticator(page);

    try {
        await addWpTestCookie(page.context());
        await page.goto(LOGIN_URL, { waitUntil: 'domcontentloaded' });
        await page.fill('#user_login', TEST_USER);
        await page.fill('#user_pass', TEST_PASS);
        await page.click('#wp-submit');

        // Should redirect to passkey challenge page (action=cs_devtools_2fa).
        await page.waitForURL(/cs_devtools_2fa|cs_devtools_token/, { timeout: 15000 });
        console.log('✅  Intercepted at passkey challenge page:', page.url());

        // The passkey challenge page auto-calls navigator.credentials.get().
        // The virtual authenticator handles it and submits the form.
        // We should end up in wp-admin.
        await page.waitForURL(/wp-admin/, { timeout: 20000 });
        console.log('✅  Logged in via passkey. URL:', page.url());

        // Confirm we're actually in the dashboard.
        await expect(page.locator('#wpbody')).toBeVisible({ timeout: 5000 });

    } finally {
        await disableVirtualAuthenticator(client, authenticatorId);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. Remove a passkey
// ─────────────────────────────────────────────────────────────────────────────
test('Remove a passkey', async ({ page }) => {
    await wpLogin(page, TEST_USER, TEST_PASS);
    await page.goto(SECURITY_URL);

    // Check there's at least one passkey to remove (from test 3).
    const firstItem = page.locator('#cs-pk-list .cs-pk-item').first();
    await expect(firstItem).toBeVisible({ timeout: 5000 });

    // Click Remove — handle the confirm dialog.
    page.on('dialog', d => d.accept());
    await firstItem.locator('.cs-pk-delete').click();

    // Item should disappear.
    await expect(firstItem).toBeHidden({ timeout: 5000 });

    // Badge should update.
    const badgeText = await page.locator('#cs-pk-badge').textContent();
    console.log(`✅  Passkey removed. Badge now: "${badgeText.trim()}"`);
});

// ─────────────────────────────────────────────────────────────────────────────
// 6. Cleanup — ensure cs_devtools_test has no passkeys left
// ─────────────────────────────────────────────────────────────────────────────
test('Cleanup — verify no passkeys remain for cs_devtools_test', async ({ page }) => {
    await wpLogin(page, TEST_USER, TEST_PASS);
    await page.goto(SECURITY_URL);

    // After test 5 removed the passkey, there should be none.
    const items = page.locator('#cs-pk-list .cs-pk-item');
    const count = await items.count();
    expect(count).toBe(0);

    const badge = page.locator('#cs-pk-badge');
    await expect(badge).toContainText(/none/i, { timeout: 3000 });
    console.log('✅  No passkeys remain for cs_devtools_test.');
});
