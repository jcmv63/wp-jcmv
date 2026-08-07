<?php
/**
 * Normalisation des libellés de tailles (ADR-005).
 *
 * Classe utilitaire sans état, sur le modèle d'AgeCalculator : elle ne connaît
 * ni CPT, ni taxonomie, ni base de données. Elle vit dans Domain parce que
 * trois couches s'en servent — Registration pour les sanitize_callback, Admin
 * pour la metabox et les écrans de termes, et le repository pour la lecture.
 *
 * Elle était initialement logée dans Registration\PostTypes, du temps où les
 * tailles n'étaient qu'une postmeta. Depuis la scission famille / système de
 * tailles, cette dépendance était à l'envers : Taxonomies n'a pas à passer par
 * PostTypes pour manipuler des chaînes.
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sizes {

	/**
	 * Libellés nettoyés, dédoublonnés, **ordre préservé**.
	 *
	 * L'ordre est l'information principale — aucun tri ne classe correctement
	 * « 10 ans, 12 ans, S, M, L, XL », ni alphabétique ni numérique. Il ne
	 * vient que de la personne qui a saisi le système, et doit donc traverser
	 * cette fonction intact.
	 *
	 * Le dédoublonnage est insensible à la casse et aux espaces : « XL » et
	 * « xl  » sont la même taille, et deux variantes en base rendraient tout
	 * comptage de commandes faux sans que personne ne le voie.
	 *
	 * @param mixed $value Tableau de libellés, ou chaîne séparée par virgules.
	 * @return array<int, string>
	 */
	public static function normalize( $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$sizes = array();
		$seen  = array();

		foreach ( $value as $label ) {
			$label = sanitize_text_field( (string) $label );
			// Espaces multiples ou insécables recollés : « 12  ans » et
			// « 12 ans » ne doivent pas cohabiter.
			$label = trim( preg_replace( '/\s+/u', ' ', $label ) ?? '' );

			if ( '' === $label ) {
				continue;
			}

			$key = self::fold( $label );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$sizes[]      = $label;
		}

		return $sizes;
	}

	/**
	 * Forme normalisée d'un libellé, pour comparaison seulement.
	 *
	 * mbstring est présent partout où ce plugin tourne, mais WordPress ne le
	 * garantit pas : le repli sur strtolower() ne gère pas les accents, ce qui
	 * n'a aucune conséquence sur des libellés de tailles (`S`, `XL`, `110`,
	 * `10 ans`). Autant ne pas faire échouer un enregistrement pour ça.
	 */
	public static function fold( string $label ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label );
	}

	/**
	 * Réordonne des libellés selon un ordre de référence.
	 *
	 * Ce qui appartient à la référence vient d'abord, dans SON ordre ; le
	 * reste suit, dans l'ordre reçu. Sans quoi une taille ajoutée à la main
	 * mais déjà présente dans le système se retrouverait en fin de liste,
	 * cassant la progression que le bureau a pris soin de saisir.
	 *
	 * @param array<int, string> $labels    Libellés à ordonner.
	 * @param array<int, string> $reference Ordre de référence ; vide = pas de tri.
	 * @return array<int, string>
	 */
	public static function order_by( array $labels, array $reference ): array {
		if ( ! $reference ) {
			return $labels;
		}

		$index  = array_map( array( self::class, 'fold' ), $reference );
		$connus = array();
		$autres = array();

		foreach ( $labels as $label ) {
			$rang = array_search( self::fold( $label ), $index, true );

			if ( false === $rang ) {
				$autres[] = $label;
			} else {
				$connus[ $rang ] = $label;
			}
		}

		ksort( $connus );

		return array_merge( array_values( $connus ), $autres );
	}
}
