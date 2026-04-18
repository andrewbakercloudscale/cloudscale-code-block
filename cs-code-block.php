<?php
/**
 * Plugin Name: CloudScale Cyber and Devtools
 * Plugin URI: https://your-wordpress-site.example.com
 * Description: Developer toolkit with syntax-highlighted code blocks, SQL query tool, code migrator, site monitor, and login security (passkeys, TOTP, email 2FA, hide login URL).
 * Version: 1.9.106
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
if ( ! defined( 'SAVEQUERIES' ) && get_option( 'csdt_devtools_perf_monitor_enabled', '1' ) !== '0' ) {
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

    const VERSION      = '1.9.106';
    const HLJS_VERSION = '11.11.1';
    const HLJS_CDN     = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/';
    const TOOLS_SLUG   = 'cloudscale-devtools';
    const MIGRATE_NONCE = 'csdt_devtools_code_migrate_action';
    const CUSTOM_404_OPTION  = 'csdt_devtools_custom_404';
    const SCHEME_404_OPTION  = 'csdt_devtools_404_scheme';
    const HISCORE_NS         = 'csdt-devtools/v1';
    const SCORE_NONCE_ACTION = 'csdt_devtools_score_post';

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
        self::maybe_migrate_smtp_prefix();
        self::maybe_migrate_usermeta_prefix();
        add_filter( 'xmlrpc_enabled', '__return_false' );

        // One-click security hardening — option-driven filters applied at every boot
        if ( get_option( 'csdt_devtools_disable_app_passwords', '0' ) === '1' ) {
            if ( get_option( 'csdt_test_accounts_enabled', '0' ) === '1' ) {
                // Test-account mode: block per-user (not site-wide) so test accounts still authenticate
                add_filter( 'wp_is_application_passwords_available_for_user', [ __CLASS__, 'filter_app_pw_for_user' ], 10, 2 );
            } else {
                add_filter( 'wp_is_application_passwords_available', '__return_false' );
            }
        }
        // Test-account cleanup cron + single-use hook (always when feature is enabled)
        if ( get_option( 'csdt_test_accounts_enabled', '0' ) === '1' ) {
            add_action( 'application_password_did_authenticate', [ __CLASS__, 'test_account_after_auth' ], 10, 2 );
        }
        if ( get_option( 'csdt_devtools_hide_wp_version', '0' ) === '1' ) {
            remove_action( 'wp_head', 'wp_generator' );
            add_filter( 'the_generator', '__return_empty_string' );
            // Strip ?ver= query strings from enqueued scripts/styles to prevent version fingerprinting
            add_filter( 'style_loader_src',  [ __CLASS__, 'strip_asset_ver' ], 9999 );
            add_filter( 'script_loader_src', [ __CLASS__, 'strip_asset_ver' ], 9999 );
        }

        add_action( 'init', [ __CLASS__, 'load_textdomain' ] );
        add_action( 'init', [ __CLASS__, 'register_block' ] );
        add_action( 'init', [ __CLASS__, 'register_shortcode' ] );
        add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_convert_script' ] );
        add_action( 'admin_menu', [ __CLASS__, 'add_tools_page' ] );
        add_action( 'wp_dashboard_setup', [ __CLASS__, 'register_dashboard_widget' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_init',       [ __CLASS__, 'redirect_legacy_slug' ] );
        add_action( 'init', [ __CLASS__, 'redirect_legacy_help_url' ], 1 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );

        // Migration AJAX
        add_action( 'wp_ajax_csdt_devtools_migrate_scan', [ __CLASS__, 'ajax_scan' ] );
        add_action( 'wp_ajax_csdt_devtools_migrate_preview', [ __CLASS__, 'ajax_preview' ] );
        add_action( 'wp_ajax_csdt_devtools_migrate_single', [ __CLASS__, 'ajax_migrate_single' ] );
        add_action( 'wp_ajax_csdt_devtools_migrate_all', [ __CLASS__, 'ajax_migrate_all' ] );

        // SQL AJAX
        add_action( 'wp_ajax_csdt_devtools_sql_run', [ __CLASS__, 'ajax_sql_run' ] );

        // Settings AJAX
        add_action( 'wp_ajax_csdt_devtools_save_theme_setting', [ __CLASS__, 'ajax_save_theme_setting' ] );

        // Login security AJAX
        add_action( 'wp_ajax_csdt_devtools_login_save',          [ __CLASS__, 'ajax_login_save' ] );
        add_action( 'wp_ajax_csdt_devtools_bf_log_fetch',        [ __CLASS__, 'ajax_bf_log_fetch' ] );
        add_action( 'wp_ajax_csdt_devtools_totp_setup_start',    [ __CLASS__, 'ajax_totp_setup_start' ] );
        add_action( 'wp_ajax_csdt_devtools_totp_setup_verify',   [ __CLASS__, 'ajax_totp_setup_verify' ] );
        add_action( 'wp_ajax_csdt_devtools_2fa_disable',         [ __CLASS__, 'ajax_2fa_disable' ] );
        add_action( 'wp_ajax_csdt_devtools_email_2fa_enable',    [ __CLASS__, 'ajax_email_2fa_enable' ] );
        add_action( 'admin_init',           [ __CLASS__, 'email_2fa_confirm_check' ] );
        add_action( 'after_password_reset', [ __CLASS__, 'on_password_reset' ], 10, 1 );
        add_action( 'profile_update',       [ __CLASS__, 'on_profile_update' ], 10, 2 );
        CSDT_DevTools_Passkey::register_hooks();

        // Thumbnails / Social Preview AJAX
        add_action( 'wp_ajax_csdt_devtools_social_check_url',   [ __CLASS__, 'ajax_social_check_url' ] );
        add_action( 'wp_ajax_csdt_devtools_social_scan_posts',  [ __CLASS__, 'ajax_social_scan_posts' ] );
        add_action( 'wp_ajax_csdt_devtools_social_scan_media',  [ __CLASS__, 'ajax_social_scan_media' ] );
        add_action( 'wp_ajax_csdt_devtools_social_fix_image',      [ __CLASS__, 'ajax_social_fix_image' ] );
        add_action( 'wp_ajax_csdt_devtools_social_generate_formats', [ __CLASS__, 'ajax_social_generate_formats' ] );
        add_action( 'wp_ajax_csdt_devtools_social_platform_save',    [ __CLASS__, 'ajax_social_platform_save' ] );
        add_action( 'wp_ajax_csdt_devtools_social_fix_all_batch',   [ __CLASS__, 'ajax_social_fix_all_batch' ] );
        add_action( 'wp_ajax_csdt_devtools_social_diagnose_formats', [ __CLASS__, 'ajax_social_diagnose_formats' ] );
        add_action( 'save_post_post',  [ __CLASS__, 'on_post_saved' ], 100, 3 );
        add_action( 'admin_notices',   [ __CLASS__, 'social_format_admin_notice' ] );
        // Serve platform-specific og:image based on crawler User-Agent.
        add_action( 'wp_head', [ __CLASS__, 'output_crawler_og_image' ], 1 );
        add_action( 'wp_ajax_csdt_devtools_social_cf_test',     [ __CLASS__, 'ajax_social_cf_test' ] );
        add_action( 'wp_ajax_csdt_devtools_cf_purge',           [ __CLASS__, 'ajax_cf_purge' ] );
        add_action( 'wp_ajax_csdt_devtools_cf_save',            [ __CLASS__, 'ajax_cf_save' ] );

        // SMTP AJAX
        add_action( 'wp_ajax_csdt_devtools_smtp_save',      [ __CLASS__, 'ajax_smtp_save' ] );
        add_action( 'wp_ajax_csdt_devtools_smtp_test',      [ __CLASS__, 'ajax_smtp_test' ] );
        add_action( 'wp_ajax_csdt_devtools_smtp_log_clear', [ __CLASS__, 'ajax_smtp_log_clear' ] );
        add_action( 'wp_ajax_csdt_devtools_smtp_log_fetch', [ __CLASS__, 'ajax_smtp_log_fetch' ] );

        add_action( 'wp_ajax_csdt_devtools_vuln_scan',          [ __CLASS__, 'ajax_vuln_scan' ] );
        add_action( 'wp_ajax_csdt_devtools_deep_scan',          [ __CLASS__, 'ajax_deep_scan' ] );
        add_action( 'wp_ajax_csdt_devtools_scan_status',        [ __CLASS__, 'ajax_scan_status' ] );
        add_action( 'wp_ajax_csdt_devtools_cancel_scan',        [ __CLASS__, 'ajax_cancel_scan' ] );
        add_action( 'wp_ajax_csdt_devtools_vuln_save_key',      [ __CLASS__, 'ajax_vuln_save_key' ] );
        add_action( 'wp_ajax_csdt_devtools_security_test_key',  [ __CLASS__, 'ajax_security_test_key' ] );
        add_action( 'wp_ajax_csdt_devtools_server_logs_status',     [ __CLASS__, 'ajax_server_logs_status' ] );
        add_action( 'wp_ajax_csdt_devtools_server_logs_fetch',      [ __CLASS__, 'ajax_server_logs_fetch' ] );
        add_action( 'wp_ajax_csdt_devtools_logs_setup_php',         [ __CLASS__, 'ajax_logs_setup_php' ] );
        add_action( 'wp_ajax_csdt_devtools_logs_custom_save',       [ __CLASS__, 'ajax_logs_custom_save' ] );
        add_action( 'wp_ajax_csdt_devtools_scan_history',       [ __CLASS__, 'ajax_scan_history' ] );
        add_action( 'wp_ajax_csdt_devtools_save_schedule',      [ __CLASS__, 'ajax_save_schedule' ] );
        add_action( 'wp_ajax_csdt_devtools_quick_fix',          [ __CLASS__, 'ajax_apply_quick_fix' ] );
        add_action( 'wp_ajax_csdt_devtools_csp_save',           [ __CLASS__, 'ajax_csp_save' ] );
        add_action( 'wp_ajax_csdt_devtools_csp_rollback',       [ __CLASS__, 'ajax_csp_rollback' ] );
        add_action( 'send_headers',                             [ __CLASS__, 'output_security_headers' ] );
        add_action( 'wp_ajax_csdt_test_account_create',          [ __CLASS__, 'ajax_create_test_account' ] );
        add_action( 'wp_ajax_csdt_test_account_revoke',          [ __CLASS__, 'ajax_revoke_test_account' ] );
        add_action( 'wp_ajax_csdt_test_account_settings_save',   [ __CLASS__, 'ajax_save_test_account_settings' ] );
        add_action( 'csdt_cleanup_test_accounts',                [ __CLASS__, 'cleanup_expired_test_accounts' ] );
        add_action( 'csdt_scheduled_scan',                      [ __CLASS__, 'run_scheduled_scan' ] );
        add_filter( 'cron_schedules',                           [ __CLASS__, 'add_cron_schedules' ] );

        add_action( 'csdt_devtools_run_vuln_scan', [ __CLASS__, 'cron_vuln_scan' ] );
        add_action( 'csdt_devtools_run_deep_scan', [ __CLASS__, 'cron_deep_scan' ] );

        // Email log — always active so every wp_mail() call is tracked site-wide,
        // regardless of whether our SMTP is enabled.
        add_filter( 'wp_mail',        [ __CLASS__, 'smtp_log_capture' ] );
        add_action( 'wp_mail_failed', [ __CLASS__, 'smtp_log_on_failure' ] );
        // Priority 5 so it runs before phpmailer_configure (priority 10) and sets action_function first.
        add_action( 'phpmailer_init', [ __CLASS__, 'smtp_log_set_callback' ], 5 );

        // SMTP — configure phpmailer and override from address only when fully configured.
        // Guard: if host is empty we skip configuration entirely so other plugins' emails
        // continue to work via PHP mail() rather than silently failing.
        if ( get_option( 'csdt_devtools_smtp_enabled', '0' ) === '1'
            && '' !== trim( (string) get_option( 'csdt_devtools_smtp_host', '' ) )
        ) {
            add_action( 'phpmailer_init', [ __CLASS__, 'phpmailer_configure' ] );
            if ( get_option( 'csdt_devtools_smtp_from_email', '' ) ) {
                add_filter( 'wp_mail_from',      [ __CLASS__, 'smtp_from_email' ] );
            }
            if ( get_option( 'csdt_devtools_smtp_from_name', '' ) ) {
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
        add_action( 'wp_ajax_csdt_devtools_save_404_settings',    [ __CLASS__, 'ajax_save_404_settings' ] );

        // Performance monitor — EXPLAIN endpoint.
        add_action( 'wp_ajax_csdt_devtools_perf_explain',       [ __CLASS__, 'ajax_perf_explain' ] );
        add_action( 'wp_ajax_csdt_devtools_perf_debug_toggle',  [ __CLASS__, 'ajax_perf_debug_toggle' ] );

        // Performance monitor — only register data-collection hooks when the monitor is enabled.
        // This prevents SAVEQUERIES-scale memory accumulation on every request when disabled.
        if ( get_option( 'csdt_devtools_perf_monitor_enabled', '1' ) !== '0' ) {
            add_filter( 'pre_http_request', [ __CLASS__, 'perf_http_before' ], 10, 3 );
            add_action( 'http_api_debug',   [ __CLASS__, 'perf_http_after' ],  10, 5 );

            // If the user enabled debug logging via the panel, activate PHP error logging
            // using ini_set — this works regardless of WP_DEBUG in wp-config.php and
            // survives Docker container rebuilds because the setting lives in the DB.
            if ( get_option( 'csdt_devtools_perf_debug_logging', false ) ) {
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
            // Inject JSON data at priority 15 — before wp_print_footer_scripts (priority 20) so
            // cs-perf-monitor.js reads window.csdtDevtoolsPerfData when its IIFE runs.
            add_action( 'admin_footer', [ __CLASS__, 'perf_inject_data' ],   15 );
            add_action( 'admin_footer', [ __CLASS__, 'perf_output_panel' ], 9999 );

            // Performance monitor — panel rendering (frontend, admin users only).
            add_action( 'wp_enqueue_scripts', [ __CLASS__, 'perf_frontend_enqueue' ] );
            add_action( 'wp_footer', [ __CLASS__, 'perf_inject_data' ],   15 );
            add_action( 'wp_footer', [ __CLASS__, 'perf_output_panel' ], 9999 );

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
        $pair_slug = get_option( 'csdt_devtools_code_theme_pair', 'atom-one' );
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
            'csdt-code-block-frontend',
            plugins_url( 'assets/cs-code-block.css', __FILE__ ),
            [ 'hljs-theme-dark', 'hljs-theme-light' ],
            self::VERSION
        );

        wp_register_script(
            'csdt-code-block-frontend',
            plugins_url( 'assets/cs-code-block.js', __FILE__ ),
            [ 'hljs-core' ],
            self::VERSION,
            true
        );

        wp_register_style(
            'csdt-code-block-editor',
            plugins_url( 'assets/cs-code-block-editor.css', __FILE__ ),
            [],
            self::VERSION
        );

        wp_register_script(
            'cloudscale-code-block-editor-script',
            plugins_url( 'blocks/code/editor.js', __FILE__ ),
            [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-hooks' ],
            self::VERSION,
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
            'csdt-code-block-convert',
            plugins_url( 'assets/cs-convert.js', __FILE__ ),
            [ 'wp-blocks', 'wp-data' ],
            self::VERSION,
            true
        );
        wp_add_inline_style( 'csdt-code-block-editor', self::get_convert_toast_css() );
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
        wp_enqueue_style( 'csdt-code-block-frontend' );
        wp_enqueue_script( 'hljs-core' );
        wp_enqueue_script( 'csdt-code-block-frontend' );

        $default_theme = get_option( 'csdt_devtools_code_default_theme', 'dark' );
        $pair_slug     = get_option( 'csdt_devtools_code_theme_pair', 'atom-one' );
        $registry      = self::get_theme_registry();
        $pair          = isset( $registry[ $pair_slug ] ) ? $registry[ $pair_slug ] : $registry['atom-one'];

        wp_localize_script( 'csdt-code-block-frontend', 'csdtDevtoolsCodeConfig', [
            'defaultTheme'  => $default_theme,
            'themePair'     => $pair_slug,
            'darkBg'        => $pair['dark_bg'],
            'darkToolbar'   => $pair['dark_toolbar'],
            'lightBg'       => $pair['light_bg'],
            'lightToolbar'  => $pair['light_toolbar'],
        ] );
    }

    /* ==================================================================
       3. SHORTCODE [csdt_devtools_code]
       ================================================================== */

    /**
     * Registers the [csdt_devtools_code] shortcode.
     *
     * @since  1.0.0
     * @return void
     */
    public static function register_shortcode() {
        add_shortcode( 'csdt_devtools_code', [ __CLASS__, 'render_shortcode' ] );
    }

    /**
     * Renders the [csdt_devtools_code] shortcode.
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
        ], $atts, 'csdt_devtools_code' );

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
        register_setting( 'csdt_devtools_code_settings', 'csdt_devtools_code_default_theme', [
            'type'              => 'string',
            'sanitize_callback' => function ( $val ) {
                return in_array( $val, [ 'dark', 'light' ] ) ? $val : 'dark';
            },
            'default' => 'dark',
        ] );

        $valid_themes = array_keys( self::get_theme_registry() );
        register_setting( 'csdt_devtools_code_settings', 'csdt_devtools_code_theme_pair', [
            'type'              => 'string',
            'sanitize_callback' => function ( $val ) use ( $valid_themes ) {
                return in_array( $val, $valid_themes, true ) ? $val : 'atom-one';
            },
            'default' => 'atom-one',
        ] );

        register_setting( 'csdt_devtools_code_settings', 'csdt_devtools_perf_monitor_enabled', [
            'type'              => 'string',
            'sanitize_callback' => function ( $val ) {
                return '0' === $val ? '0' : '1';
            },
            'default' => '1',
        ] );

        // Login security settings
        register_setting( 'csdt_devtools_login_settings', 'csdt_devtools_login_hide_enabled', [
            'type'              => 'string',
            'sanitize_callback' => function ( $v ) { return '1' === $v ? '1' : '0'; },
            'default'           => '0',
        ] );
        register_setting( 'csdt_devtools_login_settings', 'csdt_devtools_login_slug', [
            'type'              => 'string',
            'sanitize_callback' => function ( $v ) {
                $slug = sanitize_title( $v );
                // Disallow WP reserved slugs
                $reserved = [ 'wp-login', 'wp-admin', 'login', 'admin', 'dashboard' ];
                return in_array( $slug, $reserved, true ) ? '' : $slug;
            },
            'default' => '',
        ] );
        register_setting( 'csdt_devtools_login_settings', 'csdt_devtools_2fa_method', [
            'type'              => 'string',
            'sanitize_callback' => function ( $v ) {
                return in_array( $v, [ 'off', 'email', 'totp' ], true ) ? $v : 'off';
            },
            'default' => 'off',
        ] );
        register_setting( 'csdt_devtools_login_settings', 'csdt_devtools_2fa_force_admins', [
            'type'              => 'string',
            'sanitize_callback' => function ( $v ) { return '1' === $v ? '1' : '0'; },
            'default'           => '0',
        ] );
        register_setting( 'csdt_devtools_login_settings', 'csdt_devtools_2fa_grace_logins', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) {
                $n = (int) $v;
                return ( $n >= 0 && $n <= 10 ) ? (string) $n : '0';
            },
            'default' => '0',
        ] );
        register_setting( 'csdt_devtools_login_settings', 'csdt_devtools_session_duration', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) {
                $valid = [ 'default', '1', '7', '14', '30', '90', '365' ];
                return in_array( $v, $valid, true ) ? $v : 'default';
            },
            'default' => 'default',
        ] );
        register_setting( 'csdt_devtools_login_settings', 'csdt_devtools_brute_force_enabled', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) { return $v === '1' ? '1' : '0'; },
            'default'           => '1',
        ] );
        register_setting( 'csdt_devtools_login_settings', 'csdt_devtools_brute_force_attempts', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) {
                $n = (int) $v;
                return ( $n >= 1 && $n <= 100 ) ? (string) $n : '5';
            },
            'default' => '5',
        ] );
        register_setting( 'csdt_devtools_login_settings', 'csdt_devtools_brute_force_lockout', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) {
                $n = (int) $v;
                return ( $n >= 1 && $n <= 1440 ) ? (string) $n : '5';
            },
            'default' => '5',
        ] );

        // SMTP settings
        register_setting( 'csdt_devtools_smtp_settings', 'csdt_devtools_smtp_enabled', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) { return $v === '1' ? '1' : '0'; },
            'default'           => '0',
        ] );
        register_setting( 'csdt_devtools_smtp_settings', 'csdt_devtools_smtp_host', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );
        register_setting( 'csdt_devtools_smtp_settings', 'csdt_devtools_smtp_port', [
            'type'              => 'integer',
            'sanitize_callback' => static function ( $v ) {
                $v = absint( $v );
                return $v > 0 ? $v : 587;
            },
            'default'           => 587,
        ] );
        register_setting( 'csdt_devtools_smtp_settings', 'csdt_devtools_smtp_encryption', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) {
                return in_array( $v, [ 'tls', 'ssl', 'none' ], true ) ? $v : 'tls';
            },
            'default'           => 'tls',
        ] );
        register_setting( 'csdt_devtools_smtp_settings', 'csdt_devtools_smtp_auth', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) { return $v === '1' ? '1' : '0'; },
            'default'           => '1',
        ] );
        register_setting( 'csdt_devtools_smtp_settings', 'csdt_devtools_smtp_user', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );
        register_setting( 'csdt_devtools_smtp_settings', 'csdt_devtools_smtp_pass', [
            'type'              => 'string',
            'sanitize_callback' => static function ( $v ) { return $v; },
            'default'           => '',
        ] );
        register_setting( 'csdt_devtools_smtp_settings', 'csdt_devtools_smtp_from_email', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default'           => '',
        ] );
        register_setting( 'csdt_devtools_smtp_settings', 'csdt_devtools_smtp_from_name', [
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
            wp_redirect( home_url( '/wordpress-plugin-help/cloudscale-cyber-devtools-help/' ), 301 );
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
            'CloudScale Cyber and Devtools',
            '🌩️ Cyber and Devtools',
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
            'csdt-admin-tabs',
            plugins_url( 'assets/cs-admin-tabs.css', __FILE__ ),
            [],
            self::VERSION
        );
        // Explain modal description styling — scoped to .cs-explain-desc.
        wp_add_inline_style( 'csdt-admin-tabs', self::get_explain_modal_css() );

        // Migrate CSS + JS
        wp_enqueue_style(
            'csdt-code-migrate',
            plugins_url( 'assets/cs-code-migrate.css', __FILE__ ),
            [],
            self::VERSION
        );
        wp_enqueue_script(
            'csdt-code-migrate',
            plugins_url( 'assets/cs-code-migrate.js', __FILE__ ),
            [],
            self::VERSION,
            true
        );
        wp_localize_script( 'csdt-code-migrate', 'csdtDevtoolsMigrate', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( self::MIGRATE_NONCE ),
        ] );

        // Settings save JS
        wp_enqueue_script(
            'csdt-admin-settings',
            plugins_url( 'assets/cs-admin-settings.js', __FILE__ ),
            [],
            self::VERSION,
            true
        );
        wp_localize_script( 'csdt-admin-settings', 'csdtDevtoolsAdminSettings', [
            'nonce' => wp_create_nonce( 'csdt_devtools_code_settings_inline' ),
        ] );

        // SQL editor JS
        wp_enqueue_script(
            'csdt-sql-editor',
            plugins_url( 'assets/cs-sql-editor.js', __FILE__ ),
            [],
            self::VERSION,
            true
        );
        wp_localize_script( 'csdt-sql-editor', 'csdtDevtoolsSqlEditor', [
            'nonce' => wp_create_nonce( 'csdt_devtools_sql_nonce' ),
        ] );

        // Login security JS (only loaded on the login tab)
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'home'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $active_tab === 'login' ) {
            wp_enqueue_script(
                'csdt-qrcode',
                plugins_url( 'assets/qrcode.min.js', __FILE__ ),
                [],
                self::VERSION,
                true
            );
            wp_enqueue_script(
                'csdt-login',
                plugins_url( 'assets/cs-login.js', __FILE__ ),
                [ 'csdt-qrcode' ],
                self::VERSION,
                true
            );
            wp_localize_script( 'csdt-login', 'csdtDevtoolsLogin', [
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'csdt_devtools_login_nonce' ),
                'currentUser' => get_current_user_id(),
                'mailTabUrl'  => admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=mail' ),
            ] );
            wp_enqueue_script(
                'csdt-passkey',
                plugins_url( 'assets/cs-passkey.js', __FILE__ ),
                [ 'csdt-login' ],
                self::VERSION,
                true
            );
            wp_enqueue_script(
                'csdt-test-accounts',
                plugins_url( 'assets/cs-test-accounts.js', __FILE__ ),
                [ 'csdt-login' ],
                self::VERSION,
                true
            );
            wp_localize_script( 'csdt-test-accounts', 'csdtTestAccounts', [
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'csdt_devtools_login_nonce' ),
                'accounts' => self::get_active_test_accounts(),
            ] );
        }

        if ( $active_tab === 'mail' ) {
            wp_enqueue_script(
                'csdt-smtp',
                plugins_url( 'assets/cs-smtp.js', __FILE__ ),
                [],
                self::VERSION,
                true
            );
            wp_localize_script( 'csdt-smtp', 'csdtDevtoolsSmtp', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( self::SMTP_NONCE ),
                'testTo'  => wp_get_current_user()->user_email,
            ] );
        }

        if ( $active_tab === '404' ) {
            wp_enqueue_script(
                'csdt-404-admin',
                plugins_url( 'assets/cs-404-admin.js', __FILE__ ),
                [],
                self::VERSION,
                true
            );
            wp_localize_script( 'csdt-404-admin', 'csdtDevtools404', [
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( 'csdt_devtools_404_settings' ),
                'custom_404' => get_option( self::CUSTOM_404_OPTION, 1 ) ? 1 : 0,
                'scheme'     => get_option( self::SCHEME_404_OPTION, 'ocean' ),
                'previewUrl' => home_url( '/this-page-does-not-exist' ),
            ] );
        }

        if ( $active_tab === 'security' ) {
            wp_enqueue_script(
                'csdt-vuln-scan',
                plugins_url( 'assets/cs-vuln-scan.js', __FILE__ ),
                [],
                self::VERSION,
                true
            );
            $saved_model      = get_option( 'csdt_devtools_security_model', '_auto' );
            $saved_deep_model = get_option( 'csdt_devtools_deep_scan_model', '_auto_deep' );
            $saved_prompt     = get_option( 'csdt_devtools_security_prompt', '' );
            $saved_provider   = get_option( 'csdt_devtools_ai_provider', 'anthropic' );
            $api_key          = get_option( 'csdt_devtools_anthropic_key', '' );
            $gemini_key       = get_option( 'csdt_devtools_gemini_key', '' );
            $masked_key       = $api_key    ? '••••••••' . substr( $api_key,    -4 ) : '';
            $masked_gemini    = $gemini_key ? '••••••••' . substr( $gemini_key, -4 ) : '';
            $has_key          = $saved_provider === 'gemini' ? ! empty( $gemini_key ) : ! empty( $api_key );
            wp_localize_script( 'csdt-vuln-scan', 'csdtVulnScan', [
                'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( 'csdt_devtools_security_nonce' ),
                'hasKey'         => $has_key,
                'savedProvider'  => $saved_provider,
                'maskedKey'      => $masked_key,
                'maskedGemini'   => $masked_gemini,
                'savedModel'     => $saved_model,
                'savedDeepModel' => $saved_deep_model,
                'savedPrompt'    => $saved_prompt,
                'defaultPrompt'  => self::default_security_prompt(),
                'scanHistory'    => get_option( 'csdt_scan_history', [] ),
            ] );
        }

        if ( $active_tab === 'thumbnails' ) {
            $thumb_js = plugin_dir_path( __FILE__ ) . 'assets/cs-thumbnails.js';
            wp_enqueue_script(
                'csdt-thumbnails',
                plugins_url( 'assets/cs-thumbnails.js', __FILE__ ),
                [],
                self::VERSION,
                true
            );
            wp_localize_script( 'csdt-thumbnails', 'csdtDevtoolsThumbs', [
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'csdt_devtools_thumbnails' ),
                'siteUrl'  => home_url( '/' ),
            ] );
            // Thumbnails-tab-specific CSS — injected as inline style to avoid an
            // extra HTTP request and keep the render method free of <style> tags.
            wp_add_inline_style( 'csdt-admin-tabs', self::get_thumbnails_admin_css() );
        }

        if ( $active_tab === 'logs' ) {
            $logs_js = plugin_dir_path( __FILE__ ) . 'assets/cs-server-logs.js';
            wp_enqueue_script(
                'csdt-server-logs',
                plugins_url( 'assets/cs-server-logs.js', __FILE__ ),
                [],
                self::VERSION,
                true
            );
            wp_localize_script( 'csdt-server-logs', 'csdtServerLogs', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'csdt_devtools_server_logs' ),
                'sources' => self::get_log_sources(),
            ] );
        }

        // Email-verified modal countdown — only needed when the verification
        // redirect lands back on the login tab with ?email_2fa_activated=1.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $active_tab === 'login' && isset( $_GET['email_2fa_activated'] ) && '1' === $_GET['email_2fa_activated'] ) {
            wp_add_inline_script(
                'csdt-admin-settings',
                '(function(){' .
                'var modal=document.getElementById("cs-email-verified-modal");' .
                'var cd=document.getElementById("cs-modal-countdown");' .
                'var closeBtn=document.getElementById("cs-email-modal-close");' .
                'var n=6;' .
                'var t=setInterval(function(){n--;if(cd)cd.textContent=n;if(n<=0){clearInterval(t);if(modal)modal.style.display="none";}},1000);' .
                'function dismiss(){clearInterval(t);if(modal)modal.style.display="none";}' .
                'if(closeBtn)closeBtn.addEventListener("click",dismiss);' .
                'if(modal)modal.addEventListener("click",function(e){if(e.target===modal)dismiss();});' .
                '(function(){var u=new URL(location.href);u.searchParams.delete("email_2fa_activated");history.replaceState(null,"",u.toString());})();' .
                '})()'
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
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'home';
        $base_url   = admin_url( 'tools.php?page=' . self::TOOLS_SLUG );
        ?>
        <div class="wrap">
        <div id="cs-app">

            <!-- Banner -->
            <div id="cs-banner">
                <div>
                    <div id="cs-banner-title">⚡ CloudScale Cyber and Devtools</div>
                    <div id="cs-banner-sub"><?php esc_html_e( 'Code blocks, SQL tools, code migrator, site monitor &amp; login security', 'cloudscale-devtools' ); ?> &middot; v<?php echo esc_html( self::VERSION ); ?></div>
                </div>
                <div id="cs-banner-right">
                    <span class="cs-badge cs-badge-green">✅ <?php esc_html_e( 'Totally Free', 'cloudscale-devtools' ); ?></span>
                    <a href="https://your-wordpress-site.example.com" target="_blank" rel="noopener noreferrer" class="cs-badge cs-badge-orange" style="text-decoration:none">andrewbaker.ninja</a>
                    <a href="https://your-wordpress-site.example.com/wordpress-plugin-help/cloudscale-cyber-devtools-help/" target="_blank" rel="noopener noreferrer" class="cs-badge cs-badge-help" style="text-decoration:none">❓ <?php esc_html_e( 'Help', 'cloudscale-devtools' ); ?></a>
                </div>
            </div>

            <!-- Tab bar -->
            <div id="cs-tab-bar">
                <a href="<?php echo esc_url( $base_url . '&tab=home' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'home' ? 'active' : ''; ?>">
                    🏠 <?php esc_html_e( 'Home', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=login' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'login' ? 'active' : ''; ?>">
                    🔐 <?php esc_html_e( 'Login Security', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=security' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                    🛡️ <?php esc_html_e( 'Security Scan', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=migrate' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'migrate' ? 'active' : ''; ?>">
                    🔄 <?php esc_html_e( 'Code Migrator', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=mail' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'mail' ? 'active' : ''; ?>">
                    📧 <?php esc_html_e( 'Mail / SMTP', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=thumbnails' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'thumbnails' ? 'active' : ''; ?>">
                    🖼️ <?php esc_html_e( 'Thumbnails', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=sql' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'sql' ? 'active' : ''; ?>">
                    🗄️ <?php esc_html_e( 'SQL Command', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=404' ); ?>"
                   class="cs-tab <?php echo $active_tab === '404' ? 'active' : ''; ?>">
                    🎮 <?php esc_html_e( '404 Games', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=logs' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'logs' ? 'active' : ''; ?>">
                    📋 <?php esc_html_e( 'Server Logs', 'cloudscale-devtools' ); ?>
                </a>
            </div>

            <!-- Copy All action bar -->
            <div id="cs-tab-actions">
                <button id="cs-copy-all-btn" class="cs-copy-all-btn" title="<?php esc_attr_e( 'Copy all content from this tab to clipboard', 'cloudscale-devtools' ); ?>">
                    &#128203; <?php esc_html_e( 'Copy All', 'cloudscale-devtools' ); ?>
                </button>
            </div>

            <?php if ( $active_tab === 'home' ) : ?>
                <div class="cs-tab-content active">
                    <?php self::render_home_panel(); ?>
                </div>
            <?php elseif ( $active_tab === 'migrate' ) : ?>
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
            <?php elseif ( $active_tab === 'security' ) : ?>
                <div class="cs-tab-content active">
                    <?php self::render_security_panel(); ?>
                </div>
            <?php elseif ( $active_tab === 'logs' ) : ?>
                <div class="cs-tab-content active">
                    <?php self::render_server_logs_panel(); ?>
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
    /**
     * Allowed HTML tags/attrs for item descriptions that contain links.
     *
     * @var array<string,array<string,bool>>
     */
    private static array $explain_kses = [
        'a'      => [ 'href' => true, 'target' => true, 'rel' => true ],
        'strong' => [],
        'em'     => [],
        'code'   => [],
        'br'     => [],
        'ul'     => [],
        'ol'     => [],
        'li'     => [],
        'p'      => [],
        'h4'     => [],
    ];

    /**
     * Renders an "Explain…" button + inline modal.
     *
     * Each item in $items may have:
     *   'name' => string   — section heading
     *   'rec'  => string   — badge label (Recommended | Note | Optional)
     *   'desc' => string   — plain-text description (escaped with esc_html)
     *   'html' => string   — HTML description rendered via wp_kses (overrides 'desc')
     *
     * @param string $id    Unique slug used to build element IDs.
     * @param string $title Modal title.
     * @param array  $items Array of item arrays.
     */
    private static function render_explain_btn( string $id, string $title, array $items, string $intro = '' ): void {
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
                    <?php if ( $intro ) : ?>
                    <div style="background:#f0f6ff;border-left:4px solid #2271b1;border-radius:0 6px 6px 0;padding:12px 16px;margin-bottom:14px;font-size:13px;color:#1a1a1a;line-height:1.6">
                        <?php echo wp_kses( $intro, self::$explain_kses ); ?>
                    </div>
                    <?php endif; ?>
                    <?php foreach ( $items as $item ) :
                        $rec    = $item['rec'];
                        $is_rec = str_contains( $rec, 'Recommended' );
                        $is_opt = str_contains( $rec, 'Optional' );
                        $bg     = $is_rec ? '#edfaef' : ( $is_opt ? '#f6f7f7' : '#f0f6fc' );
                        $col    = $is_rec ? '#1a7a34' : ( $is_opt ? '#50575e' : '#1a4a7a' );
                        $bdr    = $is_rec ? '#1a7a34' : ( $is_opt ? '#c3c4c7' : '#2271b1' );
                    ?>
                    <div style="border:1px solid #e0e0e0;border-radius:6px;padding:14px 16px;margin-bottom:10px">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px">
                            <strong style="font-size:13px;color:#111;line-height:1.3"><?php echo esc_html( $item['name'] ); ?></strong>
                            <span style="flex-shrink:0;display:inline-block;background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $col ); ?>;border:1px solid <?php echo esc_attr( $bdr ); ?>;border-radius:4px;font-size:10px;font-weight:700;padding:2px 8px;white-space:nowrap;letter-spacing:0.02em;text-transform:uppercase"><?php echo esc_html( $rec ); ?></span>
                        </div>
                        <div class="cs-explain-desc">
                            <?php
                            if ( ! empty( $item['html'] ) ) {
                                echo wp_kses( $item['html'], self::$explain_kses ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised via wp_kses with restricted allowlist
                            } else {
                                echo esc_html( $item['desc'] ?? '' );
                            }
                            ?>
                        </div>
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
        $theme       = get_option( 'csdt_devtools_code_default_theme', 'dark' );
        $pair_slug   = get_option( 'csdt_devtools_code_theme_pair', 'atom-one' );
        $perf_on     = get_option( 'csdt_devtools_perf_monitor_enabled', '1' ) !== '0';
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
                        <select id="cs-settings-pair" name="csdt_devtools_code_theme_pair" class="cs-input">
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
                        <select id="cs-settings-theme" name="csdt_devtools_code_default_theme" class="cs-input">
                            <option value="dark" <?php selected( $theme, 'dark' ); ?>><?php esc_html_e( 'Dark', 'cloudscale-devtools' ); ?></option>
                            <option value="light" <?php selected( $theme, 'light' ); ?>><?php esc_html_e( 'Light', 'cloudscale-devtools' ); ?></option>
                        </select>
                        <span class="cs-hint"><?php esc_html_e( 'Visitors can still toggle per block.', 'cloudscale-devtools' ); ?></span>
                    </div>
                </div>
                <div class="cs-field" style="margin-top:14px">
                    <label class="cs-label"><?php esc_html_e( 'CS Monitor panel:', 'cloudscale-devtools' ); ?></label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" id="cs-settings-perf-enabled" name="csdt_devtools_perf_monitor_enabled" value="1" <?php checked( $perf_on ); ?>>
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
    /* ==================================================================
       Server Logs tab
    ================================================================== */

    private static function get_log_sources(): array {
        $sources = [];

        // PHP error log — prefer path set by our mu-plugin; fall back to php.ini if it's a real file
        $php_log = get_option( 'csdt_php_error_log_path', '' );
        if ( ! $php_log ) {
            $ini_log = ini_get( 'error_log' );
            if ( $ini_log && is_file( $ini_log ) ) {
                $php_log = $ini_log;
            }
        }
        if ( $php_log ) {
            $sources['php_error'] = [ 'label' => 'PHP Error Log', 'path' => $php_log ];
        }

        // WordPress debug.log — prefer relocated path set by quick-fix mu-plugin
        $relocated    = get_option( 'csdt_debug_log_path', '' );
        $wp_debug_log = $relocated ?: WP_CONTENT_DIR . '/debug.log';
        $sources['wp_debug'] = [ 'label' => 'WordPress Debug Log', 'path' => $wp_debug_log ];

        // Web server error log — check common paths that may be readable by the web user
        $web_error_candidates = [
            '/var/log/apache2/error.log',
            '/var/log/httpd/error_log',
            '/var/log/nginx/error.log',
            '/var/log/apache2/error_log',
        ];
        foreach ( $web_error_candidates as $path ) {
            if ( is_readable( $path ) ) {
                $sources['web_error'] = [ 'label' => 'Web Server Error Log', 'path' => $path ];
                break;
            }
        }

        // Web server access log
        $web_access_candidates = [
            '/var/log/apache2/access.log',
            '/var/log/httpd/access_log',
            '/var/log/nginx/access.log',
        ];
        foreach ( $web_access_candidates as $path ) {
            if ( is_readable( $path ) ) {
                $sources['web_access'] = [ 'label' => 'Web Server Access Log', 'path' => $path ];
                break;
            }
        }

        // WP Cron log
        $cron_log = WP_CONTENT_DIR . '/cron.log';
        if ( file_exists( $cron_log ) ) {
            $sources['wp_cron'] = [ 'label' => 'WP Cron Log', 'path' => $cron_log ];
        }

        // Admin-configured custom paths
        $custom = get_option( 'csdt_custom_log_paths', [] );
        if ( is_array( $custom ) ) {
            foreach ( $custom as $i => $cp ) {
                if ( ! empty( $cp['label'] ) && ! empty( $cp['path'] ) ) {
                    $sources[ 'custom_' . $i ] = [
                        'label'  => sanitize_text_field( $cp['label'] ),
                        'path'   => $cp['path'],
                        'custom' => true,
                    ];
                }
            }
        }

        return $sources;
    }

    public static function ajax_logs_setup_php(): void {
        check_ajax_referer( 'csdt_devtools_server_logs', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $log_path = WP_CONTENT_DIR . '/php-error.log';
        $mu_dir   = WP_CONTENT_DIR . '/mu-plugins';
        if ( ! is_dir( $mu_dir ) ) {
            wp_mkdir_p( $mu_dir );
        }

        $mu_file = $mu_dir . '/csdt-php-error-log.php';
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        $written = file_put_contents(
            $mu_file,
            '<?php' . "\n" .
            '// Redirects PHP error_log to a readable file — managed by CloudScale DevTools.' . "\n" .
            '// phpcs:ignore WordPress.PHP.IniSet.Risky' . "\n" .
            '@ini_set( \'error_log\', ' . var_export( $log_path, true ) . ' );' . "\n"
        );

        if ( false === $written ) {
            wp_send_json_error( __( 'Could not write mu-plugin. Check that wp-content/mu-plugins is writable.', 'cloudscale-devtools' ) );
            return;
        }

        update_option( 'csdt_php_error_log_path', $log_path, false );
        wp_send_json_success( [ 'path' => $log_path, 'sources' => self::get_log_sources() ] );
    }

    public static function ajax_logs_custom_save(): void {
        check_ajax_referer( 'csdt_devtools_server_logs', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $raw   = isset( $_POST['paths'] ) ? wp_unslash( $_POST['paths'] ) : '[]'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $paths = json_decode( $raw, true );
        if ( ! is_array( $paths ) ) {
            $paths = [];
        }

        $clean = [];
        foreach ( $paths as $p ) {
            $label = sanitize_text_field( $p['label'] ?? '' );
            $path  = sanitize_text_field( $p['path']  ?? '' );
            if ( $label !== '' && $path !== '' ) {
                $clean[] = [ 'label' => $label, 'path' => $path ];
            }
        }

        update_option( 'csdt_custom_log_paths', $clean );
        wp_send_json_success( [ 'sources' => self::get_log_sources() ] );
    }

    private static function render_server_logs_panel(): void {
        $sources        = self::get_log_sources();
        $php_configured = ! empty( get_option( 'csdt_php_error_log_path', '' ) );
        $custom_paths   = get_option( 'csdt_custom_log_paths', [] );
        ?>
        <div class="cs-panel" id="cs-panel-logs">
            <div class="cs-section-header" style="background:linear-gradient(90deg,#1a2035 0%,#1e2d40 100%);border-left:3px solid #4a9eff;">
                <span>📋 <?php esc_html_e( 'Server Logs', 'cloudscale-devtools' ); ?></span>
                <span class="cs-header-hint"><?php esc_html_e( 'Read-only view of PHP error log, WordPress debug log, and web server logs', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'server-logs', 'Server Logs', [
                    [ 'name' => 'Log Sources',       'rec' => 'Info',        'html' => 'The panel automatically detects common log file locations for your server stack — Apache, Nginx, PHP-FPM, WordPress debug log, and any custom paths you add. Each source button shows a colour-coded status: <strong>green</strong> (readable), <strong>amber</strong> (empty), <strong>red</strong> (not found or permission denied).' ],
                    [ 'name' => 'PHP Error Log',     'rec' => 'Recommended', 'html' => 'If PHP is logging to <code>/dev/stderr</code> (the default in many Docker/container setups), errors cannot be read here. Click <strong>Enable</strong> to install a mu-plugin that redirects PHP errors to <code>wp-content/php-error.log</code>. The mu-plugin runs on every request before other plugins load.' ],
                    [ 'name' => 'Filters',           'rec' => 'Info',        'html' => '<ul><li><strong>Search</strong> — live text filter across all visible lines</li><li><strong>Level</strong> — show only lines at or above a severity (emergency → debug)</li><li><strong>Lines</strong> — how many tail lines to fetch from the server (100–2000)</li></ul>Colour coding: red = error/critical, amber = warning, blue = notice/info, grey = debug.' ],
                    [ 'name' => 'Auto-refresh',      'rec' => 'Optional',    'html' => 'Enable <em>Tail mode</em> to poll the selected log every 30 seconds automatically. Useful when watching a running process or debugging a live issue without leaving the page.' ],
                    [ 'name' => 'Custom Log Paths',  'rec' => 'Optional',    'html' => 'Add any absolute file path your web server user can read. Common extras: application logs (Laravel <code>storage/logs/laravel.log</code>), cron output files, or a custom PHP-FPM pool log. Labels are free-text — choose something descriptive. Custom paths are saved as a WordPress option and survive plugin updates.' ],
                    [ 'name' => 'Permissions',       'rec' => 'Info',        'html' => 'System logs (e.g. <code>/var/log/syslog</code>, <code>/var/log/auth.log</code>) are typically owned by <code>root</code> and not readable by <code>www-data</code>. This is intentional OS hardening — the plugin shows a clear "permission denied" message rather than an error. To expose a system log, add your web server user to the <code>adm</code> group or use a log-shipping tool.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">

                <?php if ( ! $php_configured ) : ?>
                <div id="cs-logs-php-setup" style="display:flex;align-items:flex-start;gap:12px;padding:12px 14px;margin-bottom:16px;background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;">
                    <div style="flex:1;font-size:13px;color:#92400e;line-height:1.5;">
                        <strong><?php esc_html_e( 'PHP Error Log not writing to a file', 'cloudscale-devtools' ); ?></strong><br>
                        <?php esc_html_e( 'PHP is currently logging to a system stream (e.g. /dev/stderr) that cannot be read here. Click Enable to install a mu-plugin that redirects PHP errors to wp-content/php-error.log.', 'cloudscale-devtools' ); ?>
                    </div>
                    <button type="button" class="cs-btn-primary cs-btn-sm" id="cs-logs-php-setup-btn" style="flex-shrink:0;white-space:nowrap;">
                        ⚡ <?php esc_html_e( 'Enable', 'cloudscale-devtools' ); ?>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Source picker -->
                <div id="cs-logs-sources" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                    <?php foreach ( $sources as $key => $src ) : ?>
                        <button class="cs-btn-secondary cs-log-src-btn" data-source="<?php echo esc_attr( $key ); ?>">
                            <?php echo esc_html( $src['label'] ); ?>
                        </button>
                    <?php endforeach; ?>
                    <?php if ( empty( $sources ) ) : ?>
                        <span style="color:#888;font-size:13px;"><?php esc_html_e( 'No log paths detected on this server.', 'cloudscale-devtools' ); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Toolbar -->
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:10px;">
                    <input type="text" id="cs-logs-search" placeholder="<?php esc_attr_e( 'Search…', 'cloudscale-devtools' ); ?>"
                           style="flex:1;min-width:160px;max-width:320px;" class="cs-text-input">
                    <select id="cs-logs-level" class="cs-sec-select" style="width:auto;">
                        <option value=""><?php esc_html_e( 'All levels', 'cloudscale-devtools' ); ?></option>
                        <option value="error"><?php esc_html_e( 'Error+', 'cloudscale-devtools' ); ?></option>
                        <option value="warn"><?php esc_html_e( 'Warning only', 'cloudscale-devtools' ); ?></option>
                        <option value="notice"><?php esc_html_e( 'Notice only', 'cloudscale-devtools' ); ?></option>
                    </select>
                    <select id="cs-logs-lines" class="cs-sec-select" style="width:auto;">
                        <option value="100">100 lines</option>
                        <option value="300" selected>300 lines</option>
                        <option value="500">500 lines</option>
                        <option value="1000">1000 lines</option>
                    </select>
                    <button id="cs-logs-refresh" class="cs-btn-secondary">🔄 <?php esc_html_e( 'Refresh', 'cloudscale-devtools' ); ?></button>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#9da5b4;cursor:pointer;">
                        <input type="checkbox" id="cs-logs-tail" style="cursor:pointer;">
                        <?php esc_html_e( 'Auto-refresh (30s)', 'cloudscale-devtools' ); ?>
                    </label>
                    <span id="cs-logs-status" style="font-size:12px;color:#888;margin-left:auto;"></span>
                </div>

                <!-- Log viewer -->
                <div id="cs-logs-viewer" style="
                    background:#0d1117;
                    border:1px solid rgba(255,255,255,0.08);
                    border-radius:6px;
                    padding:12px;
                    height:520px;
                    overflow-y:auto;
                    font-family:'SF Mono','Fira Code',monospace;
                    font-size:12px;
                    line-height:1.6;
                    color:#c9d1d9;
                ">
                    <div class="cs-logs-placeholder" style="color:#555;padding:20px;text-align:center;">
                        <?php esc_html_e( 'Select a log source above to view entries.', 'cloudscale-devtools' ); ?>
                    </div>
                </div>

                <!-- Custom log paths -->
                <div style="margin-top:20px;border-top:1px solid #e8edf5;padding-top:16px;">
                    <div style="font-weight:600;font-size:13px;color:#1d2327;margin-bottom:8px;">
                        <?php esc_html_e( 'Custom log paths', 'cloudscale-devtools' ); ?>
                    </div>
                    <p style="font-size:12px;color:#6b7280;margin:0 0 10px;">
                        <?php esc_html_e( 'Add any log file your web server user can read (e.g. a custom nginx log, a container log written to a shared volume, or any application log file).', 'cloudscale-devtools' ); ?>
                    </p>
                    <div id="cs-logs-custom-list">
                        <?php foreach ( (array) $custom_paths as $i => $cp ) : ?>
                        <div class="cs-logs-custom-row" style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
                            <input type="text" class="cs-text-input cs-logs-custom-label" placeholder="<?php esc_attr_e( 'Label', 'cloudscale-devtools' ); ?>" value="<?php echo esc_attr( $cp['label'] ?? '' ); ?>" style="width:140px;flex-shrink:0;">
                            <input type="text" class="cs-text-input cs-logs-custom-path" placeholder="<?php esc_attr_e( '/path/to/file.log', 'cloudscale-devtools' ); ?>" value="<?php echo esc_attr( $cp['path'] ?? '' ); ?>" style="flex:1;min-width:0;">
                            <button type="button" class="cs-btn-secondary cs-btn-sm cs-logs-custom-remove" style="color:#dc2626;border-color:#fca5a5;flex-shrink:0;">✕</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex;gap:8px;margin-top:8px;">
                        <button type="button" class="cs-btn-secondary cs-btn-sm" id="cs-logs-custom-add">+ <?php esc_html_e( 'Add path', 'cloudscale-devtools' ); ?></button>
                        <button type="button" class="cs-btn-primary cs-btn-sm" id="cs-logs-custom-save">💾 <?php esc_html_e( 'Save', 'cloudscale-devtools' ); ?></button>
                        <span id="cs-logs-custom-saved" class="cs-settings-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                    </div>
                </div>

            </div>
        </div>
        <style>
        .cs-log-src-btn.status-ok            { border-color:#22c55e; color:#22c55e; }
        .cs-log-src-btn.status-not-found     { border-color:#555; color:#555; }
        .cs-log-src-btn.status-permission-denied { border-color:#f59e0b; color:#f59e0b; }
        .cs-log-src-btn.status-empty         { border-color:#6366f1; color:#6366f1; }
        .cs-log-src-btn.active               { background:rgba(74,158,255,0.15); border-color:#4a9eff; color:#4a9eff; }
        .cs-log-line                         { padding:1px 0; white-space:pre-wrap; word-break:break-all; border-bottom:1px solid rgba(255,255,255,0.03); }
        .cs-log-line.level-emerg,
        .cs-log-line.level-alert,
        .cs-log-line.level-crit              { color:#ff6b6b; }
        .cs-log-line.level-error             { color:#f87171; }
        .cs-log-line.level-warn              { color:#fbbf24; }
        .cs-log-line.level-notice            { color:#a78bfa; }
        .cs-log-line.level-info              { color:#60a5fa; }
        .cs-log-line.level-debug             { color:#6b7280; }
        .cs-log-line.level-default           { color:#c9d1d9; }
        </style>
        <?php
    }

    public static function ajax_server_logs_status(): void {
        check_ajax_referer( 'csdt_devtools_server_logs', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $sources  = self::get_log_sources();
        $statuses = [];
        foreach ( $sources as $key => $src ) {
            $path = $src['path'];
            if ( ! file_exists( $path ) ) {
                $statuses[ $key ] = [ 'status' => 'not_found' ];
            } elseif ( ! is_readable( $path ) ) {
                $statuses[ $key ] = [ 'status' => 'permission_denied' ];
            } elseif ( filesize( $path ) === 0 ) {
                $statuses[ $key ] = [ 'status' => 'empty' ];
            } else {
                $statuses[ $key ] = [ 'status' => 'ok' ];
            }
        }
        wp_send_json_success( $statuses );
    }

    public static function ajax_server_logs_fetch(): void {
        check_ajax_referer( 'csdt_devtools_server_logs', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $source_key = isset( $_POST['source'] ) ? sanitize_key( $_POST['source'] ) : '';
        $lines_req  = min( 1000, max( 50, (int) ( $_POST['lines'] ?? 300 ) ) );
        $sources    = self::get_log_sources();

        if ( ! isset( $sources[ $source_key ] ) ) {
            wp_send_json_error( [ 'message' => 'Unknown source.' ] );
            return;
        }

        $path = $sources[ $source_key ]['path'];

        if ( ! file_exists( $path ) ) {
            wp_send_json_success( [ 'status' => 'not_found', 'lines' => [], 'count' => 0, 'path' => $path ] );
            return;
        }
        if ( ! is_readable( $path ) ) {
            wp_send_json_success( [ 'status' => 'permission_denied', 'lines' => [], 'count' => 0, 'path' => $path ] );
            return;
        }

        // Read last N lines efficiently without loading the entire file
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        $handle = @fopen( $path, 'rb' );
        if ( ! $handle ) {
            wp_send_json_success( [ 'status' => 'error', 'lines' => [], 'count' => 0, 'path' => $path ] );
            return;
        }

        fseek( $handle, 0, SEEK_END );
        $file_size  = ftell( $handle );
        $chunk_size = 65536; // 64 KB chunks, read from end
        $buffer     = '';
        $pos        = $file_size;
        $line_count = 0;

        while ( $pos > 0 && $line_count < $lines_req + 1 ) {
            $read = min( $chunk_size, $pos );
            $pos -= $read;
            fseek( $handle, $pos );
            $buffer     = fread( $handle, $read ) . $buffer;
            $line_count = substr_count( $buffer, "\n" );
        }
        fclose( $handle );

        $all_lines = explode( "\n", $buffer );
        // Remove trailing empty line
        if ( end( $all_lines ) === '' ) { array_pop( $all_lines ); }
        $lines = array_slice( $all_lines, -$lines_req );

        wp_send_json_success( [
            'status' => count( $lines ) > 0 ? 'ok' : 'empty',
            'lines'  => $lines,
            'count'  => count( $lines ),
            'path'   => $path,
        ] );
    }

    private static function render_migrate_panel() {
        ?>
        <div class="cs-panel" id="cs-panel-migrator">
            <div class="cs-section-header">
                <span>🔄 CODE BLOCK MIGRATOR</span>
                <?php self::render_explain_btn( 'migrator', 'Code Block Migrator', [
                    [ 'name' => 'Scan Posts',    'rec' => 'Informational', 'html' => 'Scans all posts and pages for legacy WordPress <code>wp:code</code> and <code>wp:preformatted</code> blocks that can be upgraded to CloudScale Code Blocks with full syntax highlighting.' ],
                    [ 'name' => 'Preview',       'rec' => 'Recommended',   'html' => 'Shows a side-by-side before/after diff for each post <em>before</em> committing any changes, so you can review exactly what will be converted.' ],
                    [ 'name' => 'Migrate',       'rec' => 'Optional',      'html' => 'Converts detected legacy blocks to CloudScale format. Each post is saved with the converted markup.<br><br><strong>Take a backup first</strong> — this cannot be undone without one.' ],
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
                    [ 'name' => 'Read-only',     'rec' => 'Informational', 'html' => 'Only <code>SELECT</code>, <code>SHOW</code>, <code>DESCRIBE</code>, and <code>EXPLAIN</code> queries are permitted. Write operations (<code>INSERT</code>, <code>UPDATE</code>, <code>DELETE</code>, <code>DROP</code>, <code>ALTER</code>, <code>TRUNCATE</code>) are blocked to prevent accidental data loss.' ],
                    [ 'name' => 'Table Prefix',  'rec' => 'Informational', 'html' => 'Your WordPress table prefix is shown in the header. Use it in your queries, e.g. <code>SELECT * FROM wp_posts LIMIT 10</code> or <code>SELECT * FROM wp_options WHERE option_name = \'siteurl\'</code>.' ],
                    [ 'name' => 'Quick Queries', 'rec' => 'Recommended',   'html' => 'Use the preset queries below for common diagnostics without needing to write SQL from scratch. Press <code>Enter</code> or <code>Ctrl+Enter</code> to run a query, <code>Shift+Enter</code> to insert a newline.' ],
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
                    [ 'name' => 'Health & Diagnostics', 'rec' => 'Recommended',   'html' => 'MySQL version, table sizes, connection limits, and WordPress table row counts at a glance. Good first check when diagnosing slow sites.' ],
                    [ 'name' => 'Content Summary',      'rec' => 'Informational', 'html' => 'Counts posts by type and status, revisions, auto-drafts, spam comments, and users for a quick content audit. Useful before a site migration.' ],
                    [ 'name' => 'Cleanup Candidates',   'rec' => 'Optional',      'html' => 'Identifies orphaned <code>postmeta</code> rows, expired transients, and bloated <code>wp_options</code> autoloaded rows that may be slowing down your database.' ],
                    [ 'name' => 'Security Checks',      'rec' => 'Optional',      'html' => 'Looks for <code>http://</code> (non-HTTPS) URLs or stale IP addresses in <code>wp_options</code> and post GUIDs — common indicators of old content or unfinished HTTP→HTTPS migrations.' ],
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
                    <button type="button" class="cs-quick-btn cs-sql-quick" data-sql="SELECT ID, post_title, post_type FROM <?php echo esc_attr( $prefix ); ?>posts WHERE post_status = 'publish' AND ID NOT IN (SELECT post_id FROM <?php echo esc_attr( $prefix ); ?>postmeta WHERE meta_key = '_csdt_devtools_seo_desc' AND meta_value != '') ORDER BY post_date DESC LIMIT 50;">
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
     * @since  1.9.4
     * @return void
     */
    private static function render_login_panel(): void {
        $hide_on      = get_option( 'csdt_devtools_login_hide_enabled', '0' ) === '1';
        $slug         = get_option( 'csdt_devtools_login_slug', '' );
        $method       = get_option( 'csdt_devtools_2fa_method', 'off' );
        $force        = get_option( 'csdt_devtools_2fa_force_admins', '0' ) === '1';
        $user_id      = get_current_user_id();
        $totp_active  = get_user_meta( $user_id, 'csdt_devtools_totp_enabled', true ) === '1';
        $email_active = get_user_meta( $user_id, 'csdt_devtools_2fa_email_enabled', true ) === '1';
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
                    [ 'name' => 'Enable Hide Login',  'rec' => 'Recommended', 'html' => 'Moves your login page to a secret URL. Direct requests to <code>/wp-login.php</code> return a <code>404</code>, stopping bots and credential-stuffing scripts from even finding the login form.' ],
                    [ 'name' => 'Custom Login Path',  'rec' => 'Recommended', 'html' => 'The URL slug that serves your login page, e.g. <code>/my-secret-login</code>. Use letters, numbers, and hyphens only.<br><br><strong>Save the full login URL somewhere safe</strong> — you will need it to log in after enabling this feature.' ],
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
        $session_duration = get_option( 'csdt_devtools_session_duration', 'default' );
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
                    [ 'name' => 'Session Lifetime',     'rec' => 'Recommended', 'html' => 'Sets how long the WordPress auth cookie stays valid before the user must log in again.<br><br><ul><li><strong>1–7 days</strong> — higher-security environments (banking, staging, admin-heavy sites)</li><li><strong>30–90 days</strong> — convenience for trusted personal devices</li><li><strong>WordPress default</strong> — 2 days (48 hours), or 14 days when "Remember Me" is checked at login</li></ul>' ],
                    [ 'name' => 'Remember Me & timing', 'rec' => 'Note',        'html' => 'When a custom duration is set, the <strong>Remember Me</strong> checkbox is overridden — all new sessions get the same lifetime regardless.<br><br>Changing this setting only affects <em>new</em> logins. Users already logged in keep their current session cookie until it expires or they log out.' ],
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
        $bf_enabled  = get_option( 'csdt_devtools_brute_force_enabled', '1' );
        $bf_attempts = get_option( 'csdt_devtools_brute_force_attempts', '5' );
        $bf_lockout  = get_option( 'csdt_devtools_brute_force_lockout', '5' );
        ?>
        <div class="cs-panel" id="cs-panel-brute-force">
            <div class="cs-section-header cs-section-header-red">
                <span>🔒 BRUTE-FORCE PROTECTION</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Temporarily lock accounts after repeated failed logins', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'brute-force', 'Brute-Force Protection', [
                    [ 'name' => 'How it works',   'rec' => 'Info',        'html' => 'After <em>N</em> consecutive failed login attempts for the same username, the account is locked for the configured duration. The lock is <strong>per-username, not per-IP</strong> — it also stops distributed attacks spread across multiple IPs. The counter resets automatically after the lockout period expires.' ],
                    [ 'name' => 'Failed attempts', 'rec' => 'Recommended', 'html' => '<ul><li><code>3</code> — tighter security, but risks locking out users who mistype their password</li><li><code>5</code> — default, good balance</li><li><code>10</code> — more forgiving for sites with non-technical users</li></ul>To unlock an account immediately, delete the transient key <code>csdt_devtools_lockout_{username}</code> from the database.' ],
                    [ 'name' => 'Lockout period',  'rec' => 'Recommended', 'html' => 'Default is <code>5</code> minutes. The lock lifts automatically — no admin action needed.<br><br><ul><li><strong>5 min</strong> — default, enough to stop most automated attacks</li><li><strong>15–30 min</strong> — slows attacks further, slight UX delay for forgotten-password users</li></ul>' ],
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

                <div id="cs-bf-log-wrap" class="cs-bf-log-wrap">
                    <div class="cs-bf-log-header">
                        <span class="cs-bf-log-title">📊 <?php esc_html_e( 'Failed Login Attempts — Last 14 Days', 'cloudscale-devtools' ); ?></span>
                        <span id="cs-bf-log-total" class="cs-bf-log-total"></span>
                    </div>
                    <div id="cs-bf-chart" class="cs-bf-chart"></div>
                    <div id="cs-bf-table-wrap" class="cs-bf-table-wrap">
                        <div class="cs-bf-loading"><?php esc_html_e( 'Loading…', 'cloudscale-devtools' ); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Your 2FA Setup (current user) ─────────── -->
        <div class="cs-panel">
            <div class="cs-section-header cs-section-header-green">
                <span>👤 YOUR 2FA SETUP</span>
                <span class="cs-header-hint"><?php echo esc_html( wp_get_current_user()->user_login ); ?></span>
                <?php self::render_explain_btn( '2fa-setup', 'Your 2FA Setup', [
                    [ 'name' => 'Authenticator App (TOTP)', 'rec' => 'Recommended', 'html' => 'Generates a <strong>6-digit code every 30 seconds</strong> using a TOTP app. Works offline and is the most secure 2FA method.<br><br><ul><li><strong>Google Authenticator</strong> — iOS / Android</li><li><strong>Authy</strong> — iOS / Android / Desktop</li><li><strong>1Password</strong> — built-in TOTP support</li><li><strong>Apple Passwords</strong> — iOS 18+ / macOS 15+</li></ul>' ],
                    [ 'name' => 'Email Code',               'rec' => 'Optional',    'html' => 'Sends a one-time code to your account email on each login. Simpler to set up but depends on email deliverability — if your site\'s outgoing email is unreliable, use an authenticator app instead.' ],
                    [ 'name' => 'Passkey',                  'rec' => 'Recommended', 'html' => 'Uses <strong>Face ID</strong>, <strong>Touch ID</strong>, <strong>Windows Hello</strong>, or a hardware security key (YubiKey, etc.) as your second factor. Register a passkey in the <strong>Passkeys</strong> panel, then select this method here.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">

                <!-- Email 2FA status -->
                <?php
                // Check if a verification email is already pending for this user.
                $email_pending = (bool) get_user_meta( $user_id, 'csdt_devtools_email_verify_pending', true );
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
                    [ 'name' => 'Off',                      'rec' => 'Not Recommended', 'html' => 'Disables 2FA site-wide. Passwords alone are vulnerable to phishing and brute-force attacks — not recommended for any public site.' ],
                    [ 'name' => 'Email Code',               'rec' => 'Optional',        'html' => 'Requires users to enter a code sent to their email after each password login. Works out of the box with no app required — but depends on your site\'s outgoing email working reliably.' ],
                    [ 'name' => 'Authenticator App (TOTP)', 'rec' => 'Recommended',     'html' => 'Each user configures their own TOTP app (<strong>Google Authenticator</strong>, <strong>Authy</strong>, <strong>1Password</strong>). Most secure option — works offline, no email dependency.' ],
                    [ 'name' => 'Force 2FA for Admins',     'rec' => 'Recommended',     'html' => 'Blocks <code>administrator</code>-role users from accessing the dashboard until they have set up 2FA. Strongly recommended on any multi-user site.' ],
                    [ 'name' => 'Grace Logins',             'rec' => 'Advanced',        'html' => 'Allows a user to log in up to <em>N</em> times before 2FA is enforced. The counter is per-user and never resets automatically. Default is <code>0</code> (2FA required from the first login).<br><br><strong>Tip for automated test accounts:</strong> set to <code>1</code>. Tools like Playwright cannot complete a real 2FA challenge — one grace login lets a test account authenticate for setup steps without disabling 2FA site-wide.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <p class="cs-login-desc"><?php esc_html_e( 'Require a second verification step after password login. Email sends a one-time code; Authenticator uses Google Authenticator, Authy, or any TOTP app.', 'cloudscale-devtools' ); ?></p>

                <!-- Site-wide default -->
                <?php
                $has_passkeys = ! empty( CSDT_DevTools_Passkey::get_passkeys( $user_id ) );
                ?>
                <div class="cs-field-row">
                    <div class="cs-field">
                        <label class="cs-label"><?php esc_html_e( 'Site-wide Default Method:', 'cloudscale-devtools' ); ?></label>
                        <div class="cs-2fa-method-group">
                            <label class="cs-radio-label <?php echo $method === 'off' ? 'active' : ''; ?>">
                                <input type="radio" name="csdt_devtools_2fa_method" value="off" <?php checked( $method, 'off' ); ?>>
                                <span class="cs-radio-icon">🚫</span> <?php esc_html_e( 'Off', 'cloudscale-devtools' ); ?>
                            </label>
                            <label class="cs-radio-label <?php echo $method === 'email' ? 'active' : ''; ?> <?php echo ! $email_active ? 'cs-radio-disabled' : ''; ?>"
                                   <?php echo ! $email_active ? 'title="' . esc_attr__( 'Enable Email Code for your account first', 'cloudscale-devtools' ) . '"' : ''; ?>>
                                <input type="radio" name="csdt_devtools_2fa_method" value="email" <?php checked( $method, 'email' ); ?> <?php disabled( ! $email_active ); ?>>
                                <span class="cs-radio-icon">📧</span> <?php esc_html_e( 'Email Code', 'cloudscale-devtools' ); ?>
                            </label>
                            <label class="cs-radio-label <?php echo $method === 'totp' ? 'active' : ''; ?> <?php echo ! $totp_active ? 'cs-radio-disabled' : ''; ?>"
                                   <?php echo ! $totp_active ? 'title="' . esc_attr__( 'Set up Authenticator App for your account first', 'cloudscale-devtools' ) . '"' : ''; ?>>
                                <input type="radio" name="csdt_devtools_2fa_method" value="totp" <?php checked( $method, 'totp' ); ?> <?php disabled( ! $totp_active ); ?>>
                                <span class="cs-radio-icon">📱</span> <?php esc_html_e( 'Authenticator App', 'cloudscale-devtools' ); ?>
                            </label>
                            <label class="cs-radio-label <?php echo $method === 'passkey' ? 'active' : ''; ?> <?php echo ! $has_passkeys ? 'cs-radio-disabled' : ''; ?>"
                                   <?php echo ! $has_passkeys ? 'title="' . esc_attr__( 'Register a passkey for your account first', 'cloudscale-devtools' ) . '"' : ''; ?>>
                                <input type="radio" name="csdt_devtools_2fa_method" value="passkey" <?php checked( $method, 'passkey' ); ?> <?php disabled( ! $has_passkeys ); ?>>
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

                <?php $grace_logins = (int) get_option( 'csdt_devtools_2fa_grace_logins', '0' ); ?>
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
                    [ 'name' => 'What is a passkey?',    'rec' => 'Informational', 'html' => 'A passkey is a cryptographic credential stored on your device. It replaces passwords with biometrics (<strong>Face ID</strong>, <strong>Touch ID</strong>, <strong>Windows Hello</strong>) or hardware keys (YubiKey, etc.). No secret is ever sent over the network — the private key never leaves your device.' ],
                    [ 'name' => 'Registering a passkey', 'rec' => 'Recommended',  'html' => 'Click <strong>+ Add Passkey</strong>, give it a name (e.g. <code>iPhone 16</code> or <code>MacBook Touch ID</code>), then follow your device\'s biometric prompt.<br><br>Register multiple passkeys for different devices so you always have a backup.' ],
                    [ 'name' => 'Test',                  'rec' => 'Optional',     'html' => 'Verifies a passkey is working correctly <em>without</em> logging out. Use this after registering a new passkey to confirm the credential round-trips successfully.' ],
                    [ 'name' => 'Remove',                'rec' => 'Optional',     'html' => 'Deletes the passkey from your account. You can re-register it at any time — the device credential itself is not affected.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <?php CSDT_DevTools_Passkey::render_section( $user_id ); ?>
            </div>
        </div>

        <?php
        /* ── Test Account Manager ─────────────────────────────────────── */
        $ta_enabled     = get_option( 'csdt_test_accounts_enabled', '0' ) === '1';
        $ta_ttl         = get_option( 'csdt_test_account_ttl', '1800' );
        $ta_single_use  = get_option( 'csdt_test_account_single_use', '0' ) === '1';
        $ta_accounts    = self::get_active_test_accounts();
        ?>
        <div class="cs-panel" id="cs-panel-test-accounts">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#0f172a,#1e3a5f)">
                <span>🧪 <?php esc_html_e( 'TEST ACCOUNT MANAGER', 'cloudscale-devtools' ); ?></span>
                <span class="cs-header-hint"><?php esc_html_e( 'Temporary single-use accounts for Playwright / CI pipelines', 'cloudscale-devtools' ); ?></span>
            </div>
            <div class="cs-panel-body">
                <div class="cs-sec-settings">

                    <div class="cs-sec-row">
                        <span class="cs-sec-label"><?php esc_html_e( 'Enable:', 'cloudscale-devtools' ); ?></span>
                        <div class="cs-sec-control">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" id="cs-ta-enabled" <?php checked( $ta_enabled ); ?>>
                                <span><?php esc_html_e( 'Allow temporary test accounts with application passwords', 'cloudscale-devtools' ); ?></span>
                            </label>
                            <span class="cs-hint"><?php esc_html_e( 'Creates subscriber-level accounts with app passwords for automated testing. When enabled, app passwords are restricted to test accounts only — all production accounts remain blocked.', 'cloudscale-devtools' ); ?></span>
                        </div>
                    </div>

                    <div id="cs-ta-options" <?php echo $ta_enabled ? '' : 'style="display:none"'; ?>>

                        <div class="cs-sec-row">
                            <span class="cs-sec-label"><?php esc_html_e( 'Default TTL:', 'cloudscale-devtools' ); ?></span>
                            <div class="cs-sec-control">
                                <select id="cs-ta-ttl" class="cs-sec-select" style="width:auto;">
                                    <option value="1800"  <?php selected( $ta_ttl, '1800' );  ?>><?php esc_html_e( '30 minutes', 'cloudscale-devtools' ); ?></option>
                                    <option value="3600"  <?php selected( $ta_ttl, '3600' );  ?>><?php esc_html_e( '1 hour',     'cloudscale-devtools' ); ?></option>
                                    <option value="7200"  <?php selected( $ta_ttl, '7200' );  ?>><?php esc_html_e( '2 hours',    'cloudscale-devtools' ); ?></option>
                                    <option value="86400" <?php selected( $ta_ttl, '86400' ); ?>><?php esc_html_e( '24 hours',   'cloudscale-devtools' ); ?></option>
                                </select>
                                <span class="cs-hint"><?php esc_html_e( 'Accounts are automatically deleted after this time.', 'cloudscale-devtools' ); ?></span>
                            </div>
                        </div>

                        <div class="cs-sec-row">
                            <span class="cs-sec-label"><?php esc_html_e( 'Single-use:', 'cloudscale-devtools' ); ?></span>
                            <div class="cs-sec-control">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="checkbox" id="cs-ta-single-use" <?php checked( $ta_single_use ); ?>>
                                    <span><?php esc_html_e( 'Delete account on first successful authentication', 'cloudscale-devtools' ); ?></span>
                                </label>
                                <span class="cs-hint"><?php esc_html_e( 'Extra security — each test run gets fresh credentials. May cause issues if Playwright retries a failed request.', 'cloudscale-devtools' ); ?></span>
                            </div>
                        </div>

                        <div class="cs-sec-row">
                            <span class="cs-sec-label"></span>
                            <div class="cs-sec-control" style="display:flex;align-items:center;gap:8px;">
                                <button type="button" class="cs-btn-primary" id="cs-ta-save">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
                                <span class="cs-settings-saved" id="cs-ta-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                            </div>
                        </div>

                        <hr class="cs-sec-divider">

                        <div class="cs-sec-row">
                            <span class="cs-sec-label"><?php esc_html_e( 'Create account:', 'cloudscale-devtools' ); ?></span>
                            <div class="cs-sec-control">
                                <button type="button" class="cs-btn-primary" id="cs-ta-create">+ <?php esc_html_e( 'Create Test Account', 'cloudscale-devtools' ); ?></button>
                                <span class="cs-hint"><?php esc_html_e( 'Credentials shown once only — copy them immediately.', 'cloudscale-devtools' ); ?></span>
                            </div>
                        </div>

                        <div id="cs-ta-creds" style="display:none;margin:0 0 16px 0;padding:16px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;">
                            <div style="font-weight:600;color:#166534;margin-bottom:10px;">✓ <?php esc_html_e( 'Test account created — copy credentials now (shown once)', 'cloudscale-devtools' ); ?></div>
                            <div style="font-family:monospace;font-size:13px;line-height:1.9;color:#1d2327;">
                                <div><strong><?php esc_html_e( 'Username:', 'cloudscale-devtools' ); ?></strong> <span id="cs-ta-cred-user"></span></div>
                                <div><strong><?php esc_html_e( 'App password:', 'cloudscale-devtools' ); ?></strong> <span id="cs-ta-cred-pw"></span></div>
                                <div><strong><?php esc_html_e( 'REST URL:', 'cloudscale-devtools' ); ?></strong> <span id="cs-ta-cred-url"></span></div>
                                <div><strong><?php esc_html_e( 'Expires:', 'cloudscale-devtools' ); ?></strong> <span id="cs-ta-cred-expires"></span></div>
                            </div>
                            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                                <button type="button" class="cs-btn-secondary cs-btn-sm" id="cs-ta-copy-json">⎘ <?php esc_html_e( 'Copy as JSON', 'cloudscale-devtools' ); ?></button>
                                <button type="button" class="cs-btn-secondary cs-btn-sm" id="cs-ta-copy-curl">⎘ <?php esc_html_e( 'Copy curl example', 'cloudscale-devtools' ); ?></button>
                            </div>
                        </div>

                        <div style="font-weight:600;font-size:13px;color:#1d2327;margin-bottom:8px;"><?php esc_html_e( 'Active test accounts:', 'cloudscale-devtools' ); ?></div>
                        <div id="cs-ta-list">
                            <?php if ( empty( $ta_accounts ) ) : ?>
                                <p style="color:#888;font-size:13px;margin:0;"><?php esc_html_e( 'No active test accounts.', 'cloudscale-devtools' ); ?></p>
                            <?php else : ?>
                                <?php foreach ( $ta_accounts as $acct ) :
                                    $mins = max( 0, (int) ceil( ( $acct['expires_at'] - time() ) / 60 ) );
                                ?>
                                <div class="cs-ta-account-row" style="display:flex;align-items:center;gap:12px;padding:8px 12px;margin-bottom:4px;background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;">
                                    <div style="flex:1;font-family:monospace;font-size:13px;"><?php echo esc_html( $acct['username'] ); ?></div>
                                    <div style="font-size:12px;color:#6b7280;"><?php printf( esc_html__( 'expires in %dm', 'cloudscale-devtools' ), $mins ); ?></div>
                                    <button type="button" class="cs-btn-secondary cs-btn-sm cs-ta-revoke" data-user-id="<?php echo esc_attr( $acct['user_id'] ); ?>" style="color:#dc2626;border-color:#fca5a5;"><?php esc_html_e( 'Revoke', 'cloudscale-devtools' ); ?></button>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                    </div>
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
        if ( ! check_ajax_referer( 'csdt_devtools_sql_nonce', 'nonce', false ) ) {
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
        if ( ! check_ajax_referer( 'csdt_devtools_code_settings_inline', 'nonce', false ) ) {
            wp_send_json_error( 'Bad nonce' );
        }

        $theme = isset( $_POST['theme'] ) ? sanitize_text_field( wp_unslash( $_POST['theme'] ) ) : 'dark';
        if ( ! in_array( $theme, [ 'dark', 'light' ], true ) ) {
            $theme = 'dark';
        }
        update_option( 'csdt_devtools_code_default_theme', $theme );

        $valid_pairs = array_keys( self::get_theme_registry() );
        $pair        = isset( $_POST['theme_pair'] ) ? sanitize_text_field( wp_unslash( $_POST['theme_pair'] ) ) : 'atom-one';
        if ( ! in_array( $pair, $valid_pairs, true ) ) {
            $pair = 'atom-one';
        }
        update_option( 'csdt_devtools_code_theme_pair', $pair );

        $perf_enabled = isset( $_POST['csdt_devtools_perf_monitor_enabled'] ) && '1' === $_POST['csdt_devtools_perf_monitor_enabled'] ? '1' : '0'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        update_option( 'csdt_devtools_perf_monitor_enabled', $perf_enabled );

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
            'csdt-perf-monitor',
            plugins_url( 'assets/cs-perf-monitor.css', __FILE__ ),
            [],
            self::VERSION
        );
        wp_enqueue_script(
            'csdt-perf-monitor',
            plugins_url( 'assets/cs-perf-monitor.js', __FILE__ ),
            [],
            self::VERSION,
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
            'csdt-perf-monitor',
            plugins_url( 'assets/cs-perf-monitor.css', __FILE__ ),
            [],
            self::VERSION
        );
        wp_enqueue_script(
            'csdt-perf-monitor',
            plugins_url( 'assets/cs-perf-monitor.js', __FILE__ ),
            [],
            self::VERSION,
            true
        );
    }

    /**
     * Injects performance data as a JS global before footer scripts are printed.
     *
     * Hooked to admin_footer / wp_footer at priority 15, before WordPress
     * calls wp_print_footer_scripts() at priority 20. This ensures
     * window.csdtDevtoolsPerfData is set before cs-perf-monitor.js IIFE runs.
     *
     * @since  1.8.113
     * @return void
     */
    public static function perf_inject_data(): void {
        global $wpdb;
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( get_option( 'csdt_devtools_perf_monitor_enabled', '1' ) === '0' ) {
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

        // Request time snapshot at data-injection time (priority 15).
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
                'explain_nonce'      => wp_create_nonce( 'csdt_devtools_perf_explain' ),
                'debug_nonce'        => wp_create_nonce( 'csdt_devtools_perf_debug' ),
                'wp_debug'           => (bool) get_option( 'csdt_devtools_perf_debug_logging', false ),
                'wp_debug_log'       => (bool) get_option( 'csdt_devtools_perf_debug_logging', false ),
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
                self::$perf_milestones
            ),
        ];

        wp_add_inline_script( 'csdt-perf-monitor', 'window.csdtDevtoolsPerfData=' . wp_json_encode( $data ) . ';', 'before' );
    }

    /**
     * Outputs the performance monitor panel HTML skeleton at footer.
     *
     * Fires at priority 9999 so it appears at the very end of the page body.
     * Data is injected earlier via perf_inject_data() at priority 15.
     *
     * @since  1.8.0
     * @return void
     */
    public static function perf_output_panel(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( get_option( 'csdt_devtools_perf_monitor_enabled', '1' ) === '0' ) {
            return;
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static HTML, no user data
        echo self::perf_panel_html();
    }

    // ─── Thumbnails admin CSS ─────────────────────────────────────────────────

    /**
     * Returns the CSS for the Thumbnails admin tab.
     *
     * Injected via wp_add_inline_style() on the cs-admin-tabs handle when the
     * thumbnails tab is active, keeping the render method free of <style> tags.
     *
     * @since  1.8.113
     * @return string
     */
    private static function get_thumbnails_admin_css(): string {
        return '
.cs-thumb-cf-steps{display:flex;flex-direction:column;gap:12px;margin-top:10px}
.cs-thumb-cf-step{display:flex;gap:12px;align-items:flex-start}
.cs-thumb-cf-step-num{min-width:26px;height:26px;background:#e65100;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;margin-top:2px}
.cs-thumb-cf-code{background:#1e1e1e;color:#e8e8e8;padding:10px 14px;border-radius:4px;font-size:12px;overflow-x:auto;white-space:pre-wrap;word-break:break-all;margin:6px 0}
.cs-thumb-report-hdr{display:flex;justify-content:space-between;align-items:center;padding:9px 14px;border-radius:4px;margin-bottom:10px;font-size:13px;font-weight:600}
.cs-thumb-pass-hdr{background:#edfaed;color:#276227}
.cs-thumb-warn-hdr{background:#fff8e5;color:#7a5a00}
.cs-thumb-fail-hdr{background:#fdf0f0;color:#8c2020}
.cs-thumb-tally{display:flex;gap:12px;font-size:13px}
.cs-thumb-section{border:1px solid #e0e0e0;border-radius:4px;margin-bottom:10px;overflow:hidden}
.cs-thumb-section-title{background:#f6f7f7;padding:6px 12px;font-size:12px;font-weight:700;color:#333;border-bottom:1px solid #e0e0e0;text-transform:uppercase;letter-spacing:.4px}
.cs-thumb-results-list{margin:0;padding:6px 10px;list-style:none}
.cs-thumb-result{display:flex;gap:8px;padding:3px 0;font-size:12px;align-items:flex-start}
.cs-thumb-pass{color:#276227}
.cs-thumb-warn{color:#7a5a00}
.cs-thumb-fail{color:#8c2020}
.cs-thumb-fix{margin-top:3px;font-size:11px;color:#1a4a7a;background:#f0f6fc;border-left:3px solid #2271b1;padding:3px 7px;border-radius:0 3px 3px 0}
.cs-thumb-info{color:#555}
.cs-thumb-ua-grid{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.cs-thumb-ua-chip{padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600}
.cs-thumb-ua-ok{background:#edfaed;color:#276227}
.cs-thumb-ua-fail{background:#fdf0f0;color:#8c2020}
.cs-thumb-ua-warn{background:#fff8e5;color:#7a5a00}
.cs-input-light-placeholder::placeholder{color:#bbb;font-weight:400}
.cs-thumb-posts-table{width:100%;border-collapse:collapse;font-size:13px}
.cs-thumb-posts-table th{background:#f6f7f7;padding:7px 10px;text-align:left;border-bottom:2px solid #ddd}
.cs-thumb-posts-table td{padding:7px 10px;border-bottom:1px solid #eee;vertical-align:top}
.cs-thumb-badge-ok{display:inline-block;background:#edfaed;color:#276227;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
.cs-thumb-badge-warn{display:inline-block;background:#fff8e5;color:#7a5a00;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
.cs-thumb-badge-fail{display:inline-block;background:#fdf0f0;color:#8c2020;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
.cs-thumb-audit-table{font-size:12px;width:100%;border-collapse:collapse}
.cs-thumb-audit-table th{background:#f6f7f7;padding:6px 10px;text-align:left;border-bottom:2px solid #ddd}
.cs-thumb-audit-table td{padding:6px 10px;border-bottom:1px solid #eee;vertical-align:top}
.cs-platform-grid{display:flex;flex-wrap:wrap;gap:10px}
.cs-platform-card{display:flex;align-items:flex-start;gap:8px;padding:10px 12px;border:2px solid #ddd;border-radius:6px;cursor:pointer;transition:border-color .15s,background .15s;min-width:160px;flex:1 1 auto;max-width:200px}
.cs-platform-card:hover{border-color:#2271b1}
.cs-platform-checked{border-color:#2271b1;background:#f0f6ff}
.cs-platform-card input{margin-top:2px;flex-shrink:0}
.cs-platform-card-body{display:flex;flex-direction:column;gap:2px}
.cs-platform-name{font-size:13px;font-weight:600;color:#333}
.cs-platform-dims{font-size:11px;color:#555}
.cs-platform-limit{font-size:11px;color:#888}
.cs-fix-modal-wrap{margin-top:8px;padding:10px 12px;background:#f6f7f7;border:1px solid #e0e0e0;border-radius:5px;font-size:12px}
.cs-fix-platform-row{display:flex;align-items:center;gap:8px;padding:4px 0;border-bottom:1px solid #ebebeb}
.cs-fix-platform-row:last-child{border-bottom:none}
.cs-fix-platform-label{min-width:90px;font-weight:600;color:#333;font-size:12px}
.cs-fix-platform-dims{color:#888;font-size:11px;min-width:80px}
.cs-fix-platform-status{font-size:11px;flex:1}
.cs-fix-preview-thumb{width:48px;height:28px;object-fit:cover;border-radius:2px;border:1px solid #ddd;flex-shrink:0}
';
    }

    /**
     * Returns CSS for the Explain modal description content.
     *
     * Injected via wp_add_inline_style() on the csdt-admin-tabs handle on
     * every admin page load, scoped to .cs-explain-desc so it cannot leak.
     *
     * @since  1.8.118
     * @return string
     */
    private static function get_explain_modal_css(): string {
        return '
.cs-explain-desc{color:#50575e;font-size:12px;line-height:1.7}
.cs-explain-desc p{margin:0 0 8px 0}
.cs-explain-desc p:last-child{margin-bottom:0}
.cs-explain-desc code{display:inline;background:#1e2430;color:#e8b86d;padding:1px 6px;border-radius:3px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:11px;white-space:nowrap;word-break:break-all}
.cs-explain-desc strong{color:#111827;font-weight:700}
.cs-explain-desc em{font-style:italic}
.cs-explain-desc a{color:#2271b1;text-decoration:underline}
.cs-explain-desc a:hover{color:#135e96}
.cs-explain-desc ul,.cs-explain-desc ol{margin:6px 0 0 0;padding-left:20px}
.cs-explain-desc li{margin-bottom:4px}
.cs-explain-desc h4{margin:10px 0 4px;font-size:12px;font-weight:700;color:#111827;text-transform:uppercase;letter-spacing:.04em}
';
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
                    : sanitize_text_field( wp_unslash( (string) $v ) );
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
        $failed_logins_1h  = (int) get_transient( 'csdt_devtools_failed_logins_1h' );
        $failed_logins_24h = (int) get_transient( 'csdt_devtools_failed_logins_24h' );

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
            $log_path = get_option( 'csdt_debug_log_path', '' ) ?: ( is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : ( WP_CONTENT_DIR . '/debug.log' ) );
            if ( file_exists( $log_path ) ) {
                $debug_log_mb = round( (float) filesize( $log_path ) / 1048576, 1 );
            }
        }

        // ── Web server version (from Server header) ───────────────────────────
        $server_software = isset( $_SERVER['SERVER_SOFTWARE'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) )
            : '';
        $nginx_version  = '';
        $apache_version = '';
        if ( preg_match( '/nginx\/([0-9][0-9.]*)/i', $server_software, $sv_m ) ) {
            $nginx_version = $sv_m[1];
        } elseif ( preg_match( '/Apache\/([0-9][0-9.]*)/i', $server_software, $sv_m ) ) {
            $apache_version = $sv_m[1];
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
            'db_full_version'        => $db_full_version,
            'nginx_version'          => $nginx_version,
            'apache_version'         => $apache_version,
            'brute_force_enabled'    => get_option( 'csdt_devtools_brute_force_enabled', '1' ) === '1',
        ];
    }

    /**
     * Fired on wp_login_failed. Increments rolling failed-login counters stored
     * as transients so the CS Monitor can surface brute-force signals.
     *
     * @param string $username The username that failed authentication.
     * @return void
     */
    private static function get_client_ip(): string {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $candidate = sanitize_text_field( wp_unslash( explode( ',', $_SERVER[ $key ] )[0] ) );
                if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                    return $candidate;
                }
            }
        }
        return '';
    }

    public static function perf_track_failed_login( string $username ): void {
        // 1-hour rolling window.
        $c1h = (int) get_transient( 'csdt_devtools_failed_logins_1h' );
        set_transient( 'csdt_devtools_failed_logins_1h', $c1h + 1, HOUR_IN_SECONDS );
        // 24-hour rolling window.
        $c24h = (int) get_transient( 'csdt_devtools_failed_logins_24h' );
        set_transient( 'csdt_devtools_failed_logins_24h', $c24h + 1, DAY_IN_SECONDS );

        // Persistent 14-day rolling log [timestamp, username, ip] — capped at 500 entries.
        $log    = get_option( 'csdt_devtools_bf_log', [] );
        if ( ! is_array( $log ) ) {
            $log = [];
        }
        $cutoff = time() - 14 * DAY_IN_SECONDS;
        $log    = array_values( array_filter( $log, fn( $e ) => isset( $e[0] ) && $e[0] >= $cutoff ) );
        if ( count( $log ) >= 500 ) {
            array_shift( $log );
        }
        $log[] = [ time(), sanitize_user( $username, true ), self::get_client_ip() ];
        update_option( 'csdt_devtools_bf_log', $log, false );

        // ── Brute-force per-account lockout ──────────────────────────────────
        if ( get_option( 'csdt_devtools_brute_force_enabled', '1' ) !== '1' || empty( $username ) ) {
            return;
        }
        $max_attempts = max( 1, (int) get_option( 'csdt_devtools_brute_force_attempts', '5' ) );
        $lockout_secs = max( 60, (int) get_option( 'csdt_devtools_brute_force_lockout', '5' ) * MINUTE_IN_SECONDS );
        $slug         = md5( strtolower( $username ) );
        $count_key    = 'csdt_devtools_bf_count_' . $slug;
        $lock_key     = 'csdt_devtools_bf_lock_' . $slug;
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
        check_ajax_referer( 'csdt_devtools_perf_explain', 'nonce' );

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
        check_ajax_referer( 'csdt_devtools_perf_debug', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $enable = isset( $_POST['enable'] ) ? ( '1' === $_POST['enable'] || 'true' === $_POST['enable'] ) : null;
        if ( null === $enable ) {
            $enable = ! (bool) get_option( 'csdt_devtools_perf_debug_logging', false );
        }

        if ( $enable ) {
            update_option( 'csdt_devtools_perf_debug_logging', 1, false );
        } else {
            delete_option( 'csdt_devtools_perf_debug_logging' );
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
        $log_file = get_option( 'csdt_debug_log_path', '' ) ?: (
            defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG )
                ? WP_DEBUG_LOG
                : WP_CONTENT_DIR . '/debug.log'
        );

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
     * which reads window.csdtDevtoolsPerfData.
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
                    . '<div id="cs-ptab-scroll">'
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
                    . '<button id="cs-perf-copy" class="cs-ptab-copy" title="Copy current tab to clipboard">&#128203; Copy</button>'
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

    const LOGIN_NONCE           = 'csdt_devtools_login_nonce';
    const SMTP_NONCE            = 'csdt_devtools_smtp_nonce';
    const LOGIN_2FA_TRANSIENT   = 'csdt_devtools_2fa_pending_';    // + random token
    const LOGIN_OTP_TRANSIENT   = 'csdt_devtools_2fa_otp_';        // + user_id
    const EMAIL_VERIFY_TRANSIENT = 'csdt_devtools_email_verify_';  // + random token (10 min)
    const TOTP_CHARS            = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // ── A. Hide Login — custom URL slug ──────────────────────────────────

    /**
     * Fired on `init` at priority 1. If the current request matches the
     * custom login slug, serve wp-login.php transparently from that URL.
     *
     * @since  1.9.4
     * @return void
     */
    public static function login_serve_custom_slug(): void {
        if ( get_option( 'csdt_devtools_login_hide_enabled', '0' ) !== '1' ) {
            return;
        }
        $slug = get_option( 'csdt_devtools_login_slug', '' );
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
        // Exception: let logout, password-reset, and similar actions fall through
        // to wp-login.php so they are processed correctly.
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $passthrough = [ 'logout', 'lostpassword', 'rp', 'resetpass', 'postpass' ];
        if ( ! in_array( $action, $passthrough, true ) && is_user_logged_in() ) {
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
        $duration = get_option( 'csdt_devtools_session_duration', 'default' );
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
        if ( get_option( 'csdt_devtools_session_duration', 'default' ) !== 'default' ) {
            $_POST['rememberme'] = 'forever'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
    }

    /**
     * Returns true when the auth cookie should be written as a persistent cookie.
     * Respects the custom session duration setting (always persistent) and falls
     * back to the user's original "Remember Me" choice stored in the 2FA transient.
     *
     * @since  1.9.10
     * @param  array $pending  2FA pending transient data (may be empty for non-2FA logins).
     * @return bool
     */
    private static function login_should_remember( array $pending = [] ): bool {
        if ( get_option( 'csdt_devtools_session_duration', 'default' ) !== 'default' ) {
            return true;
        }
        return ! empty( $pending['remember'] );
    }

    /**
     * Hooked to `authenticate` at priority 1. Returns a WP_Error when the
     * submitted username has been temporarily locked due to repeated failed attempts.
     *
     * @since  1.9.10
     * @param  \WP_User|\WP_Error|null $user
     * @param  string                  $username
     * @param  string                  $password
     * @return \WP_User|\WP_Error|null
     */
    public static function login_brute_force_check( $user, string $username, string $password ) {
        if ( get_option( 'csdt_devtools_brute_force_enabled', '1' ) !== '1' ) {
            return $user;
        }
        if ( empty( $username ) ) {
            return $user;
        }
        $lock_key     = 'csdt_devtools_bf_lock_' . md5( strtolower( $username ) );
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
            'csdt_devtools_account_locked',
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
        $skip   = [ 'logout', 'lostpassword', 'rp', 'resetpass', 'postpass', 'register', 'csdt_devtools_2fa' ];
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
     * @since  1.9.4
     * @return void
     */
    public static function login_block_direct_access(): void {
        if ( get_option( 'csdt_devtools_login_hide_enabled', '0' ) !== '1' ) {
            return;
        }
        $slug = get_option( 'csdt_devtools_login_slug', '' );
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
        $safe   = [ 'logout', 'lostpassword', 'rp', 'resetpass', 'postpass', 'register', 'csdt_devtools_2fa' ];
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
     * @since  1.9.4
     * @param  string $url
     * @param  string $redirect
     * @param  bool   $force_reauth
     * @return string
     */
    public static function login_custom_url( string $url, string $redirect, bool $force_reauth ): string {
        if ( get_option( 'csdt_devtools_login_hide_enabled', '0' ) !== '1' ) {
            return $url;
        }
        $slug = get_option( 'csdt_devtools_login_slug', '' );
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
     * @since  1.9.4
     * @param  string $url
     * @param  string $redirect
     * @return string
     */
    public static function login_custom_logout_url( string $url, string $redirect ): string {
        if ( get_option( 'csdt_devtools_login_hide_enabled', '0' ) !== '1' ) {
            return $url;
        }
        $slug = get_option( 'csdt_devtools_login_slug', '' );
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
     * @since  1.9.4
     * @param  string $url
     * @param  string $redirect
     * @return string
     */
    public static function login_custom_lostpassword_url( string $url, string $redirect ): string {
        if ( get_option( 'csdt_devtools_login_hide_enabled', '0' ) !== '1' ) {
            return $url;
        }
        $slug = get_option( 'csdt_devtools_login_slug', '' );
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
     * @since  1.9.4
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
     * @since  1.9.4
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
     * @since  1.9.4
     * @param  string $url
     * @param  string $path
     * @return string
     */
    private static function login_rewrite_login_url( string $url, string $path ): string {
        if ( get_option( 'csdt_devtools_login_hide_enabled', '0' ) !== '1' ) {
            return $url;
        }
        $slug = get_option( 'csdt_devtools_login_slug', '' );
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
     * @since  1.9.4
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
        $grace_limit = (int) get_option( 'csdt_devtools_2fa_grace_logins', '0' );
        if ( $grace_limit > 0 ) {
            $grace_count = (int) get_user_meta( $user->ID, 'csdt_devtools_2fa_grace_count', true );
            if ( $grace_count < $grace_limit ) {
                update_user_meta( $user->ID, 'csdt_devtools_2fa_grace_count', $grace_count + 1 );
                return $user; // Skip 2FA — grace login consumed.
            }
        }

        // Avoid triggering 2FA during a 2FA verification POST itself.
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $action === 'csdt_devtools_2fa' ) {
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
            'action'   => 'csdt_devtools_2fa',
            'csdt_devtools_token' => rawurlencode( $token ),
        ], wp_login_url() );

        wp_safe_redirect( $login_url );
        exit;
    }

    /**
     * Fired on `login_init`. Handles the 2FA code entry form: display and verification.
     *
     * @since  1.9.4
     * @return void
     */
    public static function login_2fa_handle(): void {
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $action !== 'csdt_devtools_2fa' ) {
            return;
        }

        $token   = isset( $_REQUEST['csdt_devtools_token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['csdt_devtools_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
        if ( $method === 'passkey' && ! empty( $_POST['csdt_devtools_pk_fallback'] ) ) {
            // Only send a new OTP if one hasn't been sent in the last 30 seconds (prevents spam from double-clicks).
            $rate_key    = 'csdt_devtools_pk_fb_' . $user_id;
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
        if ( $method === 'passkey' && isset( $_POST['csdt_devtools_pk_cred_id'] ) ) {
            $result = CSDT_DevTools_Passkey::verify_login_assertion( $token, $user_id );
            if ( $result === true ) {
                delete_transient( self::LOGIN_2FA_TRANSIENT . $token );
                wp_set_auth_cookie( $user_id, self::login_should_remember( $pending ) );
                $redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : admin_url();
                // Use JS top-level navigation: on macOS/Windows, the browser runs the passkey
                // ceremony inside an OS overlay. wp_safe_redirect() navigates that overlay's
                // frame instead of the main tab, painting the dashboard inside the sheet.
                // window.top.location.href escapes any sub-frame and lands the user correctly.
                echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
                echo '<script>window.top.location.href=' . wp_json_encode( $redirect ) . ';</script>';
                echo '</body></html>';
                exit;
            }
            // Verification failed — re-render challenge with error.
            $error = $result->get_error_message();
        }

        // ── Passkey challenge page (GET or re-render after failure) ──────────
        if ( $method === 'passkey' && empty( $_POST['csdt_devtools_2fa_code'] ) ) {
            CSDT_DevTools_Passkey::render_login_challenge( $token, $user_id, $error );
            // render_login_challenge() exits.
        }

        // Handle code submission.
        if ( isset( $_POST['csdt_devtools_2fa_code'] ) ) {
            if ( ! isset( $_POST['csdt_devtools_2fa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csdt_devtools_2fa_nonce'] ) ), 'csdt_devtools_2fa_verify_' . $token ) ) {
                $error = __( 'Security check failed. Please try again.', 'cloudscale-devtools' );
            } else {
                $code    = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['csdt_devtools_2fa_code'] ) ) );
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
                        $secret = get_user_meta( $user_id, 'csdt_devtools_totp_secret', true );
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
     * @since  1.9.4
     * @param  string $token  Pending auth token.
     * @param  string $method 'email' or 'totp'.
     * @param  string $error  Optional error message.
     * @return void
     */
    private static function login_2fa_render_form( string $token, string $method, string $error = '' ): void {
        // Use WordPress's own login page scaffolding.
        login_header( __( 'Two-Factor Authentication', 'cloudscale-devtools' ), '', null );

        $nonce      = wp_create_nonce( 'csdt_devtools_2fa_verify_' . $token );
        $method_txt = $method === 'email'
            ? __( 'Enter the 6-digit code that was sent to your email address.', 'cloudscale-devtools' )
            : __( 'Enter the 6-digit code from your authenticator app.', 'cloudscale-devtools' );

        $icon = $method === 'email' ? '📧' : '📱';
        ?>
        <form name="csdt_devtools_2faform" id="csdt_devtools_2faform" action="" method="post">
            <p style="text-align:center;font-size:48px;margin:0 0 8px"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — emoji literal ?></p>
            <p style="text-align:center;margin:0 0 20px;color:#555;font-size:13px;line-height:1.5"><?php echo esc_html( $method_txt ); ?></p>

            <?php if ( $error ) : ?>
                <div id="login_error" class="notice notice-error" style="margin:0 0 16px"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endif; ?>

            <p>
                <label for="csdt_devtools_2fa_code"><?php esc_html_e( 'Authentication Code', 'cloudscale-devtools' ); ?></label>
                <input type="text" name="csdt_devtools_2fa_code" id="csdt_devtools_2fa_code" class="input"
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

            <input type="hidden" name="action"     value="csdt_devtools_2fa">
            <input type="hidden" name="csdt_devtools_token"   value="<?php echo esc_attr( $token ); ?>">
            <input type="hidden" name="csdt_devtools_2fa_nonce" value="<?php echo esc_attr( $nonce ); ?>">

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
     * @since  1.9.4
     * @param  \WP_User $user
     * @return string
     */
    private static function login_2fa_method_for_user( \WP_User $user ): string {
        $site_method = get_option( 'csdt_devtools_2fa_method', 'off' );
        $force       = get_option( 'csdt_devtools_2fa_force_admins', '0' ) === '1';

        // Passkeys always take priority when the user has any registered.
        if ( ! empty( CSDT_DevTools_Passkey::get_passkeys( $user->ID ) ) ) {
            return 'passkey';
        }

        // If force is on and user is admin, enforce the site method.
        if ( $force && user_can( $user, 'manage_options' ) && $site_method !== 'off' ) {
            // If TOTP forced but user hasn't set it up, fall back to email.
            if ( $site_method === 'totp' && get_user_meta( $user->ID, 'csdt_devtools_totp_enabled', true ) !== '1' ) {
                return 'email';
            }
            return $site_method;
        }

        // Per-user TOTP.
        if ( get_user_meta( $user->ID, 'csdt_devtools_totp_enabled', true ) === '1' ) {
            return 'totp';
        }

        // Per-user email 2FA.
        if ( get_user_meta( $user->ID, 'csdt_devtools_2fa_email_enabled', true ) === '1' ) {
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
     * @since  1.9.4
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
     * @since  1.9.4
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
     * @since  1.9.4
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
     * @since  1.9.4
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
     * @since  1.9.4
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
     * AJAX: returns the 14-day failed login log for the brute-force panel.
     */
    public static function ajax_bf_log_fetch(): void {
        check_ajax_referer( self::LOGIN_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 403 );
        }
        $log = get_option( 'csdt_devtools_bf_log', [] );
        if ( ! is_array( $log ) ) {
            $log = [];
        }
        $cutoff = time() - 14 * DAY_IN_SECONDS;
        $log    = array_values( array_filter( $log, fn( $e ) => isset( $e[0] ) && $e[0] >= $cutoff ) );
        wp_send_json_success( [ 'log' => $log, 'now' => time() ] );
    }

    /**
     * AJAX: saves Hide Login and 2FA site-wide settings.
     *
     * @since  1.9.4
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
        update_option( 'csdt_devtools_login_hide_enabled', $hide );
        update_option( 'csdt_devtools_login_slug', $slug );

        // 2FA
        $method = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : 'off';
        if ( ! in_array( $method, [ 'off', 'email', 'totp' ], true ) ) {
            $method = 'off';
        }
        $force = isset( $_POST['force_admins'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['force_admins'] ) ) ? '1' : '0';
        update_option( 'csdt_devtools_2fa_method', $method );
        update_option( 'csdt_devtools_2fa_force_admins', $force );

        // Session duration
        $valid_durations = [ 'default', '1', '7', '14', '30', '90', '365' ];
        $duration        = isset( $_POST['session_duration'] ) ? sanitize_key( wp_unslash( $_POST['session_duration'] ) ) : 'default';
        if ( ! in_array( $duration, $valid_durations, true ) ) {
            $duration = 'default';
        }
        update_option( 'csdt_devtools_session_duration', $duration );

        // Brute-force protection
        $bf_enabled  = isset( $_POST['bf_enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['bf_enabled'] ) ) ? '1' : '0';
        $bf_attempts = isset( $_POST['bf_attempts'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['bf_attempts'] ) ) : 5;
        $bf_lockout  = isset( $_POST['bf_lockout'] )  ? (int) sanitize_text_field( wp_unslash( $_POST['bf_lockout'] ) )  : 5;
        if ( $bf_attempts < 1 || $bf_attempts > 100 )   { $bf_attempts = 5; }
        if ( $bf_lockout  < 1 || $bf_lockout  > 1440 )  { $bf_lockout  = 5; }
        update_option( 'csdt_devtools_brute_force_enabled',  $bf_enabled );
        update_option( 'csdt_devtools_brute_force_attempts', (string) $bf_attempts );
        update_option( 'csdt_devtools_brute_force_lockout',  (string) $bf_lockout );

        // Grace logins
        $grace_logins = isset( $_POST['grace_logins'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['grace_logins'] ) ) : 0;
        if ( $grace_logins < 0 || $grace_logins > 10 ) { $grace_logins = 0; }
        update_option( 'csdt_devtools_2fa_grace_logins', (string) $grace_logins );

        $new_url = $hide === '1' && $slug ? home_url( '/' . $slug . '/' ) : wp_login_url();
        wp_send_json_success( [ 'login_url' => $new_url ] );
    }

    /**
     * AJAX: generates a new TOTP secret and returns the QR code URL for setup.
     * Stores the secret as a pending (unconfirmed) user meta key.
     *
     * @since  1.9.4
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
        update_user_meta( $user_id, 'csdt_devtools_totp_secret_pending', $secret );

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
     * @since  1.9.4
     * @return void
     */
    public static function ajax_totp_setup_verify(): void {
        check_ajax_referer( self::LOGIN_NONCE, 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $code    = isset( $_POST['code'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) : '';
        $user_id = get_current_user_id();
        $secret  = get_user_meta( $user_id, 'csdt_devtools_totp_secret_pending', true );

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
        update_user_meta( $user_id, 'csdt_devtools_totp_secret', $secret );
        update_user_meta( $user_id, 'csdt_devtools_totp_enabled', '1' );
        delete_user_meta( $user_id, 'csdt_devtools_totp_secret_pending' );

        // If user had email 2FA, disable it (TOTP is preferred).
        delete_user_meta( $user_id, 'csdt_devtools_2fa_email_enabled' );

        // Security state changed — destroy all other open sessions.
        wp_destroy_other_sessions();

        wp_send_json_success( [ 'message' => __( 'Authenticator app activated!', 'cloudscale-devtools' ) ] );
    }

    /**
     * AJAX: disables 2FA for the current user (email or TOTP).
     *
     * @since  1.9.4
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
            delete_user_meta( $user_id, 'csdt_devtools_totp_secret' );
            delete_user_meta( $user_id, 'csdt_devtools_totp_secret_pending' );
            update_user_meta( $user_id, 'csdt_devtools_totp_enabled', '0' );
        } elseif ( $method === 'email' ) {
            update_user_meta( $user_id, 'csdt_devtools_2fa_email_enabled', '0' );
            delete_user_meta( $user_id, 'csdt_devtools_email_verify_pending' );
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
     * @since  1.9.4
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
        update_user_meta( $user_id, 'csdt_devtools_email_verify_pending', '1' );

        $callback = add_query_arg(
            [ 'csdt_devtools_email_verify' => rawurlencode( $token ) ],
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
            delete_user_meta( $user_id, 'csdt_devtools_email_verify_pending' );
            // Surface the real SMTP error if captured, otherwise the warning from diagnostics.
            $detail = $transport_error ?: $warning ?: __( 'Check your WordPress mail configuration.', 'cloudscale-devtools' );
            // Flag when SMTP isn't configured so the UI can prompt the user to set it up.
            $smtp_not_configured = get_option( 'csdt_devtools_smtp_enabled', '0' ) !== '1'
                || '' === trim( (string) get_option( 'csdt_devtools_smtp_host', '' ) );
            wp_send_json_error( [
                'message'             => sprintf( __( 'Email not sent: %s', 'cloudscale-devtools' ), $detail ),
                'port_warning'        => $warning,
                'smtp_not_configured' => $smtp_not_configured,
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
     * @since  1.9.4
     * @return void
     */
    public static function email_2fa_confirm_check(): void {
        if ( ! isset( $_GET['csdt_devtools_email_verify'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        if ( ! is_user_logged_in() ) {
            return;
        }

        $token     = sanitize_text_field( wp_unslash( $_GET['csdt_devtools_email_verify'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
        update_user_meta( $user_id, 'csdt_devtools_2fa_email_enabled', '1' );
        delete_user_meta( $user_id, 'csdt_devtools_email_verify_pending' );

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
     * @since  1.9.4
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
     * @since  1.9.4
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
     * @since  1.9.4
     * @return void
     */
    private static function render_smtp_panel(): void {
        $enabled    = get_option( 'csdt_devtools_smtp_enabled',    '0' ) === '1';
        $host       = get_option( 'csdt_devtools_smtp_host',       '' );
        $port       = get_option( 'csdt_devtools_smtp_port',       587 );
        $encryption = get_option( 'csdt_devtools_smtp_encryption', 'tls' );
        $auth       = get_option( 'csdt_devtools_smtp_auth',       '1' ) === '1';
        $user       = get_option( 'csdt_devtools_smtp_user',       '' );
        $has_pass   = '' !== get_option( 'csdt_devtools_smtp_pass', '' );
        $from_email = get_option( 'csdt_devtools_smtp_from_email', '' );
        $from_name  = get_option( 'csdt_devtools_smtp_from_name',  '' );
        ?>

        <!-- ── SMTP Configuration ─────────────────────────────── -->
        <div class="cs-panel" id="cs-panel-smtp">
            <div class="cs-section-header cs-section-header-blue">
                <span>📧 SMTP CONFIGURATION</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Replace PHP mail() with a real SMTP connection', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'smtp', 'SMTP Configuration', [
                    [
                        'name' => 'Enable SMTP',
                        'rec'  => 'Recommended',
                        'desc' => 'Routes all WordPress emails through your own SMTP server instead of the server\'s PHP mail() function. This dramatically improves deliverability and lets you use Gmail, Outlook, or any hosted mail service.',
                    ],
                    [
                        'name' => 'App Passwords',
                        'rec'  => 'Note',
                        'html' => 'Gmail and most modern providers require an <strong>App Password</strong> — a separate password generated specifically for third-party apps — rather than your regular account password. This is required when two-factor authentication (2FA) is enabled on the account.'
                            . '<br><br>'
                            . 'Generate an App Password from your provider\'s security settings and paste it into the Password field below:'
                            . '<br><br>'
                            . '<strong>Gmail</strong> — <a href="https://support.google.com/accounts/answer/185833" target="_blank" rel="noopener noreferrer">support.google.com/accounts/answer/185833</a><br>'
                            . '<strong>Outlook / Microsoft 365</strong> — <a href="https://support.microsoft.com/en-us/account-billing/using-app-passwords-with-apps-that-don-t-support-two-step-verification-5896ed9b-4263-e681-128a-a6f2979a7944" target="_blank" rel="noopener noreferrer">support.microsoft.com — App passwords</a><br>'
                            . '<strong>Yahoo Mail</strong> — <a href="https://help.yahoo.com/kb/generate-third-party-passwords-sln15241.html" target="_blank" rel="noopener noreferrer">help.yahoo.com — Generate app passwords</a><br>'
                            . '<strong>Zoho Mail</strong> — <a href="https://www.zoho.com/mail/help/adminconsole/two-factor-authentication.html" target="_blank" rel="noopener noreferrer">zoho.com/mail/help — Two-factor authentication</a>',
                    ],
                    [
                        'name' => 'Send Test Email',
                        'rec'  => 'Note',
                        'desc' => 'Sends a test message to your admin email using your current saved settings. If it fails, check that your host, port, and encryption match your provider\'s requirements (port 587 + TLS is the safest default), and that you\'re using an App Password where required.',
                    ],
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
     * @since  1.9.4
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
     * @since  1.9.4
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
            $existing_pass = get_option( 'csdt_devtools_smtp_pass', '' );
            if ( $auth === '1' && $new_pass === '' && $existing_pass === '' ) {
                $errors[] = __( 'Password is required when SMTP Authentication is enabled.', 'cloudscale-devtools' );
            }
            if ( ! empty( $errors ) ) {
                wp_send_json_error( implode( ' ', $errors ) );
            }
        }

        update_option( 'csdt_devtools_smtp_enabled',    $enabled );
        update_option( 'csdt_devtools_smtp_host',       $host );
        update_option( 'csdt_devtools_smtp_port',       $port );
        update_option( 'csdt_devtools_smtp_encryption', $encryption );
        update_option( 'csdt_devtools_smtp_auth',       $auth );
        update_option( 'csdt_devtools_smtp_user',       $user );
        update_option( 'csdt_devtools_smtp_from_email', $from_email );
        update_option( 'csdt_devtools_smtp_from_name',  $from_name );

        // Only update password if the user explicitly provided one.
        if ( $new_pass !== '' ) {
            update_option( 'csdt_devtools_smtp_pass', $new_pass );
        }

        wp_send_json_success();
    }

    /**
     * AJAX: sends a test email using current SMTP settings.
     *
     * @since  1.9.4
     * @return void
     */
    public static function ajax_smtp_test(): void {
        check_ajax_referer( self::SMTP_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'type' => 'auth' ], 403 );
        }

        $enabled    = get_option( 'csdt_devtools_smtp_enabled', '0' );
        $host       = trim( (string) get_option( 'csdt_devtools_smtp_host', '' ) );
        $port       = (int) get_option( 'csdt_devtools_smtp_port', 587 );
        $encryption = (string) get_option( 'csdt_devtools_smtp_encryption', 'tls' );
        $auth       = get_option( 'csdt_devtools_smtp_auth', '1' ) === '1';
        $user       = trim( (string) get_option( 'csdt_devtools_smtp_user', '' ) );
        $pass       = (string) get_option( 'csdt_devtools_smtp_pass', '' );

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

            $from_email = get_option( 'csdt_devtools_smtp_from_email', '' ) ?: get_bloginfo( 'admin_email' );
            $from_name  = get_option( 'csdt_devtools_smtp_from_name', '' ) ?: $site;
            $mail->setFrom( $from_email, $from_name );
            $mail->addAddress( $to );
            $mail->isHTML( true );
            $mail->CharSet = 'UTF-8';
            $mail->Subject  = sprintf( '[%s] CloudScale Cyber and Devtools — SMTP Test', $site );
            $mail->Body     = '<p>This is a test email from <strong>CloudScale Cyber and Devtools</strong>.</p>'
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
     * @since  1.9.4
     * @param  \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance (passed by reference).
     * @return void
     */
    public static function phpmailer_configure( $phpmailer ): void {
        $phpmailer->isSMTP();
        $phpmailer->Host      = (string) get_option( 'csdt_devtools_smtp_host', '' );
        $port                 = (int) get_option( 'csdt_devtools_smtp_port', 587 );
        $phpmailer->Port      = $port > 0 ? $port : 587;
        $encryption           = (string) get_option( 'csdt_devtools_smtp_encryption', 'tls' );
        $encryption           = in_array( $encryption, [ 'tls', 'ssl', 'none' ], true ) ? $encryption : 'tls';
        $phpmailer->SMTPSecure = $encryption === 'none' ? '' : $encryption;
        // Default auth to ON — empty/missing option means "never explicitly turned off".
        $auth_val             = get_option( 'csdt_devtools_smtp_auth', '1' );
        $phpmailer->SMTPAuth  = $auth_val !== '0';
        $phpmailer->Username  = (string) get_option( 'csdt_devtools_smtp_user', '' );
        $phpmailer->Password  = (string) get_option( 'csdt_devtools_smtp_pass', '' );
        $phpmailer->SMTPDebug = 0;
    }

    /**
     * Filter: overrides wp_mail_from with configured from email.
     *
     * @since  1.9.4
     * @param  string $email Default from email.
     * @return string
     */
    public static function smtp_from_email( string $email ): string {
        $configured = get_option( 'csdt_devtools_smtp_from_email', '' );
        return $configured ?: $email;
    }

    /**
     * Filter: overrides wp_mail_from_name with configured from name.
     *
     * @since  1.9.4
     * @param  string $name Default from name.
     * @return string
     */
    public static function smtp_from_name( string $name ): string {
        $configured = get_option( 'csdt_devtools_smtp_from_name', '' );
        return $configured ?: $name;
    }

    /* ==================================================================
       EMAIL LOG
       ================================================================== */

    const EMAIL_LOG_OPTION  = 'csdt_devtools_email_log';
    const EMAIL_LOG_MAX     = 100;

    /**
     * wp_mail filter — captures outgoing email details before send.
     *
     * @since  1.9.4
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
            'via'     => ( get_option( 'csdt_devtools_smtp_enabled', '0' ) === '1'
                          && '' !== trim( (string) get_option( 'csdt_devtools_smtp_host', '' ) ) )
                         ? 'smtp' : 'phpmail',
        ];
        return $args;
    }

    /**
     * phpmailer_init (priority 5) — sets the PHPMailer action_function callback
     * so we receive a reliable success/failure signal after every send attempt.
     *
     * @since  1.9.4
     * @param  \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
     * @return void
     */
    public static function smtp_log_set_callback( $phpmailer ): void {
        $phpmailer->action_function = [ __CLASS__, 'smtp_log_on_send' ];
    }

    /**
     * PHPMailer action_function callback — fires after every send attempt.
     *
     * @since  1.9.4
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
     * @since  1.9.4
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
     * @since  1.9.4
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
     * @since  1.9.4
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
     * @since  1.9.4
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

    // ── Prefix migration (cs_ → csdt_devtools_) ───────────────────────────────

    /**
     * One-time migration: renames options and user meta from the old cs_ prefix
     * to csdt_devtools_.  Runs on every load but exits immediately after the first
     * successful run (guarded by a flag option).
     */
    private static function maybe_migrate_prefix(): void {
        if ( get_option( 'csdt_devtools_prefix_migrated' ) ) {
            return;
        }

        // ── Options ──────────────────────────────────────────────────────────
        $option_map = [
            'cs_hide_login'           => 'csdt_devtools_hide_login',
            'cs_login_slug'           => 'csdt_devtools_login_slug',
            'cs_2fa_method'           => 'csdt_devtools_2fa_method',
            'cs_2fa_force_admins'     => 'csdt_devtools_2fa_force_admins',
            'cs_code_default_theme'   => 'csdt_devtools_code_default_theme',
            'cs_code_theme_pair'      => 'csdt_devtools_code_theme_pair',
            'cs_perf_monitor_enabled' => 'csdt_devtools_perf_monitor_enabled',
            'cs_perf_debug_logging'   => 'csdt_devtools_perf_debug_logging',
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
            'cs_passkeys'            => 'csdt_devtools_passkeys',
            'cs_totp_enabled'        => 'csdt_devtools_totp_enabled',
            'cs_totp_secret'         => 'csdt_devtools_totp_secret',
            'cs_totp_secret_pending' => 'csdt_devtools_totp_secret_pending',
            'cs_2fa_email_enabled'   => 'csdt_devtools_2fa_email_enabled',
            'cs_email_verify_pending' => 'csdt_devtools_email_verify_pending',
        ];
        foreach ( $meta_map as $old => $new ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update( $wpdb->usermeta, [ 'meta_key' => $new ], [ 'meta_key' => $old ] );
        }

        update_option( 'csdt_devtools_prefix_migrated', '1' );
    }

    /**
     * One-time migration: renames SMTP options from the cs_devtools_ prefix
     * (missed by the first migration) to csdt_devtools_.
     */
    private static function maybe_migrate_smtp_prefix(): void {
        if ( get_option( 'csdt_devtools_smtp_prefix_migrated' ) ) {
            return;
        }

        $smtp_map = [
            'cs_devtools_smtp_enabled'    => 'csdt_devtools_smtp_enabled',
            'cs_devtools_smtp_host'       => 'csdt_devtools_smtp_host',
            'cs_devtools_smtp_port'       => 'csdt_devtools_smtp_port',
            'cs_devtools_smtp_encryption' => 'csdt_devtools_smtp_encryption',
            'cs_devtools_smtp_auth'       => 'csdt_devtools_smtp_auth',
            'cs_devtools_smtp_user'       => 'csdt_devtools_smtp_user',
            'cs_devtools_smtp_pass'       => 'csdt_devtools_smtp_pass',
            'cs_devtools_smtp_from_email' => 'csdt_devtools_smtp_from_email',
            'cs_devtools_smtp_from_name'  => 'csdt_devtools_smtp_from_name',
        ];
        foreach ( $smtp_map as $old => $new ) {
            $val = get_option( $old );
            if ( $val !== false ) {
                update_option( $new, $val );
                delete_option( $old );
            }
        }

        update_option( 'csdt_devtools_smtp_prefix_migrated', '1' );
    }

    /**
     * One-time migration: renames TOTP/2FA user meta from cs_devtools_ prefix
     * (missed by the first migration which used incorrect short keys) to csdt_devtools_.
     */
    private static function maybe_migrate_usermeta_prefix(): void {
        if ( get_option( 'csdt_devtools_usermeta_prefix_migrated' ) ) {
            return;
        }

        global $wpdb;
        $meta_map = [
            'cs_devtools_totp_enabled'        => 'csdt_devtools_totp_enabled',
            'cs_devtools_totp_secret'         => 'csdt_devtools_totp_secret',
            'cs_devtools_totp_secret_pending' => 'csdt_devtools_totp_secret_pending',
            'cs_devtools_2fa_email_enabled'   => 'csdt_devtools_2fa_email_enabled',
            'cs_devtools_email_verify_pending' => 'csdt_devtools_email_verify_pending',
            'cs_devtools_passkeys'            => 'csdt_devtools_passkeys',
        ];
        foreach ( $meta_map as $old => $new ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update( $wpdb->usermeta, [ 'meta_key' => $new ], [ 'meta_key' => $old ] );
        }

        update_option( 'csdt_devtools_usermeta_prefix_migrated', '1' );
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
        $is_preview = isset( $_GET['csdt_devtools_preview_scheme'] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! $is_preview && ! get_option( self::CUSTOM_404_OPTION, 1 ) ) { return; }

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

        $preview_key   = isset( $_GET['csdt_devtools_preview_scheme'] ) ? sanitize_key( wp_unslash( $_GET['csdt_devtools_preview_scheme'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only palette preview
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
        <button class="cs404-tab" data-game="spaceinvaders">👾 Invaders</button>
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
    <div id="cs404-si-ctrl" class="cs404-miner-ctrl" style="display:none;">
        <button id="cs404-sil" class="cs404-miner-btn">◀</button>
        <button id="cs404-sif" class="cs404-miner-btn">● Fire</button>
        <button id="cs404-sir" class="cs404-miner-btn">▶</button>
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
        register_rest_route( self::HISCORE_NS, '/hiscore/(?P<game>runner|jetpack|racer|miner|asteroids|snake|mrdo)', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'rest_get_hiscore' ],
                // Public leaderboard read — no authentication required.
                'permission_callback' => static fn() => true,
                'args'                => [ 'game' => [ 'required' => true, 'type' => 'string' ] ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'rest_set_hiscore' ],
                // Public score submission — open to guests by design (404 mini-games).
                // CSRF protection is enforced via nonce verification in the callback.
                'permission_callback' => static fn() => true,
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
        $raw  = get_option( 'csdt_devtools_leaderboard_' . $game, '' );
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

        $score_caps = [ 'runner' => 999999, 'jetpack' => 999999, 'racer' => 999999, 'miner' => 2000, 'asteroids' => 999999, 'snake' => 9990, 'mrdo' => 99990 ];
        if ( isset( $score_caps[ $game ] ) && $score > $score_caps[ $game ] ) {
            return new WP_Error( 'score_invalid', __( 'Score exceeds maximum for this game.', 'cloudscale-devtools' ), [ 'status' => 422 ] );
        }

        // Rate limit: max 5 submissions per IP per game per 10 minutes.
        $ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $ip_key = 'csdt_devtools_rl_' . md5( $ip . $game );
        $count  = (int) get_transient( $ip_key );
        if ( $count >= 5 ) {
            return new WP_Error( 'rate_limited', __( 'Too many score submissions. Try again later.', 'cloudscale-devtools' ), [ 'status' => 429 ] );
        }
        set_transient( $ip_key, $count + 1, 600 );

        $raw = get_option( 'csdt_devtools_leaderboard_' . $game, '' );
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
        update_option( 'csdt_devtools_leaderboard_' . $game, wp_json_encode( $lb ), false );
        return rest_ensure_response( [ 'ok' => true, 'leaderboard' => $lb ] );
    }

    /** AJAX handler: saves the 404 enable toggle and colour scheme. */
    public static function ajax_save_404_settings(): void {
        check_ajax_referer( 'csdt_devtools_404_settings', 'nonce' );
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
        $enabled        = (bool) get_option( self::CUSTOM_404_OPTION, 1 );
        ?>
        <div class="cs-panel" id="cs-panel-404">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#f57c00,#e65100);">
                <span>🎮 404 GAMES PAGE</span>
                <?php self::render_explain_btn( '404-games', '404 Games Page', [
                    [ 'name' => 'Enable',        'rec' => 'Toggle', 'desc' => 'When enabled, replaces the default WordPress 404 page with a fun interactive page featuring 7 mini-games: Runner, Jetpack, Racer, Miner, Asteroids, Snake, and Mr. Do!. No theme dependency — works even if the active theme is broken.' ],
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
    private const THUMB_NONCE = 'csdt_devtools_thumbnails';

    private const SOCIAL_PLATFORMS = [
        // target_kb = optimum file size to aim for during generation.
        // max_kb    = platform's hard limit (used only for compatibility warnings).
        'facebook'  => [ 'label' => 'Facebook',    'w' => 1200, 'h' => 630,  'target_kb' => 400, 'max_kb' => 8000 ],
        'twitter'   => [ 'label' => 'X / Twitter', 'w' => 1200, 'h' => 628,  'target_kb' => 400, 'max_kb' => 5000 ],
        'whatsapp'  => [ 'label' => 'WhatsApp',    'w' => 1200, 'h' => 630,  'target_kb' => 200, 'max_kb' => 300  ],
        'linkedin'  => [ 'label' => 'LinkedIn',    'w' => 1200, 'h' => 627,  'target_kb' => 400, 'max_kb' => 5000 ],
        'instagram' => [ 'label' => 'Instagram',   'w' => 1080, 'h' => 1080, 'target_kb' => 400, 'max_kb' => 8000 ],
    ];

    private const SOCIAL_UAS = [
        'WhatsApp'            => 'WhatsApp/2.23.24.82 A',
        'Facebook'            => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
        'Facebot'             => 'Facebot',
        'LinkedInBot'         => 'LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)',
        'Twitterbot'          => 'Twitterbot/1.0',
    ];

    // ─── Panel render ────────────────────────────────────────────────────

    private static function render_thumbnails_panel(): void {
        $cf_zone  = get_option( 'csdt_devtools_cf_zone_id', '' );
        $cf_token = get_option( 'csdt_devtools_cf_api_token', '' );
        $cf_token_masked = $cf_token ? str_repeat( '•', 12 ) . substr( $cf_token, -4 ) : '';
        ?>
        <div class="cs-panel" id="cs-panel-thumbs-checker">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#1565c0,#0d47a1);">
                <span>🔍 URL SOCIAL PREVIEW CHECKER</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Run a full social-preview diagnostic on any URL', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'social-checker', 'URL Social Preview Checker', [
                    [ 'name' => 'HTTPS',                'rec' => 'Required',     'html' => 'Social crawlers refuse to load preview images served over <code>http://</code>. The URL must use <code>https://</code>.' ],
                    [ 'name' => 'HTTP Response',        'rec' => 'Required',     'html' => 'The page must return <code>HTTP 200</code> for the crawler\'s User-Agent. A <code>403</code> or bot-block will prevent any preview from loading.' ],
                    [ 'name' => 'Response Time',        'rec' => 'Recommended',  'html' => 'Crawlers time out after ~<code>3 seconds</code>. Pages that take longer to respond will show no preview — check for slow plugins or uncached pages.' ],
                    [ 'name' => 'og:title',             'rec' => 'Required',     'html' => 'The title shown in the social card. Without <code>og:title</code>, platforms fall back to the page <code>&lt;title&gt;</code> tag or show nothing.' ],
                    [ 'name' => 'og:description',       'rec' => 'Recommended',  'html' => 'The summary text shown under the title. Recommended for all platforms; Twitter/X truncates to ~<code>200</code> chars.' ],
                    [ 'name' => 'og:image',             'rec' => 'Required',     'html' => 'The preview image. Must be an absolute <code>https://</code> URL. Recommended size: <code>1200×630 px</code>, max <code>8 MB</code>. Facebook enforces a minimum of <code>200×200 px</code>.' ],
                    [ 'name' => 'og:image dimensions',  'rec' => 'Recommended',  'html' => '<code>og:image:width</code> and <code>og:image:height</code> tell crawlers the size without downloading the image. Speeds up card rendering and avoids layout shifts.' ],
                    [ 'name' => 'robots.txt',           'rec' => 'Info',         'html' => 'Checks that <code>robots.txt</code> does not block <code>Googlebot</code>, <code>Twitterbot</code>, <code>facebookexternalhit</code>, or other social crawlers from accessing the page.' ],
                    [ 'name' => 'Crawler UA test',      'rec' => 'Info',         'html' => 'Re-fetches the page using each platform\'s real crawler User-Agent string to confirm the page is not blocked by a WAF, Cloudflare rule, or bot-protection plugin.' ],
                ] ); ?>
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
                        <input type="url" id="cs-cf-purge-url" class="cs-input cs-input-light-placeholder" style="max-width:420px;flex:1"
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
                <span>📋 POST SOCIAL PREVIEW SCAN</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Check the last 50 posts — will their images work on each social platform?', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'post-social-scan', 'Post Social Preview Scan', [
                    [ 'name' => 'Facebook',      'rec' => 'Min 200×200',    'html' => 'Checks the WordPress featured image file directly — no live HTTP fetch. Recommended <code>1200×630 px</code>, max <code>8 MB</code>. Optimised versions are auto-generated to <code>/wp-content/uploads/social-formats/</code> when you publish or update a post.' ],
                    [ 'name' => 'X / Twitter',   'rec' => 'Min 280×150',    'html' => '<code>summary_large_image</code> card format. Recommended <code>1200×628 px</code>, max <code>5 MB</code>. Auto-generated at the correct crop on every post save with a new featured image.' ],
                    [ 'name' => 'WhatsApp',      'rec' => 'Max 300 KB',     'html' => 'Strict <code>300 KB</code> hard limit — images over this are <strong>silently hidden</strong> with no error message. The plugin automatically compresses the image at lower JPEG quality until it fits, so your WhatsApp preview will always appear.' ],
                    [ 'name' => 'LinkedIn',      'rec' => 'Min 200×110',    'html' => 'Recommended <code>1200×627 px</code>, max <code>5 MB</code>. Auto-generated with the correct crop. Portrait-oriented or very small images often display poorly in LinkedIn feed cards.' ],
                    [ 'name' => 'Instagram',     'rec' => '1080×1080 sq',   'html' => 'Square <code>1:1</code> format for direct feed post uploads. Min <code>320×320</code>, recommended <code>1080×1080</code>, max <code>8 MB</code>.<br><br><strong>Note:</strong> Instagram does not scrape OG tags for link previews — this format is for direct uploads only.' ],
                    [ 'name' => 'Auto-generate', 'rec' => 'Automatic',      'html' => 'Every time you publish or update a post with a new featured image, the plugin automatically generates correctly sized and compressed images for each enabled platform. Nothing changes if the featured image hasn\'t changed.' ],
                    [ 'name' => 'Fix',           'rec' => 'Manual action',  'html' => 'Manually triggers generation for a single post. Use this to regenerate after changing platform settings, or for posts that existed before auto-generation was enabled.' ],
                    [ 'name' => 'Fix all',            'rec' => 'Manual action',  'html' => 'Runs <strong>Fix</strong> for every post in the current scan results (up to 50). Useful for quickly fixing the posts you just scanned.' ],
                    [ 'name' => 'Fix All Posts on Site', 'rec' => 'Bulk action', 'html' => 'Processes every published post on the entire site in batches of <code>10</code>, generating platform formats for each. Shows live progress (e.g. <code>Fixing 45 / 320</code>). Posts without a featured image are skipped automatically.' ],
                    [ 'name' => 'Re-check',      'rec' => 'Diagnostic',     'html' => 'Runs the full live URL diagnostic (OG tags, robots.txt, crawler UA test) on this specific post URL and scrolls to the URL checker results above.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <p class="cs-hint" style="margin-bottom:10px"><?php esc_html_e( 'Checks the featured image of your last 50 published posts and shows per-platform compatibility. Uses local file data — no live HTTP requests.', 'cloudscale-devtools' ); ?></p>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <button type="button" class="cs-btn-primary" id="cs-thumb-audit-btn" data-mode="recent">📋 <?php esc_html_e( 'Scan Last 50 Posts', 'cloudscale-devtools' ); ?></button>
                    <button type="button" class="cs-btn-primary" id="cs-thumb-audit-top-btn" data-mode="top" style="background:#1565c0">🔥 <?php esc_html_e( 'Scan Top 50 Posts', 'cloudscale-devtools' ); ?></button>
                    <button type="button" class="cs-btn-secondary" id="cs-thumb-fix-all-btn" style="display:none;background:#2271b1;color:#fff;padding:7px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px">🔧 <?php esc_html_e( 'Fix all', 'cloudscale-devtools' ); ?></button>
                    <button type="button" class="cs-btn-secondary" id="cs-thumb-fix-site-btn" style="background:#6a1b9a;color:#fff;padding:7px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px">🌐 <?php esc_html_e( 'Fix All Posts on Site', 'cloudscale-devtools' ); ?></button>
                    <span id="cs-thumb-audit-progress" style="font-size:12px;color:#888"></span>
                </div>
                <div id="cs-thumb-audit-results" style="margin-top:14px;display:none"></div>
            </div>
        </div>

        <?php
        $enabled_platforms = get_option( 'csdt_devtools_social_platforms', array_keys( self::SOCIAL_PLATFORMS ) );
        ?>
        <div class="cs-panel" id="cs-panel-thumbs-platforms">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#00695c,#004d40);">
                <span>🎨 SOCIAL FORMAT SETTINGS</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Auto-generates on every post save — select which platforms to prepare images for', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'social-formats', 'Social Format Settings', [
                    [ 'name' => 'Facebook 1200×630',    'rec' => 'Optimum ~400 KB',  'html' => 'Optimum: <code>1200×630 px</code> at under <code>400 KB</code> JPEG. Hard limit: <code>8 MB</code>. Minimum: <code>200×200 px</code>. Landscape <code>1.91:1</code> ratio — the plugin auto-crops to this exact frame so Facebook always shows your image, not a random one from the page.' ],
                    [ 'name' => 'X / Twitter 1200×628', 'rec' => 'Optimum ~400 KB',  'html' => 'Optimum: <code>1200×628 px</code> at under <code>400 KB</code> JPEG. Hard limit: <code>5 MB</code>. Minimum for large card: <code>280×150 px</code>. Slightly shorter than Facebook — a separate dedicated crop prevents the subject being letterboxed or clipped.' ],
                    [ 'name' => 'WhatsApp 1200×630',    'rec' => 'Optimum ~200 KB',  'html' => 'Optimum: <code>1200×630 px</code> at under <code>200 KB</code> JPEG. Hard limit: <strong><code>300 KB</code></strong> — images over this are <strong>silently dropped</strong> with no error message. The plugin targets <code>200 KB</code> for a safe margin, retrying at lower quality until the file fits.' ],
                    [ 'name' => 'LinkedIn 1200×627',    'rec' => 'Optimum ~400 KB',  'html' => 'Optimum: <code>1200×627 px</code> at under <code>400 KB</code> JPEG. Hard limit: <code>5 MB</code>. Minimum: <code>200×110 px</code>. Landscape cards perform best — portrait images are cropped awkwardly or shown very small in the LinkedIn feed.' ],
                    [ 'name' => 'Instagram 1080×1080',  'rec' => 'Optimum ~400 KB',  'html' => 'Optimum: <code>1080×1080 px</code> square at under <code>400 KB</code> JPEG. Hard limit: <code>8 MB</code>. Minimum: <code>320×320 px</code>. Square <code>1:1</code> crop for direct feed uploads — Instagram does not scrape OG tags for link preview cards.' ],
                    [ 'name' => 'Auto-generation',      'rec' => 'How it works',     'html' => 'Every time you publish or update a post with a new featured image, the plugin automatically generates all enabled platform formats at the optimum size and quality — not just within the hard limit. Unchanged images are skipped. <strong>Originals are never modified.</strong>' ],
                    [ 'name' => 'Save Settings',        'rec' => 'Required once',    'html' => 'Saves which platforms are enabled. Only checked platforms are generated on post save. Changes take effect on the next publish or update.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <p class="cs-hint" style="margin-bottom:14px"><?php esc_html_e( 'Social format images are generated automatically every time you publish or update a post with a new featured image. You can also generate them manually using the Fix button in the scan above. Original images are never modified.', 'cloudscale-devtools' ); ?></p>
                <div class="cs-platform-grid">
                    <?php foreach ( self::SOCIAL_PLATFORMS as $key => $p ) :
                        $checked   = in_array( $key, $enabled_platforms, true );
                        $opt_kb    = $p['target_kb'];
                        $size_note = $opt_kb >= 1000 ? 'optimum ~' . ( $opt_kb / 1000 ) . ' MB' : 'optimum ~' . $opt_kb . ' KB';
                        ?>
                        <label class="cs-platform-card <?php echo $checked ? 'cs-platform-checked' : ''; ?>">
                            <input type="checkbox" name="cs_social_platform[]" value="<?php echo esc_attr( $key ); ?>"
                                   class="cs-platform-cb" <?php checked( $checked ); ?>>
                            <div class="cs-platform-card-body">
                                <span class="cs-platform-name"><?php echo esc_html( $p['label'] ); ?></span>
                                <span class="cs-platform-dims"><?php echo esc_html( $p['w'] . '×' . $p['h'] . 'px' ); ?></span>
                                <span class="cs-platform-limit"><?php echo esc_html( $size_note ); ?></span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:14px;display:flex;align-items:center;gap:10px">
                    <button type="button" class="cs-btn-primary" id="cs-platform-save-btn">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
                    <span class="cs-settings-saved" id="cs-platform-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                </div>
            </div>
        </div>

        <?php
        // CSS for this tab is injected via wp_add_inline_style() in enqueue_admin_assets().
        // See get_thumbnails_admin_css() for the ruleset.
    }

    /**
     * Validates that a URL does not point to a private/reserved IP address.
     *
     * Prevents SSRF attacks from admin-initiated URL checks. Only allows
     * publicly routable destinations; rejects localhost, RFC-1918 ranges,
     * link-local, and other reserved blocks.
     *
     * @param  string $url The URL to validate.
     * @return bool         True if safe to fetch, false if internal/reserved.
     */
    private static function is_safe_external_url( string $url ): bool {
        if ( ! wp_http_validate_url( $url ) ) {
            return false;
        }
        $parsed = wp_parse_url( $url );
        $host   = $parsed['host'] ?? '';
        if ( ! $host ) {
            return false;
        }
        // Resolve hostname to IP.
        $ip = gethostbyname( $host );
        // gethostbyname returns the original hostname unchanged on failure.
        if ( $ip === $host && ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            // Could not resolve — reject.
            return false;
        }
        // Reject private/reserved ranges.
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
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
        if ( ! self::is_safe_external_url( $url ) ) {
            wp_send_json_error( [ 'message' => 'URL must be a publicly accessible address.' ] );
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
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [ [ 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ] ],
        ] );
        $results = [];
        foreach ( $posts as $post ) {
            $url      = get_permalink( $post );
            $diag     = self::social_diagnose_url( $url );
            $thumb_id = get_post_thumbnail_id( $post->ID );
            $attach_id = $thumb_id ? (int) $thumb_id : null;
            $can_fix   = false;
            if ( $attach_id ) {
                $file    = get_attached_file( $attach_id );
                $can_fix = $file && file_exists( $file );
            }
            $results[] = [
                'id'        => $post->ID,
                'title'     => get_the_title( $post ),
                'url'       => $url,
                'totals'    => $diag['totals'],
                'og_image'  => $diag['og_image'] ?? '',
                'img_kb'    => $diag['img_kb'] ?? null,
                'img_w'     => $diag['img_w'] ?? null,
                'img_h'     => $diag['img_h'] ?? null,
                'attach_id' => $attach_id,
                'can_fix'   => $can_fix,
            ];
        }
        wp_send_json_success( $results );
    }

    // ─── Per-platform compatibility check ────────────────────────────────

    private static function check_platform_compat( int $width, int $height, float $kb, bool $https ): array {
        $r = [];

        // Facebook — optimum ~400 KB, hard limit 8 MB, min 200×200, ideal 1200×630
        if ( ! $https ) {
            $r['facebook'] = [ 'status' => 'fail', 'msg' => 'Image must be HTTPS' ];
        } elseif ( $width < 200 || $height < 200 ) {
            $r['facebook'] = [ 'status' => 'fail', 'msg' => 'Too small — minimum 200×200 px' ];
        } elseif ( $kb > 8000 ) {
            $r['facebook'] = [ 'status' => 'fail', 'msg' => 'Too large — hard limit 8 MB' ];
        } elseif ( $width < 1200 || $height < 630 ) {
            $r['facebook'] = [ 'status' => 'warn', 'msg' => 'Below optimum 1200×630 — Fix will crop and resize to the ideal size' ];
        } elseif ( $kb > 400 ) {
            $r['facebook'] = [ 'status' => 'warn', 'msg' => "{$kb} KB — above optimum ~400 KB. Fix will compress to the ideal size" ];
        } else {
            $r['facebook'] = [ 'status' => 'pass', 'msg' => 'Ready — optimum size and quality' ];
        }

        // X / Twitter — optimum ~400 KB, hard limit 5 MB, min 280×150, ideal 1200×628
        if ( ! $https ) {
            $r['twitter'] = [ 'status' => 'fail', 'msg' => 'Image must be HTTPS' ];
        } elseif ( $width < 280 || $height < 150 ) {
            $r['twitter'] = [ 'status' => 'fail', 'msg' => 'Too small — minimum 280×150 px for large card' ];
        } elseif ( $kb > 5000 ) {
            $r['twitter'] = [ 'status' => 'fail', 'msg' => 'Too large — hard limit 5 MB' ];
        } elseif ( $width < 1200 || $height < 628 ) {
            $r['twitter'] = [ 'status' => 'warn', 'msg' => 'Below optimum 1200×628 — Fix will crop and resize to the ideal size' ];
        } elseif ( $kb > 400 ) {
            $r['twitter'] = [ 'status' => 'warn', 'msg' => "{$kb} KB — above optimum ~400 KB. Fix will compress to the ideal size" ];
        } else {
            $r['twitter'] = [ 'status' => 'pass', 'msg' => 'Ready — optimum size and quality' ];
        }

        // WhatsApp — optimum ~200 KB, hard limit 300 KB (images over this are silently hidden)
        if ( ! $https ) {
            $r['whatsapp'] = [ 'status' => 'fail', 'msg' => 'Image must be HTTPS' ];
        } elseif ( $kb > 300 ) {
            $r['whatsapp'] = [ 'status' => 'fail', 'msg' => "{$kb} KB — over 300 KB hard limit. Preview will be silently hidden. Fix will compress below the limit" ];
        } elseif ( $width < 1200 || $height < 630 ) {
            $r['whatsapp'] = [ 'status' => 'warn', 'msg' => 'Below optimum 1200×630 — Fix will crop, resize, and compress to under 200 KB' ];
        } elseif ( $kb > 200 ) {
            $r['whatsapp'] = [ 'status' => 'warn', 'msg' => "{$kb} KB — above optimum ~200 KB (close to 300 KB hard limit). Fix will compress to the safe target" ];
        } else {
            $r['whatsapp'] = [ 'status' => 'pass', 'msg' => 'Ready — within optimum 200 KB target' ];
        }

        // LinkedIn — optimum ~400 KB, hard limit 5 MB, min 200×110, ideal 1200×627
        if ( ! $https ) {
            $r['linkedin'] = [ 'status' => 'fail', 'msg' => 'Image must be HTTPS' ];
        } elseif ( $width < 200 || $height < 110 ) {
            $r['linkedin'] = [ 'status' => 'fail', 'msg' => 'Too small — minimum 200×110 px' ];
        } elseif ( $kb > 5000 ) {
            $r['linkedin'] = [ 'status' => 'fail', 'msg' => 'Too large — hard limit 5 MB' ];
        } elseif ( $width < 1200 || $height < 627 ) {
            $r['linkedin'] = [ 'status' => 'warn', 'msg' => 'Below optimum 1200×627 — Fix will crop and resize to the ideal size' ];
        } elseif ( $kb > 400 ) {
            $r['linkedin'] = [ 'status' => 'warn', 'msg' => "{$kb} KB — above optimum ~400 KB. Fix will compress to the ideal size" ];
        } else {
            $r['linkedin'] = [ 'status' => 'pass', 'msg' => 'Ready — optimum size and quality' ];
        }

        // Instagram — optimum ~400 KB, hard limit 8 MB, min 320×320, ideal 1080×1080 square
        if ( $width < 320 || $height < 320 ) {
            $r['instagram'] = [ 'status' => 'fail', 'msg' => 'Too small — minimum 320×320 px' ];
        } elseif ( $kb > 8000 ) {
            $r['instagram'] = [ 'status' => 'fail', 'msg' => 'Too large — hard limit 8 MB' ];
        } elseif ( $width < 1080 || $height < 1080 ) {
            $r['instagram'] = [ 'status' => 'warn', 'msg' => 'Below optimum 1080×1080 square — Fix will crop to a square and resize to the ideal size' ];
        } elseif ( $kb > 400 ) {
            $r['instagram'] = [ 'status' => 'warn', 'msg' => "{$kb} KB — above optimum ~400 KB. Fix will compress to the ideal size" ];
        } else {
            $r['instagram'] = [ 'status' => 'pass', 'msg' => 'Ready — optimum size and quality' ];
        }

        return $r;
    }

    // ─── AJAX: scan featured images for last 50 posts ─────────────────────

    public static function ajax_social_scan_media(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $mode = isset( $_POST['mode'] ) && $_POST['mode'] === 'top'
            ? 'top' : 'recent';

        // "Top posts" — order by view count meta if available, else comment count.
        $view_meta_keys = [ 'post_views_count', 'views', '_post_views', 'wpb_post_views_count', 'jetpack-views' ];
        $view_meta_found = false;
        if ( $mode === 'top' ) {
            foreach ( $view_meta_keys as $mk ) {
                $sample = get_posts( [ 'post_type' => 'post', 'posts_per_page' => 1, 'meta_key' => $mk, 'orderby' => 'meta_value_num', 'order' => 'DESC', 'fields' => 'ids' ] );
                if ( ! empty( $sample ) ) {
                    $view_meta_found = $mk;
                    break;
                }
            }
        }

        $query_args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'fields'         => 'ids',
        ];

        if ( $mode === 'top' ) {
            if ( $view_meta_found ) {
                $query_args['meta_key'] = $view_meta_found;
                $query_args['orderby']  = 'meta_value_num';
                $query_args['order']    = 'DESC';
            } else {
                // Fall back to comment count as a proxy for popularity.
                $query_args['orderby'] = 'comment_count';
                $query_args['order']   = 'DESC';
            }
        } else {
            $query_args['orderby'] = 'date';
            $query_args['order']   = 'DESC';
        }

        $posts = get_posts( $query_args );

        $results = [];

        foreach ( $posts as $post_id ) {
            $post_url  = get_permalink( $post_id );
            $thumb_id  = get_post_thumbnail_id( $post_id );

            if ( ! $thumb_id ) {
                // No featured image — all platforms fail.
                $all_fail = [];
                foreach ( array_keys( self::SOCIAL_PLATFORMS ) as $key ) {
                    $all_fail[ $key ] = [ 'status' => 'fail', 'msg' => 'No featured image set' ];
                }
                $results[] = [
                    'post_id'   => $post_id,
                    'title'     => get_the_title( $post_id ),
                    'post_url'  => $post_url,
                    'img_url'   => '',
                    'attach_id' => null,
                    'width'     => 0,
                    'height'    => 0,
                    'size_kb'   => null,
                    'status'    => 'fail',
                    'no_image'  => true,
                    'platforms' => $all_fail,
                    'can_fix'   => false,
                ];
                continue;
            }

            $attach_id = (int) $thumb_id;
            $img_url   = wp_get_attachment_url( $attach_id );
            $meta      = wp_get_attachment_metadata( $attach_id );
            $width     = (int) ( $meta['width']  ?? 0 );
            $height    = (int) ( $meta['height'] ?? 0 );
            $kb        = null;
            $can_fix   = false;
            $https     = $img_url && str_starts_with( $img_url, 'https://' );

            $file = get_attached_file( $attach_id );
            if ( $file && file_exists( $file ) ) {
                $bytes   = (int) filesize( $file );
                $kb      = round( $bytes / 1024, 1 );
                $ext     = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
                $can_fix = in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp' ], true );
            }

            $platforms = self::check_platform_compat( $width, $height, (float) ( $kb ?? 0 ), $https );

            // Derive overall status from worst platform result.
            $status = 'pass';
            foreach ( $platforms as $pc ) {
                if ( $pc['status'] === 'fail' )      { $status = 'fail'; break; }
                if ( $pc['status'] === 'warn' )       { $status = 'warn'; }
            }

            $results[] = [
                'post_id'   => $post_id,
                'title'     => get_the_title( $post_id ),
                'post_url'  => $post_url,
                'img_url'   => $img_url,
                'attach_id' => $attach_id,
                'width'     => $width,
                'height'    => $height,
                'size_kb'   => $kb,
                'status'    => $status,
                'no_image'  => false,
                'platforms' => $platforms,
                'can_fix'   => $can_fix,
            ];
        }

        $counts    = array_count_values( array_column( $results, 'status' ) );
        $sort_note = '';
        if ( $mode === 'top' ) {
            $sort_note = $view_meta_found
                ? sprintf( __( 'sorted by view count (%s)', 'cloudscale-devtools' ), $view_meta_found )
                : __( 'sorted by comment count (no view-count plugin detected)', 'cloudscale-devtools' );
        }
        wp_send_json_success( [
            'total_scanned' => count( $results ),
            'pass'          => $counts['pass'] ?? 0,
            'warn'          => $counts['warn'] ?? 0,
            'fail'          => $counts['fail'] ?? 0,
            'mode'          => $mode,
            'sort_note'     => $sort_note,
            'posts'         => $results,
        ] );
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

    // ─── AJAX: save platform settings ────────────────────────────────────

    public static function ajax_social_platform_save(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $raw      = isset( $_POST['platforms'] ) ? (array) $_POST['platforms'] : [];
        $allowed  = array_keys( self::SOCIAL_PLATFORMS );
        $filtered = array_values( array_intersect( $raw, $allowed ) );
        update_option( 'csdt_devtools_social_platforms', $filtered );
        wp_send_json_success( [ 'saved' => $filtered ] );
    }

    // ─── Shared: generate per-platform social format images ─────────────

    private static function generate_social_formats_for_post( int $post_id ): ?array {
        $thumb_id = get_post_thumbnail_id( $post_id );
        if ( ! $thumb_id ) return null;

        $source_file = get_attached_file( (int) $thumb_id );
        if ( ! $source_file || ! file_exists( $source_file ) ) return null;

        $enabled = get_option( 'csdt_devtools_social_platforms', array_keys( self::SOCIAL_PLATFORMS ) );
        if ( empty( $enabled ) ) return null;

        $upload   = wp_upload_dir();
        $dest_dir = trailingslashit( $upload['basedir'] ) . 'social-formats/' . $post_id;
        $dest_url = trailingslashit( $upload['baseurl'] ) . 'social-formats/' . $post_id;
        wp_mkdir_p( $dest_dir );

        $ext = strtolower( pathinfo( $source_file, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp' ], true ) ) {
            $ext = 'jpg';
        }
        // Convert PNG/WebP to JPEG so lossy quality reduction can actually shrink the file.
        if ( in_array( $ext, [ 'png', 'webp' ], true ) ) {
            $ext = 'jpg';
        }

        $results = [];

        foreach ( self::SOCIAL_PLATFORMS as $key => $platform ) {
            if ( ! in_array( $key, $enabled, true ) ) continue;

            $filename = "{$dest_dir}/{$key}.{$ext}";
            $file_url = "{$dest_url}/{$key}.{$ext}";
            $quality  = 90;

            for ( $attempt = 0; $attempt < 4; $attempt++ ) {
                $editor = wp_get_image_editor( $source_file );
                if ( is_wp_error( $editor ) ) {
                    $results[ $key ] = [ 'success' => false, 'label' => $platform['label'], 'error' => $editor->get_error_message() ];
                    continue 2;
                }
                $editor->resize( $platform['w'], $platform['h'], true );
                $editor->set_quality( $quality );
                $saved = $editor->save( $filename );
                if ( is_wp_error( $saved ) ) {
                    $results[ $key ] = [ 'success' => false, 'label' => $platform['label'], 'error' => $saved->get_error_message() ];
                    continue 2;
                }
                $kb = round( (int) filesize( $filename ) / 1024, 1 );
                if ( $kb <= $platform['target_kb'] || $quality <= 55 ) break;
                $quality -= 10;
            }

            $kb          = round( (int) filesize( $filename ) / 1024, 1 );
            $under_limit = $kb <= $platform['target_kb'];

            $results[ $key ] = [
                'success'     => true,
                'label'       => $platform['label'],
                'w'           => $platform['w'],
                'h'           => $platform['h'],
                'kb'          => $kb,
                'max_kb'      => $platform['max_kb'],
                'under_limit' => $under_limit,
                'url'         => $file_url,
                'preview_url' => $file_url . '?v=' . time(),
            ];
        }

        update_post_meta( $post_id, '_csdt_social_formats', $results );
        return $results;
    }

    // ─── Hook: auto-generate on post publish / update ────────────────────

    public static function on_post_saved( int $post_id, \WP_Post $post, bool $update ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) )                 return;
        if ( $post->post_status !== 'publish' )                return;

        $thumb_id = (int) get_post_thumbnail_id( $post_id );
        if ( ! $thumb_id ) return;

        // Skip if the thumbnail hasn't changed since last generation.
        $last_thumb = (int) get_post_meta( $post_id, '_csdt_social_formats_thumb_id', true );
        if ( $last_thumb === $thumb_id ) return;

        $results = self::generate_social_formats_for_post( $post_id );
        if ( $results === null ) return;

        update_post_meta( $post_id, '_csdt_social_formats_thumb_id', $thumb_id );

        // Store for admin notice on next page load.
        $user_id = get_current_user_id();
        set_transient( "cs_sfmt_{$user_id}_{$post_id}", $results, 120 );
    }

    // ─── Admin notice: shown after auto-generation ───────────────────────

    public static function social_format_admin_notice(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->base !== 'post' ) return;

        $post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
        if ( ! $post_id ) return;

        $user_id = get_current_user_id();
        $results = get_transient( "cs_sfmt_{$user_id}_{$post_id}" );
        if ( ! $results ) return;
        delete_transient( "cs_sfmt_{$user_id}_{$post_id}" );

        $ok_labels = [];
        $fail_labels = [];
        foreach ( $results as $r ) {
            if ( ! empty( $r['success'] ) ) {
                $size_note = $r['under_limit'] ? '' : ' ⚠';
                $ok_labels[] = $r['label'] . ' (' . $r['w'] . '×' . $r['h'] . ', ' . $r['kb'] . ' KB' . $size_note . ')';
            } else {
                $fail_labels[] = $r['label'];
            }
        }
        if ( empty( $ok_labels ) ) return;

        echo '<div class="notice notice-success is-dismissible" style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px">';
        echo '<span style="font-size:20px;line-height:1.4">🎨</span>';
        echo '<div><strong>' . esc_html__( 'Social format images generated automatically', 'cloudscale-devtools' ) . '</strong><br>';
        echo '<span style="font-size:12px;color:#50575e">' . esc_html( implode( ' &nbsp;·&nbsp; ', $ok_labels ) ) . '</span>';
        if ( ! empty( $fail_labels ) ) {
            echo '<br><span style="font-size:12px;color:#8c2020">✘ Failed: ' . esc_html( implode( ', ', $fail_labels ) ) . '</span>';
        }
        echo '</div></div>';
    }

    // ─── AJAX: generate per-platform social formats ───────────────────────

    public static function ajax_social_generate_formats(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'No post ID.' ] );
        }
        if ( ! get_post_thumbnail_id( $post_id ) ) {
            wp_send_json_error( [ 'message' => 'No featured image set for this post.' ] );
        }
        $results = self::generate_social_formats_for_post( $post_id );
        if ( $results === null ) {
            wp_send_json_error( [ 'message' => 'Could not generate formats — check the featured image file and platform settings.' ] );
        }
        // Mark as up-to-date so the save hook won't re-run for this thumbnail.
        update_post_meta( $post_id, '_csdt_social_formats_thumb_id', (int) get_post_thumbnail_id( $post_id ) );
        wp_send_json_success( $results );
    }

    // ─── AJAX: diagnose social formats for a post ────────────────────────
    // Checks: (1) what's stored in meta, (2) whether image files exist on disk,
    // (3) whether image URLs are reachable by each crawler UA.

    public static function ajax_social_diagnose_formats(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'No post ID.' ] );
        }

        $result = [];

        // ── 1. Meta state ────────────────────────────────────────────────
        $formats   = get_post_meta( $post_id, '_csdt_social_formats', true );
        $old_formats = get_post_meta( $post_id, '_cs_social_formats', true );
        $thumb_id  = (int) get_post_meta( $post_id, '_csdt_social_formats_thumb_id', true );
        $current_thumb = (int) get_post_thumbnail_id( $post_id );

        $result['meta'] = [
            'has_new_key'    => ! empty( $formats ),
            'has_old_key'    => ! empty( $old_formats ),
            'thumb_id_saved' => $thumb_id,
            'thumb_id_now'   => $current_thumb,
            'thumb_stale'    => $thumb_id !== $current_thumb,
            'no_thumbnail'   => ! $current_thumb,
        ];

        if ( empty( $formats ) && ! empty( $old_formats ) ) {
            $formats = $old_formats;
            $result['meta']['using_old_key'] = true;
        }

        // ── 2. Per-platform: file existence + URL reachability ───────────
        $upload   = wp_upload_dir();
        $dest_dir = trailingslashit( $upload['basedir'] ) . 'social-formats/' . $post_id;

        $test_uas = [
            'LinkedInBot' => 'LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)',
            'Facebook'    => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
            'Twitterbot'  => 'Twitterbot/1.0',
        ];

        $platforms_out = [];
        foreach ( self::SOCIAL_PLATFORMS as $key => $p ) {
            $meta_entry = $formats[ $key ] ?? null;
            $entry = [
                'label'       => $p['label'],
                'meta_status' => 'missing',
                'url'         => null,
                'file_exists' => false,
                'ua_results'  => [],
            ];

            if ( $meta_entry !== null ) {
                if ( ! empty( $meta_entry['success'] ) ) {
                    $entry['meta_status'] = 'ok';
                    $entry['url']         = $meta_entry['url'] ?? null;
                    $entry['kb']          = $meta_entry['kb'] ?? null;
                    $entry['w']           = $meta_entry['w'] ?? null;
                    $entry['h']           = $meta_entry['h'] ?? null;
                } else {
                    $entry['meta_status'] = 'failed';
                    $entry['error']       = $meta_entry['error'] ?? 'Unknown error';
                }
            }

            // Check file on disk (try .jpg and .png).
            foreach ( [ 'jpg', 'png', 'webp' ] as $ext ) {
                $path = "{$dest_dir}/{$key}.{$ext}";
                if ( file_exists( $path ) ) {
                    $entry['file_exists'] = true;
                    $entry['file_path']   = $path;
                    $entry['file_kb']     = round( filesize( $path ) / 1024, 1 );
                    break;
                }
            }

            // Test URL reachability with crawler UAs (only if a URL is stored).
            if ( ! empty( $entry['url'] ) ) {
                foreach ( $test_uas as $ua_label => $ua_string ) {
                    $resp = wp_remote_head( $entry['url'], [
                        'user-agent'  => $ua_string,
                        'timeout'     => 8,
                        'redirection' => 3,
                    ] );
                    if ( is_wp_error( $resp ) ) {
                        $entry['ua_results'][ $ua_label ] = [ 'code' => 0, 'ok' => false, 'error' => $resp->get_error_message() ];
                    } else {
                        $code = (int) wp_remote_retrieve_response_code( $resp );
                        $entry['ua_results'][ $ua_label ] = [ 'code' => $code, 'ok' => $code === 200 ];
                    }
                }
            }

            $platforms_out[ $key ] = $entry;
        }

        $result['platforms'] = $platforms_out;

        // ── 3. What og:image would each crawler see ──────────────────────
        $post_url = get_permalink( $post_id );
        $og_seen = [];
        foreach ( $test_uas as $ua_label => $ua_string ) {
            $resp = wp_remote_get( $post_url, [
                'user-agent'  => $ua_string,
                'timeout'     => 10,
                'redirection' => 5,
            ] );
            if ( is_wp_error( $resp ) ) {
                $og_seen[ $ua_label ] = [ 'ok' => false, 'error' => $resp->get_error_message() ];
                continue;
            }
            $code = (int) wp_remote_retrieve_response_code( $resp );
            $body = wp_remote_retrieve_body( $resp );
            preg_match( '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']|<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/', $body, $m );
            $og_url = $m[1] ?? $m[2] ?? null;
            $og_seen[ $ua_label ] = [
                'ok'     => $code === 200,
                'code'   => $code,
                'og_url' => $og_url,
                'has_og' => ! empty( $og_url ),
            ];
        }
        $result['og_seen'] = $og_seen;

        wp_send_json_success( $result );
    }

    // ─── AJAX: batch fix all posts ────────────────────────────────────────

    public static function ajax_social_fix_all_batch(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $offset     = absint( $_POST['offset']     ?? 0 );
        $batch_size = 10; // process 10 per request to avoid timeouts

        $total = (int) wp_count_posts( 'post' )->publish;

        $posts = get_posts( [
            'post_type'        => 'post',
            'post_status'      => 'publish',
            'posts_per_page'   => $batch_size,
            'offset'           => $offset,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'fields'           => 'ids',
            'suppress_filters' => false,
        ] );

        $batch_results = [];
        foreach ( $posts as $post_id ) {
            $thumb_id = get_post_thumbnail_id( $post_id );
            if ( ! $thumb_id ) {
                $batch_results[] = [ 'post_id' => $post_id, 'skipped' => true, 'reason' => 'no_thumbnail' ];
                continue;
            }
            $file = get_attached_file( (int) $thumb_id );
            if ( ! $file || ! file_exists( $file ) ) {
                $batch_results[] = [ 'post_id' => $post_id, 'skipped' => true, 'reason' => 'file_missing' ];
                continue;
            }
            $results = self::generate_social_formats_for_post( $post_id );
            if ( $results ) {
                update_post_meta( $post_id, '_csdt_social_formats_thumb_id', (int) $thumb_id );
            }
            $batch_results[] = [ 'post_id' => $post_id, 'skipped' => false, 'ok' => $results !== null ];
        }

        $next_offset = $offset + count( $posts );
        wp_send_json_success( [
            'total'        => $total,
            'offset'       => $offset,
            'next_offset'  => $next_offset,
            'has_more'     => $next_offset < $total,
            'batch'        => $batch_results,
        ] );
    }

    // ─── Crawler UA detection: serve platform-specific og:image ──────────

    public static function output_crawler_og_image(): void {
        if ( ! is_singular( 'post' ) ) return;

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ( ! $ua ) return;

        $platform = null;
        if ( str_contains( $ua, 'WhatsApp' ) )                                          $platform = 'whatsapp';
        elseif ( str_contains( $ua, 'facebookexternalhit' ) || str_contains( $ua, 'Facebot' ) ) $platform = 'facebook';
        elseif ( str_contains( $ua, 'Twitterbot' ) )                                    $platform = 'twitter';
        elseif ( str_contains( $ua, 'LinkedInBot' ) )                                   $platform = 'linkedin';
        elseif ( str_contains( $ua, 'Instagram' ) )                                     $platform = 'instagram';

        if ( ! $platform ) return;

        $post_id = get_the_ID();
        if ( ! $post_id ) return;

        $formats = get_post_meta( $post_id, '_csdt_social_formats', true );
        // Backward compat: fall back to old meta key from before the cs_ → csdt_ rename.
        if ( empty( $formats ) ) {
            $formats = get_post_meta( $post_id, '_cs_social_formats', true );
        }
        if ( empty( $formats[ $platform ]['url'] ) ) return;

        $img_url = esc_url( $formats[ $platform ]['url'] );
        $p       = self::SOCIAL_PLATFORMS[ $platform ];

        // Output early — this fires at priority 1 so it lands before SEO plugin tags.
        // Duplicate og:image tags are fine; the first one is used by most crawlers.
        echo "\n<!-- CloudScale: platform-specific og:image for {$platform} -->\n";
        echo '<meta property="og:image" content="' . $img_url . '" />' . "\n";
        echo '<meta property="og:image:width" content="' . (int) $p['w'] . '" />' . "\n";
        echo '<meta property="og:image:height" content="' . (int) $p['h'] . '" />' . "\n";
    }

    // ─── AJAX: Cloudflare crawler UA test ────────────────────────────────

    public static function ajax_social_cf_test(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : home_url( '/' );
        if ( ! self::is_safe_external_url( $url ) ) {
            wp_send_json_error( [ 'message' => 'URL must be a publicly accessible address.' ] );
        }
        wp_send_json_success( self::social_test_crawlers( $url ) );
    }

    // ─── AJAX: Cloudflare cache purge ────────────────────────────────────

    public static function ajax_cf_purge(): void {
        check_ajax_referer( self::THUMB_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $zone_id = get_option( 'csdt_devtools_cf_zone_id', '' );
        $token   = get_option( 'csdt_devtools_cf_api_token', '' );
        if ( ! $zone_id || ! $token ) {
            wp_send_json_error( [ 'message' => __( 'Cloudflare Zone ID and API Token are required. Please save them above.', 'cloudscale-devtools' ) ] );
        }
        $purge_url = isset( $_POST['purge_url'] ) ? esc_url_raw( wp_unslash( $_POST['purge_url'] ) ) : '';
        // Ensure purge_url belongs to this site — prevents purging arbitrary Cloudflare-cached URLs.
        if ( $purge_url && strpos( $purge_url, home_url() ) !== 0 ) {
            wp_send_json_error( [ 'message' => __( 'URL must belong to this site.', 'cloudscale-devtools' ) ] );
        }
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
        update_option( 'csdt_devtools_cf_zone_id', $zone_id );
        if ( $token !== '' ) {
            update_option( 'csdt_devtools_cf_api_token', $token );
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
                : [ 'type' => 'fail', 'msg' => 'URL uses HTTP — WhatsApp requires HTTPS for link previews.', 'fix' => 'Install an SSL certificate (Let\'s Encrypt is free) and update your WordPress Address and Site Address in Settings → General to use https://. Add a redirect rule to force HTTP → HTTPS.' ] ],
        ];

        // 2. HTTP response (WhatsApp UA)
        $head = wp_remote_head( $url, [ 'user-agent' => $wa_ua, 'redirection' => 5, 'timeout' => 12, 'sslverify' => true ] );
        $http_ok = false;
        if ( is_wp_error( $head ) ) {
            $sections[] = [ 'title' => 'HTTP Response', 'results' => [ [ 'type' => 'fail', 'msg' => 'Could not connect: ' . $head->get_error_message() ] ] ];
        } else {
            $code = wp_remote_retrieve_response_code( $head );
            $http_ok = ( $code === 200 );
            $is_redirect = in_array( $code, [ 301, 302, 307, 308 ], true );
            $sections[] = [ 'title' => 'HTTP Response (WhatsApp UA)', 'results' => [ $code === 200
                ? [ 'type' => 'pass', 'msg' => 'HTTP 200 OK' ]
                : [ 'type' => $is_redirect ? 'warn' : 'fail',
                    'msg'  => "HTTP $code — " . ( $is_redirect ? 'Redirect (crawlers follow, but adds latency)' : 'Non-200 response; crawler may not read OG tags' ),
                    'fix'  => $is_redirect
                        ? 'Update og:url and your canonical tag to point directly to the final URL to avoid the redirect chain.'
                        : 'Check your WAF, Cloudflare firewall rules, or bot-protection plugins — they may be blocking the WhatsApp crawler User-Agent. Add facebookexternalhit, WhatsApp, Twitterbot, and LinkedInBot to your allowlist.' ] ] ];
        }

        // 3. Fetch HTML + measure response time
        $start = microtime( true );
        $resp  = wp_remote_get( $url, [ 'user-agent' => $wa_ua, 'redirection' => 5, 'timeout' => 18 ] );
        $elapsed = round( microtime( true ) - $start, 2 );
        $html = is_wp_error( $resp ) ? '' : wp_remote_retrieve_body( $resp );

        $sections[] = [ 'title' => 'Response Time', 'results' => [ $elapsed < 3.0
            ? [ 'type' => 'pass', 'msg' => "{$elapsed}s — within 3s crawler timeout" ]
            : ( $elapsed < 5.0
                ? [ 'type' => 'warn', 'msg' => "{$elapsed}s — approaching WhatsApp 3–5s timeout", 'fix' => 'Enable a page caching plugin (e.g. WP Super Cache or W3 Total Cache) and enable Cloudflare\'s HTML caching. Crawlers hit cold pages — caching ensures they get a fast response every time.' ]
                : [ 'type' => 'fail', 'msg' => "{$elapsed}s — exceeds 5s; crawler will likely abort before reading OG tags", 'fix' => 'Enable full-page caching immediately. Check for slow database queries using the CS Monitor DB tab, deactivate heavy plugins, and enable Cloudflare in front of the origin server.' ] ) ] ];

        // 4. OG tags
        $og_results = [];
        $og_fixes = [
            'og:title'       => 'Add <meta property="og:title" content="Your Page Title"> to the <head>. Use an SEO plugin like Yoast or Rank Math — they generate this automatically from your post title.',
            'og:description' => 'Add <meta property="og:description" content="Your description (max ~200 chars)">. Most SEO plugins set this from the meta description field on each post/page.',
            'og:image'       => 'Add <meta property="og:image" content="https://yoursite.com/image.jpg">. Use a 1200×630px JPEG/PNG under 300 KB. Set a site-wide fallback in your SEO plugin settings.',
            'og:url'         => 'Add <meta property="og:url" content="https://yoursite.com/this-page/"> using the canonical URL of this page.',
            'og:type'        => 'Add <meta property="og:type" content="website"> (or "article" for blog posts). Most SEO plugins set this automatically.',
        ];
        foreach ( [ 'og:title', 'og:description', 'og:image', 'og:url', 'og:type' ] as $prop ) {
            $val = self::social_extract_property( $html, $prop );
            $og_results[] = $val
                ? [ 'type' => 'pass', 'msg' => "$prop: " . mb_substr( $val, 0, 80 ) ]
                : [ 'type' => 'fail', 'msg' => "$prop is missing", 'fix' => $og_fixes[ $prop ] ?? '' ];
        }
        foreach ( [ 'twitter:card', 'twitter:image' ] as $name ) {
            $val = self::social_extract_name( $html, $name );
            $og_results[] = $val
                ? [ 'type' => 'pass', 'msg' => "$name: " . mb_substr( $val, 0, 80 ) ]
                : [ 'type' => 'warn', 'msg' => "$name missing — X/Twitter may not render large card",
                    'fix'  => $name === 'twitter:card'
                        ? 'Add <meta name="twitter:card" content="summary_large_image"> to show the full-width image card on X/Twitter. Most SEO plugins have a Twitter Card setting.'
                        : 'Add <meta name="twitter:image" content="https://yoursite.com/image.jpg">. X/Twitter uses this over og:image if present.' ];
        }
        $sections[] = [ 'title' => 'Open Graph Tags', 'results' => $og_results ];

        // 5. og:image analysis
        $og_image = self::social_extract_property( $html, 'og:image' );
        $img_results = [];
        if ( ! $og_image ) {
            $img_results[] = [ 'type' => 'fail', 'msg' => 'og:image is missing — cannot analyse image.', 'fix' => 'Set a featured image on this post/page and ensure your SEO plugin is configured to use it as og:image. Add a site-wide fallback image in your SEO plugin settings.' ];
        } else {
            $img_head = wp_remote_head( $og_image, [ 'user-agent' => $wa_ua, 'timeout' => 10, 'redirection' => 3 ] );
            if ( is_wp_error( $img_head ) ) {
                $img_results[] = [ 'type' => 'fail', 'msg' => 'og:image URL unreachable: ' . $img_head->get_error_message(), 'fix' => 'Verify the image URL is publicly accessible. Check that the file exists in your Media Library and that no security plugin or Cloudflare rule is blocking direct image access.' ];
            } else {
                $img_code = wp_remote_retrieve_response_code( $img_head );
                $img_results[] = $img_code === 200
                    ? [ 'type' => 'pass', 'msg' => 'og:image URL returns HTTP 200' ]
                    : [ 'type' => 'fail', 'msg' => "og:image URL returns HTTP $img_code — image inaccessible", 'fix' => "The image file returned HTTP $img_code. Re-upload the image to your Media Library, update the og:image URL to the new path, and confirm the file is publicly readable (check file permissions and any WAF rules blocking image requests)." ];
                $ct = wp_remote_retrieve_header( $img_head, 'content-type' );
                $img_results[] = str_contains( (string) $ct, 'image/' )
                    ? [ 'type' => 'pass', 'msg' => "Content-Type: $ct" ]
                    : [ 'type' => 'warn', 'msg' => "Unexpected Content-Type: '$ct'", 'fix' => "The URL is not serving an image file. Verify the og:image URL points directly to a JPEG, PNG, or WebP file (not a page or redirect). If using a CDN, check that it is not transforming the response Content-Type." ];
            }
            $img_results[] = str_starts_with( $og_image, 'https://' )
                ? [ 'type' => 'pass', 'msg' => 'og:image uses HTTPS' ]
                : [ 'type' => 'fail', 'msg' => 'og:image uses HTTP — WhatsApp requires HTTPS images', 'fix' => 'Update the og:image URL to use https://. If your site has SSL, the image URL should automatically use HTTPS — check your SEO plugin settings or the post\'s custom OG image field.' ];

            $img_resp = wp_remote_get( $og_image, [ 'user-agent' => $wa_ua, 'timeout' => 20, 'redirection' => 3 ] );
            if ( ! is_wp_error( $img_resp ) ) {
                $img_body = wp_remote_retrieve_body( $img_resp );
                $img_bytes = strlen( $img_body );
                $img_kb    = round( $img_bytes / 1024, 1 );
                if ( $img_bytes > 307200 ) {
                    $img_results[] = [ 'type' => 'fail', 'msg' => "Image is {$img_kb} KB — exceeds WhatsApp's 300 KB silent-failure threshold. Compress to under 250 KB.", 'fix' => "Use the Media Library Audit below to recompress this image, or use squoosh.app / TinyPNG to manually compress it to under 250 KB. Then re-upload and update the og:image URL." ];
                } elseif ( $img_bytes > 204800 ) {
                    $img_results[] = [ 'type' => 'warn', 'msg' => "Image is {$img_kb} KB — approaching 300 KB WhatsApp limit. Consider optimising.", 'fix' => "Compress the image to under 200 KB using TinyPNG or the Media Library Audit recompress tool below. JPEG at 80% quality typically achieves good compression without visible quality loss." ];
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
                            $img_results[] = [ 'type' => 'warn', 'msg' => "Dimensions: {$img_w}×{$img_h}px — below recommended 1200×630", 'fix' => "Resize or recreate the image at 1200×630px. This is the optimal size for Facebook, LinkedIn, and WhatsApp. Use Canva or your image editor to export at exactly 1200×630px." ];
                        } else {
                            $img_results[] = [ 'type' => 'fail', 'msg' => "Dimensions: {$img_w}×{$img_h}px — too small for reliable social previews", 'fix' => "Replace this image with one at least 1200×630px. Images smaller than 600px wide are often ignored by social crawlers entirely. Create a new featured image at 1200×630px." ];
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
            $rb_results[] = [ 'type' => 'warn', 'msg' => 'robots.txt not found — ensure crawlers are not blocked elsewhere', 'fix' => 'Create a robots.txt at your domain root. In WordPress, go to Settings → Reading and ensure "Discourage search engines" is unchecked. Yoast SEO auto-generates robots.txt — enable it if available.' ];
        } else {
            $rb_body = wp_remote_retrieve_body( $robots_resp );
            foreach ( [ 'facebookexternalhit', 'WhatsApp', 'Facebot', 'LinkedInBot', 'Twitterbot' ] as $bot ) {
                if ( preg_match( '/User-agent:\s*' . preg_quote( $bot, '/' ) . '.*?Disallow:\s*\//si', $rb_body ) ) {
                    $rb_results[] = [ 'type' => 'fail', 'msg' => "robots.txt blocks $bot — this prevents all previews from that platform", 'fix' => "Remove the Disallow rule for $bot from your robots.txt. Add \"User-agent: $bot\\nDisallow:\" (empty Disallow = allow all) to explicitly permit this crawler. Edit robots.txt via your SEO plugin or directly in the site root." ];
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
            $cf_results[] = [ 'type' => 'info', 'msg' => 'If any crawler UA test failed, set up a WAF Skip rule in Cloudflare for social crawler user agents — see the Cloudflare Setup panel.' ];
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
            wp_delete_file( $backup );
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
            wp_delete_file( $backup );
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

    /* ==================================================================
       Security tab helpers + render
       ================================================================== */

    private static function default_security_prompt(): string {
        return 'You are an expert WordPress security auditor with deep knowledge of WordPress internals, common attack vectors, and security hardening best practices.'
            . "\n\nAnalyse the provided site configuration and return a comprehensive, prioritised security assessment."
            . "\n\nReturn ONLY valid JSON — no markdown code fences, no explanation outside the JSON. Use this exact schema:"
            . "\n{\"score\":<integer 0-100>,\"score_label\":\"<Excellent|Good|Fair|Poor|Critical>\",\"summary\":\"<2-3 sentence executive summary>\","
            . "\"critical\":[{\"title\":\"...\",\"detail\":\"...\",\"fix\":\"...\"}],"
            . "\"high\":[{\"title\":\"...\",\"detail\":\"...\",\"fix\":\"...\"}],"
            . "\"medium\":[{\"title\":\"...\",\"detail\":\"...\",\"fix\":\"...\"}],"
            . "\"low\":[{\"title\":\"...\",\"detail\":\"...\",\"fix\":\"...\"}],"
            . "\"good\":[{\"title\":\"...\",\"detail\":\"...\"}]}"
            . "\n\nScoring: 90-100 Excellent, 75-89 Good, 55-74 Fair, 35-54 Poor, 0-34 Critical."
            . "\n\nFor each issue — title: concise problem name; detail: specific risk with exploit path; fix: exact actionable steps (include WP-CLI commands, file paths, or wp-config.php constants where relevant)."
            . "\n\nAnalyse: WordPress/PHP version currency, plugin/theme security posture, authentication hardening (2FA, brute-force, admin username), configuration security (debug mode, file editing, DB prefix), exposed sensitive files, HTTP security headers, HTTPS enforcement, and any notable risk combinations.";
    }

    private static function gather_security_data(): array {
        global $wpdb;

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins    = get_plugins();
        $active_plugins = (array) get_option( 'active_plugins', [] );
        $plugin_updates = get_site_transient( 'update_plugins' );
        $plugins_list   = [];
        foreach ( $all_plugins as $file => $p ) {
            $has_upd        = is_object( $plugin_updates ) && isset( $plugin_updates->response[ $file ] );
            $plugins_list[] = [
                'name'    => $p['Name'],
                'version' => $p['Version'],
                'active'  => in_array( $file, $active_plugins, true ),
                'update'  => $has_upd,
                'new_ver' => $has_upd ? ( $plugin_updates->response[ $file ]->new_version ?? null ) : null,
            ];
        }
        usort( $plugins_list, fn( $a, $b ) => (int) $b['active'] - (int) $a['active'] );

        $wp_updates = get_site_transient( 'update_core' );
        $wp_current = get_bloginfo( 'version' );
        $wp_latest  = $wp_updates->updates[0]->version ?? $wp_current;

        $user_counts       = count_users();
        $admin_user_exists = (bool) get_user_by( 'login', 'admin' );

        $sec_headers = [];
        $home_resp   = wp_remote_get( home_url( '/' ), [
            'timeout'    => 5,
            'sslverify'  => false,
            'user-agent' => 'Mozilla/5.0 (compatible; CSDT-SecurityScan/1.0)',
        ] );
        if ( ! is_wp_error( $home_resp ) ) {
            $h = wp_remote_retrieve_headers( $home_resp );
            foreach ( [ 'x-frame-options', 'x-content-type-options', 'strict-transport-security',
                        'content-security-policy', 'referrer-policy', 'permissions-policy' ] as $hname ) {
                $sec_headers[ $hname ] = $h[ $hname ] ?? null;
            }
        }

        $exposed = [];
        foreach ( [ 'readme.html', 'license.txt', 'wp-config.php.bak', '.env' ] as $f ) {
            if ( file_exists( ABSPATH . $f ) ) {
                $check = wp_remote_head( home_url( '/' . $f ), [ 'timeout' => 3, 'sslverify' => false ] );
                if ( ! is_wp_error( $check ) && (int) wp_remote_retrieve_response_code( $check ) === 200 ) {
                    $exposed[] = $f;
                }
            }
        }

        $config_perms = file_exists( ABSPATH . 'wp-config.php' )
            ? substr( sprintf( '%o', fileperms( ABSPATH . 'wp-config.php' ) ), -4 )
            : 'unknown';

        return [
            'wordpress' => [
                'version'    => $wp_current,
                'latest'     => $wp_latest,
                'up_to_date' => version_compare( $wp_current, $wp_latest, '>=' ),
            ],
            'php_version'    => PHP_VERSION,
            'plugins'        => $plugins_list,
            'plugin_summary' => [
                'total'    => count( $plugins_list ),
                'active'   => count( array_filter( $plugins_list, fn( $p ) => $p['active'] ) ),
                'inactive' => count( array_filter( $plugins_list, fn( $p ) => ! $p['active'] ) ),
                'outdated' => count( array_filter( $plugins_list, fn( $p ) => $p['update'] ) ),
            ],
            'users' => [
                'admin_login_exists' => $admin_user_exists,
                'admin_count'        => $user_counts['avail_roles']['administrator'] ?? 0,
                'total_users'        => $user_counts['total_users'],
            ],
            'configuration' => [
                'wp_debug'           => defined( 'WP_DEBUG' ) && WP_DEBUG,
                'wp_debug_display'   => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
                'wp_debug_log'       => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
                'disallow_file_edit' => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
                'disallow_file_mods' => defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS,
                'force_ssl_admin'    => defined( 'FORCE_SSL_ADMIN' ) && FORCE_SSL_ADMIN,
                'db_prefix'          => $wpdb->prefix,
                'db_prefix_default'  => $wpdb->prefix === 'wp_',
                'wp_config_perms'    => $config_perms,
            ],
            'site' => [
                'url'                    => home_url( '/' ),
                'is_https'               => is_ssl(),
                'login_url_hidden'       => get_option( 'csdt_devtools_login_hide_enabled', '0' ) === '1',
                'xmlrpc_exists'          => file_exists( ABSPATH . 'xmlrpc.php' ) && (bool) apply_filters( 'xmlrpc_enabled', true ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                'open_registration'      => (bool) get_option( 'users_can_register', 0 ),
                'pingbacks_enabled'      => get_option( 'default_ping_status' ) === 'open',
                'wp_version_in_meta'     => ! has_filter( 'the_generator', '__return_empty_string' ) && ! has_filter( 'wp_head', 'wp_generator' ),
                'default_comment_status' => get_option( 'default_comment_status' ),
            ],
            'security_features' => [
                'brute_force_enabled'  => get_option( 'csdt_devtools_brute_force_enabled', '1' ) === '1',
                'two_fa_site_method'   => get_option( 'csdt_devtools_2fa_method', 'off' ),
                'two_fa_totp_admins'   => ( function () {
                    $admins = get_users( [ 'role' => 'administrator', 'fields' => 'ID' ] );
                    $count  = 0;
                    foreach ( $admins as $id ) {
                        if ( get_user_meta( (int) $id, 'csdt_devtools_totp_enabled', true ) === '1' ) {
                            $count++;
                        }
                    }
                    return $count;
                } )(),
                'passkeys_admin_count' => ( function () {
                    $admins = get_users( [ 'role' => 'administrator', 'fields' => 'ID' ] );
                    $count  = 0;
                    foreach ( $admins as $id ) {
                        // Passkeys stored as JSON string — use the class method which decodes it
                        $keys = CSDT_DevTools_Passkey::get_passkeys( (int) $id );
                        if ( ! empty( $keys ) ) {
                            $count++;
                        }
                    }
                    return $count;
                } )(),
                'admin_count'          => count( get_users( [ 'role' => 'administrator', 'fields' => 'ID' ] ) ),
                'failed_logins_1h'     => (int) get_transient( 'csdt_devtools_failed_logins_1h' ),
                'failed_logins_24h'    => (int) get_transient( 'csdt_devtools_failed_logins_24h' ),
                'app_passwords'        => ( function () {
                    $enabled       = function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available();
                    $admins_with   = 0;
                    $total_app_pw  = 0;
                    if ( $enabled ) {
                        foreach ( get_users( [ 'role' => 'administrator', 'fields' => 'ID' ] ) as $id ) {
                            $pws = WP_Application_Passwords::get_user_application_passwords( (int) $id );
                            if ( ! empty( $pws ) ) {
                                $admins_with++;
                                $total_app_pw += count( $pws );
                            }
                        }
                    }
                    return [
                        'enabled'          => $enabled,
                        'admins_with_app_pw'=> $admins_with,
                        'total_app_passwords'=> $total_app_pw,
                    ];
                } )(),
            ],
            'exposed_files'    => $exposed,
            'security_headers' => $sec_headers,
        ];
    }

    public static function strip_asset_ver( string $src ): string {
        if ( strpos( $src, 'ver=' ) !== false ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }

    private static function get_quick_fixes(): array {
        $app_pw_available = function_exists( 'wp_is_application_passwords_available' )
                           && wp_is_application_passwords_available();
        return [
            [
                'id'        => 'disable_pingbacks',
                'title'     => 'Pingbacks & trackbacks enabled',
                'detail'    => 'WordPress sends/receives trackback notifications — commonly abused for DDoS amplification and spam.',
                'fixed'     => get_option( 'default_ping_status' ) !== 'open',
                'fix_label' => 'Disable Pingbacks',
            ],
            [
                'id'        => 'close_registration',
                'title'     => 'Open user registration',
                'detail'    => 'Anyone can register an account on this site. Widens attack surface for spam and privilege escalation.',
                'fixed'     => ! (bool) get_option( 'users_can_register', 0 ),
                'fix_label' => 'Disable Registration',
            ],
            [
                'id'           => 'disable_app_passwords',
                'title'        => 'Application passwords enabled',
                'detail'       => get_option( 'csdt_devtools_test_accounts_enabled', '0' ) === '1'
                    ? 'App passwords are required for the Test Account Manager feature and are intentionally enabled.'
                    : ( get_option( 'csdt_devtools_app_pw_2fa_ack', '0' ) === '1'
                        ? 'App passwords are intentionally enabled — 2FA is active and REST API use is authorised.'
                        : 'App passwords allow REST API authentication and can bypass two-factor authentication. Disable unless needed.' ),
                'fixed'        => get_option( 'csdt_devtools_disable_app_passwords', '0' ) === '1'
                              || ! $app_pw_available
                              || get_option( 'csdt_devtools_test_accounts_enabled', '0' ) === '1'
                              || get_option( 'csdt_devtools_app_pw_2fa_ack', '0' ) === '1',
                'fix_label'    => 'Disable App Passwords',
                'dismiss_label'=> 'Using with 2FA',
                'dismiss_id'   => 'app_pw_2fa_ack',
            ],
            [
                'id'        => 'hide_wp_version',
                'title'     => 'WordPress version exposed in HTML',
                'detail'    => 'The <generator> meta tag and asset ?ver= query strings reveal your WP version, helping attackers target known vulnerabilities.',
                'fixed'     => get_option( 'csdt_devtools_hide_wp_version', '0' ) === '1'
                              || has_filter( 'the_generator', '__return_empty_string' ),
                'fix_label' => 'Hide WP Version',
            ],
            [
                'id'        => 'close_comments',
                'title'     => 'Comments open by default on new posts',
                'detail'    => 'Open comments invite spam, XSS payloads, and link injection attacks.',
                'fixed'     => get_option( 'default_comment_status' ) !== 'open',
                'fix_label' => 'Close Comments',
            ],
            [
                'id'        => 'wpconfig_perms',
                'title'     => 'wp-config.php permissions too open (0644)',
                'detail'    => '0644 is world-readable. Tighten to 0600 so only the server process owner can read DB credentials and salts.',
                'fixed'     => ( function () {
                    $f = ABSPATH . 'wp-config.php';
                    if ( ! file_exists( $f ) ) { return true; }
                    $perms = substr( sprintf( '%o', fileperms( $f ) ), -4 );
                    return in_array( $perms, [ '0600', '0640' ], true );
                } )(),
                'fix_label' => 'Set to 0600',
            ],
            [
                'id'           => 'security_headers',
                'title'        => 'Security headers not set',
                'detail'       => get_option( 'csdt_devtools_sec_headers_ack', '0' ) === '1'
                    ? 'Security headers are confirmed set externally (Cloudflare, nginx, or CDN).'
                    : 'X-Content-Type-Options, X-Frame-Options, Referrer-Policy, and Permissions-Policy are missing. These prevent MIME sniffing, clickjacking, and referrer leakage.',
                'fixed'        => ( function () {
                    if ( get_option( 'csdt_devtools_safe_headers_enabled', '0' ) === '1' ) {
                        return true;
                    }
                    if ( get_option( 'csdt_devtools_sec_headers_ack', '0' ) === '1' ) {
                        return true;
                    }
                    $cached = get_transient( 'csdt_sec_headers_check' );
                    if ( $cached !== false ) {
                        return (bool) $cached;
                    }
                    $resp = wp_remote_get( home_url( '/' ), [ 'timeout' => 4, 'sslverify' => false ] );
                    if ( is_wp_error( $resp ) ) {
                        return false;
                    }
                    $h        = wp_remote_retrieve_headers( $resp );
                    $required = [ 'x-content-type-options', 'x-frame-options', 'referrer-policy', 'permissions-policy' ];
                    $all_set  = empty( array_filter( $required, fn( $n ) => empty( $h[ $n ] ) ) );
                    set_transient( 'csdt_sec_headers_check', $all_set ? '1' : '0', 300 );
                    return $all_set;
                } )(),
                'fix_label'    => 'Enable Headers',
                'dismiss_label'=> 'Set Externally',
                'dismiss_id'   => 'security_headers_ack',
            ],
            [
                'id'        => 'block_debug_log',
                'title'     => 'debug.log exposed publicly',
                'detail'    => 'debug.log is HTTP-accessible. On nginx, .htaccess rules are ignored — the only PHP-level fix is to move the file one directory above the web root. It stays readable via the Server Logs tab.',
                'fixed'     => ! file_exists( WP_CONTENT_DIR . '/debug.log' ),
                'fix_label' => 'Move Outside Web Root',
            ],
        ];
    }

    // ── Security Headers ──────────────────────────────────────────────────────

    public static function output_security_headers(): void {
        if ( is_admin() ) { return; }
        if ( get_option( 'csdt_devtools_safe_headers_enabled', '0' ) === '1' ) {
            header( 'X-Content-Type-Options: nosniff' );
            header( 'X-Frame-Options: SAMEORIGIN' );
            header( 'Referrer-Policy: strict-origin-when-cross-origin' );
            header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
        }
        if ( get_option( 'csdt_devtools_csp_enabled', '0' ) === '1' ) {
            $csp = self::build_csp_header();
            if ( $csp ) {
                $mode = get_option( 'csdt_devtools_csp_mode', 'enforce' );
                $hdr  = $mode === 'report_only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
                header( $hdr . ': ' . $csp );
            }
        }
    }

    private static function build_csp_header(): string {
        $services = json_decode( get_option( 'csdt_devtools_csp_services', '[]' ), true );
        if ( ! is_array( $services ) ) { $services = []; }
        $custom = trim( get_option( 'csdt_devtools_csp_custom', '' ) );

        $d = [
            'default-src' => [ "'self'" ],
            'script-src'  => [ "'self'", "'unsafe-inline'" ],
            'style-src'   => [ "'self'", "'unsafe-inline'" ],
            'img-src'     => [ "'self'", 'data:', 'https:' ],
            'font-src'    => [ "'self'", 'data:' ],
            'connect-src' => [ "'self'" ],
            'frame-src'   => [ "'self'" ],
            'object-src'  => [ "'none'" ],
            'base-uri'    => [ "'self'" ],
            'form-action' => [ "'self'" ],
        ];

        $map = [
            'google_analytics'    => [
                'script-src'  => [ 'https://www.googletagmanager.com', 'https://www.google-analytics.com' ],
                'img-src'     => [ 'https://www.google-analytics.com', 'https://www.googletagmanager.com' ],
                'connect-src' => [ 'https://www.google-analytics.com', 'https://analytics.google.com', 'https://stats.g.doubleclick.net', 'https://region1.google-analytics.com' ],
            ],
            'google_adsense'      => [
                'script-src'  => [ 'https://pagead2.googlesyndication.com', 'https://partner.googleadservices.com', 'https://tpc.googlesyndication.com' ],
                'frame-src'   => [ 'https://googleads.g.doubleclick.net', 'https://tpc.googlesyndication.com' ],
                'img-src'     => [ 'https://pagead2.googlesyndication.com' ],
                'connect-src' => [ 'https://pagead2.googlesyndication.com' ],
            ],
            'google_tag_manager'  => [
                'script-src'  => [ 'https://www.googletagmanager.com' ],
                'img-src'     => [ 'https://www.googletagmanager.com' ],
            ],
            'cloudflare_insights' => [
                'script-src'  => [ 'https://static.cloudflareinsights.com' ],
                'connect-src' => [ 'https://cloudflareinsights.com' ],
            ],
            'facebook_pixel'      => [
                'script-src'  => [ 'https://connect.facebook.net' ],
                'img-src'     => [ 'https://www.facebook.com' ],
                'connect-src' => [ 'https://www.facebook.com' ],
            ],
            'recaptcha'           => [
                'script-src'  => [ 'https://www.google.com', 'https://www.gstatic.com' ],
                'frame-src'   => [ 'https://www.google.com' ],
            ],
            'youtube'             => [
                'frame-src'   => [ 'https://www.youtube.com', 'https://www.youtube-nocookie.com' ],
            ],
            'vimeo'               => [
                'frame-src'   => [ 'https://player.vimeo.com' ],
            ],
        ];

        foreach ( $services as $svc ) {
            if ( ! isset( $map[ $svc ] ) ) { continue; }
            foreach ( $map[ $svc ] as $dir => $vals ) {
                foreach ( $vals as $v ) {
                    if ( ! in_array( $v, $d[ $dir ], true ) ) { $d[ $dir ][] = $v; }
                }
            }
        }

        $parts = [];
        foreach ( $d as $dir => $vals ) { $parts[] = $dir . ' ' . implode( ' ', $vals ); }
        if ( $custom ) { $parts[] = $custom; }
        return implode( '; ', $parts );
    }

    private static function render_csp_panel(): void {
        $csp_on       = get_option( 'csdt_devtools_csp_enabled', '0' ) === '1';
        $csp_mode     = get_option( 'csdt_devtools_csp_mode', 'enforce' );
        $csp_services = json_decode( get_option( 'csdt_devtools_csp_services', '[]' ), true );
        if ( ! is_array( $csp_services ) ) { $csp_services = []; }
        $csp_custom   = get_option( 'csdt_devtools_csp_custom', '' );
        $csp_backup   = json_decode( get_option( 'csdt_devtools_csp_backup', '' ), true );
        $backup_time  = is_array( $csp_backup ) ? ( $csp_backup['saved_at'] ?? 0 ) : 0;

        $services = [
            'google_analytics'    => 'Google Analytics (GA4 / gtag.js)',
            'google_adsense'      => 'Google AdSense',
            'google_tag_manager'  => 'Google Tag Manager',
            'cloudflare_insights' => 'Cloudflare Web Analytics',
            'facebook_pixel'      => 'Facebook Pixel',
            'recaptcha'           => 'Google reCAPTCHA',
            'youtube'             => 'YouTube embeds',
            'vimeo'               => 'Vimeo embeds',
        ];
        ?>
        <hr class="cs-sec-divider">
        <div class="cs-section-header" style="background:linear-gradient(90deg,#1a1f2e 0%,#1e2535 100%);border-left:3px solid #6366f1;margin-bottom:0;">
            <span>🛡️ <?php esc_html_e( 'Content Security Policy (CSP)', 'cloudscale-devtools' ); ?></span>
            <span class="cs-header-hint"><?php esc_html_e( 'Block unauthorised scripts and resources. Select the services your site uses before enabling.', 'cloudscale-devtools' ); ?></span>
        </div>
        <div style="padding:16px 0 8px;" id="cs-csp-panel">

            <!-- Enable + Mode -->
            <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;padding:0 2px 14px;border-bottom:1px solid #f1f5f9;margin-bottom:14px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;cursor:pointer;">
                    <input type="checkbox" id="cs-csp-enabled" <?php checked( $csp_on ); ?>>
                    <?php esc_html_e( 'Enable CSP', 'cloudscale-devtools' ); ?>
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
                    <input type="radio" name="cs-csp-mode" value="enforce" <?php checked( $csp_mode, 'enforce' ); ?>>
                    <?php esc_html_e( 'Enforce', 'cloudscale-devtools' ); ?>
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
                    <input type="radio" name="cs-csp-mode" value="report_only" <?php checked( $csp_mode, 'report_only' ); ?>>
                    <?php esc_html_e( 'Report-Only (test mode)', 'cloudscale-devtools' ); ?>
                </label>
            </div>

            <!-- Service checkboxes -->
            <div style="margin-bottom:14px;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:10px;"><?php esc_html_e( 'Third-party services used on this site', 'cloudscale-devtools' ); ?></div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:6px;">
                    <?php foreach ( $services as $key => $label ) : ?>
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;padding:7px 10px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;cursor:pointer;">
                        <input type="checkbox" class="cs-csp-service" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $csp_services, true ) ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Custom directives -->
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:6px;"><?php esc_html_e( 'Additional directives (appended verbatim)', 'cloudscale-devtools' ); ?></label>
                <input type="text" id="cs-csp-custom" class="cs-text-input" style="width:100%;font-family:monospace;font-size:12px;"
                       placeholder="upgrade-insecure-requests; block-all-mixed-content"
                       value="<?php echo esc_attr( $csp_custom ); ?>">
            </div>

            <!-- Live preview -->
            <div style="margin-bottom:14px;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:6px;"><?php esc_html_e( 'Preview', 'cloudscale-devtools' ); ?></div>
                <pre id="cs-csp-preview" style="background:#0f172a;color:#e2e8f0;padding:12px;border-radius:6px;font-size:11px;white-space:pre-wrap;word-break:break-all;margin:0;max-height:140px;overflow-y:auto;"></pre>
            </div>

            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <button type="button" id="cs-csp-save-btn" class="cs-btn-primary cs-btn-sm"><?php esc_html_e( 'Save CSP Settings', 'cloudscale-devtools' ); ?></button>
                <?php if ( $backup_time ) : ?>
                <button type="button" id="cs-csp-rollback-btn" class="cs-btn-secondary cs-btn-sm" style="border-color:#f87171;color:#dc2626;">
                    ↩ <?php esc_html_e( 'Rollback to previous settings', 'cloudscale-devtools' ); ?>
                    <span style="font-weight:400;font-size:11px;opacity:.8;">(<?php echo esc_html( human_time_diff( $backup_time ) . ' ' . __( 'ago', 'cloudscale-devtools' ) ); ?>)</span>
                </button>
                <?php endif; ?>
                <span id="cs-csp-saved"    style="display:none;color:#16a34a;font-size:13px;font-weight:600;">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                <span id="cs-csp-rolledback" style="display:none;color:#d97706;font-size:13px;font-weight:600;">↩ <?php esc_html_e( 'Rolled back', 'cloudscale-devtools' ); ?></span>
            </div>
        </div>

        <script>
        (function(){
            var base = {
                'default-src': ["'self'"],
                'script-src':  ["'self'","'unsafe-inline'"],
                'style-src':   ["'self'","'unsafe-inline'"],
                'img-src':     ["'self'","data:","https:"],
                'font-src':    ["'self'","data:"],
                'connect-src': ["'self'"],
                'frame-src':   ["'self'"],
                'object-src':  ["'none'"],
                'base-uri':    ["'self'"],
                'form-action': ["'self'"]
            };
            var serviceMap = {
                google_analytics:    { 'script-src':['https://www.googletagmanager.com','https://www.google-analytics.com'], 'img-src':['https://www.google-analytics.com','https://www.googletagmanager.com'], 'connect-src':['https://www.google-analytics.com','https://analytics.google.com','https://stats.g.doubleclick.net','https://region1.google-analytics.com'] },
                google_adsense:      { 'script-src':['https://pagead2.googlesyndication.com','https://partner.googleadservices.com','https://tpc.googlesyndication.com'], 'frame-src':['https://googleads.g.doubleclick.net','https://tpc.googlesyndication.com'], 'img-src':['https://pagead2.googlesyndication.com'], 'connect-src':['https://pagead2.googlesyndication.com'] },
                google_tag_manager:  { 'script-src':['https://www.googletagmanager.com'], 'img-src':['https://www.googletagmanager.com'] },
                cloudflare_insights: { 'script-src':['https://static.cloudflareinsights.com'], 'connect-src':['https://cloudflareinsights.com'] },
                facebook_pixel:      { 'script-src':['https://connect.facebook.net'], 'img-src':['https://www.facebook.com'], 'connect-src':['https://www.facebook.com'] },
                recaptcha:           { 'script-src':['https://www.google.com','https://www.gstatic.com'], 'frame-src':['https://www.google.com'] },
                youtube:             { 'frame-src':['https://www.youtube.com','https://www.youtube-nocookie.com'] },
                vimeo:               { 'frame-src':['https://player.vimeo.com'] }
            };

            function buildPreview() {
                var d = JSON.parse(JSON.stringify(base));
                document.querySelectorAll('.cs-csp-service:checked').forEach(function(cb){
                    var svc = serviceMap[cb.value];
                    if (!svc) return;
                    Object.keys(svc).forEach(function(dir){
                        svc[dir].forEach(function(v){ if (d[dir].indexOf(v) === -1) d[dir].push(v); });
                    });
                });
                var parts = Object.keys(d).map(function(k){ return k + ' ' + d[k].join(' '); });
                var custom = document.getElementById('cs-csp-custom');
                if (custom && custom.value.trim()) parts.push(custom.value.trim());
                document.getElementById('cs-csp-preview').textContent = parts.join(';\n');
            }

            document.querySelectorAll('.cs-csp-service').forEach(function(cb){ cb.addEventListener('change', buildPreview); });
            var customIn = document.getElementById('cs-csp-custom');
            if (customIn) customIn.addEventListener('input', buildPreview);
            buildPreview();

            var saveBtn  = document.getElementById('cs-csp-save-btn');
            var savedMsg = document.getElementById('cs-csp-saved');
            if (saveBtn) {
                saveBtn.addEventListener('click', function(){
                    saveBtn.disabled = true;
                    var services = [];
                    document.querySelectorAll('.cs-csp-service:checked').forEach(function(cb){ services.push(cb.value); });
                    var modeEl = document.querySelector('input[name="cs-csp-mode"]:checked');
                    var fd = new FormData();
                    fd.append('action',   'csdt_devtools_csp_save');
                    fd.append('nonce',    csdtVulnScan.nonce);
                    fd.append('enabled',  document.getElementById('cs-csp-enabled').checked ? '1' : '0');
                    fd.append('mode',     modeEl ? modeEl.value : 'enforce');
                    fd.append('services', JSON.stringify(services));
                    fd.append('custom',   customIn ? customIn.value.trim() : '');
                    fetch(csdtVulnScan.ajaxUrl, { method:'POST', body:fd })
                        .then(function(r){ return r.json(); })
                        .then(function(resp){
                        saveBtn.disabled = false;
                        if (savedMsg) { savedMsg.style.display = 'inline'; setTimeout(function(){ savedMsg.style.display = 'none'; }, 2500); }
                        // Show rollback button if one wasn't already visible.
                        if (resp && resp.data && resp.data.has_backup) {
                            var rb = document.getElementById('cs-csp-rollback-btn');
                            if (!rb) {
                                rb = document.createElement('button');
                                rb.id = 'cs-csp-rollback-btn';
                                rb.type = 'button';
                                rb.className = 'cs-btn-secondary cs-btn-sm';
                                rb.style.cssText = 'border-color:#f87171;color:#dc2626;';
                                rb.textContent = '↩ Rollback to previous settings';
                                saveBtn.parentNode.insertBefore(rb, saveBtn.nextSibling);
                                wireRollback(rb);
                            }
                        }
                    })
                    .catch(function(){ saveBtn.disabled = false; });
                });
            }

            function wireRollback(btn) {
                if (!btn) return;
                btn.addEventListener('click', function(){
                    if (!confirm('Restore the previous CSP settings? This will overwrite the current configuration.')) { return; }
                    btn.disabled = true;
                    var fd = new FormData();
                    fd.append('action', 'csdt_devtools_csp_rollback');
                    fd.append('nonce',  csdtVulnScan.nonce);
                    fetch(csdtVulnScan.ajaxUrl, { method:'POST', body:fd })
                        .then(function(r){ return r.json(); })
                        .then(function(resp){
                            if (!resp.success) { alert('Rollback failed: ' + (resp.data || 'unknown error')); btn.disabled = false; return; }
                            var d = resp.data;
                            // Restore UI state.
                            var en = document.getElementById('cs-csp-enabled');
                            if (en) en.checked = d.enabled === '1';
                            var modeEl = document.querySelector('input[name="cs-csp-mode"][value="' + (d.mode || 'enforce') + '"]');
                            if (modeEl) modeEl.checked = true;
                            document.querySelectorAll('.cs-csp-service').forEach(function(cb){
                                cb.checked = Array.isArray(d.services) && d.services.indexOf(cb.value) !== -1;
                            });
                            if (customIn) customIn.value = d.custom || '';
                            buildPreview();
                            btn.remove();
                            var rb2 = document.getElementById('cs-csp-rolledback');
                            if (rb2) { rb2.style.display = 'inline'; setTimeout(function(){ rb2.style.display = 'none'; }, 3000); }
                        })
                        .catch(function(){ btn.disabled = false; });
                });
            }
            wireRollback(document.getElementById('cs-csp-rollback-btn'));
        })();
        </script>
        <?php
    }

    public static function ajax_csp_save(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Unauthorized', 403 ); }

        $enabled  = isset( $_POST['enabled'] )  ? sanitize_key( wp_unslash( $_POST['enabled'] ) )                            : '0';
        $mode     = isset( $_POST['mode'] )     ? sanitize_key( wp_unslash( $_POST['mode'] ) )                               : 'enforce';
        $services = isset( $_POST['services'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['services'] ) ), true ) : [];
        $custom   = isset( $_POST['custom'] )   ? sanitize_textarea_field( wp_unslash( $_POST['custom'] ) )                  : '';

        if ( ! is_array( $services ) ) { $services = []; }
        $allowed_services = [ 'google_analytics', 'google_adsense', 'google_tag_manager', 'cloudflare_insights', 'facebook_pixel', 'recaptcha', 'youtube', 'vimeo' ];
        $services = array_values( array_intersect( $services, $allowed_services ) );

        // Snapshot current settings before overwriting so rollback is always possible.
        update_option( 'csdt_devtools_csp_backup', wp_json_encode( [
            'enabled'  => get_option( 'csdt_devtools_csp_enabled', '0' ),
            'mode'     => get_option( 'csdt_devtools_csp_mode', 'enforce' ),
            'services' => get_option( 'csdt_devtools_csp_services', '[]' ),
            'custom'   => get_option( 'csdt_devtools_csp_custom', '' ),
            'saved_at' => time(),
        ] ) );

        update_option( 'csdt_devtools_csp_enabled',  $enabled === '1' ? '1' : '0' );
        update_option( 'csdt_devtools_csp_mode',     in_array( $mode, [ 'enforce', 'report_only' ], true ) ? $mode : 'enforce' );
        update_option( 'csdt_devtools_csp_services', wp_json_encode( $services ) );
        update_option( 'csdt_devtools_csp_custom',   $custom );

        wp_send_json_success( [ 'has_backup' => true ] );
    }

    public static function ajax_csp_rollback(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Unauthorized', 403 ); }

        $raw = get_option( 'csdt_devtools_csp_backup', '' );
        if ( ! $raw ) { wp_send_json_error( 'No backup available' ); }

        $backup = json_decode( $raw, true );
        if ( ! is_array( $backup ) ) { wp_send_json_error( 'Backup corrupt' ); }

        update_option( 'csdt_devtools_csp_enabled',  $backup['enabled']  ?? '0' );
        update_option( 'csdt_devtools_csp_mode',     $backup['mode']     ?? 'enforce' );
        update_option( 'csdt_devtools_csp_services', $backup['services'] ?? '[]' );
        update_option( 'csdt_devtools_csp_custom',   $backup['custom']   ?? '' );
        delete_option( 'csdt_devtools_csp_backup' );

        wp_send_json_success( [
            'enabled'  => $backup['enabled']  ?? '0',
            'mode'     => $backup['mode']      ?? 'enforce',
            'services' => json_decode( $backup['services'] ?? '[]', true ),
            'custom'   => $backup['custom']    ?? '',
        ] );
    }

    public static function register_dashboard_widget(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        wp_add_dashboard_widget(
            'csdt_security_summary',
            '🤖 CloudScale Cyber and Devtools',
            [ __CLASS__, 'render_dashboard_widget' ]
        );
    }

    public static function render_dashboard_widget(): void {
        $ai_provider   = get_option( 'csdt_devtools_ai_provider', 'anthropic' );
        $anthropic_key = get_option( 'csdt_devtools_anthropic_key', '' );
        $gemini_key    = get_option( 'csdt_devtools_gemini_key', '' );
        $has_key       = $ai_provider === 'gemini' ? ! empty( $gemini_key ) : ! empty( $anthropic_key );
        $provider_lbl  = $ai_provider === 'gemini' ? 'Google Gemini' : 'Anthropic Claude';

        $history   = get_option( 'csdt_scan_history', [] );
        $last_scan = ! empty( $history ) ? $history[0] : null;
        $score_cls = '#888';
        if ( $last_scan ) {
            $s = (int) ( $last_scan['score'] ?? 0 );
            $score_cls = $s >= 75 ? '#16a34a' : ( $s >= 55 ? '#d97706' : '#dc2626' );
        }

        $bf_on      = get_option( 'csdt_devtools_brute_force_enabled', '1' ) === '1';
        $login_slug = get_option( 'csdt_devtools_login_slug', '' );
        $force_2fa  = get_option( 'csdt_devtools_force_2fa', '0' ) === '1';
        $email_2fa  = get_option( 'csdt_devtools_2fa_method', 'off' ) === 'email';
        $admins     = get_users( [ 'role' => 'administrator' ] );
        $adm_tot    = count( $admins );
        $adm_2fa    = 0;
        foreach ( $admins as $u ) {
            if ( get_user_meta( $u->ID, 'csdt_devtools_totp_enabled', true ) === '1'
                 || ! empty( get_user_meta( $u->ID, 'csdt_devtools_passkeys', true ) )
                 || $email_2fa ) {
                $adm_2fa++;
            }
        }

        $base_url = admin_url( 'tools.php?page=cloudscale-devtools' );
        ?>
        <style>
        #csdt_security_summary .cs-dw-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:13px;}
        #csdt_security_summary .cs-dw-row:last-child{border-bottom:none;}
        #csdt_security_summary .cs-dw-lbl{color:#94a3b8;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;}
        #csdt_security_summary .cs-dw-section{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;padding:12px 0 6px;border-bottom:2px solid #e5e7eb;margin-bottom:4px;}
        #csdt_security_summary .cs-dw-actions{margin-top:14px;display:flex;}
        #csdt_security_summary .cs-dw-actions a{flex:1;text-align:center;padding:8px 10px;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;transition:opacity .15s;}
        #csdt_security_summary .cs-dw-btn-pri{background:linear-gradient(135deg,#1a3a8f,#1e6fd9);color:#fff!important;}
        #csdt_security_summary .cs-dw-btn-pri:hover{opacity:.88;}
        </style>

        <div class="cs-dw-section">🤖 <?php esc_html_e( 'AI Security', 'cloudscale-devtools' ); ?></div>
        <div class="cs-dw-row">
            <span class="cs-dw-lbl"><?php esc_html_e( 'STATUS', 'cloudscale-devtools' ); ?></span>
            <span style="color:<?php echo $has_key ? '#16a34a' : '#dc2626'; ?>;font-weight:600;">
                <?php echo $has_key ? '✅ ' . esc_html( $provider_lbl ) : '⚠️ ' . esc_html__( 'API key not set', 'cloudscale-devtools' ); ?>
            </span>
        </div>

        <div class="cs-dw-section" style="margin-top:10px;display:flex;align-items:center;justify-content:space-between;">
            <span>🛡️ <?php esc_html_e( 'Last Security Scan', 'cloudscale-devtools' ); ?></span>
            <?php if ( $last_scan ) : ?><a href="<?php echo esc_url( $base_url . '&tab=security' ); ?>" style="font-size:11px;font-weight:600;color:#0e6b8f;text-decoration:none;"><?php esc_html_e( 'View Report →', 'cloudscale-devtools' ); ?></a><?php endif; ?>
        </div>
        <?php if ( $last_scan ) : ?>
        <div class="cs-dw-row">
            <span class="cs-dw-lbl"><?php esc_html_e( 'SCORE', 'cloudscale-devtools' ); ?></span>
            <span style="color:<?php echo esc_attr( $score_cls ); ?>;font-weight:600;"><?php echo esc_html( ( $last_scan['score_label'] ?? '' ) . ' · ' . ( $last_scan['score'] ?? '' ) ); ?></span>
        </div>
        <div class="cs-dw-row">
            <span class="cs-dw-lbl"><?php esc_html_e( 'CRITICAL', 'cloudscale-devtools' ); ?></span>
            <span style="color:<?php echo (int) ( $last_scan['critical_count'] ?? 0 ) > 0 ? '#dc2626' : '#16a34a'; ?>;font-weight:600;"><?php echo (int) ( $last_scan['critical_count'] ?? 0 ); ?></span>
        </div>
        <div class="cs-dw-row">
            <span class="cs-dw-lbl"><?php esc_html_e( 'HIGH', 'cloudscale-devtools' ); ?></span>
            <span style="color:<?php echo (int) ( $last_scan['high_count'] ?? 0 ) > 0 ? '#d97706' : '#16a34a'; ?>;font-weight:600;"><?php echo (int) ( $last_scan['high_count'] ?? 0 ); ?></span>
        </div>
        <div class="cs-dw-row">
            <span class="cs-dw-lbl"><?php esc_html_e( 'SCANNED', 'cloudscale-devtools' ); ?></span>
            <span style="color:#888;"><?php echo esc_html( human_time_diff( (int) ( $last_scan['scanned_at'] ?? 0 ) ) . ' ' . __( 'ago', 'cloudscale-devtools' ) ); ?></span>
        </div>
        <?php else : ?>
        <div class="cs-dw-row"><span style="color:#94a3b8;"><?php esc_html_e( 'No scans run yet', 'cloudscale-devtools' ); ?></span></div>
        <?php endif; ?>

        <div class="cs-dw-section" style="margin-top:10px;">🔒 <?php esc_html_e( 'Login Security', 'cloudscale-devtools' ); ?></div>
        <div class="cs-dw-row">
            <span class="cs-dw-lbl"><?php esc_html_e( 'BRUTE FORCE', 'cloudscale-devtools' ); ?></span>
            <span style="color:<?php echo $bf_on ? '#16a34a' : '#dc2626'; ?>;font-weight:600;"><?php echo $bf_on ? esc_html__( 'Protected', 'cloudscale-devtools' ) : esc_html__( 'Disabled', 'cloudscale-devtools' ); ?></span>
        </div>
        <div class="cs-dw-row">
            <span class="cs-dw-lbl"><?php esc_html_e( '2FA ADMINS', 'cloudscale-devtools' ); ?></span>
            <span style="color:<?php echo $adm_2fa === $adm_tot ? '#16a34a' : ( $adm_2fa > 0 ? '#d97706' : '#dc2626' ); ?>;font-weight:600;"><?php echo esc_html( $adm_2fa . ' / ' . $adm_tot ); ?></span>
        </div>
        <div class="cs-dw-row">
            <span class="cs-dw-lbl"><?php esc_html_e( 'HIDE LOGIN', 'cloudscale-devtools' ); ?></span>
            <span style="color:<?php echo ! empty( $login_slug ) ? '#16a34a' : '#dc2626'; ?>;font-weight:600;"><?php echo ! empty( $login_slug ) ? '✅ /' . esc_html( $login_slug ) : esc_html__( 'Disabled', 'cloudscale-devtools' ); ?></span>
        </div>
        <div class="cs-dw-row">
            <span class="cs-dw-lbl"><?php esc_html_e( 'FORCE 2FA', 'cloudscale-devtools' ); ?></span>
            <span style="color:<?php echo $force_2fa ? '#16a34a' : '#94a3b8'; ?>;font-weight:600;"><?php echo $force_2fa ? esc_html__( 'On', 'cloudscale-devtools' ) : esc_html__( 'Off', 'cloudscale-devtools' ); ?></span>
        </div>

        <div class="cs-dw-actions">
            <a href="<?php echo esc_url( $base_url ); ?>" class="cs-dw-btn-pri" style="flex:1;text-align:center;"><?php esc_html_e( 'View Cyber and Devtools', 'cloudscale-devtools' ); ?></a>
        </div>
        <?php
    }

    private static function render_home_panel(): void {
        // ── Gather data ───────────────────────────────────────────────────────
        $ai_provider   = get_option( 'csdt_devtools_ai_provider', 'anthropic' );
        $anthropic_key = get_option( 'csdt_devtools_anthropic_key', '' );
        $gemini_key    = get_option( 'csdt_devtools_gemini_key', '' );
        $has_key       = $ai_provider === 'gemini' ? ! empty( $gemini_key ) : ! empty( $anthropic_key );
        $provider_lbl  = $ai_provider === 'gemini' ? 'Google Gemini' : 'Anthropic Claude';

        $history   = get_option( 'csdt_scan_history', [] );
        $last_scan = ! empty( $history ) ? $history[0] : null;
        $score_cls = 'cs-hv-neutral';
        if ( $last_scan ) {
            $s = (int) ( $last_scan['score'] ?? 0 );
            if ( $s >= 75 )     { $score_cls = 'cs-hv-green'; }
            elseif ( $s >= 55 ) { $score_cls = 'cs-hv-orange'; }
            else                { $score_cls = 'cs-hv-red'; }
        }

        $fixes      = self::get_quick_fixes();
        $fixes_tot  = count( $fixes );
        $fixes_done = count( array_filter( $fixes, function ( $f ) { return ! empty( $f['fixed'] ); } ) );
        $fixes_cls  = $fixes_done === $fixes_tot ? 'cs-hv-green' : ( $fixes_done >= $fixes_tot - 1 ? 'cs-hv-orange' : 'cs-hv-red' );

        $bf_on     = get_option( 'csdt_devtools_brute_force_enabled', '1' ) === '1';

        $admins      = get_users( [ 'role' => 'administrator' ] );
        $adm_tot     = count( $admins );
        $email_2fa   = get_option( 'csdt_devtools_2fa_method', 'off' ) === 'email';
        $adm_2fa     = 0;
        foreach ( $admins as $u ) {
            if ( get_user_meta( $u->ID, 'csdt_devtools_totp_enabled', true ) === '1'
                 || ! empty( get_user_meta( $u->ID, 'csdt_devtools_passkeys', true ) )
                 || $email_2fa ) {
                $adm_2fa++;
            }
        }

        $login_slug  = get_option( 'csdt_devtools_login_slug', '' );
        $sched_on    = get_option( 'csdt_scan_schedule_enabled', '0' ) === '1';
        $sched_freq  = get_option( 'csdt_scan_schedule_freq', 'weekly' );
        $base_url    = admin_url( 'tools.php?page=cloudscale-devtools' );
        ?>
        <style>
        .cs-home-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;padding:20px 20px 0;}
        .cs-home-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;}
        .cs-home-card-hd{background:#f8fafc;border-bottom:1px solid #e5e7eb;padding:11px 16px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#64748b;display:flex;align-items:center;justify-content:space-between;gap:6px;text-decoration:none;}
        a.cs-home-card-hd:hover{background:#f1f5f9;color:#0e6b8f;}
        .cs-home-card-hd-lft{display:flex;align-items:center;gap:6px;}
        .cs-home-card-hd-arrow{font-size:11px;color:#94a3b8;}
        a.cs-home-card-hd:hover .cs-home-card-hd-arrow{color:#0e6b8f;}
        .cs-home-row{display:flex;justify-content:space-between;align-items:center;padding:9px 16px;border-bottom:1px solid #f1f5f9;font-size:13px;}
        .cs-home-row:last-child{border-bottom:none;}
        .cs-home-lbl{color:#94a3b8;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;flex-shrink:0;}
        .cs-home-rval{display:flex;align-items:center;gap:8px;}
        .cs-hv-green{color:#16a34a;font-weight:600;}
        .cs-hv-red{color:#dc2626;font-weight:600;}
        .cs-hv-orange{color:#d97706;font-weight:600;}
        .cs-hv-neutral{color:#1e293b;font-weight:600;}
        .cs-hv-muted{color:#94a3b8;}
        .cs-home-nav{font-size:11px;font-weight:600;color:#0e6b8f;text-decoration:none;white-space:nowrap;padding:2px 6px;border-radius:4px;background:#eff6ff;}
        .cs-home-nav:hover{background:#dbeafe;color:#1d4ed8;}
        .cs-home-actions{display:flex;gap:10px;padding:16px 20px 20px;}
        .cs-home-actions .cs-btn-primary,.cs-home-actions .cs-btn-secondary{flex:1;text-align:center;justify-content:center;}
        </style>

        <?php
        $sec_url   = $base_url . '&tab=security';
        $login_url = $base_url . '&tab=login';
        ?>
        <div id="cs-panel-home" class="cs-panel" style="margin-bottom:0;">
        <div class="cs-home-grid">

            <!-- AI Security -->
            <div class="cs-home-card">
                <a href="<?php echo esc_url( $sec_url ); ?>" class="cs-home-card-hd">
                    <span class="cs-home-card-hd-lft">🤖 <?php esc_html_e( 'AI Security', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-home-card-hd-arrow">Security Scan →</span>
                </a>
                <div class="cs-home-row">
                    <span class="cs-home-lbl"><?php esc_html_e( 'STATUS', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-home-rval">
                        <span class="<?php echo $has_key ? 'cs-hv-green' : 'cs-hv-red'; ?>">
                            <?php echo $has_key ? '✅ ' . esc_html__( 'Configured', 'cloudscale-devtools' ) : '⚠️ ' . esc_html__( 'Not set', 'cloudscale-devtools' ); ?>
                        </span>
                        <?php if ( ! $has_key ) : ?><a href="<?php echo esc_url( $sec_url ); ?>" class="cs-home-nav"><?php esc_html_e( 'Set up API key', 'cloudscale-devtools' ); ?> →</a><?php endif; ?>
                    </span>
                </div>
                <div class="cs-home-row">
                    <span class="cs-home-lbl"><?php esc_html_e( 'PROVIDER', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-hv-neutral"><?php echo $has_key ? esc_html( $provider_lbl ) : '<span class="cs-hv-muted">—</span>'; ?></span>
                </div>
                <div class="cs-home-row">
                    <span class="cs-home-lbl"><?php esc_html_e( 'SCHEDULED SCANS', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-home-rval">
                        <span class="<?php echo $sched_on ? 'cs-hv-green' : 'cs-hv-muted'; ?>">
                            <?php echo $sched_on ? esc_html( ucfirst( $sched_freq ) ) : esc_html__( 'Disabled', 'cloudscale-devtools' ); ?>
                        </span>
                        <?php if ( ! $sched_on ) : ?><a href="<?php echo esc_url( $sec_url ); ?>" class="cs-home-nav"><?php esc_html_e( 'Schedule', 'cloudscale-devtools' ); ?> →</a><?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Last Scan -->
            <div class="cs-home-card">
                <a href="<?php echo esc_url( $sec_url ); ?>" class="cs-home-card-hd">
                    <span class="cs-home-card-hd-lft">🛡️ <?php esc_html_e( 'Last Security Scan', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-home-card-hd-arrow"><?php echo $last_scan ? esc_html__( 'View report', 'cloudscale-devtools' ) : esc_html__( 'Run scan', 'cloudscale-devtools' ); ?> →</span>
                </a>
                <?php if ( $last_scan ) : ?>
                <div class="cs-home-row">
                    <span class="cs-home-lbl"><?php esc_html_e( 'SCORE', 'cloudscale-devtools' ); ?></span>
                    <span class="<?php echo esc_attr( $score_cls ); ?>"><?php echo esc_html( ( $last_scan['score_label'] ?? '' ) . ' · ' . ( $last_scan['score'] ?? '' ) ); ?></span>
                </div>
                <div class="cs-home-row">
                    <span class="cs-home-lbl"><?php esc_html_e( 'CRITICAL', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-home-rval">
                        <span class="<?php echo (int) ( $last_scan['critical_count'] ?? 0 ) > 0 ? 'cs-hv-red' : 'cs-hv-green'; ?>"><?php echo (int) ( $last_scan['critical_count'] ?? 0 ); ?></span>
                        <?php if ( (int) ( $last_scan['critical_count'] ?? 0 ) > 0 ) : ?><a href="<?php echo esc_url( $sec_url ); ?>" class="cs-home-nav"><?php esc_html_e( 'View', 'cloudscale-devtools' ); ?> →</a><?php endif; ?>
                    </span>
                </div>
                <div class="cs-home-row">
                    <span class="cs-home-lbl"><?php esc_html_e( 'HIGH', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-home-rval">
                        <span class="<?php echo (int) ( $last_scan['high_count'] ?? 0 ) > 0 ? 'cs-hv-orange' : 'cs-hv-green'; ?>"><?php echo (int) ( $last_scan['high_count'] ?? 0 ); ?></span>
                        <?php if ( (int) ( $last_scan['high_count'] ?? 0 ) > 0 ) : ?><a href="<?php echo esc_url( $sec_url ); ?>" class="cs-home-nav"><?php esc_html_e( 'View', 'cloudscale-devtools' ); ?> →</a><?php endif; ?>
                    </span>
                </div>
                <div class="cs-home-row">
                    <span class="cs-home-lbl"><?php esc_html_e( 'TYPE', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-hv-neutral"><?php echo ( $last_scan['type'] ?? '' ) === 'deep' ? esc_html__( 'Deep Dive', 'cloudscale-devtools' ) : esc_html__( 'Standard', 'cloudscale-devtools' ); ?></span>
                </div>
                <div class="cs-home-row">
                    <span class="cs-home-lbl"><?php esc_html_e( 'SCANNED', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-hv-muted"><?php echo esc_html( human_time_diff( (int) ( $last_scan['scanned_at'] ?? 0 ) ) . ' ' . __( 'ago', 'cloudscale-devtools' ) ); ?></span>
                </div>
                <?php else : ?>
                <div class="cs-home-row">
                    <span class="cs-hv-muted"><?php esc_html_e( 'No scans run yet', 'cloudscale-devtools' ); ?></span>
                    <a href="<?php echo esc_url( $sec_url ); ?>" class="cs-home-nav"><?php esc_html_e( 'Run now', 'cloudscale-devtools' ); ?> →</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Fixes -->
            <div class="cs-home-card">
                <a href="<?php echo esc_url( $sec_url ); ?>" class="cs-home-card-hd">
                    <span class="cs-home-card-hd-lft">⚡ <?php esc_html_e( 'Quick Fixes', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-home-card-hd-arrow"><?php esc_html_e( 'Fix all', 'cloudscale-devtools' ); ?> →</span>
                </a>
                <div class="cs-home-row">
                    <span class="cs-home-lbl"><?php esc_html_e( 'RESOLVED', 'cloudscale-devtools' ); ?></span>
                    <span class="<?php echo esc_attr( $fixes_cls ); ?>"><?php echo esc_html( $fixes_done . ' / ' . $fixes_tot ); ?></span>
                </div>
                <?php foreach ( $fixes as $fix ) : ?>
                <div class="cs-home-row">
                    <span class="cs-home-lbl" style="max-width:55%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo esc_attr( $fix['title'] ); ?>"><?php echo esc_html( strtoupper( $fix['title'] ) ); ?></span>
                    <span class="cs-home-rval">
                        <span class="<?php echo ! empty( $fix['fixed'] ) ? 'cs-hv-green' : 'cs-hv-red'; ?>"><?php echo ! empty( $fix['fixed'] ) ? '✅' : '⚠️'; ?></span>
                        <?php if ( empty( $fix['fixed'] ) ) : ?><a href="<?php echo esc_url( $sec_url ); ?>" class="cs-home-nav"><?php esc_html_e( 'Fix', 'cloudscale-devtools' ); ?> →</a><?php endif; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Login Security -->
            <div class="cs-home-card">
                <a href="<?php echo esc_url( $login_url ); ?>" class="cs-home-card-hd">
                    <span class="cs-home-card-hd-lft">🔐 <?php esc_html_e( 'Login Security', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-home-card-hd-arrow"><?php esc_html_e( 'Settings', 'cloudscale-devtools' ); ?> →</span>
                </a>
                <div class="cs-home-row">
                    <span class="cs-home-lbl"><?php esc_html_e( 'BRUTE FORCE', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-home-rval">
                        <span class="<?php echo $bf_on ? 'cs-hv-green' : 'cs-hv-red'; ?>"><?php echo $bf_on ? '✅ ' . esc_html__( 'Enabled', 'cloudscale-devtools' ) : '⚠️ ' . esc_html__( 'Disabled', 'cloudscale-devtools' ); ?></span>
                        <?php if ( ! $bf_on ) : ?><a href="<?php echo esc_url( $login_url ); ?>" class="cs-home-nav"><?php esc_html_e( 'Enable', 'cloudscale-devtools' ); ?> →</a><?php endif; ?>
                    </span>
                </div>
                <div class="cs-home-row">
                    <span class="cs-home-lbl"><?php esc_html_e( '2FA ADMINS', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-home-rval">
                        <span class="<?php echo $adm_2fa === $adm_tot ? 'cs-hv-green' : 'cs-hv-orange'; ?>"><?php echo esc_html( $adm_2fa . ' / ' . $adm_tot ); ?></span>
                        <?php if ( $adm_2fa < $adm_tot ) : ?><a href="<?php echo esc_url( $login_url ); ?>" class="cs-home-nav"><?php esc_html_e( 'Set up 2FA', 'cloudscale-devtools' ); ?> →</a><?php endif; ?>
                    </span>
                </div>
                <div class="cs-home-row">
                    <span class="cs-home-lbl"><?php esc_html_e( 'HIDE LOGIN', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-home-rval">
                        <span class="<?php echo $login_slug ? 'cs-hv-green' : 'cs-hv-orange'; ?>"><?php echo $login_slug ? '✅ /' . esc_html( $login_slug ) : '⚠️ ' . esc_html__( 'Default URL', 'cloudscale-devtools' ); ?></span>
                        <?php if ( ! $login_slug ) : ?><a href="<?php echo esc_url( $login_url ); ?>" class="cs-home-nav"><?php esc_html_e( 'Set up', 'cloudscale-devtools' ); ?> →</a><?php endif; ?>
                    </span>
                </div>
                <div class="cs-home-row">
                    <span class="cs-home-lbl"><?php esc_html_e( 'FORCE 2FA', 'cloudscale-devtools' ); ?></span>
                    <?php $force_2fa = get_option( 'csdt_devtools_2fa_force_admins', '0' ) === '1'; ?>
                    <span class="cs-home-rval">
                        <span class="<?php echo $force_2fa ? 'cs-hv-green' : 'cs-hv-muted'; ?>"><?php echo $force_2fa ? '✅ ' . esc_html__( 'Enforced', 'cloudscale-devtools' ) : esc_html__( 'Optional', 'cloudscale-devtools' ); ?></span>
                        <?php if ( ! $force_2fa ) : ?><a href="<?php echo esc_url( $login_url ); ?>" class="cs-home-nav"><?php esc_html_e( 'Enforce', 'cloudscale-devtools' ); ?> →</a><?php endif; ?>
                    </span>
                </div>
            </div>

        </div><!-- /grid -->

        <div class="cs-home-actions">
            <a href="<?php echo esc_url( $sec_url ); ?>" class="cs-btn-primary">🔍 <?php esc_html_e( 'Run Security Scan', 'cloudscale-devtools' ); ?></a>
            <a href="<?php echo esc_url( $login_url ); ?>" class="cs-btn-secondary">🔐 <?php esc_html_e( 'Login Security', 'cloudscale-devtools' ); ?></a>
        </div>

        </div><!-- /cs-panel -->
        <?php
    }

    private static function render_security_panel(): void {
        ?>
        <div class="cs-panel" id="cs-panel-security">
            <div class="cs-section-header cs-section-header-red">
                <span>🛡️ <?php esc_html_e( 'AI Cyber Audit', 'cloudscale-devtools' ); ?></span>
                <span class="cs-header-hint"><?php esc_html_e( 'Claude AI analyses your WordPress configuration and gives prioritised remediation advice', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'cyber-audit', 'AI Cyber Audit', [
                    [ 'name' => 'Quick Fixes',         'rec' => 'Critical',     'html' => 'Automated one-click remediations for common misconfigurations — moving <code>debug.log</code> outside the web root, disabling XML-RPC, hiding the WordPress version, and more. Each fix shows its current status so you can see what still needs attention at a glance.' ],
                    [ 'name' => 'Standard Cyber Scan', 'rec' => 'Recommended',  'html' => 'A fast scan (a few seconds) that checks your WordPress core settings, active plugins and themes, user accounts, file permissions, and wp-config.php for common security misconfigurations. Results are sent to an AI model which prioritises findings and gives tailored remediation advice.' ],
                    [ 'name' => 'Deep Dive Scan',      'rec' => 'Recommended',  'html' => 'A comprehensive scan that adds: static code analysis of plugin PHP files (looking for <code>eval</code>, shell functions, obfuscation, and suspicious patterns), external HTTP probes (open redirects, directory listing on <code>/wp-content/plugins/</code> and <code>/wp-content/themes/</code>, weak TLS protocols, CORS headers), DNS checks (SPF, DMARC, DKIM), PHP end-of-life status, and an AI-powered code triage step that classifies each static finding as confirmed, false positive, or needs-context.' ],
                    [ 'name' => 'Code Triage',         'rec' => 'Info',         'html' => 'After a deep scan, the top 10 highest-risk static findings are sent to an AI model with ±10 lines of surrounding code. The model classifies each as <strong>Confirmed</strong> (genuine risk), <strong>False Positive</strong> (safe code), or <strong>Needs Context</strong> (depends on usage). Only confirmed findings are forwarded to the main audit AI, reducing noise.' ],
                    [ 'name' => 'Scan History',        'rec' => 'Info',         'html' => 'The last 10 scan results are saved automatically. Click any entry in the history table to reload that report instantly — useful for comparing your security posture over time or reviewing a scan after making changes.' ],
                    [ 'name' => 'Scheduled Scans',     'rec' => 'Optional',     'html' => 'Run a deep scan automatically on a daily or weekly schedule. Results are stored in scan history. Enable email alerts to receive the AI summary in your inbox whenever a scheduled scan completes.' ],
                    [ 'name' => 'AI Providers',        'rec' => 'Info',         'html' => '<p>Two AI providers are supported. You supply your own API key — keys are stored only in your WordPress database (<code>wp_options</code>) and sent only to the provider\'s own API endpoint.</p><p><strong>Anthropic Claude</strong> — recommended for best results.<br>Get your key: <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com/settings/keys</a><br>Models: <code>claude-sonnet-4-6</code> (fast, cost-effective) · <code>claude-opus-4-7</code> (most capable)<br><a href="https://docs.anthropic.com/en/docs/about-claude/models/overview" target="_blank" rel="noopener">View latest Claude models →</a></p><p><strong>Google Gemini</strong> — free tier available.<br>Get your key: <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">aistudio.google.com/app/apikey</a><br>Models: <code>gemini-2.0-flash</code> (fast, free tier) · <code>gemini-2.5-pro</code> (most capable)<br><a href="https://ai.google.dev/gemini-api/docs/models" target="_blank" rel="noopener">View latest Gemini models →</a></p><p>Deep Dive scans run two AI calls — Code Triage pre-classification uses the faster model first to reduce cost before the main audit call.</p>' ],
                ],
                'The <strong>AI Cyber Audit</strong> uses frontier AI — Anthropic Claude or Google Gemini — to analyse your WordPress installation and produce a prioritised, scored security report in under 60 seconds. Think of it as a security consultant in your admin panel: it doesn\'t just list what\'s wrong, it tells you what to fix first and exactly how to fix it. A Standard scan takes seconds; a Deep Dive goes further with live HTTP probes, DNS checks, TLS quality analysis, and static code scanning of your plugins. You need an API key from one of the two providers — a free Gemini tier is available with no credit card required.' ); ?>
            </div>
            <div class="cs-panel-body">

                <div class="cs-tab-intro">
                    <p><?php echo wp_kses( __( '<strong>AI Cyber Audit</strong> connects to a frontier AI model — Anthropic Claude or Google Gemini — to analyse your WordPress installation and deliver a prioritised security report in under 60 seconds. It checks your core configuration, plugins, user accounts, file permissions, and key wp-config.php settings, then uses AI to score each finding as Critical / High / Medium / Low and give you specific steps to fix it. The <strong>Deep Dive</strong> extends this with live HTTP probes, DNS checks, TLS quality analysis, static PHP code scanning, and AI-powered triage of suspicious code patterns.', 'cloudscale-devtools' ), [ 'strong' => [] ] ); ?></p>
                    <p><?php echo wp_kses( __( 'To get started, select an AI provider below, paste in your API key, and click <strong>Run AI Cyber Audit</strong>. You can get a free Gemini key at <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">aistudio.google.com</a> with no credit card required.', 'cloudscale-devtools' ), [ 'strong' => [], 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] ); ?></p>
                </div>

                <div class="cs-sec-settings">

                    <div class="cs-sec-row">
                        <span class="cs-sec-label"><?php esc_html_e( 'AI Provider:', 'cloudscale-devtools' ); ?></span>
                        <div class="cs-sec-control">
                            <select id="cs-sec-provider" class="cs-sec-select">
                                <option value="anthropic"><?php esc_html_e( 'Anthropic Claude', 'cloudscale-devtools' ); ?></option>
                                <option value="gemini"><?php esc_html_e( 'Google Gemini', 'cloudscale-devtools' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="cs-sec-row" id="cs-row-anthropic-key">
                        <span class="cs-sec-label"><?php esc_html_e( 'API Key:', 'cloudscale-devtools' ); ?></span>
                        <div class="cs-sec-control">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                <input type="password" id="cs-sec-api-key" class="cs-text-input cs-sec-key-input"
                                       autocomplete="off" placeholder="sk-ant-api03-…">
                                <button type="button" class="cs-btn-secondary" id="cs-sec-test-key">
                                    <?php esc_html_e( 'Test Key', 'cloudscale-devtools' ); ?>
                                </button>
                                <span id="cs-sec-key-status" class="cs-sec-key-status"></span>
                            </div>
                            <span class="cs-hint"><?php echo wp_kses(
                                __( 'Get your key at <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com</a>. Stored encrypted in wp_options.', 'cloudscale-devtools' ),
                                [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ]
                            ); ?></span>
                        </div>
                    </div>

                    <div class="cs-sec-row" id="cs-row-gemini-key" style="display:none">
                        <span class="cs-sec-label"><?php esc_html_e( 'API Key:', 'cloudscale-devtools' ); ?></span>
                        <div class="cs-sec-control">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                <input type="password" id="cs-sec-gemini-key" class="cs-text-input cs-sec-key-input"
                                       autocomplete="off" placeholder="AIza…">
                                <button type="button" class="cs-btn-secondary" id="cs-sec-test-gemini-key">
                                    <?php esc_html_e( 'Test Key', 'cloudscale-devtools' ); ?>
                                </button>
                                <span id="cs-sec-gemini-key-status" class="cs-sec-key-status"></span>
                            </div>
                            <span class="cs-hint"><?php echo wp_kses(
                                __( 'Get your key at <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">aistudio.google.com</a>. Stored in wp_options.', 'cloudscale-devtools' ),
                                [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ]
                            ); ?></span>
                        </div>
                    </div>

                    <div class="cs-sec-row">
                        <span class="cs-sec-label"><?php esc_html_e( 'Audit model:', 'cloudscale-devtools' ); ?></span>
                        <div class="cs-sec-control">
                            <select id="cs-sec-model" class="cs-sec-select">
                                <option value="_auto">✨ Auto</option>
                            </select>
                        </div>
                    </div>

                    <div class="cs-sec-row">
                        <span class="cs-sec-label"><?php esc_html_e( 'Deep dive model:', 'cloudscale-devtools' ); ?></span>
                        <div class="cs-sec-control">
                            <select id="cs-sec-deep-model" class="cs-sec-select">
                                <option value="_auto_deep">✨ Auto</option>
                            </select>
                        </div>
                    </div>

                    <div class="cs-sec-row cs-sec-row-prompt">
                        <span class="cs-sec-label"><?php esc_html_e( 'System prompt:', 'cloudscale-devtools' ); ?></span>
                        <div class="cs-sec-control">
                            <textarea id="cs-sec-prompt" class="cs-sec-prompt-area" rows="10"></textarea>
                            <div style="display:flex;align-items:center;gap:6px;margin-top:8px;flex-wrap:wrap">
                                <button type="button" class="cs-btn-secondary cs-btn-sm" id="cs-sec-copy-prompt">⎘ <?php esc_html_e( 'Copy', 'cloudscale-devtools' ); ?></button>
                                <button type="button" class="cs-btn-secondary cs-btn-sm" id="cs-sec-reset-prompt"><?php esc_html_e( 'Reset to default', 'cloudscale-devtools' ); ?></button>
                                <div style="flex:1"></div>
                                <button type="button" class="cs-btn-primary" id="cs-sec-save">💾 <?php esc_html_e( 'Save AI Settings', 'cloudscale-devtools' ); ?></button>
                                <span class="cs-settings-saved" id="cs-sec-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                            </div>
                        </div>
                    </div>

                </div>

                <hr class="cs-sec-divider">

                <!-- Scheduled scan settings -->
                <?php
                $sched_enabled  = get_option( 'csdt_scan_schedule_enabled', '0' ) === '1';
                $sched_freq     = get_option( 'csdt_scan_schedule_freq',    'weekly' );
                $sched_type     = get_option( 'csdt_scan_schedule_type',    'deep' );
                $sched_email    = get_option( 'csdt_scan_schedule_email',   '1' ) === '1';
                $sched_ntfy_url = get_option( 'csdt_scan_schedule_ntfy_url', '' );
                $sched_ntfy_tok = get_option( 'csdt_scan_schedule_ntfy_token', '' );
                $next_run       = wp_next_scheduled( 'csdt_scheduled_scan' );
                ?>
                <div class="cs-sec-settings" style="margin-top:0;padding-top:0;">
                    <div class="cs-sec-row">
                        <span class="cs-sec-label"><?php esc_html_e( 'Scheduled Scan:', 'cloudscale-devtools' ); ?></span>
                        <div class="cs-sec-control">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" id="cs-sched-enabled" <?php checked( $sched_enabled ); ?>>
                                <span><?php esc_html_e( 'Run automatically on a schedule', 'cloudscale-devtools' ); ?></span>
                            </label>
                            <?php if ( $next_run ) : ?>
                            <span class="cs-hint"><?php printf( esc_html__( 'Next run: %s', 'cloudscale-devtools' ), esc_html( wp_date( 'D j M Y, g:ia', $next_run ) ) ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div id="cs-sched-options" <?php echo $sched_enabled ? '' : 'style="display:none"'; ?>>
                        <div class="cs-sec-row">
                            <span class="cs-sec-label"><?php esc_html_e( 'Frequency:', 'cloudscale-devtools' ); ?></span>
                            <div class="cs-sec-control">
                                <select id="cs-sched-freq" class="cs-sec-select" style="width:auto;">
                                    <option value="weekly"  <?php selected( $sched_freq, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'cloudscale-devtools' ); ?></option>
                                    <option value="monthly" <?php selected( $sched_freq, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'cloudscale-devtools' ); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="cs-sec-row">
                            <span class="cs-sec-label"><?php esc_html_e( 'Scan type:', 'cloudscale-devtools' ); ?></span>
                            <div class="cs-sec-control">
                                <select id="cs-sched-type" class="cs-sec-select" style="width:auto;">
                                    <option value="standard" <?php selected( $sched_type, 'standard' ); ?>><?php esc_html_e( 'AI Cyber Audit (fast)', 'cloudscale-devtools' ); ?></option>
                                    <option value="deep"     <?php selected( $sched_type, 'deep' ); ?>><?php esc_html_e( 'AI Deep Dive Cyber Audit (comprehensive)', 'cloudscale-devtools' ); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="cs-sec-row">
                            <span class="cs-sec-label"><?php esc_html_e( 'Notify via email:', 'cloudscale-devtools' ); ?></span>
                            <div class="cs-sec-control">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="checkbox" id="cs-sched-email" <?php checked( $sched_email ); ?>>
                                    <span><?php printf( esc_html__( 'Send results to %s', 'cloudscale-devtools' ), '<strong>' . esc_html( get_option( 'admin_email' ) ) . '</strong>' ); ?></span>
                                </label>
                            </div>
                        </div>
                        <div class="cs-sec-row">
                            <span class="cs-sec-label"><?php esc_html_e( 'ntfy.sh topic:', 'cloudscale-devtools' ); ?></span>
                            <div class="cs-sec-control">
                                <input type="text" id="cs-sched-ntfy-url" class="cs-text-input"
                                       placeholder="https://ntfy.sh/your-topic"
                                       value="<?php echo esc_attr( $sched_ntfy_url ); ?>"
                                       style="max-width:320px;">
                                <span class="cs-hint"><?php echo wp_kses( __( 'Optional push notification via <a href="https://ntfy.sh" target="_blank" rel="noopener">ntfy.sh</a>. Use a private topic or self-hosted server.', 'cloudscale-devtools' ), [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] ); ?></span>
                            </div>
                        </div>
                        <div class="cs-sec-row">
                            <span class="cs-sec-label"><?php esc_html_e( 'ntfy auth token:', 'cloudscale-devtools' ); ?></span>
                            <div class="cs-sec-control">
                                <input type="password" id="cs-sched-ntfy-token" class="cs-text-input"
                                       autocomplete="off" placeholder="<?php echo $sched_ntfy_tok ? '••••••••' : esc_attr__( 'Optional — for protected topics', 'cloudscale-devtools' ); ?>"
                                       style="max-width:320px;">
                                <span class="cs-hint"><?php esc_html_e( 'Bearer token if your topic requires authentication.', 'cloudscale-devtools' ); ?></span>
                            </div>
                        </div>
                        <div class="cs-sec-row">
                            <span class="cs-sec-label"></span>
                            <div class="cs-sec-control">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <button type="button" class="cs-btn-primary" id="cs-sched-save">💾 <?php esc_html_e( 'Save Schedule', 'cloudscale-devtools' ); ?></button>
                                    <span class="cs-settings-saved" id="cs-sched-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="cs-sec-divider">

                <!-- Quick Fixes -->
                <div class="cs-section-header" style="background:linear-gradient(90deg,#1a1f2e 0%,#1e2535 100%);border-left:3px solid #f59e0b;margin-bottom:0;">
                    <span>⚡ <?php esc_html_e( 'Quick Fixes', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-header-hint"><?php esc_html_e( 'One-click hardening actions for common WordPress security settings', 'cloudscale-devtools' ); ?></span>
                </div>
                <div id="cs-quick-fixes-panel" style="padding:12px 0 4px;">
                <?php foreach ( self::get_quick_fixes() as $fix ) :
                    $is_fixed = (bool) $fix['fixed'];
                ?>
                    <div class="cs-quick-fix-row" data-fix-id="<?php echo esc_attr( $fix['id'] ); ?>" style="display:flex;align-items:center;gap:12px;padding:10px 14px;margin-bottom:6px;background:<?php echo $is_fixed ? 'rgba(0,0,0,0.02)' : '#fff'; ?>;border-radius:6px;border:1px solid <?php echo $is_fixed ? 'rgba(0,0,0,0.07)' : 'rgba(0,0,0,0.12)'; ?>;">
                        <div style="flex-shrink:0;font-size:16px;line-height:1;"><?php echo $is_fixed ? '<span style="color:#16a34a;">✓</span>' : '<span style="color:#d97706;">⚠</span>'; ?></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:600;color:<?php echo $is_fixed ? '#6b7280' : '#1d2327'; ?>;"><?php echo esc_html( $fix['title'] ); ?></div>
                            <div style="font-size:12px;color:#50575e;margin-top:2px;"><?php echo esc_html( $fix['detail'] ); ?></div>
                        </div>
                        <div style="flex-shrink:0;">
                        <?php if ( $is_fixed ) : ?>
                            <span style="font-size:12px;color:#16a34a;font-weight:600;">Fixed ✓</span>
                        <?php else : ?>
                            <button type="button" class="cs-btn-primary cs-btn-sm cs-quick-fix-btn"
                                    data-fix-id="<?php echo esc_attr( $fix['id'] ); ?>"
                                    style="white-space:nowrap;">
                                <?php echo esc_html( $fix['fix_label'] ); ?>
                            </button>
                        <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>

                <?php self::render_csp_panel(); ?>

                <hr class="cs-sec-divider">

                <div class="cs-scan-row">
                    <div class="cs-scan-col">
                        <div class="cs-scan-col-header">
                            <span class="cs-scan-col-title"><?php esc_html_e( 'Internal Config Audit', 'cloudscale-devtools' ); ?></span>
                            <span class="cs-scan-col-hint"><?php esc_html_e( 'WordPress settings, plugins, users, debug flags — fast', 'cloudscale-devtools' ); ?></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                            <button id="cs-vuln-scan-btn" class="cs-btn-primary" disabled>
                                🔍 <?php esc_html_e( 'Run AI Cyber Audit', 'cloudscale-devtools' ); ?>
                            </button>
                            <button id="cs-vuln-cancel-btn" class="cs-btn-secondary" style="display:none">
                                ✕ <?php esc_html_e( 'Cancel', 'cloudscale-devtools' ); ?>
                            </button>
                            <span id="cs-vuln-model-badge" class="cs-scan-model-badge"></span>
                        </div>
                        <span id="cs-vuln-scan-status" class="cs-vuln-inline-msg"></span>
                        <div id="cs-vuln-progress" class="cs-scan-progress">
                            <div class="cs-scan-progress-fill"></div>
                        </div>
                        <div id="cs-vuln-results" class="cs-vuln-results" style="display:none;margin-top:6px"></div>
                    </div>

                    <div class="cs-scan-col cs-scan-col-deep">
                        <div class="cs-scan-col-header">
                            <span class="cs-scan-col-title"><?php esc_html_e( 'AI Deep Dive Cyber Audit', 'cloudscale-devtools' ); ?></span>
                            <span class="cs-scan-col-hint"><?php esc_html_e( 'Internal config + plugin code scan + external exposure: SSL cert, login/xmlrpc, REST user enum, author enum, directory listing — 30–60s', 'cloudscale-devtools' ); ?></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                            <button id="cs-deep-scan-btn" class="cs-btn-primary cs-btn-deep" disabled>
                                🕵️ <?php esc_html_e( 'Run AI Deep Dive Cyber Audit', 'cloudscale-devtools' ); ?>
                            </button>
                            <button id="cs-deep-cancel-btn" class="cs-btn-secondary" style="display:none">
                                ✕ <?php esc_html_e( 'Cancel', 'cloudscale-devtools' ); ?>
                            </button>
                            <span id="cs-deep-model-badge" class="cs-scan-model-badge"></span>
                        </div>
                        <span id="cs-deep-scan-status" class="cs-vuln-inline-msg"></span>
                        <div id="cs-deep-progress" class="cs-scan-progress">
                            <div class="cs-scan-progress-fill deep"></div>
                        </div>
                        <div id="cs-deep-results" class="cs-vuln-results" style="display:none;margin-top:6px"></div>
                    </div>
                </div>

            </div>

            <!-- Scan History -->
            <div class="cs-section-header" style="margin-top:24px;background:linear-gradient(90deg,#1a1f2e 0%,#1e2535 100%);border-left:3px solid #6366f1;">
                <span>📈 <?php esc_html_e( 'Scan History', 'cloudscale-devtools' ); ?></span>
                <span class="cs-header-hint"><?php esc_html_e( 'Last 10 scans — track your security score over time', 'cloudscale-devtools' ); ?></span>
            </div>
            <div id="cs-scan-history-wrap" style="padding:12px 0;">
                <?php
                $history = get_option( 'csdt_scan_history', [] );
                if ( ! empty( $history ) ) : ?>
                <canvas id="cs-scan-history-chart" height="180"
                    style="width:100%;max-width:100%;display:block;margin-bottom:20px;border-radius:6px;background:#fff;border:1px solid #e2e8f0;"></canvas>
                <?php endif; if ( empty( $history ) ) :
                ?>
                    <p style="color:#888;font-size:13px;margin:0;padding:8px 0;"><?php esc_html_e( 'No scan history yet. Run your first AI Cyber Audit above.', 'cloudscale-devtools' ); ?></p>
                <?php else : ?>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                    <?php foreach ( $history as $entry ) :
                        $score       = (int) ( $entry['score'] ?? 0 );
                        $label       = esc_html( $entry['score_label'] ?? '' );
                        $type_label  = $entry['type'] === 'deep' ? 'Deep Dive' : 'AI Cyber Audit';
                        $date        = $entry['scanned_at'] ? wp_date( 'D j M Y, g:ia', $entry['scanned_at'] ) : '';
                        $score_color = $score >= 90 ? '#22c55e' : ( $score >= 75 ? '#4ade80' : ( $score >= 55 ? '#fbbf24' : ( $score >= 35 ? '#f97316' : '#ef4444' ) ) );
                    ?>
                        <div style="display:flex;align-items:flex-start;gap:14px;padding:10px 12px;background:rgba(255,255,255,0.03);border-radius:6px;border:1px solid rgba(255,255,255,0.06);">
                            <div style="flex-shrink:0;text-align:center;min-width:48px;">
                                <div style="font-size:1.4rem;font-weight:700;color:<?php echo esc_attr( $score_color ); ?>;line-height:1;"><?php echo esc_html( $score ); ?></div>
                                <div style="font-size:10px;color:<?php echo esc_attr( $score_color ); ?>;opacity:.8;"><?php echo esc_html( $label ); ?></div>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:12px;font-weight:600;color:#9da5b4;margin-bottom:3px;">
                                    <?php echo esc_html( $type_label ); ?>
                                    <span style="font-weight:400;opacity:.7;margin-left:8px;"><?php echo esc_html( $date ); ?></span>
                                </div>
                                <div style="font-size:12px;color:#c5cad4;line-height:1.5;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
                                    <?php echo esc_html( $entry['summary'] ?? '' ); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
        <?php
    }

    /* ==================================================================
       Vulnerability Scan AJAX handlers
       ================================================================== */

    public static function add_cron_schedules( array $schedules ): array {
        $schedules['csdt_monthly'] = [
            'interval' => 30 * DAY_IN_SECONDS,
            'display'  => __( 'Once Monthly', 'cloudscale-devtools' ),
        ];
        $schedules['csdt_every_5min'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __( 'Every 5 Minutes', 'cloudscale-devtools' ),
        ];
        return $schedules;
    }

    public static function ajax_save_schedule(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $enabled   = ! empty( $_POST['enabled'] ) && $_POST['enabled'] === '1';
        $freq      = in_array( $_POST['freq']  ?? '', [ 'weekly', 'monthly' ], true ) ? sanitize_key( $_POST['freq'] ) : 'weekly';
        $type      = in_array( $_POST['type']  ?? '', [ 'standard', 'deep' ], true )  ? sanitize_key( $_POST['type'] ) : 'deep';
        $email     = ! empty( $_POST['email_notify'] ) && $_POST['email_notify'] === '1';
        $ntfy_url  = esc_url_raw( wp_unslash( $_POST['ntfy_url']   ?? '' ) );
        $ntfy_tok  = sanitize_text_field( wp_unslash( $_POST['ntfy_token'] ?? '' ) );
        $ntfy_tok  = trim( str_replace( '•', '', $ntfy_tok ) );

        update_option( 'csdt_scan_schedule_enabled',    $enabled ? '1' : '0', false );
        update_option( 'csdt_scan_schedule_freq',       $freq,                false );
        update_option( 'csdt_scan_schedule_type',       $type,                false );
        update_option( 'csdt_scan_schedule_email',      $email ? '1' : '0',   false );
        update_option( 'csdt_scan_schedule_ntfy_url',   $ntfy_url,            false );
        if ( $ntfy_tok !== '' ) {
            update_option( 'csdt_scan_schedule_ntfy_token', $ntfy_tok, false );
        }

        // Re-register cron event
        wp_clear_scheduled_hook( 'csdt_scheduled_scan' );
        $next_run = null;
        if ( $enabled ) {
            $recurrence = $freq === 'monthly' ? 'csdt_monthly' : 'weekly';
            wp_schedule_event( time() + HOUR_IN_SECONDS, $recurrence, 'csdt_scheduled_scan' );
            $next_run = wp_next_scheduled( 'csdt_scheduled_scan' );
        }

        wp_send_json_success( [
            'saved'    => true,
            'next_run' => $next_run ? wp_date( 'D j M Y, g:ia', $next_run ) : null,
        ] );
    }

    public static function run_scheduled_scan(): void {
        $type = get_option( 'csdt_scan_schedule_type', 'deep' );
        if ( $type === 'deep' ) {
            self::cron_deep_scan();
        } else {
            self::cron_vuln_scan();
        }

        // Fetch the freshly stored result to notify
        $result = $type === 'deep'
            ? get_option( 'csdt_deep_scan_v1' )
            : get_option( 'csdt_security_scan_v2' );

        if ( $result && isset( $result['report'] ) ) {
            self::send_scan_notifications( $result['report'], $type );
        }
    }

    private static function send_scan_notifications( array $report, string $type ): void {
        $score       = $report['score']       ?? '?';
        $label       = $report['score_label'] ?? '';
        $summary     = $report['summary']     ?? '';
        $critical    = count( $report['critical'] ?? [] );
        $high        = count( $report['high']     ?? [] );
        $type_label  = $type === 'deep' ? 'AI Deep Dive Cyber Audit' : 'AI Cyber Audit';
        $site        = get_bloginfo( 'name' ) ?: home_url();
        $admin_url   = admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=security' );

        $subject = sprintf( '[%s] Security Scan Complete — Score: %s/100 (%s)', $site, $score, $label );
        $body    = sprintf(
            "%s completed for %s\n\nScore: %s/100 (%s)\nCritical: %d | High: %d\n\n%s\n\nView full report: %s",
            $type_label, $site, $score, $label, $critical, $high, $summary, $admin_url
        );

        // Email notification
        if ( get_option( 'csdt_scan_schedule_email', '1' ) === '1' ) {
            wp_mail( get_option( 'admin_email' ), $subject, $body );
        }

        // ntfy.sh push notification
        $ntfy_url = get_option( 'csdt_scan_schedule_ntfy_url', '' );
        if ( $ntfy_url ) {
            $priority = $critical > 0 ? 'urgent' : ( $high > 0 ? 'high' : 'default' );
            $headers  = [
                'Title'    => $subject,
                'Priority' => $priority,
                'Tags'     => $score >= 75 ? 'white_check_mark' : ( $score >= 55 ? 'warning' : 'rotating_light' ),
                'Click'    => $admin_url,
            ];
            $ntfy_tok = get_option( 'csdt_scan_schedule_ntfy_token', '' );
            if ( $ntfy_tok ) {
                $headers['Authorization'] = 'Bearer ' . $ntfy_tok;
            }
            wp_remote_post( $ntfy_url, [
                'timeout' => 10,
                'headers' => $headers,
                'body'    => $body,
            ] );
        }
    }

    public static function ajax_apply_quick_fix(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $action = isset( $_POST['fix_action'] ) ? sanitize_key( wp_unslash( $_POST['fix_action'] ) ) : '';
        $fix_id = isset( $_POST['fix_id'] )     ? sanitize_key( wp_unslash( $_POST['fix_id'] ) )     : '';

        if ( $action === 'list' ) {
            wp_send_json_success( [ 'fixes' => self::get_quick_fixes() ] );
            return;
        }

        if ( $action !== 'apply' ) {
            wp_send_json_error( 'Invalid action' );
            return;
        }

        switch ( $fix_id ) {
            case 'security_headers':
                update_option( 'csdt_devtools_safe_headers_enabled', '1' );
                delete_transient( 'csdt_sec_headers_check' );
                break;
            case 'security_headers_ack':
                update_option( 'csdt_devtools_sec_headers_ack', '1' );
                delete_transient( 'csdt_sec_headers_check' );
                break;
            case 'app_pw_2fa_ack':
                update_option( 'csdt_devtools_app_pw_2fa_ack', '1' );
                break;
            case 'disable_pingbacks':
                update_option( 'default_ping_status',   'closed' );
                update_option( 'default_pingback_flag', 0 );
                break;
            case 'close_registration':
                update_option( 'users_can_register', 0 );
                break;
            case 'disable_app_passwords':
                update_option( 'csdt_devtools_disable_app_passwords', '1' );
                break;
            case 'hide_wp_version':
                update_option( 'csdt_devtools_hide_wp_version', '1' );
                break;
            case 'close_comments':
                update_option( 'default_comment_status', 'closed' );
                break;
            case 'wpconfig_perms':
                $cfg_file = ABSPATH . 'wp-config.php';
                if ( ! file_exists( $cfg_file ) || ! is_writable( dirname( $cfg_file ) ) ) {
                    wp_send_json_error( 'wp-config.php not found or directory not writable.' );
                    return;
                }
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
                if ( ! chmod( $cfg_file, 0600 ) ) {
                    wp_send_json_error( 'chmod failed — server may restrict permission changes.' );
                    return;
                }
                break;
            case 'block_debug_log':
                $old_log = WP_CONTENT_DIR . '/debug.log';
                $new_log = rtrim( dirname( rtrim( ABSPATH, '/\\' ) ), '/\\' ) . '/wordpress-debug.log';

                // 1. Migrate existing content and delete from web root.
                if ( file_exists( $old_log ) ) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
                    $existing = file_get_contents( $old_log );
                    if ( $existing !== false && $existing !== '' ) {
                        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                        file_put_contents( $new_log, $existing, FILE_APPEND );
                    }
                    wp_delete_file( $old_log );
                }

                // 2. Rewrite WP_DEBUG_LOG in wp-config.php so WordPress writes to the safe
                //    path from the very first line of execution — before any mu-plugin runs.
                $cfg_file     = ABSPATH . 'wp-config.php';
                $cfg_updated  = false;
                if ( is_readable( $cfg_file ) && is_writable( $cfg_file ) ) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
                    $cfg = file_get_contents( $cfg_file );
                    $safe_path  = str_replace( "'", "\\'", $new_log );
                    $new_define = "define( 'WP_DEBUG_LOG', '" . $safe_path . "' );";
                    $pattern    = "/define\s*\(\s*['\"]WP_DEBUG_LOG['\"]\s*,\s*(?:true|false|'[^']*'|\"[^\"]*\")\s*\)\s*;/i";
                    if ( preg_match( $pattern, $cfg ) ) {
                        $cfg = preg_replace( $pattern, $new_define, $cfg );
                    } else {
                        // No existing define — insert before the "stop editing" marker.
                        $cfg = preg_replace(
                            '/\/\*\s*That\'s all[^*]*\*\//is',
                            $new_define . "\n\n/* That's all, stop editing! Happy publishing. */",
                            $cfg,
                            1
                        );
                    }
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                    if ( $cfg && file_put_contents( $cfg_file, $cfg ) !== false ) {
                        $cfg_updated = true;
                    }
                }

                // 3. Store new path and write mu-plugin as belt-and-suspenders fallback.
                update_option( 'csdt_debug_log_path', $new_log, false );
                $mu_dir = WP_CONTENT_DIR . '/mu-plugins';
                if ( ! is_dir( $mu_dir ) ) { wp_mkdir_p( $mu_dir ); }
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                file_put_contents(
                    $mu_dir . '/csdt-secure-logs.php',
                    '<?php' . "\n" .
                    '// Belt-and-suspenders: redirect error_log to safe path — by CloudScale DevTools.' . "\n" .
                    '@ini_set( \'error_log\', ' . var_export( $new_log, true ) . ' );' . "\n"
                );

                if ( ! $cfg_updated ) {
                    // wp-config.php not writable — mu-plugin is the only protection.
                    // Return success with a warning so the caller can surface it.
                    wp_send_json_success( [
                        'fixes'   => self::get_quick_fixes(),
                        'warning' => 'debug.log deleted and mu-plugin installed, but wp-config.php is not writable — WP_DEBUG_LOG still points to the old path. The file may reappear on the next PHP error. To make this permanent, set WP_DEBUG_LOG to \'' . $new_log . '\' in wp-config.php manually.',
                    ] );
                    return;
                }
                break;
            default:
                wp_send_json_error( 'Unknown fix ID' );
                return;
        }

        wp_send_json_success( [ 'fixes' => self::get_quick_fixes() ] );
    }

    public static function ajax_vuln_save_key(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $provider        = isset( $_POST['provider'] )    ? sanitize_key( wp_unslash( $_POST['provider'] ) )             : 'anthropic';
        $raw_key         = isset( $_POST['api_key'] )     ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) )       : '';
        $raw_gemini      = isset( $_POST['gemini_key'] )  ? sanitize_text_field( wp_unslash( $_POST['gemini_key'] ) )    : '';
        $clean_key       = trim( str_replace( '•', '', $raw_key ) );
        $clean_gemini    = trim( str_replace( '•', '', $raw_gemini ) );
        $model           = isset( $_POST['model'] )       ? sanitize_text_field( wp_unslash( $_POST['model'] ) )         : '_auto';
        $deep_model      = isset( $_POST['deep_model'] )  ? sanitize_text_field( wp_unslash( $_POST['deep_model'] ) )   : '_auto_deep';
        $prompt          = isset( $_POST['prompt'] )      ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) )   : '';

        update_option( 'csdt_devtools_ai_provider',    $provider,   false );
        if ( $clean_key     !== '' ) { update_option( 'csdt_devtools_anthropic_key', $clean_key,    false ); }
        if ( $clean_gemini  !== '' ) { update_option( 'csdt_devtools_gemini_key',    $clean_gemini, false ); }
        update_option( 'csdt_devtools_security_model',  $model,      false );
        update_option( 'csdt_devtools_deep_scan_model', $deep_model, false );
        update_option( 'csdt_devtools_security_prompt', $prompt,     false );
        delete_option( 'csdt_security_scan_v2' );
        delete_option( 'csdt_deep_scan_v1' );

        $saved_ant = get_option( 'csdt_devtools_anthropic_key', '' );
        $saved_gem = get_option( 'csdt_devtools_gemini_key', '' );
        $has_key   = $provider === 'gemini' ? ! empty( $saved_gem ) : ! empty( $saved_ant );
        wp_send_json_success( [
            'saved'         => true,
            'has_key'       => $has_key,
            'masked'        => $saved_ant ? '••••••••' . substr( $saved_ant, -4 ) : '',
            'maskedGemini'  => $saved_gem ? '••••••••' . substr( $saved_gem, -4 ) : '',
        ] );
    }

    public static function ajax_security_test_key(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : 'anthropic';
        $raw_key  = isset( $_POST['api_key'] )  ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
        $key      = trim( str_replace( '•', '', $raw_key ) );

        if ( $provider === 'gemini' ) {
            if ( ! $key ) { $key = get_option( 'csdt_devtools_gemini_key', '' ); }
            if ( ! $key ) { wp_send_json_error( [ 'message' => 'No Gemini API key provided.' ] ); return; }

            $url  = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . rawurlencode( $key );
            $resp = wp_remote_post( $url, [
                'timeout' => 15,
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( [ 'contents' => [ [ 'role' => 'user', 'parts' => [ [ 'text' => 'Hi' ] ] ] ] ] ),
            ] );
        } else {
            if ( ! $key ) { $key = get_option( 'csdt_devtools_anthropic_key', '' ); }
            if ( ! $key ) { wp_send_json_error( [ 'message' => 'No API key provided.' ] ); return; }

            $resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
                'timeout' => 15,
                'headers' => [
                    'x-api-key'         => $key,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'model'      => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 10,
                    'messages'   => [ [ 'role' => 'user', 'content' => 'Hi' ] ],
                ] ),
            ] );
        }

        if ( is_wp_error( $resp ) ) {
            wp_send_json_error( [ 'message' => 'Connection error: ' . $resp->get_error_message() ] );
            return;
        }

        $code = wp_remote_retrieve_response_code( $resp );
        if ( $code === 200 ) {
            wp_send_json_success( [ 'valid' => true, 'message' => '✓ API key is valid' ] );
        } else {
            $body = json_decode( wp_remote_retrieve_body( $resp ), true );
            $err  = $body['error']['message'] ?? $body['error']['status'] ?? "HTTP {$code}";
            wp_send_json_error( [ 'valid' => false, 'message' => $err ] );
        }
    }

    // ── Background execution helper ──────────────────────────────────

    private static function send_json_and_continue( array $data ): void {
        if ( function_exists( 'set_time_limit' ) ) {
            set_time_limit( 0 );
        }
        // Discard any output buffers so headers can be sent cleanly
        while ( ob_get_level() ) {
            ob_end_clean();
        }
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Connection: close' );
        $body = wp_json_encode( [ 'success' => true, 'data' => $data ] );
        header( 'Content-Length: ' . strlen( $body ) );
        echo $body;
        flush();
        // On PHP-FPM: close the HTTP connection but keep the process running
        if ( function_exists( 'fastcgi_finish_request' ) ) {
            fastcgi_finish_request();
        }
    }

    // ── Parallel AI calls via curl_multi ─────────────────────────────

    private static function build_ai_curl_handle( string $provider, string $system, string $user_message, string $model, int $max_tokens ): \CurlHandle {
        if ( $provider === 'gemini' ) {
            $key = get_option( 'csdt_devtools_gemini_key', '' );
            if ( ! $key ) { throw new \RuntimeException( 'No Gemini API key configured.' ); }
            if ( $model === '_auto' || $model === '_auto_deep' ) { $model = 'gemini-2.0-flash'; }
            $url     = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $key );
            $body    = wp_json_encode( [
                'systemInstruction' => [ 'parts' => [ [ 'text' => $system ] ] ],
                'contents'          => [ [ 'role' => 'user', 'parts' => [ [ 'text' => $user_message ] ] ] ],
                'generationConfig'  => [ 'maxOutputTokens' => $max_tokens ],
            ] );
            $headers = [ 'Content-Type: application/json' ];
        } else {
            $key = get_option( 'csdt_devtools_anthropic_key', '' );
            if ( ! $key ) { throw new \RuntimeException( 'No Anthropic API key configured.' ); }
            if ( $model === '_auto' )      { $model = 'claude-sonnet-4-6'; }
            if ( $model === '_auto_deep' ) { $model = 'claude-opus-4-7'; }
            $url     = 'https://api.anthropic.com/v1/messages';
            $body    = wp_json_encode( [
                'model'      => $model,
                'max_tokens' => $max_tokens,
                'system'     => $system,
                'messages'   => [ [ 'role' => 'user', 'content' => $user_message ] ],
            ] );
            $headers = [
                'x-api-key: ' . $key,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ];
        }
        $ch = curl_init();
        curl_setopt_array( $ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_SSL_VERIFYPEER => true,
        ] );
        return $ch;
    }

    private static function parse_ai_curl_text( string $provider, string $body ): string {
        $data = json_decode( $body, true );
        if ( ! $data ) { throw new \RuntimeException( 'Empty or invalid API response.' ); }
        if ( $provider === 'gemini' ) {
            if ( isset( $data['error'] ) ) { throw new \RuntimeException( $data['error']['message'] ?? 'Gemini API error.' ); }
            return trim( $data['candidates'][0]['content']['parts'][0]['text'] ?? '' );
        }
        if ( isset( $data['error'] ) ) { throw new \RuntimeException( $data['error']['message'] ?? 'Anthropic API error.' ); }
        return trim( $data['content'][0]['text'] ?? '' );
    }

    private static function dispatch_parallel_ai_calls( array $calls ): array {
        $provider = get_option( 'csdt_devtools_ai_provider', 'anthropic' );
        $mh       = curl_multi_init();
        $handles  = [];
        foreach ( $calls as $i => $call ) {
            $ch          = self::build_ai_curl_handle( $provider, $call['system'], $call['user'], $call['model'], $call['max_tokens'] );
            $handles[$i] = $ch;
            curl_multi_add_handle( $mh, $ch );
        }
        $running = null;
        do {
            curl_multi_exec( $mh, $running );
            if ( $running ) { curl_multi_select( $mh, 1.0 ); }
        } while ( $running > 0 );

        $texts = [];
        foreach ( $handles as $i => $ch ) {
            $texts[$i] = self::parse_ai_curl_text( $provider, (string) curl_multi_getcontent( $ch ) );
            curl_multi_remove_handle( $mh, $ch );
            curl_close( $ch );
        }
        curl_multi_close( $mh );
        return $texts;
    }

    private static function parse_ai_json( string $text ): array {
        $text   = preg_replace( '/^```(?:json)?\s*/i', '', trim( $text ) );
        $text   = preg_replace( '/\s*```$/', '', $text );
        $report = json_decode( $text, true );
        if ( ! $report || ! isset( $report['score'] ) ) {
            throw new \RuntimeException( 'AI returned unexpected format.' );
        }
        return $report;
    }

    private static function merge_reports( array $a, array $b ): array {
        $score = (int) round( $a['score'] * 0.45 + $b['score'] * 0.55 );
        $label = $score >= 90 ? 'Excellent' : ( $score >= 75 ? 'Good' : ( $score >= 55 ? 'Fair' : ( $score >= 35 ? 'Poor' : 'Critical' ) ) );
        $sum_a = rtrim( $a['summary'] ?? '', '. ' );
        $sum_b = ltrim( $b['summary'] ?? '' );
        return [
            'score'       => $score,
            'score_label' => $label,
            'summary'     => $sum_a . '. ' . $sum_b,
            'critical'    => array_merge( $a['critical'] ?? [], $b['critical'] ?? [] ),
            'high'        => array_merge( $a['high']     ?? [], $b['high']     ?? [] ),
            'medium'      => array_merge( $a['medium']   ?? [], $b['medium']   ?? [] ),
            'low'         => array_merge( $a['low']      ?? [], $b['low']      ?? [] ),
            'good'        => array_merge( $a['good']     ?? [], $b['good']     ?? [] ),
        ];
    }

    private static function default_internal_scan_prompt(): string {
        return <<<'PROMPT'
You are a WordPress security expert. Analyse the provided internal WordPress configuration data only.

Focus on: WordPress/PHP version currency, WP_DEBUG/WP_DEBUG_DISPLAY flags (exposed to public = critical), DISALLOW_FILE_EDIT/MODS, database prefix (wp_ default is a risk), user accounts (admin username exists, counts), active plugin list (outdated plugins), brute force protection, 2FA configuration (email/TOTP/passkey counts per admin), login URL obfuscation, wp-config.php file permissions, open user registration, pingbacks enabled (DDoS amplification), WordPress version in meta generator tag, default comment status.

Return ONLY a JSON object (no markdown, no code fences) with this exact schema:
{"score":0-100,"score_label":"Excellent|Good|Fair|Poor|Critical","summary":"1-2 sentences on internal config security posture","critical":[{"title":"...","detail":"...","fix":"..."}],"high":[...],"medium":[...],"low":[...],"good":[{"title":"...","detail":"..."}]}

Score the internal configuration on a 0-100 scale. Be strict. Include good practices for hardened settings.
PROMPT;
    }

    private static function default_external_scan_prompt(): string {
        return <<<'PROMPT'
You are a penetration tester. Analyse the provided external exposure checks and plugin code scan data only.

For external checks assess: HTTP security headers (CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy), exposed endpoints (wp-login.php, xmlrpc.php, wp-cron.php, REST API user enumeration, author enumeration /?author=1, directory listing), SSL certificate validity and days to expiry, HTTP→HTTPS redirect enforcement, exposed sensitive files (debug.log, .env, wp-config.php.bak, .git/config, readme.html, phpinfo.php, error_log, composer.json, backup archives), database admin tools accessible (adminer, phpMyAdmin), server-status/server-info pages, and email DNS security (SPF and DMARC records present).

For plugin code scan (plugin_code_scan): list detected patterns as context only — raw static analysis that may include false positives.

For code_triage: AI-verified verdicts on the static findings. Each entry has verdict (confirmed|false_positive|needs_context), severity, type, explanation, and fix. Only raise confirmed findings as real issues — do not report false_positive items as vulnerabilities. Use the severity from code_triage for confirmed items. For needs_context items, mention them at low severity. Name plugin, file, and line number in every code finding.

Return ONLY a JSON object (no markdown, no code fences) with this exact schema:
{"score":0-100,"score_label":"Excellent|Good|Fair|Poor|Critical","summary":"1-2 sentences on external exposure and code scan posture","critical":[{"title":"...","detail":"...","fix":"..."}],"high":[...],"medium":[...],"low":[...],"good":[{"title":"...","detail":"..."}]}

Score external exposure on a 0-100 scale. Prioritise externally reachable issues at critical/high. Include good practices for blocked endpoints and hardened headers.
PROMPT;
    }

    // ── Cancel scan ───────────────────────────────────────────────────

    public static function ajax_cancel_scan(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'deep';
        if ( $type === 'deep' ) {
            set_transient( 'csdt_deep_scan_cancelled', '1', 300 );
            delete_transient( 'csdt_deep_scan_status' );
        } else {
            set_transient( 'csdt_vuln_scan_cancelled', '1', 300 );
            delete_transient( 'csdt_vuln_scan_status' );
        }
        wp_send_json_success( [ 'cancelled' => true ] );
    }

    // ── AI dispatcher — Anthropic or Gemini ──────────────────────────

    private static function dispatch_ai_call( string $system, string $user_message, string $model, int $max_tokens ): string {
        $provider = get_option( 'csdt_devtools_ai_provider', 'anthropic' );

        if ( $provider === 'gemini' ) {
            $key = get_option( 'csdt_devtools_gemini_key', '' );
            if ( ! $key ) { throw new \RuntimeException( 'No Gemini API key configured.' ); }
            if ( $model === '_auto' || $model === '_auto_deep' ) { $model = 'gemini-2.0-flash'; }

            $url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $key );
            $resp = wp_remote_post( $url, [
                'timeout' => 180,
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( [
                    'systemInstruction' => [ 'parts' => [ [ 'text' => $system ] ] ],
                    'contents'          => [ [ 'role' => 'user', 'parts' => [ [ 'text' => $user_message ] ] ] ],
                    'generationConfig'  => [ 'maxOutputTokens' => $max_tokens ],
                ] ),
            ] );
            if ( is_wp_error( $resp ) ) { throw new \RuntimeException( $resp->get_error_message() ); }
            $code = wp_remote_retrieve_response_code( $resp );
            $body = json_decode( wp_remote_retrieve_body( $resp ), true );
            if ( $code !== 200 ) {
                $err = $body['error']['message'] ?? "HTTP {$code}";
                throw new \RuntimeException( $err );
            }
            return trim( $body['candidates'][0]['content']['parts'][0]['text'] ?? '' );

        } else {
            $key = get_option( 'csdt_devtools_anthropic_key', '' );
            if ( ! $key ) { throw new \RuntimeException( 'No Anthropic API key configured.' ); }
            if ( $model === '_auto' )      { $model = 'claude-sonnet-4-6'; }
            if ( $model === '_auto_deep' ) { $model = 'claude-opus-4-7'; }

            $resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
                'timeout' => 180,
                'headers' => [
                    'x-api-key'         => $key,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'model'      => $model,
                    'max_tokens' => $max_tokens,
                    'system'     => $system,
                    'messages'   => [ [ 'role' => 'user', 'content' => $user_message ] ],
                ] ),
            ] );
            if ( is_wp_error( $resp ) ) { throw new \RuntimeException( $resp->get_error_message() ); }
            $code = wp_remote_retrieve_response_code( $resp );
            $raw  = wp_remote_retrieve_body( $resp );
            $api  = json_decode( $raw, true );
            if ( $code !== 200 ) {
                $err = $api['error']['message'] ?? "HTTP {$code}";
                throw new \RuntimeException( $err );
            }
            return trim( $api['content'][0]['text'] ?? '' );
        }
    }

    public static function ajax_vuln_scan(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $cache_only = ! empty( $_POST['cache_only'] );

        // Page-load pre-fill: return cache silently or signal nothing cached
        if ( $cache_only ) {
            $cached = get_option( 'csdt_security_scan_v2' );
            if ( $cached !== false ) {
                wp_send_json_success( array_merge( $cached, [ 'from_cache' => true ] ) );
            } else {
                wp_send_json_success( [ 'no_cache' => true ] );
            }
            return;
        }

        $provider = get_option( 'csdt_devtools_ai_provider', 'anthropic' );
        $has_key  = $provider === 'gemini'
            ? ! empty( get_option( 'csdt_devtools_gemini_key', '' ) )
            : ! empty( get_option( 'csdt_devtools_anthropic_key', '' ) );
        if ( ! $has_key ) {
            wp_send_json_error( [ 'message' => 'No API key configured.', 'need_key' => true ] );
            return;
        }

        // Clear previous result and mark as running
        delete_option( 'csdt_security_scan_v2' );
        set_transient( 'csdt_vuln_scan_status', [ 'status' => 'running', 'started_at' => time() ], 600 );

        // Send response immediately, then run scan after connection closes
        self::send_json_and_continue( [ 'queued' => true ] );
        self::cron_vuln_scan();
        exit;
    }

    public static function cron_vuln_scan(): void {
        if ( function_exists( 'set_time_limit' ) ) { set_time_limit( 0 ); }

        if ( get_transient( 'csdt_vuln_scan_cancelled' ) ) {
            delete_transient( 'csdt_vuln_scan_cancelled' );
            return;
        }

        try {
            $model         = get_option( 'csdt_devtools_security_model', '_auto' );
            $system_prompt = get_option( 'csdt_devtools_security_prompt', '' ) ?: self::default_security_prompt();
            $user_message  = 'WordPress site security data (JSON):' . "\n\n" . wp_json_encode( self::gather_security_data(), JSON_PRETTY_PRINT );

            error_log( '[CSDT-SCAN] cron running, model=' . $model );
            $text = self::dispatch_ai_call( $system_prompt, $user_message, $model, 4096 );
        } catch ( \Throwable $e ) {
            set_transient( 'csdt_vuln_scan_status', [ 'status' => 'error', 'message' => $e->getMessage() ], 300 );
            return;
        }

        $text   = preg_replace( '/^```(?:json)?\s*/i', '', trim( $text ) );
        $text   = preg_replace( '/\s*```$/', '', $text );
        $report = json_decode( $text, true );

        if ( ! $report || ! isset( $report['score'] ) ) {
            set_transient( 'csdt_vuln_scan_status', [ 'status' => 'error', 'message' => 'AI returned unexpected format.' ], 300 );
            return;
        }

        $output = [
            'report'     => $report,
            'model_used' => get_option( 'csdt_devtools_ai_provider', 'anthropic' ) . '/' . $model,
            'scanned_at' => time(),
            'from_cache' => false,
        ];

        update_option( 'csdt_security_scan_v2', $output, false );
        set_transient( 'csdt_vuln_scan_status', [ 'status' => 'complete', 'completed_at' => time() ], 600 );
        self::append_scan_history( 'standard', $report, $output['model_used'], $output['scanned_at'] );
        error_log( '[CSDT-SCAN] cron complete, score=' . $report['score'] );
    }

    /* ==================================================================
       Deep Scan — internal config + external exposure checks
    ================================================================== */

    private static function check_ssl_certificate( string $host ): array {
        if ( empty( $host ) ) {
            return [ 'available' => false, 'error' => 'No host' ];
        }
        $ctx = stream_context_create( [
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer'       => false,
                'verify_peer_name'  => false,
            ],
        ] );
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        $stream = @stream_socket_client( 'ssl://' . $host . ':443', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx );
        if ( ! $stream ) {
            return [ 'available' => false, 'error' => $errstr ?: "errno $errno" ];
        }
        $params = stream_context_get_params( $stream );
        fclose( $stream );
        $cert_res = $params['options']['ssl']['peer_certificate'] ?? null;
        if ( ! $cert_res ) {
            return [ 'available' => false, 'error' => 'No peer cert captured' ];
        }
        $cert = openssl_x509_parse( $cert_res );
        if ( ! $cert ) {
            return [ 'available' => false, 'error' => 'openssl_x509_parse failed' ];
        }
        $valid_to  = $cert['validTo_time_t']   ?? 0;
        $valid_from= $cert['validFrom_time_t'] ?? 0;
        $now       = time();
        $days_left = $valid_to ? (int) floor( ( $valid_to - $now ) / DAY_IN_SECONDS ) : null;
        return [
            'available'     => true,
            'subject_cn'    => $cert['subject']['CN']  ?? '',
            'issuer'        => $cert['issuer']['CN']   ?? ( $cert['issuer']['O'] ?? '' ),
            'valid_from'    => $valid_from ? gmdate( 'Y-m-d', $valid_from ) : null,
            'valid_to'      => $valid_to   ? gmdate( 'Y-m-d', $valid_to )   : null,
            'days_left'     => $days_left,
            'expired'       => $days_left !== null && $days_left < 0,
            'expiring_soon' => $days_left !== null && $days_left >= 0 && $days_left < 30,
            'san'           => $cert['extensions']['subjectAltName'] ?? null,
        ];
    }

    private static function check_email_dns( string $host ): array {
        // Check MX records first — if none exist, email is not configured for this domain
        // and missing SPF/DMARC/DKIM is not a finding (there's nothing to protect).
        $mx_records = @dns_get_record( $host, DNS_MX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        $has_mx     = is_array( $mx_records ) && ! empty( $mx_records );

        if ( ! $has_mx ) {
            return [
                'mx_present'     => false,
                'spf_present'    => false,
                'spf_record'     => null,
                'spf_strictness' => 'not_applicable',
                'dmarc_present'  => false,
                'dmarc_record'   => null,
                'dmarc_policy'   => 'not_applicable',
                'dmarc_pct'      => null,
                'dkim_present'   => false,
                'dkim_selector'  => null,
            ];
        }

        $spf_found   = false;
        $dmarc_found = false;
        $spf_record  = null;
        $dmarc_record= null;

        // SPF — TXT record on the apex domain
        $txt = @dns_get_record( $host, DNS_TXT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if ( is_array( $txt ) ) {
            foreach ( $txt as $r ) {
                if ( isset( $r['txt'] ) && stripos( $r['txt'], 'v=spf1' ) === 0 ) {
                    $spf_found  = true;
                    $spf_record = $r['txt'];
                    break;
                }
            }
        }

        // DMARC — TXT record on _dmarc.domain
        $dmarc_txt = @dns_get_record( '_dmarc.' . $host, DNS_TXT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if ( is_array( $dmarc_txt ) ) {
            foreach ( $dmarc_txt as $r ) {
                if ( isset( $r['txt'] ) && stripos( $r['txt'], 'v=DMARC1' ) === 0 ) {
                    $dmarc_found  = true;
                    $dmarc_record = $r['txt'];
                    break;
                }
            }
        }

        // DKIM — probe common selectors used by major ESPs
        $dkim_found    = false;
        $dkim_selector = null;
        foreach ( [ 'google', 'default', 'mail', 'dkim', 'k1', 'selector1', 'selector2', 'mandrill', 'mailjet', 'sendgrid', 'amazonses', 'smtp' ] as $sel ) {
            $dkim_txt = @dns_get_record( $sel . '._domainkey.' . $host, DNS_TXT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            if ( is_array( $dkim_txt ) ) {
                foreach ( $dkim_txt as $r ) {
                    if ( isset( $r['txt'] ) && stripos( $r['txt'], 'v=DKIM1' ) !== false ) {
                        $dkim_found    = true;
                        $dkim_selector = $sel;
                        break 2;
                    }
                }
            }
        }

        // SPF strictness — ~all (soft fail) still lets spoofed mail through
        $spf_strictness = 'missing';
        if ( $spf_found && $spf_record ) {
            if ( strpos( $spf_record, '+all' ) !== false )     { $spf_strictness = 'pass_all'; }
            elseif ( strpos( $spf_record, '-all' ) !== false ) { $spf_strictness = 'hard_fail'; }
            elseif ( strpos( $spf_record, '~all' ) !== false ) { $spf_strictness = 'soft_fail'; }
            elseif ( strpos( $spf_record, '?all' ) !== false ) { $spf_strictness = 'neutral'; }
            else                                               { $spf_strictness = 'unknown'; }
        }

        // DMARC policy — p=none does nothing (monitoring only)
        $dmarc_policy = 'missing';
        $dmarc_pct    = 100;
        if ( $dmarc_found && $dmarc_record ) {
            if ( preg_match( '/\bp=([^;\s]+)/i', $dmarc_record, $pm ) ) {
                $dmarc_policy = strtolower( trim( $pm[1] ) );
            }
            if ( preg_match( '/\bpct=(\d+)/i', $dmarc_record, $pm ) ) {
                $dmarc_pct = (int) $pm[1];
            }
        }

        return [
            'mx_present'     => true,
            'spf_present'    => $spf_found,
            'spf_record'     => $spf_record,
            'spf_strictness' => $spf_strictness,
            'dmarc_present'  => $dmarc_found,
            'dmarc_record'   => $dmarc_record,
            'dmarc_policy'   => $dmarc_policy,
            'dmarc_pct'      => $dmarc_pct,
            'dkim_present'   => $dkim_found,
            'dkim_selector'  => $dkim_selector,
        ];
    }

    private static function gather_external_checks(): array {
        $base = home_url( '/' );
        $host = (string) wp_parse_url( $base, PHP_URL_HOST );

        $ext = [];

        // SSL certificate
        $ext['ssl'] = self::check_ssl_certificate( $host );

        // Helper: head request, returns [code, error]
        $head = function ( string $url ): array {
            $r = wp_remote_head( $url, [ 'timeout' => 4, 'sslverify' => false, 'redirection' => 0 ] );
            return is_wp_error( $r )
                ? [ 'code' => 'error', 'error' => $r->get_error_message() ]
                : [ 'code' => wp_remote_retrieve_response_code( $r ), 'location' => wp_remote_retrieve_header( $r, 'location' ) ];
        };

        // wp-login.php exposure
        $login_r = $head( $base . 'wp-login.php' );
        $ext['wp_login'] = [
            'code'       => $login_r['code'],
            'accessible' => isset( $login_r['code'] ) && is_int( $login_r['code'] ) && $login_r['code'] < 400,
        ];

        // xmlrpc.php
        $xmlrpc_r = $head( $base . 'xmlrpc.php' );
        $ext['xmlrpc'] = [
            'code'       => $xmlrpc_r['code'],
            'accessible' => isset( $xmlrpc_r['code'] ) && is_int( $xmlrpc_r['code'] ) && $xmlrpc_r['code'] < 400,
        ];

        // REST API user enumeration
        $rest_r = wp_remote_get( $base . 'wp-json/wp/v2/users', [ 'timeout' => 5, 'sslverify' => false ] );
        $ext['rest_users'] = [ 'exposed' => false, 'count' => 0, 'slugs' => [] ];
        if ( ! is_wp_error( $rest_r ) && wp_remote_retrieve_response_code( $rest_r ) === 200 ) {
            $users = json_decode( wp_remote_retrieve_body( $rest_r ), true );
            if ( is_array( $users ) && ! empty( $users ) ) {
                $ext['rest_users']['exposed'] = true;
                $ext['rest_users']['count']   = count( $users );
                $ext['rest_users']['slugs']   = array_values( array_slice( array_column( $users, 'slug' ), 0, 5 ) );
            }
        }

        // Author enumeration /?author=1
        $author_r = wp_remote_head( $base . '?author=1', [ 'timeout' => 4, 'sslverify' => false, 'redirection' => 0 ] );
        $ext['author_enum'] = [ 'exposed' => false ];
        if ( ! is_wp_error( $author_r ) ) {
            $code = wp_remote_retrieve_response_code( $author_r );
            $loc  = wp_remote_retrieve_header( $author_r, 'location' );
            if ( $code >= 300 && $code < 400 && $loc && strpos( $loc, '/author/' ) !== false ) {
                $ext['author_enum'] = [ 'exposed' => true, 'redirects_to' => $loc ];
            }
        }

        // Uploads directory listing
        $uploads_r = wp_remote_get( $base . 'wp-content/uploads/', [ 'timeout' => 4, 'sslverify' => false ] );
        $uploads_body = is_wp_error( $uploads_r ) ? '' : wp_remote_retrieve_body( $uploads_r );
        $ext['uploads_listing'] = (
            ! is_wp_error( $uploads_r ) &&
            wp_remote_retrieve_response_code( $uploads_r ) === 200 &&
            ( stripos( $uploads_body, 'Index of' ) !== false || stripos( $uploads_body, 'Parent Directory' ) !== false )
        );

        // Plugins and themes directory listing (reveals installed software to targeted attackers)
        $plugins_r    = wp_remote_get( $base . 'wp-content/plugins/', [ 'timeout' => 4, 'sslverify' => false ] );
        $plugins_body = is_wp_error( $plugins_r ) ? '' : wp_remote_retrieve_body( $plugins_r );
        $ext['plugins_listing'] = (
            ! is_wp_error( $plugins_r ) &&
            wp_remote_retrieve_response_code( $plugins_r ) === 200 &&
            ( stripos( $plugins_body, 'Index of' ) !== false || stripos( $plugins_body, 'Parent Directory' ) !== false )
        );

        $themes_r    = wp_remote_get( $base . 'wp-content/themes/', [ 'timeout' => 4, 'sslverify' => false ] );
        $themes_body = is_wp_error( $themes_r ) ? '' : wp_remote_retrieve_body( $themes_r );
        $ext['themes_listing'] = (
            ! is_wp_error( $themes_r ) &&
            wp_remote_retrieve_response_code( $themes_r ) === 200 &&
            ( stripos( $themes_body, 'Index of' ) !== false || stripos( $themes_body, 'Parent Directory' ) !== false )
        );

        // Exposed sensitive files
        $ext['exposed_files'] = [];
        foreach ( [ 'readme.html', 'license.txt', 'phpinfo.php', 'wp-config.php.bak', '.env', '.htaccess', '.git/config', 'error_log', 'composer.json', 'package.json' ] as $f ) {
            $r = $head( $base . $f );
            if ( isset( $r['code'] ) && is_int( $r['code'] ) && $r['code'] === 200 ) {
                $ext['exposed_files'][] = $f;
            }
        }

        // wp-cron.php publicly accessible (DDoS / resource-abuse vector)
        $cron_r = $head( $base . 'wp-cron.php' );
        $ext['wp_cron_public'] = isset( $cron_r['code'] ) && is_int( $cron_r['code'] ) && $cron_r['code'] < 400;

        // debug.log exposed (leaks credentials, stack traces, internal paths)
        $debug_r = $head( $base . 'wp-content/debug.log' );
        $ext['debug_log_exposed'] = isset( $debug_r['code'] ) && $debug_r['code'] === 200;

        // Adminer / phpMyAdmin reachable (full DB access)
        $db_tools_exposed = [];
        foreach ( [ 'adminer.php', 'adminer/', 'phpmyadmin/', 'pma/', 'phpMyAdmin/', 'db/' ] as $path ) {
            $r = $head( $base . $path );
            if ( isset( $r['code'] ) && is_int( $r['code'] ) && $r['code'] < 400 ) {
                $db_tools_exposed[] = $path;
            }
        }
        $ext['db_tools_exposed'] = $db_tools_exposed;

        // Apache server-status / server-info (leaks live requests and internal IPs)
        $server_status_r = $head( $base . 'server-status' );
        $server_info_r   = $head( $base . 'server-info' );
        $ext['server_status_exposed'] = isset( $server_status_r['code'] ) && $server_status_r['code'] === 200;
        $ext['server_info_exposed']   = isset( $server_info_r['code'] )   && $server_info_r['code'] === 200;

        // Backup archives exposed in webroot (full site or DB dump)
        $backup_files_exposed = [];
        $domain_slug          = str_replace( '.', '', (string) wp_parse_url( $base, PHP_URL_HOST ) );
        $backup_candidates    = [
            'backup.zip', 'backup.tar.gz', 'backup.sql',
            'site.zip', 'site.tar.gz',
            'wordpress.zip', 'wordpress.tar.gz',
            'db.sql', 'database.sql', 'dump.sql',
            $domain_slug . '.zip', $domain_slug . '.sql',
            'wp-backup.zip', 'backup.bak',
        ];
        foreach ( $backup_candidates as $f ) {
            $r = $head( $base . $f );
            if ( isset( $r['code'] ) && $r['code'] === 200 ) {
                $backup_files_exposed[] = $f;
            }
        }
        $ext['backup_files_exposed'] = $backup_files_exposed;

        // HTTP → HTTPS redirect enforcement
        $http_base    = preg_replace( '/^https:/i', 'http:', $base );
        $http_r       = wp_remote_head( $http_base, [ 'timeout' => 5, 'sslverify' => false, 'redirection' => 0 ] );
        $http_code    = is_wp_error( $http_r ) ? null : wp_remote_retrieve_response_code( $http_r );
        $http_loc     = is_wp_error( $http_r ) ? null : wp_remote_retrieve_header( $http_r, 'location' );
        $ext['http_to_https'] = [
            'redirects'   => $http_code !== null && $http_code >= 300 && $http_code < 400 && $http_loc && stripos( $http_loc, 'https://' ) === 0,
            'http_code'   => $http_code,
        ];

        // TLS weak protocol check — test whether TLS 1.0 / 1.1 are still accepted
        $ext['tls_weak_protocols'] = [ 'checked' => false, 'tls10_accepted' => false, 'tls11_accepted' => false ];
        if ( function_exists( 'stream_socket_client' ) ) {
            $tls_tests = [];
            if ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT' ) ) {
                $tls_tests['tls10_accepted'] = STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT;
            }
            if ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT' ) ) {
                $tls_tests['tls11_accepted'] = STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
            }
            foreach ( $tls_tests as $field => $crypto_method ) {
                $ext['tls_weak_protocols']['checked'] = true;
                $ctx  = stream_context_create( [
                    'ssl' => [
                        'crypto_method'    => $crypto_method,
                        'verify_peer'      => false,
                        'verify_peer_name' => false,
                    ],
                ] );
                // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                $sock = @stream_socket_client( 'ssl://' . $host . ':443', $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $ctx );
                if ( $sock ) {
                    $ext['tls_weak_protocols'][ $field ] = true;
                    fclose( $sock );
                }
            }
        }

        // Cookie security flags — inspect Set-Cookie headers from wp-login.php
        $cookie_r = wp_remote_get( $base . 'wp-login.php', [ 'timeout' => 5, 'sslverify' => false ] );
        $ext['cookie_security'] = [ 'checked' => false ];
        if ( ! is_wp_error( $cookie_r ) ) {
            $raw_headers = wp_remote_retrieve_headers( $cookie_r );
            $set_cookies = [];
            // WP_HTTP_Requests_Response may return Set-Cookie as array or string
            $sc = $raw_headers['set-cookie'] ?? [];
            if ( is_string( $sc ) ) { $sc = [ $sc ]; }
            foreach ( (array) $sc as $cookie_str ) {
                if ( stripos( $cookie_str, 'wordpress' ) !== false ) {
                    $set_cookies[] = $cookie_str;
                }
            }
            if ( ! empty( $set_cookies ) ) {
                $all_secure   = true;
                $all_httponly = true;
                $all_samesite = true;
                foreach ( $set_cookies as $cs ) {
                    if ( stripos( $cs, '; Secure' ) === false )   { $all_secure   = false; }
                    if ( stripos( $cs, '; HttpOnly' ) === false )  { $all_httponly = false; }
                    if ( stripos( $cs, 'SameSite' ) === false )    { $all_samesite = false; }
                }
                $ext['cookie_security'] = [
                    'checked'    => true,
                    'secure'     => $all_secure,
                    'httponly'   => $all_httponly,
                    'samesite'   => $all_samesite,
                    'cookie_secure_constant' => defined( 'COOKIE_SECURE' ) && COOKIE_SECURE,
                ];
            }
        }

        // WAF / CDN detection
        $waf_detected  = [];
        $waf_headers_r = wp_remote_get( $base, [ 'timeout' => 5, 'sslverify' => false ] );
        if ( ! is_wp_error( $waf_headers_r ) ) {
            $wh = wp_remote_retrieve_headers( $waf_headers_r );
            if ( $wh['cf-ray'] || $wh['cf-cache-status'] || $wh['cf-request-id'] ) {
                $waf_detected[] = 'Cloudflare';
            }
            if ( $wh['x-sucuri-id'] || $wh['x-sucuri-cache'] ) {
                $waf_detected[] = 'Sucuri';
            }
            if ( $wh['x-fw-hash'] || $wh['x-fw-static'] ) {
                $waf_detected[] = 'Wordfence';
            }
            if ( $wh['x-cache'] && stripos( (string) $wh['x-cache'], 'cloudfront' ) !== false ) {
                $waf_detected[] = 'CloudFront';
            }
        }
        // Also check if Wordfence plugin is active (server-side indicator)
        $active_plugins = (array) get_option( 'active_plugins', [] );
        foreach ( $active_plugins as $pf ) {
            if ( stripos( $pf, 'wordfence' ) !== false && ! in_array( 'Wordfence', $waf_detected, true ) ) {
                $waf_detected[] = 'Wordfence (plugin active)';
            }
        }
        $ext['waf_cdn'] = [
            'detected' => ! empty( $waf_detected ),
            'providers'=> $waf_detected,
        ];

        // Email security — SPF and DMARC DNS records
        $ext['email_dns'] = self::check_email_dns( $host );

        // Security headers (from external perspective via public URL)
        $headers_r = wp_remote_get( $base, [ 'timeout' => 5, 'sslverify' => false ] );
        $ext['security_headers_external'] = [];
        if ( ! is_wp_error( $headers_r ) ) {
            $h = wp_remote_retrieve_headers( $headers_r );
            foreach ( [ 'x-frame-options', 'x-content-type-options', 'strict-transport-security',
                        'content-security-policy', 'referrer-policy', 'permissions-policy',
                        'access-control-allow-origin', 'x-powered-by', 'server' ] as $hname ) {
                $ext['security_headers_external'][ $hname ] = $h[ $hname ] ?? null;
            }
        }

        // CSP quality — presence alone is not enough; weak directives leave XSS open
        $csp_val     = $ext['security_headers_external']['content-security-policy'] ?? null;
        $csp_quality = [ 'present' => (bool) $csp_val, 'issues' => [] ];
        if ( $csp_val ) {
            if ( stripos( $csp_val, "'unsafe-inline'" ) !== false ) { $csp_quality['issues'][] = 'unsafe-inline'; }
            if ( stripos( $csp_val, "'unsafe-eval'" ) !== false )   { $csp_quality['issues'][] = 'unsafe-eval'; }
            if ( preg_match( '/(?:^|[\s;])(\*)[\s;]/', $csp_val ) ) { $csp_quality['issues'][] = 'wildcard-source'; }
            if ( stripos( $csp_val, 'default-src' ) === false )     { $csp_quality['issues'][] = 'no-default-src'; }
            $csp_quality['grade'] = empty( $csp_quality['issues'] ) ? 'good' : 'weak';
        } else {
            $csp_quality['grade'] = 'missing';
        }
        $ext['csp_quality'] = $csp_quality;

        // HSTS quality — max-age must be ≥1 year to be effective
        $hsts_val    = $ext['security_headers_external']['strict-transport-security'] ?? null;
        $hsts_quality = [ 'present' => (bool) $hsts_val, 'issues' => [] ];
        if ( $hsts_val ) {
            $max_age = 0;
            if ( preg_match( '/max-age=(\d+)/i', $hsts_val, $m ) ) { $max_age = (int) $m[1]; }
            $hsts_quality['max_age']             = $max_age;
            $hsts_quality['includes_subdomains'] = stripos( $hsts_val, 'includeSubDomains' ) !== false;
            $hsts_quality['preload']             = stripos( $hsts_val, 'preload' ) !== false;
            if ( $max_age < 31536000 )              { $hsts_quality['issues'][] = 'max-age-too-short'; }
            if ( ! $hsts_quality['includes_subdomains'] ) { $hsts_quality['issues'][] = 'no-includeSubDomains'; }
            $hsts_quality['grade'] = empty( $hsts_quality['issues'] ) ? 'good' : 'weak';
        } else {
            $hsts_quality['grade'] = 'missing';
        }
        $ext['hsts_quality'] = $hsts_quality;

        // Server header version leak — e.g. "nginx/1.18.0" reveals exact version for CVE targeting
        $server_hdr = $ext['security_headers_external']['server'] ?? null;
        $ext['server_version_leak'] = [
            'header'        => $server_hdr,
            'leaks_version' => $server_hdr !== null && (bool) preg_match( '/\/[\d.]+/', $server_hdr ),
        ];

        return $ext;
    }

    private static function scan_plugin_code(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $active_plugins = (array) get_option( 'active_plugins', [] );
        $plugins_dir    = WP_PLUGIN_DIR;

        // Patterns that warrant attention in plugin code
        $patterns = [
            // Remote code execution
            'eval('                          => 'eval()',
            'base64_decode('                 => 'base64_decode()',
            'exec('                          => 'exec()',
            'shell_exec('                    => 'shell_exec()',
            'system('                        => 'system()',
            'passthru('                      => 'passthru()',
            'popen('                         => 'popen()',
            'proc_open('                     => 'proc_open()',
            'assert('                        => 'assert()',
            'preg_replace.*\/e'              => 'preg_replace /e modifier',
            'create_function('               => 'create_function()',
            // File operations with user input
            'file_put_contents.*\$_'         => 'file_put_contents with user input',
            'move_uploaded_file'             => 'move_uploaded_file()',
            // Outbound requests with user input
            'wp_remote_get.*\$_'             => 'outbound request with user input',
            // SQL injection — direct use of user input in DB queries
            '\$wpdb->(query|get_results|get_row|get_var|prepare).*\$_(GET|POST|REQUEST|COOKIE)' => 'SQL query with raw user input (SQLi risk)',
            // XSS — echoing user input without escaping
            'echo\s+\$_(GET|POST|REQUEST|COOKIE|SERVER)\[' => 'echo user input without escaping (XSS risk)',
            'print\s+\$_(GET|POST|REQUEST|COOKIE)\['       => 'print user input without escaping (XSS risk)',
            // Unsafe deserialization
            'unserialize\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)' => 'unserialize() with user input (RCE/object injection)',
            // Remote file inclusion
            'include\s*\(\s*\$_(GET|POST|REQUEST)'         => 'include() with user input (RFI risk)',
            'require\s*\(\s*\$_(GET|POST|REQUEST)'         => 'require() with user input (RFI risk)',
        ];

        $results = [];

        foreach ( $active_plugins as $plugin_file ) {
            $plugin_slug = dirname( $plugin_file );
            if ( $plugin_slug === '.' ) {
                continue; // single-file plugin, skip
            }
            $plugin_path = $plugins_dir . '/' . $plugin_slug;
            if ( ! is_dir( $plugin_path ) ) {
                continue;
            }

            // Skip known safe large libraries
            $skip_dirs = [ 'vendor', 'node_modules', 'assets', 'dist', 'build' ];

            $findings      = [];
            $files_scanned = 0;

            $iter = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator( $plugin_path, FilesystemIterator::SKIP_DOTS ),
                    function ( $file, $key, $iter ) use ( $skip_dirs ) {
                        if ( $iter->hasChildren() ) {
                            return ! in_array( $file->getFilename(), $skip_dirs, true );
                        }
                        return $file->getExtension() === 'php';
                    }
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ( $iter as $file ) {
                if ( $files_scanned >= 200 ) {
                    break; // cap per plugin
                }
                $files_scanned++;
                $content = @file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                if ( $content === false ) {
                    continue;
                }
                $rel = str_replace( $plugin_path . '/', '', $file->getPathname() );
                foreach ( $patterns as $needle => $label ) {
                    if ( preg_match( '/' . $needle . '/i', $content ) ) {
                        // Get the first matching line for context
                        $lines = explode( "\n", $content );
                        foreach ( $lines as $ln => $line ) {
                            if ( preg_match( '/' . $needle . '/i', $line ) ) {
                                $findings[] = [
                                    'pattern' => $label,
                                    'file'    => $rel,
                                    'line'    => $ln + 1,
                                    'snippet' => trim( substr( $line, 0, 120 ) ),
                                ];
                                break; // one example per pattern per file
                            }
                        }
                        if ( count( $findings ) >= 15 ) {
                            break 2; // cap total findings per plugin
                        }
                    }
                }
            }

            if ( ! empty( $findings ) ) {
                $results[] = [
                    'plugin'        => $plugin_slug,
                    'files_scanned' => $files_scanned,
                    'findings'      => $findings,
                ];
            }
        }

        return $results;
    }

    /**
     * AI-powered triage of static code scan findings.
     * Reads ±10 lines of context around each flagged line, sends up to 10 snippets
     * to the cheapest available model, and returns per-snippet verdicts.
     */
    private static function triage_code_snippets_with_ai( array $scan_results ): array {
        if ( empty( $scan_results ) ) {
            return [ 'skipped' => true, 'reason' => 'no_findings', 'results' => [] ];
        }

        // Flatten findings and sort by risk priority
        $priority_order = [
            'eval()', 'unserialize() with user input (RCE/object injection)',
            'preg_replace /e modifier', 'create_function()',
            'SQL query with raw user input (SQLi risk)',
            'include() with user input (RFI risk)', 'require() with user input (RFI risk)',
            'exec()', 'shell_exec()', 'system()', 'passthru()', 'popen()', 'proc_open()',
            'base64_decode()', 'assert()', 'echo user input without escaping (XSS risk)',
            'print user input without escaping (XSS risk)',
            'file_put_contents with user input', 'move_uploaded_file()',
            'outbound request with user input',
        ];

        $flat = [];
        foreach ( $scan_results as $plugin_result ) {
            foreach ( $plugin_result['findings'] as $finding ) {
                $flat[] = array_merge( $finding, [ 'plugin' => $plugin_result['plugin'] ] );
            }
        }

        usort( $flat, function ( $a, $b ) use ( $priority_order ) {
            $ai = array_search( $a['pattern'], $priority_order, true );
            $bi = array_search( $b['pattern'], $priority_order, true );
            $ai = $ai === false ? 999 : $ai;
            $bi = $bi === false ? 999 : $bi;
            return $ai - $bi;
        } );

        $top = array_slice( $flat, 0, 10 );

        // Build snippet blocks with ±10 lines of context
        $blocks = [];
        foreach ( $top as $idx => $s ) {
            $abs = WP_PLUGIN_DIR . '/' . $s['plugin'] . '/' . $s['file'];
            $ctx = '';
            if ( is_readable( $abs ) ) {
                $lines = @file( $abs, FILE_IGNORE_NEW_LINES ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                if ( is_array( $lines ) ) {
                    $start = max( 0, $s['line'] - 11 );
                    $end   = min( count( $lines ) - 1, $s['line'] + 9 );
                    for ( $i = $start; $i <= $end; $i++ ) {
                        $marker = ( $i + 1 === $s['line'] ) ? '  // <<< FLAGGED' : '';
                        $ctx   .= ( $i + 1 ) . ': ' . $lines[ $i ] . $marker . "\n";
                    }
                }
            }
            if ( ! $ctx ) {
                $ctx = $s['line'] . ': ' . $s['snippet'] . "  // <<< FLAGGED\n";
            }
            $blocks[] = '[' . ( $idx + 1 ) . '] Plugin: ' . $s['plugin']
                . ' | File: ' . $s['file']
                . ' | Line: ' . $s['line']
                . ' | Flagged as: ' . $s['pattern'] . "\n"
                . "```php\n" . $ctx . '```';
        }

        $system = 'You are a WordPress PHP security expert. Analyse code snippets flagged by automated static analysis. Determine whether each is a genuine exploitable vulnerability or a false positive. Be precise — many static flags are false positives (e.g. eval() inside a template engine, base64_decode() for legitimate asset loading, shell_exec() behind a capability check). Return ONLY a valid JSON array with no markdown wrapping.';

        $user = 'Analyse these ' . count( $blocks ) . " flagged PHP snippets from active WordPress plugins. The flagged line is marked // <<< FLAGGED.\n\n"
              . implode( "\n\n", $blocks ) . "\n\n"
              . "Return a JSON array — one object per snippet:\n"
              . '{"id":<n>,"verdict":"confirmed|false_positive|needs_context","severity":"critical|high|medium|low|none","type":"<vulnerability type or null>","explanation":"<1-2 concise sentences>","fix":"<specific code-level fix or null if false positive>"}';

        // Use cheapest/fastest model for triage — cost ~$0.01-0.03 per scan
        $provider     = get_option( 'csdt_devtools_ai_provider', 'anthropic' );
        $triage_model = $provider === 'gemini' ? 'gemini-2.0-flash' : 'claude-haiku-4-5-20251001';

        try {
            $raw = self::dispatch_ai_call( $system, $user, $triage_model, 2048 );
        } catch ( \Throwable $e ) {
            return [ 'skipped' => true, 'reason' => 'api_error', 'error' => $e->getMessage(), 'results' => [] ];
        }

        // Strip markdown fences if present
        $raw = preg_replace( '/^```(?:json)?\s*/i', '', trim( $raw ) );
        $raw = preg_replace( '/\s*```$/i', '', trim( $raw ) );

        $verdicts = json_decode( $raw, true );
        if ( ! is_array( $verdicts ) ) {
            return [ 'skipped' => true, 'reason' => 'parse_error', 'raw_preview' => substr( $raw, 0, 300 ), 'results' => [] ];
        }

        // Index verdicts by id for merge
        $by_id = [];
        foreach ( $verdicts as $v ) {
            if ( isset( $v['id'] ) ) { $by_id[ (int) $v['id'] ] = $v; }
        }

        $output = [];
        foreach ( $top as $idx => $s ) {
            $v        = $by_id[ $idx + 1 ] ?? [];
            $output[] = [
                'plugin'      => $s['plugin'],
                'file'        => $s['file'],
                'line'        => $s['line'],
                'pattern'     => $s['pattern'],
                'verdict'     => $v['verdict']     ?? 'needs_context',
                'severity'    => $v['severity']    ?? 'unknown',
                'type'        => $v['type']        ?? null,
                'explanation' => $v['explanation'] ?? null,
                'fix'         => $v['fix']         ?? null,
            ];
        }

        $confirmed = array_filter( $output, function ( $r ) { return $r['verdict'] === 'confirmed'; } );

        return [
            'skipped'          => false,
            'snippets_triaged' => count( $output ),
            'confirmed_count'  => count( $confirmed ),
            'results'          => $output,
        ];
    }

    private static function audit_users(): array {
        $weak_usernames = [ 'admin', 'administrator', 'webmaster', 'root', 'wp-admin', 'wordpress', 'test', 'user', 'demo' ];
        $admins         = get_users( [ 'role' => 'administrator' ] );
        $weak_admin_logins = [];
        $admins_no_2fa     = [];

        foreach ( $admins as $user ) {
            if ( in_array( strtolower( $user->user_login ), $weak_usernames, true ) ) {
                $weak_admin_logins[] = $user->user_login;
            }
            $has_totp    = get_user_meta( $user->ID, 'csdt_devtools_totp_enabled', true ) === '1';
            $has_passkey = ! empty( get_user_meta( $user->ID, 'csdt_devtools_passkeys', true ) );
            $has_email2fa= get_option( 'csdt_devtools_2fa_method', 'off' ) === 'email';
            if ( ! $has_totp && ! $has_passkey && ! $has_email2fa ) {
                $admins_no_2fa[] = $user->user_login;
            }
        }

        $role_counts = [];
        foreach ( [ 'editor', 'author', 'contributor', 'subscriber' ] as $role ) {
            $count = count( get_users( [ 'role' => $role, 'fields' => 'ID' ] ) );
            if ( $count > 0 ) {
                $role_counts[ $role ] = $count;
            }
        }

        return [
            'admin_count'         => count( $admins ),
            'weak_admin_usernames'=> $weak_admin_logins,
            'admins_without_2fa'  => $admins_no_2fa,
            'admins_without_2fa_count' => count( $admins_no_2fa ),
            'non_admin_role_counts'=> $role_counts,
        ];
    }

    private static function audit_cron_events(): array {
        $crons = _get_cron_array();
        if ( empty( $crons ) || ! is_array( $crons ) ) {
            return [ 'disable_wp_cron' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON, 'total_events' => 0, 'hooks' => [], 'suspicious_hooks' => [] ];
        }

        // Collect all scheduled hook names
        $all_hooks = [];
        foreach ( $crons as $hooks ) {
            foreach ( array_keys( $hooks ) as $hook ) {
                $all_hooks[] = $hook;
            }
        }
        $unique_hooks = array_values( array_unique( $all_hooks ) );

        // Known WP core hooks
        $core_hooks = [
            'wp_scheduled_delete', 'wp_update_plugins', 'wp_update_themes', 'wp_version_check',
            'wp_scheduled_auto_draft_delete', 'delete_expired_transients', 'wp_privacy_delete_old_export_files',
            'recovery_mode_clean_expired_keys', 'wp_site_health_scheduled_check',
            'wp_update_user_counts', 'wp_delete_temp_updater_backups',
        ];

        // Build known hooks from active plugins (use option-stored hook prefixes as heuristic)
        $active_plugins = (array) get_option( 'active_plugins', [] );
        $plugin_prefixes = array_map( fn( $f ) => strtolower( str_replace( '-', '_', dirname( $f ) ) ), $active_plugins );

        $suspicious = [];
        foreach ( $unique_hooks as $hook ) {
            if ( in_array( $hook, $core_hooks, true ) ) {
                continue;
            }
            $matched = false;
            foreach ( $plugin_prefixes as $prefix ) {
                if ( $prefix !== '.' && stripos( $hook, $prefix ) !== false ) {
                    $matched = true;
                    break;
                }
            }
            // Also pass through anything with common legit patterns
            if ( ! $matched && ! preg_match( '/^(wp_|wc_|woo|yoast|rank_math|acf_|tribe_|vc_|elementor|jetpack|akismet|wordfence|sucuri|updraft|backup|cache|cron|schedule|clean|purge|sync|check|update|send|mail|report)/i', $hook ) ) {
                $suspicious[] = $hook;
            }
        }

        return [
            'disable_wp_cron' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
            'total_events'    => count( $unique_hooks ),
            'hooks'           => array_slice( $unique_hooks, 0, 30 ),
            'suspicious_hooks'=> $suspicious,
        ];
    }

    private static function enrich_plugins_with_wporg( array $active_plugin_files ): array {
        $results = [];
        $two_years_ago = strtotime( '-2 years' );

        foreach ( $active_plugin_files as $plugin_file ) {
            $slug = dirname( $plugin_file );
            if ( $slug === '.' ) {
                continue; // single-file plugin, skip
            }

            $resp = wp_remote_get(
                'https://api.wordpress.org/plugins/info/1.0/' . rawurlencode( $slug ) . '.json',
                [ 'timeout' => 6, 'sslverify' => true ]
            );
            if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) {
                continue;
            }
            $data = json_decode( wp_remote_retrieve_body( $resp ), true );
            if ( empty( $data ) || isset( $data['error'] ) ) {
                continue; // not in WP.org repo (premium plugin etc.)
            }

            $last_updated_ts = isset( $data['last_updated'] ) ? strtotime( $data['last_updated'] ) : null;
            $results[ $slug ] = [
                'slug'             => $slug,
                'last_updated'     => $data['last_updated'] ?? null,
                'last_updated_ts'  => $last_updated_ts,
                'abandoned'        => $last_updated_ts && $last_updated_ts < $two_years_ago,
                'years_since_update' => $last_updated_ts ? round( ( time() - $last_updated_ts ) / YEAR_IN_SECONDS, 1 ) : null,
                'active_installs'  => $data['active_installs'] ?? null,
                'rating'           => isset( $data['rating'] ) ? (int) $data['rating'] : null,
                'requires_wp'      => $data['requires'] ?? null,
                'tested_up_to'     => $data['tested'] ?? null,
            ];
        }

        return $results;
    }

    private static function check_plugin_vulnerabilities( array $active_plugin_files, array $all_plugins ): array {
        $vulns = [];

        foreach ( $active_plugin_files as $plugin_file ) {
            $slug    = dirname( $plugin_file );
            $version = $all_plugins[ $plugin_file ]['Version'] ?? null;
            if ( $slug === '.' || ! $version ) {
                continue;
            }

            // Patchstack public vulnerability API — no key required
            $resp = wp_remote_get(
                'https://patchstack.com/database/api/v1/vulnerability?search=' . rawurlencode( $slug ) . '&per_page=5',
                [ 'timeout' => 8, 'sslverify' => true ]
            );
            if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) {
                continue;
            }
            $data = json_decode( wp_remote_retrieve_body( $resp ), true );
            if ( empty( $data['data'] ) || ! is_array( $data['data'] ) ) {
                continue;
            }

            foreach ( $data['data'] as $vuln ) {
                $fixed_in = $vuln['fixed_in'] ?? null;
                // Only include if the installed version is affected (below fixed_in, or no fix released)
                $affected = ! $fixed_in || version_compare( $version, $fixed_in, '<' );
                if ( ! $affected ) {
                    continue;
                }
                $vulns[] = [
                    'plugin'       => $slug,
                    'version'      => $version,
                    'cve'          => $vuln['cve_id'] ?? null,
                    'title'        => $vuln['title'] ?? $vuln['vuln_type'] ?? 'Unknown vulnerability',
                    'severity'     => $vuln['severity'] ?? null,
                    'cvss'         => $vuln['cvss_score'] ?? null,
                    'fixed_in'     => $fixed_in,
                    'disclosed_at' => $vuln['disclosed_at'] ?? null,
                ];
                if ( count( $vulns ) >= 20 ) {
                    break 2; // cap total
                }
            }
        }

        return $vulns;
    }

    private static function check_core_integrity(): array {
        $version = get_bloginfo( 'version' );
        $resp    = wp_remote_get(
            'https://api.wordpress.org/core/checksums/1.0/?version=' . rawurlencode( $version ) . '&locale=en_US',
            [ 'timeout' => 8, 'sslverify' => true ]
        );
        if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) {
            return [ 'available' => false, 'error' => 'Could not fetch checksums from WordPress.org' ];
        }
        $body      = json_decode( wp_remote_retrieve_body( $resp ), true );
        $checksums = $body['checksums'] ?? null;
        if ( ! is_array( $checksums ) ) {
            return [ 'available' => false, 'error' => 'Invalid checksum response' ];
        }

        // High-value files most commonly backdoored
        $check_files = [
            'index.php',
            'wp-login.php',
            'wp-settings.php',
            'wp-load.php',
            'wp-config-sample.php',
            'wp-includes/functions.php',
            'wp-includes/pluggable.php',
            'wp-includes/class-wp-hook.php',
            'wp-includes/class-wp-query.php',
            'wp-includes/user.php',
            'wp-admin/index.php',
            'wp-admin/includes/file.php',
        ];

        $modified  = [];
        $missing   = [];
        $checked   = 0;

        foreach ( $check_files as $file ) {
            if ( ! isset( $checksums[ $file ] ) ) {
                continue;
            }
            $path = ABSPATH . $file;
            if ( ! file_exists( $path ) ) {
                $missing[] = $file;
                continue;
            }
            $checked++;
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            $actual = @md5_file( $path );
            if ( $actual && $actual !== $checksums[ $file ] ) {
                $modified[] = $file;
            }
        }

        return [
            'available'      => true,
            'wp_version'     => $version,
            'files_checked'  => $checked,
            'modified_files' => $modified,
            'missing_files'  => $missing,
            'clean'          => empty( $modified ) && empty( $missing ),
        ];
    }

    private static function scan_malware_indicators(): array {
        $uploads_dir = wp_upload_dir();
        $uploads_base= $uploads_dir['basedir'];

        // 1. PHP files in uploads directory (should be zero)
        $php_in_uploads = [];
        if ( is_dir( $uploads_base ) ) {
            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $uploads_base, FilesystemIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ( $iter as $file ) {
                if ( $file->getExtension() === 'php' ) {
                    $php_in_uploads[] = str_replace( $uploads_base . '/', '', $file->getPathname() );
                    if ( count( $php_in_uploads ) >= 10 ) {
                        break;
                    }
                }
            }
        }

        // 2. PHP files modified in the last 7 days outside plugin/theme dirs
        $recently_modified = [];
        $cutoff            = time() - ( 7 * DAY_IN_SECONDS );
        $skip_paths        = [ WP_PLUGIN_DIR, get_theme_root() ];

        $scan_dirs = [ ABSPATH, ABSPATH . 'wp-includes', ABSPATH . 'wp-admin' ];
        foreach ( $scan_dirs as $dir ) {
            if ( ! is_dir( $dir ) ) {
                continue;
            }
            $iter = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
                    function ( $file, $key, $iter ) use ( $skip_paths ) {
                        if ( $iter->hasChildren() ) {
                            foreach ( $skip_paths as $skip ) {
                                if ( strpos( $file->getPathname(), $skip ) === 0 ) {
                                    return false;
                                }
                            }
                            return true;
                        }
                        return $file->getExtension() === 'php';
                    }
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ( $iter as $file ) {
                if ( $file->getMTime() > $cutoff ) {
                    $recently_modified[] = str_replace( ABSPATH, '', $file->getPathname() );
                    if ( count( $recently_modified ) >= 15 ) {
                        break 2;
                    }
                }
            }
        }

        return [
            'php_files_in_uploads'      => $php_in_uploads,
            'php_files_in_uploads_count'=> count( $php_in_uploads ),
            'recently_modified_php'     => $recently_modified,
            'recently_modified_count'   => count( $recently_modified ),
        ];
    }

    private static function gather_theme_data(): array {
        $theme        = wp_get_theme();
        $parent       = $theme->parent();
        $update_themes = get_site_transient( 'update_themes' );
        $has_update   = isset( $update_themes->response[ $theme->get_stylesheet() ] );
        $parent_update = $parent ? isset( $update_themes->response[ $parent->get_stylesheet() ] ) : false;
        return [
            'active_theme'        => $theme->get( 'Name' ),
            'active_theme_version'=> $theme->get( 'Version' ),
            'active_theme_update' => $has_update,
            'parent_theme'        => $parent ? $parent->get( 'Name' ) : null,
            'parent_theme_update' => $parent_update,
        ];
    }

    private static function check_auth_salts(): array {
        $defaults = [ 'put your unique phrase here', '' ];
        $keys     = [ 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
                      'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ];
        $weak     = [];
        foreach ( $keys as $k ) {
            if ( ! defined( $k ) || in_array( constant( $k ), $defaults, true ) || strlen( constant( $k ) ) < 32 ) {
                $weak[] = $k;
            }
        }
        return [
            'all_set'  => empty( $weak ),
            'weak_keys'=> $weak,
        ];
    }

    private static function gather_deep_security_data(): array {
        $base           = self::gather_security_data();
        $active_files   = (array) get_option( 'active_plugins', [] );
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins    = get_plugins();
        $external       = self::gather_external_checks();
        $code_scan      = self::scan_plugin_code();
        $theme          = self::gather_theme_data();
        $salts          = self::check_auth_salts();
        $wporg_data     = self::enrich_plugins_with_wporg( $active_files );
        $cve_data       = self::check_plugin_vulnerabilities( $active_files, $all_plugins );
        $core_integrity = self::check_core_integrity();
        $malware        = self::scan_malware_indicators();
        $user_audit     = self::audit_users();
        $cron_audit     = self::audit_cron_events();

        // PHP end-of-life status
        $php_eol_dates = [
            '5.6' => '2018-12-31',
            '7.0' => '2019-01-10',
            '7.1' => '2019-12-01',
            '7.2' => '2019-11-30',
            '7.3' => '2020-12-06',
            '7.4' => '2022-11-28',
            '8.0' => '2023-11-26',
            '8.1' => '2025-12-31',
            '8.2' => '2026-12-31',
            '8.3' => '2027-12-31',
            '8.4' => '2028-12-31',
        ];
        $php_minor    = implode( '.', array_slice( explode( '.', PHP_VERSION ), 0, 2 ) );
        $php_eol_date = $php_eol_dates[ $php_minor ] ?? null;
        $php_is_eol   = $php_eol_date !== null && strtotime( $php_eol_date ) < time();
        $php_eol_info = [
            'version'    => PHP_VERSION,
            'minor'      => $php_minor,
            'eol_date'   => $php_eol_date,
            'is_eol'     => $php_is_eol,
            'days_since' => ( $php_is_eol && $php_eol_date ) ? (int) round( ( time() - strtotime( $php_eol_date ) ) / 86400 ) : null,
            'known'      => $php_eol_date !== null,
        ];

        // WordPress auto-update configuration
        $auto_updates = [
            'updater_globally_disabled' => defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED,
            'core_auto_update_constant' => defined( 'WP_AUTO_UPDATE_CORE' ) ? WP_AUTO_UPDATE_CORE : null,
        ];
        $auto_updates['core_disabled'] = $auto_updates['updater_globally_disabled'] || $auto_updates['core_auto_update_constant'] === false;

        // PHP display_errors — exposes stack traces and file paths to all visitors
        $di_raw         = (string) ini_get( 'display_errors' );
        $display_errors = [
            'display_errors_on' => ! in_array( $di_raw, [ '', '0', 'Off', 'off', 'FALSE', 'false' ], true ),
            'wp_debug_display'  => defined( 'WP_DEBUG_DISPLAY' ) ? WP_DEBUG_DISPLAY : null,
            'ini_value'         => $di_raw,
        ];

        // Inactive (deactivated) plugins — installed on disk, still exploitable via directory traversal
        $inactive_plugins = [];
        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            if ( ! in_array( $plugin_file, $active_files, true ) ) {
                $inactive_plugins[] = [
                    'name'    => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'file'    => $plugin_file,
                ];
            }
        }

        return array_merge( $base, [
            'theme'              => $theme,
            'auth_salts'         => $salts,
            'user_audit'         => $user_audit,
            'cron_audit'         => $cron_audit,
            'plugin_wporg'       => $wporg_data,
            'plugin_cves'        => $cve_data,
            'core_integrity'     => $core_integrity,
            'malware_indicators' => $malware,
            'external_checks'    => $external,
            'plugin_code_scan'   => $code_scan,
            'php_eol'            => $php_eol_info,
            'auto_updates'       => $auto_updates,
            'display_errors'     => $display_errors,
            'inactive_plugins'   => $inactive_plugins,
        ] );
    }

    private static function default_deep_scan_prompt(): string {
        return <<<'PROMPT'
You are a professional penetration tester and WordPress security expert performing a comprehensive security audit.

You will receive a JSON object with these categories:

1. Internal config — WP/PHP versions (also php_eol key: version, minor, eol_date, is_eol, days_since — EOL PHP receives no security patches, treat as critical if is_eol=true), debug flags, DISALLOW_FILE_EDIT/MODS, FORCE_SSL_ADMIN, database prefix, admin username, user counts, brute force, 2FA (email/TOTP/passkey counts), login URL obfuscation, wp-config.php permissions. Also includes app_passwords: enabled flag, how many admins have application passwords created (app passwords bypass 2FA). display_errors key: display_errors_on=true means PHP stack traces and file paths are exposed to all visitors — high risk on any production site. auto_updates key: updater_globally_disabled and core_disabled flags — if core_disabled=true the site will not auto-patch security releases.
2. Site config — open user registration, pingbacks enabled (DDoS amplification), WP version in meta generator tag, comment defaults.
3. Theme — active theme name/version, pending update for active or parent theme.
4. Auth salts — all 8 WP secret keys/salts set and non-default (weak salts = session forgery).
5. User audit (user_audit) — admin_count, weak_admin_usernames (e.g. "admin", "administrator"), admins_without_2fa (list of admin logins with no TOTP/passkey/email 2FA), non_admin_role_counts.
6. Cron audit (cron_audit) — disable_wp_cron flag, suspicious_hooks (scheduled hook names that don't match any active plugin or WP core hook — potential malware persistence).
7. Plugin WP.org data (plugin_wporg) — for each active plugin: last_updated, abandoned (>2 years since update), years_since_update, active_installs, tested_up_to. Abandoned plugins with low install counts are high risk. Also includes inactive_plugins key: list of installed-but-deactivated plugins (name, version, file) — they sit on disk unpatched and can be exploited via directory traversal or have known CVEs even though not running.
8. Known CVEs (plugin_cves) — each entry has: plugin slug, version installed, CVE ID, title, severity (critical/high/medium/low), CVSS score, fixed_in version. ANY unfixed CVE at critical/high severity is a critical finding.
9. Core file integrity (core_integrity) — MD5 comparison of key WP core files against WordPress.org checksums. modified_files = likely backdoor. This is CRITICAL if any files are listed.
10. Malware indicators (malware_indicators) — php_files_in_uploads (PHP files found in uploads dir — should be zero, any found = likely webshell), recently_modified_php (core PHP files modified in last 7 days outside plugin/theme dirs — warrants investigation).
11. External checks — SSL validity/expiry, HTTP→HTTPS redirect, TLS weak protocols (tls_weak_protocols: checked, tls10_accepted, tls11_accepted — TLS 1.0/1.1 deprecated since 2021, susceptible to POODLE/BEAST attacks), wp-login.php/xmlrpc.php/wp-cron.php access, REST API user enum (rest_users: exposed, count, slugs), author enum, directory listings (uploads_listing, plugins_listing, themes_listing — plugins/themes listing reveals exact software versions to attackers), exposed files (debug.log, .env, backup archives, phpinfo.php, .git/config etc), adminer/phpMyAdmin, server-status/server-info, WAF/CDN detected (waf_cdn.detected, waf_cdn.providers), cookie_security (WP session cookies Secure/HttpOnly/SameSite flags), email DNS (email_dns: mx_present=false means no email is configured for this domain — skip all SPF/DMARC/DKIM findings entirely; mx_present=true means email is active and all three records matter: spf_present, spf_strictness: hard_fail=good/soft_fail=weak/pass_all=dangerous; dmarc_present, dmarc_policy: none=monitoring-only-does-nothing/quarantine=acceptable/reject=best, dmarc_pct; dkim_present, dkim_selector — all three required with strong policies for full spoofing protection), security headers (csp_quality: grade good/weak/missing, issues: unsafe-inline/unsafe-eval/wildcard-source/no-default-src — any issue weakens XSS mitigation; hsts_quality: grade, max_age, includes_subdomains, issues — max-age < 31536000 means HTTPS not enforced for a full year; X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, access-control-allow-origin — wildcard "*" allows credential theft from any origin), server_version_leak: leaks_version=true means Server header discloses exact software version (e.g. nginx/1.18.0) aiding targeted CVE exploitation.
12. Plugin code scan — raw static analysis findings (may include false positives): RCE functions (eval, exec, shell_exec, base64_decode), SQLi (wpdb with raw $_GET/$_POST), XSS (unescaped echo of user input), unserialize with user input, RFI (include/require with user input). Includes plugin, file, line number.
13. Code triage (code_triage) — AI-verified verdicts on the static scan findings. Each entry: plugin, file, line, verdict (confirmed|false_positive|needs_context), severity, type, explanation, fix. ONLY report confirmed findings as real vulnerabilities — ignore false_positives. Use triage severity for confirmed items. For needs_context, mention at low severity with explanation.

Cross-correlate ALL categories for compound risks:
- Known CVE (critical/high) = immediately critical regardless of other factors
- Modified core files = active compromise, treat as critical
- PHP files in uploads = likely webshell, treat as critical
- wp-login.php accessible + brute force disabled = critical combined risk
- Abandoned plugin (>2 years) + known CVE = critical
- No WAF/CDN detected + multiple exposed endpoints = significantly elevated risk
- email_dns.mx_present=false = domain has no email — do NOT flag missing SPF/DMARC/DKIM, they are irrelevant; ADD a good[] entry: title "No email configured", detail "No MX records found for this domain — SPF, DMARC, and DKIM checks were skipped as they are not applicable."
- email_dns.mx_present=true + missing SPF + DMARC = email spoofing trivially possible
- email_dns.mx_present=true + spf_strictness=soft_fail (~all) = SPF won't block spoofed emails — flag medium; -all required
- email_dns.mx_present=true + dmarc_policy=none = DMARC record exists but does nothing (monitoring only) — flag medium; quarantine/reject required to block
- email_dns.mx_present=true + spf_strictness=soft_fail + dmarc_policy=none = email spoofing fully unblocked despite records existing — escalate to high
- wp-cron.php public = unauthenticated resource exhaustion
- Default auth salts = any active session can be forged
- debug.log exposed = credentials and stack traces publicly readable
- WP version in meta + outdated WP = targeted exploit possible
- Admins without 2FA = single password compromise = full site takeover
- Application passwords enabled + admins have app passwords = 2FA bypassable via REST API
- Suspicious cron hooks = possible malware persistence mechanism
- WP session cookies missing Secure/HttpOnly = session hijacking risk
- PHP EOL (php_eol.is_eol=true) = no security patches for PHP engine itself — critical if days_since > 365
- TLS 1.0/1.1 accepted = deprecated protocols, POODLE/BEAST exploitable — mark high
- Missing DKIM (dkim_present=false) = email spoofing possible even with SPF+DMARC — all three needed
- plugins_listing or themes_listing exposed = reveals exact plugin/theme versions to targeted attackers
- access-control-allow-origin: "*" = wildcard CORS allows any site to make credentialed requests — critical if combined with sensitive REST endpoints
- REST API user enum exposed (rest_users.exposed=true) = real usernames exposed for credential stuffing — escalates brute force risk significantly
- Abandoned plugin (plugin_wporg: abandoned=true) with no active CVEs = still high risk — unpatched future vulnerabilities likely
- display_errors.display_errors_on=true = PHP stack traces with file paths and variable values visible to all visitors — mark high on production
- auto_updates.core_disabled=true = WP core will not auto-patch security releases; combined with outdated WP version = high risk
- inactive_plugins count > 0 = deactivated plugins on disk are unpatched attack surface; flag names and versions for awareness
- csp_quality.grade=missing or weak + any XSS code finding = actively exploitable XSS without browser-side mitigation
- hsts_quality.grade=missing or max_age < 31536000 = HTTPS not enforced long-term, HTTP downgrade / MITM possible
- server_version_leak.leaks_version=true + unpatched software = version fingerprinting directly aids targeted exploitation — escalate severity

Return ONLY a JSON object (no markdown, no code fences, no explanation):
{
  "score": <integer 0-100>,
  "score_label": "<Excellent|Good|Fair|Poor|Critical>",
  "summary": "<2-3 sentence executive summary — lead with the most critical finding>",
  "critical": [{"title":"...","detail":"...","fix":"..."}],
  "high":     [{"title":"...","detail":"...","fix":"..."}],
  "medium":   [{"title":"...","detail":"...","fix":"..."}],
  "low":      [{"title":"...","detail":"...","fix":"..."}],
  "good":     [{"title":"...","detail":"..."}]
}

Scoring (be strict — known CVEs and modified core files force score to 0-34):
90-100: Excellent — no CVEs, clean core, hardened config, no significant exposure
75-89:  Good — minor issues only, no critical/high CVEs
55-74:  Fair — medium CVEs or some external exposure
35-54:  Poor — high CVEs, multiple exposures, or config weaknesses
0-34:   Critical — critical CVE, modified core files, webshell indicators, or actively exploitable exposure

Name exact plugin slugs, CVE IDs, file paths, and settings in every finding. Include GOOD PRACTICES for correctly hardened items.
PROMPT;
    }

    public static function ajax_deep_scan(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $cache_only = ! empty( $_POST['cache_only'] );

        // Page-load pre-fill: return cache silently or signal nothing cached
        if ( $cache_only ) {
            $cached = get_option( 'csdt_deep_scan_v1' );
            if ( $cached !== false ) {
                wp_send_json_success( array_merge( $cached, [ 'from_cache' => true ] ) );
            } else {
                wp_send_json_success( [ 'no_cache' => true ] );
            }
            return;
        }

        $provider = get_option( 'csdt_devtools_ai_provider', 'anthropic' );
        $has_key  = $provider === 'gemini'
            ? ! empty( get_option( 'csdt_devtools_gemini_key', '' ) )
            : ! empty( get_option( 'csdt_devtools_anthropic_key', '' ) );
        if ( ! $has_key ) {
            wp_send_json_error( [ 'message' => 'No API key configured.', 'need_key' => true ] );
            return;
        }

        // Clear previous result and mark as running
        delete_option( 'csdt_deep_scan_v1' );
        set_transient( 'csdt_deep_scan_status', [ 'status' => 'running', 'started_at' => time() ], 900 );

        // Send response immediately, then run scan after connection closes
        self::send_json_and_continue( [ 'queued' => true ] );
        self::cron_deep_scan();
        exit;
    }

    public static function cron_deep_scan(): void {
        if ( function_exists( 'set_time_limit' ) ) { set_time_limit( 0 ); }

        try {
            if ( get_transient( 'csdt_deep_scan_cancelled' ) ) {
                delete_transient( 'csdt_deep_scan_cancelled' );
                return;
            }

            $model     = get_option( 'csdt_devtools_deep_scan_model', '_auto_deep' );
            $base_data = self::gather_security_data();
            $external  = self::gather_external_checks();
            $code_scan   = self::scan_plugin_code();
            $code_triage = self::triage_code_snippets_with_ai( $code_scan );

            if ( get_transient( 'csdt_deep_scan_cancelled' ) ) {
                delete_transient( 'csdt_deep_scan_cancelled' );
                return;
            }

            $msg_internal = 'WordPress internal configuration data (JSON):' . "\n\n" . wp_json_encode( $base_data, JSON_PRETTY_PRINT );
            $msg_external = 'WordPress external exposure, plugin code scan, and AI code triage data (JSON):' . "\n\n" . wp_json_encode( [
                'external_checks'  => $external,
                'plugin_code_scan' => $code_scan,
                'code_triage'      => $code_triage,
            ], JSON_PRETTY_PRINT );

            if ( function_exists( 'curl_multi_init' ) ) {
                error_log( '[CSDT-DEEP] firing two parallel AI calls, model=' . $model );
                $texts    = self::dispatch_parallel_ai_calls( [
                    [ 'system' => self::default_internal_scan_prompt(), 'user' => $msg_internal, 'model' => $model, 'max_tokens' => 4096 ],
                    [ 'system' => self::default_external_scan_prompt(), 'user' => $msg_external, 'model' => $model, 'max_tokens' => 4096 ],
                ] );
                $report = self::merge_reports( self::parse_ai_json( $texts[0] ), self::parse_ai_json( $texts[1] ) );
            } else {
                // Fallback: single sequential call
                error_log( '[CSDT-DEEP] curl_multi unavailable, falling back to sequential, model=' . $model );
                $text   = self::dispatch_ai_call( self::default_deep_scan_prompt(), 'WordPress site full security data (JSON):' . "\n\n" . wp_json_encode( [ 'internal' => $base_data, 'external_checks' => $external, 'plugin_code_scan' => $code_scan, 'code_triage' => $code_triage ], JSON_PRETTY_PRINT ), $model, 8192 );
                $report = self::parse_ai_json( $text );
            }

        } catch ( \Throwable $e ) {
            set_transient( 'csdt_deep_scan_status', [ 'status' => 'error', 'message' => $e->getMessage() ], 300 );
            return;
        }

        if ( get_transient( 'csdt_deep_scan_cancelled' ) ) {
            delete_transient( 'csdt_deep_scan_cancelled' );
            return;
        }

        $output = [
            'report'      => $report,
            'code_triage' => $code_triage,
            'model_used'  => get_option( 'csdt_devtools_ai_provider', 'anthropic' ) . '/' . $model,
            'scanned_at'  => time(),
            'from_cache'  => false,
        ];

        update_option( 'csdt_deep_scan_v1', $output, false );
        set_transient( 'csdt_deep_scan_status', [ 'status' => 'complete', 'completed_at' => time() ], 900 );
        self::append_scan_history( 'deep', $report, $output['model_used'], $output['scanned_at'] );
        error_log( '[CSDT-DEEP] cron complete (parallel), score=' . $report['score'] );
    }

    private static function append_scan_history( string $type, array $report, string $model_used, int $scanned_at ): void {
        $history = get_option( 'csdt_scan_history', [] );
        if ( ! is_array( $history ) ) { $history = []; }
        array_unshift( $history, [
            'type'           => $type,
            'score'          => $report['score']       ?? null,
            'score_label'    => $report['score_label'] ?? '',
            'summary'        => $report['summary']     ?? '',
            'critical_count' => count( $report['critical'] ?? [] ),
            'high_count'     => count( $report['high']     ?? [] ),
            'model_used'     => $model_used,
            'scanned_at'     => $scanned_at,
        ] );
        // Keep last 10 across both scan types
        $history = array_slice( $history, 0, 10 );
        update_option( 'csdt_scan_history', $history, false );
    }

    public static function ajax_scan_history(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
            return;
        }
        wp_send_json_success( get_option( 'csdt_scan_history', [] ) );
    }

    public static function ajax_scan_status(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'standard';

        if ( $type === 'deep' ) {
            $status_key = 'csdt_deep_scan_status';
            $result_key = 'csdt_deep_scan_v1';
        } else {
            $status_key = 'csdt_vuln_scan_status';
            $result_key = 'csdt_security_scan_v2';
        }

        $status = get_transient( $status_key );
        $result = get_option( $result_key );

        if ( ! $status ) {
            if ( $result ) {
                wp_send_json_success( [ 'status' => 'complete', 'data' => array_merge( $result, [ 'from_cache' => true ] ) ] );
            } else {
                wp_send_json_success( [ 'status' => 'idle' ] );
            }
            return;
        }

        if ( $status['status'] === 'running' ) {
            wp_send_json_success( [ 'status' => 'running' ] );
            return;
        }

        if ( $status['status'] === 'complete' && $result ) {
            wp_send_json_success( [ 'status' => 'complete', 'data' => array_merge( $result, [ 'from_cache' => false ] ) ] );
            return;
        }

        if ( $status['status'] === 'error' ) {
            wp_send_json_success( [ 'status' => 'error', 'message' => $status['message'] ?? 'Scan failed.' ] );
            return;
        }

        wp_send_json_success( [ 'status' => 'idle' ] );
    }

    /* ==================================================================
       TEST ACCOUNT MANAGER
       ================================================================== */

    private static function get_active_test_accounts(): array {
        $users = get_users( [
            'meta_key'   => 'csdt_test_account',
            'meta_value' => '1',
            'fields'     => [ 'ID', 'user_login' ],
        ] );

        $accounts = [];
        foreach ( $users as $u ) {
            $expires_at  = (int) get_user_meta( $u->ID, 'csdt_test_expires_at', true );
            $single_use  = (bool) get_user_meta( $u->ID, 'csdt_test_single_use', true );
            $accounts[] = [
                'user_id'    => $u->ID,
                'username'   => $u->user_login,
                'expires_at' => $expires_at,
                'expires_in' => max( 0, $expires_at - time() ),
                'single_use' => $single_use,
            ];
        }

        return $accounts;
    }

    private static function create_test_account( int $ttl = 1800 ): array {
        $username  = 'test-' . wp_generate_password( 8, false, false );
        $password  = wp_generate_password( 20 );
        $email     = $username . '@test.local';
        $user_id   = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            return [ 'error' => $user_id->get_error_message() ];
        }

        $user = new WP_User( $user_id );
        $user->set_role( 'subscriber' );

        $expires_at  = time() + $ttl;
        $single_use  = get_option( 'csdt_test_account_single_use', '0' ) === '1';

        update_user_meta( $user_id, 'csdt_test_account',    '1' );
        update_user_meta( $user_id, 'csdt_test_expires_at', $expires_at );
        update_user_meta( $user_id, 'csdt_test_single_use', $single_use ? '1' : '0' );

        [ $app_password, $item ] = WP_Application_Passwords::create_new_application_password(
            $user_id,
            [ 'name' => 'playwright-ci' ]
        );

        if ( is_wp_error( $app_password ) ) {
            wp_delete_user( $user_id );
            return [ 'error' => $app_password->get_error_message() ];
        }

        $formatted_pw = implode( ' ', str_split( $app_password, 4 ) );

        return [
            'user_id'    => $user_id,
            'username'   => $username,
            'app_password' => $formatted_pw,
            'rest_url'   => rest_url( 'wp/v2/users/me' ),
            'expires_at' => $expires_at,
            'accounts'   => self::get_active_test_accounts(),
        ];
    }

    public static function ajax_create_test_account(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 403 );
        }
        check_ajax_referer( 'csdt_devtools_login_nonce', 'nonce' );

        $ttl    = (int) get_option( 'csdt_test_account_ttl', '1800' );
        $result = self::create_test_account( $ttl );

        if ( isset( $result['error'] ) ) {
            wp_send_json_error( $result['error'] );
        }

        wp_send_json_success( $result );
    }

    public static function ajax_revoke_test_account(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 403 );
        }
        check_ajax_referer( 'csdt_devtools_login_nonce', 'nonce' );

        $user_id = (int) ( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_send_json_error( 'Missing user_id' );
        }

        if ( get_user_meta( $user_id, 'csdt_test_account', true ) !== '1' ) {
            wp_send_json_error( 'Not a test account' );
        }

        wp_delete_user( $user_id );

        wp_send_json_success( [ 'accounts' => self::get_active_test_accounts() ] );
    }

    public static function ajax_save_test_account_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 403 );
        }
        check_ajax_referer( 'csdt_devtools_login_nonce', 'nonce' );

        $enabled     = ( $_POST['enabled']     ?? '0' ) === '1' ? '1' : '0';
        $ttl         = in_array( (string) ( $_POST['ttl'] ?? '1800' ), [ '1800', '3600', '7200', '86400' ], true )
                       ? (string) $_POST['ttl'] : '1800';
        $single_use  = ( $_POST['single_use']  ?? '0' ) === '1' ? '1' : '0';

        update_option( 'csdt_test_accounts_enabled',     $enabled );
        update_option( 'csdt_test_account_ttl',          $ttl );
        update_option( 'csdt_test_account_single_use',   $single_use );

        if ( $enabled === '1' ) {
            if ( ! wp_next_scheduled( 'csdt_cleanup_test_accounts' ) ) {
                wp_schedule_event( time() + 300, 'csdt_every_5min', 'csdt_cleanup_test_accounts' );
            }
        } else {
            wp_clear_scheduled_hook( 'csdt_cleanup_test_accounts' );
        }

        wp_send_json_success();
    }

    public static function cleanup_expired_test_accounts(): void {
        $users = get_users( [
            'meta_key'   => 'csdt_test_account',
            'meta_value' => '1',
            'fields'     => [ 'ID' ],
        ] );

        $now = time();
        foreach ( $users as $u ) {
            $expires_at = (int) get_user_meta( $u->ID, 'csdt_test_expires_at', true );
            if ( $expires_at && $expires_at < $now ) {
                wp_delete_user( $u->ID );
            }
        }
    }

    public static function filter_app_pw_for_user( $available, $user ): bool {
        if ( get_user_meta( $user->ID, 'csdt_test_account', true ) === '1' ) {
            return true;
        }
        return false;
    }

    public static function test_account_after_auth( $user, $app_password ): void {
        if ( get_user_meta( $user->ID, 'csdt_test_single_use', true ) === '1' ) {
            wp_delete_user( $user->ID );
        }
    }

}

CloudScale_DevTools::init();
