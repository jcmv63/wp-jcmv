<?php
/**
 * Saisons sportives (septembre → août), table wp_jcmv_season.
 *
 * Règles métier (ADR-001) :
 * - Cycle de vie draft → active → archived.
 * - Une seule saison active : l'index unique partiel n'existant pas en MySQL,
 *   activate() garantit la contrainte par transaction applicative.
 * - « Préparer la saison suivante » duplique créneaux et tarifs de la saison
 *   source vers une nouvelle saison brouillon (start_year + 1).
 * - Seule une saison brouillon peut être supprimée (avec ses créneaux/tarifs).
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SeasonRepository {

	/**
	 * Toutes les saisons, la plus récente d'abord.
	 *
	 * @return object[]
	 */
	public function all(): array {
		global $wpdb;
		$table = Schema::table( 'season' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nom de table interne.
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY start_year DESC" );
	}

	public function find( int $id ): ?object {
		global $wpdb;
		$table = Schema::table( 'season' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		return $row ?: null;
	}

	public function find_by_year( int $start_year ): ?object {
		global $wpdb;
		$table = Schema::table( 'season' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE start_year = %d", $start_year ) );

		return $row ?: null;
	}

	/**
	 * La saison active (affichage public), ou null.
	 */
	public function active(): ?object {
		global $wpdb;
		$table = Schema::table( 'season' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( "SELECT * FROM {$table} WHERE status = 'active' LIMIT 1" );

		return $row ?: null;
	}

	/**
	 * Crée une saison brouillon.
	 *
	 * @return int|WP_Error ID créé, ou erreur (année déjà existante).
	 */
	public function create( int $start_year, float $licence = 0.0, float $adhesion = 0.0, string $licence_note = '', string $adhesion_note = '' ) {
		global $wpdb;

		if ( $this->find_by_year( $start_year ) ) {
			return new WP_Error( 'jcmv_season_exists', sprintf( 'La saison %d-%d existe déjà.', $start_year, $start_year + 1 ) );
		}

		$now = current_time( 'mysql' );
		$ok  = $wpdb->insert(
			Schema::table( 'season' ),
			array(
				'start_year'      => $start_year,
				'status'          => 'draft',
				'licence_amount'  => $licence,
				'adhesion_amount' => $adhesion,
				'licence_note'    => $licence_note,
				'adhesion_note'   => $adhesion_note,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'jcmv_db_error', $wpdb->last_error ?: 'Insertion impossible.' );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Met à jour les frais fixes (licence FFJDA, adhésion club) et leurs mentions.
	 *
	 * @return true|WP_Error
	 */
	public function update_fees( int $id, float $licence, float $adhesion, string $licence_note = '', string $adhesion_note = '' ) {
		global $wpdb;

		if ( ! $this->find( $id ) ) {
			return new WP_Error( 'jcmv_season_not_found', 'Saison introuvable.' );
		}

		$wpdb->update(
			Schema::table( 'season' ),
			array(
				'licence_amount'  => $licence,
				'adhesion_amount' => $adhesion,
				'licence_note'    => $licence_note,
				'adhesion_note'   => $adhesion_note,
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%f', '%f', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Active une saison : archive l'actuelle et active la cible, atomiquement.
	 *
	 * @return true|WP_Error
	 */
	public function activate( int $id ) {
		global $wpdb;
		$table = Schema::table( 'season' );

		$season = $this->find( $id );
		if ( ! $season ) {
			return new WP_Error( 'jcmv_season_not_found', 'Saison introuvable.' );
		}
		if ( 'active' === $season->status ) {
			return true;
		}

		$now = current_time( 'mysql' );

		$wpdb->query( 'START TRANSACTION' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$archived = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'archived', updated_at = %s WHERE status = 'active' AND id != %d", $now, $id ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$activated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'active', updated_at = %s WHERE id = %d", $now, $id ) );

		if ( false === $archived || false === $activated ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'jcmv_db_error', $wpdb->last_error ?: 'Activation impossible.' );
		}

		$wpdb->query( 'COMMIT' );

		return true;
	}

	/**
	 * Prépare la saison suivante : nouvelle saison brouillon (année + 1),
	 * frais recopiés, créneaux et tarifs dupliqués.
	 *
	 * @return int|WP_Error ID de la nouvelle saison.
	 */
	public function prepare_next( int $source_id ) {
		global $wpdb;

		$source = $this->find( $source_id );
		if ( ! $source ) {
			return new WP_Error( 'jcmv_season_not_found', 'Saison source introuvable.' );
		}

		$new_year = (int) $source->start_year + 1;
		if ( $this->find_by_year( $new_year ) ) {
			return new WP_Error( 'jcmv_season_exists', sprintf( 'La saison %d-%d existe déjà.', $new_year, $new_year + 1 ) );
		}

		$now = current_time( 'mysql' );

		$wpdb->query( 'START TRANSACTION' );

		$created = $this->create(
			$new_year,
			(float) $source->licence_amount,
			(float) $source->adhesion_amount,
			(string) $source->licence_note,
			(string) $source->adhesion_note
		);

		if ( is_wp_error( $created ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $created;
		}

		$schedule = Schema::table( 'schedule' );
		$pricing  = Schema::table( 'pricing' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$dup_schedule = $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$schedule} (season_id, course_id, location_id, weekday, start_time, end_time, note, sort_order, created_at, updated_at)
			 SELECT %d, course_id, location_id, weekday, start_time, end_time, note, sort_order, %s, %s
			 FROM {$schedule} WHERE season_id = %d",
			$created,
			$now,
			$now,
			$source_id
		) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$dup_pricing = $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$pricing} (season_id, course_id, label, amount, period, note, sort_order, created_at, updated_at)
			 SELECT %d, course_id, label, amount, period, note, sort_order, %s, %s
			 FROM {$pricing} WHERE season_id = %d",
			$created,
			$now,
			$now,
			$source_id
		) );

		if ( false === $dup_schedule || false === $dup_pricing ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'jcmv_db_error', $wpdb->last_error ?: 'Duplication impossible.' );
		}

		$wpdb->query( 'COMMIT' );

		return $created;
	}

	/**
	 * Supprime une saison BROUILLON et ses créneaux/tarifs.
	 *
	 * @return true|WP_Error
	 */
	public function delete( int $id ) {
		global $wpdb;

		$season = $this->find( $id );
		if ( ! $season ) {
			return new WP_Error( 'jcmv_season_not_found', 'Saison introuvable.' );
		}
		if ( 'draft' !== $season->status ) {
			return new WP_Error( 'jcmv_season_not_draft', 'Seule une saison brouillon peut être supprimée.' );
		}

		$wpdb->query( 'START TRANSACTION' );

		$wpdb->delete( Schema::table( 'schedule' ), array( 'season_id' => $id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pricing' ), array( 'season_id' => $id ), array( '%d' ) );
		$deleted = $wpdb->delete( Schema::table( 'season' ), array( 'id' => $id ), array( '%d' ) );

		if ( false === $deleted ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'jcmv_db_error', $wpdb->last_error ?: 'Suppression impossible.' );
		}

		$wpdb->query( 'COMMIT' );

		return true;
	}

	/**
	 * Libellé d'affichage : « 2026-2027 ».
	 */
	public static function label( object $season ): string {
		return $season->start_year . '-' . ( (int) $season->start_year + 1 );
	}
}
