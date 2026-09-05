<?php
/**
 * Écran de liste des produits (ADR-005).
 *
 * Une seule colonne, et une seule raison d'exister : un produit sans image mise
 * en avant est écarté du bloc boutique par le repository. La règle est bonne —
 * une carte aux quatre cinquièmes vide se lit comme un bug, et un catalogue
 * troué est pire qu'un catalogue court — mais elle était SILENCIEUSE. Le bureau
 * publiait une fiche, ne la voyait pas apparaître, et rien ne lui disait
 * pourquoi.
 *
 * D'où le choix de la liste plutôt que d'une notice sur l'écran d'édition :
 * une notice ne se voit qu'après coup, sur une fiche à la fois, alors que la
 * colonne montre tout le catalogue d'un coup d'œil et répond à la question
 * avant qu'elle ne soit posée.
 *
 * Classe distincte de ProduitMetabox : celle-ci traite l'écran d'édition, on
 * est ici sur l'écran de liste. Deux écrans, deux responsabilités.
 *
 * @package wp-jcmv
 */

namespace JCMV\Admin;

use JCMV\Registration\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProduitListe {

	private const COLONNE = 'jcmv_photo';

	public static function register(): void {
		add_filter( 'manage_' . PostTypes::PRODUIT . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . PostTypes::PRODUIT . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Réutilise la feuille de la metabox plutôt que d'en créer une seconde pour
	 * quinze lignes. Le même identifiant est déclaré des deux côtés : WordPress
	 * dédoublonne, et les deux écrans ne coexistent jamais.
	 */
	public static function enqueue( string $hook ): void {
		if ( 'edit.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || PostTypes::PRODUIT !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'jcmv-produit-admin',
			JCMV_PLUGIN_URL . 'assets/css/produit-metabox.css',
			array(),
			JCMV_VERSION
		);
	}

	/**
	 * Insère « Photo » juste avant le titre.
	 *
	 * Position choisie, pas par défaut : la colonne répond à « pourquoi ce
	 * produit n'apparaît-il pas ? », question qu'on se pose en balayant la
	 * liste. En queue de tableau, derrière la famille et la date, elle ne serait
	 * jamais lue.
	 *
	 * Aucun type déclaré : le filtre est public, et une extension tierce peut y
	 * faire passer autre chose qu'un tableau. Sur un écran d'administration, on
	 * valide plutôt qu'on ne contraint — une TypeError ici, c'est une page
	 * blanche.
	 *
	 * @param mixed $columns Colonnes de l'écran.
	 * @return mixed
	 */
	public static function columns( $columns ) {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}

		if ( ! isset( $columns['title'] ) ) {
			$columns[ self::COLONNE ] = __( 'Photo', 'wp-jcmv' );

			return $columns;
		}

		$out = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$out[ self::COLONNE ] = __( 'Photo', 'wp-jcmv' );
			}

			$out[ $key ] = $label;
		}

		return $out;
	}

	/**
	 * @param mixed $column  Identifiant de la colonne rendue.
	 * @param mixed $post_id Produit de la ligne.
	 */
	public static function column( $column, $post_id ): void {
		if ( self::COLONNE !== $column ) {
			return;
		}

		$post_id  = (int) $post_id;
		$photo_id = (int) get_post_thumbnail_id( $post_id );

		if ( $photo_id && wp_attachment_is_image( $photo_id ) ) {
			printf(
				'<span class="jcmv-colonne-photo">%s</span>',
				wp_get_attachment_image( $photo_id, array( 60, 60 ), false, array( 'alt' => '' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par wp_get_attachment_image().
			);

			return;
		}

		/*
		 * Le message dépend du statut. Sur un brouillon, « n'apparaît pas sur le
		 * site » serait faux — aucun brouillon n'apparaît — et l'avertissement
		 * deviendrait du bruit qu'on apprend à ignorer, ce qui le rendrait
		 * inopérant là où il compte vraiment.
		 */
		$message = 'publish' === get_post_status( $post_id )
			? __( 'Sans photo : n\'apparaît pas sur le site.', 'wp-jcmv' )
			: __( 'Sans photo', 'wp-jcmv' );

		printf(
			'<span class="jcmv-colonne-photo jcmv-colonne-photo--vide">%s</span>',
			esc_html( $message )
		);
	}
}
