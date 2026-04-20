( function () {
    'use strict';

    var loadBtns      = document.querySelectorAll( '.cs-debug-load-btn' );
    var loadStatus    = document.getElementById( 'csdt-debug-load-status' );
    var logLines      = document.getElementById( 'csdt-debug-log-lines' );
    var inputArea     = document.getElementById( 'csdt-debug-input' );
    var analyzeBtn    = document.getElementById( 'csdt-debug-analyze' );
    var analyzeStatus = document.getElementById( 'csdt-debug-analyze-status' );
    var resultDiv     = document.getElementById( 'csdt-debug-result' );

    if ( ! analyzeBtn || ! inputArea ) { return; }

    var cfg = window.csdtDebug || {};

    function esc( s ) {
        return String( s )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' );
    }

    function post( action, data, nonce ) {
        var fd = new FormData();
        fd.append( 'action', action );
        fd.append( 'nonce', nonce );
        Object.keys( data ).forEach( function ( k ) { fd.append( k, data[ k ] ); } );
        return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } )
            .then( function ( r ) { return r.json(); } );
    }

    function isErrorLine( line ) {
        var t = line.toLowerCase();
        return /\b(fatal|error|critical|uncaught|exception|warning|deprecated)\b/.test( t );
    }

    function loadSource( source ) {
        if ( loadStatus ) { loadStatus.textContent = 'Loading\u2026'; }
        if ( logLines ) { logLines.style.display = 'none'; logLines.innerHTML = ''; }

        post( 'csdt_devtools_server_logs_fetch', { source: source, lines: 200 }, cfg.logsNonce )
            .then( function ( res ) {
                if ( ! res.success ) {
                    if ( loadStatus ) { loadStatus.textContent = 'Log not available.'; }
                    return;
                }
                var d = res.data;
                if ( d.status === 'not_found' ) {
                    if ( loadStatus ) { loadStatus.textContent = 'Log file not found: ' + d.path; }
                    return;
                }
                if ( d.status === 'permission_denied' ) {
                    if ( loadStatus ) { loadStatus.textContent = 'Permission denied: ' + d.path; }
                    return;
                }
                if ( d.status === 'empty' || ! d.lines || ! d.lines.length ) {
                    if ( loadStatus ) { loadStatus.textContent = 'Log is empty.'; }
                    return;
                }

                var errors = d.lines.filter( isErrorLine );
                if ( ! errors.length ) {
                    if ( loadStatus ) { loadStatus.textContent = 'No errors in the last ' + d.count + ' lines \u2014 log looks clean.'; }
                    return;
                }

                if ( loadStatus ) {
                    loadStatus.textContent = errors.length + ' error' + ( errors.length === 1 ? '' : 's' ) + ' found. Click a line to analyze.';
                }

                var recent = errors.slice( -60 );
                logLines.innerHTML = recent.map( function ( line ) {
                    return '<div class="csdt-debug-line" data-line="' + esc( line ) + '" '
                         + 'style="padding:6px 12px;border-bottom:1px solid #1e293b;cursor:pointer;'
                         + 'font-size:.78em;font-family:monospace;color:#f87171;white-space:nowrap;'
                         + 'overflow:hidden;text-overflow:ellipsis;" title="' + esc( line ) + '">'
                         + esc( line )
                         + '</div>';
                } ).join( '' );
                logLines.style.display = 'block';
            } )
            .catch( function () {
                if ( loadStatus ) { loadStatus.textContent = 'Failed to fetch log.'; }
            } );
    }

    loadBtns.forEach( function ( btn ) {
        btn.addEventListener( 'click', function () { loadSource( btn.dataset.source ); } );
    } );

    if ( logLines ) {
        logLines.addEventListener( 'click', function ( e ) {
            var row = e.target.closest( '.csdt-debug-line' );
            if ( ! row ) { return; }
            inputArea.value = row.dataset.line;
            inputArea.focus();
            // Highlight selected
            logLines.querySelectorAll( '.csdt-debug-line' ).forEach( function ( r ) {
                r.style.background = '';
            } );
            row.style.background = '#1e3a5f';
        } );
    }

    function renderResult( text ) {
        if ( ! text ) { return '<p style="color:#94a3b8;">No analysis returned.</p>'; }

        var sections = [
            { key: 'Root Cause',     icon: '&#x1F534;', color: '#fca5a5' },
            { key: 'Why It Happens', icon: '&#x1F7E1;', color: '#fde68a' },
            { key: 'How to Fix It',  icon: '&#x1F7E2;', color: '#86efac' },
        ];

        var html = text;

        // Replace section headers with styled divs (handle **Header** or Header:)
        sections.forEach( function ( s ) {
            html = html.replace(
                new RegExp( '\\*\\*' + s.key + '\\*\\*:?|' + s.key + ':', 'g' ),
                '<div style="color:' + s.color + ';font-weight:700;font-size:1em;margin:20px 0 6px;">'
                + s.icon + ' ' + s.key + '</div>'
            );
        } );

        // Inline code
        html = html.replace( /`([^`\n]+)`/g, '<code style="background:#1e293b;padding:1px 5px;border-radius:3px;font-size:.9em;">$1</code>' );
        // Bold
        html = html.replace( /\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>' );
        // Numbered list items
        html = html.replace( /^(\d+)\.\s+(.+)$/gm, '<div style="margin:4px 0 4px 8px;"><strong>$1.</strong> $2</div>' );
        // Paragraph breaks
        html = html.replace( /\n{2,}/g, '</p><p style="margin:8px 0;">' );
        html = html.replace( /\n/g, '<br>' );

        return '<div style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:22px 26px;color:#e2e8f0;font-size:.9em;line-height:1.75;">'
             + '<p style="margin:0 0 8px;">' + html + '</p>'
             + '</div>';
    }

    analyzeBtn.addEventListener( 'click', function () {
        var input = inputArea.value.trim();
        if ( ! input ) {
            if ( analyzeStatus ) { analyzeStatus.textContent = 'Paste an error or load from logs first.'; }
            return;
        }
        analyzeBtn.disabled = true;
        if ( analyzeStatus ) { analyzeStatus.textContent = '\u23F3 Analyzing\u2026'; }
        resultDiv.style.display = 'none';

        post( 'csdt_ai_debug_log', { input: input }, cfg.aiNonce )
            .then( function ( res ) {
                analyzeBtn.disabled = false;
                if ( analyzeStatus ) { analyzeStatus.textContent = ''; }
                if ( ! res.success ) {
                    resultDiv.innerHTML = '<p style="color:#f87171;">' + esc( ( res.data && res.data.message ) || 'Analysis failed.' ) + '</p>';
                } else {
                    resultDiv.innerHTML = renderResult( res.data.analysis || '' );
                }
                resultDiv.style.display = 'block';
                resultDiv.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
            } )
            .catch( function () {
                analyzeBtn.disabled = false;
                if ( analyzeStatus ) { analyzeStatus.textContent = 'Request failed \u2014 check your connection.'; }
            } );
    } );

    // Auto-load PHP error log on tab open if sources are available
    var sources = cfg.sources || {};
    if ( sources.php_error ) {
        loadSource( 'php_error' );
    }

    // PHP Error Alerting save button
    var saveBtn     = document.getElementById( 'csdt-errmon-save' );
    var enabledChk  = document.getElementById( 'csdt-errmon-enabled' );
    var thresholdIn = document.getElementById( 'csdt-errmon-threshold' );
    var saveStatus  = document.getElementById( 'csdt-errmon-status' );

    if ( saveBtn && enabledChk ) {
        saveBtn.addEventListener( 'click', function () {
            saveBtn.disabled = true;
            if ( saveStatus ) { saveStatus.textContent = '\u23F3 Saving\u2026'; }
            post( 'csdt_php_error_monitor_save', {
                enabled:   enabledChk.checked ? '1' : '0',
                threshold: thresholdIn ? thresholdIn.value : '1',
            }, cfg.debugNonce )
                .then( function ( res ) {
                    saveBtn.disabled = false;
                    if ( saveStatus ) {
                        saveStatus.textContent = res.success ? '\u2705 Saved' : '\u274C ' + esc( ( res.data && res.data.message ) || 'Failed' );
                        setTimeout( function () { if ( saveStatus ) { saveStatus.textContent = ''; } }, 3000 );
                    }
                } )
                .catch( function () {
                    saveBtn.disabled = false;
                    if ( saveStatus ) { saveStatus.textContent = 'Request failed.'; }
                } );
        } );
    }

}() );
