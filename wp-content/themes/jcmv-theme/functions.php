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
