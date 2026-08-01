/**
 * Bloc « Partenaires » — réglages d'éditeur, rendu réel côté serveur.
 *
 * Pas de build : même convention que les autres blocs du plugin, on
 * consomme les globales wp.* directement (voir index.asset.php).
 */
( function ( blocks, element, blockEditor, components ) {
	var el = element.createElement;

	blocks.registerBlockType( 'jcmv/partenaires', {
		edit: function ( props ) {
			var variante = props.attributes.variante;
			var isRuban = 'ruban' === variante;

			var inspector = el(
				blockEditor.InspectorControls,
				null,
				el(
					components.PanelBody,
					{ title: 'Affichage' },
					el( components.SelectControl, {
						label: 'Variante',
						value: variante,
						options: [
							{ label: 'Grille (page dédiée)', value: 'grille' },
							{ label: 'Ruban (une ligne)', value: 'ruban' },
						],
						help: isRuban
							? 'Une ligne, nombre de logos limité. Passe sur deux lignes sur mobile.'
							: 'Tous les partenaires publiés ayant un logo.',
						onChange: function ( value ) {
							props.setAttributes( { variante: value } );
						},
					} ),
					isRuban &&
						el( components.RangeControl, {
							label: 'Nombre de logos',
							value: props.attributes.limite,
							min: 2,
							max: 12,
							onChange: function ( value ) {
								props.setAttributes( { limite: value } );
							},
						} )
				)
			);

			var placeholder = el(
				'div',
				{
					className: 'components-placeholder',
					style: { padding: '24px', minHeight: '0' },
				},
				isRuban
					? 'Partenaires — ruban de ' +
							props.attributes.limite +
							' logos (les logos s’afficheront ici sur le site).'
					: 'Partenaires — grille de tous les logos (les logos s’afficheront ici sur le site).'
			);

			return el( element.Fragment, null, inspector, placeholder );
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components );
