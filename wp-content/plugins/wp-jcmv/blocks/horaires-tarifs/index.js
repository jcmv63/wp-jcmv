/**
 * Bloc « Horaires & tarifs » — placeholder d'éditeur, rendu réel côté serveur.
 */
( function ( blocks, element ) {
	blocks.registerBlockType( 'jcmv/horaires-tarifs', {
		edit: function () {
			return element.createElement(
				'div',
				{
					className: 'components-placeholder',
					style: { padding: '24px', minHeight: '0' },
				},
				'Horaires & tarifs — les cartes de créneaux de la saison active s’afficheront ici sur le site.'
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element );
