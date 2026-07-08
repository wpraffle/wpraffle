/**
 * WPRaffle — Gutenberg block registration (no-build).
 *
 * Uses the global wp.* packages loaded by core as dependencies, so no
 * @wordpress/scripts build step is required. Each block is a thin wrapper
 * around a server-side render_callback (registered in PHP); the editor side
 * only renders the inspector control (a raffle picker) and a placeholder.
 */
( function ( wp ) {
    var registerBlockType = wp.blocks.registerBlockType;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var SelectControl = wp.components.SelectControl;
    var ServerSideRender = wp.serverSideRender;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;

    var raffles = ( window.wpraffleBlocks && window.wpraffleBlocks.raffles ) || [];
    var blockDefs = ( window.wpraffleBlocks && window.wpraffleBlocks.blocks ) || {};

    function inspector( props ) {
        var attrs = props.attributes;
        var setAttributes = props.setAttributes;
        return el( InspectorControls, null,
            el( PanelBody, { title: 'Raffle', initialOpen: true },
                el( SelectControl, {
                    label: 'Select Raffle',
                    value: attrs.raffle_id || 0,
                    options: [ { value: 0, label: '— Select —' } ].concat( raffles ),
                    onChange: function ( v ) { setAttributes( { raffle_id: parseInt( v, 10 ) || 0 } ); }
                } )
            )
        );
    }

    function edit( name ) {
        return function ( props ) {
            var blockProps = useBlockProps.save ? useBlockProps.save() : {};
            var attrs = props.attributes;
            var placeholder = el( 'div', Object.assign( { className: 'wpr-block-placeholder', style: { padding: '24px', background: '#f9fafb', border: '1px dashed #d1d5db', borderRadius: '8px', textAlign: 'center', color: '#6b7280' } }, blockProps ),
                attrs.raffle_id
                    ? el( Fragment, null,
                        el( 'div', { style: { fontWeight: 700, color: '#1f2937', marginBottom: '4px' } }, ( blockDefs[ name ] && blockDefs[ name ].title ) || name ),
                        el( 'div', null, 'Raffle #' + attrs.raffle_id )
                    )
                    : 'Select a raffle in the block settings.'
            );
            return el( Fragment, null, inspector( props ), placeholder );
        };
    }

    Object.keys( blockDefs ).forEach( function ( name ) {
        registerBlockType( name, {
            title: blockDefs[ name ].title,
            description: blockDefs[ name ].description,
            category: 'widgets',
            icon: 'tickets-alt',
            attributes: {
                raffle_id: { type: 'number', default: 0 }
            },
            edit: edit( name ),
            // save() returns null because rendering is server-side.
            save: function () { return null; }
        } );
    } );
} )( window.wp );
