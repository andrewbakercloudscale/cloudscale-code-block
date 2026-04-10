/**
 * Session Persistence — focused Playwright test
 *
 * Verifies that when a custom session duration is configured, the WordPress
 * auth cookie is written as a persistent cookie (explicit expiry, not expire=0)
 * so it survives a browser kill / swipe-up on mobile.
 *
 * Uses cs_devtools_test (admin) — no separate admin credentials needed.
 *
 * Run:  npx playwright test tests/session-persistence.spec.js --headed
 */

const { test, expect, request } = require('@playwright/test');

const SITE      = process.env.WP_SITE      || 'https://andrewbaker.ninja';
const TEST_USER = process.env.WP_TEST_USER || 'cs_session_test';
const TEST_PASS = process.env.WP_TEST_PASS || 'SessionTest2026!';

const LOGIN_URL   = `${SITE}/wp-login.php`;
const SETTINGS_URL = `${SITE}/wp-admin/tools.php?page=cloudscale-devtools&tab=login`;

/** Add the WP test cookie (browser compatibility check) */
async function addWpTestCookie( ctx ) {
    await ctx.addCookies( [ {
        name:     'wordpress_test_cookie',
        value:    'WP Cookie check',
        domain:   new URL( SITE ).hostname,
        path:     '/',
        secure:   true,
        httpOnly: false,
        sameSite: 'Lax',
    } ] );
}

/** Log in and return — throws if login fails */
async function wpLogin( page, user, pass ) {
    await addWpTestCookie( page.context() );
    await page.goto( LOGIN_URL, { waitUntil: 'domcontentloaded' } );
    await page.fill( '#user_login', user );
    await page.fill( '#user_pass',  pass );
    await Promise.all( [
        page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 30_000 } ),
        page.click( '#wp-submit' ),
    ] );
    if ( page.url().includes( 'wp-login.php' ) ) {
        const err = await page.locator( '#login_error' ).textContent().catch( () => 'unknown' );
        throw new Error( `Login failed: ${err.trim()}` );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Baseline — confirm default WP sessions ARE session cookies (expire = -1)
// ─────────────────────────────────────────────────────────────────────────────
test( 'Baseline — default WP session is a session cookie (expires=-1)', async ( { browser } ) => {
    // Make sure session duration is set to "default" first.
    const ctx  = await browser.newContext( { ignoreHTTPSErrors: true } );
    const page = await ctx.newPage();
    await wpLogin( page, TEST_USER, TEST_PASS );

    // Navigate to settings and set duration to "default".
    await page.goto( SETTINGS_URL );
    await page.locator( '#cs-session-duration' ).selectOption( 'default' );
    await page.locator( '#cs-session-save' ).click();
    await expect( page.locator( '#cs-session-saved' ) ).toBeVisible( { timeout: 5_000 } );
    console.log( '✅  Session duration set to default.' );

    // Log out, then log back in fresh so the new setting applies.
    await page.goto( `${SITE}/wp-login.php?action=logout` );
    const confirmLogout = page.locator( 'a[href*="action=logout"]' );
    if ( await confirmLogout.isVisible( { timeout: 3_000 } ).catch( () => false ) ) {
        await confirmLogout.click();
    }
    await page.waitForTimeout( 500 );
    await wpLogin( page, TEST_USER, TEST_PASS );

    const cookies  = await ctx.cookies();
    const authCook = cookies.find( c => c.name.startsWith( 'wordpress_logged_in_' ) );
    console.log( `  Auth cookie expires = ${authCook?.expires}` );

    // With default WP (no remember-me), expire should be -1 (session cookie).
    expect( authCook ).toBeTruthy();
    expect( authCook.expires ).toBe( -1 );
    console.log( '✅  Confirmed: default login is a session cookie (expires=-1).' );
    await ctx.close();
} );

// ─────────────────────────────────────────────────────────────────────────────
// 2. With custom duration — cookie must be persistent (expires > 0)
// ─────────────────────────────────────────────────────────────────────────────
test( 'Custom 30-day session — auth cookie must be persistent (expires > 0)', async ( { browser } ) => {
    const ctx  = await browser.newContext( { ignoreHTTPSErrors: true } );
    const page = await ctx.newPage();
    await wpLogin( page, TEST_USER, TEST_PASS );

    // Set 30-day session.
    await page.goto( SETTINGS_URL );
    await page.locator( '#cs-session-duration' ).selectOption( '30' );
    await page.locator( '#cs-session-save' ).click();
    await expect( page.locator( '#cs-session-saved' ) ).toBeVisible( { timeout: 5_000 } );
    console.log( '✅  Session duration set to 30 days.' );

    // Log out and back in so the new cookie is issued.
    await page.goto( `${SITE}/wp-login.php?action=logout` );
    const confirmLogout = page.locator( 'a[href*="action=logout"]' );
    if ( await confirmLogout.isVisible( { timeout: 3_000 } ).catch( () => false ) ) {
        await confirmLogout.click();
    }
    await page.waitForTimeout( 500 );
    await wpLogin( page, TEST_USER, TEST_PASS );

    const cookies  = await ctx.cookies();
    const authCook = cookies.find( c => c.name.startsWith( 'wordpress_logged_in_' ) );
    console.log( `  Auth cookie expires = ${authCook?.expires}  (0 = session cookie, >0 = persistent)` );

    expect( authCook ).toBeTruthy();
    // -1 or 0 means session cookie — this is the bug we're fixing.
    expect( authCook.expires ).toBeGreaterThan( 0 );

    const expiryDate = new Date( authCook.expires * 1000 );
    const daysFromNow = ( authCook.expires - Date.now() / 1000 ) / 86400;
    console.log( `  Expires: ${expiryDate.toISOString()} (~${Math.round( daysFromNow )} days from now)` );
    expect( daysFromNow ).toBeGreaterThan( 25 );  // should be ~30 days
    console.log( '✅  Auth cookie is persistent — survives browser kill/swipe-up.' );
    await ctx.close();
} );

// ─────────────────────────────────────────────────────────────────────────────
// 3. Simulate browser kill — new context with saved cookies stays logged in
// ─────────────────────────────────────────────────────────────────────────────
test( 'Simulated browser kill — re-opening with saved cookies stays logged in', async ( { browser } ) => {
    // First context: log in with 30-day session and grab cookies.
    const ctx1  = await browser.newContext( { ignoreHTTPSErrors: true } );
    const page1 = await ctx1.newPage();
    await wpLogin( page1, TEST_USER, TEST_PASS );

    // Ensure 30-day duration is set.
    await page1.goto( SETTINGS_URL );
    await page1.locator( '#cs-session-duration' ).selectOption( '30' );
    await page1.locator( '#cs-session-save' ).click();
    await expect( page1.locator( '#cs-session-saved' ) ).toBeVisible( { timeout: 5_000 } );

    // Re-login to get the fresh persistent cookie.
    await page1.goto( `${SITE}/wp-login.php?action=logout` );
    const confirmLogout = page1.locator( 'a[href*="action=logout"]' );
    if ( await confirmLogout.isVisible( { timeout: 3_000 } ).catch( () => false ) ) {
        await confirmLogout.click();
    }
    await page1.waitForTimeout( 500 );
    await wpLogin( page1, TEST_USER, TEST_PASS );

    const cookies = await ctx1.cookies();
    const authCook = cookies.find( c => c.name.startsWith( 'wordpress_logged_in_' ) );
    expect( authCook?.expires ).toBeGreaterThan( 0 );
    console.log( `  Captured persistent cookie: ${authCook?.name}, expires ${new Date( authCook.expires * 1000 ).toISOString()}` );

    // "Kill" the browser — close context 1.
    await ctx1.close();
    console.log( '  Browser context closed (simulating swipe-up / app kill).' );

    // "Reopen" — new context with the saved cookies (simulating restored session).
    const ctx2   = await browser.newContext( { ignoreHTTPSErrors: true } );
    await ctx2.addCookies( cookies );
    const page2  = await ctx2.newPage();
    await page2.goto( `${SITE}/wp-admin/`, { waitUntil: 'domcontentloaded' } );

    // Should land in wp-admin, not be redirected to login.
    const finalUrl = page2.url();
    console.log( `  After "reopen", landed at: ${finalUrl}` );
    expect( finalUrl ).not.toContain( 'wp-login.php' );
    expect( finalUrl ).toContain( 'wp-admin' );
    console.log( '✅  Session survived simulated browser kill — still logged in.' );
    await ctx2.close();
} );

// ─────────────────────────────────────────────────────────────────────────────
// Cleanup — restore default session duration
// ─────────────────────────────────────────────────────────────────────────────
test( 'Cleanup — restore default session duration', async ( { browser } ) => {
    const ctx  = await browser.newContext( { ignoreHTTPSErrors: true } );
    const page = await ctx.newPage();
    await wpLogin( page, TEST_USER, TEST_PASS );
    await page.goto( SETTINGS_URL );
    await page.locator( '#cs-session-duration' ).selectOption( 'default' );
    await page.locator( '#cs-session-save' ).click();
    await expect( page.locator( '#cs-session-saved' ) ).toBeVisible( { timeout: 5_000 } );
    console.log( '✅  Session duration restored to default.' );
    await ctx.close();
} );
