<?php
/**
 * Seed de contenu JCMV — remplit les pages avec les patterns du thème,
 * pour livrer un site sans page vide (contenus à retravailler ensuite
 * dans l'éditeur).
 *
 * Idempotent : une page dont le contenu n'est PAS vide n'est jamais modifiée.
 * Usage : ./scripts/setup-content.sh (exécuté via `wp eval-file`).
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit( "À exécuter via wp eval-file.\n" );
}

$registry = WP_Block_Patterns_Registry::get_instance();

$pat = function ( $slug ) use ( $registry ) {
	$p = $registry->get_registered( $slug );
	if ( ! $p || empty( $p['content'] ) ) {
		WP_CLI::warning( "Pattern introuvable : {$slug}" );
		return '';
	}
	return $p['content'] . "\n";
};

$fill = function ( $path, $content ) {
	$page = get_page_by_path( $path );
	if ( ! $page ) {
		WP_CLI::warning( "Page absente : {$path} — lancer d'abord scripts/setup-structure.sh" );
		return;
	}
	if ( '' !== trim( $page->post_content ) ) {
		WP_CLI::log( "— {$path} : contenu existant, non modifié" );
		return;
	}
	wp_update_post(
		array(
			'ID'           => $page->ID,
			'post_content' => $content,
		)
	);
	WP_CLI::success( "{$path} : contenu injecté" );
};

/* ---------- Accueil : hero + chiffres + témoignage + FAQ + CTA ---------- */

$fill(
	'accueil',
	$pat( 'jcmv/hero' )
	. $pat( 'jcmv/chiffres-cles' )
	. $pat( 'jcmv/dernieres-actualites' )
	. $pat( 'jcmv/temoignage' )
	. $pat( 'jcmv/faq' )
	. $pat( 'jcmv/bandeau-inscription' )
);

/* ---------- Le club : présentation + valeurs + chiffres + témoignage ---------- */

$club = <<<'HTML'
<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">Depuis 50 ans, le Judo Club des Martres-de-Veyre fait vivre le judo dans la commune et ses alentours. Du baby-judo aux compétiteurs, chacun y trouve sa place, encadré par des professeurs diplômés d'État.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Nos valeurs</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Énergie</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Le mouvement, l'effort, l'intensité du combat — des séances vivantes, pour progresser en s'amusant.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Respect</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Le code moral du judo guide chaque cours : politesse, courage, sincérité, honneur, modestie, contrôle de soi, amitié.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Esprit club</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Convivial et accessible, le club réunit les familles autour du tatami, des plus petits aux vétérans.</p>
<!-- /wp:paragraph -->

HTML;

$fill(
	'le-club',
	$club
	. $pat( 'jcmv/chiffres-cles' )
	. $pat( 'jcmv/temoignage' )
);

/* ---------- Pratiquer : disciplines + FAQ + CTA ---------- */

$pratiquer = <<<'HTML'
<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">Trois disciplines sont proposées au club, pour tous les âges et tous les niveaux.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Judo</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Dès 3 ans avec le baby-judo, puis par catégories d'âge jusqu'aux seniors. Loisir ou compétition, chacun avance à son rythme vers la ceinture suivante.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Cross-training</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Renforcement musculaire et cardio en circuit, ouvert à toutes et tous — le complément idéal du judo, ou une pratique à part entière.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Self-défense</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Apprendre à se protéger avec des techniques simples et efficaces, dans un cadre bienveillant.</p>
<!-- /wp:paragraph -->

HTML;

$fill(
	'pratiquer',
	$pratiquer
	. $pat( 'jcmv/faq' )
	. $pat( 'jcmv/bandeau-inscription' )
);

/* ---------- Horaires et tarifs : placeholder module + dojos + CTA ---------- */

$horaires = <<<'HTML'
<!-- wp:paragraph {"className":"is-style-jcmv-alerte-info"} -->
<p class="is-style-jcmv-alerte-info">Les horaires et tarifs de la saison seront affichés ici prochainement. En attendant, contactez-nous pour connaître les créneaux disponibles.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Où pratiquer&nbsp;?</h2>
<!-- /wp:heading -->

HTML;

$fill(
	'horaires-tarifs',
	$horaires
	. $pat( 'jcmv/dojos' )
	. $pat( 'jcmv/bandeau-inscription' )
);

/* ---------- Contact : texte + dojos ---------- */

$contact = <<<'HTML'
<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">Une question sur les cours, les inscriptions ou la vie du club&nbsp;? Venez nous rencontrer directement au dojo aux heures d'entraînement — ou écrivez-nous, un formulaire de contact arrive bientôt.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Nos dojos</h2>
<!-- /wp:heading -->

HTML;

$fill(
	'contact',
	$contact
	. $pat( 'jcmv/dojos' )
);

/* ---------- Premier article de bienvenue ---------- */

if ( ! get_page_by_path( 'bienvenue-sur-le-nouveau-site', OBJECT, 'post' ) ) {
	$welcome = <<<'HTML'
<!-- wp:paragraph -->
<p>Le Judo Club des Martres-de-Veyre fait peau neuve en ligne&nbsp;! Vous retrouverez ici les actualités du club&nbsp;: résultats de compétition, stages, événements et informations pratiques.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Bonne visite, et à bientôt sur le tatami.</p>
<!-- /wp:paragraph -->
HTML;

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Bienvenue sur le nouveau site du club',
			'post_name'    => 'bienvenue-sur-le-nouveau-site',
			'post_content' => $welcome,
		)
	);
	WP_CLI::success( "Article de bienvenue créé (#{$post_id})" );
} else {
	WP_CLI::log( '— article de bienvenue : déjà présent' );
}

WP_CLI::log( 'Seed terminé.' );
