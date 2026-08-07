/**
 * Bloc « Boutique » — réglages d'éditeur, rendu réel côté serveur.
 *
 * Pas de build : même convention que les autres blocs du plugin, on consomme
 * les globales wp.* directement (voir index.asset.php).
 *
 * Les rayons sont lus par l'API REST plutôt que codés en dur : le bureau peut
 * en créer, et une liste figée ici le lui interdirait de fait.
 */
( function ( blocks, element, blockEditor, components, coreData, data ) {
	var el = element.createElement;

	blocks.registerBlockType( 'jcmv/boutique', {
		edit: function ( props ) {
			var attributes = props.attributes;

			var rayons = data.useSelect( function ( select ) {
				return select( coreData.store ).getEntityRecords(
					'taxonomy',
					'jcmv_categorie_produit',
					{ per_page: -1, hide_empty: false }
				);
			}, [] );

			var optionsRayons = [ { label: 'Tous les rayons', value: '' } ].concat(
				( rayons || [] ).map( function ( rayon ) {
					return { label: rayon.name, value: rayon.slug };
				} )
			);

			var libelleRayon = optionsRayons.filter( function ( option ) {
				return option.value === attributes.categorie;
			} )[ 0 ];

			var inspector = el(
				blockEditor.InspectorControls,
				null,
				el(
					components.PanelBody,
					{ title: 'Affichage' },
					el( components.SelectControl, {
						label: 'Rayon',
						value: attributes.categorie,
						options: optionsRayons,
						help: 'Limite la grille à un rayon. « Tous les rayons » affiche le catalogue complet.',
						onChange: function ( value ) {
							props.setAttributes( { categorie: value } );
						},
					} ),
					el( components.RangeControl, {
						label: 'Colonnes',
						value: attributes.colonnes,
						min: 2,
						max: 4,
						help: 'Sur mobile, la grille repasse à une colonne quel que soit ce réglage.',
						onChange: function ( value ) {
							props.setAttributes( { colonnes: value } );
						},
					} ),
					el( components.RangeControl, {
						label: 'Nombre de produits',
						value: attributes.limite,
						min: 0,
						max: 24,
						help:
							0 === attributes.limite
								? 'Tous les produits du rayon.'
								: 'Les ' + attributes.limite + ' premiers, dans l’ordre d’affichage.',
						onChange: function ( value ) {
							props.setAttributes( { limite: value } );
						},
					} ),
					el( components.ToggleControl, {
						label: 'Bloc « Détails » dépliable',
						checked: attributes.afficherDetails,
						help: attributes.afficherDetails
							? 'Chaque produit peut être déplié pour montrer sa description, ses coloris et ses tarifs par taille.'
							: 'Seuls la photo, le nom et le prix sont affichés — utile pour un aperçu en page d’accueil.',
						onChange: function ( value ) {
							props.setAttributes( { afficherDetails: value } );
						},
					} )
				)
			);

			var resume =
				'Boutique — ' +
				( attributes.categorie
					? 'rayon « ' + ( libelleRayon ? libelleRayon.label : attributes.categorie ) + ' »'
					: 'tous les rayons' ) +
				', ' +
				attributes.colonnes +
				' colonnes' +
				( attributes.limite ? ', ' + attributes.limite + ' produits' : '' ) +
				'. Les produits s’afficheront ici sur le site.';

			var placeholder = el(
				'div',
				{
					className: 'components-placeholder',
					style: { padding: '24px', minHeight: '0' },
				},
				resume
			);

			return el( element.Fragment, null, inspector, placeholder );
		},
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.coreData,
	window.wp.data
);
