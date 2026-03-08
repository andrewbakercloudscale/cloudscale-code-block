( function( blocks, element, blockEditor, components, i18n ) {
    'use strict';

    var el               = element.createElement;
    var useBlockProps     = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody         = components.PanelBody;
    var SelectControl     = components.SelectControl;
    var TextControl       = components.TextControl;
    var Fragment          = element.Fragment;
    var createBlock       = blocks.createBlock;
    var __                = i18n.__;

    var languageOptions = [
        { label: 'Auto Detect', value: '' },
        { label: 'Bash / Shell', value: 'bash' },
        { label: 'C', value: 'c' },
        { label: 'C++', value: 'cpp' },
        { label: 'C#', value: 'csharp' },
        { label: 'CSS', value: 'css' },
        { label: 'Dockerfile', value: 'dockerfile' },
        { label: 'Go', value: 'go' },
        { label: 'GraphQL', value: 'graphql' },
        { label: 'HCL / Terraform', value: 'hcl' },
        { label: 'HTML / XML', value: 'xml' },
        { label: 'Java', value: 'java' },
        { label: 'JavaScript', value: 'javascript' },
        { label: 'JSON', value: 'json' },
        { label: 'Kotlin', value: 'kotlin' },
        { label: 'Lua', value: 'lua' },
        { label: 'Makefile', value: 'makefile' },
        { label: 'Markdown', value: 'markdown' },
        { label: 'Perl', value: 'perl' },
        { label: 'PHP', value: 'php' },
        { label: 'Python', value: 'python' },
        { label: 'R', value: 'r' },
        { label: 'Ruby', value: 'ruby' },
        { label: 'Rust', value: 'rust' },
        { label: 'SCSS', value: 'scss' },
        { label: 'SQL', value: 'sql' },
        { label: 'Swift', value: 'swift' },
        { label: 'TOML', value: 'toml' },
        { label: 'TypeScript', value: 'typescript' },
        { label: 'YAML', value: 'yaml' }
    ];

    var themeOptions = [
        { label: 'Default (from settings)', value: '' },
        { label: 'Dark', value: 'dark' },
        { label: 'Light', value: 'light' }
    ];

    function cleanHtml( text ) {
        if ( ! text ) return '';
        text = text.replace( /<br\s*\/?>/gi, '\n' );
        text = text.replace( /<[^>]+>/g, '' );
        var tmp = document.createElement( 'textarea' );
        tmp.innerHTML = text;
        return tmp.value.replace( /\n+$/, '' );
    }

    blocks.registerBlockType( 'cloudscale/code-block', {

        transforms: {
            from: [
                {
                    type: 'block',
                    blocks: [ 'core/code' ],
                    transform: function( attributes ) {
                        return createBlock( 'cloudscale/code-block', {
                            content: attributes.content || '',
                            language: ''
                        } );
                    }
                },
                {
                    type: 'block',
                    blocks: [ 'core/preformatted' ],
                    transform: function( attributes ) {
                        return createBlock( 'cloudscale/code-block', {
                            content: cleanHtml( attributes.content || '' ),
                            language: ''
                        } );
                    }
                },
                {
                    type: 'raw',
                    priority: 1,
                    isMatch: function( node ) {
                        if ( ! node || node.nodeType !== 1 ) return false;
                        var tag = node.nodeName.toUpperCase();
                        return tag === 'PRE' || tag === 'CODE';
                    },
                    transform: function( node ) {
                        var code = '';
                        var lang = '';
                        var codeEl = node.querySelector( 'code' );
                        if ( codeEl ) {
                            code = codeEl.textContent || '';
                            var cls = codeEl.getAttribute( 'class' ) || '';
                            var m = cls.match( /language-([a-zA-Z0-9+#._-]+)/ );
                            if ( m ) lang = m[1];
                            if ( ! lang ) lang = codeEl.getAttribute( 'lang' ) || '';
                        } else {
                            code = cleanHtml( node.innerHTML || '' );
                        }
                        return createBlock( 'cloudscale/code-block', {
                            content: code.replace( /\n+$/, '' ),
                            language: lang
                        } );
                    }
                }
            ]
        },

        edit: function( props ) {
            var attributes = props.attributes;

            var blockProps = useBlockProps( {
                className: 'cs-code-editor-wrapper'
            } );

            function onChangeCode( event ) {
                props.setAttributes( { content: event.target.value } );
            }

            function onKeyDown( event ) {
                if ( event.key === 'Tab' ) {
                    event.preventDefault();
                    var ta    = event.target;
                    var start = ta.selectionStart;
                    var end   = ta.selectionEnd;
                    var value = ta.value;
                    ta.value  = value.substring( 0, start ) + '    ' + value.substring( end );
                    ta.selectionStart = ta.selectionEnd = start + 4;
                    props.setAttributes( { content: ta.value } );
                }
            }

            var langLabel = 'Auto Detect';
            if ( attributes.language ) {
                for ( var i = 0; i < languageOptions.length; i++ ) {
                    if ( languageOptions[i].value === attributes.language ) {
                        langLabel = languageOptions[i].label;
                        break;
                    }
                }
            }

            var copyLabelState = element.useState( 'Copy' );
            var getCopyLabel = copyLabelState[0];
            var setCopyLabel = copyLabelState[1];

            function onCopyCode() {
                var code = attributes.content || '';
                if ( ! code ) return;
                if ( navigator.clipboard && navigator.clipboard.writeText ) {
                    navigator.clipboard.writeText( code ).then( function() {
                        setCopyLabel( 'Copied!' );
                        setTimeout( function() { setCopyLabel( 'Copy' ); }, 1500 );
                    } );
                } else {
                    var ta = document.createElement( 'textarea' );
                    ta.value = code;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild( ta );
                    ta.select();
                    document.execCommand( 'copy' );
                    document.body.removeChild( ta );
                    setCopyLabel( 'Copied!' );
                    setTimeout( function() { setCopyLabel( 'Copy' ); }, 1500 );
                }
            }

            function onClearCode() {
                props.setAttributes( { content: '' } );
            }

            function decodeClipboardText( text ) {
                // Gutenberg sometimes JSON-encodes clipboard text internally,
                // producing unicode escapes like u003c for < and u0022 for ".
                // Attempt to detect and decode that form before setting content.
                if ( typeof text !== 'string' ) return text;
                // If it looks like a JSON string value (wrapped in quotes), parse it
                var trimmed = text.trim();
                if ( trimmed.charAt(0) === '"' && trimmed.charAt( trimmed.length - 1 ) === '"' ) {
                    try { return JSON.parse( trimmed ); } catch(e) {}
                }
                // If it contains bare unicode escapes (u003c style without backslash),
                // reconstruct as a quoted JSON string and parse
                if ( /(?:^|[^\\])u[0-9a-fA-F]{4}/.test( trimmed ) ) {
                    try { return JSON.parse( '"' + trimmed.replace( /"/g, '\\"' ) + '"' ); } catch(e) {}
                }
                return text;
            }

            function onPasteCode() {
                if ( navigator.clipboard && navigator.clipboard.readText ) {
                    navigator.clipboard.readText().then( function( text ) {
                        props.setAttributes( { content: decodeClipboardText( text ) } );
                    } ).catch( function() {} );
                }
            }

            return el( Fragment, {},
                el( InspectorControls, {},
                    el( PanelBody, { title: __( 'Code Settings', 'cs-code-block' ), initialOpen: true },
                        el( SelectControl, {
                            label: __( 'Language', 'cs-code-block' ),
                            help: __( 'Leave on Auto Detect to let highlight.js guess the language.', 'cs-code-block' ),
                            value: attributes.language,
                            options: languageOptions,
                            onChange: function( val ) { props.setAttributes( { language: val } ); }
                        } ),
                        el( TextControl, {
                            label: __( 'Title', 'cs-code-block' ),
                            help: __( 'Optional label shown above the code (e.g. filename).', 'cs-code-block' ),
                            value: attributes.title,
                            onChange: function( val ) { props.setAttributes( { title: val } ); }
                        } ),
                        el( SelectControl, {
                            label: __( 'Dark / Light Mode', 'cs-code-block' ),
                            help: __( 'Override for this block. The color theme is set site wide in Tools > CloudScale Code and SQL.', 'cs-code-block' ),
                            value: attributes.theme,
                            options: themeOptions,
                            onChange: function( val ) { props.setAttributes( { theme: val } ); }
                        } )
                    )
                ),
                el( 'div', blockProps,
                    el( 'div', { className: 'cs-code-editor-toolbar' },
                        el( 'span', { className: 'cs-code-editor-icon' },
                            el( 'svg', { xmlns: 'http://www.w3.org/2000/svg', width: '16', height: '16', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: '2', strokeLinecap: 'round', strokeLinejoin: 'round' },
                                el( 'polyline', { points: '16 18 22 12 16 6' } ),
                                el( 'polyline', { points: '8 6 2 12 8 18' } )
                            )
                        ),
                        el( 'span', { className: 'cs-code-editor-label' }, 'CloudScale Code' ),
                        attributes.language
                            ? el( 'span', { className: 'cs-code-editor-lang' }, langLabel )
                            : el( 'span', { className: 'cs-code-editor-lang cs-code-editor-lang-auto' }, 'Auto Detect' ),
                        attributes.title
                            ? el( 'span', { className: 'cs-code-editor-title' }, attributes.title )
                            : null,
                        el( 'div', { className: 'cs-code-editor-toolbar-actions' },
                            el( 'button', {
                                className: 'cs-code-editor-btn cs-code-editor-btn-copy',
                                type: 'button',
                                title: __( 'Copy code to clipboard', 'cs-code-block' ),
                                onClick: onCopyCode
                            }, getCopyLabel ),
                            el( 'button', {
                                className: 'cs-code-editor-btn cs-code-editor-btn-paste',
                                type: 'button',
                                title: __( 'Paste clipboard content', 'cs-code-block' ),
                                onClick: onPasteCode
                            }, 'Paste' ),
                            el( 'button', {
                                className: 'cs-code-editor-btn cs-code-editor-btn-clear',
                                type: 'button',
                                title: __( 'Clear all code', 'cs-code-block' ),
                                onClick: onClearCode
                            }, 'Clear' )
                        )
                    ),
                    el( 'textarea', {
                        className: 'cs-code-editor-textarea',
                        value: attributes.content,
                        onChange: onChangeCode,
                        onKeyDown: onKeyDown,
                        placeholder: __( 'Paste or type your code here...', 'cs-code-block' ),
                        rows: Math.max( 8, ( attributes.content.split( '\n' ).length || 1 ) + 2 ),
                        spellCheck: false,
                        autoComplete: 'off',
                        autoCorrect: 'off',
                        autoCapitalize: 'off'
                    } )
                )
            );
        },
        save: function() {
            return null;
        }
    } );

} )(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n
);
