<?php
/**
 * Rendu serveur du bloc « Boutique » (ADR-002 : pas de REST public, sortie
 * cacheable ; ADR-005 pour le modèle).
 *
 * Le catalogue est une vitrine : aucun panier, aucun prix transmis, aucune
 * action d'achat. La seule information transactionnelle affichée est le prix,
 * et il est déclaratif.
 *
 * @package wp-jcmv
 *
 * @var array    $attributes Attributs du bloc.
 * @var string   $content    Contenu interne (inutilisé).
 * @var WP_Block $block      Instance du bloc.
 */

use JCMV\Domain\ProductRepository;
use JCMV\Registration\ImageSizes;

$jcmv_categorie = isset( $attributes['categorie'] ) ? sanitize_title( (string) $attributes['categorie'] ) : '';
$jcmv_limite    = max( 0, (int) ( $attributes['limite'] ?? 0 ) );
$jcmv_colonnes  = min( 4, max( 2, (int) ( $attributes['colonnes'] ?? 3 ) ) );
$jcmv_details   = ! isset( $attributes['afficherDetails'] ) || (bool) $attributes['afficherDetails'];

$jcmv_produits = ( new ProductRepository() )->all( $jcmv_categorie, $jcmv_limite );

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

		$jcmv_total_photos = count( $jcmv_galerie ) + 1;
		$jcmv_a_du_detail  = $jcmv_details
			&& ( '' !== trim( wp_strip_all_tags( $jcmv_produit['description'] ) )
				|| '' !== $jcmv_produit['coloris']
				|| $jcmv_produit['grille'] );
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

					<?php if ( $jcmv_galerie ) : ?>
						<?php
						/*
						 * Les vignettes ne servent à rien sans JavaScript : le
						 * CSS les masque tant que view.js n'a pas posé la classe
						 * jcmv-shop--js. La photo principale, elle, reste
						 * visible dans tous les cas.
						 */
						?>
						<ul class="jcmv-shop__thumbs">
							<?php for ( $jcmv_i = 0; $jcmv_i < $jcmv_total_photos; $jcmv_i++ ) : ?>
								<li class="jcmv-shop__thumb-item">
									<button type="button"
										class="jcmv-shop__thumb<?php echo 0 === $jcmv_i ? ' is-active' : ''; ?>"
										data-jcmv-photo="<?php echo esc_attr( (string) $jcmv_i ); ?>"
										aria-pressed="<?php echo 0 === $jcmv_i ? 'true' : 'false'; ?>">
										<?php
										echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() échappe déjà.
											0 === $jcmv_i ? $jcmv_produit['photo_id'] : (int) $jcmv_galerie[ $jcmv_i - 1 ],
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
								</li>
							<?php endfor; ?>
						</ul>
					<?php endif; ?>
				</div>

				<div class="jcmv-shop__body">
					<h3 class="jcmv-shop__name"><?php echo esc_html( $jcmv_produit['nom'] ); ?></h3>

					<p class="jcmv-shop__price">
						<?php if ( $jcmv_produit['prix'] > 0 ) : ?>
							<?php if ( $jcmv_produit['prix_a_partir_de'] ) : ?>
								<span class="jcmv-shop__price-prefix"><?php esc_html_e( 'à partir de', 'wp-jcmv' ); ?></span>
							<?php endif; ?>
							<?php echo esc_html( ProductRepository::format_price( $jcmv_produit['prix'] ) ); ?>
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
									 * do_blocks() puis wpautop() : la description peut
									 * avoir été saisie en blocs (produits récents) ou en
									 * texte brut (import). On ne passe pas par le filtre
									 * the_content, qui rejouerait tout l'empilement de
									 * filtres du thème et des extensions dans une boucle.
									 */
									echo wp_kses_post( wpautop( do_blocks( $jcmv_produit['description'] ) ) );
									?>
								</div>
							<?php endif; ?>

							<?php if ( '' !== $jcmv_produit['coloris'] ) : ?>
								<p class="jcmv-shop__coloris">
									<strong><?php esc_html_e( 'Coloris :', 'wp-jcmv' ); ?></strong>
									<?php echo esc_html( $jcmv_produit['coloris'] ); ?>
								</p>
							<?php endif; ?>

							<?php if ( $jcmv_produit['grille'] ) : ?>
								<table class="jcmv-shop__grid">
									<caption class="jcmv-sr-only">
										<?php
										printf(
											/* translators: %s : nom du produit. */
											esc_html__( 'Tarifs par taille de %s', 'wp-jcmv' ),
											esc_html( $jcmv_produit['nom'] )
										);
										?>
									</caption>
									<thead>
										<tr>
											<th scope="col"><?php esc_html_e( 'Taille', 'wp-jcmv' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Prix', 'wp-jcmv' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $jcmv_produit['grille'] as $jcmv_ligne ) : ?>
											<tr>
												<th scope="row"><?php echo esc_html( $jcmv_ligne['taille'] ); ?></th>
												<td><?php echo esc_html( ProductRepository::format_price( $jcmv_ligne['prix'] ) ); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</details>
					<?php endif; ?>
				</div>
			</article>
		</li>
	<?php endforeach; ?>
</ul>
