<?php
/**
 * Formatage des montants affichés sur le site.
 *
 * Classe utilitaire sans état, sur le modèle d'AgeCalculator, de Sizes et
 * d'Integrity : elle ne connaît ni CPT, ni table, ni requête.
 *
 * Elle remplace trois formateurs divergents — ProductRepository::format_price()
 * et deux closures recopiées dans les blocs frais-fixes et horaires-tarifs. Les
 * deux dernières affichaient systématiquement deux décimales et forçaient les
 * séparateurs français en dur, si bien que le même montant se lisait « 25 € »
 * dans la boutique et « 25,00 € » dans les tarifs. Une règle de format tranchée
 * à un endroit doit valoir partout, sinon elle se rejoue à chaque fichier.
 *
 * La règle :
 *
 * - partie décimale nulle → seule la partie entière est affichée. « 25,00 € »
 *   sur une étiquette de t-shirt ou une grille de tarifs fait comptable, pas
 *   club ;
 * - partie décimale non nulle → deux décimales, sans quoi 24,50 € s'afficherait
 *   « 24,5 € » ou, pire, « 25 € » ;
 * - valeur absente (null, chaîne vide, texte) → 0. Un tarif non renseigné se lit
 *   « 0 € », jamais « € » ni « -1 € » : le gabarit décide ensuite s'il vaut
 *   mieux masquer la ligne, ce que fait déjà le bloc boutique avec sa mention
 *   « Prix sur demande ».
 *
 * Les séparateurs viennent de number_format_i18n() et donc de la locale du
 * site, au lieu d'être écrits en dur comme dans les closures remplacées.
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Money {

	/** Devise affichée. Le club n'encaisse qu'en euros (ADR-005). */
	private const CURRENCY = '€';

	/**
	 * Montant prêt à l'affichage : « 25 € », « 24,50 € », « 0 € ».
	 *
	 * @param mixed $amount Montant ; null, vide ou non numérique valent 0.
	 */
	public static function format( $amount ): string {
		return self::number( $amount ) . ' ' . self::CURRENCY;
	}

	/**
	 * Partie numérique seule, sans devise — pour un tableau, un attribut
	 * `content` de microdonnées, ou une phrase qui porte déjà l'unité.
	 *
	 * @param mixed $amount Montant ; null, vide ou non numérique valent 0.
	 */
	public static function number( $amount ): string {
		$value = self::normalize( $amount );

		/*
		 * Comparaison sur les centimes entiers plutôt que sur des flottants
		 * arrondis : `round( $x, 2 ) === round( $x, 0 )` compare deux flottants
		 * avec ===, ce qui tient pour les valeurs issues de DECIMAL(8,2) mais
		 * pas pour un montant calculé (une somme de frais, par exemple).
		 */
		$cents = (int) round( $value * 100 );

		return number_format_i18n( $value, 0 === $cents % 100 ? 0 : 2 );
	}

	/**
	 * Toute entrée ramenée à un flottant exploitable.
	 *
	 * Les montants arrivent de trois sources aux types différents : colonnes
	 * DECIMAL rendues en chaînes par $wpdb, postmeta pouvant être vide, et
	 * calculs en PHP. Les normaliser ici évite un cast dans chaque gabarit.
	 *
	 * @param mixed $amount Valeur brute.
	 */
	private static function normalize( $amount ): float {
		if ( null === $amount || '' === $amount ) {
			return 0.0;
		}

		// Virgule décimale tolérée, comme PostTypes::sanitize_amount() : une
		// valeur saisie à la main peut transiter sous cette forme.
		if ( is_string( $amount ) ) {
			$amount = str_replace( ',', '.', $amount );
		}

		return is_numeric( $amount ) ? (float) $amount : 0.0;
	}
}
