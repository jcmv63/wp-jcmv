<?php
/**
 * Tarifs des cours (table wp_jcmv_pricing).
 *
 * Même contrat que ScheduleRepository : écriture par lot cours × saison,
 * remplacement atomique.
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PricingRepository {

	/**
	 * Tous les tarifs d'une saison.
	 *
	 * @return object[]
	 */
	public function for_season( int $season_id ): array {
		global $wpdb;
		$table = Schema::table( 'pricing' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nom de table interne.
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE season_id = %d ORDER BY course_id, sort_order, id",
			$season_id
		) );
	}

	/**
	 * Remplace l'ensemble des tarifs d'un cours pour une saison.
	 *
	 * @param array $rows Lignes [ label, amount, period?, note?, sort_order? ].
	 * @return true|WP_Error
	 */
	public function replace_for_course( int $season_id, int $course_id, array $rows ) {
		global $wpdb;
		$table = Schema::table( 'pricing' );

		$clean = array();
		foreach ( array_values( $rows ) as $i => $row ) {
			$row    = (array) $row;
			$label  = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			$amount = (float) ( $row['amount'] ?? 0 );

			if ( '' === $label ) {
				return new WP_Error( 'jcmv_invalid_pricing', sprintf( 'Tarif %d : libellé manquant.', $i + 1 ) );
			}
			if ( $amount < 0 ) {
				return new WP_Error( 'jcmv_invalid_pricing', sprintf( 'Tarif %d : montant négatif.', $i + 1 ) );
			}

			$clean[] = array(
				'label'      => $label,
				'amount'     => $amount,
				'period'     => sanitize_text_field( (string) ( $row['period'] ?? '' ) ),
				'note'       => sanitize_text_field( (string) ( $row['note'] ?? '' ) ),
				'sort_order' => isset( $row['sort_order'] ) ? absint( $row['sort_order'] ) : $i,
			);
		}

		$now = current_time( 'mysql' );

		$wpdb->query( 'START TRANSACTION' );

		$deleted = $wpdb->delete(
			$table,
			array(
				'season_id' => $season_id,
				'course_id' => $course_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $deleted ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'jcmv_db_error', $wpdb->last_error ?: 'Écriture impossible.' );
		}

		foreach ( $clean as $row ) {
			$ok = $wpdb->insert(
				$table,
				array_merge(
					array(
						'season_id'  => $season_id,
						'course_id'  => $course_id,
						'created_at' => $now,
						'updated_at' => $now,
					),
					$row
				),
				array( '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%d' )
			);

			if ( false === $ok ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'jcmv_db_error', $wpdb->last_error ?: 'Écriture impossible.' );
			}
		}

		$wpdb->query( 'COMMIT' );

		return true;
	}
}
