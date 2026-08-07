<?php
/**
 * Produits de la boutique (CPT jcmv_produit, ADR-005).
 *
 * Donnée entièrement native WordPress (ADR-001, niveau « contenu
 * administrable ») : lecture par WP_Query, aucune table custom, aucun $wpdb.
 *
 * Règle métier : un produit sans photo n'est pas affichable dans une grille
 * de produits. Elle est appliquée ici plutôt que dans le gabarit, pour la
 * même raison que chez les partenaires — masquer vaut mieux qu'un trou.
 *
 * @package wp-jcmv
 */

namespace JCMV\Domain;

use JCMV\Registration\PostTypes;
use JCMV\Registration\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProductRepository {

	/** Garde-fou : au-delà, ce n'est plus une vitrine de club. */
	private const MAX = 100;

	/**
	 * Produits publiés disposant d'une photo, dans l'ordre d'affichage.
	 *
	 * @param string $famille Slug de famille ; vide = toutes les familles.
	 * @param int    $limit   Nombre maximum ; 0 ou négatif = tous.
	 * @return array<int, array{
	 *     id:int, nom:string, description:string, photo_id:int,
	 *     galerie:array<int,int>, couleur:string, dispo:string,
	 *     dispo_label:string, tailles:array<int,string>, prix:float
	 * }>
	 */
	public function all( string $famille = '', int $limit = 0 ): array {
		$args = array(
			'post_type'        => PostTypes::PRODUIT,
			'post_status'      => 'publish',
			'numberposts'      => self::MAX,
			'orderby'          => 'menu_order title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		);

		if ( '' !== $famille ) {
			// Filtrage par slug et non par ID de terme : l'attribut du bloc est
			// enregistré dans le contenu de la page, et un ID de terme diffère
			// d'un environnement à l'autre. Le slug, lui, voyage.
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- catalogue de quelques dizaines d'entrées.
				array(
					'taxonomy' => Taxonomies::FAMILLE,
					'field'    => 'slug',
					'terms'    => $famille,
				),
			);
		}

		$posts = get_posts( $args );

		if ( ! $posts ) {
			return array();
		}

		$products = array();

		foreach ( $posts as $post ) {
			$photo_id = (int) get_post_thumbnail_id( $post->ID );

			if ( ! $photo_id ) {
				continue;
			}

			$dispo = PostTypes::sanitize_dispo( get_post_meta( $post->ID, 'jcmv_produit_dispo', true ) );

			$products[] = array(
				'id'          => (int) $post->ID,
				'nom'         => get_the_title( $post ),
				'description' => (string) $post->post_content,
				'photo_id'    => $photo_id,
				'galerie'     => PostTypes::sanitize_gallery( get_post_meta( $post->ID, 'jcmv_produit_galerie', true ) ),
				'couleur'     => (string) get_post_meta( $post->ID, 'jcmv_produit_couleur', true ),
				'dispo'       => $dispo,
				'dispo_label' => PostTypes::DISPONIBILITES[ $dispo ],
				// L'ordre vient de la saisie (système de tailles) : aucun tri ici,
				// aucun tri n'étant capable de classer « 10 ans, S, M, L ».
				'tailles'     => PostTypes::sanitize_sizes( get_post_meta( $post->ID, 'jcmv_produit_tailles', true ) ),
				'prix'        => round( max( 0, (float) get_post_meta( $post->ID, 'jcmv_produit_prix', true ) ), 2 ),
			);

			if ( $limit > 0 && count( $products ) >= $limit ) {
				break;
			}
		}

		return $products;
	}

	/**
	 * Formate un montant pour l'affichage : « 25 € », « 24,50 € ».
	 *
	 * Les centimes ne sont montrés que s'il y en a — « 25,00 € » sur une
	 * étiquette de t-shirt fait comptable, pas boutique.
	 */
	public static function format_price( float $amount ): string {
		$decimals = ( round( $amount, 2 ) === round( $amount, 0 ) ) ? 0 : 2;

		return number_format_i18n( $amount, $decimals ) . ' €';
	}
}
