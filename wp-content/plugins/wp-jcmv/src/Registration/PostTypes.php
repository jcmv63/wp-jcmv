<?php
/**
 * CPT du module club (ADR-001, niveau « contenu administrable »).
 *
 * - jcmv_cours : le pivot du modèle (nom, description, ordre d'affichage).
 * - jcmv_lieu  : dojo / lieu de pratique (adresse en postmeta).
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

	public const COURS = 'jcmv_cours';
	public const LIEU  = 'jcmv_lieu';

	public static function register(): void {
		// Fiches simples (nom + description) : l'éditeur de blocs est
		// disproportionné ici. show_in_rest reste actif pour l'app Saisons.
		add_filter(
			'use_block_editor_for_post_type',
			static function ( bool $use, string $post_type ): bool {
				return in_array( $post_type, array( self::COURS, self::LIEU ), true ) ? false : $use;
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
}
