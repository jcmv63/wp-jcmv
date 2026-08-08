<?php
/**
 * API REST du module club — namespace jcmv/v1 (ADR-002).
 *
 * Minimale et fermée : uniquement ce que l'app admin « Saisons » consomme.
 * Tous les endpoints exigent la capability manage_jcmv_club ; le nonce REST
 * est géré par @wordpress/api-fetch. Aucun endpoint public : l'affichage
 * front appellera directement les repositories (rendu serveur).
 *
 * Les listes cours/lieux sont servies par le REST natif des CPT (wp/v2).
 *
 * @package wp-jcmv
 */

namespace JCMV\Rest;

use JCMV\Domain\Integrity;
use JCMV\Domain\PricingRepository;
use JCMV\Domain\Schema;
use JCMV\Domain\ScheduleRepository;
use JCMV\Domain\SeasonRepository;
use JCMV\Domain\Transaction;
use JCMV\Registration\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SeasonsController {

	private const NS = 'jcmv/v1';

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Plafond de lignes acceptées par lot.
	 *
	 * Borne de transport, pas règle métier : aucun cours n'a cinquante créneaux,
	 * et sans plafond une charge utile aberrante ferait boucler des insertions
	 * dans une transaction ouverte.
	 */
	private const MAX_ROWS = 50;

	public static function routes(): void {
		$perm = static function (): bool {
			return current_user_can( Capabilities::MANAGE );
		};

		register_rest_route(
			self::NS,
			'/seasons',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'list_seasons' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'create_season' ),
					'permission_callback' => $perm,
					'args'                => array(
						'start_year' => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 2000,
							'maximum'  => 2100,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/seasons/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( self::class, 'delete_season' ),
				'permission_callback' => $perm,
				'args'                => self::id_args(),
			)
		);

		register_rest_route(
			self::NS,
			'/seasons/(?P<id>\d+)/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'activate_season' ),
				'permission_callback' => $perm,
				'args'                => self::id_args(),
			)
		);

		register_rest_route(
			self::NS,
			'/seasons/(?P<id>\d+)/prepare',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'prepare_next' ),
				'permission_callback' => $perm,
				'args'                => self::id_args(),
			)
		);

		register_rest_route(
			self::NS,
			'/seasons/(?P<id>\d+)/fees',
			array(
				'methods'             => 'PUT',
				'callback'            => array( self::class, 'update_fees' ),
				'permission_callback' => $perm,
				'args'                => self::id_args() + array(
					'licence_amount'  => self::amount_arg(),
					'adhesion_amount' => self::amount_arg(),
					'licence_note'    => self::note_arg(),
					'adhesion_note'   => self::note_arg(),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/seasons/(?P<id>\d+)/grid',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'grid' ),
				'permission_callback' => $perm,
				'args'                => self::id_args(),
			)
		);

		/*
		 * Une seule route pour les deux lots d'un cours. Il y en avait deux —
		 * .../schedules et .../pricing — que l'app appelait à la suite : un
		 * échec sur la seconde laissait la première écrite, et le bureau lisait
		 * « Sauvegarde impossible » sur une sauvegarde à moitié faite
		 * (revue §1.3). Le contrat d'ADR-002 est « par lot cours × saison » :
		 * il ne se tient qu'avec une seule requête, dans une seule transaction.
		 */
		register_rest_route(
			self::NS,
			'/seasons/(?P<id>\d+)/courses/(?P<course_id>\d+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( self::class, 'save_course' ),
				'permission_callback' => $perm,
				'args'                => self::course_args(),
			)
		);
	}

	/* ---------- Schémas d'arguments ---------- */

	/**
	 * Identifiant de saison porté par l'URL.
	 *
	 * Le motif `(?P<id>\d+)` de la route garantit déjà des chiffres ; le schéma
	 * ajoute la conversion en entier et le rejet du zéro, et surtout documente
	 * l'endpoint dans le manifeste REST.
	 */
	private static function id_args(): array {
		return array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
		);
	}

	/**
	 * Arguments de la route d'écriture par lot cours × saison.
	 *
	 * `schedules` et `pricing` ne sont volontairement décrits qu'au gros grain —
	 * des tableaux, bornés. Le contenu des lignes est validé par les
	 * repositories, qui rendent des messages exploitables par le bureau
	 * (« Créneau 2 : lieu manquant. ») là où un schéma JSON produirait
	 * « schedules[1][location_id] n'est pas de type integer ». Dupliquer la
	 * validation ici donnerait deux jeux de règles à maintenir, et le moins
	 * lisible des deux gagnerait la course.
	 */
	private static function course_args(): array {
		return self::id_args() + array(
			'course_id' => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
			'schedules' => self::rows_arg(),
			'pricing'   => self::rows_arg(),
		);
	}

	/** Lot de lignes à écrire ; absent vaut « aucune ligne », donc table rase. */
	private static function rows_arg(): array {
		return array(
			'type'     => 'array',
			'default'  => array(),
			'maxItems' => self::MAX_ROWS,
			'items'    => array( 'type' => 'object' ),
		);
	}

	/** Montant en euros, borné par le type DECIMAL(8,2) de la colonne. */
	private static function amount_arg(): array {
		return array(
			'type'     => 'number',
			'required' => true,
			'minimum'  => 0,
			'maximum'  => Schema::AMOUNT_MAX,
		);
	}

	/** Mention libre accompagnant un montant ; varchar(190) en base. */
	private static function note_arg(): array {
		return array(
			'type'              => 'string',
			'default'           => '',
			'maxLength'         => 190,
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	/* ---------- Saisons ---------- */

	public static function list_seasons(): WP_REST_Response {
		$repo = new SeasonRepository();

		return rest_ensure_response( array_map( array( self::class, 'format_season' ), $repo->all() ) );
	}

	public static function create_season( WP_REST_Request $request ) {
		$repo = new SeasonRepository();
		$id   = $repo->create( (int) $request['start_year'] );

		if ( is_wp_error( $id ) ) {
			return self::error( $id );
		}

		return self::season_response( $repo, $id );
	}

	public static function delete_season( WP_REST_Request $request ) {
		$repo   = new SeasonRepository();
		$result = $repo->delete( (int) $request['id'] );

		return is_wp_error( $result ) ? self::error( $result ) : rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function activate_season( WP_REST_Request $request ) {
		$repo   = new SeasonRepository();
		$result = $repo->activate( (int) $request['id'] );

		if ( is_wp_error( $result ) ) {
			return self::error( $result );
		}

		return rest_ensure_response( array_map( array( self::class, 'format_season' ), $repo->all() ) );
	}

	public static function prepare_next( WP_REST_Request $request ) {
		$repo = new SeasonRepository();
		$id   = $repo->prepare_next( (int) $request['id'] );

		if ( is_wp_error( $id ) ) {
			return self::error( $id );
		}

		return rest_ensure_response( array_map( array( self::class, 'format_season' ), $repo->all() ) );
	}

	public static function update_fees( WP_REST_Request $request ) {
		$repo   = new SeasonRepository();
		$result = $repo->update_fees(
			(int) $request['id'],
			(float) $request->get_param( 'licence_amount' ),
			(float) $request->get_param( 'adhesion_amount' ),
			sanitize_text_field( (string) $request->get_param( 'licence_note' ) ),
			sanitize_text_field( (string) $request->get_param( 'adhesion_note' ) )
		);

		if ( is_wp_error( $result ) ) {
			return self::error( $result );
		}

		return self::season_response( $repo, (int) $request['id'] );
	}

	/* ---------- Grille créneaux + tarifs ---------- */

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function grid( WP_REST_Request $request ) {
		$season_id = (int) $request['id'];

		// Une saison inconnue renvoyait une grille vide en 200 : indiscernable
		// d'une saison réelle sans créneau, pour le client comme pour le
		// diagnostic.
		$season = Integrity::season( $season_id );
		if ( is_wp_error( $season ) ) {
			return self::error( $season );
		}

		return rest_ensure_response(
			array(
				'schedules' => array_map( array( self::class, 'format_schedule' ), ( new ScheduleRepository() )->for_season( $season_id ) ),
				'pricing'   => array_map( array( self::class, 'format_pricing' ), ( new PricingRepository() )->for_season( $season_id ) ),
			)
		);
	}

	/**
	 * Écrit créneaux ET tarifs d'un cours, en tout ou rien.
	 *
	 * Les deux repositories ouvrent chacun leur transaction ; Transaction::run()
	 * les fond dans celle-ci, seule à valider ou annuler. Un tarif refusé annule
	 * donc aussi l'écriture des créneaux, et le message d'erreur redevient
	 * vrai : rien n'a été enregistré.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_course( WP_REST_Request $request ) {
		$season_id = (int) $request['id'];
		$course_id = (int) $request['course_id'];
		$schedules = (array) $request->get_param( 'schedules' );
		$pricing   = (array) $request->get_param( 'pricing' );

		$result = Transaction::run(
			static function () use ( $season_id, $course_id, $schedules, $pricing ) {
				$written = ( new ScheduleRepository() )->replace_for_course( $season_id, $course_id, $schedules );

				if ( is_wp_error( $written ) ) {
					return $written;
				}

				return ( new PricingRepository() )->replace_for_course( $season_id, $course_id, $pricing );
			}
		);

		if ( is_wp_error( $result ) ) {
			return self::error( $result );
		}

		return self::grid( $request );
	}

	/* ---------- Formatage ---------- */

	private static function format_season( object $s ): array {
		return array(
			'id'              => (int) $s->id,
			'start_year'      => (int) $s->start_year,
			'label'           => SeasonRepository::label( $s ),
			'status'          => $s->status,
			'licence_amount'  => (float) $s->licence_amount,
			'adhesion_amount' => (float) $s->adhesion_amount,
			'licence_note'    => $s->licence_note,
			'adhesion_note'   => $s->adhesion_note,
		);
	}

	private static function format_schedule( object $row ): array {
		return array(
			'id'          => (int) $row->id,
			'course_id'   => (int) $row->course_id,
			'location_id' => (int) $row->location_id,
			'weekday'     => (int) $row->weekday,
			'start_time'  => substr( $row->start_time, 0, 5 ),
			'end_time'    => substr( $row->end_time, 0, 5 ),
			'note'        => $row->note,
			'sort_order'  => (int) $row->sort_order,
		);
	}

	private static function format_pricing( object $row ): array {
		return array(
			'id'         => (int) $row->id,
			'course_id'  => (int) $row->course_id,
			'label'      => $row->label,
			'amount'     => (float) $row->amount,
			'period'     => $row->period,
			'note'       => $row->note,
			'sort_order' => (int) $row->sort_order,
		);
	}

	/**
	 * Relit et sérialise une saison, en gardant le cas où elle a disparu.
	 *
	 * `format_season()` attend un `object` : lui passer directement le retour de
	 * `find()` produisait une TypeError — donc un 500 opaque — si la ligne
	 * s'évaporait entre l'écriture et la relecture.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	private static function season_response( SeasonRepository $repo, int $id ) {
		$season = $repo->find( $id );

		if ( ! $season ) {
			return self::error( new WP_Error( 'jcmv_season_not_found', 'Saison introuvable après écriture.' ) );
		}

		return rest_ensure_response( self::format_season( $season ) );
	}

	/**
	 * Codes désignant une cible absente : 404 plutôt que 400, la requête étant
	 * bien formée — c'est la ressource qui manque.
	 */
	private const NOT_FOUND = array(
		'jcmv_season_not_found',
		'jcmv_course_not_found',
		'jcmv_location_not_found',
	);

	/**
	 * Attache le statut HTTP correspondant au code d'erreur.
	 *
	 * Le statut est déduit du code plutôt que passé par chaque appelant : une
	 * erreur ajoutée dans un repository obtient ainsi le bon statut sans qu'on
	 * ait à penser à revenir ici.
	 */
	private static function error( WP_Error $error ): WP_Error {
		$code = $error->get_error_code();

		if ( in_array( $code, self::NOT_FOUND, true ) ) {
			$status = 404;
		} elseif ( 'jcmv_db_error' === $code ) {
			// Écriture refusée par la base : la requête n'y est pour rien.
			$status = 500;
		} else {
			$status = 400;
		}

		$error->add_data( array( 'status' => $status ), $code );

		return $error;
	}
}
