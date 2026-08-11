<?php
/**
 * JCMV — thème enfant de Twenty Twenty-Five.
 *
 * @package jcmv-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feuille de composants (front).
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'jcmv-components',
			get_stylesheet_directory_uri() . '/assets/css/components.css',
			array(),
			wp_get_theme()->get( 'Version' )
		);
	}
);

/**
 * Mêmes styles dans l'éditeur de blocs.
 */
add_action(
	'after_setup_theme',
	function () {
		add_editor_style( 'assets/css/components.css' );
	}
);

/**
 * Favicons (set généré via realfavicongenerator.net, servi depuis le thème).
 * Si une icône de site est définie dans les réglages WordPress, elle prime.
 */
add_action(
	'wp_head',
	function () {
		if ( has_site_icon() ) {
			return;
		}
		$base = get_theme_file_uri( 'assets/favicon' );
		printf( '<link rel="icon" type="image/png" href="%s/favicon-96x96.png" sizes="96x96">' . "\n", esc_url( $base ) );
		printf( '<link rel="icon" type="image/svg+xml" href="%s/favicon.svg">' . "\n", esc_url( $base ) );
		printf( '<link rel="shortcut icon" href="%s/favicon.ico">' . "\n", esc_url( $base ) );
		printf( '<link rel="apple-touch-icon" sizes="180x180" href="%s/apple-touch-icon.png">' . "\n", esc_url( $base ) );
		printf( '<link rel="manifest" href="%s/site.webmanifest">' . "\n", esc_url( $base ) );
	}
);

/**
 * Styles de bloc : alertes de la charte (§05) sur le bloc Paragraphe.
 * Jamais de couleur seule : le message doit rester explicite.
 */
add_action(
	'init',
	function () {
		$alertes = array(
			'jcmv-alerte-succes'    => __( 'Alerte — succès', 'jcmv-theme' ),
			'jcmv-alerte-erreur'    => __( 'Alerte — erreur', 'jcmv-theme' ),
			'jcmv-alerte-info'      => __( 'Alerte — information', 'jcmv-theme' ),
			'jcmv-alerte-attention' => __( 'Alerte — attention', 'jcmv-theme' ),
		);
		foreach ( $alertes as $name => $label ) {
			register_block_style(
				'core/paragraph',
				array(
					'name'  => $name,
					'label' => $label,
				)
			);
		}
	}
);

/**
 * Mises à jour du thème depuis le manifeste GitHub (branche `updates` du
 * monorepo, publié par le workflow release-theme).
 *
 * Deux durées de cache, et c'est le point important. La version initiale
 * mémorisait l'échec aussi longtemps que le succès : un 404 — le cas normal
 * tant que la branche `updates` ne contenait pas encore `theme.json` — était
 * gravé pour six heures, et aucune release publiée entre-temps n'était vue.
 * Constaté en production le 2026-08-02 : deux releases invisibles.
 *
 * Un échec est par nature transitoire (réseau, GitHub indisponible, manifeste
 * pas encore publié), il ne se met donc en cache que brièvement. Un succès est
 * stable et supporte une durée longue.
 */
define( 'JCMV_THEME_UPDATE_MANIFEST', 'https://raw.githubusercontent.com/jcmv63/wp-jcmv/updates/theme.json' );
define( 'JCMV_THEME_UPDATE_TTL_OK', 6 * HOUR_IN_SECONDS );
define( 'JCMV_THEME_UPDATE_TTL_KO', 15 * MINUTE_IN_SECONDS );

add_filter(
	'pre_set_site_transient_update_themes',
	function ( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$manifest = get_transient( 'jcmv_theme_update_manifest' );

		if ( ! is_array( $manifest ) ) {
			$manifest = array();
			$response = wp_remote_get( JCMV_THEME_UPDATE_MANIFEST, array( 'timeout' => 10 ) );

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( is_array( $decoded ) ) {
					$manifest = $decoded;
				}
			}

			// Un manifeste sans `version` n'est pas exploitable : on le traite
			// comme un échec, quel que soit le code HTTP reçu.
			$reussite = ! empty( $manifest['version'] );

			set_transient(
				'jcmv_theme_update_manifest',
				$manifest,
				$reussite ? JCMV_THEME_UPDATE_TTL_OK : JCMV_THEME_UPDATE_TTL_KO
			);
		}

		$current = wp_get_theme( 'jcmv-theme' )->get( 'Version' );

		if ( ! empty( $manifest['version'] )
			&& ! empty( $manifest['download_url'] )
			&& version_compare( $manifest['version'], $current, '>' ) ) {
			$transient->response['jcmv-theme'] = array(
				'theme'       => 'jcmv-theme',
				'new_version' => $manifest['version'],
				'package'     => $manifest['download_url'],
				'url'         => $manifest['details_url'] ?? '',
			);
		}

		return $transient;
	}
);

/**
 * Onglet « Événements » actif sur les pages de The Events Calendar.
 *
 * `core/navigation-link` ne marque un item comme actif que si son identifiant
 * de contenu égale `get_queried_object_id()`. Sur une archive de CPT, l'objet
 * interrogé est un `WP_Post_Type` et cet identifiant vaut 0 : aucun item ne peut
 * correspondre. Le soulignement rouge de la charte (§05) disparaît donc sur
 * toute la section calendrier — archive, fiche événement, catégories — alors
 * qu'il s'affiche partout ailleurs. Un lien personnalisé, sans identifiant du
 * tout, échouerait de la même façon.
 *
 * On repose donc les deux marqueurs que le cœur aurait posés : `current-menu-item`
 * sur le `<li>` et `aria-current="page"` sur le lien. La classe seule suffirait
 * au CSS, mais laisserait les lecteurs d'écran sans l'information : c'est le
 * même correctif, autant le faire entier.
 *
 * Le rapprochement se fait sur le chemin d'URL, pas sur l'URL entière : le lien
 * du menu et celui rendu par TEC peuvent différer de schéma, de sous-domaine ou
 * de slash final sans désigner autre chose.
 */
add_filter(
	'render_block',
	function ( $block_content, $block ) {
		if ( 'core/navigation-link' !== ( $block['blockName'] ?? '' ) || empty( $block['attrs']['url'] ) ) {
			return $block_content;
		}

		// Calculé une fois par requête : `false` hors section calendrier.
		static $chemin_cible = null;

		if ( null === $chemin_cible ) {
			$dans_section = is_post_type_archive( 'tribe_events' )
				|| is_singular( 'tribe_events' )
				|| is_tax( 'tribe_events_cat' );

			$url_archive = '';
			if ( $dans_section ) {
				$url_archive = function_exists( 'tribe_get_events_link' )
					? tribe_get_events_link()
					: (string) get_post_type_archive_link( 'tribe_events' );
			}

			$chemin_cible = $url_archive ? jcmv_chemin_url( $url_archive ) : false;
		}

		if ( ! $chemin_cible || jcmv_chemin_url( $block['attrs']['url'] ) !== $chemin_cible ) {
			return $block_content;
		}

		$balises = new WP_HTML_Tag_Processor( $block_content );

		if ( $balises->next_tag( 'LI' ) ) {
			$balises->add_class( 'current-menu-item' );
		}
		if ( $balises->next_tag( 'A' ) ) {
			$balises->set_attribute( 'aria-current', 'page' );
		}

		return $balises->get_updated_html();
	},
	10,
	2
);

/**
 * Chemin normalisé d'une URL, pour comparaison : sans schéma, sans hôte, sans
 * requête ni slash final. Renvoie '' si l'URL n'a pas de chemin exploitable.
 *
 * @param string $url URL absolue ou relative.
 * @return string
 */
function jcmv_chemin_url( $url ) {
	$chemin = (string) wp_parse_url( $url, PHP_URL_PATH );

	return '' === $chemin ? '' : untrailingslashit( $chemin );
}

/**
 * Catégorie de compositions (patterns) du club.
 */
add_action(
	'init',
	function () {
		register_block_pattern_category(
			'jcmv',
			array( 'label' => __( 'JCMV', 'jcmv-theme' ) )
		);
	}
);
