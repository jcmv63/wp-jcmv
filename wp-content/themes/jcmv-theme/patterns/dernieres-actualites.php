<?php
/**
 * Title: Dernières actualités
 * Slug: jcmv/dernieres-actualites
 * Categories: jcmv, posts
 * Viewport width: 1080
 * Description: Les 5 derniers articles — le plus récent en grande carte verticale (image, date, titre, extrait), les 4 suivants en cartes horizontales compactes (vignette, date, titre), avec bouton vers la page Actualités.
 */
?>
<!-- wp:group {"className":"jcmv-latest","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group jcmv-latest">

	<!-- wp:heading -->
	<h2 class="wp-block-heading">Dernières actualités</h2>
	<!-- /wp:heading -->

	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|40"}}}} -->
	<div class="wp-block-columns">

		<!-- wp:column {"width":"40%"} -->
		<div class="wp-block-column" style="flex-basis:40%">

			<!-- wp:query {"query":{"perPage":1,"postType":"post","order":"desc","orderBy":"date"}} -->
			<div class="wp-block-query">
				<!-- wp:post-template -->
					<!-- wp:group {"className":"jcmv-card jcmv-card--post","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained"}} -->
					<div class="wp-block-group jcmv-card jcmv-card--post">
						<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"3/2"} /-->
						<!-- wp:group {"className":"jcmv-card__body","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
						<div class="wp-block-group jcmv-card__body" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">
							<!-- wp:post-date {"fontSize":"small","textColor":"gris-texte"} /-->
							<!-- wp:post-title {"level":3,"isLink":true,"fontSize":"x-large"} /-->
							<!-- wp:post-excerpt {"moreText":"Lire la suite","excerptLength":30,"fontSize":"small","textColor":"gris-texte"} /-->
						</div>
						<!-- /wp:group -->
					</div>
					<!-- /wp:group -->
				<!-- /wp:post-template -->
			</div>
			<!-- /wp:query -->

		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"60%"} -->
		<div class="wp-block-column" style="flex-basis:60%">

			<!-- wp:query {"query":{"perPage":8,"offset":1,"postType":"post","order":"desc","orderBy":"date"}} -->
			<div class="wp-block-query">
				<!-- wp:post-template {"layout":{"type":"grid","minimumColumnWidth":"260px"}} -->
					<!-- wp:group {"className":"jcmv-card jcmv-card--mini","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
					<div class="wp-block-group jcmv-card jcmv-card--mini">
						<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"1","width":"96px"} /-->
						<!-- wp:group {"className":"jcmv-card__body","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30","right":"var:preset|spacing|30"},"blockGap":"4px"}},"layout":{"type":"constrained"}} -->
						<div class="wp-block-group jcmv-card__body" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
							<!-- wp:post-date {"fontSize":"small","textColor":"gris-texte"} /-->
							<!-- wp:post-title {"level":3,"isLink":true,"fontSize":"medium"} /-->
						</div>
						<!-- /wp:group -->
					</div>
					<!-- /wp:group -->
				<!-- /wp:post-template -->
			</div>
			<!-- /wp:query -->

		</div>
		<!-- /wp:column -->

	</div>
	<!-- /wp:columns -->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
	<div class="wp-block-buttons">
		<!-- wp:button {"className":"is-style-outline"} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/actualites/">Toutes les actualités</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->

</div>
<!-- /wp:group -->
