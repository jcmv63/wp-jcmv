<?php
/**
 * Metabox du CPT jcmv_produit (ADR-005).
 *
 * Deux boîtes seulement, malgré cinq champs : « Prix et disponibilité »
 * regroupe ce qui change souvent, « Photos et tailles » ce qui ne bouge
 * qu'à la création. Le bureau n'ouvre donc qu'une boîte pour un
 * réétiquetage de début de saison.
 *
 * Le JS est vanilla et sans build (règle ADR-002, « pas d'usine à gaz ») :
 * ajout/suppression de lignes tarifaires et sélection de photos via
 * wp.media, que le core fournit déjà.
 *
 * @package wp-jcmv
 */

namespace JCMV\Admin;

use JCMV\Domain\ProductPriceRepository;
use JCMV\Registration\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProduitMetabox {

	/**
	 * Doit différer du name des champs : deux inputs de même name se
	 * recouvrent dans $_POST et wp_verify_nonce() reçoit alors la valeur du
	 * champ (leçon de PartenaireMetabox).
	 */
	private const NONCE = 'jcmv_produit_meta_nonce';

	public static function register(): void {
		add_action( 'add_meta_boxes_' . PostTypes::PRODUIT, array( self::class, 'add' ) );
		add_action( 'save_post_' . PostTypes::PRODUIT, array( self::class, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'admin_notices', array( self::class, 'notice' ) );
	}

	public static function add(): void {
		add_meta_box(
			'jcmv-produit-prix',
			__( 'Prix et disponibilité', 'wp-jcmv' ),
			array( self::class, 'render_prix' ),
			PostTypes::PRODUIT,
			'normal',
			'high'
		);

		add_meta_box(
			'jcmv-produit-medias',
			__( 'Photos et tailles', 'wp-jcmv' ),
			array( self::class, 'render_medias' ),
			PostTypes::PRODUIT,
			'normal',
			'default'
		);
	}

	/**
	 * Charge wp.media et le script de la metabox sur le seul écran d'édition
	 * d'un produit — jamais sur le reste de l'admin.
	 */
	public static function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen || PostTypes::PRODUIT !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'jcmv-produit-admin',
			JCMV_PLUGIN_URL . 'assets/js/produit-metabox.js',
			array( 'jquery' ),
			JCMV_VERSION,
			true
		);

		wp_localize_script(
			'jcmv-produit-admin',
			'jcmvProduit',
			array(
				'galerieMax'   => PostTypes::GALERIE_MAX,
				'mediaTitle'   => __( 'Photos du produit', 'wp-jcmv' ),
				'mediaButton'  => __( 'Utiliser ces photos', 'wp-jcmv' ),
				'confirmVider' => __( 'Retirer toutes les photos complémentaires ?', 'wp-jcmv' ),
			)
		);

		wp_enqueue_style(
			'jcmv-produit-admin',
			JCMV_PLUGIN_URL . 'assets/css/produit-metabox.css',
			array(),
			JCMV_VERSION
		);
	}

	public static function render_prix( \WP_Post $post ): void {
		$prix    = (float) get_post_meta( $post->ID, 'jcmv_produit_prix', true );
		$coloris = (string) get_post_meta( $post->ID, 'jcmv_produit_coloris', true );
		$dispo   = PostTypes::sanitize_dispo( get_post_meta( $post->ID, 'jcmv_produit_dispo', true ) );
		$grille  = ( new ProductPriceRepository() )->for_product( $post->ID );

		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="jcmv-produit-prix"><?php esc_html_e( 'Prix', 'wp-jcmv' ); ?></label>
				</th>
				<td>
					<input type="text" inputmode="decimal" id="jcmv-produit-prix" name="jcmv_produit_prix"
						class="small-text" value="<?php echo esc_attr( $prix > 0 ? (string) $prix : '' ); ?>" />
					<span aria-hidden="true">€</span>
					<p class="description">
						<?php esc_html_e( 'Prix unique du produit. Sans effet si une grille de tarifs par taille est renseignée plus bas — c\'est alors elle qui fait foi.', 'wp-jcmv' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="jcmv-produit-coloris"><?php esc_html_e( 'Coloris', 'wp-jcmv' ); ?></label>
				</th>
				<td>
					<input type="text" id="jcmv-produit-coloris" name="jcmv_produit_coloris"
						class="regular-text" value="<?php echo esc_attr( $coloris ); ?>"
						placeholder="<?php esc_attr_e( 'blanc, bleu marine', 'wp-jcmv' ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Facultatif, saisie libre. Si un coloris a son propre tarif, en faire un produit distinct.', 'wp-jcmv' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="jcmv-produit-dispo"><?php esc_html_e( 'Disponibilité', 'wp-jcmv' ); ?></label>
				</th>
				<td>
					<select id="jcmv-produit-dispo" name="jcmv_produit_dispo">
						<?php foreach ( PostTypes::DISPONIBILITES as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $dispo, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Information déclarative : aucun stock n\'est décompté automatiquement. Pour retirer durablement un produit du site, le repasser en brouillon plutôt que le supprimer.', 'wp-jcmv' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2 class="jcmv-produit-sous-titre"><?php esc_html_e( 'Tarifs par taille', 'wp-jcmv' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'À remplir uniquement si le prix dépend de la taille (judogi). Les tailles s\'affichent sur le site dans l\'ordre saisi ici — du plus petit au plus grand.', 'wp-jcmv' ); ?>
		</p>

		<table class="jcmv-tarifs widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Taille', 'wp-jcmv' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Prix (€)', 'wp-jcmv' ); ?></th>
					<th scope="col" class="jcmv-tarifs__actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'wp-jcmv' ); ?></span></th>
				</tr>
			</thead>
			<tbody id="jcmv-tarifs-lignes">
				<?php foreach ( $grille as $i => $ligne ) : ?>
					<?php self::render_tarif_row( $i, $ligne['taille'], $ligne['prix'] ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p>
			<button type="button" class="button" id="jcmv-tarifs-ajouter">
				<?php esc_html_e( 'Ajouter une taille', 'wp-jcmv' ); ?>
			</button>
		</p>

		<?php
		/*
		 * Témoin de présence du formulaire. Sans lui, une grille dont toutes
		 * les lignes viennent d'être retirées ne posterait plus aucun champ
		 * jcmv_tarif — indiscernable d'un enregistrement qui ne concerne pas
		 * les tarifs (édition rapide, écriture programmatique). Le bureau ne
		 * pourrait alors jamais vider une grille.
		 */
		?>
		<input type="hidden" name="jcmv_tarif_soumis" value="1" />

		<?php // Gabarit cloné par le JS. __INDEX__ est remplacé à l'insertion. ?>
		<template id="jcmv-tarifs-gabarit">
			<?php self::render_tarif_row( '__INDEX__', '', null ); ?>
		</template>
		<?php
	}

	/**
	 * Une ligne de la grille tarifaire.
	 *
	 * @param int|string $index  Index de la ligne, ou le jeton du gabarit.
	 * @param string     $taille Libellé de taille.
	 * @param float|null $prix   Montant ; null pour une ligne vierge.
	 */
	private static function render_tarif_row( $index, string $taille, ?float $prix ): void {
		$name = 'jcmv_tarif[' . $index . ']';
		?>
		<tr class="jcmv-tarifs__ligne">
			<td>
				<label class="screen-reader-text"><?php esc_html_e( 'Taille', 'wp-jcmv' ); ?></label>
				<input type="text" class="regular-text" name="<?php echo esc_attr( $name . '[taille]' ); ?>"
					value="<?php echo esc_attr( $taille ); ?>"
					placeholder="<?php esc_attr_e( 'M, 150 cm, 4…', 'wp-jcmv' ); ?>" />
			</td>
			<td>
				<label class="screen-reader-text"><?php esc_html_e( 'Prix', 'wp-jcmv' ); ?></label>
				<input type="text" inputmode="decimal" class="small-text" name="<?php echo esc_attr( $name . '[prix]' ); ?>"
					value="<?php echo esc_attr( null === $prix ? '' : (string) $prix ); ?>" />
			</td>
			<td class="jcmv-tarifs__actions">
				<button type="button" class="button-link jcmv-tarifs__supprimer">
					<?php esc_html_e( 'Retirer', 'wp-jcmv' ); ?>
				</button>
			</td>
		</tr>
		<?php
	}

	public static function render_medias( \WP_Post $post ): void {
		$galerie = PostTypes::sanitize_gallery( get_post_meta( $post->ID, 'jcmv_produit_galerie', true ) );
		?>
		<p class="description">
			<?php
			printf(
				/* translators: %d : nombre maximum de photos complémentaires. */
				esc_html__( 'La photo principale se règle dans « Image mise en avant » : sans elle, le produit n\'apparaît pas sur le site. Jusqu\'à %d photos complémentaires peuvent être ajoutées ici (dos, détail du flocage).', 'wp-jcmv' ),
				(int) PostTypes::GALERIE_MAX
			);
			?>
		</p>

		<div id="jcmv-galerie" class="jcmv-galerie" data-max="<?php echo esc_attr( (string) PostTypes::GALERIE_MAX ); ?>">
			<ul class="jcmv-galerie__liste" id="jcmv-galerie-liste">
				<?php foreach ( $galerie as $attachment_id ) : ?>
					<li class="jcmv-galerie__item" data-id="<?php echo esc_attr( (string) $attachment_id ); ?>">
						<?php echo wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par wp_get_attachment_image(). ?>
						<button type="button" class="button-link jcmv-galerie__retirer">
							<?php esc_html_e( 'Retirer', 'wp-jcmv' ); ?>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>

			<input type="hidden" id="jcmv-galerie-ids" name="jcmv_produit_galerie"
				value="<?php echo esc_attr( implode( ',', $galerie ) ); ?>" />

			<p>
				<button type="button" class="button" id="jcmv-galerie-choisir">
					<?php esc_html_e( 'Choisir des photos', 'wp-jcmv' ); ?>
				</button>
			</p>
		</div>

		<p class="description">
			<?php esc_html_e( 'Les photos sont recadrées au format portrait pour que toutes les vignettes du catalogue s\'alignent : cadrer le produit au centre, et privilégier un fond uni.', 'wp-jcmv' ); ?>
		</p>
		<?php
	}

	/**
	 * @param int $post_id ID du produit.
	 */
	public static function save( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE ] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| wp_is_post_revision( $post_id )
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['jcmv_produit_prix'] ) ) {
			update_post_meta(
				$post_id,
				'jcmv_produit_prix',
				PostTypes::sanitize_amount( wp_unslash( $_POST['jcmv_produit_prix'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- assaini par sanitize_amount().
			);
		}

		if ( isset( $_POST['jcmv_produit_coloris'] ) ) {
			update_post_meta(
				$post_id,
				'jcmv_produit_coloris',
				sanitize_text_field( wp_unslash( $_POST['jcmv_produit_coloris'] ) )
			);
		}

		if ( isset( $_POST['jcmv_produit_dispo'] ) ) {
			update_post_meta(
				$post_id,
				'jcmv_produit_dispo',
				PostTypes::sanitize_dispo( wp_unslash( $_POST['jcmv_produit_dispo'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- assaini par sanitize_dispo().
			);
		}

		if ( isset( $_POST['jcmv_produit_galerie'] ) ) {
			$ids = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['jcmv_produit_galerie'] ) ) ) );
			update_post_meta( $post_id, 'jcmv_produit_galerie', PostTypes::sanitize_gallery( $ids ) );
		}

		/*
		 * La grille n'est réécrite que si le formulaire l'a effectivement
		 * soumise (témoin jcmv_tarif_soumis). Sans ce garde-fou, une édition
		 * rapide depuis la liste des produits ou une écriture programmatique
		 * effacerait silencieusement des tarifs que personne n'a touchés.
		 *
		 * Le tableau jcmv_tarif, lui, peut légitimement être absent : c'est
		 * ce que produit une grille dont on vient de retirer toutes les lignes.
		 */
		if ( isset( $_POST['jcmv_tarif_soumis'] ) ) {
			$posted = isset( $_POST['jcmv_tarif'] ) && is_array( $_POST['jcmv_tarif'] )
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- chaque champ est assaini dans la fermeture ci-dessous.
				? array_values( wp_unslash( $_POST['jcmv_tarif'] ) )
				: array();

			$rows = array_map(
				static function ( $row ): array {
					$row = (array) $row;
					return array(
						'taille' => isset( $row['taille'] ) ? sanitize_text_field( (string) $row['taille'] ) : '',
						'prix'   => isset( $row['prix'] ) ? sanitize_text_field( (string) $row['prix'] ) : '0',
					);
				},
				$posted
			);

			$result = ( new ProductPriceRepository() )->replace_for_product( $post_id, $rows );

			if ( is_wp_error( $result ) ) {
				// L'écriture des tarifs a échoué mais le produit, lui, est
				// enregistré : le signaler plutôt que laisser croire à un
				// succès complet.
				set_transient( 'jcmv_produit_erreur_' . get_current_user_id(), $result->get_error_message(), 60 );
			}
		}
	}

	/**
	 * Signale un échec d'écriture de la grille tarifaire.
	 *
	 * Le produit ayant été enregistré, WordPress affiche « Article mis à
	 * jour » : sans ce message, le bureau repartirait convaincu que ses
	 * tarifs sont en base.
	 */
	public static function notice(): void {
		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen || PostTypes::PRODUIT !== $screen->post_type ) {
			return;
		}

		// Clé par utilisateur et non par produit : au moment où le message
		// s'affiche, on sort d'une redirection et l'ID du produit courant
		// n'est pas garanti d'être encore résolu.
		$key     = 'jcmv_produit_erreur_' . get_current_user_id();
		$message = get_transient( $key );

		if ( ! $message ) {
			return;
		}

		delete_transient( $key );

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s : message d'erreur de la base de données. */
					__( 'Les tarifs par taille n\'ont pas pu être enregistrés : %s', 'wp-jcmv' ),
					(string) $message
				)
			)
		);
	}

}
