<?php
/**
 * Title: Ruban partenaires
 * Slug: jcmv/partenaires
 * Categories: jcmv, call-to-action
 * Viewport width: 1280
 * Description: Bandeau gris clair avec les logos des partenaires du club sur une ligne (deux sur mobile) et un lien vers la page dédiée. Le nombre de logos se règle dans les réglages du bloc.
 *
 * Le bloc jcmv/partenaires est fourni par le plugin wp-jcmv : si le plugin
 * est désactivé, l'emplacement affiche un bloc introuvable.
 */
?>
<!-- wp:group {"align":"full","backgroundColor":"accent-5","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|50"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-accent-5-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50)">

	<!-- wp:heading {"textAlign":"center","level":2} -->
	<h2 class="wp-block-heading has-text-align-center">Ils soutiennent le club</h2>
	<!-- /wp:heading -->

	<!-- wp:jcmv/partenaires {"variante":"ruban","limite":6} /-->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
	<div class="wp-block-buttons">
		<!-- wp:button {"className":"is-style-outline"} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/partenaires/">Tous nos partenaires</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->

</div>
<!-- /wp:group -->
