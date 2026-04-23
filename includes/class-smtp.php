<?php
/**
 * SMTP mail configuration, email log, and prefix-migration helpers.
 *
 * @package CloudScale_DevTools
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CSDT_SMTP {

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
                <?php CloudScale_DevTools::render_explain_btn( 'smtp', 'SMTP Configuration', [
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
                    <button type="button" class="cs-btn-primary" id="cs-smtp-save">💾 <?php esc_html_e( 'Save Settings', 'cloudscale-devtools' ); ?></button>
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
                <?php CloudScale_DevTools::render_explain_btn( 'smtp-from', 'From Address', [
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
                <?php CloudScale_DevTools::render_explain_btn( 'email-log', 'Email Activity Log', [
                    [ 'name' => 'How it works',   'rec' => 'Overview',     'html' => 'Every email WordPress sends via <code>wp_mail()</code> is intercepted and logged — recipient, subject, status (sent / failed), and timestamp. The log holds the last 100 entries and is stored as a WordPress option.' ],
                    [ 'name' => 'Failed emails',  'rec' => 'Important',    'html' => 'A <strong>Failed</strong> status means <code>wp_mail()</code> returned false. The most common cause is an unconfigured SMTP server — WordPress falls back to PHP <code>mail()</code> which many hosts block. Configure SMTP in the SMTP Configuration panel above.' ],
                    [ 'name' => 'Resend',         'rec' => 'Optional',     'html' => 'You can resend any logged email using the Resend button. This re-triggers <code>wp_mail()</code> with the same recipient and subject. Useful for testing SMTP changes without waiting for a real event.' ],
                    [ 'name' => 'Privacy',        'rec' => 'Info',         'html' => 'Email body content is stored (up to 100 KB per email) so you can view it later. The log is visible to administrators only and is cleared when you click Clear Log or uninstall the plugin.' ],
                ] ); ?>
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

        <!-- Email View Modal -->
        <div id="csdt-email-modal" style="display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:10px;width:min(860px,94vw);max-height:88vh;display:flex;flex-direction:column;box-shadow:0 8px 40px rgba(0,0,0,.35);">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #e2e8f0;">
                    <strong style="font-size:15px;color:#1e293b;" id="csdt-email-modal-subject">Email</strong>
                    <button id="csdt-email-modal-close" type="button" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b;line-height:1;">&times;</button>
                </div>
                <div id="csdt-email-modal-meta" style="padding:10px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:12px;color:#475569;display:flex;gap:20px;flex-wrap:wrap;"></div>
                <div id="csdt-email-modal-body" style="flex:1;overflow:auto;padding:0;"></div>
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
                    <th style="padding:7px 10px;border-bottom:1px solid #e0e0e0"></th>
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
                    <td style="padding:7px 10px;border-bottom:1px solid #f0f0f0">
                        <?php if ( ! empty( $entry['body'] ) ) : ?>
                        <button type="button" class="csdt-email-view-btn" data-idx="<?php echo esc_attr( $i ); ?>"
                            style="background:none;border:1px solid #2563eb;color:#2563eb;border-radius:4px;padding:2px 10px;font-size:11px;cursor:pointer;white-space:nowrap;">
                            <?php esc_html_e( 'View', 'cloudscale-devtools' ); ?>
                        </button>
                        <?php endif; ?>
                    </td>
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
        check_ajax_referer( CloudScale_DevTools::SMTP_NONCE, 'nonce' );
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
        check_ajax_referer( CloudScale_DevTools::SMTP_NONCE, 'nonce' );
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
        $hdrs = $args['headers'] ?? [];
        if ( is_string( $hdrs ) ) { $hdrs = [ $hdrs ]; }
        $is_html = false;
        foreach ( $hdrs as $h ) {
            if ( stripos( $h, 'content-type' ) !== false && stripos( $h, 'text/html' ) !== false ) {
                $is_html = true;
                break;
            }
        }
        $body = (string) ( $args['message'] ?? '' );
        if ( ! $is_html && ( strpos( $body, '<html' ) !== false || strpos( $body, '<body' ) !== false ) ) {
            $is_html = true;
        }

        self::$smtp_log_pending = [
            'ts'      => time(),
            'to'      => (string) $to,
            'subject' => (string) ( $args['subject'] ?? '' ),
            'body'    => mb_substr( $body, 0, 102400 ),
            'is_html' => $is_html,
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
        check_ajax_referer( CloudScale_DevTools::SMTP_NONCE, 'nonce' );
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
        check_ajax_referer( CloudScale_DevTools::SMTP_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $log = get_option( self::EMAIL_LOG_OPTION, [] );
        if ( ! is_array( $log ) ) { $log = []; }
        // Strip body from table-refresh payload to keep it lightweight
        $slim = array_map( static function ( $e ) {
            unset( $e['body'] );
            return $e;
        }, $log );
        wp_send_json_success( $slim );
    }

    public static function ajax_smtp_log_view(): void {
        check_ajax_referer( CloudScale_DevTools::SMTP_NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $idx = isset( $_POST['idx'] ) ? (int) $_POST['idx'] : -1;
        $log = get_option( self::EMAIL_LOG_OPTION, [] );
        if ( ! is_array( $log ) || ! isset( $log[ $idx ] ) ) {
            wp_send_json_error( 'Not found' );
        }
        wp_send_json_success( $log[ $idx ] );
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

}
