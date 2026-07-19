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

use JCMV\Domain\PricingRepository;
use JCMV\Domain\ScheduleRepository;
use JCMV\Domain\SeasonRepository;
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
			)
		);

		register_rest_route(
			self::NS,
			'/seasons/(?P<id>\d+)/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'activate_season' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NS,
			'/seasons/(?P<id>\d+)/prepare',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'prepare_next' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NS,
			'/seasons/(?P<id>\d+)/fees',
			array(
				'methods'             => 'PUT',
				'callback'            => array( self::class, 'update_fees' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NS,
			'/seasons/(?P<id>\d+)/grid',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'grid' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NS,
			'/seasons/(?P<id>\d+)/courses/(?P<course_id>\d+)/schedules',
			array(
				'methods'             => 'PUT',
				'callback'            => array( self::class, 'save_schedules' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NS,
			'/seasons/(?P<id>\d+)/courses/(?P<course_id>\d+)/pricing',
			array(
				'methods'             => 'PUT',
				'callback'            => array( self::class, 'save_pricing' ),
				'permission_callback' => $perm,
			)
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

		return rest_ensure_response( self::format_season( $repo->find( $id ) ) );
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

		return rest_ensure_response( self::format_season( $repo->find( (int) $request['id'] ) ) );
	}

	/* ---------- Grille créneaux + tarifs ---------- */

	public static function grid( WP_REST_Request $request ): WP_REST_Response {
		$season_id = (int) $request['id'];

		return rest_ensure_response(
			array(
				'schedules' => array_map( array( self::class, 'format_schedule' ), ( new ScheduleRepository() )->for_season( $season_id ) ),
				'pricing'   => array_map( array( self::class, 'format_pricing' ), ( new PricingRepository() )->for_season( $season_id ) ),
			)
		);
	}

	public static function save_schedules( WP_REST_Request $request ) {
		$season_id = (int) $request['id'];
		$course_id = (int) $request['course_id'];
		$rows      = (array) $request->get_param( 'rows' );

		$result = ( new ScheduleRepository() )->replace_for_course( $season_id, $course_id, $rows );
		if ( is_wp_error( $result ) ) {
			return self::error( $result );
		}

		return self::grid( $request );
	}

	public static function save_pricing( WP_REST_Request $request ) {
		$season_id = (int) $request['id'];
		$course_id = (int) $request['course_id'];
		$rows      = (array) $request->get_param( 'rows' );

		$result = ( new PricingRepository() )->replace_for_course( $season_id, $course_id, $rows );
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

	private static function error( WP_Error $error ): WP_Error {
		$error->add_data( array( 'status' => 400 ) );
		return $error;
	}
}
