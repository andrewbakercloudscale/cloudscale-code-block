# WordPress Code Block with Syntax Highlighting

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue) ![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple) ![License](https://img.shields.io/badge/License-GPLv2-green) ![Version](https://img.shields.io/badge/Version-1.7.18-orange)

Proper syntax highlighted code blocks for WordPress. Automatic language detection, one click copy to clipboard, dark and light theme toggle, line numbers, full width responsive display. Works as both a Gutenberg block and a shortcode. Zero build step required. Completely free.

No webpack. No npm install. No node modules. No subscriptions. No upsells. Upload and activate.

> Full write up: [Building a Better Code Block for WordPress](https://andrewbaker.ninja/2026/02/27/building-a-better-code-block-for-wordpress-cloudscale-code-block-plugin/)

## Features

- **Syntax highlighting** via highlight.js 11.11.1 with 28 languages out of the box
- **Automatic language detection** with manual override per block
- **Copy to clipboard** button on every code block
- **Dark/light theme toggle** (Atom One Dark and Atom One Light)
- **Line numbers** for easy reference
- **Optional title bar** for filenames or descriptions
- **Gutenberg block** registered under the Formatting category
- **Shortcode** (`cs_code`) for classic editor or non Gutenberg contexts
- **On demand asset loading**: highlight.js and theme CSS only load on pages with code blocks
- **Server side rendering** via PHP callback, immune to Gutenberg markup validation errors

### Supported Languages

Bash, C, C++, C#, CSS, Diff, Docker, Go, GraphQL, HTML/XML, INI, Java, JavaScript, JSON, Kotlin, Makefile, Markdown, Nginx, PHP, PowerShell, Python, Ruby, Rust, SCSS, Shell, SQL, TypeScript, YAML.

## The Gutenberg Paste Problem

When you paste markdown with fenced code blocks, Gutenberg intercepts the paste and creates `core/code` blocks before any plugin can touch it. CloudScale detects this and shows a floating toast:

> 2 core code blocks found **Convert All to CloudScale**

One click converts every `core/code` and `core/preformatted` block in the post to CloudScale Code Blocks, preserving all code content. Paste your markdown, click Convert All, done.

## Bulk Migration

If you have existing posts using WordPress default code blocks or the Code Syntax Block plugin, the built in migrator handles bulk conversion.

Go to **Tools > Code Block Migrator**:

1. **Scan Posts** finds every post containing legacy code blocks
2. **Preview** shows side by side comparison of original and converted markup
3. **Migrate This Post** converts a single post, or **Migrate All Remaining** batch converts everything

The migrator handles three block types: `wp:code` (WordPress default), `wp:code-syntax-block/code` (Code Syntax Block plugin), and `wp:preformatted` (preformatted text blocks). Language hints are extracted from attributes and CSS classes. Code content is never modified.

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher

## Installation

1. Download the latest release zip from the [Releases](../../releases) page
2. In WordPress admin go to **Plugins > Add New > Upload Plugin**
3. Upload the zip file, click **Install Now**, then **Activate Plugin**
4. The block appears in the Gutenberg inserter under **Formatting**
5. Settings at **Settings > CloudScale Code**

### Upgrading

Deactivate > Delete > Upload zip > Activate.

## Configuration

**Default theme**: Set dark or light at **Settings > CloudScale Code**. Individual blocks can override via the sidebar inspector.

**Language**: Auto detected by default. Override per block with the language selector (28 languages).

**Title**: Optional title bar above the code block, useful for filenames.

## Technical Details

The plugin registers a single Gutenberg block (`cloudscale/code-block`) with a PHP render callback. Block data is stored as three attributes: `content` (raw code), `language` (optional), and `title` (optional). The block uses `save: function() { return null; }` so all rendering is server side, making it resilient to markup changes and avoiding the "unexpected or invalid content" validation error.

The auto convert watcher uses `wp.data.subscribe` to monitor the block store. Conversion calls `wp.data.dispatch('core/block-editor').replaceBlock()` to swap blocks while preserving content.

## License

GPLv2 or later. See [LICENSE](LICENSE) for the full text.

## Author

[Andrew Baker](https://andrewbaker.ninja/) - CIO at Capitec Bank, South Africa.
