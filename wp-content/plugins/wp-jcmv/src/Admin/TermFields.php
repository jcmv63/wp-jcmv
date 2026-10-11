<?php
/**
 * Champs de paramétrage sur les écrans de termes
 * (ADR-002, niveau 2 : formulaires PHP natifs, pas de JS).
 *
 * - jcmv_categorie_age   : age_min / age_max, couleur facultative
 * - jcmv_systeme_taille  : liste ordonnée des tailles du système (ADR-005)
 *
 * @package wp-jcmv
 */

namespace JCMV\Admin;

use JCMV\Domain\AgeColors;
use JCMV\Domain\Sizes;
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
		add_filter( "manage_edit-{$tax}_columns", array( self::class, 'add_color_column' ) );
		add_filter( "manage_{$tax}_custom_column", array( self::class, 'render_color_column' ), 10, 3 );

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
		<div class="form-field">
			<?php self::render_color_inputs( '' ); ?>
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
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Couleur', 'wp-jcmv' ); ?></th>
			<td><?php self::render_color_inputs( self::color_of( $term->term_id ) ); ?></td>
		</tr>
		<?php
	}

	/* --- Couleur d'une catégorie d'âge --- */

	/**
	 * Valeur affichée par le sélecteur quand la catégorie n'a pas de couleur :
	 * le rouge de « Compétition », c'est-à-dire ce que le liseré montre déjà.
	 */
	private const COULEUR_PAR_DEFAUT = '#ee3435';

	/**
	 * Sélecteur natif + case « Aucune couleur », sans JavaScript (ADR-002,
	 * niveau 2).
	 *
	 * Un `<input type="color">` a toujours une valeur : il ne sait pas dire
	 * « aucune couleur ». La première version confiait ce choix à une case
	 * « activer » qu'il fallait cocher EN PLUS de choisir la couleur — choisir
	 * seulement la couleur l'effaçait à l'enregistrement, sans message. Le
	 * sens est désormais inversé et l'intention lue dans la saisie (save()) :
	 *
	 * - catégorie qui avait une couleur, « Aucune couleur » coché → la
	 *   couleur est supprimée, même si le sélecteur a aussi été touché ;
	 * - sinon, couleur changée → elle est enregistrée (la case, cochée
	 *   d'office pour une catégorie sans couleur, est alors ignorée) ;
	 * - sinon → rien ne change.
	 *
	 * Le champ caché `jcmv_couleur_initiale` porte la valeur affichée au
	 * chargement : c'est la seule façon, sans script, de distinguer « laissé
	 * sur le rouge par défaut » de « rouge choisi exprès ».
	 *
	 * @param string $color Couleur enregistrée, `#rrggbb` ou chaîne vide.
	 */
	private static function render_color_inputs( string $color ): void {
		$shown = '' !== $color ? $color : self::COULEUR_PAR_DEFAUT;
		?>
		<input type="color" id="jcmv-couleur" name="jcmv_couleur" value="<?php echo esc_attr( $shown ); ?>"
			aria-describedby="jcmv-couleur-aide" style="display:block;width:64px;height:32px;padding:2px">
		<input type="hidden" name="jcmv_couleur_initiale" value="<?php echo esc_attr( '' !== $color ? $color : '' ); ?>">
		<label for="jcmv-couleur-aucune" style="display:block;margin-top:6px">
			<input type="checkbox" id="jcmv-couleur-aucune" name="jcmv_couleur_aucune" value="1" <?php checked( '' === $color ); ?>>
			<?php esc_html_e( 'Aucune couleur (liseré rouge de « Compétition »)', 'wp-jcmv' ); ?>
		</label>
		<p class="description" id="jcmv-couleur-aide"><?php esc_html_e( 'Couleur du liseré gauche des compétitions de cette seule catégorie d\'âge, dans le calendrier. Choisir une couleur suffit : elle remplace « Aucune couleur ». Pour revenir au rouge par défaut, cocher « Aucune couleur ». Les teintes très claires (blanc, jaune pâle) se voient peu sur le fond rose des compétitions.', 'wp-jcmv' ); ?></p>
		<?php
	}

	private static function color_of( int $term_id ): string {
		return AgeColors::of( $term_id );
	}

	/**
	 * @param array<string,string> $columns Colonnes de la liste des termes.
	 * @return array<string,string>
	 */
	public static function add_color_column( array $columns ): array {
		$columns['jcmv_couleur'] = __( 'Couleur', 'wp-jcmv' );

		return $columns;
	}

	/**
	 * Pastille de couleur dans la liste des catégories d'âge, pour voir d'un
	 * coup d'œil lesquelles en ont une.
	 *
	 * @param string $content Contenu déjà calculé de la cellule.
	 * @param string $column  Identifiant de colonne.
	 * @param int    $term_id Terme de la ligne.
	 */
	public static function render_color_column( $content, $column, $term_id ): string {
		if ( 'jcmv_couleur' !== $column ) {
			return (string) $content;
		}

		$color = self::color_of( (int) $term_id );

		if ( '' === $color ) {
			return '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Aucune couleur', 'wp-jcmv' ) . '</span>';
		}

		return sprintf(
			'<span style="display:inline-block;width:16px;height:16px;border-radius:4px;vertical-align:middle;margin-right:6px;box-shadow:inset 0 0 0 1px rgba(0,0,0,.15);background:%1$s"></span><code>%1$s</code>',
			esc_attr( $color )
		);
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

		self::save_color( $term_id );
	}

	/**
	 * Enregistre la couleur selon l'intention lue dans la saisie (voir
	 * render_color_inputs()). Sans le champ caché — formulaire tiers, ou
	 * modification rapide qui n'envoie ni nonce ni champ —, rien n'est touché.
	 */
	private static function save_color( int $term_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce vérifié par save().
		if ( ! isset( $_POST['jcmv_couleur'], $_POST['jcmv_couleur_initiale'] ) ) {
			return;
		}

		$submitted = strtolower( (string) sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST['jcmv_couleur'] ) ) ) );
		$initial   = strtolower( (string) sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST['jcmv_couleur_initiale'] ) ) ) );
		$none      = ! empty( $_POST['jcmv_couleur_aucune'] );
		// phpcs:enable

		$shown_initially = '' !== $initial ? $initial : self::COULEUR_PAR_DEFAUT;
		$changed         = '' !== $submitted && $submitted !== $shown_initially;

		// La case n'exprime un choix que si elle a été cochée par
		// l'utilisateur, c'est-à-dire si la catégorie avait une couleur : pour
		// une catégorie sans couleur, elle est cochée d'office.
		if ( '' !== $initial && $none ) {
			delete_term_meta( $term_id, Taxonomies::META_COULEUR );
			return;
		}

		if ( $changed ) {
			update_term_meta( $term_id, Taxonomies::META_COULEUR, $submitted );
		}
		// Sinon : rien n'a changé.
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
		$tailles = Sizes::normalize( get_term_meta( $term->term_id, Taxonomies::META_TAILLES, true ) );
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
			Sizes::normalize( wp_unslash( $_POST['jcmv_tailles'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- assaini par Sizes::normalize().
		);
	}
}
