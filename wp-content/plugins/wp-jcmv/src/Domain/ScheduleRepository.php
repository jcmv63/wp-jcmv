<?php
/**
 * Créneaux horaires (table wp_jcmv_schedule).
 *
 * L'écriture se fait « par lot cours × saison » (ADR-002) : la grille de
 * l'admin envoie l'état complet des créneaux d'un cours pour une saison,
 * replace_for_course() remplace tout atomiquement.
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ScheduleRepository {

	/**
	 * Tous les créneaux d'une saison, triés pour l'affichage.
	 *
	 * @return object[]
	 */
	public function for_season( int $season_id ): array {
		global $wpdb;
		$table = Schema::table( 'schedule' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nom de table interne.
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE season_id = %d ORDER BY course_id, sort_order, weekday, start_time",
			$season_id
		) );
	}

	/**
	 * Remplace l'ensemble des créneaux d'un cours pour une saison.
	 *
	 * @param array $rows Lignes [ location_id, weekday, start_time, end_time, note?, sort_order? ].
	 * @return true|WP_Error
	 */
	public function replace_for_course( int $season_id, int $course_id, array $rows ) {
		$table = Schema::table( 'schedule' );

		/*
		 * Cibles vérifiées avant toute écriture (ADR-001) : sans cela, un
		 * season_id ou un course_id arbitraire produit des lignes que rien
		 * n'affiche, que la suppression de saison ne ramasse pas, et que
		 * DeletionGuard prend pour des références légitimes.
		 */
		foreach ( array( Integrity::season( $season_id ), Integrity::course( $course_id ) ) as $cible ) {
			if ( is_wp_error( $cible ) ) {
				return $cible;
			}
		}

		$clean = array();
		foreach ( array_values( $rows ) as $i => $row ) {
			$validated = $this->validate_row( (array) $row, $i );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}
			$clean[] = $validated;
		}

		$now = current_time( 'mysql' );

		/*
		 * Transaction déléguée : si un appelant en a déjà ouvert une — le
		 * contrôleur qui écrit créneaux ET tarifs d'un cours d'un seul geste —
		 * celle-ci s'y fond au lieu de la valider prématurément.
		 */
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

				foreach ( $clean as $i => $row ) {
					$ok = $wpdb->insert(
						$table,
						array(
							'season_id'   => $season_id,
							'course_id'   => $course_id,
							'location_id' => $row['location_id'],
							'weekday'     => $row['weekday'],
							'start_time'  => $row['start_time'],
							'end_time'    => $row['end_time'],
							'note'        => $row['note'],
							'sort_order'  => $row['sort_order'] ?? $i,
							'created_at'  => $now,
							'updated_at'  => $now,
						),
						array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
					);

					if ( false === $ok ) {
						return new WP_Error( 'jcmv_db_error', $wpdb->last_error ?: 'Écriture impossible.' );
					}
				}

				return true;
			}
		);
	}

	/**
	 * @return array|WP_Error Ligne nettoyée.
	 */
	private function validate_row( array $row, int $index ) {
		$location_id = absint( $row['location_id'] ?? 0 );
		$weekday     = absint( $row['weekday'] ?? 0 );
		$start       = self::sanitize_time( (string) ( $row['start_time'] ?? '' ) );
		$end         = self::sanitize_time( (string) ( $row['end_time'] ?? '' ) );

		if ( $location_id <= 0 ) {
			return new WP_Error( 'jcmv_invalid_schedule', sprintf( 'Créneau %d : lieu manquant.', $index + 1 ) );
		}

		// Un lieu renseigné mais qui n'est pas un jcmv_lieu passait jusqu'ici :
		// le front appelait ensuite get_the_title() dessus et affichait le titre
		// de n'importe quel contenu, page comprise.
		$lieu = Integrity::location( $location_id );
		if ( is_wp_error( $lieu ) ) {
			return new WP_Error(
				'jcmv_invalid_schedule',
				sprintf( 'Créneau %d : %s', $index + 1, $lieu->get_error_message() )
			);
		}

		if ( $weekday < 1 || $weekday > 7 ) {
			return new WP_Error( 'jcmv_invalid_schedule', sprintf( 'Créneau %d : jour invalide (1 = lundi … 7 = dimanche).', $index + 1 ) );
		}
		if ( null === $start || null === $end ) {
			return new WP_Error( 'jcmv_invalid_schedule', sprintf( 'Créneau %d : horaire invalide (format HH:MM).', $index + 1 ) );
		}
		if ( $end <= $start ) {
			return new WP_Error( 'jcmv_invalid_schedule', sprintf( 'Créneau %d : l\'heure de fin doit suivre l\'heure de début.', $index + 1 ) );
		}

		return array(
			'location_id' => $location_id,
			'weekday'     => $weekday,
			'start_time'  => $start,
			'end_time'    => $end,
			'note'        => sanitize_text_field( (string) ( $row['note'] ?? '' ) ),
			'sort_order'  => isset( $row['sort_order'] ) ? absint( $row['sort_order'] ) : null,
		);
	}

	/**
	 * « 17:30 » ou « 17:30:00 » → « 17:30:00 », sinon null.
	 */
	private static function sanitize_time( string $time ): ?string {
		if ( preg_match( '/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', trim( $time ), $m ) ) {
			return $m[1] . ':' . $m[2] . ':00';
		}
		return null;
	}
}
