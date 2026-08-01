<?php
/**
 * Title: Pied de page JCMV
 * Slug: jcmv/footer
 * Categories: jcmv, footer
 * Block Types: core/template-part/footer
 * Inserter: no
 *
 * Zone 1 (`jcmv-footer__top`) : deux conteneurs côte à côte — `identity`
 * (logo + nom + coordonnées) et `links` (trois listes). Zone 2
 * (`jcmv-footer__bottom`) : copyright à gauche, réseaux sociaux à droite.
 *
 * La rupture est pilotée par le CONTENU, pas par la fenêtre : les trois
 * conteneurs sont en `flex` + `flex-wrap`, et ce sont les `flex-basis`
 * définis dans components.css qui décident du moment où ça passe à la ligne.
 * Aucune media query n'est nécessaire dans cette zone. Un seuil exprimé en
 * largeur de contenu (« les trois listes exigent 490 px ») reste juste si le
 * pied de page est un jour placé dans un contexte plus étroit, là où une
 * media query devinée sur la fenêtre serait fausse.
 *
 * Corollaire à connaître : le CSS ne sait pas qu'un élément flex a basculé à
 * la ligne. On ne peut donc pas styler différemment l'état « rompu » sans
 * réintroduire une requête — d'où l'alignement à gauche partout.
 *
 * Les `layout` de type `flex` sont ceux du cœur : c'est WordPress qui génère
 * `display: flex`, `flex-wrap` et `gap` depuis `blockGap`. components.css ne
 * fournit que les `flex-basis` des enfants.
 *
 * Le padding horizontal du groupe racine est explicitement à `0`, et ce zéro
 * est nécessaire : WordPress pose `has-global-padding` sur les blocs en
 * disposition « constrained » qui déclarent un padding, quand
 * `useRootPaddingAwareAlignments` est actif. Cette classe réapplique le
 * padding racine, hérité ici de Twenty Twenty-Five (`spacing|50`, soit 24 px
 * sur l'échelle du thème enfant). Sans le zéro explicite, le filet de la
 * barre du bas s'arrêterait à 24 px des bords. Le style en ligne l'emporte
 * sur la classe. Les gouttières sont portées par `__top` et `__bottom`.
 */
?>
<!-- wp:group {"align":"full","className":"jcmv-footer","backgroundColor":"accent-4","textColor":"base","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|60","left":"0","right":"0"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull jcmv-footer has-base-color has-accent-4-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-right:0;padding-bottom:var(--wp--preset--spacing--60);padding-left:0">

	<!-- wp:group {"className":"jcmv-footer__top","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|70","left":"var:preset|spacing|80"}}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"top"}} -->
	<div class="wp-block-group jcmv-footer__top">

		<!-- wp:group {"className":"jcmv-footer__identity","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|50","left":"var:preset|spacing|50"}}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"top"}} -->
		<div class="wp-block-group jcmv-footer__identity">

			<!-- wp:html -->
			<a class="jcmv-footer__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logo-blanc.svg' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> — accueil" width="84" height="84">
			</a>
			<!-- /wp:html -->

			<!-- wp:group {"className":"jcmv-footer__coords","layout":{"type":"constrained"},"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
			<div class="wp-block-group jcmv-footer__coords">
				<!-- wp:paragraph {"className":"jcmv-footer__title"} -->
				<p class="jcmv-footer__title">Judo Club des Martres-de-Veyre</p>
				<!-- /wp:paragraph -->
				<!-- wp:html -->
				<address class="jcmv-footer__contact">
					49 avenue de la Gare<br>
					63730 Les Martres-de-Veyre<br>
					<a href="tel:+33473392429">04 73 39 24 29 (Gymnase)</a><br>
					<a href="tel:+33473392488">04 73 39 24 88 (Centre ville)</a><br>
					<a href="mailto:judo.lesmartresdeveyre@gmail.com">judo.lesmartresdeveyre@gmail.com</a>
				</address>
				<!-- /wp:html -->
			</div>
			<!-- /wp:group -->

		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"jcmv-footer__links","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|70","left":"var:preset|spacing|60"}}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"top"}} -->
		<div class="wp-block-group jcmv-footer__links">

			<!-- wp:group {"layout":{"type":"constrained"},"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
			<div class="wp-block-group">
				<!-- wp:paragraph {"className":"jcmv-footer__title"} -->
				<p class="jcmv-footer__title">Le club</p>
				<!-- /wp:paragraph -->
				<!-- wp:list {"className":"jcmv-footer__list"} -->
				<ul class="wp-block-list jcmv-footer__list" role="list">
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
				<ul class="wp-block-list jcmv-footer__list" role="list">
					<!-- wp:list-item --><li><a href="/horaires-tarifs">Horaires &amp; tarifs</a></li><!-- /wp:list-item -->
					<!-- wp:list-item --><li><a href="/contact">Contact</a></li><!-- /wp:list-item -->
					<!-- wp:list-item --><li><a href="/mentions-legales">Mentions légales</a></li><!-- /wp:list-item -->
				</ul>
				<!-- /wp:list -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"layout":{"type":"constrained"},"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
			<div class="wp-block-group">
				<!-- wp:paragraph {"className":"jcmv-footer__title"} -->
				<p class="jcmv-footer__title">Réseau Judo</p>
				<!-- /wp:paragraph -->
				<!-- wp:list {"className":"jcmv-footer__list"} -->
				<ul class="wp-block-list jcmv-footer__list" role="list">
					<!-- wp:list-item --><li><a href="https://www.ffjudo.com/" target="_blank" rel="noopener">FFJDA<span class="screen-reader-text"> (nouvelle fenêtre)</span></a></li><!-- /wp:list-item -->
					<!-- wp:list-item --><li><a href="https://www.judo-auvergnerhonealpes.fr/" target="_blank" rel="noopener">Ligue AURA<span class="screen-reader-text"> (nouvelle fenêtre)</span></a></li><!-- /wp:list-item -->
					<!-- wp:list-item --><li><a href="https://www.comitejudo63.fr/" target="_blank" rel="noopener">Comité 63<span class="screen-reader-text"> (nouvelle fenêtre)</span></a></li><!-- /wp:list-item -->
				</ul>
				<!-- /wp:list -->
			</div>
			<!-- /wp:group -->

		</div>
		<!-- /wp:group -->

	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"jcmv-footer__bottom","layout":{"type":"constrained"}} -->
	<div class="wp-block-group jcmv-footer__bottom">

	<!-- wp:group {"className":"jcmv-footer__bottom-inner","layout":{"type":"constrained"}} -->
	<div class="wp-block-group jcmv-footer__bottom-inner">

		<!-- wp:paragraph {"fontSize":"small","className":"jcmv-footer__copyright"} -->
		<p class="jcmv-footer__copyright has-small-font-size">© <?php echo esc_html( wp_date( 'Y' ) ); ?> Judo Club des Martres-de-Veyre — Club affilié FFJDA</p>
		<!-- /wp:paragraph -->

		<!-- wp:html -->
		<div class="jcmv-footer__social">
			<a href="https://www.facebook.com/judomartresdeveyre" target="_blank" rel="noopener" aria-label="Facebook du club (nouvelle fenêtre)">
				<svg viewBox="133.333 133.333 666.667 666.667" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path transform="matrix(1.3333333,0,0,-1.3333333,800,466.66667)" d="m 0,0 c 0,138.071 -111.929,250 -250,250 -138.071,0 -250,-111.929 -250,-250 0,-117.245 80.715,-215.622 189.606,-242.638 v 166.242 h -51.552 V 0 h 51.552 v 32.919 c 0,85.092 38.508,124.532 122.048,124.532 15.838,0 43.167,-3.105 54.347,-6.211 V 81.986 c -5.901,0.621 -16.149,0.932 -28.882,0.932 -40.993,0 -56.832,-15.528 -56.832,-55.9 V 0 h 81.659 l -14.028,-76.396 h -67.631 V -248.169 C -95.927,-233.218 0,-127.818 0,0"/></svg>
			</a>
			<a href="https://www.instagram.com/judolesmartresdeveyre" target="_blank" rel="noopener" aria-label="Instagram du club (nouvelle fenêtre)">
				<svg viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path transform="translate(-2.5 -2.5)" d="M295.42,6c-53.2,2.51-89.53,11-121.29,23.48-32.87,12.81-60.73,30-88.45,57.82S40.89,143,28.17,175.92c-12.31,31.83-20.65,68.19-23,121.42S2.3,367.68,2.56,503.46,3.42,656.26,6,709.6c2.54,53.19,11,89.51,23.48,121.28,12.83,32.87,30,60.72,57.83,88.45S143,964.09,176,976.83c31.8,12.29,68.17,20.67,121.39,23s70.35,2.87,206.09,2.61,152.83-.86,206.16-3.39S799.1,988,830.88,975.58c32.87-12.86,60.74-30,88.45-57.84S964.1,862,976.81,829.06c12.32-31.8,20.69-68.17,23-121.35,2.33-53.37,2.88-70.41,2.62-206.17s-.87-152.78-3.4-206.1-11-89.53-23.47-121.32c-12.85-32.87-30-60.7-57.82-88.45S862,40.87,829.07,28.19c-31.82-12.31-68.17-20.7-121.39-23S637.33,2.3,501.54,2.56,348.75,3.4,295.42,6m5.84,903.88c-48.75-2.12-75.22-10.22-92.86-17-23.36-9-40-19.88-57.58-37.29s-28.38-34.11-37.5-57.42c-6.85-17.64-15.1-44.08-17.38-92.83-2.48-52.69-3-68.51-3.29-202s.22-149.29,2.53-202c2.08-48.71,10.23-75.21,17-92.84,9-23.39,19.84-40,37.29-57.57s34.1-28.39,57.43-37.51c17.62-6.88,44.06-15.06,92.79-17.38,52.73-2.5,68.53-3,202-3.29s149.31.21,202.06,2.53c48.71,2.12,75.22,10.19,92.83,17,23.37,9,40,19.81,57.57,37.29s28.4,34.07,37.52,57.45c6.89,17.57,15.07,44,17.37,92.76,2.51,52.73,3.08,68.54,3.32,202s-.23,149.31-2.54,202c-2.13,48.75-10.21,75.23-17,92.89-9,23.35-19.85,40-37.31,57.56s-34.09,28.38-57.43,37.5c-17.6,6.87-44.07,15.07-92.76,17.39-52.73,2.48-68.53,3-202.05,3.29s-149.27-.25-202-2.53m407.6-674.61a60,60,0,1,0,59.88-60.1,60,60,0,0,0-59.88,60.1M245.77,503c.28,141.8,115.44,256.49,257.21,256.22S759.52,643.8,759.25,502,643.79,245.48,502,245.76,245.5,361.22,245.77,503m90.06-.18a166.67,166.67,0,1,1,167,166.34,166.65,166.65,0,0,1-167-166.34"/></svg>
			</a>
		</div>
		<!-- /wp:html -->

	</div>
	<!-- /wp:group -->

	</div>
	<!-- /wp:group -->

</div>
<!-- /wp:group -->
