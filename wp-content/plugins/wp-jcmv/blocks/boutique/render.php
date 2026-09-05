<?php
/**
 * Rendu serveur du bloc « Boutique » (ADR-002 : pas de REST public, sortie
 * cacheable ; ADR-005 pour le modèle).
 *
 * Le catalogue est une vitrine : aucun panier, aucune action d'achat, aucun
 * contrôle de formulaire. Les tailles sont une liste, pas une liste
 * déroulante — sans prise de commande, un `<select>` ne déclencherait rien,
 * et son contenu n'est pas indexable. La déroulante viendra avec le
 * formulaire de commande, alimentée par la même meta.
 *
 * @package wp-jcmv
 *
 * @var array    $attributes Attributs du bloc.
 * @var string   $content    Contenu interne (inutilisé).
 * @var WP_Block $block      Instance du bloc.
 */

use JCMV\Domain\Money;
use JCMV\Domain\ProductRepository;
use JCMV\Registration\ImageSizes;

$jcmv_famille   = isset( $attributes['famille'] ) ? sanitize_title( (string) $attributes['famille'] ) : '';
$jcmv_limite    = max( 0, (int) ( $attributes['limite'] ?? 0 ) );
$jcmv_colonnes  = min( 4, max( 2, (int) ( $attributes['colonnes'] ?? 3 ) ) );
$jcmv_details   = ! isset( $attributes['afficherDetails'] ) || (bool) $attributes['afficherDetails'];

$jcmv_repo     = new ProductRepository();
$jcmv_produits = $jcmv_repo->all( $jcmv_famille, $jcmv_limite );

if ( ! $jcmv_produits ) {
	// Message réservé au bureau : un visiteur n'a pas à savoir qu'une grille
	// est vide, il ne verra simplement rien.
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p><em>' . esc_html__( 'Aucun produit publié avec photo pour le moment — JCMV → Produits. (Message visible uniquement par le bureau.)', 'wp-jcmv' ) . '</em></p>';
	}
	return;
}

$jcmv_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'jcmv-shop jcmv-shop--cols-' . $jcmv_colonnes,
	)
);
?>
<ul <?php echo $jcmv_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par get_block_wrapper_attributes(). ?>>
	<?php foreach ( $jcmv_produits as $jcmv_index => $jcmv_produit ) : ?>
		<?php
		/*
		 * Le texte alternatif porte la désignation du produit, jamais « photo
		 * de » ni « image » : les lecteurs d'écran annoncent déjà la nature de
		 * l'élément (hygiène RGAA, même règle que le bloc Partenaires).
		 *
		 * Les deux premières cartes ne sont pas différées : sur une page
		 * dédiée à la boutique, elles sont au-dessus de la ligne de flottaison
		 * et porteraient le LCP.
		 */
		$jcmv_loading = $jcmv_index < 2 ? 'eager' : 'lazy';

		$jcmv_photo = wp_get_attachment_image(
			$jcmv_produit['photo_id'],
			ImageSizes::PRODUIT,
			false,
			array(
				'alt'     => $jcmv_produit['nom'],
				'loading' => $jcmv_loading,
				'class'   => 'jcmv-shop__photo is-active',
			)
		);

		if ( ! $jcmv_photo ) {
			continue;
		}

		$jcmv_galerie = array_values(
			array_filter(
				$jcmv_produit['galerie'],
				static function ( $id ) {
					return wp_attachment_is_image( (int) $id );
				}
			)
		);

		/*
		 * Pile des photos de la carte : l'image mise en avant, puis la galerie.
		 * C'est elle que parcourt la bande de vignettes, là où la boucle
		 * piochait auparavant dans deux sources avec un décalage d'indice à
		 * faire de tête (« rang 0 ou galerie[rang - 1] »).
		 */
		$jcmv_pile         = array_merge( array( (int) $jcmv_produit['photo_id'] ), array_map( 'intval', $jcmv_galerie ) );
		$jcmv_total_photos = count( $jcmv_pile );
		$jcmv_a_du_detail  = $jcmv_details
			&& ( '' !== trim( wp_strip_all_tags( $jcmv_produit['description'] ) )
				|| '' !== $jcmv_produit['couleur']
				|| $jcmv_produit['tailles'] );
		?>
		<li class="jcmv-shop__item">
			<article class="jcmv-shop__card">
				<div class="jcmv-shop__media"<?php echo $jcmv_galerie ? ' data-jcmv-gallery' : ''; ?>>
					<div class="jcmv-shop__frame">
						<?php
						echo $jcmv_photo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() échappe déjà.
						?>
						<?php foreach ( $jcmv_galerie as $jcmv_photo_id ) : ?>
							<?php
							echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() échappe déjà.
								(int) $jcmv_photo_id,
								ImageSizes::PRODUIT,
								false,
								array(
									'alt'     => $jcmv_produit['nom'],
									'loading' => 'lazy',
									'class'   => 'jcmv-shop__photo',
								)
							);
							?>
						<?php endforeach; ?>
					</div>

					<?php
					/*
					 * La bande est affichée même quand le produit n'a qu'une
					 * photo, et c'est une décision, pas un effet de bord.
					 *
					 * Le cadre étant en ratio fixe, toutes les photos font la
					 * même hauteur ; c'est la bande qui, présente ici et absente
					 * là, décalait les titres d'une carte à l'autre et cassait
					 * l'alignement de la grille. En la rendant toujours, on
					 * garde les vignettes collées à la photo qu'elles commandent
					 * — la convention e-commerce — sans payer ce décalage.
					 *
					 * Le prix est une miniature redondante sur les produits à
					 * une seule photo. Il est ramené au minimum juste en
					 * dessous : ce n'est alors pas un bouton.
					 *
					 * Les vignettes ne servent à rien sans JavaScript : le CSS
					 * masque la bande entière tant que view.js n'a pas posé la
					 * classe jcmv-shop--js. La photo principale, elle, reste
					 * visible dans tous les cas — et toutes les cartes sont
					 * alors logées à la même enseigne, donc alignées.
					 */
					?>
					<ul class="jcmv-shop__thumbs"<?php echo 1 === $jcmv_total_photos ? ' aria-hidden="true"' : ''; ?>>
						<?php foreach ( $jcmv_pile as $jcmv_i => $jcmv_photo_id ) : ?>
							<li class="jcmv-shop__thumb-item">
								<?php if ( 1 === $jcmv_total_photos ) : ?>
									<?php
									/*
									 * Une seule photo : la vignette n'est plus
									 * une commande, elle n'a rien à choisir.
									 * Un <span> plutôt qu'un <button> lui retire
									 * son arrêt de tabulation et son
									 * « Photo 1 sur 1, bouton, enfoncé » — répété
									 * sur chaque carte d'une grille, c'était le
									 * vrai coût de cette décision.
									 *
									 * Elle garde le cadre actif : sans lui, elle
									 * se lirait « non sélectionnée » à côté de
									 * voisines qui, elles, le sont.
									 */
									?>
									<span class="jcmv-shop__thumb jcmv-shop__thumb--inerte is-active">
										<?php
										echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() échappe déjà.
											(int) $jcmv_photo_id,
											'thumbnail',
											false,
											array(
												'alt'     => '',
												'loading' => 'lazy',
											)
										);
										?>
									</span>
								<?php else : ?>
									<button type="button"
										class="jcmv-shop__thumb<?php echo 0 === $jcmv_i ? ' is-active' : ''; ?>"
										data-jcmv-photo="<?php echo esc_attr( (string) $jcmv_i ); ?>"
										aria-pressed="<?php echo 0 === $jcmv_i ? 'true' : 'false'; ?>">
										<?php
										echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() échappe déjà.
											(int) $jcmv_photo_id,
											'thumbnail',
											false,
											array(
												'alt'     => '',
												'loading' => 'lazy',
											)
										);
										?>
										<span class="jcmv-sr-only">
											<?php
											printf(
												/* translators: 1 : rang de la photo, 2 : nombre total, 3 : nom du produit. */
												esc_html__( 'Photo %1$d sur %2$d de %3$s', 'wp-jcmv' ),
												(int) $jcmv_i + 1,
												(int) $jcmv_total_photos,
												esc_html( $jcmv_produit['nom'] )
											);
											?>
										</span>
									</button>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div class="jcmv-shop__body">
					<h3 class="jcmv-shop__name"><?php echo esc_html( $jcmv_produit['nom'] ); ?></h3>

					<p class="jcmv-shop__price">
						<?php if ( $jcmv_produit['prix'] > 0 ) : ?>
							<?php echo esc_html( Money::format( $jcmv_produit['prix'] ) ); ?>
						<?php else : ?>
							<span class="jcmv-shop__price-ask"><?php esc_html_e( 'Prix sur demande', 'wp-jcmv' ); ?></span>
						<?php endif; ?>
					</p>

					<?php // Le statut « disponible » n'est pas affiché : c'est l'état attendu, le signaler serait du bruit. ?>
					<?php if ( 'disponible' !== $jcmv_produit['dispo'] ) : ?>
						<p class="jcmv-shop__status jcmv-shop__status--<?php echo esc_attr( $jcmv_produit['dispo'] ); ?>">
							<?php echo esc_html( $jcmv_produit['dispo_label'] ); ?>
						</p>
					<?php endif; ?>

					<?php if ( $jcmv_a_du_detail ) : ?>
						<details class="jcmv-shop__details">
							<summary class="jcmv-shop__summary">
								<?php esc_html_e( 'Détails', 'wp-jcmv' ); ?>
							</summary>

							<?php if ( '' !== trim( wp_strip_all_tags( $jcmv_produit['description'] ) ) ) : ?>
								<div class="jcmv-shop__description">
									<?php
									/*
									 * La description est saisie en éditeur classique
									 * (ADR-005) : du HTML simple, que wpautop suffit à
									 * mettre en paragraphes. On ne passe pas par le
									 * filtre the_content, qui rejouerait tout
									 * l'empilement du thème et des extensions dans une
									 * boucle.
									 */
									echo wp_kses_post( wpautop( $jcmv_produit['description'] ) );
									?>
								</div>
							<?php endif; ?>

							<?php if ( '' !== $jcmv_produit['couleur'] ) : ?>
								<p class="jcmv-shop__couleur">
									<strong><?php esc_html_e( 'Couleur :', 'wp-jcmv' ); ?></strong>
									<?php echo esc_html( $jcmv_produit['couleur'] ); ?>
								</p>
							<?php endif; ?>

							<?php if ( $jcmv_produit['tailles'] ) : ?>
								<?php
								/*
								 * Une liste et non une phrase à virgules : chaque
								 * taille est une entité distincte, et un lecteur
								 * d'écran annonce alors leur nombre. L'ordre est
								 * celui de la saisie.
								 */
								?>
								<p class="jcmv-shop__tailles-titre">
									<strong><?php esc_html_e( 'Tailles disponibles :', 'wp-jcmv' ); ?></strong>
								</p>
								<ul class="jcmv-shop__tailles">
									<?php foreach ( $jcmv_produit['tailles'] as $jcmv_taille ) : ?>
										<li class="jcmv-shop__taille"><?php echo esc_html( $jcmv_taille ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</details>
					<?php endif; ?>
				</div>
			</article>
		</li>
	<?php endforeach; ?>
</ul>

<?php
/*
 * Avertissement réservé au bureau, comme le message de grille vide plus haut :
 * un visiteur n'a pas à savoir qu'un plafond existe, mais la personne qui vient
 * d'ajouter un produit invisible doit comprendre pourquoi.
 */
if ( $jcmv_repo->tronque() && current_user_can( 'edit_posts' ) ) :
	?>
	<p><em>
		<?php
		printf(
			/* translators: %d : nombre maximum de produits affichés par le bloc. */
			esc_html__( 'Le catalogue dépasse %d produits : les suivants ne sont pas affichés. Dépublier les références qui ne sont plus proposées, ou répartir le catalogue sur plusieurs blocs filtrés par famille. (Message visible uniquement par le bureau.)', 'wp-jcmv' ),
			(int) ProductRepository::MAX
		);
		?>
	</em></p>
	<?php
endif;
