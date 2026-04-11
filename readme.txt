=== CloudScale DevTools ===
Contributors: andrewbaker
Tags: code block, syntax highlighting, gutenberg block, dark mode, highlight.js
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.8.120
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Syntax highlighted code block with 14 color themes, auto language detection, clipboard copy, dark/light toggle, migrator, and SQL query tool.

== Description ==

CloudScale DevTools is a lightweight, zero dependency Gutenberg block plugin that renders beautifully syntax highlighted code on your WordPress site using highlight.js. It includes a built in code block migrator to convert legacy WordPress code blocks in bulk, and a read only SQL query tool for database diagnostics.

= Features =

* Gutenberg block and [cs_code] shortcode for syntax highlighted code
* 14 popular color themes loaded from the highlight.js CDN: Atom One, GitHub, Monokai, Nord, Dracula, Tokyo Night, VS 2015 / VS Code, Stack Overflow, Night Owl, Gruvbox, Solarized, Panda, Tomorrow Night, Shades of Purple
* Dark and light mode toggle per block with site wide default
* Auto language detection for 30+ languages including Bash, Python, Java, JavaScript, TypeScript, Go, Rust, PHP, SQL, and more
* One click clipboard copy button
* Optional line numbers toggle
* Language badge auto detection
* Custom title / filename label per block
* Code Block Migrator: scan posts for legacy core/code blocks and batch convert them
* SQL Command: read only query tool with quick queries for database health checks, content summaries, bloat diagnostics, table sizes, orphaned postmeta, expired transients, and URL migration helpers
* Admin UI styled to match the CloudScale plugin family

= Requirements =

* WordPress 5.8 or later
* PHP 7.4 or later
* MySQL 5.7 or MariaDB 10.3 or later (for SQL Command features)
* highlight.js 11.11.1 loaded from cdnjs CDN (no local files required)

== Installation ==

1. Upload the cs-code-block folder to /wp-content/plugins/
2. Activate the plugin through the Plugins menu in WordPress
3. Add a CloudScale DevTools block from the Gutenberg block inserter, or use the [cs_code] shortcode
4. Configure your preferred color theme and dark/light default under Tools > CloudScale DevTools

If you were previously using the standalone CloudScale SQL Command plugin, you can deactivate and delete it after activating this version. All SQL functionality is now built in.

== Frequently Asked Questions ==

= How do I change the syntax color theme? =

Go to Tools > CloudScale Code Block. On the Code Migrator tab you will see the Code Block Settings panel at the top. Select your preferred color theme from the dropdown and click Save Settings. The change applies to all code blocks site wide.

= Can visitors toggle between dark and light mode? =

Yes. Every code block has a sun/moon toggle button in its toolbar. The toggle switches between the dark and light variant of your chosen color theme. You set the site wide default (dark or light) in the settings, but visitors can override it per block.

= What languages are supported? =

The plugin uses highlight.js with auto detection. It supports over 30 languages out of the box including Bash, Python, Java, JavaScript, TypeScript, C, C++, C#, Go, Rust, Ruby, PHP, Swift, Kotlin, SQL, JSON, YAML, XML, HTML, CSS, SCSS, Markdown, Makefile, Dockerfile, Lua, Perl, R, INI, TOML, Diff, GraphQL, HCL/Terraform, Objective C, and VB.NET. You can also set the language explicitly per block in the Gutenberg sidebar.

= Is the SQL Command tool safe? =

Yes. The SQL Command tool only allows SELECT, SHOW, DESCRIBE, and EXPLAIN queries. All write operations (INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE, etc.) are blocked. It requires the manage_options capability so only administrators can access it.

= What are the quick queries in the SQL tool? =

The SQL tab includes 14 preset quick queries organized into four groups: Health and Diagnostics (database health check, site identity options, table sizes and rows), Content Summary (posts by type and status, site stats summary, latest published posts), Bloat and Cleanup Checks (orphaned postmeta, expired transients, revisions/drafts/trash, largest autoloaded options), and URL and Migration Helpers (HTTP references, posts with HTTP GUIDs, old IP references, posts missing meta descriptions).

= Can I run the query by pressing Enter? =

Yes. Press Enter to run the query. Use Shift+Enter to insert a newline. Ctrl+Enter also runs the query.

== Screenshots ==

1. Code block on the frontend with Atom One Dark theme, language badge, copy button, and dark/light toggle
2. Admin Tools page with the Code Block Settings panel and Code Block Migrator
3. SQL Command tab with query editor, results table, and quick query buttons
4. Gutenberg editor sidebar with language, title, and theme override options

== Changelog ==

= 1.8.118 =
* Fixed: Explain modals now render formatted HTML — inline code tokens styled with dark background, bold/italic emphasis, and bullet lists; all describe items converted from plain text to rich HTML markup

= 1.8.113 =
* Added: "Fix All Posts on Site" button — batch-processes every published post on the site in groups of 10, generating platform-specific social format images with live progress counter (e.g. "Fixing 45 / 320")
* Added: Crawler UA detection — `wp_head` at priority 1 outputs platform-specific `og:image` meta tag before SEO plugins, so Facebook, X/Twitter, WhatsApp, LinkedIn, and Instagram each receive the correctly-sized image for their platform
* Fixed: PNG and WebP featured images now converted to JPEG during social format generation so lossy quality compression can reduce file sizes to platform targets (Fix button was producing oversized files for PNG sources)
* Fixed: WebP featured images were excluded from Fix / Fix all — added `webp` to the supported source formats list
* Fixed: Performance monitor data was injected after footer scripts ran, so the panel always showed empty data — data now injected at wp_footer/admin_footer priority 15, before scripts print at priority 20
* Fixed: Inline email-verified modal countdown script moved from PHP template to wp_add_inline_script() — eliminates echoed <script> tag
* Fixed: Thumbnails tab inline <style> block moved to wp_add_inline_style() — eliminates echoed <style> tag
* Fixed: wp_delete_file() now used in place of unlink() when cleaning up image backup temp files
* Fixed: REST hi-score endpoints changed from __return_true to explicit anonymous permission callbacks
* Fixed: Missing wp_unslash() before sanitize_text_field() on WP query vars in performance monitor
* Security: Added SSRF protection on admin URL-check endpoints — URLs that resolve to private/reserved IP ranges (localhost, RFC-1918, link-local) are now rejected before outbound HTTP requests are made
* Security: Cloudflare cache-purge endpoint now validates that the supplied URL belongs to the current site before calling the Cloudflare API
* Security: Fixed DOM XSS in email 2FA enable flow — AJAX response message was concatenated into innerHTML; replaced with safe DOM element creation + textContent
* Security: TOTP secret is now cleared from the DOM immediately after successful activation

= 1.8.107 =
* Fixed: session cookie hook was wrong — login_form_login is a display hook that never fires on a successful login POST; moved to login_init so the persistent-cookie flag is set before WordPress processes credentials

= 1.8.89 =
* Added: Brute-force protection — configurable per-account lockout after N failed login attempts (default 5 attempts, 5-minute lock), with admin UI to adjust both thresholds
* Fixed: Session persistence — login sessions now survive browser close when a custom session duration is set (auth cookie was a session cookie; now writes a persistent cookie)
* Added: Thumbnails tab — Social Preview Diagnostics with URL checker (9-point OG/image diagnostic), recent posts auto-scan, Cloudflare WAF setup guide + crawler UA tester + cache purge, and Media Library auditor with one-click recompress for oversized images

= 1.8.87 =
* Fixed: PDF button moved from cramped header toolbar into the Summary pane itself — blue "↓ Download PDF" button at top of Summary tab, always visible
* Fixed: ms precision — all timings now show 1 decimal place consistently (was 0–4dp depending on magnitude)

= 1.8.84 =
* Fixed: ms formatting reduced to 1 decimal place throughout (was showing 4dp e.g. "0.000ms")
* Added: CS Monitor Summary tab — Download PDF button generates a print-ready report with all Issues (including full Explain steps) + request timeline + site health
* Added: CS Monitor Issues — WordPress core update check (flags outdated WP with remediation steps)
* Added: CS Monitor Issues — MySQL/MariaDB EOL version detection with version-specific upgrade steps
* Added: CS Monitor Summary — "PHP Errors & Warnings" section shows up to 8 recent log entries inline (errors, warnings, deprecated notices)
* Added: CS Monitor Site Health — WordPress core, MySQL/MariaDB version badges

= 1.8.83 =
* Added: CS Monitor — disk space check (warning ≥85%, critical ≥95%) with SSH diagnostics in Explain
* Added: CS Monitor — PHP OPcache check: disabled warning, OOM restarts critical, low hit rate and memory pressure warnings
* Added: CS Monitor — uploads directory writable check
* Added: CS Monitor — PHP upload_max_filesize, post_max_size, max_execution_time checks
* Added: CS Monitor — stale .maintenance file detection (maintenance mode loop)
* Added: CS Monitor — siteurl/home URL mismatch detection (login redirect loop cause)
* Added: CS Monitor — missing rewrite rules detection (404 on pretty permalinks)
* Added: CS Monitor — wp-config.php world-readable permission check
* Added: CS Monitor — debug.log size check (warning ≥10MB, critical ≥100MB)
* Added: CS Monitor Issues tab — Copy All button copies every issue with full Explain remediation steps as plain text
* Added: CS Monitor Issues tab — summary badge count in toolbar
* Improved: CS Monitor Summary Site Health section now shows disk, OPcache, uploads, PHP limits, URL config, maintenance, rewrite rules, wp-config perms, debug.log, and load average rows

= 1.8.82 =
* Added: CS Monitor Issues tab now flags high memory usage (≥75% of memory_limit → warning, ≥90% → critical) with remediation steps
* Added: CS Monitor reads system load average (via sys_getloadavg) and CPU count (via /proc/cpuinfo) — flags high CPU load as warning/critical
* Added: CS Monitor raises a critical issue when XML-RPC is enabled and ≥5 failed logins/hour are detected (active XML-RPC brute force correlation)

= 1.8.56 =
* Changed: Admin page slug renamed from cloudscale-code-sql to cloudscale-devtools (URL is now tools.php?page=cloudscale-devtools); legacy URL redirects automatically
* Fixed: Help page slug and title updated to cloudscale-devtools-help / CloudScale DevTools: Online Help

= 1.7.57 =
* Added: Code Block Settings now includes a checkbox to show/hide the CS Monitor performance panel

= 1.7.47 =
* Added: CS Monitor — Assets tab showing all enqueued JS and CSS files with plugin attribution, type filter, and search
* Added: CS Monitor — Hooks tab showing top 50 hooks by cumulative time, with sortable columns and search
* Added: CS Monitor — Object cache stats card in Summary (hit rate, hits/misses, persistent cache detection)
* Added: CS Monitor — Slowest Hooks section in Summary showing top 8 hooks by total time
* Added: CS Monitor — Assets summary card showing JS/CSS counts

= 1.7.35 =
* Fixed: decodeClipboardText() in editor.js now correctly handles \n, \t, \r, \\, and \uXXXX escape sequences — previously JSON.parse failed silently when \u0022 decoded to " inside the string, storing literal n and u0022 in the database instead of newlines and double-quotes
* Fixed: null guard added to attributes.content.split() in editor.js to prevent a TypeError if content is null
* Fixed: migrateAllBtn inline opacity/pointer-events styles removed from PHP HTML — they were never cleared when the button was re-enabled; disabled state now handled via CSS :disabled selector
* Fixed: printf format string in render_migrate_panel() no longer uses esc_html__() on the outer string when HTML is injected via %s placeholder

= 1.7.20 =
* Security: is_safe_query() now rejects queries containing semicolons, preventing statement stacking
* Security: Removed $_REQUEST fallback in SQL AJAX handler
* Fixed: Echoed <style> block removed from admin page; inline <script> blocks extracted to enqueued JS files (PCP compliance)
* Fixed: Dynamic style injection in block editor replaced with wp_add_inline_style()
* Fixed: console.warn() and console.log() removed from cs-convert.js
* Added: uninstall.php removes plugin options on deletion
* Added: load_plugin_textdomain() on init; 48 strings wrapped with i18n functions
* Added: Full DocBlocks on all methods
* Changed: date() replaced with wp_date() in migration scan

= 1.7.17 =
* Added: Copy button now shows a "Copy" label alongside the clipboard icon
* Changed: CSS refactored for copy button styling

= 1.7.16 =
* Fixed: build_migrate_block() JSON encoding corrected; removed JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES flags that corrupted block attributes containing <, >, and quote characters
* Added: filemtime() cache busting for all enqueued assets
* Improved: block editor toolbar enhancements

= 1.7.15 =
* Fix: split decode_migrated_content() into two independent passes so the bare-n newline fix always runs regardless of whether unicode escapes were already decoded in the database.

= 1.7.14 =
* Fix: decode_migrated_content() in render_block() decodes unicode escapes and bare newline separators left by the v1 migration bug, fixing display of affected code blocks without requiring database changes.

= 1.7.13 =
* Fixed: Code Block Migrator now uses safe JSON encoding so special characters like <, >, and quotes are stored as proper unicode escapes in block comment attributes, preventing content corruption on subsequent Gutenberg saves


= 1.7.3 =
* Embedded admin CSS inline for reliable rendering across all WordPress configurations
* Admin UI now matches the CloudScale Page Views plugin design language
* Navy gradient banner, dark tab bar with orange active indicator, white card panels with colored gradient section headers

= 1.7.0 =
* Added 14 popular syntax color themes: Atom One, GitHub, Monokai, Nord, Dracula, Tokyo Night, VS 2015/VS Code, Stack Overflow, Night Owl, Gruvbox, Solarized, Panda, Tomorrow Night, Shades of Purple
* New Color Theme selector in the settings panel
* Each theme loads its dark and light variant from the highlight.js CDN
* Toggle button switches between the dark and light variant of the chosen theme
* Refactored frontend CSS to use CSS custom properties for theme agnostic styling
* Theme backgrounds and toolbar colours adapt automatically to the selected theme
* Added HCL/Terraform and TOML to the language selector
* Added 14 new SQL quick queries organized into four groups: Health and Diagnostics, Content Summary, Bloat and Cleanup Checks, URL and Migration Helpers
* Enter key now runs the SQL query (Shift+Enter for newline)

= 1.6.0 =
* Merged CloudScale SQL Command plugin into CloudScale Code Block
* Combined Tools page at Tools > CloudScale Code Block with tabbed interface
* Code Block Migrator and SQL Command are now tabs on the same page
* Moved Settings (default theme) as inline options on the Code Block Migrator tab
* Removed separate Settings > CloudScale Code Block page
* Removed separate Tools > CloudScale SQL page
* Added AJAX save for theme settings (no page reload required)

= 1.5.0 =
* Added Code Block Migrator tool
* Auto convert toast in editor for core code blocks
* Transform support from core/code and core/preformatted

= 1.1.0 =
* Fixed: Block now spans full content width in both editor and frontend
* Fixed: Dark mode is now the proper default with Atom One Dark syntax colors
* Fixed: Code no longer wraps; horizontal scroll on overflow
* Fixed: Editor style properly registered via block.json editorStyle
* Added: Alignment support (wide, full) in block toolbar

= 1.0.0 =
* Initial release

== External services ==

This plugin loads syntax highlighting scripts and stylesheets from the cdnjs CDN operated by Cloudflare, Inc.

* Service: cdnjs (https://cdnjs.cloudflare.com/)
* When: On every page that contains a code block (frontend and block editor).
* What is sent: Standard HTTP request headers including visitor IP address and user agent, as required by any CDN request. No site content or user data is transmitted by the plugin itself.
* Why: To serve the highlight.js library and theme stylesheets without bundling them locally.
* Cloudflare Privacy Policy: https://www.cloudflare.com/privacypolicy/
* Cloudflare Terms of Service: https://www.cloudflare.com/terms/

== Upgrade Notice ==

= 1.7.3 =
Admin UI restyled to match the CloudScale plugin family. If upgrading from a version before 1.6.0, you can deactivate the standalone CloudScale SQL Command plugin as its functionality is now built in.
