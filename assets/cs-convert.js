/**
 * CloudScale Code Block - Auto Convert
 *
 * This script runs independently of the block registration.
 * It watches for core/code and core/preformatted blocks and provides
 * convert buttons (per-block banners + floating toast).
 */
( function() {
    'use strict';

    // Bail if wp.data or wp.blocks aren't available
    if ( ! window.wp || ! window.wp.data || ! window.wp.blocks ) {
        console.warn( '[CloudScale] wp.data or wp.blocks not available' );
        return;
    }

    var wpData   = window.wp.data;
    var wpBlocks = window.wp.blocks;

    var TOAST_ID = 'cs-convert-all-toast';

    // =========================================================================
    //  Helper: clean HTML entities and <br>
    // =========================================================================

    function cleanHtml( text ) {
        if ( ! text ) return '';
        text = text.replace( /<br\s*\/?>/gi, '\n' );
        text = text.replace( /<[^>]+>/g, '' );
        var tmp = document.createElement( 'textarea' );
        tmp.innerHTML = text;
        return tmp.value.replace( /\n+$/, '' );
    }

    // =========================================================================
    //  Helper: find core code blocks recursively
    // =========================================================================

    function findCoreCodeBlocks( blockList ) {
        var found = [];
        for ( var i = 0; i < blockList.length; i++ ) {
            var b = blockList[ i ];
            if ( b.name === 'core/code' || b.name === 'core/preformatted' ) {
                found.push( b );
            }
            if ( b.innerBlocks && b.innerBlocks.length ) {
                found = found.concat( findCoreCodeBlocks( b.innerBlocks ) );
            }
        }
        return found;
    }

    // =========================================================================
    //  Convert a single block by clientId
    // =========================================================================

    function convertBlock( clientId ) {
        var editor = wpData.select( 'core/block-editor' );
        var block  = editor.getBlock( clientId );
        if ( ! block ) return;

        var content = block.attributes.content || '';
        if ( block.name === 'core/preformatted' ) {
            content = cleanHtml( content );
        }

        wpData.dispatch( 'core/block-editor' ).replaceBlock(
            clientId,
            wpBlocks.createBlock( 'cloudscale/code-block', {
                content: content,
                language: ''
            } )
        );
    }

    // =========================================================================
    //  Convert ALL core code blocks
    // =========================================================================

    function convertAll() {
        var editor     = wpData.select( 'core/block-editor' );
        var coreBlocks = findCoreCodeBlocks( editor.getBlocks() );

        for ( var i = 0; i < coreBlocks.length; i++ ) {
            var b       = coreBlocks[ i ];
            var content = b.attributes.content || '';
            if ( b.name === 'core/preformatted' ) {
                content = cleanHtml( content );
            }
            wpData.dispatch( 'core/block-editor' ).replaceBlock(
                b.clientId,
                wpBlocks.createBlock( 'cloudscale/code-block', {
                    content: content,
                    language: ''
                } )
            );
        }
    }

    // Expose globally
    window.__csConvertBlock = convertBlock;
    window.__csConvertAll   = convertAll;

    // =========================================================================
    //  Inject CSS
    // =========================================================================

    var css = document.createElement( 'style' );
    css.textContent = '' +
        '#' + TOAST_ID + ' {' +
            'position: fixed;' +
            'bottom: 24px;' +
            'right: 24px;' +
            'z-index: 999999;' +
            'background: linear-gradient(135deg, #1e3a5f 0%, #0d9488 100%);' +
            'color: #fff;' +
            'padding: 16px 20px;' +
            'border-radius: 10px;' +
            'box-shadow: 0 8px 32px rgba(0,0,0,0.3);' +
            'display: flex;' +
            'align-items: center;' +
            'gap: 16px;' +
            'font-size: 14px;' +
            'font-weight: 500;' +
            'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;' +
            'animation: cs-toast-in 0.3s ease-out;' +
        '}' +
        '#' + TOAST_ID + ' button {' +
            'background: #fff;' +
            'color: #1e3a5f;' +
            'font-weight: 700;' +
            'border-radius: 6px;' +
            'padding: 10px 24px;' +
            'font-size: 14px;' +
            'border: none;' +
            'white-space: nowrap;' +
            'cursor: pointer;' +
            'box-shadow: 0 2px 8px rgba(0,0,0,0.15);' +
            'font-family: inherit;' +
        '}' +
        '#' + TOAST_ID + ' button:hover {' +
            'background: #f0fdf4;' +
        '}' +
        '@keyframes cs-toast-in {' +
            'from { opacity: 0; transform: translateY(20px); }' +
            'to { opacity: 1; transform: translateY(0); }' +
        '}';
    document.head.appendChild( css );

    // =========================================================================
    //  Watch for core code blocks and show/hide toast
    // =========================================================================

    var _timer = null;

    function checkBlocks() {
        var editor = wpData.select( 'core/block-editor' );
        if ( ! editor ) return;

        var allBlocks = editor.getBlocks();
        if ( ! allBlocks ) return;

        var coreBlocks = findCoreCodeBlocks( allBlocks );
        var toast      = document.getElementById( TOAST_ID );

        if ( coreBlocks.length > 0 ) {
            if ( ! toast ) {
                toast    = document.createElement( 'div' );
                toast.id = TOAST_ID;
                document.body.appendChild( toast );
            }
            var s = coreBlocks.length > 1 ? 's' : '';
            toast.innerHTML = '' +
                '<span>\u26A0\uFE0F ' + coreBlocks.length + ' core code block' + s + ' found</span>' +
                '<button onclick="window.__csConvertAll()">\u26A1 Convert All to CloudScale</button>';
        } else {
            if ( toast ) {
                toast.remove();
            }
        }
    }

    wpData.subscribe( function() {
        if ( _timer ) clearTimeout( _timer );
        _timer = setTimeout( checkBlocks, 300 );
    } );

    // Also run once after a short delay to catch initial state
    setTimeout( checkBlocks, 1000 );

    console.log( '[CloudScale] Auto-convert watcher loaded' );

} )();
