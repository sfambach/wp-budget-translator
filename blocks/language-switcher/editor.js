( function ( wp ) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	registerBlockType( 'budget-translator/language-switcher', {
		edit: function () {
			var blockProps = useBlockProps( {
				className: 'bt-lang-switcher-block-editor'
			} );

			return el(
				'div',
				blockProps,
				el( ServerSideRender, {
					block: 'budget-translator/language-switcher'
				} ),
				el(
					'p',
					{ className: 'components-placeholder__instructions' },
					__( 'Language switcher (frontend preview).', 'budget-translator' )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
