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
