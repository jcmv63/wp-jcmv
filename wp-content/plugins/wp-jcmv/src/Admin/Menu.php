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
use JCMV\Registration\PostTypes;
use JCMV\Registration\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Menu {

	public const SLUG = 'jcmv-club';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_pages' ) );
		add_filter( 'parent_file', array( self::class, 'highlight_parent' ) );
	}

	public static function add_pages(): void {
		add_menu_page(
			__( 'JCMV — Gestion du club', 'wp-jcmv' ),
			__( 'JCMV', 'wp-jcmv' ),
			Capabilities::MANAGE,
			self::SLUG,
			array( self::class, 'render_app' ),
			'dashicons-editor-kitchensink',
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

		/*
		 * WordPress n'ajoute les sous-menus de taxonomies que si le CPT a
		 * show_in_menu === true (wp-admin/menu.php). Nos CPT pointent vers
		 * 'jcmv-club', donc les écrans Disciplines / Catégories d'âge existent
		 * mais ne sont liés nulle part : on les rattache à la main.
		 */
		foreach ( self::taxonomy_submenus() as $taxonomy => $label ) {
			$tax_obj = get_taxonomy( $taxonomy );
			if ( ! $tax_obj ) {
				continue;
			}

			add_submenu_page(
				self::SLUG,
				$label,
				$label,
				$tax_obj->cap->manage_terms,
				self::taxonomy_url( $taxonomy )
			);
		}
	}

	/** @return array<string,string> slug de taxonomie => libellé du sous-menu. */
	private static function taxonomy_submenus(): array {
		return array(
			Taxonomies::DISCIPLINE    => __( 'Disciplines', 'wp-jcmv' ),
			Taxonomies::CATEGORIE_AGE => __( 'Catégories d\'âge', 'wp-jcmv' ),
		);
	}

	private static function taxonomy_url( string $taxonomy ): string {
		return 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=' . PostTypes::COURS;
	}

	/**
	 * Garde le menu JCMV ouvert et surligné sur les écrans de taxonomie.
	 */
	public static function highlight_parent( string $parent_file ): string {
		global $current_screen;

		if ( $current_screen instanceof \WP_Screen
			&& 'edit-tags' === $current_screen->base
			&& isset( self::taxonomy_submenus()[ $current_screen->taxonomy ] ) ) {
			return self::SLUG;
		}

		return $parent_file;
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
