<?php
/**
 * Écran « Pied de page » : titres et liens des trois colonnes.
 *
 * Niveau 2 de l'ADR-002 — formulaire PHP classique (admin-post.php, nonce,
 * redirection après POST). Le besoin n'est pas intrinsèquement interactif :
 * quelques lignes retouchées une ou deux fois par an. Le JS n'apporte que le
 * confort (ajouter, supprimer, monter, descendre) et l'écran reste utilisable
 * sans lui — deux lignes vides sont toujours rendues en réserve, et une
 * sauvegarde en réaffiche deux.
 *
 * @package wp-jcmv
 */

namespace JCMV\Admin;

use JCMV\Domain\FooterLinksRepository;
use JCMV\Registration\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FooterPage {

	public const SLUG = 'jcmv-footer';

	/** Action admin-post.php et nom du nonce (distincts des noms de champs). */
	private const ACTION = 'jcmv_footer_liens';

	private const NONCE = 'jcmv_footer_liens_nonce';

	/** Lignes vides rendues en fin de colonne (chemin sans JavaScript). */
	private const SPARE_ROWS = 2;

	/**
	 * Suffixe de hook de l'écran, retenu à l'ajout du sous-menu.
	 *
	 * Il dépend du titre du menu parent (« JCMV » → jcmv_page_jcmv-footer) :
	 * le reconstruire à la main en ferait une constante que le renommage du
	 * menu casserait en silence, en désactivant JS et CSS de l'écran.
	 */
	private static string $hook = '';

	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_post' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Ajout du sous-menu — appelé par Admin\Menu, sur le hook admin_menu.
	 */
	public static function add_page(): void {
		$hook = add_submenu_page(
			Menu::SLUG,
			__( 'Pied de page', 'wp-jcmv' ),
			__( 'Pied de page', 'wp-jcmv' ),
			Capabilities::MANAGE,
			self::SLUG,
			array( self::class, 'render' )
		);

		self::$hook = is_string( $hook ) ? $hook : '';
	}

	public static function enqueue( string $hook ): void {
		if ( '' === self::$hook || $hook !== self::$hook ) {
			return;
		}

		wp_enqueue_style(
			'jcmv-footer-liens',
			JCMV_PLUGIN_URL . 'assets/css/footer-liens.css',
			array(),
			JCMV_VERSION
		);

		wp_enqueue_script(
			'jcmv-footer-liens',
			JCMV_PLUGIN_URL . 'assets/js/footer-liens.js',
			array(),
			JCMV_VERSION,
			true
		);

		wp_localize_script(
			'jcmv-footer-liens',
			'jcmvFooter',
			array(
				'max'  => FooterLinksRepository::MAX_LINKS,
				'i18n' => array(
					'add'    => __( 'Ajouter un lien', 'wp-jcmv' ),
					'remove' => __( 'Supprimer ce lien', 'wp-jcmv' ),
					'up'     => __( 'Monter ce lien', 'wp-jcmv' ),
					'down'   => __( 'Descendre ce lien', 'wp-jcmv' ),
				),
			)
		);
	}

	/**
	 * Réception du formulaire : capability, nonce, enregistrement, puis
	 * redirection (PRG — un rechargement ne doit pas rejouer l'écriture).
	 */
	public static function handle_post(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die(
				esc_html__( 'Droits insuffisants pour modifier le pied de page.', 'wp-jcmv' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::ACTION, self::NONCE );

		$input = ( isset( $_POST['jcmv_footer'] ) && is_array( $_POST['jcmv_footer'] ) )
			? wp_unslash( $_POST['jcmv_footer'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- assaini champ par champ dans FooterLinksRepository::save().
			: array();

		( new FooterLinksRepository() )->save( $input );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::SLUG,
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}

		$columns = ( new FooterLinksRepository() )->raw();
		$updated = isset( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple accusé de réception après redirection.
		?>
		<div class="wrap jcmv-footer-admin">
			<h1><?php esc_html_e( 'Pied de page', 'wp-jcmv' ); ?></h1>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Colonnes du pied de page enregistrées.', 'wp-jcmv' ); ?></p>
				</div>
			<?php endif; ?>

			<p class="description jcmv-footer-admin__intro">
				<?php esc_html_e( 'Les trois colonnes de liens affichées en bas de toutes les pages. Choisir une page du site pour un lien interne : il suivra la page même si son adresse change. Pour un lien externe, laisser « Lien externe » et saisir l\'adresse — il s\'ouvrira dans un nouvel onglet. Une ligne sans libellé ou sans cible est ignorée, et un lien vers une page dépubliée disparaît du pied de page.', 'wp-jcmv' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION, self::NONCE ); ?>

				<div class="jcmv-footer-cols">
					<?php foreach ( $columns as $index => $column ) : ?>
						<?php self::render_column( (int) $index, $column ); ?>
					<?php endforeach; ?>
				</div>

				<?php submit_button( __( 'Enregistrer', 'wp-jcmv' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @param array{titre:string, liens:array<int, array{label:string, page_id:int, url:string}>} $column Colonne.
	 */
	private static function render_column( int $index, array $column ): void {
		$rows = $column['liens'];

		for ( $i = 0; $i < self::SPARE_ROWS; $i++ ) {
			$rows[] = array(
				'label'   => '',
				'page_id' => 0,
				'url'     => '',
			);
		}

		$base = 'jcmv_footer[' . $index . ']';
		?>
		<fieldset class="jcmv-footer-col" data-col="<?php echo esc_attr( (string) $index ); ?>">
			<legend class="screen-reader-text">
				<?php
				/* translators: %d : numéro de la colonne du pied de page. */
				printf( esc_html__( 'Colonne %d', 'wp-jcmv' ), (int) $index + 1 );
				?>
			</legend>

			<p class="jcmv-footer-col__titre">
				<label>
					<span><?php esc_html_e( 'Titre de la colonne', 'wp-jcmv' ); ?></span>
					<input type="text" class="regular-text" maxlength="60"
						name="<?php echo esc_attr( $base . '[titre]' ); ?>"
						value="<?php echo esc_attr( $column['titre'] ); ?>">
				</label>
			</p>

			<div class="jcmv-footer-rows">
				<?php foreach ( $rows as $row_index => $row ) : ?>
					<?php self::render_row( $index, (int) $row_index, $row ); ?>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * @param array{label:string, page_id:int, url:string} $data Lien.
	 */
	private static function render_row( int $col, int $row, array $data ): void {
		$base = 'jcmv_footer[' . $col . '][liens][' . $row . ']';
		$id   = 'jcmv-footer-' . $col . '-' . $row;

		$pages = wp_dropdown_pages(
			array(
				'name'              => $base . '[page_id]',
				'id'                => $id . '-page',
				'class'             => 'jcmv-footer-row__page',
				'selected'          => $data['page_id'],
				'show_option_none'  => __( '— Lien externe —', 'wp-jcmv' ),
				'option_none_value' => '0',
				'post_status'       => 'publish',
				'echo'              => 0,
			)
		);
		?>
		<div class="jcmv-footer-row">
			<label class="jcmv-footer-row__field jcmv-footer-row__field--label">
				<span class="screen-reader-text"><?php esc_html_e( 'Libellé du lien', 'wp-jcmv' ); ?></span>
				<input type="text" maxlength="60"
					placeholder="<?php esc_attr_e( 'Libellé', 'wp-jcmv' ); ?>"
					name="<?php echo esc_attr( $base . '[label]' ); ?>"
					value="<?php echo esc_attr( $data['label'] ); ?>">
			</label>

			<?php if ( $pages ) : ?>
				<label class="jcmv-footer-row__field">
					<span class="screen-reader-text"><?php esc_html_e( 'Page du site', 'wp-jcmv' ); ?></span>
					<?php echo $pages; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par wp_dropdown_pages(). ?>
				</label>
			<?php else : ?>
				<?php // Aucune page publiée : le champ n'aurait rien à proposer. ?>
				<input type="hidden" name="<?php echo esc_attr( $base . '[page_id]' ); ?>" value="0">
			<?php endif; ?>

			<label class="jcmv-footer-row__field">
				<span class="screen-reader-text"><?php esc_html_e( 'Adresse du lien externe', 'wp-jcmv' ); ?></span>
				<input type="url" class="jcmv-footer-row__url"
					placeholder="https://"
					name="<?php echo esc_attr( $base . '[url]' ); ?>"
					value="<?php echo esc_attr( $data['url'] ); ?>">
			</label>
		</div>
		<?php
	}
}
