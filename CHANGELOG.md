# Changelog

All notable changes to CloudScale Code Block are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [1.8.120] - 2026-04-11

### Fixed
- Code Block editor: raw paste transform now detects markdown fenced blocks pasted as `<p>` elements (plain-text clipboard format from terminals and text editors) and converts them to a CloudScale code block with the correct language set — previously the fence markers became stray paragraphs

## [1.8.119] - 2026-04-11

### Added
- Thumbnails: Diagnose button on each flagged post row — checks meta state (both old `_cs_social_formats` and new `_csdt_social_formats` keys), file existence on disk for each platform, URL reachability per crawler UA, and og:image seen by each crawler
- Thumbnails: URL checker results — Copy Results to Clipboard button
- Thumbnails: Crawler Access Test merged into URL checker results; removed from separate Cloudflare Setup panel

### Fixed
- Thumbnails: `output_crawler_og_image()` now falls back to legacy `_cs_social_formats` meta key so posts published before the cs_ → csdt_ rename still serve the correct og:image
- Thumbnails: optimum image size targets reduced from 900 KB → 400 KB for Facebook, Twitter, LinkedIn, and Instagram
- Admin CSS: section header layout — title and hint text no longer run together; hint is right-aligned via `flex: 1; margin-left: auto`

### Changed
- Code Block editor: Paste button (and Ctrl+V into the textarea) now detects markdown fenced code blocks — extracts language from the fence header (handles aliases: `sh`/`shell`/`zsh` → bash, `py` → python, `js` → javascript, etc.) and strips fence markers so only the code content is stored

## [1.8.114] - 2026-04-10

### Fixed
- Explain modals: description content now renders formatted HTML — inline `<code>` tokens styled with dark background and amber text, `<strong>` / `<em>` emphasis, and `<ul>/<li>` bullet lists
- Explain modals: all plain-text `desc` items converted to `html` format with `<code>` markup for commands, file paths, query types, and technical values
- Expand `$explain_kses` allowlist: added `ul`, `ol`, `li`, `p`, `h4` so structured content can be used in item descriptions

## [1.8.111] - 2026-04-11

### Added
- Thumbnails: "Fix All Posts on Site" batch button — processes every published post in groups of 10 with live progress counter
- Thumbnails: Crawler UA detection — wp_head priority-1 og:image output so each platform receives its correctly-sized image

### Fixed
- Thumbnails: PNG and WebP featured images now converted to JPEG during social format generation so lossy compression can meet platform size targets
- Thumbnails: WebP source images were silently skipped by Fix/Fix All — webp now in supported formats list
- CS Monitor: performance data was injected after footer scripts ran (priority 9999), so the panel always showed empty data; data now injected at priority 15 via wp_add_inline_script() before scripts print at priority 20
- PCP: email-verified modal countdown script moved from inline PHP `<script>` to wp_add_inline_script()
- PCP: Thumbnails tab inline `<style>` block moved to wp_add_inline_style() on the cs-admin-tabs handle
- PCP: wp_delete_file() replaces unlink() for image backup temp-file cleanup
- PCP: REST hi-score endpoints use explicit anonymous permission callbacks instead of `__return_true` string
- PCP: Missing wp_unslash() before sanitize_text_field() on $wp->query_vars values in perf monitor

### Security
- SSRF protection on admin URL-check endpoints — private/reserved IP ranges rejected before outbound HTTP
- Cloudflare cache-purge endpoint validates supplied URL belongs to the current site
- DOM XSS fixed in email 2FA enable flow — innerHTML concatenation replaced with safe DOM + textContent
- TOTP secret cleared from DOM immediately after successful activation

## [1.8.107] - 2026-04-10

### Fixed
- Session cookie: hook moved from login_form_login (display-only, never fires on POST) to login_init so persistent-cookie flag is set before WordPress processes credentials

## [1.8.89] - 2026-04-10

### Added
- Login: brute-force protection — configurable per-account lockout after N failed attempts (default 5 attempts, 5-minute lock) with admin UI
- Thumbnails tab: Social Preview Diagnostics — URL checker (9-point OG/image diagnostic), recent posts auto-scan, Cloudflare WAF setup guide, crawler UA tester, cache purge, Media Library auditor with one-click recompress

### Fixed
- Login: session persistence — auth cookie is now persistent (not a session cookie) when a custom session duration is set

## [1.7.48] - 2026-04-05

### Added
- Code Block Settings: checkbox to show/hide the CS Monitor performance panel (saved via AJAX alongside theme settings; hides the panel for all users when unchecked)

## [1.7.36] - 2026-04-05

### Added
- CS Monitor: **Assets tab** — all enqueued JS and CSS files, attributed to plugin/theme/wp-core, with type filter and search
- CS Monitor: **Hooks tab** — top 50 WordPress hooks by cumulative execution time; sortable by count, total, or max time; search filter
- CS Monitor: **Object cache stats** card in Summary — hit rate, hit/miss counts, persistent cache detection (Redis/Memcache)
- CS Monitor: **Slowest Hooks** section in Summary — top 8 hooks by total time with bar chart
- CS Monitor: **Assets** summary card showing JS + CSS counts

## [1.7.25] - 2026-03-23

### Fixed
- `editor.js`: `onPasteCode` now handles both the toolbar Paste button and the browser's native paste event (Ctrl+V) — previously Ctrl+V completely bypassed `decodeClipboardText()`, meaning escape-sequence decoding never ran on keyboard pastes; textarea now has `onPaste` handler that calls `event.preventDefault()` and reads from `event.clipboardData` synchronously

## [1.7.24] - 2026-03-23

### Fixed
- `editor.js`: `decodeClipboardText()` replaced broken `JSON.parse` approach with direct regex-based decoding — `JSON.parse` was silently failing when `\u0022` decoded to `"` inside the JSON string, causing `\n` to be stored as literal `n` and `\u0022` to be stored as literal `u0022` in the database; now handles `\n`, `\t`, `\r`, `\\`, `\uXXXX`, and bare `uXXXX` correctly
- `editor.js`: Added `(attributes.content || '')` null guard on `.split('\n')` row calculation to prevent TypeError on null content
- `cs-code-block.php`: Removed `opacity:.5;pointer-events:none` inline styles from the Migrate All button — inline styles were never cleared when the button was re-enabled via JS, leaving it visually disabled
- `assets/cs-code-migrate.css`: Added `.cs-btn-orange:disabled` and `.cs-btn-primary:disabled` CSS rules to handle disabled state declaratively
- `cs-code-block.php`: Fixed `printf()` in `render_migrate_panel()` — outer format string was wrapped in `esc_html__()` while `%s` was replaced with raw HTML; outer string now uses `__()` with a `phpcs:ignore` annotation
- `blocks/code/block.json`: Synced `version` field from `1.7.18` to `1.7.23`

## [1.7.21] - 2026-03-22

### Fixed
- `readme.txt`: Reduced tags from 8 to 5 (WordPress.org enforces a maximum of 5)
- `readme.txt`: Shortened short description to 141 characters (WordPress.org maximum is 150)
- `readme.txt`: Updated `Requires at least` from 5.8 to 6.0 to match plugin header
- `cs-code-block.php`: Added `phpcs:ignore InputNotSanitized` on `$_POST['sql']` — validated via `is_safe_query()`, not a standard `sanitize_*()` call
- `cs-code-block.php`: Added `phpcs:ignore InputNotSanitized` on `$_POST['post_id']` reads in `ajax_preview()` and `ajax_migrate_single()` — sanitised via `(int)` cast
- `cs-convert.js`: Removed `onclick` attribute from JS-generated toast button; replaced with `addEventListener`
- `cs-code-block.js`: Added `.catch()` to `copyToClipboard()` promise chain — logs clipboard API rejection via `console.error()`
- `cs-admin-settings.js`: Added `console.error()` to settings save `.catch()` block

## [1.7.18] - 2026-03-13

### Security
- Hardened `is_safe_query()`: SQL queries containing semicolons are now rejected, preventing statement stacking
- Removed `$_REQUEST` fallback in SQL AJAX handler — only `$_POST` accepted
- Added `phpcs:ignore` annotations with rationale for all intentional `$wpdb` direct-query patterns

### Fixed
- Removed echoed `<style>` block from admin tools page — all styles now served via enqueued `cs-admin-tabs.css`
- Extracted inline `<script>` blocks from settings and SQL panels into enqueued JS files (`cs-admin-settings.js`, `cs-sql-editor.js`)
- Replaced dynamic `document.head.appendChild(style)` injection in block editor with `wp_add_inline_style()`
- Removed `console.warn()` and `console.log()` debug calls from `cs-convert.js`

### Added
- `uninstall.php` — removes `cs_code_default_theme` and `cs_code_theme_pair` options on plugin deletion
- `load_plugin_textdomain()` called on `init`; 48 user-facing strings wrapped with i18n functions across frontend and admin
- Full DocBlocks (`@since`, `@param`, `@return`) on all class methods and class definition
- `@package` and `@since` tags in plugin file header

### Changed
- `wp_ajax_cs_save_theme_setting` hook consolidated into `init()` alongside all other AJAX hooks
- `date()` replaced with `wp_date()` in migration scan to respect site timezone
- `block.json` version synced to plugin version

## [1.7.17]

### Added
- Copy button now shows a "Copy" label alongside the clipboard icon

### Changed
- CSS refactored for copy button styling

## [1.7.16]

### Fixed
- `build_migrate_block()` JSON encoding corrected: removed `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` flags that caused `<`, `>`, and quote characters to be stored as literal bytes, corrupting block attributes on subsequent Gutenberg saves

### Changed
- All enqueued assets now use `filemtime()` for cache busting
- Block editor toolbar improvements

## [1.7.15]

### Fixed
- Split `decode_migrated_content()` into two independent passes so the bare-`\n` newline fix always runs regardless of whether unicode escapes were already decoded in the database

## [1.7.14]

### Fixed
- `decode_migrated_content()` in `render_block()` decodes unicode escapes and bare newline separators left by the v1 migration bug, fixing display of affected code blocks without requiring database changes

## [1.7.13]

### Fixed
- Code Block Migrator now uses safe JSON encoding so special characters like `<`, `>`, and quotes are stored as proper unicode escapes in block comment attributes, preventing content corruption on subsequent Gutenberg saves

## [1.7.8]

### Added
- Copy, Paste, and Clear buttons to the block editor toolbar

## [1.7.6]

### Fixed
- White toolbar text for improved contrast
- UNDEFINED language badge no longer displayed
- Powered by CloudScale link added to block toolbar

## [1.7.3]

### Changed
- Admin CSS embedded inline for reliable rendering across all WordPress configurations
- Admin UI restyled to match the CloudScale plugin family: navy gradient banner, dark tab bar with orange active indicator, white card panels with coloured gradient section headers

## [1.7.0]

### Added
- 14 popular syntax colour themes: Atom One, GitHub, Monokai, Nord, Dracula, Tokyo Night, VS 2015/VS Code, Stack Overflow, Night Owl, Gruvbox, Solarized, Panda, Tomorrow Night, Shades of Purple
- Colour Theme selector in the settings panel
- HCL/Terraform and TOML to the language selector
- 14 new SQL quick queries organised into four groups: Health and Diagnostics, Content Summary, Bloat and Cleanup Checks, URL and Migration Helpers

### Changed
- Each theme loads its dark and light variant from the highlight.js CDN
- Toggle button switches between the dark and light variant of the chosen theme
- Frontend CSS refactored to use CSS custom properties for theme-agnostic styling
- Theme backgrounds and toolbar colours adapt automatically to the selected theme
- Enter key now runs the SQL query; Shift+Enter inserts a newline

## [1.6.0]

### Added
- Merged CloudScale SQL Command plugin into CloudScale Code Block
- Combined Tools page at Tools > CloudScale Code and SQL with tabbed interface
- AJAX save for theme settings (no page reload required)

### Removed
- Separate Settings > CloudScale Code Block page
- Separate Tools > CloudScale SQL page

## [1.5.0]

### Added
- Code Block Migrator tool to batch-convert legacy WordPress code blocks
- Auto-convert toast in the block editor for `core/code` and `core/preformatted` blocks
- Transform support from `core/code` and `core/preformatted`

## [1.1.0]

### Fixed
- Block now spans full content width in both editor and frontend
- Dark mode is now the proper default with Atom One Dark syntax colours
- Code no longer wraps; horizontal scroll on overflow
- Editor style properly registered via `block.json` `editorStyle`

### Added
- Alignment support (wide, full) in block toolbar

## [1.0.0]

### Added
- Initial release: Gutenberg block and `[cs_code]` shortcode for syntax-highlighted code
- Auto language detection for 30+ languages via highlight.js
- One-click clipboard copy button
- Dark/light mode toggle per block with site-wide default
- Language badge auto-detection
- Optional line numbers toggle
- Custom title/filename label per block
