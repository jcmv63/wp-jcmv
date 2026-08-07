/**
 * Bloc « Boutique » — réglages d'éditeur, rendu réel côté serveur.
 *
 * Pas de build : même convention que les autres blocs du plugin, on consomme
 * les globales wp.* directement (voir index.asset.php).
 *
 * Les familles sont lues par l'API REST plutôt que codées en dur : le bureau
 * en créer, et une liste figée ici le lui interdirait de fait.
 */
( function ( blocks, element, blockEditor, components, coreData, data ) {
	var el = element.createElement;

	blocks.registerBlockType( 'jcmv/boutique', {
		edit: function ( props ) {
			var attributes = props.attributes;

			var familles = data.useSelect( function ( select ) {
				return select( coreData.store ).getEntityRecords(
					'taxonomy',
					'jcmv_famille',
					{ per_page: -1, hide_empty: false }
				);
			}, [] );

			var optionsFamilles = [ { label: 'Toutes les familles', value: '' } ].concat(
				( familles || [] ).map( function ( famille ) {
					return { label: famille.name, value: famille.slug };
				} )
			);

			var libelleFamille = optionsFamilles.filter( function ( option ) {
				return option.value === attributes.famille;
			} )[ 0 ];

			var inspector = el(
				blockEditor.InspectorControls,
				null,
				el(
					components.PanelBody,
					{ title: 'Affichage' },
					el( components.SelectControl, {
						label: 'Famille',
						value: attributes.famille,
						options: optionsFamilles,
						help: 'Limite la grille à une famille de produits. « Toutes les familles » affiche le catalogue complet.',
						onChange: function ( value ) {
							props.setAttributes( { famille: value } );
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
								? 'Tous les produits de la famille.'
								: 'Les ' + attributes.limite + ' premiers, dans l’ordre d’affichage.',
						onChange: function ( value ) {
							props.setAttributes( { limite: value } );
						},
					} ),
					el( components.ToggleControl, {
						label: 'Bloc « Détails » dépliable',
						checked: attributes.afficherDetails,
						help: attributes.afficherDetails
							? 'Chaque produit peut être déplié pour montrer sa description, sa couleur et ses tailles disponibles.'
							: 'Seuls la photo, le nom et le prix sont affichés — utile pour un aperçu en page d’accueil.',
						onChange: function ( value ) {
							props.setAttributes( { afficherDetails: value } );
						},
					} )
				)
			);

			var resume =
				'Boutique — ' +
				( attributes.famille
					? 'famille « ' + ( libelleFamille ? libelleFamille.label : attributes.famille ) + ' »'
					: 'toutes les familles' ) +
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
