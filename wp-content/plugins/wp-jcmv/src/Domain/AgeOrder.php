<?php
/**
 * Ordre d'affichage des catégories d'âge.
 *
 * Le référentiel FFJDA ne se classe pas par nom : alphabétiquement, Cadet
 * (14 ans) passe avant Éveil Judo (4 ans), et Benjamin (10 ans) arrive en
 * deuxième position. Le seul ordre qui ait un sens, pour un adhérent comme
 * pour le bureau, est celui des bornes d'âge — portées par le term meta
 * `age_min` (voir Registration\Taxonomies et Admin\TermFields).
 *
 * Une seule définition de cet ordre, partagée par le front (flux ICS) et par
 * l'admin (cases à cocher des écrans cours et événements) : deux copies de la
 * même règle finiraient par diverger le jour où l'une des deux est ajustée.
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AgeOrder {

	/** Term meta portant la borne basse, seule clé de tri. */
	private const META_AGE_MIN = 'age_min';

	/**
	 * Comparateur usort() : âge croissant, puis nom à âge égal.
	 *
	 * Un terme sans borne renseignée compte pour 0 et remonte en tête de liste
	 * plutôt que de disparaître. C'est aussi la raison pour laquelle le tri se
	 * fait ici, en PHP, et non par `orderby => 'meta_value_num'` : préciser un
	 * `meta_key` à WP_Term_Query joint `termmeta` et escamoterait purement et
	 * simplement un terme fraîchement créé dont l'âge n'a pas encore été saisi.
	 * `register_term_meta( 'default' => 0 )` n'écrit aucune ligne en base et ne
	 * protège donc pas de ce cas.
	 */
	public static function compare( WP_Term $a, WP_Term $b ): int {
		$age_a = (int) get_term_meta( $a->term_id, self::META_AGE_MIN, true );
		$age_b = (int) get_term_meta( $b->term_id, self::META_AGE_MIN, true );

		return $age_a === $age_b
			? strnatcasecmp( $a->name, $b->name )
			: $age_a <=> $age_b;
	}

	/**
	 * Trie une liste de termes de jcmv_categorie_age, de Baby Judo à Sénior.
	 *
	 * @param WP_Term[] $terms Termes à classer.
	 * @return WP_Term[] Nouvelle liste, réindexée.
	 */
	public static function sort( array $terms ): array {
		usort( $terms, array( self::class, 'compare' ) );

		return $terms;
	}
}
