/**
 * Init Review System — Block Editor integration.
 *
 * Viết bằng vanilla JS (không JSX, không build step) để deploy trực tiếp lên
 * SVN của WordPress.org mà không cần Node/webpack. Mỗi block dùng
 * ServerSideRender để xem trước, và PHP render.php tương ứng (đăng ký qua
 * "render" trong block.json) để xuất HTML — dùng lại 100% logic shortcode
 * đã có, không lặp lại code.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var ServerSideRender = wp.serverSideRender;

	function toInt( value, fallback ) {
		var parsed = parseInt( value, 10 );
		return isNaN( parsed ) ? fallback : parsed;
	}

	// Best-effort helper: while editing a post, default the preview to the
	// current post so "0 = current post" isn't empty in the editor canvas.
	// Never overwrites the saved attribute — postId stays 0 unless the user
	// explicitly sets it in the sidebar.
	function getEditedPostIdForPreview() {
		if ( ! wp.data || ! wp.data.select || ! wp.data.select( 'core/editor' ) ) {
			return 0;
		}
		var id = wp.data.select( 'core/editor' ).getCurrentPostId();
		return id ? id : 0;
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

	function htmlClassControl( attributes, setAttributes ) {
		return el( TextControl, {
			label: __( 'Custom CSS class', 'init-review-system' ),
			value: attributes.htmlClass,
			onChange: function ( value ) {
				setAttributes( { htmlClass: value } );
			},
		} );
	}

	// ---------------------------------------------------------------------
	// init-review-system/review-score
	// ---------------------------------------------------------------------
	registerBlockType( 'init-review-system/review-score', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			var previewAttributes = Object.assign( {}, attributes );

			if ( ! previewAttributes.postId ) {
				previewAttributes.postId = getEditedPostIdForPreview();
			}

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Review Score Settings', 'init-review-system' ), initialOpen: true },
						postIdControl( attributes, setAttributes ),
						el( ToggleControl, {
							label: __( 'Show Star Icon', 'init-review-system' ),
							checked: !! attributes.showIcon,
							onChange: function ( value ) {
								setAttributes( { showIcon: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show "/5" Suffix', 'init-review-system' ),
							checked: !! attributes.showSub,
							onChange: function ( value ) {
								setAttributes( { showSub: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show Review Count', 'init-review-system' ),
							checked: !! attributes.showCount,
							onChange: function ( value ) {
								setAttributes( { showCount: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Hide If No Reviews', 'init-review-system' ),
							checked: !! attributes.hideIfEmpty,
							onChange: function ( value ) {
								setAttributes( { hideIfEmpty: value } );
							},
						} ),
						htmlClassControl( attributes, setAttributes )
					)
				),
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: 'init-review-system/review-score',
						attributes: previewAttributes,
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );

	// ---------------------------------------------------------------------
	// init-review-system/review-system
	// ---------------------------------------------------------------------
	registerBlockType( 'init-review-system/review-system', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			var previewAttributes = Object.assign( {}, attributes );

			if ( ! previewAttributes.postId ) {
				previewAttributes.postId = getEditedPostIdForPreview();
			}

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Review Widget Settings', 'init-review-system' ), initialOpen: true },
						postIdControl( attributes, setAttributes ),
						el( ToggleControl, {
							label: __( 'Output Schema.org Markup', 'init-review-system' ),
							checked: !! attributes.showSchema,
							onChange: function ( value ) {
								setAttributes( { showSchema: value } );
							},
						} ),
						htmlClassControl( attributes, setAttributes )
					)
				),
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: 'init-review-system/review-system',
						attributes: previewAttributes,
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );

	// ---------------------------------------------------------------------
	// init-review-system/review-criteria
	// ---------------------------------------------------------------------
	registerBlockType( 'init-review-system/review-criteria', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			var previewAttributes = Object.assign( {}, attributes );

			if ( ! previewAttributes.postId ) {
				previewAttributes.postId = getEditedPostIdForPreview();
			}

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Review Criteria Settings', 'init-review-system' ), initialOpen: true },
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
						el( ToggleControl, {
							label: __( 'Output Schema.org Markup', 'init-review-system' ),
							checked: !! attributes.showSchema,
							onChange: function ( value ) {
								setAttributes( { showSchema: value } );
							},
						} ),
						htmlClassControl( attributes, setAttributes )
					)
				),
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: 'init-review-system/review-criteria',
						attributes: previewAttributes,
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );

	// ---------------------------------------------------------------------
	// init-review-system/reactions
	// ---------------------------------------------------------------------
	registerBlockType( 'init-review-system/reactions', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			var previewAttributes = Object.assign( {}, attributes );

			if ( ! previewAttributes.postId ) {
				previewAttributes.postId = getEditedPostIdForPreview();
			}

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Reactions Settings', 'init-review-system' ), initialOpen: true },
						postIdControl( attributes, setAttributes ),
						el( ToggleControl, {
							label: __( 'Load CSS', 'init-review-system' ),
							checked: !! attributes.loadCss,
							onChange: function ( value ) {
								setAttributes( { loadCss: value } );
							},
						} ),
						htmlClassControl( attributes, setAttributes )
					)
				),
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: 'init-review-system/reactions',
						attributes: previewAttributes,
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
