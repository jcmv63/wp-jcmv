<?php
/**
 * Rendu serveur du bloc « Abonnement au calendrier » (ADR-004).
 *
 * Amélioration progressive : le balisage émis ici est déjà utilisable sans
 * JavaScript — le bouton pointe sur le flux global et l'URL affichée est la
 * sienne. `view.js` ne fait que recalculer ces deux valeurs au cochage.
 *
 * Toutes les catégories sont listées, y compris celles sans événement à venir :
 * la liste reste stable d'une visite à l'autre et un abonnement pris en début
 * de saison se remplira à mesure des saisies.
 *
 * @package wp-jcmv
 *
 * @var array    $attributes Attributs du bloc.
 * @var string   $content    Contenu interne (inutilisé).
 * @var WP_Block $block      Instance du bloc.
 */

use JCMV\Front\CalendarFeed;

$jcmv_terms = CalendarFeed::categories();

if ( ! $jcmv_terms ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p><em>' . esc_html__( 'Aucune catégorie d\'âge définie — JCMV → Catégories d\'âge. (Message visible uniquement par le bureau.)', 'wp-jcmv' ) . '</em></p>';
	}
	return;
}

$jcmv_titre    = trim( (string) ( $attributes['titre'] ?? '' ) );
$jcmv_https    = CalendarFeed::url();
$jcmv_webcal   = CalendarFeed::url( array(), true );
$jcmv_base     = home_url( '/' . CalendarFeed::BASE . '/' );
$jcmv_id       = wp_unique_id( 'jcmv-abo-' );
$jcmv_wrapper  = get_block_wrapper_attributes( array( 'class' => 'jcmv-abo' ) );
?>
<section <?php echo $jcmv_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par get_block_wrapper_attributes(). ?>
	data-jcmv-abo
	data-base="<?php echo esc_url( $jcmv_base ); ?>"
	data-tous="<?php echo esc_attr( CalendarFeed::ALL ); ?>"
	aria-labelledby="<?php echo esc_attr( $jcmv_id ); ?>-titre">

	<?php if ( '' !== $jcmv_titre ) : ?>
		<h2 class="jcmv-abo__titre" id="<?php echo esc_attr( $jcmv_id ); ?>-titre">
			<?php echo esc_html( $jcmv_titre ); ?>
		</h2>
	<?php else : ?>
		<span class="jcmv-sr-only" id="<?php echo esc_attr( $jcmv_id ); ?>-titre">
			<?php esc_html_e( 'Abonnement au calendrier', 'wp-jcmv' ); ?>
		</span>
	<?php endif; ?>

	<p class="jcmv-abo__intro">
		<?php esc_html_e( 'Recevez les compétitions, stages et temps forts du club directement dans le calendrier de votre téléphone. Choisissez les catégories qui vous concernent.', 'wp-jcmv' ); ?>
	</p>

	<?php // Le groupe de cases est un fieldset : sans lui, un lecteur d'écran annonce neuf cases isolées, sans le libellé qui leur donne un sens. ?>
	<fieldset class="jcmv-abo__groupe">
		<legend class="jcmv-abo__legende">
			<?php esc_html_e( 'Catégories d\'âge', 'wp-jcmv' ); ?>
		</legend>

		<ul class="jcmv-abo__liste">
			<?php foreach ( $jcmv_terms as $jcmv_term ) : ?>
				<li class="jcmv-abo__item">
					<label class="jcmv-abo__case">
						<?php // Toutes cochées par défaut : qui clique sans rien comprendre obtient le calendrier complet, le résultat le plus utile. ?>
						<input type="checkbox"
							name="jcmv-categorie"
							value="<?php echo esc_attr( $jcmv_term->slug ); ?>"
							checked>
						<span><?php echo esc_html( $jcmv_term->name ); ?></span>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>

		<p class="jcmv-abo__actions-groupe">
			<button type="button" class="jcmv-abo__tout" data-jcmv-tout hidden>
				<?php esc_html_e( 'Tout cocher', 'wp-jcmv' ); ?>
			</button>
			<button type="button" class="jcmv-abo__rien" data-jcmv-rien hidden>
				<?php esc_html_e( 'Tout décocher', 'wp-jcmv' ); ?>
			</button>
		</p>
	</fieldset>

	<p class="jcmv-abo__vide" data-jcmv-vide hidden role="status">
		<?php esc_html_e( 'Sélectionnez au moins une catégorie.', 'wp-jcmv' ); ?>
	</p>

	<p class="jcmv-abo__cta">
		<?php // esc_url() accepte `webcal` : il figure dans wp_allowed_protocols(). ?>
		<a class="jcmv-abo__bouton" href="<?php echo esc_url( $jcmv_webcal ); ?>" data-jcmv-bouton>
			<?php esc_html_e( 'S\'abonner', 'wp-jcmv' ); ?>
		</a>
	</p>

	<?php
	/*
	 * Deux sorties, et non une : iPhone et Outlook ouvrent l'application sur un
	 * lien `webcal://`, tandis que Google Agenda réclame une URL `https` à
	 * coller dans « Autres agendas → À partir de l'URL ». Servir un seul des
	 * deux laisse la moitié des familles sur le carreau.
	 */
	?>
	<div class="jcmv-abo__url">
		<label class="jcmv-abo__url-label" for="<?php echo esc_attr( $jcmv_id ); ?>-url">
			<?php esc_html_e( 'Ou copiez cette adresse dans Google Agenda :', 'wp-jcmv' ); ?>
		</label>
		<span class="jcmv-abo__url-ligne">
			<?php // aria-live : le changement d'URL au cochage doit être annoncé, pas seulement visible. ?>
			<input class="jcmv-abo__url-champ"
				id="<?php echo esc_attr( $jcmv_id ); ?>-url"
				type="text"
				readonly
				aria-live="polite"
				value="<?php echo esc_url( $jcmv_https ); ?>"
				data-jcmv-url>
			<button type="button" class="jcmv-abo__copier" data-jcmv-copier hidden>
				<?php esc_html_e( 'Copier', 'wp-jcmv' ); ?>
			</button>
		</span>
		<span class="jcmv-sr-only" role="status" data-jcmv-copie-statut></span>
	</div>

	<?php
	/*
	 * Réserve obligatoire (ADR-004) : les clients calendrier relisent les flux
	 * externes à leur propre rythme — Google souvent avec 12 à 24 h de retard.
	 * Le taire exposerait le club au reproche d'avoir « prévenu » via un canal
	 * que personne n'a vu à temps.
	 */
	?>
	<p class="jcmv-abo__reserve">
		<?php esc_html_e( 'Votre calendrier se met à jour automatiquement, mais avec un délai qui peut atteindre 24 heures selon votre application. Pour les changements de dernière minute, fiez-vous au site et aux messages du club.', 'wp-jcmv' ); ?>
	</p>
</section>
