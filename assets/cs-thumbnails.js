/* ===========================================================
   CloudScale DevTools — Thumbnails / Social Preview  v1.9.6
   Handles: URL checker, post social scan, Cloudflare
            crawler test, CF cache purge, platform formats.
   =========================================================== */
( function () {
    'use strict';

    const { ajaxUrl, nonce, siteUrl } = window.csdtDevtoolsThumbs || {};

    // ── Helpers ───────────────────────────────────────────────────────────

    function post( action, data ) {
        const body = new URLSearchParams( { action, nonce, ...data } );
        return fetch( ajaxUrl, { method: 'POST', body } )
            .then( r => r.json() )
            .then( res => {
                if ( res === -1 || res === 0 ) {
                    return { success: false, data: { message: 'Session expired — please reload.' } };
                }
                return res;
            } );
    }

    function esc( str ) {
        return String( str )
            .replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
    }

    function setLoading( el, msg ) {
        el.style.display = 'block';
        el.innerHTML = `<p style="color:#555;font-size:13px">⏳ ${esc( msg || 'Loading…' )}</p>`;
    }

    // ── URL Checker ───────────────────────────────────────────────────────

    const checkBtn     = document.getElementById( 'cs-thumb-check-btn' );
    const checkUrlEl   = document.getElementById( 'cs-thumb-check-url' );
    const checkResults = document.getElementById( 'cs-thumb-check-results' );

    if ( checkBtn ) {
        checkBtn.addEventListener( 'click', () => {
            const url = ( checkUrlEl?.value || '' ).trim();
            if ( ! url ) { alert( 'Please enter a URL.' ); return; }
            checkBtn.disabled = true;
            checkBtn.textContent = 'Running…';
            setLoading( checkResults, 'Running all diagnostic checks — this may take 10–20 seconds…' );

            post( 'csdt_devtools_social_check_url', { url } ).then( res => {
                checkBtn.disabled = false;
                checkBtn.textContent = '🔍 Run Diagnostic';
                if ( ! res.success ) {
                    checkResults.innerHTML = `<p style="color:#8c2020">${esc( res.data?.message || 'Error' )}</p>`;
                    return;
                }
                checkResults.innerHTML = renderReport( res.data );
            } ).catch( () => {
                checkBtn.disabled = false;
                checkBtn.textContent = '🔍 Run Diagnostic';
                checkResults.innerHTML = '<p style="color:#8c2020">AJAX request failed. Check your connection.</p>';
            } );
        } );
    }

    function renderReport( data ) {
        const t = data.totals;
        const cls = t.fail > 0 ? 'fail' : t.warn > 0 ? 'warn' : 'pass';
        const msg = t.fail > 0
            ? `${t.fail} critical issue(s) found`
            : t.warn > 0 ? `${t.warn} warning(s) — review recommended` : 'All checks passed';

        const icons = { pass: '✔', warn: '⚠', fail: '✘', info: 'ℹ' };
        let html = `
            <div class="cs-thumb-report-hdr cs-thumb-${cls}-hdr">
                <strong>${esc( msg )}</strong>
                <span class="cs-thumb-tally">
                    <span style="color:#276227">✔ ${t.pass}</span>
                    <span style="color:#7a5a00">⚠ ${t.warn}</span>
                    <span style="color:#8c2020">✘ ${t.fail}</span>
                </span>
            </div>`;

        for ( const sec of data.sections ) {
            html += `<div class="cs-thumb-section">
                <div class="cs-thumb-section-title">${esc( sec.title )}</div>
                <ul class="cs-thumb-results-list">`;
            for ( const r of sec.results ) {
                const icon = icons[ r.type ] || 'ℹ';
                const fixHtml = r.fix
                    ? `<div class="cs-thumb-fix">💡 ${esc( r.fix )}</div>`
                    : '';
                html += `<li class="cs-thumb-result cs-thumb-${r.type || 'info'}">
                    <span>${icon}</span>
                    <span>${esc( r.msg )}${fixHtml}</span>
                </li>`;
            }
            html += '</ul></div>';
        }
        return html;
    }

    // ── Post Social Preview Scan ──────────────────────────────────────────

    const auditBtn      = document.getElementById( 'cs-thumb-audit-btn' );
    const auditTopBtn   = document.getElementById( 'cs-thumb-audit-top-btn' );
    const auditProgress = document.getElementById( 'cs-thumb-audit-progress' );
    const auditResults  = document.getElementById( 'cs-thumb-audit-results' );
    const fixAllBtn     = document.getElementById( 'cs-thumb-fix-all-btn' );
    const fixSiteBtn    = document.getElementById( 'cs-thumb-fix-site-btn' );

    let lastScanPosts = [];

    function runScan( mode ) {
        const btn       = mode === 'top' ? auditTopBtn : auditBtn;
        const otherBtn  = mode === 'top' ? auditBtn : auditTopBtn;
        const loadMsg   = mode === 'top'
            ? 'Reading featured images for the top 50 posts by view count…'
            : 'Reading featured images for the last 50 published posts…';
        const origLabel = btn ? btn.innerHTML : '';

        if ( btn ) btn.disabled = true;
        if ( btn ) btn.textContent = 'Scanning…';
        if ( otherBtn ) otherBtn.disabled = true;
        if ( auditProgress ) auditProgress.textContent = 'Checking featured images…';
        if ( fixAllBtn ) fixAllBtn.style.display = 'none';
        setLoading( auditResults, loadMsg );

        post( 'csdt_devtools_social_scan_media', { mode } ).then( res => {
            if ( btn ) { btn.disabled = false; btn.innerHTML = origLabel; }
            if ( otherBtn ) otherBtn.disabled = false;
            if ( auditProgress ) auditProgress.textContent = '';
            if ( ! res.success ) {
                auditResults.innerHTML = `<p style="color:#8c2020">${esc( res.data?.message || 'Error' )}</p>`;
                return;
            }
            lastScanPosts = res.data.posts || [];
            auditResults.style.display = 'block';
            auditResults.innerHTML = renderPostScan( res.data );
            const fixable = lastScanPosts.filter( p => p.can_fix && p.status !== 'pass' );
            if ( fixAllBtn && fixable.length ) fixAllBtn.style.display = '';
        } ).catch( () => {
            if ( btn ) { btn.disabled = false; btn.innerHTML = origLabel; }
            if ( otherBtn ) otherBtn.disabled = false;
            if ( auditProgress ) auditProgress.textContent = '';
            auditResults.innerHTML = '<p style="color:#8c2020">Request failed.</p>';
        } );
    }

    if ( auditBtn )    auditBtn.addEventListener(    'click', () => runScan( 'recent' ) );
    if ( auditTopBtn ) auditTopBtn.addEventListener( 'click', () => runScan( 'top' ) );

    // Fix all posts in one go.
    if ( fixAllBtn ) {
        fixAllBtn.addEventListener( 'click', () => {
            const fixable = lastScanPosts.filter( p => p.can_fix );
            if ( ! fixable.length ) return;
            fixAllBtn.disabled = true;
            let done = 0;
            fixAllBtn.textContent = `Fixing 0 / ${fixable.length}…`;
            const next = () => {
                if ( done >= fixable.length ) {
                    fixAllBtn.disabled = false;
                    fixAllBtn.textContent = `✔ Fixed ${done} posts`;
                    return;
                }
                const p = fixable[ done ];
                fixAllBtn.textContent = `Fixing ${done + 1} / ${fixable.length}…`;
                post( 'csdt_devtools_social_generate_formats', { post_id: p.post_id } ).then( () => {
                    const fixRow = document.getElementById( `cs-scan-fix-row-${p.post_id}` );
                    if ( fixRow ) fixRow.innerHTML = '<span style="color:#276227;font-size:11px">✔ Formats generated</span>';
                    done++;
                    next();
                } ).catch( () => { done++; next(); } );
            };
            next();
        } );
    }

    // Fix All Posts on Site — batch endpoint, processes all published posts.
    if ( fixSiteBtn ) {
        fixSiteBtn.addEventListener( 'click', () => {
            if ( ! confirm( 'This will generate social format images for every published post on the site. It may take a few minutes for large sites. Continue?' ) ) return;

            fixSiteBtn.disabled = true;
            if ( auditProgress ) auditProgress.textContent = 'Starting…';

            let totalPosts  = 0;
            let processed   = 0;
            let skipped     = 0;
            let errored     = 0;

            function runBatch( offset ) {
                post( 'csdt_devtools_social_fix_all_batch', { offset } ).then( res => {
                    if ( ! res.success ) {
                        fixSiteBtn.disabled    = false;
                        fixSiteBtn.textContent = '🌐 Fix All Posts on Site';
                        if ( auditProgress ) auditProgress.textContent = '✗ Batch error — see console.';
                        console.error( 'csdt_devtools_social_fix_all_batch error', res );
                        return;
                    }
                    const d = res.data;
                    if ( totalPosts === 0 ) totalPosts = d.total;

                    ( d.batch || [] ).forEach( item => {
                        if ( item.skipped )    skipped++;
                        else if ( item.ok )    processed++;
                        else                   errored++;
                    } );

                    const done = processed + skipped + errored;
                    fixSiteBtn.textContent = `Fixing ${done} / ${totalPosts}…`;
                    if ( auditProgress ) auditProgress.textContent = `${processed} fixed, ${skipped} skipped, ${errored} errors`;

                    if ( d.has_more ) {
                        runBatch( d.next_offset );
                    } else {
                        fixSiteBtn.disabled    = false;
                        fixSiteBtn.textContent = `✔ Done — ${processed} fixed, ${skipped} skipped`;
                        if ( auditProgress ) auditProgress.textContent = '';
                    }
                } ).catch( err => {
                    fixSiteBtn.disabled    = false;
                    fixSiteBtn.textContent = '🌐 Fix All Posts on Site';
                    if ( auditProgress ) auditProgress.textContent = '✗ Network error.';
                    console.error( 'fix_all_batch network error', err );
                } );
            }

            runBatch( 0 );
        } );
    }

    const PLATFORM_LABELS = {
        facebook:  'FB',
        twitter:   'X',
        whatsapp:  'WA',
        linkedin:  'LI',
        instagram: 'IG',
    };

    const PLATFORM_FULL = {
        facebook:  'Facebook',
        twitter:   'X / Twitter',
        whatsapp:  'WhatsApp',
        linkedin:  'LinkedIn',
        instagram: 'Instagram',
    };

    // ── Platform detail modal (shared, one per page) ──────────────────────

    const platformModal = ( () => {
        const overlay = document.createElement( 'div' );
        overlay.id = 'cs-platform-modal-overlay';
        overlay.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99998;overflow-y:auto;padding:40px 16px';
        const box = document.createElement( 'div' );
        box.style.cssText = 'background:#fff;border-radius:8px;max-width:520px;margin:0 auto;box-shadow:0 8px 32px rgba(0,0,0,.25);overflow:hidden';
        overlay.appendChild( box );
        document.body.appendChild( overlay );
        overlay.addEventListener( 'click', ( e ) => { if ( e.target === overlay ) close(); } );
        document.addEventListener( 'keydown', ( e ) => { if ( e.key === 'Escape' ) close(); } );
        function close() { overlay.style.display = 'none'; }
        function open( title, platforms ) {
            const STATUS_LABELS = { pass: 'Ready', warn: 'Warning', fail: 'Issue' };
            const cols = { pass: '#276227', warn: '#7a5a00', fail: '#8c2020' };
            const bgs  = { pass: '#edfaed', warn: '#fff8e5', fail: '#fdf0f0' };
            const icons = { pass: '✔', warn: '⚠', fail: '✘' };
            let rows = '';
            for ( const [ key, p ] of Object.entries( platforms ) ) {
                const full  = PLATFORM_FULL[ key ] || key;
                const col   = cols[ p.status ]  || '#555';
                const bg    = bgs[ p.status ]   || '#f6f7f7';
                const icon  = icons[ p.status ] || 'ℹ';
                const badge = STATUS_LABELS[ p.status ] || p.status;
                rows += `<div style="display:flex;align-items:flex-start;gap:12px;padding:10px 20px;border-bottom:1px solid #f0f0f0">
                    <div style="min-width:96px;font-size:13px;font-weight:600;color:#333;padding-top:2px">${esc( full )}</div>
                    <div style="flex:1">
                        <span style="display:inline-block;background:${bg};color:${col};padding:1px 8px;border-radius:10px;font-size:11px;font-weight:700;margin-bottom:4px">${icon} ${esc( badge )}</span>
                        <div style="font-size:12px;color:#50575e;line-height:1.5">${esc( p.msg )}</div>
                    </div>
                </div>`;
            }
            box.innerHTML = `
                <div style="background:#1d2327;color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
                    <strong style="font-size:14px">📋 Social Platform Compatibility</strong>
                    <button onclick="document.getElementById('cs-platform-modal-overlay').style.display='none'"
                        style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;line-height:1;padding:0 2px">&times;</button>
                </div>
                <div style="padding:12px 20px 6px;font-size:12px;color:#555;border-bottom:1px solid #eee">${esc( title )}</div>
                ${rows}
                <div style="padding:10px 20px;font-size:11px;color:#999;text-align:right">Click outside or press Esc to close</div>`;
            overlay.style.display = 'block';
        }
        return { open };
    } )();

    function renderPlatformChips( platforms, postId, title ) {
        if ( ! platforms ) return '';
        const jsonAttr = esc( JSON.stringify( platforms ) );
        const titleAttr = esc( title || '' );
        let html = `<div class="cs-platform-chips" data-platforms="${jsonAttr}" data-title="${titleAttr}" style="display:flex;flex-wrap:wrap;gap:4px;margin-top:5px;cursor:pointer" title="Click any chip to see full details">`;
        for ( const [ key, p ] of Object.entries( platforms ) ) {
            const label = PLATFORM_LABELS[ key ] || key;
            const bg    = p.status === 'pass' ? '#edfaed' : p.status === 'warn' ? '#fff8e5' : '#fdf0f0';
            const col   = p.status === 'pass' ? '#276227' : p.status === 'warn' ? '#7a5a00' : '#8c2020';
            const icon  = p.status === 'pass' ? '✔' : p.status === 'warn' ? '⚠' : '✘';
            html += `<span class="cs-chip" style="display:inline-flex;align-items:center;gap:3px;background:${bg};color:${col};padding:2px 7px;border-radius:10px;font-size:11px;font-weight:600;cursor:pointer;user-select:none"
                >${icon} ${esc( label )}</span>`;
        }
        html += '</div>';
        return html;
    }

    // Any chip click → open modal with all platforms for that post.
    document.addEventListener( 'click', ( e ) => {
        const chip = e.target.closest( '.cs-chip' );
        if ( ! chip ) return;
        const wrap = chip.closest( '.cs-platform-chips' );
        if ( ! wrap ) return;
        try {
            const platforms = JSON.parse( wrap.dataset.platforms || '{}' );
            platformModal.open( wrap.dataset.title || '', platforms );
        } catch ( err ) { /* ignore */ }
    } );

    function renderPostScan( data ) {
        const { total_scanned, pass, warn, fail, posts, mode, sort_note } = data;
        const modeLabel = mode === 'top' ? 'top' : 'most recent';
        const sortHint  = sort_note ? ` <span style="color:#888;font-size:11px">(${esc( sort_note )})</span>` : '';

        let html = `<div style="margin-bottom:12px;font-size:13px">
            Checked <strong>${esc( String( total_scanned ) )}</strong> ${esc( modeLabel )} posts${sortHint} —
            <span style="color:#276227">✔ ${esc( String( pass ) )} all platforms OK</span> &nbsp;
            <span style="color:#7a5a00">⚠ ${esc( String( warn ) )} warnings</span> &nbsp;
            <span style="color:#8c2020">✘ ${esc( String( fail ) )} issues</span>
        </div>`;

        const problem = posts.filter( p => p.status !== 'pass' );

        for ( const p of problem ) {
            const dims = p.width && p.height ? `${p.width}×${p.height}px` : '';
            const size = p.size_kb !== null ? `${p.size_kb} KB` : '';
            const meta = [ dims, size ].filter( Boolean ).join( ' · ' );

            const imgPreview = p.img_url
                ? `<img src="${esc( p.img_url )}" style="width:60px;height:40px;object-fit:cover;border-radius:3px;flex-shrink:0;border:1px solid #ddd" loading="lazy" alt="">`
                : `<div style="width:60px;height:40px;background:#f0f0f0;border-radius:3px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:18px;border:1px solid #ddd">🖼</div>`;

            const overallCol = p.status === 'fail' ? '#8c2020' : '#7a5a00';
            const overallIcon = p.status === 'fail' ? '✘' : '⚠';

            const fixBtn = p.can_fix
                ? `<button class="button button-small cs-scan-fix-btn" data-post-id="${esc( String( p.post_id ) )}" style="font-size:11px">🔧 Fix</button>`
                : '';

            html += `<div style="display:flex;gap:12px;align-items:flex-start;padding:10px 0;border-bottom:1px solid #f0f0f0">
                ${imgPreview}
                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                        <span style="color:${overallCol};font-weight:700;font-size:13px">${overallIcon}</span>
                        <a href="${esc( p.post_url )}" target="_blank" rel="noopener" style="font-size:13px;font-weight:600;color:#1a3a8f;text-decoration:none;word-break:break-word">${esc( p.title )}</a>
                    </div>
                    ${meta ? `<div style="font-size:11px;color:#888;margin-top:2px">${esc( meta )}</div>` : ''}
                    ${p.no_image ? '<div style="font-size:12px;color:#8c2020;margin-top:3px">No featured image set</div>' : renderPlatformChips( p.platforms, p.post_id, p.title )}
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;align-items:center">
                        <button class="button button-small cs-thumb-recheck-btn" data-url="${esc( p.post_url )}" style="font-size:11px">Re-check</button>
                        <a href="${esc( p.post_url )}" target="_blank" rel="noopener" class="button button-small" style="font-size:11px">View Post</a>
                        ${fixBtn}
                    </div>
                    <div id="cs-scan-fix-row-${esc( String( p.post_id ) )}" style="margin-top:4px"></div>
                </div>
            </div>`;
        }

        if ( fail === 0 && warn === 0 ) {
            html += `<p style="color:#276227;font-weight:600;margin-top:8px">✔ All ${esc( String( total_scanned ) )} posts are ready for all social platforms.</p>`;
        } else if ( problem.length < total_scanned ) {
            const okCount = total_scanned - problem.length;
            html += `<p style="color:#276227;font-size:12px;margin-top:10px">✔ ${esc( String( okCount ) )} post${okCount === 1 ? '' : 's'} already ready for all platforms — not shown above.</p>`;
        }

        return html;
    }

    // Re-check individual post from scan table.
    document.addEventListener( 'click', ( e ) => {
        const btn = e.target.closest( '.cs-thumb-recheck-btn' );
        if ( ! btn ) return;
        const url = btn.dataset.url;
        if ( checkUrlEl ) checkUrlEl.value = url;
        checkResults.style.display = 'block';
        setLoading( checkResults, `Re-checking ${url}…` );
        checkBtn?.scrollIntoView( { behavior: 'smooth', block: 'center' } );

        post( 'csdt_devtools_social_check_url', { url } ).then( res => {
            if ( ! res.success ) {
                checkResults.innerHTML = `<p style="color:#8c2020">${esc( res.data?.message || 'Error' )}</p>`;
                return;
            }
            checkResults.innerHTML = renderReport( res.data );
            checkResults.scrollIntoView( { behavior: 'smooth', block: 'start' } );
        } ).catch( () => {
            checkResults.innerHTML = '<p style="color:#8c2020">Request failed.</p>';
        } );
    } );

    // Fix individual post — generate platform formats.
    document.addEventListener( 'click', ( e ) => {
        const btn = e.target.closest( '.cs-scan-fix-btn' );
        if ( ! btn ) return;
        const postId = btn.dataset.postId;
        const fixRow = document.getElementById( `cs-scan-fix-row-${postId}` );
        btn.disabled = true;
        btn.textContent = 'Generating…';
        if ( fixRow ) fixRow.innerHTML = '<span style="color:#555;font-size:11px">⏳ Generating platform formats…</span>';

        post( 'csdt_devtools_social_generate_formats', { post_id: postId } ).then( res => {
            btn.style.display = 'none';
            if ( ! res.success ) {
                if ( fixRow ) fixRow.innerHTML = `<span style="color:#8c2020;font-size:11px">✘ ${esc( res.data?.message || 'Failed' )}</span>`;
                btn.disabled = false;
                btn.textContent = '🔧 Fix';
                btn.style.display = '';
                return;
            }
            if ( fixRow ) fixRow.innerHTML = renderFixResult( res.data );
        } ).catch( () => {
            btn.disabled = false;
            btn.textContent = '🔧 Fix';
            if ( fixRow ) fixRow.innerHTML = '<span style="color:#8c2020;font-size:11px">✘ Request failed</span>';
        } );
    } );

    function renderFixResult( platforms ) {
        let html = '<div class="cs-fix-modal-wrap">';
        for ( const [ key, r ] of Object.entries( platforms ) ) {
            if ( ! r.success ) {
                html += `<div class="cs-fix-platform-row">
                    <span class="cs-fix-platform-label">${esc( r.label || key )}</span>
                    <span class="cs-fix-platform-status" style="color:#8c2020">✘ ${esc( r.error || 'Failed' )}</span>
                </div>`;
                continue;
            }
            const sizeOk  = r.under_limit;
            const sizeCol = sizeOk ? '#276227' : '#8c2020';
            const sizeIcon = sizeOk ? '✔' : '⚠ over limit';
            html += `<div class="cs-fix-platform-row">
                <span class="cs-fix-platform-label">${esc( r.label )}</span>
                <span class="cs-fix-platform-dims">${esc( r.w + '×' + r.h )}</span>
                <span class="cs-fix-platform-status" style="color:${sizeCol}">${sizeIcon} ${esc( String( r.kb ) )} KB</span>
                <a href="${esc( r.preview_url )}" target="_blank" rel="noopener" title="Preview" style="flex-shrink:0">
                    <img src="${esc( r.preview_url )}" class="cs-fix-preview-thumb" alt="${esc( r.label )}">
                </a>
            </div>`;
        }
        html += '</div>';
        return html;
    }

    // ── Cloudflare Crawler Test ───────────────────────────────────────────

    const cfTestBtn     = document.getElementById( 'cs-thumb-cf-test-btn' );
    const cfTestUrl     = document.getElementById( 'cs-thumb-cf-test-url' );
    const cfTestResults = document.getElementById( 'cs-thumb-cf-test-results' );

    if ( cfTestBtn ) {
        cfTestBtn.addEventListener( 'click', () => {
            const url = ( cfTestUrl?.value || siteUrl || '' ).trim();
            if ( ! url ) { alert( 'Please enter a URL to test.' ); return; }
            cfTestBtn.disabled = true;
            cfTestBtn.textContent = 'Testing…';
            setLoading( cfTestResults, 'Sending requests with each social crawler user agent…' );

            post( 'csdt_devtools_social_cf_test', { url } ).then( res => {
                cfTestBtn.disabled = false;
                cfTestBtn.textContent = '🤖 Test Crawler Access';
                if ( ! res.success ) {
                    cfTestResults.innerHTML = `<p style="color:#8c2020">${esc( res.data?.message || 'Error' )}</p>`;
                    return;
                }
                cfTestResults.innerHTML = renderCfTestResults( res.data, url );
            } ).catch( () => {
                cfTestBtn.disabled = false;
                cfTestBtn.textContent = '🤖 Test Crawler Access';
                cfTestResults.innerHTML = '<p style="color:#8c2020">Request failed.</p>';
            } );
        } );
    }

    function renderCfTestResults( results, url ) {
        const allPass = Object.values( results ).every( r => r.type === 'pass' );
        const header  = allPass
            ? '<p style="color:#276227;font-weight:600">✔ All crawlers can access the page — your WAF skip rule is working correctly.</p>'
            : '<p style="color:#8c2020;font-weight:600">✘ One or more crawlers are being blocked. Check your Cloudflare WAF skip rule.</p>';

        let chips = '<div class="cs-thumb-ua-grid">';
        for ( const [ label, r ] of Object.entries( results ) ) {
            const chipCls = r.type === 'pass' ? 'ok' : 'fail';
            const icon    = r.type === 'pass' ? '✔' : '✘';
            chips += `<div class="cs-thumb-ua-chip cs-thumb-ua-${chipCls}" title="${esc( r.msg )}">${icon} ${esc( label )}</div>`;
        }
        chips += '</div>';

        let detail = '<ul class="cs-thumb-results-list" style="margin-top:10px">';
        for ( const [ label, r ] of Object.entries( results ) ) {
            detail += `<li class="cs-thumb-result cs-thumb-${r.type}">
                <span>${r.type === 'pass' ? '✔' : '✘'}</span>
                <span><strong>${esc( label )}:</strong> ${esc( r.msg )}</span>
            </li>`;
        }
        detail += '</ul>';

        return header + chips + detail;
    }

    // ── Cloudflare Cache Purge ────────────────────────────────────────────

    const cfPurgeBtn    = document.getElementById( 'cs-cf-purge-btn' );
    const cfPurgeUrl    = document.getElementById( 'cs-cf-purge-url' );
    const cfPurgeResult = document.getElementById( 'cs-cf-purge-result' );
    const cfSaveBtn     = document.getElementById( 'cs-cf-save-btn' );
    const cfZoneId      = document.getElementById( 'cs-cf-zone-id' );
    const cfApiToken    = document.getElementById( 'cs-cf-api-token' );
    const cfSaved       = document.getElementById( 'cs-cf-saved' );

    if ( cfSaveBtn ) {
        cfSaveBtn.addEventListener( 'click', () => {
            cfSaveBtn.disabled = true;
            post( 'csdt_devtools_cf_save', {
                zone_id:   cfZoneId?.value.trim() || '',
                api_token: cfApiToken?.value || '',
            } ).then( res => {
                cfSaveBtn.disabled = false;
                if ( res.success ) {
                    if ( cfSaved ) { cfSaved.classList.add( 'visible' ); setTimeout( () => cfSaved.classList.remove( 'visible' ), 3000 ); }
                    if ( cfApiToken ) cfApiToken.value = '';
                } else {
                    alert( res.data?.message || 'Save failed.' );
                }
            } ).catch( () => { cfSaveBtn.disabled = false; alert( 'Request failed.' ); } );
        } );
    }

    if ( cfPurgeBtn ) {
        cfPurgeBtn.addEventListener( 'click', () => {
            cfPurgeBtn.disabled = true;
            cfPurgeBtn.textContent = 'Purging…';
            setLoading( cfPurgeResult, 'Sending purge request to Cloudflare…' );

            post( 'csdt_devtools_cf_purge', { purge_url: cfPurgeUrl?.value.trim() || '' } ).then( res => {
                cfPurgeBtn.disabled = false;
                cfPurgeBtn.textContent = '🗑️ Purge Cache';
                cfPurgeResult.style.display = 'block';
                cfPurgeResult.innerHTML = res.success
                    ? `<p style="color:#276227;font-weight:600">✔ ${esc( res.data?.message || 'Purged.' )}</p>`
                    : `<p style="color:#8c2020">✘ ${esc( res.data?.message || 'Purge failed.' )}</p>`;
            } ).catch( () => {
                cfPurgeBtn.disabled = false;
                cfPurgeBtn.textContent = '🗑️ Purge Cache';
                cfPurgeResult.innerHTML = '<p style="color:#8c2020">Request failed.</p>';
            } );
        } );
    }

    // ── Platform settings save ────────────────────────────────────────────

    const platformSaveBtn = document.getElementById( 'cs-platform-save-btn' );
    const platformSaved   = document.getElementById( 'cs-platform-saved' );

    // Highlight card on checkbox change.
    document.querySelectorAll( '.cs-platform-cb' ).forEach( cb => {
        cb.addEventListener( 'change', () => {
            const card = cb.closest( '.cs-platform-card' );
            if ( card ) card.classList.toggle( 'cs-platform-checked', cb.checked );
        } );
    } );

    if ( platformSaveBtn ) {
        platformSaveBtn.addEventListener( 'click', () => {
            const selected = Array.from( document.querySelectorAll( '.cs-platform-cb:checked' ) )
                .map( cb => cb.value );
            platformSaveBtn.disabled = true;
            const params = { nonce, action: 'csdt_devtools_social_platform_save' };
            selected.forEach( ( v, i ) => { params[ `platforms[${i}]` ] = v; } );
            const body = new URLSearchParams( params );
            fetch( ajaxUrl, { method: 'POST', body } )
                .then( r => r.json() )
                .then( res => {
                    platformSaveBtn.disabled = false;
                    if ( res.success ) {
                        if ( platformSaved ) {
                            platformSaved.classList.add( 'visible' );
                            setTimeout( () => platformSaved.classList.remove( 'visible' ), 3000 );
                        }
                    } else {
                        alert( res.data?.message || 'Save failed.' );
                    }
                } )
                .catch( () => { platformSaveBtn.disabled = false; alert( 'Request failed.' ); } );
        } );
    }

} )();
