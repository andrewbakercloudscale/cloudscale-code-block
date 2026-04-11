<?php
/**
 * CloudScale Code Block — Passkey (WebAuthn) support
 *
 * Pure PHP, no Composer. Requires the OpenSSL extension (standard in all PHP builds).
 * Implements FIDO2 / WebAuthn credential registration and authentication.
 *
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ── Minimal CBOR decoder (subset needed for WebAuthn attestation/assertion) ──

final class CSDT_DevTools_CBOR {

    public static function decode( string $data ): mixed {
        $o = 0;
        return self::item( $data, $o );
    }

    private static function item( string $d, int &$o ): mixed {
        $b = ord( $d[ $o++ ] );
        $t = $b >> 5;
        $i = $b & 0x1f;
        $n = self::uint( $d, $o, $i );

        switch ( $t ) {
            case 0: return $n;
            case 1: return -1 - $n;
            case 2: $s = substr( $d, $o, $n ); $o += $n; return $s;
            case 3: $s = substr( $d, $o, $n ); $o += $n; return $s;
            case 4:
                $a = [];
                for ( $j = 0; $j < $n; $j++ ) { $a[] = self::item( $d, $o ); }
                return $a;
            case 5:
                $m = [];
                for ( $j = 0; $j < $n; $j++ ) { $k = self::item( $d, $o ); $m[ $k ] = self::item( $d, $o ); }
                return $m;
            case 6: return self::item( $d, $o );
            case 7:
                if ( $i === 20 ) return false;
                if ( $i === 21 ) return true;
                if ( $i === 22 || $i === 23 ) return null;
                if ( $i === 25 ) { $o += 2; return 0.0; }
                if ( $i === 26 ) { $f = unpack( 'G', substr( $d, $o, 4 ) )[1]; $o += 4; return $f; }
                if ( $i === 27 ) { $f = unpack( 'E', substr( $d, $o, 8 ) )[1]; $o += 8; return $f; }
        }
        return null;
    }

    private static function uint( string $d, int &$o, int $i ): int {
        if ( $i <= 23 ) return $i;
        if ( $i === 24 ) return ord( $d[ $o++ ] );
        if ( $i === 25 ) { $v = unpack( 'n', substr( $d, $o, 2 ) )[1]; $o += 2; return $v; }
        if ( $i === 26 ) { $v = unpack( 'N', substr( $d, $o, 4 ) )[1]; $o += 4; return $v; }
        if ( $i === 27 ) {
            $hi = unpack( 'N', substr( $d, $o, 4 ) )[1];
            $lo = unpack( 'N', substr( $d, $o + 4, 4 ) )[1];
            $o += 8;
            return $hi * 4294967296 + $lo;
        }
        throw new \RuntimeException( "CBOR: unsupported additional info $i" );
    }
}

// ── Passkey handler ──────────────────────────────────────────────────────────

class CSDT_DevTools_Passkey {

    const META_KEY          = 'csdt_devtools_passkeys';
    const CHALLENGE_PREFIX  = 'csdt_devtools_pk_ch_';   // + suffix (user_id or 'login_' + token)
    const CHALLENGE_TTL     = 120;           // seconds
    const NONCE_ACTION      = 'csdt_devtools_login_nonce';

    // ── Hooks ────────────────────────────────────────────────────────────────

    public static function register_hooks(): void {
        add_action( 'wp_ajax_csdt_devtools_pk_register_start',  [ __CLASS__, 'ajax_register_start'  ] );
        add_action( 'wp_ajax_csdt_devtools_pk_register_finish', [ __CLASS__, 'ajax_register_finish' ] );
        add_action( 'wp_ajax_csdt_devtools_pk_delete',          [ __CLASS__, 'ajax_delete'          ] );
        add_action( 'wp_ajax_csdt_devtools_pk_test_start',      [ __CLASS__, 'ajax_test_start'      ] );
        add_action( 'wp_ajax_csdt_devtools_pk_test_finish',     [ __CLASS__, 'ajax_test_finish'     ] );
    }

    // ── AJAX: Registration start ─────────────────────────────────────────────

    public static function ajax_register_start(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Unauthorized.', 401 );
        }

        $user      = wp_get_current_user();
        $challenge = random_bytes( 32 );
        set_transient( self::CHALLENGE_PREFIX . $user->ID, base64_encode( $challenge ), self::CHALLENGE_TTL );

        // Exclude already-registered credential IDs.
        $exclude = array_map(
            fn( $c ) => [ 'type' => 'public-key', 'id' => $c['id'] ],
            self::get_passkeys( $user->ID )
        );

        wp_send_json_success( [
            'challenge'              => self::b64u( $challenge ),
            'rp'                     => [
                'name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
                'id'   => self::rp_id(),
            ],
            'user'                   => [
                'id'          => self::b64u( (string) $user->ID ),
                'name'        => $user->user_login,
                'displayName' => $user->display_name,
            ],
            'pubKeyCredParams'       => [
                [ 'type' => 'public-key', 'alg' => -7   ],  // ES256 / P-256
                [ 'type' => 'public-key', 'alg' => -257 ],  // RS256
            ],
            'timeout'                => 60000,
            'attestation'            => 'none',
            'excludeCredentials'     => $exclude,
            'authenticatorSelection' => [
                'residentKey'      => 'preferred',
                'userVerification' => 'preferred',
            ],
        ] );
    }

    // ── AJAX: Registration finish ────────────────────────────────────────────

    public static function ajax_register_finish(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Unauthorized.', 401 );
        }

        $user_id    = get_current_user_id();
        $stored_b64 = get_transient( self::CHALLENGE_PREFIX . $user_id );
        if ( ! $stored_b64 ) {
            wp_send_json_error( __( 'Registration timed out — please try again.', 'cloudscale-devtools' ) );
        }
        delete_transient( self::CHALLENGE_PREFIX . $user_id );
        $expected_ch = base64_decode( $stored_b64 );

        $cred_name    = isset( $_POST['name'] )             ? sanitize_text_field( wp_unslash( $_POST['name'] ) )             : 'My Passkey';
        $cdj_b64u     = isset( $_POST['clientDataJSON'] )   ? sanitize_text_field( wp_unslash( $_POST['clientDataJSON'] ) )   : '';
        $atto_b64u    = isset( $_POST['attestationObject'] )? sanitize_text_field( wp_unslash( $_POST['attestationObject'] ) ): '';
        $cred_id_b64u = isset( $_POST['credentialId'] )     ? sanitize_text_field( wp_unslash( $_POST['credentialId'] ) )     : '';

        if ( ! $cdj_b64u || ! $atto_b64u || ! $cred_id_b64u ) {
            wp_send_json_error( __( 'Incomplete registration data.', 'cloudscale-devtools' ) );
        }

        // Validate clientDataJSON.
        $cdj  = json_decode( self::b64u_decode( $cdj_b64u ), true );
        $err  = self::check_client_data( $cdj, 'webauthn.create', $expected_ch );
        if ( $err ) { wp_send_json_error( $err ); }

        // Parse attestationObject.
        try {
            $att = CSDT_DevTools_CBOR::decode( self::b64u_decode( $atto_b64u ) );
        } catch ( \Exception $e ) {
            wp_send_json_error( __( 'Attestation parse error.', 'cloudscale-devtools' ) );
        }
        $auth_data = $att['authData'] ?? '';
        if ( strlen( $auth_data ) < 37 ) {
            wp_send_json_error( __( 'AuthData too short.', 'cloudscale-devtools' ) );
        }

        $parsed = self::parse_auth_data( $auth_data );
        if ( ! hash_equals( hash( 'sha256', self::rp_id(), true ), $parsed['rp_id_hash'] ) ) {
            wp_send_json_error( __( 'RP ID mismatch.', 'cloudscale-devtools' ) );
        }
        if ( ! ( $parsed['flags'] & 0x01 ) ) {
            wp_send_json_error( __( 'User presence not confirmed.', 'cloudscale-devtools' ) );
        }
        if ( ! ( $parsed['flags'] & 0x40 ) || empty( $parsed['cred_id'] ) || empty( $parsed['cose_key'] ) ) {
            wp_send_json_error( __( 'Missing credential data in attestation.', 'cloudscale-devtools' ) );
        }

        $pem = self::cose_to_pem( $parsed['cose_key'] );
        if ( ! $pem ) {
            wp_send_json_error( __( 'Unsupported key type. Use a P-256 or RSA passkey.', 'cloudscale-devtools' ) );
        }

        $cred_id     = self::b64u( $parsed['cred_id'] );
        $credentials = self::get_passkeys( $user_id );
        foreach ( $credentials as $c ) {
            if ( $c['id'] === $cred_id ) {
                wp_send_json_error( __( 'This passkey is already registered.', 'cloudscale-devtools' ) );
            }
        }

        $alg           = (int) ( $parsed['cose_key'][3] ?? -7 );
        $credentials[] = [
            'id'         => $cred_id,
            'pem'        => $pem,
            'alg'        => $alg,
            'sign_count' => $parsed['sign_count'],
            'name'       => $cred_name ?: 'Passkey',
            'created'    => time(),
        ];
        self::save_passkeys( $user_id, $credentials );

        wp_send_json_success( [
            'message' => __( 'Passkey registered successfully!', 'cloudscale-devtools' ),
            'id'      => $cred_id,
            'name'    => $cred_name ?: 'Passkey',
        ] );
    }

    // ── AJAX: Delete ─────────────────────────────────────────────────────────

    public static function ajax_delete(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Unauthorized.', 401 );
        }

        $cred_id = isset( $_POST['cred_id'] ) ? sanitize_text_field( wp_unslash( $_POST['cred_id'] ) ) : '';
        if ( ! $cred_id ) {
            wp_send_json_error( 'Missing credential ID.' );
        }

        $user_id     = get_current_user_id();
        $credentials = array_values( array_filter(
            self::get_passkeys( $user_id ),
            fn( $c ) => $c['id'] !== $cred_id
        ) );
        self::save_passkeys( $user_id, $credentials );

        wp_send_json_success( [ 'message' => 'Removed.' ] );
    }

    // ── AJAX: Test passkey (admin panel) ────────────────────────────────────────

    /** Returns a WebAuthn challenge so the admin can test a stored passkey without logging out. */
    public static function ajax_test_start(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! is_user_logged_in() ) { wp_send_json_error( 'Unauthorized.', 401 ); }

        $user_id     = get_current_user_id();
        $credentials = self::get_passkeys( $user_id );
        if ( empty( $credentials ) ) { wp_send_json_error( 'No passkeys registered.' ); }

        $challenge = random_bytes( 32 );
        set_transient( self::CHALLENGE_PREFIX . 'test_' . $user_id, base64_encode( $challenge ), self::CHALLENGE_TTL );

        wp_send_json_success( [
            'challenge'        => self::b64u( $challenge ),
            'rpId'             => self::rp_id(),
            'timeout'          => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => [],
        ] );
    }

    /** Verifies the assertion from the test flow. Returns success/error message. */
    public static function ajax_test_finish(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! is_user_logged_in() ) { wp_send_json_error( 'Unauthorized.', 401 ); }

        $user_id  = get_current_user_id();
        $suffix   = 'test_' . $user_id;
        $stored   = get_transient( self::CHALLENGE_PREFIX . $suffix );
        if ( ! $stored ) { wp_send_json_error( 'Challenge expired — try again.' ); }

        $result = self::do_verify_assertion( $user_id, $suffix );
        if ( $result === true ) {
            wp_send_json_success( 'Passkey verified successfully! ✅ Your passkey is working correctly.' );
        }

        wp_send_json_error( $result->get_error_message() );
    }

    // ── Login challenge page ─────────────────────────────────────────────────

    /**
     * Renders the passkey authentication page at login.
     * Auto-triggers navigator.credentials.get() and submits a form on success.
     *
     * @param string $token   Pending 2FA token (shared with email/TOTP flow).
     * @param int    $user_id User to authenticate.
     * @return void  Always exits.
     */
    public static function render_login_challenge( string $token, int $user_id, string $error = '' ): void {
        $credentials = self::get_passkeys( $user_id );
        if ( empty( $credentials ) ) {
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        $challenge = random_bytes( 32 );
        set_transient( self::CHALLENGE_PREFIX . 'login_' . $token, base64_encode( $challenge ), self::CHALLENGE_TTL );

        // Use discoverable credentials (empty allowCredentials) so any synced passkey
        // for this rpId is accepted — avoids credential ID mismatch across devices.
        $opts = [
            'challenge'        => self::b64u( $challenge ),
            'rpId'             => self::rp_id(),
            'timeout'          => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => [],
        ];
        $nonce = wp_create_nonce( 'csdt_devtools_pk_login_' . $token );

        $fallback_url = add_query_arg( [ 'action' => 'csdt_devtools_2fa', 'csdt_devtools_token' => rawurlencode( $token ) ], wp_login_url() );

        login_header( __( 'Passkey Authentication', 'cloudscale-devtools' ), '', null );
        ?>
          <?php // Single form — WP's .login form CSS styles it as the white card. ?>
          <form id="cs-pk-login-form" method="post"
                action="<?php echo esc_url( add_query_arg( [ 'action' => 'csdt_devtools_2fa', 'csdt_devtools_token' => rawurlencode( $token ) ], wp_login_url() ) ); ?>">
            <input type="hidden" name="action"           value="csdt_devtools_2fa">
            <input type="hidden" name="csdt_devtools_token"         value="<?php echo esc_attr( $token ); ?>">
            <input type="hidden" name="csdt_devtools_pk_nonce"      value="<?php echo esc_attr( $nonce ); ?>">
            <input type="hidden" name="csdt_devtools_pk_cred_id"    id="cs-pk-cred-id">
            <input type="hidden" name="csdt_devtools_pk_client_data" id="cs-pk-client-data">
            <input type="hidden" name="csdt_devtools_pk_auth_data"  id="cs-pk-auth-data">
            <input type="hidden" name="csdt_devtools_pk_signature"  id="cs-pk-signature">
            <?php // Fallback fields — swapped in by JS when email fallback is chosen. ?>
            <input type="hidden" id="cs-pk-fallback-field" name="csdt_devtools_pk_fallback" value="">

            <div style="text-align:center;padding:8px 0 4px;">
              <div id="cs-pk-icon" style="font-size:52px;margin-bottom:14px;transition:opacity .3s"><?php echo $error ? '❌' : '🔑'; ?></div>
              <p id="cs-pk-msg" style="font-size:14px;margin:0 0 18px;<?php echo $error ? 'color:#d63638;' : 'color:#555;'; ?>">
                <?php echo $error ? esc_html( $error ) : esc_html__( 'Waiting for your passkey…', 'cloudscale-devtools' ); ?>
              </p>
              <p id="cs-pk-retry-wrap" style="<?php echo $error ? '' : 'display:none;'; ?>margin:0 0 6px;">
                <button type="button" id="cs-pk-retry"
                        style="background:#2271b1;color:#fff;border:none;border-radius:6px;padding:9px 18px;cursor:pointer;font-size:13px;font-weight:600;width:100%;">
                  🔑 <?php esc_html_e( 'Try passkey again', 'cloudscale-devtools' ); ?>
                </button>
              </p>
              <p id="cs-pk-fallback-wrap" style="<?php echo $error ? '' : 'display:none;'; ?>margin:10px 0 0;">
                <button type="button" id="cs-pk-fallback"
                        style="background:#f0f4ff;border:1.5px solid #c7d2fe;color:#2271b1;cursor:pointer;font-size:13px;font-weight:600;padding:9px 18px;border-radius:6px;width:100%;display:flex;align-items:center;justify-content:center;gap:7px;">
                  📧 <?php esc_html_e( 'Send me an email code instead', 'cloudscale-devtools' ); ?>
                </button>
              </p>
            </div>
          </form>

          <p style="text-align:center;margin-top:12px;">
            <a href="<?php echo esc_url( wp_login_url() ); ?>" style="font-size:13px;color:#555;">
              &larr; <?php esc_html_e( 'Back to login', 'cloudscale-devtools' ); ?>
            </a>
          </p>
        <script>
        (function () {
            const opts = <?php echo wp_json_encode( $opts ); ?>;

            function b64u( b ) {
                const p = b.replace(/-/g,'+').replace(/_/g,'/');
                return Uint8Array.from( atob( p + '==='.slice(0,(4-p.length%4)%4) ), c => c.charCodeAt(0) );
            }
            function buf64( b ) {
                return btoa( String.fromCharCode(...new Uint8Array(b)) ).replace(/\+/g,'-').replace(/\//g,'_').replace(/=/g,'');
            }

            async function run() {
                const msg  = document.getElementById('cs-pk-msg');
                const icon     = document.getElementById('cs-pk-icon');
                const wrap     = document.getElementById('cs-pk-retry-wrap');
                const fallback = document.getElementById('cs-pk-fallback-wrap');
                try {
                    const cred = await navigator.credentials.get({ publicKey: {
                        challenge:        b64u( opts.challenge ),
                        rpId:             opts.rpId,
                        timeout:          opts.timeout,
                        userVerification: opts.userVerification,
                        allowCredentials: opts.allowCredentials.map( c => ({ type:'public-key', id: b64u(c.id) }) ),
                    }});
                    document.getElementById('cs-pk-cred-id').value      = buf64( cred.rawId );
                    document.getElementById('cs-pk-client-data').value   = buf64( cred.response.clientDataJSON );
                    document.getElementById('cs-pk-auth-data').value     = buf64( cred.response.authenticatorData );
                    document.getElementById('cs-pk-signature').value     = buf64( cred.response.signature );
                    if ( msg )  msg.textContent  = '<?php esc_html_e( 'Verified — signing you in…', 'cloudscale-devtools' ); ?>';
                    if ( icon ) icon.textContent = '✅';
                    document.getElementById('cs-pk-login-form').submit();
                } catch ( e ) {
                    if ( msg ) { msg.textContent = e.message || '<?php esc_html_e( 'Passkey authentication failed.', 'cloudscale-devtools' ); ?>'; msg.style.color = '#d63638'; }
                    if ( icon ) icon.textContent = '❌';
                    if ( wrap )     wrap.style.display = '';
                    if ( fallback ) fallback.style.display = '';
                }
            }

            document.getElementById('cs-pk-retry')?.addEventListener('click', run);
            document.getElementById('cs-pk-fallback')?.addEventListener('click', function () {
                const btn = this;
                if ( btn.dataset.submitted ) return; // prevent double-submit
                btn.dataset.submitted = '1';
                btn.disabled = true;
                btn.textContent = '📧 Sending code…';
                btn.style.opacity = '0.7';
                const f = document.getElementById('cs-pk-login-form');
                if ( f ) {
                    document.getElementById('cs-pk-fallback-field').value = '1';
                    f.action = <?php echo wp_json_encode( $fallback_url ); ?>;
                    f.submit();
                }
            });
            run();
        })();
        </script>
        <?php
        login_footer();
        exit;
    }

    // ── Login assertion verification ─────────────────────────────────────────

    /**
     * Verifies a passkey assertion submitted via login form POST.
     * Called from CloudScale_DevTools::login_2fa_handle().
     *
     * @param string $token   Pending 2FA token.
     * @param int    $user_id Expected user ID.
     * @return true|\WP_Error
     */
    public static function verify_login_assertion( string $token, int $user_id ) {
        if ( ! isset( $_POST['csdt_devtools_pk_nonce'] ) ||
             ! wp_verify_nonce(
                 sanitize_text_field( wp_unslash( $_POST['csdt_devtools_pk_nonce'] ) ),
                 'csdt_devtools_pk_login_' . $token
             ) ) {
            return new \WP_Error( 'nonce', __( 'Security check failed.', 'cloudscale-devtools' ) );
        }
        return self::do_verify_assertion( $user_id, 'login_' . $token );
    }

    // ── Admin section HTML ───────────────────────────────────────────────────

    /**
     * Renders the Passkeys section for the Login Security admin tab.
     *
     * @param int $user_id Logged-in admin user.
     */
    public static function render_section( int $user_id ): void {
        $passkeys = self::get_passkeys( $user_id );
        $count    = count( $passkeys );
        ?>
        <div class="cs-2fa-divider"></div>

        <div class="cs-2fa-row" id="cs-pk-row">
            <div class="cs-2fa-row-icon">🔑</div>
            <div class="cs-2fa-row-body">
                <div class="cs-2fa-row-title">
                    <?php esc_html_e( 'Passkeys', 'cloudscale-devtools' ); ?>
                </div>
                <div class="cs-2fa-row-desc">
                    <?php esc_html_e( 'Face ID, Touch ID, Windows Hello, or hardware security keys. Sign in with one tap — no code to type.', 'cloudscale-devtools' ); ?>
                </div>
            </div>
            <div class="cs-2fa-row-action">
                <?php if ( $count > 0 ) : ?>
                    <span class="cs-2fa-badge cs-2fa-badge-on" id="cs-pk-badge">
                        <?php echo esc_html( sprintf(
                            /* translators: %d = number of passkeys */
                            _n( '%d passkey', '%d passkeys', $count, 'cloudscale-devtools' ),
                            $count
                        ) ); ?>
                    </span>
                <?php else : ?>
                    <span class="cs-2fa-badge cs-2fa-badge-off" id="cs-pk-badge">
                        <?php esc_html_e( 'None registered', 'cloudscale-devtools' ); ?>
                    </span>
                <?php endif; ?>
                <button type="button" class="cs-btn-primary" id="cs-pk-add-btn" style="margin-left:10px">
                    <?php esc_html_e( '+ Add Passkey', 'cloudscale-devtools' ); ?>
                </button>
            </div>
        </div>

        <?php if ( $count > 0 ) : ?>
        <div id="cs-pk-list" class="cs-pk-list">
            <?php foreach ( $passkeys as $pk ) :
                $date = ! empty( $pk['created'] ) ? date_i18n( get_option( 'date_format' ), $pk['created'] ) : '—'; ?>
            <div class="cs-pk-item" data-id="<?php echo esc_attr( $pk['id'] ); ?>">
                <span class="cs-pk-icon">🔑</span>
                <span class="cs-pk-name"><?php echo esc_html( $pk['name'] ); ?></span>
                <span class="cs-pk-date"><?php echo esc_html( $date ); ?></span>
                <span class="cs-pk-test-result" style="display:none;font-size:12px;font-weight:600"></span>
                <button type="button" class="cs-btn-secondary cs-pk-test" data-id="<?php echo esc_attr( $pk['id'] ); ?>">
                    <?php esc_html_e( 'Test', 'cloudscale-devtools' ); ?>
                </button>
                <button type="button" class="cs-btn-pink cs-pk-delete" data-id="<?php echo esc_attr( $pk['id'] ); ?>">
                    <?php esc_html_e( 'Remove', 'cloudscale-devtools' ); ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else : ?>
        <div id="cs-pk-list" class="cs-pk-list" style="display:none"></div>
        <?php endif; ?>

        <!-- Add Passkey wizard -->
        <div id="cs-pk-wizard" class="cs-pk-wizard" style="display:none">
            <div class="cs-pk-wizard-inner">
                <h3 class="cs-pk-wizard-title">🔑 <?php esc_html_e( 'Register a Passkey', 'cloudscale-devtools' ); ?></h3>
                <p class="cs-pk-wizard-desc">
                    <?php esc_html_e( 'Give this passkey a label so you can identify it later (e.g. "iPhone 16", "MacBook Pro").', 'cloudscale-devtools' ); ?>
                </p>
                <div class="cs-pk-name-row">
                    <input type="text" id="cs-pk-name-input" class="cs-input cs-pk-name-input"
                           placeholder="<?php esc_attr_e( 'e.g. iPhone 16', 'cloudscale-devtools' ); ?>"
                           maxlength="50" autocomplete="off">
                    <button type="button" class="cs-btn-primary" id="cs-pk-register-btn">
                        <?php esc_html_e( 'Register', 'cloudscale-devtools' ); ?>
                    </button>
                    <button type="button" class="cs-btn-secondary" id="cs-pk-cancel-btn">
                        <?php esc_html_e( 'Cancel', 'cloudscale-devtools' ); ?>
                    </button>
                </div>
                <div id="cs-pk-wizard-status" class="cs-pk-wizard-status" style="display:none"></div>
            </div>
        </div>
        <?php
    }

    // ── Core assertion verification ──────────────────────────────────────────

    /**
     * @param int    $user_id
     * @param string $challenge_suffix  Transient suffix (user_id or 'login_' + token).
     * @return true|\WP_Error
     */
    private static function do_verify_assertion( int $user_id, string $challenge_suffix ) {
        $stored = get_transient( self::CHALLENGE_PREFIX . $challenge_suffix );
        if ( ! $stored ) {
            return new \WP_Error( 'expired', __( 'Challenge expired — please try again.', 'cloudscale-devtools' ) );
        }
        delete_transient( self::CHALLENGE_PREFIX . $challenge_suffix );
        $expected_ch = base64_decode( $stored );

        $cred_id_b64u = isset( $_POST['csdt_devtools_pk_cred_id'] )      ? sanitize_text_field( wp_unslash( $_POST['csdt_devtools_pk_cred_id'] ) )      : '';
        $cdj_b64u     = isset( $_POST['csdt_devtools_pk_client_data'] )  ? sanitize_text_field( wp_unslash( $_POST['csdt_devtools_pk_client_data'] ) )  : '';
        $ad_b64u      = isset( $_POST['csdt_devtools_pk_auth_data'] )    ? sanitize_text_field( wp_unslash( $_POST['csdt_devtools_pk_auth_data'] ) )    : '';
        $sig_b64u     = isset( $_POST['csdt_devtools_pk_signature'] )    ? sanitize_text_field( wp_unslash( $_POST['csdt_devtools_pk_signature'] ) )    : '';

        if ( ! $cred_id_b64u || ! $cdj_b64u || ! $ad_b64u || ! $sig_b64u ) {
            return new \WP_Error( 'missing', __( 'Incomplete assertion.', 'cloudscale-devtools' ) );
        }

        $cdj_raw = self::b64u_decode( $cdj_b64u );
        $cdj     = json_decode( $cdj_raw, true );
        $err     = self::check_client_data( $cdj, 'webauthn.get', $expected_ch );
        if ( $err ) { return new \WP_Error( 'clientdata', $err ); }

        $auth_data = self::b64u_decode( $ad_b64u );
        if ( strlen( $auth_data ) < 37 ) {
            return new \WP_Error( 'authdata', __( 'AuthData too short.', 'cloudscale-devtools' ) );
        }
        if ( ! hash_equals( hash( 'sha256', self::rp_id(), true ), substr( $auth_data, 0, 32 ) ) ) {
            return new \WP_Error( 'rpid', __( 'RP ID mismatch.', 'cloudscale-devtools' ) );
        }
        if ( ! ( ord( $auth_data[32] ) & 0x01 ) ) {
            return new \WP_Error( 'up', __( 'User presence not confirmed.', 'cloudscale-devtools' ) );
        }

        // Look up stored credential.
        $cred_id     = self::b64u( self::b64u_decode( $cred_id_b64u ) );
        $credentials = self::get_passkeys( $user_id );
        $cred        = null;
        foreach ( $credentials as &$c ) {
            if ( $c['id'] === $cred_id ) { $cred = &$c; break; }
        }
        unset( $c );
        if ( $cred === null ) {
            return new \WP_Error( 'notfound', __( 'Passkey not found.', 'cloudscale-devtools' ) );
        }

        // Verify signature: sig over (authData || SHA256(clientDataJSON)).
        $verify_data = $auth_data . hash( 'sha256', $cdj_raw, true );
        $sig_bytes   = self::b64u_decode( $sig_b64u );
        if ( ! self::verify_sig( $cred['pem'], (int) ( $cred['alg'] ?? -7 ), $verify_data, $sig_bytes ) ) {
            return new \WP_Error( 'sig', __( 'Signature verification failed.', 'cloudscale-devtools' ) );
        }

        // Update sign counter (non-zero counters must increment to detect cloning).
        $sign_count = unpack( 'N', substr( $auth_data, 33, 4 ) )[1];
        if ( $sign_count !== 0 && $sign_count <= (int) $cred['sign_count'] ) {
            return new \WP_Error( 'counter', __( 'Sign counter anomaly — possible credential cloning.', 'cloudscale-devtools' ) );
        }
        $cred['sign_count'] = $sign_count;
        self::save_passkeys( $user_id, $credentials );

        return true;
    }

    // ── WebAuthn helpers ─────────────────────────────────────────────────────

    /**
     * Validates the clientDataJSON fields common to create and get.
     *
     * @param array|null $cdj       Decoded clientDataJSON.
     * @param string     $type      Expected type string.
     * @param string     $expected  Expected raw challenge bytes.
     * @return string|null  Error message, or null on success.
     */
    private static function check_client_data( ?array $cdj, string $type, string $expected ): ?string {
        if ( ! $cdj ) return __( 'Invalid clientDataJSON.', 'cloudscale-devtools' );
        if ( ( $cdj['type'] ?? '' ) !== $type ) return __( 'Unexpected operation type.', 'cloudscale-devtools' );
        if ( ! hash_equals( $expected, self::b64u_decode( $cdj['challenge'] ?? '' ) ) ) {
            return __( 'Challenge mismatch.', 'cloudscale-devtools' );
        }
        if ( ! self::check_origin( $cdj['origin'] ?? '' ) ) {
            return __( 'Origin mismatch.', 'cloudscale-devtools' );
        }
        return null;
    }

    /**
     * Parses WebAuthn authenticator data bytes.
     *
     * Layout:
     *   [0..31]  rpIdHash (32 bytes)
     *   [32]     flags    (1 byte)
     *   [33..36] signCount (uint32 BE)
     *   [37..]   attestedCredentialData (when AT flag = bit 6 is set)
     *     [37..52]  aaguid (16 bytes)
     *     [53..54]  credIdLen (uint16 BE)
     *     [55..55+L] credentialId
     *     [55+L..]   CBOR-encoded public key
     */
    private static function parse_auth_data( string $b ): array {
        $r = [
            'rp_id_hash' => substr( $b, 0, 32 ),
            'flags'      => ord( $b[32] ),
            'sign_count' => unpack( 'N', substr( $b, 33, 4 ) )[1],
        ];
        if ( ( $r['flags'] & 0x40 ) && strlen( $b ) > 55 ) {
            $cid_len        = unpack( 'n', substr( $b, 53, 2 ) )[1];
            $r['cred_id']   = substr( $b, 55, $cid_len );
            $cose_raw       = substr( $b, 55 + $cid_len );
            try { $r['cose_key'] = CSDT_DevTools_CBOR::decode( $cose_raw ); } catch ( \Exception $e ) { /* leave unset */ }
        }
        return $r;
    }

    /**
     * Converts a CBOR-decoded COSE public key map to a base64-encoded DER string.
     * Stored without PEM headers/newlines to avoid WordPress meta storage corruption.
     * Use pem_from_stored() to convert back to a PEM before passing to OpenSSL.
     */
    private static function cose_to_pem( array $cose ): ?string {
        $kty = $cose[1] ?? null;

        if ( $kty === 2 ) {
            $x = $cose[-2] ?? null;
            $y = $cose[-3] ?? null;
            if ( ! $x || ! $y || strlen( $x ) !== 32 || strlen( $y ) !== 32 ) return null;
            return base64_encode( self::ec_p256_spki( "\x04" . $x . $y ) );
        }

        if ( $kty === 3 ) {
            $n = $cose[-1] ?? null;
            $e = $cose[-2] ?? null;
            if ( ! $n || ! $e ) return null;
            return base64_encode( self::rsa_spki( $n, $e ) );
        }

        return null;
    }

    /**
     * Converts a stored key value to a valid PEM string for OpenSSL.
     *
     * Handles three formats:
     *  - New: plain base64-encoded DER (no PEM headers) — safe, wrap and return.
     *  - Old/good: PEM with real newlines — return as-is.
     *  - Old/corrupted: PEM where newlines were replaced by the letter 'n' due to
     *    WordPress stripslashes processing of JSON-encoded meta values. Repairs by
     *    removing the spurious 'n' characters at known 64-char base64 boundaries.
     */
    private static function pem_from_stored( string $stored ): string {
        // New format: plain base64 DER (no PEM markers).
        if ( strncmp( $stored, '-----', 5 ) !== 0 ) {
            $der = base64_decode( $stored, true );
            if ( ! $der ) return $stored;
            return "-----BEGIN PUBLIC KEY-----\n"
                . chunk_split( base64_encode( $der ), 64, "\n" )
                . "-----END PUBLIC KEY-----\n";
        }

        // Old format with real newlines — fine as-is.
        if ( strpos( $stored, "\n" ) !== false ) {
            return $stored;
        }

        // Old format with corrupted newlines ('n' instead of 0x0A).
        // Find body between first '-----n' and last 'n-----END'.
        $body_start = strpos( $stored, '-----n' );
        if ( $body_start === false ) return $stored;
        $body_start += 6;

        $footer_pos = strrpos( $stored, 'n-----END' );
        if ( $footer_pos === false ) return $stored;

        $body = substr( $stored, $body_start, $footer_pos - $body_start );

        // Remove the corrupted 'n' characters that appear at 64-char line breaks.
        $b64    = '';
        $offset = 0;
        $blen   = strlen( $body );
        while ( $offset < $blen ) {
            $chunk   = substr( $body, $offset, 64 );
            $b64    .= $chunk;
            $offset += 64;
            // Skip exactly one 'n' if it sits at the chunk boundary (corrupted newline).
            if ( $offset < $blen && $body[ $offset ] === 'n' ) {
                $offset++;
            }
        }

        $der = base64_decode( $b64, true );
        if ( ! $der ) return $stored;
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split( base64_encode( $der ), 64, "\n" )
            . "-----END PUBLIC KEY-----\n";
    }

    /**
     * SubjectPublicKeyInfo DER for EC P-256.
     * OIDs: id-ecPublicKey (1.2.840.10045.2.1) + prime256v1 (1.2.840.10045.3.1.7).
     */
    private static function ec_p256_spki( string $point ): string {
        // AlgorithmIdentifier SEQUENCE containing both OIDs.
        $alg = "\x30\x13"
             . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"         // ecPublicKey OID
             . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";    // P-256 OID
        $bs  = "\x03" . self::dl( strlen( $point ) + 1 ) . "\x00" . $point; // BIT STRING
        return "\x30" . self::dl( strlen( $alg ) + strlen( $bs ) ) . $alg . $bs;
    }

    /** SubjectPublicKeyInfo DER for RSA (rsaEncryption OID + PKCS#1 key). */
    private static function rsa_spki( string $n, string $e ): string {
        $ni  = self::der_int( $n );
        $ei  = self::der_int( $e );
        $seq = "\x30" . self::dl( strlen( $ni ) + strlen( $ei ) ) . $ni . $ei;
        $bs  = "\x03" . self::dl( strlen( $seq ) + 1 ) . "\x00" . $seq;
        $alg = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"; // rsaEncryption OID + NULL
        return "\x30" . self::dl( strlen( $alg ) + strlen( $bs ) ) . $alg . $bs;
    }

    /** Encodes bytes as a DER INTEGER (prepends 0x00 if high bit set). */
    private static function der_int( string $v ): string {
        $v = ltrim( $v, "\x00" ) ?: "\x00";
        if ( ord( $v[0] ) > 0x7f ) { $v = "\x00" . $v; }
        return "\x02" . self::dl( strlen( $v ) ) . $v;
    }

    /** DER length encoding. */
    private static function dl( int $len ): string {
        if ( $len < 0x80 )    return chr( $len );
        if ( $len < 0x100 )   return "\x81" . chr( $len );
        if ( $len < 0x10000 ) return "\x82" . chr( $len >> 8 ) . chr( $len & 0xff );
        throw new \RuntimeException( 'DER: length overflow' );
    }

    /** Verifies an OpenSSL signature. alg: -7 = ES256, -257 = RS256. */
    private static function verify_sig( string $stored_key, int $alg, string $data, string $sig ): bool {
        if ( $alg !== -7 && $alg !== -257 ) return false;
        $pem = self::pem_from_stored( $stored_key );
        $key = openssl_pkey_get_public( $pem );
        if ( ! $key ) return false;
        return openssl_verify( $data, $sig, $key, OPENSSL_ALGO_SHA256 ) === 1;
    }

    // ── Utility ──────────────────────────────────────────────────────────────

    public static function rp_id(): string {
        return (string) parse_url( home_url(), PHP_URL_HOST );
    }

    private static function origin(): string {
        $p      = parse_url( home_url() );
        $scheme = $p['scheme'] ?? 'https';
        $host   = $p['host']   ?? '';
        $port   = '';
        if ( isset( $p['port'] ) ) {
            if ( ! ( ( $scheme === 'https' && $p['port'] === 443 ) || ( $scheme === 'http' && $p['port'] === 80 ) ) ) {
                $port = ':' . $p['port'];
            }
        }
        return $scheme . '://' . $host . $port;
    }

    private static function check_origin( string $origin ): bool {
        return $origin === self::origin();
    }

    public static function b64u( string $data ): string {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    public static function b64u_decode( string $data ): string {
        return base64_decode( strtr( $data, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $data ) % 4 ) % 4 ) );
    }

    public static function get_passkeys( int $user_id ): array {
        $raw = get_user_meta( $user_id, self::META_KEY, true );
        if ( ! $raw ) return [];
        $arr = json_decode( $raw, true );
        return is_array( $arr ) ? $arr : [];
    }

    private static function save_passkeys( int $user_id, array $creds ): void {
        update_user_meta( $user_id, self::META_KEY, wp_json_encode( $creds ) );
    }
}
