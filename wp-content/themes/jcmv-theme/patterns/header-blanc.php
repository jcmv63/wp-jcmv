<?php
/**
 * Title: En-tête JCMV (blanc)
 * Slug: jcmv/header-blanc
 * Categories: jcmv, header
 * Block Types: core/template-part/header
 * Inserter: no
 *
 * Variante fond blanc de la charte (§05) : à utiliser quand la navigation
 * est immédiatement suivie d'un bloc à fond anthracite (hero, bandeau titre),
 * pour marquer la limite entre le chrome du site et son contenu.
 * Logo couleur, liens anthracite clair, actif anthracite + soulignement rouge.
 */
?>
<!-- wp:group {"align":"full","className":"jcmv-header jcmv-header--light","backgroundColor":"base","textColor":"contrast","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull jcmv-header jcmv-header--light has-contrast-color has-base-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--50)">

	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
	<div class="wp-block-group">

		<!-- wp:html -->
		<a class="jcmv-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logo.svg' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> — accueil" width="48" height="48">
		</a>
		<!-- /wp:html -->

		<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|60"}}} -->
		<div class="wp-block-group">

			<!-- wp:navigation {"overlayMenu":"mobile","overlayBackgroundColor":"base","overlayTextColor":"contrast","fontFamily":"display","fontSize":"small","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.04em"},"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"flex","justifyContent":"right"}} /-->

			<!-- wp:buttons -->
			<div class="wp-block-buttons">
				<!-- wp:button {"className":"jcmv-header__cta"} -->
				<div class="wp-block-button jcmv-header__cta"><a class="wp-block-button__link wp-element-button" href="/inscription">Je m'inscris</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->

		</div>
		<!-- /wp:group -->

	</div>
	<!-- /wp:group -->

</div>
<!-- /wp:group -->
