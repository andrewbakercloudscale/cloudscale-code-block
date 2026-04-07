/**
 * CloudScale DevTools — 404 Games admin panel JS.
 *
 * Handles the enable toggle and colour scheme picker.
 * Depends on csDevtools404 (ajaxUrl, nonce, custom_404, scheme, previewUrl) localised by PHP.
 */
( function () {
    'use strict';

    var ajaxUrl    = csDevtools404.ajaxUrl;
    var nonce      = csDevtools404.nonce;
    var previewUrl = csDevtools404.previewUrl;

    // ── Enable toggle ────────────────────────────────────────────────────────

    var chkEnabled = document.getElementById( 'cs-404-enabled' );
    var toggleMsg  = document.getElementById( 'cs-404-toggle-msg' );

    if ( chkEnabled ) {
        chkEnabled.checked = parseInt( csDevtools404.custom_404, 10 ) === 1;

        chkEnabled.addEventListener( 'change', function () {
            var val = chkEnabled.checked ? 1 : 0;
            var fd  = new FormData();
            fd.append( 'action',     'cs_devtools_save_404_settings' );
            fd.append( 'nonce',      nonce );
            fd.append( 'custom_404', val );

            fetch( ajaxUrl, { method: 'POST', body: fd } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( resp ) {
                    if ( toggleMsg ) {
                        if ( resp.success ) {
                            toggleMsg.innerHTML = '<span style="color:#2e7d32;">&#10003; Setting saved.</span>';
                        } else {
                            toggleMsg.innerHTML = '<span style="color:#c62828;">&#10007; Failed to save.</span>';
                        }
                        toggleMsg.style.display = '';
                        setTimeout( function () { toggleMsg.style.display = 'none'; toggleMsg.innerHTML = ''; }, 2500 );
                    }
                } )
                .catch( function () {
                    if ( toggleMsg ) {
                        toggleMsg.innerHTML = '<span style="color:#c62828;">&#10007; Request failed.</span>';
                        toggleMsg.style.display = '';
                    }
                } );
        } );
    }

    // ── Colour scheme picker ─────────────────────────────────────────────────

    var schemeGrid  = document.getElementById( 'cs-404-scheme-grid' );
    var saveBtn     = document.getElementById( 'cs-404-save-scheme' );
    var schemeMsg   = document.getElementById( 'cs-404-scheme-msg' );
    var previewLink = document.getElementById( 'cs-404-preview-link' );

    function getActiveScheme() {
        var active = schemeGrid ? schemeGrid.querySelector( '.cs-404-scheme-swatch.active' ) : null;
        return active ? active.dataset.scheme : 'ocean';
    }

    function updatePreviewLink( scheme ) {
        if ( previewLink ) {
            previewLink.href = previewUrl + '?cs_devtools_preview_scheme=' + encodeURIComponent( scheme );
        }
    }

    // Set initial preview link.
    updatePreviewLink( getActiveScheme() );

    if ( schemeGrid ) {
        schemeGrid.addEventListener( 'click', function ( e ) {
            var btn = e.target.closest( '.cs-404-scheme-swatch' );
            if ( ! btn ) { return; }
            schemeGrid.querySelectorAll( '.cs-404-scheme-swatch' ).forEach( function ( el ) {
                el.style.borderColor = '#ddd';
                el.classList.remove( 'active' );
            } );
            btn.style.borderColor = '#f57c00';
            btn.classList.add( 'active' );
            updatePreviewLink( btn.dataset.scheme );
        } );
    }

    if ( saveBtn ) {
        saveBtn.addEventListener( 'click', function () {
            var scheme = getActiveScheme();
            saveBtn.disabled    = true;
            saveBtn.textContent = 'Saving…';

            var fd = new FormData();
            fd.append( 'action',     'cs_devtools_save_404_settings' );
            fd.append( 'nonce',      nonce );
            fd.append( 'scheme',     scheme );
            fd.append( 'custom_404', chkEnabled && chkEnabled.checked ? 1 : 0 );

            fetch( ajaxUrl, { method: 'POST', body: fd } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( resp ) {
                    saveBtn.disabled    = false;
                    saveBtn.textContent = '💾 Save Scheme';
                    if ( schemeMsg ) {
                        if ( resp.success ) {
                            schemeMsg.innerHTML = '<span style="color:#2e7d32;">&#10003; Scheme saved.</span>';
                        } else {
                            schemeMsg.innerHTML = '<span style="color:#c62828;">&#10007; Failed to save.</span>';
                        }
                        schemeMsg.style.display = '';
                        setTimeout( function () { schemeMsg.style.display = 'none'; schemeMsg.innerHTML = ''; }, 2500 );
                    }
                } )
                .catch( function () {
                    saveBtn.disabled    = false;
                    saveBtn.textContent = '💾 Save Scheme';
                    if ( schemeMsg ) {
                        schemeMsg.innerHTML = '<span style="color:#c62828;">&#10007; Request failed.</span>';
                        schemeMsg.style.display = '';
                    }
                } );
        } );
    }

} )();
