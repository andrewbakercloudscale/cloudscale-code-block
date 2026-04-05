<?php
/**
 * Plugin Name: CloudScale Code Block
 * Plugin URI: https://your-wordpress-site.example.com
 * Description: Syntax highlighted code block with auto language detection, clipboard copy, dark/light mode toggle, code block migrator, and read only SQL query tool. Works as a Gutenberg block and as a [cs_code] shortcode.
 * Version: 1.7.53
 * Author: Andrew Baker
 * Author URI: https://your-wordpress-site.example.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: cloudscale-code-block
 *
 * @package CloudScale_Code_Block
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enable DB query saving for the performance monitor.
// Defined unconditionally so frontend page loads are also captured.
// Only the panel rendering checks user capability before outputting data.
if ( ! defined( 'SAVEQUERIES' ) ) {
    define( 'SAVEQUERIES', true );
}

/**
 * CloudScale Code Block — main plugin class.
 *
 * Handles block registration, shortcode, admin tools, settings,
 * the code block migration tool, and the SQL command tool.
 *
 * @package CloudScale_Code_Block
 * @since   1.0.0
 */
class CloudScale_Code_Block {

    const VERSION      = '1.7.47';
    const HLJS_VERSION = '11.11.1';
    const HLJS_CDN     = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/';
    const TOOLS_SLUG   = 'cloudscale-code-sql';
    const MIGRATE_NONCE = 'cs_code_migrate_action';

    /**
     * Returns the theme registry mapping slugs to CDN filenames and colour values.
     *
     * Each entry maps a slug to its dark and light CDN filenames,
     * display label, and background colours for the wrapper/toolbar.
     *
     * @since  1.7.0
     * @return array<string, array<string, string>>
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

    // Performance monitor — static storage.
    /** @var array HTTP calls captured during this request. */
    private static $perf_http_calls = [];
    /** @var float|null Microtime when last HTTP request started. */
    private static $perf_http_timer = null;
    /** @var array PHP errors captured during this request. */
    private static $perf_php_errors = [];
    /** @var array|null Active plugin prefix → slug map cache. */
    private static $perf_plugin_map = null;
    /** @var callable|null Previous PHP error handler to chain into. */
    private static $perf_prev_error_handler = null;
    /** @var string Template filename captured via template_include filter. */
    private static $perf_template = '';
    /** @var array Hook fire stats: [ hook => ['count'=>int,'total_ms'=>float,'max_ms'=>float] ] */
    private static $perf_hooks = [];
    /** @var float|null Timestamp of last hook fire (ms). */
    private static $perf_hook_last_ms = null;
    /** @var string|null Name of last hook fired. */
    private static $perf_hook_last_name = null;

    /**
     * Registers all plugin hooks.
     *
     * @since  1.0.0
     * @return void
     */
    public static function init() {
        add_action( 'init', [ __CLASS__, 'load_textdomain' ] );
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

        // Settings AJAX
        add_action( 'wp_ajax_cs_save_theme_setting', [ __CLASS__, 'ajax_save_theme_setting' ] );

        // Performance monitor — EXPLAIN endpoint.
        add_action( 'wp_ajax_cs_perf_explain',       [ __CLASS__, 'ajax_perf_explain' ] );
        add_action( 'wp_ajax_cs_perf_debug_toggle',  [ __CLASS__, 'ajax_perf_debug_toggle' ] );

        // Performance monitor — data collection runs on every page load.
        add_filter( 'pre_http_request', [ __CLASS__, 'perf_http_before' ], 10, 3 );
        add_action( 'http_api_debug',   [ __CLASS__, 'perf_http_after' ],  10, 5 );
        // If the user enabled debug logging via the panel, activate PHP error logging
        // using ini_set — this works regardless of WP_DEBUG in wp-config.php and
        // survives Docker container rebuilds because the setting lives in the DB.
        if ( get_option( 'cs_perf_debug_logging', false ) ) {
            // phpcs:ignore WordPress.PHP.IniSet.Risky
            @ini_set( 'log_errors', '1' );
            // phpcs:ignore WordPress.PHP.IniSet.Risky
            @ini_set( 'error_log', WP_CONTENT_DIR . '/debug.log' );
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting
            error_reporting( E_ALL );
        }

        // Register error handler late (priority 9999 on plugins_loaded) so we sit
        // on top of any handler registered by other plugins (e.g. Query Monitor).
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
        add_action( 'plugins_loaded', function () {
            self::$perf_prev_error_handler = set_error_handler(
                [ __CLASS__, 'perf_error_handler' ],
                E_WARNING | E_NOTICE | E_DEPRECATED | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED
            );
        }, 9999 );

        // Performance monitor — panel rendering (admin pages).
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'perf_enqueue' ] );
        add_action( 'admin_footer',          [ __CLASS__, 'perf_output_panel' ], 9999 );

        // Performance monitor — panel rendering (frontend, admin users only).
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'perf_frontend_enqueue' ] );
        add_action( 'wp_footer',          [ __CLASS__, 'perf_output_panel' ], 9999 );

        // Capture the active template filename for the page-context strip.
        add_filter( 'template_include', [ __CLASS__, 'perf_capture_template' ], 9999 );

        // Hook timing tracker — fires on every action/filter.
        add_action( 'all', [ __CLASS__, 'perf_hook_tracker' ] );

        // Scripts & styles — collect at footer time (after everything is enqueued).
        add_action( 'admin_footer', [ __CLASS__, 'perf_capture_assets' ], 1 );
        add_action( 'wp_footer',    [ __CLASS__, 'perf_capture_assets' ], 1 );
    }

    /* ==================================================================
       0. TEXT DOMAIN
       ================================================================== */

    /**
     * Loads the plugin text domain for translations.
     *
     * @since  1.0.0
     * @return void
     */
    public static function load_textdomain(): void {
        load_plugin_textdomain(
            'cloudscale-code-block',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }

    /* ==================================================================
       1. BLOCK REGISTRATION
       ================================================================== */

    /**
     * Registers the block type and all its scripts and stylesheets.
     *
     * @since  1.0.0
     * @return void
     */
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

    /**
     * Enqueues the block editor auto-convert script and attaches the toast inline style.
     *
     * @since  1.5.0
     * @return void
     */
    public static function enqueue_convert_script() {
        wp_enqueue_script(
            'cs-code-block-convert',
            plugins_url( 'assets/cs-convert.js', __FILE__ ),
            [ 'wp-blocks', 'wp-data' ],
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-convert.js' ),
            true
        );
        wp_add_inline_style( 'cs-code-block-editor', self::get_convert_toast_css() );
    }

    /**
     * Returns the CSS string for the block editor convert-all toast notification.
     *
     * @since  1.7.17
     * @return string
     */
    private static function get_convert_toast_css(): string {
        return '#cs-convert-all-toast{'
            . 'position:fixed;bottom:24px;right:24px;z-index:999999;'
            . 'background:linear-gradient(135deg,#1e3a5f 0%,#0d9488 100%);'
            . 'color:#fff;padding:16px 20px;border-radius:10px;'
            . 'box-shadow:0 8px 32px rgba(0,0,0,0.3);'
            . 'display:flex;align-items:center;gap:16px;'
            . 'font-size:14px;font-weight:500;'
            . 'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;'
            . 'animation:cs-toast-in 0.3s ease-out;'
            . '}'
            . '#cs-convert-all-toast button{'
            . 'background:#fff;color:#1e3a5f;font-weight:700;border-radius:6px;'
            . 'padding:10px 24px;font-size:14px;border:none;white-space:nowrap;'
            . 'cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,0.15);font-family:inherit;'
            . '}'
            . '#cs-convert-all-toast button:hover{background:#f0fdf4;}'
            . '@keyframes cs-toast-in{'
            . 'from{opacity:0;transform:translateY(20px);}'
            . 'to{opacity:1;transform:translateY(0);}'
            . '}';
    }

    /* ==================================================================
       2. RENDER (shared by block + shortcode)
       ================================================================== */

    /**
     * Renders a code block on the frontend.
     *
     * @since  1.0.0
     * @param  array  $attributes    Block attributes.
     * @param  string $block_content Existing block content (unused).
     * @return string HTML output.
     */
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

    /**
     * Builds the full HTML markup for a code block.
     *
     * @since  1.0.0
     * @param  string $id    Unique HTML element ID.
     * @param  string $code  Code content to display.
     * @param  string $lang  Language identifier for highlight.js, or empty for auto-detect.
     * @param  string $title Optional filename or title label.
     * @param  string $theme Per-block colour-theme override slug, or empty for site default.
     * @return string HTML markup.
     */
    private static function build_html( $id, $code, $lang, $title, $theme ) {
        $lang_class = $lang ? 'language-' . esc_attr( $lang ) : '';

        $cloudscale_link = '<a class="cs-code-brand" href="https://your-wordpress-site.example.com/2026/02/27/building-a-better-code-block-for-wordpress-cloudscale-code-block-plugin/" target="_blank" rel="noopener noreferrer"><span class="cs-brand-bolt">&#9889;</span> Powered by CloudScale</a>';

        $title_html = '';
        if ( $title ) {
            $title_html = '<div class="cs-code-title">' . esc_html( $title ) . '</div>';
        }

        ob_start();
        ?>
        <div class="cs-code-wrapper" id="<?php echo esc_attr( $id ); ?>"<?php if ( $theme ) { echo ' data-theme="' . esc_attr( $theme ) . '"'; } ?>>
            <div class="cs-code-toolbar">
                <?php echo wp_kses_post( $cloudscale_link ); ?>
                <?php echo wp_kses_post( $title_html ); ?>
                <div class="cs-code-actions">
                    <span class="cs-code-lang-badge"></span>
                    <button class="cs-code-lines-toggle" title="<?php esc_attr_e( 'Toggle line numbers', 'cloudscale-code-block' ); ?>" aria-label="<?php esc_attr_e( 'Toggle line numbers', 'cloudscale-code-block' ); ?>">
                        <svg class="cs-icon-lines" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><text x="4" y="7" font-size="7" fill="currentColor" stroke="none" font-family="monospace">1</text><text x="4" y="13" font-size="7" fill="currentColor" stroke="none" font-family="monospace">2</text><text x="4" y="19" font-size="7" fill="currentColor" stroke="none" font-family="monospace">3</text></svg>
                    </button>
                    <button class="cs-code-theme-toggle" title="<?php esc_attr_e( 'Toggle light/dark mode', 'cloudscale-code-block' ); ?>" aria-label="<?php esc_attr_e( 'Toggle theme', 'cloudscale-code-block' ); ?>">
                        <svg class="cs-icon-sun" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        <svg class="cs-icon-moon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </button>
                    <button class="cs-code-copy" title="<?php esc_attr_e( 'Copy to clipboard', 'cloudscale-code-block' ); ?>" aria-label="<?php esc_attr_e( 'Copy code', 'cloudscale-code-block' ); ?>">
                        <svg class="cs-icon-copy" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <svg class="cs-icon-check" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <span class="cs-copy-label"><?php esc_html_e( 'Copy', 'cloudscale-code-block' ); ?></span>
                    </button>
                </div>
            </div>
            <div class="cs-code-body">
                <pre><code class="<?php echo esc_attr( $lang_class ); ?>"><?php echo esc_html( $code ); ?></code></pre>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueues frontend scripts and styles on first block render, then localises config.
     *
     * @since  1.0.0
     * @return void
     */
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

    /**
     * Registers the [cs_code] shortcode.
     *
     * @since  1.0.0
     * @return void
     */
    public static function register_shortcode() {
        add_shortcode( 'cs_code', [ __CLASS__, 'render_shortcode' ] );
    }

    /**
     * Renders the [cs_code] shortcode.
     *
     * @since  1.0.0
     * @param  array       $atts    Shortcode attributes.
     * @param  string|null $content Shortcode content.
     * @return string HTML output.
     */
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

    /**
     * Decodes WordPress-mangled HTML entities and line breaks from shortcode content.
     *
     * @since  1.0.0
     * @param  string|null $content Raw shortcode content.
     * @return string Plain-text code with entities decoded.
     */
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

    /**
     * Registers plugin settings with sanitise callbacks.
     *
     * @since  1.0.0
     * @return void
     */
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

        register_setting( 'cs_code_settings', 'cs_perf_monitor_enabled', [
            'type'              => 'string',
            'sanitize_callback' => function ( $val ) {
                return '0' === $val ? '0' : '1';
            },
            'default' => '1',
        ] );
    }

    /* ==================================================================
       5. COMBINED TOOLS PAGE (Code Block Migrator + SQL Command)
       ================================================================== */

    /**
     * Adds the combined Tools page to the WordPress admin menu.
     *
     * @since  1.6.0
     * @return void
     */
    public static function add_tools_page() {
        add_management_page(
            'CloudScale Code and SQL',
            '🌩️ CloudScale Code and SQL',
            'manage_options',
            self::TOOLS_SLUG,
            [ __CLASS__, 'render_tools_page' ]
        );
    }

    /**
     * Conditionally enqueues admin assets on the plugin tools page only.
     *
     * @since  1.6.0
     * @param  string $hook Current admin page hook suffix.
     * @return void
     */
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

        // Settings save JS
        wp_enqueue_script(
            'cs-admin-settings',
            plugins_url( 'assets/cs-admin-settings.js', __FILE__ ),
            [],
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-admin-settings.js' ),
            true
        );
        wp_localize_script( 'cs-admin-settings', 'csAdminSettings', [
            'nonce' => wp_create_nonce( 'cs_code_settings_inline' ),
        ] );

        // SQL editor JS
        wp_enqueue_script(
            'cs-sql-editor',
            plugins_url( 'assets/cs-sql-editor.js', __FILE__ ),
            [],
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-sql-editor.js' ),
            true
        );
        wp_localize_script( 'cs-sql-editor', 'csSqlEditor', [
            'nonce' => wp_create_nonce( 'cs_sql_nonce' ),
        ] );
    }

    /**
     * Renders the combined Code Migrator and SQL Command tools page.
     *
     * @since  1.6.0
     * @return void
     */
    public static function render_tools_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'migrate';
        $base_url   = admin_url( 'tools.php?page=' . self::TOOLS_SLUG );
        ?>
        <div class="wrap">
        <div id="cs-app">

            <!-- Banner -->
            <div id="cs-banner">
                <div>
                    <div id="cs-banner-title">⚡ CloudScale Code and SQL</div>
                    <div id="cs-banner-sub"><?php esc_html_e( 'Syntax highlighting, code migration, and database query tool', 'cloudscale-code-block' ); ?> &middot; v<?php echo esc_html( self::VERSION ); ?></div>
                </div>
                <div id="cs-banner-right">
                    <span class="cs-badge cs-badge-green">✅ <?php esc_html_e( 'Totally Free', 'cloudscale-code-block' ); ?></span>
                    <span class="cs-badge cs-badge-orange">andrewbaker.ninja</span>
                </div>
            </div>

            <!-- Tab bar -->
            <div id="cs-tab-bar">
                <a href="<?php echo esc_url( $base_url . '&tab=migrate' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'migrate' ? 'active' : ''; ?>">
                    🔄 <?php esc_html_e( 'Code Migrator', 'cloudscale-code-block' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=sql' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'sql' ? 'active' : ''; ?>">
                    🗄️ <?php esc_html_e( 'SQL Command', 'cloudscale-code-block' ); ?>
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

    /**
     * Renders the Code Block Settings panel (colour theme and default mode selectors).
     *
     * @since  1.6.0
     * @return void
     */
    private static function render_settings_panel() {
        $theme       = get_option( 'cs_code_default_theme', 'dark' );
        $pair_slug   = get_option( 'cs_code_theme_pair', 'atom-one' );
        $perf_on     = get_option( 'cs_perf_monitor_enabled', '1' ) !== '0';
        $registry    = self::get_theme_registry();
        ?>
        <div class="cs-panel">
            <div class="cs-section-header cs-section-header-teal">
                <span>🎨 CODE BLOCK SETTINGS</span>
            </div>
            <div class="cs-panel-body">
                <div class="cs-field-row">
                    <div class="cs-field">
                        <label class="cs-label" for="cs-settings-pair"><?php esc_html_e( 'Color Theme:', 'cloudscale-code-block' ); ?></label>
                        <select id="cs-settings-pair" name="cs_code_theme_pair" class="cs-input">
                            <?php foreach ( $registry as $slug => $info ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $pair_slug, $slug ); ?>>
                                    <?php echo esc_html( $info['label'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="cs-hint"><?php esc_html_e( 'Syntax highlighting color scheme loaded from CDN.', 'cloudscale-code-block' ); ?></span>
                    </div>
                    <div class="cs-field">
                        <label class="cs-label" for="cs-settings-theme"><?php esc_html_e( 'Default Mode:', 'cloudscale-code-block' ); ?></label>
                        <select id="cs-settings-theme" name="cs_code_default_theme" class="cs-input">
                            <option value="dark" <?php selected( $theme, 'dark' ); ?>><?php esc_html_e( 'Dark', 'cloudscale-code-block' ); ?></option>
                            <option value="light" <?php selected( $theme, 'light' ); ?>><?php esc_html_e( 'Light', 'cloudscale-code-block' ); ?></option>
                        </select>
                        <span class="cs-hint"><?php esc_html_e( 'Visitors can still toggle per block.', 'cloudscale-code-block' ); ?></span>
                    </div>
                </div>
                <div class="cs-field" style="margin-top:14px">
                    <label class="cs-label"><?php esc_html_e( 'CS Monitor panel:', 'cloudscale-code-block' ); ?></label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" id="cs-settings-perf-enabled" name="cs_perf_monitor_enabled" value="1" <?php checked( $perf_on ); ?>>
                        <span style="font-size:13px;color:#555"><?php esc_html_e( 'Show the ⚡ CS Monitor performance panel', 'cloudscale-code-block' ); ?></span>
                    </label>
                    <span class="cs-hint"><?php esc_html_e( 'Visible to admins only. Uncheck to hide the panel on all pages.', 'cloudscale-code-block' ); ?></span>
                </div>
                <div style="margin-top:14px;display:flex;align-items:center;gap:10px">
                    <button type="button" class="cs-btn-primary" id="cs-settings-save">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-code-block' ); ?></button>
                    <span class="cs-settings-saved" id="cs-settings-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-code-block' ); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    /* ==================================================================
       5b. Migrate panel
       ================================================================== */

    /**
     * Renders the Code Block Migrator panel.
     *
     * @since  1.5.0
     * @return void
     */
    private static function render_migrate_panel() {
        ?>
        <div class="cs-panel">
            <div class="cs-section-header">
                <span>🔄 CODE BLOCK MIGRATOR</span>
            </div>
            <div class="cs-panel-body">
                <p style="color:#555;margin:0 0 16px;font-size:13px;line-height:1.6">
                    <?php esc_html_e( 'Scan your posts for legacy WordPress code blocks, preview changes, then migrate one at a time or all at once.', 'cloudscale-code-block' ); ?>
                </p>

                <div class="cs-migrate-toolbar">
                    <button id="cs-scan-btn" class="cs-btn-primary" style="padding:8px 20px;font-size:13px">
                        <span class="dashicons dashicons-search" style="font-size:14px;width:14px;height:14px;margin-top:1px"></span> <?php esc_html_e( 'Scan Posts', 'cloudscale-code-block' ); ?>
                    </button>
                    <button id="cs-migrate-all-btn" class="cs-btn-orange" style="padding:8px 20px;font-size:13px" disabled>
                        <span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;margin-top:1px"></span> <?php esc_html_e( 'Migrate All Remaining', 'cloudscale-code-block' ); ?>
                    </button>
                    <span id="cs-scan-status" class="cs-status"></span>
                </div>

                <div id="cs-results-area">
                    <p class="cs-migrate-hint"><?php printf( __( 'Click %s to find all posts with legacy code blocks.', 'cloudscale-code-block' ), '<strong>' . esc_html__( 'Scan Posts', 'cloudscale-code-block' ) . '</strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- format string is hardcoded, only %s is user-visible and escaped above ?></p>
                </div>
            </div>
        </div>

        <div id="cs-preview-modal" class="cs-modal" style="display:none;">
            <div class="cs-modal-backdrop"></div>
            <div class="cs-modal-content">
                <div class="cs-modal-header">
                    <h2 id="cs-modal-title"><?php esc_html_e( 'Preview', 'cloudscale-code-block' ); ?></h2>
                    <button class="cs-modal-close">&times;</button>
                </div>
                <div class="cs-modal-body" id="cs-modal-body">
                    <?php esc_html_e( 'Loading...', 'cloudscale-code-block' ); ?>
                </div>
                <div class="cs-modal-footer">
                    <button id="cs-modal-migrate-btn" class="cs-btn-primary" data-post-id="" style="padding:8px 20px">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Migrate This Post', 'cloudscale-code-block' ); ?>
                    </button>
                    <button class="cs-modal-close-btn" style="background:#fff;border:1.5px solid #dce3ef;border-radius:5px;padding:6px 16px;font-size:12px;font-weight:600;cursor:pointer"><?php esc_html_e( 'Cancel', 'cloudscale-code-block' ); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /* ==================================================================
       5c. SQL Command panel
       ================================================================== */

    /**
     * Renders the SQL Command query panel including quick-query buttons.
     *
     * @since  1.6.0
     * @return void
     */
    private static function render_sql_panel() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        ?>
        <div class="cs-panel">
            <div class="cs-section-header cs-section-header-purple">
                <span>🗄️ SQL Query</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Table prefix:', 'cloudscale-code-block' ); ?> <code><?php echo esc_html( $prefix ); ?></code> &nbsp;·&nbsp; ⚠ <?php esc_html_e( 'Read only (SELECT, SHOW, DESCRIBE, EXPLAIN)', 'cloudscale-code-block' ); ?></span>
            </div>
            <div class="cs-panel-body">
                <textarea id="cs-sql-input" class="cs-sql-textarea" placeholder="SELECT option_name, option_value FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_name = 'siteurl';"></textarea>
                <div style="display:flex;align-items:center;gap:10px;margin-top:12px">
                    <button type="button" class="cs-btn-primary" id="cs-sql-run" style="padding:8px 20px;font-size:13px">▶ <?php esc_html_e( 'Run Query', 'cloudscale-code-block' ); ?></button>
                    <button type="button" class="cs-btn-pink" id="cs-sql-clear">🧹 <?php esc_html_e( 'Clear', 'cloudscale-code-block' ); ?></button>
                    <span id="cs-sql-status" style="font-size:12px;color:#888"></span>
                    <span style="margin-left:auto;font-size:11px;color:#999"><?php esc_html_e( 'Enter or Ctrl+Enter to run', 'cloudscale-code-block' ); ?></span>
                </div>
            </div>
        </div>

        <div class="cs-panel">
            <div class="cs-section-header cs-section-header-green">
                <span>📊 <?php esc_html_e( 'Results', 'cloudscale-code-block' ); ?></span>
                <span id="cs-sql-meta" style="font-size:12px;opacity:0.85"></span>
            </div>
            <div class="cs-panel-body">
                <div id="cs-sql-results" style="overflow-x:auto;font-size:13px">
                    <div style="text-align:center;color:#999;padding:40px 0"><?php esc_html_e( 'Run a query to see results here', 'cloudscale-code-block' ); ?></div>
                </div>
            </div>
        </div>

        <div class="cs-panel">
            <div class="cs-section-header cs-section-header-orange">
                <span>⚡ <?php esc_html_e( 'Quick Queries', 'cloudscale-code-block' ); ?></span>
            </div>
            <div class="cs-panel-body">
                <p class="cs-quick-group-label">🏥 <?php esc_html_e( 'Health and Diagnostics', 'cloudscale-code-block' ); ?></p>
                <div class="cs-quick-grid">
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT @@version AS mysql_version, @@global.max_connections AS max_connections, @@global.wait_timeout AS wait_timeout_sec, @@global.max_allowed_packet / 1024 / 1024 AS max_packet_mb, DATABASE() AS current_db;">
                        🩺 <?php esc_html_e( 'Database health check', 'cloudscale-code-block' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT option_id, option_name, LEFT(option_value, 200) AS option_value_preview FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_name IN ('siteurl','home','blogname','blogdescription','wp_version','db_version');">
                        🏠 <?php esc_html_e( 'Site identity options', 'cloudscale-code-block' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT table_name, engine, table_rows, ROUND(data_length/1024/1024, 2) AS data_mb, ROUND(index_length/1024/1024, 2) AS index_mb, ROUND((data_length + index_length)/1024/1024, 2) AS total_mb FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY (data_length + index_length) DESC;">
                        📊 <?php esc_html_e( 'Table names, sizes and rows', 'cloudscale-code-block' ); ?>
                    </button>
                </div>

                <p class="cs-quick-group-label">📈 <?php esc_html_e( 'Content Summary', 'cloudscale-code-block' ); ?></p>
                <div class="cs-quick-grid">
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT post_type, post_status, COUNT(*) AS total FROM <?php echo esc_attr( $prefix ); ?>posts GROUP BY post_type, post_status ORDER BY total DESC;">
                        📰 <?php esc_html_e( 'Posts by type and status', 'cloudscale-code-block' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status='publish') AS published_posts, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_type='revision') AS revisions, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status='auto-draft') AS auto_drafts, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status='trash') AS trashed, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>comments) AS total_comments, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>comments WHERE comment_approved='spam') AS spam_comments, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>users) AS users, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_name LIKE '%_transient_%') AS transients;">
                        📋 <?php esc_html_e( 'Site stats summary', 'cloudscale-code-block' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT ID, post_title, post_date, post_status FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status = 'publish' AND post_type = 'post' ORDER BY post_date DESC LIMIT 20;">
                        📝 <?php esc_html_e( 'Latest 20 published posts', 'cloudscale-code-block' ); ?>
                    </button>
                </div>

                <p class="cs-quick-group-label">🧹 <?php esc_html_e( 'Bloat and Cleanup Checks', 'cloudscale-code-block' ); ?></p>
                <div class="cs-quick-grid">
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT COUNT(*) AS orphaned_postmeta FROM <?php echo esc_attr( $prefix ); ?>postmeta pm LEFT JOIN <?php echo esc_attr( $prefix ); ?>posts p ON pm.post_id = p.ID WHERE p.ID IS NULL;">
                        🗑️ <?php esc_html_e( 'Orphaned postmeta count', 'cloudscale-code-block' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT COUNT(*) AS expired_transients FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP();">
                        ⏰ <?php esc_html_e( 'Expired transients count', 'cloudscale-code-block' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT post_type, COUNT(*) AS total FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_type = 'revision' OR post_status = 'auto-draft' OR post_status = 'trash' GROUP BY post_type, post_status ORDER BY total DESC;">
                        📦 <?php esc_html_e( 'Revisions, drafts and trash', 'cloudscale-code-block' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT LEFT(option_name, 40) AS option_name, LENGTH(option_value) AS value_bytes FROM <?php echo esc_attr( $prefix ); ?>options WHERE autoload = 'yes' ORDER BY LENGTH(option_value) DESC LIMIT 30;">
                        ⚖️ <?php esc_html_e( 'Largest autoloaded options', 'cloudscale-code-block' ); ?>
                    </button>
                </div>

                <p class="cs-quick-group-label">🔍 <?php esc_html_e( 'URL and Migration Helpers', 'cloudscale-code-block' ); ?></p>
                <div class="cs-quick-grid">
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT option_id, option_name, option_value FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_value LIKE '%http://andrewbaker%';">
                        🔗 <?php esc_html_e( 'HTTP references (andrewbaker)', 'cloudscale-code-block' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT ID, post_title, post_type, post_status, guid FROM <?php echo esc_attr( $prefix ); ?>posts WHERE guid LIKE '%http://%' LIMIT 50;">
                        📰 <?php esc_html_e( 'Posts with HTTP GUIDs', 'cloudscale-code-block' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT post_id, meta_key, LEFT(meta_value, 200) AS meta_value_preview FROM <?php echo esc_attr( $prefix ); ?>postmeta WHERE meta_value LIKE '%http://54.195%' LIMIT 50;">
                        🖥️ <?php esc_html_e( 'Old IP references (postmeta)', 'cloudscale-code-block' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT ID, post_title, post_type FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status = 'publish' AND ID NOT IN (SELECT post_id FROM <?php echo esc_attr( $prefix ); ?>postmeta WHERE meta_key = '_cs_seo_desc' AND meta_value != '') ORDER BY post_date DESC LIMIT 50;">
                        📝 <?php esc_html_e( 'Posts missing meta descriptions', 'cloudscale-code-block' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php
    }

    /* ==================================================================
       6. SQL COMMAND: Query validation + AJAX
       ================================================================== */

    /**
     * Returns true when the SQL string begins with a read-only keyword and contains no semicolons.
     *
     * @since  1.6.0
     * @param  string $sql Raw SQL string to validate.
     * @return bool
     */
    private static function is_safe_query( string $sql ): bool {
        $clean = trim( $sql );
        // Strip all block comments (including mid-query MySQL /*!...*/ optimizer hints),
        // line comments (-- and #), and surrounding whitespace before keyword check.
        $clean = preg_replace( '/\/\*.*?\*\//s', '', $clean );
        $clean = preg_replace( '/(--|#)[^\n]*/m', '', $clean );
        $clean = trim( $clean );
        // Reject any query containing a semicolon — prevents statement stacking
        // (e.g. SELECT 1; DROP TABLE wp_users).
        if ( strpos( $clean, ';' ) !== false ) {
            return false;
        }
        // Reject file-system abuse clauses regardless of SELECT keyword.
        if ( preg_match( '/\b(INTO\s+OUTFILE|INTO\s+DUMPFILE|LOAD_FILE)\b/i', $clean ) ) {
            return false;
        }
        if ( preg_match( '/^(\w+)/i', $clean, $m ) ) {
            $first = strtoupper( $m[1] );
            return in_array( $first, [ 'SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN' ], true );
        }
        return false;
    }

    /**
     * AJAX handler: executes a validated read-only SQL query and returns results as JSON.
     *
     * @since  1.6.0
     * @return void Sends JSON response and exits.
     */
    public static function ajax_sql_run(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 403 );
        }
        if ( ! check_ajax_referer( 'cs_sql_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Bad nonce', 403 );
        }

        $raw = isset( $_POST['sql'] ) ? $_POST['sql'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- raw SQL for admin tool; unslashed on next line, validated via is_safe_query()
        $sql = trim( wp_unslash( $raw ) );
        if ( ! $sql ) {
            wp_send_json_error( 'Empty query' );
        }

        if ( ! self::is_safe_query( $sql ) ) {
            wp_send_json_error( 'Only SELECT, SHOW, DESCRIBE, and EXPLAIN queries are allowed. Do not include shell commands like sudo or mysql.' );
        }

        global $wpdb;
        $wpdb->suppress_errors( true );
        $start = microtime( true );
        // prepare() cannot be applied to a free-form admin SQL tool — the entire
        // query is the user's input, leaving no placeholders to bind. Safety is
        // provided by is_safe_query() (read-only keywords + no semicolons),
        // manage_options capability gate, and nonce verification above.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

    /**
     * AJAX handler: saves the colour theme and default mode settings.
     *
     * @since  1.6.0
     * @return void Sends JSON response and exits.
     */
    public static function ajax_save_theme_setting(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden' );
        }
        if ( ! check_ajax_referer( 'cs_code_settings_inline', 'nonce', false ) ) {
            wp_send_json_error( 'Bad nonce' );
        }

        $theme = isset( $_POST['theme'] ) ? sanitize_text_field( wp_unslash( $_POST['theme'] ) ) : 'dark';
        if ( ! in_array( $theme, [ 'dark', 'light' ], true ) ) {
            $theme = 'dark';
        }
        update_option( 'cs_code_default_theme', $theme );

        $valid_pairs = array_keys( self::get_theme_registry() );
        $pair        = isset( $_POST['theme_pair'] ) ? sanitize_text_field( wp_unslash( $_POST['theme_pair'] ) ) : 'atom-one';
        if ( ! in_array( $pair, $valid_pairs, true ) ) {
            $pair = 'atom-one';
        }
        update_option( 'cs_code_theme_pair', $pair );

        $perf_enabled = isset( $_POST['cs_perf_monitor_enabled'] ) && '1' === $_POST['cs_perf_monitor_enabled'] ? '1' : '0'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        update_option( 'cs_perf_monitor_enabled', $perf_enabled );

        wp_send_json_success( [ 'theme' => $theme, 'theme_pair' => $pair, 'perf_enabled' => $perf_enabled ] );
    }
    /* ==================================================================
       7. MIGRATION TOOL

       ================================================================== */

    /* ==================================================================
       7a. Migration: Block conversion logic
       ================================================================== */

    /**
     * Returns the regex pattern that matches legacy wp:code blocks.
     *
     * @since  1.5.0
     * @return string PCRE pattern string.
     */
    private static function get_code_pattern() {
        return '#<!-- wp:(code-syntax-block/code|code)\s*(\{[^}]*\})?\s*-->\s*'
             . '<pre[^>]*class="[^"]*wp-block-code[^"]*"[^>]*>\s*'
             . '<code([^>]*)>(.*?)</code>\s*'
             . '</pre>\s*'
             . '<!-- /wp:\1\s*-->#s';
    }

    /**
     * Returns the regex pattern that matches legacy wp:preformatted blocks.
     *
     * @since  1.5.0
     * @return string PCRE pattern string.
     */
    private static function get_preformatted_pattern() {
        return '#<!-- wp:preformatted\s*(\{[^}]*\})?\s*-->\s*'
             . '<pre[^>]*class="[^"]*wp-block-preformatted[^"]*"[^>]*>(.*?)</pre>\s*'
             . '<!-- /wp:preformatted\s*-->#s';
    }

    /**
     * Converts a matched legacy wp:code block into a CloudScale block comment.
     *
     * @since  1.5.0
     * @param  array $matches preg_replace_callback match array.
     * @return string New block comment markup.
     */
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

    /**
     * Converts a matched legacy wp:preformatted block into a CloudScale block comment.
     *
     * @since  1.5.0
     * @param  array $matches preg_replace_callback match array.
     * @return string New block comment markup.
     */
    private static function convert_preformatted_block( $matches ) {
        $code_content = $matches[2] ?? '';

        $code = str_ireplace( [ '<br>', '<br/>', '<br />' ], "\n", $code_content );
        $code = strip_tags( $code );
        $code = html_entity_decode( $code, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $code = rtrim( $code, "\n" );

        return self::build_migrate_block( $code, '' );
    }

    /**
     * Builds a CloudScale block comment from code content and an optional language slug.
     *
     * @since  1.5.0
     * @param  string $code Code content.
     * @param  string $lang Language identifier, or empty string for auto-detect.
     * @return string Block comment markup.
     */
    private static function build_migrate_block( $code, $lang ) {
        $attrs = [ 'content' => $code ];
        if ( ! empty( $lang ) ) {
            $attrs['language'] = $lang;
        }

        $attrs_json = wp_json_encode( $attrs );

        return '<!-- wp:cloudscale/code-block ' . $attrs_json . ' /-->';
    }

    /**
     * Counts the total number of legacy code blocks in post content.
     *
     * @since  1.5.0
     * @param  string $content Post content.
     * @return int Number of legacy code blocks found.
     */
    private static function count_migrate_blocks( $content ) {
        $count  = preg_match_all( self::get_code_pattern(), $content, $m );
        $count += preg_match_all( self::get_preformatted_pattern(), $content, $m );
        return $count;
    }

    /**
     * Converts all legacy code and preformatted blocks in post content to CloudScale blocks.
     *
     * @since  1.5.0
     * @param  string $content Post content.
     * @return string Post content with legacy blocks replaced.
     */
    private static function convert_content( $content ) {
        $content = preg_replace_callback( self::get_code_pattern(), [ __CLASS__, 'convert_code_block' ], $content );
        $content = preg_replace_callback( self::get_preformatted_pattern(), [ __CLASS__, 'convert_preformatted_block' ], $content );
        return $content;
    }

    /**
     * Truncates a string to a maximum byte length, appending an ellipsis when cut.
     *
     * @since  1.5.0
     * @param  string $str String to truncate.
     * @param  int    $max Maximum byte length.
     * @return string Truncated string.
     */
    private static function truncate_block( $str, $max ) {
        if ( strlen( $str ) <= $max ) {
            return $str;
        }
        return substr( $str, 0, $max ) . "\n... [truncated]";
    }

    /**
     * Builds a before/after preview array for all legacy blocks in post content.
     *
     * @since  1.5.0
     * @param  string $content Post content.
     * @return array<int, array<string, mixed>> Preview data for each block found.
     */
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

    /**
     * AJAX handler: scans all posts for legacy code blocks and returns a list.
     *
     * @since  1.5.0
     * @return void Sends JSON response and exits.
     */
    public static function ajax_scan() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 403 );
        }

        if ( ! check_ajax_referer( self::MIGRATE_NONCE, 'nonce', false ) ) {
            wp_send_json_error( 'Bad nonce', 403 );
        }

        global $wpdb;

        // Static query — no user data; $wpdb->posts is a trusted WP core property.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
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
                    'date'        => wp_date( 'd M Y', strtotime( $post->post_date ) ),
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

    /**
     * AJAX handler: returns a before/after preview of the migration for a single post.
     *
     * @since  1.5.0
     * @return void Sends JSON response and exits.
     */
    public static function ajax_preview() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 403 );
        }

        if ( ! check_ajax_referer( self::MIGRATE_NONCE, 'nonce', false ) ) {
            wp_send_json_error( 'Bad nonce', 403 );
        }

        $post_id = (int) ( $_POST['post_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised via (int) cast
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

    /**
     * AJAX handler: migrates all legacy code blocks in a single post.
     *
     * @since  1.5.0
     * @return void Sends JSON response and exits.
     */
    public static function ajax_migrate_single() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 403 );
        }

        if ( ! check_ajax_referer( self::MIGRATE_NONCE, 'nonce', false ) ) {
            wp_send_json_error( 'Bad nonce', 403 );
        }

        $post_id = (int) ( $_POST['post_id'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised via (int) cast
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
            'message'         => 'Migrated ' . $count . ' block(s) in "' . esc_html( $post->post_title ) . '".',
        ] );
    }

    /**
     * AJAX handler: migrates all legacy code blocks across all matching posts.
     *
     * @since  1.5.0
     * @return void Sends JSON response and exits.
     */
    public static function ajax_migrate_all() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 403 );
        }

        if ( ! check_ajax_referer( self::MIGRATE_NONCE, 'nonce', false ) ) {
            wp_send_json_error( 'Bad nonce', 403 );
        }

        global $wpdb;

        // Static query — no user data; $wpdb->posts is a trusted WP core property.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
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
                $details[] = '#' . $post->ID . ': ' . esc_html( $post->post_title ) . ' (' . $count . ' blocks)';
            }
        }

        wp_send_json_success( [
            'migrated_posts'  => $migrated_posts,
            'migrated_blocks' => $migrated_blocks,
            'details'         => $details,
        ] );
    }

    /* ==================================================================
       PERFORMANCE MONITOR — HTTP capture
       ================================================================== */

    /**
     * Records microtime before each outbound HTTP request starts.
     *
     * Returns the $pre value unchanged so it never short-circuits the request.
     *
     * @param  false|array|\WP_Error $pre  Pre-emptive response or false.
     * @param  array                 $args Request arguments.
     * @param  string                $url  Request URL.
     * @return false|array|\WP_Error
     */
    public static function perf_http_before( $pre, $args, $url ) {
        self::$perf_http_timer = microtime( true );
        return $pre;
    }

    /**
     * Captures a completed HTTP request into the performance monitor data store.
     *
     * @param  array|\WP_Error $response    HTTP response or WP_Error.
     * @param  string          $context     Transport context string.
     * @param  string          $class       WP_HTTP transport class name.
     * @param  array           $parsed_args Parsed request arguments.
     * @param  string          $url         Request URL.
     * @return void
     */
    public static function perf_http_after( $response, $context, $class, $parsed_args, $url ) {
        $elapsed_ms            = self::$perf_http_timer
            ? round( ( microtime( true ) - self::$perf_http_timer ) * 1000, 2 )
            : 0;
        self::$perf_http_timer = null;

        $status = 0;
        $cached = false;
        $error  = null;

        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
        } else {
            $status  = (int) wp_remote_retrieve_response_code( $response );
            $headers = wp_remote_retrieve_headers( $response );
            // Detect CDN / proxy cache hits.
            if ( ! empty( $headers['x-cache'] ) && false !== stripos( $headers['x-cache'], 'HIT' ) ) {
                $cached = true;
            } elseif ( ! empty( $headers['cf-cache-status'] ) && 'HIT' === strtoupper( $headers['cf-cache-status'] ) ) {
                $cached = true;
            } elseif ( ! empty( $headers['x-wp-cache'] ) && 'HIT' === strtoupper( $headers['x-wp-cache'] ) ) {
                $cached = true;
            }
        }

        // Use a real file-path backtrace for accurate plugin attribution.
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
        $bt     = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 25 );
        $plugin = self::perf_plugin_from_frames( $bt );

        self::$perf_http_calls[] = [
            'url'     => $url,
            'method'  => strtoupper( $parsed_args['method'] ?? 'GET' ),
            'status'  => $status,
            'time_ms' => $elapsed_ms,
            'cached'  => $cached,
            'plugin'  => $plugin,
            'error'   => $error,
        ];
    }

    /**
     * Captures PHP warnings, notices, and deprecations into the performance monitor store.
     *
     * Chains to any previously registered error handler so existing error reporting
     * (WP_DEBUG display, logging) continues to work unaffected.
     *
     * @param  int    $errno   Error number / level bitmask.
     * @param  string $errstr  Error message.
     * @param  string $errfile File where the error occurred.
     * @param  int    $errline Line number where the error occurred.
     * @return bool   false to allow PHP's default handler to also run.
     */
    public static function perf_error_handler( int $errno, string $errstr, string $errfile = '', int $errline = 0 ): bool {
        static $count = 0;
        if ( $count < 75 ) {
            $count++;
            $levels = [
                E_WARNING         => 'Warning',
                E_NOTICE          => 'Notice',
                E_DEPRECATED      => 'Deprecated',
                E_USER_WARNING    => 'Warning',
                E_USER_NOTICE     => 'Notice',
                E_USER_DEPRECATED => 'Deprecated',
            ];
            self::$perf_php_errors[] = [
                'level'   => $levels[ $errno ] ?? 'Notice',
                'message' => $errstr,
                'file'    => defined( 'ABSPATH' ) ? str_replace( ABSPATH, '', $errfile ) : $errfile,
                'line'    => $errline,
            ];
        }

        // Chain to the previous handler (e.g. WordPress debug display/logging).
        if ( is_callable( self::$perf_prev_error_handler ) ) {
            return (bool) call_user_func( self::$perf_prev_error_handler, $errno, $errstr, $errfile, $errline );
        }

        return false; // let PHP's built-in handler continue.
    }

    /* ==================================================================
       PERFORMANCE MONITOR — Admin assets + panel output
       ================================================================== */

    /**
     * Enqueues the performance monitor CSS and JS on all admin pages for admins.
     *
     * @param  string $hook Current admin page hook suffix.
     * @return void
     */
    public static function perf_enqueue( string $hook ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $base = plugin_dir_path( __FILE__ ) . 'assets/';
        $bust = filemtime( $base . 'cs-perf-monitor.css' );
        wp_enqueue_style(
            'cs-perf-monitor',
            plugins_url( 'assets/cs-perf-monitor.css', __FILE__ ),
            [],
            $bust
        );
        wp_enqueue_script(
            'cs-perf-monitor',
            plugins_url( 'assets/cs-perf-monitor.js', __FILE__ ),
            [],
            $bust,
            true
        );
    }

    /**
     * Stores the template filename so it can be included in panel meta context.
     *
     * @param  string $template Full path to the active template file.
     * @return string           Unchanged template path.
     */
    public static function perf_capture_template( string $template ): string {
        self::$perf_template = basename( $template );
        return $template;
    }

    /**
     * Tracks every action/filter fire for hook timing.
     * Called via add_action('all', ...) so receives the current hook name automatically.
     *
     * @return void
     */
    public static function perf_hook_tracker(): void {
        $hook = current_filter();
        $now  = microtime( true ) * 1000;

        // Close out the previous hook's timing.
        if ( null !== self::$perf_hook_last_ms && null !== self::$perf_hook_last_name ) {
            $elapsed = $now - self::$perf_hook_last_ms;
            $prev    = self::$perf_hook_last_name;
            if ( ! isset( self::$perf_hooks[ $prev ] ) ) {
                self::$perf_hooks[ $prev ] = [ 'count' => 0, 'total_ms' => 0.0, 'max_ms' => 0.0 ];
            }
            self::$perf_hooks[ $prev ]['count']++;
            self::$perf_hooks[ $prev ]['total_ms'] += $elapsed;
            if ( $elapsed > self::$perf_hooks[ $prev ]['max_ms'] ) {
                self::$perf_hooks[ $prev ]['max_ms'] = $elapsed;
            }
        }

        self::$perf_hook_last_ms   = $now;
        self::$perf_hook_last_name = $hook;
    }

    /**
     * Captures all enqueued scripts and styles at footer time (priority 1).
     *
     * @return void
     */
    public static function perf_capture_assets(): void {
        // Only needs to run once; admin_footer and wp_footer both call this.
        static $done = false;
        if ( $done ) {
            return;
        }
        $done = true;
    }

    /**
     * Builds the scripts & styles payload for the panel.
     *
     * @return array{ scripts: array, styles: array }
     */
    private static function perf_build_assets_data(): array {
        $scripts_obj = wp_scripts();
        $styles_obj  = wp_styles();

        // WP registers scripts/styles with src=false (inline-only); cast to string
        // so the JS side always receives a string, never a boolean false.
        $scripts = [];
        foreach ( $scripts_obj->done as $handle ) {
            if ( ! isset( $scripts_obj->registered[ $handle ] ) ) {
                continue;
            }
            $dep = $scripts_obj->registered[ $handle ];
            $src = is_string( $dep->src ) ? $dep->src : '';
            $scripts[] = [
                'handle' => (string) $handle,
                'src'    => $src,
                'plugin' => self::perf_attr_asset( $src ),
                'ver'    => is_string( $dep->ver ) ? $dep->ver : ( $dep->ver ? (string) $dep->ver : '' ),
            ];
        }

        $styles = [];
        foreach ( $styles_obj->done as $handle ) {
            if ( ! isset( $styles_obj->registered[ $handle ] ) ) {
                continue;
            }
            $dep = $styles_obj->registered[ $handle ];
            $src = is_string( $dep->src ) ? $dep->src : '';
            $styles[] = [
                'handle' => (string) $handle,
                'src'    => $src,
                'plugin' => self::perf_attr_asset( $src ),
                'ver'    => is_string( $dep->ver ) ? $dep->ver : ( $dep->ver ? (string) $dep->ver : '' ),
            ];
        }

        return [ 'scripts' => $scripts, 'styles' => $styles ];
    }

    /**
     * Attributes an asset URL to a plugin or theme slug.
     *
     * @param  string $src Asset URL or path.
     * @return string      Plugin slug, 'theme', 'wp-core', or 'unknown'.
     */
    private static function perf_attr_asset( string $src ): string {
        if ( empty( $src ) ) {
            return 'unknown';
        }
        $content_url = content_url();
        // Strip the site URL to get a relative path for easier matching.
        $rel = str_replace( site_url(), '', $src );

        if ( false !== strpos( $rel, '/plugins/' ) ) {
            if ( preg_match( '#/plugins/([^/]+)/#', $rel, $m ) ) {
                return $m[1];
            }
        }
        if ( false !== strpos( $rel, '/themes/' ) ) {
            return 'theme';
        }
        if ( false !== strpos( $rel, '/wp-includes/' ) || false !== strpos( $rel, '/wp-admin/' ) ) {
            return 'wp-core';
        }
        return 'unknown';
    }

    /**
     * Builds the object-cache stats payload.
     *
     * @return array
     */
    private static function perf_build_cache_data(): array {
        global $wp_object_cache;

        if ( ! is_object( $wp_object_cache ) ) {
            return [ 'available' => false ];
        }

        // Standard WP internal cache (non-persistent).
        $hits   = method_exists( $wp_object_cache, 'cache_hits' )
            ? $wp_object_cache->cache_hits
            : ( $wp_object_cache->hits ?? null );
        $misses = method_exists( $wp_object_cache, 'cache_misses' )
            ? $wp_object_cache->cache_misses
            : ( $wp_object_cache->misses ?? null );

        // Fallback: try public properties directly (most object cache plugins expose these).
        if ( null === $hits )   { $hits   = $wp_object_cache->cache_hits   ?? $wp_object_cache->hits   ?? null; }
        if ( null === $misses ) { $misses = $wp_object_cache->cache_misses ?? $wp_object_cache->misses ?? null; }

        $total    = (int) $hits + (int) $misses;
        $hit_rate = $total > 0 ? round( ( (int) $hits / $total ) * 100, 1 ) : null;

        // Redis / Memcache info string if available.
        $info = null;
        if ( method_exists( $wp_object_cache, 'info' ) ) {
            $raw = $wp_object_cache->info();
            $info = is_string( $raw ) ? $raw : wp_json_encode( $raw );
        }

        // Group stats — available on persistent caches (e.g. Redis Object Cache plugin).
        $groups = [];
        if ( isset( $wp_object_cache->stats ) && is_array( $wp_object_cache->stats ) ) {
            foreach ( $wp_object_cache->stats as $group => $stat ) {
                if ( is_array( $stat ) ) {
                    $groups[] = [
                        'group'   => $group,
                        'hits'    => $stat['hits']   ?? 0,
                        'misses'  => $stat['misses'] ?? 0,
                        'bytes'   => $stat['bytes']  ?? 0,
                    ];
                }
            }
        }

        return [
            'available'  => true,
            'persistent' => ( defined( 'WP_REDIS_VERSION' ) || defined( 'MEMCACHE_VERSION' ) ),
            'hits'       => (int) $hits,
            'misses'     => (int) $misses,
            'hit_rate'   => $hit_rate,
            'info'       => $info,
            'groups'     => $groups,
        ];
    }

    /**
     * Builds the top-N hooks timing payload.
     *
     * @return array
     */
    private static function perf_build_hooks_data(): array {
        $hooks = self::$perf_hooks;
        // Sort by total_ms descending.
        uasort( $hooks, static function ( $a, $b ) {
            return $b['total_ms'] <=> $a['total_ms'];
        } );

        $result = [];
        foreach ( array_slice( $hooks, 0, 50, true ) as $name => $stat ) {
            $result[] = [
                'hook'     => $name,
                'count'    => $stat['count'],
                'total_ms' => round( $stat['total_ms'], 2 ),
                'max_ms'   => round( $stat['max_ms'], 2 ),
                'avg_ms'   => $stat['count'] > 0 ? round( $stat['total_ms'] / $stat['count'], 2 ) : 0,
            ];
        }
        return $result;
    }

    /**
     * Enqueues performance monitor CSS and JS on frontend pages for admin users.
     *
     * @return void
     */
    public static function perf_frontend_enqueue() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $base = plugin_dir_path( __FILE__ ) . 'assets/';
        $bust = filemtime( $base . 'cs-perf-monitor.css' );
        wp_enqueue_style(
            'cs-perf-monitor',
            plugins_url( 'assets/cs-perf-monitor.css', __FILE__ ),
            [],
            $bust
        );
        wp_enqueue_script(
            'cs-perf-monitor',
            plugins_url( 'assets/cs-perf-monitor.js', __FILE__ ),
            [],
            $bust,
            true
        );
    }

    /**
     * Outputs the performance monitor panel HTML and JSON payload at footer.
     *
     * Fires on both admin_footer and wp_footer so the panel appears on all
     * pages for manage_options users.
     *
     * @return void
     */
    public static function perf_output_panel() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( get_option( 'cs_perf_monitor_enabled', '1' ) === '0' ) {
            return;
        }

        $queries = self::perf_build_query_data();
        $http    = self::$perf_http_calls;
        $errors  = self::$perf_php_errors;
        $logs    = self::perf_build_log_data();
        $assets  = self::perf_build_assets_data();
        $cache   = self::perf_build_cache_data();
        $hooks   = self::perf_build_hooks_data();

        $q_total = 0.0;
        foreach ( $queries as $q ) {
            $q_total += $q['time_ms'];
        }
        $h_total = 0.0;
        foreach ( $http as $h ) {
            $h_total += $h['time_ms'];
        }

        // Request time from PHP superglobal (µs precision, PHP 5.4+).
        $page_ms = isset( $_SERVER['REQUEST_TIME_FLOAT'] )
            ? round( ( microtime( true ) - (float) $_SERVER['REQUEST_TIME_FLOAT'] ) * 1000, 2 )
            : 0;

        $data = [
            'queries' => $queries,
            'http'    => $http,
            'errors'  => $errors,
            'logs'    => $logs,
            'assets'  => $assets,
            'cache'   => $cache,
            'hooks'   => $hooks,
            'meta'    => [
                'query_count'    => count( $queries ),
                'query_total_ms' => round( $q_total, 2 ),
                'http_count'     => count( $http ),
                'http_total_ms'  => round( $h_total, 2 ),
                'error_count'    => count( $errors ),
                'log_count'      => count( $logs ),
                'script_count'   => count( $assets['scripts'] ),
                'style_count'    => count( $assets['styles'] ),
                'hook_count'     => count( $hooks ),
                'page_load_ms'       => $page_ms,
                'is_admin'           => is_admin(),
                'url'                => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
                'ajax_url'           => admin_url( 'admin-ajax.php' ),
                'explain_nonce'      => wp_create_nonce( 'cs_perf_explain' ),
                'debug_nonce'        => wp_create_nonce( 'cs_perf_debug' ),
                'wp_debug'           => (bool) get_option( 'cs_perf_debug_logging', false ),
                'wp_debug_log'       => (bool) get_option( 'cs_perf_debug_logging', false ),
                'savequeries_active' => defined( 'SAVEQUERIES' ) && SAVEQUERIES,
                // Page-context strip: what screen / template is this?
                'wp_screen'          => ( is_admin() && function_exists( 'get_current_screen' ) && get_current_screen() )
                                            ? get_current_screen()->id
                                            : '',
                'page_type'          => is_admin() ? 'admin'
                                        : ( is_singular() ? get_post_type() . ' (single)'
                                        : ( is_archive() ? 'archive'
                                        : ( is_home()    ? 'blog home'
                                        : ( is_front_page() ? 'front page' : 'other' ) ) ) ),
                'template'           => self::$perf_template,
            ],
        ];

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<script>window.csPerfData=' . wp_json_encode( $data ) . ';</script>' . "\n";
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo self::perf_panel_html();
    }

    /* ==================================================================
       PERFORMANCE MONITOR — Query processing
       ================================================================== */

    /**
     * Processes $wpdb->queries into a structured array for the panel.
     *
     * @return array
     */
    private static function perf_build_query_data(): array {
        global $wpdb;

        if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES || empty( $wpdb->queries ) ) {
            return [];
        }

        $seen   = [];
        $result = [];

        foreach ( $wpdb->queries as $q ) {
            $sql     = isset( $q[0] ) ? trim( (string) $q[0] ) : '';
            $time_ms = isset( $q[1] ) ? round( (float) $q[1] * 1000, 4 ) : 0.0;
            $bt_str  = isset( $q[2] ) ? (string) $q[2] : '';
            // Index 4 = row count for SELECT queries, rows_affected for write queries.
            $rows = isset( $q[4] ) && is_numeric( $q[4] ) ? (int) $q[4] : -1;

            if ( '' === $sql ) {
                continue;
            }

            // Duplicate detection: normalise whitespace before hashing.
            $hash    = md5( preg_replace( '/\s+/', ' ', strtolower( $sql ) ) );
            $is_dupe = array_key_exists( $hash, $seen );
            if ( ! $is_dupe ) {
                $seen[ $hash ] = true;
            }

            // Extract leading keyword.
            preg_match( '/^\s*(\w+)/i', $sql, $kw );
            $keyword = strtoupper( $kw[1] ?? 'QUERY' );

            $result[] = [
                'sql'     => $sql,
                'keyword' => $keyword,
                'time_ms' => $time_ms,
                'rows'    => $rows,
                'plugin'  => self::perf_plugin_from_query_bt( $bt_str ),
                'caller'  => self::perf_caller_from_query_bt( $bt_str ),
                'stack'   => self::perf_parse_stack( $bt_str ),
                'is_dupe' => $is_dupe,
            ];
        }

        return $result;
    }

    /**
     * Extracts the responsible plugin slug from a SAVEQUERIES backtrace string.
     *
     * wp_debug_backtrace_summary() includes require/include calls with file paths
     * like require('wp-content/plugins/SLUG/...'), so we can extract the slug.
     *
     * @param  string $bt SAVEQUERIES backtrace string.
     * @return string     Plugin directory slug or 'WordPress Core'.
     */
    private static function perf_plugin_from_query_bt( string $bt ): string {
        // Primary: require/include with plugin path (most accurate).
        if ( preg_match( "/(?:require|include)(?:_once)?\(['\"]wp-content\/plugins\/([^\/'\",\)]+)/i", $bt, $m ) ) {
            return $m[1];
        }

        // Fallback: match class/function prefixes against installed plugin slugs.
        $map = self::perf_get_plugin_prefix_map();
        foreach ( $map as $prefix => $slug ) {
            if ( 1 === preg_match( '/\b' . preg_quote( $prefix, '/' ) . '/i', $bt ) ) {
                return $slug;
            }
        }

        return 'WordPress Core';
    }

    /**
     * Returns the most relevant calling function from a SAVEQUERIES backtrace string.
     *
     * Skips internal WordPress and wpdb frames to surface the application-level caller.
     *
     * @param  string $bt Backtrace string.
     * @return string     Caller name (truncated to 70 chars) or empty string.
     */
    private static function perf_caller_from_query_bt( string $bt ): string {
        static $skip = [ 'wpdb', 'WP_Hook', 'do_action', 'apply_filters',
                          'require', 'include', '{main}', 'wp-settings', 'wp-blog-header' ];

        $frames = array_map( 'trim', explode( ',', $bt ) );
        foreach ( $frames as $frame ) {
            if ( '' === $frame ) {
                continue;
            }
            $skip_it = false;
            foreach ( $skip as $s ) {
                if ( false !== stripos( $frame, $s ) ) {
                    $skip_it = true;
                    break;
                }
            }
            if ( ! $skip_it ) {
                return strlen( $frame ) > 70 ? substr( $frame, 0, 67 ) . '...' : $frame;
            }
        }
        return '';
    }

    /**
     * Parses a SAVEQUERIES backtrace string into a typed array of call-chain frames.
     *
     * Each frame is annotated with a type so the JS can colour-code the trace:
     *   hook     — do_action / apply_filters (the WP entry point for this code path)
     *   plugin   — require/include from wp-content/plugins/
     *   theme    — require/include from wp-content/themes/
     *   file     — require/include from WP core
     *   wp       — WP_Hook, call_user_func, {main}
     *   db       — wpdb methods
     *   code     — application-level function or class method (the "real" work)
     *
     * @param  string $bt SAVEQUERIES backtrace string.
     * @return array<int, array{frame: string, type: string}>
     */
    private static function perf_parse_stack( string $bt ): array {
        if ( '' === $bt ) {
            return [];
        }

        $frames = array_values( array_filter( array_map( 'trim', explode( ',', $bt ) ) ) );
        $result = [];

        foreach ( $frames as $frame ) {
            if ( '' === $frame ) {
                continue;
            }

            if ( preg_match( '/^(do_action|apply_filters)\b/i', $frame ) ) {
                $type = 'hook';
            } elseif ( preg_match( '/^WP_Hook\b/i', $frame ) ) {
                $type = 'wp';
            } elseif ( preg_match( '/^(call_user_func|{main})/i', $frame ) ) {
                $type = 'wp';
            } elseif ( preg_match( '/^wpdb\b/i', $frame ) ) {
                $type = 'db';
            } elseif ( preg_match( '/^(require|include)(?:_once)?\(/i', $frame ) ) {
                if ( false !== stripos( $frame, '/plugins/' ) ) {
                    $type = 'plugin';
                } elseif ( false !== stripos( $frame, '/themes/' ) ) {
                    $type = 'theme';
                } else {
                    $type = 'file';
                }
            } else {
                $type = 'code';
            }

            $result[] = [ 'frame' => $frame, 'type' => $type ];
        }

        return $result;
    }

    /**
     * Attributes an HTTP call to a plugin using real debug_backtrace frames with file paths.
     *
     * @param  array $frames debug_backtrace() frames.
     * @return string        Plugin directory slug or 'WordPress Core'.
     */
    private static function perf_plugin_from_frames( array $frames ): string {
        $plugins_dir = wp_normalize_path( WP_PLUGIN_DIR );
        foreach ( $frames as $frame ) {
            if ( empty( $frame['file'] ) ) {
                continue;
            }
            $file = wp_normalize_path( $frame['file'] );
            if ( 0 === strpos( $file, $plugins_dir . '/' ) ) {
                $relative = substr( $file, strlen( $plugins_dir ) + 1 );
                $parts    = explode( '/', $relative );
                if ( ! empty( $parts[0] ) ) {
                    return $parts[0];
                }
            }
        }
        return 'WordPress Core';
    }

    /**
     * Builds a map of function/class prefixes to plugin slugs from active plugins.
     *
     * Used as a fallback when file-path attribution is not available.
     *
     * @return array<string, string>
     */
    private static function perf_get_plugin_prefix_map(): array {
        if ( null !== self::$perf_plugin_map ) {
            return self::$perf_plugin_map;
        }
        self::$perf_plugin_map = [];
        foreach ( get_option( 'active_plugins', [] ) as $plugin_file ) {
            $slug = dirname( $plugin_file );
            if ( '.' === $slug ) {
                $slug = basename( $plugin_file, '.php' );
            }
            $snake = str_replace( '-', '_', strtolower( $slug ) );
            foreach ( [ $slug, $snake, strtoupper( $snake ) ] as $prefix ) {
                if ( strlen( $prefix ) > 2 ) {
                    self::$perf_plugin_map[ $prefix ] = $slug;
                }
            }
        }
        return self::$perf_plugin_map;
    }

    /* ==================================================================
       PERFORMANCE MONITOR — EXPLAIN AJAX endpoint
       ================================================================== */

    /**
     * AJAX handler: runs EXPLAIN on a captured SELECT query and returns the plan.
     *
     * Only SELECT, SHOW, and DESCRIBE queries are accepted.
     * Access is restricted to manage_options users via nonce + capability check.
     *
     * @return void  Sends JSON and exits.
     */
    public static function ajax_perf_explain() {
        check_ajax_referer( 'cs_perf_explain', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $sql = isset( $_POST['sql'] ) ? trim( wp_unslash( $_POST['sql'] ) ) : '';

        if ( '' === $sql ) {
            wp_send_json_error( 'No SQL provided.' );
        }

        if ( ! preg_match( '/^\s*(SELECT|SHOW|DESCRIBE)\s/i', $sql ) ) {
            wp_send_json_error( 'Only SELECT, SHOW, and DESCRIBE queries can be explained.' );
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( 'EXPLAIN ' . $sql, ARRAY_A );

        if ( $wpdb->last_error ) {
            wp_send_json_error( $wpdb->last_error );
        }

        wp_send_json_success( [ 'rows' => $rows ?: [] ] );
    }

    /**
     * AJAX: toggle WP_DEBUG + WP_DEBUG_LOG + WP_DEBUG_DISPLAY in wp-config.php.
     *
     * Reads the current state, flips it, rewrites the relevant defines in the file.
     * Restricted to manage_options admins.
     *
     * @return void  Sends JSON and exits.
     */
    public static function ajax_perf_debug_toggle() {
        check_ajax_referer( 'cs_perf_debug', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $enable = isset( $_POST['enable'] ) ? ( '1' === $_POST['enable'] || 'true' === $_POST['enable'] ) : null;
        if ( null === $enable ) {
            $enable = ! (bool) get_option( 'cs_perf_debug_logging', false );
        }

        if ( $enable ) {
            update_option( 'cs_perf_debug_logging', 1, false );
        } else {
            delete_option( 'cs_perf_debug_logging' );
        }

        wp_send_json_success( [
            'enabled' => $enable,
            'message' => $enable
                ? 'Debug logging enabled. Reload the page to start capturing logs.'
                : 'Debug logging disabled.',
        ] );
    }

    /* ==================================================================
       PERFORMANCE MONITOR — Log data
       ================================================================== */

    /**
     * Reads the WordPress debug.log file (last 500 lines) and merges with
     * in-memory PHP errors captured by perf_error_handler().
     *
     * Each entry: { ts, level, message, source }
     *   source: 'debug_log' | 'php_handler'
     *
     * @return array
     */
    private static function perf_build_log_data(): array {
        $entries = [];

        // ── 1. Read debug.log ──────────────────────────────────────────────────
        $log_file = defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG )
            ? WP_DEBUG_LOG
            : WP_CONTENT_DIR . '/debug.log';

        if ( is_readable( $log_file ) ) {
            $lines = [];
            $fp    = fopen( $log_file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
            if ( $fp ) {
                // Read last 600 lines efficiently.
                $all = [];
                while ( ! feof( $fp ) ) {
                    $all[] = fgets( $fp );
                }
                fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
                $lines = array_slice( $all, -600 );
            }

            // Merge continuation lines (stack traces) into their parent entry.
            $buffer = '';
            $flush  = function ( string $buf ) use ( &$entries ): void {
                if ( '' === $buf ) return;
                // WordPress debug.log format: [DD-Mon-YYYY HH:MM:SS UTC] PHP Level: message
                if ( preg_match( '/^\[([^\]]+)\]\s+PHP\s+(\w[\w\s]*?):\s+(.*)/s', $buf, $m ) ) {
                    $entries[] = [
                        'ts'      => $m[1],
                        'level'   => strtolower( trim( $m[2] ) ),
                        'message' => trim( $m[3] ),
                        'source'  => 'debug_log',
                    ];
                } elseif ( preg_match( '/^\[([^\]]+)\]\s+(.*)/s', $buf, $m ) ) {
                    $entries[] = [
                        'ts'      => $m[1],
                        'level'   => 'info',
                        'message' => trim( $m[2] ),
                        'source'  => 'debug_log',
                    ];
                }
            };

            foreach ( $lines as $line ) {
                $line = rtrim( (string) $line );
                if ( preg_match( '/^\[/', $line ) && '' !== $buffer ) {
                    $flush( $buffer );
                    $buffer = $line;
                } else {
                    $buffer .= ( '' === $buffer ? '' : "\n" ) . $line;
                }
            }
            $flush( $buffer );
        }

        // ── 2. Merge in-memory PHP errors (captured this request) ──────────────
        foreach ( self::$perf_php_errors as $e ) {
            $entries[] = [
                'ts'      => gmdate( 'd-M-Y H:i:s' ) . ' UTC',
                'level'   => isset( $e['type'] ) ? strtolower( $e['type'] ) : 'notice',
                'message' => ( isset( $e['message'] ) ? $e['message'] : '' )
                             . ( ! empty( $e['file'] ) ? ' in ' . $e['file'] . ':' . ( $e['line'] ?? '' ) : '' ),
                'source'  => 'php_handler',
            ];
        }

        // Sort by timestamp desc — keep last 500.
        $entries = array_slice( $entries, -500 );
        return array_reverse( $entries );
    }

    /* ==================================================================
       PERFORMANCE MONITOR — Panel HTML scaffold
       ================================================================== */

    /**
     * Returns the performance monitor panel HTML.
     *
     * All data rendering is handled client-side by cs-perf-monitor.js
     * which reads window.csPerfData.
     *
     * @return string HTML markup.
     */
    private static function perf_panel_html(): string {
        return '<div id="cs-perf" class="cs-perf-collapsed" role="complementary" aria-label="' . esc_attr__( 'CloudScale Performance Monitor', 'cloudscale-code-block' ) . '">'
            . '<div id="cs-perf-resize" title="Drag to resize"></div>'
            . '<div id="cs-perf-header">'
                . '<div class="cs-perf-hl">'
                . '<button id="cs-perf-toggle" class="cs-perf-monitor-btn" title="Toggle panel (Ctrl+Shift+M)" aria-expanded="false">'
                        . '<span class="cs-perf-logo">&#9889;</span>'
                        . '<span class="cs-perf-name">CS&nbsp;Monitor</span>'
                        . '<span class="cs-perf-name-short">CS</span>'
                        . '<span id="cs-perf-toggle-arrow" class="cs-perf-toggle-arrow">&#9650;</span>'
                    . '</button>'
                    . '<span id="cs-pb-db"  class="cs-perf-badge cs-pb-db"  title="Database queries">DB&nbsp;<em>0</em></span>'
                    . '<span id="cs-pb-http" class="cs-perf-badge cs-pb-http" title="HTTP / REST calls">HTTP&nbsp;<em>0</em></span>'
                    . '<span id="cs-pb-log"  class="cs-perf-badge cs-pb-log"  title="Log entries" style="display:none">LOG&nbsp;<em>0</em></span>'
                . '</div>'
                . '<div class="cs-perf-hr">'
                    . '<span id="cs-perf-ttl" class="cs-perf-total"></span>'
                    . '<button id="cs-perf-export" class="cs-perf-btn" title="Export data as JSON (download)">&#8595;&nbsp;JSON</button>'
                    . '<button id="cs-perf-help-btn" class="cs-perf-btn cs-perf-help-btn" title="What am I looking at?">?</button>'
                . '</div>'
            . '</div>'
            . '<div id="cs-perf-help" class="cs-perf-help" style="display:none">'
                . '<button id="cs-perf-help-close" class="cs-perf-help-close" aria-label="Close help">&times;</button>'
                . '<h3 class="cs-help-title">&#9889; CS Monitor — What you\'re looking at</h3>'
                . '<div class="cs-help-grid">'
                    . '<div class="cs-help-card">'
                        . '<div class="cs-help-card-title cs-help-db">&#128200; DB Queries</div>'
                        . '<p>Every database query WordPress and your plugins ran to build this page. Slow queries hurt page speed. N+1 means the same query is looping — a common plugin performance bug.</p>'
                    . '</div>'
                    . '<div class="cs-help-card">'
                        . '<div class="cs-help-card-title cs-help-http">&#127758; HTTP / REST</div>'
                        . '<p>Outbound HTTP calls made during page load — licence checks, remote APIs, update pings. These add latency because PHP waits for the response before continuing.</p>'
                    . '</div>'
                    . '<div class="cs-help-card">'
                        . '<div class="cs-help-card-title cs-help-log">&#128196; Logs</div>'
                        . '<p>All entries from wp-content/debug.log plus PHP warnings and notices captured during this request. Filter by level (error, warning, notice, deprecated) to focus on what matters.</p>'
                    . '</div>'
                    . '<div class="cs-help-card">'
                        . '<div class="cs-help-card-title cs-help-assets">&#128190; Assets</div>'
                        . '<p>Every JS and CSS file WordPress loaded on this page, grouped by plugin. Use this to find plugin asset bloat, duplicate libraries, or unexpected front-end includes.</p>'
                    . '</div>'
                    . '<div class="cs-help-card">'
                        . '<div class="cs-help-card-title cs-help-hooks">&#128279; Hooks</div>'
                        . '<p>WordPress action and filter hooks fired during this request, sorted by cumulative time. Slow hooks reveal expensive callbacks added by plugins or your theme.</p>'
                    . '</div>'
                    . '<div class="cs-help-card">'
                        . '<div class="cs-help-card-title cs-help-sum">&#9650; Summary</div>'
                        . '<p>Which plugin is responsible for the most DB time, the slowest queries, N+1 patterns, and duplicate queries — the fastest way to find what\'s slowing your site.</p>'
                    . '</div>'
                . '</div>'
                . '<div class="cs-help-legend">'
                    . '<div class="cs-help-legend-title">Query speed colour coding</div>'
                    . '<div class="cs-help-swatches">'
                        . '<span class="cs-hs cs-hs-fast"></span><span>Fast (&lt;10ms)</span>'
                        . '<span class="cs-hs cs-hs-med"></span><span>Medium (10–50ms)</span>'
                        . '<span class="cs-hs cs-hs-slow"></span><span>Slow (50–200ms)</span>'
                        . '<span class="cs-hs cs-hs-crit"></span><span>Critical (&gt;200ms)</span>'
                        . '<span class="cs-hs cs-hs-dupe"></span><span>Duplicate query</span>'
                        . '<span class="cs-hs cs-hs-n1"></span><span>N+1 pattern (same query looping)</span>'
                    . '</div>'
                . '</div>'
            . '</div>'
            . '<div id="cs-perf-body">'
                . '<div id="cs-perf-ctx" aria-label="Page context"></div>'
                . '<div id="cs-perf-tabs" role="tablist">'
                    . '<button class="cs-ptab active" data-tab="db"      role="tab" aria-selected="true">DB Queries <span id="cs-ptc-db">0</span></button>'
                    . '<button class="cs-ptab"         data-tab="http"    role="tab" aria-selected="false">HTTP / REST <span id="cs-ptc-http">0</span></button>'
                    . '<button class="cs-ptab"         data-tab="logs"    role="tab" aria-selected="false">Logs <span id="cs-ptc-log">0</span></button>'
                    . '<button class="cs-ptab"         data-tab="assets"  role="tab" aria-selected="false">Assets <span id="cs-ptc-assets">0</span></button>'
                    . '<button class="cs-ptab"         data-tab="hooks"   role="tab" aria-selected="false">Hooks <span id="cs-ptc-hooks">0</span></button>'
                    . '<button class="cs-ptab"         data-tab="summary" role="tab" aria-selected="false">Summary</button>'
                . '</div>'
                . '<div id="cs-perf-filters">'
                    . '<input type="search" id="cs-pf-search" placeholder="Filter&#8230;" aria-label="Filter rows">'
                    . '<select id="cs-pf-plugin" aria-label="Filter by plugin"><option value="">All plugins</option></select>'
                    . '<select id="cs-pf-speed" aria-label="Filter by speed">'
                        . '<option value="0">Any speed</option>'
                        . '<option value="10">Slow &gt;10ms</option>'
                        . '<option value="50">Slow &gt;50ms</option>'
                        . '<option value="100">Critical &gt;100ms</option>'
                    . '</select>'
                    . '<label class="cs-pf-dupe-lbl"><input type="checkbox" id="cs-pf-dupe"> Dupes only</label>'
                . '</div>'
                . '<div id="cs-pp-db" class="cs-ppane active" role="tabpanel">'
                    . '<div class="cs-tbl-wrap">'
                        . '<table class="cs-ptable"><thead><tr>'
                            . '<th class="c-n">#</th>'
                            . '<th class="c-q">Query</th>'
                            . '<th class="c-p cs-sortable" data-sort="plugin">Plugin&nbsp;&#8597;</th>'
                            . '<th class="c-r cs-sortable" data-sort="rows">Rows&nbsp;&#8597;</th>'
                            . '<th class="c-t cs-sortable cs-sort-active" data-sort="time">Time&nbsp;&#8595;</th>'
                        . '</tr></thead><tbody id="cs-db-rows"></tbody></table>'
                    . '</div>'
                . '</div>'
                . '<div id="cs-pp-http" class="cs-ppane" role="tabpanel">'
                    . '<div class="cs-tbl-wrap">'
                        . '<table class="cs-ptable"><thead><tr>'
                            . '<th class="c-n">#</th>'
                            . '<th class="c-m">Method</th>'
                            . '<th class="c-u">URL</th>'
                            . '<th class="c-p">Plugin</th>'
                            . '<th class="c-s">Status</th>'
                            . '<th class="c-t cs-sortable" data-sort="time">Time&nbsp;&#8597;</th>'
                        . '</tr></thead><tbody id="cs-http-rows"></tbody></table>'
                    . '</div>'
                . '</div>'
                . '<div id="cs-pp-logs" class="cs-ppane" role="tabpanel">'
                    . '<div id="cs-debug-bar" class="cs-debug-bar">'
                        . '<span id="cs-debug-status" class="cs-debug-status"></span>'
                        . '<button id="cs-debug-toggle" class="cs-debug-toggle-btn">Enable debug logging</button>'
                        . '<span id="cs-debug-msg" class="cs-debug-msg"></span>'
                    . '</div>'
                    . '<div class="cs-log-filters">'
                        . '<input type="search" id="cs-lf-search" placeholder="Filter logs&#8230;" aria-label="Filter log entries">'
                        . '<select id="cs-lf-level" aria-label="Filter by level">'
                            . '<option value="">All levels</option>'
                            . '<option value="fatal error">Fatal</option>'
                            . '<option value="error">Error</option>'
                            . '<option value="warning">Warning</option>'
                            . '<option value="notice">Notice</option>'
                            . '<option value="deprecated">Deprecated</option>'
                            . '<option value="info">Info</option>'
                        . '</select>'
                        . '<select id="cs-lf-source" aria-label="Filter by source">'
                            . '<option value="">All sources</option>'
                            . '<option value="debug_log">debug.log file</option>'
                            . '<option value="php_handler">This request</option>'
                        . '</select>'
                    . '</div>'
                    . '<div id="cs-log-list" class="cs-log-list"></div>'
                . '</div>'
                . '<div id="cs-pp-assets" class="cs-ppane" role="tabpanel">'
                    . '<div class="cs-assets-filters">'
                        . '<input type="search" id="cs-af-search" placeholder="Filter assets&#8230;" aria-label="Filter assets">'
                        . '<select id="cs-af-type" aria-label="Filter by type">'
                            . '<option value="">JS &amp; CSS</option>'
                            . '<option value="scripts">JS only</option>'
                            . '<option value="styles">CSS only</option>'
                        . '</select>'
                        . '<select id="cs-af-plugin" aria-label="Filter by plugin"><option value="">All plugins</option></select>'
                    . '</div>'
                    . '<div class="cs-tbl-wrap">'
                        . '<table class="cs-ptable"><thead><tr>'
                            . '<th class="c-at">Type</th>'
                            . '<th class="c-ah">Handle</th>'
                            . '<th class="c-ap">Plugin</th>'
                            . '<th class="c-au">Source</th>'
                        . '</tr></thead><tbody id="cs-assets-rows"></tbody></table>'
                    . '</div>'
                . '</div>'
                . '<div id="cs-pp-hooks" class="cs-ppane" role="tabpanel">'
                    . '<div class="cs-hooks-filters">'
                        . '<input type="search" id="cs-hkf-search" placeholder="Filter hooks&#8230;" aria-label="Filter hooks">'
                    . '</div>'
                    . '<div class="cs-tbl-wrap">'
                        . '<table class="cs-ptable"><thead><tr>'
                            . '<th class="c-hk">Hook</th>'
                            . '<th class="c-hc cs-sortable" data-sort="count">Count&nbsp;&#8597;</th>'
                            . '<th class="c-ht cs-sortable cs-sort-hk-time" data-sort="total_ms">Total&nbsp;&#8597;</th>'
                            . '<th class="c-hm cs-sortable" data-sort="max_ms">Max&nbsp;&#8597;</th>'
                        . '</tr></thead><tbody id="cs-hooks-rows"></tbody></table>'
                    . '</div>'
                . '</div>'
                . '<div id="cs-pp-summary" class="cs-ppane" role="tabpanel">'
                    . '<div id="cs-summary-wrap" class="cs-tbl-wrap"></div>'
                . '</div>'
                . '<div id="cs-perf-foot"><span id="cs-perf-foot-txt"></span></div>'
            . '</div>'
        . '</div>' . "\n";
    }
}

CloudScale_Code_Block::init();
