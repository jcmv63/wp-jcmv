<?php
/**
 * Partenaires du club (CPT jcmv_partenaire).
 *
 * Contrairement aux saisons / créneaux / tarifs, la donnée est native
 * WordPress (ADR-001, niveau « contenu administrable ») : lecture par
 * WP_Query, pas de $wpdb.
 *
 * Règle métier : un partenaire sans logo n'est pas affichable dans un
 * ruban de logos — il est écarté ici plutôt que dans chaque gabarit.
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

use JCMV\Registration\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PartnerRepository {

	/**
	 * Partenaires publiés disposant d'un logo, dans l'ordre d'affichage.
	 *
	 * @param int $limit Nombre maximum ; 0 ou négatif = tous (plafonné à 100).
	 * @return array<int, array{id:int, nom:string, url:string, logo_id:int}>
	 */
	public function all( int $limit = 0 ): array {
		$posts = get_posts(
			array(
				'post_type'        => PostTypes::PARTENAIRE,
				'post_status'      => 'publish',
				'numberposts'      => 100,
				'orderby'          => 'menu_order title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		$partners = array();

		foreach ( $posts as $post ) {
			$logo_id = (int) get_post_thumbnail_id( $post->ID );

			// Sans logo, pas d'affichage : un ruban de logos ne peut pas
			// rendre une fiche vide, et masquer vaut mieux qu'un trou.
			if ( ! $logo_id ) {
				continue;
			}

			$partners[] = array(
				'id'      => (int) $post->ID,
				'nom'     => get_the_title( $post ),
				'url'     => (string) get_post_meta( $post->ID, 'jcmv_partenaire_url', true ),
				'logo_id' => $logo_id,
			);

			if ( $limit > 0 && count( $partners ) >= $limit ) {
				break;
			}
		}

		return $partners;
	}

	/**
	 * Nombre de partenaires affichables — sert à décider si le lien
	 * « tous nos partenaires » a un intérêt.
	 */
	public function count(): int {
		return count( $this->all() );
	}
}
