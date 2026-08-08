<?php
/**
 * Intégrité référentielle applicative (ADR-001).
 *
 * Schema.php pose la règle : « Pas de clé étrangère SQL vers wp_posts
 * (convention WordPress) : l'intégrité est applicative, dans les repositories. »
 * Cette classe est cette intégrité. Elle était jusqu'ici une intention écrite en
 * commentaire et nulle part ailleurs — un `course_id` arbitraire posté sur
 * l'API REST créait des lignes orphelines, invisibles dans l'admin, jamais
 * purgées par la suppression d'une saison, et que DeletionGuard interprétait au
 * contraire comme des références bloquant la suppression d'un cours.
 *
 * Classe utilitaire sans état, sur le modèle d'AgeCalculator et de Sizes : elle
 * ne fait que répondre « cet identifiant désigne-t-il bien ce qu'il prétend ».
 * Elle vit dans Domain parce que les trois repositories s'en servent, et parce
 * que la règle ne doit pas dépendre de l'interface qui a servi à écrire — REST
 * aujourd'hui, WP-CLI ou un import demain.
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

use JCMV\Registration\PostTypes;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Integrity {

	/**
	 * La saison existe-t-elle ?
	 *
	 * @return true|WP_Error
	 */
	public static function season( int $season_id ) {
		global $wpdb;
		$table = Schema::table( 'season' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nom de table interne.
		$found = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$table} WHERE id = %d LIMIT 1", $season_id ) );

		return $found
			? true
			: new WP_Error( 'jcmv_season_not_found', 'Saison introuvable.' );
	}

	/**
	 * L'identifiant désigne-t-il un cours ?
	 *
	 * @return true|WP_Error
	 */
	public static function course( int $course_id ) {
		return self::post( $course_id, PostTypes::COURS, 'jcmv_course_not_found', 'Cours introuvable.' );
	}

	/**
	 * L'identifiant désigne-t-il un lieu ?
	 *
	 * @return true|WP_Error
	 */
	public static function location( int $location_id ) {
		return self::post( $location_id, PostTypes::LIEU, 'jcmv_location_not_found', 'Lieu introuvable.' );
	}

	/**
	 * Vérifie qu'un ID désigne un post du type attendu, hors corbeille.
	 *
	 * Le statut de publication n'est PAS exigé : le bureau prépare une saison
	 * avec des cours encore en brouillon, c'est le fonctionnement normal
	 * (« dépublier = désactiver », ADR-001). La corbeille, elle, est exclue —
	 * un cours mis au rebut ne doit pas se voir attacher de nouveaux créneaux.
	 *
	 * @param int    $id        Identifiant à vérifier.
	 * @param string $post_type Type attendu.
	 * @param string $code      Code d'erreur WP_Error.
	 * @param string $message   Message destiné au bureau.
	 * @return true|WP_Error
	 */
	private static function post( int $id, string $post_type, string $code, string $message ) {
		if ( $id <= 0 ) {
			return new WP_Error( $code, $message );
		}

		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post || $post_type !== $post->post_type || 'trash' === $post->post_status ) {
			return new WP_Error( $code, $message );
		}

		return true;
	}
}
