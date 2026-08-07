<?php
/**
 * Blocs front dynamiques (ADR-002) : rendu serveur via render.php,
 * données lues directement dans les repositories (aucun REST public).
 * Le CSS des blocs consomme les tokens du thème (guide §8).
 *
 * @package wp-jcmv
 */

namespace JCMV\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Blocks {

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_types' ) );
		add_filter( 'block_categories_all', array( self::class, 'category' ) );
	}

	public static function register_types(): void {
		register_block_type( JCMV_PLUGIN_DIR . 'blocks/horaires-tarifs' );
		register_block_type( JCMV_PLUGIN_DIR . 'blocks/frais-fixes' );
		register_block_type( JCMV_PLUGIN_DIR . 'blocks/partenaires' );
		register_block_type( JCMV_PLUGIN_DIR . 'blocks/abonnement-calendrier' );
		register_block_type( JCMV_PLUGIN_DIR . 'blocks/boutique' );
	}

	/**
	 * Catégorie « JCMV » dans l'inséreur de blocs.
	 */
	public static function category( array $categories ): array {
		$categories[] = array(
			'slug'  => 'jcmv',
			'title' => 'JCMV',
		);
		return $categories;
	}
}
