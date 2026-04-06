# CloudScale DevTools

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple) ![License](https://img.shields.io/badge/License-GPLv2-green) ![Version](https://img.shields.io/badge/Version-1.8.42-orange)

A free WordPress developer toolkit. Everything runs on your server — no external APIs, no subscriptions, no upsells.

## Features

### Code Block
- Syntax highlighting via **highlight.js 11.11.1** with 190+ languages and auto-detection
- **14 colour themes**: Atom One, GitHub, Monokai, Nord, Dracula, Tokyo Night, VS 2015, VS Code, Stack Overflow, Night Owl, Gruvbox, Solarized, Panda, Shades of Purple
- Dark/light toggle per block, site-wide default in settings
- One-click copy to clipboard, optional line numbers, optional filename label
- Gutenberg block (`cloudscale/code`) and `[cs_code]` shortcode

### Code Block Migrator
- Bulk converts legacy `wp:code`, `wp:preformatted`, Code Syntax Block, and shortcode blocks to CloudScale format
- Scan → Preview (side-by-side diff) → Migrate single or all

### SQL Query Tool
- Run read-only `SELECT`, `SHOW`, `DESCRIBE`, and `EXPLAIN` queries from wp-admin
- 14 built-in quick queries: health diagnostics, content summary, bloat/cleanup, URL migration helpers
- Restricted to `manage_options` capability; all write operations blocked

### Hide Login URL
- Moves `/wp-login.php` to a secret slug of your choosing
- Bots probing the default URL get a 404; the real form is served transparently at the new address
- `wp_login_url()`, `logout_url()`, and `lostpassword_url()` filters updated automatically

### Two-Factor Authentication
- **Email OTP** — 6-digit code emailed after password login, expires in 10 minutes
- **TOTP** (RFC 6238) — Google Authenticator, Authy, 1Password, or any compatible app
- **Passkey** — biometric or hardware key (see below)
- Force 2FA for all administrators site-wide

### Passkeys (WebAuthn / FIDO2)
- Register Face ID, Touch ID, Windows Hello, or YubiKey as a 2FA second factor
- Private key never leaves the device; phishing-resistant by design
- Test button verifies each registered passkey without logging out

## Requirements

- WordPress 6.0+
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.3+

## Installation

1. Download the latest release zip from the [Releases](../../releases) page
2. In WordPress admin go to **Plugins > Add New > Upload Plugin**
3. Upload the zip, click **Install Now**, then **Activate**
4. Navigate to **Tools > 🌩️ CloudScale DevTools**

## License

GPLv2 or later. See [LICENSE](LICENSE) for the full text.

## Author

[Andrew Baker](https://andrewbaker.ninja/)
