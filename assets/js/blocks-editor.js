// === Init Review System — Block Editor integration ===
// Vanilla JS, không cần build step. Cả 4 block đều là dynamic block (render
// bằng PHP qua render.php), editor chỉ hiển thị preview qua ServerSideRender.
( function ( wp ) {
    'use strict';

    const el = wp.element.createElement;
    const Fragment = wp.element.Fragment;
    const { __ } = wp.i18n;
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, ToggleControl } = wp.components;
    const ServerSideRender = wp.serverSideRender;

    function toInt( value, fallback ) {
        const n = parseInt( value, 10 );
        return isNaN( n ) ? fallback : n;
    }

    // ID bài đang soạn — dùng cho preview khi block để postId = 0.
    function getCurrentPostId() {
        if ( ! wp.data || ! wp.data.select || ! wp.data.select( 'core/editor' ) ) {
            return 0;
        }
        return wp.data.select( 'core/editor' ).getCurrentPostId() || 0;
    }

    function postIdControl( attributes, setAttributes ) {
        return el( TextControl, {
            label: __( 'Post ID (0 = current post)', 'init-review-system' ),
            type: 'number',
            value: attributes.postId,
            onChange: function ( value ) {
                setAttributes( { postId: toInt( value, 0 ) } );
            },
        } );
    }

    function classControl( attributes, setAttributes ) {
        return el( TextControl, {
            label: __( 'Custom CSS class', 'init-review-system' ),
            value: attributes.htmlClass,
            onChange: function ( value ) {
                setAttributes( { htmlClass: value } );
            },
        } );
    }

    function toggleControl( label, attributes, setAttributes, key ) {
        return el( ToggleControl, {
            label: label,
            checked: !! attributes[ key ],
            onChange: function ( value ) {
                const next = {};
                next[ key ] = value;
                setAttributes( next );
            },
        } );
    }

    function preview( blockName, attributes, blockProps ) {
        const previewAttributes = Object.assign( {}, attributes );
        if ( ! previewAttributes.postId ) {
            previewAttributes.postId = getCurrentPostId();
        }

        return el( 'div', blockProps, el( ServerSideRender, {
            block: blockName,
            attributes: previewAttributes,
        } ) );
    }

    function save() {
        return null;
    }

    registerBlockType( 'init-review-system/review-score', {
        edit: function ( props ) {
            const attributes = props.attributes;
            const setAttributes = props.setAttributes;
            const blockProps = useBlockProps();

            return el( Fragment, {},
                el( InspectorControls, {},
                    el( PanelBody, { title: __( 'Review Score Settings', 'init-review-system' ), initialOpen: true },
                        postIdControl( attributes, setAttributes ),
                        toggleControl( __( 'Show Star Icon', 'init-review-system' ), attributes, setAttributes, 'showIcon' ),
                        toggleControl( __( 'Show "/5" Suffix', 'init-review-system' ), attributes, setAttributes, 'showSub' ),
                        toggleControl( __( 'Show Review Count', 'init-review-system' ), attributes, setAttributes, 'showCount' ),
                        toggleControl( __( 'Hide If No Reviews', 'init-review-system' ), attributes, setAttributes, 'hideIfEmpty' ),
                        classControl( attributes, setAttributes )
                    )
                ),
                preview( 'init-review-system/review-score', attributes, blockProps )
            );
        },
        save: save,
    } );

    registerBlockType( 'init-review-system/review-system', {
        edit: function ( props ) {
            const attributes = props.attributes;
            const setAttributes = props.setAttributes;
            const blockProps = useBlockProps();

            return el( Fragment, {},
                el( InspectorControls, {},
                    el( PanelBody, { title: __( 'Review Widget Settings', 'init-review-system' ), initialOpen: true },
                        postIdControl( attributes, setAttributes ),
                        toggleControl( __( 'Output Schema.org Markup', 'init-review-system' ), attributes, setAttributes, 'showSchema' ),
                        classControl( attributes, setAttributes )
                    )
                ),
                preview( 'init-review-system/review-system', attributes, blockProps )
            );
        },
        save: save,
    } );

    registerBlockType( 'init-review-system/review-criteria', {
        edit: function ( props ) {
            const attributes = props.attributes;
            const setAttributes = props.setAttributes;
            const blockProps = useBlockProps();

            return el( Fragment, {},
                el( InspectorControls, {},
                    el( PanelBody, { title: __( 'Review Criteria Settings', 'init-review-system' ), initialOpen: true },
                        postIdControl( attributes, setAttributes ),
                        el( TextControl, {
                            label: __( 'Reviews Per Page (0 = all)', 'init-review-system' ),
                            type: 'number',
                            value: attributes.perPage,
                            onChange: function ( value ) {
                                setAttributes( { perPage: toInt( value, 0 ) } );
                            },
                        } ),
                        el( TextControl, {
                            label: __( 'Page Number', 'init-review-system' ),
                            type: 'number',
                            value: attributes.paged,
                            onChange: function ( value ) {
                                setAttributes( { paged: toInt( value, 1 ) } );
                            },
                        } ),
                        toggleControl( __( 'Output Schema.org Markup', 'init-review-system' ), attributes, setAttributes, 'showSchema' ),
                        classControl( attributes, setAttributes )
                    )
                ),
                preview( 'init-review-system/review-criteria', attributes, blockProps )
            );
        },
        save: save,
    } );

    registerBlockType( 'init-review-system/reactions', {
        edit: function ( props ) {
            const attributes = props.attributes;
            const setAttributes = props.setAttributes;
            const blockProps = useBlockProps();

            return el( Fragment, {},
                el( InspectorControls, {},
                    el( PanelBody, { title: __( 'Reactions Settings', 'init-review-system' ), initialOpen: true },
                        postIdControl( attributes, setAttributes ),
                        toggleControl( __( 'Load CSS', 'init-review-system' ), attributes, setAttributes, 'loadCss' ),
                        classControl( attributes, setAttributes )
                    )
                ),
                preview( 'init-review-system/reactions', attributes, blockProps )
            );
        },
        save: save,
    } );
} )( window.wp );
