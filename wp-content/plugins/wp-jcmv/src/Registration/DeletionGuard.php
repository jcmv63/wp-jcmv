<?php
/**
 * Garde-fou ADR-001 : jamais de suppression d'un cours ou d'un lieu référencé
 * par des créneaux ou tarifs — y compris en saison archivée. La convention est
 * « dépublier = désactiver », la suppression n'est possible que pour un
 * élément jamais utilisé.
 *
 * Implémenté via les filtres court-circuit pre_trash_post / pre_delete_post :
 * retourner false annule l'opération (WordPress affiche alors un échec).
 *
 * Les produits de la boutique (ADR-005) ne sont pas concernés : rien ne les
 * référence, et leurs données tiennent entièrement en postmeta — donc
 * emportées par WordPress à la suppression.
 *
 * @package wp-jcmv
 */

namespace JCMV\Registration;

use JCMV\Domain\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeletionGuard {

	public static function register(): void {
		add_filter( 'pre_trash_post', array( self::class, 'guard' ), 10, 2 );
		add_filter( 'pre_delete_post', array( self::class, 'guard' ), 10, 2 );
	}

	/**
	 * @param bool|null $check Court-circuit en cours (null = laisser faire).
	 * @param \WP_Post  $post  Post concerné.
	 * @return bool|null False pour bloquer, $check sinon.
	 */
	public static function guard( $check, $post ) {
		if ( null !== $check || ! $post instanceof \WP_Post ) {
			return $check;
		}

		if ( PostTypes::LIEU === $post->post_type && self::is_location_referenced( (int) $post->ID ) ) {
			return false;
		}

		if ( PostTypes::COURS === $post->post_type && self::is_course_referenced( (int) $post->ID ) ) {
			return false;
		}

		return $check;
	}

	private static function is_location_referenced( int $location_id ): bool {
		global $wpdb;
		$schedule = Schema::table( 'schedule' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nom de table interne.
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$schedule} WHERE location_id = %d LIMIT 1", $location_id ) );
	}

	private static function is_course_referenced( int $course_id ): bool {
		global $wpdb;
		$schedule = Schema::table( 'schedule' );
		$pricing  = Schema::table( 'pricing' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- noms de tables internes.
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$schedule} WHERE course_id = %d LIMIT 1", $course_id ) )
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			|| (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$pricing} WHERE course_id = %d LIMIT 1", $course_id ) );
	}
}
