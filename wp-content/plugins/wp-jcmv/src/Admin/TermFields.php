<?php
/**
 * Champs age_min / age_max sur les termes de jcmv_categorie_age
 * (ADR-002, niveau 2 : formulaires PHP natifs, pas de JS).
 *
 * @package wp-jcmv
 */

namespace JCMV\Admin;

use JCMV\Registration\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TermFields {

	private const NONCE = 'jcmv_age_fields';

	public static function register(): void {
		$tax = Taxonomies::CATEGORIE_AGE;
		add_action( "{$tax}_add_form_fields", array( self::class, 'render_add' ) );
		add_action( "{$tax}_edit_form_fields", array( self::class, 'render_edit' ) );
		add_action( "created_{$tax}", array( self::class, 'save' ) );
		add_action( "edited_{$tax}", array( self::class, 'save' ) );
	}

	public static function render_add(): void {
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<div class="form-field">
			<label for="jcmv-age-min"><?php esc_html_e( 'Âge minimum', 'wp-jcmv' ); ?></label>
			<input type="number" id="jcmv-age-min" name="age_min" min="0" max="99" value="">
		</div>
		<div class="form-field">
			<label for="jcmv-age-max"><?php esc_html_e( 'Âge maximum', 'wp-jcmv' ); ?></label>
			<input type="number" id="jcmv-age-max" name="age_max" min="0" max="99" value="">
			<p><?php esc_html_e( 'Bornes indicatives : elles servent à calculer les années de naissance affichées, à partir de l\'année de début de la saison active.', 'wp-jcmv' ); ?></p>
		</div>
		<?php
	}

	public static function render_edit( \WP_Term $term ): void {
		$age_min = (int) get_term_meta( $term->term_id, 'age_min', true );
		$age_max = (int) get_term_meta( $term->term_id, 'age_max', true );
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<tr class="form-field">
			<th scope="row"><label for="jcmv-age-min"><?php esc_html_e( 'Âge minimum', 'wp-jcmv' ); ?></label></th>
			<td><input type="number" id="jcmv-age-min" name="age_min" min="0" max="99" value="<?php echo esc_attr( (string) $age_min ); ?>"></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="jcmv-age-max"><?php esc_html_e( 'Âge maximum', 'wp-jcmv' ); ?></label></th>
			<td>
				<input type="number" id="jcmv-age-max" name="age_max" min="0" max="99" value="<?php echo esc_attr( (string) $age_max ); ?>">
				<p class="description"><?php esc_html_e( 'Bornes indicatives : elles servent à calculer les années de naissance affichées, à partir de l\'année de début de la saison active.', 'wp-jcmv' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public static function save( int $term_id ): void {
		if ( ! isset( $_POST[ self::NONCE ] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE )
			|| ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		foreach ( array( 'age_min', 'age_max' ) as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_term_meta( $term_id, $field, absint( wp_unslash( $_POST[ $field ] ) ) );
			}
		}
	}
}
