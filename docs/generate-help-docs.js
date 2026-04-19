'use strict';
const helpLib = require('REPO_BASE/shared-help-docs/help-lib.js');

helpLib.run({
    baseUrl:    process.env.WP_BASE_URL,
    cookies:    process.env.WP_COOKIES,
    restUser:   process.env.WP_REST_USER,
    restPass:   process.env.WP_REST_PASS,
    docsDir:    process.env.WP_DOCS_DIR,

    pluginName: 'CloudScale Cyber and Devtools',
    pluginDesc: 'Free WordPress security plugin — AI cyber audit using Claude &amp; Gemini, two-factor authentication, passkeys, login URL protection, one-click hardening, server logs, and code blocks.',
    seoTitle:  'CloudScale Cyber & Devtools | Free WordPress Security Plugin',
    seoDesc:   'Free WordPress security plugin with AI cyber audit (Claude & Gemini), 2FA, passkeys, one-click hardening, server logs, and code blocks. No subscription.',

    schema: {
        '@context': 'https://schema.org',
        '@type': 'SoftwareApplication',
        'name': 'CloudScale Cyber and Devtools',
        'applicationCategory': 'SecurityApplication',
        'operatingSystem': 'WordPress',
        'offers': { '@type': 'Offer', 'price': '0', 'priceCurrency': 'USD' },
        'description': 'Free WordPress security plugin powered by Anthropic Claude and Google Gemini AI. Features: AI cyber audit, two-factor authentication, passkeys (WebAuthn), hide login URL, brute-force protection, CSP builder, server logs, SQL tool, and syntax-highlighted code blocks.',
        'url': 'https://your-wordpress-site.example.com/wordpress-plugin-help/cloudscale-cyber-devtools-help/',
        'downloadUrl': 'https://your-s3-bucket.s3.af-south-1.amazonaws.com/cloudscale-devtools.zip',
        'softwareVersion': '1.9.117',
        'author': { '@type': 'Person', 'name': 'Andrew Baker', 'url': 'https://your-wordpress-site.example.com' },
        'isAccessibleForFree': true,
        'license': 'https://www.gnu.org/licenses/gpl-2.0.html',
    },
    pageTitle:  'CloudScale Cyber and Devtools',
    pageSlug:   'cloudscale-cyber-devtools-help',
    downloadUrl: 'https://your-s3-bucket.s3.af-south-1.amazonaws.com/cloudscale-devtools.zip',
    repoUrl:     'https://github.com/andrewbakercloudscale/cloudscale-cyber-devtools',
    adminUrl:   `${process.env.WP_BASE_URL}/wp-admin/tools.php?page=cloudscale-devtools`,

    pluginFile: `${__dirname}/../cs-code-block.php`,

    pluginIntro: `
<h2 style="font-size:1.6em;font-weight:800;color:#0f172a;margin:0 0 16px;">What is CloudScale Cyber and Devtools?</h2>
<p style="font-size:1.05em;color:#374151;margin:0 0 20px;line-height:1.75;">CloudScale Cyber and Devtools is a <strong>free, open-source WordPress security and developer toolkit</strong> powered by the world's most capable AI models — <strong>Anthropic Claude</strong> (Sonnet and Opus 4) and <strong>Google Gemini</strong> (Flash and 2.5 Pro). These are the same frontier models used by enterprise security teams, now available for your WordPress site in a single free plugin. Built by the community, for the community — everything runs on your own server and you supply your own API key. No premium tier, no nag screens.</p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin:0 0 28px;">
<div style="background:#fff5f5;border-left:4px solid #e53e3e;border-radius:0 8px 8px 0;padding:18px 20px;">
<h3 style="margin:0 0 10px;font-size:1em;font-weight:700;color:#1a202c;text-transform:uppercase;letter-spacing:.05em;">🛡️ Security</h3>
<ul style="margin:0;padding-left:18px;color:#374151;font-size:.95em;line-height:1.8;">
<li><strong>AI Cyber Audit</strong> — scored security report in under 60 seconds using Claude or Gemini</li>
<li><strong>Deep Dive Scan</strong> — HTTP probes, DNS checks, TLS, PHP code analysis</li>
<li><strong>Quick Fixes</strong> — one-click hardening for common misconfigurations</li>
<li><strong>Scheduled Scans</strong> — daily/weekly background scans with email alerts</li>
<li><strong>CSP Builder</strong> — build a Content Security Policy safely with rollback</li>
<li><strong>Server Logs</strong> — read PHP, WordPress and web server logs in-browser</li>
</ul>
</div>
<div style="background:#f0f9ff;border-left:4px solid #0e6b8f;border-radius:0 8px 8px 0;padding:18px 20px;">
<h3 style="margin:0 0 10px;font-size:1em;font-weight:700;color:#1a202c;text-transform:uppercase;letter-spacing:.05em;">🔐 Login Security</h3>
<ul style="margin:0;padding-left:18px;color:#374151;font-size:.95em;line-height:1.8;">
<li><strong>Hide Login URL</strong> — move /wp-login.php to a secret slug</li>
<li><strong>Two-Factor Authentication</strong> — email OTP, TOTP (authenticator app), or passkeys</li>
<li><strong>Passkeys (WebAuthn)</strong> — Face ID, Touch ID, Windows Hello, YubiKey</li>
<li><strong>Brute-Force Protection</strong> — per-account lockout after N failed attempts</li>
<li><strong>Force 2FA for admins</strong> — block dashboard access until 2FA is set up</li>
<li><strong>Test Account Manager</strong> — temporary accounts for Playwright / CI pipelines</li>
</ul>
</div>
<div style="background:#f0fdf4;border-left:4px solid #16a34a;border-radius:0 8px 8px 0;padding:18px 20px;">
<h3 style="margin:0 0 10px;font-size:1em;font-weight:700;color:#1a202c;text-transform:uppercase;letter-spacing:.05em;">🛠️ Developer Tools</h3>
<ul style="margin:0;padding-left:18px;color:#374151;font-size:.95em;line-height:1.8;">
<li><strong>Syntax-highlighted Code Block</strong> — 190+ languages, 14 themes, bundled locally</li>
<li><strong>Code Block Migrator</strong> — batch-convert blocks from other plugins</li>
<li><strong>SQL Query Tool</strong> — read-only SELECT queries in-browser</li>
<li><strong>SMTP Mail</strong> — replace PHP mail() with authenticated SMTP</li>
<li><strong>Performance Monitor</strong> — overlay showing queries, hooks, assets per page</li>
<li><strong>Custom 404 Page</strong> — branded 404 with 7 playable mini-games and leaderboard</li>
</ul>
</div>
<div style="background:#fafafa;border-left:4px solid #6366f1;border-radius:0 8px 8px 0;padding:18px 20px;">
<h3 style="margin:0 0 10px;font-size:1em;font-weight:700;color:#1a202c;text-transform:uppercase;letter-spacing:.05em;">⚡ Getting Started</h3>
<ol style="margin:0;padding-left:18px;color:#374151;font-size:.95em;line-height:1.8;">
<li>Download the zip using the button above</li>
<li>In WordPress: <strong>Plugins → Add New → Upload Plugin</strong></li>
<li>Upload, install, and activate</li>
<li>Go to <strong>Tools → Cyber and Devtools</strong></li>
<li>For the AI Cyber Audit: get a free API key from <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio</a> (no credit card) or <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">Anthropic</a></li>
</ol>
<p style="margin:12px 0 0;font-size:.9em;color:#64748b;"><strong>Requirements:</strong> WordPress 6.0+, PHP 7.4+</p>
</div>
</div>`,

    sections: [
        { id: 'hide-login', label: 'Hide Login URL',        file: 'panel-hide-login.png',  tabSelector: 'a[href*="tab=login"]',    elementSelector: '#cs-panel-hide-login',
          altText: 'WordPress Hide Login URL settings — move wp-login.php to a secret URL to block bot attacks',
          jsBeforeShot: () => {
            var s = document.getElementById('cs-login-slug');
            if (s) s.value = 'your-secret-slug';
            var u = document.getElementById('cs-current-login-url');
            if (u) u.textContent = window.location.origin + '/your-secret-slug/';
          } },
        { id: '2fa',        label: 'Two-Factor Auth',       file: 'panel-2fa.png',         tabSelector: 'a[href*="tab=login"]',    elementSelector: '#cs-panel-2fa',
          altText: 'WordPress two-factor authentication settings — email OTP, TOTP authenticator app, and passkeys' },
        { id: 'passkeys',   label: 'Passkeys (WebAuthn)',   file: 'panel-passkeys.png',    tabSelector: 'a[href*="tab=login"]',    elementSelector: '#cs-panel-passkeys',
          altText: 'WordPress passkeys WebAuthn registration — Face ID, Touch ID and hardware security key login' },
        { id: 'security',   label: 'AI Cyber Audit',        file: 'panel-security.png',    tabSelector: 'a[href*="tab=security"]', elementSelector: '#cs-vuln-results',
          altText: 'WordPress AI security audit result — score 100/100 with Claude 4 and Gemini 2.5 Pro, free security plugin',
          jsBeforeShot: () => {
            // Inject demo data: score 100, no real findings
            var r = document.getElementById('cs-vuln-results');
            if (r) {
                r.style.display = 'block';
                r.innerHTML =
                    '<div class="cs-audit-header">' +
                    '<div class="cs-audit-score-circle cs-audit-score-excellent">' +
                    '<span class="cs-audit-score-num">100</span>' +
                    '<span class="cs-audit-score-lbl">Excellent</span>' +
                    '</div>' +
                    '<div class="cs-audit-meta">' +
                    '<p class="cs-audit-summary-text">Your WordPress installation demonstrates exceptional security. All critical controls are in place — security headers, 2FA, hidden login URL, disabled file editing, and no vulnerable plugins. Nothing to remediate.</p>' +
                    '<span class="cs-audit-meta-line">Model: claude-sonnet-4-6 · Auto AI Model</span>' +
                    '</div></div>' +
                    '<div class="cs-audit-section cs-audit-sec-good">' +
                    '<h4 class="cs-audit-section-title">Good Practices (8)</h4>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>Security headers configured</strong> — X-Content-Type-Options, X-Frame-Options, Referrer-Policy, and Permissions-Policy all set.</div></div>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>WordPress auto-updates enabled</strong> — Core security patches applied automatically.</div></div>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>File editing disabled</strong> — DISALLOW_FILE_EDIT is set in wp-config.php.</div></div>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>Debug mode off in production</strong> — WP_DEBUG and display_errors are disabled.</div></div>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>Strong administrator credentials</strong> — No default or weak passwords detected.</div></div>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>Login URL hidden</strong> — Custom login path protects against automated brute-force attempts.</div></div>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>Two-factor authentication active</strong> — All administrator accounts protected with 2FA.</div></div>' +
                    '<div class="cs-audit-good-item"><span class="cs-audit-good-check">✓</span><div><strong>XML-RPC disabled</strong> — Endpoint blocked to prevent credential-stuffing attacks.</div></div>' +
                    '</div>';
            }
            // Make all quick fixes show as fixed
            document.querySelectorAll('#cs-quick-fixes-list [data-fix-id]').forEach(function(btn) {
                var wrap = btn.closest('div[style*="flex-shrink"]') || btn.parentElement;
                if (wrap) wrap.innerHTML = '<span style="font-size:12px;color:#16a34a;font-weight:600;">Fixed \u2713</span>';
            });
            // Hide AI settings form (scope to security panel — .cs-sec-settings appears in other panels too)
            var panel = document.getElementById('cs-panel-security');
            if (panel) {
                var ctrl = panel.querySelector('.cs-sec-settings');
                if (ctrl) ctrl.style.display = 'none';
                var intro = panel.querySelector('.cs-tab-intro');
                if (intro) intro.style.display = 'none';
            }
            // Also scrub any API key inputs that might be visible anywhere
            document.querySelectorAll('input[id*="key"], input[id*="api"], input[type="password"]').forEach(function(el) {
                el.value = '';
            });
          }
        },
        { id: 'code-block', label: 'Code Block',             file: 'panel-code-block.png',  tabSelector: 'a[href*="tab=migrate"]', elementSelector: '#cs-panel-code-settings',
          altText: 'WordPress syntax-highlighted code block settings — 190 languages, 14 themes, no CDN, completely free' },
        { id: 'migrator',   label: 'Code Block Migrator',   file: 'panel-migrator.png',    tabSelector: 'a[href*="tab=migrate"]', elementSelector: '#cs-panel-migrator',
          altText: 'WordPress code block migrator — batch convert from Enlighter, SyntaxHighlighter, and other plugins' },
        { id: 'sql-tool',   label: 'SQL Query Tool',        file: 'panel-sql-tool.png',    tabSelector: 'a[href*="tab=sql"]',     elementSelector: '#cs-panel-sql',
          altText: 'WordPress read-only SQL query tool — safe database inspection inside wp-admin without phpMyAdmin' },
        { id: 'server-logs',label: 'Server Logs',           file: 'panel-server-logs.png', tabSelector: 'a[href*="tab=logs"]',    elementSelector: '#cs-panel-logs',
          altText: 'WordPress server log viewer — PHP error logs, debug logs, and web server logs without SSH access' },
    ],

    docs: {
        'hide-login': `
<div style="background:#f0fdf4;border-left:4px solid #16a34a;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">🔐 Stop Bots Before They Even See Your Login Page</h2>
<p style="margin:0 0 10px;color:#374151;">Every WordPress site on the internet is hammered by bots probing <code>/wp-login.php</code> every hour. These aren't targeted attacks — they're automated scanners running 24/7, trying thousands of password combinations. If they can reach your login page, they will keep trying. Hide Login URL makes your login page invisible to them: bots get a 404 and move on. No login form means no brute-force attack.</p>
<p style="margin:0;color:#374151;"><strong>Competing plugins charge $49–$99/year</strong> for this feature (iThemes Security Pro, All-in-One Security Premium). CloudScale includes it free, bundled with 2FA and Passkeys in the same plugin — no juggling three separate security plugins.</p>
</div>
<p>When enabled, a WordPress <code>init</code> hook (priority 1) intercepts requests to your chosen secret slug and serves the login form transparently — no redirect, no URL change, the form just loads. Direct requests to <code>/wp-login.php</code> return a clean 404. All internal WordPress links (password reset emails, logout URLs) automatically update to use your secret URL.</p>
<p><strong>Setup takes 30 seconds:</strong></p>
<ol>
<li>Toggle <em>Enable Hide Login</em> on.</li>
<li>Enter your secret slug (e.g. <code>team-portal</code>). Avoid <code>login</code>, <code>admin</code>, or <code>dashboard</code> — bots know those too.</li>
<li>Click <em>Save</em> and bookmark the new URL immediately.</li>
<li>If you ever lose the URL: <code>wp option get csdt_devtools_login_slug</code> via WP-CLI will retrieve it.</li>
</ol>
<p><strong>What stays unaffected:</strong> WP-CLI, XML-RPC, REST API, and WP Cron all bypass the login URL check entirely — nothing breaks.</p>`,

        '2fa': `
<div style="background:#fdf4ff;border-left:4px solid #9333ea;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">🔑 A Stolen Password Should Never Be Enough to Break In</h2>
<p style="margin:0 0 10px;color:#374151;">Passwords get leaked in data breaches, reused across sites, and phished out of users. Two-factor authentication (2FA) means an attacker who has your password still cannot log in — they also need physical access to your phone, email inbox, or hardware key. For WordPress admins, 2FA is the single most effective account protection you can add.</p>
<p style="margin:0;color:#374151;"><strong>WP 2FA Pro charges $79/year.</strong> Wordfence Premium (which includes 2FA) charges $119/year. CloudScale gives you email OTP, TOTP authenticator apps, and Passkeys — all three methods — completely free, in the same plugin you use for everything else.</p>
</div>
<p><strong>Three methods, one plugin:</strong></p>
<ul>
<li><strong>Email OTP</strong> — a 6-digit code sent to the user's email after login. No app needed. Code expires in 10 minutes. Best for non-technical users.</li>
<li><strong>Authenticator app (TOTP)</strong> — standard RFC 6238 algorithm. Works with Google Authenticator, Authy, 1Password, Bitwarden, or any TOTP app. Generates a new code every 30 seconds, works offline, immune to email interception.</li>
<li><strong>Passkey (WebAuthn)</strong> — replaces the code prompt with Face ID, Touch ID, Windows Hello, or a hardware security key. The fastest and most phishing-resistant option available. See the Passkeys section below.</li>
</ul>
<p><strong>Admin enforcement:</strong> Enable <em>Force 2FA for administrators</em> and any admin who hasn't configured their second factor gets blocked at the dashboard until they do — they can't skip it. A configurable grace period lets existing admins set up 2FA before enforcement kicks in.</p>
<p><strong>Brute-Force Protection</strong> is built into the same tab: lock accounts after N failed attempts (default: 5 attempts, 5-minute lockout). Both thresholds are yours to configure.</p>
<p><strong>Session Duration</strong> lets you override WordPress's default session length. When set, persistent cookies keep sessions alive across browser closes — useful for teams who find constant re-authentication disruptive.</p>`,

        'passkeys': `
<div style="background:#fff7ed;border-left:4px solid #ea580c;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">🪪 The Most Secure WordPress Login Method Available — and It's Free</h2>
<p style="margin:0 0 10px;color:#374151;">Even TOTP codes can be phished: a fake login page captures your password and OTP code in real time and replays them instantly. Passkeys cannot be phished this way. They are cryptographically bound to your site's exact domain — a fake domain simply cannot trigger your passkey. This is the authentication standard used by Apple, Google, and Microsoft for their own products, now available for your WordPress site at no cost.</p>
<p style="margin:0;color:#374151;"><strong>Most WordPress passkey plugins don't exist as free products.</strong> The handful that do charge $50–$100/year for a commercial FIDO2 implementation. CloudScale's passkey support is a full WebAuthn/FIDO2 implementation, open-source, and completely free.</p>
</div>
<p><strong>How it works:</strong> When you register a passkey, your device generates a public/private key pair. The private key never leaves your device. At login, your server sends a random challenge; your device signs it with the private key; the server verifies the signature against your stored public key. No secret is ever transmitted over the network.</p>
<p><strong>Supported authenticators:</strong> Face ID (iPhone, iPad, Mac), Touch ID (MacBook), Windows Hello (fingerprint, face, PIN), Android biometrics, and hardware security keys (YubiKey 5 series, Google Titan, etc.).</p>
<p><strong>Registering a passkey:</strong></p>
<ol>
<li>Click <em>+ Add Passkey</em> and give it a label (e.g. "iPhone 16 Pro", "YubiKey").</li>
<li>Click <em>Register</em> — your browser prompts for biometric confirmation or hardware key tap.</li>
<li>The passkey is saved to your account. Register one per device you log in from.</li>
</ol>
<p><strong>Browser support:</strong> Chrome 108+, Safari 16+, Edge 108+, Firefox 122+. If a browser doesn't support passkeys, the login flow falls back to email OTP automatically — no user is ever locked out.</p>`,

        'security': `
<div style="background:#fff5f5;border-left:4px solid #c0392b;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">🛡️ A Security Consultant in Your WordPress Dashboard — for Free</h2>
<p style="margin:0 0 10px;color:#374151;">A professional WordPress security audit costs $500–$5,000 and takes days to schedule. Generic security checklists from free plugins tell you what to check but not what it means for your specific site. CloudScale connects directly to the world's most capable AI models — <strong>Anthropic Claude 4</strong> and <strong>Google Gemini 2.5 Pro</strong> — analyses your entire WordPress installation, and delivers a scored, prioritised report with specific remediation steps in under 60 seconds. The same class of AI used by enterprise security teams, working on your site.</p>
<p style="margin:0;color:#374151;"><strong>Wordfence Premium costs $119/year. Sucuri costs $199/year. WPScan costs $25–$75/month.</strong> These tools run signature-based scans — they match known patterns against a database. CloudScale's AI audit understands context: it reads your configuration, your plugins, and your code and reasons about what's actually risky for your specific setup. You supply your own API key (free Gemini tier available, no credit card). The plugin itself costs nothing.</p>
</div>
<p><strong>Standard Scan</strong> audits WordPress core settings, active plugins and themes, user accounts, file permissions, and wp-config.php hardening constants. The AI scores each finding Critical / High / Medium / Low and gives you specific steps to fix it — not generic advice, but instructions for your exact configuration.</p>
<p><strong>Deep Dive Scan</strong> adds live probes your site's security team would run manually:</p>
<ul>
<li><strong>Static PHP code analysis</strong> of every active plugin — flags <code>eval()</code>, shell execution functions, code obfuscation, and suspicious patterns that malware authors use</li>
<li><strong>Live HTTP probes</strong> — open directory listing, weak TLS (SSLv3, TLS 1.0), CORS misconfigurations, server version header leaks</li>
<li><strong>DNS security checks</strong> — SPF strictness, DMARC policy strength, DKIM probes (skipped entirely for domains with no MX records — no false positives for non-email sites)</li>
<li><strong>CSP quality analysis</strong> — flags <code>unsafe-inline</code>, <code>unsafe-eval</code>, wildcard sources, and missing directives in your Content Security Policy</li>
<li><strong>AI Code Triage</strong> — the 10 highest-risk static findings are sent to the AI with surrounding code context; each is classified as Confirmed Threat / False Positive / Needs Review before the main audit runs</li>
</ul>
<p><strong>Quick Fixes</strong> appear above the scan results — one-click remediations for the most common misconfigurations. Each shows green (done) or amber (needs attention) at a glance.</p>
<p><strong>Scheduled Scans</strong> run automatically on a daily or weekly schedule with email alerts when new issues are found — so you know about problems before your users or Google do.</p>
<p><strong>AI Providers — your choice:</strong></p>
<ul>
<li><strong>Anthropic Claude</strong> (recommended for depth) — <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com/settings/keys</a>. Models: claude-sonnet-4-6 (fast) · claude-opus-4-7 (most thorough)</li>
<li><strong>Google Gemini</strong> (free tier, no credit card) — <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">aistudio.google.com/app/apikey</a>. Models: gemini-2.0-flash (free) · gemini-2.5-pro (most capable)</li>
</ul>`,

        'code-block': `
<div style="background:#f0f9ff;border-left:4px solid #0e6b8f;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">💻 Beautiful Code Blocks — Without Paying $50/Year or Slowing Your Site Down</h2>
<p style="margin:0 0 10px;color:#374151;">Most WordPress code highlighting plugins have one of two problems: they load scripts from an external CDN (adding 100–300ms to every page load, hurting your Core Web Vitals score, and breaking if the CDN goes down), or they charge $30–$50/year for features that should be free. <strong>Enlighter</strong> loads from their own servers. <strong>SyntaxHighlighter Evolved</strong> loads from WordPress.com's CDN. <strong>Prismatic</strong> charges $29/year for a theme switcher.</p>
<p style="margin:0;color:#374151;">CloudScale bundles highlight.js 11.11.1 <strong>entirely on your own server</strong> — zero external HTTP requests, zero CDN dependency, zero annual fee. Your pages load faster, your cache hit rates improve, and your syntax highlighting works even when third-party services are down.</p>
</div>
<p>The Code Block is a native Gutenberg block (<code>cloudscale/code</code>) and a <code>[cs_code]</code> shortcode. It works everywhere WordPress renders content.</p>
<p><strong>190+ languages with auto-detection.</strong> CloudScale detects the language automatically from the code content. Override it manually in the block sidebar when detection picks the wrong one.</p>
<p><strong>14 professional colour themes</strong> — Atom One Dark/Light, GitHub, Monokai, Nord, Dracula, Tokyo Night, VS Code, VS 2015, Stack Overflow, Night Owl, Gruvbox, Solarized, Panda, Shades of Purple. A toggle button switches between dark and light variants, storing the preference in <code>localStorage</code> so it follows the reader across pages.</p>
<p><strong>Copy to clipboard</strong> — one click. Line numbers are rendered via CSS counter so they are never included when someone copies the code.</p>
<p><strong>INI/TOML auto-repair</strong> — Gutenberg breaks INI and TOML files at bare <code>[section]</code> headers by treating them as block delimiters. CloudScale detects this silently and reassembles the fragments, showing a brief toast so you know it happened.</p>`,

        'migrator': `
<div style="background:#fefce8;border-left:4px solid #ca8a04;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">🔄 Switch Plugins Without Touching 100 Posts by Hand</h2>
<p style="margin:0 0 10px;color:#374151;">Switching code highlighting plugins normally means opening every post, finding the old block or shortcode, deleting it, re-inserting the new one, and republishing — for every single post on your site. On a blog with 100 posts, that's hours of tedious work with plenty of room for mistakes.</p>
<p style="margin:0;color:#374151;">No other free WordPress plugin offers automated batch migration from multiple source formats with a preview step before committing. CloudScale does it in three clicks: Scan → Preview → Migrate All.</p>
</div>
<p>The Migrator scans your database for posts and pages using any supported legacy format, shows you a precise before/after diff, and converts them all to CloudScale blocks in a single operation.</p>
<p><strong>Supported source formats:</strong></p>
<ul>
<li>WordPress core <code>&lt;!-- wp:code --&gt;</code> and <code>&lt;!-- wp:preformatted --&gt;</code> blocks</li>
<li>Code Syntax Block plugin (<code>&lt;!-- wp:code-syntax-block/code --&gt;</code>)</li>
<li>Legacy shortcodes: <code>[code]</code>, <code>[sourcecode]</code>, and common variants</li>
</ul>
<p><strong>Workflow:</strong></p>
<ol>
<li><strong>Scan</strong> — finds every post and page with supported blocks. Shows title, status, date, and block count.</li>
<li><strong>Preview</strong> — shows the exact before/after content diff per post. Nothing is written to the database at this stage.</li>
<li><strong>Migrate</strong> — convert one post at a time, or migrate everything in a single click.</li>
</ol>
<p>⚠ The migrator writes directly to <code>post_content</code>. Always take a database backup first — use the CloudScale Backup &amp; Restore plugin for a one-click snapshot before you begin.</p>`,

        'sql-tool': `
<div style="background:#f8fafc;border-left:4px solid #64748b;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">🗄️ Query Your Live Database Safely — No phpMyAdmin, No SSH</h2>
<p style="margin:0 0 10px;color:#374151;">phpMyAdmin is powerful but complex to install securely, and leaving it exposed is a serious vulnerability. Adminer is a single PHP file that attackers actively scan for. Desktop tools like TablePlus require you to open a database port to your laptop. For WordPress administrators who just need to check table sizes, find orphaned data, or troubleshoot a slow query, those options are overkill — or a security liability.</p>
<p style="margin:0;color:#374151;">CloudScale's SQL tool lives inside wp-admin, accessible only to administrators, and is <strong>read-only by design</strong> — it is architecturally impossible to delete or modify data through it. No separate installation, no open ports, no exposed files.</p>
</div>
<p><strong>Read-only enforcement:</strong> Every query passes through <code>is_safe_query()</code> which strips comments, rejects semicolons (blocking statement stacking), blocks <code>INTO OUTFILE</code> and <code>LOAD_FILE</code>, and only permits <code>SELECT</code>, <code>SHOW</code>, <code>DESCRIBE</code>, <code>EXPLAIN</code>. Even if an administrator tries to run a destructive query, it is rejected before reaching the database.</p>
<p><strong>14 built-in quick queries</strong> cover the most common diagnostic tasks without writing a single line of SQL:</p>
<ul>
<li><em>Health &amp; Diagnostics</em> — database status, site options, table sizes and row counts</li>
<li><em>Content Summary</em> — posts by type and status, latest published content</li>
<li><em>Bloat &amp; Cleanup</em> — orphaned postmeta, expired transients, revisions, largest autoloaded options (the most common cause of slow WordPress admin)</li>
<li><em>URL &amp; Migration Helpers</em> — HTTP references (for HTTP→HTTPS migrations), posts with old IP references, posts missing meta descriptions</li>
</ul>
<p><strong>Keyboard shortcuts:</strong> <kbd>Enter</kbd> or <kbd>Ctrl+Enter</kbd> runs the query. <kbd>Shift+Enter</kbd> inserts a newline for multi-line queries.</p>`,

        'server-logs': `
<div style="background:#f0fdf4;border-left:4px solid #15803d;padding:18px 22px;border-radius:0 8px 8px 0;margin-bottom:24px;">
<h2 style="margin:0 0 8px;font-size:1.25em;color:#0f172a;background:transparent!important;padding:0!important;border:none!important;">📋 Read Your Server Logs Without Leaving WordPress</h2>
<p style="margin:0 0 10px;color:#374151;">When something breaks on a WordPress site, the answer is almost always in a log file. But accessing logs normally means SSH access (which many hosting plans don't provide), navigating a cPanel file manager, or asking your hosting provider to email you a file. For agency developers, that means waiting. For site owners on shared hosting, that means never seeing the logs at all.</p>
<p style="margin:0;color:#374151;"><strong>Query Monitor</strong> shows database queries and hooks but not server-level PHP or Nginx/Apache logs. <strong>Debug Bar</strong> only surfaces WP_DEBUG output. Neither replaces direct log access. CloudScale gives you the actual log files — PHP errors, WordPress debug output, and web server logs — in a clean, searchable interface inside wp-admin, with no SSH required.</p>
</div>
<p><strong>All your log sources in one place:</strong> The source picker lists every available log file with a live status indicator (readable, not found, permission denied, or empty). Switch between PHP error log, WordPress debug log, and web server access/error logs with a single click.</p>
<p><strong>Live search</strong> filters entries as you type with highlighted matches — essential for finding a specific error in a log with thousands of lines.</p>
<p><strong>Severity filter</strong> narrows results to Emergency, Alert, Critical, Error, Warning, Notice, Info, or Debug. Cuts through noise on busy production sites where Info and Debug lines dominate.</p>
<p><strong>Auto-refresh tail mode</strong> polls for new entries every 30 seconds. Reproduce a bug in one browser tab while watching the log update in real time in another — the fastest way to trace an intermittent error.</p>
<p><strong>Custom log paths</strong> — add any file path (Nginx error log, a custom application log, a cron output file). Paths persist across sessions.</p>
<p><strong>One-click PHP error logging setup</strong> — if PHP error logging isn't configured on the server, a button writes the required <code>php.ini</code> directives automatically. No server configuration knowledge required.</p>`,
    },
}).catch(err => { console.error('ERROR:', err.message); process.exit(1); });
