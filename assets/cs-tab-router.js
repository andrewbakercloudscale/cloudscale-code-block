/**
 * cs-tab-router.js — client-side tab switching without full page reloads.
 *
 * Strategy:
 *   - First visit to a tab: fetch the page, inject new scripts/styles, swap content.
 *   - Re-visit to a tab already seen this session: full page reload (fast from cache),
 *     which re-runs all JS init code from scratch.
 *
 * This avoids the need to refactor JS modules for re-initialisation, while still
 * eliminating the reload cost for the most common linear navigation pattern.
 */
( function () {
    'use strict';

    var params      = new URLSearchParams( window.location.search );
    var loadedSrcs  = {};   // external script/style URLs already on page
    var loadedInlineIds = {}; // inline script IDs (wp_localize_script data blocks)
    var styleContents   = {}; // inline style element ID → textContent length

    var visitedTabs = {};
    var currentSlug = params.get( 'tab' ) || 'home';

    // Snapshot scripts already on the page
    document.querySelectorAll( 'script[src]' ).forEach( function ( s ) {
        var src = s.getAttribute( 'src' );
        if ( src ) loadedSrcs[ src ] = true;
    } );

    // Snapshot external stylesheets already on the page
    document.querySelectorAll( 'link[rel="stylesheet"]' ).forEach( function ( l ) {
        var href = l.getAttribute( 'href' );
        if ( href ) loadedSrcs[ href ] = true;
    } );

    // Snapshot inline scripts with IDs (wp_localize_script data blocks)
    document.querySelectorAll( 'script[id]:not([src])' ).forEach( function ( s ) {
        if ( s.id ) loadedInlineIds[ s.id ] = true;
    } );

    // Snapshot inline style elements (wp_add_inline_style blocks)
    document.querySelectorAll( 'style[id]' ).forEach( function ( s ) {
        if ( s.id ) styleContents[ s.id ] = s.textContent.length;
    } );

    visitedTabs[ currentSlug ] = true;

    // ── Utilities ─────────────────────────────────────────────────────────

    function loadScript( src ) {
        if ( loadedSrcs[ src ] ) return Promise.resolve();
        loadedSrcs[ src ] = true;
        return new Promise( function ( resolve ) {
            var el    = document.createElement( 'script' );
            el.src    = src;
            el.onload = el.onerror = resolve;
            document.head.appendChild( el );
        } );
    }

    function execInlineScripts( container ) {
        container.querySelectorAll( 'script:not([src])' ).forEach( function ( old ) {
            var neo         = document.createElement( 'script' );
            neo.textContent = old.textContent;
            old.parentNode.replaceChild( neo, old );
        } );
    }

    /**
     * Inject wp_localize_script inline data blocks and wp_add_inline_style blocks
     * from the fetched document that are new or extended vs what is on the page.
     * Must run BEFORE loading external scripts so variables are defined when scripts run.
     */
    function injectAssetsFromDoc( doc ) {
        // 1. Inline styles (wp_add_inline_style) — update if content grew
        doc.querySelectorAll( 'style[id]' ).forEach( function ( s ) {
            if ( ! s.id ) return;
            var fetchedLen = s.textContent.length;
            var knownLen   = styleContents[ s.id ] || 0;
            if ( fetchedLen > knownLen ) {
                var existing = document.getElementById( s.id );
                if ( existing ) {
                    existing.textContent = s.textContent;
                } else {
                    var neo       = document.createElement( 'style' );
                    neo.id        = s.id;
                    neo.textContent = s.textContent;
                    document.head.appendChild( neo );
                }
                styleContents[ s.id ] = fetchedLen;
            }
        } );

        // 2. Inline scripts with IDs (wp_localize_script data, e.g. csdt-site-audit-js-before)
        doc.querySelectorAll( 'script[id]:not([src])' ).forEach( function ( s ) {
            if ( ! s.id || loadedInlineIds[ s.id ] ) return;
            loadedInlineIds[ s.id ] = true;
            var neo         = document.createElement( 'script' );
            neo.textContent = s.textContent;
            document.head.appendChild( neo ); // executes synchronously
        } );
    }

    function showSpinner() {
        var el = document.getElementById( 'csr-spinner' );
        if ( ! el ) {
            el    = document.createElement( 'div' );
            el.id = 'csr-spinner';
            el.setAttribute( 'aria-live', 'polite' );
            el.style.cssText = 'position:fixed;inset:0;display:flex;align-items:center;justify-content:center;z-index:99999;pointer-events:none;';
            el.innerHTML     = '<div style="background:rgba(255,255,255,.93);border-radius:8px;padding:13px 22px;font-size:13px;color:#374151;box-shadow:0 4px 18px rgba(0,0,0,.14);">&#x23F3; Loading&hellip;</div>';
            document.body.appendChild( el );
        }
        el.style.display = 'flex';
    }

    function hideSpinner() {
        var el = document.getElementById( 'csr-spinner' );
        if ( el ) el.style.display = 'none';
    }

    function slugFromHref( href ) {
        var m = ( href || '' ).match( /[?&]tab=([^&#]+)/ );
        return m ? m[ 1 ] : null;
    }

    function updateTabBar( slug ) {
        document.querySelectorAll( '#cs-tab-bar .cs-tab' ).forEach( function ( a ) {
            a.classList.toggle( 'active', slugFromHref( a.getAttribute( 'href' ) ) === slug );
        } );
    }

    // ── Core switch logic ─────────────────────────────────────────────────

    function switchTab( slug, url ) {
        if ( visitedTabs[ slug ] ) {
            // Already visited — full reload so JS re-inits cleanly from cache.
            window.location.href = url;
            return;
        }

        visitedTabs[ slug ] = true;
        currentSlug         = slug;

        history.pushState( { tab: slug }, '', url );
        updateTabBar( slug );
        showSpinner();

        fetch( url, { credentials: 'same-origin' } )
            .then( function ( r ) {
                if ( ! r.ok ) throw new Error( 'HTTP ' + r.status );
                return r.text();
            } )
            .then( function ( html ) {
                var doc   = new DOMParser().parseFromString( html, 'text/html' );
                var loads = [];

                // Inject wp_localize_script data + wp_add_inline_style BEFORE loading scripts
                injectAssetsFromDoc( doc );

                // Load new external scripts
                doc.querySelectorAll( 'script[src]' ).forEach( function ( s ) {
                    var src = s.getAttribute( 'src' );
                    if ( src && ! loadedSrcs[ src ] ) loads.push( loadScript( src ) );
                } );

                // Load new external stylesheets
                doc.querySelectorAll( 'link[rel="stylesheet"]' ).forEach( function ( l ) {
                    var href = l.getAttribute( 'href' );
                    if ( href && ! loadedSrcs[ href ] ) {
                        loadedSrcs[ href ] = true;
                        var neo  = document.createElement( 'link' );
                        neo.rel  = 'stylesheet';
                        neo.href = href;
                        document.head.appendChild( neo );
                    }
                } );

                return Promise.all( loads ).then( function () { return doc; } );
            } )
            .then( function ( doc ) {
                var newContent = doc.querySelector( '.cs-tab-content' );
                var curContent = document.querySelector( '.cs-tab-content' );

                if ( newContent && curContent ) {
                    curContent.className = newContent.className;
                    curContent.innerHTML = newContent.innerHTML;
                    execInlineScripts( curContent );
                }

                hideSpinner();
                window.scrollTo( 0, 0 );
                document.dispatchEvent( new CustomEvent( 'csdt:tab-shown', { detail: { tab: slug } } ) );
            } )
            .catch( function () {
                // Graceful fallback to full page navigation.
                window.location.href = url;
            } );
    }

    // ── Event wiring ──────────────────────────────────────────────────────

    document.addEventListener( 'click', function ( e ) {
        var link = e.target.closest( '#cs-tab-bar .cs-tab' );
        if ( ! link ) return;
        var slug = slugFromHref( link.getAttribute( 'href' ) );
        if ( ! slug ) return;
        e.preventDefault();
        switchTab( slug, link.getAttribute( 'href' ) );
    } );

    window.addEventListener( 'popstate', function () {
        // Reload on back/forward — simpler than re-injecting stale content.
        window.location.reload();
    } );

} )();
