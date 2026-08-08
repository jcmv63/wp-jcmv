<?php
/**
 * Transactions SQL, avec gestion de l'imbrication.
 *
 * MySQL ne connaît pas les transactions imbriquées : un second
 * `START TRANSACTION` valide implicitement la première. Chaque repository
 * ouvrant la sienne, il était donc impossible d'écrire créneaux ET tarifs d'un
 * cours dans le même tout-ou-rien — l'app enchaînait deux requêtes HTTP, et un
 * échec sur la seconde laissait la première écrite (revue §1.3).
 *
 * Cette classe tient un compteur de profondeur : seul l'appel le plus externe
 * ouvre, valide ou annule. Les appels internes se contentent de rendre leur
 * résultat, que l'englobante interprète. Un repository garde ainsi son contrat
 * — « ma méthode est atomique » — sans empêcher qu'on l'englobe.
 *
 * Convention : le travail rend `true` (ou toute valeur non-WP_Error) pour
 * valider, un `WP_Error` pour annuler. Une exception annule aussi, puis
 * repropage.
 *
 * Réserve : tout ceci suppose des tables InnoDB. dbDelta les crée avec le
 * moteur par défaut du serveur, InnoDB depuis MySQL 5.5 — mais sur un
 * hébergement qui forcerait MyISAM, les transactions ne feraient rien, sans
 * bruit. Ce n'est pas une régression (le code précédent avait la même
 * hypothèse), c'est une hypothèse à connaître.
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Transaction {

	/**
	 * Profondeur d'imbrication courante.
	 *
	 * Ne compte que les transactions ouvertes par cette classe : un
	 * `START TRANSACTION` lancé à la main ailleurs lui échapperait, d'où la
	 * conversion de tous les repositories du plugin.
	 */
	private static int $depth = 0;

	/**
	 * Exécute un traitement dans une transaction.
	 *
	 * @param callable $work Traitement ; rend WP_Error pour annuler.
	 * @return mixed Ce que rend $work.
	 */
	public static function run( callable $work ) {
		global $wpdb;

		// Déjà dans une transaction : c'est l'englobante qui tranchera.
		if ( self::$depth > 0 ) {
			++self::$depth;

			try {
				return $work();
			} finally {
				--self::$depth;
			}
		}

		$wpdb->query( 'START TRANSACTION' );
		self::$depth = 1;

		try {
			$result = $work();
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			self::$depth = 0;
			throw $e;
		}

		$wpdb->query( is_wp_error( $result ) ? 'ROLLBACK' : 'COMMIT' );
		self::$depth = 0;

		return $result;
	}
}
