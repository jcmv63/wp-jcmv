<?php
/**
 * Reprise du widget « Events List » de The Events Calendar.
 *
 * L'apparence du widget est traitée en CSS dans le thème (components.css,
 * section « Widget Évènements à venir »). Ce module ne couvre que les deux
 * points qu'aucune feuille de style ne peut atteindre : le libellé du lien de
 * sortie, et les classes portées par ce lien.
 *
 * Aucun gabarit de TEC n'est copié : les deux filtres ci-dessous agissent sur
 * les variables du gabarit puis sur son HTML de sortie. Le jour où TEC met à
 * jour `view-more.php`, on hérite de la nouvelle version — là où une surcharge
 * de gabarit dans le thème aurait figé un instantané de la 6.17.
 *
 * @package wp-jcmv
 */

namespace JCMV\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EventsWidget {

	/**
	 * Slug de la vue du widget (Tribe\Events\Views\V2\Widgets\Widget_List).
	 */
	private const VIEW_SLUG = 'widget-events-list';

	/**
	 * Nom de hook du gabarit du lien de sortie.
	 *
	 * TEC le construit en concaténant l'espace de noms d'origine (`events`),
	 * le dossier de vues (`v2`) et le chemin du gabarit. Vérifié sur TEC 6.17.
	 */
	private const VIEW_MORE_TEMPLATE = 'events/v2/widgets/widget-events-list/view-more';

	public static function register(): void {
		add_filter(
			'tribe_events_views_v2_view_' . self::VIEW_SLUG . '_template_vars',
			array( self::class, 'view_more_labels' )
		);

		add_filter(
			'tribe_template_html:' . self::VIEW_MORE_TEMPLATE,
			array( self::class, 'view_more_button_classes' )
		);
	}

	/**
	 * Libellé du lien de sortie du widget.
	 *
	 * `Widget_View::get_view_more_text()` retourne « View Calendar » en dur,
	 * sans filtre dédié ; la valeur n'est atteignable qu'ici, via les variables
	 * du gabarit. Passer par la traduction du plugin aurait touché toutes les
	 * vues de TEC, pas seulement ce widget.
	 *
	 * `view_more_title` alimente l'attribut `title` du lien, lu par les
	 * lecteurs d'écran. Il est réécrit avec le libellé : laissé tel quel, il
	 * annoncerait « View more events » là où le lien affiche autre chose.
	 *
	 * @param array<string,mixed> $vars Variables du gabarit de la vue.
	 *
	 * @return array<string,mixed>
	 */
	public static function view_more_labels( array $vars ): array {
		$vars['view_more_text']  = __( 'Tous les évènements', 'wp-jcmv' );
		$vars['view_more_title'] = __( 'Voir tous les évènements du club.', 'wp-jcmv' );

		return $vars;
	}

	/**
	 * Fait porter au lien de sortie les classes de bouton du thème.
	 *
	 * Le but est de supprimer une duplication : sans ces classes, l'apparence
	 * du bouton devait être recopiée à la main dans components.css (taille,
	 * graisse, interlettrage, rayon, gouttières), avec la dette de
	 * synchronisation qui va avec. Le balisage produit ici est exactement
	 * celui des boutons outline du thème — `div.wp-block-button.is-style-outline`
	 * contenant `a.wp-block-button__link.wp-element-button` — de sorte que les
	 * styles de `theme.json` s'appliquent sans un octet de CSS supplémentaire.
	 *
	 * Les deux classes de TEC sont retirées du lien, et c'est délibéré :
	 * `tribe-common-anchor-thin` pose un soulignement par bordure basse qui
	 * trancherait le bouton, et `…__view-more-link` porte des couleurs d'état
	 * en spécificité 0,3,0 — soit deux crans au-dessus des styles de theme.json,
	 * publiés en `:root :where(…)`. Les laisser en place aurait obligé à
	 * réintroduire du CSS correctif, c'est à dire à ne rien avoir gagné.
	 *
	 * WP_HTML_Tag_Processor plutôt qu'une expression régulière : il comprend
	 * les attributs et ne peut pas produire de HTML invalide. Si le balisage de
	 * TEC change, `next_tag()` ne trouve rien et le HTML ressort intact — le
	 * bouton retombe alors sur le rendu par défaut du plugin, sans casse.
	 *
	 * @param string $html HTML du gabarit `view-more`.
	 *
	 * @return string
	 */
	public static function view_more_button_classes( string $html ): string {
		$tags = new \WP_HTML_Tag_Processor( $html );

		if ( $tags->next_tag( array( 'class_name' => 'tribe-events-widget-events-list__view-more' ) ) ) {
			$tags->add_class( 'wp-block-button' );
			$tags->add_class( 'is-style-outline' );
		}

		if ( $tags->next_tag( array( 'class_name' => 'tribe-events-widget-events-list__view-more-link' ) ) ) {
			$tags->remove_class( 'tribe-events-widget-events-list__view-more-link' );
			$tags->remove_class( 'tribe-common-anchor-thin' );
			$tags->add_class( 'wp-block-button__link' );
			$tags->add_class( 'wp-element-button' );
		}

		return $tags->get_updated_html();
	}
}
