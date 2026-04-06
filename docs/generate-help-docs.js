'use strict';
const helpLib = require('/Users/cp363412/Desktop/github/shared-help-docs/help-lib.js');

helpLib.run({
    baseUrl:    process.env.WP_BASE_URL,
    cookies:    process.env.WP_COOKIES,
    restUser:   process.env.WP_REST_USER,
    restPass:   process.env.WP_REST_PASS,
    docsDir:    process.env.WP_DOCS_DIR,

    pluginName: 'CloudScale DevTools',
    pluginDesc: 'CloudScale DevTools is a free WordPress plugin that runs entirely on your server — no external CDN, no API calls, no subscription. Beautiful syntax highlighting, one-click copy, dark/light mode, a built-in SQL query tool, hide login URL, two-factor authentication, and passkey support. Completely free.',
    pageTitle:  'CloudScale DevTools: Online Help',
    pageSlug:   'cloudscale-devtools-help',
    downloadUrl: 'https://andrewninjawordpress.s3.af-south-1.amazonaws.com/cloudscale-devtools.zip',
    adminUrl:   `${process.env.WP_BASE_URL}/wp-admin/tools.php?page=cloudscale-devtools`,

    pluginFile: `${__dirname}/../cs-code-block.php`,

    sections: [
        { id: 'code-block', label: 'Code Block Overview',  file: 'panel-code-block.png',  elementSelector: '#cs-panel-code-settings' },
        { id: 'migrator',   label: 'Code Block Migrator',  file: 'panel-migrator.png',    elementSelector: '#cs-panel-migrator' },
        { id: 'sql-tool',   label: 'SQL Query Tool',       file: 'panel-sql-tool.png',    tabSelector: 'a[href*="tab=sql"]', elementSelector: '#cs-panel-sql' },
        { id: 'hide-login', label: 'Hide Login URL',       file: 'panel-hide-login.png',  tabSelector: 'a[href*="tab=login"]', elementSelector: '#cs-panel-hide-login' },
        { id: '2fa',        label: 'Two-Factor Auth',      file: 'panel-2fa.png',         tabSelector: 'a[href*="tab=login"]', elementSelector: '#cs-panel-2fa' },
        { id: 'passkeys',   label: 'Passkeys (WebAuthn)',  file: 'panel-passkeys.png',    tabSelector: 'a[href*="tab=login"]', elementSelector: '#cs-panel-passkeys' },
    ],

    docs: {
        'code-block': `
<div style="background:#f0f9ff;border-left:4px solid #0e6b8f;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:28px;">
<h2 style="margin:0 0 10px;font-size:1.3em;color:#0f172a;">Why CloudScale Code Block?</h2>
<p style="margin:0 0 10px;">Popular code highlighting plugins like Enlighter and SyntaxHighlighter load external CDN scripts that add 100–300ms to your page load time. Others charge $30–$50/year for features that should come included. Some require you to write custom CSS just to make the output look presentable.</p>
<p style="margin:0 0 10px;">CloudScale Code Block bundles everything locally — zero external requests, zero impact on your CDN cache hit rate. Auto language detection, clipboard copy button, dark and light theme toggle, and line numbers all work out of the box. It also includes a one-click migrator to import code blocks from other popular plugins.</p>
<p style="margin:0;"><strong>Completely free.</strong> No premium version, no nag screens, no feature gating. Use it on as many sites as you want.</p>
</div>
<p>The <strong>CloudScale Code Block</strong> is a registered Gutenberg block (<code>cloudscale/code</code>) and a <code>[cs_code]</code> shortcode for displaying syntax-highlighted code in WordPress posts and pages. Syntax highlighting is powered by <strong>highlight.js 11.11.1</strong> loaded from the cdnjs CDN, supporting over 190 languages with auto-detection.</p>
<p><strong>Block and shortcode usage:</strong></p>
<ul>
<li><strong>Gutenberg block</strong> — search for "CloudScale Code Block" in the block inserter (<kbd>/code</kbd>). Language, theme override, title, and line numbers are all configurable in the block sidebar panel.</li>
<li><strong>Shortcode:</strong> <code>[cs_code lang="php" title="functions.php"]your code here[/cs_code]</code>. Supported attributes: <code>lang</code> (any highlight.js language alias), <code>title</code> (displayed as a filename label above the block), <code>theme</code> (overrides the site-wide theme for this block).</li>
</ul>
<p><strong>Features:</strong></p>
<ul>
<li><strong>Auto language detection</strong> — highlight.js analyses the code content and selects the most likely language. Accuracy is high for common languages (PHP, JavaScript, Python, SQL, Bash) but lower for short snippets. Override manually via the block sidebar when detection is wrong.</li>
<li><strong>14 colour themes</strong> — Atom One (default), GitHub, Monokai, Nord, Dracula, Tokyo Night, VS 2015, VS Code, Stack Overflow, Night Owl, Gruvbox, Solarized, Panda, Shades of Purple. Each theme loads a dark and light variant; the toggle button switches between them. The selection is stored in <code>localStorage</code> per browser, so each reader's preference persists across page loads.</li>
<li><strong>Copy to clipboard</strong> — uses the Clipboard API (<code>navigator.clipboard.writeText()</code>) with a fallback to <code>document.execCommand('copy')</code> for older browsers.</li>
<li><strong>Line numbers</strong> — toggle per block. Line numbers are rendered via CSS counter to avoid polluting the copied text when a reader clicks Copy.</li>
</ul>
<p><strong>Requirements:</strong> WordPress 6.0+, PHP 7.4+. The block editor script uses <code>@wordpress/blocks</code> API version available in WordPress 5.8+, but full sidebar panel support requires 6.0.</p>`,

        'sql-tool': `
<p>The <strong>SQL Query Tool</strong> lets WordPress administrators run read-only SELECT queries against the live database from within wp-admin — without needing phpMyAdmin, Adminer, or SSH access. Results are displayed in a paginated table with column headers and query execution time.</p>
<p><strong>Security model:</strong></p>
<ul>
<li>Access is restricted to users with the <code>manage_options</code> capability (Administrators only). All AJAX handlers verify a nonce generated with the action <code>cs_devtools_sql_nonce</code>.</li>
<li>Every query is validated by <code>is_safe_query()</code> before execution. This function: strips all block comments (including MySQL optimizer hint syntax <code>/*!...*/</code>) and line comments (<code>--</code> and <code>#</code>), rejects any query containing a semicolon (prevents statement stacking), blocks <code>INTO OUTFILE</code>, <code>INTO DUMPFILE</code>, and <code>LOAD_FILE</code> clauses, and only allows queries starting with <code>SELECT</code>, <code>SHOW</code>, <code>DESCRIBE</code>, <code>DESC</code>, or <code>EXPLAIN</code>.</li>
<li>Queries are executed via <code>$wpdb->get_results()</code> with <code>suppress_errors(true)</code>. Any MySQL error is caught and displayed without exposing the full stack trace.</li>
</ul>
<p><strong>14 built-in quick queries</strong> organised into four groups:</p>
<ul>
<li><em>Health &amp; Diagnostics</em>: database health check, site identity options, table sizes and row counts.</li>
<li><em>Content Summary</em>: posts by type and status, site stats, latest 20 published posts.</li>
<li><em>Bloat &amp; Cleanup</em>: orphaned postmeta, expired transients, revisions/drafts/trash, largest autoloaded options.</li>
<li><em>URL &amp; Migration Helpers</em>: HTTP references, posts with HTTP GUIDs, old IP references, posts missing meta descriptions.</li>
</ul>
<p><strong>Keyboard shortcuts:</strong> <kbd>Enter</kbd> or <kbd>Ctrl+Enter</kbd> runs the query. <kbd>Shift+Enter</kbd> inserts a newline.</p>`,

        'hide-login': `
<p>The <strong>Hide Login URL</strong> feature moves your WordPress login page away from the default <code>/wp-login.php</code> address to a secret URL of your choosing. Bots and automated scanners that probe <code>/wp-login.php</code> receive a 404 response — they never see the login form, which eliminates the vast majority of brute-force and credential-stuffing traffic.</p>
<p><strong>How it works:</strong></p>
<ul>
<li>When enabled, a custom WordPress <code>init</code> hook (priority 1) intercepts requests to your chosen slug and serves <code>wp-login.php</code> transparently, so the login form loads correctly at the new URL without any redirect.</li>
<li>Direct requests to <code>/wp-login.php</code> are blocked by a <code>login_init</code> hook and return a 404.</li>
<li>The <code>wp_login_url()</code>, <code>logout_url()</code>, and <code>lostpassword_url()</code> filters are overridden to always produce your custom URL, so all internal WordPress links (password reset emails, "Back to login" links) continue to work correctly.</li>
</ul>
<p><strong>Setup:</strong></p>
<ol>
<li>Toggle <em>Enable Hide Login</em> on.</li>
<li>Enter a slug in <em>Custom Login Path</em> — letters, numbers, and hyphens only (e.g. <code>my-secret-login</code>). Avoid obvious words like <code>login</code>, <code>admin</code>, or <code>dashboard</code>.</li>
<li>Click <em>Save Hide Login Settings</em>.</li>
<li><strong>Immediately note the new URL</strong> shown after saving. If you lose it, you can recover access via WP-CLI: <code>wp option get cs_devtools_login_slug</code>.</li>
</ol>
<p><strong>Compatibility:</strong> WP-CLI, XMLRPC, REST API, and WP Cron are unaffected — they bypass the login URL check entirely. Other security plugins that check <code>$pagenow === 'wp-login.php'</code> will continue to work because the plugin sets <code>$pagenow</code> correctly when serving the custom slug.</p>`,

        '2fa': `
<p><strong>Two-Factor Authentication (2FA)</strong> requires users to prove their identity with a second factor — a one-time code — in addition to their password. Even if a password is compromised, an attacker cannot log in without also having access to the second factor.</p>
<p><strong>Available methods:</strong></p>
<ul>
<li><strong>Email code</strong> — after a successful password login, a 6-digit code is emailed to the user's account address. The code expires after 10 minutes. No app required, but relies on reliable email delivery.</li>
<li><strong>Authenticator app (TOTP)</strong> — uses the industry-standard Time-based One-Time Password algorithm (RFC 6238). Users scan a QR code with Google Authenticator, Authy, 1Password, or any compatible app. The app generates a fresh 6-digit code every 30 seconds. Works completely offline.</li>
<li><strong>Passkey</strong> — replaces the code prompt with a biometric check (Face ID, Touch ID, Windows Hello) or hardware security key. The fastest and most phishing-resistant method. Requires at least one registered passkey (see the Passkeys section).</li>
</ul>
<p><strong>Site-wide settings:</strong></p>
<ul>
<li><em>2FA Method</em> — sets which method is available to users. "Off" disables 2FA entirely.</li>
<li><em>Force 2FA for all administrators</em> — when checked, any user with the Administrator role who has not yet configured their chosen 2FA method will be blocked from the dashboard after login until they complete setup. This enforces 2FA without requiring each admin to opt in manually.</li>
</ul>
<p><strong>Per-user setup:</strong> Each user configures their own 2FA credentials in the <em>Your 2FA Setup</em> panel. TOTP setup requires scanning a QR code or manually entering the Base32 secret into an authenticator app, then verifying with a live code. Email 2FA requires confirming a verification link sent to the account email before it is activated.</p>`,

        'passkeys': `
<p><strong>Passkeys</strong> are FIDO2/WebAuthn credentials that replace password-based 2FA codes with a biometric or hardware-key verification. When you log in, instead of typing a 6-digit code, you authenticate with Face ID, Touch ID, Windows Hello, or a physical security key (YubiKey, etc.).</p>
<p><strong>How passkeys work:</strong></p>
<ul>
<li>Registration generates a public/private key pair on your device. The private key never leaves your device — only the public key is stored on the server (in WordPress user meta).</li>
<li>At login, the server sends a random challenge. Your device signs it with the private key. The server verifies the signature against the stored public key. No secret is transmitted over the network.</li>
<li>Passkeys are bound to the site's domain (Relying Party ID), making them inherently phishing-resistant — a fake domain cannot trigger your real passkey.</li>
</ul>
<p><strong>Registering a passkey:</strong></p>
<ol>
<li>In the <em>Passkeys (WebAuthn)</em> panel, click <em>+ Add Passkey</em>.</li>
<li>Give the passkey a recognisable label (e.g. "MacBook Pro", "iPhone 16", "YubiKey 5").</li>
<li>Click <em>Register</em> — your browser will prompt you for biometric verification or to insert a hardware key.</li>
<li>On success, the passkey appears in the list. Register one passkey per device you want to use for login.</li>
</ol>
<p><strong>Using passkeys for login:</strong> In the <em>Two-Factor Authentication</em> settings, set the 2FA method to <em>Passkey</em>. After a successful password login, you will be prompted to verify with your passkey instead of typing a code.</p>
<p><strong>Browser support:</strong> Chrome 108+, Safari 16+, Edge 108+, Firefox 122+. Older browsers fall back to an email OTP code automatically.</p>
<p><strong>Testing:</strong> Use the <em>Test</em> button next to each registered passkey to verify it is working correctly without logging out. The test performs a full WebAuthn assertion round-trip and reports success or failure.</p>`,

        'migrator': `
<p>The <strong>Code Block Migrator</strong> converts legacy code block shortcodes and HTML from other WordPress syntax highlighting plugins to CloudScale Code Blocks in a single batch operation — without manual copy-paste or post-by-post editing.</p>
<p><strong>Supported source formats:</strong></p>
<ul>
<li>WordPress core <code><!-- wp:code --></code> blocks — preserves language class attributes where present.</li>
<li>WordPress core <code><!-- wp:preformatted --></code> blocks.</li>
<li><code><!-- wp:code-syntax-block/code --></code> blocks from Code Syntax Block plugin.</li>
<li>Legacy <code>[code]</code>, <code>[sourcecode]</code>, and similar shortcodes — language attribute is preserved if present.</li>
</ul>
<p><strong>Migration workflow:</strong></p>
<ol>
<li><strong>Scan</strong> — queries <code>wp_posts</code> for all posts and pages whose <code>post_content</code> contains a <code>LIKE</code> match on the supported block comment and shortcode patterns. Results are displayed as a list with post title, status, date, and block count.</li>
<li><strong>Preview</strong> — for each post, shows a before/after diff of the exact content changes that will be made. No database writes occur at this stage.</li>
<li><strong>Migrate single</strong> — converts one post at a time. Calls <code>$wpdb->update()</code> to write the converted <code>post_content</code> and immediately flushes the post cache with <code>clean_post_cache()</code>.</li>
<li><strong>Migrate all</strong> — processes every remaining post in a single AJAX request. For large sites (&gt;500 posts), run this during low-traffic periods as it holds a series of database write locks.</li>
</ol>
<p><strong>Always take a backup before running the migrator.</strong> The conversion modifies <code>post_content</code> directly in the database. If the output is not what you expected, restore from backup — there is no undo button.</p>`,
    },
}).catch(err => { console.error('ERROR:', err.message); process.exit(1); });
