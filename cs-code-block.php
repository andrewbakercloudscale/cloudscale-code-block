<?php
/**
 * Plugin Name: CloudScale DevTools
 * Plugin URI: https://your-wordpress-site.example.com
 * Description: Developer toolkit with syntax-highlighted code blocks, SQL query tool, code migrator, site monitor, and login security (passkeys, TOTP, email 2FA, hide login URL).
 * Version: 1.8.59
 * Author: Andrew Baker
 * Author URI: https://your-wordpress-site.example.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: cloudscale-devtools
 *
 * @package CloudScale_DevTools
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-cs-passkey.php';

// Enable DB query saving only when CS Monitor is active (avoids memory overhead when disabled).
if ( ! defined( 'SAVEQUERIES' ) && get_option( 'cs_devtools_perf_monitor_enabled', '1' ) !== '0' ) {
    define( 'SAVEQUERIES', true );
}

/**
 * CloudScale Code Block — main plugin class.
 *
 * Handles block registration, shortcode, admin tools, settings,
 * the code block migration tool, and the SQL command tool.
 *
 * @package CloudScale_DevTools
 * @since   1.0.0
 */
class CloudScale_DevTools {

    const VERSION      = '1.8.56';
    const HLJS_VERSION = '11.11.1';
    const HLJS_CDN     = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/';
    const TOOLS_SLUG   = 'cloudscale-devtools';
    const MIGRATE_NONCE = 'cs_devtools_code_migrate_action';

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
    /** @var array Transient stats: [ key => [ gets, hits, sets, deletes ] ] */
    private static $perf_transients = [];
    /** @var array Template hierarchy candidates captured via *_template_hierarchy filters. */
    private static $perf_template_hierarchy = [];

    /**
     * Registers all plugin hooks.
     *
     * @since  1.0.0
     * @return void
     */
    public static function init() {
        self::maybe_migrate_prefix();
        add_action( 'init', [ __CLASS__, 'load_textdomain' ] );
        add_action( 'init', [ __CLASS__, 'register_block' ] );
        add_action( 'init', [ __CLASS__, 'register_shortcode' ] );
        add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_convert_script' ] );
        add_action( 'admin_menu', [ __CLASS__, 'add_tools_page' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_init',       [ __CLASS__, 'redirect_legacy_slug' ] );
        add_action( 'init', [ __CLASS__, 'redirect_legacy_help_url' ], 1 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );

        // Migration AJAX
        add_action( 'wp_ajax_cs_devtools_migrate_scan', [ __CLASS__, 'ajax_scan' ] );
        add_action( 'wp_ajax_cs_devtools_migrate_preview', [ __CLASS__, 'ajax_preview' ] );
        add_action( 'wp_ajax_cs_devtools_migrate_single', [ __CLASS__, 'ajax_migrate_single' ] );
        add_action( 'wp_ajax_cs_devtools_migrate_all', [ __CLASS__, 'ajax_migrate_all' ] );

        // SQL AJAX
        add_action( 'wp_ajax_cs_devtools_sql_run', [ __CLASS__, 'ajax_sql_run' ] );

        // Settings AJAX
        add_action( 'wp_ajax_cs_devtools_save_theme_setting', [ __CLASS__, 'ajax_save_theme_setting' ] );

        // Login security AJAX
        add_action( 'wp_ajax_cs_devtools_login_save',          [ __CLASS__, 'ajax_login_save' ] );
        add_action( 'wp_ajax_cs_devtools_totp_setup_start',    [ __CLASS__, 'ajax_totp_setup_start' ] );
        add_action( 'wp_ajax_cs_devtools_totp_setup_verify',   [ __CLASS__, 'ajax_totp_setup_verify' ] );
        add_action( 'wp_ajax_cs_devtools_2fa_disable',         [ __CLASS__, 'ajax_2fa_disable' ] );
        add_action( 'wp_ajax_cs_devtools_email_2fa_enable',    [ __CLASS__, 'ajax_email_2fa_enable' ] );
        add_action( 'admin_init',           [ __CLASS__, 'email_2fa_confirm_check' ] );
        add_action( 'after_password_reset', [ __CLASS__, 'on_password_reset' ], 10, 1 );
        add_action( 'profile_update',       [ __CLASS__, 'on_profile_update' ], 10, 2 );
        CS_DevTools_Passkey::register_hooks();

        // Login security — URL intercept / 2FA flow (early, priority 1 on init).
        add_action( 'init',        [ __CLASS__, 'login_serve_custom_slug' ], 1 );
        add_action( 'login_init',  [ __CLASS__, 'login_block_direct_access' ], 1 );
        add_action( 'login_init',  [ __CLASS__, 'login_2fa_handle' ] );
        add_filter( 'authenticate',        [ __CLASS__, 'login_2fa_intercept' ], 100, 3 );
        add_filter( 'login_url',           [ __CLASS__, 'login_custom_url' ], 10, 3 );
        add_filter( 'logout_url',          [ __CLASS__, 'login_custom_logout_url' ], 10, 2 );
        add_filter( 'lostpassword_url',    [ __CLASS__, 'login_custom_lostpassword_url' ], 10, 2 );
        add_filter( 'network_site_url',    [ __CLASS__, 'login_custom_network_url' ], 10, 3 );
        add_filter( 'site_url',            [ __CLASS__, 'login_custom_site_url' ], 10, 4 );

        // Performance monitor — EXPLAIN endpoint.
        add_action( 'wp_ajax_cs_devtools_perf_explain',       [ __CLASS__, 'ajax_perf_explain' ] );
        add_action( 'wp_ajax_cs_devtools_perf_debug_toggle',  [ __CLASS__, 'ajax_perf_debug_toggle' ] );

        // Performance monitor — only register data-collection hooks when the monitor is enabled.
        // This prevents SAVEQUERIES-scale memory accumulation on every request when disabled.
        if ( get_option( 'cs_devtools_perf_monitor_enabled', '1' ) !== '0' ) {
            add_filter( 'pre_http_request', [ __CLASS__, 'perf_http_before' ], 10, 3 );
            add_action( 'http_api_debug',   [ __CLASS__, 'perf_http_after' ],  10, 5 );

            // If the user enabled debug logging via the panel, activate PHP error logging
            // using ini_set — this works regardless of WP_DEBUG in wp-config.php and
            // survives Docker container rebuilds because the setting lives in the DB.
            if ( get_option( 'cs_devtools_perf_debug_logging', false ) ) {
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

            // Transient + template hierarchy observer (single all-hook for both).
            add_action( 'all',                      [ __CLASS__, 'perf_misc_tracker' ] );
            add_action( 'setted_transient',         [ __CLASS__, 'perf_transient_set' ] );
            add_action( 'setted_site_transient',    [ __CLASS__, 'perf_transient_set' ] );
            add_action( 'deleted_transient',        [ __CLASS__, 'perf_transient_delete' ] );
            add_action( 'deleted_site_transient',   [ __CLASS__, 'perf_transient_delete' ] );

            // Scripts & styles — collect at footer time (after everything is enqueued).
            add_action( 'admin_footer', [ __CLASS__, 'perf_capture_assets' ], 1 );
            add_action( 'wp_footer',    [ __CLASS__, 'perf_capture_assets' ], 1 );
        }
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
            'cloudscale-devtools',
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
        $pair_slug = get_option( 'cs_devtools_code_theme_pair', 'atom-one' );
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
                    <button class="cs-code-lines-toggle" title="<?php esc_attr_e( 'Toggle line numbers', 'cloudscale-devtools' ); ?>" aria-label="<?php esc_attr_e( 'Toggle line numbers', 'cloudscale-devtools' ); ?>">
                        <svg class="cs-icon-lines" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><text x="4" y="7" font-size="7" fill="currentColor" stroke="none" font-family="monospace">1</text><text x="4" y="13" font-size="7" fill="currentColor" stroke="none" font-family="monospace">2</text><text x="4" y="19" font-size="7" fill="currentColor" stroke="none" font-family="monospace">3</text></svg>
                    </button>
                    <button class="cs-code-theme-toggle" title="<?php esc_attr_e( 'Toggle light/dark mode', 'cloudscale-devtools' ); ?>" aria-label="<?php esc_attr_e( 'Toggle theme', 'cloudscale-devtools' ); ?>">
                        <svg class="cs-icon-sun" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        <svg class="cs-icon-moon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </button>
                    <button class="cs-code-copy" title="<?php esc_attr_e( 'Copy to clipboard', 'cloudscale-devtools' ); ?>" aria-label="<?php esc_attr_e( 'Copy code', 'cloudscale-devtools' ); ?>">
                        <svg class="cs-icon-copy" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <svg class="cs-icon-check" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <span class="cs-copy-label"><?php esc_html_e( 'Copy', 'cloudscale-devtools' ); ?></span>
                    </button>
                </div>
            </div>
            <div class="cs-code-body">
                <pre><code class="<?php echo esc_attr( $lang_class ); ?>"><?php echo str_replace( [ '[', ']' ], [ '&#91;', '&#93;' ], esc_html( $code ) ); ?></code></pre>
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

        $default_theme = get_option( 'cs_devtools_code_default_theme', 'dark' );
        $pair_slug     = get_option( 'cs_devtools_code_theme_pair', 'atom-one' );
        $registry      = self::get_theme_registry();
        $pair          = isset( $registry[ $pair_slug ] ) ? $registry[ $pair_slug ] : $registry['atom-one'];

        wp_localize_script( 'cs-code-block-frontend', 'csDevtoolsCodeConfig', [
            'defaultTheme'  => $default_theme,
            'themePair'     => $pair_slug,
            'darkBg'        => $pair['dark_bg'],
            'darkToolbar'   => $pair['dark_toolbar'],
            'lightBg'       => $pair['light_bg'],
            'lightToolbar'  => $pair['light_toolbar'],
        ] );
    }

    /* ==================================================================
       3. SHORTCODE [cs_devtools_code]
       ================================================================== */

    /**
     * Registers the [cs_devtools_code] shortcode.
     *
     * @since  1.0.0
     * @return void
     */
    public static function register_shortcode() {
        add_shortcode( 'cs_devtools_code', [ __CLASS__, 'render_shortcode' ] );
    }

    /**
     * Renders the [cs_devtools_code] shortcode.
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
        ], $atts, 'cs_devtools_code' );

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
        register_setting( 'cs_devtools_code_settings', 'cs_devtools_code_default_theme', [
            'type'              => 'string',
            'sanitize_callback' => function ( $val ) {
                return in_array( $val, [ 'dark', 'light' ] ) ? $val : 'dark';
            },
            'default' => 'dark',
        ] );

        $valid_themes = array_keys( self::get_theme_registry() );
        register_setting( 'cs_devtools_code_settings', 'cs_devtools_code_theme_pair', [
            'type'              => 'string',
            'sanitize_callback' => function ( $val ) use ( $valid_themes ) {
                return in_array( $val, $valid_themes, true ) ? $val : 'atom-one';
            },
            'default' => 'atom-one',
        ] );

        register_setting( 'cs_devtools_code_settings', 'cs_devtools_perf_monitor_enabled', [
            'type'              => 'string',
            'sanitize_callback' => function ( $val ) {
                return '0' === $val ? '0' : '1';
            },
            'default' => '1',
        ] );

        // Login security settings
        register_setting( 'cs_devtools_login_settings', 'cs_devtools_login_hide_enabled', [
            'type'              => 'string',
            'sanitize_callback' => function ( $v ) { return '1' === $v ? '1' : '0'; },
            'default'           => '0',
        ] );
        register_setting( 'cs_devtools_login_settings', 'cs_devtools_login_slug', [
            'type'              => 'string',
            'sanitize_callback' => function ( $v ) {
                $slug = sanitize_title( $v );
                // Disallow WP reserved slugs
                $reserved = [ 'wp-login', 'wp-admin', 'login', 'admin', 'dashboard' ];
                return in_array( $slug, $reserved, true ) ? '' : $slug;
            },
            'default' => '',
        ] );
        register_setting( 'cs_devtools_login_settings', 'cs_devtools_2fa_method', [
            'type'              => 'string',
            'sanitize_callback' => function ( $v ) {
                return in_array( $v, [ 'off', 'email', 'totp' ], true ) ? $v : 'off';
            },
            'default' => 'off',
        ] );
        register_setting( 'cs_devtools_login_settings', 'cs_devtools_2fa_force_admins', [
            'type'              => 'string',
            'sanitize_callback' => function ( $v ) { return '1' === $v ? '1' : '0'; },
            'default'           => '0',
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
    /**
     * Redirects legacy ?page=cloudscale-code-sql URLs to the new slug.
     *
     * @since  1.8.56
     * @return void
     */
    /**
     * Redirects the old help page URL to the current one.
     *
     * @since  1.8.56
     * @return void
     */
    public static function redirect_legacy_help_url() {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        if ( strpos( $uri, 'code-block-help' ) !== false ) {
            wp_redirect( home_url( '/wordpress-plugin-help/cloudscale-devtools-help/' ), 301 );
            exit;
        }
    }

    public static function redirect_legacy_slug() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'cloudscale-code-sql' ) {
            $args = $_GET;
            $args['page'] = self::TOOLS_SLUG;
            wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
            exit;
        }
    }

    public static function add_tools_page() {
        add_management_page(
            'CloudScale DevTools',
            '🌩️ CloudScale DevTools',
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
        wp_localize_script( 'cs-code-migrate', 'csDevtoolsMigrate', [
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
        wp_localize_script( 'cs-admin-settings', 'csDevtoolsAdminSettings', [
            'nonce' => wp_create_nonce( 'cs_devtools_code_settings_inline' ),
        ] );

        // SQL editor JS
        wp_enqueue_script(
            'cs-sql-editor',
            plugins_url( 'assets/cs-sql-editor.js', __FILE__ ),
            [],
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-sql-editor.js' ),
            true
        );
        wp_localize_script( 'cs-sql-editor', 'csDevtoolsSqlEditor', [
            'nonce' => wp_create_nonce( 'cs_devtools_sql_nonce' ),
        ] );

        // Login security JS (only loaded on the login tab)
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'migrate'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $active_tab === 'login' ) {
            wp_enqueue_script(
                'cs-qrcode',
                plugins_url( 'assets/qrcode.min.js', __FILE__ ),
                [],
                filemtime( plugin_dir_path( __FILE__ ) . 'assets/qrcode.min.js' ),
                true
            );
            wp_enqueue_script(
                'cs-login',
                plugins_url( 'assets/cs-login.js', __FILE__ ),
                [ 'cs-qrcode' ],
                filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-login.js' ),
                true
            );
            wp_localize_script( 'cs-login', 'csDevtoolsLogin', [
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'cs_devtools_login_nonce' ),
                'currentUser' => get_current_user_id(),
            ] );
            wp_enqueue_script(
                'cs-passkey',
                plugins_url( 'assets/cs-passkey.js', __FILE__ ),
                [ 'cs-login' ],
                filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-passkey.js' ),
                true
            );
        }
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
                    <div id="cs-banner-title">⚡ CloudScale DevTools</div>
                    <div id="cs-banner-sub"><?php esc_html_e( 'Code blocks, SQL tools, code migrator, site monitor &amp; login security', 'cloudscale-devtools' ); ?> &middot; v<?php echo esc_html( self::VERSION ); ?></div>
                </div>
                <div id="cs-banner-right">
                    <span class="cs-badge cs-badge-green">✅ <?php esc_html_e( 'Totally Free', 'cloudscale-devtools' ); ?></span>
                    <a href="https://your-wordpress-site.example.com" target="_blank" rel="noopener noreferrer" class="cs-badge cs-badge-orange" style="text-decoration:none">andrewbaker.ninja</a>
                    <a href="https://your-wordpress-site.example.com/wordpress-plugin-help/cloudscale-devtools-help/" target="_blank" rel="noopener noreferrer" class="cs-badge cs-badge-help" style="text-decoration:none">❓ <?php esc_html_e( 'Help', 'cloudscale-devtools' ); ?></a>
                </div>
            </div>

            <!-- Tab bar -->
            <div id="cs-tab-bar">
                <a href="<?php echo esc_url( $base_url . '&tab=migrate' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'migrate' ? 'active' : ''; ?>">
                    🔄 <?php esc_html_e( 'Code Migrator', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=sql' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'sql' ? 'active' : ''; ?>">
                    🗄️ <?php esc_html_e( 'SQL Command', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=login' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'login' ? 'active' : ''; ?>">
                    🔐 <?php esc_html_e( 'Login Security', 'cloudscale-devtools' ); ?>
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
            <?php elseif ( $active_tab === 'login' ) : ?>
                <div class="cs-tab-content active">
                    <?php self::render_login_panel(); ?>
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
    /**
     * Renders an "Explain…" button and its associated modal for a panel header.
     *
     * @param string $id    Unique slug used to build element IDs.
     * @param string $title Modal title.
     * @param array  $items Array of ['name'=>'', 'rec'=>'', 'desc'=>''] entries.
     */
    private static function render_explain_btn( string $id, string $title, array $items ): void {
        $btn_id   = 'cs-explain-btn-' . $id;
        $modal_id = 'cs-explain-modal-' . $id;
        ?>
        <button type="button" id="<?php echo esc_attr( $btn_id ); ?>"
            onclick="document.getElementById('<?php echo esc_attr( $modal_id ); ?>').style.display='flex'"
            style="background:rgba(0,0,0,0.28)!important;border:1px solid rgba(255,255,255,0.55)!important;border-radius:5px!important;color:#fff!important;font-size:12px!important;font-weight:700!important;padding:5px 14px!important;cursor:pointer!important;margin-left:auto!important;flex-shrink:0!important;display:block!important;box-shadow:none!important;text-shadow:0 1px 2px rgba(0,0,0,0.4)!important;text-transform:none!important;letter-spacing:normal!important;line-height:1.4!important">
            Explain&hellip;
        </button>
        <div id="<?php echo esc_attr( $modal_id ); ?>"
             style="display:none;position:fixed;inset:0;z-index:100002;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;padding:16px;text-transform:none;letter-spacing:normal;font-weight:normal"
             onclick="if(event.target===this)this.style.display='none'">
            <div style="background:#fff;border-radius:10px;max-width:600px;width:100%;max-height:88vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,0.25)">
                <div style="padding:18px 22px 12px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:10px">
                    <strong style="font-size:15px;color:#111"><?php echo esc_html( $title ); ?></strong>
                    <button type="button"
                        onclick="document.getElementById('<?php echo esc_attr( $modal_id ); ?>').style.display='none'"
                        style="margin-left:auto;background:none;border:none;font-size:20px;cursor:pointer;color:#888;line-height:1;padding:0">&times;</button>
                </div>
                <div style="padding:16px 22px 20px">
                    <?php foreach ( $items as $item ) :
                        $rec    = $item['rec'];
                        $is_on  = str_contains( $rec, 'Recommended' );
                        $is_opt = str_contains( $rec, 'Optional' );
                        $bg     = $is_on ? '#edfaef' : ( $is_opt ? '#f6f7f7' : '#f0f6fc' );
                        $col    = $is_on ? '#1a7a34' : ( $is_opt ? '#50575e' : '#1a4a7a' );
                        $bdr    = $is_on ? '#1a7a34' : ( $is_opt ? '#c3c4c7' : '#2271b1' );
                    ?>
                    <div style="border:1px solid #e0e0e0;border-radius:6px;padding:12px 14px;margin-bottom:10px">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:5px;flex-wrap:wrap">
                            <strong style="font-size:13px"><?php echo esc_html( $item['name'] ); ?></strong>
                            <span style="background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $col ); ?>;border:1px solid <?php echo esc_attr( $bdr ); ?>;border-radius:4px;font-size:11px;font-weight:600;padding:1px 8px;white-space:nowrap"><?php echo esc_html( $rec ); ?></span>
                        </div>
                        <p style="margin:0;color:#50575e;font-size:12px;line-height:1.5;white-space:pre-line"><?php echo esc_html( $item['desc'] ); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="padding:10px 22px 16px;border-top:1px solid #e5e7eb;text-align:right">
                    <button type="button"
                        onclick="document.getElementById('<?php echo esc_attr( $modal_id ); ?>').style.display='none'"
                        style="background:#f3f4f6;border:1px solid #d1d5db;border-radius:5px;padding:6px 18px;font-size:12px;font-weight:600;cursor:pointer;color:#374151">
                        <?php esc_html_e( 'Got it', 'cloudscale-devtools' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_settings_panel() {
        $theme       = get_option( 'cs_devtools_code_default_theme', 'dark' );
        $pair_slug   = get_option( 'cs_devtools_code_theme_pair', 'atom-one' );
        $perf_on     = get_option( 'cs_devtools_perf_monitor_enabled', '1' ) !== '0';
        $registry    = self::get_theme_registry();
        ?>
        <div class="cs-panel" id="cs-panel-code-settings">
            <div class="cs-section-header cs-section-header-teal">
                <span>🎨 CODE BLOCK SETTINGS</span>
                <?php self::render_explain_btn( 'code-settings', 'Code Block Settings', [
                    [ 'name' => 'Theme Pair',           'rec' => 'Recommended', 'desc' => 'Choose a light/dark colour-scheme pair for syntax-highlighted code blocks. The pair is applied automatically based on the visitor\'s OS colour preference.' ],
                    [ 'name' => 'Default Mode',         'rec' => 'Optional',    'desc' => 'Force all code blocks to always use light or dark mode, ignoring the visitor\'s system preference. Leave unset to follow the OS setting.' ],
                    [ 'name' => 'Performance Monitor',  'rec' => 'Optional',    'desc' => 'Enables the CS Monitor DevTools panel, which tracks database queries, HTTP requests, and PHP errors on every page load. Keep disabled in production unless actively debugging.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <div class="cs-field-row">
                    <div class="cs-field">
                        <label class="cs-label" for="cs-settings-pair"><?php esc_html_e( 'Color Theme:', 'cloudscale-devtools' ); ?></label>
                        <select id="cs-settings-pair" name="cs_devtools_code_theme_pair" class="cs-input">
                            <?php foreach ( $registry as $slug => $info ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $pair_slug, $slug ); ?>>
                                    <?php echo esc_html( $info['label'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="cs-hint"><?php esc_html_e( 'Syntax highlighting color scheme loaded from CDN.', 'cloudscale-devtools' ); ?></span>
                    </div>
                    <div class="cs-field">
                        <label class="cs-label" for="cs-settings-theme"><?php esc_html_e( 'Default Mode:', 'cloudscale-devtools' ); ?></label>
                        <select id="cs-settings-theme" name="cs_devtools_code_default_theme" class="cs-input">
                            <option value="dark" <?php selected( $theme, 'dark' ); ?>><?php esc_html_e( 'Dark', 'cloudscale-devtools' ); ?></option>
                            <option value="light" <?php selected( $theme, 'light' ); ?>><?php esc_html_e( 'Light', 'cloudscale-devtools' ); ?></option>
                        </select>
                        <span class="cs-hint"><?php esc_html_e( 'Visitors can still toggle per block.', 'cloudscale-devtools' ); ?></span>
                    </div>
                </div>
                <div class="cs-field" style="margin-top:14px">
                    <label class="cs-label"><?php esc_html_e( 'CS Monitor panel:', 'cloudscale-devtools' ); ?></label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" id="cs-settings-perf-enabled" name="cs_devtools_perf_monitor_enabled" value="1" <?php checked( $perf_on ); ?>>
                        <span style="font-size:13px;color:#555"><?php esc_html_e( 'Show the ⚡ CS Monitor performance panel', 'cloudscale-devtools' ); ?></span>
                    </label>
                    <span class="cs-hint"><?php esc_html_e( 'Visible to admins only. Uncheck to hide the panel on all pages.', 'cloudscale-devtools' ); ?></span>
                </div>
                <div style="margin-top:14px;display:flex;align-items:center;gap:10px">
                    <button type="button" class="cs-btn-primary" id="cs-settings-save">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
                    <span class="cs-settings-saved" id="cs-settings-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
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
        <div class="cs-panel" id="cs-panel-migrator">
            <div class="cs-section-header">
                <span>🔄 CODE BLOCK MIGRATOR</span>
                <?php self::render_explain_btn( 'migrator', 'Code Block Migrator', [
                    [ 'name' => 'Scan Posts',    'rec' => 'Informational', 'desc' => 'Scans all posts and pages for legacy WordPress wp:code and wp:preformatted blocks that can be upgraded to CloudScale Code Blocks with full syntax highlighting.' ],
                    [ 'name' => 'Preview',       'rec' => 'Recommended',   'desc' => 'Shows a side-by-side before/after diff for each post before committing any changes, so you can review exactly what will be converted.' ],
                    [ 'name' => 'Migrate',       'rec' => 'Optional',      'desc' => 'Converts detected legacy blocks to CloudScale format. Each post is saved with the converted markup. Take a backup first — this cannot be undone without one.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <p style="color:#555;margin:0 0 16px;font-size:13px;line-height:1.6">
                    <?php esc_html_e( 'Scan your posts for legacy WordPress code blocks, preview changes, then migrate one at a time or all at once.', 'cloudscale-devtools' ); ?>
                </p>

                <div class="cs-migrate-toolbar">
                    <button id="cs-scan-btn" class="cs-btn-primary" style="padding:8px 20px;font-size:13px">
                        <span class="dashicons dashicons-search" style="font-size:14px;width:14px;height:14px;margin-top:1px"></span> <?php esc_html_e( 'Scan Posts', 'cloudscale-devtools' ); ?>
                    </button>
                    <button id="cs-migrate-all-btn" class="cs-btn-orange" style="padding:8px 20px;font-size:13px" disabled>
                        <span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;margin-top:1px"></span> <?php esc_html_e( 'Migrate All Remaining', 'cloudscale-devtools' ); ?>
                    </button>
                    <span id="cs-scan-status" class="cs-status"></span>
                </div>

                <div id="cs-results-area">
                    <p class="cs-migrate-hint"><?php printf( __( 'Click %s to find all posts with legacy code blocks.', 'cloudscale-devtools' ), '<strong>' . esc_html__( 'Scan Posts', 'cloudscale-devtools' ) . '</strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- format string is hardcoded, only %s is user-visible and escaped above ?></p>
                </div>
            </div>
        </div>

        <div id="cs-preview-modal" class="cs-modal" style="display:none;">
            <div class="cs-modal-backdrop"></div>
            <div class="cs-modal-content">
                <div class="cs-modal-header">
                    <h2 id="cs-modal-title"><?php esc_html_e( 'Preview', 'cloudscale-devtools' ); ?></h2>
                    <button class="cs-modal-close">&times;</button>
                </div>
                <div class="cs-modal-body" id="cs-modal-body">
                    <?php esc_html_e( 'Loading...', 'cloudscale-devtools' ); ?>
                </div>
                <div class="cs-modal-footer">
                    <button id="cs-modal-migrate-btn" class="cs-btn-primary" data-post-id="" style="padding:8px 20px">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Migrate This Post', 'cloudscale-devtools' ); ?>
                    </button>
                    <button class="cs-modal-close-btn" style="background:#fff;border:1.5px solid #dce3ef;border-radius:5px;padding:6px 16px;font-size:12px;font-weight:600;cursor:pointer"><?php esc_html_e( 'Cancel', 'cloudscale-devtools' ); ?></button>
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
        <div class="cs-panel" id="cs-panel-sql">
            <div class="cs-section-header cs-section-header-purple">
                <span>🗄️ SQL Query</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Table prefix:', 'cloudscale-devtools' ); ?> <code><?php echo esc_html( $prefix ); ?></code> &nbsp;·&nbsp; ⚠ <?php esc_html_e( 'Read only (SELECT, SHOW, DESCRIBE, EXPLAIN)', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'sql', 'SQL Query Tool', [
                    [ 'name' => 'Read-only',     'rec' => 'Informational', 'desc' => 'Only SELECT, SHOW, DESCRIBE, and EXPLAIN queries are permitted. INSERT, UPDATE, DELETE, and DROP are blocked to prevent accidental data loss.' ],
                    [ 'name' => 'Table Prefix',  'rec' => 'Informational', 'desc' => 'Your WordPress table prefix is shown in the header. Use it in your queries (e.g. wp_posts, wp_options).' ],
                    [ 'name' => 'Quick Queries', 'rec' => 'Recommended',   'desc' => 'Use the preset queries below for common diagnostics without needing to write SQL from scratch.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <textarea id="cs-sql-input" class="cs-sql-textarea" placeholder="SELECT option_name, option_value FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_name = 'siteurl';"></textarea>
                <div style="display:flex;align-items:center;gap:10px;margin-top:12px">
                    <button type="button" class="cs-btn-primary" id="cs-sql-run" style="padding:8px 20px;font-size:13px">▶ <?php esc_html_e( 'Run Query', 'cloudscale-devtools' ); ?></button>
                    <button type="button" class="cs-btn-pink" id="cs-sql-clear">🧹 <?php esc_html_e( 'Clear', 'cloudscale-devtools' ); ?></button>
                    <span id="cs-sql-status" style="font-size:12px;color:#888"></span>
                    <span style="margin-left:auto;font-size:11px;color:#999"><?php esc_html_e( 'Enter or Ctrl+Enter to run', 'cloudscale-devtools' ); ?></span>
                </div>
            </div>
        </div>

        <div class="cs-panel">
            <div class="cs-section-header cs-section-header-green">
                <span>📊 <?php esc_html_e( 'Results', 'cloudscale-devtools' ); ?></span>
                <span id="cs-sql-meta" style="font-size:12px;opacity:0.85"></span>
                <?php self::render_explain_btn( 'sql-results', 'SQL Results', [
                    [ 'name' => 'Table output',       'rec' => 'Informational', 'desc' => 'Query results are shown in a scrollable table with column headers. HTTP URLs in cells are highlighted for easy identification.' ],
                    [ 'name' => 'Row count / timing', 'rec' => 'Informational', 'desc' => 'The header shows the number of rows returned and the query execution time in milliseconds.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <div id="cs-sql-results" style="overflow-x:auto;font-size:13px">
                    <div style="text-align:center;color:#999;padding:40px 0"><?php esc_html_e( 'Run a query to see results here', 'cloudscale-devtools' ); ?></div>
                </div>
            </div>
        </div>

        <div class="cs-panel">
            <div class="cs-section-header cs-section-header-orange">
                <span>⚡ <?php esc_html_e( 'Quick Queries', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'quick-queries', 'Quick Queries', [
                    [ 'name' => 'Health & Diagnostics', 'rec' => 'Recommended',   'desc' => 'MySQL version, table sizes, connection limits, and WordPress table stats at a glance.' ],
                    [ 'name' => 'Content Summary',      'rec' => 'Informational', 'desc' => 'Counts posts by type and status, revisions, auto-drafts, spam comments, and users for a quick content audit.' ],
                    [ 'name' => 'Cleanup Candidates',   'rec' => 'Optional',      'desc' => 'Identifies orphaned postmeta, expired transients, and bloated autoloaded options that may be slowing down your database.' ],
                    [ 'name' => 'Security Checks',      'rec' => 'Optional',      'desc' => 'Looks for HTTP (non-HTTPS) URLs or stale IP addresses in options and post GUIDs — common indicators of old content or unfinished migrations.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <p class="cs-quick-group-label">🏥 <?php esc_html_e( 'Health and Diagnostics', 'cloudscale-devtools' ); ?></p>
                <div class="cs-quick-grid">
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT @@version AS mysql_version, @@global.max_connections AS max_connections, @@global.wait_timeout AS wait_timeout_sec, @@global.max_allowed_packet / 1024 / 1024 AS max_packet_mb, DATABASE() AS current_db;">
                        🩺 <?php esc_html_e( 'Database health check', 'cloudscale-devtools' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT option_id, option_name, LEFT(option_value, 200) AS option_value_preview FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_name IN ('siteurl','home','blogname','blogdescription','wp_version','db_version');">
                        🏠 <?php esc_html_e( 'Site identity options', 'cloudscale-devtools' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT table_name, engine, table_rows, ROUND(data_length/1024/1024, 2) AS data_mb, ROUND(index_length/1024/1024, 2) AS index_mb, ROUND((data_length + index_length)/1024/1024, 2) AS total_mb FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY (data_length + index_length) DESC;">
                        📊 <?php esc_html_e( 'Table names, sizes and rows', 'cloudscale-devtools' ); ?>
                    </button>
                </div>

                <p class="cs-quick-group-label">📈 <?php esc_html_e( 'Content Summary', 'cloudscale-devtools' ); ?></p>
                <div class="cs-quick-grid">
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT post_type, post_status, COUNT(*) AS total FROM <?php echo esc_attr( $prefix ); ?>posts GROUP BY post_type, post_status ORDER BY total DESC;">
                        📰 <?php esc_html_e( 'Posts by type and status', 'cloudscale-devtools' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status='publish') AS published_posts, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_type='revision') AS revisions, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status='auto-draft') AS auto_drafts, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status='trash') AS trashed, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>comments) AS total_comments, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>comments WHERE comment_approved='spam') AS spam_comments, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>users) AS users, (SELECT COUNT(*) FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_name LIKE '%_transient_%') AS transients;">
                        📋 <?php esc_html_e( 'Site stats summary', 'cloudscale-devtools' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT ID, post_title, post_date, post_status FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status = 'publish' AND post_type = 'post' ORDER BY post_date DESC LIMIT 20;">
                        📝 <?php esc_html_e( 'Latest 20 published posts', 'cloudscale-devtools' ); ?>
                    </button>
                </div>

                <p class="cs-quick-group-label">🧹 <?php esc_html_e( 'Bloat and Cleanup Checks', 'cloudscale-devtools' ); ?></p>
                <div class="cs-quick-grid">
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT COUNT(*) AS orphaned_postmeta FROM <?php echo esc_attr( $prefix ); ?>postmeta pm LEFT JOIN <?php echo esc_attr( $prefix ); ?>posts p ON pm.post_id = p.ID WHERE p.ID IS NULL;">
                        🗑️ <?php esc_html_e( 'Orphaned postmeta count', 'cloudscale-devtools' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT COUNT(*) AS expired_transients FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP();">
                        ⏰ <?php esc_html_e( 'Expired transients count', 'cloudscale-devtools' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT post_type, COUNT(*) AS total FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_type = 'revision' OR post_status = 'auto-draft' OR post_status = 'trash' GROUP BY post_type, post_status ORDER BY total DESC;">
                        📦 <?php esc_html_e( 'Revisions, drafts and trash', 'cloudscale-devtools' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT LEFT(option_name, 40) AS option_name, LENGTH(option_value) AS value_bytes FROM <?php echo esc_attr( $prefix ); ?>options WHERE autoload = 'yes' ORDER BY LENGTH(option_value) DESC LIMIT 30;">
                        ⚖️ <?php esc_html_e( 'Largest autoloaded options', 'cloudscale-devtools' ); ?>
                    </button>
                </div>

                <p class="cs-quick-group-label">🔍 <?php esc_html_e( 'URL and Migration Helpers', 'cloudscale-devtools' ); ?></p>
                <div class="cs-quick-grid">
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT option_id, option_name, option_value FROM <?php echo esc_attr( $prefix ); ?>options WHERE option_value LIKE '%http://andrewbaker%';">
                        🔗 <?php esc_html_e( 'HTTP references (andrewbaker)', 'cloudscale-devtools' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT ID, post_title, post_type, post_status, guid FROM <?php echo esc_attr( $prefix ); ?>posts WHERE guid LIKE '%http://%' LIMIT 50;">
                        📰 <?php esc_html_e( 'Posts with HTTP GUIDs', 'cloudscale-devtools' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT post_id, meta_key, LEFT(meta_value, 200) AS meta_value_preview FROM <?php echo esc_attr( $prefix ); ?>postmeta WHERE meta_value LIKE '%http://54.195%' LIMIT 50;">
                        🖥️ <?php esc_html_e( 'Old IP references (postmeta)', 'cloudscale-devtools' ); ?>
                    </button>
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT ID, post_title, post_type FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status = 'publish' AND ID NOT IN (SELECT post_id FROM <?php echo esc_attr( $prefix ); ?>postmeta WHERE meta_key = '_cs_devtools_seo_desc' AND meta_value != '') ORDER BY post_date DESC LIMIT 50;">
                        📝 <?php esc_html_e( 'Posts missing meta descriptions', 'cloudscale-devtools' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php
    }

    /* ==================================================================
       5d. Login Security panel
       ================================================================== */

    /**
     * Renders the Login Security admin panel (Hide Login + 2FA settings).
     *
     * @since  1.9.0
     * @return void
     */
    private static function render_login_panel(): void {
        $hide_on      = get_option( 'cs_devtools_login_hide_enabled', '0' ) === '1';
        $slug         = get_option( 'cs_devtools_login_slug', '' );
        $method       = get_option( 'cs_devtools_2fa_method', 'off' );
        $force        = get_option( 'cs_devtools_2fa_force_admins', '0' ) === '1';
        $user_id      = get_current_user_id();
        $totp_active  = get_user_meta( $user_id, 'cs_devtools_totp_enabled', true ) === '1';
        $email_active = get_user_meta( $user_id, 'cs_devtools_2fa_email_enabled', true ) === '1';
        $current_url  = empty( $slug ) ? wp_login_url() : home_url( '/' . $slug );

        // Success notice after email verification callback.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $email_just_activated = isset( $_GET['email_2fa_activated'] ) && '1' === $_GET['email_2fa_activated'];
        ?>

        <?php if ( $email_just_activated ) : ?>
        <div class="cs-modal-overlay" id="cs-email-verified-modal" role="dialog" aria-modal="true" aria-labelledby="cs-modal-title">
            <div class="cs-modal-card">
                <div class="cs-modal-icon">✅</div>
                <h2 class="cs-modal-title" id="cs-modal-title"><?php esc_html_e( 'Email Verified!', 'cloudscale-devtools' ); ?></h2>
                <p class="cs-modal-msg"><?php esc_html_e( 'Email 2FA is now active on your account. You\'ll receive a one-time code after each password login.', 'cloudscale-devtools' ); ?></p>
                <button type="button" class="cs-btn-primary cs-modal-btn" id="cs-email-modal-close">
                    <?php esc_html_e( 'Got it', 'cloudscale-devtools' ); ?>
                </button>
                <p class="cs-modal-auto"><?php esc_html_e( 'Closing in', 'cloudscale-devtools' ); ?> <span id="cs-modal-countdown">6</span>s…</p>
            </div>
        </div>
        <script>
        (function () {
            var modal    = document.getElementById( 'cs-email-verified-modal' );
            var cd       = document.getElementById( 'cs-modal-countdown' );
            var closeBtn = document.getElementById( 'cs-email-modal-close' );
            var n = 6;
            var t = setInterval( function () {
                n--;
                if ( cd ) cd.textContent = n;
                if ( n <= 0 ) { clearInterval( t ); if ( modal ) modal.style.display = 'none'; }
            }, 1000 );
            function dismiss() { clearInterval( t ); if ( modal ) modal.style.display = 'none'; }
            if ( closeBtn ) closeBtn.addEventListener( 'click', dismiss );
            if ( modal ) modal.addEventListener( 'click', function ( e ) { if ( e.target === modal ) dismiss(); } );
        })();
        </script>
        <?php endif; ?>
        <?php
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['email_verify_expired'] ) && '1' === $_GET['email_verify_expired'] ) :
        ?>
        <div class="notice notice-error" style="margin:0 0 18px">
            <p>⏰ <strong><?php esc_html_e( 'Verification link expired. Please click Enable again to send a new one.', 'cloudscale-devtools' ); ?></strong></p>
        </div>
        <?php endif; ?>

        <!-- ── Hide Login ─────────────────────────────── -->
        <div class="cs-panel" id="cs-panel-hide-login">
            <div class="cs-section-header cs-section-header-purple">
                <span>🔒 HIDE LOGIN URL</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Move wp-login.php to a secret address', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'hide-login', 'Hide Login URL', [
                    [ 'name' => 'Enable Hide Login',  'rec' => 'Recommended', 'desc' => 'Moves your login page to a secret URL. Direct requests to /wp-login.php return a 404, stopping bots and credential-stuffing scripts from even finding the login form.' ],
                    [ 'name' => 'Custom Login Path',  'rec' => 'Recommended', 'desc' => 'The URL slug that serves your login page (e.g. /my-secret-login). Use letters, numbers, and hyphens only. Save the full URL somewhere safe — you will need it to log in.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <p class="cs-login-desc"><?php esc_html_e( 'Disables direct access to wp-login.php and serves your login page at a custom URL. Bots and scanners that probe /wp-login.php will get a 404.', 'cloudscale-devtools' ); ?></p>

                <div class="cs-toggle-row">
                    <label class="cs-toggle-label">
                        <input type="checkbox" id="cs-hide-enabled" <?php checked( $hide_on ); ?>>
                        <span class="cs-toggle-switch"></span>
                        <span class="cs-toggle-text"><?php esc_html_e( 'Enable Hide Login', 'cloudscale-devtools' ); ?></span>
                    </label>
                </div>

                <div class="cs-field-row" style="margin-top:16px">
                    <div class="cs-field">
                        <label class="cs-label" for="cs-login-slug"><?php esc_html_e( 'Custom Login Path:', 'cloudscale-devtools' ); ?></label>
                        <div class="cs-slug-row">
                            <span class="cs-slug-base"><?php echo esc_html( trailingslashit( home_url() ) ); ?></span>
                            <input type="text" id="cs-login-slug" class="cs-input cs-slug-input"
                                   value="<?php echo esc_attr( $slug ); ?>"
                                   placeholder="my-secret-login"
                                   maxlength="60" autocomplete="off" spellcheck="false">
                        </div>
                        <span class="cs-hint"><?php esc_html_e( 'Letters, numbers, and hyphens only. Save this URL — you will need it to log in.', 'cloudscale-devtools' ); ?></span>
                    </div>
                </div>

                <div class="cs-login-current-url" style="margin-top:14px">
                    <span class="cs-label" style="display:inline"><?php esc_html_e( 'Current Login URL:', 'cloudscale-devtools' ); ?></span>
                    <a id="cs-current-login-url" href="<?php echo esc_url( $current_url ); ?>" target="_blank" style="margin-left:8px;font-size:13px;color:#1e6fd9"><?php echo esc_html( $current_url ); ?></a>
                </div>

                <div style="margin-top:18px;display:flex;align-items:center;gap:10px">
                    <button type="button" class="cs-btn-primary" id="cs-hide-save">💾 <?php esc_html_e( 'Save Hide Login Settings', 'cloudscale-devtools' ); ?></button>
                    <span class="cs-settings-saved" id="cs-hide-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                </div>
            </div>
        </div>

        <!-- ── Your 2FA Setup (current user) ─────────── -->
        <div class="cs-panel">
            <div class="cs-section-header cs-section-header-green">
                <span>👤 YOUR 2FA SETUP</span>
                <span class="cs-header-hint"><?php echo esc_html( wp_get_current_user()->user_login ); ?></span>
                <?php self::render_explain_btn( '2fa-setup', 'Your 2FA Setup', [
                    [ 'name' => 'Authenticator App (TOTP)', 'rec' => 'Recommended', 'desc' => 'Generates a 6-digit code every 30 seconds using an app like Google Authenticator, Authy, or 1Password. Works offline and is the most secure 2FA method.' ],
                    [ 'name' => 'Email Code',               'rec' => 'Optional',    'desc' => 'Sends a one-time code to your account email on each login. Simpler to set up but depends on email deliverability.' ],
                    [ 'name' => 'Passkey',                  'rec' => 'Recommended', 'desc' => 'Uses Face ID, Touch ID, Windows Hello, or a hardware security key. Register a passkey in the Passkeys panel, then select this method here.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">

                <!-- Email 2FA status -->
                <?php
                // Check if a verification email is already pending for this user.
                $email_pending = (bool) get_user_meta( $user_id, 'cs_devtools_email_verify_pending', true );
                ?>
                <div class="cs-2fa-row" id="cs-email-row">
                    <div class="cs-2fa-row-icon">📧</div>
                    <div class="cs-2fa-row-body">
                        <div class="cs-2fa-row-title"><?php esc_html_e( 'Email Code', 'cloudscale-devtools' ); ?></div>
                        <div class="cs-2fa-row-desc"><?php esc_html_e( 'A 6-digit code is emailed to you after your password is accepted.', 'cloudscale-devtools' ); ?></div>
                        <div class="cs-email-pending-msg" id="cs-email-pending-msg" style="<?php echo $email_pending ? '' : 'display:none'; ?>">
                            <span class="cs-pending-notice">📬 <?php esc_html_e( 'Verification email sent — click the link in the email to activate.', 'cloudscale-devtools' ); ?></span>
                        </div>
                    </div>
                    <div class="cs-2fa-row-action">
                        <?php if ( $email_active ) : ?>
                            <span class="cs-2fa-badge cs-2fa-badge-on"><?php esc_html_e( 'Active', 'cloudscale-devtools' ); ?></span>
                            <button type="button" class="cs-btn-pink cs-2fa-disable" data-method="email" style="margin-left:10px">
                                <?php esc_html_e( 'Disable', 'cloudscale-devtools' ); ?>
                            </button>
                        <?php elseif ( $email_pending ) : ?>
                            <span class="cs-2fa-badge cs-2fa-badge-pending" id="cs-email-badge"><?php esc_html_e( 'Awaiting verification', 'cloudscale-devtools' ); ?></span>
                            <button type="button" class="cs-btn-orange cs-email-enable" id="cs-email-enable-btn" style="margin-left:10px">
                                <?php esc_html_e( 'Resend', 'cloudscale-devtools' ); ?>
                            </button>
                        <?php else : ?>
                            <span class="cs-2fa-badge cs-2fa-badge-off" id="cs-email-badge"><?php esc_html_e( 'Off', 'cloudscale-devtools' ); ?></span>
                            <button type="button" class="cs-btn-orange cs-email-enable" id="cs-email-enable-btn" style="margin-left:10px">
                                <?php esc_html_e( 'Enable', 'cloudscale-devtools' ); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cs-2fa-divider"></div>

                <!-- TOTP status + setup wizard -->
                <div class="cs-2fa-row" id="cs-totp-row">
                    <div class="cs-2fa-row-icon">📱</div>
                    <div class="cs-2fa-row-body">
                        <div class="cs-2fa-row-title"><?php esc_html_e( 'Authenticator App (TOTP)', 'cloudscale-devtools' ); ?></div>
                        <div class="cs-2fa-row-desc"><?php esc_html_e( 'Google Authenticator, Authy, 1Password, or any TOTP app. Generates a fresh 6-digit code every 30 seconds.', 'cloudscale-devtools' ); ?></div>
                    </div>
                    <div class="cs-2fa-row-action">
                        <?php if ( $totp_active ) : ?>
                            <span class="cs-2fa-badge cs-2fa-badge-on" id="cs-totp-badge"><?php esc_html_e( 'Active', 'cloudscale-devtools' ); ?></span>
                            <button type="button" class="cs-btn-pink cs-2fa-disable" data-method="totp" style="margin-left:10px" id="cs-totp-disable-btn">
                                <?php esc_html_e( 'Disable', 'cloudscale-devtools' ); ?>
                            </button>
                        <?php else : ?>
                            <span class="cs-2fa-badge cs-2fa-badge-off" id="cs-totp-badge"><?php esc_html_e( 'Not set up', 'cloudscale-devtools' ); ?></span>
                            <button type="button" class="cs-btn-primary" id="cs-totp-setup-btn" style="margin-left:10px">
                                <?php esc_html_e( 'Set Up', 'cloudscale-devtools' ); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TOTP Setup Wizard (hidden until triggered) -->
                <div id="cs-totp-wizard" class="cs-totp-wizard" style="display:none">
                    <div class="cs-totp-wizard-inner">
                        <h3 class="cs-totp-wizard-title">📱 <?php esc_html_e( 'Set Up Authenticator App', 'cloudscale-devtools' ); ?></h3>

                        <div class="cs-totp-steps">
                            <div class="cs-totp-step">
                                <span class="cs-totp-step-num">1</span>
                                <?php esc_html_e( 'Open your authenticator app (Google Authenticator, Authy, 1Password, etc.) and scan this QR code:', 'cloudscale-devtools' ); ?>
                            </div>
                            <div class="cs-totp-qr-wrap">
                                <div id="cs-totp-qr-loading" class="cs-totp-qr-loading">
                                    <span class="spinner is-active" style="float:none;margin:0"></span>
                                    <?php esc_html_e( 'Generating…', 'cloudscale-devtools' ); ?>
                                </div>
                                <div id="cs-totp-qr-canvas" class="cs-totp-qr-img" style="display:none"></div>
                            </div>
                            <div class="cs-totp-manual-wrap" style="display:none" id="cs-totp-manual">
                                <span class="cs-label" style="font-size:12px"><?php esc_html_e( "Can't scan? Enter this key manually:", 'cloudscale-devtools' ); ?></span>
                                <div class="cs-totp-secret-row">
                                    <code id="cs-totp-secret-display" class="cs-totp-secret"></code>
                                    <button type="button" id="cs-totp-copy-btn" class="cs-totp-copy-btn" title="<?php esc_attr_e( 'Copy key', 'cloudscale-devtools' ); ?>">
                                        <?php esc_html_e( 'Copy', 'cloudscale-devtools' ); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="cs-totp-step" style="margin-top:16px">
                                <span class="cs-totp-step-num">2</span>
                                <?php esc_html_e( 'Enter the 6-digit code from your app to confirm setup:', 'cloudscale-devtools' ); ?>
                            </div>
                            <div class="cs-totp-verify-row">
                                <input type="text" id="cs-totp-verify-code" class="cs-input cs-totp-code-input"
                                       placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="one-time-code">
                                <button type="button" class="cs-btn-primary" id="cs-totp-verify-btn">
                                    ✓ <?php esc_html_e( 'Verify & Activate', 'cloudscale-devtools' ); ?>
                                </button>
                            </div>
                            <div id="cs-totp-verify-msg" class="cs-totp-verify-msg" style="display:none"></div>
                        </div>

                        <div style="margin-top:12px">
                            <button type="button" class="cs-btn-pink" id="cs-totp-cancel-btn" style="font-size:11px;padding:5px 12px">
                                <?php esc_html_e( 'Cancel', 'cloudscale-devtools' ); ?>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Two-Factor Authentication ─────────────── -->
        <div class="cs-panel" id="cs-panel-2fa">
            <div class="cs-section-header cs-section-header-orange">
                <span>🔑 TWO-FACTOR AUTHENTICATION</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Email code or Authenticator app', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( '2fa', 'Two-Factor Authentication', [
                    [ 'name' => 'Off',                      'rec' => 'Not Recommended', 'desc' => 'Disables 2FA site-wide. Passwords alone are vulnerable to phishing and brute-force attacks — not recommended for any public site.' ],
                    [ 'name' => 'Email Code',               'rec' => 'Optional',        'desc' => 'Requires users to enter a code sent to their email after each password login. Works out of the box with no app required.' ],
                    [ 'name' => 'Authenticator App (TOTP)', 'rec' => 'Recommended',     'desc' => 'Each user configures their own authenticator app. Most secure option — works without internet or email.' ],
                    [ 'name' => 'Force 2FA for Admins',     'rec' => 'Recommended',     'desc' => 'Blocks administrator-role users from accessing the dashboard until they have set up 2FA. Strongly recommended on any multi-user site.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <p class="cs-login-desc"><?php esc_html_e( 'Require a second verification step after password login. Email sends a one-time code; Authenticator uses Google Authenticator, Authy, or any TOTP app.', 'cloudscale-devtools' ); ?></p>

                <!-- Site-wide default -->
                <?php
                $has_passkeys = ! empty( CS_DevTools_Passkey::get_passkeys( $user_id ) );
                ?>
                <div class="cs-field-row">
                    <div class="cs-field">
                        <label class="cs-label"><?php esc_html_e( 'Site-wide Default Method:', 'cloudscale-devtools' ); ?></label>
                        <div class="cs-2fa-method-group">
                            <label class="cs-radio-label <?php echo $method === 'off' ? 'active' : ''; ?>">
                                <input type="radio" name="cs_devtools_2fa_method" value="off" <?php checked( $method, 'off' ); ?>>
                                <span class="cs-radio-icon">🚫</span> <?php esc_html_e( 'Off', 'cloudscale-devtools' ); ?>
                            </label>
                            <label class="cs-radio-label <?php echo $method === 'email' ? 'active' : ''; ?> <?php echo ! $email_active ? 'cs-radio-disabled' : ''; ?>"
                                   <?php echo ! $email_active ? 'title="' . esc_attr__( 'Enable Email Code for your account first', 'cloudscale-devtools' ) . '"' : ''; ?>>
                                <input type="radio" name="cs_devtools_2fa_method" value="email" <?php checked( $method, 'email' ); ?> <?php disabled( ! $email_active ); ?>>
                                <span class="cs-radio-icon">📧</span> <?php esc_html_e( 'Email Code', 'cloudscale-devtools' ); ?>
                            </label>
                            <label class="cs-radio-label <?php echo $method === 'totp' ? 'active' : ''; ?> <?php echo ! $totp_active ? 'cs-radio-disabled' : ''; ?>"
                                   <?php echo ! $totp_active ? 'title="' . esc_attr__( 'Set up Authenticator App for your account first', 'cloudscale-devtools' ) . '"' : ''; ?>>
                                <input type="radio" name="cs_devtools_2fa_method" value="totp" <?php checked( $method, 'totp' ); ?> <?php disabled( ! $totp_active ); ?>>
                                <span class="cs-radio-icon">📱</span> <?php esc_html_e( 'Authenticator App', 'cloudscale-devtools' ); ?>
                            </label>
                            <label class="cs-radio-label <?php echo $method === 'passkey' ? 'active' : ''; ?> <?php echo ! $has_passkeys ? 'cs-radio-disabled' : ''; ?>"
                                   <?php echo ! $has_passkeys ? 'title="' . esc_attr__( 'Register a passkey for your account first', 'cloudscale-devtools' ) . '"' : ''; ?>>
                                <input type="radio" name="cs_devtools_2fa_method" value="passkey" <?php checked( $method, 'passkey' ); ?> <?php disabled( ! $has_passkeys ); ?>>
                                <span class="cs-radio-icon">🔑</span> <?php esc_html_e( 'Passkey', 'cloudscale-devtools' ); ?>
                            </label>
                        </div>
                        <span class="cs-hint"><?php esc_html_e( 'Sets the default method. Individual users can override if force is not enabled.', 'cloudscale-devtools' ); ?></span>
                    </div>
                    <div class="cs-field">
                        <label class="cs-label"><?php esc_html_e( 'Enforcement:', 'cloudscale-devtools' ); ?></label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:2px">
                            <input type="checkbox" id="cs-2fa-force" <?php checked( $force ); ?>>
                            <span style="font-size:13px;color:#555"><?php esc_html_e( 'Force 2FA for all administrators', 'cloudscale-devtools' ); ?></span>
                        </label>
                        <span class="cs-hint"><?php esc_html_e( 'Admins without 2FA set up will be blocked from the dashboard until they configure it.', 'cloudscale-devtools' ); ?></span>
                    </div>
                </div>

                <div style="margin-top:16px;display:flex;align-items:center;gap:10px">
                    <button type="button" class="cs-btn-primary" id="cs-2fa-save">💾 <?php esc_html_e( 'Save 2FA Settings', 'cloudscale-devtools' ); ?></button>
                    <span class="cs-settings-saved" id="cs-2fa-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                </div>
            </div>
        </div>

        <!-- ── Passkeys (WebAuthn) ────────────────────── -->
        <div class="cs-panel" id="cs-panel-passkeys">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#1e1b4b,#3730a3)">
                <span>🔑 PASSKEYS (WEBAUTHN)</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Face ID · Touch ID · Windows Hello · Security Keys', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'passkeys', 'Passkeys (WebAuthn)', [
                    [ 'name' => 'What is a passkey?',    'rec' => 'Informational', 'desc' => 'A passkey is a cryptographic credential stored on your device. It replaces passwords with biometrics (Face ID, Touch ID, Windows Hello) or hardware keys. No secret is ever sent over the network.' ],
                    [ 'name' => 'Registering a passkey', 'rec' => 'Recommended',  'desc' => 'Click "+ Add Passkey", give it a name (e.g. "iPhone 16"), then follow your device\'s biometric prompt. Register multiple passkeys for different devices.' ],
                    [ 'name' => 'Test',                  'rec' => 'Optional',     'desc' => 'Verifies a passkey is working correctly without logging out. Use this after registering to confirm the credential round-trips successfully.' ],
                    [ 'name' => 'Remove',                'rec' => 'Optional',     'desc' => 'Deletes the passkey from your account. You can re-register it at any time.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <?php CS_DevTools_Passkey::render_section( $user_id ); ?>
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
        // Strip a single trailing semicolon — a statement terminator is harmless on its own.
        $clean = rtrim( rtrim( $clean ), ';' );
        // Reject any semicolon remaining mid-query — prevents statement stacking
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
        if ( ! check_ajax_referer( 'cs_devtools_sql_nonce', 'nonce', false ) ) {
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
        if ( ! check_ajax_referer( 'cs_devtools_code_settings_inline', 'nonce', false ) ) {
            wp_send_json_error( 'Bad nonce' );
        }

        $theme = isset( $_POST['theme'] ) ? sanitize_text_field( wp_unslash( $_POST['theme'] ) ) : 'dark';
        if ( ! in_array( $theme, [ 'dark', 'light' ], true ) ) {
            $theme = 'dark';
        }
        update_option( 'cs_devtools_code_default_theme', $theme );

        $valid_pairs = array_keys( self::get_theme_registry() );
        $pair        = isset( $_POST['theme_pair'] ) ? sanitize_text_field( wp_unslash( $_POST['theme_pair'] ) ) : 'atom-one';
        if ( ! in_array( $pair, $valid_pairs, true ) ) {
            $pair = 'atom-one';
        }
        update_option( 'cs_devtools_code_theme_pair', $pair );

        $perf_enabled = isset( $_POST['cs_devtools_perf_monitor_enabled'] ) && '1' === $_POST['cs_devtools_perf_monitor_enabled'] ? '1' : '0'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        update_option( 'cs_devtools_perf_monitor_enabled', $perf_enabled );

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

        if ( $posts === null ) {
            wp_send_json_error( 'Database error: ' . ( $wpdb->last_error ?: 'could not query posts' ) );
        }

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
        wp_enqueue_style(
            'cs-perf-monitor',
            plugins_url( 'assets/cs-perf-monitor.css', __FILE__ ),
            [],
            filemtime( $base . 'cs-perf-monitor.css' )
        );
        wp_enqueue_script(
            'cs-perf-monitor',
            plugins_url( 'assets/cs-perf-monitor.js', __FILE__ ),
            [],
            filemtime( $base . 'cs-perf-monitor.js' ),
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
        wp_enqueue_style(
            'cs-perf-monitor',
            plugins_url( 'assets/cs-perf-monitor.css', __FILE__ ),
            [],
            filemtime( $base . 'cs-perf-monitor.css' )
        );
        wp_enqueue_script(
            'cs-perf-monitor',
            plugins_url( 'assets/cs-perf-monitor.js', __FILE__ ),
            [],
            filemtime( $base . 'cs-perf-monitor.js' ),
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
        global $wpdb;
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( get_option( 'cs_devtools_perf_monitor_enabled', '1' ) === '0' ) {
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
                'explain_nonce'      => wp_create_nonce( 'cs_devtools_perf_explain' ),
                'debug_nonce'        => wp_create_nonce( 'cs_devtools_perf_debug' ),
                'wp_debug'           => (bool) get_option( 'cs_devtools_perf_debug_logging', false ),
                'wp_debug_log'       => (bool) get_option( 'cs_devtools_perf_debug_logging', false ),
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
                // Environment
                'php_version'        => PHP_VERSION,
                'wp_version'         => get_bloginfo( 'version' ),
                'mysql_version'      => $wpdb->db_version(),
                'memory_limit'       => ini_get( 'memory_limit' ),
                'memory_peak_mb'     => round( memory_get_peak_usage( true ) / 1048576, 1 ),
                'active_theme'       => wp_get_theme()->get( 'Name' ),
                'is_multisite'       => is_multisite(),
            ],
            'request'    => self::perf_build_request_data(),
            'transients' => self::perf_build_transient_data(),
            'template'   => self::perf_build_template_data(),
        ];

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<script>window.csDevtoolsPerfData=' . wp_json_encode( $data ) . ';</script>' . "\n";
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo self::perf_panel_html();
    }

    /* ==================================================================
       PERFORMANCE MONITOR — Request data
       ================================================================== */

    /**
     * Collects request context: GET params, POST keys, WP query vars,
     * matched rewrite rule, and current user roles.
     *
     * @return array
     */
    private static function perf_build_request_data(): array {
        global $wp;

        // $_GET — values are in the URL so safe to show; sanitise for output.
        $get_params = [];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        foreach ( $_GET as $k => $v ) {
            $get_params[ sanitize_key( $k ) ] = is_array( $v )
                ? '(array)'
                : sanitize_text_field( wp_unslash( (string) $v ) );
        }

        // $_POST — show keys only; values could contain passwords / nonces.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $post_keys = array_map( 'sanitize_key', array_keys( $_POST ) );

        // WP query vars — only available on frontend after parse_request.
        $query_vars = [];
        if ( isset( $wp ) && is_object( $wp ) && isset( $wp->query_vars ) && is_array( $wp->query_vars ) ) {
            foreach ( $wp->query_vars as $k => $v ) {
                if ( '' === $v || false === $v || null === $v ) {
                    continue;
                }
                $query_vars[ sanitize_key( $k ) ] = is_array( $v )
                    ? '(array)'
                    : sanitize_text_field( (string) $v );
            }
        }

        return [
            'method'       => isset( $_SERVER['REQUEST_METHOD'] )
                ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
                : 'GET',
            'get'          => $get_params,
            'post_keys'    => $post_keys,
            'matched_rule' => ( isset( $wp ) && is_object( $wp ) && isset( $wp->matched_rule ) )
                ? (string) $wp->matched_rule
                : '',
            'query_vars'   => $query_vars,
            'user_roles'   => wp_get_current_user()->roles,
        ];
    }

    /* ==================================================================
       PERFORMANCE MONITOR — Transient + template hierarchy observers
       ================================================================== */

    /**
     * Single all-hook observer for transients and template hierarchy.
     * Kept in one callback to minimise per-hook overhead (this fires on every hook).
     *
     * @param mixed $value First arg of the current filter/action (may not exist).
     * @return void
     */
    public static function perf_misc_tracker( $value = null ): void {
        $hook = current_filter();
        $ch   = isset( $hook[0] ) ? $hook[0] : '';

        // ── Transient GET tracking ────────────────────────────────────────────
        if ( 'p' === $ch && strpos( $hook, 'pre_transient_' ) === 0 ) {
            $key = substr( $hook, 14 ); // strlen('pre_transient_') = 14
            if ( ! isset( self::$perf_transients[ $key ] ) ) {
                self::$perf_transients[ $key ] = [ 'gets' => 0, 'hits' => 0, 'sets' => 0, 'deletes' => 0 ];
            }
            self::$perf_transients[ $key ]['gets']++;
            return;
        }

        // ── Transient GET result (hits only; misses = gets - hits) ────────────
        if ( 't' === $ch && strpos( $hook, 'transient_' ) === 0 ) {
            $key = substr( $hook, 10 ); // strlen('transient_') = 10
            if ( isset( self::$perf_transients[ $key ] ) && false !== $value ) {
                self::$perf_transients[ $key ]['hits']++;
            }
            return;
        }

        // ── Template hierarchy capture ────────────────────────────────────────
        // Hooks like single_template_hierarchy, page_template_hierarchy etc.
        static $suffix     = '_template_hierarchy';
        static $suffix_len = 19;
        if ( strlen( $hook ) > $suffix_len
            && substr( $hook, -$suffix_len ) === $suffix
            && is_array( $value )
        ) {
            self::$perf_template_hierarchy[] = [
                'type'      => substr( $hook, 0, -$suffix_len ),
                'templates' => array_values( $value ),
            ];
        }
    }

    /**
     * Records a transient SET. Fires on setted_transient / setted_site_transient.
     *
     * @param string $transient Transient key.
     * @return void
     */
    public static function perf_transient_set( string $transient ): void {
        if ( ! isset( self::$perf_transients[ $transient ] ) ) {
            self::$perf_transients[ $transient ] = [ 'gets' => 0, 'hits' => 0, 'sets' => 0, 'deletes' => 0 ];
        }
        self::$perf_transients[ $transient ]['sets']++;
    }

    /**
     * Records a transient DELETE. Fires on deleted_transient / deleted_site_transient.
     *
     * @param string $transient Transient key.
     * @return void
     */
    public static function perf_transient_delete( string $transient ): void {
        if ( ! isset( self::$perf_transients[ $transient ] ) ) {
            self::$perf_transients[ $transient ] = [ 'gets' => 0, 'hits' => 0, 'sets' => 0, 'deletes' => 0 ];
        }
        self::$perf_transients[ $transient ]['deletes']++;
    }

    /**
     * Builds the transient stats array for the panel.
     *
     * @return array
     */
    private static function perf_build_transient_data(): array {
        $result = [];
        foreach ( self::$perf_transients as $key => $stats ) {
            // hits only counts DB hits; persistent-cache GETs intercepted via
            // pre_transient_* may not produce a matching transient_* call.
            $misses   = max( 0, $stats['gets'] - $stats['hits'] );
            $hit_rate = $stats['gets'] > 0
                ? round( ( $stats['hits'] / $stats['gets'] ) * 100 )
                : null;
            $result[] = [
                'key'      => $key,
                'gets'     => $stats['gets'],
                'hits'     => $stats['hits'],
                'misses'   => $misses,
                'sets'     => $stats['sets'],
                'deletes'  => $stats['deletes'],
                'hit_rate' => $hit_rate,
            ];
        }
        usort( $result, function ( $a, $b ) {
            return ( $b['gets'] + $b['sets'] ) - ( $a['gets'] + $a['sets'] );
        } );
        return $result;
    }

    /**
     * Builds template hierarchy data: type, ordered candidates, and which was used.
     *
     * @return array
     */
    private static function perf_build_template_data(): array {
        if ( empty( self::$perf_template_hierarchy ) ) {
            return [ 'final' => self::$perf_template, 'hierarchy' => [] ];
        }

        $child_dir  = trailingslashit( get_stylesheet_directory() );
        $parent_dir = trailingslashit( get_template_directory() );
        $is_child   = ( $child_dir !== $parent_dir );

        $hierarchy = [];
        foreach ( self::$perf_template_hierarchy as $entry ) {
            $candidates = [];
            foreach ( $entry['templates'] as $tpl ) {
                if ( file_exists( $child_dir . $tpl ) ) {
                    $found    = true;
                    $location = 'child';
                } elseif ( $is_child && file_exists( $parent_dir . $tpl ) ) {
                    $found    = true;
                    $location = 'parent';
                } else {
                    $found    = false;
                    $location = '';
                }
                $candidates[] = [
                    'file'     => $tpl,
                    'found'    => $found,
                    'location' => $location,
                    'active'   => ( $tpl === self::$perf_template ),
                ];
            }
            $hierarchy[] = [
                'type'       => $entry['type'],
                'candidates' => $candidates,
            ];
        }

        return [
            'final'     => self::$perf_template,
            'hierarchy' => $hierarchy,
        ];
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
        check_ajax_referer( 'cs_devtools_perf_explain', 'nonce' );

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
        check_ajax_referer( 'cs_devtools_perf_debug', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $enable = isset( $_POST['enable'] ) ? ( '1' === $_POST['enable'] || 'true' === $_POST['enable'] ) : null;
        if ( null === $enable ) {
            $enable = ! (bool) get_option( 'cs_devtools_perf_debug_logging', false );
        }

        if ( $enable ) {
            update_option( 'cs_devtools_perf_debug_logging', 1, false );
        } else {
            delete_option( 'cs_devtools_perf_debug_logging' );
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
     * which reads window.csDevtoolsPerfData.
     *
     * @return string HTML markup.
     */
    private static function perf_panel_html(): string {
        return '<div id="cs-perf" class="cs-perf-collapsed" role="complementary" aria-label="' . esc_attr__( 'CloudScale Performance Monitor', 'cloudscale-devtools' ) . '">'
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
                    . '<button id="cs-perf-copy" class="cs-perf-btn" title="Copy current tab to clipboard">Copy</button>'
                    . '<button id="cs-perf-help-btn" class="cs-perf-btn cs-perf-help-btn" title="What am I looking at?">?</button>'
                . '</div>'
            . '</div>'
            . '<div id="cs-perf-help" class="cs-perf-help" style="display:none">'
                . '<button id="cs-perf-help-close" class="cs-perf-help-close" aria-label="Close help">&times;</button>'
                . '<h3 class="cs-help-title">&#9889; CS Monitor — What you\'re looking at</h3>'
                . '<div class="cs-help-grid">'
                    . '<div class="cs-help-card">'
                        . '<div class="cs-help-card-title" style="color:#f44747">&#128308; Issues</div>'
                        . '<p>All critical and warning-level problems on this page load in one place — slow queries, N+1 patterns, HTTP errors, PHP errors, cache health. Click any issue to jump to the relevant tab.</p>'
                    . '</div>'
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
                        . '<div class="cs-help-card-title" style="color:#9cdcfe">&#128196; Request</div>'
                        . '<p>Current request context: GET/POST params, matched WordPress rewrite rule, WP query vars, and current user roles. Useful for debugging routing issues and unexpected page behaviour.</p>'
                    . '</div>'
                    . '<div class="cs-help-card">'
                        . '<div class="cs-help-card-title" style="color:#c586c0">&#127959; Template</div>'
                        . '<p>The template file WordPress selected to render this page, plus the full candidate hierarchy it evaluated. Use this to find which theme file is active and why.</p>'
                    . '</div>'
                    . '<div class="cs-help-card">'
                        . '<div class="cs-help-card-title" style="color:#dcdcaa">&#9202; Transients</div>'
                        . '<p>WordPress transients read and written during this request. A low hit rate means data is being regenerated every page load — a common performance overhead from uncached expensive operations.</p>'
                    . '</div>'
                    . '<div class="cs-help-card">'
                        . '<div class="cs-help-card-title cs-help-sum">&#9650; Summary</div>'
                        . '<p>Which plugin is responsible for the most DB time, the slowest queries, N+1 patterns, duplicate queries — plus environment info (PHP/WP/MySQL versions, memory peak, active theme).</p>'
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
                    . '<button class="cs-ptab"         data-tab="issues"  role="tab" aria-selected="false">Issues <span id="cs-ptc-issues">0</span></button>'
                    . '<button class="cs-ptab active"  data-tab="db"      role="tab" aria-selected="true">DB Queries <span id="cs-ptc-db">0</span></button>'
                    . '<button class="cs-ptab"         data-tab="http"    role="tab" aria-selected="false">HTTP / REST <span id="cs-ptc-http">0</span></button>'
                    . '<button class="cs-ptab"         data-tab="logs"    role="tab" aria-selected="false">Logs <span id="cs-ptc-log">0</span></button>'
                    . '<button class="cs-ptab"         data-tab="assets"  role="tab" aria-selected="false">Assets <span id="cs-ptc-assets">0</span></button>'
                    . '<button class="cs-ptab"         data-tab="hooks"   role="tab" aria-selected="false">Hooks <span id="cs-ptc-hooks">0</span></button>'
                    . '<button class="cs-ptab"         data-tab="request"   role="tab" aria-selected="false">Request</button>'
                    . '<button class="cs-ptab"         data-tab="template"  role="tab" aria-selected="false">Template</button>'
                    . '<button class="cs-ptab"         data-tab="transients" role="tab" aria-selected="false">Transients <span id="cs-ptc-trans">0</span></button>'
                    . '<button class="cs-ptab"         data-tab="summary"   role="tab" aria-selected="false">Summary</button>'
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
                . '<div id="cs-pp-issues" class="cs-ppane" role="tabpanel">'
                    . '<div id="cs-issues-wrap" class="cs-tbl-wrap cs-issues-wrap"></div>'
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
                . '<div id="cs-pp-request" class="cs-ppane" role="tabpanel">'
                    . '<div id="cs-request-wrap" class="cs-tbl-wrap cs-request-wrap"></div>'
                . '</div>'
                . '<div id="cs-pp-template" class="cs-ppane" role="tabpanel">'
                    . '<div id="cs-template-wrap" class="cs-tbl-wrap cs-template-wrap"></div>'
                . '</div>'
                . '<div id="cs-pp-transients" class="cs-ppane" role="tabpanel">'
                    . '<div class="cs-tbl-wrap">'
                        . '<table class="cs-ptable"><thead><tr>'
                            . '<th class="c-tk">Transient key</th>'
                            . '<th class="c-tg">Gets</th>'
                            . '<th class="c-th">Hits</th>'
                            . '<th class="c-tm">Misses</th>'
                            . '<th class="c-ts">Sets</th>'
                            . '<th class="c-td">Del</th>'
                            . '<th class="c-tr">Hit&nbsp;%</th>'
                        . '</tr></thead><tbody id="cs-trans-rows"></tbody></table>'
                    . '</div>'
                . '</div>'
                . '<div id="cs-pp-summary" class="cs-ppane" role="tabpanel">'
                    . '<div id="cs-summary-wrap" class="cs-tbl-wrap"></div>'
                . '</div>'
                . '<div id="cs-perf-foot"><span id="cs-perf-foot-txt"></span></div>'
            . '</div>'
        . '</div>' . "\n";
    }

    /* ==================================================================
       8. LOGIN SECURITY — Hide Login + 2FA (Email / TOTP)
       ================================================================== */

    // ── Constants ────────────────────────────────────────────────────────

    const LOGIN_NONCE           = 'cs_devtools_login_nonce';
    const LOGIN_2FA_TRANSIENT   = 'cs_devtools_2fa_pending_';    // + random token
    const LOGIN_OTP_TRANSIENT   = 'cs_devtools_2fa_otp_';        // + user_id
    const EMAIL_VERIFY_TRANSIENT = 'cs_devtools_email_verify_';  // + random token (10 min)
    const TOTP_CHARS            = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // ── A. Hide Login — custom URL slug ──────────────────────────────────

    /**
     * Fired on `init` at priority 1. If the current request matches the
     * custom login slug, serve wp-login.php transparently from that URL.
     *
     * @since  1.9.0
     * @return void
     */
    public static function login_serve_custom_slug(): void {
        if ( get_option( 'cs_devtools_login_hide_enabled', '0' ) !== '1' ) {
            return;
        }
        $slug = get_option( 'cs_devtools_login_slug', '' );
        if ( empty( $slug ) ) {
            return;
        }

        $request = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $path    = wp_parse_url( $request, PHP_URL_PATH );
        if ( ! is_string( $path ) ) {
            return;
        }
        $path       = rtrim( $path, '/' );
        $home_path  = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
        $target     = $home_path . '/' . $slug;

        if ( $path !== $target ) {
            return;
        }

        // Mark that we arrived via the correct custom URL.
        define( 'CS_DEVTOOLS_LOGIN_CUSTOM_SLUG', true );

        // Set $pagenow so plugins that check `$pagenow === 'wp-login.php'`
        // behave correctly (e.g. security plugins, CAPTCHA plugins).
        global $pagenow;
        $pagenow = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- intentional: serving login page at custom URL

        // Adjust server globals so wp-login.php sees itself at its real path
        // and generates correct self-referencing form actions.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $_SERVER['PHP_SELF']        = '/wp-login.php';
        $_SERVER['SCRIPT_FILENAME'] = ABSPATH . 'wp-login.php';
        // Keep REQUEST_URI pointing to the custom slug so redirect_to round-trips
        // correctly; site_url filter handles the form action rewrite.

        require_once ABSPATH . 'wp-login.php'; // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude.FileIncludeFound
        exit;
    }

    /**
     * Fired on `login_init` at priority 1. Blocks direct access to
     * wp-login.php when Hide Login is enabled.
     *
     * @since  1.9.0
     * @return void
     */
    public static function login_block_direct_access(): void {
        if ( get_option( 'cs_devtools_login_hide_enabled', '0' ) !== '1' ) {
            return;
        }
        $slug = get_option( 'cs_devtools_login_slug', '' );
        if ( empty( $slug ) ) {
            return;
        }
        // Allow through: arrived via the correct custom slug.
        if ( defined( 'CS_DEVTOOLS_LOGIN_CUSTOM_SLUG' ) && CS_DEVTOOLS_LOGIN_CUSTOM_SLUG ) {
            return;
        }
        // Allow through: WP-CLI, cron, XMLRPC, and REST don't use the browser login form.
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return;
        }
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
            return;
        }
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            return;
        }
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return;
        }
        // Allow through: safe wp-login.php actions (password reset emails, logout, postpass).
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $safe   = [ 'logout', 'lostpassword', 'rp', 'resetpass', 'postpass', 'register', 'cs_devtools_2fa' ];
        if ( in_array( $action, $safe, true ) ) {
            return;
        }
        // Block — redirect direct /wp-login.php access to home.
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    /**
     * Replaces wp_login_url() return value with the custom slug URL when enabled.
     *
     * @since  1.9.0
     * @param  string $url
     * @param  string $redirect
     * @param  bool   $force_reauth
     * @return string
     */
    public static function login_custom_url( string $url, string $redirect, bool $force_reauth ): string {
        if ( get_option( 'cs_devtools_login_hide_enabled', '0' ) !== '1' ) {
            return $url;
        }
        $slug = get_option( 'cs_devtools_login_slug', '' );
        if ( empty( $slug ) ) {
            return $url;
        }
        $custom = home_url( '/' . $slug . '/' );
        if ( ! empty( $redirect ) ) {
            $custom = add_query_arg( 'redirect_to', urlencode( $redirect ), $custom );
        }
        if ( $force_reauth ) {
            $custom = add_query_arg( 'reauth', '1', $custom );
        }
        return $custom;
    }

    /**
     * Replaces the logout URL when Hide Login is enabled.
     *
     * @since  1.9.0
     * @param  string $url
     * @param  string $redirect
     * @return string
     */
    public static function login_custom_logout_url( string $url, string $redirect ): string {
        if ( get_option( 'cs_devtools_login_hide_enabled', '0' ) !== '1' ) {
            return $url;
        }
        $slug = get_option( 'cs_devtools_login_slug', '' );
        if ( empty( $slug ) ) {
            return $url;
        }
        $nonce  = wp_create_nonce( 'log-out' );
        $custom = home_url( '/' . $slug . '/?action=logout&_wpnonce=' . $nonce );
        if ( ! empty( $redirect ) ) {
            $custom = add_query_arg( 'redirect_to', urlencode( $redirect ), $custom );
        }
        return $custom;
    }

    /**
     * Replaces the lost-password URL when Hide Login is enabled.
     *
     * @since  1.9.0
     * @param  string $url
     * @param  string $redirect
     * @return string
     */
    public static function login_custom_lostpassword_url( string $url, string $redirect ): string {
        if ( get_option( 'cs_devtools_login_hide_enabled', '0' ) !== '1' ) {
            return $url;
        }
        $slug = get_option( 'cs_devtools_login_slug', '' );
        if ( empty( $slug ) ) {
            return $url;
        }
        $custom = home_url( '/' . $slug . '/?action=lostpassword' );
        if ( ! empty( $redirect ) ) {
            $custom = add_query_arg( 'redirect_to', urlencode( $redirect ), $custom );
        }
        return $custom;
    }

    /**
     * Rewrites network_site_url() calls that reference wp-login.php.
     *
     * @since  1.9.0
     * @param  string $url
     * @param  string $path
     * @param  string $scheme
     * @return string
     */
    public static function login_custom_network_url( string $url, string $path, ?string $scheme ): string {
        return self::login_rewrite_login_url( $url, $path );
    }

    /**
     * Rewrites site_url() calls that reference wp-login.php.
     *
     * @since  1.9.0
     * @param  string $url
     * @param  string $path
     * @param  string $scheme
     * @param  int    $blog_id
     * @return string
     */
    public static function login_custom_site_url( string $url, string $path, ?string $scheme, $blog_id ): string {
        return self::login_rewrite_login_url( $url, $path );
    }

    /**
     * Helper: replaces wp-login.php in a URL with the custom slug.
     *
     * @since  1.9.0
     * @param  string $url
     * @param  string $path
     * @return string
     */
    private static function login_rewrite_login_url( string $url, string $path ): string {
        if ( get_option( 'cs_devtools_login_hide_enabled', '0' ) !== '1' ) {
            return $url;
        }
        $slug = get_option( 'cs_devtools_login_slug', '' );
        if ( empty( $slug ) || strpos( $path, 'wp-login.php' ) === false ) {
            return $url;
        }
        return str_replace( 'wp-login.php', $slug . '/', $url );
    }

    // ── B. Two-Factor Authentication ─────────────────────────────────────

    /**
     * Intercepts successful authentication and triggers 2FA when required.
     * Hooked to `authenticate` at priority 100 (after core password check at 20).
     *
     * @since  1.9.0
     * @param  \WP_User|\WP_Error|null $user
     * @param  string                  $username
     * @param  string                  $password
     * @return \WP_User|\WP_Error|null
     */
    public static function login_2fa_intercept( $user, string $username, string $password ) {
        // Only act on a successfully authenticated user.
        if ( ! ( $user instanceof \WP_User ) ) {
            return $user;
        }

        $method = self::login_2fa_method_for_user( $user );
        if ( $method === 'off' ) {
            return $user;
        }

        // Avoid triggering 2FA during a 2FA verification POST itself.
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $action === 'cs_devtools_2fa' ) {
            return $user;
        }

        // Generate a short-lived pending token.
        $token = wp_generate_password( 32, false, false );
        $data  = [
            'user_id' => $user->ID,
            'method'  => $method,
            'created' => time(),
        ];

        if ( $method === 'email' ) {
            // Generate + store OTP.
            $otp = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
            set_transient( self::LOGIN_OTP_TRANSIENT . $user->ID, wp_hash( $otp ), 600 );
            // Send it.
            $site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
            $to   = $user->user_email;
            $subj = sprintf( '[%s] Your login code', $site );
            $body = self::email_html_otp( $user->display_name, $site, $otp );
            add_filter( 'wp_mail_content_type', [ __CLASS__, 'email_content_type_html' ] );
            wp_mail( $to, $subj, $body );
            remove_filter( 'wp_mail_content_type', [ __CLASS__, 'email_content_type_html' ] );
        }

        set_transient( self::LOGIN_2FA_TRANSIENT . $token, $data, 600 );

        // Redirect to the 2FA form.
        $login_url = add_query_arg( [
            'action'   => 'cs_devtools_2fa',
            'cs_devtools_token' => rawurlencode( $token ),
        ], wp_login_url() );

        wp_safe_redirect( $login_url );
        exit;
    }

    /**
     * Fired on `login_init`. Handles the 2FA code entry form: display and verification.
     *
     * @since  1.9.0
     * @return void
     */
    public static function login_2fa_handle(): void {
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $action !== 'cs_devtools_2fa' ) {
            return;
        }

        $token   = isset( $_REQUEST['cs_devtools_token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cs_devtools_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $pending = $token ? get_transient( self::LOGIN_2FA_TRANSIENT . $token ) : false;

        // Invalid or expired token → back to login.
        if ( ! $pending || empty( $pending['user_id'] ) ) {
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        $user_id = (int) $pending['user_id'];
        $method  = $pending['method'];
        $error   = '';

        // ── Passkey → email fallback ─────────────────────────────────────────
        if ( $method === 'passkey' && ! empty( $_POST['cs_devtools_pk_fallback'] ) ) {
            // Only send a new OTP if one hasn't been sent in the last 30 seconds (prevents spam from double-clicks).
            $rate_key    = 'cs_devtools_pk_fb_' . $user_id;
            $already_sent = get_transient( $rate_key );
            if ( ! $already_sent ) {
                $user = get_user_by( 'id', $user_id );
                if ( $user instanceof \WP_User ) {
                    $otp  = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
                    set_transient( self::LOGIN_OTP_TRANSIENT . $user_id, wp_hash( $otp ), 600 );
                    set_transient( $rate_key, 1, 30 ); // block re-sends for 30s
                    $site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
                    add_filter( 'wp_mail_content_type', [ __CLASS__, 'email_content_type_html' ] );
                    wp_mail( $user->user_email, sprintf( '[%s] Your login code', $site ), self::email_html_otp( $user->display_name, $site, $otp ) );
                    remove_filter( 'wp_mail_content_type', [ __CLASS__, 'email_content_type_html' ] );
                }
            }
            // Update the transient to use email method.
            $pending['method'] = 'email';
            set_transient( self::LOGIN_2FA_TRANSIENT . $token, $pending, 600 );
            $method = 'email';
        }

        // ── Passkey assertion (POST from cs-passkey login page) ──────────────
        if ( $method === 'passkey' && isset( $_POST['cs_devtools_pk_cred_id'] ) ) {
            $result = CS_DevTools_Passkey::verify_login_assertion( $token, $user_id );
            if ( $result === true ) {
                delete_transient( self::LOGIN_2FA_TRANSIENT . $token );
                wp_set_auth_cookie( $user_id, false );
                $redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : admin_url();
                wp_safe_redirect( $redirect );
                exit;
            }
            // Verification failed — re-render challenge with error.
            $error = $result->get_error_message();
        }

        // ── Passkey challenge page (GET or re-render after failure) ──────────
        if ( $method === 'passkey' && empty( $_POST['cs_devtools_2fa_code'] ) ) {
            CS_DevTools_Passkey::render_login_challenge( $token, $user_id, $error );
            // render_login_challenge() exits.
        }

        // Handle code submission.
        if ( isset( $_POST['cs_devtools_2fa_code'] ) ) {
            if ( ! isset( $_POST['cs_devtools_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cs_devtools_2fa_nonce'] ) ), 'cs_devtools_2fa_verify_' . $token ) ) {
                $error = __( 'Security check failed. Please try again.', 'cloudscale-devtools' );
            } else {
                $code    = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['cs_devtools_2fa_code'] ) ) );
                $user    = get_user_by( 'id', $user_id );
                $valid   = false;

                if ( $user instanceof \WP_User ) {
                    if ( $method === 'email' ) {
                        $stored = get_transient( self::LOGIN_OTP_TRANSIENT . $user_id );
                        if ( $stored && hash_equals( $stored, wp_hash( $code ) ) ) {
                            $valid = true;
                            delete_transient( self::LOGIN_OTP_TRANSIENT . $user_id );
                        }
                    } elseif ( $method === 'totp' ) {
                        $secret = get_user_meta( $user_id, 'cs_devtools_totp_secret', true );
                        if ( $secret ) {
                            $valid = self::totp_verify( (string) $secret, $code );
                        }
                    }
                }

                if ( $valid ) {
                    delete_transient( self::LOGIN_2FA_TRANSIENT . $token );
                    // Complete the login.
                    wp_set_auth_cookie( $user_id, false );
                    $redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : admin_url();
                    wp_safe_redirect( $redirect );
                    exit;
                } else {
                    $error = __( 'Invalid code. Please try again.', 'cloudscale-devtools' );
                }
            }
        }

        // Render the 2FA form.
        self::login_2fa_render_form( $token, $method, $error );
        exit;
    }

    /**
     * Outputs the 2FA code entry page using WordPress's own login styles.
     *
     * @since  1.9.0
     * @param  string $token  Pending auth token.
     * @param  string $method 'email' or 'totp'.
     * @param  string $error  Optional error message.
     * @return void
     */
    private static function login_2fa_render_form( string $token, string $method, string $error = '' ): void {
        // Use WordPress's own login page scaffolding.
        login_header( __( 'Two-Factor Authentication', 'cloudscale-devtools' ), '', null );

        $nonce      = wp_create_nonce( 'cs_devtools_2fa_verify_' . $token );
        $method_txt = $method === 'email'
            ? __( 'Enter the 6-digit code that was sent to your email address.', 'cloudscale-devtools' )
            : __( 'Enter the 6-digit code from your authenticator app.', 'cloudscale-devtools' );

        $icon = $method === 'email' ? '📧' : '📱';
        ?>
        <form name="cs_devtools_2faform" id="cs_devtools_2faform" action="" method="post">
            <p style="text-align:center;font-size:48px;margin:0 0 8px"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — emoji literal ?></p>
            <p style="text-align:center;margin:0 0 20px;color:#555;font-size:13px;line-height:1.5"><?php echo esc_html( $method_txt ); ?></p>

            <?php if ( $error ) : ?>
                <div id="login_error" class="notice notice-error" style="margin:0 0 16px"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endif; ?>

            <p>
                <label for="cs_devtools_2fa_code"><?php esc_html_e( 'Authentication Code', 'cloudscale-devtools' ); ?></label>
                <input type="text" name="cs_devtools_2fa_code" id="cs_devtools_2fa_code" class="input"
                       value="" size="20" maxlength="6"
                       inputmode="numeric" autocomplete="one-time-code"
                       placeholder="000000"
                       autofocus style="text-align:center;font-size:22px;letter-spacing:6px">
            </p>

            <?php
            // Pass redirect_to through if it was in the original login URL.
            $redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $redirect ) {
                echo '<input type="hidden" name="redirect_to" value="' . esc_attr( $redirect ) . '">';
            }
            ?>

            <input type="hidden" name="action"     value="cs_devtools_2fa">
            <input type="hidden" name="cs_devtools_token"   value="<?php echo esc_attr( $token ); ?>">
            <input type="hidden" name="cs_devtools_2fa_nonce" value="<?php echo esc_attr( $nonce ); ?>">

            <p class="submit">
                <input type="submit" name="wp-submit" id="wp-submit"
                       class="button button-primary button-large"
                       value="<?php esc_attr_e( 'Verify Code', 'cloudscale-devtools' ); ?>">
            </p>

            <?php if ( $method === 'email' ) : ?>
                <p style="text-align:center;margin-top:12px;font-size:12px;color:#888">
                    <?php esc_html_e( 'Didn\'t receive a code? Check your spam folder or wait up to 1 minute.', 'cloudscale-devtools' ); ?>
                </p>
            <?php endif; ?>
        </form>
        <?php
        login_footer();
    }

    /**
     * Determines which 2FA method applies to a given user.
     * Returns 'off', 'email', or 'totp'.
     *
     * @since  1.9.0
     * @param  \WP_User $user
     * @return string
     */
    private static function login_2fa_method_for_user( \WP_User $user ): string {
        $site_method = get_option( 'cs_devtools_2fa_method', 'off' );
        $force       = get_option( 'cs_devtools_2fa_force_admins', '0' ) === '1';

        // Passkeys always take priority when the user has any registered.
        if ( ! empty( CS_DevTools_Passkey::get_passkeys( $user->ID ) ) ) {
            return 'passkey';
        }

        // If force is on and user is admin, enforce the site method.
        if ( $force && user_can( $user, 'manage_options' ) && $site_method !== 'off' ) {
            // If TOTP forced but user hasn't set it up, fall back to email.
            if ( $site_method === 'totp' && get_user_meta( $user->ID, 'cs_devtools_totp_enabled', true ) !== '1' ) {
                return 'email';
            }
            return $site_method;
        }

        // Per-user TOTP.
        if ( get_user_meta( $user->ID, 'cs_devtools_totp_enabled', true ) === '1' ) {
            return 'totp';
        }

        // Per-user email 2FA.
        if ( get_user_meta( $user->ID, 'cs_devtools_2fa_email_enabled', true ) === '1' ) {
            return 'email';
        }

        // Fall back to site-wide default.
        if ( $site_method !== 'off' ) {
            if ( $site_method === 'passkey' ) {
                return 'email'; // no passkeys registered — fall back to email
            }
            // TOTP as site default only applies if user has it set up.
            if ( $site_method === 'totp' ) {
                return 'email'; // safe fallback
            }
            return $site_method;
        }

        return 'off';
    }

    // ── C. TOTP (RFC 6238) — pure PHP, no Composer dependency ────────────

    /**
     * Generates a random Base32 secret for TOTP.
     *
     * @since  1.9.0
     * @param  int $length Number of Base32 characters (16 = 80 bits of entropy).
     * @return string
     */
    private static function totp_generate_secret( int $length = 16 ): string {
        $secret = '';
        for ( $i = 0; $i < $length; $i++ ) {
            $secret .= self::TOTP_CHARS[ random_int( 0, 31 ) ];
        }
        return $secret;
    }

    /**
     * Decodes a Base32-encoded string to raw binary.
     *
     * @since  1.9.0
     * @param  string $input Base32 string (upper-case, no padding required).
     * @return string Binary string.
     */
    private static function base32_decode( string $input ): string {
        $input  = strtoupper( rtrim( $input, '=' ) );
        $output = '';
        $buffer = 0;
        $bits   = 0;
        for ( $i = 0, $len = strlen( $input ); $i < $len; $i++ ) {
            $val = strpos( self::TOTP_CHARS, $input[ $i ] );
            if ( $val === false ) {
                continue;
            }
            $buffer = ( $buffer << 5 ) | $val;
            $bits  += 5;
            if ( $bits >= 8 ) {
                $bits   -= 8;
                $output .= chr( ( $buffer >> $bits ) & 0xFF );
            }
        }
        return $output;
    }

    /**
     * Computes a 6-digit HOTP code for the given key and counter (RFC 4226 / 6238).
     *
     * @since  1.9.0
     * @param  string $secret_b32 Base32-encoded shared secret.
     * @param  int    $counter    TOTP counter value (floor(unix_time / 30)).
     * @return string Zero-padded 6-digit string.
     */
    private static function totp_compute( string $secret_b32, int $counter ): string {
        $key          = self::base32_decode( $secret_b32 );
        // Pack counter as 8-byte big-endian integer.
        $counter_bytes = pack( 'N*', 0 ) . pack( 'N*', $counter );
        $hmac          = hash_hmac( 'sha1', $counter_bytes, $key, true );
        $offset        = ord( $hmac[19] ) & 0x0F;
        $code          = (
            ( ( ord( $hmac[ $offset ]     ) & 0x7F ) << 24 ) |
            ( ( ord( $hmac[ $offset + 1 ] ) & 0xFF ) << 16 ) |
            ( ( ord( $hmac[ $offset + 2 ] ) & 0xFF ) <<  8 ) |
            (   ord( $hmac[ $offset + 3 ] ) & 0xFF )
        ) % 1000000;
        return str_pad( (string) $code, 6, '0', STR_PAD_LEFT );
    }

    /**
     * Verifies a TOTP code against the secret, allowing ±1 time-step for clock drift.
     *
     * @since  1.9.0
     * @param  string $secret_b32 Base32-encoded shared secret.
     * @param  string $code       6-digit code to verify.
     * @return bool
     */
    private static function totp_verify( string $secret_b32, string $code ): bool {
        $counter = (int) floor( time() / 30 );
        for ( $offset = -1; $offset <= 1; $offset++ ) {
            if ( hash_equals( self::totp_compute( $secret_b32, $counter + $offset ), $code ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Builds the otpauth:// URI used to provision authenticator apps via QR code.
     *
     * @since  1.9.0
     * @param  string $secret_b32 Base32-encoded secret.
     * @param  string $user_email User email shown in the app.
     * @return string Full otpauth:// URI.
     */
    private static function totp_provisioning_uri( string $secret_b32, string $user_email ): string {
        $issuer  = rawurlencode( get_bloginfo( 'name' ) );
        $account = rawurlencode( $user_email );
        return 'otpauth://totp/' . $issuer . ':' . $account
               . '?secret=' . rawurlencode( $secret_b32 )
               . '&issuer=' . $issuer
               . '&algorithm=SHA1&digits=6&period=30';
    }

    // ── D. AJAX handlers ─────────────────────────────────────────────────

    /**
     * AJAX: saves Hide Login and 2FA site-wide settings.
     *
     * @since  1.9.0
     * @return void
     */
    public static function ajax_login_save(): void {
        check_ajax_referer( self::LOGIN_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        // Hide Login
        $hide = isset( $_POST['hide_enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['hide_enabled'] ) ) ? '1' : '0';
        $slug = isset( $_POST['login_slug'] ) ? sanitize_title( wp_unslash( $_POST['login_slug'] ) ) : '';
        $reserved = [ 'wp-login', 'wp-admin', 'login', 'admin', 'dashboard' ];
        if ( in_array( $slug, $reserved, true ) ) {
            wp_send_json_error( __( 'That slug is reserved. Please choose a different one.', 'cloudscale-devtools' ) );
        }
        update_option( 'cs_devtools_login_hide_enabled', $hide );
        update_option( 'cs_devtools_login_slug', $slug );

        // 2FA
        $method = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : 'off';
        if ( ! in_array( $method, [ 'off', 'email', 'totp' ], true ) ) {
            $method = 'off';
        }
        $force = isset( $_POST['force_admins'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['force_admins'] ) ) ? '1' : '0';
        update_option( 'cs_devtools_2fa_method', $method );
        update_option( 'cs_devtools_2fa_force_admins', $force );

        $new_url = $hide === '1' && $slug ? home_url( '/' . $slug . '/' ) : wp_login_url();
        wp_send_json_success( [ 'login_url' => $new_url ] );
    }

    /**
     * AJAX: generates a new TOTP secret and returns the QR code URL for setup.
     * Stores the secret as a pending (unconfirmed) user meta key.
     *
     * @since  1.9.0
     * @return void
     */
    public static function ajax_totp_setup_start(): void {
        check_ajax_referer( self::LOGIN_NONCE, 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $user_id = get_current_user_id();
        $secret  = self::totp_generate_secret();

        // Store as pending until the user verifies their first code.
        update_user_meta( $user_id, 'cs_devtools_totp_secret_pending', $secret );

        $email = wp_get_current_user()->user_email;
        $uri   = self::totp_provisioning_uri( $secret, $email );

        wp_send_json_success( [
            'otpauth' => $uri,
            'secret'  => $secret,
        ] );
    }

    /**
     * AJAX: verifies the first TOTP code to activate the pending secret.
     *
     * @since  1.9.0
     * @return void
     */
    public static function ajax_totp_setup_verify(): void {
        check_ajax_referer( self::LOGIN_NONCE, 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $code    = isset( $_POST['code'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) : '';
        $user_id = get_current_user_id();
        $secret  = get_user_meta( $user_id, 'cs_devtools_totp_secret_pending', true );

        if ( ! $secret ) {
            wp_send_json_error( __( 'No pending setup found. Please start setup again.', 'cloudscale-devtools' ) );
        }
        if ( strlen( $code ) !== 6 ) {
            wp_send_json_error( __( 'Please enter a 6-digit code.', 'cloudscale-devtools' ) );
        }

        if ( ! self::totp_verify( $secret, $code ) ) {
            wp_send_json_error( __( 'Code incorrect. Check your app\'s time sync and try again.', 'cloudscale-devtools' ) );
        }

        // Activate: promote pending secret to live.
        update_user_meta( $user_id, 'cs_devtools_totp_secret', $secret );
        update_user_meta( $user_id, 'cs_devtools_totp_enabled', '1' );
        delete_user_meta( $user_id, 'cs_devtools_totp_secret_pending' );

        // If user had email 2FA, disable it (TOTP is preferred).
        delete_user_meta( $user_id, 'cs_devtools_2fa_email_enabled' );

        // Security state changed — destroy all other open sessions.
        wp_destroy_other_sessions();

        wp_send_json_success( [ 'message' => __( 'Authenticator app activated!', 'cloudscale-devtools' ) ] );
    }

    /**
     * AJAX: disables 2FA for the current user (email or TOTP).
     *
     * @since  1.9.0
     * @return void
     */
    public static function ajax_2fa_disable(): void {
        check_ajax_referer( self::LOGIN_NONCE, 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $method  = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : '';
        $user_id = get_current_user_id();

        if ( $method === 'totp' ) {
            delete_user_meta( $user_id, 'cs_devtools_totp_secret' );
            delete_user_meta( $user_id, 'cs_devtools_totp_secret_pending' );
            update_user_meta( $user_id, 'cs_devtools_totp_enabled', '0' );
        } elseif ( $method === 'email' ) {
            update_user_meta( $user_id, 'cs_devtools_2fa_email_enabled', '0' );
            delete_user_meta( $user_id, 'cs_devtools_email_verify_pending' );
        } else {
            wp_send_json_error( 'Unknown method.' );
        }

        // Security state changed — destroy all other open sessions.
        wp_destroy_other_sessions();

        wp_send_json_success( [ 'message' => __( '2FA disabled.', 'cloudscale-devtools' ) ] );
    }

    /**
     * AJAX: sends a verification email with a callback link.
     * Email 2FA is only activated once the user clicks the link (10-min TTL).
     * Reuses this handler for both first-enable and Resend.
     *
     * Pre-send diagnostics are sourced via the `cloudscale_email_diagnostics`
     * filter — the CloudScale Backup & Restore plugin hooks this when active,
     * providing port/MTA/relay checks. If nothing hooks the filter we fall back
     * to wp_mail_failed to surface the actual SMTP transport error instead.
     *
     * @since  1.9.0
     * @return void
     */
    public static function ajax_email_2fa_enable(): void {
        check_ajax_referer( self::LOGIN_NONCE, 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $user    = wp_get_current_user();
        $user_id = $user->ID;

        // ── Pre-send diagnostics (port / MTA / relay) ─────────────────────
        // Returns [ 'warning' => string, 'fatal' => bool ] or null when no
        // plugin has registered diagnostics for this environment.
        // CloudScale Backup & Restore hooks this filter when active.
        $diag    = apply_filters( 'cloudscale_email_diagnostics', null );
        $warning = is_array( $diag ) ? (string) ( $diag['warning'] ?? '' ) : '';
        $fatal   = is_array( $diag ) && ! empty( $diag['fatal'] );

        if ( $fatal ) {
            wp_send_json_error( [
                'message'      => __( 'Email cannot be sent from this server — see warning below.', 'cloudscale-devtools' ),
                'port_warning' => $warning,
            ] );
        }

        // ── Capture the actual SMTP transport error via wp_mail_failed ─────
        // Used when no external diagnostic plugin is active; gives the real
        // error string rather than a guessed port-probe message.
        $transport_error = '';
        $on_mail_failed  = static function ( \WP_Error $err ) use ( &$transport_error ): void {
            $transport_error = $err->get_error_message();
        };
        add_action( 'wp_mail_failed', $on_mail_failed );

        // ── Generate a single-use verification token (1-hour TTL) ────────
        $token     = wp_generate_password( 32, false, false );
        $transient = self::EMAIL_VERIFY_TRANSIENT . $token;
        set_transient( $transient, [ 'user_id' => $user_id ], 3600 );
        update_user_meta( $user_id, 'cs_devtools_email_verify_pending', '1' );

        $callback = add_query_arg(
            [ 'cs_devtools_email_verify' => rawurlencode( $token ) ],
            admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=login' )
        );

        $site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        add_filter( 'wp_mail_content_type', [ __CLASS__, 'email_content_type_html' ] );
        $sent = wp_mail(
            $user->user_email,
            sprintf( '[%s] Verify your email for 2FA', $site ),
            self::email_html_verify( $user->display_name, $site, $callback )
        );
        remove_filter( 'wp_mail_content_type', [ __CLASS__, 'email_content_type_html' ] );

        remove_action( 'wp_mail_failed', $on_mail_failed );

        if ( ! $sent ) {
            delete_transient( $transient );
            delete_user_meta( $user_id, 'cs_devtools_email_verify_pending' );
            // Surface the real SMTP error if captured, otherwise the warning from diagnostics.
            $detail = $transport_error ?: $warning ?: __( 'Check your WordPress mail configuration.', 'cloudscale-devtools' );
            wp_send_json_error( [
                'message'      => sprintf( __( 'Email not sent: %s', 'cloudscale-devtools' ), $detail ),
                'port_warning' => $warning,
            ] );
        }

        $msg = sprintf(
            /* translators: %s: email address */
            __( 'Verification email sent to %s. Click the link to activate 2FA.', 'cloudscale-devtools' ),
            $user->user_email
        );

        wp_send_json_success( [
            'message'      => $msg . ( $warning ? ' ' . __( '(See warning below.)', 'cloudscale-devtools' ) : '' ),
            'port_warning' => $warning,
        ] );
    }

    /** Sets wp_mail content type to HTML (used temporarily around branded emails). */
    public static function email_content_type_html(): string {
        return 'text/html';
    }

    /**
     * Returns the branded HTML wrapper used by all CS security emails.
     *
     * @param string $inner_html Body content (already escaped).
     * @return string Full HTML document.
     */
    private static function email_html_wrap( string $inner_html ): string {
        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CloudScale</title>
</head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f0f2f5;">
  <tr><td align="center" style="padding:40px 16px;">
    <table width="560" cellpadding="0" cellspacing="0" role="presentation" style="max-width:560px;width:100%;">

      <!-- Header -->
      <tr>
        <td style="background:linear-gradient(135deg,#1a1f3c 0%,#2d3561 100%);border-radius:12px 12px 0 0;padding:28px 36px;text-align:center;">
          <span style="font-size:22px;font-weight:700;color:#ffffff;letter-spacing:-0.3px;">
            &#x26A1; CloudScale
          </span>
          <div style="font-size:11px;color:#a0aec0;margin-top:4px;letter-spacing:0.5px;text-transform:uppercase;">Code &amp; Security</div>
        </td>
      </tr>

      <!-- Body -->
      <tr>
        <td style="background:#ffffff;padding:36px 36px 32px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;">
          ' . $inner_html . '
        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td style="background:#f8fafc;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;padding:18px 36px;text-align:center;">
          <p style="margin:0;font-size:12px;color:#94a3b8;">
            You\'re receiving this because you have an account on this site.<br>
            If you didn\'t request this, you can safely ignore it.
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';
    }

    /**
     * HTML email body for the 2FA one-time login code.
     *
     * @param string $display_name User's display name.
     * @param string $site         Site name (already decoded).
     * @param string $otp          6-digit code.
     * @return string Full HTML email.
     */
    private static function email_html_otp( string $display_name, string $site, string $otp ): string {
        $inner = '
          <p style="margin:0 0 20px;font-size:15px;color:#1a202c;">Hi ' . esc_html( $display_name ) . ',</p>
          <p style="margin:0 0 24px;font-size:15px;color:#4a5568;line-height:1.6;">
            Your one-time login code for <strong>' . esc_html( $site ) . '</strong>:
          </p>

          <!-- Code box -->
          <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:28px;">
            <tr>
              <td align="center" style="background:#f0f4ff;border:2px solid #c7d2fe;border-radius:10px;padding:20px 16px;">
                <span style="font-family:\'Courier New\',Courier,monospace;font-size:38px;font-weight:700;letter-spacing:10px;color:#3730a3;">' . esc_html( $otp ) . '</span>
              </td>
            </tr>
          </table>

          <p style="margin:0 0 20px;font-size:13px;color:#718096;text-align:center;">
            &#x23F0; This code expires in <strong>10 minutes</strong>.
          </p>
          <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0;">
          <p style="margin:0;font-size:12px;color:#e53e3e;">
            &#x26A0;&#xFE0F; If you did not attempt to log in, please <strong>change your password immediately</strong>.
          </p>';

        return self::email_html_wrap( $inner );
    }

    /**
     * HTML email body for the email 2FA verification link.
     *
     * @param string $display_name User's display name.
     * @param string $site         Site name (already decoded).
     * @param string $verify_url   Full verification URL.
     * @return string Full HTML email.
     */
    private static function email_html_verify( string $display_name, string $site, string $verify_url ): string {
        $inner = '
          <p style="margin:0 0 20px;font-size:15px;color:#1a202c;">Hi ' . esc_html( $display_name ) . ',</p>
          <p style="margin:0 0 24px;font-size:15px;color:#4a5568;line-height:1.6;">
            You requested to enable <strong>Email Two-Factor Authentication</strong> on <strong>' . esc_html( $site ) . '</strong>.
            Click the button below to verify your email address and activate 2FA.
          </p>

          <!-- CTA button -->
          <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:28px;">
            <tr>
              <td align="center">
                <a href="' . esc_url( $verify_url ) . '"
                   style="display:inline-block;background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%);color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;padding:14px 36px;border-radius:8px;letter-spacing:0.2px;">
                  &#x2714;&#xFE0F; Verify Email &amp; Activate 2FA
                </a>
              </td>
            </tr>
          </table>

          <p style="margin:0 0 12px;font-size:13px;color:#718096;text-align:center;">
            &#x23F0; This link expires in <strong>1 hour</strong>.
          </p>
          <p style="margin:0;font-size:12px;color:#a0aec0;text-align:center;word-break:break-all;">
            Or copy this URL: <a href="' . esc_url( $verify_url ) . '" style="color:#6366f1;">' . esc_html( $verify_url ) . '</a>
          </p>';

        return self::email_html_wrap( $inner );
    }

    /**
     * Handles the email verification callback link.
     * Runs on admin_init — activates email 2FA when a valid token is present.
     *
     * @since  1.9.0
     * @return void
     */
    public static function email_2fa_confirm_check(): void {
        if ( ! isset( $_GET['cs_devtools_email_verify'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        if ( ! is_user_logged_in() ) {
            return;
        }

        $token     = sanitize_text_field( wp_unslash( $_GET['cs_devtools_email_verify'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $transient = self::EMAIL_VERIFY_TRANSIENT . $token;
        $data = get_transient( $transient );

        if ( ! $data || empty( $data['user_id'] ) ) {
            // Expired or invalid — redirect back without activating.
            wp_safe_redirect( admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=login&email_verify_expired=1' ) );
            exit;
        }

        $user_id = (int) $data['user_id'];

        // Verify the token belongs to the currently logged-in user.
        if ( $user_id !== get_current_user_id() ) {
            wp_safe_redirect( admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=login' ) );
            exit;
        }

        // If already activated on a previous click (e.g. email client prefetch), just redirect to success.
        if ( ! empty( $data['activated'] ) ) {
            wp_safe_redirect( admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=login&email_2fa_activated=1' ) );
            exit;
        }

        // Activate email 2FA.
        update_user_meta( $user_id, 'cs_devtools_2fa_email_enabled', '1' );
        delete_user_meta( $user_id, 'cs_devtools_email_verify_pending' );

        // Mark the transient as used (keep it alive for 10 min so re-clicks show success, not "expired").
        set_transient( $transient, [ 'user_id' => $user_id, 'activated' => true ], 600 );

        // Security state changed — destroy all other open sessions for this user.
        wp_destroy_other_sessions();

        wp_safe_redirect( admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=login&email_2fa_activated=1' ) );
        exit;
    }

    /**
     * Destroys all other sessions when a user resets their password.
     *
     * @since  1.9.0
     * @param  \WP_User $user  The user whose password was reset.
     * @return void
     */
    public static function on_password_reset( \WP_User $user ): void {
        // Destroy all sessions so the newly-reset password must be used everywhere.
        WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
    }

    /**
     * Destroys all other sessions when a user's email or password changes.
     *
     * @since  1.9.0
     * @param  int       $user_id      Updated user ID.
     * @param  \WP_User  $old_userdata User data before the update.
     * @return void
     */
    public static function on_profile_update( int $user_id, \WP_User $old_userdata ): void {
        $new_user = get_userdata( $user_id );
        if ( ! $new_user ) {
            return;
        }
        $email_changed    = $old_userdata->user_email !== $new_user->user_email;
        $password_changed = $old_userdata->user_pass  !== $new_user->user_pass;

        if ( $email_changed || $password_changed ) {
            // If the currently-logged-in user changed their own account, keep their
            // current session alive; destroy all others.  For admin-changed accounts
            // (different user_id) destroy every session outright.
            if ( get_current_user_id() === $user_id ) {
                wp_destroy_other_sessions();
            } else {
                WP_Session_Tokens::get_instance( $user_id )->destroy_all();
            }
        }
    }

    // ── Prefix migration (cs_ → cs_devtools_) ───────────────────────────────

    /**
     * One-time migration: renames options and user meta from the old cs_ prefix
     * to cs_devtools_.  Runs on every load but exits immediately after the first
     * successful run (guarded by a flag option).
     */
    private static function maybe_migrate_prefix(): void {
        if ( get_option( 'cs_devtools_prefix_migrated' ) ) {
            return;
        }

        // ── Options ──────────────────────────────────────────────────────────
        $option_map = [
            'cs_hide_login'           => 'cs_devtools_hide_login',
            'cs_login_slug'           => 'cs_devtools_login_slug',
            'cs_2fa_method'           => 'cs_devtools_2fa_method',
            'cs_2fa_force_admins'     => 'cs_devtools_2fa_force_admins',
            'cs_code_default_theme'   => 'cs_devtools_code_default_theme',
            'cs_code_theme_pair'      => 'cs_devtools_code_theme_pair',
            'cs_perf_monitor_enabled' => 'cs_devtools_perf_monitor_enabled',
            'cs_perf_debug_logging'   => 'cs_devtools_perf_debug_logging',
        ];
        foreach ( $option_map as $old => $new ) {
            $val = get_option( $old );
            if ( $val !== false ) {
                update_option( $new, $val );
                delete_option( $old );
            }
        }

        // ── User meta (all users) ─────────────────────────────────────────────
        global $wpdb;
        $meta_map = [
            'cs_passkeys'            => 'cs_devtools_passkeys',
            'cs_totp_enabled'        => 'cs_devtools_totp_enabled',
            'cs_totp_secret'         => 'cs_devtools_totp_secret',
            'cs_totp_secret_pending' => 'cs_devtools_totp_secret_pending',
            'cs_2fa_email_enabled'   => 'cs_devtools_2fa_email_enabled',
            'cs_email_verify_pending' => 'cs_devtools_email_verify_pending',
        ];
        foreach ( $meta_map as $old => $new ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update( $wpdb->usermeta, [ 'meta_key' => $new ], [ 'meta_key' => $old ] );
        }

        update_option( 'cs_devtools_prefix_migrated', '1' );
    }
}

CloudScale_DevTools::init();
