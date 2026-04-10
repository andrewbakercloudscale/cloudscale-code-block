<?php
/**
 * Plugin Name: CloudScale DevTools
 * Plugin URI: https://andrewbaker.ninja
 * Description: Developer toolkit with syntax-highlighted code blocks, SQL query tool, code migrator, site monitor, and login security (passkeys, TOTP, email 2FA, hide login URL).
 * Version: 1.8.95
 * Author: Andrew Baker
 * Author URI: https://andrewbaker.ninja
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

    const VERSION      = '1.8.95';
    const HLJS_VERSION = '11.11.1';
    const HLJS_CDN     = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/';
    const TOOLS_SLUG   = 'cloudscale-devtools';
    const MIGRATE_NONCE = 'cs_devtools_code_migrate_action';
    const CUSTOM_404_OPTION  = 'cs_devtools_custom_404';
    const SCHEME_404_OPTION  = 'cs_devtools_404_scheme';
    const HISCORE_NS         = 'cs-devtools/v1';
    const SCORE_NONCE_ACTION = 'cs_devtools_score_post';

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
    /** @var array Request lifecycle milestones: [ ['label'=>string, 'ms'=>float] ] */
    private static $perf_milestones = [];
    /** @var array|null Pending email log entry for the in-flight wp_mail() call. */
    private static $smtp_log_pending = null;

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

        // Thumbnails / Social Preview AJAX
        add_action( 'wp_ajax_cs_devtools_social_check_url',   [ __CLASS__, 'ajax_social_check_url' ] );
        add_action( 'wp_ajax_cs_devtools_social_scan_posts',  [ __CLASS__, 'ajax_social_scan_posts' ] );
        add_action( 'wp_ajax_cs_devtools_social_scan_media',  [ __CLASS__, 'ajax_social_scan_media' ] );
        add_action( 'wp_ajax_cs_devtools_social_fix_image',   [ __CLASS__, 'ajax_social_fix_image' ] );
        add_action( 'wp_ajax_cs_devtools_social_cf_test',     [ __CLASS__, 'ajax_social_cf_test' ] );
        add_action( 'wp_ajax_cs_devtools_cf_purge',           [ __CLASS__, 'ajax_cf_purge' ] );
        add_action( 'wp_ajax_cs_devtools_cf_save',            [ __CLASS__, 'ajax_cf_save' ] );

        // SMTP AJAX
        add_action( 'wp_ajax_cs_devtools_smtp_save',      [ __CLASS__, 'ajax_smtp_save' ] );
        add_action( 'wp_ajax_cs_devtools_smtp_test',      [ __CLASS__, 'ajax_smtp_test' ] );
        add_action( 'wp_ajax_cs_devtools_smtp_log_clear', [ __CLASS__, 'ajax_smtp_log_clear' ] );
        add_action( 'wp_ajax_cs_devtools_smtp_log_fetch', [ __CLASS__, 'ajax_smtp_log_fetch' ] );

        // Email log — always active so every wp_mail() call is tracked site-wide,
        // regardless of whether our SMTP is enabled.
        add_filter( 'wp_mail',        [ __CLASS__, 'smtp_log_capture' ] );
        add_action( 'wp_mail_failed', [ __CLASS__, 'smtp_log_on_failure' ] );
        // Priority 5 so it runs before phpmailer_configure (priority 10) and sets action_function first.
        add_action( 'phpmailer_init', [ __CLASS__, 'smtp_log_set_callback' ], 5 );

        // SMTP — configure phpmailer and override from address only when fully configured.
        // Guard: if host is empty we skip configuration entirely so other plugins' emails
        // continue to work via PHP mail() rather than silently failing.
        if ( get_option( 'cs_devtools_smtp_enabled', '0' ) === '1'
            && '' !== trim( (string) get_option( 'cs_devtools_smtp_host', '' ) )
        ) {
            add_action( 'phpmailer_init', [ __CLASS__, 'phpmailer_configure' ] );
            if ( get_option( 'cs_devtools_smtp_from_email', '' ) ) {
                add_filter( 'wp_mail_from',      [ __CLASS__, 'smtp_from_email' ] );
            }
            if ( get_option( 'cs_devtools_smtp_from_name', '' ) ) {
                add_filter( 'wp_mail_from_name', [ __CLASS__, 'smtp_from_name' ] );
            }
        }

        // Login security — URL intercept / 2FA flow (early, priority 1 on init).
        add_action( 'init',        [ __CLASS__, 'login_serve_custom_slug' ], 1 );
        add_action( 'login_init',  [ __CLASS__, 'login_redirect_authenticated' ], 0 );
        add_action( 'login_init',  [ __CLASS__, 'login_block_direct_access' ], 1 );
        add_filter( 'auth_cookie_expiration', [ __CLASS__, 'login_session_expiration' ], 10, 3 );
        add_action( 'login_init',  [ __CLASS__, 'login_2fa_handle' ] );
        add_filter( 'authenticate',        [ __CLASS__, 'login_2fa_intercept' ], 100, 3 );
        add_filter( 'login_url',           [ __CLASS__, 'login_custom_url' ], 10, 3 );
        add_filter( 'logout_url',          [ __CLASS__, 'login_custom_logout_url' ], 10, 2 );
        add_filter( 'lostpassword_url',    [ __CLASS__, 'login_custom_lostpassword_url' ], 10, 2 );
        add_filter( 'network_site_url',    [ __CLASS__, 'login_custom_network_url' ], 10, 3 );
        add_filter( 'site_url',            [ __CLASS__, 'login_custom_site_url' ], 10, 4 );

        // Brute-force protection — check before authentication (priority 1, before password check).
        add_filter( 'authenticate',    [ __CLASS__, 'login_brute_force_check' ], 1, 3 );
        // Force persistent cookie when a custom session duration is configured.
        // Must be login_init (fires before the POST is processed) not login_form_login
        // (which is a display hook that never fires on a successful login POST).
        add_action( 'login_init', [ __CLASS__, 'login_force_remember' ], 5 );
        // Security monitor — always track failed logins regardless of monitor toggle.
        add_action( 'wp_login_failed', [ __CLASS__, 'perf_track_failed_login' ] );

        // Custom 404 page + hiscore leaderboard.
        add_action( 'template_redirect',                        [ __CLASS__, 'maybe_custom_404' ], 1 );
        add_action( 'rest_api_init',                            [ __CLASS__, 'register_hiscore_routes' ] );
        add_action( 'wp_ajax_cs_devtools_save_404_settings',    [ __CLASS__, 'ajax_save_404_settings' ] );

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

            // Request lifecycle milestones for the waterfall timeline.
            // Registered at PHP_INT_MAX so we capture the time after all other
            // callbacks on that hook have finished running.
            foreach ( [
                'plugins_loaded'    => 'Plugins loaded',
                'init'              => 'WP init',
                'admin_init'        => 'Admin init',
                'wp_loaded'         => 'WP loaded',
                'wp'                => 'Query setup',
                'template_redirect' => 'Template',
            ] as $_ms_hook => $_ms_label ) {
                add_action( $_ms_hook, static function () use ( $_ms_label ) {
                    self::perf_record_milestone( $_ms_label );
                }, PHP_INT_MAX );
            }
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

        $cloudscale_link = '<a class="cs-code-brand" href="https://andrewbaker.ninja/2026/02/27/building-a-better-code-block-for-wordpress-cloudscale-code-block-plugin/" target="_blank" rel="noopener noreferrer"><span class="cs-brand-bolt">&#9889;</span> Powered by CloudScale</a>';

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
        register_setting( 'cs_devtools_login_settings', 'cs_devtools_2fa_grace_logins', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) {
                $n = (int) $v;
                return ( $n >= 0 && $n <= 10 ) ? (string) $n : '0';
            },
            'default' => '0',
        ] );
        register_setting( 'cs_devtools_login_settings', 'cs_devtools_session_duration', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) {
                $valid = [ 'default', '1', '7', '14', '30', '90', '365' ];
                return in_array( $v, $valid, true ) ? $v : 'default';
            },
            'default' => 'default',
        ] );
        register_setting( 'cs_devtools_login_settings', 'cs_devtools_brute_force_enabled', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) { return $v === '1' ? '1' : '0'; },
            'default'           => '1',
        ] );
        register_setting( 'cs_devtools_login_settings', 'cs_devtools_brute_force_attempts', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) {
                $n = (int) $v;
                return ( $n >= 1 && $n <= 100 ) ? (string) $n : '5';
            },
            'default' => '5',
        ] );
        register_setting( 'cs_devtools_login_settings', 'cs_devtools_brute_force_lockout', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) {
                $n = (int) $v;
                return ( $n >= 1 && $n <= 1440 ) ? (string) $n : '5';
            },
            'default' => '5',
        ] );

        // SMTP settings
        register_setting( 'cs_devtools_smtp_settings', 'cs_devtools_smtp_enabled', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) { return $v === '1' ? '1' : '0'; },
            'default'           => '0',
        ] );
        register_setting( 'cs_devtools_smtp_settings', 'cs_devtools_smtp_host', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );
        register_setting( 'cs_devtools_smtp_settings', 'cs_devtools_smtp_port', [
            'type'              => 'integer',
            'sanitize_callback' => static function ( $v ) {
                $v = absint( $v );
                return $v > 0 ? $v : 587;
            },
            'default'           => 587,
        ] );
        register_setting( 'cs_devtools_smtp_settings', 'cs_devtools_smtp_encryption', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) {
                return in_array( $v, [ 'tls', 'ssl', 'none' ], true ) ? $v : 'tls';
            },
            'default'           => 'tls',
        ] );
        register_setting( 'cs_devtools_smtp_settings', 'cs_devtools_smtp_auth', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) { return $v === '1' ? '1' : '0'; },
            'default'           => '1',
        ] );
        register_setting( 'cs_devtools_smtp_settings', 'cs_devtools_smtp_user', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );
        register_setting( 'cs_devtools_smtp_settings', 'cs_devtools_smtp_pass', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) { return $v; },
            'default'           => '',
        ] );
        register_setting( 'cs_devtools_smtp_settings', 'cs_devtools_smtp_from_email', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default'           => '',
        ] );
        register_setting( 'cs_devtools_smtp_settings', 'cs_devtools_smtp_from_name', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
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

        if ( $active_tab === 'mail' ) {
            wp_enqueue_script(
                'cs-smtp',
                plugins_url( 'assets/cs-smtp.js', __FILE__ ),
                [],
                filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-smtp.js' ),
                true
            );
            wp_localize_script( 'cs-smtp', 'csDevtoolsSmtp', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( self::SMTP_NONCE ),
                'testTo'  => wp_get_current_user()->user_email,
            ] );
        }

        if ( $active_tab === '404' ) {
            wp_enqueue_script(
                'cs-404-admin',
                plugins_url( 'assets/cs-404-admin.js', __FILE__ ),
                [],
                filemtime( plugin_dir_path( __FILE__ ) . 'assets/cs-404-admin.js' ),
                true
            );
            wp_localize_script( 'cs-404-admin', 'csDevtools404', [
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( 'cs_devtools_404_settings' ),
                'custom_404' => get_option( self::CUSTOM_404_OPTION, 0 ) ? 1 : 0,
                'scheme'     => get_option( self::SCHEME_404_OPTION, 'ocean' ),
                'previewUrl' => home_url( '/this-page-does-not-exist' ),
            ] );
        }

        if ( $active_tab === 'thumbnails' ) {
            $thumb_js = plugin_dir_path( __FILE__ ) . 'assets/cs-thumbnails.js';
            wp_enqueue_script(
                'cs-thumbnails',
                plugins_url( 'assets/cs-thumbnails.js', __FILE__ ),
                [],
                file_exists( $thumb_js ) ? filemtime( $thumb_js ) : self::VERSION,
                true
            );
            wp_localize_script( 'cs-thumbnails', 'csDevtoolsThumbs', [
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'cs_devtools_thumbnails' ),
                'siteUrl'  => home_url( '/' ),
            ] );
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
                    <a href="https://andrewbaker.ninja" target="_blank" rel="noopener noreferrer" class="cs-badge cs-badge-orange" style="text-decoration:none">andrewbaker.ninja</a>
                    <a href="https://andrewbaker.ninja/wordpress-plugin-help/cloudscale-devtools-help/" target="_blank" rel="noopener noreferrer" class="cs-badge cs-badge-help" style="text-decoration:none">❓ <?php esc_html_e( 'Help', 'cloudscale-devtools' ); ?></a>
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
                <a href="<?php echo esc_url( $base_url . '&tab=mail' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'mail' ? 'active' : ''; ?>">
                    📧 <?php esc_html_e( 'Mail / SMTP', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=404' ); ?>"
                   class="cs-tab <?php echo $active_tab === '404' ? 'active' : ''; ?>">
                    🎮 <?php esc_html_e( '404 Games', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=thumbnails' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'thumbnails' ? 'active' : ''; ?>">
                    🖼️ <?php esc_html_e( 'Thumbnails', 'cloudscale-devtools' ); ?>
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
            <?php elseif ( $active_tab === 'mail' ) : ?>
                <div class="cs-tab-content active">
                    <?php self::render_smtp_panel(); ?>
                </div>
            <?php elseif ( $active_tab === '404' ) : ?>
                <div class="cs-tab-content active">
                    <?php self::render_404_panel(); ?>
                </div>
            <?php elseif ( $active_tab === 'thumbnails' ) : ?>
                <div class="cs-tab-content active">
                    <?php self::render_thumbnails_panel(); ?>
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
                        <div style="text-align:center;margin-bottom:6px">
                            <span style="display:inline-block;background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $col ); ?>;border:1px solid <?php echo esc_attr( $bdr ); ?>;border-radius:4px;font-size:11px;font-weight:600;padding:1px 8px;white-space:nowrap"><?php echo esc_html( $rec ); ?></span>
                        </div>
                        <strong style="display:block;font-size:13px;margin-bottom:4px"><?php echo esc_html( $item['name'] ); ?></strong>
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

        <!-- ── Session Duration ──────────────────────── -->
        <?php
        $session_duration = get_option( 'cs_devtools_session_duration', 'default' );
        $duration_options = [
            'default' => __( 'WordPress default (2 days / 14 days with Remember Me)', 'cloudscale-devtools' ),
            '1'       => __( '1 day', 'cloudscale-devtools' ),
            '7'       => __( '7 days', 'cloudscale-devtools' ),
            '14'      => __( '14 days', 'cloudscale-devtools' ),
            '30'      => __( '30 days', 'cloudscale-devtools' ),
            '90'      => __( '90 days', 'cloudscale-devtools' ),
            '365'     => __( '1 year', 'cloudscale-devtools' ),
        ];
        ?>
        <div class="cs-panel" id="cs-panel-session">
            <div class="cs-section-header cs-section-header-blue">
                <span>⏱ SESSION DURATION</span>
                <span class="cs-header-hint"><?php esc_html_e( 'How long login sessions stay valid', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'session-duration', 'Session Duration', [
                    [ 'name' => 'Session Lifetime',     'rec' => 'Recommended', 'desc' => 'Sets how long the WordPress auth cookie stays valid before the user must log in again. Choose a shorter duration (1–7 days) for higher-security environments, or a longer one (30–90 days) for convenience. Leave on WordPress default if you have no specific requirement.' ],
                    [ 'name' => 'Remember Me & timing', 'rec' => 'Note',        'desc' => "When a custom duration is set, the \"Remember Me\" checkbox is overridden — all new sessions get the same lifetime regardless.\n\nChanging this setting only affects new logins. Users who are already logged in keep their current session cookie until it expires or they log out." ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <div class="cs-field-row">
                    <div class="cs-field">
                        <label class="cs-label" for="cs-session-duration"><?php esc_html_e( 'Session expires after:', 'cloudscale-devtools' ); ?></label>
                        <select id="cs-session-duration" class="cs-input" style="max-width:360px">
                            <?php foreach ( $duration_options as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $session_duration, (string) $val ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="cs-hint"><?php esc_html_e( 'Applies from the next login. Existing sessions are not affected.', 'cloudscale-devtools' ); ?></span>
                    </div>
                </div>
                <div style="margin-top:18px;display:flex;align-items:center;gap:10px">
                    <button type="button" class="cs-btn-primary" id="cs-session-save">💾 <?php esc_html_e( 'Save Session Settings', 'cloudscale-devtools' ); ?></button>
                    <span class="cs-settings-saved" id="cs-session-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                </div>
            </div>
        </div>

        <!-- ── Brute-Force Protection ───────────────── -->
        <?php
        $bf_enabled  = get_option( 'cs_devtools_brute_force_enabled', '1' );
        $bf_attempts = get_option( 'cs_devtools_brute_force_attempts', '5' );
        $bf_lockout  = get_option( 'cs_devtools_brute_force_lockout', '5' );
        ?>
        <div class="cs-panel" id="cs-panel-brute-force">
            <div class="cs-section-header cs-section-header-red">
                <span>🔒 BRUTE-FORCE PROTECTION</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Temporarily lock accounts after repeated failed logins', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'brute-force', 'Brute-Force Protection', [
                    [ 'name' => 'How it works',   'rec' => 'Info',        'desc' => 'After N consecutive failed login attempts for the same username, the account is locked for the configured duration. The lock is per-username, not per-IP, so it also stops distributed attacks across multiple IPs. The counter resets automatically after the lockout period expires.' ],
                    [ 'name' => 'Failed attempts', 'rec' => 'Recommended', 'desc' => 'Default is 5 attempts. Lower values (3) give tighter security but risk locking out users who mistype their password a few times. Higher values (10) are more forgiving. Admins can unlock an account immediately by flushing that user\'s lockout transient from the database.' ],
                    [ 'name' => 'Lockout period',  'rec' => 'Recommended', 'desc' => 'Default is 5 minutes. The lock lifts automatically — no admin action needed. Longer periods (15–30 minutes) slow down attacks further but delay legitimate users who forgot their password.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <div class="cs-field-row">
                    <div class="cs-field">
                        <label class="cs-label">
                            <input type="checkbox" id="cs-bf-enabled" <?php checked( $bf_enabled, '1' ); ?>>
                            <?php esc_html_e( 'Enable brute-force account lockout', 'cloudscale-devtools' ); ?>
                        </label>
                        <span class="cs-hint"><?php esc_html_e( 'Locks the account after too many failed login attempts.', 'cloudscale-devtools' ); ?></span>
                    </div>
                </div>
                <div class="cs-field-row" id="cs-bf-options">
                    <div class="cs-field" style="margin-right:32px">
                        <label class="cs-label" for="cs-bf-attempts"><?php esc_html_e( 'Failed attempts before lockout:', 'cloudscale-devtools' ); ?></label>
                        <input type="number" id="cs-bf-attempts" class="cs-input" min="1" max="100"
                               value="<?php echo esc_attr( $bf_attempts ); ?>" style="max-width:100px">
                        <span class="cs-hint"><?php esc_html_e( 'Consecutive failures for the same username. Default: 5.', 'cloudscale-devtools' ); ?></span>
                    </div>
                    <div class="cs-field">
                        <label class="cs-label" for="cs-bf-lockout"><?php esc_html_e( 'Lockout duration (minutes):', 'cloudscale-devtools' ); ?></label>
                        <input type="number" id="cs-bf-lockout" class="cs-input" min="1" max="1440"
                               value="<?php echo esc_attr( $bf_lockout ); ?>" style="max-width:100px">
                        <span class="cs-hint"><?php esc_html_e( 'How long the account stays locked. Default: 5.', 'cloudscale-devtools' ); ?></span>
                    </div>
                </div>
                <div style="margin-top:18px;display:flex;align-items:center;gap:10px">
                    <button type="button" class="cs-btn-primary" id="cs-bf-save">💾 <?php esc_html_e( 'Save Brute-Force Settings', 'cloudscale-devtools' ); ?></button>
                    <span class="cs-settings-saved" id="cs-bf-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
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
                    [ 'name' => 'Grace Logins',             'rec' => 'Advanced',        'desc' => "Allows a user to log in up to N times before 2FA is required. The counter is per-user and never resets automatically.\n\nDefault is 0 (2FA always required from the first login).\n\nTip for automated test accounts: set to 1. Playwright and similar tools cannot complete a real 2FA challenge, so granting one grace login lets a test account authenticate for setup steps without disabling 2FA site-wide." ],
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

                <?php $grace_logins = (int) get_option( 'cs_devtools_2fa_grace_logins', '0' ); ?>
                <div class="cs-field-row" style="margin-top:16px">
                    <div class="cs-field">
                        <label class="cs-label" for="cs-2fa-grace-logins"><?php esc_html_e( 'Grace logins before 2FA is required:', 'cloudscale-devtools' ); ?></label>
                        <input type="number" id="cs-2fa-grace-logins" class="cs-input" min="0" max="10"
                               value="<?php echo esc_attr( $grace_logins ); ?>" style="max-width:100px">
                        <span class="cs-hint"><?php esc_html_e( 'Allow N logins without 2FA per user. 0 = 2FA required from first login. For automated test accounts use 1.', 'cloudscale-devtools' ); ?></span>
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

        $parsed_url = wp_parse_url( $url );
        $home_host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );

        self::$perf_http_calls[] = [
            'url'      => $url,
            'method'   => strtoupper( $parsed_args['method'] ?? 'GET' ),
            'status'   => $status,
            'time_ms'  => $elapsed_ms,
            'cached'   => $cached,
            'plugin'   => $plugin,
            'error'    => $error,
            // Security flags.
            'insecure' => isset( $parsed_url['scheme'] ) && 'http' === strtolower( $parsed_url['scheme'] ),
            'external' => isset( $parsed_url['host'] ) && strtolower( $parsed_url['host'] ) !== strtolower( $home_host ),
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
        $in_footer = isset( $scripts_obj->in_footer ) && is_array( $scripts_obj->in_footer )
            ? $scripts_obj->in_footer : [];

        $scripts = [];
        foreach ( $scripts_obj->done as $handle ) {
            if ( ! isset( $scripts_obj->registered[ $handle ] ) ) {
                continue;
            }
            $dep      = $scripts_obj->registered[ $handle ];
            $src      = is_string( $dep->src ) ? $dep->src : '';
            $strategy = isset( $dep->extra['strategy'] ) ? (string) $dep->extra['strategy'] : '';
            $scripts[] = [
                'handle'    => (string) $handle,
                'src'       => $src,
                'plugin'    => self::perf_attr_asset( $src ),
                'ver'       => is_string( $dep->ver ) ? $dep->ver : ( $dep->ver ? (string) $dep->ver : '' ),
                'in_footer' => in_array( $handle, $in_footer, true ),
                'strategy'  => $strategy, // 'defer', 'async', or ''
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
    /**
     * Records a named request-lifecycle milestone with ms-since-request-start.
     *
     * @param  string $label Human-readable phase label.
     * @return void
     */
    private static function perf_record_milestone( string $label ): void {
        if ( ! isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ) {
            return;
        }
        self::$perf_milestones[] = [
            'label' => $label,
            'ms'    => round( ( microtime( true ) - (float) $_SERVER['REQUEST_TIME_FLOAT'] ) * 1000, 1 ),
        ];
    }

    private static function perf_build_hooks_data(): array {
        global $wp_filter;

        $hooks = self::$perf_hooks;
        // Sort by total_ms descending.
        uasort( $hooks, static function ( $a, $b ) {
            return $b['total_ms'] <=> $a['total_ms'];
        } );

        $result = [];
        foreach ( array_slice( $hooks, 0, 50, true ) as $name => $stat ) {
            // Collect registered callbacks from $wp_filter for attribution.
            $callbacks = [];
            if ( isset( $wp_filter[ $name ] ) && $wp_filter[ $name ] instanceof WP_Hook ) {
                $cb_count = 0;
                foreach ( $wp_filter[ $name ]->callbacks as $priority => $cbs ) {
                    foreach ( $cbs as $cb_info ) {
                        if ( $cb_count >= 20 ) {
                            break 2;
                        }
                        $info        = self::perf_callback_info( $cb_info['function'] );
                        $callbacks[] = [
                            'priority' => (int) $priority,
                            'label'    => $info['label'],
                            'plugin'   => $info['plugin'],
                        ];
                        $cb_count++;
                    }
                }
            }

            $result[] = [
                'hook'      => $name,
                'count'     => $stat['count'],
                'total_ms'  => round( $stat['total_ms'], 2 ),
                'max_ms'    => round( $stat['max_ms'], 2 ),
                'avg_ms'    => $stat['count'] > 0 ? round( $stat['total_ms'] / $stat['count'], 2 ) : 0,
                'callbacks' => $callbacks,
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
            'health'     => self::perf_build_health_data(),
            'milestones' => array_merge(
                [ [ 'label' => 'Request start', 'ms' => 0.0 ] ],
                self::$perf_milestones,
                [ [ 'label' => 'Panel output', 'ms' => $page_ms ] ]
            ),
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
     * Site health snapshot: autoloaded options bloat, WP-Cron backlog, and
     * security configuration flags. Cheap to compute — one aggregate DB query
     * for autoload size plus in-memory checks for everything else.
     *
     * @return array
     */
    private static function perf_build_health_data(): array {
        global $wpdb;

        // ── Autoloaded options ────────────────────────────────────────────────
        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT SUM(LENGTH(option_value)) AS total_bytes, COUNT(*) AS total_count
             FROM {$wpdb->options}
             WHERE autoload = 'yes'",
            ARRAY_A
        );
        $autoload_kb    = $row ? round( (float) $row['total_bytes'] / 1024, 1 ) : 0.0;
        $autoload_count = $row ? (int) $row['total_count'] : 0;

        // Top 5 largest autoloaded options (skip transients — they are ephemeral).
        $top_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT option_name, LENGTH(option_value) AS size_bytes
             FROM {$wpdb->options}
             WHERE autoload = 'yes'
               AND option_name NOT LIKE '\_transient\_%'
               AND option_name NOT LIKE '\_site\_transient\_%'
             ORDER BY size_bytes DESC
             LIMIT 5",
            ARRAY_A
        );
        $large_autoloads = [];
        foreach ( ( $top_rows ?: [] ) as $r ) {
            $large_autoloads[] = [
                'name'    => $r['option_name'],
                'size_kb' => round( (float) $r['size_bytes'] / 1024, 1 ),
            ];
        }

        // ── WP-Cron backlog ───────────────────────────────────────────────────
        $cron_array   = _get_cron_array() ?: [];
        $now          = time();
        $cron_total   = 0;
        $cron_overdue = 0;
        $overdue_list = [];
        foreach ( $cron_array as $timestamp => $hooks ) {
            $cron_total += count( $hooks );
            if ( (int) $timestamp < $now ) {
                foreach ( array_keys( $hooks ) as $hook_name ) {
                    ++$cron_overdue;
                    if ( count( $overdue_list ) < 5 ) {
                        $overdue_list[] = [
                            'hook'            => $hook_name,
                            'overdue_seconds' => $now - (int) $timestamp,
                        ];
                    }
                }
            }
        }

        // ── Security configuration ────────────────────────────────────────────
        $wp_debug_display = defined( 'WP_DEBUG' ) && WP_DEBUG
            && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY );

        // ── Credential / account hygiene ─────────────────────────────────────
        $admin_user_exists = (bool) username_exists( 'admin' );

        // ── Database ──────────────────────────────────────────────────────────
        $db_prefix_default = ( $wpdb->prefix === 'wp_' );

        // ── XML-RPC ───────────────────────────────────────────────────────────
        $xmlrpc_enabled = file_exists( ABSPATH . 'xmlrpc.php' )
            && (bool) apply_filters( 'xmlrpc_enabled', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        // ── File exposure ─────────────────────────────────────────────────────
        $readme_exposed  = file_exists( ABSPATH . 'readme.html' );
        $license_exposed = file_exists( ABSPATH . 'license.txt' );

        // ── PHP version ───────────────────────────────────────────────────────
        // April 2026: 8.0 EOL Nov 2023, 8.1 EOL Dec 2025, 8.2 EOL Dec 2026, 8.3+ current.
        $php_eol = version_compare( PHP_VERSION, '8.2', '<' ); // EOL — no security patches
        $php_old = ! $php_eol && version_compare( PHP_VERSION, '8.2', '==' ); // 8.2 EOL Dec 2026

        // ── Failed logins (brute-force signal) ────────────────────────────────
        $failed_logins_1h  = (int) get_transient( 'cs_devtools_failed_logins_1h' );
        $failed_logins_24h = (int) get_transient( 'cs_devtools_failed_logins_24h' );

        // ── Author enumeration ────────────────────────────────────────────────
        // With pretty permalinks on, /?author=1 redirects to /author/username/.
        // Flag if pretty permalinks are active and no known filter blocks it.
        $author_enum_risk = ! empty( get_option( 'permalink_structure' ) )
            && ! has_filter( 'redirect_canonical', '__return_false' )
            && ! has_action( 'template_redirect', '__return_false' );

        // ── Plugins with pending updates ──────────────────────────────────────
        $plugins_with_updates = self::perf_get_plugin_update_info();

        // ── Disk space ────────────────────────────────────────────────────────
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        $disk_free  = function_exists( 'disk_free_space' )  ? @disk_free_space( ABSPATH )  : false;
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        $disk_total = function_exists( 'disk_total_space' ) ? @disk_total_space( ABSPATH ) : false;
        $disk_pct_used = ( false !== $disk_free && false !== $disk_total && $disk_total > 0 )
            ? (int) round( ( 1 - $disk_free / $disk_total ) * 100 )
            : null;
        $disk_free_gb = ( false !== $disk_free ) ? round( (float) $disk_free / 1073741824, 1 ) : null;

        // ── OPcache ───────────────────────────────────────────────────────────
        $opcache = null;
        if ( function_exists( 'opcache_get_status' ) ) {
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            $oc = @opcache_get_status( false );
            if ( false === $oc ) {
                $opcache = [ 'enabled' => false ];
            } elseif ( is_array( $oc ) ) {
                $oc_used_mb    = round( ( (float) ( $oc['memory_usage']['used_memory']   ?? 0 ) ) / 1048576, 1 );
                $oc_free_mb    = round( ( (float) ( $oc['memory_usage']['free_memory']   ?? 0 ) ) / 1048576, 1 );
                $oc_wasted_mb  = round( ( (float) ( $oc['memory_usage']['wasted_memory'] ?? 0 ) ) / 1048576, 1 );
                $oc_total_mb   = $oc_used_mb + $oc_free_mb + $oc_wasted_mb;
                $opcache = [
                    'enabled'        => true,
                    'hit_rate'       => round( (float) ( $oc['opcache_statistics']['opcache_hit_rate']   ?? 0 ), 1 ),
                    'used_mb'        => $oc_used_mb,
                    'free_mb'        => $oc_free_mb,
                    'wasted_mb'      => $oc_wasted_mb,
                    'mem_pct'        => $oc_total_mb > 0 ? (int) round( $oc_used_mb / $oc_total_mb * 100 ) : 0,
                    'oom_restarts'   => (int) ( $oc['opcache_statistics']['oom_restarts']       ?? 0 ),
                    'cached_scripts' => (int) ( $oc['opcache_statistics']['num_cached_scripts'] ?? 0 ),
                ];
            }
        }

        // ── PHP limits ────────────────────────────────────────────────────────
        $php_upload_max  = ini_get( 'upload_max_filesize' ) ?: '2M';
        $php_post_max    = ini_get( 'post_max_size' )       ?: '8M';
        $php_max_exec    = (int) ini_get( 'max_execution_time' );

        // ── Uploads directory writable ────────────────────────────────────────
        $upload_info      = wp_upload_dir();
        $uploads_writable = is_writable( $upload_info['basedir'] );

        // ── WordPress core update available ───────────────────────────────────
        $wp_update_available = false;
        $wp_latest_version   = '';
        $update_core = get_site_transient( 'update_core' );
        if ( $update_core && isset( $update_core->updates ) && is_array( $update_core->updates ) ) {
            foreach ( $update_core->updates as $update ) {
                if ( isset( $update->response ) && 'upgrade' === $update->response ) {
                    $wp_update_available = true;
                    $wp_latest_version   = isset( $update->version ) ? (string) $update->version : '';
                    break;
                }
            }
        }

        // ── MySQL / MariaDB full version (db_version() strips MariaDB suffix) ─
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $db_full_version = (string) $wpdb->get_var( 'SELECT VERSION()' );
        $is_mariadb      = false !== stripos( $db_full_version, 'mariadb' );

        // ── Maintenance mode stuck ────────────────────────────────────────────
        $maintenance_file = ABSPATH . '.maintenance';
        $maintenance_stale = false;
        if ( file_exists( $maintenance_file ) ) {
            $mtime = filemtime( $maintenance_file );
            $maintenance_stale = ( false !== $mtime ) && ( time() - $mtime > 600 ); // >10 min
        }

        // ── siteurl / home URL mismatch vs current request host ───────────────
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $current_host    = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
        $siteurl_host    = strtolower( (string) parse_url( get_option( 'siteurl' ), PHP_URL_HOST ) );
        $home_host       = strtolower( (string) parse_url( get_option( 'home' ), PHP_URL_HOST ) );
        $url_host_mismatch = ( '' !== $current_host )
            && ( ( '' !== $siteurl_host && $siteurl_host !== $current_host )
              || ( '' !== $home_host    && $home_host    !== $current_host ) );
        // Also flag if WP_SITEURL / WP_HOME constants conflict with DB values.
        $url_const_override = ( defined( 'WP_SITEURL' ) && WP_SITEURL !== get_option( 'siteurl' ) )
                           || ( defined( 'WP_HOME' )    && WP_HOME    !== get_option( 'home' ) );

        // ── Rewrite rules need flushing ───────────────────────────────────────
        $has_pretty_permalinks = ! empty( get_option( 'permalink_structure' ) );
        $rewrite_rules_missing = $has_pretty_permalinks && empty( get_option( 'rewrite_rules' ) );

        // ── wp-config.php world-readable ─────────────────────────────────────
        $wpconfig_path           = ABSPATH . 'wp-config.php';
        $wpconfig_world_readable = file_exists( $wpconfig_path )
            && ( fileperms( $wpconfig_path ) & 0004 );   // world-readable bit

        // ── debug.log size ────────────────────────────────────────────────────
        $debug_log_mb = null;
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            $log_path = is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : ( WP_CONTENT_DIR . '/debug.log' );
            if ( file_exists( $log_path ) ) {
                $debug_log_mb = round( (float) filesize( $log_path ) / 1048576, 1 );
            }
        }

        // ── System load average (Unix only) ───────────────────────────────────
        $load_avg  = function_exists( 'sys_getloadavg' )
            ? array_map( fn( float $v ): float => round( $v, 2 ), sys_getloadavg() )
            : [];
        $cpu_count = 1;
        if ( is_readable( '/proc/cpuinfo' ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $cpuinfo = file_get_contents( '/proc/cpuinfo' );
            if ( false !== $cpuinfo ) {
                preg_match_all( '/^processor\s*:/m', $cpuinfo, $cpu_matches );
                $cpu_count = max( 1, count( $cpu_matches[0] ) );
            }
        }

        return [
            'autoload_kb'          => $autoload_kb,
            'autoload_count'       => $autoload_count,
            'large_autoloads'      => $large_autoloads,
            'cron_total'           => $cron_total,
            'cron_overdue'         => $cron_overdue,
            'cron_overdue_list'    => $overdue_list,
            'wp_debug_display'     => $wp_debug_display,
            'disallow_file_edit'   => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
            'disallow_file_mods'   => defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS,
            'site_https'           => strpos( home_url(), 'https://' ) === 0,
            'admin_user_exists'    => $admin_user_exists,
            'db_prefix_default'    => $db_prefix_default,
            'xmlrpc_enabled'       => $xmlrpc_enabled,
            'readme_exposed'       => $readme_exposed,
            'license_exposed'      => $license_exposed,
            'php_eol'              => $php_eol,
            'php_old'              => $php_old,
            'failed_logins_1h'     => $failed_logins_1h,
            'failed_logins_24h'    => $failed_logins_24h,
            'author_enum_risk'     => $author_enum_risk,
            'plugins_with_updates' => $plugins_with_updates,
            'load_avg'             => $load_avg,
            'cpu_count'            => $cpu_count,
            'disk_pct_used'        => $disk_pct_used,
            'disk_free_gb'         => $disk_free_gb,
            'opcache'              => $opcache,
            'php_upload_max'       => $php_upload_max,
            'php_post_max'         => $php_post_max,
            'php_max_exec'         => $php_max_exec,
            'uploads_writable'       => $uploads_writable,
            'maintenance_stale'      => $maintenance_stale,
            'url_host_mismatch'      => $url_host_mismatch,
            'url_const_override'     => $url_const_override,
            'rewrite_rules_missing'  => $rewrite_rules_missing,
            'wpconfig_world_readable'=> (bool) $wpconfig_world_readable,
            'debug_log_mb'           => $debug_log_mb,
            'wp_update_available'    => $wp_update_available,
            'wp_latest_version'      => $wp_latest_version,
            'is_mariadb'             => $is_mariadb,
        ];
    }

    /**
     * Fired on wp_login_failed. Increments rolling failed-login counters stored
     * as transients so the CS Monitor can surface brute-force signals.
     *
     * @param string $username The username that failed authentication.
     * @return void
     */
    public static function perf_track_failed_login( string $username ): void {
        // 1-hour rolling window.
        $c1h = (int) get_transient( 'cs_devtools_failed_logins_1h' );
        set_transient( 'cs_devtools_failed_logins_1h', $c1h + 1, HOUR_IN_SECONDS );
        // 24-hour rolling window.
        $c24h = (int) get_transient( 'cs_devtools_failed_logins_24h' );
        set_transient( 'cs_devtools_failed_logins_24h', $c24h + 1, DAY_IN_SECONDS );

        // ── Brute-force per-account lockout ──────────────────────────────────
        if ( get_option( 'cs_devtools_brute_force_enabled', '1' ) !== '1' || empty( $username ) ) {
            return;
        }
        $max_attempts = max( 1, (int) get_option( 'cs_devtools_brute_force_attempts', '5' ) );
        $lockout_secs = max( 60, (int) get_option( 'cs_devtools_brute_force_lockout', '5' ) * MINUTE_IN_SECONDS );
        $slug         = md5( strtolower( $username ) );
        $count_key    = 'cs_devtools_bf_count_' . $slug;
        $lock_key     = 'cs_devtools_bf_lock_' . $slug;
        $attempts     = (int) get_transient( $count_key ) + 1;
        if ( $attempts >= $max_attempts ) {
            // Threshold reached — lock the account and clear the counter.
            set_transient( $lock_key, time() + $lockout_secs, $lockout_secs );
            delete_transient( $count_key );
        } else {
            // Still within the window — keep counting.
            set_transient( $count_key, $attempts, $lockout_secs * 2 );
        }
    }

    /**
     * Returns plugins that have a pending update available, using the cached
     * update_plugins site transient (populated by WP's own update check cron).
     * Never makes a live HTTP call — reads from DB only.
     *
     * @return array  [ { slug, name, current, new_version } ]
     */
    private static function perf_get_plugin_update_info(): array {
        $update_data = get_site_transient( 'update_plugins' );
        if ( ! $update_data || empty( $update_data->response ) ) {
            return [];
        }
        $results = [];
        foreach ( $update_data->response as $plugin_file => $plugin_data ) {
            $current_ver = $update_data->checked[ $plugin_file ] ?? '';
            $slug        = $plugin_data->slug ?? basename( dirname( $plugin_file ) );
            $results[]   = [
                'plugin'      => $plugin_file,
                'slug'        => $slug,
                'current'     => $current_ver,
                'new_version' => $plugin_data->new_version ?? '',
            ];
        }
        // Sort by slug name.
        usort( $results, fn( $a, $b ) => strcmp( $a['slug'], $b['slug'] ) );
        return $results;
    }

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
            $time_ms = isset( $q[1] ) ? round( (float) $q[1] * 1000, 1 ) : 0.0;
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
     * Maps an absolute (normalised) file path to a plugin slug, theme slug,
     * or 'WordPress Core'.  Used by perf_callback_info() for hook attribution.
     *
     * @param  string $file wp_normalize_path() output.
     * @return string
     */
    private static function perf_plugin_from_file( string $file ): string {
        if ( '' === $file ) {
            return 'WordPress Core';
        }
        $plugins_dir = wp_normalize_path( WP_PLUGIN_DIR );
        if ( str_starts_with( $file, $plugins_dir . '/' ) ) {
            $relative = substr( $file, strlen( $plugins_dir ) + 1 );
            return explode( '/', $relative )[0] ?? 'WordPress Core';
        }
        $themes_dir = wp_normalize_path( get_theme_root() );
        if ( str_starts_with( $file, $themes_dir . '/' ) ) {
            $relative = substr( $file, strlen( $themes_dir ) + 1 );
            return 'theme: ' . ( explode( '/', $relative )[0] ?? '?' );
        }
        return 'WordPress Core';
    }

    /**
     * Returns a human-readable label and plugin attribution for a hook callback.
     * Uses Reflection to locate the file that defines the callable.
     *
     * @param  mixed $cb Callback (string, array, Closure, invokable object).
     * @return array{label: string, plugin: string}
     */
    private static function perf_callback_info( $cb ): array {
        $label = '';
        $file  = '';
        try {
            if ( $cb instanceof Closure ) {
                $rf    = new ReflectionFunction( $cb );
                $file  = wp_normalize_path( (string) $rf->getFileName() );
                $label = '{closure}:' . $rf->getStartLine();
            } elseif ( is_string( $cb ) && function_exists( $cb ) ) {
                $rf    = new ReflectionFunction( $cb );
                $file  = wp_normalize_path( (string) $rf->getFileName() );
                $label = $cb . '()';
            } elseif ( is_array( $cb ) && 2 === count( $cb ) ) {
                $class  = is_object( $cb[0] ) ? get_class( $cb[0] ) : (string) $cb[0];
                $method = (string) ( $cb[1] ?? '' );
                $label  = $class . '::' . $method . '()';
                if ( $method && method_exists( $class, $method ) ) {
                    $rm   = new ReflectionMethod( $class, $method );
                    $file = wp_normalize_path( (string) $rm->getFileName() );
                }
            } elseif ( is_object( $cb ) && method_exists( $cb, '__invoke' ) ) {
                $rm    = new ReflectionMethod( $cb, '__invoke' );
                $file  = wp_normalize_path( (string) $rm->getFileName() );
                $label = get_class( $cb ) . '::__invoke()';
            } else {
                $label = is_string( $cb ) ? $cb : '(unknown)';
            }
        } catch ( ReflectionException $e ) {
            if ( is_string( $cb ) ) {
                $label = $cb;
            } elseif ( is_array( $cb ) ) {
                $class = is_object( $cb[0] ) ? get_class( $cb[0] ) : (string) $cb[0];
                $label = $class . '::' . ( $cb[1] ?? '?' ) . '()';
            }
        }
        return [
            'label'  => $label ?: '(unknown)',
            'plugin' => $file ? self::perf_plugin_from_file( $file ) : 'WordPress Core',
        ];
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
                    . '<span id="cs-pb-issues" class="cs-perf-badge cs-pb-issues-critical" title="Critical / warning issues detected" style="display:none">&#9888;&nbsp;<em>0</em></span>'
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
    const SMTP_NONCE            = 'cs_devtools_smtp_nonce';
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

        // Prevent any cache layer from storing this response — ensures the
        // auth-cookie check below runs on every visit, not a cached copy.
        nocache_headers();
        // Tell LiteSpeed Server/Cache not to cache this response.
        header( 'X-LiteSpeed-Cache-Control: no-cache' );
        // Belt-and-suspenders: fire LiteSpeed Cache plugin's no-cache action.
        do_action( 'litespeed_control_set_nocache', 'login_slug' );

        // Already authenticated — send straight to the dashboard.
        if ( is_user_logged_in() ) {
            wp_safe_redirect( admin_url() );
            exit;
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
     * Filters the auth cookie lifetime.
     *
     * When a custom session duration has been set in the Login Security settings,
     * this overrides WordPress's default 2-day (non-remember) / 14-day (remember)
     * lifetimes with the admin-configured value — applied uniformly regardless of
     * whether the user ticked "Remember Me".
     *
     * @since  1.9.4
     * @param  int  $expiration Default expiration in seconds.
     * @param  int  $user_id    User ID being authenticated.
     * @param  bool $remember   Whether "Remember Me" was checked.
     * @return int
     */
    public static function login_session_expiration( int $expiration, int $user_id, bool $remember ): int {
        $duration = get_option( 'cs_devtools_session_duration', 'default' );
        if ( 'default' === $duration ) {
            return $expiration;
        }
        return (int) $duration * DAY_IN_SECONDS;
    }

    /**
     * When a custom session duration is configured, forces "remember me" so the
     * auth cookie is written as a persistent cookie (non-zero browser expiry)
     * rather than a session cookie that browsers clear when closed/swiped away.
     *
     * Hooked to `login_init` (priority 5) — fires before WordPress reads
     * $_POST['rememberme'] when processing the login form POST, so wp_signon()
     * receives remember=true and wp_set_auth_cookie() sets an explicit expiry.
     *
     * Note: login_form_login is a DISPLAY hook (fires when rendering the form)
     * and never fires on a successful login POST — do NOT use that hook here.
     *
     * @since  1.8.88
     * @return void
     */
    public static function login_force_remember(): void {
        if ( get_option( 'cs_devtools_session_duration', 'default' ) !== 'default' ) {
            $_POST['rememberme'] = 'forever'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
    }

    /**
     * Returns true when the auth cookie should be written as a persistent cookie.
     * Respects the custom session duration setting (always persistent) and falls
     * back to the user's original "Remember Me" choice stored in the 2FA transient.
     *
     * @since  1.9.5
     * @param  array $pending  2FA pending transient data (may be empty for non-2FA logins).
     * @return bool
     */
    private static function login_should_remember( array $pending = [] ): bool {
        if ( get_option( 'cs_devtools_session_duration', 'default' ) !== 'default' ) {
            return true;
        }
        return ! empty( $pending['remember'] );
    }

    /**
     * Hooked to `authenticate` at priority 1. Returns a WP_Error when the
     * submitted username has been temporarily locked due to repeated failed attempts.
     *
     * @since  1.9.5
     * @param  \WP_User|\WP_Error|null $user
     * @param  string                  $username
     * @param  string                  $password
     * @return \WP_User|\WP_Error|null
     */
    public static function login_brute_force_check( $user, string $username, string $password ) {
        if ( get_option( 'cs_devtools_brute_force_enabled', '1' ) !== '1' ) {
            return $user;
        }
        if ( empty( $username ) ) {
            return $user;
        }
        $lock_key     = 'cs_devtools_bf_lock_' . md5( strtolower( $username ) );
        $locked_until = get_transient( $lock_key );
        if ( $locked_until === false ) {
            return $user;
        }
        $remaining = (int) $locked_until - time();
        if ( $remaining <= 0 ) {
            delete_transient( $lock_key );
            return $user;
        }
        $mins  = (int) ceil( $remaining / 60 );
        $label = $mins <= 1
            ? __( 'less than a minute', 'cloudscale-devtools' )
            : sprintf( _n( '%d minute', '%d minutes', $mins, 'cloudscale-devtools' ), $mins );
        return new \WP_Error(
            'cs_devtools_account_locked',
            sprintf(
                /* translators: %s = remaining lockout time, e.g. "5 minutes" */
                __( 'This account has been temporarily locked due to too many failed login attempts. Please try again in %s.', 'cloudscale-devtools' ),
                $label
            )
        );
    }

    /**
     * Fired on `login_init` at priority 0 — before any other login hook.
     * If the visitor already has a valid WordPress session, redirect them
     * straight to the dashboard instead of showing the login form.
     *
     * Skipped for logout, password reset, and other non-login actions so those
     * flows are never short-circuited.
     *
     * @since  1.9.4
     * @return void
     */
    public static function login_redirect_authenticated(): void {
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $skip   = [ 'logout', 'lostpassword', 'rp', 'resetpass', 'postpass', 'register', 'cs_devtools_2fa' ];
        if ( in_array( $action, $skip, true ) ) {
            return;
        }
        if ( ! is_user_logged_in() ) {
            return;
        }
        wp_safe_redirect( admin_url() );
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

        // Grace logins: allow up to N logins without 2FA being set up.
        // Useful for automated test accounts or newly invited users.
        $grace_limit = (int) get_option( 'cs_devtools_2fa_grace_logins', '0' );
        if ( $grace_limit > 0 ) {
            $grace_count = (int) get_user_meta( $user->ID, 'cs_devtools_2fa_grace_count', true );
            if ( $grace_count < $grace_limit ) {
                update_user_meta( $user->ID, 'cs_devtools_2fa_grace_count', $grace_count + 1 );
                return $user; // Skip 2FA — grace login consumed.
            }
        }

        // Avoid triggering 2FA during a 2FA verification POST itself.
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $action === 'cs_devtools_2fa' ) {
            return $user;
        }

        // Generate a short-lived pending token.
        $token = wp_generate_password( 32, false, false );
        $data  = [
            'user_id'  => $user->ID,
            'method'   => $method,
            'created'  => time(),
            'remember' => ! empty( $_POST['rememberme'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
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
                wp_set_auth_cookie( $user_id, self::login_should_remember( $pending ) );
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
                    wp_set_auth_cookie( $user_id, self::login_should_remember( $pending ) );
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

        // Session duration
        $valid_durations = [ 'default', '1', '7', '14', '30', '90', '365' ];
        $duration        = isset( $_POST['session_duration'] ) ? sanitize_key( wp_unslash( $_POST['session_duration'] ) ) : 'default';
        if ( ! in_array( $duration, $valid_durations, true ) ) {
            $duration = 'default';
        }
        update_option( 'cs_devtools_session_duration', $duration );

        // Brute-force protection
        $bf_enabled  = isset( $_POST['bf_enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['bf_enabled'] ) ) ? '1' : '0';
        $bf_attempts = isset( $_POST['bf_attempts'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['bf_attempts'] ) ) : 5;
        $bf_lockout  = isset( $_POST['bf_lockout'] )  ? (int) sanitize_text_field( wp_unslash( $_POST['bf_lockout'] ) )  : 5;
        if ( $bf_attempts < 1 || $bf_attempts > 100 )   { $bf_attempts = 5; }
        if ( $bf_lockout  < 1 || $bf_lockout  > 1440 )  { $bf_lockout  = 5; }
        update_option( 'cs_devtools_brute_force_enabled',  $bf_enabled );
        update_option( 'cs_devtools_brute_force_attempts', (string) $bf_attempts );
        update_option( 'cs_devtools_brute_force_lockout',  (string) $bf_lockout );

        // Grace logins
        $grace_logins = isset( $_POST['grace_logins'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['grace_logins'] ) ) : 0;
        if ( $grace_logins < 0 || $grace_logins > 10 ) { $grace_logins = 0; }
        update_option( 'cs_devtools_2fa_grace_logins', (string) $grace_logins );

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

    /* ==================================================================
       SMTP — MAIL / SMTP CONFIGURATION
       ================================================================== */

    /**
     * Renders the Mail / SMTP settings panel.
     *
     * @since  1.9.0
     * @return void
     */
    private static function render_smtp_panel(): void {
        $enabled    = get_option( 'cs_devtools_smtp_enabled',    '0' ) === '1';
        $host       = get_option( 'cs_devtools_smtp_host',       '' );
        $port       = get_option( 'cs_devtools_smtp_port',       587 );
        $encryption = get_option( 'cs_devtools_smtp_encryption', 'tls' );
        $auth       = get_option( 'cs_devtools_smtp_auth',       '1' ) === '1';
        $user       = get_option( 'cs_devtools_smtp_user',       '' );
        $has_pass   = '' !== get_option( 'cs_devtools_smtp_pass', '' );
        $from_email = get_option( 'cs_devtools_smtp_from_email', '' );
        $from_name  = get_option( 'cs_devtools_smtp_from_name',  '' );
        ?>

        <!-- ── SMTP Configuration ─────────────────────────────── -->
        <div class="cs-panel" id="cs-panel-smtp">
            <div class="cs-section-header cs-section-header-blue">
                <span>📧 SMTP CONFIGURATION</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Replace PHP mail() with a real SMTP connection', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'smtp', 'SMTP Configuration', [
                    [ 'name' => 'Enable SMTP',        'rec' => 'Recommended', 'desc' => 'Routes all WordPress emails through your own SMTP server instead of the server\'s PHP mail() function. This dramatically improves deliverability and lets you use Gmail, Outlook, or any hosted mail service.' ],
                    [ 'name' => 'App Passwords',      'rec' => 'Note',        'desc' => 'Gmail and most modern providers require an App Password rather than your regular account password. Generate one in your Google or provider account security settings and paste it here.' ],
                    [ 'name' => 'Send Test Email',    'rec' => 'Note',        'desc' => 'Sends a test message to your admin email using your current saved settings. If it fails, check your host, port, and encryption match your provider\'s requirements (port 587 + TLS is the safest default), and that you\'re using an App Password where required.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <p class="cs-login-desc"><?php esc_html_e( 'When enabled, all WordPress emails are sent through your SMTP server instead of the server\'s PHP mail() function. This improves deliverability and lets you use Gmail, Outlook, or any hosted mail service.', 'cloudscale-devtools' ); ?></p>

                <div class="cs-toggle-row">
                    <label class="cs-toggle-label">
                        <input type="checkbox" id="cs-smtp-enabled" <?php checked( $enabled ); ?>>
                        <span class="cs-toggle-switch"></span>
                        <span class="cs-toggle-text"><?php esc_html_e( 'Enable SMTP', 'cloudscale-devtools' ); ?></span>
                    </label>
                </div>

                <div id="cs-smtp-fields" style="margin-top:18px<?php echo $enabled ? '' : ';opacity:.5;pointer-events:none'; ?>">

                    <div class="cs-field-row">
                        <div class="cs-field">
                            <label class="cs-label" for="cs-smtp-host"><?php esc_html_e( 'SMTP Host:', 'cloudscale-devtools' ); ?></label>
                            <input type="text" id="cs-smtp-host" class="cs-input"
                                   value="<?php echo esc_attr( $host ); ?>"
                                   placeholder="smtp.gmail.com"
                                   style="max-width:360px" autocomplete="off" spellcheck="false">
                            <span class="cs-hint"><?php esc_html_e( 'Your SMTP server hostname, e.g. smtp.gmail.com or mail.yourdomain.com', 'cloudscale-devtools' ); ?></span>
                        </div>
                    </div>

                    <div class="cs-field-row" style="margin-top:14px">
                        <div class="cs-field">
                            <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
                                <div>
                                    <label class="cs-label" for="cs-smtp-port"><?php esc_html_e( 'Port:', 'cloudscale-devtools' ); ?></label>
                                    <input type="number" id="cs-smtp-port" class="cs-input"
                                           value="<?php echo esc_attr( $port ?: 587 ); ?>"
                                           min="1" max="65535" style="width:90px">
                                </div>
                                <div>
                                    <label class="cs-label" for="cs-smtp-encryption"><?php esc_html_e( 'Encryption:', 'cloudscale-devtools' ); ?></label>
                                    <select id="cs-smtp-encryption" class="cs-input" style="min-width:220px">
                                        <option value="tls"  <?php selected( $encryption ?: 'tls', 'tls' ); ?>><?php esc_html_e( 'TLS (STARTTLS) — port 587', 'cloudscale-devtools' ); ?></option>
                                        <option value="ssl"  <?php selected( $encryption ?: 'tls', 'ssl' ); ?>><?php esc_html_e( 'SSL — port 465', 'cloudscale-devtools' ); ?></option>
                                        <option value="none" <?php selected( $encryption ?: 'tls', 'none' ); ?>><?php esc_html_e( 'None — port 25', 'cloudscale-devtools' ); ?></option>
                                    </select>
                                </div>
                            </div>
                            <span class="cs-hint"><?php esc_html_e( 'TLS on 587 is recommended for most providers. Gmail requires TLS or SSL.', 'cloudscale-devtools' ); ?></span>
                        </div>
                    </div>

                    <!-- Auth -->
                    <div class="cs-toggle-row" style="margin-top:18px">
                        <label class="cs-toggle-label">
                            <input type="checkbox" id="cs-smtp-auth" <?php checked( $auth ); ?>>
                            <span class="cs-toggle-switch"></span>
                            <span class="cs-toggle-text"><?php esc_html_e( 'SMTP Authentication', 'cloudscale-devtools' ); ?></span>
                        </label>
                    </div>

                    <div id="cs-smtp-auth-fields" style="margin-top:14px<?php echo $auth ? '' : ';display:none'; ?>">
                        <div class="cs-field-row">
                            <div class="cs-field">
                                <label class="cs-label" for="cs-smtp-user"><?php esc_html_e( 'Username:', 'cloudscale-devtools' ); ?></label>
                                <input type="text" id="cs-smtp-user" class="cs-input"
                                       value="<?php echo esc_attr( $user ); ?>"
                                       placeholder="you@gmail.com"
                                       style="max-width:360px" autocomplete="off" spellcheck="false">
                            </div>
                        </div>
                        <div class="cs-field-row" style="margin-top:12px">
                            <div class="cs-field">
                                <label class="cs-label" for="cs-smtp-pass"><?php esc_html_e( 'Password:', 'cloudscale-devtools' ); ?></label>
                                <?php if ( $has_pass ) : ?>
                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                                    <span style="color:#666;font-size:13px">••••••••&nbsp;<?php esc_html_e( '(password saved)', 'cloudscale-devtools' ); ?></span>
                                    <button type="button" id="cs-smtp-pass-change" style="font-size:12px;padding:3px 10px;cursor:pointer;background:#f0f4ff;border:1.5px solid #c7d2fe;color:#2271b1;border-radius:5px">
                                        <?php esc_html_e( 'Change', 'cloudscale-devtools' ); ?>
                                    </button>
                                </div>
                                <div style="display:none;align-items:center;gap:8px" id="cs-smtp-pass-row">
                                    <input type="password" id="cs-smtp-pass" class="cs-input"
                                           placeholder="<?php esc_attr_e( 'Enter new password to replace', 'cloudscale-devtools' ); ?>"
                                           style="max-width:320px" autocomplete="new-password">
                                    <button type="button" id="cs-smtp-pass-view" style="font-size:12px;padding:3px 10px;cursor:pointer;background:#f0f4ff;border:1.5px solid #c7d2fe;color:#2271b1;border-radius:5px;white-space:nowrap">
                                        <?php esc_html_e( 'View', 'cloudscale-devtools' ); ?>
                                    </button>
                                </div>
                                <?php else : ?>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <input type="password" id="cs-smtp-pass" class="cs-input"
                                           placeholder="<?php esc_attr_e( 'App password or SMTP password', 'cloudscale-devtools' ); ?>"
                                           style="max-width:320px" autocomplete="new-password">
                                    <button type="button" id="cs-smtp-pass-view" style="font-size:12px;padding:3px 10px;cursor:pointer;background:#f0f4ff;border:1.5px solid #c7d2fe;color:#2271b1;border-radius:5px;white-space:nowrap">
                                        <?php esc_html_e( 'View', 'cloudscale-devtools' ); ?>
                                    </button>
                                </div>
                                <?php endif; ?>
                                <span class="cs-hint"><?php esc_html_e( 'For Gmail, use an App Password (not your Google account password).', 'cloudscale-devtools' ); ?></span>
                            </div>
                        </div>
                    </div>

                </div><!-- /#cs-smtp-fields -->

                <div style="margin-top:22px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <button type="button" class="cs-btn-primary" id="cs-smtp-save">💾 <?php esc_html_e( 'Save SMTP Settings', 'cloudscale-devtools' ); ?></button>
                    <span class="cs-settings-saved" id="cs-smtp-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                    <button type="button" class="cs-btn-primary" id="cs-smtp-test-btn" style="margin-left:6px">📨 <?php esc_html_e( 'Send Test Email', 'cloudscale-devtools' ); ?></button>
                    <span id="cs-smtp-test-result" style="font-size:13px"></span>
                </div>
            </div>
        </div>

        <!-- ── From Address ───────────────────────────────────── -->
        <div class="cs-panel" id="cs-panel-smtp-from">
            <div class="cs-section-header cs-section-header-green">
                <span>✉️ FROM ADDRESS</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Override the sender name and email on all outgoing mail', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'smtp-from', 'From Address', [
                    [ 'name' => 'From Name & Email',  'rec' => 'Recommended', 'desc' => 'Sets the sender name and email address that recipients see in their inbox. Leave blank to keep WordPress defaults (usually the site name and admin email).' ],
                    [ 'name' => 'SMTP Authorisation', 'rec' => 'Note',        'desc' => 'The From Email must be authorised to send via your SMTP account. Using an address your SMTP provider doesn\'t recognise will cause emails to bounce or land in spam.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <p class="cs-login-desc"><?php esc_html_e( 'Overrides the default WordPress sender details on every outgoing email. Leave blank to keep WordPress defaults.', 'cloudscale-devtools' ); ?></p>

                <div class="cs-field-row">
                    <div class="cs-field">
                        <label class="cs-label" for="cs-smtp-from-name"><?php esc_html_e( 'From Name:', 'cloudscale-devtools' ); ?></label>
                        <input type="text" id="cs-smtp-from-name" class="cs-input"
                               value="<?php echo esc_attr( $from_name ); ?>"
                               placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
                               style="max-width:360px" autocomplete="off">
                    </div>
                </div>
                <div class="cs-field-row" style="margin-top:14px">
                    <div class="cs-field">
                        <label class="cs-label" for="cs-smtp-from-email"><?php esc_html_e( 'From Email:', 'cloudscale-devtools' ); ?></label>
                        <input type="email" id="cs-smtp-from-email" class="cs-input"
                               value="<?php echo esc_attr( $from_email ); ?>"
                               placeholder="no-reply@<?php echo esc_attr( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>"
                               style="max-width:360px" autocomplete="off">
                        <span class="cs-hint"><?php esc_html_e( 'Must be a valid email address authorised to send from your SMTP account.', 'cloudscale-devtools' ); ?></span>
                    </div>
                </div>

                <div style="margin-top:18px;display:flex;align-items:center;gap:10px">
                    <button type="button" class="cs-btn-primary" id="cs-smtp-from-save">💾 <?php esc_html_e( 'Save From Address', 'cloudscale-devtools' ); ?></button>
                    <span class="cs-settings-saved" id="cs-smtp-from-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                </div>
            </div>
        </div>

        <!-- ── Email Activity Log ─────────────────────────────── -->
        <div class="cs-panel" id="cs-panel-email-log">
            <div class="cs-section-header cs-section-header-blue">
                <span>📋 EMAIL ACTIVITY LOG</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Last 100 emails sent by WordPress on this site', 'cloudscale-devtools' ); ?></span>
            </div>
            <div class="cs-panel-body">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
                    <button type="button" class="cs-btn-primary" id="cs-log-refresh" style="background:#5b6a7a">🔄 <?php esc_html_e( 'Refresh', 'cloudscale-devtools' ); ?></button>
                    <button type="button" id="cs-log-clear" style="font-size:13px;padding:6px 14px;cursor:pointer;background:#fff0f0;border:1.5px solid #f5c6cb;color:#c0392b;border-radius:6px">🗑 <?php esc_html_e( 'Clear Log', 'cloudscale-devtools' ); ?></button>
                </div>
                <div id="cs-email-log-wrap">
                    <?php self::render_email_log_table(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renders the email log table rows (also used for AJAX refresh).
     *
     * @since  1.9.0
     * @return void
     */
    private static function render_email_log_table(): void {
        $log = get_option( self::EMAIL_LOG_OPTION, [] );
        if ( ! is_array( $log ) || empty( $log ) ) {
            echo '<p style="color:#888;font-size:13px;margin:0">' . esc_html__( 'No emails logged yet. Emails are recorded here as soon as WordPress sends them.', 'cloudscale-devtools' ) . '</p>';
            return;
        }
        ?>
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
                <tr style="background:#f3f4f6;text-align:left">
                    <th style="padding:7px 10px;border-bottom:1px solid #e0e0e0;white-space:nowrap"><?php esc_html_e( 'Time', 'cloudscale-devtools' ); ?></th>
                    <th style="padding:7px 10px;border-bottom:1px solid #e0e0e0"><?php esc_html_e( 'To', 'cloudscale-devtools' ); ?></th>
                    <th style="padding:7px 10px;border-bottom:1px solid #e0e0e0"><?php esc_html_e( 'Subject', 'cloudscale-devtools' ); ?></th>
                    <th style="padding:7px 10px;border-bottom:1px solid #e0e0e0;white-space:nowrap"><?php esc_html_e( 'Via', 'cloudscale-devtools' ); ?></th>
                    <th style="padding:7px 10px;border-bottom:1px solid #e0e0e0"><?php esc_html_e( 'Status', 'cloudscale-devtools' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $log as $i => $entry ) :
                $bg     = $i % 2 === 0 ? '#fff' : '#fafafa';
                $status = $entry['status'] ?? 'unknown';
                if ( $status === 'sent' ) {
                    $badge = '<span style="color:#2d7d46;font-weight:600">✓ Sent</span>';
                } elseif ( $status === 'failed' ) {
                    $err   = ! empty( $entry['error'] ) ? ' — ' . esc_html( $entry['error'] ) : '';
                    $badge = '<span style="color:#c0392b;font-weight:600" title="' . esc_attr( $entry['error'] ?? '' ) . '">✗ Failed' . esc_html( $err ) . '</span>';
                } else {
                    $badge = '<span style="color:#888">— Unknown</span>';
                }
                $via = $entry['via'] ?? 'phpmail';
                $via_label = $via === 'smtp'
                    ? '<span style="background:#e8f5e9;color:#2d7d46;padding:1px 6px;border-radius:3px;font-size:11px">SMTP</span>'
                    : '<span style="background:#f3f4f6;color:#666;padding:1px 6px;border-radius:3px;font-size:11px">PHP mail</span>';
                ?>
                <tr style="background:<?php echo esc_attr( $bg ); ?>">
                    <td style="padding:7px 10px;border-bottom:1px solid #f0f0f0;white-space:nowrap;color:#666">
                        <?php echo esc_html( wp_date( 'M j, H:i:s', $entry['ts'] ?? 0 ) ); ?>
                    </td>
                    <td style="padding:7px 10px;border-bottom:1px solid #f0f0f0;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?php echo esc_html( $entry['to'] ?? '' ); ?>
                    </td>
                    <td style="padding:7px 10px;border-bottom:1px solid #f0f0f0;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?php echo esc_html( $entry['subject'] ?? '' ); ?>
                    </td>
                    <td style="padding:7px 10px;border-bottom:1px solid #f0f0f0"><?php echo $via_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                    <td style="padding:7px 10px;border-bottom:1px solid #f0f0f0"><?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php
    }

    /**
     * AJAX: saves SMTP and from-address settings.
     *
     * @since  1.9.0
     * @return void
     */
    public static function ajax_smtp_save(): void {
        check_ajax_referer( self::SMTP_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $enabled    = isset( $_POST['enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) ) ? '1' : '0';
        $host       = isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '';
        $port       = isset( $_POST['port'] ) ? absint( wp_unslash( $_POST['port'] ) ) : 587;
        $encryption = isset( $_POST['encryption'] ) ? sanitize_key( wp_unslash( $_POST['encryption'] ) ) : 'tls';
        $auth       = isset( $_POST['auth'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['auth'] ) ) ? '1' : '0';
        $user       = isset( $_POST['user'] ) ? sanitize_text_field( wp_unslash( $_POST['user'] ) ) : '';
        $from_email = isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '';
        $from_name  = isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '';
        $new_pass   = isset( $_POST['pass'] ) ? wp_unslash( $_POST['pass'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        if ( ! in_array( $encryption, [ 'tls', 'ssl', 'none' ], true ) ) {
            $encryption = 'tls';
        }
        if ( $port <= 0 || $port > 65535 ) {
            $port = 587;
        }

        // Validate: if enabling SMTP, require a host and (if auth on) credentials.
        if ( $enabled === '1' ) {
            $errors = [];
            if ( $host === '' ) {
                $errors[] = __( 'SMTP Host is required when SMTP is enabled.', 'cloudscale-devtools' );
            }
            if ( $auth === '1' && $user === '' ) {
                $errors[] = __( 'Username is required when SMTP Authentication is enabled.', 'cloudscale-devtools' );
            }
            $existing_pass = get_option( 'cs_devtools_smtp_pass', '' );
            if ( $auth === '1' && $new_pass === '' && $existing_pass === '' ) {
                $errors[] = __( 'Password is required when SMTP Authentication is enabled.', 'cloudscale-devtools' );
            }
            if ( ! empty( $errors ) ) {
                wp_send_json_error( implode( ' ', $errors ) );
            }
        }

        update_option( 'cs_devtools_smtp_enabled',    $enabled );
        update_option( 'cs_devtools_smtp_host',       $host );
        update_option( 'cs_devtools_smtp_port',       $port );
        update_option( 'cs_devtools_smtp_encryption', $encryption );
        update_option( 'cs_devtools_smtp_auth',       $auth );
        update_option( 'cs_devtools_smtp_user',       $user );
        update_option( 'cs_devtools_smtp_from_email', $from_email );
        update_option( 'cs_devtools_smtp_from_name',  $from_name );

        // Only update password if the user explicitly provided one.
        if ( $new_pass !== '' ) {
            update_option( 'cs_devtools_smtp_pass', $new_pass );
        }

        wp_send_json_success();
    }

    /**
     * AJAX: sends a test email using current SMTP settings.
     *
     * @since  1.9.0
     * @return void
     */
    public static function ajax_smtp_test(): void {
        check_ajax_referer( self::SMTP_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'type' => 'auth' ], 403 );
        }

        $enabled    = get_option( 'cs_devtools_smtp_enabled', '0' );
        $host       = trim( (string) get_option( 'cs_devtools_smtp_host', '' ) );
        $port       = (int) get_option( 'cs_devtools_smtp_port', 587 );
        $encryption = (string) get_option( 'cs_devtools_smtp_encryption', 'tls' );
        $auth       = get_option( 'cs_devtools_smtp_auth', '1' ) === '1';
        $user       = trim( (string) get_option( 'cs_devtools_smtp_user', '' ) );
        $pass       = (string) get_option( 'cs_devtools_smtp_pass', '' );

        // ── Pre-flight checks ─────────────────────────────────────────────
        $issues = [];
        if ( $enabled !== '1' ) {
            $issues[] = 'SMTP is not enabled — toggle it on and save first.';
        }
        if ( $host === '' ) {
            $issues[] = 'SMTP Host is empty — enter your server hostname (e.g. smtp.gmail.com).';
        }
        if ( $port <= 0 || $port > 65535 ) {
            $issues[] = 'Port is invalid — use 587 (TLS), 465 (SSL), or 25 (none).';
        }
        if ( $auth && $user === '' ) {
            $issues[] = 'Authentication is on but Username is empty.';
        }
        if ( $auth && $pass === '' ) {
            $issues[] = 'Authentication is on but no Password is saved.';
        }
        if ( ! empty( $issues ) ) {
            wp_send_json_error( [ 'type' => 'preflight', 'issues' => $issues ] );
        }

        // ── Use PHPMailer directly so we capture real SMTP debug output ───
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

        $debug_log = [];
        $to        = wp_get_current_user()->user_email;
        $site      = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

        try {
            $mail             = new PHPMailer\PHPMailer\PHPMailer( true );
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->Port       = $port;
            $mail->SMTPSecure = $encryption === 'none' ? '' : $encryption;
            $mail->SMTPAuth   = $auth;
            $mail->Username   = $user;
            $mail->Password   = $pass;
            $mail->SMTPDebug  = 2;
            $mail->Debugoutput = static function ( string $str ) use ( &$debug_log ): void {
                $clean = trim( $str );
                if ( $clean !== '' ) {
                    $debug_log[] = $clean;
                }
            };

            $from_email = get_option( 'cs_devtools_smtp_from_email', '' ) ?: get_bloginfo( 'admin_email' );
            $from_name  = get_option( 'cs_devtools_smtp_from_name', '' ) ?: $site;
            $mail->setFrom( $from_email, $from_name );
            $mail->addAddress( $to );
            $mail->isHTML( true );
            $mail->CharSet = 'UTF-8';
            $mail->Subject  = sprintf( '[%s] CloudScale DevTools — SMTP Test', $site );
            $mail->Body     = '<p>This is a test email from <strong>CloudScale DevTools</strong>.</p>'
                            . '<p>Your SMTP configuration is working correctly.</p>';

            $mail->send();

            wp_send_json_success( [ 'to' => $to ] );

        } catch ( PHPMailer\PHPMailer\Exception $e ) {
            // Surface the PHPMailer error plus the last few relevant SMTP conversation lines.
            $filtered = array_values( array_filter(
                $debug_log,
                static function ( string $line ): bool {
                    // Skip lines that are just raw email body content.
                    return ! preg_match( '/^(Date:|From:|To:|Subject:|MIME|Content-|Message-ID:|X-Mailer:|--[a-zA-Z0-9]+|<html|<body|<p>)/i', $line );
                }
            ) );

            wp_send_json_error( [
                'type'    => 'smtp',
                'message' => $e->getMessage(),
                'debug'   => array_slice( $filtered, -12 ),
            ] );
        }
    }

    /**
     * Configures PHPMailer to use SMTP with saved settings.
     * Hooked onto phpmailer_init when SMTP is enabled.
     *
     * @since  1.9.0
     * @param  \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance (passed by reference).
     * @return void
     */
    public static function phpmailer_configure( $phpmailer ): void {
        $phpmailer->isSMTP();
        $phpmailer->Host      = (string) get_option( 'cs_devtools_smtp_host', '' );
        $port                 = (int) get_option( 'cs_devtools_smtp_port', 587 );
        $phpmailer->Port      = $port > 0 ? $port : 587;
        $encryption           = (string) get_option( 'cs_devtools_smtp_encryption', 'tls' );
        $encryption           = in_array( $encryption, [ 'tls', 'ssl', 'none' ], true ) ? $encryption : 'tls';
        $phpmailer->SMTPSecure = $encryption === 'none' ? '' : $encryption;
        // Default auth to ON — empty/missing option means "never explicitly turned off".
        $auth_val             = get_option( 'cs_devtools_smtp_auth', '1' );
        $phpmailer->SMTPAuth  = $auth_val !== '0';
        $phpmailer->Username  = (string) get_option( 'cs_devtools_smtp_user', '' );
        $phpmailer->Password  = (string) get_option( 'cs_devtools_smtp_pass', '' );
        $phpmailer->SMTPDebug = 0;
    }

    /**
     * Filter: overrides wp_mail_from with configured from email.
     *
     * @since  1.9.0
     * @param  string $email Default from email.
     * @return string
     */
    public static function smtp_from_email( string $email ): string {
        $configured = get_option( 'cs_devtools_smtp_from_email', '' );
        return $configured ?: $email;
    }

    /**
     * Filter: overrides wp_mail_from_name with configured from name.
     *
     * @since  1.9.0
     * @param  string $name Default from name.
     * @return string
     */
    public static function smtp_from_name( string $name ): string {
        $configured = get_option( 'cs_devtools_smtp_from_name', '' );
        return $configured ?: $name;
    }

    /* ==================================================================
       EMAIL LOG
       ================================================================== */

    const EMAIL_LOG_OPTION  = 'cs_devtools_email_log';
    const EMAIL_LOG_MAX     = 100;

    /**
     * wp_mail filter — captures outgoing email details before send.
     *
     * @since  1.9.0
     * @param  array $args wp_mail arguments.
     * @return array Unchanged.
     */
    public static function smtp_log_capture( array $args ): array {
        $to = $args['to'] ?? '';
        if ( is_array( $to ) ) {
            $to = implode( ', ', $to );
        }
        self::$smtp_log_pending = [
            'ts'      => time(),
            'to'      => (string) $to,
            'subject' => (string) ( $args['subject'] ?? '' ),
            'status'  => 'pending',
            'error'   => '',
            'via'     => ( get_option( 'cs_devtools_smtp_enabled', '0' ) === '1'
                          && '' !== trim( (string) get_option( 'cs_devtools_smtp_host', '' ) ) )
                         ? 'smtp' : 'phpmail',
        ];
        return $args;
    }

    /**
     * phpmailer_init (priority 5) — sets the PHPMailer action_function callback
     * so we receive a reliable success/failure signal after every send attempt.
     *
     * @since  1.9.0
     * @param  \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
     * @return void
     */
    public static function smtp_log_set_callback( $phpmailer ): void {
        $phpmailer->action_function = [ __CLASS__, 'smtp_log_on_send' ];
    }

    /**
     * PHPMailer action_function callback — fires after every send attempt.
     *
     * @since  1.9.0
     * @param  bool   $is_sent Whether the send succeeded.
     * @param  array  $to      Recipient addresses.
     * @param  array  $cc      CC addresses (unused).
     * @param  array  $bcc     BCC addresses (unused).
     * @param  string $subject Subject line (unused — already captured).
     * @param  string $body    Message body (unused).
     * @param  string $from    Sender address (unused).
     * @return void
     */
    public static function smtp_log_on_send( bool $is_sent, array $to, array $cc, array $bcc, string $subject, string $body, string $from ): void {
        if ( self::$smtp_log_pending === null ) {
            return;
        }
        $entry           = self::$smtp_log_pending;
        $entry['status'] = $is_sent ? 'sent' : 'failed';
        self::smtp_log_write( $entry );
        self::$smtp_log_pending = null;
    }

    /**
     * wp_mail_failed action — fires when wp_mail() returns false (PHPMailer threw).
     *
     * @since  1.9.0
     * @param  \WP_Error $error WP_Error with PHPMailer error message.
     * @return void
     */
    public static function smtp_log_on_failure( \WP_Error $error ): void {
        $entry = self::$smtp_log_pending ?? [
            'ts'      => time(),
            'to'      => '',
            'subject' => '(unknown)',
            'status'  => 'pending',
            'error'   => '',
            'via'     => 'unknown',
        ];
        $entry['status'] = 'failed';
        $entry['error']  = $error->get_error_message();
        self::smtp_log_write( $entry );
        self::$smtp_log_pending = null;
    }

    /**
     * Prepends a log entry to the stored email log (newest-first, capped at 100).
     *
     * @since  1.9.0
     * @param  array $entry Log entry array.
     * @return void
     */
    private static function smtp_log_write( array $entry ): void {
        $log = get_option( self::EMAIL_LOG_OPTION, [] );
        if ( ! is_array( $log ) ) {
            $log = [];
        }
        array_unshift( $log, $entry );
        update_option( self::EMAIL_LOG_OPTION, array_slice( $log, 0, self::EMAIL_LOG_MAX ), false );
    }

    /**
     * AJAX: clears the email log.
     *
     * @since  1.9.0
     * @return void
     */
    public static function ajax_smtp_log_clear(): void {
        check_ajax_referer( self::SMTP_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        delete_option( self::EMAIL_LOG_OPTION );
        wp_send_json_success();
    }

    /**
     * AJAX: returns the email log as JSON for client-side refresh.
     *
     * @since  1.9.0
     * @return void
     */
    public static function ajax_smtp_log_fetch(): void {
        check_ajax_referer( self::SMTP_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $log = get_option( self::EMAIL_LOG_OPTION, [] );
        if ( ! is_array( $log ) ) {
            $log = [];
        }
        wp_send_json_success( $log );
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

    /* ==================================================================
       Custom 404 page with games
       ================================================================== */

    /** Returns the 12 built-in colour scheme definitions for the 404 page. */
    public static function get_404_schemes(): array {
        return [
            'ocean'    => [ 'name' => 'Ocean',    'bg1' => '#cce9fb', 'bg2' => '#a8d8f0', 'acc' => '#f57c00', 'da' => '#e65100', 'text' => '#0d2a4a', 'card' => 'rgba(255,255,255,0.45)', 'dm' => false ],
            'midnight' => [ 'name' => 'Midnight', 'bg1' => '#0f172a', 'bg2' => '#1e293b', 'acc' => '#60a5fa', 'da' => '#3b82f6', 'text' => '#e2e8f0', 'card' => 'rgba(15,23,42,0.65)',  'dm' => true  ],
            'forest'   => [ 'name' => 'Forest',   'bg1' => '#d1fae5', 'bg2' => '#a7f3d0', 'acc' => '#059669', 'da' => '#047857', 'text' => '#064e3b', 'card' => 'rgba(255,255,255,0.45)', 'dm' => false ],
            'sunset'   => [ 'name' => 'Sunset',   'bg1' => '#fff1e6', 'bg2' => '#fde68a', 'acc' => '#ea580c', 'da' => '#c2410c', 'text' => '#7c2d12', 'card' => 'rgba(255,255,255,0.45)', 'dm' => false ],
            'slate'    => [ 'name' => 'Slate',    'bg1' => '#e2e8f0', 'bg2' => '#cbd5e1', 'acc' => '#7c3aed', 'da' => '#6d28d9', 'text' => '#1e293b', 'card' => 'rgba(255,255,255,0.45)', 'dm' => false ],
            'rose'     => [ 'name' => 'Rose',     'bg1' => '#fff1f2', 'bg2' => '#fecdd3', 'acc' => '#e11d48', 'da' => '#be123c', 'text' => '#881337', 'card' => 'rgba(255,255,255,0.45)', 'dm' => false ],
            'emerald'  => [ 'name' => 'Emerald',  'bg1' => '#ecfdf5', 'bg2' => '#d1fae5', 'acc' => '#d97706', 'da' => '#b45309', 'text' => '#064e3b', 'card' => 'rgba(255,255,255,0.45)', 'dm' => false ],
            'violet'   => [ 'name' => 'Violet',   'bg1' => '#1e1b4b', 'bg2' => '#312e81', 'acc' => '#a78bfa', 'da' => '#7c3aed', 'text' => '#ede9fe', 'card' => 'rgba(49,46,129,0.5)',   'dm' => true  ],
            'charcoal' => [ 'name' => 'Charcoal', 'bg1' => '#1c1c1e', 'bg2' => '#2c2c2e', 'acc' => '#f57c00', 'da' => '#e65100', 'text' => '#e5e5ea', 'card' => 'rgba(44,44,46,0.6)',    'dm' => true  ],
            'arctic'   => [ 'name' => 'Arctic',   'bg1' => '#f0fdfa', 'bg2' => '#ccfbf1', 'acc' => '#0d9488', 'da' => '#0f766e', 'text' => '#134e4a', 'card' => 'rgba(255,255,255,0.45)', 'dm' => false ],
            'copper'   => [ 'name' => 'Copper',   'bg1' => '#fdf6ec', 'bg2' => '#fde8c8', 'acc' => '#b45309', 'da' => '#92400e', 'text' => '#451a03', 'card' => 'rgba(255,255,255,0.45)', 'dm' => false ],
            'cosmic'   => [ 'name' => 'Cosmic',   'bg1' => '#0a0015', 'bg2' => '#1a0033', 'acc' => '#e879f9', 'da' => '#d946ef', 'text' => '#fae8ff', 'card' => 'rgba(26,0,51,0.5)',     'dm' => true  ],
        ];
    }

    /** Builds inline CSS overrides for the chosen colour scheme (empty string for default). */
    public static function get_404_scheme_css( string $key ): string {
        $schemes = self::get_404_schemes();
        if ( ! isset( $schemes[ $key ] ) || 'ocean' === $key ) {
            return '';
        }
        $s    = $schemes[ $key ];
        $bg1  = esc_attr( $s['bg1'] );
        $bg2  = esc_attr( $s['bg2'] );
        $acc  = esc_attr( $s['acc'] );
        $da   = esc_attr( $s['da'] );
        $text = esc_attr( $s['text'] );
        $card = esc_attr( $s['card'] );
        $css  = "body{background:linear-gradient(160deg,{$bg1} 0%,{$bg2} 100%);color:{$text};}";
        $css .= ".cs404-heading{background:linear-gradient(135deg,{$acc},{$da});-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;}";
        $css .= ".cs404-btn,.cs404-home-btn{background:linear-gradient(135deg,{$acc},{$da});box-shadow:0 4px 24px {$acc}44;}";
        $css .= ".cs404-btn:hover,.cs404-home-btn:hover{box-shadow:0 6px 28px {$acc}66;}";
        $css .= ".cs404-tab.active{background:linear-gradient(135deg,{$acc},{$da});box-shadow:0 2px 12px {$acc}44;}";
        $css .= "#cs404-game{background:{$card};border-color:rgba(128,128,128,0.2);}";
        $css .= ".cs404-lb-score{color:{$acc};}";
        $css .= ".cs404-lb-row-gold{background:{$acc}18;}";
        if ( $s['dm'] ) {
            $css .= ".cs404-desc,.cs404-site-name,.cs404-tagline{color:{$text};}";
            $css .= ".cs404-tab{background:rgba(255,255,255,0.08);color:{$text};border-color:rgba(255,255,255,0.1);}";
            $css .= ".cs404-tab:hover{background:rgba(255,255,255,0.14);}";
            $css .= ".cs404-miner-btn{background:rgba(255,255,255,0.1);color:{$text};border-color:rgba(255,255,255,0.15);}";
            $css .= "#cs404-lb-panel{background:rgba(255,255,255,0.05);border-color:rgba(255,255,255,0.1);}";
            $css .= ".cs404-lb-header{background:rgba(255,255,255,0.07);color:{$text};}";
            $css .= ".cs404-lb-name{color:{$text};}.cs404-lb-empty{color:{$text};}";
            $css .= ".cs404-lb-row{border-bottom-color:rgba(255,255,255,0.07);}";
        }
        return $css;
    }

    /**
     * Intercepts WordPress 404 responses and outputs the custom games page.
     *
     * Hooked on `template_redirect` at priority 1.
     */
    public static function maybe_custom_404(): void {
        if ( ! is_404() ) { return; }
        $is_preview = isset( $_GET['cs_devtools_preview_scheme'] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! $is_preview && ! get_option( self::CUSTOM_404_OPTION, 0 ) ) { return; }

        status_header( 404 );
        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );

        $site_name    = get_bloginfo( 'name' );
        $site_tagline = get_bloginfo( 'description' );
        $home_url     = home_url( '/' );
        $logo_html    = '';
        if ( has_custom_logo() ) {
            $logo_html = get_custom_logo();
        } elseif ( $icon_url = get_site_icon_url( 64 ) ) {
            $logo_html = '<img src="' . esc_url( $icon_url ) . '" alt="" width="48" height="48">';
        }

        $css_path = plugin_dir_path( __FILE__ ) . 'assets/cs-custom-404.css';
        $js_path  = plugin_dir_path( __FILE__ ) . 'assets/cs-custom-404.js';
        $css_url  = plugins_url( 'assets/cs-custom-404.css', __FILE__ ) . '?ver=' . self::VERSION . '.' . filemtime( $css_path );
        $js_url   = plugins_url( 'assets/cs-custom-404.js',  __FILE__ ) . '?ver=' . self::VERSION . '.' . filemtime( $js_path );

        $preview_key   = isset( $_GET['cs_devtools_preview_scheme'] ) ? sanitize_key( wp_unslash( $_GET['cs_devtools_preview_scheme'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only palette preview
        $all_schemes   = self::get_404_schemes();
        $active_scheme = ( $preview_key && isset( $all_schemes[ $preview_key ] ) ) ? $preview_key : get_option( self::SCHEME_404_OPTION, 'ocean' );
        $scheme_css    = self::get_404_scheme_css( $active_scheme );
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html__( 'Page Not Found', 'cloudscale-devtools' ); ?> &mdash; <?php echo esc_html( $site_name ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>">
<?php if ( $scheme_css ) : ?><style><?php echo $scheme_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-validated hex colours and static CSS property names ?></style><?php endif; ?>
</head>
<body>
<div class="cs404-heading-row">
<h1 class="cs404-heading">404 <?php echo esc_html__( 'Page Not Found', 'cloudscale-devtools' ); ?></h1>
<a href="<?php echo esc_url( $home_url ); ?>" class="cs404-home-btn">&#8592; Home</a>
</div>
<div class="cs404-dots" aria-hidden="true">
    <div class="cs404-dot" style="width:3px;height:3px;top:11%;left:7%;opacity:.7;"></div>
    <div class="cs404-dot" style="width:2px;height:2px;top:19%;left:86%;opacity:.5;"></div>
    <div class="cs404-dot" style="width:4px;height:4px;top:73%;left:6%;opacity:.6;"></div>
    <div class="cs404-dot" style="width:2px;height:2px;top:81%;left:91%;opacity:.5;"></div>
    <div class="cs404-dot" style="width:3px;height:3px;top:44%;left:3%;opacity:.4;"></div>
    <div class="cs404-dot" style="width:2px;height:2px;top:34%;left:96%;opacity:.4;"></div>
    <div class="cs404-dot" style="width:5px;height:5px;top:89%;left:50%;opacity:.25;background:#f57c00;"></div>
    <div class="cs404-dot" style="width:3px;height:3px;top:5%;left:48%;opacity:.35;background:#f57c00;"></div>
</div>
<div class="cs404-game-wrap">
    <div class="cs404-tabs">
        <button class="cs404-tab active" data-game="runner">🏃 Runner</button>
        <button class="cs404-tab" data-game="jetpack">🚀 Jetpack</button>
        <button class="cs404-tab" data-game="racer">🚗 Racer</button>
        <button class="cs404-tab" data-game="miner">⛏ Miner</button>
        <button class="cs404-tab" data-game="asteroids">🌌 Asteroids</button>
        <button class="cs404-tab" data-game="snake">🐍 Snake</button>
        <button class="cs404-tab" data-game="pacman">👻 Pac-Man</button>
    </div>
    <div style="position:relative;display:inline-block;max-width:100%;">
        <canvas id="cs404-game" width="620" height="280" aria-label="404 Olympics mini-games"></canvas>
        <div id="cs404-name-overlay" style="display:none;position:absolute;inset:0;z-index:10;background:rgba(13,42,74,0.88);border-radius:10px;flex-direction:column;align-items:center;justify-content:center;gap:14px;box-shadow:inset 0 0 0 2px rgba(245,124,0,0.6);">
            <p style="font-size:22px;font-weight:900;color:#f57c00;margin:0;">🏆 New High Score!</p>
            <p style="font-size:14px;color:#cce9fb;margin:0;">Enter your name:</p>
            <input id="cs404-name-input" type="text" maxlength="20" placeholder="Your name"
                style="font-size:16px;padding:8px 14px;border:2px solid #f57c00;border-radius:8px;outline:none;text-align:center;width:200px;">
            <button id="cs404-name-save"
                style="background:linear-gradient(135deg,#f57c00,#e65100);color:#fff;border:none;border-radius:8px;padding:9px 28px;font-size:15px;font-weight:700;cursor:pointer;">
                Save
            </button>
        </div>
    </div>
    <div id="cs404-miner-ctrl" class="cs404-miner-ctrl">
        <button id="cs404-ml" class="cs404-miner-btn">◀</button>
        <button id="cs404-mj" class="cs404-miner-btn">▲ Jump</button>
        <button id="cs404-mr" class="cs404-miner-btn">▶</button>
    </div>
    <div id="cs404-asteroids-ctrl" class="cs404-miner-ctrl">
        <button id="cs404-asl" class="cs404-miner-btn">◀</button>
        <button id="cs404-asu" class="cs404-miner-btn">▲ Thrust</button>
        <button id="cs404-ass" class="cs404-miner-btn">● Shoot</button>
        <button id="cs404-asr" class="cs404-miner-btn">▶</button>
    </div>
    <div id="cs404-4dir-ctrl" style="display:none;grid-template-columns:repeat(3,44px);grid-template-rows:repeat(3,44px);gap:4px;justify-content:center;margin-top:10px;">
        <span></span>
        <button id="cs404-4up" class="cs404-miner-btn" style="grid-column:2;grid-row:1;">▲</button>
        <span></span>
        <button id="cs404-4lt" class="cs404-miner-btn" style="grid-column:1;grid-row:2;">◀</button>
        <span style="grid-column:2;grid-row:2;"></span>
        <button id="cs404-4rt" class="cs404-miner-btn" style="grid-column:3;grid-row:2;">▶</button>
        <span></span>
        <button id="cs404-4dn" class="cs404-miner-btn" style="grid-column:2;grid-row:3;">▼</button>
        <span></span>
    </div>
    <div id="cs404-lb-panel">
        <div class="cs404-lb-header">
            <span id="cs404-lb-title">🏆 Runner — Top 10</span>
        </div>
        <div id="cs404-lb-body">
            <p class="cs404-lb-empty">No scores yet — be the first!</p>
        </div>
    </div>
</div>
<div class="cs404-wrap">
    <p class="cs404-desc"><?php echo esc_html__( "The page you're looking for doesn't exist or may have been moved.", 'cloudscale-devtools' ); ?></p>
    <a href="<?php echo esc_url( $home_url ); ?>" class="cs404-btn">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
        <?php echo esc_html__( 'Back to Home', 'cloudscale-devtools' ); ?>
    </a>
    <div class="cs404-brand">
        <?php if ( $logo_html ) : ?><div class="cs404-logo"><?php echo wp_kses_post( $logo_html ); ?></div><?php endif; ?>
        <p class="cs404-site-name"><?php echo esc_html( $site_name ); ?></p>
        <?php if ( $site_tagline ) : ?><p class="cs404-tagline"><?php echo esc_html( $site_tagline ); ?></p><?php endif; ?>
    </div>
</div>

<?php echo '<script>var CS_PCR_API=' . wp_json_encode( rest_url( self::HISCORE_NS ) ) . ';var CS_PCR_SCORE_NONCE=' . wp_json_encode( wp_create_nonce( self::SCORE_NONCE_ACTION ) ) . ';</script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone exit-page ?>
<?php echo '<script src="' . esc_url( $js_url ) . '"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone 404 exit-page, no wp_head/wp_footer ?>

</body>
</html>
        <?php
        exit;
    }

    /** Registers per-game hi-score REST endpoints. */
    public static function register_hiscore_routes(): void {
        register_rest_route( self::HISCORE_NS, '/hiscore/(?P<game>runner|jetpack|racer|miner|asteroids|snake|pacman)', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'rest_get_hiscore' ],
                'permission_callback' => '__return_true',
                'args'                => [ 'game' => [ 'required' => true, 'type' => 'string' ] ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'rest_set_hiscore' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'game'  => [ 'required' => true, 'type' => 'string' ],
                    'score' => [ 'required' => true, 'type' => 'integer', 'minimum' => 1, 'maximum' => 999999 ],
                    'name'  => [ 'required' => true, 'type' => 'string', 'maxLength' => 30 ],
                ],
            ],
        ] );
    }

    /** Returns the top-10 leaderboard for one game. */
    public static function rest_get_hiscore( WP_REST_Request $request ): WP_REST_Response {
        $game = sanitize_key( $request->get_param( 'game' ) );
        $raw  = get_option( 'cs_devtools_leaderboard_' . $game, '' );
        $lb   = $raw ? json_decode( $raw, true ) : [];
        if ( ! is_array( $lb ) ) { $lb = []; }
        return rest_ensure_response( [ 'leaderboard' => $lb ] );
    }

    /** Inserts a score into the top-10 leaderboard for one game. */
    public static function rest_set_hiscore( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'x_wp_score_nonce' );
        if ( ! $nonce || ! wp_verify_nonce( sanitize_text_field( $nonce ), self::SCORE_NONCE_ACTION ) ) {
            return new WP_Error( 'forbidden', __( 'Invalid nonce.', 'cloudscale-devtools' ), [ 'status' => 403 ] );
        }
        $game  = sanitize_key( $request->get_param( 'game' ) );
        $score = (int) $request->get_param( 'score' );
        $name  = sanitize_text_field( $request->get_param( 'name' ) );

        $score_caps = [ 'runner' => 999999, 'jetpack' => 999999, 'racer' => 999999, 'miner' => 2000, 'asteroids' => 999999, 'snake' => 9990, 'pacman' => 99990 ];
        if ( isset( $score_caps[ $game ] ) && $score > $score_caps[ $game ] ) {
            return new WP_Error( 'score_invalid', __( 'Score exceeds maximum for this game.', 'cloudscale-devtools' ), [ 'status' => 422 ] );
        }

        // Rate limit: max 5 submissions per IP per game per 10 minutes.
        $ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $ip_key = 'cs_devtools_rl_' . md5( $ip . $game );
        $count  = (int) get_transient( $ip_key );
        if ( $count >= 5 ) {
            return new WP_Error( 'rate_limited', __( 'Too many score submissions. Try again later.', 'cloudscale-devtools' ), [ 'status' => 429 ] );
        }
        set_transient( $ip_key, $count + 1, 600 );

        $raw = get_option( 'cs_devtools_leaderboard_' . $game, '' );
        $lb  = $raw ? json_decode( $raw, true ) : [];
        if ( ! is_array( $lb ) ) { $lb = []; }

        foreach ( $lb as $entry ) {
            if ( (int) $entry['score'] === $score && $entry['name'] === $name ) {
                return rest_ensure_response( [ 'ok' => false, 'leaderboard' => $lb ] );
            }
        }
        $lowest = isset( $lb[9] ) ? (int) $lb[9]['score'] : 0;
        if ( count( $lb ) >= 10 && $score <= $lowest ) {
            return rest_ensure_response( [ 'ok' => false, 'leaderboard' => $lb ] );
        }
        $lb[] = [ 'score' => $score, 'name' => $name ];
        usort( $lb, fn( $a, $b ) => (int) $b['score'] - (int) $a['score'] );
        $lb = array_slice( $lb, 0, 10 );
        update_option( 'cs_devtools_leaderboard_' . $game, wp_json_encode( $lb ), false );
        return rest_ensure_response( [ 'ok' => true, 'leaderboard' => $lb ] );
    }

    /** AJAX handler: saves the 404 enable toggle and colour scheme. */
    public static function ajax_save_404_settings(): void {
        check_ajax_referer( 'cs_devtools_404_settings', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Forbidden.', 'cloudscale-devtools' ) );
        }

        $custom_404 = isset( $_POST['custom_404'] ) ? ( absint( wp_unslash( $_POST['custom_404'] ) ) ? 1 : 0 ) : 0;
        update_option( self::CUSTOM_404_OPTION, $custom_404 );

        if ( isset( $_POST['scheme'] ) ) {
            $schemes    = self::get_404_schemes();
            $scheme_key = sanitize_key( wp_unslash( $_POST['scheme'] ) );
            if ( isset( $schemes[ $scheme_key ] ) ) {
                update_option( self::SCHEME_404_OPTION, $scheme_key );
            }
        }

        wp_send_json_success( [ 'custom_404' => $custom_404, 'scheme' => get_option( self::SCHEME_404_OPTION, 'ocean' ) ] );
    }

    /** Renders the 404 Games settings panel. */
    private static function render_404_panel(): void {
        $current_scheme = get_option( self::SCHEME_404_OPTION, 'ocean' );
        $enabled        = (bool) get_option( self::CUSTOM_404_OPTION, 0 );
        ?>
        <div class="cs-panel" id="cs-panel-404">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#f57c00,#e65100);">
                <span>🎮 404 GAMES PAGE</span>
                <?php self::render_explain_btn( '404-games', '404 Games Page', [
                    [ 'name' => 'Enable',        'rec' => 'Toggle', 'desc' => 'When enabled, replaces the default WordPress 404 page with a fun interactive page featuring 5 mini-games: Runner, Jetpack, Racer, Miner, and Asteroids. No theme dependency — works even if the active theme is broken.' ],
                    [ 'name' => 'Colour Scheme', 'rec' => 'Optional', 'desc' => 'Choose from 12 built-in colour palettes. Changes take effect immediately. Use Preview to see the result before saving.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <div class="cs-field" style="margin-bottom:20px;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                        <input type="checkbox" id="cs-404-enabled" <?php checked( $enabled ); ?>>
                        <span class="cs-label" style="margin:0;"><?php esc_html_e( 'Enable custom 404 games page', 'cloudscale-devtools' ); ?></span>
                    </label>
                    <span class="cs-hint"><?php esc_html_e( 'Replaces the default WordPress 404 with 5 playable mini-games and a global leaderboard.', 'cloudscale-devtools' ); ?></span>
                    <div id="cs-404-toggle-msg" style="margin-top:8px;display:none;"></div>
                </div>

                <div class="cs-field" style="margin-bottom:16px;">
                    <label class="cs-label"><?php esc_html_e( 'Colour Scheme:', 'cloudscale-devtools' ); ?></label>
                    <div class="cs-pcr-scheme-grid" id="cs-404-scheme-grid" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">
                        <?php foreach ( self::get_404_schemes() as $key => $s ) : ?>
                        <button type="button" class="cs-404-scheme-swatch<?php echo $key === $current_scheme ? ' active' : ''; ?>"
                            data-scheme="<?php echo esc_attr( $key ); ?>"
                            style="border:2px solid <?php echo $key === $current_scheme ? '#f57c00' : '#ddd'; ?>;border-radius:8px;padding:4px;background:none;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:4px;width:76px;">
                            <span style="display:block;width:60px;height:36px;border-radius:5px;background:linear-gradient(135deg,<?php echo esc_attr( $s['bg1'] ); ?>,<?php echo esc_attr( $s['bg2'] ); ?>);position:relative;">
                                <span style="position:absolute;bottom:4px;right:4px;width:12px;height:12px;border-radius:50%;background:<?php echo esc_attr( $s['acc'] ); ?>;"></span>
                            </span>
                            <span style="font-size:11px;color:#333;"><?php echo esc_html( $s['name'] ); ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:10px;margin-top:16px;">
                    <button type="button" class="cs-btn-primary" id="cs-404-save-scheme">💾 <?php esc_html_e( 'Save Scheme', 'cloudscale-devtools' ); ?></button>
                    <a href="<?php echo esc_url( home_url( '/this-page-does-not-exist' ) ); ?>" target="_blank" rel="noopener" id="cs-404-preview-link"
                       style="display:inline-block;padding:7px 16px;border-radius:5px;background:#555;color:#fff;text-decoration:none;font-size:13px;">
                        <?php esc_html_e( 'Preview 404', 'cloudscale-devtools' ); ?> &rarr;
                    </a>
                    <span id="cs-404-scheme-msg" style="display:none;"></span>
                </div>
            </div>
        </div>
        <?php
    }

    /* ==================================================================
       17. THUMBNAILS — Social Preview Diagnostics
       ================================================================== */

    // ─── Nonce / constants ──────────────────────────────────────────────
    private const THUMB_NONCE = 'cs_devtools_thumbnails';

    private const SOCIAL_UAS = [
        'WhatsApp'            => 'WhatsApp/2.23.24.82 A',
        'Facebook'            => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
        'Facebot'             => 'Facebot',
        'LinkedInBot'         => 'LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)',
        'Twitterbot'          => 'Twitterbot/1.0',
    ];

    // ─── Panel render ────────────────────────────────────────────────────

    private static function render_thumbnails_panel(): void {
        $cf_zone  = get_option( 'cs_devtools_cf_zone_id', '' );
        $cf_token = get_option( 'cs_devtools_cf_api_token', '' );
        $cf_token_masked = $cf_token ? str_repeat( '•', 12 ) . substr( $cf_token, -4 ) : '';
        ?>
        <div class="cs-panel" id="cs-panel-thumbs-checker">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#1565c0,#0d47a1);">
                <span>🔍 URL SOCIAL PREVIEW CHECKER</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Run a full social-preview diagnostic on any URL', 'cloudscale-devtools' ); ?></span>
            </div>
            <div class="cs-panel-body">
                <p class="cs-hint" style="margin-bottom:10px"><?php esc_html_e( 'Checks OG tags, og:image size/dimensions, HTTPS, robots.txt, and verifies each platform crawler can actually read the page.', 'cloudscale-devtools' ); ?></p>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <input type="url" id="cs-thumb-check-url" class="cs-input" style="max-width:520px;flex:1"
                           placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>"
                           value="<?php echo esc_attr( home_url( '/' ) ); ?>">
                    <button type="button" class="cs-btn-primary" id="cs-thumb-check-btn">🔍 <?php esc_html_e( 'Run Diagnostic', 'cloudscale-devtools' ); ?></button>
                </div>
                <div id="cs-thumb-check-results" style="margin-top:14px;display:none"></div>
            </div>
        </div>

        <div class="cs-panel" id="cs-panel-thumbs-posts">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#2e7d32,#1b5e20);">
                <span>📋 RECENT POSTS SCAN</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Auto-check the last 10 posts that have a featured image', 'cloudscale-devtools' ); ?></span>
            </div>
            <div class="cs-panel-body">
                <p class="cs-hint" style="margin-bottom:10px"><?php esc_html_e( 'Fetches your 10 most recent published posts with featured images and runs the full diagnostic on each. Results show the overall status per post.', 'cloudscale-devtools' ); ?></p>
                <button type="button" class="cs-btn-primary" id="cs-thumb-scan-btn">📋 <?php esc_html_e( 'Scan Recent Posts', 'cloudscale-devtools' ); ?></button>
                <div id="cs-thumb-scan-results" style="margin-top:14px;display:none"></div>
            </div>
        </div>

        <div class="cs-panel" id="cs-panel-thumbs-cloudflare">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#e65100,#bf360c);">
                <span>☁️ CLOUDFLARE SETUP &amp; DIAGNOSTICS</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Configure WAF bypass rules and test cache behaviour', 'cloudscale-devtools' ); ?></span>
            </div>
            <div class="cs-panel-body">

                <!-- CF Setup Guide -->
                <div class="cs-thumb-cf-guide">
                    <h3 style="margin-top:0;font-size:14px;color:#333"><?php esc_html_e( 'Why social previews fail with Cloudflare', 'cloudscale-devtools' ); ?></h3>
                    <p class="cs-hint"><?php esc_html_e( 'Cloudflare\'s Bot Fight Mode and Super Bot Fight Mode block social crawler user agents (WhatsApp, Facebook, LinkedIn, X/Twitter) before they can read your page\'s OG tags. The fix is a WAF Custom Rule that skips Bot Fight Mode for those specific UAs.', 'cloudscale-devtools' ); ?></p>

                    <div class="cs-thumb-cf-steps">
                        <div class="cs-thumb-cf-step">
                            <span class="cs-thumb-cf-step-num">1</span>
                            <div>
                                <strong><?php esc_html_e( 'Open Cloudflare Dashboard', 'cloudscale-devtools' ); ?></strong>
                                <p class="cs-hint"><?php esc_html_e( 'Go to your Cloudflare dashboard → select your domain → Security → WAF → Custom Rules.', 'cloudscale-devtools' ); ?></p>
                            </div>
                        </div>
                        <div class="cs-thumb-cf-step">
                            <span class="cs-thumb-cf-step-num">2</span>
                            <div>
                                <strong><?php esc_html_e( 'Create a Custom Rule: "Allow Social Crawlers"', 'cloudscale-devtools' ); ?></strong>
                                <p class="cs-hint"><?php esc_html_e( 'Use the Expression Editor and paste this expression:', 'cloudscale-devtools' ); ?></p>
                                <pre class="cs-thumb-cf-code">(http.user_agent contains "WhatsApp") or (http.user_agent contains "facebookexternalhit") or (http.user_agent contains "Facebot") or (http.user_agent contains "LinkedInBot") or (http.user_agent contains "Twitterbot")</pre>
                                <p class="cs-hint"><?php esc_html_e( 'Set the Action to "Skip" and tick "Bot Fight Mode" and "Super Bot Fight Mode".', 'cloudscale-devtools' ); ?></p>
                            </div>
                        </div>
                        <div class="cs-thumb-cf-step">
                            <span class="cs-thumb-cf-step-num">3</span>
                            <div>
                                <strong><?php esc_html_e( 'Deploy and verify', 'cloudscale-devtools' ); ?></strong>
                                <p class="cs-hint"><?php esc_html_e( 'Save the rule, then use the "Test Crawler Access" button below to confirm each crawler UA gets a 200 response with OG tags present.', 'cloudscale-devtools' ); ?></p>
                            </div>
                        </div>
                        <div class="cs-thumb-cf-step">
                            <span class="cs-thumb-cf-step-num">4</span>
                            <div>
                                <strong><?php esc_html_e( 'Cache note', 'cloudscale-devtools' ); ?></strong>
                                <p class="cs-hint"><?php esc_html_e( 'If social platforms have already cached a failed preview, purge the Cloudflare cache for that URL and then use each platform\'s debug tool to force a re-scrape (Facebook Sharing Debugger, LinkedIn Post Inspector, etc.).', 'cloudscale-devtools' ); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Crawler UA Test -->
                <div style="margin-top:18px;padding-top:16px;border-top:1px solid #e0e0e0">
                    <h3 style="font-size:14px;color:#333;margin-top:0"><?php esc_html_e( 'Test Crawler Access', 'cloudscale-devtools' ); ?></h3>
                    <p class="cs-hint"><?php esc_html_e( 'Fetches a page using each social crawler\'s user agent from the server. If all return HTTP 200 with OG tags present, your WAF rule is working.', 'cloudscale-devtools' ); ?></p>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
                        <input type="url" id="cs-thumb-cf-test-url" class="cs-input" style="max-width:480px;flex:1"
                               placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>"
                               value="<?php echo esc_attr( home_url( '/' ) ); ?>">
                        <button type="button" class="cs-btn-primary" id="cs-thumb-cf-test-btn">🤖 <?php esc_html_e( 'Test Crawler Access', 'cloudscale-devtools' ); ?></button>
                    </div>
                    <div id="cs-thumb-cf-test-results" style="display:none"></div>
                </div>

                <!-- CF Cache Purge -->
                <div style="margin-top:18px;padding-top:16px;border-top:1px solid #e0e0e0">
                    <h3 style="font-size:14px;color:#333;margin-top:0"><?php esc_html_e( 'Cloudflare Cache Purge', 'cloudscale-devtools' ); ?></h3>
                    <p class="cs-hint"><?php esc_html_e( 'After fixing OG tags or image issues, purge the Cloudflare cache to force crawlers to re-fetch the page. Requires a Cloudflare API Token with Cache Purge permission and your Zone ID.', 'cloudscale-devtools' ); ?></p>

                    <div class="cs-field-row" style="flex-wrap:wrap;gap:12px">
                        <div class="cs-field" style="min-width:240px">
                            <label class="cs-label" for="cs-cf-zone-id"><?php esc_html_e( 'Zone ID', 'cloudscale-devtools' ); ?></label>
                            <input type="text" id="cs-cf-zone-id" class="cs-input" value="<?php echo esc_attr( $cf_zone ); ?>"
                                   placeholder="<?php esc_attr_e( '32-character hex string', 'cloudscale-devtools' ); ?>">
                        </div>
                        <div class="cs-field" style="min-width:280px">
                            <label class="cs-label" for="cs-cf-api-token"><?php esc_html_e( 'API Token (Cache Purge permission)', 'cloudscale-devtools' ); ?></label>
                            <input type="password" id="cs-cf-api-token" class="cs-input" value=""
                                   placeholder="<?php echo esc_attr( $cf_token_masked ?: __( 'Paste token here', 'cloudscale-devtools' ) ); ?>">
                            <span class="cs-hint"><?php esc_html_e( 'Leave blank to keep the saved token. Clear and save to remove.', 'cloudscale-devtools' ); ?></span>
                        </div>
                    </div>

                    <div style="margin:12px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        <input type="url" id="cs-cf-purge-url" class="cs-input" style="max-width:420px;flex:1"
                               placeholder="<?php esc_attr_e( 'https://yoursite.com/your-post/ (leave blank to purge everything)', 'cloudscale-devtools' ); ?>">
                        <button type="button" class="cs-btn-primary" id="cs-cf-purge-btn">🗑️ <?php esc_html_e( 'Purge Cache', 'cloudscale-devtools' ); ?></button>
                        <button type="button" class="cs-btn-secondary" id="cs-cf-save-btn" style="background:#555;color:#fff;padding:7px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px">💾 <?php esc_html_e( 'Save CF Settings', 'cloudscale-devtools' ); ?></button>
                    </div>
                    <div id="cs-cf-purge-result" style="display:none;margin-top:8px"></div>
                    <span class="cs-settings-saved" id="cs-cf-saved">✓ <?php esc_html_e( 'CF Settings Saved', 'cloudscale-devtools' ); ?></span>
                </div>
            </div>
        </div>

        <div class="cs-panel" id="cs-panel-thumbs-media">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#6a1b9a,#4a148c);">
                <span>📦 MEDIA LIBRARY AUDIT</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Find images that exceed the WhatsApp 300 KB threshold or are too small', 'cloudscale-devtools' ); ?></span>
            </div>
            <div class="cs-panel-body">
                <p class="cs-hint" style="margin-bottom:10px"><?php esc_html_e( 'Scans your media library for images that may cause silent failures on WhatsApp (>300 KB) or render low-quality previews on any platform (under 1200×630 px). Use "Recompress" to auto-fix oversized JPEGs/PNGs.', 'cloudscale-devtools' ); ?></p>
                <button type="button" class="cs-btn-primary" id="cs-thumb-audit-btn">🔎 <?php esc_html_e( 'Audit Media Library', 'cloudscale-devtools' ); ?></button>
                <span id="cs-thumb-audit-progress" style="margin-left:10px;font-size:12px;color:#888"></span>
                <div id="cs-thumb-audit-results" style="margin-top:14px;display:none"></div>
            </div>
        </div>

        <style>
        .cs-thumb-cf-steps { display:flex;flex-direction:column;gap:12px;margin-top:10px }
        .cs-thumb-cf-step  { display:flex;gap:12px;align-items:flex-start }
        .cs-thumb-cf-step-num { min-width:26px;height:26px;background:#e65100;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;margin-top:2px }
        .cs-thumb-cf-code  { background:#1e1e1e;color:#e8e8e8;padding:10px 14px;border-radius:4px;font-size:12px;overflow-x:auto;white-space:pre-wrap;word-break:break-all;margin:6px 0 }
        .cs-thumb-report-hdr { display:flex;justify-content:space-between;align-items:center;padding:9px 14px;border-radius:4px;margin-bottom:10px;font-size:13px;font-weight:600 }
        .cs-thumb-pass-hdr { background:#edfaed;color:#276227 }
        .cs-thumb-warn-hdr { background:#fff8e5;color:#7a5a00 }
        .cs-thumb-fail-hdr { background:#fdf0f0;color:#8c2020 }
        .cs-thumb-tally { display:flex;gap:12px;font-size:13px }
        .cs-thumb-section { border:1px solid #e0e0e0;border-radius:4px;margin-bottom:10px;overflow:hidden }
        .cs-thumb-section-title { background:#f6f7f7;padding:6px 12px;font-size:12px;font-weight:700;color:#333;border-bottom:1px solid #e0e0e0;text-transform:uppercase;letter-spacing:.4px }
        .cs-thumb-results-list { margin:0;padding:6px 10px;list-style:none }
        .cs-thumb-result { display:flex;gap:8px;padding:3px 0;font-size:12px;align-items:flex-start }
        .cs-thumb-pass { color:#276227 }
        .cs-thumb-warn { color:#7a5a00 }
        .cs-thumb-fail { color:#8c2020 }
        .cs-thumb-info { color:#555 }
        .cs-thumb-ua-grid { display:flex;flex-wrap:wrap;gap:8px;margin-top:8px }
        .cs-thumb-ua-chip { padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600 }
        .cs-thumb-ua-ok   { background:#edfaed;color:#276227 }
        .cs-thumb-ua-fail { background:#fdf0f0;color:#8c2020 }
        .cs-thumb-ua-warn { background:#fff8e5;color:#7a5a00 }
        .cs-thumb-posts-table { width:100%;border-collapse:collapse;font-size:13px }
        .cs-thumb-posts-table th { background:#f6f7f7;padding:7px 10px;text-align:left;border-bottom:2px solid #ddd }
        .cs-thumb-posts-table td { padding:7px 10px;border-bottom:1px solid #eee;vertical-align:top }
        .cs-thumb-badge-ok   { display:inline-block;background:#edfaed;color:#276227;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600 }
        .cs-thumb-badge-warn { display:inline-block;background:#fff8e5;color:#7a5a00;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600 }
        .cs-thumb-badge-fail { display:inline-block;background:#fdf0f0;color:#8c2020;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600 }
        .cs-thumb-audit-table { font-size:12px;width:100%;border-collapse:collapse }
        .cs-thumb-audit-table th { background:#f6f7f7;padding:6px 10px;text-align:left;border-bottom:2px solid #ddd }
        .cs-thumb-audit-table td { padding:6px 10px;border-bottom:1px solid #eee;vertical-align:top }
        </style>
        <?php
    }

    // ─── AJAX: check a single URL ────────────────────────────────────────

    public static function ajax_social_check_url(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        if ( ! $url ) {
            wp_send_json_error( [ 'message' => 'No URL provided.' ] );
        }
        wp_send_json_success( self::social_diagnose_url( $url ) );
    }

    // ─── AJAX: scan last 10 posts ────────────────────────────────────────

    public static function ajax_social_scan_posts(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $posts = get_posts( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'meta_query'     => [ [ 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ] ],
        ] );
        $results = [];
        foreach ( $posts as $post ) {
            $url  = get_permalink( $post );
            $diag = self::social_diagnose_url( $url );
            $results[] = [
                'id'      => $post->ID,
                'title'   => get_the_title( $post ),
                'url'     => $url,
                'totals'  => $diag['totals'],
                'og_image'=> $diag['og_image'] ?? '',
                'img_kb'  => $diag['img_kb'] ?? null,
                'img_w'   => $diag['img_w'] ?? null,
                'img_h'   => $diag['img_h'] ?? null,
            ];
        }
        wp_send_json_success( $results );
    }

    // ─── AJAX: media library audit ───────────────────────────────────────

    public static function ajax_social_scan_media(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        global $wpdb;
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT p.ID, p.post_title, pm.meta_value AS attach_meta
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_metadata'
             WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
               AND p.post_mime_type LIKE 'image/%'
             ORDER BY p.ID DESC LIMIT 500"
        );
        $upload  = wp_upload_dir();
        $basedir = $upload['basedir'];
        $baseurl = $upload['baseurl'];
        $total   = 0;
        $issues  = [];

        foreach ( $rows as $row ) {
            $total++;
            $meta = maybe_unserialize( $row->attach_meta );
            if ( ! is_array( $meta ) || empty( $meta['file'] ) ) {
                continue;
            }
            $abs  = $basedir . '/' . $meta['file'];
            $url  = $baseurl . '/' . $meta['file'];
            if ( ! file_exists( $abs ) ) {
                continue;
            }
            $bytes  = (int) filesize( $abs );
            $kb     = round( $bytes / 1024, 1 );
            $width  = (int) ( $meta['width']  ?? 0 );
            $height = (int) ( $meta['height'] ?? 0 );
            $ext    = strtolower( pathinfo( $abs, PATHINFO_EXTENSION ) );
            $flags  = [];

            if ( $bytes > 307200 ) {
                $flags[] = [ 'severity' => 'fail',  'issue' => "{$kb} KB — exceeds WhatsApp 300 KB threshold (silent failure)" ];
            } elseif ( $bytes > 204800 ) {
                $flags[] = [ 'severity' => 'warn', 'issue' => "{$kb} KB — approaching 300 KB WhatsApp limit" ];
            }
            if ( in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp' ], true ) ) {
                if ( $width > 0 && $height > 0 && ( $width < 1200 || $height < 630 ) ) {
                    $flags[] = [ 'severity' => $width < 600 ? 'fail' : 'warn', 'issue' => "{$width}×{$height}px — below 1200×630 minimum recommendation" ];
                }
            }
            if ( empty( $flags ) ) {
                continue;
            }
            $issues[] = [
                'id'      => (int) $row->ID,
                'title'   => esc_html( $row->post_title ),
                'file'    => esc_html( basename( $abs ) ),
                'url'     => esc_url( $url ),
                'size_kb' => $kb,
                'width'   => $width,
                'height'  => $height,
                'ext'     => $ext,
                'flags'   => $flags,
                'can_fix' => in_array( $ext, [ 'jpg', 'jpeg', 'png' ], true ) && $bytes > 204800,
            ];
        }
        wp_send_json_success( [ 'total_scanned' => $total, 'issues_found' => count( $issues ), 'issues' => $issues ] );
    }

    // ─── AJAX: recompress an oversized image ─────────────────────────────

    public static function ajax_social_fix_image(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $attachment_id = absint( $_POST['attachment_id'] ?? 0 );
        if ( ! $attachment_id ) {
            wp_send_json_error( [ 'message' => 'No attachment ID.' ] );
        }
        $result = self::social_recompress_image( $attachment_id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        wp_send_json_success( $result );
    }

    // ─── AJAX: Cloudflare crawler UA test ────────────────────────────────

    public static function ajax_social_cf_test(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : home_url( '/' );
        wp_send_json_success( self::social_test_crawlers( $url ) );
    }

    // ─── AJAX: Cloudflare cache purge ────────────────────────────────────

    public static function ajax_cf_purge(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $zone_id = get_option( 'cs_devtools_cf_zone_id', '' );
        $token   = get_option( 'cs_devtools_cf_api_token', '' );
        if ( ! $zone_id || ! $token ) {
            wp_send_json_error( [ 'message' => __( 'Cloudflare Zone ID and API Token are required. Please save them above.', 'cloudscale-devtools' ) ] );
        }
        $purge_url = isset( $_POST['purge_url'] ) ? esc_url_raw( wp_unslash( $_POST['purge_url'] ) ) : '';
        $cf_api    = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
        $body      = $purge_url
            ? wp_json_encode( [ 'files' => [ $purge_url ] ] )
            : wp_json_encode( [ 'purge_everything' => true ] );
        $response = wp_remote_post( $cf_api, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $body,
            'timeout' => 15,
        ] );
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! empty( $data['success'] ) ) {
            wp_send_json_success( [
                'message' => $purge_url
                    ? sprintf( __( 'Cache purged for: %s', 'cloudscale-devtools' ), $purge_url )
                    : __( 'Entire Cloudflare cache purged successfully.', 'cloudscale-devtools' ),
            ] );
        } else {
            $errors = isset( $data['errors'] ) ? wp_json_encode( $data['errors'] ) : __( 'Unknown error', 'cloudscale-devtools' );
            wp_send_json_error( [ 'message' => $errors ] );
        }
    }

    // ─── AJAX: save CF credentials ───────────────────────────────────────

    public static function ajax_cf_save(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $zone_id = isset( $_POST['zone_id'] ) ? sanitize_text_field( wp_unslash( $_POST['zone_id'] ) ) : '';
        $token   = isset( $_POST['api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['api_token'] ) ) : '';
        update_option( 'cs_devtools_cf_zone_id', $zone_id );
        if ( $token !== '' ) {
            update_option( 'cs_devtools_cf_api_token', $token );
        }
        wp_send_json_success( [ 'message' => __( 'Cloudflare settings saved.', 'cloudscale-devtools' ) ] );
    }

    // ─── Private: full URL diagnostic ────────────────────────────────────

    /**
     * Runs all social-preview checks against a URL and returns a structured
     * result array with sections (title + result items) and summary totals.
     */
    private static function social_diagnose_url( string $url ): array {
        $sections = [];
        $og_image = '';
        $img_kb   = null;
        $img_w    = null;
        $img_h    = null;

        $wa_ua = self::SOCIAL_UAS['WhatsApp'];

        // 1. HTTPS
        $pass = str_starts_with( $url, 'https://' );
        $sections[] = [
            'title'   => 'HTTPS',
            'results' => [ $pass
                ? [ 'type' => 'pass', 'msg' => 'URL uses HTTPS' ]
                : [ 'type' => 'fail', 'msg' => 'URL uses HTTP — WhatsApp requires HTTPS for link previews.' ] ],
        ];

        // 2. HTTP response (WhatsApp UA)
        $head = wp_remote_head( $url, [ 'user-agent' => $wa_ua, 'redirection' => 5, 'timeout' => 12, 'sslverify' => true ] );
        $http_ok = false;
        if ( is_wp_error( $head ) ) {
            $sections[] = [ 'title' => 'HTTP Response', 'results' => [ [ 'type' => 'fail', 'msg' => 'Could not connect: ' . $head->get_error_message() ] ] ];
        } else {
            $code = wp_remote_retrieve_response_code( $head );
            $http_ok = ( $code === 200 );
            $sections[] = [ 'title' => 'HTTP Response (WhatsApp UA)', 'results' => [ $code === 200
                ? [ 'type' => 'pass', 'msg' => 'HTTP 200 OK' ]
                : [ 'type' => in_array( $code, [ 301, 302, 307, 308 ], true ) ? 'warn' : 'fail',
                    'msg'  => "HTTP $code — " . ( in_array( $code, [ 301, 302, 307, 308 ], true ) ? 'Redirect (crawlers follow, but adds latency)' : 'Non-200 response; crawler may not read OG tags' ) ] ] ];
        }

        // 3. Fetch HTML + measure response time
        $start = microtime( true );
        $resp  = wp_remote_get( $url, [ 'user-agent' => $wa_ua, 'redirection' => 5, 'timeout' => 18 ] );
        $elapsed = round( microtime( true ) - $start, 2 );
        $html = is_wp_error( $resp ) ? '' : wp_remote_retrieve_body( $resp );

        $sections[] = [ 'title' => 'Response Time', 'results' => [ $elapsed < 3.0
            ? [ 'type' => 'pass', 'msg' => "{$elapsed}s — within 3s crawler timeout" ]
            : ( $elapsed < 5.0
                ? [ 'type' => 'warn', 'msg' => "{$elapsed}s — approaching WhatsApp 3–5s timeout" ]
                : [ 'type' => 'fail', 'msg' => "{$elapsed}s — exceeds 5s; crawler will likely abort before reading OG tags" ] ) ] ];

        // 4. OG tags
        $og_results = [];
        foreach ( [ 'og:title', 'og:description', 'og:image', 'og:url', 'og:type' ] as $prop ) {
            $val = self::social_extract_property( $html, $prop );
            $og_results[] = $val
                ? [ 'type' => 'pass', 'msg' => "$prop: " . mb_substr( $val, 0, 80 ) ]
                : [ 'type' => 'fail', 'msg' => "$prop is missing" ];
        }
        foreach ( [ 'twitter:card', 'twitter:image' ] as $name ) {
            $val = self::social_extract_name( $html, $name );
            $og_results[] = $val
                ? [ 'type' => 'pass', 'msg' => "$name: " . mb_substr( $val, 0, 80 ) ]
                : [ 'type' => 'warn', 'msg' => "$name missing — X/Twitter may not render large card" ];
        }
        $sections[] = [ 'title' => 'Open Graph Tags', 'results' => $og_results ];

        // 5. og:image analysis
        $og_image = self::social_extract_property( $html, 'og:image' );
        $img_results = [];
        if ( ! $og_image ) {
            $img_results[] = [ 'type' => 'fail', 'msg' => 'og:image is missing — cannot analyse image.' ];
        } else {
            $img_head = wp_remote_head( $og_image, [ 'user-agent' => $wa_ua, 'timeout' => 10, 'redirection' => 3 ] );
            if ( is_wp_error( $img_head ) ) {
                $img_results[] = [ 'type' => 'fail', 'msg' => 'og:image URL unreachable: ' . $img_head->get_error_message() ];
            } else {
                $img_code = wp_remote_retrieve_response_code( $img_head );
                $img_results[] = $img_code === 200
                    ? [ 'type' => 'pass', 'msg' => 'og:image URL returns HTTP 200' ]
                    : [ 'type' => 'fail', 'msg' => "og:image URL returns HTTP $img_code — image inaccessible" ];
                $ct = wp_remote_retrieve_header( $img_head, 'content-type' );
                $img_results[] = str_contains( (string) $ct, 'image/' )
                    ? [ 'type' => 'pass', 'msg' => "Content-Type: $ct" ]
                    : [ 'type' => 'warn', 'msg' => "Unexpected Content-Type: '$ct'" ];
            }
            $img_results[] = str_starts_with( $og_image, 'https://' )
                ? [ 'type' => 'pass', 'msg' => 'og:image uses HTTPS' ]
                : [ 'type' => 'fail', 'msg' => 'og:image uses HTTP — WhatsApp requires HTTPS images' ];

            $img_resp = wp_remote_get( $og_image, [ 'user-agent' => $wa_ua, 'timeout' => 20, 'redirection' => 3 ] );
            if ( ! is_wp_error( $img_resp ) ) {
                $img_body = wp_remote_retrieve_body( $img_resp );
                $img_bytes = strlen( $img_body );
                $img_kb    = round( $img_bytes / 1024, 1 );
                if ( $img_bytes > 307200 ) {
                    $img_results[] = [ 'type' => 'fail', 'msg' => "Image is {$img_kb} KB — exceeds WhatsApp's 300 KB silent-failure threshold. Compress to under 250 KB." ];
                } elseif ( $img_bytes > 204800 ) {
                    $img_results[] = [ 'type' => 'warn', 'msg' => "Image is {$img_kb} KB — approaching 300 KB WhatsApp limit. Consider optimising." ];
                } else {
                    $img_results[] = [ 'type' => 'pass', 'msg' => "Image is {$img_kb} KB — within the 300 KB WhatsApp limit." ];
                }
                if ( function_exists( 'imagecreatefromstring' ) ) {
                    $res = @imagecreatefromstring( $img_body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                    if ( $res ) {
                        $img_w = imagesx( $res );
                        $img_h = imagesy( $res );
                        imagedestroy( $res );
                        if ( $img_w >= 1200 && $img_h >= 630 ) {
                            $img_results[] = [ 'type' => 'pass', 'msg' => "Dimensions: {$img_w}×{$img_h}px — meets 1200×630 minimum" ];
                        } elseif ( $img_w >= 600 ) {
                            $img_results[] = [ 'type' => 'warn', 'msg' => "Dimensions: {$img_w}×{$img_h}px — below recommended 1200×630" ];
                        } else {
                            $img_results[] = [ 'type' => 'fail', 'msg' => "Dimensions: {$img_w}×{$img_h}px — too small for reliable social previews" ];
                        }
                    }
                }
            }
        }
        $sections[] = [ 'title' => 'og:image Analysis', 'results' => $img_results ];

        // 6. robots.txt
        $base_url    = preg_replace( '#(https?://[^/]+).*#', '$1', $url );
        $robots_resp = wp_remote_get( "$base_url/robots.txt", [ 'timeout' => 8 ] );
        $rb_results  = [];
        if ( is_wp_error( $robots_resp ) || wp_remote_retrieve_response_code( $robots_resp ) !== 200 ) {
            $rb_results[] = [ 'type' => 'warn', 'msg' => 'robots.txt not found — ensure crawlers are not blocked elsewhere' ];
        } else {
            $rb_body = wp_remote_retrieve_body( $robots_resp );
            foreach ( [ 'facebookexternalhit', 'WhatsApp', 'Facebot', 'LinkedInBot', 'Twitterbot' ] as $bot ) {
                if ( preg_match( '/User-agent:\s*' . preg_quote( $bot, '/' ) . '.*?Disallow:\s*\//si', $rb_body ) ) {
                    $rb_results[] = [ 'type' => 'fail', 'msg' => "robots.txt blocks $bot — this prevents all previews from that platform" ];
                } else {
                    $rb_results[] = [ 'type' => 'pass', 'msg' => "robots.txt does not block $bot" ];
                }
            }
        }
        $sections[] = [ 'title' => 'robots.txt', 'results' => $rb_results ];

        // 7. Cloudflare detection
        $cf_results = [];
        $cf_ray = wp_remote_retrieve_header( is_wp_error( $head ) ? [] : $head, 'cf-ray' );
        if ( $cf_ray ) {
            $cf_cache = wp_remote_retrieve_header( is_wp_error( $head ) ? [] : $head, 'cf-cache-status' );
            $cf_results[] = [ 'type' => 'pass', 'msg' => "Cloudflare active: cf-ray $cf_ray" . ( $cf_cache ? " | Cache: $cf_cache" : '' ) ];
            $cf_results[] = [ 'type' => 'info', 'msg' => 'If any crawler UA test above failed, set up a WAF Skip rule in Cloudflare for social crawler user agents (see Cloudflare Setup panel below).' ];
        } else {
            $cf_results[] = [ 'type' => 'pass', 'msg' => 'No Cloudflare detected — WAF skip rule not required' ];
        }
        $sections[] = [ 'title' => 'Cloudflare', 'results' => $cf_results ];

        // Totals
        $pass = $warn = $fail = 0;
        foreach ( $sections as $s ) {
            foreach ( $s['results'] as $r ) {
                match ( $r['type'] ) { 'pass' => $pass++, 'warn' => $warn++, 'fail' => $fail++, default => null };
            }
        }

        return [
            'url'      => $url,
            'sections' => $sections,
            'totals'   => [ 'pass' => $pass, 'warn' => $warn, 'fail' => $fail ],
            'og_image' => $og_image,
            'img_kb'   => $img_kb,
            'img_w'    => $img_w,
            'img_h'    => $img_h,
        ];
    }

    /** Runs the five social crawler UA tests against a URL — used by the CF test button. */
    private static function social_test_crawlers( string $url ): array {
        $results = [];
        foreach ( self::SOCIAL_UAS as $label => $ua ) {
            $resp = wp_remote_get( $url, [ 'user-agent' => $ua, 'redirection' => 5, 'timeout' => 15 ] );
            if ( is_wp_error( $resp ) ) {
                $results[ $label ] = [ 'type' => 'fail', 'code' => 0, 'og' => false, 'msg' => $resp->get_error_message() ];
                continue;
            }
            $code = wp_remote_retrieve_response_code( $resp );
            $body = wp_remote_retrieve_body( $resp );
            $has_og = (bool) preg_match( '/property=["\']og:image["\']/', $body );
            $challenged = str_contains( $body, 'challenge-platform' );
            if ( $code === 200 && $has_og ) {
                $results[ $label ] = [ 'type' => 'pass', 'code' => $code, 'og' => true, 'msg' => $challenged ? 'HTTP 200, og:image present (Cloudflare challenge script detected — WAF skip rule is working)' : 'HTTP 200, og:image present' ];
            } elseif ( $code === 200 && ! $has_og ) {
                $results[ $label ] = [ 'type' => 'fail', 'code' => $code, 'og' => false, 'msg' => $challenged ? 'HTTP 200 but og:image absent — Bot Fight Mode is blocking this crawler. WAF skip rule needed.' : 'HTTP 200 but og:image absent in response' ];
            } else {
                $results[ $label ] = [ 'type' => 'fail', 'code' => $code, 'og' => false, 'msg' => "HTTP $code — crawler is being blocked" ];
            }
        }
        return $results;
    }

    /** Helper: extract og:meta property content. */
    private static function social_extract_property( string $html, string $prop ): string {
        if ( preg_match( '/property=["\']' . preg_quote( $prop, '/' ) . '["\'][^>]+content=["\']([^"\']+)["\']/', $html, $m ) ) {
            return trim( $m[1] );
        }
        if ( preg_match( '/content=["\']([^"\']+)["\'][^>]+property=["\']' . preg_quote( $prop, '/' ) . '["\']/', $html, $m ) ) {
            return trim( $m[1] );
        }
        return '';
    }

    /** Helper: extract meta name content. */
    private static function social_extract_name( string $html, string $name ): string {
        if ( preg_match( '/name=["\']' . preg_quote( $name, '/' ) . '["\'][^>]+content=["\']([^"\']+)["\']/', $html, $m ) ) {
            return trim( $m[1] );
        }
        if ( preg_match( '/content=["\']([^"\']+)["\'][^>]+name=["\']' . preg_quote( $name, '/' ) . '["\']/', $html, $m ) ) {
            return trim( $m[1] );
        }
        return '';
    }

    /**
     * Helper: recompress an attachment to under 300 KB using WP_Image_Editor.
     *
     * @return array|\WP_Error
     */
    private static function social_recompress_image( int $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return new \WP_Error( 'not_found', __( 'Attachment file not found on disk.', 'cloudscale-devtools' ) );
        }
        $ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png' ], true ) ) {
            return new \WP_Error( 'unsupported', __( 'Only JPEG and PNG images can be recompressed.', 'cloudscale-devtools' ) );
        }
        // Backup the original.
        $backup = $file_path . '.cs-backup';
        if ( ! copy( $file_path, $backup ) ) {
            return new \WP_Error( 'backup_failed', __( 'Could not create backup — aborting to protect original.', 'cloudscale-devtools' ) );
        }
        $editor = wp_get_image_editor( $file_path );
        if ( is_wp_error( $editor ) ) {
            unlink( $backup );
            return $editor;
        }
        // Resize if larger than 1200×630 (maintaining aspect ratio, no upscaling).
        $size = $editor->get_size();
        if ( $size['width'] > 1200 || $size['height'] > 630 ) {
            $editor->resize( 1200, 630, false );
        }
        $editor->set_quality( 80 );
        $saved = $editor->save( $file_path );
        if ( is_wp_error( $saved ) ) {
            copy( $backup, $file_path );
            unlink( $backup );
            return $saved;
        }
        $new_bytes = filesize( $file_path );
        // Still over 300 KB? Try quality 65.
        if ( $new_bytes > 307200 ) {
            $e2 = wp_get_image_editor( $backup );
            if ( ! is_wp_error( $e2 ) ) {
                $e2->set_quality( 65 );
                $e2->save( $file_path );
                $new_bytes = filesize( $file_path );
            }
        }
        $new_kb = round( $new_bytes / 1024, 1 );
        // Regenerate attachment metadata.
        $meta = wp_generate_attachment_metadata( $attachment_id, $file_path );
        wp_update_attachment_metadata( $attachment_id, $meta );
        return [
            'attachment_id' => $attachment_id,
            'new_size_kb'   => $new_kb,
            'backup'        => basename( $backup ),
            'under_limit'   => $new_bytes <= 307200,
            'message'       => $new_bytes <= 307200
                ? sprintf( __( 'Recompressed to %s KB — within the WhatsApp 300 KB threshold.', 'cloudscale-devtools' ), $new_kb )
                : sprintf( __( 'Recompressed to %s KB — still above threshold. Manual intervention needed.', 'cloudscale-devtools' ), $new_kb ),
        ];
    }
}

CloudScale_DevTools::init();
