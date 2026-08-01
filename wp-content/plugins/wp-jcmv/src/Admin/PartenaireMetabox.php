<?php
/**
 * Metabox « Site web » du CPT jcmv_partenaire (ADR-002, niveau 2 : au plus
 * une metabox si un champ apparaît).
 *
 * @package wp-jcmv
 */

namespace JCMV\Admin;

use JCMV\Registration\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PartenaireMetabox {

	/**
	 * Doit différer du name du champ : deux inputs de même name se recouvrent
	 * dans $_POST et wp_verify_nonce() reçoit alors la valeur du champ.
	 */
	private const NONCE = 'jcmv_partenaire_url_nonce';

	public static function register(): void {
		add_action( 'add_meta_boxes_' . PostTypes::PARTENAIRE, array( self::class, 'add' ) );
		add_action( 'save_post_' . PostTypes::PARTENAIRE, array( self::class, 'save' ) );
	}

	public static function add(): void {
		add_meta_box(
			'jcmv-partenaire-url',
			__( 'Site web du partenaire', 'wp-jcmv' ),
			array( self::class, 'render' ),
			PostTypes::PARTENAIRE,
			'normal',
			'high'
		);
	}

	public static function render( \WP_Post $post ): void {
		$url = (string) get_post_meta( $post->ID, 'jcmv_partenaire_url', true );
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<p>
			<label class="screen-reader-text" for="jcmv-partenaire-url"><?php esc_html_e( 'Adresse du site', 'wp-jcmv' ); ?></label>
			<input type="url" id="jcmv-partenaire-url" name="jcmv_partenaire_url" class="large-text"
				value="<?php echo esc_attr( $url ); ?>" placeholder="https://exemple.fr" />
		</p>
		<p class="description">
			<?php esc_html_e( 'Facultatif. Sans adresse, le logo est affiché sans lien.', 'wp-jcmv' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( "Le logo se règle dans « Image mise en avant » : sans logo, le partenaire n'apparaît pas sur le site.", 'wp-jcmv' ); ?>
		</p>
		<?php
	}

	public static function save( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE ] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE )
			|| defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE
			|| ! current_user_can( 'edit_post', $post_id )
			|| ! isset( $_POST['jcmv_partenaire_url'] ) ) {
			return;
		}

		update_post_meta( $post_id, 'jcmv_partenaire_url', esc_url_raw( wp_unslash( $_POST['jcmv_partenaire_url'] ) ) );
	}
}
