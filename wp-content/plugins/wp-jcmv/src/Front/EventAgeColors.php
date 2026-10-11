<?php
/**
 * Liseré des compétitions aux couleurs de la catégorie d'âge.
 *
 * Dans le calendrier, la couleur d'un badge vient des « couleurs de
 * catégorie » de TEC (≥ 6.14) : une feuille générée pose
 * `.tribe_events_cat-{slug} { --tec-color-category-primary: … }` et le liseré
 * gauche lit cette variable. On ne touche ni à ce mécanisme ni aux gabarits :
 * on redéfinit la même variable, plus spécifiquement, pour les seules
 * compétitions dont la catégorie d'âge a une couleur.
 *
 * Deux pièces :
 *
 * 1. Une classe `jcmv-age-{slug}` sur l'événement, posée par le filtre
 *    `post_class` — TEC construit ses classes d'événement avec
 *    get_post_class(). WordPress n'y ajoute d'office que les termes des
 *    taxonomies publiques, et `jcmv_categorie_age` ne l'est pas : sans ce
 *    filtre, rien dans le balisage ne dit la catégorie d'âge. La classe n'est
 *    posée que si l'événement a UNE catégorie d'âge — à plusieurs, aucune
 *    couleur ne serait juste, et le liseré garde celle de « Compétition ».
 *
 * 2. Une feuille de style d'une ligne par couleur définie, imprimée dans le
 *    `<head>` :
 *    `.tribe_events_cat-competition.jcmv-age-minime { --tec-color-category-primary: #3b7dd8 }`.
 *    Spécificité 0,2,0 contre 0,1,0 pour la règle de TEC : l'ordre de
 *    chargement est indifférent. Sans couleur pour la catégorie d'âge,
 *    aucune ligne : la variable de TEC s'applique, c'est le repli voulu.
 *    Le `<head>` survit à la navigation AJAX entre les mois, qui ne remplace
 *    que le conteneur de la vue ; les événements rechargés reçoivent leur
 *    classe par le même filtre, côté REST.
 *
 * @package wp-jcmv
 */

namespace JCMV\Front;

use JCMV\Domain\AgeColors;
use JCMV\Registration\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EventAgeColors {

	/** CPT des événements de The Events Calendar. */
	private const TEC_CPT = 'tribe_events';

	/** Taxonomie des catégories d'événement de TEC. */
	private const TEC_CATEGORIES = 'tribe_events_cat';

	/**
	 * Slug de la catégorie TEC concernée. Si la catégorie « Compétition » est
	 * un jour renommée AVEC son slug, c'est ici qu'il faut le reporter — sans
	 * quoi les liserés reviennent tous au rouge, sans autre symptôme.
	 */
	private const COMPETITION = 'competition';

	/** Préfixe de la classe posée sur l'événement. */
	public const CLASS_PREFIX = 'jcmv-age-';

	public static function register(): void {
		add_filter( 'post_class', array( self::class, 'add_age_class' ), 10, 3 );
		add_action( 'wp_head', array( self::class, 'print_styles' ), 20 );
	}

	/**
	 * @param string[]     $classes Classes de l'article.
	 * @param string|array $class   Classes demandées par l'appelant (inutilisé).
	 * @param int          $post_id Article concerné.
	 * @return string[]
	 */
	public static function add_age_class( $classes, $class, $post_id ): array {
		$classes = (array) $classes;

		if ( self::TEC_CPT !== get_post_type( (int) $post_id ) ) {
			return $classes;
		}

		$terms = get_the_terms( (int) $post_id, Taxonomies::CATEGORIE_AGE );

		if ( is_array( $terms ) && 1 === count( $terms ) ) {
			$classes[] = self::CLASS_PREFIX . sanitize_html_class( $terms[0]->slug );
		}

		return $classes;
	}

	/**
	 * Une règle par catégorie d'âge colorée. Rien n'est imprimé tant qu'aucune
	 * couleur n'est définie, ni sans TEC.
	 */
	public static function print_styles(): void {
		if ( ! post_type_exists( self::TEC_CPT ) ) {
			return;
		}

		$rules = '';

		foreach ( AgeColors::all() as $slug => $color ) {
			$rules .= sprintf(
				".%s-%s.%s%s{--tec-color-category-primary:%s}\n",
				self::TEC_CATEGORIES,
				sanitize_html_class( self::COMPETITION ),
				self::CLASS_PREFIX,
				sanitize_html_class( $slug ),
				$color // Déjà passé par sanitize_hex_color() (Domain\AgeColors).
			);
		}

		if ( '' === $rules ) {
			return;
		}

		printf( "<style id=\"jcmv-age-colors\">\n%s</style>\n", $rules ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- classes assainies, couleurs validées.
	}
}
