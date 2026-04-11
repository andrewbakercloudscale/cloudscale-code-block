/* ===========================================================
   CloudScale Code Block — Login Security admin JS  v1.9.5
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
        el.classList.add( 'visible' );
        if ( ! ok ) el.style.color = '#e53e3e';
        setTimeout( () => {
            el.classList.remove( 'visible' );
            el.style.color = '';
        }, 3000 );
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
                    alert( res.data || 'Save failed.' );
                }
            } ).catch( () => {
                hideSaveBtn.disabled = false;
                alert( 'Save failed. Check your connection.' );
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
                if ( res.success ) {
                    flash( sessionSaved, true );
                } else {
                    alert( res.data || 'Save failed.' );
                }
            } ).catch( () => {
                sessionSaveBtn.disabled = false;
                alert( 'Save failed. Check your connection.' );
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
                if ( res.success ) {
                    flash( bfSaved, true );
                } else {
                    alert( res.data || 'Save failed.' );
                }
            } ).catch( () => {
                bfSaveBtn.disabled = false;
                alert( 'Save failed. Check your connection.' );
            } );
        } );
    }

    if ( twoFaSaveBtn ) {
        twoFaSaveBtn.addEventListener( 'click', () => {
            twoFaSaveBtn.disabled = true;
            post( 'csdt_devtools_login_save', collectLoginPayload() ).then( res => {
                twoFaSaveBtn.disabled = false;
                if ( res.success ) {
                    flash( twoFaSaved, true );
                } else {
                    alert( res.data || 'Save failed.' );
                }
            } ).catch( () => {
                twoFaSaveBtn.disabled = false;
                alert( 'Save failed. Check your connection.' );
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
                        span.textContent   = '✗ ' + ( d.message || 'Failed to send.' );
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

    // ── Slug live preview ─────────────────────────────────────────────────

    const slugInput = document.getElementById( 'cs-login-slug' );
    const urlLink   = document.getElementById( 'cs-current-login-url' );
    const baseEl    = document.querySelector( '.cs-slug-base' );

    if ( slugInput && urlLink && baseEl ) {
        slugInput.addEventListener( 'input', () => {
            const base = baseEl.textContent.replace( /\/$/, '' );
            const slug = slugInput.value.trim();
            const full = slug ? base + '/' + slug + '/' : urlLink.dataset.default || base + '/wp-login.php';
            urlLink.textContent = full;
            urlLink.href        = full;
        } );
        // Store original URL for empty-slug reset
        if ( urlLink ) urlLink.dataset.default = urlLink.href;
    }

} )();
