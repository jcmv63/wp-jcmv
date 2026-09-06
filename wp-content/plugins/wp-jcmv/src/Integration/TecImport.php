<?php
/**
 * Import CSV de The Events Calendar : rattachement à la catégorie d'âge.
 *
 * L'importateur CSV de TEC ne connaît que ses propres champs : sa colonne
 * « Event Category » alimente `tribe_events_cat`, et rien d'autre. Une
 * compétition importée arrive donc sans catégorie d'âge, alors que c'est
 * elle qui décide de sa présence dans le flux ICS et dans le widget des
 * prochains événements (ADR-004). Corriger 25 événements à la main après
 * chaque import du calendrier fédéral n'est pas une option.
 *
 * Deux greffes : un filtre ajoute la colonne à l'écran de correspondance de
 * l'importateur (sans quoi la seule valeur possible pour cette colonne est
 * « Do Not Import », et la donnée est perdue en silence), une action pose les
 * termes une fois l'événement enregistré.
 *
 * Pourquoi une action après coup, et non `tax_input` au moment de l'insertion
 * comme le fait TEC pour ses propres catégories : wp_insert_post() n'honore
 * `tax_input` que si `current_user_can( $tax->cap->assign_terms )`, soit ici
 * `edit_posts`. La file d'attente de l'importateur peut être vidée par
 * wp-cron, sans utilisateur courant — l'événement serait alors créé et sa
 * catégorie d'âge jetée, sans la moindre trace. wp_set_object_terms() n'a pas
 * cette barrière.
 *
 * Les termes absents sont ignorés, jamais créés : le référentiel FFJDA est
 * seedé (Domain\Seed) et un libellé mal orthographié dans un tableur ne doit
 * pas silencieusement ajouter une dixième catégorie d'âge au club. Il est en
 * revanche journalisé, sans quoi la ligne fautive resterait invisible jusqu'à
 * ce qu'un adhérent signale une date manquante dans son agenda.
 *
 * @package wp-jcmv
 */

namespace JCMV\Integration;

use JCMV\Registration\Taxonomies;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TecImport {

	/** Clé de la colonne ajoutée au mapping de l'importateur TEC. */
	private const COLUMN = 'jcmv_categorie_age';

	/** Séparateur admis entre plusieurs catégories d'une même cellule. */
	private const SEPARATOR = ',';

	/** CPT de The Events Calendar. */
	private const TEC_CPT = 'tribe_events';

	public static function register(): void {
		add_filter( 'tribe_events_importer_event_column_names', array( self::class, 'add_column' ) );
		add_action( 'tec_events_csv_importer_post_update', array( self::class, 'assign_terms' ), 10, 3 );
	}

	/**
	 * Ajoute la colonne à la liste déroulante de l'écran de correspondance.
	 *
	 * @param array<string,string> $columns Colonnes proposées par TEC.
	 * @return array<string,string>
	 */
	public static function add_column( array $columns ): array {
		$columns[ self::COLUMN ] = __( 'Catégorie d\'âge JCMV', 'wp-jcmv' );

		return $columns;
	}

	/**
	 * Pose les catégories d'âge sur l'événement qui vient d'être importé.
	 *
	 * L'affectation remplace les termes existants plutôt que de s'y ajouter :
	 * le fichier fédéral fait autorité sur les événements qu'il décrit, et
	 * c'est déjà le parti pris de TEC pour ses propres catégories.
	 *
	 * @param int          $post_id  Événement créé ou mis à jour.
	 * @param array<mixed> $record   Ligne brute du CSV.
	 * @param object       $importer Instance de l'importateur TEC.
	 */
	public static function assign_terms( $post_id, array $record, $importer ): void {
		// Tribe__Events__API::createEvent() peut renvoyer un WP_Error, que le
		// hook relaie tel quel : le caster en entier serait une fatale.
		if ( ! is_numeric( $post_id ) ) {
			return;
		}

		$post_id = (int) $post_id;

		// Le hook vit sur la classe de base de l'importateur : il se déclenche
		// aussi pour les imports de lieux et d'organisateurs, qui n'ont pas de
		// colonne de catégorie d'âge.
		if ( self::TEC_CPT !== get_post_type( $post_id ) ) {
			return;
		}

		if ( ! is_object( $importer ) || ! method_exists( $importer, 'get_value_by_key' ) ) {
			return;
		}

		$raw = trim( (string) $importer->get_value_by_key( $record, self::COLUMN ) );

		if ( '' === $raw ) {
			return;
		}

		list( $ids, $unknown ) = self::resolve( $raw );

		if ( array() !== $unknown ) {
			self::log_unknown( $post_id, $unknown );
		}

		if ( array() === $ids ) {
			return;
		}

		wp_set_object_terms( $post_id, $ids, Taxonomies::CATEGORIE_AGE );
	}

	/**
	 * Résout une cellule (« cadet » ou « Cadet, Junior ») en IDs de termes.
	 *
	 * Slug puis libellé, et le second n'est pas une politesse : le terme
	 * « Sénior / Vétéran » porte le slug `senior`, alors que
	 * sanitize_title() de son libellé donne `senior-veteran`. Pour cette
	 * catégorie-là, seule la recherche par nom aboutit. L'inverse est vrai
	 * pour une cellule saisie en slug. Les deux chemins servent.
	 *
	 * @param string $raw Contenu de la cellule.
	 * @return array{0: int[], 1: string[]} IDs résolus, puis valeurs inconnues.
	 */
	private static function resolve( string $raw ): array {
		$ids     = array();
		$unknown = array();

		foreach ( explode( self::SEPARATOR, $raw ) as $value ) {
			$value = trim( $value );

			if ( '' === $value ) {
				continue;
			}

			$term = get_term_by( 'slug', sanitize_title( $value ), Taxonomies::CATEGORIE_AGE );

			if ( ! $term instanceof WP_Term ) {
				$term = get_term_by( 'name', $value, Taxonomies::CATEGORIE_AGE );
			}

			if ( $term instanceof WP_Term ) {
				$ids[] = (int) $term->term_id;
				continue;
			}

			$unknown[] = $value;
		}

		return array( array_values( array_unique( $ids ) ), $unknown );
	}

	/**
	 * Signale les valeurs qui n'ont trouvé aucun terme.
	 *
	 * Sous WP_DEBUG seulement : en production, une ligne fautive ne doit pas
	 * faire grossir un fichier de log que personne ne lit.
	 *
	 * @param int      $post_id Événement concerné.
	 * @param string[] $unknown Valeurs non résolues.
	 */
	private static function log_unknown( int $post_id, array $unknown ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf(
				'[wp-jcmv] Import TEC, événement #%d : catégorie(s) d\'âge inconnue(s) : %s. Aucun terme n\'est créé — vérifier l\'orthographe dans le CSV.',
				$post_id,
				implode( ', ', $unknown )
			)
		);
	}
}
