<?php
/**
 * Metabox du CPT jcmv_produit (ADR-005).
 *
 * Deux boîtes : « Produit » regroupe ce qui change souvent (prix,
 * disponibilité, tailles), « Photos » ce qui ne bouge qu'à la création. Le
 * bureau n'ouvre donc qu'une boîte pour un réétiquetage de début de saison.
 *
 * Les tailles proposées viennent du système de tailles choisi (term meta
 * jcmv_tailles). Ce que le produit enregistre, ce sont les **libellés
 * cochés**, pas un pointeur vers le terme : modifier un système n'altère donc
 * jamais un produit existant, et une taille conservée d'un ancien système
 * reste visible, signalée « hors système ».
 *
 * Le JS est vanilla et sans build (règle ADR-002, « pas d'usine à gaz ») :
 * reconstruction des cases au changement de système, et sélection de photos via
 * wp.media, que le core fournit déjà.
 *
 * @package wp-jcmv
 */

namespace JCMV\Admin;

use JCMV\Domain\Sizes;
use JCMV\Registration\PostTypes;
use JCMV\Registration\Taxonomies;

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

	/** Nonce propre à la boîte « Photos complémentaires » (voir nonce_ok()). */
	private const NONCE_PHOTOS = 'jcmv_produit_photos_nonce';

	public static function register(): void {
		add_action( 'add_meta_boxes_' . PostTypes::PRODUIT, array( self::class, 'add' ) );
		add_action( 'save_post_' . PostTypes::PRODUIT, array( self::class, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'admin_notices', array( self::class, 'notice' ) );
	}

	public static function add(): void {
		add_meta_box(
			'jcmv-produit',
			__( 'Produit', 'wp-jcmv' ),
			array( self::class, 'render_produit' ),
			PostTypes::PRODUIT,
			'normal',
			'high'
		);

		add_meta_box(
			'jcmv-produit-photos',
			__( 'Photos complémentaires', 'wp-jcmv' ),
			array( self::class, 'render_photos' ),
			PostTypes::PRODUIT,
			'normal',
			'default'
		);
	}

	/**
	 * Charge wp.media et les assets de la metabox sur le seul écran d'édition
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
				// Tous les systèmes d'un coup : quelques termes, quelques
				// dizaines de libellés. Un aller-retour REST pour ça serait
				// plus de code et plus lent.
				'systemes'   => self::systemes(),
				'galerieMax' => PostTypes::GALERIE_MAX,
				'i18n'       => array(
					'horsSysteme' => __( 'hors système', 'wp-jcmv' ),
					'sansTailles' => __( 'Ce système de tailles ne contient aucune taille. Les définir dans JCMV → Taille produit, ou en ajouter à la main ci-dessous.', 'wp-jcmv' ),
					'sansSysteme' => __( 'Choisir un système de tailles pour voir les tailles proposées.', 'wp-jcmv' ),
					'retirer'     => __( 'Retirer', 'wp-jcmv' ),
					'mediaTitle'  => __( 'Photos du produit', 'wp-jcmv' ),
					'mediaButton' => __( 'Utiliser ces photos', 'wp-jcmv' ),
				),
			)
		);

		wp_enqueue_style(
			'jcmv-produit-admin',
			JCMV_PLUGIN_URL . 'assets/css/produit-metabox.css',
			array(),
			JCMV_VERSION
		);
	}

	/**
	 * Tailles de tous les systèmes, indexées par ID de terme.
	 *
	 * @return array<string, array<int, string>>
	 */
	private static function systemes(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::SYSTEME_TAILLE,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$systemes = array();
		foreach ( $terms as $term ) {
			// Clé en chaîne : json_encode() rendrait un tableau JSON, et non un
			// objet, si les IDs formaient par hasard une suite depuis 0 — le JS
			// lirait alors les tailles par index au lieu de l'ID du système.
			$systemes[ (string) $term->term_id ] = Sizes::normalize(
				get_term_meta( $term->term_id, Taxonomies::META_TAILLES, true )
			);
		}

		return $systemes;
	}

	public static function render_produit( \WP_Post $post ): void {
		$prix    = (float) get_post_meta( $post->ID, 'jcmv_produit_prix', true );
		$couleur = (string) get_post_meta( $post->ID, 'jcmv_produit_couleur', true );
		$dispo   = PostTypes::sanitize_dispo( get_post_meta( $post->ID, 'jcmv_produit_dispo', true ) );

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
						<?php esc_html_e( 'Un seul prix par produit. Si le tarif dépend de la taille (judogi), créer deux produits — par exemple « Judogi enfant » et « Judogi adulte ».', 'wp-jcmv' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="jcmv-produit-couleur"><?php esc_html_e( 'Couleur', 'wp-jcmv' ); ?></label>
				</th>
				<td>
					<input type="text" id="jcmv-produit-couleur" name="jcmv_produit_couleur"
						class="regular-text" value="<?php echo esc_attr( $couleur ); ?>"
						placeholder="<?php esc_attr_e( 'noir', 'wp-jcmv' ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Facultatif. Un produit a une seule couleur : un t-shirt noir et un t-shirt blanc sont deux produits distincts.', 'wp-jcmv' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="jcmv-produit-dispo"><?php esc_html_e( 'Disponibilité', 'wp-jcmv' ); ?></label>
				</th>
				<td>
					<select id="jcmv-produit-dispo" name="jcmv_produit_dispo">
						<?php foreach ( PostTypes::disponibilites() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $dispo, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Information déclarative : aucun stock n\'est décompté. Pour retirer durablement un produit du site, le repasser en brouillon plutôt que le supprimer.', 'wp-jcmv' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2 class="jcmv-produit-sous-titre"><?php esc_html_e( 'Tailles disponibles', 'wp-jcmv' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Cocher les tailles réellement proposées pour ce produit. La liste vient du système de tailles sélectionné dans la colonne de droite ; elle se met à jour si le système change.', 'wp-jcmv' ); ?>
		</p>

		<div id="jcmv-tailles" class="jcmv-tailles">
			<?php self::render_tailles( $post ); ?>
		</div>

		<p class="jcmv-tailles-libres">
			<label for="jcmv-tailles-ajout"><?php esc_html_e( 'Ajouter des tailles absentes du système', 'wp-jcmv' ); ?></label>
			<input type="text" id="jcmv-tailles-ajout" name="jcmv_produit_tailles_libres" class="regular-text"
				value="" placeholder="<?php esc_attr_e( '3XL, taille unique…', 'wp-jcmv' ); ?>" />
			<span class="description">
				<?php esc_html_e( 'Séparées par des virgules. Elles seront ajoutées à ce produit seulement, sans modifier le système de tailles.', 'wp-jcmv' ); ?>
			</span>
		</p>

		<?php
		/*
		 * Témoin de présence du formulaire. Sans lui, un produit dont on vient
		 * de décocher toutes les tailles ne posterait plus aucun champ
		 * jcmv_produit_tailles — indiscernable d'un enregistrement qui ne
		 * concerne pas les tailles (édition rapide, écriture programmatique).
		 * Le bureau ne pourrait alors jamais vider une liste.
		 */
		?>
		<input type="hidden" name="jcmv_tailles_soumis" value="1" />
		<?php
	}

	/**
	 * Cases à cocher des tailles : celles du système courant, puis les tailles
	 * du produit qui n'y figurent pas.
	 *
	 * Ces dernières sont la contrepartie visible de la règle « le système
	 * n'est pas une référence » : une taille retirée du système, ou ajoutée à
	 * la main, reste attachée au produit et doit se voir.
	 */
	private static function render_tailles( \WP_Post $post ): void {
		$tailles = Sizes::normalize( get_post_meta( $post->ID, 'jcmv_produit_tailles', true ) );
		$systeme = wp_get_object_terms( $post->ID, Taxonomies::SYSTEME_TAILLE, array( 'fields' => 'ids' ) );
		$systeme = ( ! is_wp_error( $systeme ) && $systeme ) ? (int) $systeme[0] : 0;

		$proposees = $systeme
			? Sizes::normalize( get_term_meta( $systeme, Taxonomies::META_TAILLES, true ) )
			: array();

		// Comparaison insensible à la casse, comme le dédoublonnage de
		// Sizes::normalize() : « XL » coché ne doit pas réapparaître en doublon
		// « hors système » face à un « xl » dans le système.
		$index        = array_map( array( Sizes::class, 'fold' ), $proposees );
		$hors_systeme = array_values(
			array_filter(
				$tailles,
				static function ( string $taille ) use ( $index ): bool {
					return ! in_array( Sizes::fold( $taille ), $index, true );
				}
			)
		);

		if ( ! $systeme ) {
			echo '<p class="description">' . esc_html__( 'Choisir un système de tailles pour voir les tailles proposées.', 'wp-jcmv' ) . '</p>';
		} elseif ( ! $proposees ) {
			echo '<p class="description">' . esc_html__( 'Ce système de tailles ne contient aucune taille. Les définir dans JCMV → Taille produit, ou en ajouter à la main ci-dessous.', 'wp-jcmv' ) . '</p>';
		}

		if ( ! $proposees && ! $hors_systeme ) {
			return;
		}

		/*
		 * La pastille « hors système » n'a de sens que s'il y a un système dont
		 * être sorti. Sans système sélectionné, TOUTES les tailles du produit
		 * tomberaient dans $hors_systeme et seraient signalées — un mur de
		 * pastilles qui n'informe de rien, et qui pousse à décocher.
		 */
		$badge = $systeme > 0;
		$coche = array_map( array( Sizes::class, 'fold' ), $tailles );

		echo '<ul class="jcmv-tailles__liste">';

		foreach ( $proposees as $taille ) {
			self::render_taille_case( $taille, in_array( Sizes::fold( $taille ), $coche, true ), false );
		}

		foreach ( $hors_systeme as $taille ) {
			self::render_taille_case( $taille, true, $badge );
		}

		echo '</ul>';
	}

	private static function render_taille_case( string $taille, bool $checked, bool $hors_systeme ): void {
		?>
		<li class="jcmv-tailles__item<?php echo $hors_systeme ? ' is-hors-systeme' : ''; ?>">
			<label>
				<input type="checkbox" name="jcmv_produit_tailles[]"
					value="<?php echo esc_attr( $taille ); ?>" <?php checked( $checked ); ?> />
				<span><?php echo esc_html( $taille ); ?></span>
				<?php if ( $hors_systeme ) : ?>
					<em class="jcmv-tailles__hors"><?php esc_html_e( 'hors système', 'wp-jcmv' ); ?></em>
				<?php endif; ?>
			</label>
		</li>
		<?php
	}

	public static function render_photos( \WP_Post $post ): void {
		$galerie = PostTypes::sanitize_gallery( get_post_meta( $post->ID, 'jcmv_produit_galerie', true ) );
		wp_nonce_field( self::NONCE_PHOTOS, self::NONCE_PHOTOS );
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
	 * Vérifie le nonce d'une boîte donnée.
	 *
	 * Chaque metabox porte le sien : les Options de l'écran permettent de
	 * masquer l'une sans l'autre, et un nonce unique logé dans une seule boîte
	 * ferait rejeter en silence les champs de l'autre.
	 */
	private static function nonce_ok( string $name ): bool {
		return isset( $_POST[ $name ] )
			&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ $name ] ) ), $name );
	}

	/**
	 * @param int $post_id ID du produit.
	 */
	public static function save( int $post_id ): void {
		// Garde-fous communs aux deux boîtes.
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| wp_is_post_revision( $post_id )
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( self::nonce_ok( self::NONCE_PHOTOS ) && isset( $_POST['jcmv_produit_galerie'] ) ) {
			$ids = explode( ',', sanitize_text_field( wp_unslash( $_POST['jcmv_produit_galerie'] ) ) );
			update_post_meta( $post_id, 'jcmv_produit_galerie', PostTypes::sanitize_gallery( $ids ) );
		}

		if ( ! self::nonce_ok( self::NONCE ) ) {
			return;
		}

		if ( isset( $_POST['jcmv_produit_prix'] ) ) {
			update_post_meta(
				$post_id,
				'jcmv_produit_prix',
				PostTypes::sanitize_amount( wp_unslash( $_POST['jcmv_produit_prix'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- assaini par sanitize_amount().
			);
		}

		if ( isset( $_POST['jcmv_produit_couleur'] ) ) {
			update_post_meta(
				$post_id,
				'jcmv_produit_couleur',
				sanitize_text_field( wp_unslash( $_POST['jcmv_produit_couleur'] ) )
			);
		}

		if ( isset( $_POST['jcmv_produit_dispo'] ) ) {
			update_post_meta(
				$post_id,
				'jcmv_produit_dispo',
				PostTypes::sanitize_dispo( wp_unslash( $_POST['jcmv_produit_dispo'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- assaini par sanitize_dispo().
			);
		}

		if ( isset( $_POST['jcmv_tailles_soumis'] ) ) {
			// Les cases cochées arrivent dans l'ordre du DOM, donc dans l'ordre
			// du système ; les ajouts libres suivent. Sizes::normalize()
			// dédoublonne sans réordonner, order_by() remet dans l'ordre.
			$cochees = isset( $_POST['jcmv_produit_tailles'] ) && is_array( $_POST['jcmv_produit_tailles'] )
				? wp_unslash( $_POST['jcmv_produit_tailles'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- assaini par Sizes::normalize().
				: array();

			$libres = isset( $_POST['jcmv_produit_tailles_libres'] )
				? explode( ',', sanitize_text_field( wp_unslash( $_POST['jcmv_produit_tailles_libres'] ) ) )
				: array();

			$tailles = Sizes::normalize( array_merge( (array) $cochees, $libres ) );

			/*
			 * Une taille tapée dans le champ libre alors qu'elle existe déjà
			 * dans le système atterrirait en fin de liste, cassant la
			 * progression saisie par le bureau. On réaligne sur l'ordre du
			 * système ; ce qui n'en fait pas partie garde sa place à la suite.
			 */
			$tailles = Sizes::order_by( $tailles, self::tailles_du_systeme( $post_id ) );

			update_post_meta( $post_id, 'jcmv_produit_tailles', $tailles );

			self::signaler_ajouts_ignores( $libres, $tailles );
		}
	}

	/**
	 * Tailles du système actuellement rattaché au produit.
	 *
	 * Lues au moment de l'enregistrement : `tax_input` est traité par
	 * wp_insert_post() avant que save_post ne se déclenche, le terme est donc
	 * déjà à jour quand on arrive ici.
	 *
	 * @return array<int, string>
	 */
	private static function tailles_du_systeme( int $post_id ): array {
		$systeme = wp_get_object_terms( $post_id, Taxonomies::SYSTEME_TAILLE, array( 'fields' => 'ids' ) );
		$systeme = ( ! is_wp_error( $systeme ) && $systeme ) ? (int) $systeme[0] : 0;

		return $systeme
			? Sizes::normalize( get_term_meta( $systeme, Taxonomies::META_TAILLES, true ) )
			: array();
	}

	/**
	 * Prévient si une saisie du champ libre n'a pas été retenue.
	 *
	 * Le cas courant est bénin — la taille était déjà cochée — mais rien ne le
	 * disait : le bureau tapait une valeur, la voyait disparaître du champ, et
	 * n'avait aucun moyen de savoir si elle avait été prise en compte ou
	 * perdue. Le doute coûte plus cher que le message.
	 *
	 * @param array<int, string> $libres  Saisies brutes du champ d'ajout.
	 * @param array<int, string> $retenus Libellés finalement enregistrés.
	 */
	private static function signaler_ajouts_ignores( array $libres, array $retenus ): void {
		$index   = array_map( array( Sizes::class, 'fold' ), $retenus );
		$ignores = array();

		foreach ( Sizes::normalize( $libres ) as $taille ) {
			if ( ! in_array( Sizes::fold( $taille ), $index, true ) ) {
				$ignores[] = $taille;
			}
		}

		if ( $ignores ) {
			set_transient( 'jcmv_produit_ignores_' . get_current_user_id(), $ignores, 60 );
		}
	}

	/**
	 * Affiche les saisies non retenues du champ d'ajout de tailles.
	 *
	 * Clé par utilisateur et non par produit : au moment où le message
	 * s'affiche, on sort d'une redirection et l'ID du produit courant n'est pas
	 * garanti d'être encore résolu.
	 */
	public static function notice(): void {
		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen || PostTypes::PRODUIT !== $screen->post_type ) {
			return;
		}

		$key     = 'jcmv_produit_ignores_' . get_current_user_id();
		$ignores = get_transient( $key );

		if ( ! is_array( $ignores ) || ! $ignores ) {
			return;
		}

		delete_transient( $key );

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s : liste des tailles ignorées, séparées par des virgules. */
					__( 'Tailles non ajoutées, car déjà présentes dans la liste du produit : %s', 'wp-jcmv' ),
					implode( ', ', $ignores )
				)
			)
		);
	}
}
