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
 * `jcmv-produit` fait l'inverse : **hard crop** (ADR-005). Une grille de
 * produits n'est lisible que si toutes les vignettes ont le même gabarit ;
 * un t-shirt photographié au format paysage et un judogi en portrait
 * casseraient l'alignement. Le rognage centré est ici un service rendu au
 * bureau, qui n'a pas à cadrer ses photos au pixel.
 *
 * 600 × 750 (ratio 4:5) : sert une carte de ~300 px en HiDPI.
 *
 * @package wp-jcmv
 */

namespace JCMV\Registration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ImageSizes {

	public const LOGO    = 'jcmv-logo';
	public const PRODUIT = 'jcmv-produit';

	public static function register(): void {
		add_action( 'after_setup_theme', array( self::class, 'add' ) );
		add_filter( 'image_size_names_choose', array( self::class, 'name' ) );
	}

	public static function add(): void {
		add_image_size( self::LOGO, 600, 200, false );
		add_image_size( self::PRODUIT, 600, 750, true );
	}

	/**
	 * Rend la taille sélectionnable dans la médiathèque (utile au bureau
	 * pour vérifier ce qui sera servi).
	 */
	public static function name( array $sizes ): array {
		$sizes[ self::LOGO ]    = __( 'Logo partenaire', 'wp-jcmv' );
		$sizes[ self::PRODUIT ] = __( 'Produit boutique', 'wp-jcmv' );
		return $sizes;
	}
}
