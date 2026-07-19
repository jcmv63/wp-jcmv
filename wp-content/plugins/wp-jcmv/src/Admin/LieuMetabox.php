<?php
/**
 * Metabox « Adresse » du CPT jcmv_lieu (ADR-002, niveau 2 : au plus une
 * metabox si un champ apparaît).
 *
 * @package wp-jcmv
 */

namespace JCMV\Admin;

use JCMV\Registration\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LieuMetabox {

	private const NONCE = 'jcmv_lieu_adresse';

	public static function register(): void {
		add_action( 'add_meta_boxes_' . PostTypes::LIEU, array( self::class, 'add' ) );
		add_action( 'save_post_' . PostTypes::LIEU, array( self::class, 'save' ) );
	}

	public static function add(): void {
		add_meta_box(
			'jcmv-lieu-adresse',
			__( 'Adresse', 'wp-jcmv' ),
			array( self::class, 'render' ),
			PostTypes::LIEU,
			'normal',
			'high'
		);
	}

	public static function render( \WP_Post $post ): void {
		$adresse = (string) get_post_meta( $post->ID, 'jcmv_adresse', true );
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<p>
			<label class="screen-reader-text" for="jcmv-adresse"><?php esc_html_e( 'Adresse', 'wp-jcmv' ); ?></label>
			<textarea id="jcmv-adresse" name="jcmv_adresse" rows="3" class="large-text" placeholder="<?php esc_attr_e( "Rue Jean Zay\n63730 Les Martres-de-Veyre", 'wp-jcmv' ); ?>"><?php echo esc_textarea( $adresse ); ?></textarea>
		</p>
		<?php
	}

	public static function save( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE ] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE )
			|| defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE
			|| ! current_user_can( 'edit_post', $post_id )
			|| ! isset( $_POST['jcmv_adresse'] ) ) {
			return;
		}

		update_post_meta( $post_id, 'jcmv_adresse', sanitize_textarea_field( wp_unslash( $_POST['jcmv_adresse'] ) ) );
	}
}
