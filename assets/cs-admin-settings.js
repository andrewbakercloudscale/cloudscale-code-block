/**
 * CloudScale Code Block - Admin Settings Save
 *
 * Handles the AJAX save for the Code Block Settings panel.
 * Depends on csAdminSettings (nonce) localised by PHP.
 */
( function() {
    'use strict';

    var saveBtn   = document.getElementById( 'cs-settings-save' );
    var selPair   = document.getElementById( 'cs-settings-pair' );
    var selTheme  = document.getElementById( 'cs-settings-theme' );
    var savedMsg  = document.getElementById( 'cs-settings-saved' );
    var chkPerf   = document.getElementById( 'cs-settings-perf-enabled' );

    if ( ! saveBtn ) {
        return;
    }

    saveBtn.addEventListener( 'click', function() {
        saveBtn.disabled    = true;
        saveBtn.textContent = 'Saving...';

        var fd = new FormData();
        fd.append( 'action',     'cs_save_theme_setting' );
        fd.append( 'nonce',      csAdminSettings.nonce );
        fd.append( 'theme',      selTheme.value );
        fd.append( 'theme_pair', selPair.value );
        fd.append( 'cs_perf_monitor_enabled', chkPerf && chkPerf.checked ? '1' : '0' );

        fetch( ajaxurl, { method: 'POST', body: fd } )
            .then( function( r ) { return r.json(); } )
            .then( function( resp ) {
                saveBtn.disabled    = false;
                saveBtn.textContent = '💾 Save Settings';
                if ( resp.success ) {
                    savedMsg.classList.add( 'visible' );
                    setTimeout( function() {
                        savedMsg.classList.remove( 'visible' );
                    }, 2000 );
                    var perfPanel = document.getElementById( 'cs-perf' );
                    if ( perfPanel ) {
                        perfPanel.style.display = resp.data.perf_enabled === '1' ? '' : 'none';
                    }
                }
            } )
            .catch( function( e ) {
                saveBtn.disabled    = false;
                saveBtn.textContent = '💾 Save Settings';
                console.error( 'cs-code-block: settings save failed', e );
            } );
    } );
} )();
