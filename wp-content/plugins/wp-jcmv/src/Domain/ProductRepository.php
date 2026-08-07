<?php
/**
 * Produits de la boutique (CPT jcmv_produit, ADR-005).
 *
 * Donnée native WordPress (ADR-001, niveau « contenu administrable ») :
 * lecture par WP_Query, pas de $wpdb — sauf pour la grille tarifaire, qui
 * vit en table custom et transite par ProductPriceRepository.
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
	 * @param string $category Slug de rayon ; vide = tous les rayons.
	 * @param int    $limit    Nombre maximum ; 0 ou négatif = tous.
	 * @return array<int, array{
	 *     id:int, nom:string, description:string, photo_id:int,
	 *     galerie:array<int,int>, coloris:string, dispo:string,
	 *     dispo_label:string, grille:array<int, array{taille:string, prix:float}>,
	 *     prix:float, prix_a_partir_de:bool
	 * }>
	 */
	public function all( string $category = '', int $limit = 0 ): array {
		$args = array(
			'post_type'        => PostTypes::PRODUIT,
			'post_status'      => 'publish',
			'numberposts'      => self::MAX,
			'orderby'          => 'menu_order title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		);

		if ( '' !== $category ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- catalogue de quelques dizaines d'entrées.
				array(
					'taxonomy' => Taxonomies::CATEGORIE_PRODUIT,
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		$posts = get_posts( $args );

		if ( ! $posts ) {
			return array();
		}

		// Grilles de tous les produits en une requête, avant la boucle.
		$grids = ( new ProductPriceRepository() )->for_products( wp_list_pluck( $posts, 'ID' ) );

		$products = array();

		foreach ( $posts as $post ) {
			$photo_id = (int) get_post_thumbnail_id( $post->ID );

			if ( ! $photo_id ) {
				continue;
			}

			$grid  = $grids[ (int) $post->ID ] ?? array();
			$dispo = PostTypes::sanitize_dispo( get_post_meta( $post->ID, 'jcmv_produit_dispo', true ) );

			list( $price, $from ) = self::resolve_price(
				(float) get_post_meta( $post->ID, 'jcmv_produit_prix', true ),
				$grid
			);

			$products[] = array(
				'id'               => (int) $post->ID,
				'nom'              => get_the_title( $post ),
				'description'      => (string) $post->post_content,
				'photo_id'         => $photo_id,
				'galerie'          => PostTypes::sanitize_gallery( get_post_meta( $post->ID, 'jcmv_produit_galerie', true ) ),
				'coloris'          => (string) get_post_meta( $post->ID, 'jcmv_produit_coloris', true ),
				'dispo'            => $dispo,
				'dispo_label'      => PostTypes::DISPONIBILITES[ $dispo ],
				'grille'           => $grid,
				'prix'             => $price,
				'prix_a_partir_de' => $from,
			);

			if ( $limit > 0 && count( $products ) >= $limit ) {
				break;
			}
		}

		return $products;
	}

	/**
	 * Prix à afficher pour un produit.
	 *
	 * La grille prime sur le prix unique dès qu'elle existe : quand le bureau
	 * a pris la peine de saisir des tarifs par taille, le champ prix unique
	 * est au mieux redondant, au pire périmé. On affiche alors le minimum,
	 * signalé comme « à partir de » — sauf si toutes les tailles sont au même
	 * tarif, auquel cas « à partir de » serait un mensonge par omission.
	 *
	 * @param float                                        $single Prix unique (postmeta).
	 * @param array<int, array{taille:string, prix:float}> $grid   Grille tarifaire.
	 * @return array{0:float, 1:bool} Prix affiché, et s'il s'agit d'un « à partir de ».
	 */
	public static function resolve_price( float $single, array $grid ): array {
		if ( ! $grid ) {
			return array( round( max( 0, $single ), 2 ), false );
		}

		$prices = array_column( $grid, 'prix' );

		return array( round( min( $prices ), 2 ), min( $prices ) < max( $prices ) );
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
