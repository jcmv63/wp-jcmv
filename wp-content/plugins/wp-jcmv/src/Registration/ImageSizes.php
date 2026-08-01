<?php
/**
 * Tailles d'image propres au plugin.
 *
 * `jcmv-logo` est volontairement en **soft crop** (4e argument à false) :
 * un logo de partenaire se contient, il ne se rogne jamais. Les deux
 * dimensions bornent la boîte, le ratio d'origine est préservé.
 *
 * 200 px de haut pour servir la boîte la plus grande (72 px en variante
 * grille) sur écran HiDPI ; 600 px de large pour absorber les logos très
 * allongés (noms d'enseigne) dans une carte de ~315 px.
 *
 * Toute augmentation de --jcmv-logo-h dans le CSS du bloc doit être
 * répercutée ici, puis suivie d'une régénération des miniatures.
 *
 * @package wp-jcmv
 */

namespace JCMV\Registration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ImageSizes {

	public const LOGO = 'jcmv-logo';

	public static function register(): void {
		add_action( 'after_setup_theme', array( self::class, 'add' ) );
		add_filter( 'image_size_names_choose', array( self::class, 'name' ) );
	}

	public static function add(): void {
		add_image_size( self::LOGO, 600, 200, false );
	}

	/**
	 * Rend la taille sélectionnable dans la médiathèque (utile au bureau
	 * pour vérifier ce qui sera servi).
	 */
	public static function name( array $sizes ): array {
		$sizes[ self::LOGO ] = __( 'Logo partenaire', 'wp-jcmv' );
		return $sizes;
	}
}
