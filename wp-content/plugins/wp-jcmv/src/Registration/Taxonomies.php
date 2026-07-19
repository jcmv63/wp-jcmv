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
 *
 * @package wp-jcmv
 */

namespace JCMV\Registration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Taxonomies {

	public const DISCIPLINE    = 'jcmv_discipline';
	public const CATEGORIE_AGE = 'jcmv_categorie_age';

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
	}

	/**
	 * Metabox de la discipline en boutons radio : un cours est rattaché à
	 * UNE discipline (ADR-001). WordPress sauvegarde via tax_input.
	 */
	public static function discipline_radio_metabox( \WP_Post $post ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => self::DISCIPLINE,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			echo '<p>' . esc_html__( 'Aucune discipline définie.', 'wp-jcmv' ) . '</p>';
			return;
		}

		$current = wp_get_object_terms( $post->ID, self::DISCIPLINE, array( 'fields' => 'ids' ) );
		$current = ( ! is_wp_error( $current ) && $current ) ? (int) $current[0] : 0;

		echo '<ul style="margin:0">';
		foreach ( $terms as $term ) {
			printf(
				'<li><label><input type="radio" name="tax_input[%s][]" value="%d" %s> %s</label></li>',
				esc_attr( self::DISCIPLINE ),
				(int) $term->term_id,
				checked( $current, (int) $term->term_id, false ),
				esc_html( $term->name )
			);
		}
		echo '</ul>';
	}
}
