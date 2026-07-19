/**
 * Bloc « Licence & adhésion » — placeholder d'éditeur, rendu réel côté serveur.
 */
( function ( blocks, element ) {
	blocks.registerBlockType( 'jcmv/frais-fixes', {
		edit: function () {
			return element.createElement(
				'div',
				{
					className: 'components-placeholder',
					style: { padding: '24px', minHeight: '0' },
				},
				'Licence & adhésion — l’encart récapitulatif de la saison active s’affichera ici sur le site.'
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element );
