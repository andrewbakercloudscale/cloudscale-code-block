/* ===========================================================
   CloudScale DevTools — Mail / SMTP admin JS  v1.9.0
   Handles: SMTP settings save, from-address save, test email.
   =========================================================== */
( function () {
    'use strict';

    const { ajaxUrl, nonce, testTo } = window.csdtDevtoolsSmtp || {};

    // ── Helpers ───────────────────────────────────────────────────────────

    function post( action, data ) {
        const body = new URLSearchParams( { action, nonce, ...data } );
        return fetch( ajaxUrl, { method: 'POST', body } )
            .then( r => r.json() )
            .then( res => {
                if ( res === -1 || res === 0 ) {
                    return { success: false, data: 'Session expired — please reload and try again.' };
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

    // ── Enable toggle — dim/enable fields ────────────────────────────────

    const enabledToggle = document.getElementById( 'cs-smtp-enabled' );
    const smtpFields    = document.getElementById( 'cs-smtp-fields' );

    if ( enabledToggle && smtpFields ) {
        enabledToggle.addEventListener( 'change', () => {
            smtpFields.style.opacity       = enabledToggle.checked ? '1'    : '0.5';
            smtpFields.style.pointerEvents = enabledToggle.checked ? 'auto' : 'none';
        } );
    }

    // ── Auth toggle — show/hide credentials ──────────────────────────────

    const authToggle   = document.getElementById( 'cs-smtp-auth' );
    const authFields   = document.getElementById( 'cs-smtp-auth-fields' );

    if ( authToggle && authFields ) {
        authToggle.addEventListener( 'change', () => {
            authFields.style.display = authToggle.checked ? '' : 'none';
        } );
    }

    // ── Encryption → auto-fill port ──────────────────────────────────────

    const encryptionSel = document.getElementById( 'cs-smtp-encryption' );
    const portInput     = document.getElementById( 'cs-smtp-port' );

    if ( encryptionSel && portInput ) {
        encryptionSel.addEventListener( 'change', () => {
            const portMap = { tls: '587', ssl: '465', none: '25' };
            portInput.value = portMap[ encryptionSel.value ] || '587';
        } );
    }

    // ── Password "Change" reveal + View toggle ───────────────────────────

    const passChangeBtn = document.getElementById( 'cs-smtp-pass-change' );
    const passRow       = document.getElementById( 'cs-smtp-pass-row' );
    const passInput     = document.getElementById( 'cs-smtp-pass' );
    const passViewBtn   = document.getElementById( 'cs-smtp-pass-view' );

    if ( passChangeBtn && passRow ) {
        passChangeBtn.addEventListener( 'click', () => {
            passRow.style.display = 'flex';
            passChangeBtn.parentElement.style.display = 'none';
            if ( passInput ) passInput.focus();
        } );
    }

    if ( passViewBtn && passInput ) {
        passViewBtn.addEventListener( 'click', () => {
            const isHidden = passInput.type === 'password';
            passInput.type        = isHidden ? 'text'    : 'password';
            passViewBtn.textContent = isHidden ? 'Hide'  : 'View';
        } );
    }

    // ── SMTP save ─────────────────────────────────────────────────────────

    const smtpSaveBtn = document.getElementById( 'cs-smtp-save' );
    const smtpSaved   = document.getElementById( 'cs-smtp-saved' );

    function collectSmtpPayload() {
        return {
            enabled:    document.getElementById( 'cs-smtp-enabled' )?.checked    ? '1' : '0',
            host:       document.getElementById( 'cs-smtp-host' )?.value.trim()  || '',
            port:       document.getElementById( 'cs-smtp-port' )?.value         || '587',
            encryption: document.getElementById( 'cs-smtp-encryption' )?.value   || 'tls',
            auth:       document.getElementById( 'cs-smtp-auth' )?.checked       ? '1' : '0',
            user:       document.getElementById( 'cs-smtp-user' )?.value.trim()  || '',
            // Only send password if the user has entered something.
            pass:       document.getElementById( 'cs-smtp-pass' )?.value         || '',
            from_email: document.getElementById( 'cs-smtp-from-email' )?.value.trim() || '',
            from_name:  document.getElementById( 'cs-smtp-from-name' )?.value.trim()  || '',
        };
    }

    if ( smtpSaveBtn ) {
        smtpSaveBtn.addEventListener( 'click', () => {
            smtpSaveBtn.disabled = true;
            post( 'csdt_devtools_smtp_save', collectSmtpPayload() ).then( res => {
                smtpSaveBtn.disabled = false;
                flash( smtpSaved, res.success );
                if ( ! res.success ) {
                    alert( res.data || 'Save failed.' );
                }
            } ).catch( () => {
                smtpSaveBtn.disabled = false;
                alert( 'Save failed. Check your connection.' );
            } );
        } );
    }

    // ── From Address save ─────────────────────────────────────────────────

    const fromSaveBtn = document.getElementById( 'cs-smtp-from-save' );
    const fromSaved   = document.getElementById( 'cs-smtp-from-saved' );

    if ( fromSaveBtn ) {
        fromSaveBtn.addEventListener( 'click', () => {
            fromSaveBtn.disabled = true;
            // Re-use the same AJAX action — server saves all fields each time.
            post( 'csdt_devtools_smtp_save', collectSmtpPayload() ).then( res => {
                fromSaveBtn.disabled = false;
                flash( fromSaved, res.success );
                if ( ! res.success ) {
                    alert( res.data || 'Save failed.' );
                }
            } ).catch( () => {
                fromSaveBtn.disabled = false;
                alert( 'Save failed. Check your connection.' );
            } );
        } );
    }

    // ── Send test email ───────────────────────────────────────────────────

    const testBtn    = document.getElementById( 'cs-smtp-test-btn' );
    const testResult = document.getElementById( 'cs-smtp-test-result' );

    function renderTestError( data ) {
        if ( ! testResult ) return;
        testResult.innerHTML = '';

        if ( data?.type === 'preflight' && Array.isArray( data.issues ) ) {
            // Pre-flight: render a simple list of what's missing/wrong.
            const heading = document.createElement( 'strong' );
            heading.style.color = '#c0392b';
            heading.textContent = '✗ Fix these issues before testing:';
            testResult.appendChild( heading );
            const ul = document.createElement( 'ul' );
            ul.style.cssText = 'margin:6px 0 0 18px;padding:0;color:#c0392b;font-size:13px';
            data.issues.forEach( msg => {
                const li = document.createElement( 'li' );
                li.textContent = msg;
                ul.appendChild( li );
            } );
            testResult.appendChild( ul );

        } else if ( data?.type === 'smtp' ) {
            // SMTP error: show the message + collapsible debug log.
            const msg = document.createElement( 'div' );
            msg.style.cssText = 'color:#c0392b;font-weight:600;font-size:13px';
            msg.textContent = '✗ ' + ( data.message || 'SMTP error.' );
            testResult.appendChild( msg );

            if ( data.debug && data.debug.length ) {
                const details = document.createElement( 'details' );
                details.style.cssText = 'margin-top:8px;font-size:12px';
                const summary = document.createElement( 'summary' );
                summary.style.cssText = 'cursor:pointer;color:#666';
                summary.textContent = 'Show SMTP debug log';
                details.appendChild( summary );
                const pre = document.createElement( 'pre' );
                pre.style.cssText = 'margin:6px 0 0;padding:8px 10px;background:#f8f8f8;border:1px solid #e0e0e0;border-radius:4px;overflow-x:auto;color:#333;line-height:1.5;font-size:11px;white-space:pre-wrap';
                pre.textContent = data.debug.join( '\n' );
                details.appendChild( pre );
                testResult.appendChild( details );
            }

        } else {
            testResult.style.color = '#c0392b';
            testResult.textContent = '✗ ' + ( typeof data === 'string' ? data : 'Send failed.' );
        }
    }

    if ( testBtn ) {
        testBtn.addEventListener( 'click', () => {
            testBtn.disabled    = true;
            testBtn.textContent = '⏳ Sending…';
            if ( testResult ) {
                testResult.innerHTML = '';
                testResult.style.color = '';
            }
            post( 'csdt_devtools_smtp_test', {} ).then( res => {
                testBtn.disabled    = false;
                testBtn.textContent = '📨 Send Test Email';
                if ( res.success ) {
                    if ( testResult ) {
                        testResult.style.color  = '#2d7d46';
                        testResult.textContent  = '✓ Email sent to ' + ( res.data?.to || testTo );
                    }
                } else {
                    renderTestError( res.data );
                }
                // Refresh the activity log either way so the attempt appears immediately.
                setTimeout( refreshLog, 500 );
            } ).catch( () => {
                testBtn.disabled    = false;
                testBtn.textContent = '📨 Send Test Email';
                if ( testResult ) {
                    testResult.style.color = '#c0392b';
                    testResult.textContent = '✗ Request failed. Check your connection.';
                }
            } );
        } );
    }

    // ── Email log: refresh and clear ─────────────────────────────────────

    const logWrap      = document.getElementById( 'cs-email-log-wrap' );
    const logRefreshBtn = document.getElementById( 'cs-log-refresh' );
    const logClearBtn  = document.getElementById( 'cs-log-clear' );

    function refreshLog() {
        if ( ! logWrap ) return;
        post( 'csdt_devtools_smtp_log_fetch', {} ).then( res => {
            if ( ! res.success ) return;
            const rows = res.data;
            if ( ! rows || rows.length === 0 ) {
                logWrap.innerHTML = '<p style="color:#888;font-size:13px;margin:0">No emails logged yet. Emails are recorded here as soon as WordPress sends them.</p>';
                return;
            }
            const monthNames = [ 'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec' ];
            function fmtTs( ts ) {
                const d = new Date( ts * 1000 );
                const pad = n => String( n ).padStart( 2, '0' );
                return monthNames[ d.getMonth() ] + ' ' + d.getDate() + ', ' + pad( d.getHours() ) + ':' + pad( d.getMinutes() ) + ':' + pad( d.getSeconds() );
            }
            let html = '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:13px">'
                     + '<thead><tr style="background:#f3f4f6;text-align:left">'
                     + '<th style="padding:7px 10px;border-bottom:1px solid #e0e0e0;white-space:nowrap">Time</th>'
                     + '<th style="padding:7px 10px;border-bottom:1px solid #e0e0e0">To</th>'
                     + '<th style="padding:7px 10px;border-bottom:1px solid #e0e0e0">Subject</th>'
                     + '<th style="padding:7px 10px;border-bottom:1px solid #e0e0e0;white-space:nowrap">Via</th>'
                     + '<th style="padding:7px 10px;border-bottom:1px solid #e0e0e0">Status</th>'
                     + '</tr></thead><tbody>';
            rows.forEach( ( row, i ) => {
                const bg      = i % 2 === 0 ? '#fff' : '#fafafa';
                const via     = row.via === 'smtp'
                    ? '<span style="background:#e8f5e9;color:#2d7d46;padding:1px 6px;border-radius:3px;font-size:11px">SMTP</span>'
                    : '<span style="background:#f3f4f6;color:#666;padding:1px 6px;border-radius:3px;font-size:11px">PHP mail</span>';
                let status;
                if ( row.status === 'sent' ) {
                    status = '<span style="color:#2d7d46;font-weight:600">✓ Sent</span>';
                } else if ( row.status === 'failed' ) {
                    const err = row.error ? ' — ' + row.error : '';
                    status = '<span style="color:#c0392b;font-weight:600">✗ Failed' + err + '</span>';
                } else {
                    status = '<span style="color:#888">— Unknown</span>';
                }
                html += `<tr style="background:${bg}">
                    <td style="padding:7px 10px;border-bottom:1px solid #f0f0f0;white-space:nowrap;color:#666">${fmtTs(row.ts)}</td>
                    <td style="padding:7px 10px;border-bottom:1px solid #f0f0f0;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${row.to}</td>
                    <td style="padding:7px 10px;border-bottom:1px solid #f0f0f0;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${row.subject}</td>
                    <td style="padding:7px 10px;border-bottom:1px solid #f0f0f0">${via}</td>
                    <td style="padding:7px 10px;border-bottom:1px solid #f0f0f0">${status}</td>
                </tr>`;
            } );
            html += '</tbody></table></div>';
            logWrap.innerHTML = html;
        } );
    }

    if ( logRefreshBtn ) {
        logRefreshBtn.addEventListener( 'click', refreshLog );
    }

    if ( logClearBtn ) {
        logClearBtn.addEventListener( 'click', () => {
            if ( ! confirm( 'Clear all email log entries?' ) ) return;
            logClearBtn.disabled = true;
            post( 'csdt_devtools_smtp_log_clear', {} ).then( res => {
                logClearBtn.disabled = false;
                if ( res.success ) refreshLog();
            } ).catch( () => { logClearBtn.disabled = false; } );
        } );
    }

} )();
