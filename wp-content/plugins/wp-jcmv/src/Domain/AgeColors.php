<?php
/**
 * Couleurs des catégories d'âge (term meta `jcmv_couleur`).
 *
 * Lecture seule, partagée par l'écran d'édition des termes (Admin\TermFields)
 * et le front (Front\EventAgeColors). Chaque valeur repasse par
 * sanitize_hex_color() en lecture : elle finit dans une feuille de style, et
 * une valeur écrite par un autre chemin que le formulaire (import, SQL, REST)
 * n'est pas présumée saine.
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

use JCMV\Registration\Taxonomies;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AgeColors {

	/**
	 * Couleur d'une catégorie d'âge, `#rrggbb` ou chaîne vide.
	 */
	public static function of( int $term_id ): string {
		return (string) sanitize_hex_color( (string) get_term_meta( $term_id, Taxonomies::META_COULEUR, true ) );
	}

	/**
	 * Catégories d'âge ayant une couleur, indexées par slug.
	 *
	 * Une seule requête : `meta_key` restreint aux termes porteurs de la
	 * méta, et WordPress amorce le cache des métas des termes renvoyés.
	 *
	 * @return array<string,string> slug => `#rrggbb`
	 */
	public static function all(): array {
		if ( ! taxonomy_exists( Taxonomies::CATEGORIE_AGE ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::CATEGORIE_AGE,
				'hide_empty' => false,
				'meta_key'   => Taxonomies::META_COULEUR, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- une poignée de termes.
			)
		);

		if ( ! is_array( $terms ) ) {
			return array();
		}

		$colors = array();

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$color = self::of( $term->term_id );

			if ( '' !== $color ) {
				$colors[ $term->slug ] = $color;
			}
		}

		return $colors;
	}
}
