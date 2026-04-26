/**
 * cs-tab-router.js — client-side tab switching without full page reloads.
 *
 * Strategy:
 *   - First visit to a tab: fetch the page, extract tab content + load any new scripts.
 *   - Re-visit to a tab already seen this session: full page reload (fast from cache),
 *     which re-runs all JS init code from scratch.
 *
 * This avoids the need to refactor JS modules for re-initialisation, while still
 * eliminating the reload cost for the most common linear navigation pattern.
 */
( function () {
    'use strict';

    var params      = new URLSearchParams( window.location.search );
    var loadedSrcs  = {};
    var visitedTabs = {};
    var currentSlug = params.get( 'tab' ) || 'home';

    // Snapshot scripts already on the page
    document.querySelectorAll( 'script[src]' ).forEach( function ( s ) {
        var src = s.getAttribute( 'src' );
        if ( src ) loadedSrcs[ src ] = true;
    } );
    visitedTabs[ currentSlug ] = true;

    // ── Utilities ─────────────────────────────────────────────────────────

    function loadScript( src ) {
        if ( loadedSrcs[ src ] ) return Promise.resolve();
        loadedSrcs[ src ] = true;
        return new Promise( function ( resolve ) {
            var el   = document.createElement( 'script' );
            el.src   = src;
            el.onload = el.onerror = resolve;
            document.head.appendChild( el );
        } );
    }

    function execInlineScripts( container ) {
        container.querySelectorAll( 'script:not([src])' ).forEach( function ( old ) {
            var neo       = document.createElement( 'script' );
            neo.textContent = old.textContent;
            old.parentNode.replaceChild( neo, old );
        } );
    }

    function showSpinner() {
        var el = document.getElementById( 'csr-spinner' );
        if ( !el ) {
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
                if ( !r.ok ) throw new Error( 'HTTP ' + r.status );
                return r.text();
            } )
            .then( function ( html ) {
                var doc   = new DOMParser().parseFromString( html, 'text/html' );
                var loads = [];

                doc.querySelectorAll( 'script[src]' ).forEach( function ( s ) {
                    var src = s.getAttribute( 'src' );
                    if ( src && !loadedSrcs[ src ] ) loads.push( loadScript( src ) );
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
        if ( !link ) return;
        var slug = slugFromHref( link.getAttribute( 'href' ) );
        if ( !slug ) return;
        e.preventDefault();
        switchTab( slug, link.getAttribute( 'href' ) );
    } );

    window.addEventListener( 'popstate', function () {
        // Reload on back/forward — simpler than re-injecting stale content.
        window.location.reload();
    } );

} )();
