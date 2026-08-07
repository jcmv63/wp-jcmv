<?php
/**
 * Champs de paramétrage sur les écrans de termes
 * (ADR-002, niveau 2 : formulaires PHP natifs, pas de JS).
 *
 * - jcmv_categorie_age   : age_min / age_max
 * - jcmv_systeme_taille  : liste ordonnée des tailles du système (ADR-005)
 *
 * @package wp-jcmv
 */

namespace JCMV\Admin;

use JCMV\Registration\PostTypes;
use JCMV\Registration\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TermFields {

	private const NONCE = 'jcmv_age_fields';

	private const NONCE_TAILLES = 'jcmv_tailles_fields';

	public static function register(): void {
		$tax = Taxonomies::CATEGORIE_AGE;
		add_action( "{$tax}_add_form_fields", array( self::class, 'render_add' ) );
		add_action( "{$tax}_edit_form_fields", array( self::class, 'render_edit' ) );
		add_action( "created_{$tax}", array( self::class, 'save' ) );
		add_action( "edited_{$tax}", array( self::class, 'save' ) );

		$systeme = Taxonomies::SYSTEME_TAILLE;
		add_action( "{$systeme}_add_form_fields", array( self::class, 'render_tailles_add' ) );
		add_action( "{$systeme}_edit_form_fields", array( self::class, 'render_tailles_edit' ) );
		add_action( "created_{$systeme}", array( self::class, 'save_tailles' ) );
		add_action( "edited_{$systeme}", array( self::class, 'save_tailles' ) );
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

	/* --- Tailles d'un système de tailles (ADR-005) --- */

	/**
	 * Texte d'aide, identique aux deux écrans : c'est là que se joue la
	 * compréhension du mécanisme par le bureau.
	 */
	private static function tailles_help(): string {
		return __( 'Tailles de ce système, séparées par des virgules, dans l\'ordre du plus petit au plus grand — c\'est cet ordre qui sera repris sur le site. Exemples : « S, M, L, XL » pour des tailles internationales, « 110, 120, 130 » pour des judogis, « 38, 40, 42 » pour des pointures.', 'wp-jcmv' );
	}

	private static function tailles_note(): string {
		return __( 'Modifier cette liste ne change aucun produit déjà enregistré : chaque produit conserve les tailles qui lui ont été cochées.', 'wp-jcmv' );
	}

	public static function render_tailles_add(): void {
		wp_nonce_field( self::NONCE_TAILLES, self::NONCE_TAILLES );
		?>
		<div class="form-field">
			<label for="jcmv-tailles"><?php esc_html_e( 'Tailles', 'wp-jcmv' ); ?></label>
			<input type="text" id="jcmv-tailles" name="jcmv_tailles" value="">
			<p><?php echo esc_html( self::tailles_help() ); ?></p>
		</div>
		<?php
	}

	public static function render_tailles_edit( \WP_Term $term ): void {
		$tailles = PostTypes::sanitize_sizes( get_term_meta( $term->term_id, Taxonomies::META_TAILLES, true ) );
		wp_nonce_field( self::NONCE_TAILLES, self::NONCE_TAILLES );
		?>
		<tr class="form-field">
			<th scope="row"><label for="jcmv-tailles"><?php esc_html_e( 'Tailles', 'wp-jcmv' ); ?></label></th>
			<td>
				<input type="text" id="jcmv-tailles" name="jcmv_tailles" class="large-text"
					value="<?php echo esc_attr( implode( ', ', $tailles ) ); ?>">
				<p class="description"><?php echo esc_html( self::tailles_help() ); ?></p>
				<p class="description"><?php echo esc_html( self::tailles_note() ); ?></p>
			</td>
		</tr>
		<?php
	}

	public static function save_tailles( int $term_id ): void {
		if ( ! isset( $_POST[ self::NONCE_TAILLES ] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_TAILLES ] ) ), self::NONCE_TAILLES )
			|| ! current_user_can( 'manage_categories' )
			|| ! isset( $_POST['jcmv_tailles'] ) ) {
			return;
		}

		update_term_meta(
			$term_id,
			Taxonomies::META_TAILLES,
			PostTypes::sanitize_sizes( wp_unslash( $_POST['jcmv_tailles'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- assaini par sanitize_sizes().
		);
	}
}
