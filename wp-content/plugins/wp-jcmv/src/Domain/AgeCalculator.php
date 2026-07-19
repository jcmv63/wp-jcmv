<?php
/**
 * Années de naissance affichées pour une catégorie d'âge.
 *
 * Règle ADR-001 : calculées depuis start_year de la saison et les bornes
 * d'âge de la catégorie — jamais stockées, jamais dérivées de la date du
 * jour. Exemple : saison 2026-2027 (start_year 2026), Mini-poussin 7-8 ans
 * → né(e)s en 2019-2018.
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AgeCalculator {

	/**
	 * Bornes d'années de naissance.
	 *
	 * @return array{youngest: int, oldest: int} Année la plus récente / la plus ancienne.
	 */
	public static function birth_years( int $season_start_year, int $age_min, int $age_max ): array {
		return array(
			'youngest' => $season_start_year - $age_min,
			'oldest'   => $season_start_year - $age_max,
		);
	}

	/**
	 * Libellé public, format charte : « Né(e)s en 2019–2018 ».
	 * Sans borne renseignée (0), renvoie une chaîne vide.
	 */
	public static function label( int $season_start_year, int $age_min, int $age_max ): string {
		if ( $age_min <= 0 && $age_max <= 0 ) {
			return '';
		}

		$years = self::birth_years( $season_start_year, $age_min, $age_max );

		if ( $age_max >= 99 ) {
			// Catégorie ouverte vers le haut (séniors/vétérans).
			return sprintf( 'Né(e)s en %d et avant', $years['youngest'] );
		}

		if ( $years['youngest'] === $years['oldest'] ) {
			return sprintf( 'Né(e)s en %d', $years['youngest'] );
		}

		return sprintf( 'Né(e)s en %d–%d', $years['youngest'], $years['oldest'] );
	}
}
