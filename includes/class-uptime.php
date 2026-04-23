<?php
/**
 * Uptime monitor — Cloudflare Worker ping with alert/recovery notifications.
 *
 * @package CloudScale_DevTools
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CSDT_Uptime {

    // ── REST API — Readiness Probe ───────────────────────────────────────────

    public static function get_readiness_url(): string { return self::readiness_url(); }

    private static function readiness_url(): string {
        $slug = sanitize_key( (string) get_option( 'csdt_readiness_slug', '' ) );
        // Primary path: csdt/cf-callback/{slug} — allows WAF wildcard exclusion on /wp-json/csdt/cf-callback/*
        return rest_url( 'csdt/cf-callback/' . ( $slug ?: 'ready' ) );
    }

    public static function register_rest_routes(): void {
        $args = [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'rest_readiness_probe' ],
            'permission_callback' => '__return_true',
        ];
        // Primary route — WAF-excluded namespace
        register_rest_route( 'csdt/cf-callback', '/(?P<slug>[a-zA-Z0-9_-]+)', $args );
        // Legacy routes kept for backward compat with already-deployed workers
        register_rest_route( 'csdt/v1', '/ready', $args );
        register_rest_route( 'csdt/v1', '/ready/(?P<slug>[a-zA-Z0-9_-]+)', $args );
    }

    public static function rest_readiness_probe( \WP_REST_Request $request ): \WP_REST_Response {
        // Slug guard — URL slug must match stored slug (or 'ready' when none is set)
        $stored_slug = sanitize_key( (string) get_option( 'csdt_readiness_slug', '' ) );
        $url_slug    = sanitize_key( (string) ( $request->get_param( 'slug' ) ?? '' ) );
        $expected    = $stored_slug ?: 'ready';
        if ( $url_slug !== $expected ) {
            return new \WP_REST_Response( null, 404 );
        }

        $stored_token = (string) get_option( 'csdt_uptime_token', '' );

        // Accept Bearer token in Authorization header or ?token= query param
        $auth   = $request->get_header( 'Authorization' ) ?? '';
        $token  = str_starts_with( $auth, 'Bearer ' ) ? substr( $auth, 7 ) : (string) ( $request->get_param( 'token' ) ?? '' );

        if ( $stored_token === '' || ! hash_equals( $stored_token, $token ) ) {
            update_option( 'csdt_readiness_last_bad_auth', time(), false );
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Unauthorized' ], 401 );
        }

        $checks = self::run_readiness_checks();
        $all_ok = ! in_array( false, array_column( $checks, 'ok' ), true );
        $now    = time();

        update_option( 'csdt_readiness_last_queried',       $now,   false );
        update_option( 'csdt_readiness_last_queried_checks', $checks, false );

        return new \WP_REST_Response( [
            'ok'         => $all_ok,
            'checks'     => $checks,
            'site'       => get_site_url(),
            'checked_at' => $now,
        ], $all_ok ? 200 : 503 );
    }

    private static function run_readiness_checks(): array {
        global $wpdb;
        $checks = [];

        // DB check
        $db_ok = false;
        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $result = $wpdb->get_var( 'SELECT 1' );
            $db_ok  = ( '1' === (string) $result );
        } catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
        }
        $checks['db'] = [ 'ok' => $db_ok, 'message' => $db_ok ? 'Connected' : 'Query failed' ];

        // PHP-FPM saturation check
        $fpm_ok   = true;
        $fpm_info = [];
        $probe    = rtrim( get_option( 'csdt_fpm_probe_url', '' ), '/' );
        if ( $probe ) {
            $resp = wp_remote_get( $probe . '/fpm-status?json', [ 'timeout' => 3, 'sslverify' => false ] );
            if ( ! is_wp_error( $resp ) && 200 === wp_remote_retrieve_response_code( $resp ) ) {
                $body = json_decode( wp_remote_retrieve_body( $resp ), true );
                if ( is_array( $body ) ) {
                    $active  = (int) ( $body['active processes'] ?? 0 );
                    $total   = (int) ( $body['total processes']  ?? 0 );
                    $sat     = $total > 0 ? (int) round( $active / $total * 100 ) : 0;
                    $fpm_ok  = $sat < 90;
                    $fpm_info = [ 'active' => $active, 'total' => $total, 'saturation_pct' => $sat ];
                }
            }
        }
        $checks['fpm'] = array_merge( [ 'ok' => $fpm_ok ], $fpm_info );

        // WordPress (implicit — reaching this code means WP booted)
        $checks['wp'] = [ 'ok' => true, 'version' => get_bloginfo( 'version' ) ];

        return $checks;
    }

    public static function admin_bar_badge_styles(): void {
        if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) return;
        $css = '#wp-admin-bar-csdt-health>.ab-item{font-weight:700!important}'
             . '#wp-admin-bar-csdt-health.csdt-bar-critical>.ab-item{color:#fca5a5!important}'
             . '#wp-admin-bar-csdt-health.csdt-bar-high>.ab-item{color:#fdba74!important}'
             . '#wp-admin-bar-csdt-health.csdt-bar-medium>.ab-item{color:#fde68a!important}'
             . '#wp-admin-bar-csdt-health.csdt-bar-ok>.ab-item{color:#86efac!important}';
        wp_add_inline_style( 'admin-bar', $css );
    }

    public static function render_admin_bar_badge( \WP_Admin_Bar $bar ): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $audit_url  = admin_url( 'tools.php?page=' . CloudScale_DevTools::TOOLS_SLUG . '&tab=site-audit' );
        $uptime_url = admin_url( 'tools.php?page=' . CloudScale_DevTools::TOOLS_SLUG . '&tab=optimizer' );

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

    private static function record_ping( int $status_code, int $response_ms ): void {
        $is_up = $status_code >= 200 && $status_code < 500;
        $now   = time();
        update_option( 'csdt_uptime_last_ping', [
            'time'   => $now,
            'up'     => $is_up,
            'ms'     => $response_ms,
            'status' => $status_code,
        ], false );
        $raw   = get_option( 'csdt_uptime_raw', [] );
        $raw[] = [ 't' => $now, 'up' => $is_up ? 1 : 0, 'ms' => $response_ms, 's' => $status_code ];
        if ( count( $raw ) > 180 ) { $raw = array_slice( $raw, -180 ); }
        update_option( 'csdt_uptime_raw', $raw, false );
        self::uptime_aggregate_hourly( $now, $is_up, $response_ms );
    }

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

        self::record_ping( $status_code, $response_ms );

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
        check_ajax_referer( CloudScale_DevTools::OPTIMIZER_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $token = (string) get_option( 'csdt_uptime_token', '' );
        if ( $token === '' ) {
            $token = bin2hex( random_bytes( 24 ) );
            update_option( 'csdt_uptime_token', $token, false );
        }

        $site_url  = get_site_url();
        $ping_url  = admin_url( 'admin-ajax.php' );
        $ready_url = self::readiness_url();
        $ntfy_url  = (string) get_option( 'csdt_uptime_ntfy_url', get_option( 'csdt_scan_schedule_ntfy_url', '' ) );

        wp_send_json_success( [
            'token'         => $token,
            'ready_url'     => $ready_url,
            'ready_slug'    => get_option( 'csdt_readiness_slug', '' ),
            'worker_js'     => self::uptime_worker_js(),
            'wrangler_toml' => self::uptime_wrangler_toml( $site_url, $ping_url, $ready_url, $token, $ntfy_url ),
        ] );
    }

    public static function ajax_uptime_history(): void {
        check_ajax_referer( CloudScale_DevTools::OPTIMIZER_NONCE, 'nonce' );
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

        $readiness_last    = (int) get_option( 'csdt_readiness_last_queried',       0 );
        $readiness_bad     = (int) get_option( 'csdt_readiness_last_bad_auth',      0 );
        $readiness_checks  = get_option( 'csdt_readiness_last_queried_checks',      null );

        wp_send_json_success( [
            'last_ping'          => $last_ping,
            'raw'                => $raw,
            'hourly'             => array_values( array_slice( $hourly, -48 ) ),
            'uptime_24h'         => $uptime_24h,
            'uptime_7d'          => $uptime_7d,
            'avg_ms_24h'         => $avg_ms_24h,
            'enabled'            => get_option( 'csdt_uptime_enabled', '0' ) === '1',
            'ready_url'          => self::readiness_url(),
            'ready_slug'         => get_option( 'csdt_readiness_slug', '' ),
            'readiness_last'     => $readiness_last ?: null,
            'readiness_bad'      => $readiness_bad  ?: null,
            'readiness_checks'   => $readiness_checks,
        ] );
    }

    public static function ajax_uptime_save_settings(): void {
        check_ajax_referer( CloudScale_DevTools::OPTIMIZER_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $ntfy_url = esc_url_raw( wp_unslash( $_POST['ntfy_url'] ?? '' ) );
        update_option( 'csdt_uptime_ntfy_url', $ntfy_url, false );
        $slug = sanitize_key( wp_unslash( $_POST['ready_slug'] ?? '' ) );
        update_option( 'csdt_readiness_slug', $slug, false );
        wp_send_json_success( [ 'saved' => true, 'ready_url' => self::readiness_url() ] );
    }

    public static function ajax_uptime_test_endpoint(): void {
        check_ajax_referer( CloudScale_DevTools::OPTIMIZER_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $token      = (string) get_option( 'csdt_uptime_token', '' );
        $worker_url = (string) get_option( 'csdt_uptime_worker_url', '' );

        if ( $token === '' ) {
            wp_send_json_error( [ 'message' => 'No token set — click Generate Token first.' ] );
            return;
        }

        // Resolve worker URL if not stored — look it up via CF API
        if ( $worker_url === '' ) {
            $worker_url = self::resolve_worker_url();
            if ( $worker_url !== '' ) {
                update_option( 'csdt_uptime_worker_url', $worker_url, false );
            }
        }

        // If we have the worker URL, trigger CF Worker (full E2E: CF → readiness → ping back)
        if ( $worker_url !== '' ) {
            $start = microtime( true );
            $resp  = wp_remote_post( $worker_url, [
                'timeout' => 20,
                'headers' => [ 'Authorization' => 'Bearer ' . $token ],
            ] );
            $ms = (int) round( ( microtime( true ) - $start ) * 1000 );

            if ( is_wp_error( $resp ) ) {
                wp_send_json_error( [ 'message' => 'Could not reach CF Worker: ' . $resp->get_error_message() . '. Try redeploying the Worker to refresh its URL.' ] );
                return;
            }
            $code = wp_remote_retrieve_response_code( $resp );
            if ( $code === 401 ) {
                wp_send_json_error( [ 'message' => 'CF Worker rejected the token. Redeploy the Worker to sync the token.' ] );
                return;
            }
            $body       = json_decode( wp_remote_retrieve_body( $resp ), true );
            $probe_code = (int) ( $body['status_code'] ?? $code );
            $probe_ms   = (int) ( $body['response_ms']  ?? $ms );
            self::record_ping( $probe_code, $probe_ms );
            wp_send_json_success( [
                'via'         => 'cf_worker',
                'ok'          => ! empty( $body['ok'] ),
                'status_code' => $probe_code,
                'ms'          => $probe_ms,
                'worker_url'  => $worker_url,
            ] );
            return;
        }

        // Fallback: call readiness endpoint directly from WP server
        $url   = self::readiness_url();
        $start = microtime( true );
        $resp  = wp_remote_get( $url, [
            'timeout' => 15,
            'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Cache-Control' => 'no-store' ],
        ] );
        $ms = (int) round( ( microtime( true ) - $start ) * 1000 );

        if ( is_wp_error( $resp ) ) {
            wp_send_json_error( [ 'message' => 'Request failed: ' . $resp->get_error_message() ] );
            return;
        }
        $code = wp_remote_retrieve_response_code( $resp );
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        self::record_ping( (int) $code, $ms );
        wp_send_json_success( [
            'via'         => 'direct',
            'status_code' => $code,
            'ok'          => $code === 200,
            'ms'          => $ms,
            'checks'      => $body['checks'] ?? null,
            'url'         => $url,
        ] );
    }

    public static function ajax_uptime_deploy_worker(): void {
        check_ajax_referer( CloudScale_DevTools::OPTIMIZER_NONCE, 'nonce' );
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

        $site_url  = get_site_url();
        $ping_url  = admin_url( 'admin-ajax.php' );
        $ready_url = self::readiness_url();

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
                [ 'type' => 'plain_text', 'name' => 'SITE_URL',   'text' => $site_url  ],
                [ 'type' => 'plain_text', 'name' => 'PING_URL',   'text' => $ping_url  ],
                [ 'type' => 'plain_text', 'name' => 'READY_URL',  'text' => $ready_url ],
                [ 'type' => 'plain_text', 'name' => 'PING_TOKEN', 'text' => $token     ],
                [ 'type' => 'plain_text', 'name' => 'NTFY_URL',   'text' => $ntfy_url  ],
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

        // Step 4: Get workers.dev subdomain so we can trigger the worker via HTTP for the Test button
        $subdomain_resp = wp_remote_get(
            "https://api.cloudflare.com/client/v4/accounts/{$account_id}/workers/subdomain",
            [ 'headers' => [ 'Authorization' => 'Bearer ' . $cf_token ], 'timeout' => 10 ]
        );
        $worker_trigger_url = '';
        if ( ! is_wp_error( $subdomain_resp ) ) {
            $sub_data  = json_decode( wp_remote_retrieve_body( $subdomain_resp ), true );
            $subdomain = $sub_data['result']['subdomain'] ?? '';
            if ( $subdomain ) {
                $worker_trigger_url = "https://cloudscale-uptime.{$subdomain}.workers.dev";
                update_option( 'csdt_uptime_worker_url', $worker_trigger_url, false );
            }
        }

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

    private static function resolve_worker_url(): string {
        $zone_id  = (string) get_option( 'csdt_devtools_cf_zone_id', '' );
        $cf_token = (string) get_option( 'csdt_devtools_cf_api_token', '' );
        if ( $zone_id === '' || $cf_token === '' ) { return ''; }

        $zone_resp  = wp_remote_get( 'https://api.cloudflare.com/client/v4/zones/' . rawurlencode( $zone_id ),
            [ 'headers' => [ 'Authorization' => 'Bearer ' . $cf_token ], 'timeout' => 10 ] );
        if ( is_wp_error( $zone_resp ) ) { return ''; }
        $account_id = json_decode( wp_remote_retrieve_body( $zone_resp ), true )['result']['account']['id'] ?? '';
        if ( ! $account_id ) { return ''; }

        $sub_resp = wp_remote_get( "https://api.cloudflare.com/client/v4/accounts/{$account_id}/workers/subdomain",
            [ 'headers' => [ 'Authorization' => 'Bearer ' . $cf_token ], 'timeout' => 10 ] );
        if ( is_wp_error( $sub_resp ) ) { return ''; }
        $subdomain = json_decode( wp_remote_retrieve_body( $sub_resp ), true )['result']['subdomain'] ?? '';
        return $subdomain ? "https://cloudscale-uptime.{$subdomain}.workers.dev" : '';
    }

    private static function uptime_worker_js(): string {
        return <<<'JS'
// CloudScale Uptime Monitor — readiness probe
// HTTP POST / with Authorization: Bearer <PING_TOKEN> triggers a manual probe (used by WP Admin Test button)
async function runProbe(env, ctx) {
  const start = Date.now();
  let statusCode = 0, responseMs = 0, isUp = false;
  try {
    const res = await fetch(env.READY_URL, {
      method: 'GET',
      headers: {
        'Authorization': 'Bearer ' + env.PING_TOKEN,
        'User-Agent': 'CloudScale-Uptime/1.0',
        'Cache-Control': 'no-store',
      },
      signal: AbortSignal.timeout(15000),
    });
    statusCode = res.status;
    responseMs = Date.now() - start;
    isUp = statusCode === 200;
  } catch(e) { responseMs = Date.now() - start; }

  if (!isUp && env.NTFY_URL) {
    ctx.waitUntil(fetch(env.NTFY_URL, {
      method: 'POST',
      headers: {'Title': 'Site Down: ' + env.SITE_URL, 'Priority': 'urgent', 'Tags': 'rotating_light'},
      body: 'Readiness probe: ' + (statusCode ? 'HTTP ' + statusCode : 'timeout') + ' — ' + responseMs + 'ms',
    }).catch(() => {}));
  }

  ctx.waitUntil(fetch(env.PING_URL, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=csdt_uptime_ping&token=' + encodeURIComponent(env.PING_TOKEN) + '&status_code=' + statusCode + '&response_ms=' + responseMs,
    signal: AbortSignal.timeout(10000),
  }).catch(() => {}));

  return { statusCode, responseMs, isUp };
}

export default {
  async scheduled(event, env, ctx) {
    ctx.waitUntil(runProbe(env, ctx));
  },

  async fetch(request, env, ctx) {
    if (request.method !== 'POST') {
      return new Response('Method Not Allowed', { status: 405 });
    }
    const auth = request.headers.get('Authorization') || '';
    if (auth !== 'Bearer ' + env.PING_TOKEN) {
      return new Response('Unauthorized', { status: 401 });
    }
    const result = await runProbe(env, ctx);
    return new Response(JSON.stringify({ ok: result.isUp, status_code: result.statusCode, response_ms: result.responseMs, triggered: true }), {
      headers: { 'Content-Type': 'application/json' },
    });
  },
};
JS;
    }

    private static function uptime_wrangler_toml( string $site_url, string $ping_url, string $ready_url, string $token, string $ntfy_url ): string {
        return "name = \"cloudscale-uptime\"\nmain = \"worker.js\"\ncompatibility_date = \"2024-11-01\"\n\n[vars]\nSITE_URL = \"{$site_url}\"\nPING_URL = \"{$ping_url}\"\nREADY_URL = \"{$ready_url}\"\nPING_TOKEN = \"{$token}\"\nNTFY_URL = \"{$ntfy_url}\"\n\n[[triggers.crons]]\ncrons = [\"* * * * *\"]\n";
    }

}
