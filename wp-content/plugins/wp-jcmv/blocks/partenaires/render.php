<?php
/**
 * Rendu serveur du bloc « Partenaires » (ADR-002 : pas de REST public,
 * sortie cacheable).
 *
 * Les liens sortants sont sponsorisés : rel="sponsored" (hygiène SEO —
 * un partenariat n'est pas un échange de liens éditorial) + noopener.
 * L'attribut alt porte le nom du partenaire, jamais « logo ».
 *
 * @package wp-jcmv
 *
 * @var array    $attributes Attributs du bloc.
 * @var string   $content    Contenu interne (inutilisé).
 * @var WP_Block $block      Instance du bloc.
 */

use JCMV\Domain\PartnerRepository;
use JCMV\Registration\ImageSizes;

$jcmv_variante = ( isset( $attributes['variante'] ) && 'ruban' === $attributes['variante'] ) ? 'ruban' : 'grille';
$jcmv_limite   = ( 'ruban' === $jcmv_variante ) ? max( 1, (int) ( $attributes['limite'] ?? 6 ) ) : 0;

$jcmv_partners = ( new PartnerRepository() )->all( $jcmv_limite );

if ( ! $jcmv_partners ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p><em>' . esc_html__( 'Aucun partenaire avec logo pour le moment — JCMV → Partenaires. (Message visible uniquement par le bureau.)', 'wp-jcmv' ) . '</em></p>';
	}
	return;
}

$jcmv_wrapper = get_block_wrapper_attributes(
	array( 'class' => 'jcmv-partners jcmv-partners--' . $jcmv_variante )
);
?>
<ul <?php echo $jcmv_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par get_block_wrapper_attributes(). ?>>
	<?php foreach ( $jcmv_partners as $jcmv_partner ) : ?>
		<?php
		// Les deux variantes vivent sous la ligne de flottaison (ruban en pied
		// d'accueil, grille sous le chapeau de la page dédiée) : lazy sans risque
		// pour le LCP. À revoir si le ruban remontait en tête de page.
		$jcmv_logo = wp_get_attachment_image(
			$jcmv_partner['logo_id'],
			ImageSizes::LOGO,
			false,
			array(
				'alt'     => $jcmv_partner['nom'],
				'loading' => 'lazy',
				'class'   => 'jcmv-partners__logo',
			)
		);

		if ( ! $jcmv_logo ) {
			continue;
		}
		?>
		<li class="jcmv-partners__item">
			<?php if ( $jcmv_partner['url'] ) : ?>
				<a class="jcmv-partners__link"
					href="<?php echo esc_url( $jcmv_partner['url'] ); ?>"
					target="_blank"
					rel="sponsored noopener external">
					<?php
					echo $jcmv_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() échappe déjà.
					?>
					<?php // Le nom est déjà porté par l'attribut alt de l'image : ne pas le répéter ici. ?>
					<span class="jcmv-sr-only"><?php esc_html_e( '(nouvel onglet)', 'wp-jcmv' ); ?></span>
				</a>
			<?php else : ?>
				<span class="jcmv-partners__link">
					<?php
					echo $jcmv_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() échappe déjà.
					?>
				</span>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
