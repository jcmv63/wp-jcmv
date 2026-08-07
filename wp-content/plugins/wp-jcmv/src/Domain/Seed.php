<?php
/**
 * Référentiels embarqués (ADR-001, niveau « structure ») : disciplines et
 * catégories d'âge FFJDA, seedés à l'activation.
 *
 * - Idempotent : un terme existant (slug) n'est jamais recréé ni écrasé —
 *   les modifications du bureau (noms, bornes) sont préservées.
 * - Slugs immuables : ce sont les identifiants stables du code et des flux.
 * - Les bornes d'âge sont des ÂGES indicatifs ; les années de naissance
 *   affichées se calculent depuis start_year de la saison (jamais stockées).
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

use JCMV\Registration\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Seed {

	private const DISCIPLINES = array(
		'judo'          => 'Judo',
		'cross-training' => 'Cross-training',
		'self-defense'  => 'Self-défense',
	);

	/**
	 * Référentiel FFJDA : slug => [ label, âge min, âge max ].
	 * Bornes par défaut indicatives, éditables ensuite dans l'admin.
	 */
	private const CATEGORIES_AGE = array(
		'baby-judo'    => array( 'Baby Judo', 3, 3 ),
		'eveil-judo'   => array( 'Éveil Judo', 4, 5 ),
		'mini-poussin' => array( 'Mini-poussin', 6, 7 ),
		'poussin'      => array( 'Poussin', 8, 9 ),
		'benjamin'     => array( 'Benjamin', 10, 11 ),
		'minime'       => array( 'Minime', 12, 13 ),
		'cadet'        => array( 'Cadet', 14, 16 ),
		'junior'       => array( 'Junior', 17, 19 ),
		'senior'       => array( 'Sénior / Vétéran', 20, 99 ),
	);

	/*
	 * Les familles et les systèmes de tailles de la boutique (ADR-005) ne sont
	 * volontairement PAS seedés. On n'embarque un référentiel que lorsqu'il
	 * existe en dehors du club — catégories FFJDA, disciplines. Les familles de
	 * produits sont un choix de présentation du bureau, et les systèmes de
	 * tailles se lisent sur le catalogue du fournisseur : les deviner ici
	 * reviendrait à imposer des slugs immuables sur des valeurs inventées.
	 */

	public static function run(): void {
		foreach ( self::DISCIPLINES as $slug => $label ) {
			if ( ! term_exists( $slug, Taxonomies::DISCIPLINE ) ) {
				wp_insert_term( $label, Taxonomies::DISCIPLINE, array( 'slug' => $slug ) );
			}
		}

		foreach ( self::CATEGORIES_AGE as $slug => $def ) {
			list( $label, $age_min, $age_max ) = $def;

			if ( term_exists( $slug, Taxonomies::CATEGORIE_AGE ) ) {
				continue;
			}

			$term = wp_insert_term( $label, Taxonomies::CATEGORIE_AGE, array( 'slug' => $slug ) );
			if ( is_wp_error( $term ) ) {
				continue;
			}

			update_term_meta( $term['term_id'], 'age_min', $age_min );
			update_term_meta( $term['term_id'], 'age_max', $age_max );
		}
	}
}
