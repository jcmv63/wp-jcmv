<?php
/**
 * Grille tarifaire des produits (table wp_jcmv_produit_tarif, ADR-005).
 *
 * Même contrat que PricingRepository : lecture par parent, remplacement
 * atomique du lot. Les lignes n'ont pas d'identité propre — rien ne les
 * référence — d'où le delete + insert plutôt qu'un diff ligne à ligne.
 *
 * La grille est facultative : un produit sans ligne affiche son prix unique.
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProductPriceRepository {

	private const TABLE = 'produit_tarif';

	/**
	 * Grille d'un produit, dans l'ordre d'affichage.
	 *
	 * L'ordre est celui saisi par le bureau, jamais un tri sur le prix : une
	 * grille de tailles se lit du plus petit au plus grand, et c'est une
	 * information que seule la personne qui saisit détient (« 4 » vient après
	 * « 3 », mais « M » avant « L »).
	 *
	 * @return array<int, array{taille:string, prix:float}>
	 */
	public function for_product( int $product_id ): array {
		global $wpdb;
		$table = Schema::table( self::TABLE );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nom de table interne.
				"SELECT taille, price FROM {$table} WHERE product_id = %d ORDER BY sort_order, id",
				$product_id
			)
		);

		$grid = array();
		foreach ( (array) $rows as $row ) {
			$grid[] = array(
				'taille' => (string) $row->taille,
				'prix'   => (float) $row->price,
			);
		}

		return $grid;
	}

	/**
	 * Grilles de plusieurs produits en une requête.
	 *
	 * Le bloc boutique affiche jusqu'à cent produits : une requête par produit
	 * transformerait une page en cascade de requêtes pour une donnée qui tient
	 * en un SELECT ... IN.
	 *
	 * @param array<int, int> $product_ids IDs de produits.
	 * @return array<int, array<int, array{taille:string, prix:float}>> Indexé par ID de produit.
	 */
	public function for_products( array $product_ids ): array {
		$product_ids = array_values( array_filter( array_map( 'absint', $product_ids ) ) );

		if ( ! $product_ids ) {
			return array();
		}

		global $wpdb;
		$table        = Schema::table( self::TABLE );
		$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nom de table interne et placeholders générés.
				"SELECT product_id, taille, price FROM {$table} WHERE product_id IN ({$placeholders}) ORDER BY product_id, sort_order, id",
				$product_ids
			)
		);

		$grids = array();
		foreach ( (array) $rows as $row ) {
			$grids[ (int) $row->product_id ][] = array(
				'taille' => (string) $row->taille,
				'prix'   => (float) $row->price,
			);
		}

		return $grids;
	}

	/**
	 * Remplace l'intégralité de la grille d'un produit.
	 *
	 * Les lignes dont la taille est vide sont ignorées silencieusement : c'est
	 * ce que produit une ligne ajoutée puis laissée en blanc dans la metabox,
	 * et refuser l'enregistrement complet pour cela serait hostile.
	 *
	 * @param array<int, array{taille?:string, prix?:mixed}> $rows Lignes saisies, dans l'ordre.
	 * @return true|WP_Error
	 */
	public function replace_for_product( int $product_id, array $rows ) {
		global $wpdb;
		$table = Schema::table( self::TABLE );

		$clean = array();
		foreach ( array_values( $rows ) as $i => $row ) {
			$row    = (array) $row;
			$taille = sanitize_text_field( (string) ( $row['taille'] ?? '' ) );

			if ( '' === $taille ) {
				continue;
			}

			$clean[] = array(
				'taille'     => $taille,
				'price'      => \JCMV\Registration\PostTypes::sanitize_amount( $row['prix'] ?? 0 ),
				'sort_order' => $i,
			);
		}

		$now = current_time( 'mysql' );

		$wpdb->query( 'START TRANSACTION' );

		$deleted = $wpdb->delete( $table, array( 'product_id' => $product_id ), array( '%d' ) );

		if ( false === $deleted ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'jcmv_db_error', $wpdb->last_error ? $wpdb->last_error : 'Écriture impossible.' );
		}

		foreach ( $clean as $row ) {
			$ok = $wpdb->insert(
				$table,
				array(
					'product_id' => $product_id,
					'taille'     => $row['taille'],
					'price'      => $row['price'],
					'sort_order' => $row['sort_order'],
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d', '%s', '%f', '%d', '%s', '%s' )
			);

			if ( false === $ok ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'jcmv_db_error', $wpdb->last_error ? $wpdb->last_error : 'Écriture impossible.' );
			}
		}

		$wpdb->query( 'COMMIT' );

		return true;
	}

	/**
	 * Purge la grille d'un produit supprimé.
	 *
	 * Sans ce nettoyage, la table accumulerait des lignes orphanes — il n'y a
	 * pas de clé étrangère pour les emporter (convention ADR-001).
	 */
	public function delete_for_product( int $product_id ): void {
		global $wpdb;

		$wpdb->delete( Schema::table( self::TABLE ), array( 'product_id' => $product_id ), array( '%d' ) );
	}
}
