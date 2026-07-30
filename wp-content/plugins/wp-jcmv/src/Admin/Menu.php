<?php
/**
 * Menu d'administration « JCMV » : point d'entrée unique du module pour le
 * bureau. La page d'accueil du menu monte l'app Saisons (ADR-002, niveau 3) ;
 * si le bundle n'est pas compilé, la marche à suivre est affichée à la place.
 * Les CPT Cours et Lieux s'y rattachent via show_in_menu => 'jcmv-club'.
 *
 * @package wp-jcmv
 */

namespace JCMV\Admin;

use JCMV\Registration\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Menu {

	public const SLUG = 'jcmv-club';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_pages' ) );
	}

	public static function add_pages(): void {
		add_menu_page(
			__( 'JCMV — Gestion du club', 'wp-jcmv' ),
			__( 'JCMV', 'wp-jcmv' ),
			Capabilities::MANAGE,
			self::SLUG,
			array( self::class, 'render_app' ),
			'dashicons-universal-access',
			26
		);

		// Renomme la première entrée (dupliquée du menu parent) en « Saisons ».
		add_submenu_page(
			self::SLUG,
			__( 'Saisons', 'wp-jcmv' ),
			__( 'Saisons', 'wp-jcmv' ),
			Capabilities::MANAGE,
			self::SLUG,
			array( self::class, 'render_app' )
		);
	}

	public static function render_app(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Saisons', 'wp-jcmv' ) . '</h1>';

		if ( ! SaisonsPage::is_built() ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'L\'application n\'est pas compilée. Lancer : cd wp-content/plugins/wp-jcmv/admin-ui && npm install && npm run build', 'wp-jcmv' );
			echo '</p></div></div>';
			return;
		}

		echo '<div id="jcmv-saisons-app"></div></div>';
	}
}
