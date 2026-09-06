<?php
/**
 * Catégorie d'âge sur les événements, côté visiteur.
 *
 * `jcmv_categorie_age` est rattachée au CPT de The Events Calendar
 * (Registration\Taxonomies), mais TEC n'affiche que ses propres taxonomies :
 * une compétition importée porte bien « Cadet » en base sans que rien ne le
 * montre au visiteur. C'est pourtant ce qui lui dit si la date le concerne.
 *
 * Deux emplacements, deux hooks, aucun gabarit copié :
 *
 * - la fiche d'un événement, en ligne du bloc « Détails ». Le hook de fin de
 *   liste existe dans les deux gabarits de fiche, le classique
 *   (`modules/meta/details.php`) et celui de l'éditeur de blocs
 *   (`blocks/parts/details.php`) : une seule greffe couvre les deux ;
 * - la vue Liste, en badges sous le titre. Les vues V2 déclenchent une action
 *   après chaque partie de gabarit incluse, et le contexte local — donc
 *   l'événement — est encore en place à ce moment-là.
 *
 * Surcharger `views/v2/list/event/title.php` dans le thème marcherait aussi,
 * mais figerait le balisage de TEC : à chaque mise à jour du plugin, la copie
 * diverge en silence. Le hook encode un chemin de gabarit, ce qui n'est pas
 * sans risque non plus — si TEC restructure ses vues, les badges
 * disparaissent —, mais l'échec est alors une absence, pas un balisage périmé.
 *
 * Termes classés par âge (Domain\AgeOrder), comme partout ailleurs. Affichés
 * en texte : la taxonomie est `public => false`, ses pages d'archive
 * n'existent pas, un lien pointerait dans le vide.
 *
 * @package wp-jcmv
 */

namespace JCMV\Front;

use JCMV\Domain\AgeOrder;
use JCMV\Registration\Taxonomies;
use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EventAgeMeta {

	/** Fin du bloc « Détails » de la fiche événement. */
	private const HOOK_SINGLE = 'tribe_events_single_meta_details_section_end';

	/** Après le titre d'une ligne de la vue Liste (vues V2). */
	private const HOOK_LIST = 'tribe_template_after_include:events/v2/list/event/title';

	public static function register(): void {
		add_action( self::HOOK_SINGLE, array( self::class, 'render_details_row' ) );
		add_action( self::HOOK_LIST, array( self::class, 'render_list_badges' ), 10, 3 );
	}

	/**
	 * Ajoute une ligne à la liste de définitions du bloc « Détails ».
	 *
	 * L'intitulé reprend le balisage des lignes voisines (coût, catégorie,
	 * étiquettes) pour hériter des styles de TEC sans une ligne de CSS ; la
	 * classe `jcmv-event-age-label` s'y ajoute pour rester ciblable le jour où
	 * cette ligne devra se distinguer des autres. La valeur, elle, sort en
	 * badges plutôt qu'en texte : mêmes pastilles que dans la vue Liste, pour
	 * que la même information se reconnaisse d'un écran à l'autre.
	 *
	 * La liste de badges est fille directe du `<li>` et non du
	 * `<span class="tribe-events-meta-value">` des lignes voisines : un `<ul>`
	 * dans un `<span>` ne serait pas du HTML valide.
	 */
	public static function render_details_row(): void {
		$names = self::names_for( get_the_ID() );

		if ( array() === $names ) {
			return;
		}

		printf(
			'<li class="tribe-events-meta-item"><span class="tribe-events-meta-label jcmv-event-age-label">%s</span>%s</li>',
			esc_html(
				_n(
					'Catégorie d\'âge :',
					'Catégories d\'âge :',
					count( $names ),
					'wp-jcmv'
				)
			),
			self::badges( $names, 'jcmv-event-ages--meta' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Affiche les badges sous le titre, dans la vue Liste.
	 *
	 * Sortie par `echo` et non par retour : l'action est capturée par un
	 * tampon de sortie dans Tribe__Template::actions_after_template().
	 *
	 * @param string       $file     Gabarit inclus (inutilisé).
	 * @param array<mixed> $name     Nom du gabarit (inutilisé).
	 * @param object       $template Instance du moteur de gabarits de TEC.
	 */
	public static function render_list_badges( $file, $name, $template ): void {
		if ( ! is_object( $template ) || ! method_exists( $template, 'get' ) ) {
			return;
		}

		$event = $template->get( 'event' );

		if ( ! $event instanceof WP_Post ) {
			return;
		}

		$names = self::names_for( $event->ID );

		if ( array() === $names ) {
			return;
		}

		echo self::badges( $names, 'jcmv-event-ages--list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Balisage des badges, commun aux deux emplacements.
	 *
	 * Le modificateur porte ce qui diffère d'un contexte à l'autre — position
	 * dans la colonne flex de la vue Liste, espacement dans le bloc
	 * « Détails ». Une classe à nous plutôt qu'un sélecteur descendant depuis
	 * un `.tribe-*` : la mise en forme ne doit dépendre d'aucun nom de classe
	 * de TEC, qui n'est pas une API.
	 *
	 * @param string[] $names    Catégories d'âge, déjà classées.
	 * @param string   $modifier Classe de contexte.
	 */
	private static function badges( array $names, string $modifier ): string {
		$items = '';

		foreach ( $names as $age ) {
			$items .= sprintf(
				'<li class="jcmv-badge jcmv-badge--pale">%s</li>',
				esc_html( $age )
			);
		}

		return sprintf(
			'<ul class="jcmv-event-ages %s">%s</ul>',
			esc_attr( $modifier ),
			$items // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Noms des catégories d'âge d'un événement, classés du plus jeune au plus âgé.
	 *
	 * Aucune requête supplémentaire par événement en vue Liste : la WP_Query
	 * de la vue amorce le cache des termes de toute la page
	 * (`update_post_term_cache`, actif par défaut).
	 *
	 * @param int|false $post_id Événement, ou false hors de la boucle.
	 * @return string[]
	 */
	private static function names_for( $post_id ): array {
		if ( ! $post_id ) {
			return array();
		}

		$terms = get_the_terms( (int) $post_id, Taxonomies::CATEGORIE_AGE );

		// get_the_terms() renvoie false (aucun terme) ou un WP_Error si la
		// taxonomie n'est pas enregistrée — TEC actif sans le module club.
		if ( ! is_array( $terms ) || array() === $terms ) {
			return array();
		}

		return array_map(
			static fn ( WP_Term $term ): string => $term->name,
			AgeOrder::sort( $terms )
		);
	}
}
