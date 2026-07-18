<?php
/**
 * Title: Pied de page JCMV
 * Slug: jcmv/footer
 * Categories: jcmv, footer
 * Block Types: core/template-part/footer
 * Inserter: no
 *
 * Fond anthracite foncé, logo blanc, liens légaux (RGPD : lien permanent
 * « Gérer mes cookies »), copyright. Charte §05 + §11.
 */
?>
<!-- wp:group {"align":"full","className":"jcmv-footer","backgroundColor":"accent-4","textColor":"base","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|50"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull jcmv-footer has-base-color has-accent-4-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50)">

	<!-- wp:html -->
	<a class="jcmv-footer__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
		<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logo-blanc.svg' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> — accueil" width="84" height="84">
	</a>
	<!-- /wp:html -->

	<!-- wp:paragraph {"align":"center","className":"jcmv-footer__title"} -->
	<p class="has-text-align-center jcmv-footer__title">Judo Club des Martres-de-Veyre</p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph {"align":"center","className":"jcmv-footer__links"} -->
	<p class="has-text-align-center jcmv-footer__links"><a href="<?php echo esc_url( home_url( '/mentions-legales/' ) ); ?>">Mentions légales</a> · <a href="<?php echo esc_url( home_url( '/politique-de-confidentialite/' ) ); ?>">Politique de confidentialité</a> · <a href="#gerer-cookies">Gérer mes cookies</a> · <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph {"align":"center","fontSize":"small","className":"jcmv-footer__copyright"} -->
	<p class="has-text-align-center jcmv-footer__copyright has-small-font-size">© <?php echo esc_html( gmdate( 'Y' ) ); ?> Judo Club des Martres-de-Veyre — Association affiliée FFJDA</p>
	<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
