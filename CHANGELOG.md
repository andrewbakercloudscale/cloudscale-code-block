# Changelog

All notable changes to CloudScale Code Block are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
