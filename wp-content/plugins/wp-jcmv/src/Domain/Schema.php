<?php
/**
 * Schéma des tables custom (ADR-001, niveau « relationnel saisonnier »).
 *
 * Conventions :
 * - Pas de clé étrangère SQL vers wp_posts (convention WordPress) :
 *   l'intégrité est applicative, dans les repositories.
 * - course_id / location_id référencent des IDs de wp_posts (CPT jcmv_cours,
 *   jcmv_lieu) ; season_id référence wp_jcmv_season.id.
 * - weekday : ISO-8601 (1 = lundi … 7 = dimanche).
 * - Les montants sont en euros, DECIMAL(8,2).
 * - created_at / updated_at sont renseignés par les repositories (pas de
 *   DEFAULT CURRENT_TIMESTAMP : dbDelta le gère mal).
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schema {

	/**
	 * À incrémenter à chaque évolution du schéma ; comparée à l'option
	 * pour rejouer dbDelta() au chargement suivant.
	 */
	public const DB_VERSION = '1';

	private const OPTION = 'jcmv_db_version';

	/**
	 * Statuts autorisés d'une saison (cycle de vie ADR-001).
	 */
	public const SEASON_STATUSES = array( 'draft', 'active', 'archived' );

	/**
	 * Rejoue les migrations si la version stockée est en retard.
	 */
	public static function maybe_migrate(): void {
		if ( get_option( self::OPTION ) !== self::DB_VERSION ) {
			self::migrate();
		}
	}

	/**
	 * Crée / met à niveau les tables (idempotent, via dbDelta).
	 */
	public static function migrate(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix;

		dbDelta(
			"CREATE TABLE {$prefix}jcmv_season (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				start_year smallint(5) unsigned NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'draft',
				licence_amount decimal(8,2) NOT NULL DEFAULT 0.00,
				adhesion_amount decimal(8,2) NOT NULL DEFAULT 0.00,
				licence_note varchar(190) NOT NULL DEFAULT '',
				adhesion_note varchar(190) NOT NULL DEFAULT '',
				created_at datetime NULL,
				updated_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY start_year (start_year),
				KEY status (status)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$prefix}jcmv_schedule (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				season_id bigint(20) unsigned NOT NULL,
				course_id bigint(20) unsigned NOT NULL,
				location_id bigint(20) unsigned NOT NULL,
				weekday tinyint(3) unsigned NOT NULL,
				start_time time NOT NULL,
				end_time time NOT NULL,
				note varchar(190) NOT NULL DEFAULT '',
				sort_order int(11) NOT NULL DEFAULT 0,
				created_at datetime NULL,
				updated_at datetime NULL,
				PRIMARY KEY  (id),
				KEY season_course (season_id,course_id),
				KEY location (location_id)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$prefix}jcmv_pricing (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				season_id bigint(20) unsigned NOT NULL,
				course_id bigint(20) unsigned NOT NULL,
				label varchar(190) NOT NULL,
				amount decimal(8,2) NOT NULL DEFAULT 0.00,
				period varchar(50) NOT NULL DEFAULT '',
				note varchar(190) NOT NULL DEFAULT '',
				sort_order int(11) NOT NULL DEFAULT 0,
				created_at datetime NULL,
				updated_at datetime NULL,
				PRIMARY KEY  (id),
				KEY season_course (season_id,course_id)
			) {$charset};"
		);

		update_option( self::OPTION, self::DB_VERSION );
	}

	/**
	 * Noms complets des tables (préfixe inclus), pour les repositories.
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'jcmv_' . $name;
	}
}
