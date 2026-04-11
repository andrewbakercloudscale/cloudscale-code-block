/* ===========================================================
   CloudScale Code Block — Passkey (WebAuthn) admin JS  v1.9.0
   Handles: passkey registration wizard, passkey list management.
   =========================================================== */
( function () {
    'use strict';

    // Shared with cs-login.js — both are enqueued on the login tab.
    const { ajaxUrl, nonce } = window.csdtDevtoolsLogin || {};

    // ── Helpers ───────────────────────────────────────────────────────────────

    function post( action, data ) {
        const body = new URLSearchParams( { action, nonce, ...data } );
        return fetch( ajaxUrl, { method: 'POST', body } )
            .then( r => r.json() )
            .then( res => {
                if ( res === -1 || res === 0 ) {
                    return { success: false, data: 'Session expired — please reload the page and try again.' };
                }
                return res;
            } );
    }

    function b64u( b ) {
        const s = b.replace( /-/g, '+' ).replace( /_/g, '/' );
        return Uint8Array.from( atob( s + '==='.slice( 0, ( 4 - s.length % 4 ) % 4 ) ), c => c.charCodeAt( 0 ) );
    }

    function buf64( buf ) {
        return btoa( String.fromCharCode( ...new Uint8Array( buf ) ) )
            .replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=/g, '' );
    }

    // ── Element refs ──────────────────────────────────────────────────────────

    const addBtn      = document.getElementById( 'cs-pk-add-btn' );
    const wizard      = document.getElementById( 'cs-pk-wizard' );
    const nameInput   = document.getElementById( 'cs-pk-name-input' );
    const registerBtn = document.getElementById( 'cs-pk-register-btn' );
    const cancelBtn   = document.getElementById( 'cs-pk-cancel-btn' );
    const statusEl    = document.getElementById( 'cs-pk-wizard-status' );
    const badge       = document.getElementById( 'cs-pk-badge' );
    const pkList      = document.getElementById( 'cs-pk-list' );

    // ── Wizard open / close ───────────────────────────────────────────────────

    function openWizard() {
        if ( wizard ) wizard.style.display = 'block';
        if ( addBtn ) addBtn.style.display = 'none';
        if ( nameInput ) { nameInput.value = ''; nameInput.focus(); }
        hideStatus();
    }

    function closeWizard() {
        if ( wizard ) wizard.style.display = 'none';
        if ( addBtn ) addBtn.style.display = '';
    }

    function showStatus( msg, ok ) {
        if ( ! statusEl ) return;
        statusEl.style.display   = '';
        statusEl.style.color     = ok ? '#065f46' : '#b91c1c';
        statusEl.style.background = ok ? '#d1fae5' : '#fee2e2';
        statusEl.textContent = msg;
    }

    function hideStatus() {
        if ( statusEl ) statusEl.style.display = 'none';
    }

    if ( addBtn )    addBtn.addEventListener( 'click', openWizard );
    if ( cancelBtn ) cancelBtn.addEventListener( 'click', closeWizard );

    if ( nameInput ) {
        nameInput.addEventListener( 'keydown', e => {
            if ( e.key === 'Enter' ) registerBtn?.click();
        } );
    }

    // ── Registration flow ─────────────────────────────────────────────────────

    if ( registerBtn ) {
        registerBtn.addEventListener( 'click', async () => {
            if ( ! window.PublicKeyCredential ) {
                showStatus( '✗ Your browser does not support passkeys. Try Chrome, Safari, or Edge.', false );
                return;
            }

            const name = ( nameInput?.value || '' ).trim() || 'My Passkey';
            registerBtn.disabled    = true;
            registerBtn.textContent = 'Waiting for device…';
            hideStatus();

            // 1. Get registration options from server.
            let startRes;
            try {
                startRes = await post( 'csdt_devtools_pk_register_start', {} );
            } catch {
                registerBtn.disabled    = false;
                registerBtn.textContent = 'Register';
                showStatus( '✗ Network error. Please try again.', false );
                return;
            }
            if ( ! startRes?.success ) {
                registerBtn.disabled    = false;
                registerBtn.textContent = 'Register';
                showStatus( '✗ ' + ( startRes?.data || 'Failed to start registration.' ), false );
                return;
            }

            const o = startRes.data;

            // 2. Convert challenge + user.id to ArrayBuffer.
            const publicKey = {
                challenge:              b64u( o.challenge ),
                rp:                     o.rp,
                user:                   { id: b64u( o.user.id ), name: o.user.name, displayName: o.user.displayName },
                pubKeyCredParams:       o.pubKeyCredParams,
                timeout:                o.timeout,
                attestation:            o.attestation,
                authenticatorSelection: o.authenticatorSelection,
                excludeCredentials:     ( o.excludeCredentials || [] ).map( c => ( { type: 'public-key', id: b64u( c.id ) } ) ),
            };

            // 3. Prompt the browser / device.
            let cred;
            try {
                cred = await navigator.credentials.create( { publicKey } );
            } catch ( err ) {
                registerBtn.disabled    = false;
                registerBtn.textContent = 'Register';
                const msg = err.name === 'NotAllowedError'
                    ? 'Registration was cancelled or timed out.'
                    : ( err.message || 'Device registration failed.' );
                showStatus( '✗ ' + msg, false );
                return;
            }

            // 4. Send attestation to server.
            let finishRes;
            try {
                finishRes = await post( 'csdt_devtools_pk_register_finish', {
                    name,
                    credentialId:      buf64( cred.rawId ),
                    clientDataJSON:    buf64( cred.response.clientDataJSON ),
                    attestationObject: buf64( cred.response.attestationObject ),
                } );
            } catch {
                registerBtn.disabled    = false;
                registerBtn.textContent = 'Register';
                showStatus( '✗ Network error verifying registration.', false );
                return;
            }

            registerBtn.disabled    = false;
            registerBtn.textContent = 'Register';

            if ( finishRes?.success ) {
                showStatus( '✅ Passkey registered! Refreshing…', true );
                setTimeout( () => location.reload(), 1200 );
            } else {
                showStatus( '✗ ' + ( finishRes?.data || 'Registration failed.' ), false );
            }
        } );
    }

    // ── Delete passkeys ───────────────────────────────────────────────────────

    function attachDeleteHandlers( scope ) {
        ( scope || document ).querySelectorAll( '.cs-pk-delete' ).forEach( btn => {
            if ( btn.dataset.bound ) return;
            btn.dataset.bound = '1';
            btn.addEventListener( 'click', async () => {
                const item = btn.closest( '.cs-pk-item' );
                const name = item?.querySelector( '.cs-pk-name' )?.textContent?.trim() || 'this passkey';
                if ( ! confirm( `Remove "${name}"? You can register it again at any time.` ) ) return;

                btn.disabled = true;
                const res = await post( 'csdt_devtools_pk_delete', { cred_id: btn.dataset.id } ).catch( () => null );

                if ( res?.success ) {
                    item?.remove();
                    updateBadge();
                } else {
                    btn.disabled = false;
                    alert( res?.data || 'Failed to remove passkey.' );
                }
            } );
        } );
    }

    function updateBadge() {
        if ( ! badge ) return;
        const remaining = pkList?.querySelectorAll( '.cs-pk-item' ).length ?? 0;
        if ( remaining === 0 ) {
            badge.textContent = 'None registered';
            badge.className   = 'cs-2fa-badge cs-2fa-badge-off';
        } else {
            badge.textContent = remaining + ( remaining === 1 ? ' passkey' : ' passkeys' );
            badge.className   = 'cs-2fa-badge cs-2fa-badge-on';
        }
    }

    attachDeleteHandlers( document );

    // ── Test passkeys ─────────────────────────────────────────────────────────

    function attachTestHandlers( scope ) {
        ( scope || document ).querySelectorAll( '.cs-pk-test' ).forEach( btn => {
            if ( btn.dataset.bound ) return;
            btn.dataset.bound = '1';
            btn.addEventListener( 'click', async () => {
                const item   = btn.closest( '.cs-pk-item' );
                const result = item?.querySelector( '.cs-pk-test-result' );
                btn.disabled = true;
                btn.textContent = 'Testing…';
                if ( result ) { result.style.display = 'none'; result.textContent = ''; }

                try {
                    // 1. Get challenge from server.
                    const startRes = await post( 'csdt_devtools_pk_test_start', {} );
                    if ( ! startRes?.success ) throw new Error( startRes?.data || 'Could not start test.' );
                    const o = startRes.data;

                    // 2. Invoke authenticator.
                    const cred = await navigator.credentials.get({ publicKey: {
                        challenge:        b64u( o.challenge ),
                        rpId:             o.rpId,
                        timeout:          o.timeout,
                        userVerification: o.userVerification,
                        allowCredentials: [],
                    }});

                    // 3. Send assertion to server for verification.
                    const finishRes = await post( 'csdt_devtools_pk_test_finish', {
                        csdt_devtools_pk_cred_id:    buf64( cred.rawId ),
                        csdt_devtools_pk_client_data: buf64( cred.response.clientDataJSON ),
                        csdt_devtools_pk_auth_data:  buf64( cred.response.authenticatorData ),
                        csdt_devtools_pk_signature:  buf64( cred.response.signature ),
                    });

                    if ( finishRes?.success ) {
                        if ( result ) { result.textContent = '✅ Working'; result.style.color = '#065f46'; result.style.display = ''; }
                    } else {
                        if ( result ) { result.textContent = '❌ ' + ( finishRes?.data || 'Verification failed' ); result.style.color = '#b91c1c'; result.style.display = ''; }
                    }
                } catch ( e ) {
                    const msg = e.name === 'NotAllowedError'
                        ? '❌ Browser: no matching passkey found'
                        : ( '❌ ' + ( e.message || 'Unknown error' ) );
                    if ( result ) { result.textContent = msg; result.style.color = '#b91c1c'; result.style.display = ''; }
                }

                btn.disabled = false;
                btn.textContent = 'Test';
            } );
        } );
    }

    attachTestHandlers( document );

} )();
