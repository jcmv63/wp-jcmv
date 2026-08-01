<?php
/**
 * Title: Pied de page JCMV
 * Slug: jcmv/footer
 * Categories: jcmv, footer
 * Block Types: core/template-part/footer
 * Inserter: no
 *
 * Zone 1 : trois colonnes — identité (logo + nom), deux listes de liens,
 * réseaux sociaux. Zone 2 : copyright.
 */
?>
<!-- wp:group {"align":"full","className":"jcmv-footer","backgroundColor":"accent-4","textColor":"base","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|60","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull jcmv-footer has-base-color has-accent-4-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--50)">

	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|60"}}}} -->
	<div class="wp-block-columns">

		<!-- wp:column -->
		<div class="wp-block-column">

			<!-- wp:html -->
			<a class="jcmv-footer__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logo-blanc.svg' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> — accueil" width="84" height="84">
			</a>
			<!-- /wp:html -->

			<!-- wp:paragraph {"className":"jcmv-footer__title"} -->
			<p class="jcmv-footer__title">Judo Club des Martres-de-Veyre</p>
			<!-- /wp:paragraph -->

		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">

			<!-- wp:group {"className":"jcmv-footer__nav","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"top"},"style":{"spacing":{"blockGap":"var:preset|spacing|70"}}} -->
			<div class="wp-block-group jcmv-footer__nav">

				<!-- wp:group {"layout":{"type":"constrained"},"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
				<div class="wp-block-group">
					<!-- wp:paragraph {"className":"jcmv-footer__title"} -->
					<p class="jcmv-footer__title">Le club</p>
					<!-- /wp:paragraph -->
					<!-- wp:list {"className":"jcmv-footer__list"} -->
					<ul class="wp-block-list jcmv-footer__list">
						<!-- wp:list-item --><li><a href="/le-club">À propos</a></li><!-- /wp:list-item -->
						<!-- wp:list-item --><li><a href="/lequipe">L'équipe</a></li><!-- /wp:list-item -->
						<!-- wp:list-item --><li><a href="/partenaires">Les partenaires</a></li><!-- /wp:list-item -->
					</ul>
					<!-- /wp:list -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"layout":{"type":"constrained"},"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
				<div class="wp-block-group">
					<!-- wp:paragraph {"className":"jcmv-footer__title"} -->
					<p class="jcmv-footer__title">Infos pratiques</p>
					<!-- /wp:paragraph -->
					<!-- wp:list {"className":"jcmv-footer__list"} -->
					<ul class="wp-block-list jcmv-footer__list">
						<!-- wp:list-item --><li><a href="/horaires-tarifs">Horaires &amp; tarifs</a></li><!-- /wp:list-item -->
						<!-- wp:list-item --><li><a href="/contact">Contact</a></li><!-- /wp:list-item -->
            <!-- wp:list-item --><li><a href="/mentions-legales">Mentions légales</a></li><!-- /wp:list-item -->
					</ul>
					<!-- /wp:list -->
				</div>
				<!-- /wp:group -->

			</div>
			<!-- /wp:group -->

		</div>
		<!-- /wp:column -->

		<!-- wp:column {"className":"jcmv-footer__follow"} -->
		<div class="wp-block-column jcmv-footer__follow">

			<!-- wp:paragraph {"className":"jcmv-footer__title"} -->
			<p class="jcmv-footer__title">Suivez-nous</p>
			<!-- /wp:paragraph -->

			<!-- wp:html -->
			<div class="jcmv-footer__social">
				<a href="https://www.facebook.com/judomartresdeveyre" target="_blank" rel="noopener">
					<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logo-fb.png' ) ); ?>" alt="Facebook du club" width="28" height="28">
				</a>
				<a href="https://www.instagram.com/judolesmartresdeveyre" target="_blank" rel="noopener">
					<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logo-ig.png' ) ); ?>" alt="Instagram du club" width="28" height="28">
				</a>
			</div>
			<!-- /wp:html -->

		</div>
		<!-- /wp:column -->

	</div>
	<!-- /wp:columns -->

	<!-- wp:group {"className":"jcmv-footer__bottom","layout":{"type":"constrained"}} -->
	<div class="wp-block-group jcmv-footer__bottom">
		<!-- wp:paragraph {"align":"center","fontSize":"small","className":"jcmv-footer__copyright"} -->
		<p class="has-text-align-center jcmv-footer__copyright has-small-font-size">© <?php echo esc_html( gmdate( 'Y' ) ); ?> Judo Club des Martres-de-Veyre — Club affilié FFJDA</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

</div>
<!-- /wp:group -->
