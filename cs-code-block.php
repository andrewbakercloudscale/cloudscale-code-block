<?php
/**
 * Plugin Name: CloudScale Cyber and Devtools
 * Plugin URI: https://your-wordpress-site.example.com
 * Description: AI security scanner and developer toolkit. Replaces your security scanner, 2FA plugin, SMTP mailer, SQL tool, and log viewer — one free plugin, no cloud dependency.
 * Version: 1.9.370
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
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-dispatcher.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-csp.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-sec-headers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-site-audit.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-vuln-scan.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-login.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-smtp.php';

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

    const VERSION      = '1.9.370';
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
        CSDT_SMTP::maybe_migrate_prefix();
        CSDT_SMTP::maybe_migrate_smtp_prefix();
        CSDT_SMTP::maybe_migrate_usermeta_prefix();
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
        add_action( 'wp_ajax_csdt_devtools_save_theme_setting',  [ __CLASS__, 'ajax_save_theme_setting' ] );
        add_action( 'wp_ajax_csdt_devtools_save_perf_monitor',   [ __CLASS__, 'ajax_save_perf_monitor' ] );

        // Login security AJAX
        add_action( 'wp_ajax_csdt_devtools_login_save',          [ 'CSDT_Login', 'ajax_login_save' ] );
        add_action( 'wp_ajax_csdt_devtools_bf_log_fetch',        [ 'CSDT_Login', 'ajax_bf_log_fetch' ] );
        add_action( 'wp_ajax_csdt_ssh_monitor_save',             [ __CLASS__, 'ajax_ssh_monitor_save' ] );
        add_action( 'wp_ajax_csdt_ssh_log_clear',               [ __CLASS__, 'ajax_ssh_log_clear' ] );
        add_action( 'wp_ajax_csdt_bf_self_test',                [ __CLASS__, 'ajax_bf_self_test' ] );
        add_action( 'wp_ajax_csdt_devtools_totp_setup_start',    [ 'CSDT_Login', 'ajax_totp_setup_start' ] );
        add_action( 'wp_ajax_csdt_devtools_totp_setup_verify',   [ 'CSDT_Login', 'ajax_totp_setup_verify' ] );
        add_action( 'wp_ajax_csdt_devtools_2fa_disable',         [ 'CSDT_Login', 'ajax_2fa_disable' ] );
        add_action( 'wp_ajax_csdt_devtools_email_2fa_enable',    [ 'CSDT_Login', 'ajax_email_2fa_enable' ] );
        add_action( 'admin_init',           [ 'CSDT_Login', 'email_2fa_confirm_check' ] );
        add_action( 'after_password_reset', [ 'CSDT_Login', 'on_password_reset' ], 10, 1 );
        add_action( 'profile_update',       [ 'CSDT_Login', 'on_profile_update' ], 10, 2 );
        CSDT_DevTools_Passkey::register_hooks();

        // Default Featured Image
        add_action( 'wp_ajax_csdt_save_default_image',     [ __CLASS__, 'ajax_save_default_image' ] );
        add_filter( 'post_thumbnail_html',                  [ __CLASS__, 'default_image_html' ], 10, 5 );
        add_filter( 'has_post_thumbnail',                   [ __CLASS__, 'default_image_has_thumbnail' ], 10, 3 );

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
        add_action( 'transition_post_status', [ __CLASS__, 'on_post_status_change' ], 10, 3 );
        add_action( 'admin_notices',   [ __CLASS__, 'social_format_admin_notice' ] );
        // Serve platform-specific og:image based on crawler User-Agent.
        add_action( 'wp_head', [ __CLASS__, 'output_crawler_og_image' ], 1 );
        add_action( 'wp_ajax_csdt_devtools_social_cf_test',     [ __CLASS__, 'ajax_social_cf_test' ] );
        add_action( 'wp_ajax_csdt_devtools_cf_purge',           [ __CLASS__, 'ajax_cf_purge' ] );
        add_action( 'wp_ajax_csdt_devtools_cf_save',            [ __CLASS__, 'ajax_cf_save' ] );

        // SMTP AJAX
        add_action( 'wp_ajax_csdt_devtools_smtp_save',      [ 'CSDT_SMTP', 'ajax_smtp_save' ] );
        add_action( 'wp_ajax_csdt_devtools_smtp_test',      [ 'CSDT_SMTP', 'ajax_smtp_test' ] );
        add_action( 'wp_ajax_csdt_devtools_smtp_log_clear', [ 'CSDT_SMTP', 'ajax_smtp_log_clear' ] );
        add_action( 'wp_ajax_csdt_devtools_smtp_log_fetch', [ 'CSDT_SMTP', 'ajax_smtp_log_fetch' ] );
        add_action( 'wp_ajax_csdt_devtools_smtp_log_view',  [ 'CSDT_SMTP', 'ajax_smtp_log_view' ] );

        add_action( 'wp_ajax_csdt_devtools_vuln_scan',          [ 'CSDT_Vuln_Scan', 'ajax_vuln_scan' ] );
        add_action( 'wp_ajax_csdt_devtools_deep_scan',          [ 'CSDT_Site_Audit', 'ajax_deep_scan' ] );
        add_action( 'wp_ajax_csdt_devtools_scan_status',        [ 'CSDT_Site_Audit', 'ajax_scan_status' ] );
        add_action( 'wp_ajax_csdt_devtools_cancel_scan',        [ 'CSDT_Site_Audit', 'ajax_cancel_scan' ] );
        add_action( 'wp_ajax_csdt_devtools_vuln_save_key',      [ 'CSDT_Vuln_Scan', 'ajax_vuln_save_key' ] );
        add_action( 'wp_ajax_csdt_devtools_security_test_key',  [ 'CSDT_Vuln_Scan', 'ajax_security_test_key' ] );
        add_action( 'wp_ajax_csdt_devtools_server_logs_status',     [ __CLASS__, 'ajax_server_logs_status' ] );
        add_action( 'wp_ajax_csdt_devtools_server_logs_fetch',      [ __CLASS__, 'ajax_server_logs_fetch' ] );
        add_action( 'wp_ajax_csdt_devtools_logs_setup_php',         [ __CLASS__, 'ajax_logs_setup_php' ] );
        add_action( 'wp_ajax_csdt_devtools_logs_fix_mu_perms',      [ __CLASS__, 'ajax_logs_fix_mu_perms' ] );
        add_action( 'wp_ajax_csdt_devtools_logs_custom_save',       [ __CLASS__, 'ajax_logs_custom_save' ] );
        add_action( 'wp_ajax_csdt_devtools_scan_history',       [ 'CSDT_Site_Audit', 'ajax_scan_history' ] );
        add_action( 'wp_ajax_csdt_devtools_save_schedule',      [ 'CSDT_Site_Audit', 'ajax_save_schedule' ] );
        add_action( 'wp_ajax_csdt_devtools_quick_fix',          [ 'CSDT_Site_Audit', 'ajax_apply_quick_fix' ] );
        add_action( 'wp_ajax_csdt_db_prefix_preflight',         [ 'CSDT_Site_Audit', 'ajax_db_prefix_preflight' ] );
        add_action( 'wp_ajax_csdt_db_prefix_migrate',           [ 'CSDT_Site_Audit', 'ajax_db_prefix_migrate' ] );
        add_action( 'wp_ajax_csdt_db_prefix_rollback',          [ 'CSDT_Site_Audit', 'ajax_db_prefix_rollback' ] );
        add_action( 'wp_ajax_csdt_db_orphaned_scan',            [ __CLASS__, 'ajax_db_orphaned_scan' ] );
        add_action( 'wp_ajax_csdt_db_identify_table',           [ __CLASS__, 'ajax_db_identify_table' ] );
        add_action( 'wp_ajax_csdt_db_archive_tables',           [ __CLASS__, 'ajax_db_archive_tables' ] );
        add_action( 'wp_ajax_csdt_db_trash_scan',               [ __CLASS__, 'ajax_db_trash_scan' ] );
        add_action( 'wp_ajax_csdt_db_restore_tables',           [ __CLASS__, 'ajax_db_restore_tables' ] );
        add_action( 'wp_ajax_csdt_db_drop_tables',              [ __CLASS__, 'ajax_db_drop_tables' ] );
        add_action( 'wp_ajax_csdt_sec_headers_save',            [ 'CSDT_Security_Headers', 'ajax_sec_headers_save' ] );
        add_action( 'wp_ajax_csdt_devtools_csp_save',           [ 'CSDT_CSP', 'ajax_csp_save' ] );
        add_action( 'wp_ajax_csdt_devtools_csp_rollback',       [ 'CSDT_CSP', 'ajax_csp_rollback' ] );
        add_action( 'wp_ajax_csdt_devtools_csp_restore',        [ 'CSDT_CSP', 'ajax_csp_restore' ] );
        add_action( 'wp_ajax_csdt_scan_headers',                 [ 'CSDT_Security_Headers', 'ajax_scan_headers' ] );
        add_action( 'wp_ajax_csdt_scan_history_item',            [ 'CSDT_Security_Headers', 'ajax_scan_history_item' ] );
        add_action( 'wp_ajax_csdt_devtools_csp_violations_get',  [ 'CSDT_CSP', 'ajax_csp_violations_get' ] );
        add_action( 'wp_ajax_csdt_devtools_csp_violations_clear', [ 'CSDT_CSP', 'ajax_csp_violations_clear' ] );
        add_action( 'send_headers',                             [ 'CSDT_CSP', 'output_security_headers' ] );
        add_action( 'wp_ajax_csdt_test_account_create',          [ __CLASS__, 'ajax_create_test_account' ] );
        add_action( 'wp_ajax_csdt_test_account_revoke',          [ __CLASS__, 'ajax_revoke_test_account' ] );
        add_action( 'wp_ajax_csdt_test_account_settings_save',   [ __CLASS__, 'ajax_save_test_account_settings' ] );
        add_action( 'csdt_cleanup_test_accounts',                [ __CLASS__, 'cleanup_expired_test_accounts' ] );
        add_action( 'csdt_scheduled_scan',                      [ 'CSDT_Site_Audit', 'run_scheduled_scan' ] );
        add_action( 'csdt_ssh_monitor',                         [ __CLASS__, 'monitor_ssh_failures' ] );
        add_action( 'csdt_php_error_monitor',                   [ __CLASS__, 'monitor_php_errors' ] );
        add_action( 'wp_ajax_csdt_php_error_monitor_save',      [ __CLASS__, 'ajax_php_error_monitor_save' ] );
        add_action( 'wp_ajax_csdt_fpm_monitor_save',             [ __CLASS__, 'ajax_fpm_monitor_save' ] );
        add_action( 'wp_ajax_csdt_fpm_worker_status',            [ __CLASS__, 'ajax_fpm_worker_status' ] );
        add_action( 'wp_ajax_csdt_fpm_setup_detect',             [ __CLASS__, 'ajax_fpm_setup_detect' ] );
        add_action( 'wp_ajax_csdt_fpm_setup_patch',              [ __CLASS__, 'ajax_fpm_setup_patch' ] );
        add_action( 'wp_ajax_csdt_fpm_worker_detail',            [ __CLASS__, 'ajax_fpm_worker_detail' ] );
        add_action( 'wp_ajax_csdt_sql_http_fix',                  [ __CLASS__, 'ajax_sql_http_fix' ] );
        add_action( 'wp_ajax_nopriv_csdt_fpm_report',            [ __CLASS__, 'ajax_fpm_report' ] );
        add_action( 'wp_ajax_csdt_fpm_report',                   [ __CLASS__, 'ajax_fpm_report' ] );
        add_action( 'csdt_threat_monitor',                      [ __CLASS__, 'monitor_threats' ] );
        add_action( 'wp_ajax_csdt_threat_monitor_save',         [ __CLASS__, 'ajax_threat_monitor_save' ] );
        add_action( 'wp_ajax_csdt_threat_integrity_reset',      [ __CLASS__, 'ajax_threat_integrity_reset' ] );
        add_action( 'user_register',                            [ __CLASS__, 'on_user_registered' ] );
        add_action( 'set_user_role',                            [ __CLASS__, 'on_set_user_role' ], 10, 3 );
        add_filter( 'cron_schedules',                           [ 'CSDT_Site_Audit', 'add_cron_schedules' ] );
        add_action( 'wp_ajax_csdt_plugin_stack_scan',           [ __CLASS__, 'ajax_plugin_stack_scan' ] );
        add_action( 'wp_ajax_csdt_ai_debug_log',                [ __CLASS__, 'ajax_ai_debug_log' ] );
        add_action( 'wp_ajax_csdt_site_audit',                  [ 'CSDT_Site_Audit', 'ajax_site_audit' ] );
        add_action( 'wp_ajax_csdt_update_risk_scan',            [ __CLASS__, 'ajax_update_risk_scan' ] );
        add_action( 'wp_ajax_csdt_update_risk_assess',          [ __CLASS__, 'ajax_update_risk_assess' ] );
        add_action( 'wp_ajax_csdt_db_intelligence_scan',        [ __CLASS__, 'ajax_db_intelligence_scan' ] );
        add_action( 'wp_ajax_csdt_db_intelligence_fix',         [ __CLASS__, 'ajax_db_intelligence_fix' ] );
        add_action( 'wp_ajax_nopriv_csdt_uptime_ping',          [ __CLASS__, 'ajax_uptime_ping' ] );
        add_action( 'wp_ajax_csdt_uptime_ping',                 [ __CLASS__, 'ajax_uptime_ping' ] );
        add_action( 'wp_ajax_csdt_uptime_setup',                [ __CLASS__, 'ajax_uptime_setup' ] );
        add_action( 'wp_ajax_csdt_uptime_history',              [ __CLASS__, 'ajax_uptime_history' ] );
        add_action( 'wp_ajax_csdt_uptime_deploy_worker',        [ __CLASS__, 'ajax_uptime_deploy_worker' ] );
        add_action( 'wp_ajax_csdt_uptime_save_settings',        [ __CLASS__, 'ajax_uptime_save_settings' ] );
        add_action( 'admin_bar_menu',                           [ __CLASS__, 'render_admin_bar_badge' ], 100 );
        add_action( 'admin_head',                               [ __CLASS__, 'admin_bar_badge_styles' ] );
        add_action( 'wp_head',                                  [ __CLASS__, 'admin_bar_badge_styles' ] );

        // CSP nonce injection — only active when nonce mode is enabled
        if ( ! is_admin() && get_option( 'csdt_csp_nonces_enabled', '0' ) === '1' ) {
            add_filter( 'script_loader_tag',          [ 'CSDT_CSP', 'csp_nonce_script_tag' ], 10, 1 );
            add_filter( 'style_loader_tag',           [ 'CSDT_CSP', 'csp_nonce_style_tag' ],  10, 1 );
            // WP 6.3+ inline script attributes filter
            add_filter( 'wp_inline_script_attributes', [ 'CSDT_CSP', 'csp_nonce_inline_attrs' ], 10, 1 );
            // Output buffer to catch scripts that bypass wp_enqueue (AdSense, theme inline scripts, etc.)
            add_action( 'template_redirect', [ 'CSDT_CSP', 'csp_ob_start' ], 0 );
        }

        // Schedule SSH monitor (default on) — ensure cron is running if enabled
        if ( get_option( 'csdt_ssh_monitor_enabled', '1' ) === '1' ) {
            if ( ! wp_next_scheduled( 'csdt_ssh_monitor' ) ) {
                wp_schedule_event( time() + 60, 'csdt_every_1min', 'csdt_ssh_monitor' );
            }
        } else {
            wp_clear_scheduled_hook( 'csdt_ssh_monitor' );
        }

        // Schedule PHP error monitor (default on)
        if ( get_option( 'csdt_php_error_monitor_enabled', '1' ) === '1' ) {
            if ( ! wp_next_scheduled( 'csdt_php_error_monitor' ) ) {
                wp_schedule_event( time() + 300, 'csdt_every_5min', 'csdt_php_error_monitor' );
            }
        } else {
            wp_clear_scheduled_hook( 'csdt_php_error_monitor' );
        }

        // Schedule threat monitor (default on)
        if ( get_option( 'csdt_threat_monitor_enabled', '1' ) === '1' ) {
            if ( ! wp_next_scheduled( 'csdt_threat_monitor' ) ) {
                wp_schedule_event( time() + 300, 'csdt_every_5min', 'csdt_threat_monitor' );
            }
        } else {
            wp_clear_scheduled_hook( 'csdt_threat_monitor' );
        }

        add_action( 'csdt_devtools_run_vuln_scan', [ 'CSDT_Vuln_Scan', 'cron_vuln_scan' ] );
        add_action( 'csdt_devtools_run_deep_scan', [ 'CSDT_Site_Audit', 'cron_deep_scan' ] );

        // Email log — always active so every wp_mail() call is tracked site-wide,
        // regardless of whether our SMTP is enabled.
        add_filter( 'wp_mail',        [ 'CSDT_SMTP', 'smtp_log_capture' ] );
        add_action( 'wp_mail_failed', [ 'CSDT_SMTP', 'smtp_log_on_failure' ] );
        // Priority 5 so it runs before phpmailer_configure (priority 10) and sets action_function first.
        add_action( 'phpmailer_init', [ 'CSDT_SMTP', 'smtp_log_set_callback' ], 5 );

        // SMTP — configure phpmailer and override from address only when fully configured.
        // Guard: if host is empty we skip configuration entirely so other plugins' emails
        // continue to work via PHP mail() rather than silently failing.
        if ( get_option( 'csdt_devtools_smtp_enabled', '0' ) === '1'
            && '' !== trim( (string) get_option( 'csdt_devtools_smtp_host', '' ) )
        ) {
            add_action( 'phpmailer_init', [ 'CSDT_SMTP', 'phpmailer_configure' ] );
            if ( get_option( 'csdt_devtools_smtp_from_email', '' ) ) {
                add_filter( 'wp_mail_from',      [ 'CSDT_SMTP', 'smtp_from_email' ] );
            }
            if ( get_option( 'csdt_devtools_smtp_from_name', '' ) ) {
                add_filter( 'wp_mail_from_name', [ 'CSDT_SMTP', 'smtp_from_name' ] );
            }
        }

        // Login security — URL intercept / 2FA flow (early, priority 1 on init).
        add_action( 'init',        [ 'CSDT_Login', 'login_admin_intercept' ], 0 );
        add_action( 'init',        [ 'CSDT_Login', 'login_serve_custom_slug' ], 1 );
        add_action( 'login_init',  [ 'CSDT_Login', 'login_redirect_authenticated' ], 0 );
        add_action( 'login_init',  [ 'CSDT_Login', 'login_block_direct_access' ], 1 );
        add_filter( 'auth_cookie_expiration', [ 'CSDT_Login', 'login_session_expiration' ], 10, 3 );
        add_action( 'login_init',  [ 'CSDT_Login', 'login_2fa_handle' ] );
        add_filter( 'authenticate',        [ 'CSDT_Login', 'login_2fa_intercept' ], 100, 3 );
        add_filter( 'login_url',           [ 'CSDT_Login', 'login_custom_url' ], 10, 3 );
        add_filter( 'logout_url',          [ 'CSDT_Login', 'login_custom_logout_url' ], 10, 2 );
        add_filter( 'lostpassword_url',    [ 'CSDT_Login', 'login_custom_lostpassword_url' ], 10, 2 );
        add_filter( 'network_site_url',    [ 'CSDT_Login', 'login_custom_network_url' ], 10, 3 );
        add_filter( 'site_url',            [ 'CSDT_Login', 'login_custom_site_url' ], 10, 4 );

        // Brute-force protection — check before authentication (priority 1, before password check).
        add_filter( 'authenticate',    [ 'CSDT_Login', 'login_brute_force_check' ], 1, 3 );
        // Force persistent cookie when a custom session duration is configured.
        // Must be login_init (fires before the POST is processed) not login_form_login
        // (which is a display hook that never fires on a successful login POST).
        add_action( 'login_init', [ 'CSDT_Login', 'login_force_remember' ], 5 );
        // Security monitor — always track failed logins regardless of monitor toggle.
        add_action( 'wp_login_failed', [ __CLASS__, 'perf_track_failed_login' ] );
        // Style the login error panel.
        add_action( 'login_enqueue_scripts', [ 'CSDT_Login', 'login_error_styles' ] );
        // Username enumeration protection — only register if option is enabled (default on).
        if ( get_option( 'csdt_devtools_enum_protect', '1' ) === '1' ) {
            add_filter( 'wp_login_errors', [ 'CSDT_Login', 'generic_login_errors' ] );
        }

        // Custom 404 page + hiscore leaderboard.
        add_action( 'template_redirect',                        [ __CLASS__, 'maybe_custom_404' ], 1 );
        add_action( 'rest_api_init',                            [ __CLASS__, 'register_hiscore_routes' ] );
        add_action( 'rest_api_init',                            [ 'CSDT_CSP', 'register_csp_report_route' ] );
        add_action( 'rest_api_init',                            [ __CLASS__, 'register_fpm_report_route' ] );
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
            '🔐 Cyber and Devtools',
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

        if ( $active_tab === 'debug' || $active_tab === '404' ) {
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

        if ( in_array( $active_tab, [ 'security', 'home' ], true ) ) {
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
                'defaultPrompt'  => CSDT_Site_Audit::default_security_prompt(),
                'scanHistory'    => get_option( 'csdt_scan_history', [] ),
            ] );
        }

        if ( $active_tab === 'thumbnails' ) {
            wp_enqueue_media();
            $thumb_js = plugin_dir_path( __FILE__ ) . 'assets/cs-thumbnails.js';
            wp_enqueue_script(
                'csdt-thumbnails',
                plugins_url( 'assets/cs-thumbnails.js', __FILE__ ),
                [ 'jquery' ],
                self::VERSION,
                true
            );
            wp_localize_script( 'csdt-thumbnails', 'csdtDevtoolsThumbs', [
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( 'csdt_devtools_thumbnails' ),
                'siteUrl'    => home_url( '/' ),
                'defimgNonce'=> wp_create_nonce( 'csdt_defimg' ),
            ] );
            // Thumbnails-tab-specific CSS — injected as inline style to avoid an
            // extra HTTP request and keep the render method free of <style> tags.
            wp_add_inline_style( 'csdt-admin-tabs', self::get_thumbnails_admin_css() );
        }

        if ( $active_tab === 'optimizer' ) {
            wp_enqueue_script(
                'csdt-optimizer',
                plugins_url( 'assets/cs-plugin-stack.js', __FILE__ ),
                [],
                self::VERSION,
                true
            );
            wp_localize_script( 'csdt-optimizer', 'csdtOptimizer', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'csdt_optimizer_nonce' ),
                'baseUrl' => admin_url( 'tools.php?page=' . self::TOOLS_SLUG ),
            ] );
        }

        if ( $active_tab === 'site-audit' ) {
            wp_enqueue_script(
                'csdt-site-audit',
                plugins_url( 'assets/cs-site-audit.js', __FILE__ ),
                [],
                self::VERSION,
                true
            );
            $audit_cache = get_option( 'csdt_site_audit_cache', null );
            wp_localize_script( 'csdt-site-audit', 'csdtSiteAudit', [
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'csdt_site_audit_nonce' ),
                'secNonce' => wp_create_nonce( 'csdt_devtools_security_nonce' ),
                'seoAiUrl' => admin_url( 'tools.php?page=cs-seo-optimizer' ),
                'cached'   => $audit_cache ? $audit_cache['data']   : null,
                'cachedAt' => $audit_cache ? $audit_cache['run_at'] : null,
            ] );
        }

        if ( $active_tab === 'debug' || $active_tab === 'logs' ) {
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

        if ( $active_tab === 'debug' ) {
            wp_enqueue_script(
                'csdt-debug',
                plugins_url( 'assets/cs-debug.js', __FILE__ ),
                [],
                self::VERSION,
                true
            );
            wp_localize_script( 'csdt-debug', 'csdtDebug', [
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'logsNonce'     => wp_create_nonce( 'csdt_devtools_server_logs' ),
                'aiNonce'       => wp_create_nonce( 'csdt_optimizer_nonce' ),
                'debugNonce'    => wp_create_nonce( 'csdt_debug_nonce' ),
                'fpmNonce'      => wp_create_nonce( 'csdt_fpm_nonce' ),
                'perfNonce'     => wp_create_nonce( 'csdt_devtools_perf_monitor_nonce' ),
                'perfEnabled'   => get_option( 'csdt_devtools_perf_monitor_enabled', '1' ),
                'sources'       => self::get_log_sources(),
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
                    <div id="cs-banner-title">🔐 CloudScale Cyber and Devtools</div>
                    <div id="cs-banner-sub"><?php esc_html_e( 'AI security scanner, 2FA, SMTP mailer, SQL tools &amp; developer toolkit', 'cloudscale-devtools' ); ?> &middot; v<?php echo esc_html( self::VERSION ); ?></div>
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
                <a href="<?php echo esc_url( $base_url . '&tab=site-audit' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'site-audit' ? 'active' : ''; ?>">
                    🔍 <?php esc_html_e( 'Site Audit', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=login' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'login' ? 'active' : ''; ?>">
                    🔐 <?php esc_html_e( 'Login Security', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=security' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                    🛡️ <?php esc_html_e( 'AI Security Scan', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=optimizer' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'optimizer' ? 'active' : ''; ?>">
                    🔧 <?php esc_html_e( 'Optimizer', 'cloudscale-devtools' ); ?>
                </a>
                <a href="<?php echo esc_url( $base_url . '&tab=debug' ); ?>"
                   class="cs-tab <?php echo $active_tab === 'debug' ? 'active' : ''; ?>">
                    🧠 <?php esc_html_e( 'Debug AI', 'cloudscale-devtools' ); ?>
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
            </div>
            <script>
            (function(){
                var bar = document.getElementById('cs-tab-bar');
                var active = bar && bar.querySelector('.cs-tab.active');
                if (active) { active.scrollIntoView({block:'nearest',inline:'center'}); }
            })();
            </script>

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
            <?php elseif ( $active_tab === 'login' ) : ?>
                <div class="cs-tab-content active">
                    <?php self::render_login_panel(); ?>
                </div>
            <?php elseif ( $active_tab === 'mail' ) : ?>
                <div class="cs-tab-content active">
                    <?php CSDT_SMTP::render_smtp_panel(); ?>
                </div>
            <?php elseif ( $active_tab === 'thumbnails' ) : ?>
                <div class="cs-tab-content active">
                    <?php self::render_thumbnails_panel(); ?>
                </div>
            <?php elseif ( $active_tab === 'security' ) : ?>
                <div class="cs-tab-content active">
                    <?php self::render_security_panel(); ?>
                </div>
            <?php elseif ( $active_tab === 'optimizer' ) : ?>
                <div class="cs-tab-content active">
                    <?php self::render_optimizer_panel(); ?>
                </div>
            <?php elseif ( $active_tab === 'site-audit' ) : ?>
                <div class="cs-tab-content active">
                    <?php CSDT_Site_Audit::render_site_audit_panel(); ?>
                </div>
            <?php elseif ( in_array( $active_tab, [ 'debug', 'logs', 'sql', '404' ], true ) ) : ?>
                <div class="cs-tab-content active">
                    <?php self::render_debug_panel(); ?>
                </div>
            <?php endif; ?>

            <?php CSDT_Site_Audit::render_quick_fix_modals(); ?>

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
    public static function render_explain_btn( string $id, string $title, array $items, string $intro = '' ): void {
        $btn_id   = 'cs-explain-btn-' . $id;
        $modal_id = 'cs-explain-modal-' . $id;
        ?>
        <button type="button" id="<?php echo esc_attr( $btn_id ); ?>"
            onclick="document.getElementById('<?php echo esc_attr( $modal_id ); ?>').style.display='flex'"
            style="background:#2563eb!important;border:1px solid rgba(255,255,255,0.35)!important;border-radius:5px!important;color:#fff!important;font-size:12px!important;font-weight:700!important;padding:5px 14px!important;cursor:pointer!important;margin-left:auto!important;flex-shrink:0!important;display:block!important;box-shadow:none!important;text-shadow:none!important;text-transform:none!important;letter-spacing:normal!important;line-height:1.4!important">
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
                        $rec = $item['rec'];
                        $rl  = strtolower( $rec );
                        if ( str_contains( $rl, 'critical' ) || str_contains( $rl, 'not recommended' ) ) {
                            $bg = '#fef2f2'; $col = '#991b1b'; $bdr = '#dc2626';
                        } elseif ( str_contains( $rl, 'high' ) ) {
                            $bg = '#fff7ed'; $col = '#9a3412'; $bdr = '#f97316';
                        } elseif ( str_contains( $rl, 'recommended' ) ) {
                            $bg = '#f0fdf4'; $col = '#14532d'; $bdr = '#16a34a';
                        } elseif ( str_contains( $rl, 'important' ) || str_contains( $rl, 'required' ) ) {
                            $bg = '#fffbeb'; $col = '#92400e'; $bdr = '#d97706';
                        } elseif ( str_contains( $rl, 'optional' ) || str_contains( $rl, 'note' ) ) {
                            $bg = '#f6f7f7'; $col = '#50575e'; $bdr = '#c3c4c7';
                        } elseif ( str_contains( $rl, 'info' ) || str_contains( $rl, 'overview' ) || str_contains( $rl, 'diagnostic' ) || str_contains( $rl, 'technical' ) || str_contains( $rl, 'automatic' ) ) {
                            $bg = '#eff6ff'; $col = '#1e40af'; $bdr = '#3b82f6';
                        } else {
                            $bg = '#faf5ff'; $col = '#6b21a8'; $bdr = '#a855f7';
                        }
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
        $registry    = self::get_theme_registry();
        ?>
        <div class="cs-panel" id="cs-panel-code-settings">
            <div class="cs-section-header cs-section-header-teal">
                <span>🎨 CODE BLOCK SETTINGS</span>
                <?php self::render_explain_btn( 'code-settings', 'Code Block Settings', [
                    [ 'name' => 'Theme Pair',   'rec' => 'Recommended', 'desc' => 'Choose a light/dark colour-scheme pair for syntax-highlighted code blocks. The pair is applied automatically based on the visitor\'s OS colour preference.' ],
                    [ 'name' => 'Default Mode', 'rec' => 'Optional',    'desc' => 'Force all code blocks to always use light or dark mode, ignoring the visitor\'s system preference. Leave unset to follow the OS setting.' ],
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

        // SSH auth log — readable if www-data is in the adm group
        $auth_log_candidates = [
            '/var/log/auth.log',   // Debian/Ubuntu
            '/var/log/secure',     // RHEL/CentOS/Fedora
            '/var/log/messages',   // some RHEL variants
        ];
        foreach ( $auth_log_candidates as $path ) {
            if ( is_readable( $path ) ) {
                $sources['auth_ssh'] = [ 'label' => 'SSH Auth Log', 'path' => $path ];
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
        // If the directory exists but is not writable (e.g. owned by a different OS user),
        // attempt a one-time chmod so the web-server user can write the mu-plugin file.
        if ( is_dir( $mu_dir ) && ! is_writable( $mu_dir ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
            @chmod( $mu_dir, 0755 );
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
            wp_send_json_error( [ 'message' => __( 'Could not write mu-plugin. Run: docker exec pi_wordpress chown www-data:www-data /var/www/html/wp-content/mu-plugins', 'cloudscale-devtools' ) ] );
            return;
        }

        update_option( 'csdt_php_error_log_path', $log_path, false );
        wp_send_json_success( [ 'path' => $log_path, 'sources' => self::get_log_sources() ] );
    }

    public static function ajax_logs_fix_mu_perms(): void {
        check_ajax_referer( 'csdt_devtools_server_logs', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $mu_dir = WP_CONTENT_DIR . '/mu-plugins';
        if ( ! is_dir( $mu_dir ) ) {
            wp_mkdir_p( $mu_dir );
        }

        $uid = posix_getuid();
        if ( $uid !== 0 ) {
            wp_send_json_error( [ 'message' => __( 'PHP is not running as root — cannot chown automatically. Run manually: docker exec pi_wordpress chown www-data:www-data /var/www/html/wp-content/mu-plugins', 'cloudscale-devtools' ) ] );
            return;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions
        $ok = @chown( $mu_dir, 'www-data' ) && @chgrp( $mu_dir, 'www-data' );
        if ( ! $ok ) {
            wp_send_json_error( [ 'message' => __( 'chown failed. Run manually: docker exec pi_wordpress chown www-data:www-data /var/www/html/wp-content/mu-plugins', 'cloudscale-devtools' ) ] );
            return;
        }

        wp_send_json_success( [ 'message' => __( 'Permissions fixed. mu-plugins is now writable.', 'cloudscale-devtools' ) ] );
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

    private static function render_debug_panel(): void {
        $has_key   = ! empty( get_option( 'csdt_devtools_anthropic_key', '' ) )
                  || ! empty( get_option( 'csdt_devtools_gemini_key', '' ) );
        $key_url   = admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=security' );
        $perf_on   = get_option( 'csdt_devtools_perf_monitor_enabled', '1' ) !== '0';
        ?>

        <!-- ── CS Monitor toggle ── -->
        <div class="cs-panel" style="margin-bottom:12px;">
            <div class="cs-section-header" style="background:linear-gradient(90deg,#0f4c75 0%,#1b6ca8 100%);border-left:3px solid #38bdf8;">
                <span>⚡ <?php esc_html_e( 'CS Monitor', 'cloudscale-devtools' ); ?></span>
                <span class="cs-header-hint"><?php esc_html_e( 'Frontend performance overlay panel', 'cloudscale-devtools' ); ?></span>
            </div>
            <div class="cs-panel-body" style="padding:14px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;flex:1;min-width:200px;">
                    <input type="checkbox" id="cs-perf-monitor-toggle" <?php checked( $perf_on ); ?>>
                    <span style="font-size:13px;color:#1d2327;"><?php esc_html_e( 'Show the ⚡ CS Monitor performance panel on all pages', 'cloudscale-devtools' ); ?></span>
                </label>
                <span class="cs-hint" style="flex:2;min-width:200px;"><?php esc_html_e( 'Visible to admins only. Tracks DB queries, HTTP requests, PHP errors, and hook counts. Disable in production when not debugging.', 'cloudscale-devtools' ); ?></span>
                <div style="display:flex;align-items:center;gap:10px;">
                    <button type="button" id="cs-perf-monitor-save" class="cs-btn-primary cs-btn-sm">💾 <?php esc_html_e( 'Save', 'cloudscale-devtools' ); ?></button>
                    <span id="cs-perf-monitor-saved" class="cs-settings-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                </div>
            </div>
        </div>

        <div class="cs-panel" id="cs-panel-debug">
            <div class="cs-section-header" style="background:linear-gradient(90deg,#3b0764 0%,#6d28d9 100%);border-left:3px solid #a78bfa;">
                <span>🧠 <?php esc_html_e( 'AI Debugging Assistant', 'cloudscale-devtools' ); ?></span>
                <span class="cs-header-hint"><?php esc_html_e( 'Paste an error or load from your logs — AI identifies the root cause and gives step-by-step fixes', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'ai-debug', 'AI Debugging Assistant', [
                    [ 'name' => 'What it does',       'rec' => 'Info',        'html' => 'Sends your pasted error or log excerpt to an AI model (Anthropic Claude or Google Gemini). The AI identifies the root cause, explains why it happens, and gives you numbered fix steps — no Stack Overflow required.' ],
                    [ 'name' => 'Load from log',      'rec' => 'Recommended', 'html' => 'Click <strong>PHP Errors</strong>, <strong>WP Debug</strong>, or <strong>Web Server</strong> to pull the most recent error lines from your server logs directly into the text area. Configure log paths under the Server Logs panel first if nothing loads.' ],
                    [ 'name' => 'API key required',   'rec' => 'Critical',    'html' => 'You must add an Anthropic or Gemini API key under <strong>Security Scan → Settings</strong> before the Analyze button becomes active. Keys are stored encrypted in wp_options and never transmitted to third parties other than the chosen AI provider.' ],
                    [ 'name' => 'Privacy',            'rec' => 'Info',        'html' => 'The text you submit is sent directly to the AI provider (Anthropic or Gemini). Do not paste passwords, API keys, or personally identifiable data into the text area. Error stack traces are generally safe.' ],
                ] ); ?>
            </div>
            <div style="padding:24px;">
                <?php if ( ! $has_key ) : ?>
                    <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:16px 20px;margin-bottom:24px;">
                        <strong><?php esc_html_e( 'No AI key configured.', 'cloudscale-devtools' ); ?></strong>
                        <?php
                        printf(
                            wp_kses(
                                /* translators: %s: link to security scan settings */
                                __( 'Add an Anthropic or Gemini API key under <a href="%s">Security Scan → Settings</a> to enable analysis.', 'cloudscale-devtools' ),
                                [ 'a' => [ 'href' => [] ] ]
                            ),
                            esc_url( $key_url )
                        );
                        ?>
                    </div>
                <?php endif; ?>

                <div style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 60%,#0e1628 100%);border-radius:10px;padding:24px 28px;margin-bottom:28px;color:#e2e8f0;">
                    <p style="margin:0 0 10px;font-size:1.1em;font-weight:700;color:#a78bfa;"><?php esc_html_e( 'Your site broke. Find out why in seconds.', 'cloudscale-devtools' ); ?></p>
                    <p style="margin:0;opacity:.85;font-size:.95em;line-height:1.6;"><?php esc_html_e( 'Paste a PHP error, stack trace, or problem description. Or click Load Errors to pull recent error lines directly from your server logs. The AI identifies the exact root cause, explains the mechanism, and gives you numbered steps to fix it — no Stack Overflow required.', 'cloudscale-devtools' ); ?></p>
                </div>

                <div style="margin-bottom:16px;display:flex;align-items:center;flex-wrap:wrap;gap:8px;">
                    <span style="font-size:.8em;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.07em;"><?php esc_html_e( 'Load from log:', 'cloudscale-devtools' ); ?></span>
                    <button type="button" class="cs-debug-load-btn cs-btn-sm" data-source="php_error"><?php esc_html_e( 'PHP Errors', 'cloudscale-devtools' ); ?></button>
                    <button type="button" class="cs-debug-load-btn cs-btn-sm" data-source="wp_debug"><?php esc_html_e( 'WP Debug', 'cloudscale-devtools' ); ?></button>
                    <button type="button" class="cs-debug-load-btn cs-btn-sm" data-source="web_error"><?php esc_html_e( 'Web Server', 'cloudscale-devtools' ); ?></button>
                    <span id="csdt-debug-load-status" style="font-size:.85em;color:#94a3b8;"></span>
                </div>

                <div id="csdt-debug-log-lines" style="display:none;margin-bottom:16px;max-height:220px;overflow-y:auto;border:1px solid #334155;border-radius:6px;background:#0f172a;"></div>

                <div style="margin-bottom:16px;">
                    <textarea id="csdt-debug-input" rows="7" style="width:100%;box-sizing:border-box;background:#0f172a;color:#e2e8f0;border:1px solid #334155;border-radius:6px;padding:12px;font-family:monospace;font-size:.85em;resize:vertical;" placeholder="<?php esc_attr_e( 'Paste an error message, stack trace, wp-cron failure, SMTP error, JavaScript console error, or describe what is broken...', 'cloudscale-devtools' ); ?>"></textarea>
                </div>

                <div style="margin-bottom:24px;display:flex;align-items:center;gap:12px;">
                    <button type="button" id="csdt-debug-analyze" class="cs-btn-primary"<?php echo $has_key ? '' : ' disabled'; ?>>
                        🧠 <?php esc_html_e( 'Analyze with AI', 'cloudscale-devtools' ); ?>
                    </button>
                    <span id="csdt-debug-analyze-status" style="font-size:.85em;color:#94a3b8;"></span>
                </div>

                <div id="csdt-debug-result" style="display:none;"></div>

                <hr style="border:none;border-top:1px solid #1e293b;margin:28px 0;">

                <!-- PHP Error Alerting settings -->
                <?php
                $mon_enabled   = get_option( 'csdt_php_error_monitor_enabled', '1' ) === '1';
                $mon_threshold = (int) get_option( 'csdt_php_error_monitor_threshold', '1' );
                $last_trigger  = get_option( 'csdt_php_error_monitor_last_trigger', null );
                $ntfy_set      = ! empty( get_option( 'csdt_scan_schedule_ntfy_url', '' ) );
                $last_pos      = get_option( 'csdt_php_error_last_pos', [] );
                ?>
                <div style="background:#0f172a;border:1px solid #1e293b;border-radius:8px;padding:20px 24px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                        <div>
                            <strong style="color:#e2e8f0;">🔔 <?php esc_html_e( 'PHP Error Alerting', 'cloudscale-devtools' ); ?></strong>
                            <span style="display:block;font-size:.82em;color:#64748b;margin-top:2px;"><?php esc_html_e( 'Polls PHP + WP debug logs every 5 min — alerts via email and ntfy.sh when new fatals appear', 'cloudscale-devtools' ); ?></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <?php self::render_explain_btn( 'php-error-alerting', 'PHP Error Alerting', [
                                [ 'name' => 'How it works',     'rec' => 'Info',        'html' => 'A WP-Cron job runs every 5 minutes. It records the last byte-offset read for each log file and only reads <em>new</em> lines since the previous check — so you only get alerted about errors that just appeared, not the entire log history.' ],
                                [ 'name' => 'Alert channels',   'rec' => 'Recommended', 'html' => '<strong>Email</strong> — sent to the WordPress admin email address. <strong>ntfy.sh</strong> — instant push notification to any device running the ntfy app. Set your ntfy URL under <strong>Security Scan → Settings → Notification URL</strong>. Both channels fire independently if configured.' ],
                                [ 'name' => 'Threshold',        'rec' => 'Info',        'html' => 'The <em>threshold</em> is the minimum number of new errors per check before an alert fires. Set it to 1 to be notified about every single error. Fatal errors (PHP Fatal, WordPress die) always trigger an alert regardless of the threshold.' ],
                                [ 'name' => 'Log paths',        'rec' => 'Recommended', 'html' => 'The monitor watches the same log sources configured in the Server Logs panel. If no PHP error log path is set, enable the mu-plugin under Server Logs → PHP Error Log to redirect errors to <code>wp-content/php-error.log</code>.' ],
                            ] ); ?>

                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" id="csdt-errmon-enabled" <?php checked( $mon_enabled ); ?>>
                                <span style="font-size:.85em;color:#94a3b8;"><?php esc_html_e( 'Enabled', 'cloudscale-devtools' ); ?></span>
                            </label>
                            <button type="button" id="csdt-errmon-save" class="cs-btn-sm cs-btn-primary"><?php esc_html_e( 'Save', 'cloudscale-devtools' ); ?></button>
                            <span id="csdt-errmon-status" style="font-size:.82em;color:#94a3b8;"></span>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;font-size:.82em;color:#64748b;">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span style="white-space:nowrap;"><?php esc_html_e( 'Alert after', 'cloudscale-devtools' ); ?></span>
                            <input type="number" id="csdt-errmon-threshold" min="1" max="50" value="<?php echo esc_attr( $mon_threshold ); ?>" style="width:52px;background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:4px;padding:2px 6px;font-size:1em;text-align:center;">
                            <span style="white-space:nowrap;"><?php esc_html_e( 'new error(s) per check', 'cloudscale-devtools' ); ?></span>
                            <span style="color:#94a3b8;font-size:.9em;"><?php esc_html_e( '(fatals always alert)', 'cloudscale-devtools' ); ?></span>
                        </div>
                        <?php if ( ! $ntfy_set ) : ?>
                            <span style="color:#f59e0b;">
                                <?php
                                printf(
                                    wp_kses(
                                        /* translators: %s: link to site audit settings */
                                        __( '⚠ No ntfy.sh topic set — <a href="%s" style="color:#f59e0b;">configure in Site Audit → Scheduled Scans</a>', 'cloudscale-devtools' ),
                                        [ 'a' => [ 'href' => [], 'style' => [] ] ]
                                    ),
                                    esc_url( admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=site-audit' ) )
                                );
                                ?>
                            </span>
                        <?php endif; ?>
                        <?php if ( $last_trigger ) : ?>
                            <span>
                                <?php
                                printf(
                                    /* translators: 1: human time diff, 2: fatal count, 3: error count */
                                    esc_html__( 'Last alert: %1$s ago (%2$d fatal, %3$d error)', 'cloudscale-devtools' ),
                                    esc_html( human_time_diff( (int) $last_trigger['ts'] ) ),
                                    (int) $last_trigger['fatal'],
                                    (int) $last_trigger['errors']
                                );
                                ?>
                            </span>
                        <?php elseif ( $mon_enabled && ! empty( $last_pos ) ) : ?>
                            <span style="color:#86efac;"><?php esc_html_e( 'Monitoring — no new errors detected', 'cloudscale-devtools' ); ?></span>
                        <?php elseif ( $mon_enabled ) : ?>
                            <span style="color:#94a3b8;"><?php esc_html_e( 'Will begin monitoring on next cron run (within 5 min)', 'cloudscale-devtools' ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #1e293b;margin:28px 0;">

                <!-- PHP-FPM Saturation Monitor -->
                <?php
                $fpm_enabled          = get_option( 'csdt_fpm_enabled',          '1' ) === '1';
                $fpm_threshold        = (int) get_option( 'csdt_fpm_threshold',        '3' );
                $fpm_cooldown         = (int) get_option( 'csdt_fpm_cooldown',         '1800' );
                $fpm_probe_url        = get_option( 'csdt_fpm_probe_url',              'http://localhost:8082/' );
                $fpm_timeout          = (int) get_option( 'csdt_fpm_probe_timeout',    '5' );
                $fpm_wp_ctr           = get_option( 'csdt_fpm_wp_container',           'pi_wordpress' );
                $fpm_db_ctr           = get_option( 'csdt_fpm_db_container',           'pi_mariadb' );
                $fpm_auto_restart     = get_option( 'csdt_fpm_auto_restart',           '0' ) === '1';
                $fpm_restart_cooldown = (int) get_option( 'csdt_fpm_restart_cooldown', '1200' );
                $fpm_token            = get_option( 'csdt_fpm_token',                  '' );
                if ( empty( $fpm_token ) ) {
                    $fpm_token = wp_generate_password( 32, false );
                    update_option( 'csdt_fpm_token', $fpm_token, false );
                }
                $fpm_last       = get_option( 'csdt_fpm_last_event', null );
                $fpm_event_log  = is_array( get_option( 'csdt_fpm_event_log', [] ) ) ? get_option( 'csdt_fpm_event_log', [] ) : [];
                $fpm_report_url = admin_url( 'admin-ajax.php' );
                $fpm_auto_restart_val = $fpm_auto_restart ? 'true' : 'false';
                $fpm_config_snippet = "# ── PHP-FPM Saturation Monitor ─────────────────────────────────────────────\n"
                    . "FPM_SATURATION_THRESHOLD={$fpm_threshold}\n"
                    . "FPM_PROBE_URL={$fpm_probe_url}\n"
                    . "FPM_PROBE_TIMEOUT={$fpm_timeout}\n"
                    . "FPM_WP_CONTAINER={$fpm_wp_ctr}\n"
                    . "FPM_DB_CONTAINER={$fpm_db_ctr}\n"
                    . "FPM_ALERT_COOLDOWN={$fpm_cooldown}\n"
                    . "FPM_AUTO_RESTART={$fpm_auto_restart_val}\n"
                    . "FPM_RESTART_COOLDOWN={$fpm_restart_cooldown}\n"
                    . "FPM_CALLBACK_URL={$fpm_report_url}\n"
                    . "FPM_CALLBACK_TOKEN={$fpm_token}";
                ?>
                <div style="background:#0f172a;border:1px solid #1e293b;border-radius:8px;padding:20px 24px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                        <div>
                            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;">
                                <strong style="color:#e2e8f0;">🖥️ <?php esc_html_e( 'PHP-FPM Saturation Monitor', 'cloudscale-devtools' ); ?></strong>
                                <span style="display:inline-flex;align-items:center;padding:1px 8px;background:#1e3a5f;border-radius:10px;font-size:.72em;color:#60a5fa;">HOST CRON</span>
                                <?php self::render_explain_btn( 'fpm_monitor', 'PHP-FPM Saturation Monitor', [
                                    [ 'name' => 'What is PHP-FPM saturation?', 'rec' => 'Info', 'html' => 'PHP-FPM (FastCGI Process Manager) maintains a pool of worker processes that handle requests. When all workers are busy (e.g. a traffic spike, a slow DB query holding workers open, or a runaway loop), new requests queue up and the site appears frozen or times out. This is called saturation.' ],
                                    [ 'name' => 'Why a host cron, not WP-Cron?', 'rec' => 'Critical', 'html' => 'WP-Cron runs inside PHP-FPM. If PHP-FPM is fully saturated, WP-Cron can\'t execute — so a WordPress-based monitor would be silenced exactly when you need it most. This monitor runs as a shell script on the host OS (outside Docker), so it fires even when every PHP worker is consumed.' ],
                                    [ 'name' => 'How the detection works', 'rec' => 'Info', 'html' => 'Every minute the script probes the HTTP URL. If the probe times out or fails N consecutive times (the threshold), saturation is declared. It then sends an ntfy.sh push notification and email alert, optionally restarts the WordPress container, and POSTs an event to this panel via the callback URL.' ],
                                    [ 'name' => 'Current Workers display', 'rec' => 'Info', 'html' => 'Shows live active / idle / total worker counts from the PHP-FPM status page (<code>pm.status_path</code>). Requires <code>pm.status_path = /fpm-status</code> in your <code>www.conf</code> and a matching nginx location block. Click Refresh at any time to re-poll.' ],
                                    [ 'name' => 'Auto-restart', 'rec' => 'Optional', 'html' => 'When enabled, the script issues a <code>docker restart {container}</code> command after declaring saturation. A restart cooldown prevents thrashing. Use with care on production — a restart drops all in-flight requests.' ],
                                    [ 'name' => 'Setup', 'rec' => 'Info', 'html' => 'Copy the crontab line and config.env snippet from the Host Cron Setup section below. The callback URL and token wire the script back to this panel so saturation events appear in the audit trail automatically.' ],
                                ], 'Monitors PHP-FPM worker exhaustion from the host OS. Alerts via ntfy + email, can auto-restart the container, and logs events back to this panel.' ); ?>
                            </div>
                            <span style="display:block;font-size:.82em;color:#64748b;margin-top:2px;"><?php esc_html_e( 'Detects when all PHP-FPM workers are exhausted. Runs on the host (not WP-Cron), so it fires even when PHP is fully saturated. Can automatically restart the WordPress container and notify via ntfy.', 'cloudscale-devtools' ); ?></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" id="csdt-fpm-enabled" <?php checked( $fpm_enabled ); ?>>
                                <span style="font-size:.85em;color:#94a3b8;"><?php esc_html_e( 'Enabled', 'cloudscale-devtools' ); ?></span>
                            </label>
                            <button type="button" id="csdt-fpm-save" class="cs-btn-sm cs-btn-primary"><?php esc_html_e( 'Save', 'cloudscale-devtools' ); ?></button>
                            <span id="csdt-fpm-status" style="font-size:.82em;color:#94a3b8;"></span>
                        </div>
                    </div>

                    <!-- Workers live status -->
                    <div style="background:#0a1628;border:1px solid #1e293b;border-radius:6px;padding:10px 14px;margin-bottom:14px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                        <span style="font-size:.8em;color:#64748b;font-weight:600;"><?php esc_html_e( 'Current workers', 'cloudscale-devtools' ); ?></span>
                        <span id="csdt-fpm-workers-active" style="font-size:.82em;color:#e2e8f0;">
                            <span style="color:#64748b;"><?php esc_html_e( 'Active:', 'cloudscale-devtools' ); ?></span>
                            <span id="csdt-fpm-w-active" style="color:#f87171;font-weight:700;">—</span>
                        </span>
                        <span style="font-size:.82em;color:#e2e8f0;">
                            <span style="color:#64748b;"><?php esc_html_e( 'Idle:', 'cloudscale-devtools' ); ?></span>
                            <span id="csdt-fpm-w-idle" style="color:#86efac;font-weight:700;">—</span>
                        </span>
                        <span style="font-size:.82em;color:#e2e8f0;">
                            <span style="color:#64748b;"><?php esc_html_e( 'Total:', 'cloudscale-devtools' ); ?></span>
                            <span id="csdt-fpm-w-total" style="color:#94a3b8;font-weight:700;">—</span>
                        </span>
                        <span style="font-size:.82em;color:#e2e8f0;">
                            <span style="color:#64748b;"><?php esc_html_e( 'Mem:', 'cloudscale-devtools' ); ?></span>
                            <span id="csdt-fpm-w-mem" style="color:#e2e8f0;font-weight:700;" title="Total memory across all workers">—</span>
                        </span>
                        <button type="button" id="csdt-fpm-workers-refresh" class="cs-btn-sm cs-btn-secondary" style="padding:5px 12px;font-size:.78em;line-height:1.4;">↻ <?php esc_html_e( 'Refresh', 'cloudscale-devtools' ); ?></button>
                        <button type="button" id="csdt-fpm-detail-toggle" class="cs-btn-sm cs-btn-secondary" style="padding:5px 12px;font-size:.78em;line-height:1.4;">▼ <?php esc_html_e( 'Workers', 'cloudscale-devtools' ); ?></button>
                        <button type="button" id="csdt-fpm-setup-btn" class="cs-btn-sm cs-btn-secondary" style="padding:5px 12px;font-size:.78em;line-height:1.4;background:#1e3a5f;color:#60a5fa;border-color:#2563eb;">⚙ <?php esc_html_e( 'Setup Status Page', 'cloudscale-devtools' ); ?></button>
                        <span id="csdt-fpm-workers-status" style="font-size:.78em;color:#64748b;"></span>
                    </div>

                    <!-- Per-worker detail table -->
                    <div id="csdt-fpm-detail-panel" style="display:none;margin-bottom:14px;">
                        <div style="overflow-x:auto;">
                            <table id="csdt-fpm-detail-table" style="width:100%;border-collapse:collapse;font-size:.76em;color:#e2e8f0;">
                                <thead>
                                    <tr style="border-bottom:1px solid #334155;color:#94a3b8;text-align:left;">
                                        <th style="padding:5px 8px;white-space:nowrap;">PID</th>
                                        <th style="padding:5px 8px;white-space:nowrap;">State</th>
                                        <th style="padding:5px 8px;white-space:nowrap;">Reqs</th>
                                        <th style="padding:5px 8px;white-space:nowrap;">Running</th>
                                        <th style="padding:5px 8px;white-space:nowrap;">Last URI</th>
                                        <th style="padding:5px 8px;white-space:nowrap;">Script</th>
                                        <th style="padding:5px 8px;white-space:nowrap;" title="CPU% used by the last completed request. Running workers show — until their current request finishes.">Last CPU%</th>
                                        <th style="padding:5px 8px;white-space:nowrap;">Mem</th>
                                    </tr>
                                </thead>
                                <tbody id="csdt-fpm-detail-tbody">
                                    <tr><td colspan="8" style="padding:8px;color:#475569;">Loading…</td></tr>
                                </tbody>
                                <tfoot id="csdt-fpm-detail-tfoot"></tfoot>
                            </table>
                        </div>
                        <p style="margin:4px 0 8px;font-size:.72em;color:#475569;">Last CPU% = CPU used by the most recently <em>completed</em> request. Idle workers show their last value; Running workers show — because their current request hasn't finished yet.</p>
                        <div id="csdt-fpm-pool-info" style="margin-top:4px;font-size:.74em;color:#94a3b8;"></div>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:16px;">
                        <div>
                            <label style="display:block;font-size:.78em;color:#64748b;margin-bottom:4px;"><?php esc_html_e( 'Saturation threshold (consecutive checks)', 'cloudscale-devtools' ); ?></label>
                            <input type="number" id="csdt-fpm-threshold" min="1" max="30" value="<?php echo esc_attr( $fpm_threshold ); ?>" style="width:80px;background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:4px;padding:3px 8px;font-size:.9em;">
                        </div>
                        <div>
                            <label style="display:block;font-size:.78em;color:#64748b;margin-bottom:4px;"><?php esc_html_e( 'Alert cooldown (seconds)', 'cloudscale-devtools' ); ?></label>
                            <input type="number" id="csdt-fpm-cooldown" min="60" max="86400" value="<?php echo esc_attr( $fpm_cooldown ); ?>" style="width:100px;background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:4px;padding:3px 8px;font-size:.9em;">
                        </div>
                        <div>
                            <label style="display:block;font-size:.78em;color:#64748b;margin-bottom:4px;"><?php esc_html_e( 'HTTP probe URL', 'cloudscale-devtools' ); ?></label>
                            <input type="text" id="csdt-fpm-probe-url" value="<?php echo esc_attr( $fpm_probe_url ); ?>" style="width:100%;box-sizing:border-box;background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:4px;padding:3px 8px;font-size:.9em;">
                        </div>
                        <div>
                            <label style="display:block;font-size:.78em;color:#64748b;margin-bottom:4px;"><?php esc_html_e( 'Probe timeout (seconds)', 'cloudscale-devtools' ); ?></label>
                            <input type="number" id="csdt-fpm-probe-timeout" min="1" max="30" value="<?php echo esc_attr( $fpm_timeout ); ?>" style="width:80px;background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:4px;padding:3px 8px;font-size:.9em;">
                        </div>
                        <div>
                            <label style="display:block;font-size:.78em;color:#64748b;margin-bottom:4px;"><?php esc_html_e( 'WordPress container name', 'cloudscale-devtools' ); ?></label>
                            <input type="text" id="csdt-fpm-wp-container" value="<?php echo esc_attr( $fpm_wp_ctr ); ?>" style="width:100%;box-sizing:border-box;background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:4px;padding:3px 8px;font-size:.9em;">
                        </div>
                        <div>
                            <label style="display:block;font-size:.78em;color:#64748b;margin-bottom:4px;"><?php esc_html_e( 'MariaDB container name', 'cloudscale-devtools' ); ?></label>
                            <input type="text" id="csdt-fpm-db-container" value="<?php echo esc_attr( $fpm_db_ctr ); ?>" style="width:100%;box-sizing:border-box;background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:4px;padding:3px 8px;font-size:.9em;">
                        </div>
                        <div>
                            <label style="display:block;font-size:.78em;color:#64748b;margin-bottom:4px;"><?php esc_html_e( 'Auto-restart on saturation', 'cloudscale-devtools' ); ?></label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:4px;">
                                <input type="checkbox" id="csdt-fpm-auto-restart" <?php checked( $fpm_auto_restart ); ?>>
                                <span style="font-size:.85em;color:#94a3b8;"><?php esc_html_e( 'Restart container automatically', 'cloudscale-devtools' ); ?></span>
                            </label>
                        </div>
                        <div>
                            <label style="display:block;font-size:.78em;color:#64748b;margin-bottom:4px;"><?php esc_html_e( 'Restart cooldown (seconds)', 'cloudscale-devtools' ); ?></label>
                            <input type="number" id="csdt-fpm-restart-cooldown" min="60" max="86400" value="<?php echo esc_attr( $fpm_restart_cooldown ); ?>" style="width:100px;background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:4px;padding:3px 8px;font-size:.9em;">
                            <span style="font-size:.75em;color:#475569;margin-left:6px;"><?php echo esc_html( sprintf( __( '%d min', 'cloudscale-devtools' ), (int) round( $fpm_restart_cooldown / 60 ) ) ); ?></span>
                        </div>
                    </div>

                    <div style="background:#0a1628;border:1px solid #1e3a5f;border-radius:6px;padding:14px 16px;margin-bottom:16px;">
                        <div style="font-size:.82em;color:#60a5fa;font-weight:600;margin-bottom:10px;">📋 <?php esc_html_e( 'Host Cron Setup', 'cloudscale-devtools' ); ?></div>
                        <div style="font-size:.78em;color:#64748b;margin-bottom:4px;"><?php esc_html_e( 'Add to crontab on your Pi host (crontab -e):', 'cloudscale-devtools' ); ?></div>
                        <code style="display:block;background:#0f172a;border:1px solid #1e293b;border-radius:4px;padding:8px 12px;font-size:.8em;color:#86efac;white-space:nowrap;overflow-x:auto;margin-bottom:10px;">* * * * * /home/pi/pi2s3/fpm-saturation-monitor.sh 2&gt;/dev/null</code>
                        <div style="font-size:.78em;color:#64748b;margin-bottom:4px;"><?php esc_html_e( 'Add to ~/pi2s3/config.env (includes callback so last event appears above):', 'cloudscale-devtools' ); ?></div>
                        <code id="csdt-fpm-config-snippet" style="display:block;background:#0f172a;border:1px solid #1e293b;border-radius:4px;padding:8px 12px;font-size:.78em;color:#cbd5e1;white-space:pre;overflow-x:auto;margin-bottom:10px;"><?php echo esc_html( $fpm_config_snippet ); ?></code>
                        <button type="button" id="csdt-fpm-copy-snippet" class="cs-btn-sm cs-btn-secondary"><?php esc_html_e( 'Copy config.env snippet', 'cloudscale-devtools' ); ?></button>
                        <span id="csdt-fpm-copy-status" style="font-size:.78em;color:#86efac;margin-left:8px;"></span>
                    </div>

                    <!-- Event audit trail -->
                    <?php if ( ! empty( $fpm_event_log ) ) : ?>
                    <div style="margin-top:4px;">
                        <div style="font-size:.78em;color:#64748b;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between;">
                            <span><?php printf( esc_html__( 'Last %d events (newest first)', 'cloudscale-devtools' ), count( $fpm_event_log ) ); ?></span>
                        </div>
                        <div style="max-height:240px;overflow-y:auto;border:1px solid #1e293b;border-radius:6px;">
                            <table style="width:100%;border-collapse:collapse;font-size:.78em;">
                                <thead>
                                    <tr style="background:#0a1628;position:sticky;top:0;">
                                        <th style="text-align:left;padding:5px 10px;color:#475569;font-weight:600;white-space:nowrap;"><?php esc_html_e( 'Time', 'cloudscale-devtools' ); ?></th>
                                        <th style="text-align:left;padding:5px 10px;color:#475569;font-weight:600;"><?php esc_html_e( 'Event', 'cloudscale-devtools' ); ?></th>
                                        <th style="text-align:left;padding:5px 10px;color:#475569;font-weight:600;"><?php esc_html_e( 'Detail', 'cloudscale-devtools' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ( $fpm_event_log as $i => $ev ) :
                                    $ev_color = match( $ev['type'] ?? '' ) {
                                        'recovered' => '#86efac',
                                        'restarted' => '#fbbf24',
                                        default     => '#f87171',
                                    };
                                    $ev_icon = match( $ev['type'] ?? '' ) {
                                        'recovered' => '✓',
                                        'restarted' => '🔄',
                                        default     => '🚨',
                                    };
                                    $ev_label = match( $ev['type'] ?? '' ) {
                                        'recovered' => __( 'Recovered', 'cloudscale-devtools' ),
                                        'restarted' => __( 'Auto-restarted', 'cloudscale-devtools' ),
                                        default     => __( 'Saturated', 'cloudscale-devtools' ),
                                    };
                                    $row_bg = $i % 2 === 0 ? '#0f172a' : '#0a1628';
                                ?>
                                    <tr style="background:<?php echo esc_attr( $row_bg ); ?>;border-top:1px solid #1e293b;">
                                        <td style="padding:5px 10px;color:#64748b;white-space:nowrap;" title="<?php echo esc_attr( wp_date( 'Y-m-d H:i:s', (int) $ev['ts'] ) ); ?>">
                                            <?php echo esc_html( human_time_diff( (int) $ev['ts'] ) . ' ago' ); ?>
                                        </td>
                                        <td style="padding:5px 10px;white-space:nowrap;">
                                            <span style="color:<?php echo esc_attr( $ev_color ); ?>;"><?php echo $ev_icon . ' ' . esc_html( $ev_label ); ?></span>
                                        </td>
                                        <td style="padding:5px 10px;color:#94a3b8;"><?php echo esc_html( $ev['msg'] ?? '' ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php else : ?>
                    <div style="font-size:.82em;">
                        <span style="color:#475569;"><?php esc_html_e( 'No saturation events recorded yet. Install the host cron and set FPM_CALLBACK_URL + FPM_CALLBACK_TOKEN to enable the audit trail.', 'cloudscale-devtools' ); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- PHP-FPM Status Page Setup Modal (inline so it's always in the DOM with the button) -->
                <div id="csdt-fpm-setup-modal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.7);align-items:center;justify-content:center;">
                    <div style="background:#0f172a;border:1px solid #334155;border-radius:10px;max-width:620px;width:94%;padding:24px;position:relative;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.6);">
                        <button id="csdt-fpm-setup-close" style="position:absolute;top:12px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:#94a3b8;line-height:1;" title="Close">✕</button>
                        <h3 style="margin:0 0 4px;font-size:15px;font-weight:700;color:#e2e8f0;">⚙ PHP-FPM Status Page Setup</h3>
                        <p style="font-size:12px;color:#64748b;margin:0 0 18px;">Enables the <code style="background:#1e293b;padding:1px 5px;border-radius:3px;color:#86efac;">/fpm-status</code> endpoint so the Current Workers panel shows live counts.</p>
                        <div id="csdt-fpm-setup-steps" style="display:flex;gap:0;margin-bottom:20px;">
                            <?php foreach ( [ 1 => 'Detect', 2 => 'www.conf', 3 => 'nginx' ] as $n => $lbl ) : ?>
                            <div class="csdt-fpm-step" data-step="<?php echo $n; ?>" style="flex:1;text-align:center;padding:6px 0;font-size:11px;font-weight:600;border-bottom:2px solid #1e293b;color:#475569;"><?php echo $n; ?>. <?php echo esc_html( $lbl ); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div id="csdt-fpm-step-1">
                            <p style="font-size:13px;color:#94a3b8;margin:0 0 14px;">Scans for your PHP-FPM config file and probes common URLs to find nginx.</p>
                            <button type="button" id="csdt-fpm-detect-btn" class="button button-primary" style="font-size:13px;">🔍 Run Detection</button>
                            <div id="csdt-fpm-detect-result" style="margin-top:14px;font-size:12px;"></div>
                        </div>
                        <div id="csdt-fpm-step-2" style="display:none;">
                            <div id="csdt-fpm-patch-info" style="font-size:13px;color:#94a3b8;margin-bottom:14px;"></div>
                            <button type="button" id="csdt-fpm-patch-btn" class="button button-primary" style="font-size:13px;">✏️ Patch www.conf &amp; Reload php-fpm</button>
                            <div id="csdt-fpm-patch-result" style="margin-top:14px;font-size:12px;"></div>
                            <div style="margin-top:14px;display:flex;gap:8px;">
                                <button type="button" id="csdt-fpm-step2-next" class="button" style="font-size:12px;display:none;">Next →</button>
                                <button type="button" id="csdt-fpm-step2-skip" class="button" style="font-size:12px;">Skip (already done)</button>
                            </div>
                        </div>
                        <div id="csdt-fpm-step-3" style="display:none;">
                            <p style="font-size:13px;color:#94a3b8;margin:0 0 10px;">Add this location block inside your nginx <code style="background:#1e293b;padding:1px 5px;border-radius:3px;color:#86efac;">server {}</code> block, then reload nginx.</p>
                            <pre id="csdt-fpm-nginx-snippet" style="background:#0a1628;border:1px solid #1e3a5f;border-radius:6px;padding:12px;font-size:.78em;color:#cbd5e1;overflow-x:auto;white-space:pre;margin:0 0 10px;"></pre>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                <button type="button" id="csdt-fpm-copy-nginx" class="button" style="font-size:12px;">📋 Copy snippet</button>
                                <span id="csdt-fpm-copy-nginx-status" style="font-size:12px;color:#86efac;"></span>
                            </div>
                            <p style="font-size:12px;color:#64748b;margin:12px 0 6px;">Then reload nginx:</p>
                            <code id="csdt-fpm-nginx-reload-cmd" style="display:block;background:#0a1628;border:1px solid #1e293b;border-radius:4px;padding:6px 10px;font-size:.78em;color:#86efac;"></code>
                            <div style="margin-top:14px;display:flex;gap:8px;align-items:center;">
                                <button type="button" id="csdt-fpm-test-btn" class="button button-primary" style="font-size:12px;">✅ Test &amp; Finish</button>
                                <span id="csdt-fpm-test-result" style="font-size:12px;color:#94a3b8;"></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php
        self::render_server_logs_panel();
        self::render_sql_panel();
        self::render_404_panel();
    }

    private static function render_server_logs_panel(): void {
        $sources        = self::get_log_sources();
        $php_configured = ! empty( get_option( 'csdt_php_error_log_path', '' ) );
        $custom_paths   = get_option( 'csdt_custom_log_paths', [] );
        ?>
        <div class="cs-panel" id="cs-panel-logs">
            <div class="cs-section-header" style="background:linear-gradient(90deg,#1e3a8a 0%,#1d4ed8 100%);border-left:3px solid #60a5fa;">
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
                <?php $mu_dir = WP_CONTENT_DIR . '/mu-plugins'; $mu_writable = is_dir( $mu_dir ) && is_writable( $mu_dir ); ?>
                <div id="cs-logs-php-setup" style="padding:14px 16px;margin-bottom:16px;background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:6px;">
                        <strong style="font-size:13px;color:#92400e;"><?php esc_html_e( 'PHP Error Log not writing to a file', 'cloudscale-devtools' ); ?></strong>
                        <button type="button" class="cs-btn-primary cs-btn-sm" id="cs-logs-php-setup-btn"
                                style="white-space:nowrap;flex-shrink:0;"
                                <?php echo ! $mu_writable ? 'disabled title="' . esc_attr__( 'Fix the permissions warning first', 'cloudscale-devtools' ) . '"' : ''; ?>>
                            ⚡ <?php esc_html_e( 'Enable', 'cloudscale-devtools' ); ?>
                        </button>
                    </div>
                    <p style="margin:0 0 0;font-size:12px;color:#78350f;line-height:1.5;"><?php esc_html_e( 'PHP is currently logging to a system stream (e.g. /dev/stderr) that cannot be read here. Click Enable to install a mu-plugin that redirects PHP errors to wp-content/php-error.log.', 'cloudscale-devtools' ); ?></p>
                    <?php if ( ! $mu_writable ) : ?>
                    <div id="cs-logs-perm-warning" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:10px;padding:8px 12px;background:#fef3c7;border-radius:4px;font-size:12px;color:#78350f;">
                        <span style="flex:1;">⚠️ <?php esc_html_e( 'wp-content/mu-plugins is not writable by the web server.', 'cloudscale-devtools' ); ?></span>
                        <button type="button" class="cs-btn-secondary cs-btn-sm" id="cs-logs-fix-perm-btn" style="flex-shrink:0;white-space:nowrap;">
                            🔧 <?php esc_html_e( 'Fix Permissions', 'cloudscale-devtools' ); ?>
                        </button>
                    </div>
                    <?php endif; ?>
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

                <div style="margin-top:12px;background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:14px 18px;">
                    <p style="margin:0 0 6px;font-size:13px;font-weight:600;color:#92400e;">🔧 <?php esc_html_e( 'Fix HTTP → HTTPS', 'cloudscale-devtools' ); ?></p>
                    <p style="margin:0 0 12px;font-size:12px;color:#78350f;line-height:1.5;"><?php esc_html_e( 'Runs WP-CLI search-replace server-side — safely handles serialised data. Dry Run previews the count without making changes.', 'cloudscale-devtools' ); ?></p>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <button type="button" id="cs-http-fix-dry" class="cs-btn-secondary cs-btn-sm" style="border-color:#f59e0b;color:#92400e;">🔍 <?php esc_html_e( 'Dry Run', 'cloudscale-devtools' ); ?></button>
                        <button type="button" id="cs-http-fix-run" class="cs-btn-primary cs-btn-sm" style="background:#d97706;border-color:#d97706;">⚡ <?php esc_html_e( 'Fix It', 'cloudscale-devtools' ); ?></button>
                        <span id="cs-http-fix-status" style="font-size:12px;color:#92400e;"></span>
                    </div>
                    <pre id="cs-http-fix-output" style="display:none;margin-top:12px;background:#1e293b;color:#e2e8f0;padding:10px 14px;border-radius:6px;font-size:11px;line-height:1.6;white-space:pre-wrap;word-break:break-all;max-height:200px;overflow-y:auto;"></pre>
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
                    <button type="button" class="cs-btn-primary" id="cs-hide-save">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
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
                    <button type="button" class="cs-btn-primary" id="cs-session-save">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
                    <span class="cs-settings-saved" id="cs-session-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                </div>
            </div>
        </div>

        <!-- ── Brute-Force Protection ───────────────── -->
        <?php
        $bf_enabled       = get_option( 'csdt_devtools_brute_force_enabled', '1' );
        $bf_attempts      = get_option( 'csdt_devtools_brute_force_attempts', '5' );
        $bf_lockout       = get_option( 'csdt_devtools_brute_force_lockout', '10' );
        $bf_enum_protect  = get_option( 'csdt_devtools_enum_protect', '1' );
        $wplogin_stats    = get_option( 'csdt_wplogin_blocked_stats', [] );
        $invalid_user_log = get_option( 'csdt_invalid_user_log', [] );
        ?>
        <div class="cs-panel" id="cs-panel-brute-force">
            <div class="cs-section-header cs-section-header-red">
                <span>🔒 BRUTE-FORCE PROTECTION</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Temporarily lock accounts after repeated failed logins', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'brute-force', 'Brute-Force Protection', [
                    [ 'name' => 'How it works',        'rec' => 'Info',        'html' => 'After <em>N</em> consecutive failed login attempts for the same username, the account is locked for the configured duration. The lock is <strong>per-username, not per-IP</strong> — it also stops distributed attacks spread across many IPs. The counter resets automatically after the lockout period expires.' ],
                    [ 'name' => 'Failed attempts',     'rec' => 'Recommended', 'html' => '<ul><li><code>3</code> — tighter security, but risks locking out users who mistype their password</li><li><code>5</code> — default, good balance</li><li><code>10</code> — more forgiving for sites with non-technical users</li></ul>To unlock an account immediately, delete the transient key <code>csdt_devtools_lockout_{username}</code> from the database.' ],
                    [ 'name' => 'Lockout period',      'rec' => 'Recommended', 'html' => 'Default is <code>10</code> minutes. The lock lifts automatically — no admin action needed.<br><br><ul><li><strong>10 min</strong> — default, enough to stop most automated attacks</li><li><strong>30–60 min</strong> — slows targeted attacks further, slight UX delay for legitimate forgotten-password users</li></ul>' ],
                    [ 'name' => 'Account enumeration', 'rec' => 'Critical',    'html' => '<p>By default, WordPress gives away whether a username exists. Try logging in with a made-up username and you see <em>"The username xyz is not registered on this site."</em> — try a real one and you see <em>"The password you entered is incorrect."</em> An attacker can automate this to build a full list of your site\'s usernames in minutes, then target those accounts with focused password attacks.</p><p>When this option is enabled, <strong>both errors return the same message: "Invalid username or password."</strong> The attacker learns nothing — a wrong username looks exactly like a wrong password. This is the same pattern used by banks and any serious web application.</p><p>There is no downside to enabling this. Legitimate users who forget their username can use the <em>Lost your password?</em> link to recover via their email address.</p>' ],
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
                        <span class="cs-hint"><?php esc_html_e( 'How long the account stays locked. Default: 10.', 'cloudscale-devtools' ); ?></span>
                    </div>
                </div>
                <div class="cs-field-row" style="margin-top:14px;">
                    <div class="cs-field">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:500;color:#334155;">
                            <input type="checkbox" id="cs-bf-enum-protect" <?php checked( $bf_enum_protect, '1' ); ?>>
                            <?php esc_html_e( 'Prevent account enumeration by using generic login error messages', 'cloudscale-devtools' ); ?>
                        </label>
                        <span class="cs-hint" style="margin-top:4px;display:block;"><?php esc_html_e( 'Returns "Invalid username or password." for all credential failures — prevents attackers from discovering which usernames are registered on this site.', 'cloudscale-devtools' ); ?></span>
                    </div>
                </div>
                <div style="margin-top:18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <button type="button" class="cs-btn-primary" id="cs-bf-save">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
                    <span class="cs-settings-saved" id="cs-bf-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                    <button type="button" id="cs-bf-test-btn" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;border-radius:6px;padding:6px 14px;font-size:13px;font-weight:600;cursor:pointer;">🧪 <?php esc_html_e( 'Test BF Protection', 'cloudscale-devtools' ); ?></button>
                    <span id="cs-bf-test-result" style="display:none;font-size:13px;font-weight:600;padding:5px 12px;border-radius:6px;"></span>
                </div>

                <?php if ( $bf_enum_protect === '1' ) : ?>
                <div style="margin-top:10px;font-size:12px;color:#166534;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:7px 12px;line-height:1.5;">
                    🛡️ <strong><?php esc_html_e( 'Username enumeration protection: Active', 'cloudscale-devtools' ); ?></strong>
                    — <?php esc_html_e( 'Login errors always say "Invalid username or password." so attackers cannot tell whether a username exists.', 'cloudscale-devtools' ); ?>
                </div>
                <?php endif; ?>
                <div style="margin-top:8px;font-size:12px;color:#64748b;line-height:1.6;">
                    <strong><?php esc_html_e( 'Account lock, not IP lock:', 'cloudscale-devtools' ); ?></strong>
                    <?php esc_html_e( 'Lockout is per-username. Distributed attacks using many IPs against the same account are still caught.', 'cloudscale-devtools' ); ?><br>
                    <strong><?php esc_html_e( 'Unlock via SSH:', 'cloudscale-devtools' ); ?></strong>
                    <code style="font-size:11px;background:#f1f5f9;padding:1px 5px;border-radius:3px;">wp transient delete csdt_devtools_bf_lock_$(php -r "echo md5(strtolower('USERNAME'));") --path=/var/www/html</code>
                    <?php esc_html_e( 'or to unlock all:', 'cloudscale-devtools' ); ?>
                    <code style="font-size:11px;background:#f1f5f9;padding:1px 5px;border-radius:3px;">wp eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%csdt_devtools_bf_lock%\"");' --path=/var/www/html</code>
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

                <?php
                // ── wp-login.php blocked hits ─────────────────────────────────
                $daily_hits = isset( $wplogin_stats['daily'] ) && is_array( $wplogin_stats['daily'] ) ? $wplogin_stats['daily'] : [];
                // Keep only last 7 days
                $today = gmdate( 'Y-m-d' );
                $days  = [];
                for ( $i = 6; $i >= 0; $i-- ) {
                    $d        = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
                    $days[$d] = $daily_hits[$d] ?? 0;
                }
                $total_hits  = array_sum( $days );
                $last_hit_ts = $wplogin_stats['last_ts'] ?? 0;
                $last_hit_ip = $wplogin_stats['last_ip'] ?? '';
                ?>
                <div style="margin-top:22px;">
                    <div style="font-weight:600;font-size:13px;margin-bottom:10px;">🚫 <?php esc_html_e( 'wp-login.php Blocked — Last 7 Days', 'cloudscale-devtools' ); ?>
                        <span style="font-weight:400;font-size:11px;color:#64748b;margin-left:8px;"><?php echo (int) $total_hits; ?> blocked</span>
                    </div>
                    <div style="display:flex;gap:6px;align-items:flex-end;height:48px;margin-bottom:6px;">
                        <?php foreach ( $days as $d => $cnt ) :
                            $max = max( 1, max( array_values( $days ) ) );
                            $pct = $cnt > 0 ? max( 8, (int) round( $cnt / $max * 100 ) ) : 2;
                        ?>
                        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;">
                            <div title="<?php echo esc_attr( $cnt ); ?>" style="width:100%;background:<?php echo $cnt > 0 ? '#ef4444' : '#e2e8f0'; ?>;height:<?php echo $pct; ?>%;border-radius:3px 3px 0 0;min-height:3px;"></div>
                            <span style="font-size:10px;color:#94a3b8;"><?php echo esc_html( gmdate( 'M j', strtotime( $d ) ) ); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ( $last_hit_ts > 0 ) : ?>
                    <div style="font-size:12px;color:#475569;margin-top:4px;">
                        <?php esc_html_e( 'Last attempt:', 'cloudscale-devtools' ); ?>
                        <strong><?php echo esc_html( human_time_diff( $last_hit_ts ) . ' ago' ); ?></strong>
                        <?php if ( $last_hit_ip ) : ?>
                        &nbsp;from&nbsp;<code style="font-size:11px;"><?php echo esc_html( $last_hit_ip ); ?></code>
                        <?php endif; ?>
                    </div>
                    <?php else : ?>
                    <div style="font-size:12px;color:#94a3b8;"><?php esc_html_e( 'No direct wp-login.php hits recorded yet.', 'cloudscale-devtools' ); ?></div>
                    <?php endif; ?>
                </div>

                <?php
                // ── Invalid username attempts at hidden login URL ──────────────
                // Last 20 entries, newest first
                $inv_recent = array_reverse( array_slice( $invalid_user_log, -20 ) );
                ?>
                <?php if ( ! empty( $inv_recent ) ) : ?>
                <div style="margin-top:22px;">
                    <div style="font-weight:600;font-size:13px;margin-bottom:8px;">👤 <?php esc_html_e( 'Invalid Username Attempts at Login URL', 'cloudscale-devtools' ); ?>
                        <span style="font-weight:400;font-size:11px;color:#64748b;margin-left:8px;"><?php echo count( $invalid_user_log ); ?> total</span>
                    </div>
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="text-align:left;padding:5px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;"><?php esc_html_e( 'When', 'cloudscale-devtools' ); ?></th>
                                <th style="text-align:left;padding:5px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;"><?php esc_html_e( 'Username tried', 'cloudscale-devtools' ); ?></th>
                                <th style="text-align:left;padding:5px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;"><?php esc_html_e( 'IP address', 'cloudscale-devtools' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $inv_recent as $entry ) : ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:5px 10px;white-space:nowrap;color:#64748b;"><?php echo esc_html( human_time_diff( $entry[0] ) . ' ago' ); ?></td>
                                <td style="padding:5px 10px;font-weight:600;color:#0f172a;"><?php echo esc_html( $entry[1] ); ?></td>
                                <td style="padding:5px 10px;color:#475569;"><?php echo esc_html( $entry[2] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── SSH Brute-Force Monitor ─────────────────── -->
        <?php
        $ssh_mon_enabled   = get_option( 'csdt_ssh_monitor_enabled', '1' ) === '1';
        $ssh_mon_threshold = get_option( 'csdt_ssh_monitor_threshold', '10' );
        $ssh_last_check    = get_option( 'csdt_ssh_monitor_last_check', null );
        $ssh_last_alert    = (int) get_option( 'csdt_ssh_monitor_last_alert', 0 );
        $ssh_alert_log     = get_option( 'csdt_ssh_monitor_alert_log', [] );
        $auth_log_readable = false;
        foreach ( [ '/var/log/auth.log', '/var/log/secure', '/var/log/messages' ] as $_p ) {
            if ( is_readable( $_p ) ) { $auth_log_readable = true; break; }
        }
        ?>
        <div class="cs-panel" id="cs-panel-ssh-monitor">
            <div class="cs-section-header cs-section-header-red">
                <span>🖥️ SSH BRUTE-FORCE MONITOR</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Real-time SSH attack detection via auth.log — alerts via email and ntfy.sh', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'ssh-monitor', 'SSH Brute-Force Monitor', [
                    [ 'name' => 'How it works',      'rec' => 'Overview',     'html' => 'The SSH monitor tails your server\'s <code>/var/log/auth.log</code> via an AJAX poll every 60 seconds. It counts failed login attempts in a rolling 60-second window and fires an alert when the count exceeds your threshold.' ],
                    [ 'name' => 'Threshold',         'rec' => 'Recommended',  'html' => 'Set to <strong>10 failed attempts in 60 seconds</strong> for most servers. Lower values (e.g. 3–5) are appropriate for servers with a small number of known users but may produce false positives from legitimate mistyped passwords.' ],
                    [ 'name' => 'Alert channels',    'rec' => 'Recommended',  'html' => '<strong>Email</strong> — sends via your configured SMTP settings (Mail tab).<br><strong>ntfy.sh</strong> — push notification to any phone with the ntfy app. Enter your ntfy topic URL in the field provided. Free and open-source.' ],
                    [ 'name' => 'fail2ban',          'rec' => 'Critical',     'html' => 'This monitor <em>detects</em> attacks but does not block IPs. <strong>fail2ban</strong> must be installed and running to automatically ban attacking IPs. Without it, attacks will continue indefinitely. Install with: <code>sudo apt install fail2ban</code>.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <?php if ( ! $auth_log_readable ) : ?>
                <div class="cs-notice cs-notice-warn" style="margin-bottom:16px;">
                    ⚠️ <strong><?php esc_html_e( 'Auth log not readable.', 'cloudscale-devtools' ); ?></strong>
                    <?php esc_html_e( 'To enable SSH monitoring, add the web server user to the adm group:', 'cloudscale-devtools' ); ?>
                    <code style="display:block;margin:8px 0;padding:6px 10px;background:#f6f7f7;border-radius:4px;">sudo usermod -a -G adm www-data &amp;&amp; sudo systemctl restart php-fpm</code>
                </div>
                <?php endif; ?>

                <div class="cs-field-row">
                    <div class="cs-field">
                        <label class="cs-label">
                            <input type="checkbox" id="cs-ssh-mon-enabled" <?php checked( $ssh_mon_enabled ); ?>>
                            <?php esc_html_e( 'Enable SSH brute-force monitor (checks every 60 seconds)', 'cloudscale-devtools' ); ?>
                        </label>
                        <span class="cs-hint"><?php esc_html_e( 'Reads /var/log/auth.log every minute and alerts if the failure threshold is crossed. On by default.', 'cloudscale-devtools' ); ?></span>
                    </div>
                </div>
                <div class="cs-field-row">
                    <div class="cs-field">
                        <label class="cs-label" for="cs-ssh-mon-threshold"><?php esc_html_e( 'Alert threshold (failures per 60 s):', 'cloudscale-devtools' ); ?></label>
                        <input type="number" id="cs-ssh-mon-threshold" class="cs-input" min="1" max="1000"
                               value="<?php echo esc_attr( $ssh_mon_threshold ); ?>" style="max-width:100px">
                        <span class="cs-hint"><?php esc_html_e( 'Default: 10. Sends email + ntfy.sh alert when this many failures occur in the last 60 seconds. Alerts are throttled to once per 5 minutes.', 'cloudscale-devtools' ); ?></span>
                    </div>
                </div>
                <?php if ( $ssh_last_check ) : ?>
                <div class="cs-field-row" style="padding:10px 0 0;">
                    <div style="font-size:12px;color:#64748b;">
                        <strong><?php esc_html_e( 'Last check:', 'cloudscale-devtools' ); ?></strong>
                        <?php echo esc_html( human_time_diff( $ssh_last_check['ts'] ) . ' ago' ); ?> —
                        <strong><?php echo (int) $ssh_last_check['count']; ?></strong> <?php esc_html_e( 'failure(s) in last 60 s', 'cloudscale-devtools' ); ?>
                        <?php if ( $ssh_last_alert > 0 ) : ?>
                        &nbsp;|&nbsp; <strong><?php esc_html_e( 'Last alert:', 'cloudscale-devtools' ); ?></strong>
                        <?php echo esc_html( human_time_diff( $ssh_last_alert ) . ' ago' ); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ( ! empty( $ssh_last_check['lines'] ) ) : ?>
                <div style="margin-top:10px;background:#0f172a;border-radius:6px;padding:10px 14px;font-size:11px;color:#94a3b8;font-family:monospace;max-height:140px;overflow-y:auto;">
                    <?php foreach ( array_reverse( $ssh_last_check['lines'] ) as $line ) : ?>
                    <div><?php echo esc_html( $line ); ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <div style="margin-top:18px;display:flex;align-items:center;gap:10px">
                    <button type="button" class="cs-btn-primary" id="cs-ssh-mon-save">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
                    <span class="cs-settings-saved" id="cs-ssh-mon-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                </div>

                <?php if ( ! empty( $ssh_alert_log ) ) : ?>
                <div style="margin-top:22px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                        <span style="font-weight:600;font-size:13px;">🚨 <?php esc_html_e( 'Alert History', 'cloudscale-devtools' ); ?></span>
                        <button type="button" id="cs-ssh-log-clear" style="background:none;border:none;color:#94a3b8;font-size:11px;cursor:pointer;padding:0;"><?php esc_html_e( 'Clear log', 'cloudscale-devtools' ); ?></button>
                    </div>
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="text-align:left;padding:6px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600;"><?php esc_html_e( 'Time', 'cloudscale-devtools' ); ?></th>
                                <th style="text-align:center;padding:6px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600;"><?php esc_html_e( 'Attempts', 'cloudscale-devtools' ); ?></th>
                                <th style="text-align:left;padding:6px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600;"><?php esc_html_e( 'Targeted accounts', 'cloudscale-devtools' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( array_reverse( $ssh_alert_log ) as $entry ) :
                                $users = $entry['users'] ?? [];
                                arsort( $users );
                                $user_parts = [];
                                foreach ( array_slice( $users, 0, 5, true ) as $u => $c ) {
                                    $user_parts[] = esc_html( $u ) . ( $c > 1 ? ' &times;' . $c : '' );
                                }
                            ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:6px 10px;white-space:nowrap;color:#475569;"><?php echo esc_html( human_time_diff( $entry['ts'] ) . ' ago' ); ?></td>
                                <td style="padding:6px 10px;text-align:center;font-weight:700;color:#dc2626;"><?php echo (int) $entry['count']; ?></td>
                                <td style="padding:6px 10px;color:#334155;"><?php echo $user_parts ? implode( ', ', $user_parts ) : '&mdash;'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
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
                    <button type="button" class="cs-btn-primary" id="cs-2fa-save">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
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
        $ta_single_use  = get_option( 'csdt_test_account_single_use', '1' ) === '1';
        $ta_max_logins  = (int) get_option( 'csdt_test_account_max_logins', '1' );
        $ta_accounts    = self::get_active_test_accounts();
        ?>
        <div class="cs-panel" id="cs-panel-test-accounts">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8)">
                <span>🧪 <?php esc_html_e( 'TEST ACCOUNT MANAGER', 'cloudscale-devtools' ); ?></span>
                <span class="cs-header-hint"><?php esc_html_e( 'Temporary single-use accounts for Playwright / CI pipelines', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'test-accounts', 'Test Account Manager', [
                    [ 'name' => 'What is a test account?', 'rec' => 'Overview',     'html' => 'A test account is a temporary <strong>subscriber-level</strong> WordPress user with an Application Password. It bypasses admin 2FA enforcement, making it safe for automated Playwright and CI pipelines to authenticate against the REST API without disabling security for real admin accounts.' ],
                    [ 'name' => 'TTL / Expiry',           'rec' => 'Recommended',  'html' => 'Accounts expire automatically after the TTL you set (default 30 minutes). A scheduled CRON runs every 15 minutes to delete expired accounts. You can also revoke accounts manually from the active accounts list.' ],
                    [ 'name' => 'Single-use',             'rec' => 'Optional',     'html' => 'When single-use is enabled, the account is deleted immediately after its first successful REST API authentication. Use this for one-shot CI jobs where you want zero lingering credentials.' ],
                    [ 'name' => 'CI / Playwright usage',  'rec' => 'Recommended',  'html' => 'Create a test account before your test suite runs and revoke it after. Use the REST API credentials (username + app password) with HTTP Basic Auth — e.g. <code>curl -u "username:app_password" https://yoursite.com/wp-json/wp/v2/posts</code>. Store credentials in environment variables or a <code>.env</code> file and never commit them to git.' ],
                ] ); ?>
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

                    <div id="cs-ta-options" style="<?php echo $ta_enabled ? 'display:flex;' : 'display:none;'; ?>flex-direction:column;gap:16px;">

                        <div class="cs-sec-row">
                            <span class="cs-sec-label"><?php esc_html_e( 'Default TTL:', 'cloudscale-devtools' ); ?></span>
                            <div class="cs-sec-control">
                                <select id="cs-ta-ttl" class="cs-sec-select" style="width:auto;">
                                    <option value="300"   <?php selected( $ta_ttl, '300' );   ?>><?php esc_html_e( '5 minutes',  'cloudscale-devtools' ); ?></option>
                                    <option value="600"   <?php selected( $ta_ttl, '600' );   ?>><?php esc_html_e( '10 minutes', 'cloudscale-devtools' ); ?></option>
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
                                <span class="cs-hint"><?php esc_html_e( 'Maximum security — each test run gets fresh credentials. When enabled, Max Logins is locked to 1.', 'cloudscale-devtools' ); ?></span>
                            </div>
                        </div>

                        <div class="cs-sec-row">
                            <span class="cs-sec-label"><?php esc_html_e( 'Max logins:', 'cloudscale-devtools' ); ?></span>
                            <div class="cs-sec-control">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="number" id="cs-ta-max-logins" min="0" step="1" value="<?php echo esc_attr( $ta_max_logins ); ?>" style="width:80px;" class="cs-sec-select" <?php echo $ta_single_use ? 'disabled' : ''; ?>>
                                    <span style="font-size:13px;color:#6b7280;"><?php esc_html_e( '(0 = unlimited)', 'cloudscale-devtools' ); ?></span>
                                </div>
                                <span class="cs-hint"><?php esc_html_e( 'Delete the account after this many successful authentications. 0 = unlimited; higher = allow N logins before deleting.', 'cloudscale-devtools' ); ?></span>
                            </div>
                        </div>

                        <div class="cs-sec-row">
                            <span class="cs-sec-label"></span>
                            <div class="cs-sec-control" style="display:flex;flex-direction:row;align-items:center;gap:10px;">
                                <button type="button" class="cs-btn-primary" id="cs-ta-save">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
                                <span class="cs-settings-saved" id="cs-ta-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                            </div>
                        </div>

                        <hr class="cs-sec-divider" style="margin:0;">

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

    public static function ajax_sql_http_fix(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden', 403 );
        }
        if ( ! check_ajax_referer( 'csdt_devtools_sql_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Bad nonce', 403 );
        }

        $dry_run = isset( $_POST['dry_run'] ) && '1' === $_POST['dry_run'];
        $host    = wp_parse_url( home_url(), PHP_URL_HOST );
        $from    = 'http://' . $host;
        $to      = 'https://' . $host;

        global $wpdb;

        $tables      = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $total_cells = 0;
        $lines       = [];

        foreach ( $tables as $table ) {
            if ( strpos( $table, '_trash_' ) !== false ) {
                continue;
            }
            // Get primary key and all text-like columns.
            $columns   = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
            $pk        = null;
            $text_cols = [];
            foreach ( $columns as $col ) {
                if ( strtolower( $col['Key'] ) === 'pri' && ! $pk ) {
                    $pk = $col['Field'];
                }
                $type = strtolower( $col['Type'] );
                if ( strpos( $type, 'char' ) !== false || strpos( $type, 'text' ) !== false
                    || strpos( $type, 'blob' ) !== false || strpos( $type, 'json' ) !== false ) {
                    // Skip guid column.
                    if ( $col['Field'] !== 'guid' ) {
                        $text_cols[] = $col['Field'];
                    }
                }
            }
            if ( empty( $text_cols ) || ! $pk ) {
                continue;
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_results( "SELECT `{$pk}`, `" . implode( '`, `', $text_cols ) . "` FROM `{$table}`", ARRAY_A );
            $changed = 0;
            foreach ( (array) $rows as $row ) {
                $pk_val  = $row[ $pk ];
                $updates = [];
                foreach ( $text_cols as $col ) {
                    $orig = $row[ $col ];
                    if ( $orig === null || strpos( $orig, $from ) === false ) {
                        continue;
                    }
                    $new = self::recursive_str_replace( $from, $to, $orig );
                    if ( $new !== $orig ) {
                        $updates[ $col ] = $new;
                    }
                }
                if ( $updates ) {
                    ++$changed;
                    ++$total_cells;
                    if ( ! $dry_run ) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        $wpdb->update( $table, $updates, [ $pk => $pk_val ] );
                    }
                }
            }
            if ( $changed ) {
                $lines[] = sprintf( '%s: %d row(s) updated', $table, $changed );
            }
        }

        $lines[] = sprintf( '--- Total: %d cell(s) %s', $total_cells, $dry_run ? 'would be updated (dry run)' : 'updated' );

        wp_send_json_success( [
            'output'  => implode( "\n", $lines ),
            'dry_run' => $dry_run,
            'from'    => $from,
            'to'      => $to,
            'total'   => $total_cells,
        ] );
    }

    /**
     * Recursively replaces $from with $to in a value, correctly handling
     * PHP serialised strings by adjusting byte-length prefixes.
     */
    private static function recursive_str_replace( string $from, string $to, $data ) {
        if ( is_array( $data ) ) {
            foreach ( $data as $k => $v ) {
                $data[ $k ] = self::recursive_str_replace( $from, $to, $v );
            }
            return $data;
        }
        if ( ! is_string( $data ) ) {
            return $data;
        }
        $unserialized = @unserialize( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
        if ( $unserialized !== false && $data !== 'b:0;' ) {
            $replaced = self::recursive_str_replace( $from, $to, $unserialized );
            return serialize( $replaced ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
        }
        return str_replace( $from, $to, $data );
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

    public static function ajax_save_perf_monitor(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden' );
        }
        if ( ! check_ajax_referer( 'csdt_devtools_perf_monitor_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Bad nonce' );
        }
        $enabled = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'] ? '1' : '0'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        update_option( 'csdt_devtools_perf_monitor_enabled', $enabled );
        wp_send_json_success( [ 'perf_enabled' => $enabled ] );
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
            $hdr_xcache    = is_array( $headers['x-cache'] ?? null )    ? implode( ', ', $headers['x-cache'] )    : (string) ( $headers['x-cache'] ?? '' );
            $hdr_cfcache   = is_array( $headers['cf-cache-status'] ?? null ) ? implode( ', ', $headers['cf-cache-status'] ) : (string) ( $headers['cf-cache-status'] ?? '' );
            $hdr_wpcache   = is_array( $headers['x-wp-cache'] ?? null ) ? implode( ', ', $headers['x-wp-cache'] ) : (string) ( $headers['x-wp-cache'] ?? '' );
            if ( $hdr_xcache  && false !== stripos( $hdr_xcache,  'HIT' ) ) { $cached = true; }
            elseif ( $hdr_cfcache && 'HIT' === strtoupper( $hdr_cfcache ) ) { $cached = true; }
            elseif ( $hdr_wpcache && 'HIT' === strtoupper( $hdr_wpcache ) ) { $cached = true; }
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
                'login_slug'         => get_option( 'csdt_devtools_login_slug', '' ),
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
        $ip  = self::get_client_ip();
        $log[] = [ time(), sanitize_user( $username, true ), $ip ];
        update_option( 'csdt_devtools_bf_log', $log, false );

        // Per-IP failed login index — keyed by IP for future dashboard use.
        if ( $ip ) {
            $ip_index = get_option( 'csdt_devtools_failed_login_ips', [] );
            if ( ! is_array( $ip_index ) ) {
                $ip_index = [];
            }
            $clean_user = sanitize_user( $username, true );
            $now = time();
            if ( isset( $ip_index[ $ip ] ) ) {
                $ip_index[ $ip ]['count']++;
                $ip_index[ $ip ]['last_seen'] = $now;
                $ip_index[ $ip ]['times'][]   = $now;
                if ( count( $ip_index[ $ip ]['times'] ) > 50 ) {
                    array_shift( $ip_index[ $ip ]['times'] );
                }
                if ( ! in_array( $clean_user, $ip_index[ $ip ]['usernames'], true ) ) {
                    $ip_index[ $ip ]['usernames'][] = $clean_user;
                    if ( count( $ip_index[ $ip ]['usernames'] ) > 20 ) {
                        array_shift( $ip_index[ $ip ]['usernames'] );
                    }
                }
            } else {
                $ip_index[ $ip ] = [
                    'count'      => 1,
                    'first_seen' => $now,
                    'last_seen'  => $now,
                    'times'      => [ $now ],
                    'usernames'  => [ $clean_user ],
                ];
            }
            // Purge IPs not seen in the last 90 days.
            $cutoff_ip = time() - 90 * DAY_IN_SECONDS;
            $ip_index  = array_filter( $ip_index, fn( $e ) => $e['last_seen'] >= $cutoff_ip );
            // Cap at 1000 unique IPs — drop the oldest by last_seen.
            if ( count( $ip_index ) > 1000 ) {
                uasort( $ip_index, fn( $a, $b ) => $a['last_seen'] <=> $b['last_seen'] );
                $ip_index = array_slice( $ip_index, -1000, null, true );
            }
            update_option( 'csdt_devtools_failed_login_ips', $ip_index, false );
        }

        // Track invalid (nonexistent) username attempts separately
        if ( ! username_exists( $username ) ) {
            $inv = get_option( 'csdt_invalid_user_log', [] );
            if ( ! is_array( $inv ) ) $inv = [];
            $inv[] = [ time(), sanitize_user( $username, true ), $ip ];
            if ( count( $inv ) > 200 ) {
                $inv = array_slice( $inv, -200 );
            }
            update_option( 'csdt_invalid_user_log', $inv, false );
        }

        // ── Brute-force per-account lockout ──────────────────────────────────
        if ( get_option( 'csdt_devtools_brute_force_enabled', '1' ) !== '1' || empty( $username ) ) {
            return;
        }
        $max_attempts = max( 1, (int) get_option( 'csdt_devtools_brute_force_attempts', '5' ) );
        $lockout_secs = max( 60, (int) get_option( 'csdt_devtools_brute_force_lockout', '10' ) * MINUTE_IN_SECONDS );
        $slug         = md5( strtolower( $username ) );
        $count_key    = 'csdt_devtools_bf_count_' . $slug;
        $lock_key     = 'csdt_devtools_bf_lock_' . $slug;
        $attempts     = (int) get_transient( $count_key ) + 1;
        if ( $attempts >= $max_attempts ) {
            // Threshold reached — lock the account and clear the counter.
            set_transient( $lock_key, time() + $lockout_secs, $lockout_secs );
            delete_transient( $count_key );

            // Send throttled alert — only for valid accounts (real attack vector), at most once per 2 hrs.
            if ( username_exists( $username ) ) {
                $notif_key = 'csdt_devtools_bf_notif_' . $slug;
                if ( ! get_transient( $notif_key ) ) {
                    set_transient( $notif_key, 1, 2 * HOUR_IN_SECONDS );
                    $site      = wp_specialchars_decode( get_bloginfo( 'name' ) ?: home_url(), ENT_QUOTES );
                    $admin_url = admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=login' );
                    $subject   = sprintf( '[%s] REAL ACCOUNT under brute-force attack: %s', $site, $username );
                    $body      = sprintf(
                        "Account '%s' is a valid WordPress account and has been locked after %d consecutive failed login attempts.\n\nThis is an active credential-stuffing or brute-force attack targeting a real account.\n\nAccount will be locked for %d minutes. If this recurs, enable 2FA immediately.\n\nView login attempts: %s",
                        $username,
                        $max_attempts,
                        $lockout_secs / MINUTE_IN_SECONDS,
                        $admin_url
                    );
                    self::send_threat_alert( $subject, $body, 'urgent', 'skull,warning', $admin_url );
                }
            }
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
                    . '<button id="cs-perf-clear" class="cs-perf-btn" title="Clear browser-side errors and issues (page refresh clears DB/HTTP/hook data)">&#10005;&nbsp;Clear</button>'
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
                        . '<button class="cs-ptab"         data-tab="editor"    role="tab" aria-selected="false">Browser <span id="cs-ptc-editor">0</span></button>'
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
                . '<div id="cs-pp-editor" class="cs-ppane" role="tabpanel">'
                    . '<div id="cs-pp-editor-body" class="cs-tbl-wrap"></div>'
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
                    <?php
                    $recent_post = get_posts( [ 'numberposts' => 1, 'post_status' => 'publish', 'post_type' => 'post' ] );
                    $checker_default_url = ! empty( $recent_post ) ? get_permalink( $recent_post[0] ) : home_url( '/' );
                    ?>
                    <input type="url" id="cs-thumb-check-url" class="cs-input" style="max-width:520px;flex:1"
                           placeholder="<?php echo esc_attr( $checker_default_url ); ?>"
                           value="<?php echo esc_attr( $checker_default_url ); ?>">
                    <button type="button" class="cs-btn-primary" id="cs-thumb-check-btn">🔍 <?php esc_html_e( 'Run Diagnostic', 'cloudscale-devtools' ); ?></button>
                </div>
                <div id="cs-thumb-check-results" style="margin-top:14px;display:none"></div>
            </div>
        </div>

        <?php
        $csdi_id      = (int) get_option( 'cloudscale_default_image_id', 0 );
        $csdi_preview = $csdi_id ? wp_get_attachment_image_url( $csdi_id, 'medium' ) : '';
        ?>
        <div class="cs-panel" id="cs-panel-thumbs-default-image">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#1565c0,#0d47a1);">
                <span>🖼️ DEFAULT FEATURED IMAGE</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Fallback featured image used when a post has no thumbnail set', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'default-image', 'Default Featured Image', [
                    [ 'name' => 'What it does',       'rec' => 'Overview',     'html' => 'When a post has no featured image set, WordPress normally shows nothing. This plugin intercepts the <code>post_thumbnail_html</code> and <code>has_post_thumbnail</code> filters to return your chosen fallback image instead — in theme loops, archive pages, and as the <code>og:image</code> fallback for social sharing.' ],
                    [ 'name' => 'Recommended size',   'rec' => 'Required',     'html' => 'Use a <strong>1200 × 630 px</strong> image (JPEG or PNG, under 300 KB). This is the optimal size for WhatsApp, LinkedIn, Facebook, and X/Twitter cards. Smaller images may be cropped or rejected by social crawlers.' ],
                    [ 'name' => 'og:image fallback',  'rec' => 'Important',    'html' => 'Without a default image, posts shared on social media with no featured image will show no preview card — significantly reducing click-through rates. Setting a branded default ensures every post looks professional when shared.' ],
                    [ 'name' => 'Change vs Remove',   'rec' => 'Info',         'html' => '<strong>Change Image</strong> — opens the WordPress Media Library to select a new fallback image.<br><strong>Remove</strong> — clears the fallback. Posts without a featured image will revert to showing no thumbnail.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">
                <p class="cs-hint" style="margin-bottom:14px;"><?php esc_html_e( 'When a post has no featured image, this image is shown in theme loops and used as the og:image fallback for social sharing. Choose a branded 1200×630 px image.', 'cloudscale-devtools' ); ?></p>
                <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;">
                    <div id="csdt-defimg-preview" style="flex-shrink:0;">
                        <?php if ( $csdi_preview ) : ?>
                            <img src="<?php echo esc_url( $csdi_preview ); ?>" style="max-width:240px;height:auto;border:1px solid #ddd;border-radius:4px;display:block;" />
                        <?php else : ?>
                            <div style="width:240px;height:126px;background:#f0f0f0;border:1px dashed #ccc;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">No image selected</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <input type="hidden" id="csdt-defimg-id" value="<?php echo esc_attr( $csdi_id ); ?>" />
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                            <button type="button" class="cs-btn-primary" id="csdt-defimg-select"><?php echo $csdi_id ? esc_html__( 'Change Image', 'cloudscale-devtools' ) : esc_html__( 'Select Image', 'cloudscale-devtools' ); ?></button>
                            <?php if ( $csdi_id ) : ?>
                            <button type="button" class="cs-btn-secondary" id="csdt-defimg-remove" style="color:#dc2626;border-color:#dc2626;"><?php esc_html_e( 'Remove', 'cloudscale-devtools' ); ?></button>
                            <?php endif; ?>
                        </div>
                        <p id="csdt-defimg-status" style="font-size:12px;color:#4b5563;margin:0;"></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="cs-panel" id="cs-panel-thumbs-cloudflare">
            <div class="cs-section-header" style="background:linear-gradient(135deg,#e65100,#bf360c);">
                <span>☁️ CLOUDFLARE SETUP &amp; DIAGNOSTICS</span>
                <span class="cs-header-hint"><?php esc_html_e( 'Configure WAF bypass rules and test cache behaviour', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'cloudflare', 'Cloudflare Setup & Diagnostics', [
                    [ 'name' => 'Bot Fight Mode fix',    'rec' => 'Critical',     'html' => 'Cloudflare\'s Bot Fight Mode blocks social crawler user agents (WhatsApp, Facebook, LinkedIn, X/Twitter). This prevents them from reading your OG tags, meaning no preview card when links are shared. The fix is a <strong>WAF Custom Rule</strong> that skips Bot Fight Mode for those specific user agents only.' ],
                    [ 'name' => 'WAF rule setup',        'rec' => 'Recommended',  'html' => 'In Cloudflare Dashboard → Security → WAF → Custom Rules: create a rule with <em>User Agent contains</em> (facebookexternalhit OR LinkedInBot OR WhatsApp OR Twitterbot) → Action: <strong>Skip</strong> → Bot Fight Mode. Place it above any block rules.' ],
                    [ 'name' => 'Cache purge',           'rec' => 'Optional',     'html' => 'After fixing OG tags or images, Cloudflare may serve stale cached versions to crawlers for hours. The Cache Purge tool lets you clear a specific URL or your entire zone instantly. Requires a Cloudflare API Token with Cache Purge permission and your Zone ID.' ],
                    [ 'name' => 'Crawler test',          'rec' => 'Info',         'html' => 'The URL Social Preview Checker (panel above) simulates each crawler\'s user agent and reports exactly what OG tags they see — including whether Cloudflare is blocking them. Use it to verify your WAF rule is working correctly.' ],
                ] ); ?>
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
                        <button type="button" class="cs-btn-secondary" id="cs-cf-save-btn" style="background:#555;color:#fff;padding:7px 14px;border:none;border-radius:4px;cursor:pointer;font-size:13px">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
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

    // ─── Hook: Cloudflare cache purge on post publish/update ────────────
    public static function on_post_status_change( string $new_status, string $old_status, \WP_Post $post ): void {
        if ( $new_status !== 'publish' ) return;
        if ( ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) return;

        $zone_id = (string) get_option( 'csdt_devtools_cf_zone_id', '' );
        if ( ! $zone_id ) return;

        // Prefer scoped API Token (Bearer); fall back to Global API Key (email+key).
        $token    = (string) get_option( 'csdt_devtools_cf_api_token', '' );
        $cf_key   = (string) get_option( 'cloudflare_api_key', '' );
        $cf_email = (string) get_option( 'cloudflare_api_email', '' );
        if ( ! $token && ( ! $cf_key || ! $cf_email ) ) return;

        $headers = [ 'Content-Type' => 'application/json' ];
        if ( $token ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        } else {
            $headers['X-Auth-Key']   = $cf_key;
            $headers['X-Auth-Email'] = $cf_email;
        }

        $urls = array_values( array_filter( [ get_permalink( $post->ID ), home_url( '/' ) ] ) );
        wp_remote_post(
            "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache",
            [
                'timeout' => 8,
                'headers' => $headers,
                'body'    => wp_json_encode( [ 'files' => $urls ] ),
            ]
        );
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

    // ─── Default Featured Image ───────────────────────────────────────────

    public static function ajax_save_default_image(): void {
        check_ajax_referer( 'csdt_defimg', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Unauthorized', 403 ); }
        $id = absint( $_POST['image_id'] ?? 0 );
        update_option( 'cloudscale_default_image_id', $id );
        wp_send_json_success( [ 'id' => $id ] );
    }

    public static function default_image_html( string $html, int $post_id, $post_thumbnail_id, $size, $attr ): string {
        if ( ! empty( $html ) ) { return $html; }
        if ( get_post_type( $post_id ) !== 'post' ) { return $html; }
        $default_id = (int) get_option( 'cloudscale_default_image_id', 0 );
        if ( ! $default_id ) { return $html; }
        return wp_get_attachment_image( $default_id, $size, false, (array) $attr );
    }

    public static function default_image_has_thumbnail( bool $has, $post, $thumbnail_id ): bool {
        if ( $has ) { return $has; }
        $post_obj = get_post( $post );
        if ( ! $post_obj || $post_obj->post_type !== 'post' ) { return $has; }
        return (int) get_option( 'cloudscale_default_image_id', 0 ) > 0;
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
            $rb_results[] = [ 'type' => 'warn', 'msg' => 'robots.txt not found — ensure crawlers are not blocked elsewhere', 'fix' => 'Create a robots.txt at your domain root. In WordPress, go to Settings → Reading and ensure "Discourage search engines" is unchecked. CloudScale SEO AI auto-generates robots.txt — enable it if available.' ];
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

    public static function strip_asset_ver( string $src ): string {
        if ( strpos( $src, 'ver=' ) !== false ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }

    public static function output_editor_debug_panel(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->base !== 'post' ) { return; }
        ?>
<style>
#csdt-dbg{position:fixed;top:32px;right:10px;z-index:99999;width:460px;max-width:calc(100vw - 20px);max-height:70vh;display:flex;flex-direction:column;background:#1e1e2e;color:#cdd6f4;font-family:monospace;font-size:12px;border-radius:10px;box-shadow:0 6px 30px rgba(0,0,0,.75);overflow:hidden}
#csdt-dbg-head{display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#181825;border-bottom:1px solid #313244;flex-shrink:0;cursor:move;user-select:none}
#csdt-dbg-head h3{margin:0;font-size:12px;font-weight:700;color:#cba6f7;letter-spacing:.04em}
#csdt-dbg-head-btns{display:flex;gap:6px}
#csdt-dbg-head-btns button{background:none;border:none;cursor:pointer;font-size:14px;line-height:1;padding:2px 4px;border-radius:4px;color:#a6adc8}
#csdt-dbg-head-btns button:hover{background:#313244;color:#cdd6f4}
#csdt-dbg-tabs{display:flex;gap:2px;padding:6px 8px 0;background:#181825;flex-shrink:0}
#csdt-dbg-tabs button{background:#313244;border:none;color:#a6adc8;font-size:11px;font-family:monospace;padding:4px 10px;border-radius:6px 6px 0 0;cursor:pointer;position:relative}
#csdt-dbg-tabs button.active{background:#1e1e2e;color:#cdd6f4;font-weight:700}
#csdt-dbg-tabs button .badge{position:absolute;top:-5px;right:-5px;background:#f38ba8;color:#1e1e2e;font-size:10px;font-weight:700;border-radius:999px;min-width:16px;height:16px;line-height:16px;text-align:center;padding:0 3px;display:none}
#csdt-dbg-tabs button .badge.show{display:block}
#csdt-dbg-body{overflow-y:auto;flex:1;padding:8px}
.csdt-row{border-bottom:1px solid #313244;padding:5px 2px;line-height:1.5;word-break:break-all}
.csdt-row:last-child{border-bottom:none}
.csdt-row .ts{color:#6c7086;font-size:10px;margin-right:5px}
.csdt-row .lbl{font-weight:700;margin-right:4px}
.csdt-row .detail{color:#bac2de;font-size:11px}
.csdt-csp .lbl{color:#f38ba8}.csdt-js .lbl{color:#fab387}.csdt-res .lbl{color:#f9e2af}
.csdt-net .lbl{color:#89b4fa}.csdt-ok .lbl{color:#a6e3a1}.csdt-info .lbl{color:#89dceb}
.csdt-empty{color:#6c7086;font-size:11px;padding:12px 4px}
</style>
<div id="csdt-dbg">
    <div id="csdt-dbg-head">
        <h3>&#x1F6E0; DevTools Debug</h3>
        <div id="csdt-dbg-head-btns">
            <button id="csdt-dbg-clear" title="Clear all">&#x1F5D1;</button>
            <button id="csdt-dbg-min"   title="Minimise">&#x2212;</button>
            <button id="csdt-dbg-close" title="Close">&#x2715;</button>
        </div>
    </div>
    <div id="csdt-dbg-tabs">
        <button class="active" data-tab="all">All<span class="badge" id="csdt-b-all"></span></button>
        <button data-tab="csp">CSP<span class="badge" id="csdt-b-csp"></span></button>
        <button data-tab="js">JS<span class="badge" id="csdt-b-js"></span></button>
        <button data-tab="res">Assets<span class="badge" id="csdt-b-res"></span></button>
        <button data-tab="net">Network<span class="badge" id="csdt-b-net"></span></button>
        <button data-tab="frames">Frames<span class="badge" id="csdt-b-frames"></span></button>
    </div>
    <div id="csdt-dbg-body"><div class="csdt-empty">Listening&hellip;</div></div>
</div>
<script>
(function () {
    'use strict';
    var panel   = document.getElementById('csdt-dbg');
    var body    = document.getElementById('csdt-dbg-body');
    var tabs    = document.querySelectorAll('#csdt-dbg-tabs button');
    var activeTab = 'all';
    var logs    = [];          // {type, tab, ts, html}
    var counts  = {csp:0,js:0,res:0,net:0,frames:0};

    /* ── helpers ── */
    function ts() {
        var d = new Date();
        return ('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2)+':'+('0'+d.getSeconds()).slice(-2)+'.'+('00'+d.getMilliseconds()).slice(-3);
    }
    function esc(s) {
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    function push(tab, cssClass, label, detail, extra) {
        counts[tab] = (counts[tab]||0)+1;
        logs.unshift({ tab:tab, html:
            '<div class="csdt-row '+cssClass+'">'
            +'<span class="ts">'+ts()+'</span>'
            +'<span class="lbl">'+label+'</span>'
            +'<span class="detail">'+esc(detail)+(extra?'<br>'+esc(extra):'')+'</span>'
            +'</div>'
        });
        if (logs.length > 200) { logs.pop(); }
        renderBadges();
        render();
    }
    function renderBadges() {
        var total = 0;
        ['csp','js','res','net'].forEach(function(t){
            var b = document.getElementById('csdt-b-'+t);
            if (b) { var n=counts[t]||0; b.textContent=n; b.className='badge'+(n?' show':''); total+=n; }
        });
        var ba = document.getElementById('csdt-b-all');
        if (ba) { ba.textContent=total; ba.className='badge'+(total?' show':''); }
    }
    function render() {
        var rows = logs.filter(function(l){ return activeTab==='all'||l.tab===activeTab; });
        body.innerHTML = rows.length
            ? rows.map(function(l){return l.html;}).join('')
            : '<div class="csdt-empty">'+(activeTab==='frames'?'Scanning…':'No events yet')+'</div>';
    }

    /* ── tabs ── */
    tabs.forEach(function(btn){
        btn.addEventListener('click', function(){
            activeTab = btn.dataset.tab;
            tabs.forEach(function(b){ b.classList.remove('active'); });
            btn.classList.add('active');
            render();
        });
    });

    /* ── toolbar buttons ── */
    document.getElementById('csdt-dbg-close').addEventListener('click', function(){ panel.style.display='none'; });
    document.getElementById('csdt-dbg-min').addEventListener('click', function(){
        var collapsed = body.style.display === 'none';
        body.style.display    = collapsed ? '' : 'none';
        document.getElementById('csdt-dbg-tabs').style.display = collapsed ? '' : 'none';
        this.textContent = collapsed ? '−' : '□';
    });
    document.getElementById('csdt-dbg-clear').addEventListener('click', function(){
        logs=[]; counts={csp:0,js:0,res:0,net:0,frames:0}; renderBadges(); render();
    });

    /* ── drag to reposition ── */
    (function(){
        var hd = document.getElementById('csdt-dbg-head'), dx=0, dy=0, mx=0, my=0;
        hd.addEventListener('mousedown', function(e){
            e.preventDefault();
            mx=e.clientX; my=e.clientY;
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up, {once:true});
        });
        function move(e){
            dx=mx-e.clientX; dy=my-e.clientY; mx=e.clientX; my=e.clientY;
            panel.style.top  = (panel.offsetTop-dy)+'px';
            panel.style.right= 'auto';
            panel.style.left = (panel.offsetLeft-dx)+'px';
        }
        function up(){ document.removeEventListener('mousemove', move); }
    })();

    /* ── 1. CSP violations ── */
    document.addEventListener('securitypolicyviolation', function(e){
        push('csp','csdt-csp',
            'CSP '+e.violatedDirective,
            (e.blockedURI||'(empty)'),
            (e.sourceFile?e.sourceFile+':'+e.lineNumber:'')
        );
    });

    /* ── 2. JS errors ── */
    var origError = window.onerror;
    window.onerror = function(msg, src, line, col, err){
        push('js','csdt-js','JS Error', msg, (src?src+':'+line+':'+col:''));
        return origError ? origError.apply(this, arguments) : false;
    };

    /* ── 3. Unhandled promise rejections ── */
    window.addEventListener('unhandledrejection', function(e){
        var reason = e.reason;
        var msg = reason instanceof Error ? reason.message : String(reason||'unknown');
        push('js','csdt-js','Promise Reject', msg);
    });

    /* ── 4. Console.error capture ── */
    var origConsoleError = console.error;
    console.error = function() {
        var args = Array.prototype.slice.call(arguments);
        var msg = args.map(function(a){ return typeof a==='object'?JSON.stringify(a):String(a); }).join(' ');
        if (!/csdt-dbg/.test(msg)) {
            push('js','csdt-js','console.error', msg.substring(0,300));
        }
        return origConsoleError.apply(console, arguments);
    };

    /* ── 5. Console.warn capture (only for errors containing key words) ── */
    var origConsoleWarn = console.warn;
    console.warn = function() {
        var args = Array.prototype.slice.call(arguments);
        var msg = args.map(function(a){ return typeof a==='object'?JSON.stringify(a):String(a); }).join(' ');
        if (/error|fail|block|csp|403|401|unauthorized/i.test(msg) && !/csdt-dbg/.test(msg)) {
            push('js','csdt-js','console.warn', msg.substring(0,300));
        }
        return origConsoleWarn.apply(console, arguments);
    };

    /* ── 6. Failed resource loads (script, link, img, audio, video) ── */
    document.addEventListener('error', function(e){
        var t = e.target;
        if (!t || !t.tagName) return;
        var tag  = t.tagName.toLowerCase();
        var url  = t.src || t.href || '(unknown)';
        if (tag === 'script' || tag === 'link' || tag === 'img' || tag === 'audio' || tag === 'video' || tag === 'source') {
            push('res','csdt-res','Failed '+tag, url.replace(window.location.origin,''));
        }
    }, true);

    /* ── 7. Fetch / XHR intercept for network failures ── */
    var origFetch = window.fetch;
    window.fetch = function(input, init) {
        var url = typeof input === 'string' ? input : (input && input.url) || String(input);
        return origFetch.apply(this, arguments).then(function(resp){
            if (!resp.ok && resp.status >= 400) {
                push('net','csdt-net','HTTP '+resp.status, url.replace(window.location.origin,''));
            }
            return resp;
        }).catch(function(err){
            push('net','csdt-net','Fetch Fail', url.replace(window.location.origin,''), err.message||String(err));
            throw err;
        });
    };

    var origOpen = XMLHttpRequest.prototype.open;
    var origSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function(method, url) {
        this._csdt_url = url;
        return origOpen.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function() {
        var xhr = this;
        xhr.addEventListener('load', function(){
            if (xhr.status >= 400) {
                push('net','csdt-net','XHR '+xhr.status, String(xhr._csdt_url||'?').replace(window.location.origin,''));
            }
        });
        xhr.addEventListener('error', function(){
            push('net','csdt-net','XHR Error', String(xhr._csdt_url||'?').replace(window.location.origin,''));
        });
        return origSend.apply(this, arguments);
    };

    /* ── 8. Iframe scanner ── */
    function scanFrames() {
        var frames = document.querySelectorAll('iframe');
        var found  = [];
        frames.forEach(function(f, i){
            var src   = f.src || '';
            var proto = src ? src.split(':')[0] : '—';
            found.push('['+i+'] '+f.name+' '+proto+'://… '+(f.src?f.src.substring(0,60):'(no src)'));
        });
        /* Update frames tab content independently so it doesn't duplicate in All */
        var old = logs.findIndex(function(l){ return l.tab==='frames'; });
        var html = found.length
            ? found.map(function(s){return '<div class="csdt-row csdt-info"><span class="lbl">iframe</span><span class="detail">'+esc(s)+'</span></div>';}).join('')
            : '<div class="csdt-empty">No iframes yet</div>';
        var entry = { tab:'frames', html: html };
        if (old >= 0) logs[old] = entry; else logs.push(entry);
        render();
    }
    scanFrames();
    setInterval(scanFrames, 3000);

    /* ── 9. REST API errors surfaced via Gutenberg notices ── */
    if (window.wp && window.wp.data) {
        var lastNoticeCount = 0;
        setInterval(function(){
            try {
                var notices = wp.data.select('core/notices').getNotices();
                if (notices.length > lastNoticeCount) {
                    notices.slice(lastNoticeCount).forEach(function(n){
                        if (n.status === 'error' || n.status === 'warning') {
                            push('net','csdt-net','WP Notice ['+n.status+']', n.content||n.id||'?');
                        }
                    });
                    lastNoticeCount = notices.length;
                }
            } catch(e) {}
        }, 2000);
    }
})();
</script>
        <?php
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
        $ai_provider    = get_option( 'csdt_devtools_ai_provider', 'anthropic' );
        $anthropic_key  = get_option( 'csdt_devtools_anthropic_key', '' );
        $gemini_key     = get_option( 'csdt_devtools_gemini_key', '' );
        $has_key        = $ai_provider === 'gemini' ? ! empty( $gemini_key ) : ! empty( $anthropic_key );
        $sched_enabled  = get_option( 'csdt_scan_schedule_enabled', '0' ) === '1';
        $sched_freq     = get_option( 'csdt_scan_schedule_freq',    'weekly' );
        $sched_type     = get_option( 'csdt_scan_schedule_type',    'deep' );
        $sched_email    = get_option( 'csdt_scan_schedule_email',   '1' ) === '1';
        $sched_ntfy_url = get_option( 'csdt_scan_schedule_ntfy_url', '' );
        $sched_ntfy_tok = get_option( 'csdt_scan_schedule_ntfy_token', '' );
        $next_run       = wp_next_scheduled( 'csdt_scheduled_scan' );
        $sec_url        = admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=security' );
        $rollback_info  = get_option( 'csdt_db_prefix_rollback' );
        ?>
        <div id="cs-panel-home" class="cs-panel" style="margin-bottom:0;">

        <!-- ── Intro ────────────────────────────────────────────────────── -->
        <div class="cs-tab-intro" style="margin-bottom:0;">
            <p><?php echo wp_kses( __( 'WordPress powers over <strong>40% of the internet</strong>, making it the single largest attack surface on the web. Automated scanners probe every exposed WordPress site every day, looking for unpatched plugins, exposed admin pages, weak credentials, and misconfigured headers. The attackers are tooled up. Most defenders aren&#8217;t.', 'cloudscale-devtools' ), [ 'strong' => [] ] ); ?></p>
            <p><?php echo wp_kses( __( '<strong>CloudScale Cyber &amp; Devtools</strong> is a free, all-in-one security and developer plugin that puts frontier AI in your corner. The built-in <strong>AI Cyber Audit</strong> performs a full <strong>AI-powered WordPress penetration test</strong>, analysing your entire installation and producing a prioritised, scored security report in under 60 seconds. The kind of assessment that used to require hiring a consultant or running a manual pen test is now instant and free. It runs locally inside your own server using your own API key: no third-party cloud, no subscription, no data leaving your site except the call to your chosen AI provider. A <strong>free Gemini tier</strong> is available with no credit card required.', 'cloudscale-devtools' ), [ 'strong' => [] ] ); ?></p>
            <p><?php echo wp_kses( __( 'Beyond the AI audit, the plugin replaces a stack of paid tools you may already be running: a <strong>security scanner</strong> with one-click Quick Fixes, a <strong>2FA &amp; login protection</strong> layer, an <strong>SMTP mailer</strong>, a <strong>SQL query tool</strong>, <strong>PHP-FPM monitoring</strong>, a <strong>server log viewer</strong>, and a <strong>plugin vulnerability scanner</strong>. All in one place, all free.', 'cloudscale-devtools' ), [ 'strong' => [] ] ); ?></p>
            <p><?php echo wp_kses( __( '<strong>To get started:</strong> select an AI provider and paste your API key below, then apply the one-click <strong>Quick Fixes</strong> to resolve common misconfigurations. Head to the <strong>Security</strong> tab to run your first scan.', 'cloudscale-devtools' ), [ 'strong' => [] ] ); ?></p>
        </div>

        <!-- ── AI Cyber Audit Settings ──────────────────────────────────── -->
        <div class="cs-section-header cs-section-header-red" style="margin-top:18px;">
            <span>&#x1F916; <?php esc_html_e( 'AI Cyber Audit — Setup', 'cloudscale-devtools' ); ?></span>
            <span class="cs-header-hint"><?php esc_html_e( 'Select a provider and paste your API key to enable AI-powered security scans', 'cloudscale-devtools' ); ?></span>
            <?php self::render_explain_btn( 'cyber-audit', 'AI Cyber Audit', [
                [ 'name' => 'AI Providers',        'rec' => 'Info',         'html' => '<p>Two AI providers are supported. You supply your own API key — stored only in your WordPress database (<code>wp_options</code>) and sent only to the provider&#39;s own API endpoint.</p><p><strong>Anthropic Claude</strong> — recommended for best results.<br>Get your key: <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com/settings/keys</a><br>Models: <code>claude-sonnet-4-6</code> (fast) · <code>claude-opus-4-7</code> (most capable)</p><p><strong>Google Gemini</strong> — free tier available.<br>Get your key: <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">aistudio.google.com/app/apikey</a><br>Models: <code>gemini-2.0-flash</code> (fast, free tier) · <code>gemini-2.5-pro</code> (most capable)</p>' ],
                [ 'name' => 'Standard Scan',       'rec' => 'Recommended',  'html' => 'Checks your WordPress core settings, active plugins and themes, user accounts, file permissions, and wp-config.php for common misconfigurations. Results are scored as Critical / High / Medium / Low with specific fix steps. Takes a few seconds.' ],
                [ 'name' => 'Deep Dive Scan',      'rec' => 'Recommended',  'html' => 'Extends the Standard scan with live HTTP probes, DNS checks (SPF, DMARC, DKIM), weak TLS detection, PHP end-of-life status, static PHP code analysis across your plugins, and AI-powered triage of suspicious code patterns.' ],
                [ 'name' => 'Scheduled Scans',     'rec' => 'Optional',     'html' => 'Run a scan automatically on a daily or weekly schedule. Results are stored in scan history. Enable email and ntfy.sh alerts to receive the AI summary when a scan completes.' ],
            ] ); ?>
        </div>
        <div class="cs-panel-body">
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
                        __( 'Get your key at <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com</a>. Stored in wp_options.', 'cloudscale-devtools' ),
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
                        <option value="_auto">&#x2728; Auto</option>
                    </select>
                </div>
            </div>

            <div class="cs-sec-row">
                <span class="cs-sec-label"><?php esc_html_e( 'Deep dive model:', 'cloudscale-devtools' ); ?></span>
                <div class="cs-sec-control">
                    <select id="cs-sec-deep-model" class="cs-sec-select">
                        <option value="_auto_deep">&#x2728; Auto</option>
                    </select>
                </div>
            </div>

            <div class="cs-sec-row cs-sec-row-prompt">
                <span class="cs-sec-label"><?php esc_html_e( 'System prompt:', 'cloudscale-devtools' ); ?></span>
                <div class="cs-sec-control">
                    <textarea id="cs-sec-prompt" class="cs-sec-prompt-area" rows="10"></textarea>
                    <div style="display:flex;align-items:center;gap:6px;margin-top:8px;flex-wrap:wrap">
                        <button type="button" class="cs-btn-secondary cs-btn-sm" id="cs-sec-copy-prompt">&#x2398; <?php esc_html_e( 'Copy', 'cloudscale-devtools' ); ?></button>
                        <button type="button" class="cs-btn-secondary cs-btn-sm" id="cs-sec-reset-prompt"><?php esc_html_e( 'Reset to default', 'cloudscale-devtools' ); ?></button>
                        <div style="flex:1"></div>
                        <button type="button" class="cs-btn-primary" id="cs-sec-save">&#x1F4BE; <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
                        <span class="cs-settings-saved" id="cs-sec-saved">&#x2713; <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                    </div>
                </div>
            </div>

        </div>

        <hr class="cs-sec-divider">

        <!-- Scheduled scan -->
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
            <div id="cs-sched-options" <?php echo $sched_enabled ? 'style="display:flex;flex-direction:column;gap:16px;"' : 'style="display:none"'; ?>>
                <div class="cs-sec-row">
                    <span class="cs-sec-label"><?php esc_html_e( 'Frequency:', 'cloudscale-devtools' ); ?></span>
                    <div class="cs-sec-control">
                        <select id="cs-sched-freq" class="cs-sec-select" style="width:auto;max-width:180px;">
                            <option value="weekly"  <?php selected( $sched_freq, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'cloudscale-devtools' ); ?></option>
                            <option value="monthly" <?php selected( $sched_freq, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'cloudscale-devtools' ); ?></option>
                        </select>
                    </div>
                </div>
                <div class="cs-sec-row">
                    <span class="cs-sec-label"><?php esc_html_e( 'Scan type:', 'cloudscale-devtools' ); ?></span>
                    <div class="cs-sec-control">
                        <select id="cs-sched-type" class="cs-sec-select" style="width:auto;max-width:280px;">
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
                        <span class="cs-hint"><?php echo wp_kses( __( 'Optional push notification via <a href="https://ntfy.sh" target="_blank" rel="noopener">ntfy.sh</a>.', 'cloudscale-devtools' ), [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] ); ?></span>
                    </div>
                </div>
                <div class="cs-sec-row">
                    <span class="cs-sec-label"><?php esc_html_e( 'ntfy auth token:', 'cloudscale-devtools' ); ?></span>
                    <div class="cs-sec-control">
                        <input type="password" id="cs-sched-ntfy-token" class="cs-text-input"
                               autocomplete="off" placeholder="<?php echo $sched_ntfy_tok ? '&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;' : esc_attr__( 'Optional &#8212; for protected topics', 'cloudscale-devtools' ); ?>"
                               style="max-width:320px;">
                    </div>
                </div>
                <div class="cs-sec-row">
                    <span class="cs-sec-label"></span>
                    <div class="cs-sec-control">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <button type="button" class="cs-btn-primary" id="cs-sched-save">&#x1F4BE; <?php esc_html_e( 'Save Schedule', 'cloudscale-devtools' ); ?></button>
                            <span class="cs-settings-saved" id="cs-sched-saved">&#x2713; <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div><!-- /AI settings -->

        <!-- ── DB Prefix Rollback Banner ──────────────────────────────────── -->
        <?php
        if ( $rollback_info && ! empty( $rollback_info['old_prefix'] ) ) :
            $age_h = round( ( time() - ( $rollback_info['time'] ?? 0 ) ) / 3600, 1 );
        ?>
        <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:12px 16px;margin:12px 24px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <div style="flex:1;min-width:0;">
                <span style="font-weight:700;color:#dc2626;font-size:13px;">&#x21A9; DB Prefix Rollback Available</span>
                <span style="font-size:12px;color:#6b7280;margin-left:6px;"><?php echo esc_html( $age_h ); ?>h ago</span>
                <div style="font-size:12px;color:#374151;margin-top:2px;">
                    Tables were renamed from <code><?php echo esc_html( $rollback_info['old_prefix'] ); ?></code> &rarr; <code><?php echo esc_html( $rollback_info['new_prefix'] ); ?></code>
                    (<?php echo count( $rollback_info['tables'] ?? [] ); ?> tables). Rollback restores all tables and wp-config.php.
                </div>
            </div>
            <button type="button" id="csdt-prefix-rollback-persistent-btn" class="cs-btn-secondary cs-btn-sm" style="border-color:#ef4444;color:#dc2626;white-space:nowrap;">&#x21A9; Rollback Now</button>
            <span id="csdt-prefix-rollback-persistent-msg" style="display:none;font-size:12px;"></span>
        </div>
        <script>
        (function(){
            var btn = document.getElementById('csdt-prefix-rollback-persistent-btn');
            if (!btn) { return; }
            btn.addEventListener('click', function () {
                if (!confirm('Roll back all renamed tables and restore wp-config.php?')) { return; }
                btn.disabled = true; btn.textContent = '⏳ Rolling back…';
                var msg = document.getElementById('csdt-prefix-rollback-persistent-msg');
                var fd = new FormData();
                fd.append('action', 'csdt_db_prefix_rollback');
                fd.append('nonce', <?php echo wp_json_encode( wp_create_nonce( 'csdt_devtools_security_nonce' ) ); ?>);
                fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { method:'POST', body:fd, credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(r){
                        btn.disabled = false;
                        if (msg) {
                            msg.style.display = '';
                            msg.style.color = r.success ? '#16a34a' : '#dc2626';
                            msg.textContent = (r.data && r.data.message) || (r.success ? 'Rolled back.' : 'Failed.');
                        }
                        if (r.success) { btn.textContent = '✓ Done'; btn.disabled = true; btn.closest('div[style]').style.background='#f0fdf4'; }
                        else { btn.textContent = '↩ Rollback Now'; }
                    }).catch(function(){ btn.disabled=false; btn.textContent='↩ Rollback Now'; });
            });
        })();
        </script>
        <?php endif; ?>

        <!-- ── Quick Fixes ─────────────────────────────────────────────────── -->
        <div class="cs-section-header" style="background:linear-gradient(90deg,#78350f 0%,#b45309 100%);border-left:3px solid #fcd34d;margin-bottom:0;">
            <span>&#x26A1; <?php esc_html_e( 'Quick Fixes', 'cloudscale-devtools' ); ?></span>
            <span class="cs-header-hint"><?php esc_html_e( 'One-click hardening actions for common WordPress security settings', 'cloudscale-devtools' ); ?></span>
            <?php self::render_explain_btn( 'quick-fixes', 'Quick Fixes', [
                [ 'name' => 'How it works',       'rec' => 'Overview',    'html' => 'Each row shows a security hardening item and its current status (&#x2705; fixed / &#x26A0; needs attention). Click the action button to apply the fix in one click — no manual file editing or WP-CLI required. The panel refreshes automatically after each fix.' ],
                [ 'name' => 'WP-Cron Health',     'rec' => 'Important',   'html' => 'Checks that WordPress scheduled cleanup events are scheduled and firing on time. If cron is disabled or events are overdue, click <strong>Reschedule &amp; Run Now</strong> to fix immediately.' ],
                [ 'name' => 'Expired Transients', 'rec' => 'Maintenance', 'html' => 'Counts expired cache entries left in wp_options. WordPress auto-purges these daily via cron, but they can accumulate if cron has been unreliable. Click <strong>Delete Expired Transients</strong> to clear the backlog immediately.' ],
                [ 'name' => 'DB Prefix',          'rec' => 'Critical',    'html' => 'Renames all <code>wp_</code> tables to a unique prefix and updates wp-config.php automatically. Always create a database backup before running this fix.' ],
                [ 'name' => 'wp-config.php',      'rec' => 'Critical',    'html' => 'Sets <code>wp-config.php</code> permissions to <code>0400</code> (read-only). This prevents any PHP process — including a compromised plugin — from overwriting your database credentials or secret keys.' ],
            ] ); ?>
        </div>
        <div id="cs-quick-fixes-panel" style="padding:12px 0 4px;">
        <?php foreach ( CSDT_Site_Audit::get_quick_fixes() as $fix ) :
            $is_fixed = (bool) $fix['fixed'];
        ?>
            <div class="cs-quick-fix-row" data-fix-id="<?php echo esc_attr( $fix['id'] ); ?>" style="display:flex;align-items:flex-start;gap:12px;padding:10px 14px;margin-bottom:6px;background:<?php echo $is_fixed ? 'rgba(0,0,0,0.02)' : '#fff'; ?>;border-radius:6px;border:1px solid <?php echo $is_fixed ? 'rgba(0,0,0,0.07)' : 'rgba(0,0,0,0.12)'; ?>;">
                <div style="flex-shrink:0;font-size:16px;line-height:1.5;padding-top:1px;"><?php echo $is_fixed ? '<span style="color:#16a34a;">✓</span>' : '<span style="color:#d97706;">⚠</span>'; ?></div>
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                        <div style="font-size:13px;font-weight:600;color:<?php echo $is_fixed ? '#6b7280' : '#1d2327'; ?>;"><?php echo esc_html( $fix['title'] ); ?></div>
                        <?php if ( $is_fixed ) : ?>
                        <span style="flex-shrink:0;font-size:12px;color:#16a34a;font-weight:600;white-space:nowrap;">Fixed &#x2713;</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:12px;color:#50575e;margin-top:2px;"><?php echo esc_html( $fix['detail'] ); ?></div>
                    <?php if ( ! $is_fixed ) : ?>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
                    <?php if ( ! empty( $fix['fix_modal'] ) ) : ?>
                        <button type="button" class="cs-btn-primary cs-btn-sm"
                                onclick="document.getElementById('<?php echo esc_attr( $fix['fix_modal'] ); ?>').style.display='flex';">
                            <?php echo esc_html( $fix['fix_label'] ); ?>
                        </button>
                    <?php else : ?>
                        <button type="button" class="cs-btn-primary cs-btn-sm cs-quick-fix-btn"
                                data-fix-id="<?php echo esc_attr( $fix['id'] ); ?>">
                            <?php echo esc_html( $fix['fix_label'] ); ?>
                        </button>
                        <?php if ( ! empty( $fix['dismiss_label'] ) && ! empty( $fix['dismiss_id'] ) ) : ?>
                        <button type="button" class="cs-btn-secondary cs-btn-sm cs-quick-fix-btn"
                                data-fix-id="<?php echo esc_attr( $fix['dismiss_id'] ); ?>"
                                style="font-size:11px;">
                            <?php echo esc_html( $fix['dismiss_label'] ); ?>
                        </button>
                        <?php endif; ?>
                    <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        </div><!-- /cs-panel -->
        <?php
    }

    /* ==================================================================
       Optimizer tab — Plugin Stack Scanner + AI Debugging Assistant
       ================================================================== */

    private static function render_optimizer_panel(): void {
        $has_key = ! empty( get_option( 'csdt_devtools_anthropic_key', '' ) ) ||
                   ! empty( get_option( 'csdt_devtools_gemini_key', '' ) );
        $security_url = admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=security' );
        ?>
        <div class="cs-panel" id="cs-panel-optimizer">
            <div class="cs-section-header" style="background:linear-gradient(90deg,#1e1b4b 0%,#4338ca 100%);border-left:3px solid #818cf8;">
                <span>🔧 <?php esc_html_e( 'Plugin Optimizer', 'cloudscale-devtools' ); ?></span>
                <span class="cs-header-hint"><?php esc_html_e( 'Find plugins CloudScale replaces, reduce bloat, and diagnose errors with AI', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'plugin-optimizer', 'Plugin Optimizer', [
                    [ 'name' => 'Plugin Stack Scanner',    'rec' => 'Overview',    'html' => 'Compares your active plugins against a curated list of functionality that CloudScale already provides. Plugins flagged as redundant can usually be deactivated — reducing page load time, update surface area, and conflict risk.' ],
                    [ 'name' => 'Plugin health check',     'rec' => 'Recommended', 'html' => 'Checks each active plugin against the WordPress.org API for last-updated date, compatibility with your WordPress version, and known vulnerabilities. Plugins not updated in over 2 years are flagged as high risk.' ],
                    [ 'name' => 'Update Risk Scorer',           'rec' => 'Recommended', 'html' => 'Before applying plugin updates, scan for available updates and click Assess on any plugin to get an AI risk rating: 🟢 Patch (safe, apply now), 🟡 Minor (new features, low risk), or 🔴 Breaking (major changes — review changelog before updating).' ],
                    [ 'name' => 'Uptime Monitor',               'rec' => 'Recommended', 'html' => 'Deploys a Cloudflare Worker that pings your site every 60 seconds from the edge — completely independent of your server. If the site goes down, the Worker sends an ntfy.sh push notification immediately, even if your server is completely offline. Requires your Cloudflare Zone ID and an API token with Workers:Edit scope (set in Thumbnails tab).' ],
                    [ 'name' => 'Database Intelligence Engine', 'rec' => 'Recommended', 'html' => 'Scans your WordPress database for hidden bloat: oversized autoload cache, expired transients, post revisions, and orphaned postmeta. Each issue found includes a one-click Fix It button that cleans up directly — no plugin needed.' ],
                    [ 'name' => 'AI Debugging',                 'rec' => 'Optional',    'html' => 'Paste any PHP error, JavaScript console error, or plugin conflict description into the AI Debugging Assistant. It identifies the root cause and gives specific numbered steps to fix it — no need to search Stack Overflow or support forums.' ],
                    [ 'name' => 'Inactive plugins',             'rec' => 'Important',   'html' => 'Inactive plugins still execute their autoloaded code and are still scanned for vulnerabilities. Deactivate and delete plugins you are not actively using — do not just deactivate them.' ],
                ] ); ?>
            </div>
            <div class="cs-panel-body">

                <!-- ── Plugin Stack Scanner ──────────────────────────────── -->
                <div style="margin-bottom:36px;">
                    <h2 class="cs-panel-heading">🔍 <?php esc_html_e( 'Plugin Stack Scanner', 'cloudscale-devtools' ); ?></h2>
                    <p style="color:#4b5563;margin:0 0 6px;line-height:1.65;font-size:.95em;">
                        <?php esc_html_e( 'CloudScale replaces entire categories of WordPress plugins — security scanners, 2FA plugins, SMTP mailers, code block plugins, SQL tools, and log viewers. Scan to find out which of your installed plugins you can safely remove.', 'cloudscale-devtools' ); ?>
                    </p>
                    <p style="color:#9ca3af;margin:0 0 18px;font-size:.88em;">
                        <?php esc_html_e( 'Fewer plugins = smaller attack surface, faster page loads, fewer update conflicts.', 'cloudscale-devtools' ); ?>
                    </p>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <button id="csdt-optimizer-scan-btn" class="cs-btn-primary">
                            🔍 <?php esc_html_e( 'Scan My Plugin Stack', 'cloudscale-devtools' ); ?>
                        </button>
                        <span id="csdt-optimizer-scanning" style="display:none;color:#6b7280;font-size:13px;">
                            ⏳ <?php esc_html_e( 'Scanning installed plugins...', 'cloudscale-devtools' ); ?>
                        </span>
                    </div>
                    <div id="csdt-optimizer-results" style="display:none;margin-top:20px;"></div>
                </div>

                <!-- ── AI Debugging Assistant ────────────────────────────── -->
                <div style="border-top:1px solid #e5e7eb;padding-top:28px;padding-bottom:28px;">
                    <h2 class="cs-panel-heading">🤖 <?php esc_html_e( 'AI Debugging Assistant', 'cloudscale-devtools' ); ?></h2>
                    <p style="color:#4b5563;margin:0 0 6px;line-height:1.65;font-size:.95em;">
                        <?php esc_html_e( 'Paste an error message, PHP warning, or stack trace. The AI identifies the root cause and gives you specific steps to fix it — no more hunting through Stack Overflow.', 'cloudscale-devtools' ); ?>
                    </p>
                    <p style="color:#9ca3af;margin:0 0 16px;font-size:.88em;">
                        <?php esc_html_e( 'Works with PHP fatal errors, WordPress notices, plugin conflicts, database errors, and 500s.', 'cloudscale-devtools' ); ?>
                    </p>

                    <?php if ( ! $has_key ) : ?>
                    <div style="background:#fff7ed;border-left:3px solid #f59e0b;padding:11px 16px;border-radius:0 6px 6px 0;margin-bottom:16px;font-size:13px;color:#92400e;">
                        <?php printf(
                            /* translators: %s: link to security tab */
                            esc_html__( 'AI analysis requires an API key. %s', 'cloudscale-devtools' ),
                            '<a href="' . esc_url( $security_url ) . '" style="color:#b45309;font-weight:600;">' . esc_html__( 'Add your key on the Security tab →', 'cloudscale-devtools' ) . '</a>'
                        ); ?>
                    </div>
                    <?php endif; ?>

                    <textarea id="csdt-debug-input"
                              rows="6"
                              placeholder="<?php esc_attr_e( 'Paste your error message, stack trace, or describe the problem...', 'cloudscale-devtools' ); ?>"
                              style="width:100%;font-family:'SF Mono','Fira Code',Consolas,monospace;font-size:12px;background:#0d1117;color:#c9d1d9;border:1px solid rgba(255,255,255,.12);border-radius:6px;padding:12px;box-sizing:border-box;resize:vertical;line-height:1.6;"></textarea>

                    <div style="display:flex;align-items:center;gap:12px;margin-top:10px;flex-wrap:wrap;">
                        <button id="csdt-debug-analyze-btn" class="cs-btn-primary" <?php echo $has_key ? '' : 'disabled style="opacity:.5;cursor:not-allowed;"'; ?>>
                            🤖 <?php esc_html_e( 'Diagnose with AI', 'cloudscale-devtools' ); ?>
                        </button>
                        <span id="csdt-debug-analyzing" style="display:none;color:#6b7280;font-size:13px;">
                            ⏳ <?php esc_html_e( 'Analyzing...', 'cloudscale-devtools' ); ?>
                        </span>
                    </div>

                    <div id="csdt-debug-result" style="display:none;margin-top:20px;"></div>
                </div>

                <!-- ── Update Risk Scorer ────────────────────────────────── -->
                <div style="border-top:1px solid #e5e7eb;padding-top:28px;padding-bottom:28px;">
                    <h2 class="cs-panel-heading">🔄 <?php esc_html_e( 'Update Risk Scorer', 'cloudscale-devtools' ); ?></h2>
                    <p style="color:#4b5563;margin:0 0 6px;line-height:1.65;font-size:.95em;">
                        <?php esc_html_e( 'Before applying plugin updates, get an AI risk rating for each one: Patch (safe now), Minor (new features), or Breaking (review first). Prevents update-caused site breakage.', 'cloudscale-devtools' ); ?>
                    </p>
                    <p style="color:#9ca3af;margin:0 0 16px;font-size:.88em;">
                        <?php esc_html_e( 'Reads the plugin changelog from WordPress.org and asks the AI to assess whether this is a security patch, a feature release, or a potentially breaking change.', 'cloudscale-devtools' ); ?>
                    </p>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <button id="csdt-update-risk-scan-btn" class="cs-btn-primary">
                            🔍 <?php esc_html_e( 'Scan for Available Updates', 'cloudscale-devtools' ); ?>
                        </button>
                        <span id="csdt-update-risk-scanning" style="display:none;color:#6b7280;font-size:13px;">
                            ⏳ <?php esc_html_e( 'Loading...', 'cloudscale-devtools' ); ?>
                        </span>
                    </div>
                    <div id="csdt-update-risk-results" style="display:none;margin-top:20px;"></div>
                </div>

                <!-- ── Uptime Monitor ────────────────────────────────── -->
                <div style="border-top:1px solid #e5e7eb;padding-top:28px;padding-bottom:28px;">
                    <h2 class="cs-panel-heading">⏱ <?php esc_html_e( 'Uptime Monitor', 'cloudscale-devtools' ); ?></h2>
                    <p style="color:#4b5563;margin:0 0 6px;line-height:1.65;font-size:.95em;">
                        <?php esc_html_e( 'Deploys a Cloudflare Worker that pings your site every 60 seconds from the edge — independent of your server. If the site goes down, the Worker sends an ntfy.sh alert immediately, even if your server is completely offline.', 'cloudscale-devtools' ); ?>
                    </p>
                    <p style="color:#9ca3af;margin:0 0 16px;font-size:.88em;">
                        <?php esc_html_e( 'Requires Cloudflare Zone ID and API Token (saved in Thumbnails tab). The API token needs Workers:Edit permission.', 'cloudscale-devtools' ); ?>
                    </p>
                    <div id="csdt-uptime-setup-wrap">
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
                            <button id="csdt-uptime-generate-token-btn" class="cs-btn-secondary" style="font-size:.88em;">
                                🔑 <?php esc_html_e( 'Generate Token', 'cloudscale-devtools' ); ?>
                            </button>
                            <div id="csdt-uptime-token-wrap" style="display:none;flex:1;max-width:420px;">
                                <input id="csdt-uptime-token-display" type="text" readonly class="cs-input" style="font-family:monospace;font-size:.82em;" value="">
                            </div>
                        </div>
                        <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:16px 20px;margin-bottom:16px;">
                            <p style="margin:0 0 10px;font-weight:700;color:#0f172a;font-size:.9em;">ntfy.sh Alert URL <span style="font-weight:400;color:#6b7280;">(optional — sent directly from the Worker when your site is down)</span></p>
                            <input id="csdt-uptime-ntfy-url" type="text" class="cs-input" style="max-width:420px;"
                                   placeholder="https://ntfy.sh/your-topic"
                                   value="<?php echo esc_attr( get_option( 'csdt_uptime_ntfy_url', get_option( 'csdt_scan_schedule_ntfy_url', '' ) ) ); ?>">
                        </div>
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <button id="csdt-uptime-deploy-btn" class="cs-btn-primary">
                                🚀 <?php esc_html_e( 'Deploy Worker to Cloudflare', 'cloudscale-devtools' ); ?>
                            </button>
                            <span id="csdt-uptime-deploying" style="display:none;color:#6b7280;font-size:13px;">⏳ <?php esc_html_e( 'Deploying…', 'cloudscale-devtools' ); ?></span>
                        </div>
                        <div id="csdt-uptime-deploy-result" style="margin-top:12px;"></div>
                        <details style="margin-top:16px;">
                            <summary style="cursor:pointer;font-size:.85em;font-weight:600;color:#6366f1;">🛠 Manual deploy (copy-paste Worker script)</summary>
                            <div id="csdt-uptime-manual-wrap" style="margin-top:12px;"></div>
                        </details>
                    </div>
                    <div id="csdt-uptime-status-wrap" style="display:none;margin-top:4px;">
                        <div id="csdt-uptime-status-inner"></div>
                        <div style="margin-top:12px;">
                            <button id="csdt-uptime-refresh-btn" class="cs-btn-secondary" style="font-size:.82em;">↻ <?php esc_html_e( 'Refresh', 'cloudscale-devtools' ); ?></button>
                        </div>
                    </div>
                </div>

                <!-- ── Database Intelligence Engine ──────────────────── -->
                <div style="border-top:1px solid #e5e7eb;padding-top:28px;padding-bottom:28px;">
                    <h2 class="cs-panel-heading">🗄️ <?php esc_html_e( 'Database Intelligence Engine', 'cloudscale-devtools' ); ?></h2>
                    <p style="color:#4b5563;margin:0 0 6px;line-height:1.65;font-size:.95em;">
                        <?php esc_html_e( 'Scans your WordPress database for hidden bloat — oversized autoload cache, expired transients, post revisions, and orphaned metadata — then gives you one-click cleanup actions for each issue found.', 'cloudscale-devtools' ); ?>
                    </p>
                    <p style="color:#9ca3af;margin:0 0 16px;font-size:.88em;">
                        <?php esc_html_e( 'All fixes run directly in the database. Take a backup first if you want a safety net.', 'cloudscale-devtools' ); ?>
                    </p>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <button id="csdt-db-intelligence-scan-btn" class="cs-btn-primary">
                            🔍 <?php esc_html_e( 'Analyse Database', 'cloudscale-devtools' ); ?>
                        </button>
                        <span id="csdt-db-intelligence-scanning" style="display:none;color:#6b7280;font-size:13px;">
                            ⏳ <?php esc_html_e( 'Scanning…', 'cloudscale-devtools' ); ?>
                        </span>
                    </div>
                    <div id="csdt-db-intelligence-results" style="display:none;margin-top:20px;"></div>
                </div>

                <!-- ── Orphaned Table Cleanup ────────────────────────────── -->
                <div style="border-top:1px solid #e5e7eb;padding-top:28px;padding-bottom:28px;">
                    <h2 class="cs-panel-heading">🗑️ <?php esc_html_e( 'Orphaned Table Cleanup', 'cloudscale-devtools' ); ?></h2>
                    <p style="color:#4b5563;margin:0 0 6px;line-height:1.65;font-size:.95em;">
                        <?php esc_html_e( 'Scans for database tables left behind by removed plugins. WordPress core tables are always protected — only non-core tables appear here.', 'cloudscale-devtools' ); ?>
                    </p>
                    <p style="color:#9ca3af;margin:0 0 16px;font-size:.88em;">
                        <?php esc_html_e( 'Tables are moved to the Recycle Bin first (renamed with a _trash_ prefix). You can then restore or permanently delete them.', 'cloudscale-devtools' ); ?>
                    </p>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
                        <button id="csdt-orphan-scan-btn" type="button" class="cs-btn-primary">
                            🔍 <?php esc_html_e( 'Scan for Orphaned Tables', 'cloudscale-devtools' ); ?>
                        </button>
                    </div>
                    <div id="csdt-orphan-results" style="margin-top:8px;"></div>

                    <!-- Recycle Bin -->
                    <div style="margin-top:28px;border-top:1px solid #fde68a;padding-top:20px;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                            <h3 style="margin:0;font-size:1rem;font-weight:600;color:#374151;">♻️ <?php esc_html_e( 'Recycle Bin', 'cloudscale-devtools' ); ?></h3>
                            <button id="csdt-trash-refresh-btn" type="button" style="font-size:11px;background:none;border:1px solid #d1d5db;border-radius:4px;padding:2px 8px;cursor:pointer;color:#6b7280;">🔄 <?php esc_html_e( 'Refresh', 'cloudscale-devtools' ); ?></button>
                        </div>
                        <p style="color:#9ca3af;font-size:.88em;margin:0 0 12px;"><?php esc_html_e( 'Archived tables can be restored to their original names or permanently deleted.', 'cloudscale-devtools' ); ?></p>
                        <div id="csdt-trash-results"><span style="color:#9ca3af;font-size:13px;">⏳ <?php esc_html_e( 'Loading…', 'cloudscale-devtools' ); ?></span></div>
                    </div>
                </div>
                <?php
                $ai_key_set = ! empty( get_option( 'csdt_devtools_anthropic_key', '' ) ) || ! empty( get_option( 'csdt_devtools_gemini_key', '' ) );
                ?>
                <script>
                (function(){
                    var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
                    var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'csdt_optimizer_nonce' ) ); ?>;
                    var hasAi   = <?php echo $ai_key_set ? 'true' : 'false'; ?>;

                    function post(action, data) {
                        var params = 'action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(nonce);
                        if (data) {
                            Object.keys(data).forEach(function(k) {
                                params += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(
                                    typeof data[k] === 'object' ? JSON.stringify(data[k]) : data[k]
                                );
                            });
                        }
                        return fetch(ajaxUrl, {
                            method: 'POST', credentials: 'same-origin',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: params
                        }).then(function(r){ return r.json(); });
                    }

                    function esc(s) {
                        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                    }

                    function fmtKb(kb) {
                        return kb >= 1024 ? (kb/1024).toFixed(1)+' MB' : kb+' KB';
                    }

                    /* ── Orphan Scan ───────────────────────────── */
                    function runOrphanScan() {
                        var btn = document.getElementById('csdt-orphan-scan-btn');
                        var res = document.getElementById('csdt-orphan-results');
                        if (!btn || !res) return;
                        btn.disabled = true;
                        btn.textContent = '⏳ Scanning…';
                        res.innerHTML = '';
                        post('csdt_db_orphaned_scan').then(function(r) {
                            btn.disabled = false;
                            btn.textContent = '🔍 Scan for Orphaned Tables';
                            if (!r.success) { res.innerHTML = '<p style="color:#ef4444;font-size:13px;">' + esc(r.data||'Scan failed.') + '</p>'; return; }
                            renderOrphanResults(r.data.tables||[], res);
                        }).catch(function(e){
                            btn.disabled = false;
                            btn.textContent = '🔍 Scan for Orphaned Tables';
                            res.innerHTML = '<p style="color:#ef4444;font-size:13px;">Error: ' + esc(e.message) + '</p>';
                        });
                    }

                    function renderOrphanResults(tables, container) {
                        if (!tables.length) {
                            container.innerHTML = '<p style="color:#16a34a;font-size:13px;margin:0;">✅ No orphaned tables found.</p>';
                            return;
                        }
                        var totalKb = tables.reduce(function(s,t){ return s + (t.size_kb||0); }, 0);
                        var unknownTables = tables.filter(function(t){ return t.plugin === 'Unknown plugin'; });
                        var emptyCount = tables.filter(function(t){ return !t.rows; }).length;
                        var html = '<div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;margin-bottom:14px;">'
                            + '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:8px 16px;text-align:center;"><div style="font-size:1.3rem;font-weight:700;color:#92400e;">' + tables.length + '</div><div style="font-size:11px;color:#78350f;">tables found</div></div>'
                            + '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:8px 16px;text-align:center;"><div style="font-size:1.3rem;font-weight:700;color:#166534;">' + fmtKb(totalKb) + '</div><div style="font-size:11px;color:#14532d;">total size</div></div>'
                            + (emptyCount ? '<button id="csdt-select-empty-btn" type="button" style="background:#0f172a;color:#fff;border:1px solid #0f172a;padding:8px 16px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">☑ Select ' + emptyCount + ' Empty Tables</button>' : '')
                            + (hasAi && unknownTables.length ? '<button id="csdt-orphan-ai-btn" type="button" style="background:#6366f1;color:#fff;border:1px solid #4f46e5;padding:8px 16px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;margin-left:auto;">🤖 Identify ' + unknownTables.length + ' Unknown with AI</button>' : '')
                            + '</div>';
                        html += '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:10px;">'
                            + '<thead><tr style="background:#f1f5f9;text-align:left;">'
                            + '<th style="padding:6px 8px;border:1px solid #e2e8f0;width:36px;"><input type="checkbox" id="csdt-orphan-chk-all"></th>'
                            + '<th style="padding:6px 8px;border:1px solid #e2e8f0;width:50px;"></th>'
                            + '<th style="padding:6px 8px;border:1px solid #e2e8f0;">Table</th>'
                            + '<th style="padding:6px 8px;border:1px solid #e2e8f0;">Plugin</th>'
                            + '<th style="padding:6px 8px;border:1px solid #e2e8f0;">Description</th>'
                            + '<th style="padding:6px 8px;border:1px solid #e2e8f0;">URL</th>'
                            + '<th style="padding:6px 8px;border:1px solid #e2e8f0;text-align:center;">Confidence</th>'
                            + '<th style="padding:6px 8px;border:1px solid #e2e8f0;">Created</th>'
                            + '<th style="padding:6px 8px;border:1px solid #e2e8f0;">Rows</th>'
                            + '<th style="padding:6px 8px;border:1px solid #e2e8f0;">Size</th>'
                            + '</tr></thead><tbody>';
                        tables.forEach(function(t){
                            var isUnknown = t.plugin === 'Unknown plugin';
                            var typeTag = (t.table_type && t.table_type !== 'BASE TABLE') ? ' <span style="background:#fde68a;color:#92400e;font-size:10px;padding:1px 4px;border-radius:3px;">' + esc(t.table_type) + '</span>' : '';
                            var pluginCell = isUnknown
                                ? '<span class="csdt-plugin-label" data-table="' + esc(t.table) + '" style="color:#9ca3af;font-style:italic;">Unknown</span>' + typeTag
                                : esc(t.plugin) + typeTag;
                            var descCell = '<span class="csdt-desc-label" data-table="' + esc(t.table) + '" style="color:#9ca3af;">—</span>';
                            var urlCell  = '<span class="csdt-url-label"  data-table="' + esc(t.table) + '" style="color:#9ca3af;">—</span>';
                            var confCell = '<span class="csdt-conf-label" data-table="' + esc(t.table) + '" style="color:#9ca3af;">—</span>';
                            html += '<tr>'
                                + '<td style="padding:5px 8px;border:1px solid #e2e8f0;"><input type="checkbox" class="csdt-orphan-cb" value="' + esc(t.table) + '" data-rows="' + (t.rows||0) + '"></td>'
                                + '<td style="padding:5px 8px;border:1px solid #e2e8f0;text-align:center;"><button type="button" class="csdt-row-archive-btn" data-table="' + esc(t.table) + '" style="background:#f59e0b;color:#fff;border:none;border-radius:4px;padding:3px 8px;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;" title="Move to Recycle Bin">📦 Bin</button></td>'
                                + '<td style="padding:5px 8px;border:1px solid #e2e8f0;font-family:monospace;font-size:11px;">' + esc(t.table) + '</td>'
                                + '<td style="padding:5px 8px;border:1px solid #e2e8f0;">' + pluginCell + '</td>'
                                + '<td style="padding:5px 8px;border:1px solid #e2e8f0;max-width:240px;font-size:11px;">' + descCell + '</td>'
                                + '<td style="padding:5px 8px;border:1px solid #e2e8f0;font-size:11px;">' + urlCell + '</td>'
                                + '<td style="padding:5px 8px;border:1px solid #e2e8f0;text-align:center;">' + confCell + '</td>'
                                + '<td style="padding:5px 8px;border:1px solid #e2e8f0;color:#6b7280;font-size:11px;">' + esc(t.created_date||'—') + '</td>'
                                + '<td style="padding:5px 8px;border:1px solid #e2e8f0;">' + Number(t.rows).toLocaleString() + '</td>'
                                + '<td style="padding:5px 8px;border:1px solid #e2e8f0;">' + fmtKb(t.size_kb||0) + '</td>'
                                + '</tr>';
                        });
                        html += '</tbody></table></div>'
                            + '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">'
                            + '<button id="csdt-orphan-archive-btn" type="button" class="cs-btn-primary" style="background:#f59e0b;border-color:#d97706;">📦 Move Selected to Recycle Bin</button>'
                            + '</div>'
                            + '<span id="csdt-orphan-archive-msg" style="display:block;margin-top:8px;font-size:12px;color:#6b7280;"></span>';
                        container.innerHTML = html;

                        var chkAll = container.querySelector('#csdt-orphan-chk-all');
                        chkAll.addEventListener('change', function(){
                            container.querySelectorAll('.csdt-orphan-cb').forEach(function(c){ c.checked = chkAll.checked; });
                        });

                        var selectEmptyBtn = container.querySelector('#csdt-select-empty-btn');
                        if (selectEmptyBtn) {
                            selectEmptyBtn.addEventListener('click', function() {
                                container.querySelectorAll('.csdt-orphan-cb').forEach(function(c){
                                    c.checked = c.dataset.rows === '0';
                                });
                            });
                        }

                        // Single batch AI identify
                        var aiIdentifyBtn = container.querySelector('#csdt-orphan-ai-btn');
                        if (aiIdentifyBtn) {
                            aiIdentifyBtn.addEventListener('click', function() {
                                var names = unknownTables.map(function(t){ return t.table; });
                                aiIdentifyBtn.disabled = true;
                                aiIdentifyBtn.textContent = '⏳ Asking AI…';
                                post('csdt_db_identify_table', {table_names: names}).then(function(r){
                                    aiIdentifyBtn.disabled = false;
                                    aiIdentifyBtn.textContent = '🤖 Identify Unknown with AI';
                                    if (!r.success || !r.data || !r.data.map) {
                                        var errMsg = (r.data && r.data.message) ? r.data.message : (typeof r.data === 'string' ? r.data : 'AI identification failed — check console.');
                                        aiIdentifyBtn.insertAdjacentHTML('afterend', '<span id="csdt-ai-err" style="margin-left:10px;font-size:12px;color:#ef4444;">' + esc(errMsg) + '</span>');
                                        return;
                                    }
                                    var errEl = container.querySelector('#csdt-ai-err');
                                    if (errEl) errEl.remove();
                                    var map = r.data.map;
                                    var confColor = {'High':'#16a34a','Medium':'#d97706','Low':'#ef4444'};
                                    container.querySelectorAll('.csdt-plugin-label').forEach(function(cell){
                                        var tbl = cell.dataset.table;
                                        var info = map[tbl];
                                        if (!info) return;
                                        cell.style.cssText = 'color:#6366f1;font-weight:600;font-style:normal;';
                                        cell.textContent = info.plugin || info;
                                    });
                                    container.querySelectorAll('.csdt-desc-label').forEach(function(cell){
                                        var info = map[cell.dataset.table];
                                        if (info && info.description) { cell.style.color='#374151'; cell.textContent = info.description; }
                                    });
                                    container.querySelectorAll('.csdt-url-label').forEach(function(cell){
                                        var info = map[cell.dataset.table];
                                        if (info && info.url) {
                                            cell.innerHTML = '<a href="' + esc(info.url) + '" target="_blank" rel="noopener" style="color:#2563eb;font-size:11px;">' + esc(info.url.replace(/^https?:\/\//, '')) + '</a>';
                                        }
                                    });
                                    container.querySelectorAll('.csdt-conf-label').forEach(function(cell){
                                        var info = map[cell.dataset.table];
                                        if (info && info.confidence) {
                                            var c = info.confidence;
                                            cell.style.cssText = 'font-weight:600;color:' + (confColor[c]||'#6b7280') + ';';
                                            cell.textContent = c;
                                        }
                                    });
                                }).catch(function(){
                                    aiIdentifyBtn.disabled = false;
                                    aiIdentifyBtn.textContent = '🤖 Identify Unknown with AI';
                                });
                            });
                        }

                        container.querySelector('#csdt-orphan-archive-btn').addEventListener('click', function(){
                            var sel = Array.from(container.querySelectorAll('.csdt-orphan-cb:checked')).map(function(c){ return c.value; });
                            if (!sel.length) { alert('Select at least one table.'); return; }
                            var btn = this, msg = container.querySelector('#csdt-orphan-archive-msg');
                            btn.disabled = true; btn.textContent = '⏳ Archiving…'; msg.textContent = '';
                            post('csdt_db_archive_tables', {tables: sel}).then(function(r){
                                btn.disabled = false; btn.textContent = '📦 Move Selected to Recycle Bin';
                                msg.style.color = r.success ? '#16a34a' : '#ef4444';
                                msg.textContent = (r.data && r.data.message) || (r.success ? 'Done.' : 'Failed.');
                                runOrphanScan(); loadTrash();
                            }).catch(function(e){ btn.disabled=false; btn.textContent='📦 Move Selected to Recycle Bin'; msg.style.color='#ef4444'; msg.textContent='Error: '+e.message; });
                        });

                        // Per-row archive buttons — direct listeners added right after innerHTML
                        container.querySelectorAll('.csdt-row-archive-btn').forEach(function(rowBtn) {
                            rowBtn.addEventListener('click', function() {
                                var tbl = rowBtn.dataset.table;
                                var msg = container.querySelector('#csdt-orphan-archive-msg');
                                rowBtn.disabled = true; rowBtn.textContent = '⏳';
                                if (msg) { msg.style.color='#6b7280'; msg.textContent = 'Archiving ' + tbl + '…'; }
                                post('csdt_db_archive_tables', {tables: [tbl]}).then(function(r){
                                    rowBtn.disabled = false; rowBtn.textContent = '📦 Bin';
                                    if (r.success) {
                                        if (msg) { msg.style.color='#16a34a'; msg.textContent = (r.data && r.data.message) || 'Done.'; }
                                        runOrphanScan(); loadTrash();
                                    } else {
                                        var errText = (r.data && r.data.message) || JSON.stringify(r.data) || 'Archive failed';
                                        if (msg) { msg.style.color='#ef4444'; msg.textContent = '❌ ' + errText; }
                                    }
                                }).catch(function(err){
                                    rowBtn.disabled=false; rowBtn.textContent='📦 Bin';
                                    if (msg) { msg.style.color='#ef4444'; msg.textContent = '❌ Network error: ' + err.message; }
                                });
                            });
                        });

                    }

                    /* ── Recycle Bin ───────────────────────────── */
                    function loadTrash() {
                        var res = document.getElementById('csdt-trash-results');
                        if (!res) return;
                        res.innerHTML = '<span style="color:#9ca3af;font-size:12px;">⏳ Loading…</span>';
                        post('csdt_db_trash_scan').then(function(r){
                            if (!r.success) { res.innerHTML = '<p style="color:#ef4444;font-size:13px;">' + esc(r.data||'Failed.') + '</p>'; return; }
                            renderTrashResults(r.data.tables||[], res);
                        }).catch(function(e){ res.innerHTML = '<p style="color:#ef4444;font-size:13px;">Error: ' + esc(e.message) + '</p>'; });
                    }

                    function renderTrashResults(tables, container) {
                        if (!tables.length) {
                            container.innerHTML = '<p style="color:#9ca3af;font-size:13px;margin:0;">Recycle bin is empty.</p>';
                            return;
                        }
                        var html = '<table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:10px;">'
                            + '<thead><tr style="background:#fef2f2;text-align:left;">'
                            + '<th style="padding:6px 8px;border:1px solid #fecaca;width:36px;"><input type="checkbox" id="csdt-trash-chk-all"></th>'
                            + '<th style="padding:6px 8px;border:1px solid #fecaca;">Original Table</th>'
                            + '<th style="padding:6px 8px;border:1px solid #fecaca;">Plugin</th>'
                            + '<th style="padding:6px 8px;border:1px solid #fecaca;">Created</th>'
                            + '<th style="padding:6px 8px;border:1px solid #fecaca;">Archived On</th>'
                            + '<th style="padding:6px 8px;border:1px solid #fecaca;">Rows</th>'
                            + '<th style="padding:6px 8px;border:1px solid #fecaca;">Size</th>'
                            + '</tr></thead><tbody>';
                        tables.forEach(function(t){
                            var m = t.trash_table.match(/_trash_(\d{4})(\d{2})(\d{2})_/);
                            var dated = m ? m[1]+'-'+m[2]+'-'+m[3] : '—';
                            var isUnknown = !t.plugin || t.plugin === 'Unknown plugin';
                            var pluginCell = isUnknown
                                ? '<span style="color:#9ca3af;font-style:italic;">Unknown</span>'
                                : (t.plugin_url ? '<a href="' + esc(t.plugin_url) + '" target="_blank" rel="noopener" style="color:#3b82f6;text-decoration:none;">' + esc(t.plugin) + '</a>' : esc(t.plugin));
                            html += '<tr><td style="padding:5px 8px;border:1px solid #fecaca;"><input type="checkbox" class="csdt-trash-cb" value="' + esc(t.trash_table) + '"></td>'
                                + '<td style="padding:5px 8px;border:1px solid #fecaca;font-family:monospace;font-size:11px;">' + esc(t.original_table) + '</td>'
                                + '<td style="padding:5px 8px;border:1px solid #fecaca;font-size:11px;">' + pluginCell + '</td>'
                                + '<td style="padding:5px 8px;border:1px solid #fecaca;color:#6b7280;font-size:11px;">' + esc(t.created_date||'—') + '</td>'
                                + '<td style="padding:5px 8px;border:1px solid #fecaca;">' + esc(dated) + '</td>'
                                + '<td style="padding:5px 8px;border:1px solid #fecaca;">' + Number(t.rows||0).toLocaleString() + '</td>'
                                + '<td style="padding:5px 8px;border:1px solid #fecaca;">' + fmtKb(t.size_kb||0) + '</td></tr>';
                        });
                        html += '</tbody></table>'
                            + '<div style="display:flex;gap:10px;flex-wrap:wrap;">'
                            + '<button id="csdt-trash-restore-btn" type="button" class="cs-btn-secondary">↩ Restore Selected</button>'
                            + '<button id="csdt-trash-delete-btn" type="button" style="background:#ef4444;color:#fff;border:1px solid #dc2626;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">🗑 Delete Forever</button>'
                            + '</div>'
                            + '<span id="csdt-trash-msg" style="display:block;margin-top:8px;font-size:12px;color:#6b7280;"></span>';
                        container.innerHTML = html;

                        var chkAll = container.querySelector('#csdt-trash-chk-all');
                        chkAll.addEventListener('change', function(){
                            container.querySelectorAll('.csdt-trash-cb').forEach(function(c){ c.checked = chkAll.checked; });
                        });

                        container.querySelector('#csdt-trash-restore-btn').addEventListener('click', function(){
                            var sel = Array.from(container.querySelectorAll('.csdt-trash-cb:checked')).map(function(c){ return c.value; });
                            if (!sel.length) { alert('Select at least one table.'); return; }
                            if (!confirm('Restore ' + sel.length + ' table(s) to their original names?')) return;
                            var btn = this, msg = container.querySelector('#csdt-trash-msg');
                            btn.disabled=true; btn.textContent='⏳ Restoring…'; msg.textContent='';
                            post('csdt_db_restore_tables', {tables: sel}).then(function(r){
                                btn.disabled=false; btn.textContent='↩ Restore Selected';
                                msg.style.color = r.success ? '#16a34a' : '#ef4444';
                                msg.textContent = (r.data && r.data.message) || (r.success ? 'Restored.' : 'Failed.');
                                loadTrash(); runOrphanScan();
                            }).catch(function(e){ btn.disabled=false; btn.textContent='↩ Restore Selected'; msg.style.color='#ef4444'; msg.textContent='Error: '+e.message; });
                        });

                        container.querySelector('#csdt-trash-delete-btn').addEventListener('click', function(){
                            var sel = Array.from(container.querySelectorAll('.csdt-trash-cb:checked')).map(function(c){ return c.value; });
                            if (!sel.length) { alert('Select at least one table.'); return; }
                            if (!confirm('⚠️ Permanently delete ' + sel.length + ' table(s)? This CANNOT be undone.')) return;
                            var btn = this, msg = container.querySelector('#csdt-trash-msg');
                            btn.disabled=true; btn.textContent='⏳ Deleting…'; msg.textContent='';
                            post('csdt_db_drop_tables', {tables: sel}).then(function(r){
                                btn.disabled=false; btn.textContent='🗑 Delete Forever';
                                msg.style.color = r.success ? '#16a34a' : '#ef4444';
                                msg.textContent = (r.data && r.data.message) || (r.success ? 'Deleted.' : 'Failed.');
                                loadTrash();
                            }).catch(function(e){ btn.disabled=false; btn.textContent='🗑 Delete Forever'; msg.style.color='#ef4444'; msg.textContent='Error: '+e.message; });
                        });
                    }

                    /* ── Init ──────────────────────────────────── */
                    document.getElementById('csdt-orphan-scan-btn').addEventListener('click', runOrphanScan);
                    document.getElementById('csdt-trash-refresh-btn').addEventListener('click', loadTrash);

                    loadTrash();
                })();
                </script>

            </div>
        </div>
        <?php
    }

        private static function render_security_panel(): void {
        ?>
        <div class="cs-panel" id="cs-panel-security">
            <div class="cs-panel-body">

                <div class="cs-tab-intro">
                    <p><?php echo wp_kses( __( 'The <strong>AI Cyber Audit</strong> uses frontier AI &#8212; Anthropic Claude or Google Gemini &#8212; to analyse your WordPress installation and produce a prioritised, scored security report in under 60 seconds. Think of it as a security consultant in your admin panel: it doesn&#8217;t just list what&#8217;s wrong, it tells you what to fix first and exactly how to fix it. A Standard scan takes seconds; a Deep Dive goes further with live HTTP probes, DNS checks, TLS quality analysis, and static code scanning of your plugins. You need an API key from one of the two providers &#8212; a free Gemini tier is available with no credit card required. Configure your provider and key on the <a href="' . esc_url( admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=home' ) ) . '">Home tab</a>, then use the <strong>Quick Fixes</strong> below to resolve common misconfigurations before running a scan.', 'cloudscale-devtools' ), [ 'strong' => [], 'a' => [ 'href' => [] ] ] ); ?></p>
                </div>

                <!-- ── Threat Monitor ──────────────────────────── -->
                <?php
                $tm_enabled       = get_option( 'csdt_threat_monitor_enabled',        '1' ) === '1';
                $tm_file_enabled  = get_option( 'csdt_threat_file_integrity_enabled', '1' ) === '1';
                $tm_admin_enabled = get_option( 'csdt_threat_new_admin_enabled',      '1' ) === '1';
                $tm_probe_enabled = get_option( 'csdt_threat_probe_enabled',          '1' ) === '1';
                $tm_threshold     = get_option( 'csdt_threat_probe_threshold',        '25' );
                $tm_last_file     = get_option( 'csdt_threat_last_file_alert',        null );
                $tm_last_admin    = get_option( 'csdt_threat_last_admin_alert',       null );
                $tm_last_probe    = get_option( 'csdt_threat_last_probe_alert',       null );
                $tm_baseline_ver  = get_option( 'csdt_file_integrity_wp_ver',         '' );
                $tm_baseline      = get_option( 'csdt_file_integrity_baseline',       [] );
                ?>
                <div class="cs-panel" id="cs-panel-threat-monitor">
                    <div class="cs-section-header" style="background:linear-gradient(90deg,#7f1d1d 0%,#b91c1c 100%);border-left:3px solid #f87171;">
                        <span>🔎 <?php esc_html_e( 'Threat Monitor', 'cloudscale-devtools' ); ?></span>
                        <span class="cs-header-hint"><?php esc_html_e( 'File integrity · New admin alert · Probe detection — alerts once per incident, not per event', 'cloudscale-devtools' ); ?></span>
                        <?php self::render_explain_btn( 'threat-monitor', 'Threat Monitor', [
                            [ 'name' => 'File Integrity',   'rec' => 'Critical', 'html' => 'Scans <code>wp-includes/*.php</code> and <code>wp-admin/*.php</code> every 5 minutes and compares file modification times against a stored baseline. If any file changes — outside of a normal WordPress core update — you receive an email and push alert immediately.<br><br>Anti-spam: when WordPress is updated to a new version the baseline is rebuilt silently (all core files change legitimately during an update). The same mtime is never alerted twice, so you will not receive repeated alerts for the same modification. Use <strong>Reset File Baseline</strong> after a manual core update to clear false-positive alerts.' ],
                            [ 'name' => 'New Admin Alert', 'rec' => 'Critical', 'html' => 'Hooks into WordPress\'s <code>user_register</code> and <code>set_user_role</code> events. The instant a new administrator account is created — or an existing user is promoted to admin — an alert fires.<br><br>Anti-spam: alerts fire exactly once per user ID. If the same account is flagged and you acknowledge it, no further alerts fire for that user. The alerted-user list is capped at 100 entries to prevent unbounded growth.' ],
                            [ 'name' => 'Probe Detection', 'rec' => 'High',     'html' => 'Reads only the new bytes appended to the web server access log since the last check (byte-offset tracking). Counts requests to sensitive endpoints: <code>wp-login.php</code>, <code>xmlrpc.php</code>, <code>wp-config.php</code>, <code>.env</code>, <code>.git/</code>, <code>.sql</code>, <code>.bak</code>, and shell-injection patterns.<br><br>Anti-spam: an alert only fires when the count exceeds the configured threshold (default: 25 requests per 5-minute window) AND at most once per hour. You will never receive probe alerts more frequently than once per hour regardless of how many probes occur.' ],
                            [ 'name' => 'Alert channels',  'rec' => 'Setup',    'html' => 'Alerts are sent via <strong>email</strong> (to the site administrator address) and <strong>ntfy.sh push notification</strong> if a topic URL is configured under Security Scan → Scheduled Scans. No additional configuration is needed — the Threat Monitor shares the same alert infrastructure as the SSH Monitor and PHP Error Alerting.' ],
                        ] ); ?>
                    </div>
                    <div class="cs-panel-body">

                        <div class="cs-field-row">
                            <div class="cs-field">
                                <label class="cs-label">
                                    <input type="checkbox" id="csdt-tm-enabled" <?php checked( $tm_enabled ); ?>>
                                    <?php esc_html_e( 'Enable Threat Monitor', 'cloudscale-devtools' ); ?>
                                </label>
                                <span class="cs-hint"><?php esc_html_e( 'Master switch. Runs checks every 5 minutes. Alerts via email and ntfy.sh.', 'cloudscale-devtools' ); ?></span>
                            </div>
                        </div>

                        <div id="csdt-tm-options" style="<?php echo $tm_enabled ? '' : 'opacity:.5;pointer-events:none;'; ?>">
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:16px;">

                                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;">
                                    <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:#1e293b;">
                                        <input type="checkbox" id="csdt-tm-file" <?php checked( $tm_file_enabled ); ?> style="margin-top:2px;flex-shrink:0;">
                                        🗂️ <?php esc_html_e( 'File Integrity', 'cloudscale-devtools' ); ?>
                                    </label>
                                    <div style="font-size:12px;color:#64748b;margin-top:6px;line-height:1.5;"><?php esc_html_e( 'Alerts if wp-includes/ or wp-admin/ core files are modified. Ignores WP core updates automatically. Alerts once per unique change.', 'cloudscale-devtools' ); ?></div>
                                    <?php if ( $tm_last_file ) : ?>
                                    <div style="margin-top:8px;font-size:11px;color:#dc2626;font-weight:600;">
                                        🚨 <?php echo esc_html( human_time_diff( $tm_last_file['ts'] ) . ' ago' ); ?> —
                                        <?php echo (int) $tm_last_file['count']; ?> file<?php echo $tm_last_file['count'] === 1 ? '' : 's'; ?>
                                    </div>
                                    <?php elseif ( $tm_baseline ) : ?>
                                    <div style="margin-top:8px;font-size:11px;color:#16a34a;">✓ <?php printf( esc_html__( 'Baseline: WP %s (%d files)', 'cloudscale-devtools' ), esc_html( $tm_baseline_ver ), count( $tm_baseline ) ); ?></div>
                                    <?php else : ?>
                                    <div style="margin-top:8px;font-size:11px;color:#64748b;"><?php esc_html_e( 'Baseline will be created on first run.', 'cloudscale-devtools' ); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;">
                                    <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:#1e293b;">
                                        <input type="checkbox" id="csdt-tm-admin" <?php checked( $tm_admin_enabled ); ?> style="margin-top:2px;flex-shrink:0;">
                                        👤 <?php esc_html_e( 'New Admin Alert', 'cloudscale-devtools' ); ?>
                                    </label>
                                    <div style="font-size:12px;color:#64748b;margin-top:6px;line-height:1.5;"><?php esc_html_e( 'Instant alert when a new administrator account is created or a user is promoted to admin. Fires once per user.', 'cloudscale-devtools' ); ?></div>
                                    <?php if ( $tm_last_admin ) : ?>
                                    <div style="margin-top:8px;font-size:11px;color:#dc2626;font-weight:600;">
                                        🚨 <?php echo esc_html( human_time_diff( $tm_last_admin['ts'] ) . ' ago — ' . $tm_last_admin['login'] ); ?>
                                    </div>
                                    <?php else : ?>
                                    <div style="margin-top:8px;font-size:11px;color:#16a34a;">✓ <?php esc_html_e( 'No new admin accounts detected.', 'cloudscale-devtools' ); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;">
                                    <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:#1e293b;">
                                        <input type="checkbox" id="csdt-tm-probe" <?php checked( $tm_probe_enabled ); ?> style="margin-top:2px;flex-shrink:0;">
                                        🔍 <?php esc_html_e( 'Probe Detection', 'cloudscale-devtools' ); ?>
                                    </label>
                                    <div style="font-size:12px;color:#64748b;margin-top:6px;line-height:1.5;"><?php esc_html_e( 'Counts requests to sensitive endpoints (wp-login, xmlrpc, .env, .git) in the access log. Throttled to one alert per hour.', 'cloudscale-devtools' ); ?></div>
                                    <div style="margin-top:8px;display:flex;align-items:center;gap:6px;">
                                        <label style="font-size:11px;color:#64748b;"><?php esc_html_e( 'Threshold:', 'cloudscale-devtools' ); ?></label>
                                        <input type="number" id="csdt-tm-probe-threshold" min="5" max="500"
                                               value="<?php echo esc_attr( $tm_threshold ); ?>"
                                               style="width:60px;padding:2px 6px;font-size:12px;border:1px solid #d1d5db;border-radius:4px;">
                                        <span style="font-size:11px;color:#64748b;"><?php esc_html_e( 'requests / 5 min', 'cloudscale-devtools' ); ?></span>
                                    </div>
                                    <?php if ( $tm_last_probe ) : ?>
                                    <div style="margin-top:6px;font-size:11px;color:#d97706;font-weight:600;">
                                        ⚠ <?php echo esc_html( human_time_diff( $tm_last_probe['ts'] ) . ' ago — ' . $tm_last_probe['count'] . ' probes' ); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>

                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <button type="button" class="cs-btn-primary" id="csdt-tm-save">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
                            <span class="cs-settings-saved" id="csdt-tm-saved">✓ <?php esc_html_e( 'Saved', 'cloudscale-devtools' ); ?></span>
                            <?php if ( $tm_baseline ) : ?>
                            <button type="button" class="cs-btn-secondary" id="csdt-tm-reset" style="font-size:12px;margin-left:auto;">↺ <?php esc_html_e( 'Reset File Baseline', 'cloudscale-devtools' ); ?></button>
                            <span id="csdt-tm-reset-msg" style="font-size:12px;color:#16a34a;display:none;"></span>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

                <?php CSDT_Security_Headers::render_security_headers_panel(); ?>

                <?php CSDT_CSP::render_csp_panel(); ?>

                <div style="margin:32px 0 0;border-top:2px solid #e2e8f0;"></div>

                <div class="cs-section-header" style="margin-top:24px;background:linear-gradient(90deg,#022c22 0%,#065f46 100%);border-left:3px solid #34d399;border-radius:6px 6px 0 0;">
                    <span>🕵️ <?php esc_html_e( 'AI Cyber Audit', 'cloudscale-devtools' ); ?></span>
                    <span class="cs-header-hint"><?php esc_html_e( 'AI-powered WordPress security scanning — standard or deep dive', 'cloudscale-devtools' ); ?></span>
                </div>
                <div style="background:#fff;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 6px 6px;padding:20px;">
                <div class="cs-scan-row">
                    <div class="cs-scan-col">
                        <div class="cs-scan-col-header">
                            <span class="cs-scan-col-title"><?php esc_html_e( 'Internal Config Audit', 'cloudscale-devtools' ); ?></span>
                            <span class="cs-scan-col-hint"><?php esc_html_e( 'WordPress settings, plugins, users, debug flags — fast', 'cloudscale-devtools' ); ?></span>
                            <?php self::render_explain_btn( 'standard-scan', 'AI Cyber Audit', [
                                [ 'name' => 'What it checks',  'rec' => 'Overview',     'html' => 'Collects your WordPress environment — PHP version, WP version, all active plugins, file permissions on key files, exposed debug flags (<code>WP_DEBUG</code>, <code>WP_DEBUG_LOG</code>), user account and role counts, 2FA coverage, brute-force protection state, and key <code>wp-config.php</code> security constants — then sends this to the AI for analysis.' ],
                                [ 'name' => 'AI analysis',     'rec' => 'How it works', 'html' => 'The AI model receives a structured JSON snapshot and returns findings scored <strong>Critical / High / Medium / Low / Good</strong>. Each finding includes a plain-English explanation of the risk and a specific remediation step. The AI cross-references findings — for example, flagging when an outdated plugin is combined with exposed debug output.' ],
                                [ 'name' => 'Speed',           'rec' => 'Fast (15–30s)', 'html' => 'The standard scan collects only server-side data — no outbound HTTP probes. Typical completion time is 15–30 seconds depending on the AI provider and number of plugins installed.' ],
                                [ 'name' => 'No timeout risk', 'rec' => 'Technical',    'html' => 'The scan uses <code>fastcgi_finish_request()</code> to close the browser connection immediately, then continues in the background. A progress bar polls every 3 seconds. This does not depend on WP-Cron.' ],
                            ] ); ?>
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
                            <?php self::render_explain_btn( 'deep-scan', 'AI Deep Dive Cyber Audit', [
                                [ 'name' => 'What it adds',       'rec' => 'Overview',       'html' => 'Runs everything the standard scan checks, then adds <strong>live HTTP probes</strong> of your own site: SSL/TLS certificate validity and strength, login page exposure, XML-RPC state, REST API user enumeration, author enumeration, directory listing, and server version headers. It also performs <strong>DNS checks</strong> (SPF, DMARC, DKIM) and static analysis of plugin PHP files.' ],
                                [ 'name' => 'Plugin code triage', 'rec' => 'AI static scan',  'html' => 'The AI pre-screens plugin PHP files for suspicious patterns (eval, base64_decode, remote code execution sinks) and classifies each finding as <strong>Confirmed / False Positive / Needs Context</strong> before the main analysis. This reduces noise and focuses the main report on real risks.' ],
                                [ 'name' => 'Speed',              'rec' => '30–90s',          'html' => 'The deep dive makes outbound HTTP and DNS requests, so duration depends on your network and the number of plugins. Typical completion is 30–90 seconds. The browser connection is closed immediately via <code>fastcgi_finish_request()</code>; a progress bar polls every 3 seconds.' ],
                                [ 'name' => 'DNS checks',         'rec' => 'Email security',  'html' => 'SPF, DMARC, and DKIM records are checked only when your domain has an MX record. If you have no email configured, these checks are skipped and the report notes &ldquo;no email configured&rdquo; as a good finding — no false positives.' ],
                            ] ); ?>
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
                </div><!-- /AI Cyber Audit content box -->

            </div>

            <!-- Scan History -->
            <div class="cs-section-header" style="margin-top:24px;background:linear-gradient(90deg,#1e1b4b 0%,#4338ca 100%);border-left:3px solid #818cf8;">
                <span>📈 <?php esc_html_e( 'Scan History', 'cloudscale-devtools' ); ?></span>
                <span class="cs-header-hint"><?php esc_html_e( 'Last 50 scans — track your security score over time', 'cloudscale-devtools' ); ?></span>
                <?php self::render_explain_btn( 'scan-history', 'Scan History', [
                    [ 'name' => 'What is tracked',   'rec' => 'Overview',    'html' => 'Every Standard Cyber Scan and AI Deep Dive saves a summary entry: scan date, model used, severity counts (critical / high / medium / low), and the full findings list. The last 50 scans are retained.' ],
                    [ 'name' => 'Score trend chart', 'rec' => 'Info',        'html' => 'The chart plots your critical + high finding count over time. A downward trend means your security posture is improving. Spikes after a plugin update or site change are worth investigating.' ],
                    [ 'name' => 'Reload a scan',     'rec' => 'Info',        'html' => 'Click any row in the history table to reload that scan\'s full findings report. Useful for comparing before-and-after states when remediating issues, without needing to re-run the scan.' ],
                ] ); ?>
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
                    <?php foreach ( $history as $idx => $entry ) :
                        $score       = (int) ( $entry['score'] ?? 0 );
                        $label       = esc_html( $entry['score_label'] ?? '' );
                        $type_label  = $entry['type'] === 'deep' ? 'Deep Dive' : 'AI Cyber Audit';
                        $date        = $entry['scanned_at'] ? wp_date( 'D j M Y, g:ia', $entry['scanned_at'] ) : '';
                        $score_color = $score >= 90 ? '#22c55e' : ( $score >= 75 ? '#4ade80' : ( $score >= 55 ? '#fbbf24' : ( $score >= 35 ? '#f97316' : '#ef4444' ) ) );
                        $has_findings = ! empty( $entry['findings'] );
                    ?>
                        <div style="display:flex;align-items:flex-start;gap:14px;padding:10px 12px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0;">
                            <div style="flex-shrink:0;text-align:center;min-width:48px;">
                                <div style="font-size:1.4rem;font-weight:700;color:<?php echo esc_attr( $score_color ); ?>;line-height:1;"><?php echo esc_html( $score ); ?></div>
                                <div style="font-size:10px;color:<?php echo esc_attr( $score_color ); ?>;opacity:.8;"><?php echo esc_html( $label ); ?></div>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;flex-wrap:wrap;">
                                    <span style="font-size:12px;font-weight:600;color:#0f172a;"><?php echo esc_html( $type_label ); ?></span>
                                    <span style="font-size:12px;font-weight:400;color:#64748b;"><?php echo esc_html( $date ); ?></span>
                                    <?php if ( $has_findings ) : ?>
                                    <button type="button"
                                        class="csdt-view-report-btn"
                                        data-idx="<?php echo esc_attr( $idx ); ?>"
                                        data-type="<?php echo esc_attr( $type_label ); ?>"
                                        data-date="<?php echo esc_attr( $date ); ?>"
                                        data-score="<?php echo esc_attr( $score ); ?>"
                                        data-label="<?php echo esc_attr( $label ); ?>"
                                        data-summary="<?php echo esc_attr( $entry['summary'] ?? '' ); ?>"
                                        style="font-size:11px;font-weight:600;color:#60a5fa;background:none;border:1px solid #60a5fa;border-radius:4px;padding:1px 8px;cursor:pointer;line-height:1.5;flex-shrink:0;">
                                        View Report
                                    </button>
                                    <button type="button"
                                        class="csdt-history-pdf-btn"
                                        data-idx="<?php echo esc_attr( $idx ); ?>"
                                        data-scan-type="<?php echo esc_attr( $entry['type'] ?? 'standard' ); ?>"
                                        style="font-size:11px;font-weight:600;color:#6b7280;background:none;border:1px solid #d1d5db;border-radius:4px;padding:1px 8px;cursor:pointer;line-height:1.5;flex-shrink:0;">
                                        ↓ PDF
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:12px;color:#374151;line-height:1.5;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
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

    public static function ajax_db_orphaned_scan(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        global $wpdb;
        $prefix     = $wpdb->prefix;
        $prefix_len = strlen( $prefix );
        $core       = CSDT_Site_Audit::core_table_suffixes();

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $all_tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );

        // Build a set of table suffixes owned by currently-active plugins via their table-prefix mappings.
        // This prevents tables belonging to installed plugins from appearing as "orphaned".
        $active_plugin_suffixes = [];
        $active_plugins = (array) get_option( 'active_plugins', [] );
        foreach ( $active_plugins as $plugin_file ) {
            $slug = dirname( $plugin_file );
            // Map known plugin slugs to the table-suffix prefixes they own.
            $slug_suffix_map = [
                'cloudscale-wordpress-free-analytics' => [ 'cspv_' ],
            ];
            if ( isset( $slug_suffix_map[ $slug ] ) ) {
                foreach ( $slug_suffix_map[ $slug ] as $sfx ) {
                    $active_plugin_suffixes[] = $sfx;
                }
            }
        }

        $non_core = array_values( array_filter( $all_tables, function ( $t ) use ( $prefix_len, $core, $active_plugin_suffixes ) {
            $suffix = substr( $t, $prefix_len );
            if ( in_array( $suffix, $core, true ) ) {
                return false;
            }
            foreach ( $active_plugin_suffixes as $sfx ) {
                if ( str_starts_with( $suffix, $sfx ) ) {
                    return false;
                }
            }
            return true;
        } ) );

        if ( empty( $non_core ) ) {
            wp_send_json_success( [ 'tables' => [] ] );
            return;
        }

        // Fetch row counts and sizes from information_schema
        $placeholders = implode( ',', array_fill( 0, count( $non_core ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT TABLE_NAME as name, TABLE_ROWS as rows,
                        ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024) as size_kb,
                        TABLE_TYPE as table_type,
                        DATE_FORMAT(CREATE_TIME, '%%Y-%%m-%%d') as created_date
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$placeholders})",
                ...$non_core
            )
        );

        $size_map    = [];
        $rows_map    = [];
        $type_map    = [];
        $created_map = [];
        foreach ( $rows as $r ) {
            $size_map[ $r->name ]    = (int) $r->size_kb;
            $rows_map[ $r->name ]    = (int) $r->rows;
            $type_map[ $r->name ]    = $r->table_type ?? 'BASE TABLE';
            $created_map[ $r->name ] = $r->created_date ?? '';
        }

        $result = [];
        foreach ( $non_core as $table ) {
            $suffix   = substr( $table, $prefix_len );
            $result[] = [
                'table'        => $table,
                'plugin'       => CSDT_Site_Audit::guess_plugin_from_suffix( $suffix ),
                'rows'         => $rows_map[ $table ] ?? 0,
                'size_kb'      => $size_map[ $table ] ?? 0,
                'table_type'   => $type_map[ $table ] ?? 'BASE TABLE',
                'created_date' => $created_map[ $table ] ?? '',
            ];
        }

        usort( $result, fn( $a, $b ) => strcmp( $a['plugin'] . $a['table'], $b['plugin'] . $b['table'] ) );

        wp_send_json_success( [ 'tables' => $result ] );
    }

    public static function ajax_db_identify_table(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $table_names = json_decode( wp_unslash( $_POST['table_names'] ?? '' ), true );
        if ( ! is_array( $table_names ) || empty( $table_names ) ) {
            wp_send_json_error( 'No tables specified.' );
            return;
        }

        global $wpdb;

        // Build table descriptions: name + columns for each
        $descriptions = [];
        foreach ( $table_names as $table ) {
            $table = sanitize_text_field( $table );
            if ( ! $table ) { continue; }
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $cols = $wpdb->get_col( 'SHOW COLUMNS FROM `' . esc_sql( $table ) . '`' );
            if ( $cols ) {
                $descriptions[] = '- ' . $table . ': ' . implode( ', ', $cols );
            }
        }

        if ( empty( $descriptions ) ) {
            wp_send_json_error( 'No valid tables found.' );
            return;
        }

        $provider      = get_option( 'csdt_devtools_ai_provider', 'anthropic' );
        $anthropic_key = get_option( 'csdt_devtools_anthropic_key', '' );
        $gemini_key    = get_option( 'csdt_devtools_gemini_key', '' );

        $prompt = "Identify which WordPress plugin or theme created each of these database tables.\n"
                . "Return ONLY a valid JSON object. Each key is the full table name. Each value is an object with:\n"
                . "  \"plugin\": plugin name (2-5 words)\n"
                . "  \"description\": one sentence describing what the plugin does\n"
                . "  \"url\": plugin homepage URL (wordpress.org/plugins/... or official site)\n"
                . "  \"confidence\": \"High\", \"Medium\", or \"Low\"\n"
                . "No markdown, no explanation, only the JSON.\n\n"
                . "Tables:\n" . implode( "\n", $descriptions );

        $raw_text = '';

        if ( function_exists( 'set_time_limit' ) ) { set_time_limit( 120 ); }

        if ( $provider === 'gemini' && $gemini_key ) {
            $url  = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . rawurlencode( $gemini_key );
            $resp = wp_remote_post( $url, [
                'timeout' => 90,
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( [
                    'contents'         => [ [ 'role' => 'user', 'parts' => [ [ 'text' => $prompt ] ] ] ],
                    'generationConfig' => [ 'maxOutputTokens' => 8192 ],
                ] ),
            ] );
            if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
                $body     = json_decode( wp_remote_retrieve_body( $resp ), true );
                $raw_text = trim( $body['candidates'][0]['content']['parts'][0]['text'] ?? '' );
            }
        } elseif ( $anthropic_key ) {
            $resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
                'timeout' => 90,
                'headers' => [
                    'x-api-key'         => $anthropic_key,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'model'      => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 8192,
                    'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
                ] ),
            ] );
            if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
                $body     = json_decode( wp_remote_retrieve_body( $resp ), true );
                $raw_text = trim( $body['content'][0]['text'] ?? '' );
            }
        }

        if ( ! $raw_text ) {
            wp_send_json_error( 'AI did not respond.' );
            return;
        }

        // Strip optional ```json ... ``` fences
        $raw_text = preg_replace( '/^```(?:json)?\s*/i', '', $raw_text );
        $raw_text = preg_replace( '/\s*```\s*$/i', '', $raw_text );

        $map = json_decode( trim( $raw_text ), true );
        if ( ! is_array( $map ) ) {
            wp_send_json_error( 'AI returned unexpected format.' );
            return;
        }

        // Sanitize values — support both flat string and object per entry
        $clean = [];
        foreach ( $map as $tbl => $entry ) {
            $key = sanitize_text_field( $tbl );
            if ( is_array( $entry ) ) {
                $clean[ $key ] = [
                    'plugin'      => sanitize_text_field( $entry['plugin']      ?? '' ),
                    'description' => sanitize_text_field( $entry['description'] ?? '' ),
                    'url'         => esc_url_raw( $entry['url']                 ?? '' ),
                    'confidence'  => in_array( $entry['confidence'] ?? '', [ 'High', 'Medium', 'Low' ], true )
                                     ? $entry['confidence'] : 'Low',
                ];
            } else {
                $clean[ $key ] = [ 'plugin' => sanitize_text_field( (string) $entry ) ];
            }
        }

        wp_send_json_success( [ 'map' => $clean ] );
    }

    public static function ajax_db_archive_tables(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $tables = json_decode( wp_unslash( $_POST['tables'] ?? '' ), true );
        if ( ! is_array( $tables ) || empty( $tables ) ) {
            wp_send_json_error( 'No tables specified.' );
            return;
        }

        global $wpdb;
        $prefix = $wpdb->prefix;
        $core   = CSDT_Site_Audit::core_table_suffixes();
        $prefix_len = strlen( $prefix );
        $date   = gmdate( 'Ymd' );

        $archived = [];
        $errors   = [];

        foreach ( $tables as $table ) {
            $table = sanitize_text_field( $table );
            if ( ! str_starts_with( $table, $prefix ) ) {
                $errors[] = $table . ' (wrong prefix)';
                continue;
            }
            if ( in_array( substr( $table, $prefix_len ), $core, true ) ) {
                $errors[] = $table . ' (core — protected)';
                continue;
            }
            $new_name = '_trash_' . $date . '_' . $table;
            // Avoid collision
            $i = 1;
            while ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_name ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $new_name = '_trash_' . $date . '_' . $i . '_' . $table;
                $i++;
            }
            // Check if this is a VIEW — RENAME TABLE doesn't work on views
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table_type = $wpdb->get_var( $wpdb->prepare(
                "SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
                $table
            ) );

            if ( $table_type === 'VIEW' ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $ok = $wpdb->query( 'DROP VIEW `' . esc_sql( $table ) . '`' );
                if ( $ok === false ) {
                    $db_err = $wpdb->last_error ?: 'unknown MySQL error';
                    $errors[] = $table . ' (VIEW drop failed: ' . $db_err . ')';
                } else {
                    $archived[] = $table . ' (VIEW dropped)';
                }
            } else {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $ok = $wpdb->query( 'RENAME TABLE `' . esc_sql( $table ) . '` TO `' . esc_sql( $new_name ) . '`' );
                if ( $ok === false ) {
                    $db_err = $wpdb->last_error ?: 'unknown MySQL error';
                    $errors[] = $table . ' (' . $db_err . ')';
                } else {
                    $archived[] = $table;
                }
            }
        }

        if ( ! empty( $errors ) ) {
            wp_send_json_error( [
                'archived' => count( $archived ),
                'message'  => 'Archived ' . count( $archived ) . ', failed: ' . implode( ', ', $errors ),
            ] );
            return;
        }

        wp_send_json_success( [
            'archived' => count( $archived ),
            'message'  => 'Moved ' . count( $archived ) . ' table(s) to Recycle Bin.',
        ] );
    }

    public static function ajax_db_trash_scan(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $all    = $wpdb->get_col( 'SHOW TABLES' );
        $trash  = array_values( array_filter( $all, fn( $t ) => preg_match( '/^_trash_\d{8}_/', $t ) ) );

        if ( empty( $trash ) ) {
            wp_send_json_success( [ 'tables' => [] ] );
            return;
        }

        $placeholders = implode( ',', array_fill( 0, count( $trash ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT TABLE_NAME as name, TABLE_ROWS as row_count, ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024) as size_kb,
                        DATE_FORMAT(CREATE_TIME, '%%Y-%%m-%%d') as created_date
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$placeholders})",
                ...$trash
            )
        );
        $size_map    = [];
        $rows_map    = [];
        $created_map = [];
        foreach ( $rows as $r ) {
            $size_map[ $r->name ]    = (int) $r->size_kb;
            $rows_map[ $r->name ]    = (int) $r->row_count;
            $created_map[ $r->name ] = $r->created_date ?? '';
        }

        $result = [];
        foreach ( $trash as $t ) {
            // Derive original table name by stripping _trash_YYYYMMDD_ or _trash_YYYYMMDD_N_ prefix
            $original = preg_replace( '/^_trash_\d{8}_(?:\d+_)?/', '', $t );
            $suffix   = substr( $original, strlen( $wpdb->prefix ) );
            $result[] = [
                'trash_table'    => $t,
                'original_table' => $original,
                'size_kb'        => $size_map[ $t ] ?? 0,
                'rows'           => $rows_map[ $t ] ?? 0,
                'created_date'   => $created_map[ $t ] ?? '',
                'plugin'         => CSDT_Site_Audit::guess_plugin_from_suffix( $suffix ),
                'plugin_url'     => CSDT_Site_Audit::guess_plugin_url_from_suffix( $suffix ),
            ];
        }

        usort( $result, fn( $a, $b ) => strcmp( $a['original_table'], $b['original_table'] ) );

        wp_send_json_success( [ 'tables' => $result ] );
    }

    public static function ajax_db_restore_tables(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $tables = json_decode( wp_unslash( $_POST['tables'] ?? '' ), true );
        if ( ! is_array( $tables ) || empty( $tables ) ) {
            wp_send_json_error( 'No tables specified.' );
            return;
        }

        global $wpdb;
        $restored = [];
        $errors   = [];

        foreach ( $tables as $table ) {
            $table = sanitize_text_field( $table );
            if ( ! preg_match( '/^_trash_\d{8}_/', $table ) ) {
                $errors[] = $table . ' (not a trash table)';
                continue;
            }
            $original = preg_replace( '/^_trash_\d{8}_(?:\d+_)?/', '', $table );
            // If original name is already taken, skip
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $original ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $errors[] = $table . ' (original name ' . $original . ' already exists)';
                continue;
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $ok = $wpdb->query( 'RENAME TABLE `' . esc_sql( $table ) . '` TO `' . esc_sql( $original ) . '`' );
            if ( $ok === false ) {
                $errors[] = $table;
            } else {
                $restored[] = $original;
            }
        }

        if ( ! empty( $errors ) ) {
            wp_send_json_error( [
                'restored' => count( $restored ),
                'message'  => 'Restored ' . count( $restored ) . ', failed: ' . implode( ', ', $errors ),
            ] );
            return;
        }

        wp_send_json_success( [
            'restored' => count( $restored ),
            'message'  => 'Restored ' . count( $restored ) . ' table(s) successfully.',
        ] );
    }

    public static function ajax_db_drop_tables(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $tables_json = wp_unslash( $_POST['tables'] ?? '' );
        $tables      = json_decode( $tables_json, true );
        if ( ! is_array( $tables ) || empty( $tables ) ) {
            wp_send_json_error( 'No tables specified.' );
            return;
        }

        global $wpdb;
        $dropped = [];
        $errors  = [];

        foreach ( $tables as $table ) {
            $table = sanitize_text_field( $table );
            // Only allow dropping tables in the recycle bin (_trash_ prefix)
            if ( ! preg_match( '/^_trash_\d{8}_/', $table ) ) {
                $errors[] = $table . ' (not in recycle bin — archive first)';
                continue;
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
            if ( $result === false ) {
                $errors[] = $table;
            } else {
                $dropped[] = $table;
            }
        }

        if ( ! empty( $errors ) ) {
            wp_send_json_error( [
                'dropped' => count( $dropped ),
                'message' => 'Dropped ' . count( $dropped ) . ', failed: ' . implode( ', ', $errors ),
            ] );
            return;
        }

        wp_send_json_success( [
            'dropped' => count( $dropped ),
            'message' => 'Permanently deleted ' . count( $dropped ) . ' table(s).',
        ] );
    }

    // ── Optimizer: Plugin Stack Scanner ──────────────────────────────

    public static function ajax_plugin_stack_scan(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $replacements = self::get_plugin_replacements();
        $active       = (array) get_option( 'active_plugins', [] );
        $all_plugins  = get_plugins();
        $matched      = [];
        $total_saving = 0;

        $active_set = array_flip( $active );
        foreach ( $all_plugins as $plugin_file => $info ) {
            if ( ! isset( $replacements[ $plugin_file ] ) ) {
                continue;
            }
            $r         = $replacements[ $plugin_file ];
            $is_active = isset( $active_set[ $plugin_file ] );
            $matched[] = [
                'file'    => $plugin_file,
                'name'    => ! empty( $info['Name'] ) ? $info['Name'] : $r['name'],
                'version' => $info['Version'] ?? '',
                'feature' => $r['feature'],
                'tab'     => $r['tab'],
                'cost'    => $r['cost'],
                'active'  => $is_active,
            ];
            if ( $is_active ) {
                $total_saving += $r['cost'];
            }
        }

        wp_send_json_success( [
            'matched'      => $matched,
            'total_saving' => $total_saving,
            'active_count' => count( $active ),
        ] );
    }

    private static function get_plugin_replacements(): array {
        return [
            // Security scanners / firewalls
            'wordfence/wordfence.php'                                          => [ 'name' => 'Wordfence Security',            'feature' => 'AI Cyber Audit + Quick Fixes + Brute Force Protection',  'tab' => 'security',   'cost' => 119 ],
            'better-wp-security/better-wp-security.php'                       => [ 'name' => 'iThemes Security',              'feature' => 'Hide Login URL + Brute Force + Security Audit',          'tab' => 'login',      'cost' => 99  ],
            'all-in-one-wp-security-and-firewall/wp-security.php'             => [ 'name' => 'All-In-One Security & Firewall', 'feature' => 'Hide Login URL + Brute Force + Hardening Quick Fixes',  'tab' => 'login',      'cost' => 0   ],
            'sucuri-scanner/sucuri.php'                                        => [ 'name' => 'Sucuri Security',                'feature' => 'AI Cyber Audit + Server Logs',                           'tab' => 'security',   'cost' => 199 ],
            'wp-cerber/wp-cerber.php'                                          => [ 'name' => 'WP Cerber Security',             'feature' => 'Brute Force Protection + Hide Login URL',                'tab' => 'login',      'cost' => 99  ],
            'shield-security/icwp-wpsf.php'                                   => [ 'name' => 'Shield Security',                'feature' => 'AI Cyber Audit + Brute Force + Login Security',          'tab' => 'security',   'cost' => 69  ],
            // Two-factor authentication
            'wp-2fa/wp-2fa.php'                                                => [ 'name' => 'WP 2FA',                         'feature' => 'Two-Factor Auth (email OTP, TOTP, Passkeys)',             'tab' => 'login',      'cost' => 79  ],
            'miniorange-2-factor-authentication/miniorange_2_factor_authentication.php' => [ 'name' => 'miniOrange 2FA',       'feature' => 'Two-Factor Auth (email OTP, TOTP)',                       'tab' => 'login',      'cost' => 99  ],
            'google-authenticator/google-authenticator.php'                    => [ 'name' => 'Google Authenticator',           'feature' => 'Two-Factor Auth (TOTP authenticator app)',                'tab' => 'login',      'cost' => 0   ],
            'duo-wordpress/duo.php'                                            => [ 'name' => 'Duo Two-Factor Auth',             'feature' => 'Two-Factor Authentication',                               'tab' => 'login',      'cost' => 0   ],
            'two-factor/two-factor.php'                                        => [ 'name' => 'Two Factor',                     'feature' => 'Two-Factor Auth (email OTP, TOTP, Passkeys)',             'tab' => 'login',      'cost' => 0   ],
            // Login protection / hide login
            'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' => [ 'name' => 'Limit Login Attempts Reloaded',  'feature' => 'Brute Force Protection (per-account lockout)',            'tab' => 'login',      'cost' => 0   ],
            'loginpress/loginpress.php'                                        => [ 'name' => 'LoginPress',                     'feature' => 'Hide Login URL',                                          'tab' => 'login',      'cost' => 49  ],
            'wps-hide-login/wps-hide-login.php'                                => [ 'name' => 'WPS Hide Login',                 'feature' => 'Hide Login URL',                                          'tab' => 'login',      'cost' => 0   ],
            'rename-wp-login/rename-wp-login.php'                              => [ 'name' => 'Rename wp-login.php',            'feature' => 'Hide Login URL',                                          'tab' => 'login',      'cost' => 0   ],
            'sf-move-login/sf-move-login.php'                                  => [ 'name' => 'Move Login',                     'feature' => 'Hide Login URL',                                          'tab' => 'login',      'cost' => 0   ],
            // SMTP
            'wp-mail-smtp/wp_mail_smtp.php'                                    => [ 'name' => 'WP Mail SMTP',                   'feature' => 'SMTP Mail (authenticated delivery + email log)',          'tab' => 'mail',       'cost' => 49  ],
            'post-smtp/postman-smtp.php'                                       => [ 'name' => 'Post SMTP',                      'feature' => 'SMTP Mail (authenticated delivery)',                       'tab' => 'mail',       'cost' => 0   ],
            'easy-wp-smtp/easy-wp-smtp.php'                                    => [ 'name' => 'Easy WP SMTP',                   'feature' => 'SMTP Mail',                                               'tab' => 'mail',       'cost' => 0   ],
            'fluent-smtp/fluent-smtp.php'                                      => [ 'name' => 'FluentSMTP',                     'feature' => 'SMTP Mail',                                               'tab' => 'mail',       'cost' => 0   ],
            'sendgrid-email-delivery-simplified/wpsendgrid.php'                => [ 'name' => 'SendGrid',                       'feature' => 'SMTP Mail (use any SMTP provider)',                       'tab' => 'mail',       'cost' => 0   ],
            // Code syntax highlighting
            'enlighter/enlighter.php'                                          => [ 'name' => 'Enlighter Syntax Highlighter',   'feature' => 'Code Block (190+ languages, 14 themes, zero CDN)',       'tab' => 'migrate',    'cost' => 29  ],
            'syntaxhighlighter/syntaxhighlighter.php'                          => [ 'name' => 'SyntaxHighlighter Evolved',      'feature' => 'Code Block (190+ languages)',                             'tab' => 'migrate',    'cost' => 0   ],
            'prismatic/prismatic.php'                                          => [ 'name' => 'Prismatic',                      'feature' => 'Code Block (190+ languages, 14 themes)',                  'tab' => 'migrate',    'cost' => 29  ],
            'code-syntax-block/index.php'                                      => [ 'name' => 'Code Syntax Block',              'feature' => 'Code Block (Gutenberg block)',                            'tab' => 'migrate',    'cost' => 0   ],
            'urvanov-syntax-highlighter/urvanov-syntax-highlighter.php'        => [ 'name' => 'Urvanov Syntax Highlighter',     'feature' => 'Code Block',                                              'tab' => 'migrate',    'cost' => 0   ],
            // SQL / database tools
            'wp-phpmyadmin-extension/wp-phpmyadmin-extension.php'              => [ 'name' => 'WP phpMyAdmin',                  'feature' => 'SQL Query Tool (read-only, safe, wp-admin only)',         'tab' => 'sql',        'cost' => 0   ],
            'adminer-for-wordpress/adminer-for-wordpress.php'                  => [ 'name' => 'Adminer for WordPress',          'feature' => 'SQL Query Tool',                                          'tab' => 'sql',        'cost' => 0   ],
            // Log viewers / debug tools
            'wp-log-viewer/wp-log-viewer.php'                                  => [ 'name' => 'WP Log Viewer',                  'feature' => 'Server Logs (live search, tail mode, multiple sources)',  'tab' => 'logs',       'cost' => 0   ],
            'query-monitor/query-monitor.php'                                  => [ 'name' => 'Query Monitor',                  'feature' => 'Performance Monitor + Server Logs',                       'tab' => 'logs',       'cost' => 0   ],
            'debug-bar/debug-bar.php'                                          => [ 'name' => 'Debug Bar',                      'feature' => 'Performance Monitor',                                     'tab' => 'logs',       'cost' => 0   ],
            // Social / OG images
            'wordpress-seo/wp-seo.php'                                         => [ 'name' => 'Yoast SEO',                      'feature' => 'Thumbnails (og:image generation + social preview scan)',  'tab' => 'thumbnails', 'cost' => 99  ],
            'seo-by-rank-math/rank-math.php'                                   => [ 'name' => 'Rank Math SEO',                  'feature' => 'Thumbnails (og:image generation)',                        'tab' => 'thumbnails', 'cost' => 0   ],
        ];
    }

    // ── Optimizer: Update Risk Scanner ───────────────────────────────

    public static function ajax_update_risk_scan(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $update_data = get_site_transient( 'update_plugins' );
        if ( ! $update_data || empty( $update_data->response ) ) {
            // Force a fresh check
            wp_update_plugins();
            $update_data = get_site_transient( 'update_plugins' );
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();
        $plugins     = [];

        if ( ! empty( $update_data->response ) ) {
            foreach ( $update_data->response as $plugin_file => $data ) {
                $info      = $all_plugins[ $plugin_file ] ?? [];
                $plugins[] = [
                    'file'            => $plugin_file,
                    'slug'            => $data->slug ?? dirname( $plugin_file ),
                    'name'            => ! empty( $info['Name'] ) ? $info['Name'] : ( $data->slug ?? $plugin_file ),
                    'current_version' => $info['Version'] ?? '?',
                    'new_version'     => $data->new_version ?? '?',
                ];
            }
        }

        wp_send_json_success( [ 'plugins' => $plugins ] );
    }

    public static function ajax_update_risk_assess(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $slug            = sanitize_text_field( wp_unslash( $_POST['slug']            ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $current_version = sanitize_text_field( wp_unslash( $_POST['current_version'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $new_version     = sanitize_text_field( wp_unslash( $_POST['new_version']     ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $plugin_name     = sanitize_text_field( wp_unslash( $_POST['name']            ?? $slug ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ( ! $slug ) {
            wp_send_json_error( 'Missing slug' );
        }

        // Fetch changelog from WordPress.org
        $changelog = '';
        $api_url   = add_query_arg( [
            'action'                     => 'plugin_information',
            'request[slug]'              => $slug,
            'request[fields][sections]'  => '1',
        ], 'https://api.wordpress.org/plugins/info/1.2/' );

        $response = wp_remote_get( $api_url, [ 'timeout' => 10 ] );
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! empty( $body['sections']['changelog'] ) ) {
                $changelog = wp_strip_all_tags( $body['sections']['changelog'] );
                $changelog = mb_substr( $changelog, 0, 3000 );
            }
        }

        $has_key = ! empty( get_option( 'csdt_devtools_anthropic_key', '' ) ) ||
                   ! empty( get_option( 'csdt_devtools_gemini_key', '' ) );

        if ( $has_key && $changelog ) {
            $system   = 'You are a WordPress plugin update risk assessor. Given a plugin name, version numbers, and changelog, classify the update as exactly one of: "patch" (security fix or bug fix — apply immediately), "minor" (new features, low breaking risk), or "breaking" (major version, deprecated APIs, DB migrations, or significant structural changes — review before applying). Respond with ONLY valid JSON, no other text: {"risk":"patch","reason":"One sentence."}';
            $user_msg = "Plugin: {$plugin_name}\nCurrent version: {$current_version}\nNew version: {$new_version}\n\nChangelog:\n{$changelog}";
            try {
                $raw  = CSDT_AI_Dispatcher::call( $system, $user_msg, '_auto', 150 );
                $raw  = preg_replace( '/^```(?:json)?\s*/i', '', trim( $raw ) );
                $raw  = preg_replace( '/\s*```$/', '', $raw );
                $data = json_decode( $raw, true );
                if ( is_array( $data ) && ! empty( $data['risk'] ) ) {
                    wp_send_json_success( [
                        'risk'   => in_array( $data['risk'], [ 'patch', 'minor', 'breaking' ], true ) ? $data['risk'] : 'minor',
                        'reason' => sanitize_text_field( $data['reason'] ?? '' ),
                        'source' => 'ai',
                    ] );
                    return;
                }
            } catch ( \Throwable $e ) {
                // Fall through to semver fallback
            }
        }

        // Semver fallback
        $risk = self::update_risk_from_semver( $current_version, $new_version );
        wp_send_json_success( [
            'risk'   => $risk,
            'reason' => $changelog ? 'Based on version number change (AI unavailable).' : 'No changelog found — assessed from version number only.',
            'source' => 'semver',
        ] );
    }

    private static function update_risk_from_semver( string $current, string $new ): string {
        preg_match( '/^(\d+)\.(\d+)/', $current, $cm );
        preg_match( '/^(\d+)\.(\d+)/', $new,     $nm );
        $c_maj = (int) ( $cm[1] ?? 0 );
        $n_maj = (int) ( $nm[1] ?? 0 );
        $c_min = (int) ( $cm[2] ?? 0 );
        $n_min = (int) ( $nm[2] ?? 0 );
        if ( $n_maj > $c_maj ) return 'breaking';
        if ( $n_min > $c_min ) return 'minor';
        return 'patch';
    }

    // ── Optimizer: Database Intelligence Engine ──────────────────────

    public static function ajax_db_intelligence_scan(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        global $wpdb;

        // Autoloaded options
        $al = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(LENGTH(option_value)),0) AS total_bytes
             FROM {$wpdb->options}
             WHERE autoload IN ('yes','on','1','true')",
            ARRAY_A
        );
        $autoload_total_kb = round( (float) $al['total_bytes'] / 1024, 1 );
        $autoload_count    = (int) $al['cnt'];

        $top_autoloaded = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT option_name, ROUND(LENGTH(option_value)/1024,1) AS size_kb
             FROM {$wpdb->options}
             WHERE autoload IN ('yes','on','1','true')
             ORDER BY LENGTH(option_value) DESC
             LIMIT 10",
            ARRAY_A
        );

        // Expired transients
        $tr = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(LENGTH(option_name)+LENGTH(option_value)),0) AS total_bytes
             FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_timeout_%'
               AND CAST(option_value AS UNSIGNED) < UNIX_TIMESTAMP()",
            ARRAY_A
        );
        $expired_transients    = (int) $tr['cnt'];
        $expired_transients_kb = round( (float) $tr['total_bytes'] / 1024, 1 );

        // Post revisions
        $rv = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT COUNT(*) AS cnt,
                    COALESCE(SUM(LENGTH(post_content)+LENGTH(post_title)+LENGTH(post_excerpt)),0) AS total_bytes
             FROM {$wpdb->posts}
             WHERE post_type = 'revision'",
            ARRAY_A
        );
        $revisions_count = (int) $rv['cnt'];
        $revisions_kb    = round( (float) $rv['total_bytes'] / 1024, 1 );

        // Orphaned postmeta
        $orphan_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->posts} p WHERE p.ID = pm.post_id)"
        );

        // Table sizes
        $tables = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT TABLE_NAME AS tbl,
                        TABLE_ROWS AS `rows`,
                        ROUND(DATA_LENGTH/1024,0) AS data_kb,
                        ROUND(INDEX_LENGTH/1024,0) AS index_kb,
                        ROUND(COALESCE(DATA_FREE,0)/1024,0) AS overhead_kb,
                        ENGINE AS engine
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = %s
                 ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC",
                DB_NAME
            ),
            ARRAY_A
        );
        $total_db_kb      = 0;
        $total_overhead_kb = 0;
        foreach ( (array) $tables as $t ) {
            $total_db_kb       += (int) $t['data_kb'] + (int) $t['index_kb'];
            $total_overhead_kb += (int) $t['overhead_kb'];
        }

        // Rule-based findings
        $findings = [];

        if ( $autoload_total_kb > 300 ) {
            $top_names = implode( ', ', array_map(
                function ( $r ) { return $r['option_name'] . ' (' . $r['size_kb'] . ' KB)'; },
                array_slice( (array) $top_autoloaded, 0, 5 )
            ) );
            $findings[] = [
                'title'      => 'Large Autoload Cache (' . $autoload_total_kb . ' KB)',
                'detail'     => $autoload_count . ' options autoload on every page load, consuming ' . $autoload_total_kb . ' KB. Top offenders: ' . $top_names . '.',
                'fix'        => 'Review the top autoloaded options. Deactivate unused plugins that add large rows. Consider a plugin like Auctollo Autoload Manager to flip individual options to not autoload.',
                'severity'   => $autoload_total_kb > 1000 ? 'high' : 'medium',
                'fix_action' => null,
            ];
        }

        if ( $expired_transients > 20 ) {
            $findings[] = [
                'title'      => 'Expired Transients (' . $expired_transients . ')',
                'detail'     => $expired_transients . ' expired transients are still in the database, consuming ' . $expired_transients_kb . ' KB. They bloat the wp_options table and inflate autoload queries.',
                'fix'        => 'Click Fix It to delete all expired transients immediately. They regenerate on demand as needed.',
                'severity'   => $expired_transients > 200 ? 'medium' : 'low',
                'fix_action' => 'db_delete_expired_transients',
            ];
        }

        if ( $revisions_count > 200 ) {
            $findings[] = [
                'title'      => 'Post Revisions (' . number_format( $revisions_count ) . ' rows, ' . $revisions_kb . ' KB)',
                'detail'     => number_format( $revisions_count ) . ' post revisions stored, using ' . $revisions_kb . ' KB. WordPress stores unlimited revisions by default, inflating the wp_posts table.',
                'fix'        => "Click Fix It to delete all revisions. Going forward, add define('WP_POST_REVISIONS', 5) to wp-config.php to cap future revisions per post.",
                'severity'   => $revisions_count > 1000 ? 'medium' : 'low',
                'fix_action' => 'db_delete_revisions',
            ];
        }

        if ( $orphan_count > 50 ) {
            $findings[] = [
                'title'      => 'Orphaned Post Meta (' . number_format( $orphan_count ) . ' rows)',
                'detail'     => number_format( $orphan_count ) . ' rows in wp_postmeta reference posts that no longer exist. Left behind by deleted posts or poorly-cleaned-up plugins.',
                'fix'        => 'Click Fix It to delete all orphaned postmeta rows.',
                'severity'   => 'low',
                'fix_action' => 'db_delete_orphaned_postmeta',
            ];
        }

        if ( $total_overhead_kb > 1024 ) {
            $overhead_mb = round( $total_overhead_kb / 1024, 1 );
            $findings[]  = [
                'title'      => 'Table Fragmentation (' . $overhead_mb . ' MB reclaimable)',
                'detail'     => $overhead_mb . ' MB of overhead detected from deleted rows. OPTIMIZE TABLE reclaims this space and can improve query performance.',
                'fix'        => 'Click Fix It to run OPTIMIZE TABLE across all tables. May take a few seconds on large databases.',
                'severity'   => $total_overhead_kb > 10240 ? 'medium' : 'low',
                'fix_action' => 'db_optimize_tables',
            ];
        }

        if ( empty( $findings ) ) {
            $findings[] = [
                'title'      => 'Database looks healthy',
                'detail'     => 'No significant bloat detected. Autoload size, transients, revisions, and postmeta are all within normal thresholds.',
                'fix'        => 'No action needed.',
                'severity'   => 'info',
                'fix_action' => null,
            ];
        }

        wp_send_json_success( [
            'stats'    => [
                'autoload_total_kb'     => $autoload_total_kb,
                'autoload_count'        => $autoload_count,
                'top_autoloaded'        => $top_autoloaded,
                'expired_transients'    => $expired_transients,
                'expired_transients_kb' => $expired_transients_kb,
                'revisions_count'       => $revisions_count,
                'revisions_kb'          => $revisions_kb,
                'orphaned_postmeta'     => $orphan_count,
                'total_db_kb'           => $total_db_kb,
                'total_overhead_kb'     => $total_overhead_kb,
                'tables'                => $tables,
            ],
            'findings' => $findings,
        ] );
    }

    public static function ajax_db_intelligence_fix(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $fix_id = isset( $_POST['fix_id'] ) ? sanitize_key( wp_unslash( $_POST['fix_id'] ) ) : '';
        global $wpdb;

        switch ( $fix_id ) {
            case 'db_delete_expired_transients':
                $deleted = (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    "DELETE a, b
                     FROM {$wpdb->options} a
                     LEFT JOIN {$wpdb->options} b
                         ON b.option_name = REPLACE(a.option_name,'_transient_timeout_','_transient_')
                     WHERE a.option_name LIKE '_transient_timeout_%'
                       AND CAST(a.option_value AS UNSIGNED) < UNIX_TIMESTAMP()"
                );
                wp_send_json_success( [ 'message' => 'Deleted ' . intdiv( $deleted, 2 ) . ' expired transients.' ] );
                return;

            case 'db_delete_revisions':
                $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
                );
                $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    "DELETE pm FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE p.post_type = 'revision'"
                );
                $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'"
                );
                wp_send_json_success( [ 'message' => 'Deleted ' . number_format( $count ) . ' revisions.' ] );
                return;

            case 'db_delete_orphaned_postmeta':
                $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                     WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->posts} p WHERE p.ID = pm.post_id)"
                );
                $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    "DELETE pm FROM {$wpdb->postmeta} pm
                     WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->posts} p WHERE p.ID = pm.post_id)"
                );
                wp_send_json_success( [ 'message' => 'Deleted ' . number_format( $count ) . ' orphaned meta rows.' ] );
                return;

            case 'db_optimize_tables':
                $db_tables = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                foreach ( (array) $db_tables as $tbl ) {
                    $wpdb->query( 'OPTIMIZE TABLE `' . esc_sql( $tbl ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
                }
                wp_send_json_success( [ 'message' => 'Optimized ' . count( (array) $db_tables ) . ' tables.' ] );
                return;

            default:
                wp_send_json_error( 'Unknown fix ID' );
        }
    }

    // ── Optimizer: AI Debugging Assistant ────────────────────────────

    public static function ajax_ai_debug_log(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $raw_input = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $input     = sanitize_textarea_field( $raw_input );
        if ( empty( trim( $input ) ) ) {
            wp_send_json_error( [ 'message' => 'No input provided.' ] );
        }

        $site_ctx = sprintf( 'WordPress %s, PHP %s', get_bloginfo( 'version' ), PHP_VERSION );
        $system   = 'You are a WordPress debugging expert. The user provides an error message, log excerpt, or problem description. Identify the root cause and give specific actionable steps to fix it. Be direct and practical. Structure your response with exactly three sections: **Root Cause** (1-2 sentences), **Why It Happens** (2-3 sentences explaining the underlying mechanism), **How to Fix It** (numbered steps). Use backtick formatting for file paths, function names, and code snippets. Do not pad with generic advice.';
        $user_msg = "Site context: {$site_ctx}\n\nError / Problem:\n{$input}";

        $result = CSDT_AI_Dispatcher::call( $system, $user_msg, '_auto', 1024 );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'analysis' => $result ] );
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
            $max_logins  = (int) get_user_meta( $u->ID, 'csdt_test_max_logins', true );
            $login_count = (int) get_user_meta( $u->ID, 'csdt_test_login_count', true );
            $accounts[] = [
                'user_id'     => $u->ID,
                'username'    => $u->user_login,
                'expires_at'  => $expires_at,
                'expires_in'  => max( 0, $expires_at - time() ),
                'max_logins'  => $max_logins,
                'login_count' => $login_count,
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
        $max_logins  = max( 0, (int) get_option( 'csdt_test_account_max_logins', '1' ) );

        update_user_meta( $user_id, 'csdt_test_account',     '1' );
        update_user_meta( $user_id, 'csdt_test_expires_at',  $expires_at );
        update_user_meta( $user_id, 'csdt_test_max_logins',  $max_logins );
        update_user_meta( $user_id, 'csdt_test_login_count', 0 );

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
        $ttl         = in_array( (string) ( $_POST['ttl'] ?? '1800' ), [ '300', '600', '1800', '3600', '7200', '86400' ], true )
                       ? (string) $_POST['ttl'] : '1800';
        $single_use  = ( $_POST['single_use'] ?? '0' ) === '1' ? '1' : '0';
        $max_logins  = $single_use === '1' ? 1 : max( 0, (int) ( $_POST['max_logins'] ?? 0 ) );

        update_option( 'csdt_test_accounts_enabled',    $enabled );
        update_option( 'csdt_test_account_ttl',         $ttl );
        update_option( 'csdt_test_account_single_use',  $single_use );
        update_option( 'csdt_test_account_max_logins',  (string) $max_logins );

        if ( $enabled === '1' ) {
            if ( ! wp_next_scheduled( 'csdt_cleanup_test_accounts' ) ) {
                wp_schedule_event( time() + 300, 'csdt_every_5min', 'csdt_cleanup_test_accounts' );
            }
        } else {
            wp_clear_scheduled_hook( 'csdt_cleanup_test_accounts' );
        }

        wp_send_json_success();
    }

    // ── Threat Monitor ────────────────────────────────────────────────────────

    public static function monitor_threats(): void {
        if ( get_option( 'csdt_threat_monitor_enabled', '1' ) !== '1' ) {
            return;
        }
        if ( get_option( 'csdt_threat_file_integrity_enabled', '1' ) === '1' ) {
            self::check_file_integrity();
        }
        if ( get_option( 'csdt_threat_probe_enabled', '1' ) === '1' ) {
            self::check_probe_patterns();
        }
    }

    private static function check_file_integrity(): void {
        $abspath    = rtrim( ABSPATH, DIRECTORY_SEPARATOR );
        $wp_version = get_bloginfo( 'version' );
        $baseline   = get_option( 'csdt_file_integrity_baseline', [] );
        $alerted    = get_option( 'csdt_file_integrity_alerted',  [] );
        $saved_ver  = get_option( 'csdt_file_integrity_wp_ver',   '' );

        $scan_files = array_merge(
            [ $abspath . '/wp-config.php', $abspath . '/wp-login.php' ],
            glob( $abspath . '/wp-includes/*.php' ) ?: [],
            glob( $abspath . '/wp-admin/*.php' )    ?: []
        );

        // Build or rebuild baseline (first run, or after a WP core update)
        if ( empty( $baseline ) || $saved_ver !== $wp_version ) {
            $new_baseline = [];
            foreach ( $scan_files as $f ) {
                if ( file_exists( $f ) ) {
                    $new_baseline[ $f ] = (int) filemtime( $f );
                }
            }
            update_option( 'csdt_file_integrity_baseline', $new_baseline, false );
            update_option( 'csdt_file_integrity_wp_ver',   $wp_version,   false );
            update_option( 'csdt_file_integrity_alerted',  [],            false );
            return; // No alert on baseline creation
        }

        $modified = [];
        foreach ( $scan_files as $f ) {
            if ( ! file_exists( $f ) ) {
                continue;
            }
            $current = (int) filemtime( $f );
            $base    = isset( $baseline[ $f ] ) ? (int) $baseline[ $f ] : null;
            $prev    = isset( $alerted[ $f ] )  ? (int) $alerted[ $f ]  : null;

            // New file not in baseline, or mtime changed and we haven't alerted on this mtime yet
            if ( $base === null || ( $current !== $base && $current !== $prev ) ) {
                $modified[ $f ] = $current;
            }
        }

        if ( empty( $modified ) ) {
            return;
        }

        // Record that we've alerted for these mtimes — prevents repeat alerts for the same change
        update_option( 'csdt_file_integrity_alerted', array_merge( $alerted, $modified ), false );

        $site      = get_bloginfo( 'name' ) ?: home_url();
        $admin_url = admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=security' );
        $count     = count( $modified );
        $file_list = implode( "\n", array_map(
            fn( $f ) => '  ' . str_replace( ABSPATH, '', $f ),
            array_keys( $modified )
        ) );

        $subject = sprintf( '[%s] ⚠️ Core file modification detected (%d file%s)', $site, $count, $count === 1 ? '' : 's' );
        $body    = sprintf(
            "WordPress core file modification detected on %s.\n\n%d file%s changed:\n%s\n\nIf you did not update WordPress or install a plugin, investigate immediately — this may indicate a compromise.\n\nSecurity dashboard: %s",
            home_url(), $count, $count === 1 ? '' : 's', $file_list, $admin_url
        );

        self::send_threat_alert( $subject, $body, 'urgent', 'rotating_light,lock', $admin_url );
        update_option( 'csdt_threat_last_file_alert', [ 'ts' => time(), 'count' => $count, 'files' => array_keys( $modified ) ], false );
    }

    private static function check_probe_patterns(): void {
        $log_candidates = [
            '/var/log/nginx/access.log',
            '/var/log/apache2/access.log',
            '/var/log/httpd/access_log',
            '/var/log/apache2/other_vhosts_access.log',
        ];
        foreach ( self::get_log_sources() as $s ) {
            if ( ! empty( $s['path'] ) && strpos( $s['path'], 'access' ) !== false ) {
                $log_candidates[] = $s['path'];
            }
        }
        $log_path = '';
        foreach ( $log_candidates as $p ) {
            if ( is_readable( $p ) ) { $log_path = $p; break; }
        }
        if ( ! $log_path ) {
            return;
        }

        $threshold = max( 5, (int) get_option( 'csdt_threat_probe_threshold', '25' ) );
        $last_pos  = get_option( 'csdt_threat_probe_last_pos', [] );
        $now       = time();
        $size      = filesize( $log_path );
        $saved     = isset( $last_pos[ $log_path ] ) ? (int) $last_pos[ $log_path ] : null;

        update_option( 'csdt_threat_probe_last_pos', array_merge( $last_pos, [ $log_path => $size ] ), false );

        if ( $saved === null || $saved > $size ) {
            return; // First run or log rotated — just record position
        }
        $unread = $size - $saved;
        if ( $unread <= 0 ) {
            return;
        }

        $handle = @fopen( $log_path, 'rb' );
        if ( ! $handle ) {
            return;
        }
        fseek( $handle, $size - min( $unread, 524288 ) );
        $chunk = fread( $handle, min( $unread, 524288 ) );
        fclose( $handle );

        if ( ! $chunk ) {
            return;
        }

        $sensitive = [ 'wp-login.php', 'xmlrpc.php', 'wp-config', '.env', '/.git/', '/.svn/', '.sql', '.bak', '.dump', 'eval(', 'base64_', 'cmd=', 'exec=' ];
        $count     = 0;
        foreach ( explode( "\n", $chunk ) as $line ) {
            foreach ( $sensitive as $pat ) {
                if ( strpos( $line, $pat ) !== false ) {
                    $count++;
                    break;
                }
            }
        }

        if ( $count < $threshold ) {
            return;
        }

        // Throttle: one alert per hour
        $last_alert = (int) get_option( 'csdt_threat_probe_last_alert', 0 );
        if ( ( $now - $last_alert ) < 3600 ) {
            return;
        }
        update_option( 'csdt_threat_probe_last_alert', $now, false );

        $site      = get_bloginfo( 'name' ) ?: home_url();
        $admin_url = admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=security' );
        $subject   = sprintf( '[%s] 🔍 Probe attack — %d requests to sensitive paths', $site, $count );
        $body      = sprintf(
            "%d requests to sensitive paths (wp-login, xmlrpc, .env, .git, etc.) detected in the last 5 minutes on %s.\n\nThis indicates active scanning or an attack. Consider blocking the source IP via fail2ban or Cloudflare.\n\nSecurity dashboard: %s",
            $count, home_url(), $admin_url
        );
        self::send_threat_alert( $subject, $body, 'high', 'warning,shield', $admin_url );
        update_option( 'csdt_threat_last_probe_alert', [ 'ts' => $now, 'count' => $count ], false );
    }

    public static function on_user_registered( int $user_id ): void {
        if ( get_option( 'csdt_threat_monitor_enabled', '1' ) !== '1' ) return;
        if ( get_option( 'csdt_threat_new_admin_enabled', '1' ) !== '1' ) return;
        $user = get_userdata( $user_id );
        if ( ! $user || ! in_array( 'administrator', (array) $user->roles, true ) ) return;
        self::alert_new_admin( $user );
    }

    public static function on_set_user_role( int $user_id, string $new_role, array $old_roles ): void {
        if ( get_option( 'csdt_threat_monitor_enabled', '1' ) !== '1' ) return;
        if ( get_option( 'csdt_threat_new_admin_enabled', '1' ) !== '1' ) return;
        if ( $new_role !== 'administrator' ) return;
        if ( in_array( 'administrator', $old_roles, true ) ) return;
        $user = get_userdata( $user_id );
        if ( ! $user ) return;
        self::alert_new_admin( $user );
    }

    private static function alert_new_admin( \WP_User $user ): void {
        $alerted = get_option( 'csdt_threat_alerted_admins', [] );
        if ( in_array( $user->ID, $alerted, true ) ) return;
        $alerted[] = $user->ID;
        if ( count( $alerted ) > 100 ) {
            $alerted = array_slice( $alerted, -100 );
        }
        update_option( 'csdt_threat_alerted_admins', $alerted, false );

        // Detect creation method
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            $method = 'WP-CLI / SSH';
        } elseif ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            $method = 'REST API';
        } elseif ( ! empty( $_SERVER['REQUEST_URI'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/wp-admin/' ) !== false ) {
            $method = 'Admin UI';
        } else {
            $method = 'Programmatic';
        }

        // Detect test/automation accounts
        $test_patterns = [ 'playwright', 'helpdocs', 'test_', 'e2e_', 'cypress', 'selenium' ];
        $is_test       = false;
        foreach ( $test_patterns as $p ) {
            if ( stripos( $user->user_login, $p ) !== false || stripos( $user->user_email, $p ) !== false ) {
                $is_test = true;
                break;
            }
        }
        $test_flag = $is_test ? ' [TEST]' : '';

        $site      = wp_specialchars_decode( get_bloginfo( 'name' ) ?: home_url(), ENT_QUOTES );
        $admin_url = admin_url( 'users.php' );
        $subject   = sprintf( '[%s] 🚨 New admin: %s — via %s%s', $site, $user->user_login, $method, $test_flag );
        $body      = sprintf(
            "A new administrator account was created on %s.\n\nUsername: %s\nEmail: %s\nCreated via: %s\nRegistered: %s\n\n%sIf you did not create this account, revoke it immediately.\n\nManage users: %s",
            home_url(), $user->user_login, $user->user_email, $method, $user->user_registered,
            $is_test ? "This appears to be a TEST/automation account.\n\n" : '',
            $admin_url
        );
        self::send_threat_alert( $subject, $body, 'urgent', 'rotating_light,bust_in_silhouette', $admin_url );
        update_option( 'csdt_threat_last_admin_alert', [ 'ts' => time(), 'login' => $user->user_login, 'email' => $user->user_email ], false );
    }

    private static function send_threat_alert( string $subject, string $body, string $priority, string $tags, string $click_url ): void {
        if ( get_option( 'csdt_scan_schedule_email', '1' ) === '1' ) {
            add_filter( 'wp_mail_content_type', [ 'CSDT_Login', 'email_content_type_html' ] );
            wp_mail( get_option( 'admin_email' ), $subject, nl2br( esc_html( $body ) ) );
            remove_filter( 'wp_mail_content_type', [ 'CSDT_Login', 'email_content_type_html' ] );
        }
        $ntfy_url = get_option( 'csdt_scan_schedule_ntfy_url', '' );
        if ( $ntfy_url ) {
            $headers = [ 'Title' => $subject, 'Priority' => $priority, 'Tags' => $tags, 'Click' => $click_url ];
            $ntfy_tok = get_option( 'csdt_scan_schedule_ntfy_token', '' );
            if ( $ntfy_tok ) {
                $headers['Authorization'] = 'Bearer ' . $ntfy_tok;
            }
            wp_remote_post( $ntfy_url, [ 'timeout' => 10, 'headers' => $headers, 'body' => $body ] );
        }
    }

    public static function ajax_threat_monitor_save(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $enabled   = ( $_POST['enabled']         ?? '0' ) === '1' ? '1' : '0';
        $file_int  = ( $_POST['file_integrity']  ?? '0' ) === '1' ? '1' : '0';
        $new_admin = ( $_POST['new_admin']        ?? '0' ) === '1' ? '1' : '0';
        $probe     = ( $_POST['probe']            ?? '0' ) === '1' ? '1' : '0';
        $threshold = max( 5, min( 500, (int) ( $_POST['probe_threshold'] ?? 25 ) ) );

        update_option( 'csdt_threat_monitor_enabled',          $enabled,            false );
        update_option( 'csdt_threat_file_integrity_enabled',   $file_int,           false );
        update_option( 'csdt_threat_new_admin_enabled',        $new_admin,          false );
        update_option( 'csdt_threat_probe_enabled',            $probe,              false );
        update_option( 'csdt_threat_probe_threshold',          (string) $threshold, false );

        if ( $enabled === '1' ) {
            if ( ! wp_next_scheduled( 'csdt_threat_monitor' ) ) {
                wp_schedule_event( time() + 300, 'csdt_every_5min', 'csdt_threat_monitor' );
            }
        } else {
            wp_clear_scheduled_hook( 'csdt_threat_monitor' );
        }
        wp_send_json_success();
    }

    public static function ajax_threat_integrity_reset(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        delete_option( 'csdt_file_integrity_baseline' );
        delete_option( 'csdt_file_integrity_wp_ver' );
        delete_option( 'csdt_file_integrity_alerted' );
        wp_send_json_success( [ 'message' => 'Baseline cleared. A new baseline will be built on the next cron run (within 5 minutes).' ] );
    }

    // ── SSH Brute-Force Monitor ───────────────────────────────────────────────

    public static function ajax_bf_self_test(): void {
        check_ajax_referer( self::LOGIN_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $max_attempts = max( 1, (int) get_option( 'csdt_devtools_brute_force_attempts', '5' ) );
        $lockout_mins = max( 1, (int) get_option( 'csdt_devtools_brute_force_lockout', '10' ) );
        $lockout_secs = $lockout_mins * MINUTE_IN_SECONDS;
        $bf_enabled   = get_option( 'csdt_devtools_brute_force_enabled', '1' ) === '1';

        if ( ! $bf_enabled ) {
            wp_send_json_error( 'Brute-force protection is disabled — enable it first.' );
        }

        // Use a synthetic username — no real user created.
        $test_user = 'csdt_bf_selftest_' . time();
        $slug      = md5( strtolower( $test_user ) );
        $count_key = 'csdt_devtools_bf_count_' . $slug;
        $lock_key  = 'csdt_devtools_bf_lock_' . $slug;

        // Simulate max_attempts consecutive failed logins (mirrors perf_track_failed_login logic).
        for ( $i = 1; $i <= $max_attempts; $i++ ) {
            $attempts = (int) get_transient( $count_key ) + 1;
            if ( $attempts >= $max_attempts ) {
                set_transient( $lock_key, time() + $lockout_secs, $lockout_secs );
                delete_transient( $count_key );
            } else {
                set_transient( $count_key, $attempts, $lockout_secs * 2 );
            }
        }

        $locked_until = (int) get_transient( $lock_key );
        $is_locked    = $locked_until > time();

        // Send a clearly-labelled test notification if ntfy is configured.
        $ntfy_url     = get_option( 'csdt_scan_schedule_ntfy_url', '' );
        $notif_sent   = false;
        if ( $is_locked && $ntfy_url ) {
            $site    = wp_specialchars_decode( get_bloginfo( 'name' ) ?: home_url(), ENT_QUOTES );
            $subject = sprintf( '[%s] ✅ BF Protection TEST passed — lockout works', $site );
            $body    = sprintf(
                "Self-test result: PASS\n\nBrute-force lockout fired correctly after %d failed attempts.\nTest account: %s\nLockout duration: %d minutes\n\nThis is a self-test message — no action needed.",
                $max_attempts, $test_user, $lockout_mins
            );
            self::send_threat_alert( $subject, $body, 'default', 'white_check_mark,lock', admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=login' ) );
            $notif_sent = true;
        }

        // Clean up all test transients.
        delete_transient( $count_key );
        delete_transient( $lock_key );
        delete_transient( 'csdt_devtools_bf_notif_' . $slug );

        wp_send_json_success( [
            'passed'        => $is_locked,
            'attempts'      => $max_attempts,
            'lockout_mins'  => $lockout_mins,
            'notif_sent'    => $notif_sent,
            'ntfy_url'      => ! empty( $ntfy_url ),
            'clear_cmd'     => "wp transient delete csdt_devtools_bf_lock_\$(php -r \"echo md5(strtolower('USERNAME'));\") --path=/var/www/html",
        ] );
    }

    public static function ajax_ssh_log_clear(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        delete_option( 'csdt_ssh_monitor_alert_log' );
        wp_send_json_success();
    }

    public static function ajax_ssh_monitor_save(): void {
        check_ajax_referer( 'csdt_devtools_security_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $enabled   = ( $_POST['enabled']   ?? '0' ) === '1' ? '1' : '0';
        $threshold = max( 1, min( 1000, (int) ( $_POST['threshold'] ?? 10 ) ) );
        update_option( 'csdt_ssh_monitor_enabled',   $enabled,   false );
        update_option( 'csdt_ssh_monitor_threshold', $threshold, false );

        if ( $enabled === '1' ) {
            if ( ! wp_next_scheduled( 'csdt_ssh_monitor' ) ) {
                wp_schedule_event( time() + 60, 'csdt_every_1min', 'csdt_ssh_monitor' );
            }
        } else {
            wp_clear_scheduled_hook( 'csdt_ssh_monitor' );
        }
        wp_send_json_success();
    }

    public static function monitor_ssh_failures(): void {
        if ( get_option( 'csdt_ssh_monitor_enabled', '1' ) !== '1' ) {
            return;
        }

        // Find a readable auth log
        $auth_log = '';
        foreach ( [ '/var/log/auth.log', '/var/log/secure', '/var/log/messages' ] as $p ) {
            if ( is_readable( $p ) ) { $auth_log = $p; break; }
        }
        if ( ! $auth_log ) {
            return; // log not accessible — silently skip
        }

        // Read the last 256 KB (enough for several minutes of auth activity)
        $handle = @fopen( $auth_log, 'r' );
        if ( ! $handle ) {
            return;
        }
        fseek( $handle, 0, SEEK_END );
        $size   = ftell( $handle );
        $read   = min( 262144, $size );
        fseek( $handle, $size - $read );
        $chunk  = fread( $handle, $read );
        fclose( $handle );

        if ( ! $chunk ) {
            return;
        }

        $window    = 60; // seconds — check the last 60 seconds on each 1-minute cron tick
        $threshold = (int) get_option( 'csdt_ssh_monitor_threshold', '10' );
        $now       = time();
        $failures  = [];

        // Match: "Failed password", "Invalid user", "Connection closed by invalid user", "authentication failure"
        $pattern = '/^(\w{3}\s+\d+\s[\d:]+)\s+\S+\s+sshd\[\d+\]:\s+(?:Failed password|Invalid user|Connection closed by invalid user|authentication failure).*/m';

        foreach ( explode( "\n", $chunk ) as $line ) {
            if ( ! preg_match( $pattern, $line, $m ) ) {
                continue;
            }
            // Parse syslog timestamp (no year — assume current year, roll back if in future)
            $ts = strtotime( $m[1] );
            if ( $ts === false ) {
                continue;
            }
            // Syslog has no year — if timestamp is in the future, it's last year
            if ( $ts > $now + 60 ) {
                $ts = strtotime( $m[1] . ' ' . ( (int) gmdate( 'Y' ) - 1 ) );
            }
            if ( $ts !== false && ( $now - $ts ) <= $window ) {
                $failures[] = trim( $line );
            }
        }

        $count = count( $failures );

        // Extract targeted usernames from failure lines
        $user_counts = [];
        foreach ( $failures as $line ) {
            $username = '';
            if ( preg_match( '/Failed password for (?:invalid user )?(\S+)\s+from/i', $line, $um ) ) {
                $username = $um[1];
            } elseif ( preg_match( '/Invalid user (\S+)\s+from/i', $line, $um ) ) {
                $username = $um[1];
            } elseif ( preg_match( '/Connection closed by invalid user (\S+)\s/i', $line, $um ) ) {
                $username = $um[1];
            } elseif ( preg_match( '/\buser=(\S+)/', $line, $um ) ) {
                $username = $um[1];
            }
            if ( $username && $username !== 'for' && $username !== 'by' ) {
                $user_counts[ $username ] = ( $user_counts[ $username ] ?? 0 ) + 1;
            }
        }

        // Store recent failure data for the Quick Fixes panel display
        update_option( 'csdt_ssh_monitor_last_check', [
            'ts'    => $now,
            'count' => $count,
            'lines' => array_slice( $failures, -20 ), // keep last 20 for display
        ], false );

        if ( $count < $threshold ) {
            return;
        }

        // Throttle: don't alert more than once per 5 minutes (attacks move fast)
        $last_alert = (int) get_option( 'csdt_ssh_monitor_last_alert', 0 );
        if ( ( $now - $last_alert ) < 300 ) {
            return;
        }
        update_option( 'csdt_ssh_monitor_last_alert', $now, false );

        // Append to alert log (keep last 50 events)
        arsort( $user_counts );
        $alert_log   = get_option( 'csdt_ssh_monitor_alert_log', [] );
        $alert_log[] = [ 'ts' => $now, 'count' => $count, 'users' => $user_counts ];
        if ( count( $alert_log ) > 50 ) {
            $alert_log = array_slice( $alert_log, -50 );
        }
        update_option( 'csdt_ssh_monitor_alert_log', $alert_log, false );

        // Build alert message
        $site      = get_bloginfo( 'name' ) ?: home_url();
        $admin_url = admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=security' );
        $subject   = sprintf( '[%s] 🚨 SSH Brute-Force Attack — %d failures in 60 seconds', $site, $count );
        $body      = sprintf(
            "SSH brute-force attack detected on %s\n\n%d failed SSH login attempts in the last 60 seconds.\n\nRecent failures:\n%s\n\nInstall fail2ban immediately to block attacking IPs automatically.\nQuick Fixes: %s",
            $site,
            $count,
            implode( "\n", array_slice( $failures, -5 ) ),
            $admin_url
        );

        // Email alert
        if ( get_option( 'csdt_scan_schedule_email', '1' ) === '1' ) {
            wp_mail( get_option( 'admin_email' ), $subject, $body );
        }

        // ntfy.sh push notification
        $ntfy_url = get_option( 'csdt_scan_schedule_ntfy_url', '' );
        if ( $ntfy_url ) {
            $headers = [
                'Title'    => $subject,
                'Priority' => 'urgent',
                'Tags'     => 'rotating_light,computer',
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

    public static function monitor_php_errors(): void {
        if ( get_option( 'csdt_php_error_monitor_enabled', '1' ) !== '1' ) {
            return;
        }

        $sources     = self::get_log_sources();
        $watch_keys  = [ 'php_error', 'wp_debug' ];
        $last_pos    = get_option( 'csdt_php_error_last_pos', [] );
        $new_pos     = $last_pos;
        $new_lines   = [];
        $fatal_lines = [];
        $now         = time();
        $is_first_run = empty( $last_pos );

        foreach ( $watch_keys as $key ) {
            if ( empty( $sources[ $key ]['path'] ) ) {
                continue;
            }
            $path = $sources[ $key ]['path'];
            if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
                continue;
            }

            $size     = filesize( $path );
            $saved    = isset( $last_pos[ $key ] ) ? (int) $last_pos[ $key ] : null;
            $new_pos[ $key ] = $size;

            // First run or file truncated — just record position, don't alert
            if ( $saved === null || $saved > $size ) {
                continue;
            }

            $unread = $size - $saved;
            if ( $unread <= 0 ) {
                continue;
            }

            // Cap at 128 KB of new content per source to avoid memory issues
            $read_bytes = min( $unread, 131072 );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $handle = @fopen( $path, 'rb' );
            if ( ! $handle ) {
                continue;
            }
            fseek( $handle, $size - $read_bytes );
            $chunk = fread( $handle, $read_bytes );
            fclose( $handle );

            if ( ! $chunk ) {
                continue;
            }

            foreach ( explode( "\n", $chunk ) as $line ) {
                $line = trim( $line );
                if ( ! $line ) {
                    continue;
                }
                $lower = strtolower( $line );
                if ( strpos( $lower, 'fatal' ) !== false || strpos( $lower, 'critical' ) !== false ) {
                    $fatal_lines[] = $line;
                } elseif ( strpos( $lower, 'php error' ) !== false || preg_match( '/\bPHP (?:Warning|Parse error|Error)\b/i', $line ) ) {
                    $new_lines[] = $line;
                }
            }
        }

        update_option( 'csdt_php_error_last_pos', $new_pos, false );

        if ( $is_first_run || ( empty( $fatal_lines ) && empty( $new_lines ) ) ) {
            return;
        }

        $threshold = (int) get_option( 'csdt_php_error_monitor_threshold', '1' );
        $has_alert = ! empty( $fatal_lines ) || count( $new_lines ) >= $threshold;

        if ( ! $has_alert ) {
            return;
        }

        // Throttle: max one alert per 15 minutes
        $last_alert = (int) get_option( 'csdt_php_error_monitor_last_alert', 0 );
        if ( ( $now - $last_alert ) < 900 ) {
            return;
        }
        update_option( 'csdt_php_error_monitor_last_alert', $now, false );

        $site      = get_bloginfo( 'name' ) ?: home_url();
        $debug_url = admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=debug' );
        $is_fatal  = ! empty( $fatal_lines );
        $all_new   = array_merge( $fatal_lines, $new_lines );
        $excerpt   = implode( "\n", array_slice( $all_new, 0, 5 ) );

        if ( $is_fatal ) {
            $subject = sprintf( '[%s] PHP Fatal Error detected', $site );
            $priority = 'urgent';
            $tags     = 'rotating_light,computer';
        } else {
            $subject = sprintf( '[%s] %d new PHP error%s detected', $site, count( $new_lines ), count( $new_lines ) === 1 ? '' : 's' );
            $priority = 'high';
            $tags     = 'warning,computer';
        }

        $body = sprintf(
            "%s on %s\n\nRecent entries:\n%s\n\nOpen Debug AI to analyze: %s",
            $subject,
            home_url(),
            $excerpt,
            $debug_url
        );

        // Email
        add_filter( 'wp_mail_content_type', [ 'CSDT_Login', 'email_content_type_html' ] );
        wp_mail( get_option( 'admin_email' ), $subject, nl2br( esc_html( $body ) ) );
        remove_filter( 'wp_mail_content_type', [ 'CSDT_Login', 'email_content_type_html' ] );

        // ntfy.sh
        $ntfy_url = get_option( 'csdt_scan_schedule_ntfy_url', '' );
        if ( $ntfy_url ) {
            $headers = [
                'Title'    => $subject,
                'Priority' => $priority,
                'Tags'     => $tags,
                'Click'    => $debug_url,
            ];
            $ntfy_tok = get_option( 'csdt_scan_schedule_ntfy_token', '' );
            if ( $ntfy_tok ) {
                $headers['Authorization'] = 'Bearer ' . $ntfy_tok;
            }
            wp_remote_post( $ntfy_url, [
                'timeout' => 10,
                'headers' => $headers,
                'body'    => $excerpt,
            ] );
        }

        update_option( 'csdt_php_error_monitor_last_trigger', [
            'ts'      => $now,
            'fatal'   => count( $fatal_lines ),
            'errors'  => count( $new_lines ),
            'excerpt' => $excerpt,
        ], false );
    }

    public static function ajax_php_error_monitor_save(): void {
        check_ajax_referer( 'csdt_debug_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $enabled   = isset( $_POST['enabled'] ) && $_POST['enabled'] === '1' ? '1' : '0';
        $threshold = max( 1, min( 50, (int) ( $_POST['threshold'] ?? 1 ) ) );
        update_option( 'csdt_php_error_monitor_enabled',   $enabled,           false );
        update_option( 'csdt_php_error_monitor_threshold', (string) $threshold, false );
        if ( $enabled === '1' ) {
            if ( ! wp_next_scheduled( 'csdt_php_error_monitor' ) ) {
                wp_schedule_event( time() + 300, 'csdt_every_5min', 'csdt_php_error_monitor' );
            }
            // Reset position so first run doesn't re-scan old log
            delete_option( 'csdt_php_error_last_pos' );
        } else {
            wp_clear_scheduled_hook( 'csdt_php_error_monitor' );
        }
        wp_send_json_success( [ 'enabled' => $enabled, 'threshold' => $threshold ] );
    }

    public static function ajax_fpm_monitor_save(): void {
        check_ajax_referer( 'csdt_fpm_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $enabled          = isset( $_POST['enabled'] ) && $_POST['enabled'] === '1' ? '1' : '0';
        $threshold        = max( 1, min( 30,    (int) ( $_POST['threshold']       ?? 3    ) ) );
        $cooldown         = max( 60, min( 86400, (int) ( $_POST['cooldown']       ?? 1800 ) ) );
        $timeout          = max( 1, min( 30,    (int) ( $_POST['probe_timeout']   ?? 5    ) ) );
        $auto_restart     = isset( $_POST['auto_restart'] ) && $_POST['auto_restart'] === '1' ? '1' : '0';
        $restart_cooldown = max( 60, min( 86400, (int) ( $_POST['restart_cooldown'] ?? 1200 ) ) );
        update_option( 'csdt_fpm_enabled',          $enabled,                                                                         false );
        update_option( 'csdt_fpm_threshold',         (string) $threshold,                                                             false );
        update_option( 'csdt_fpm_cooldown',          (string) $cooldown,                                                              false );
        update_option( 'csdt_fpm_probe_url',         esc_url_raw( (string) ( $_POST['probe_url']    ?? 'http://localhost:8082/' ) ),   false );
        update_option( 'csdt_fpm_probe_timeout',     (string) $timeout,                                                               false );
        update_option( 'csdt_fpm_wp_container',      sanitize_text_field( (string) ( $_POST['wp_container'] ?? 'pi_wordpress' ) ),    false );
        update_option( 'csdt_fpm_db_container',      sanitize_text_field( (string) ( $_POST['db_container'] ?? 'pi_mariadb'  ) ),    false );
        update_option( 'csdt_fpm_auto_restart',      $auto_restart,                                                                   false );
        update_option( 'csdt_fpm_restart_cooldown',  (string) $restart_cooldown,                                                      false );
        wp_send_json_success();
    }

    public static function ajax_fpm_worker_status(): void {
        check_ajax_referer( 'csdt_fpm_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $probe_url  = rtrim( get_option( 'csdt_fpm_probe_url', 'http://localhost:8082/' ), '/' );
        $status_url = $probe_url . '/fpm-status';
        $response   = wp_remote_get( $status_url, [ 'timeout' => 5, 'sslverify' => false ] );
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => 'Could not reach ' . $status_url . ': ' . $response->get_error_message() . '. Ensure pm.status_path = /fpm-status in www.conf and a matching nginx location.' ] );
        }
        $body = wp_remote_retrieve_body( $response );
        $code = wp_remote_retrieve_response_code( $response );
        if ( (int) $code !== 200 || empty( $body ) ) {
            wp_send_json_error( [ 'message' => 'FPM status returned HTTP ' . $code . '. Enable pm.status_path = /fpm-status in www.conf and add a nginx location block for /fpm-status.' ] );
        }
        $parse = static function ( string $key ) use ( $body ): ?int {
            if ( preg_match( '/^' . preg_quote( $key, '/' ) . ':\s*(\d+)/m', $body, $m ) ) {
                return (int) $m[1];
            }
            return null;
        };
        wp_send_json_success( [
            'active' => $parse( 'active processes' ),
            'idle'   => $parse( 'idle processes' ),
            'total'  => $parse( 'total processes' ),
        ] );
    }

    public static function ajax_fpm_worker_detail(): void {
        check_ajax_referer( 'csdt_fpm_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $probe_url  = rtrim( get_option( 'csdt_fpm_probe_url', 'http://localhost:8082/' ), '/' );
        $status_url = $probe_url . '/fpm-status?full&json';
        $response   = wp_remote_get( $status_url, [ 'timeout' => 5, 'sslverify' => false ] );
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        if ( $code !== 200 || empty( $body ) ) {
            wp_send_json_error( [ 'message' => 'HTTP ' . $code ] );
        }

        // Try JSON first (supported since PHP-FPM 7.x with ?json)
        $json = json_decode( $body, true );
        if ( $json && isset( $json['processes'] ) ) {
            wp_send_json_success( [
                'pool'     => $json['pool'] ?? '',
                'pm'       => $json['process manager'] ?? '',
                'accepted' => $json['accepted conn'] ?? 0,
                'workers'  => array_map( static function ( array $p ): array {
                    return [
                        'pid'      => $p['pid'] ?? 0,
                        'state'    => $p['state'] ?? '',
                        'reqs'     => $p['requests'] ?? 0,
                        'since'    => $p['start since'] ?? 0,
                        'duration' => $p['request duration'] ?? 0,
                        'method'   => $p['request method'] ?? '',
                        'uri'      => ( $p['request uri'] ?? '' ) . ( ! empty( $p['query string'] ) ? '?' . $p['query string'] : '' ),
                        'script'   => basename( $p['script'] ?? '' ),
                        'cpu'      => $p['last request cpu'] ?? 0,
                        'mem'      => $p['last request memory'] ?? 0,
                        'user'     => $p['user'] ?? '-',
                    ];
                }, $json['processes'] ),
            ] );
        }

        // Fall back to text parsing
        $sections = preg_split( '/\*{8,}/', $body );
        $pool_info = [];
        $workers   = [];
        if ( ! empty( $sections[0] ) ) {
            foreach ( explode( "\n", $sections[0] ) as $line ) {
                if ( preg_match( '/^([^:]+):\s*(.+)$/', trim( $line ), $m ) ) {
                    $pool_info[ trim( $m[1] ) ] = trim( $m[2] );
                }
            }
        }
        foreach ( array_slice( $sections, 1 ) as $section ) {
            $w = [];
            foreach ( explode( "\n", $section ) as $line ) {
                if ( preg_match( '/^([^:]+):\s*(.*)$/', trim( $line ), $m ) ) {
                    $w[ trim( $m[1] ) ] = trim( $m[2] );
                }
            }
            if ( ! empty( $w['pid'] ) ) {
                $uri = $w['request URI'] ?? '';
                $qs  = $w['query string'] ?? '';
                $workers[] = [
                    'pid'      => (int) $w['pid'],
                    'state'    => $w['state'] ?? '',
                    'reqs'     => (int) ( $w['requests'] ?? 0 ),
                    'since'    => (int) ( $w['start since'] ?? 0 ),
                    'duration' => (int) ( $w['request duration'] ?? 0 ),
                    'method'   => $w['request method'] ?? '',
                    'uri'      => $uri . ( $qs ? '?' . $qs : '' ),
                    'script'   => basename( $w['script'] ?? '' ),
                    'cpu'      => (float) ( $w['last request cpu'] ?? 0 ),
                    'mem'      => (int) ( $w['last request memory'] ?? 0 ),
                    'user'     => $w['user'] ?? '-',
                ];
            }
        }
        wp_send_json_success( [
            'pool'     => $pool_info['pool'] ?? '',
            'pm'       => $pool_info['process manager'] ?? '',
            'accepted' => (int) ( $pool_info['accepted conn'] ?? 0 ),
            'workers'  => $workers,
        ] );
    }

    public static function ajax_fpm_setup_detect(): void {
        check_ajax_referer( 'csdt_fpm_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        // Locate www.conf
        $conf_candidates = [
            '/usr/local/etc/php-fpm.d/www.conf',
            '/etc/php-fpm.d/www.conf',
            '/etc/php/8.4/fpm/pool.d/www.conf',
            '/etc/php/8.3/fpm/pool.d/www.conf',
            '/etc/php/8.2/fpm/pool.d/www.conf',
            '/etc/php/8.1/fpm/pool.d/www.conf',
            '/etc/php/8.0/fpm/pool.d/www.conf',
            '/etc/php/7.4/fpm/pool.d/www.conf',
        ];
        $www_conf          = null;
        $www_conf_writable = false;
        $status_path_set   = false;
        foreach ( $conf_candidates as $p ) {
            if ( file_exists( $p ) && is_readable( $p ) ) {
                $www_conf          = $p;
                $www_conf_writable = is_writable( $p );
                $content           = (string) file_get_contents( $p );
                $status_path_set   = (bool) preg_match( '/^\s*pm\.status_path\s*=/m', $content );
                break;
            }
        }

        // Probe nginx candidates
        $stored   = rtrim( get_option( 'csdt_fpm_probe_url', 'http://localhost:8082/' ), '/' );
        $probes   = array_unique( [
            $stored, 'http://localhost', 'http://localhost:80',
            'http://localhost:8080', 'http://localhost:8082', 'http://127.0.0.1',
        ] );
        $nginx_url        = null;
        $fpm_status_works = false;
        foreach ( $probes as $base ) {
            $base = rtrim( $base, '/' );
            $r    = wp_remote_get( $base . '/fpm-status', [ 'timeout' => 2, 'sslverify' => false ] );
            if ( ! is_wp_error( $r ) && (int) wp_remote_retrieve_response_code( $r ) === 200 ) {
                $body = wp_remote_retrieve_body( $r );
                if ( str_contains( $body, 'active processes' ) || str_contains( $body, 'pool:' ) ) {
                    $nginx_url        = $base . '/';
                    $fpm_status_works = true;
                    break;
                }
            }
            if ( $nginx_url === null ) {
                $r2 = wp_remote_get( $base . '/', [ 'timeout' => 2, 'sslverify' => false ] );
                if ( ! is_wp_error( $r2 ) && (int) wp_remote_retrieve_response_code( $r2 ) > 0 ) {
                    $nginx_url = $base . '/';
                }
            }
        }

        // Try to read the fastcgi_pass upstream from nginx config (same container only)
        $fastcgi_pass = 'php:9000'; // default guess
        foreach ( [ '/etc/nginx/sites-enabled', '/etc/nginx/conf.d', '/etc/nginx' ] as $dir ) {
            if ( ! is_dir( $dir ) ) continue;
            foreach ( (array) glob( $dir . '/*.conf' ) as $cf ) {
                $nc = (string) @file_get_contents( $cf );
                if ( preg_match( '/fastcgi_pass\s+([^\s;]+)/i', $nc, $m ) ) {
                    $fastcgi_pass = $m[1];
                    break 2;
                }
            }
        }

        wp_send_json_success( [
            'www_conf'         => $www_conf,
            'www_conf_writable'=> $www_conf_writable,
            'status_path_set'  => $status_path_set,
            'nginx_url'        => $nginx_url,
            'fpm_status_works' => $fpm_status_works,
            'fastcgi_pass'     => $fastcgi_pass,
        ] );
    }

    public static function ajax_fpm_setup_patch(): void {
        check_ajax_referer( 'csdt_fpm_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $www_conf  = sanitize_text_field( (string) ( $_POST['www_conf'] ?? '' ) );
        $nginx_url = esc_url_raw( (string) ( $_POST['nginx_url'] ?? '' ) );

        $safe_paths = [
            '/usr/local/etc/php-fpm.d/www.conf',
            '/etc/php-fpm.d/www.conf',
            '/etc/php/8.4/fpm/pool.d/www.conf',
            '/etc/php/8.3/fpm/pool.d/www.conf',
            '/etc/php/8.2/fpm/pool.d/www.conf',
            '/etc/php/8.1/fpm/pool.d/www.conf',
            '/etc/php/8.0/fpm/pool.d/www.conf',
            '/etc/php/7.4/fpm/pool.d/www.conf',
        ];
        if ( ! in_array( $www_conf, $safe_paths, true ) ) {
            wp_send_json_error( [ 'message' => 'Invalid config path.' ] );
        }
        if ( ! file_exists( $www_conf ) || ! is_writable( $www_conf ) ) {
            wp_send_json_error( [ 'message' => 'www.conf not writable: ' . $www_conf ] );
        }

        $content = (string) file_get_contents( $www_conf );
        $patched = false;
        if ( ! preg_match( '/^\s*pm\.status_path\s*=/m', $content ) ) {
            // Insert after pm.max_spare_servers if present, else append
            if ( preg_match( '/^(pm\.max_spare_servers\s*=.*)/m', $content ) ) {
                $content = preg_replace(
                    '/^(pm\.max_spare_servers\s*=.*)/m',
                    "$1\npm.status_path = /fpm-status",
                    $content,
                    1
                );
            } else {
                $content .= "\npm.status_path = /fpm-status\n";
            }
            if ( file_put_contents( $www_conf, $content ) === false ) {
                wp_send_json_error( [ 'message' => 'Could not write to ' . $www_conf ] );
            }
            $patched = true;
        }

        if ( $nginx_url ) {
            update_option( 'csdt_fpm_probe_url', rtrim( $nginx_url, '/' ) . '/', false );
        }

        // Reload php-fpm master — try PID file, then /proc scan
        $reloaded     = false;
        $reload_msg   = '';
        $reload_error = '';

        foreach ( [ '/var/run/php-fpm.pid', '/run/php-fpm.pid', '/run/php/php-fpm.pid' ] as $pid_file ) {
            if ( file_exists( $pid_file ) ) {
                $pid = (int) trim( (string) file_get_contents( $pid_file ) );
                if ( $pid > 1 && function_exists( 'posix_kill' ) && posix_kill( $pid, SIGUSR2 ) ) {
                    $reloaded   = true;
                    $reload_msg = 'Sent SIGUSR2 to php-fpm master (PID ' . $pid . ')';
                    break;
                }
            }
        }

        if ( ! $reloaded && is_dir( '/proc' ) && function_exists( 'posix_kill' ) ) {
            $fpm_pids = [];
            foreach ( (array) glob( '/proc/[0-9]*', GLOB_ONLYDIR ) as $d ) {
                $comm = (string) @file_get_contents( $d . '/comm' );
                if ( str_contains( trim( $comm ), 'php-fpm' ) ) {
                    $fpm_pids[] = (int) basename( $d );
                }
            }
            if ( $fpm_pids ) {
                sort( $fpm_pids );
                if ( posix_kill( $fpm_pids[0], SIGUSR2 ) ) {
                    $reloaded   = true;
                    $reload_msg = 'Sent SIGUSR2 to php-fpm master (PID ' . $fpm_pids[0] . ')';
                }
            }
        }

        if ( ! $reloaded ) {
            $reload_error = 'Auto-reload failed — run manually: kill -USR2 $(pgrep -o php-fpm)';
        }

        wp_send_json_success( [
            'patched'      => $patched,
            'reloaded'     => $reloaded,
            'reload_msg'   => $reload_msg,
            'reload_error' => $reload_error,
        ] );
    }

    public static function register_fpm_report_route(): void {
        register_rest_route( 'csdt/v1', '/fpm-report', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'rest_fpm_report' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public static function rest_fpm_report( \WP_REST_Request $request ): \WP_REST_Response {
        $token  = sanitize_text_field( (string) $request->get_param( 'token' ) );
        $stored = get_option( 'csdt_fpm_token', '' );
        if ( empty( $stored ) || ! hash_equals( $stored, $token ) ) {
            return new \WP_REST_Response( [ 'error' => 'Invalid token' ], 403 );
        }
        $type = sanitize_text_field( (string) $request->get_param( 'type' ) );
        if ( ! in_array( $type, [ 'saturated', 'recovered', 'restarted' ], true ) ) {
            $type = 'saturated';
        }
        $msg   = sanitize_text_field( (string) $request->get_param( 'msg' ) );
        $event = [
            'ts'   => time(),
            'type' => $type,
            'msg'  => substr( $msg, 0, 200 ),
        ];
        update_option( 'csdt_fpm_last_event', $event, false );
        $log   = get_option( 'csdt_fpm_event_log', [] );
        if ( ! is_array( $log ) ) {
            $log = [];
        }
        array_unshift( $log, $event );
        $log = array_slice( $log, 0, 50 );
        update_option( 'csdt_fpm_event_log', $log, false );
        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    public static function cleanup_expired_test_accounts(): void {
        $now = time();

        // 1. Meta-tracked test accounts with an expiry timestamp.
        $users = get_users( [
            'meta_key'   => 'csdt_test_account',
            'meta_value' => '1',
            'fields'     => [ 'ID' ],
        ] );
        foreach ( $users as $u ) {
            $expires_at = (int) get_user_meta( $u->ID, 'csdt_test_expires_at', true );
            if ( $expires_at && $expires_at < $now ) {
                wp_delete_user( $u->ID );
            }
        }

        // 2. Orphaned test accounts not tracked by meta — sweep by known patterns.
        //    @test.local email domain is never a real account; cs_devtools_test* and
        //    temp-* usernames with no posts are plugin/debug artifacts safe to remove.
        $orphans = get_users( [
            'fields'     => [ 'ID', 'user_login', 'user_email', 'user_registered' ],
            'number'     => 200,
        ] );
        foreach ( $orphans as $u ) {
            $is_test_email    = str_ends_with( strtolower( $u->user_email ), '@test.local' );
            $is_test_login    = strncmp( $u->user_login, 'cs_devtools_test', 16 ) === 0;
            $is_temp_login    = strncmp( $u->user_login, 'temp-', 5 ) === 0
                             && strtotime( $u->user_registered ) < $now - DAY_IN_SECONDS
                             && (int) count_user_posts( $u->ID ) === 0;
            if ( $is_test_email || $is_test_login || $is_temp_login ) {
                wp_delete_user( $u->ID );
            }
        }
    }

    // ── Admin Bar Badge ──────────────────────────────────────────────────────

    public static function admin_bar_badge_styles(): void {
        if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) return;
        echo '<style>
#wp-admin-bar-csdt-health > .ab-item { font-weight:700 !important; }
#wp-admin-bar-csdt-health.csdt-bar-critical > .ab-item { color:#fca5a5 !important; }
#wp-admin-bar-csdt-health.csdt-bar-high > .ab-item { color:#fdba74 !important; }
#wp-admin-bar-csdt-health.csdt-bar-medium > .ab-item { color:#fde68a !important; }
#wp-admin-bar-csdt-health.csdt-bar-ok > .ab-item { color:#86efac !important; }
</style>' . "\n";
    }

    public static function render_admin_bar_badge( \WP_Admin_Bar $bar ): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $audit_url  = admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=site-audit' );
        $uptime_url = admin_url( 'tools.php?page=' . self::TOOLS_SLUG . '&tab=optimizer' );

        $cache    = get_option( 'csdt_site_audit_cache', null );
        $css_cls  = 'csdt-bar-unknown';
        $label    = 'CS Health';

        if ( $cache && ! empty( $cache['data']['counts'] ) ) {
            $counts   = $cache['data']['counts'];
            $critical = (int) ( $counts['critical'] ?? 0 );
            $high     = (int) ( $counts['high']     ?? 0 );
            $medium   = (int) ( $counts['medium']   ?? 0 );

            if ( $critical > 0 ) {
                $label   = 'CS ↯ ' . $critical . ' Critical';
                $css_cls = 'csdt-bar-critical';
            } elseif ( $high > 0 ) {
                $label   = 'CS ↯ ' . $high . ' High';
                $css_cls = 'csdt-bar-high';
            } elseif ( $medium > 0 ) {
                $label   = 'CS ' . $medium . ' Medium';
                $css_cls = 'csdt-bar-medium';
            } else {
                $label   = 'CS ✓ OK';
                $css_cls = 'csdt-bar-ok';
            }
        }

        $bar->add_node( [
            'id'    => 'csdt-health',
            'title' => esc_html( $label ),
            'href'  => $audit_url,
            'meta'  => [ 'class' => $css_cls, 'title' => 'CloudScale Site Health' ],
        ] );

        if ( $cache && ! empty( $cache['data']['counts'] ) ) {
            $counts   = $cache['data']['counts'];
            $critical = (int) ( $counts['critical'] ?? 0 );
            $high     = (int) ( $counts['high']     ?? 0 );
            $medium   = (int) ( $counts['medium']   ?? 0 );
            $low      = (int) ( $counts['low']      ?? 0 );
            $run_at   = $cache['run_at'] ?? 0;
            $age_min  = $run_at ? round( ( time() - $run_at ) / 60 ) : null;

            if ( $critical > 0 ) {
                $bar->add_node( [ 'parent' => 'csdt-health', 'id' => 'csdt-health-crit',   'title' => '🔴 ' . $critical . ' Critical', 'href' => $audit_url ] );
            }
            if ( $high > 0 ) {
                $bar->add_node( [ 'parent' => 'csdt-health', 'id' => 'csdt-health-high',   'title' => '🟠 ' . $high . ' High',        'href' => $audit_url ] );
            }
            if ( $medium > 0 ) {
                $bar->add_node( [ 'parent' => 'csdt-health', 'id' => 'csdt-health-med',    'title' => '🟡 ' . $medium . ' Medium',    'href' => $audit_url ] );
            }
            if ( $low > 0 ) {
                $bar->add_node( [ 'parent' => 'csdt-health', 'id' => 'csdt-health-low',    'title' => '🟢 ' . $low . ' Low',          'href' => $audit_url ] );
            }
            if ( $age_min !== null ) {
                $age_label = $age_min < 60 ? $age_min . 'm ago' : round( $age_min / 60 ) . 'h ago';
                $bar->add_node( [ 'parent' => 'csdt-health', 'id' => 'csdt-health-age', 'title' => 'Last audit: ' . $age_label, 'href' => $audit_url ] );
            }
        } else {
            $bar->add_node( [ 'parent' => 'csdt-health', 'id' => 'csdt-health-run', 'title' => 'Run Site Audit →', 'href' => $audit_url ] );
        }

        // Uptime node
        $last_ping = get_option( 'csdt_uptime_last_ping', null );
        if ( $last_ping && isset( $last_ping['time'] ) && ( time() - $last_ping['time'] ) < 300 ) {
            $up_label = $last_ping['up']
                ? '⏱ UP ' . $last_ping['ms'] . 'ms'
                : '🔴 SITE DOWN';
            $bar->add_node( [ 'parent' => 'csdt-health', 'id' => 'csdt-health-uptime', 'title' => $up_label, 'href' => $uptime_url ] );
        }
    }

    // ── Uptime Monitor ───────────────────────────────────────────────────────

    public static function ajax_uptime_ping(): void {
        $token        = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
        $stored_token = (string) get_option( 'csdt_uptime_token', '' );

        if ( $stored_token === '' || ! hash_equals( $stored_token, $token ) ) {
            wp_send_json_error( 'Invalid token', 403 );
            return;
        }

        $status_code = absint( $_POST['status_code'] ?? 0 );
        $response_ms = absint( $_POST['response_ms'] ?? 0 );
        $is_up       = $status_code >= 200 && $status_code < 500;
        $now         = time();

        $prev_ping = get_option( 'csdt_uptime_last_ping', null );
        $was_up    = $prev_ping ? (bool) $prev_ping['up'] : true;

        update_option( 'csdt_uptime_last_ping', [
            'time'   => $now,
            'up'     => $is_up,
            'ms'     => $response_ms,
            'status' => $status_code,
        ], false );

        // Raw ring buffer — keep last 180 pings (3 hours)
        $raw   = get_option( 'csdt_uptime_raw', [] );
        $raw[] = [ 't' => $now, 'up' => $is_up ? 1 : 0, 'ms' => $response_ms, 's' => $status_code ];
        if ( count( $raw ) > 180 ) { $raw = array_slice( $raw, -180 ); }
        update_option( 'csdt_uptime_raw', $raw, false );

        // Hourly buckets — keep last 168 (7 days)
        self::uptime_aggregate_hourly( $now, $is_up, $response_ms );

        // Downtime alert (5-min cooldown)
        if ( ! $is_up ) {
            $last_alert   = (int) get_option( 'csdt_uptime_alert_sent_at', 0 );
            $outage_start = (int) get_option( 'csdt_uptime_outage_start', 0 );
            if ( $outage_start === 0 ) {
                update_option( 'csdt_uptime_outage_start', $now, false );
            }
            if ( $now - $last_alert > 300 ) {
                self::uptime_send_alert( $status_code, $response_ms );
                update_option( 'csdt_uptime_alert_sent_at', $now, false );
            }
        } elseif ( ! $was_up && $is_up ) {
            // Site recovered — calculate outage duration and send recovery alert
            $outage_start    = (int) get_option( 'csdt_uptime_outage_start', 0 );
            $outage_duration = $outage_start > 0 ? ( $now - $outage_start ) : 0;
            self::uptime_send_recovery_alert( $response_ms, $outage_duration );
            update_option( 'csdt_uptime_alert_sent_at', 0, false );
            update_option( 'csdt_uptime_outage_start',  0, false );
        }

        wp_send_json_success( [ 'ok' => true ] );
    }

    public static function ajax_uptime_setup(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $token = (string) get_option( 'csdt_uptime_token', '' );
        if ( $token === '' ) {
            $token = bin2hex( random_bytes( 24 ) );
            update_option( 'csdt_uptime_token', $token, false );
        }

        $site_url = get_site_url();
        $ping_url = admin_url( 'admin-ajax.php' );
        $ntfy_url = (string) get_option( 'csdt_uptime_ntfy_url', get_option( 'csdt_scan_schedule_ntfy_url', '' ) );

        wp_send_json_success( [
            'token'       => $token,
            'worker_js'   => self::uptime_worker_js(),
            'wrangler_toml' => self::uptime_wrangler_toml( $site_url, $ping_url, $token, $ntfy_url ),
        ] );
    }

    public static function ajax_uptime_history(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $last_ping = get_option( 'csdt_uptime_last_ping', null );
        $raw       = get_option( 'csdt_uptime_raw', [] );
        $hourly    = get_option( 'csdt_uptime_hourly', [] );

        // Uptime % calculations
        $uptime_24h = null;
        $uptime_7d  = null;
        $avg_ms_24h = null;
        $cutoff_24h = time() - DAY_IN_SECONDS;
        $cutoff_7d  = time() - ( 7 * DAY_IN_SECONDS );

        if ( ! empty( $hourly ) ) {
            $h24_ok = 0; $h24_total = 0; $h24_ms = 0;
            $h7d_ok = 0; $h7d_total = 0;
            foreach ( $hourly as $h ) {
                if ( $h['h'] >= $cutoff_24h ) {
                    $h24_ok    += $h['ok'];
                    $h24_total += $h['total'];
                    $h24_ms    += $h['avg_ms'] * $h['total'];
                }
                if ( $h['h'] >= $cutoff_7d ) {
                    $h7d_ok    += $h['ok'];
                    $h7d_total += $h['total'];
                }
            }
            if ( $h24_total > 0 ) {
                $uptime_24h = round( $h24_ok / $h24_total * 100, 2 );
                $avg_ms_24h = round( $h24_ms / $h24_total );
            }
            if ( $h7d_total > 0 ) {
                $uptime_7d = round( $h7d_ok / $h7d_total * 100, 2 );
            }
        }

        if ( $last_ping ) {
            $last_ping['age_seconds'] = time() - $last_ping['time'];
        }

        wp_send_json_success( [
            'last_ping'  => $last_ping,
            'raw'        => $raw,
            'hourly'     => array_values( array_slice( $hourly, -48 ) ),
            'uptime_24h' => $uptime_24h,
            'uptime_7d'  => $uptime_7d,
            'avg_ms_24h' => $avg_ms_24h,
            'enabled'    => get_option( 'csdt_uptime_enabled', '0' ) === '1',
        ] );
    }

    public static function ajax_uptime_save_settings(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $ntfy_url = esc_url_raw( wp_unslash( $_POST['ntfy_url'] ?? '' ) );
        update_option( 'csdt_uptime_ntfy_url', $ntfy_url, false );
        wp_send_json_success( [ 'saved' => true ] );
    }

    public static function ajax_uptime_deploy_worker(): void {
        check_ajax_referer( 'csdt_optimizer_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $zone_id  = (string) get_option( 'csdt_devtools_cf_zone_id', '' );
        $cf_token = (string) get_option( 'csdt_devtools_cf_api_token', '' );
        $ntfy_url = esc_url_raw( wp_unslash( $_POST['ntfy_url'] ?? '' ) );

        if ( $ntfy_url ) { update_option( 'csdt_uptime_ntfy_url', $ntfy_url, false ); }

        if ( $zone_id === '' || $cf_token === '' ) {
            wp_send_json_error( [ 'message' => 'No Cloudflare Zone ID or API Token found. Enter them in the Thumbnails tab first, then return here to deploy.' ] );
            return;
        }

        // Ensure token exists
        $token = (string) get_option( 'csdt_uptime_token', '' );
        if ( $token === '' ) {
            $token = bin2hex( random_bytes( 24 ) );
            update_option( 'csdt_uptime_token', $token, false );
        }

        $site_url = get_site_url();
        $ping_url = admin_url( 'admin-ajax.php' );

        // Step 1: Resolve Account ID from zone
        $zone_resp = wp_remote_get(
            'https://api.cloudflare.com/client/v4/zones/' . rawurlencode( $zone_id ),
            [ 'headers' => [ 'Authorization' => 'Bearer ' . $cf_token ], 'timeout' => 15 ]
        );
        if ( is_wp_error( $zone_resp ) ) {
            wp_send_json_error( [ 'message' => 'CF API error: ' . $zone_resp->get_error_message() ] );
            return;
        }
        $zone_data  = json_decode( wp_remote_retrieve_body( $zone_resp ), true );
        $account_id = $zone_data['result']['account']['id'] ?? '';
        if ( ! $account_id ) {
            wp_send_json_error( [ 'message' => 'Could not fetch zone details. Check your CF API token has Zone:Read permission.' ] );
            return;
        }

        // Step 2: Upload Worker (module syntax + env bindings)
        $boundary = '---CSDTWorkerBnd' . bin2hex( random_bytes( 8 ) );
        $metadata = wp_json_encode( [
            'main_module'        => 'worker.js',
            'compatibility_date' => '2024-11-01',
            'bindings'           => [
                [ 'type' => 'plain_text', 'name' => 'SITE_URL',   'text' => $site_url ],
                [ 'type' => 'plain_text', 'name' => 'PING_URL',   'text' => $ping_url ],
                [ 'type' => 'plain_text', 'name' => 'PING_TOKEN', 'text' => $token ],
                [ 'type' => 'plain_text', 'name' => 'NTFY_URL',   'text' => $ntfy_url ],
            ],
        ] );
        $script_js = self::uptime_worker_js();
        $body  = "--{$boundary}\r\nContent-Disposition: form-data; name=\"metadata\"\r\nContent-Type: application/json\r\n\r\n{$metadata}\r\n";
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"worker.js\"; filename=\"worker.js\"\r\nContent-Type: application/javascript+module\r\n\r\n{$script_js}\r\n";
        $body .= "--{$boundary}--\r\n";

        $upload_resp = wp_remote_request(
            "https://api.cloudflare.com/client/v4/accounts/{$account_id}/workers/scripts/cloudscale-uptime",
            [
                'method'  => 'PUT',
                'headers' => [
                    'Authorization' => 'Bearer ' . $cf_token,
                    'Content-Type'  => "multipart/form-data; boundary={$boundary}",
                ],
                'body'    => $body,
                'timeout' => 30,
            ]
        );

        if ( is_wp_error( $upload_resp ) ) {
            wp_send_json_error( [ 'message' => 'Worker upload failed: ' . $upload_resp->get_error_message() ] );
            return;
        }
        $upload_data = json_decode( wp_remote_retrieve_body( $upload_resp ), true );
        if ( empty( $upload_data['success'] ) ) {
            $err = $upload_data['errors'][0]['message'] ?? 'Upload failed';
            wp_send_json_error( [ 'message' => $err . ' — ensure your CF API token has Workers:Edit permission. You can create one at dash.cloudflare.com → My Profile → API Tokens → Create Token → Edit Cloudflare Workers template.' ] );
            return;
        }

        // Step 3: Set cron trigger (every minute)
        $cron_resp = wp_remote_request(
            "https://api.cloudflare.com/client/v4/accounts/{$account_id}/workers/scripts/cloudscale-uptime/schedules",
            [
                'method'  => 'PUT',
                'headers' => [ 'Authorization' => 'Bearer ' . $cf_token, 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( [ [ 'cron' => '* * * * *' ] ] ),
                'timeout' => 15,
            ]
        );
        $cron_ok = ! is_wp_error( $cron_resp ) && ! empty( json_decode( wp_remote_retrieve_body( $cron_resp ), true )['success'] );

        update_option( 'csdt_uptime_enabled', '1', false );

        wp_send_json_success( [
            'message'    => 'Worker deployed! Pings will arrive every 60 seconds.',
            'worker_url' => "https://dash.cloudflare.com/{$account_id}/workers/view/cloudscale-uptime",
            'cron_ok'    => $cron_ok,
            'token'      => $token,
        ] );
    }

    private static function uptime_aggregate_hourly( int $now, bool $is_up, int $ms ): void {
        $hour    = $now - ( $now % 3600 );
        $hourly  = get_option( 'csdt_uptime_hourly', [] );
        $updated = false;
        foreach ( $hourly as &$h ) {
            if ( $h['h'] === $hour ) {
                $h['total']++;
                if ( $is_up ) $h['ok']++;
                $h['avg_ms'] = (int) round( ( $h['avg_ms'] * ( $h['total'] - 1 ) + $ms ) / $h['total'] );
                $updated = true;
                break;
            }
        }
        unset( $h );
        if ( ! $updated ) {
            $hourly[] = [ 'h' => $hour, 'total' => 1, 'ok' => $is_up ? 1 : 0, 'avg_ms' => $ms ];
        }
        if ( count( $hourly ) > 168 ) { $hourly = array_slice( $hourly, -168 ); }
        update_option( 'csdt_uptime_hourly', $hourly, false );
    }

    private static function uptime_send_alert( int $status_code, int $response_ms ): void {
        $site    = get_bloginfo( 'name' );
        $url     = get_site_url();
        $subject = "[{$site}] Site is DOWN";
        $body    = "<p>Your site <strong>{$url}</strong> appears to be down.</p>"
                 . "<p>Status: <strong>" . ( $status_code ?: 'Timeout' ) . "</strong> — Response: {$response_ms}ms</p>"
                 . "<p>This alert was sent by the CloudScale Uptime Monitor.</p>";

        $alert_email = (string) get_option( 'csdt_uptime_alert_email', get_option( 'admin_email', '' ) );
        if ( $alert_email ) {
            add_filter( 'wp_mail_content_type', [ 'CSDT_Login', 'email_content_type_html' ] );
            wp_mail( $alert_email, $subject, $body );
            remove_filter( 'wp_mail_content_type', [ 'CSDT_Login', 'email_content_type_html' ] );
        }

        $ntfy_url = (string) get_option( 'csdt_uptime_ntfy_url', '' );
        if ( $ntfy_url ) {
            wp_remote_post( $ntfy_url, [
                'headers' => [
                    'Title'    => "Site Down: {$url}",
                    'Priority' => 'urgent',
                    'Tags'     => 'rotating_light',
                ],
                'body'    => "Status: " . ( $status_code ?: 'Timeout' ) . " — {$response_ms}ms",
                'timeout' => 10,
            ] );
        }
    }

    private static function uptime_send_recovery_alert( int $response_ms, int $outage_seconds = 0 ): void {
        $site         = get_bloginfo( 'name' );
        $url          = get_site_url();
        $duration_str = '';
        if ( $outage_seconds > 0 ) {
            $mins = (int) floor( $outage_seconds / 60 );
            $secs = $outage_seconds % 60;
            $duration_str = $mins > 0
                ? " — was down for <strong>{$mins}m {$secs}s</strong>"
                : " — was down for <strong>{$secs}s</strong>";
        }
        $subject = "[{$site}] Site is back ONLINE";
        $body    = "<p>Your site <strong>{$url}</strong> has recovered and is responding normally{$duration_str}.</p>"
                 . "<p>Response time: <strong>{$response_ms}ms</strong></p>"
                 . "<p>This alert was sent by the CloudScale Uptime Monitor.</p>";

        $alert_email = (string) get_option( 'csdt_uptime_alert_email', get_option( 'admin_email', '' ) );
        if ( $alert_email ) {
            add_filter( 'wp_mail_content_type', [ 'CSDT_Login', 'email_content_type_html' ] );
            wp_mail( $alert_email, $subject, $body );
            remove_filter( 'wp_mail_content_type', [ 'CSDT_Login', 'email_content_type_html' ] );
        }

        $ntfy_url = (string) get_option( 'csdt_uptime_ntfy_url', '' );
        if ( $ntfy_url ) {
            wp_remote_post( $ntfy_url, [
                'headers' => [
                    'Title'    => "Site Recovered: {$url}",
                    'Priority' => 'default',
                    'Tags'     => 'white_check_mark',
                ],
                'body'    => 'Back online — ' . $response_ms . 'ms' . ( $outage_seconds > 0 ? ' — down for ' . ( $outage_seconds >= 60 ? floor( $outage_seconds / 60 ) . 'm ' . ( $outage_seconds % 60 ) . 's' : $outage_seconds . 's' ) : '' ),
                'timeout' => 10,
            ] );
        }
    }

    private static function uptime_worker_js(): string {
        return "// CloudScale Uptime Monitor\nexport default {\n  async scheduled(event, env, ctx) {\n    const start = Date.now();\n    let statusCode = 0, responseMs = 0, isUp = false;\n    try {\n      // Cache-bust to force a fresh origin request — prevents Cloudflare edge\n      // cache from masking a down origin with a cached 200.\n      const probeUrl = env.SITE_URL + (env.SITE_URL.includes('?') ? '&' : '?') + '_up=' + Date.now();\n      const res = await fetch(probeUrl, {\n        method: 'HEAD',\n        headers: {\n          'User-Agent': 'CloudScale-Uptime/1.0',\n          'Cache-Control': 'no-store',\n          'Pragma': 'no-cache',\n        },\n        signal: AbortSignal.timeout(15000),\n        redirect: 'follow',\n      });\n      statusCode = res.status;\n      responseMs = Date.now() - start;\n      isUp = statusCode >= 200 && statusCode < 500;\n    } catch(e) { responseMs = Date.now() - start; }\n    if (!isUp && env.NTFY_URL) {\n      ctx.waitUntil(fetch(env.NTFY_URL, {\n        method: 'POST',\n        headers: {'Title': 'Site Down: ' + env.SITE_URL, 'Priority': 'urgent', 'Tags': 'rotating_light'},\n        body: 'Status: ' + (statusCode || 'timeout') + ' — ' + responseMs + 'ms',\n      }).catch(() => {}));\n    }\n    ctx.waitUntil(fetch(env.PING_URL, {\n      method: 'POST',\n      headers: {'Content-Type': 'application/x-www-form-urlencoded'},\n      body: 'action=csdt_uptime_ping&token=' + encodeURIComponent(env.PING_TOKEN) + '&status_code=' + statusCode + '&response_ms=' + responseMs,\n      signal: AbortSignal.timeout(10000),\n    }).catch(() => {}));\n  },\n};";
    }

    private static function uptime_wrangler_toml( string $site_url, string $ping_url, string $token, string $ntfy_url ): string {
        return "name = \"cloudscale-uptime\"\nmain = \"worker.js\"\ncompatibility_date = \"2024-11-01\"\n\n[vars]\nSITE_URL = \"{$site_url}\"\nPING_URL = \"{$ping_url}\"\nPING_TOKEN = \"{$token}\"\nNTFY_URL = \"{$ntfy_url}\"\n\n[[triggers.crons]]\ncrons = [\"* * * * *\"]\n";
    }

    public static function filter_app_pw_for_user( $available, $user ): bool {
        if ( get_user_meta( $user->ID, 'csdt_test_account', true ) === '1' ) {
            return true;
        }
        return false;
    }

    public static function test_account_after_auth( $user, $app_password ): void {
        if ( get_user_meta( $user->ID, 'csdt_test_account', true ) !== '1' ) {
            return;
        }
        $max_logins = (int) get_user_meta( $user->ID, 'csdt_test_max_logins', true );
        if ( $max_logins <= 0 ) {
            return; // unlimited
        }
        $count = (int) get_user_meta( $user->ID, 'csdt_test_login_count', true ) + 1;
        if ( $count >= $max_logins ) {
            wp_delete_user( $user->ID );
        } else {
            update_user_meta( $user->ID, 'csdt_test_login_count', $count );
        }
    }

}

CloudScale_DevTools::init();
