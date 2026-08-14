<?php
/**
 * Taxonomies du module club (ADR-001).
 *
 * - jcmv_discipline     : judo, cross-training… Le bureau peut créer un terme
 *                         (ex. taïso) sans développeur.
 * - jcmv_categorie_age  : référentiel FFJDA, slugs immuables, bornes d'âge en
 *                         term meta (éditables dans l'admin, voir Admin\TermFields).
 *                         Également enregistrée sur le CPT de The Events Calendar
 *                         si présent (« compétition cadets »).
 * - jcmv_famille        : famille de produits de la boutique (ADR-005) —
 *                         classement d'affichage, orienté visiteur.
 * - jcmv_systeme_taille : système de tailles d'un produit (ADR-005) —
 *                         orienté saisie, jamais affiché ni filtré.
 *
 * Les deux axes de la boutique sont volontairement distincts : une famille
 * « Textile » peut contenir des produits en tailles françaises comme en
 * tailles internationales. Les avoir confondus a été une erreur de
 * conception, corrigée ici.
 *
 * Ni l'une ni l'autre n'est seedée : leurs termes sont des décisions du
 * bureau, prises sur le catalogue du fournisseur. Seuls les référentiels
 * externes partagés par tous les clubs (disciplines, catégories FFJDA) le
 * sont.
 *
 * @package wp-jcmv
 */

namespace JCMV\Registration;

use JCMV\Domain\AgeOrder;
use JCMV\Domain\Sizes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Taxonomies {

	public const DISCIPLINE      = 'jcmv_discipline';
	public const CATEGORIE_AGE   = 'jcmv_categorie_age';
	public const FAMILLE         = 'jcmv_famille';
	public const SYSTEME_TAILLE  = 'jcmv_systeme_taille';

	/** Term meta portant les tailles d'un système, dans l'ordre d'affichage. */
	public const META_TAILLES = 'jcmv_tailles';

	/** CPT de The Events Calendar. */
	private const TEC_CPT = 'tribe_events';

	public static function register(): void {
		register_taxonomy(
			self::DISCIPLINE,
			array( PostTypes::COURS ),
			array(
				'labels'            => array(
					'name'          => __( 'Disciplines', 'wp-jcmv' ),
					'singular_name' => __( 'Discipline', 'wp-jcmv' ),
					'add_new_item'  => __( 'Ajouter une discipline', 'wp-jcmv' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'hierarchical'      => true,
				'rewrite'           => false,
				'meta_box_cb'       => array( self::class, 'discipline_radio_metabox' ),
			)
		);

		/*
		 * Choix multiple, contrairement aux trois autres taxonomies d'ici : un
		 * cours ou une compétition s'adresse couramment à plusieurs catégories
		 * (« benjamins, minimes, cadets »). D'où la liste de cases à cocher du
		 * cœur, conservée telle quelle — mais rangée par âge, voir
		 * categorie_age_metabox().
		 */
		register_taxonomy(
			self::CATEGORIE_AGE,
			array( PostTypes::COURS ),
			array(
				'labels'            => array(
					'name'          => __( 'Catégories d\'âge', 'wp-jcmv' ),
					'singular_name' => __( 'Catégorie d\'âge', 'wp-jcmv' ),
					'add_new_item'  => __( 'Ajouter une catégorie d\'âge', 'wp-jcmv' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'hierarchical'      => true,
				'rewrite'           => false,
				'meta_box_cb'       => array( self::class, 'categorie_age_metabox' ),
			)
		);

		/*
		 * Famille de produits : Textile, Judogis, Accessoires… C'est le
		 * classement d'affichage, celui qui sert au visiteur à s'orienter et
		 * que filtre le bloc jcmv/boutique.
		 *
		 * hierarchical => true SANS hiérarchie réelle, et ce n'est pas une
		 * coquetterie : wp_set_post_terms() ne convertit `tax_input` en IDs de
		 * termes que pour les taxonomies hiérarchiques
		 * (`array_map( 'intval', $terms )`). Sur une taxonomie plate, la valeur
		 * « 19 » postée par un bouton radio arrive en chaîne, n'est trouvée ni
		 * par slug ni par nom, et wp_insert_term() crée alors un terme
		 * NOMMÉ « 19 ». Même réglage que jcmv_discipline, plate elle aussi.
		 *
		 * Choix unique : deux familles feraient apparaître le même produit
		 * dans deux grilles, ce qui n'est pas un besoin de catalogue mais de
		 * mise en avant — un autre concept, à traiter autrement le jour venu.
		 */
		register_taxonomy(
			self::FAMILLE,
			array( PostTypes::PRODUIT ),
			array(
				'labels'             => array(
					'name'          => __( 'Familles', 'wp-jcmv' ),
					'singular_name' => __( 'Famille', 'wp-jcmv' ),
					'add_new_item'  => __( 'Ajouter une famille', 'wp-jcmv' ),
					'not_found'     => __( 'Aucune famille trouvée.', 'wp-jcmv' ),
				),
				'public'             => false,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'show_admin_column'  => true,
				// La modification rapide ignore meta_box_cb et affiche une liste
				// de cases à cocher : elle permettrait de choisir deux familles,
				// en contradiction avec la metabox. On la retire plutôt que de
				// proposer deux interfaces qui ne disent pas la même chose.
				'show_in_quick_edit' => false,
				'hierarchical'       => true,
				'rewrite'            => false,
				'meta_box_cb'        => array( self::class, 'famille_radio_metabox' ),
			)
		);

		/*
		 * Système de tailles : « Taille internationale » (S, M, L…), « Taille
		 * judogi » (110, 120…), « Pointures ». Purement interne — il pilote les
		 * cases à cocher de la fiche produit et n'apparaît jamais sur le site.
		 *
		 * D'où show_admin_column => false : la colonne serait du bruit dans la
		 * liste des produits, alors que la famille, elle, mérite la sienne.
		 */
		register_taxonomy(
			self::SYSTEME_TAILLE,
			array( PostTypes::PRODUIT ),
			array(
				'labels'             => array(
					'name'          => __( 'Systèmes de tailles', 'wp-jcmv' ),
					'singular_name' => __( 'Système de tailles', 'wp-jcmv' ),
					'add_new_item'  => __( 'Ajouter un système de tailles', 'wp-jcmv' ),
					'not_found'     => __( 'Aucun système de tailles trouvé.', 'wp-jcmv' ),
				),
				'public'             => false,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'show_admin_column'  => false,
				'show_in_quick_edit' => false,
				'hierarchical'       => true,
				'rewrite'            => false,
				'meta_box_cb'        => array( self::class, 'systeme_taille_radio_metabox' ),
			)
		);

		/*
		 * Les tailles du système, ordonnées : « 110, 120, 130… ». L'ordre est
		 * l'information principale — aucun tri ne classe « 10 ans, S, M, L ».
		 *
		 * C'est une source de saisie, pas une référence : le produit enregistre
		 * ses propres libellés (postmeta jcmv_produit_tailles), si bien que
		 * modifier un système n'altère jamais un produit existant.
		 *
		 * show_in_rest => false : la donnée ne sert qu'à peupler l'écran
		 * d'édition d'un produit, jamais le front ni l'app Saisons.
		 */
		register_term_meta(
			self::SYSTEME_TAILLE,
			self::META_TAILLES,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'show_in_rest'      => false,
				'sanitize_callback' => array( Sizes::class, 'normalize' ),
			)
		);

		// Bornes d'âge (indicatives, servent au calcul des années de naissance
		// depuis start_year de la saison — jamais depuis la date du jour).
		foreach ( array( 'age_min', 'age_max' ) as $meta ) {
			register_term_meta(
				self::CATEGORIE_AGE,
				$meta,
				array(
					'type'              => 'integer',
					'single'            => true,
					'default'           => 0,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
				)
			);
		}

		// The Events Calendar : liaison des événements aux catégories d'âge.
		if ( post_type_exists( self::TEC_CPT ) ) {
			register_taxonomy_for_object_type( self::CATEGORIE_AGE, self::TEC_CPT );
		}

		/*
		 * Filet de sécurité au niveau de la donnée. Retirer la modification
		 * rapide ferme le chemin que le bureau emprunte, mais pas l'API REST ni
		 * WP-CLI, tous deux ouverts sur ce CPT. La règle « un seul terme » ne
		 * doit pas dépendre de l'interface qui a servi à écrire.
		 *
		 * Priorité tardive : tax_input est traité par wp_insert_post() avant
		 * que save_post ne se déclenche, et d'autres extensions pourraient
		 * écrire des termes sur ce hook.
		 */
		add_action( 'save_post_' . PostTypes::PRODUIT, array( self::class, 'enforce_single_term' ), 20 );
	}

	/**
	 * Ramène famille et système de tailles à un terme au plus.
	 *
	 * En cas de pluralité, on garde le premier de la liste renvoyée par
	 * WordPress — triée par nom. Un tri arbitraire resterait arbitraire, mais
	 * au moins il est reproductible : deux enregistrements successifs donnent
	 * le même résultat, et un correctif manuel n'est pas défait au suivant.
	 *
	 * @param int $post_id ID du produit enregistré.
	 */
	public static function enforce_single_term( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		foreach ( array( self::FAMILLE, self::SYSTEME_TAILLE ) as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );

			if ( is_wp_error( $terms ) || count( $terms ) < 2 ) {
				continue;
			}

			// wp_set_object_terms() ne déclenche pas save_post : pas de
			// récursion à craindre ici.
			wp_set_object_terms( $post_id, array( (int) $terms[0] ), $taxonomy );
		}
	}

	/**
	 * Metabox des catégories d'âge : la liste de cases du cœur, mais rangée
	 * dans l'ordre des bornes d'âge plutôt qu'alphabétiquement.
	 *
	 * Rien n'est redessiné ici. post_categories_meta_box() est appelée telle
	 * quelle, encadrée de deux filtres posés puis retirés : le balisage reste
	 * donc celui de WordPress, et tout ce qui s'y accroche continue de
	 * fonctionner sans qu'on ait à s'en soucier — le terme principal de Yoast,
	 * l'onglet « Plus utilisés », l'ajout d'un terme à la volée.
	 *
	 * La portée est volontairement limitée à cette metabox. La modification
	 * rapide ignore meta_box_cb (voir le commentaire de jcmv_famille plus
	 * haut) et restera alphabétique : elle n'est pas utilisée par le bureau,
	 * on ne paie pas un filtre global pour elle.
	 *
	 * Vaut pour les deux écrans qui portent la taxonomie, cours et événement :
	 * meta_box_cb est une propriété de la taxonomie, pas du type de contenu.
	 *
	 * @param \WP_Post             $post Post en cours d'édition.
	 * @param array<string, mixed> $box  Arguments de la metabox, dont taxonomy.
	 */
	public static function categorie_age_metabox( \WP_Post $post, array $box ): void {
		add_filter( 'get_terms', array( self::class, 'sort_categorie_age_terms' ), 10, 3 );
		add_filter( 'wp_terms_checklist_args', array( self::class, 'unstick_checked_terms' ) );

		post_categories_meta_box( $post, $box );

		remove_filter( 'get_terms', array( self::class, 'sort_categorie_age_terms' ), 10 );
		remove_filter( 'wp_terms_checklist_args', array( self::class, 'unstick_checked_terms' ) );
	}

	/**
	 * Classe les catégories d'âge servies à la liste de cases à cocher.
	 *
	 * Le filtre `get_terms` est large : il voit passer toutes les requêtes de
	 * termes de la page, y compris celles des autres metabox. D'où la double
	 * condition.
	 *
	 * `get => 'all'` est la signature exacte de la requête de
	 * wp_terms_checklist(). Elle sert aussi à épargner l'onglet « Plus
	 * utilisés » : wp_popular_terms_checklist() interroge la même taxonomie
	 * mais avec `orderby => 'count'`, et le classer par âge lui retirerait
	 * précisément ce qui en fait un raccourci.
	 *
	 * Aucun type déclaré sur les paramètres, à dessein : `taxonomy` vaut null
	 * dans WP_Term_Query quand la requête n'en précise aucune, et une requête
	 * de ce genre passant par là pendant le rendu ferait une TypeError, donc un
	 * écran d'administration blanc. Sur un filtre du cœur, on valide plutôt
	 * qu'on ne contraint.
	 *
	 * @param mixed $terms      Résultat de la requête de termes.
	 * @param mixed $taxonomies Taxonomies interrogées.
	 * @param mixed $args       Arguments normalisés de WP_Term_Query.
	 * @return mixed
	 */
	public static function sort_categorie_age_terms( $terms, $taxonomies, $args = array() ) {
		if ( ! is_array( $terms ) || array( self::CATEGORIE_AGE ) !== $taxonomies ) {
			return $terms;
		}

		if ( ! is_array( $args ) || 'all' !== ( $args['get'] ?? '' ) ) {
			return $terms;
		}

		// `fields => ids|names|count` renverrait des scalaires, que le
		// comparateur ne saurait pas classer. Le cas ne se présente pas depuis
		// la liste de cases, mais le filtre est public : on ne suppose rien.
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				return $terms;
			}
		}

		return AgeOrder::sort( $terms );
	}

	/**
	 * Empêche les cases cochées de remonter en tête de liste.
	 *
	 * Sans cela, l'ordre par âge tiendrait à l'écran de création puis se
	 * défairait à la première modification. The Events Calendar pose déjà ce
	 * réglage, mais uniquement sur ses propres événements
	 * (`prevent_checked_on_top_terms()`, conditionné à `tribe_is_event()`) :
	 * l'écran d'un cours n'en bénéficie pas, et rien ne garantit que ce filtre
	 * interne survive à une mise à jour du plugin.
	 *
	 * @param mixed $args Arguments de wp_terms_checklist().
	 * @return mixed
	 */
	public static function unstick_checked_terms( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		$args['checked_ontop'] = false;

		return $args;
	}

	/**
	 * Metabox de la discipline en boutons radio : un cours est rattaché à
	 * UNE discipline (ADR-001). WordPress sauvegarde via tax_input.
	 */
	public static function discipline_radio_metabox( \WP_Post $post ): void {
		self::radio_metabox( $post, self::DISCIPLINE, __( 'Aucune discipline définie.', 'wp-jcmv' ) );
	}

	/**
	 * Metabox de la famille en boutons radio (ADR-005).
	 */
	public static function famille_radio_metabox( \WP_Post $post ): void {
		self::radio_metabox(
			$post,
			self::FAMILLE,
			__( 'Aucune famille définie. JCMV → Famille produit.', 'wp-jcmv' ),
			'jcmv-famille-choix',
			true
		);
	}

	/**
	 * Metabox du système de tailles en boutons radio (ADR-005).
	 *
	 * La classe jcmv-systeme-choix est lue par le script de la metabox produit
	 * pour reconstruire les cases de tailles quand le système change.
	 */
	public static function systeme_taille_radio_metabox( \WP_Post $post ): void {
		self::radio_metabox(
			$post,
			self::SYSTEME_TAILLE,
			__( 'Aucun système de tailles défini. JCMV → Taille produit.', 'wp-jcmv' ),
			'jcmv-systeme-choix',
			true
		);
	}

	/**
	 * Rendu commun des metabox de taxonomie à choix unique.
	 *
	 * `tax_input[taxonomie][]` reste un tableau malgré le choix unique : c'est
	 * le format qu'attend WordPress, et des boutons radio n'en émettent qu'une
	 * seule valeur de toute façon.
	 *
	 * ATTENTION : ce patron n'est valide que sur une taxonomie déclarée
	 * `hierarchical => true`. Ailleurs, wp_set_post_terms() interprète l'ID
	 * posté comme un NOM de terme et en crée un nouveau (voir le commentaire
	 * de register_taxonomy plus haut).
	 *
	 * @param \WP_Post $post       Post en cours d'édition.
	 * @param string   $taxonomy   Slug de la taxonomie.
	 * @param string   $empty      Message affiché si aucun terme n'existe.
	 * @param string   $class      Classe CSS facultative sur la liste.
	 * @param bool     $allow_none Ajoute un choix « Aucune » en tête.
	 */
	private static function radio_metabox( \WP_Post $post, string $taxonomy, string $empty, string $class = '', bool $allow_none = false ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			echo '<p>' . esc_html( $empty ) . '</p>';
			return;
		}

		$current = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
		$current = ( ! is_wp_error( $current ) && $current ) ? (int) $current[0] : 0;

		printf( '<ul style="margin:0" class="%s">', esc_attr( $class ) );

		/*
		 * Un groupe de boutons radio ne se déselectionne pas : sans ce choix,
		 * un clic malencontreux serait définitif. La valeur 0 traverse
		 * intval() sans dommage, term_exists( 0 ) ne trouve rien, et
		 * wp_set_object_terms() retire alors le terme existant.
		 */
		if ( $allow_none ) {
			printf(
				'<li><label><input type="radio" name="tax_input[%s][]" value="0" %s> <em>%s</em></label></li>',
				esc_attr( $taxonomy ),
				checked( $current, 0, false ),
				esc_html__( 'Aucune', 'wp-jcmv' )
			);
		}

		foreach ( $terms as $term ) {
			printf(
				'<li><label><input type="radio" name="tax_input[%s][]" value="%d" %s> %s</label></li>',
				esc_attr( $taxonomy ),
				(int) $term->term_id,
				checked( $current, (int) $term->term_id, false ),
				esc_html( $term->name )
			);
		}
		echo '</ul>';
	}
}
