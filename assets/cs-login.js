/* ===========================================================
   CloudScale Code Block — Login Security admin JS  v1.9.10
   Handles: Hide Login save, 2FA site settings save,
            session duration, brute-force protection,
            TOTP setup wizard, email 2FA enable/disable.
   =========================================================== */
( function () {
    'use strict';

    const { ajaxUrl, nonce } = window.csdtDevtoolsLogin || {};

    // ── Helpers ───────────────────────────────────────────────────────────

    function post( action, data ) {
        const body = new URLSearchParams( { action, nonce, ...data } );
        return fetch( ajaxUrl, { method: 'POST', body } )
            .then( r => r.json() )
            .then( res => {
                // WP returns -1 for nonce failures, 0 for unknown actions.
                // Neither is a structured success/error object.
                if ( res === -1 || res === 0 ) {
                    return { success: false, data: 'Session expired — please reload the page and try again.' };
                }
                return res;
            } );
    }

    function flash( el, ok ) {
        if ( ! el ) return;
        el.textContent = ok ? '✅ Saved' : '❌ Error';
        el.style.color = ok ? '#16a34a' : '#dc2626';
        el.classList.add( 'visible' );
        setTimeout( () => {
            el.classList.remove( 'visible' );
            el.style.color = '';
        }, 10000 );
    }

    // ── Hide Login + 2FA site settings save ──────────────────────────────

    const hideSaveBtn  = document.getElementById( 'cs-hide-save' );
    const hideSaved    = document.getElementById( 'cs-hide-saved' );
    const twoFaSaveBtn = document.getElementById( 'cs-2fa-save' );
    const twoFaSaved   = document.getElementById( 'cs-2fa-saved' );

    function collectLoginPayload() {
        return {
            hide_enabled:     document.getElementById( 'cs-hide-enabled' )?.checked ? '1' : '0',
            login_slug:       document.getElementById( 'cs-login-slug' )?.value.trim() || '',
            method:           document.querySelector( 'input[name="csdt_devtools_2fa_method"]:checked' )?.value || 'off',
            force_admins:     document.getElementById( 'cs-2fa-force' )?.checked ? '1' : '0',
            grace_logins:     document.getElementById( 'cs-2fa-grace-logins' )?.value || '0',
            session_duration: document.getElementById( 'cs-session-duration' )?.value || 'default',
            bf_enabled:       document.getElementById( 'cs-bf-enabled' )?.checked ? '1' : '0',
            bf_attempts:      document.getElementById( 'cs-bf-attempts' )?.value || '5',
            bf_lockout:       document.getElementById( 'cs-bf-lockout' )?.value || '5',
            bf_enum_protect:  document.getElementById( 'cs-bf-enum-protect' )?.checked ? '1' : '0',
        };
    }

    if ( hideSaveBtn ) {
        hideSaveBtn.addEventListener( 'click', () => {
            hideSaveBtn.disabled = true;
            post( 'csdt_devtools_login_save', collectLoginPayload() ).then( res => {
                hideSaveBtn.disabled = false;
                if ( res.success ) {
                    flash( hideSaved, true );
                    // Update the displayed current login URL
                    const urlEl = document.getElementById( 'cs-current-login-url' );
                    if ( urlEl && res.data?.login_url ) {
                        urlEl.href        = res.data.login_url;
                        urlEl.textContent = res.data.login_url;
                    }
                    // (slug input already has the current value — no update needed)
                } else {
                    flash( hideSaved, false );
                }
            } ).catch( () => {
                hideSaveBtn.disabled = false;
                flash( hideSaved, false );
            } );
        } );
    }

    const sessionSaveBtn = document.getElementById( 'cs-session-save' );
    const sessionSaved   = document.getElementById( 'cs-session-saved' );

    if ( sessionSaveBtn ) {
        sessionSaveBtn.addEventListener( 'click', () => {
            sessionSaveBtn.disabled = true;
            post( 'csdt_devtools_login_save', collectLoginPayload() ).then( res => {
                sessionSaveBtn.disabled = false;
                flash( sessionSaved, res.success );
            } ).catch( () => {
                sessionSaveBtn.disabled = false;
                flash( sessionSaved, false );
            } );
        } );
    }

    const bfSaveBtn  = document.getElementById( 'cs-bf-save' );
    const bfSaved    = document.getElementById( 'cs-bf-saved' );
    const bfEnabled  = document.getElementById( 'cs-bf-enabled' );
    const bfOptions  = document.getElementById( 'cs-bf-options' );

    // Toggle numeric fields based on the enable checkbox.
    if ( bfEnabled && bfOptions ) {
        const syncBfOptions = () => { bfOptions.style.opacity = bfEnabled.checked ? '' : '0.4'; };
        syncBfOptions();
        bfEnabled.addEventListener( 'change', syncBfOptions );
    }

    if ( bfSaveBtn ) {
        bfSaveBtn.addEventListener( 'click', () => {
            bfSaveBtn.disabled = true;
            post( 'csdt_devtools_login_save', collectLoginPayload() ).then( res => {
                bfSaveBtn.disabled = false;
                flash( bfSaved, res.success );
            } ).catch( () => {
                bfSaveBtn.disabled = false;
                flash( bfSaved, false );
            } );
        } );
    }

    if ( twoFaSaveBtn ) {
        twoFaSaveBtn.addEventListener( 'click', () => {
            twoFaSaveBtn.disabled = true;
            post( 'csdt_devtools_login_save', collectLoginPayload() ).then( res => {
                twoFaSaveBtn.disabled = false;
                flash( twoFaSaved, res.success );
            } ).catch( () => {
                twoFaSaveBtn.disabled = false;
                flash( twoFaSaved, false );
            } );
        } );
    }

    // ── Radio label active highlight ──────────────────────────────────────

    document.querySelectorAll( '.cs-2fa-method-group input[type="radio"]' ).forEach( radio => {
        radio.addEventListener( 'change', () => {
            document.querySelectorAll( '.cs-2fa-method-group .cs-radio-label' ).forEach( l => l.classList.remove( 'active' ) );
            radio.closest( '.cs-radio-label' )?.classList.add( 'active' );
        } );
    } );

    // ── Email 2FA enable / resend ─────────────────────────────────────────

    const emailEnableBtn  = document.getElementById( 'cs-email-enable-btn' );
    const emailBadge      = document.getElementById( 'cs-email-badge' );
    const emailPendingMsg = document.getElementById( 'cs-email-pending-msg' );

    if ( emailEnableBtn ) {
        emailEnableBtn.addEventListener( 'click', () => {
            emailEnableBtn.disabled    = true;
            emailEnableBtn.textContent = 'Checking ports…';

            // Clear any previous port warning
            const existingWarn = document.getElementById( 'cs-email-port-warn' );
            if ( existingWarn ) existingWarn.remove();

            post( 'csdt_devtools_email_2fa_enable', {} ).then( res => {
                const d = res.data || {};

                // Show port/config warning if present (success OR failure)
                if ( d.port_warning ) {
                    const warn = document.createElement( 'div' );
                    warn.id        = 'cs-email-port-warn';
                    warn.className = 'cs-email-port-warn';
                    warn.textContent = '⚠️ ' + d.port_warning;
                    emailPendingMsg?.parentNode?.insertBefore( warn, emailPendingMsg.nextSibling );
                }

                if ( res.success ) {
                    emailEnableBtn.textContent = 'Resend';
                    emailEnableBtn.disabled    = false;
                    if ( emailBadge ) {
                        emailBadge.textContent = 'Awaiting verification';
                        emailBadge.className   = 'cs-2fa-badge cs-2fa-badge-pending';
                    }
                    if ( emailPendingMsg ) {
                        emailPendingMsg.style.display = '';
                        const span = document.createElement( 'span' );
                        span.className   = 'cs-pending-notice';
                        span.textContent = '📬 ' + ( d.message || 'Verification email sent — click the link in the email to activate.' );
                        emailPendingMsg.textContent = '';
                        emailPendingMsg.appendChild( span );
                    }
                } else {
                    emailEnableBtn.disabled    = false;
                    emailEnableBtn.textContent = 'Enable';
                    if ( emailPendingMsg ) {
                        emailPendingMsg.style.display = '';
                        const span = document.createElement( 'span' );
                        span.style.cssText = 'color:#e53e3e;font-size:12px';
                        if ( d.smtp_not_configured ) {
                            const mailUrl = ( window.csdtDevtoolsLogin || {} ).mailTabUrl || '';
                            span.innerHTML = '✗ Email isn\'t configured on this site. '
                                + ( mailUrl ? '<a href="' + mailUrl + '" style="color:#c53030;text-decoration:underline">Set up SMTP</a> to enable email delivery.' : 'Set up SMTP to enable email delivery.' );
                        } else {
                            span.textContent = '✗ ' + ( d.message || 'Failed to send.' );
                        }
                        emailPendingMsg.textContent = '';
                        emailPendingMsg.appendChild( span );
                    }
                }
            } ).catch( () => {
                emailEnableBtn.disabled    = false;
                emailEnableBtn.textContent = 'Enable';
                if ( emailPendingMsg ) {
                    emailPendingMsg.style.display = '';
                    const span = document.createElement( 'span' );
                    span.style.cssText = 'color:#e53e3e;font-size:12px';
                    span.textContent   = '✗ Network error. Try again.';
                    emailPendingMsg.textContent = '';
                    emailPendingMsg.appendChild( span );
                }
            } );
        } );
    }

    // ── 2FA disable (email or TOTP) ───────────────────────────────────────

    document.querySelectorAll( '.cs-2fa-disable' ).forEach( btn => {
        btn.addEventListener( 'click', () => {
            const method = btn.dataset.method;
            if ( ! confirm( 'Disable ' + ( method === 'totp' ? 'Authenticator App' : 'Email' ) + ' 2FA? You can re-enable it at any time.' ) ) return;
            btn.disabled = true;
            post( 'csdt_devtools_2fa_disable', { method } ).then( res => {
                if ( res.success ) {
                    location.reload();
                } else {
                    btn.disabled = false;
                    alert( res.data || 'Failed.' );
                }
            } );
        } );
    } );

    // ── TOTP Setup Wizard ─────────────────────────────────────────────────

    const totpSetupBtn  = document.getElementById( 'cs-totp-setup-btn' );
    const totpWizard    = document.getElementById( 'cs-totp-wizard' );
    const totpCancelBtn = document.getElementById( 'cs-totp-cancel-btn' );
    const totpCopyBtn   = document.getElementById( 'cs-totp-copy-btn' );
    const totpQrLoading  = document.getElementById( 'cs-totp-qr-loading' );
    const totpQrCanvas   = document.getElementById( 'cs-totp-qr-canvas' );
    const totpManual    = document.getElementById( 'cs-totp-manual' );
    const totpSecret    = document.getElementById( 'cs-totp-secret-display' );
    const totpVerifyBtn = document.getElementById( 'cs-totp-verify-btn' );
    const totpCodeInput = document.getElementById( 'cs-totp-verify-code' );
    const totpMsg       = document.getElementById( 'cs-totp-verify-msg' );

    if ( totpSetupBtn && totpWizard ) {
        totpSetupBtn.addEventListener( 'click', () => {
            totpWizard.style.display = 'block';
            totpSetupBtn.style.display = 'none';

            // Reset state
            if ( totpQrLoading ) totpQrLoading.style.display = 'flex';
            if ( totpQrCanvas )  { totpQrCanvas.style.display = 'none'; totpQrCanvas.innerHTML = ''; }
            if ( totpManual )    totpManual.style.display = 'none';
            if ( totpMsg )       { totpMsg.style.display = 'none'; totpMsg.textContent = ''; }
            if ( totpCodeInput ) totpCodeInput.value = '';

            // Fetch secret from server
            post( 'csdt_devtools_totp_setup_start', {} ).then( res => {
                if ( totpQrLoading ) totpQrLoading.style.display = 'none';
                if ( ! res.success ) {
                    alert( res.data || 'Failed to start setup.' );
                    closeTotpWizard();
                    return;
                }
                if ( totpQrCanvas && res.data.otpauth && window.QRCode ) {
                    totpQrCanvas.innerHTML = '';
                    new window.QRCode( totpQrCanvas, {
                        text:          res.data.otpauth,
                        width:         220,
                        height:        220,
                        colorDark:     '#000000',
                        colorLight:    '#ffffff',
                        correctLevel:  window.QRCode.CorrectLevel.M,
                    } );
                    totpQrCanvas.style.display = 'block';
                }
                if ( totpSecret ) totpSecret.textContent = res.data.secret;
                if ( totpManual ) totpManual.style.display = 'block';
                if ( totpCodeInput ) totpCodeInput.focus();
            } ).catch( () => {
                if ( totpQrLoading ) totpQrLoading.style.display = 'none';
                alert( 'Network error starting TOTP setup.' );
                closeTotpWizard();
            } );
        } );
    }

    function closeTotpWizard() {
        if ( totpWizard )    totpWizard.style.display = 'none';
        if ( totpSetupBtn )  totpSetupBtn.style.display = '';
    }

    if ( totpCancelBtn ) {
        totpCancelBtn.addEventListener( 'click', closeTotpWizard );
    }

    if ( totpCopyBtn ) {
        totpCopyBtn.addEventListener( 'click', () => {
            const key = totpSecret ? totpSecret.textContent.trim() : '';
            if ( ! key ) return;
            navigator.clipboard.writeText( key ).then( () => {
                const orig = totpCopyBtn.textContent;
                totpCopyBtn.textContent = '✓ Copied';
                totpCopyBtn.style.background = '#1db954';
                setTimeout( () => {
                    totpCopyBtn.textContent = orig;
                    totpCopyBtn.style.background = '';
                }, 2000 );
            } ).catch( () => {
                // Fallback for browsers without clipboard API
                const range = document.createRange();
                range.selectNodeContents( totpSecret );
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange( range );
            } );
        } );
    }

    // Only allow digits in the TOTP code input
    if ( totpCodeInput ) {
        totpCodeInput.addEventListener( 'input', () => {
            totpCodeInput.value = totpCodeInput.value.replace( /\D/g, '' ).slice( 0, 6 );
        } );
        totpCodeInput.addEventListener( 'keydown', e => {
            if ( e.key === 'Enter' ) totpVerifyBtn?.click();
        } );
    }

    if ( totpVerifyBtn ) {
        totpVerifyBtn.addEventListener( 'click', () => {
            const code = ( totpCodeInput?.value || '' ).replace( /\D/g, '' );
            if ( code.length !== 6 ) {
                showTotpMsg( 'Please enter your 6-digit code.', false );
                return;
            }

            totpVerifyBtn.disabled = true;
            totpVerifyBtn.textContent = 'Verifying…';

            post( 'csdt_devtools_totp_setup_verify', { code } ).then( res => {
                totpVerifyBtn.disabled = false;
                totpVerifyBtn.textContent = '✓ Verify & Activate';
                if ( res.success ) {
                    showTotpMsg( '✅ ' + ( res.data?.message || 'Activated!' ), true );
                    // Clear the secret from the DOM now that setup is complete.
                    if ( totpSecret ) totpSecret.textContent = '';
                    setTimeout( () => location.reload(), 1200 );
                } else {
                    showTotpMsg( '❌ ' + ( res.data || 'Incorrect code.' ), false );
                    if ( totpCodeInput ) { totpCodeInput.value = ''; totpCodeInput.focus(); }
                }
            } ).catch( () => {
                totpVerifyBtn.disabled = false;
                totpVerifyBtn.textContent = '✓ Verify & Activate';
                showTotpMsg( 'Network error. Try again.', false );
            } );
        } );
    }

    function showTotpMsg( text, ok ) {
        if ( ! totpMsg ) return;
        totpMsg.textContent     = text;
        totpMsg.style.display   = 'block';
        totpMsg.style.color     = ok ? '#1db954' : '#e53e3e';
        totpMsg.style.fontWeight = '600';
    }

    // ── Failed login log ──────────────────────────────────────────────────

    const bfLogWrap   = document.getElementById( 'cs-bf-log-wrap' );
    const bfChart     = document.getElementById( 'cs-bf-chart' );
    const bfTableWrap = document.getElementById( 'cs-bf-table-wrap' );
    const bfLogTotal  = document.getElementById( 'cs-bf-log-total' );

    function escHtml( s ) {
        return String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
    }

    function fmtAgo( secs ) {
        if ( secs < 60 )    return 'just now';
        if ( secs < 3600 )  return Math.floor( secs / 60 ) + 'm ago';
        if ( secs < 86400 ) return Math.floor( secs / 3600 ) + 'h ago';
        return Math.floor( secs / 86400 ) + 'd ago';
    }

    function renderBfChart( log, now, isAttack ) {
        if ( ! bfChart ) return;
        const DAY = 86400;
        const days = [];
        for ( let i = 13; i >= 0; i-- ) {
            const dayStart = ( Math.floor( ( now - i * DAY ) / DAY ) ) * DAY;
            const dayEnd   = dayStart + DAY;
            const count    = log.filter( e => e[0] >= dayStart && e[0] < dayEnd ).length;
            const d        = new Date( dayStart * 1000 );
            days.push( {
                label: d.toLocaleDateString( 'en', { month: 'short', day: 'numeric' } ),
                count,
                isToday: i === 0,
            } );
        }
        // Outlier-resistant scale: if the top day is 4× the second-highest, cap the
        // display max so the spike doesn't flatten every other bar.
        const rawMax = Math.max( 1, ...days.map( d => d.count ) );
        const sortedDesc = days.map( d => d.count ).sort( ( a, b ) => b - a );
        const secondMax  = sortedDesc[ 1 ] || 0;
        const isCapped   = secondMax > 0 && rawMax > secondMax * 4;
        const displayMax = isCapped ? Math.max( 1, Math.ceil( secondMax * 2 ) ) : rawMax;

        const mid = displayMax === 1 ? 1 : Math.round( displayMax / 2 );
        const topLabel = isCapped ? displayMax + '+' : displayMax;
        const yAxis = `<div class="cs-bf-yaxis">
            <span class="cs-bf-ytick">${topLabel}</span>
            <span class="cs-bf-ytick">${mid}</span>
            <span class="cs-bf-ytick">0</span>
        </div>`;

        // Compute bar width so exactly 7 bars fill the visible area.
        // 14 bars total → content is ~2× container width → overflow-x scroll activates.
        const YAXIS_W = 28, GAP = 4, VISIBLE = 7;
        const chartW  = bfChart.clientWidth || 280;
        const barW    = Math.max( 24, Math.floor( ( chartW - YAXIS_W - VISIBLE * GAP ) / VISIBLE ) );

        const bars = days.map( d => {
            const truncated = d.count > displayMax;
            const pct = truncated ? 92 : Math.round( ( d.count / displayMax ) * 100 );
            let cls = d.count === 0 ? '' : d.count >= displayMax * 0.75 ? ' cs-bf-bar-high' : d.count >= displayMax * 0.4 ? ' cs-bf-bar-mid' : '';
            const extraStyle = isAttack && d.count > 0
                ? `background:#dc2626!important;${ d.isToday ? 'box-shadow:0 0 8px rgba(220,38,38,.6);' : 'opacity:.7;' }`
                : '';
            const truncLabel = truncated
                ? `<span style="position:absolute;top:-15px;left:50%;transform:translateX(-50%);font-size:9px;font-weight:700;color:#dc2626;white-space:nowrap;">${d.count}</span>`
                : '';
            return `<div class="cs-bf-day" style="width:${barW}px;flex-shrink:0;flex-grow:0;">
                <div class="cs-bf-bar-track" style="position:relative;">
                    ${truncLabel}<div class="cs-bf-bar${cls}" style="height:${pct}%;${extraStyle}" title="${d.count} failed attempt${d.count !== 1 ? 's' : ''}"></div>
                </div>
                <div class="cs-bf-day-label" style="${isAttack && d.isToday ? 'color:#dc2626;font-weight:700;' : ''}">${d.label}</div>
            </div>`;
        } ).join( '' );

        bfChart.innerHTML = yAxis + bars;
        // Scroll to most-recent (right end). setTimeout gives iOS Safari time to compute scrollWidth.
        const scrollToEnd = () => { bfChart.scrollLeft = bfChart.scrollWidth; };
        scrollToEnd();
        setTimeout( scrollToEnd, 50 );
    }

    function renderBfTable( log, now ) {
        if ( ! bfTableWrap ) return;
        if ( log.length === 0 ) {
            bfTableWrap.innerHTML = '<div class="cs-bf-empty">No failed login attempts in the last 14 days.</div>';
            return;
        }
        const rows = log.slice().reverse().slice( 0, 200 ).map( e => {
            const d    = new Date( e[0] * 1000 );
            const time = d.toLocaleDateString( 'en', { month: 'short', day: 'numeric' } )
                       + ' ' + d.toLocaleTimeString( 'en', { hour: '2-digit', minute: '2-digit' } );
            return `<tr>
                <td class="cs-bf-td cs-bf-td-time" title="${time}">${fmtAgo( now - e[0] )}</td>
                <td class="cs-bf-td cs-bf-td-user">${escHtml( e[1] || '—' )}</td>
                <td class="cs-bf-td cs-bf-td-ip">${escHtml( e[2] || '—' )}</td>
            </tr>`;
        } ).join( '' );
        bfTableWrap.innerHTML = `<table class="cs-bf-table">
            <thead><tr>
                <th class="cs-bf-th">When</th>
                <th class="cs-bf-th">Username tried</th>
                <th class="cs-bf-th">IP address</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>`;
    }

    if ( bfLogWrap ) {
        post( 'csdt_devtools_bf_log_fetch', {} ).then( res => {
            if ( ! res.success ) return;
            const { log, now, today_count } = res.data;
            const isAttack = today_count >= 30;
            if ( bfLogTotal ) bfLogTotal.textContent = log.length + ' event' + ( log.length !== 1 ? 's' : '' );
            renderBfChart( log, now, isAttack );
            renderBfTable( log, now );

            // Active attack banner
            if ( today_count >= 30 ) {
                const banner = document.createElement( 'div' );
                banner.style.cssText = 'display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:10px 14px;margin-bottom:12px;';

                const icon = document.createElement( 'span' );
                icon.textContent = '⚠';
                icon.style.cssText = 'font-size:1.1em;color:#dc2626;flex-shrink:0;';

                const label = document.createElement( 'span' );
                label.textContent = 'Brute force attack detected';
                label.style.cssText = 'font-weight:700;color:#991b1b;font-size:.9em;';

                const badge = document.createElement( 'span' );
                badge.textContent = today_count + ' attempts today';
                badge.style.cssText = 'background:#dc2626;color:#fff;font-size:.78em;font-weight:700;padding:2px 9px;border-radius:10px;white-space:nowrap;';

                const msg = document.createElement( 'span' );
                msg.textContent = 'Credential-stuffing is actively targeting this site. Ensure 2FA is enabled.';
                msg.style.cssText = 'font-size:.82em;color:#7f1d1d;flex-basis:100%;margin-top:2px;';

                banner.appendChild( icon );
                banner.appendChild( label );
                banner.appendChild( badge );
                banner.appendChild( msg );
                bfLogWrap.insertBefore( banner, bfLogWrap.firstChild );
            }
        } ).catch( () => {
            if ( bfTableWrap ) bfTableWrap.innerHTML = '<div class="cs-bf-empty">Could not load log.</div>';
        } );
    }

    // ── BF Self-Test ──────────────────────────────────────────────────────
    const bfTestBtn    = document.getElementById( 'cs-bf-test-btn' );
    const bfTestResult = document.getElementById( 'cs-bf-test-result' );

    if ( bfTestBtn ) {
        bfTestBtn.addEventListener( 'click', () => {
            bfTestBtn.disabled = true;
            bfTestBtn.textContent = '⏳ Testing…';
            if ( bfTestResult ) { bfTestResult.style.display = 'none'; bfTestResult.textContent = ''; }

            post( 'csdt_bf_self_test', {} ).then( res => {
                bfTestBtn.disabled = false;
                bfTestBtn.textContent = '🧪 Test BF Protection';
                if ( ! bfTestResult ) return;
                bfTestResult.style.display = 'inline-block';
                if ( res.success && res.data.passed ) {
                    bfTestResult.style.cssText = 'display:inline-block;background:#dcfce7;color:#166534;border:1px solid #86efac;border-radius:6px;padding:5px 12px;font-size:13px;font-weight:600;';
                    const notif = res.data.ntfy_url
                        ? ( res.data.notif_sent ? ' ntfy sent.' : ' (no ntfy configured)' )
                        : ' (no ntfy configured)';
                    bfTestResult.textContent = `✅ PASS — lockout fired after ${res.data.attempts} attempts (${res.data.lockout_mins} min).${notif}`;
                } else if ( res.success && ! res.data.passed ) {
                    bfTestResult.style.cssText = 'display:inline-block;background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;border-radius:6px;padding:5px 12px;font-size:13px;font-weight:600;';
                    bfTestResult.textContent = '❌ FAIL — lockout did not trigger. Check BF protection is saved correctly.';
                } else {
                    bfTestResult.style.cssText = 'display:inline-block;background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;border-radius:6px;padding:5px 12px;font-size:13px;font-weight:600;';
                    bfTestResult.textContent = '❌ ' + ( res.data || 'Test failed.' );
                }
            } ).catch( () => {
                bfTestBtn.disabled = false;
                bfTestBtn.textContent = '🧪 Test BF Protection';
                if ( bfTestResult ) {
                    bfTestResult.style.cssText = 'display:inline-block;background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;border-radius:6px;padding:5px 12px;font-size:13px;font-weight:600;';
                    bfTestResult.textContent = '❌ Request failed. Check your connection.';
                }
            } );
        } );
    }

    // ── SSH Monitor save ─────────────────────────────────────────────────

    const sshMonSaveBtn = document.getElementById( 'cs-ssh-mon-save' );
    const sshMonSaved   = document.getElementById( 'cs-ssh-mon-saved' );

    if ( sshMonSaveBtn ) {
        sshMonSaveBtn.addEventListener( 'click', () => {
            sshMonSaveBtn.disabled = true;
            post( 'csdt_ssh_monitor_save', {
                enabled:   document.getElementById( 'cs-ssh-mon-enabled' )?.checked ? '1' : '0',
                threshold: document.getElementById( 'cs-ssh-mon-threshold' )?.value || '10',
            } ).then( res => {
                sshMonSaveBtn.disabled = false;
                flash( sshMonSaved, res.success );
            } ).catch( () => {
                sshMonSaveBtn.disabled = false;
                flash( sshMonSaved, false );
            } );
        } );
    }

    const sshLogClearBtn = document.getElementById( 'cs-ssh-log-clear' );
    if ( sshLogClearBtn ) {
        sshLogClearBtn.addEventListener( 'click', () => {
            if ( ! confirm( 'Clear SSH alert history?' ) ) return;
            post( 'csdt_ssh_log_clear', {} ).then( res => {
                if ( res.success ) {
                    const tbl = sshLogClearBtn.closest( 'div[style]' );
                    if ( tbl ) tbl.remove();
                }
            } );
        } );
    }

    // ── Slug live preview + randomise + weak-slug warning ────────────────

    const slugInput    = document.getElementById( 'cs-login-slug' );
    const urlLink      = document.getElementById( 'cs-current-login-url' );
    const baseEl       = document.querySelector( '.cs-slug-base' );
    const randomBtn    = document.getElementById( 'cs-login-slug-random' );
    const weakWarn     = document.getElementById( 'cs-slug-weak-warn' );

    function isWeakSlug( v ) {
        // Warn if slug is purely alpha (no digits/hyphens) and under 14 chars — likely a dictionary word.
        return v.length > 0 && v.length < 14 && /^[a-z]+$/i.test( v );
    }

    function updateSlugPreview() {
        if ( ! slugInput || ! urlLink || ! baseEl ) return;
        const base = baseEl.textContent.replace( /\/$/, '' );
        const slug = slugInput.value.trim();
        const full = slug ? base + '/' + slug + '/' : urlLink.dataset.default || base + '/wp-login.php';
        urlLink.textContent = full;
        urlLink.href        = full;
        if ( weakWarn ) weakWarn.style.display = isWeakSlug( slug ) ? '' : 'none';
    }

    if ( slugInput && urlLink && baseEl ) {
        slugInput.addEventListener( 'input', updateSlugPreview );
        // Store original URL for empty-slug reset
        urlLink.dataset.default = urlLink.href;
        // Run once on load to flag any existing weak slug
        updateSlugPreview();
    }

    if ( randomBtn && slugInput ) {
        randomBtn.addEventListener( 'click', () => {
            const bytes  = new Uint8Array( 9 );
            crypto.getRandomValues( bytes );
            // base64url without padding — 12 URL-safe chars from 9 random bytes
            const slug = btoa( String.fromCharCode( ...bytes ) )
                .replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=/g, '' );
            slugInput.value = slug;
            updateSlugPreview();
        } );
    }

} )();
