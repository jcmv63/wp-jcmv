/**
 * Bloc « Abonnement au calendrier » — réglages d'éditeur.
 *
 * Le rendu réel est côté serveur (render.php) et l'interactivité côté front
 * (view.js) : il ne reste ici qu'un titre modifiable et un aperçu statique.
 * Pas de build — mêmes conventions que les autres blocs du plugin, on consomme
 * les globales wp.* directement (voir index.asset.php).
 */
( function ( blocks, element, blockEditor, components ) {
	var el = element.createElement;

	blocks.registerBlockType( 'jcmv/abonnement-calendrier', {
		edit: function ( props ) {
			var titre = props.attributes.titre;

			var inspector = el(
				blockEditor.InspectorControls,
				null,
				el(
					components.PanelBody,
					{ title: 'Affichage' },
					el( components.TextControl, {
						label: 'Titre',
						value: titre,
						help: 'Laisser vide pour masquer le titre (il reste lu par les lecteurs d’écran).',
						onChange: function ( value ) {
							props.setAttributes( { titre: value } );
						},
					} )
				)
			);

			var apercu = el(
				'div',
				{
					className: 'components-placeholder',
					style: { padding: '24px', minHeight: '0' },
				},
				el(
					'strong',
					{ style: { display: 'block', marginBottom: '4px' } },
					titre || 'Abonnement au calendrier'
				),
				'Cases à cocher par catégorie d’âge, bouton « S’abonner » et adresse à copier. Les catégories sont lues en base : elles s’afficheront sur le site.'
			);

			return el( element.Fragment, null, inspector, apercu );
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components );
