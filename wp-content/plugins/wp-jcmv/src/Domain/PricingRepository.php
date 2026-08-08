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
		$table = Schema::table( 'pricing' );

		// Mêmes gardes que ScheduleRepository, pour la même raison (ADR-001).
		foreach ( array( Integrity::season( $season_id ), Integrity::course( $course_id ) ) as $cible ) {
			if ( is_wp_error( $cible ) ) {
				return $cible;
			}
		}

		$clean = array();
		foreach ( array_values( $rows ) as $i => $row ) {
			$row    = (array) $row;
			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );

			/*
			 * Virgule décimale acceptée, comme PostTypes::sanitize_amount() :
			 * un clavier français produit « 24,50 », qu'un (float) nu tronque
			 * à 24 sans rien signaler. On ne réutilise pas sanitize_amount()
			 * ici, qui ramène les négatifs à zéro : le contrôle ci-dessous doit
			 * pouvoir les refuser explicitement plutôt que les absorber.
			 */
			$amount = (float) str_replace( ',', '.', (string) ( $row['amount'] ?? 0 ) );

			if ( '' === $label ) {
				return new WP_Error( 'jcmv_invalid_pricing', sprintf( 'Tarif %d : libellé manquant.', $i + 1 ) );
			}
			if ( $amount < 0 ) {
				return new WP_Error( 'jcmv_invalid_pricing', sprintf( 'Tarif %d : montant négatif.', $i + 1 ) );
			}
			// DECIMAL(8,2) : au-delà, MySQL rejette la ligne. Un message clair
			// vaut mieux qu'une erreur SQL brute remontée dans l'interface.
			if ( $amount > Schema::AMOUNT_MAX ) {
				return new WP_Error(
					'jcmv_invalid_pricing',
					sprintf( 'Tarif %d : montant supérieur au maximum autorisé (%s).', $i + 1, Money::format( Schema::AMOUNT_MAX ) )
				);
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

		// Même délégation que ScheduleRepository : les deux lots d'un cours
		// doivent pouvoir tenir dans une seule transaction (revue §1.3).
		return Transaction::run(
			static function () use ( $table, $season_id, $course_id, $clean, $now ) {
				global $wpdb;

				$deleted = $wpdb->delete(
					$table,
					array(
						'season_id' => $season_id,
						'course_id' => $course_id,
					),
					array( '%d', '%d' )
				);

				if ( false === $deleted ) {
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
						return new WP_Error( 'jcmv_db_error', $wpdb->last_error ?: 'Écriture impossible.' );
					}
				}

				return true;
			}
		);
	}
}
