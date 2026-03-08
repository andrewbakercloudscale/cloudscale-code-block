<?php
/**
 * Plugin Name: CloudScale Code Block
 * Plugin URI: https://your-wordpress-site.example.com
 * Description: Syntax highlighted code block with auto language detection, clipboard copy, dark/light mode toggle, code block migrator, and read only SQL query tool. Works as a Gutenberg block and as a [cs_code] shortcode.
 * Version: 1.7.16
 * Author: Andrew Baker
 * Author URI: https://your-wordpress-site.example.com
 * License: GPL v2 or later
 * Text Domain: cs-code-block
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CloudScale_Code_Block {

    const VERSION      = '1.7.16';
    const HLJS_VERSION = '11.11.1';
    const HLJS_CDN     = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/';
    const TOOLS_SLUG   = 'cloudscale-code-sql';

    /**
     * Available theme pairs. Each entry maps a slug to its dark and light CDN filenames,
     * display label, and background colours for the wrapper/toolbar.
     */
    public static function get_theme_registry(): array {
        return [
            'atom-one' => [
                'label'        => 'Atom One',
                'dark_css'     => 'atom-one-dark',
                'light_css'    => 'atom-one-light',
                'dark_bg'      => '#282c34',
                'dark_toolbar' => '#21252b',
                'light_bg'     => '#fafafa',
                'light_toolbar'=> '#e8eaed',
            ],
            'github' => [
                'label'        => 'GitHub',
                'dark_css'     => 'github-dark',
                'light_css'    => 'github',
                'dark_bg'      => '#24292e',
                'dark_toolbar' => '#1f2428',
                'light_bg'     => '#fff',
                'light_toolbar'=> '#f6f8fa',
            ],
            'monokai' => [
                'label'        => 'Monokai',
                'dark_css'     => 'monokai',
                'light_css'    => 'atom-one-light',
                'dark_bg'      => '#272822',
                'dark_toolbar' => '#1e1f1c',
                'light_bg'     => '#fafafa',
                'light_toolbar'=> '#e8eaed',
            ],
            'nord' => [
                'label'        => 'Nord',
                'dark_css'     => 'nord',
                'light_css'    => 'atom-one-light',
                'dark_bg'      => '#2e3440',
                'dark_toolbar' => '#272c36',
                'light_bg'     => '#fafafa',
                'light_toolbar'=> '#e8eaed',
            ],
            'dracula' => [
                'label'        => 'Dracula',
                'dark_css'     => 'dracula',
                'light_css'    => 'atom-one-light',
                'dark_bg'      => '#282a36',
                'dark_toolbar' => '#21222c',
                'light_bg'     => '#fafafa',
                'light_toolbar'=> '#e8eaed',
            ],
            'tokyo-night' => [
                'label'        => 'Tokyo Night',
                'dark_css'     => 'tokyo-night-dark',
                'light_css'    => 'tokyo-night-light',
                'dark_bg'      => '#1a1b26',
                'dark_toolbar' => '#16161e',
                'light_bg'     => '#d5d6db',
                'light_toolbar'=> '#c8c9ce',
            ],
            'vs2015' => [
                'label'        => 'VS 2015 / VS Code',
                'dark_css'     => 'vs2015',
                'light_css'    => 'vs',
                'dark_bg'      => '#1e1e1e',
                'dark_toolbar' => '#181818',
                'light_bg'     => '#fff',
                'light_toolbar'=> '#f3f3f3',
            ],
            'stackoverflow' => [
                'label'        => 'Stack Overflow',
                'dark_css'     => 'stackoverflow-dark',
                'light_css'    => 'stackoverflow-light',
                'dark_bg'      => '#1c1b1b',
                'dark_toolbar' => '#151414',
                'light_bg'     => '#f6f6f6',
                'light_toolbar'=> '#e8e8e8',
            ],
            'night-owl' => [
                'label'        => 'Night Owl',
                'dark_css'     => 'night-owl',
                'light_css'    => 'atom-one-light',
                'dark_bg'      => '#011627',
                'dark_toolbar' => '#001122',
                'light_bg'     => '#fafafa',
                'light_toolbar'=> '#e8eaed',
            ],
            'gruvbox' => [
                'label'        => 'Gruvbox',
                'dark_css'     => 'base16/gruvbox-dark-hard',
                'light_css'    => 'base16/gruvbox-light-hard',
                'dark_bg'      => '#1d2021',
                'dark_toolbar' => '#171819',
                'light_bg'     => '#f9f5d7',
                'light_toolbar'=> '#ece8c8',
            ],
            'solarized' => [
                'label'        => 'Solarized',
                'dark_css'     => 'base16/solarized-dark',
                'light_css'    => 'base16/solarized-light',
                'dark_bg'      => '#002b36',
                'dark_toolbar' => '#002530',
                'light_bg'     => '#fdf6e3',
                'light_toolbar'=> '#eee8d5',
            ],
            'panda' => [
                'label'        => 'Panda',
                'dark_css'     => 'panda-syntax-dark',
                'light_css'    => 'panda-syntax-light',
                'dark_bg'      => '#292a2b',
                'dark_toolbar' => '#222324',
                'light_bg'     => '#e6e6e6',
                'light_toolbar'=> '#d9d9d9',
            ],
            'tomorrow' => [
                'label'        => 'Tomorrow Night',
                'dark_css'     => 'tomorrow-night-bright',
                'light_css'    => 'atom-one-light',
                'dark_bg'      => '#000',
                'dark_toolbar' => '#0a0a0a',
                'light_bg'     => '#fafafa',
                'light_toolbar'=> '#e8eaed',
            ],
            'shades-of-purple' => [
                'label'        => 'Shades of Purple',
                'dark_css'     => 'shades-of-purple',
                'light_css'    => 'atom-one-light',
                'dark_bg'      => '#2d2b55',
                'dark_toolbar' => '#252347',
                'light_bg'     => '#fafafa',
                'light_toolbar'=> '#e8eaed',
            ],
        ];
    }

    private static $instance_count  = 0;
    private static $assets_enqueued = false;

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_block' ] );
        add_action( 'init', [ __CLASS__, 'register_shortcode' ] );
        add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_convert_script' ] );
        add_action( 'admin_menu', [ __CLASS__, 'add_tools_page' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );

        // Migration AJAX
        add_action( 'wp_ajax_cs_migrate_scan', [ __CLASS__, 'ajax_scan' ] );
        add_action( 'wp_ajax_cs_migrate_preview', [ __CLASS__, 'ajax_preview' ] );
        add_action( 'wp_ajax_cs_migrate_single', [ __CLASS__, 'ajax_migrate_single' ] );
        add_action( 'wp_ajax_cs_migrate_all', [ __CLASS__, 'ajax_migrate_all' ] );

        // SQL AJAX
        add_action( 'wp_ajax_cs_sql_run', [ __CLASS__, 'ajax_sql_run' ] );
    }

    /* ==================================================================
       1. BLOCK REGISTRATION
       ================================================================== */

    public static function register_block() {
        $cdn = self::HLJS_CDN . self::HLJS_VERSION;

        wp_register_script(
            'hljs-core',
            $cdn . '/highlight.min.js',
            [],
            self::HLJS_VERSION,
            true
        );

        // Register both theme stylesheets from the selected pair
        $pair_slug = get_option( 'cs_code_theme_pair', 'atom-one' );
        $registry  = self::get_theme_registry();
        $pair      = isset( $registry[ $pair_slug ] ) ? $registry[ $pair_slug ] : $registry['atom-one'];

        wp_register_style(
            'hljs-theme-dark',
            $cdn . '/styles/' . $pair['dark_css'] . '.min.css',
            [],
            self::HLJS_VERSION
        );
        wp_register_style(
            'hljs-theme-light',
            $cdn . '/styles/' . $pair['light_css'] . '.min.css',
            [],
            self::HLJS_VERSION
        );

        wp_register_style(
            'cs-code-block-frontend',
            plugins_url( 'assets/cs-code-block.css', __FILE__ ),
            [ 'hljs-theme-dark', 'hljs-theme-light' ],
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-code-block.css' )
        );

        wp_register_script(
            'cs-code-block-frontend',
            plugins_url( 'assets/cs-code-block.js', __FILE__ ),
            [ 'hljs-core' ],
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-code-block.js' ),
            true
        );

        wp_register_style(
            'cs-code-block-editor',
            plugins_url( 'assets/cs-code-block-editor.css', __FILE__ ),
            [],
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-code-block-editor.css' )
        );

        wp_register_script(
            'cloudscale-code-block-editor-script',
            plugins_url( 'blocks/code/editor.js', __FILE__ ),
            [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-hooks' ],
            filemtime( plugin_dir_path( __FILE__ ) . 'blocks/code/editor.js' ),
            true
        );

        register_block_type(
            __DIR__ . '/blocks/code',
            [
                'render_callback' => [ __CLASS__, 'render_block' ],
                'editor_script'   => 'cloudscale-code-block-editor-script',
            ]
        );
    }

    /* ==================================================================
       1b. CONVERT SCRIPT
       ================================================================== */

    public static function enqueue_convert_script() {
        wp_enqueue_script(
            'cs-code-block-convert',
            plugins_url( 'assets/cs-convert.js', __FILE__ ),
            [ 'wp-blocks', 'wp-data' ],
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-convert.js' ),
            true
        );
    }

    /* ==================================================================
       2. RENDER (shared by block + shortcode)
       ================================================================== */

    public static function render_block( $attributes, $block_content = '' ) {
        self::maybe_enqueue_frontend();
        self::$instance_count++;

        $id    = 'cs-code-' . self::$instance_count;
        $code  = isset( $attributes['content'] )  ? $attributes['content'] : '';
        $lang  = isset( $attributes['language'] ) ? $attributes['language'] : '';
        $title = isset( $attributes['title'] )    ? $attributes['title']    : '';
        $theme = isset( $attributes['theme'] )    ? $attributes['theme']    : '';

        return self::build_html( $id, $code, $lang, $title, $theme );
    }

    private static function build_html( $id, $code, $lang, $title, $theme ) {
        $lang_class = $lang ? 'language-' . esc_attr( $lang ) : '';
        $theme_attr = $theme ? ' data-theme="' . esc_attr( $theme ) . '"' : '';

        $cloudscale_link = '<a class="cs-code-brand" href="https://your-wordpress-site.example.com/2026/02/27/building-a-better-code-block-for-wordpress-cloudscale-code-block-plugin/" target="_blank" rel="noopener noreferrer"><span class="cs-brand-bolt">&#9889;</span> Powered by CloudScale</a>';

        $title_html = '';
        if ( $title ) {
            $title_html = '<div class="cs-code-title">' . esc_html( $title ) . '</div>';
        }

        ob_start();
        ?>
        <div class="cs-code-wrapper" id="<?php echo esc_attr( $id ); ?>"<?php echo $theme_attr; ?>>
            <div class="cs-code-toolbar">
                <?php echo $cloudscale_link; ?>
                <?php echo $title_html; ?>
                <div class="cs-code-actions">
                    <span class="cs-code-lang-badge"></span>
                    <button class="cs-code-lines-toggle" title="Toggle line numbers" aria-label="Toggle line numbers">
                        <svg class="cs-icon-lines" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><text x="4" y="7" font-size="7" fill="currentColor" stroke="none" font-family="monospace">1</text><text x="4" y="13" font-size="7" fill="currentColor" stroke="none" font-family="monospace">2</text><text x="4" y="19" font-size="7" fill="currentColor" stroke="none" font-family="monospace">3</text></svg>
                    </button>
                    <button class="cs-code-theme-toggle" title="Toggle light/dark mode" aria-label="Toggle theme">
                        <svg class="cs-icon-sun" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        <svg class="cs-icon-moon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </button>
                    <button class="cs-code-copy" title="Copy to clipboard" aria-label="Copy code">
                        <svg class="cs-icon-copy" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <svg class="cs-icon-check" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                </div>
            </div>
            <div class="cs-code-body">
                <pre><code class="<?php echo $lang_class; ?>"><?php echo esc_html( $code ); ?></code></pre>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function maybe_enqueue_frontend() {
        if ( self::$assets_enqueued ) {
            return;
        }
        self::$assets_enqueued = true;

        wp_enqueue_style( 'hljs-theme-dark' );
        wp_enqueue_style( 'hljs-theme-light' );
        wp_enqueue_style( 'cs-code-block-frontend' );
        wp_enqueue_script( 'hljs-core' );
        wp_enqueue_script( 'cs-code-block-frontend' );

        $default_theme = get_option( 'cs_code_default_theme', 'dark' );
        $pair_slug     = get_option( 'cs_code_theme_pair', 'atom-one' );
        $registry      = self::get_theme_registry();
        $pair          = isset( $registry[ $pair_slug ] ) ? $registry[ $pair_slug ] : $registry['atom-one'];

        wp_localize_script( 'cs-code-block-frontend', 'csCodeConfig', [
            'defaultTheme'  => $default_theme,
            'themePair'     => $pair_slug,
            'darkBg'        => $pair['dark_bg'],
            'darkToolbar'   => $pair['dark_toolbar'],
            'lightBg'       => $pair['light_bg'],
            'lightToolbar'  => $pair['light_toolbar'],
        ] );
    }

    /* ==================================================================
       3. SHORTCODE [cs_code]
       ================================================================== */

    public static function register_shortcode() {
        add_shortcode( 'cs_code', [ __CLASS__, 'render_shortcode' ] );
    }

    public static function render_shortcode( $atts, $content = null ) {
        $atts = shortcode_atts( [
            'lang'  => '',
            'theme' => '',
            'title' => '',
        ], $atts, 'cs_code' );

        $code = self::decode_shortcode_content( $content );

        return self::render_block( [
            'content'  => $code,
            'language' => $atts['lang'],
            'title'    => $atts['title'],
            'theme'    => $atts['theme'],
        ] );
    }

    private static function decode_shortcode_content( $content ) {
        $content = preg_replace( '#^<p>|</p>$#i', '', trim( $content ) );
        $content = str_replace(
            [ '<br />', '<br/>', '<br>', '&#8220;', '&#8221;', '&#8216;', '&#8217;', '&nbsp;', '&#038;' ],
            [ "\n", "\n", "\n", '"', '"', "'", "'", ' ', '&' ],
            $content
        );
        $content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
        return trim( $content );
    }

    /* ==================================================================
       4. SETTINGS
       ================================================================== */

    public static function register_settings() {
        register_setting( 'cs_code_settings', 'cs_code_default_theme', [
            'type'              => 'string',
            'sanitize_callback' => function ( $val ) {
                return in_array( $val, [ 'dark', 'light' ] ) ? $val : 'dark';
            },
            'default' => 'dark',
        ] );

        $valid_themes = array_keys( self::get_theme_registry() );
        register_setting( 'cs_code_settings', 'cs_code_theme_pair', [
            'type'              => 'string',
            'sanitize_callback' => function ( $val ) use ( $valid_themes ) {
                return in_array( $val, $valid_themes, true ) ? $val : 'atom-one';
            },
            'default' => 'atom-one',
        ] );
    }

    /* ==================================================================
       5. COMBINED TOOLS PAGE (Code Block Migrator + SQL Command)
       ================================================================== */

    public static function add_tools_page() {
        add_management_page(
            'CloudScale Code and SQL',
            'CloudScale Code and SQL',
            'manage_options',
            self::TOOLS_SLUG,
            [ __CLASS__, 'render_tools_page' ]
        );
    }

    public static function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'tools_page_' . self::TOOLS_SLUG ) {
            return;
        }

        // Tabs CSS
        wp_enqueue_style(
            'cs-admin-tabs',
            plugins_url( 'assets/cs-admin-tabs.css', __FILE__ ),
            [],
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-admin-tabs.css' )
        );

        // Migrate CSS + JS
        wp_enqueue_style(
            'cs-code-migrate',
            plugins_url( 'assets/cs-code-migrate.css', __FILE__ ),
            [],
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-code-migrate.css' )
        );
        wp_enqueue_script(
            'cs-code-migrate',
            plugins_url( 'assets/cs-code-migrate.js', __FILE__ ),
            [],
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-code-migrate.js' ),
            true
        );
        wp_localize_script( 'cs-code-migrate', 'csMigrate', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( self::MIGRATE_NONCE ),
        ] );
    }

    public static function render_tools_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'migrate';
        $base_url   = admin_url( 'tools.php?page=' . self::TOOLS_SLUG );
        ?>
<style>
/* ============================================================
   CloudScale Code and SQL — Admin UI  v1.7.2
   Colour palette mirrors CloudScale Page Views.
   ============================================================ */
#cs-app * { box-sizing: border-box; }
#cs-app {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    font-size: 13px;
    color: #1a2332;
    max-width: 1200px;
    margin-top: 10px;
}
#cs-banner {
    background: linear-gradient(135deg, #1a3a8f 0%, #1e6fd9 60%, #0fb8e0 100%);
    border-radius: 8px 8px 0 0;
    padding: 20px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
#cs-banner-title {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    letter-spacing: -.3px;
}
#cs-banner-sub {
    font-size: 12px;
    color: rgba(255,255,255,.75);
    margin-top: 3px;
}
#cs-banner-right { display: flex; gap: 10px; align-items: center; }
.cs-badge {
    display: inline-block;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}
.cs-badge-green  { background: #1db954; color: #fff; }
.cs-badge-orange { background: #f47c20; color: #fff; }

/* Tab bar */
#cs-tab-bar {
    background: #1a2d6b;
    display: flex;
    gap: 0;
    padding: 0 20px;
}
#cs-app .cs-tab {
    background: transparent;
    border: none;
    color: rgba(255,255,255,.6);
    font-size: 13px;
    font-weight: 600;
    padding: 12px 20px;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all .15s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
#cs-app .cs-tab:hover { color: #fff; }
#cs-app .cs-tab:focus { outline: none; box-shadow: none; }
#cs-app .cs-tab.active { color: #fff; border-bottom-color: #f47c20; }

/* Tab content */
.cs-tab-content { display: none; padding: 20px 0 0; }
.cs-tab-content.active { display: block; }

/* Section headers */
#cs-app .cs-section-header {
    background: linear-gradient(135deg, #1a3a8f, #1e6fd9);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: 10px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
#cs-app .cs-section-header-green  { background: linear-gradient(135deg, #1a7a3a, #1db954); }
#cs-app .cs-section-header-orange { background: linear-gradient(135deg, #c45c00, #f47c20); }
#cs-app .cs-section-header-red    { background: linear-gradient(135deg, #8b1a1a, #e53e3e); }
#cs-app .cs-section-header-purple { background: linear-gradient(135deg, #5b21b6, #7c3aed); }
#cs-app .cs-section-header-teal   { background: linear-gradient(135deg, #0d6a5c, #0fb8e0); }
#cs-app .cs-section-header code {
    background: rgba(255,255,255,.2);
    color: #fff;
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 11px;
}
.cs-header-hint {
    font-size: 11px;
    font-weight: 400;
    opacity: .85;
}

/* Panels */
#cs-app .cs-panel {
    background: #fff;
    border: 1.5px solid #dce3ef;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 18px;
}
#cs-app .cs-panel-body {
    padding: 20px 24px;
}

/* Fields */
#cs-app .cs-field-row {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
    align-items: flex-start;
}
#cs-app .cs-field { flex: 1; min-width: 200px; }
#cs-app .cs-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #1a2332;
    margin-bottom: 6px;
}
#cs-app .cs-input,
#cs-app .cs-field select {
    width: 100%;
    max-width: 400px;
    padding: 5px 8px;
    border: 1.5px solid #dce3ef;
    border-radius: 4px;
    font-size: 13px;
    background: #fff;
    color: #1a2332;
}
#cs-app .cs-hint {
    display: block;
    margin-top: 4px;
    font-size: 11px;
    color: #1db954;
    font-style: italic;
    padding-left: 8px;
    border-left: 3px solid #1db954;
}

/* Buttons */
#cs-app .cs-btn-primary {
    background: linear-gradient(135deg, #1a3a8f, #1e6fd9);
    border: none;
    border-radius: 5px;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    padding: 6px 16px;
    cursor: pointer;
    transition: opacity .15s;
}
#cs-app .cs-btn-primary:hover { opacity: .88; }
#cs-app .cs-btn-orange {
    background: linear-gradient(135deg, #c45c00, #f47c20);
    border: none;
    border-radius: 5px;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    padding: 6px 16px;
    cursor: pointer;
    transition: opacity .15s;
}
#cs-app .cs-btn-orange:hover { opacity: .88; }
#cs-app .cs-btn-pink {
    background: linear-gradient(135deg, #b5348a, #d946a6);
    border: none;
    border-radius: 5px;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    padding: 6px 16px;
    cursor: pointer;
    transition: opacity .15s;
}
#cs-app .cs-btn-pink:hover { opacity: .88; }

/* Settings saved */
#cs-app .cs-settings-saved {
    color: #1db954;
    font-weight: 700;
    font-size: 12px;
    opacity: 0;
    transition: opacity 0.3s;
}
#cs-app .cs-settings-saved.visible { opacity: 1; }

/* SQL textarea */
#cs-app .cs-sql-textarea {
    width: 100%;
    min-height: 80px;
    font-family: 'SF Mono', 'Fira Code', 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.5;
    padding: 12px;
    border: 1.5px solid #1a2d6b;
    border-radius: 6px;
    background: #0d1b2a;
    color: #e0e0e0;
    resize: vertical;
}
#cs-app .cs-sql-textarea:focus {
    outline: none;
    border-color: #1e6fd9;
    box-shadow: 0 0 0 2px rgba(30,111,217,.25);
}

/* SQL table */
#cs-app .cs-sql-table {
    width: 100%;
    border-collapse: collapse;
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
}
#cs-app .cs-sql-table th {
    text-align: left;
    padding: 8px 10px;
    border-bottom: 2px solid #1a3a8f;
    background: #f0f4ff;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #1a3a8f;
    white-space: nowrap;
    position: sticky;
    top: 0;
}
#cs-app .cs-sql-table td {
    padding: 6px 10px;
    border-bottom: 1px solid #f0f4ff;
    max-width: 500px;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-all;
}
#cs-app .cs-sql-table tr:nth-child(even) { background: #f8faff; }
#cs-app .cs-sql-table tr:hover td { background: #edf2ff; }

/* Quick query buttons */
#cs-app .cs-quick-group-label {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #1a3a8f;
    margin: 16px 0 8px;
    padding: 0;
}
#cs-app .cs-quick-group-label:first-child { margin-top: 0; }
#cs-app .cs-quick-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 4px;
}
#cs-app .cs-quick-btn {
    background: #f0f4ff;
    border: 1.5px solid #c5d2f0;
    border-radius: 5px;
    padding: 5px 14px;
    font-size: 12px;
    font-weight: 600;
    color: #1a3a8f;
    cursor: pointer;
    transition: all .15s;
    white-space: nowrap;
}
#cs-app .cs-quick-btn:hover {
    background: #dce6ff;
    border-color: #1a3a8f;
}

/* Migrate toolbar */
#cs-app .cs-migrate-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
}
#cs-app .cs-migrate-toolbar .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    line-height: 16px;
    margin-top: 2px;
}
#cs-app .cs-migrate-hint {
    color: #aaa;
    font-style: italic;
    text-align: center;
    padding: 30px 0;
    font-size: 13px;
}
#cs-app .cs-status { font-size: 12px; color: #888; }
</style>
        <div class="wrap">
        <div id="cs-app">

            <!-- Banner -->
            <div id="cs-banner">
                <div>
                    <div id="cs-banner-title">⚡ CloudScale Code and SQL</div>
                    <div id="cs-banner-sub">Syntax highlighting, code migration, and database query tool &middot; v<?php echo esc_html( self::VERSION ); ?></div>
                </div>
                <div id="cs-banner-right">
                    <span class="cs-badge cs-badge-green">✅ Totally Free</span>
                    <span class="cs-badge cs-badge-orange">andrewbaker.ninja</span>
                </div>
            </div>

            <!-- Tab bar -->
            <div id="cs-tab-bar">
                <a href="<?php echo esc_url( $base_url . '&tab=migrate' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'migrate' ? 'active' : ''; ?>">
                    🔄 Code Migrator
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=sql' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'sql' ? 'active' : ''; ?>">
                    🗄️ SQL Command
                </a>
            </div>

            <?php if ( $active_tab === 'migrate' ) : ?>
                <div class="cs-tab-content active">
                    <?php self::render_settings_panel(); ?>
                    <?php self::render_migrate_panel(); ?>
                </div>
            <?php elseif ( $active_tab === 'sql' ) : ?>
                <div class="cs-tab-content active">
                    <?php self::render_sql_panel(); ?>
                </div>
            <?php endif; ?>

        </div>
        </div>
        <?php
    }

    /* ==================================================================
       5a. Settings panel (inline on Migrator tab)
       ================================================================== */

    private static function render_settings_panel() {
        $theme     = get_option( 'cs_code_default_theme', 'dark' );
        $pair_slug = get_option( 'cs_code_theme_pair', 'atom-one' );
        $registry  = self::get_theme_registry();
        $nonce     = wp_create_nonce( 'cs_code_settings_inline' );
        ?>
        <div class="cs-panel">
            <div class="cs-section-header cs-section-header-teal">
                <span>🎨 CODE BLOCK SETTINGS</span>
            </div>
            <div class="cs-panel-body">
                <div class="cs-field-row">
                    <div class="cs-field">
                        <label class="cs-label" for="cs-settings-pair">Color Theme:</label>
                        <select id="cs-settings-pair" name="cs_code_theme_pair" class="cs-input">
                            <?php foreach ( $registry as $slug => $info ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $pair_slug, $slug ); ?>>
                                    <?php echo esc_html( $info['label'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="cs-hint">Syntax highlighting color scheme loaded from CDN.</span>
                    </div>
                    <div class="cs-field">
                        <label class="cs-label" for="cs-settings-theme">Default Mode:</label>
                        <select id="cs-settings-theme" name="cs_code_default_theme" class="cs-input">
                            <option value="dark" <?php selected( $theme, 'dark' ); ?>>Dark</option>
                            <option value="light" <?php selected( $theme, 'light' ); ?>>Light</option>
                        </select>
                        <span class="cs-hint">Visitors can still toggle per block.</span>
                    </div>
                </div>
                <div style="margin-top:14px;display:flex;align-items:center;gap:10px">
                    <button type="button" class="cs-btn-primary" id="cs-settings-save">💾 Save Settings</button>
                    <span class="cs-settings-saved" id="cs-settings-saved">✓ Saved</span>
                </div>
            </div>
        </div>
        <script>
        (function() {
            var saveBtn  = document.getElementById('cs-settings-save');
            var selPair  = document.getElementById('cs-settings-pair');
            var selTheme = document.getElementById('cs-settings-theme');
            var savedMsg = document.getElementById('cs-settings-saved');

            saveBtn.addEventListener('click', function() {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';

                var fd = new FormData();
                fd.append('action', 'cs_save_theme_setting');
                fd.append('nonce', <?php echo wp_json_encode( $nonce ); ?>);
                fd.append('theme', selTheme.value);
                fd.append('theme_pair', selPair.value);

                fetch(ajaxurl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = '💾 Save Settings';
                        if (resp.success) {
                            savedMsg.classList.add('visible');
                            setTimeout(function() { savedMsg.classList.remove('visible'); }, 2000);
                        }
                    })
                    .catch(function() {
                        saveBtn.disabled = false;
                        saveBtn.textContent = '💾 Save Settings';
                    });
            });
        })();
        </script>
        <?php
    }

    /* ==================================================================
       5b. Migrate panel
       ================================================================== */

    private static function render_migrate_panel() {
        ?>
        <div class="cs-panel">
            <div class="cs-section-header">
                <span>🔄 CODE BLOCK MIGRATOR</span>
            </div>
            <div class="cs-panel-body">
                <p style="color:#555;margin:0 0 16px;font-size:13px;line-height:1.6">
                    Scan your posts for legacy WordPress code blocks, preview changes, then migrate one at a time or all at once.
                </p>

                <div class="cs-migrate-toolbar">
                    <button id="cs-scan-btn" class="cs-btn-primary" style="padding:8px 20px;font-size:13px">
                        <span class="dashicons dashicons-search" style="font-size:14px;width:14px;height:14px;margin-top:1px"></span> Scan Posts
                    </button>
                    <button id="cs-migrate-all-btn" class="cs-btn-orange" style="padding:8px 20px;font-size:13px;opacity:.5;pointer-events:none" disabled>
                        <span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;margin-top:1px"></span> Migrate All Remaining
                    </button>
                    <span id="cs-scan-status" class="cs-status"></span>
                </div>

                <div id="cs-results-area">
                    <p class="cs-migrate-hint">Click <strong>Scan Posts</strong> to find all posts with legacy code blocks.</p>
                </div>
            </div>
        </div>

        <div id="cs-preview-modal" class="cs-modal" style="display:none;">
            <div class="cs-modal-backdrop"></div>
            <div class="cs-modal-content">
                <div class="cs-modal-header">
                    <h2 id="cs-modal-title">Preview</h2>
                    <button class="cs-modal-close">&times;</button>
                </div>
                <div class="cs-modal-body" id="cs-modal-body">
                    Loading...
                </div>
                <div class="cs-modal-footer">
                    <button id="cs-modal-migrate-btn" class="cs-btn-primary" data-post-id="" style="padding:8px 20px">
                        <span class="dashicons dashicons-yes-alt"></span> Migrate This Post
                    </button>
                    <button class="cs-modal-close-btn" style="background:#fff;border:1.5px solid #dce3ef;border-radius:5px;padding:6px 16px;font-size:12px;font-weight:600;cursor:pointer">Cancel</button>
                </div>
            </div>
        </div>
        <?php
    }

    /* ==================================================================
       5c. SQL Command panel
       ================================================================== */

    private static function render_sql_panel() {
        $sql_nonce = wp_create_nonce( 'cs_sql_nonce' );
        global $wpdb;
        $prefix = $wpdb->prefix;
        ?>
        <div class="cs-panel">
            <div class="cs-section-header cs-section-header-purple">
                <span>🗄️ SQL Query</span>
                <span class="cs-header-hint">Table prefix: <code><?php echo esc_html( $prefix ); ?></code> &nbsp;·&nbsp; ⚠ Read only (SELECT, SHOW, DESCRIBE, EXPLAIN)</span>
            </div>
            <div class="cs-panel-body">
                <textarea id="cs-sql-input" class="cs-sql-textarea" placeholder="SELECT option_name, option_value FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_name = 'siteurl';"></textarea>
                <div style="display:flex;align-items:center;gap:10px;margin-top:12px">
                    <button type="button" class="cs-btn-primary" id="cs-sql-run" style="padding:8px 20px;font-size:13px">▶ Run Query</button>
                    <button type="button" class="cs-btn-pink" id="cs-sql-clear">🧹 Clear</button>
                    <span id="cs-sql-status" style="font-size:12px;color:#888"></span>
                    <span style="margin-left:auto;font-size:11px;color:#999">Enter or Ctrl+Enter to run</span>
                </div>
            </div>
        </div>

        <div class="cs-panel">
            <div class="cs-section-header cs-section-header-green">
                <span>📊 Results</span>
                <span id="cs-sql-meta" style="font-size:12px;opacity:0.85"></span>
            </div>
            <div class="cs-panel-body">
                <div id="cs-sql-results" style="overflow-x:auto;font-size:13px">
                    <div style="text-align:center;color:#999;padding:40px 0">Run a query to see results here</div>
                </div>
            </div>
        </div>

        <div class="cs-panel">
            <div class="cs-section-header cs-section-header-orange">
                <span>⚡ Quick Queries</span>
            </div>
            <div class="cs-panel-body">
                <p class="cs-quick-group-label">🏥 Health and Diagnostics</p>
                <div class="cs-quick-grid">
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT @@version AS mysql_version, @@global.max_connections AS max_connections, @@global.wait_timeout AS wait_timeout_sec, @@global.max_allowed_packet / 1024 / 1024 AS max_packet_mb, DATABASE() AS current_db;">
                        🩺 Database health check
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT option_id, option_name, LEFT(option_value, 200) AS option_value_preview FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_name IN ('siteurl','home','blogname','blogdescription','wp_version','db_version');">
                        🏠 Site identity options
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT table_name, engine, table_rows, ROUND(data_length/1024/1024, 2) AS data_mb, ROUND(index_length/1024/1024, 2) AS index_mb, ROUND((data_length + index_length)/1024/1024, 2) AS total_mb FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY (data_length + index_length) DESC;">
                        📊 Table names, sizes and rows
                    </button>
                </div>

                <p class="cs-quick-group-label">📈 Content Summary</p>
                <div class="cs-quick-grid">
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT post_type, post_status, COUNT(*) AS total FROM <?php echo esc_attr( $prefix ); ?>posts GROUP BY post_type, post_status ORDER BY total DESC;">
                        📰 Posts by type and status
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status='publish') AS published_posts, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_type='revision') AS revisions, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status='auto-draft') AS auto_drafts, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status='trash') AS trashed, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>comments) AS total_comments, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>comments WHERE comment_approved='spam') AS spam_comments, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>users) AS users, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_name LIKE '%_transient_%') AS transients;">
                        📋 Site stats summary
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT ID, post_title, post_date, post_status FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status = 'publish' AND post_type = 'post' ORDER BY post_date DESC LIMIT 20;">
                        📝 Latest 20 published posts
                    </button>
                </div>

                <p class="cs-quick-group-label">🧹 Bloat and Cleanup Checks</p>
                <div class="cs-quick-grid">
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT COUNT(*) AS orphaned_postmeta FROM <?php echo esc_attr( $prefix ); ?>postmeta pm LEFT JOIN <?php echo esc_attr( $prefix ); ?>posts p ON pm.post_id = p.ID WHERE p.ID IS NULL;">
                        🗑️ Orphaned postmeta count
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT COUNT(*) AS expired_transients FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP();">
                        ⏰ Expired transients count
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT post_type, COUNT(*) AS total FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_type = 'revision' OR post_status = 'auto-draft' OR post_status = 'trash' GROUP BY post_type, post_status ORDER BY total DESC;">
                        📦 Revisions, drafts and trash
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT LEFT(option_name, 40) AS option_name, LENGTH(option_value) AS value_bytes FROM <?php echo esc_attr( $prefix ); ?>options WHERE autoload = 'yes' ORDER BY LENGTH(option_value) DESC LIMIT 30;">
                        ⚖️ Largest autoloaded options
                    </button>
                </div>

                <p class="cs-quick-group-label">🔍 URL and Migration Helpers</p>
                <div class="cs-quick-grid">
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT option_id, option_name, option_value FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_value LIKE '%http://andrewbaker%';">
                        🔗 HTTP references (andrewbaker)
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT ID, post_title, post_type, post_status, guid FROM <?php echo esc_attr( $prefix ); ?>posts WHERE guid LIKE '%http://%' LIMIT 50;">
                        📰 Posts with HTTP GUIDs
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT post_id, meta_key, LEFT(meta_value, 200) AS meta_value_preview FROM <?php echo esc_attr( $prefix ); ?>postmeta WHERE meta_value LIKE '%http://54.195%' LIMIT 50;">
                        🖥️ Old IP references (postmeta)
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT ID, post_title, post_type FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status = 'publish' AND ID NOT IN (SELECT post_id FROM <?php echo esc_attr( $prefix ); ?>postmeta WHERE meta_key = '_cs_seo_desc' AND meta_value != '') ORDER BY post_date DESC LIMIT 50;">
                        📝 Posts missing meta descriptions
                    </button>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var input    = document.getElementById('cs-sql-input');
            var runBtn   = document.getElementById('cs-sql-run');
            var clearBtn = document.getElementById('cs-sql-clear');
            var results  = document.getElementById('cs-sql-results');
            var status   = document.getElementById('cs-sql-status');
            var meta     = document.getElementById('cs-sql-meta');
            var nonce    = <?php echo wp_json_encode( $sql_nonce ); ?>;

            if (!input || !runBtn) return;

            function escHtml(s) {
                var d = document.createElement('div');
                d.textContent = s;
                return d.innerHTML;
            }

            function run() {
                var sql = input.value.trim();
                if (!sql) return;

                runBtn.disabled = true;
                status.textContent = 'Running...';
                status.style.color = '#888';
                results.innerHTML = '<div style="text-align:center;color:#888;padding:20px">⏳ Executing query...</div>';
                meta.textContent = '';

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=cs_sql_run&nonce=' + encodeURIComponent(nonce) + '&sql=' + encodeURIComponent(sql)
                })
                .then(function(r) { return r.json(); })
                .then(function(resp) {
                    runBtn.disabled = false;

                    if (!resp.success) {
                        status.textContent = '✗ Error';
                        status.style.color = '#c3372b';
                        results.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px 14px;border-radius:4px;font-family:monospace;font-size:12px;white-space:pre-wrap">' + escHtml(typeof resp.data === 'string' ? resp.data : JSON.stringify(resp.data)) + '</div>';
                        return;
                    }

                    var d = resp.data;
                    status.textContent = '✓ Success';
                    status.style.color = '#1a7a34';
                    meta.textContent = d.count + ' row' + (d.count !== 1 ? 's' : '') + ' in ' + d.elapsed + 'ms';

                    if (!d.rows || d.rows.length === 0) {
                        results.innerHTML = '<div style="text-align:center;color:#999;padding:20px">Query returned 0 rows</div>';
                        return;
                    }

                    var cols = Object.keys(d.rows[0]);
                    var html = '<table class="cs-sql-table">';
                    html += '<thead><tr>';
                    cols.forEach(function(c) {
                        html += '<th>' + escHtml(c) + '</th>';
                    });
                    html += '</tr></thead><tbody>';

                    d.rows.forEach(function(row, i) {
                        html += '<tr>';
                        cols.forEach(function(c) {
                            var val = row[c];
                            if (val === null) val = '<span style="color:#999;font-style:italic">NULL</span>';
                            else {
                                val = escHtml(String(val));
                                val = val.replace(/(http:\/\/[^\s&lt;,;'"]+)/g, '<span style="background:#fef0f0;color:#c3372b;padding:1px 3px;border-radius:2px">$1</span>');
                            }
                            html += '<td>' + val + '</td>';
                        });
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                    results.innerHTML = html;
                })
                .catch(function(e) {
                    runBtn.disabled = false;
                    status.textContent = '✗ Network error';
                    status.style.color = '#c3372b';
                    results.innerHTML = '<div style="color:#c3372b;padding:12px">' + escHtml(e.message) + '</div>';
                });
            }

            runBtn.addEventListener('click', run);
            clearBtn.addEventListener('click', function() {
                input.value = '';
                results.innerHTML = '<div style="text-align:center;color:#999;padding:40px 0">Run a query to see results here</div>';
                status.textContent = '';
                meta.textContent = '';
                input.focus();
            });

            // Enter (plain) or Ctrl+Enter both run the query
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    run();
                }
            });

            document.querySelectorAll('.cs-sql-quick').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    input.value = this.getAttribute('data-sql');
                    run();
                });
            });
        })();
        </script>
        <?php
    }

    /* ==================================================================
       6. SQL COMMAND: Query validation + AJAX
       ================================================================== */

    private static function is_safe_query( string $sql ): bool {
        $clean = trim( $sql );
        $clean = preg_replace( '/^(\/\*.*?\*\/\s*|--[^\n]*\n\s*|#[^\n]*\n\s*)*/s', '', $clean );
        $clean = trim( $clean );
        if ( preg_match( '/^(\w+)/i', $clean, $m ) ) {
            $first = strtoupper( $m[1] );
            return in_array( $first, [ 'SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN' ], true );
        }
        return false;
    }

    public static function ajax_sql_run(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 403 );
        }
        if ( ! check_ajax_referer( 'cs_sql_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Bad nonce', 403 );
        }

        $raw = $_POST['sql'] ?? $_REQUEST['sql'] ?? '';
        $sql = trim( wp_unslash( $raw ) );
        if ( ! $sql ) {
            wp_send_json_error( 'Empty query' );
        }

        if ( ! self::is_safe_query( $sql ) ) {
            wp_send_json_error( 'Only SELECT, SHOW, DESCRIBE, and EXPLAIN queries are allowed. Do not include shell commands like sudo or mysql.' );
        }

        global $wpdb;
        $wpdb->suppress_errors( true );
        $start   = microtime( true );
        $results = $wpdb->get_results( $sql, ARRAY_A );
        $elapsed = round( ( microtime( true ) - $start ) * 1000, 2 );
        $error   = $wpdb->last_error;
        $wpdb->suppress_errors( false );

        if ( $error ) {
            wp_send_json_error( $error );
        }

        wp_send_json_success( [
            'rows'    => $results,
            'count'   => count( $results ),
            'elapsed' => $elapsed,
        ] );
    }

    /* ==================================================================
       6a. Settings AJAX save
       ================================================================== */

    public static function ajax_save_theme_setting(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden' );
        }
        if ( ! check_ajax_referer( 'cs_code_settings_inline', 'nonce', false ) ) {
            wp_send_json_error( 'Bad nonce' );
        }

        $theme = isset( $_POST['theme'] ) ? sanitize_text_field( $_POST['theme'] ) : 'dark';
        if ( ! in_array( $theme, [ 'dark', 'light' ], true ) ) {
            $theme = 'dark';
        }
        update_option( 'cs_code_default_theme', $theme );

        $valid_pairs = array_keys( self::get_theme_registry() );
        $pair        = isset( $_POST['theme_pair'] ) ? sanitize_text_field( $_POST['theme_pair'] ) : 'atom-one';
        if ( ! in_array( $pair, $valid_pairs, true ) ) {
            $pair = 'atom-one';
        }
        update_option( 'cs_code_theme_pair', $pair );

        wp_send_json_success( [ 'theme' => $theme, 'theme_pair' => $pair ] );
    }

    /* ==================================================================
       7. MIGRATION TOOL
       ================================================================== */

    const MIGRATE_NONCE = 'cs_code_migrate_action';

    /* ==================================================================
       7a. Migration: Block conversion logic
       ================================================================== */

    private static function get_code_pattern() {
        return '#<!-- wp:(code-syntax-block/code|code)\s*(\{[^}]*\})?\s*-->\s*'
             . '<pre[^>]*class="[^"]*wp-block-code[^"]*"[^>]*>\s*'
             . '<code([^>]*)>(.*?)</code>\s*'
             . '</pre>\s*'
             . '<!-- /wp:\1\s*-->#s';
    }

    private static function get_preformatted_pattern() {
        return '#<!-- wp:preformatted\s*(\{[^}]*\})?\s*-->\s*'
             . '<pre[^>]*class="[^"]*wp-block-preformatted[^"]*"[^>]*>(.*?)</pre>\s*'
             . '<!-- /wp:preformatted\s*-->#s';
    }

    private static function convert_code_block( $matches ) {
        $block_json   = $matches[2] ?? '';
        $code_attrs   = $matches[3] ?? '';
        $code_content = $matches[4] ?? '';

        $code = html_entity_decode( $code_content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $code = rtrim( $code, "\n" );

        $lang = '';

        if ( ! empty( $block_json ) ) {
            $json = json_decode( $block_json, true );
            if ( isset( $json['language'] ) ) {
                $lang = $json['language'];
            }
        }

        if ( empty( $lang ) && preg_match( '/lang=["\']([^"\']+)["\']/', $code_attrs, $lm ) ) {
            $lang = $lm[1];
        }

        if ( empty( $lang ) && preg_match( '/class=["\'][^"\']*language-([a-zA-Z0-9+#._-]+)/', $code_attrs, $lm ) ) {
            $lang = $lm[1];
        }

        return self::build_migrate_block( $code, $lang );
    }

    private static function convert_preformatted_block( $matches ) {
        $code_content = $matches[2] ?? '';

        $code = str_ireplace( [ '<br>', '<br/>', '<br />' ], "\n", $code_content );
        $code = strip_tags( $code );
        $code = html_entity_decode( $code, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $code = rtrim( $code, "\n" );

        return self::build_migrate_block( $code, '' );
    }

    private static function build_migrate_block( $code, $lang ) {
        $attrs = [ 'content' => $code ];
        if ( ! empty( $lang ) ) {
            $attrs['language'] = $lang;
        }

        $attrs_json = wp_json_encode( $attrs );

        return '<!-- wp:cloudscale/code-block ' . $attrs_json . ' /-->';
    }

    private static function count_migrate_blocks( $content ) {
        $count  = preg_match_all( self::get_code_pattern(), $content, $m );
        $count += preg_match_all( self::get_preformatted_pattern(), $content, $m );
        return $count;
    }

    private static function convert_content( $content ) {
        $content = preg_replace_callback( self::get_code_pattern(), [ __CLASS__, 'convert_code_block' ], $content );
        $content = preg_replace_callback( self::get_preformatted_pattern(), [ __CLASS__, 'convert_preformatted_block' ], $content );
        return $content;
    }

    private static function truncate_block( $str, $max ) {
        if ( strlen( $str ) <= $max ) {
            return $str;
        }
        return substr( $str, 0, $max ) . "\n... [truncated]";
    }

    private static function get_migration_preview( $content ) {
        $blocks = [];

        preg_match_all( self::get_code_pattern(), $content, $matches, PREG_SET_ORDER );
        foreach ( $matches as $match ) {
            $original  = $match[0];
            $converted = self::convert_code_block( $match );

            $lang = '';
            if ( preg_match( '/"language":"([^"]+)"/', $converted, $lm ) ) {
                $lang = $lm[1];
            }

            $code_preview = html_entity_decode( $match[4], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            $first_line   = strtok( $code_preview, "\n" );
            if ( strlen( $first_line ) > 80 ) {
                $first_line = substr( $first_line, 0, 80 ) . '...';
            }

            $blocks[] = [
                'index'      => count( $blocks ) + 1,
                'type'       => 'wp:code',
                'language'   => $lang ?: '(auto detect)',
                'first_line' => $first_line,
                'original'   => htmlspecialchars( self::truncate_block( $original, 500 ) ),
                'converted'  => htmlspecialchars( self::truncate_block( $converted, 500 ) ),
            ];
        }

        preg_match_all( self::get_preformatted_pattern(), $content, $matches, PREG_SET_ORDER );
        foreach ( $matches as $match ) {
            $original  = $match[0];
            $converted = self::convert_preformatted_block( $match );

            $code_raw   = str_ireplace( [ '<br>', '<br/>', '<br />' ], "\n", $match[2] );
            $code_raw   = strip_tags( $code_raw );
            $code_raw   = html_entity_decode( $code_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            $first_line = strtok( $code_raw, "\n" );
            if ( strlen( $first_line ) > 80 ) {
                $first_line = substr( $first_line, 0, 80 ) . '...';
            }

            $blocks[] = [
                'index'      => count( $blocks ) + 1,
                'type'       => 'wp:preformatted',
                'language'   => '(auto detect)',
                'first_line' => $first_line,
                'original'   => htmlspecialchars( self::truncate_block( $original, 500 ) ),
                'converted'  => htmlspecialchars( self::truncate_block( $converted, 500 ) ),
            ];
        }

        return $blocks;
    }

    /* ==================================================================
       7b. Migration: AJAX handlers
       ================================================================== */

    public static function ajax_scan() {
        check_ajax_referer( self::MIGRATE_NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        global $wpdb;

        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_status, post_date, post_content
             FROM {$wpdb->posts}
             WHERE post_type IN ('post', 'page')
               AND post_status != 'trash'
               AND (
                   post_content LIKE '%<!-- wp:code %'
                OR post_content LIKE '%<!-- wp:code-->%'
                OR post_content LIKE '%<!-- wp:code-syntax-block/code%'
                OR post_content LIKE '%<!-- wp:preformatted%'
               )
             ORDER BY post_date DESC"
        );

        $results = [];
        foreach ( $posts as $post ) {
            $count = self::count_migrate_blocks( $post->post_content );
            if ( $count > 0 ) {
                $results[] = [
                    'id'          => (int) $post->ID,
                    'title'       => $post->post_title,
                    'status'      => $post->post_status,
                    'date'        => date( 'd M Y', strtotime( $post->post_date ) ),
                    'block_count' => $count,
                    'edit_url'    => get_edit_post_link( $post->ID, 'raw' ),
                    'view_url'    => get_permalink( $post->ID ),
                ];
            }
        }

        wp_send_json_success( [
            'posts'        => $results,
            'total_posts'  => count( $results ),
            'total_blocks' => array_sum( array_column( $results, 'block_count' ) ),
        ] );
    }

    public static function ajax_preview() {
        check_ajax_referer( self::MIGRATE_NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        $post    = get_post( $post_id );

        if ( ! $post ) {
            wp_send_json_error( 'Post not found.' );
        }

        $blocks = self::get_migration_preview( $post->post_content );

        wp_send_json_success( [
            'post_id'     => $post_id,
            'title'       => $post->post_title,
            'block_count' => count( $blocks ),
            'blocks'      => $blocks,
        ] );
    }

    public static function ajax_migrate_single() {
        check_ajax_referer( self::MIGRATE_NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        $post    = get_post( $post_id );

        if ( ! $post ) {
            wp_send_json_error( 'Post not found.' );
        }

        $count       = self::count_migrate_blocks( $post->post_content );
        $new_content = self::convert_content( $post->post_content );

        if ( $new_content === $post->post_content ) {
            wp_send_json_error( 'No legacy code blocks found in this post.' );
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            [ 'post_content' => $new_content ],
            [ 'ID' => $post_id ],
            [ '%s' ],
            [ '%d' ]
        );
        clean_post_cache( $post_id );

        wp_send_json_success( [
            'post_id'         => $post_id,
            'blocks_migrated' => $count,
            'message'         => "Migrated {$count} block(s) in \"{$post->post_title}\".",
        ] );
    }

    public static function ajax_migrate_all() {
        check_ajax_referer( self::MIGRATE_NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        global $wpdb;

        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_content
             FROM {$wpdb->posts}
             WHERE post_type IN ('post', 'page')
               AND post_status != 'trash'
               AND (
                   post_content LIKE '%<!-- wp:code %'
                OR post_content LIKE '%<!-- wp:code-->%'
                OR post_content LIKE '%<!-- wp:code-syntax-block/code%'
                OR post_content LIKE '%<!-- wp:preformatted%'
               )
             ORDER BY ID ASC"
        );

        $migrated_posts  = 0;
        $migrated_blocks = 0;
        $details         = [];

        foreach ( $posts as $post ) {
            $count = self::count_migrate_blocks( $post->post_content );
            if ( $count === 0 ) {
                continue;
            }

            $new_content = self::convert_content( $post->post_content );

            if ( $new_content !== $post->post_content ) {
                $wpdb->update(
                    $wpdb->posts,
                    [ 'post_content' => $new_content ],
                    [ 'ID' => $post->ID ],
                    [ '%s' ],
                    [ '%d' ]
                );
                clean_post_cache( $post->ID );

                $migrated_posts++;
                $migrated_blocks += $count;
                $details[] = "#{$post->ID}: {$post->post_title} ({$count} blocks)";
            }
        }

        wp_send_json_success( [
            'migrated_posts'  => $migrated_posts,
            'migrated_blocks' => $migrated_blocks,
            'details'         => $details,
        ] );
    }
}

// Register the AJAX save handler separately (needs to be outside the class init for the hook name)
add_action( 'wp_ajax_cs_save_theme_setting', [ 'CloudScale_Code_Block', 'ajax_save_theme_setting' ] );

CloudScale_Code_Block::init();
