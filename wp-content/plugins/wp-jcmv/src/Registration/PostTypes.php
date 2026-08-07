<?php
/**
 * CPT du module club (ADR-001, niveau « contenu administrable »).
 *
 * - jcmv_cours      : le pivot du modèle (nom, description, ordre d'affichage).
 * - jcmv_lieu       : dojo / lieu de pratique (adresse en postmeta).
 * - jcmv_partenaire : sponsor du club (logo en image mise en avant, URL en
 *   postmeta, ordre d'affichage en menu_order).
 * - jcmv_produit    : article floqué de la boutique (ADR-005). Libellé
 *   « Produits » et non « Articles » : ce dernier désigne déjà les posts
 *   natifs dans l'admin française, deux entrées homonymes égareraient le
 *   bureau.
 *
 * Convention ADR-001 : dépublier = désactiver. La suppression d'un élément
 * référencé par des créneaux/tarifs est refusée (voir DeletionGuard).
 *
 * @package wp-jcmv
 */

namespace JCMV\Registration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostTypes {

	public const COURS      = 'jcmv_cours';
	public const LIEU       = 'jcmv_lieu';
	public const PARTENAIRE = 'jcmv_partenaire';
	public const PRODUIT    = 'jcmv_produit';

	/**
	 * Disponibilité déclarative d'un produit (ADR-005 : pas de décompte de
	 * stock). Le premier élément fait office de valeur par défaut.
	 *
	 * @var array<string,string>
	 */
	public const DISPONIBILITES = array(
		'disponible'   => 'Disponible',
		'sur-commande' => 'Sur commande',
		'epuise'       => 'Épuisé',
	);

	/** Nombre de photos de galerie en plus de l'image mise en avant. */
	public const GALERIE_MAX = 3;

	public static function register(): void {
		// Fiches simples (nom + description) : l'éditeur de blocs est
		// disproportionné ici. show_in_rest reste actif pour l'app Saisons.
		// jcmv_produit en est volontairement absent : sa description est du
		// contenu rédigé, qui deviendra le corps de la page produit publique
		// le jour de la bascule (ADR-005).
		add_filter(
			'use_block_editor_for_post_type',
			static function ( bool $use, string $post_type ): bool {
				return in_array( $post_type, array( self::COURS, self::LIEU, self::PARTENAIRE ), true ) ? false : $use;
			},
			10,
			2
		);

		register_post_type(
			self::COURS,
			array(
				'labels'       => array(
					'name'               => __( 'Cours', 'wp-jcmv' ),
					'singular_name'      => __( 'Cours', 'wp-jcmv' ),
					'add_new'            => __( 'Ajouter un cours', 'wp-jcmv' ),
					'add_new_item'       => __( 'Ajouter un cours', 'wp-jcmv' ),
					'edit_item'          => __( 'Modifier le cours', 'wp-jcmv' ),
					'not_found'          => __( 'Aucun cours trouvé.', 'wp-jcmv' ),
					'all_items'          => __( 'Cours', 'wp-jcmv' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'jcmv-club',
				'show_in_rest' => true,
				'rewrite'      => false,
				'has_archive'  => false,
				'supports'     => array( 'title', 'editor', 'page-attributes' ),
				'menu_icon'    => 'dashicons-universal-access',
			)
		);

		register_post_type(
			self::LIEU,
			array(
				'labels'       => array(
					'name'          => __( 'Lieux', 'wp-jcmv' ),
					'singular_name' => __( 'Lieu', 'wp-jcmv' ),
					'add_new'       => __( 'Ajouter un lieu', 'wp-jcmv' ),
					'add_new_item'  => __( 'Ajouter un lieu', 'wp-jcmv' ),
					'edit_item'     => __( 'Modifier le lieu', 'wp-jcmv' ),
					'not_found'     => __( 'Aucun lieu trouvé.', 'wp-jcmv' ),
					'all_items'     => __( 'Lieux', 'wp-jcmv' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'jcmv-club',
				'show_in_rest' => true,
				'rewrite'      => false,
				'has_archive'  => false,
				'supports'     => array( 'title' ),
			)
		);

		// Le logo est l'image mise en avant ; l'ordre du ruban et de la grille
		// est piloté par menu_order (attributs de page). Dépublier = retirer
		// du site sans perdre la fiche (convention ADR-001).
		register_post_type(
			self::PARTENAIRE,
			array(
				'labels'       => array(
					'name'          => __( 'Partenaires', 'wp-jcmv' ),
					'singular_name' => __( 'Partenaire', 'wp-jcmv' ),
					'add_new'       => __( 'Ajouter un partenaire', 'wp-jcmv' ),
					'add_new_item'  => __( 'Ajouter un partenaire', 'wp-jcmv' ),
					'edit_item'     => __( 'Modifier le partenaire', 'wp-jcmv' ),
					'not_found'     => __( 'Aucun partenaire trouvé.', 'wp-jcmv' ),
					'all_items'     => __( 'Partenaires', 'wp-jcmv' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'jcmv-club',
				'show_in_rest' => true,
				'rewrite'      => false,
				'has_archive'  => false,
				'supports'     => array( 'title', 'thumbnail', 'page-attributes' ),
				'menu_icon'    => 'dashicons-awards',
			)
		);

		/*
		 * Boutique (ADR-005). `public => false` : le catalogue s'affiche par le
		 * bloc jcmv/boutique, il n'y a pas de page par produit à ce stade. Le
		 * jour de la bascule, passer public/has_archive à true, ajouter un
		 * slug de réécriture et incrémenter Plugin::REWRITE_VERSION — aucune
		 * donnée n'est à migrer.
		 */
		register_post_type(
			self::PRODUIT,
			array(
				'labels'       => array(
					'name'          => __( 'Produits', 'wp-jcmv' ),
					'singular_name' => __( 'Produit', 'wp-jcmv' ),
					'add_new'       => __( 'Ajouter un produit', 'wp-jcmv' ),
					'add_new_item'  => __( 'Ajouter un produit', 'wp-jcmv' ),
					'edit_item'     => __( 'Modifier le produit', 'wp-jcmv' ),
					'not_found'     => __( 'Aucun produit trouvé.', 'wp-jcmv' ),
					'all_items'     => __( 'Produits', 'wp-jcmv' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'jcmv-club',
				'show_in_rest' => true,
				'rewrite'      => false,
				'has_archive'  => false,
				'supports'     => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
				'menu_icon'    => 'dashicons-tshirt',
			)
		);

		$jcmv_can_edit = static function () {
			return current_user_can( 'edit_posts' );
		};

		// Prix unique. Ignoré dès qu'une grille tarifaire existe pour le
		// produit (voir Domain\ProductRepository::resolve_price()).
		register_post_meta(
			self::PRODUIT,
			'jcmv_produit_prix',
			array(
				'type'              => 'number',
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => array( self::class, 'sanitize_amount' ),
				'auth_callback'     => $jcmv_can_edit,
			)
		);

		// Saisie libre (« blanc, bleu ») : le coloris ne fait pas varier le
		// prix (ADR-005), il n'a donc pas besoin d'être un référentiel.
		register_post_meta(
			self::PRODUIT,
			'jcmv_produit_coloris',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $jcmv_can_edit,
			)
		);

		register_post_meta(
			self::PRODUIT,
			'jcmv_produit_dispo',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => array_key_first( self::DISPONIBILITES ),
				'show_in_rest'      => true,
				'sanitize_callback' => array( self::class, 'sanitize_dispo' ),
				'auth_callback'     => $jcmv_can_edit,
			)
		);

		// Photos complémentaires (dos, détail du flocage). L'ordre est celui
		// de saisie : un tableau, donc, et non des valeurs multiples — les
		// metas non uniques ne garantissent pas l'ordre de restitution.
		register_post_meta(
			self::PRODUIT,
			'jcmv_produit_galerie',
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
				),
				'sanitize_callback' => array( self::class, 'sanitize_gallery' ),
				'auth_callback'     => $jcmv_can_edit,
			)
		);

		register_post_meta(
			self::PARTENAIRE,
			'jcmv_partenaire_url',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => static function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			self::LIEU,
			'jcmv_adresse',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_textarea_field',
				'auth_callback'     => static function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Montant en euros, jamais négatif, arrondi au centime.
	 *
	 * La virgule décimale est acceptée : c'est ce que produit un clavier
	 * français, et un champ number la refuse silencieusement selon la locale
	 * du navigateur. Mieux vaut l'accepter ici que perdre la saisie.
	 *
	 * @param mixed $value Valeur brute.
	 */
	public static function sanitize_amount( $value ): float {
		$value = str_replace( ',', '.', (string) $value );

		return round( max( 0, (float) $value ), 2 );
	}

	/**
	 * @param mixed $value Valeur brute.
	 */
	public static function sanitize_dispo( $value ): string {
		$value = sanitize_key( (string) $value );

		return isset( self::DISPONIBILITES[ $value ] ) ? $value : (string) array_key_first( self::DISPONIBILITES );
	}

	/**
	 * IDs d'attachements : entiers positifs, dédoublonnés, plafonnés.
	 *
	 * @param mixed $value Valeur brute.
	 * @return array<int, int>
	 */
	public static function sanitize_gallery( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array_filter( array_map( 'absint', $value ) );

		return array_values( array_slice( array_unique( $ids ), 0, self::GALERIE_MAX ) );
	}
}
