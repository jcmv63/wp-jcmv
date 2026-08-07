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
		/*
		 * Écran classique pour tous les CPT du module (ADR-002 niveau 1) : ce
		 * sont des fiches de données, pas des pages. Gutenberg y proposerait un
		 * canevas de mise en page à un objet qui n'en a pas, et reléguerait les
		 * champs métier sous un accordéon.
		 *
		 * jcmv_produit garde `editor` dans ses supports : l'écran classique
		 * fournit alors nativement un TinyMCE pour la description, et
		 * post_content reste post_content le jour d'une page produit publique.
		 */
		add_filter(
			'use_block_editor_for_post_type',
			static function ( bool $use, string $post_type ): bool {
				return in_array( $post_type, array( self::COURS, self::LIEU, self::PARTENAIRE, self::PRODUIT ), true ) ? false : $use;
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
				// Sans effet tant que show_in_menu pointe vers 'jcmv-club'
				// (menu_icon ne sert qu'aux entrées de premier niveau), mais
				// valide : dashicons-tshirt n'existe pas.
				'menu_icon'    => 'dashicons-products',
			)
		);

		$jcmv_can_edit = static function () {
			return current_user_can( 'edit_posts' );
		};

		// Un seul prix par produit (ADR-005). Un tarif qui varierait avec la
		// taille se traite en éclatant le produit (« Judogi enfant » /
		// « Judogi adulte »), pas en ajoutant une dimension au modèle.
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

		// Un produit, une couleur (ADR-005) : un t-shirt noir et un t-shirt
		// blanc sont deux produits. Le singulier n'est donc pas un hasard.
		register_post_meta(
			self::PRODUIT,
			'jcmv_produit_couleur',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $jcmv_can_edit,
			)
		);

		/*
		 * Tailles disponibles, dans l'ordre d'affichage. Des libellés, pas des
		 * identifiants : le système de tailles sert de source de saisie,
		 * jamais de référence (ADR-005). Modifier un système n'altère donc aucun
		 * produit existant — propriété indispensable le jour où des commandes
		 * figeront ces valeurs.
		 *
		 * Un tableau et non des metas multiples : l'ordre est l'information
		 * principale (« XS < S < M < L »), et les metas non uniques ne
		 * garantissent pas leur ordre de restitution.
		 */
		register_post_meta(
			self::PRODUIT,
			'jcmv_produit_tailles',
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
				'sanitize_callback' => array( self::class, 'sanitize_sizes' ),
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
	 * Libellés de tailles : nettoyés, dédoublonnés, **ordre préservé**.
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
	public static function sanitize_sizes( $value ): array {
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
	 * Forme normalisée d'un libellé de taille, pour comparaison seulement.
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
