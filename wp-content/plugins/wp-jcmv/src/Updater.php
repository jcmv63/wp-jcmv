<?php
/**
 * Mises à jour du plugin depuis le manifeste GitHub (branche `updates` du
 * monorepo, publié par le workflow release-plugin). Le bureau voit
 * « Mise à jour disponible » dans Extensions, comme pour n'importe quel
 * plugin (ADR-002).
 *
 * Updater volontairement minimal (pas de bibliothèque vendorée) : un
 * manifeste JSON { version, download_url, … }, un transient de cache,
 * deux filtres WordPress.
 *
 * @package wp-jcmv
 */

namespace JCMV;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Updater {

	/**
	 * Manifeste publié par la CI.
	 */
	private const MANIFEST_URL = 'https://raw.githubusercontent.com/jcmv63/wp-jcmv/updates/plugin.json';

	private const CACHE_KEY = 'jcmv_plugin_update_manifest';

	/** Durée de cache d'un manifeste correctement récupéré. */
	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Durée de cache après un échec — bien plus courte.
	 *
	 * L'échec doit être mis en cache : sans cela, chaque vérification de mise à
	 * jour relancerait un appel réseau de 10 secondes vers un service qu'on sait
	 * indisponible. Mais le mettre en cache 6 h comme un succès faisait qu'une
	 * coupure de trente secondes chez GitHub masquait les mises à jour pendant
	 * six heures. Le cache d'un échec protège de l'insistance, il ne doit pas
	 * prolonger la panne.
	 */
	private const CACHE_TTL_FAILURE = 15 * MINUTE_IN_SECONDS;

	public static function register(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( self::class, 'inject_update' ) );
		add_filter( 'plugins_api', array( self::class, 'plugin_details' ), 10, 3 );
	}

	/**
	 * Signale la mise à jour à WordPress si le manifeste est plus récent.
	 *
	 * @param object|false $transient Transient update_plugins.
	 */
	public static function inject_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$manifest = self::manifest();
		if ( empty( $manifest['version'] ) || empty( $manifest['download_url'] ) ) {
			return $transient;
		}

		if ( version_compare( $manifest['version'], JCMV_VERSION, '>' ) ) {
			$basename = plugin_basename( JCMV_PLUGIN_FILE );

			$transient->response[ $basename ] = (object) array(
				'slug'        => 'wp-jcmv',
				'plugin'      => $basename,
				'new_version' => $manifest['version'],
				'package'     => $manifest['download_url'],
				'url'         => $manifest['details_url'] ?? '',
			);
		}

		return $transient;
	}

	/**
	 * Fiche « Détails de la version » dans l'admin.
	 *
	 * @param false|object $result Résultat courant.
	 * @param string       $action Action demandée.
	 * @param object       $args   Arguments (slug…).
	 */
	public static function plugin_details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || 'wp-jcmv' !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		$manifest = self::manifest();
		if ( empty( $manifest['version'] ) ) {
			return $result;
		}

		return (object) array(
			'name'          => $manifest['name'] ?? 'JCMV — Gestion du club',
			'slug'          => 'wp-jcmv',
			'version'       => $manifest['version'],
			'requires'      => $manifest['requires'] ?? '',
			'requires_php'  => $manifest['requires_php'] ?? '',
			'last_updated'  => $manifest['last_updated'] ?? '',
			'download_link' => $manifest['download_url'],
			'homepage'      => $manifest['details_url'] ?? '',
			'sections'      => array(
				'description' => 'Gestion du club : cours, créneaux, tarifs et saisons du JCMV.',
				'changelog'   => sprintf(
					'<p><a href="%s">Notes de version sur GitHub</a></p>',
					esc_url( $manifest['details_url'] ?? '' )
				),
			),
		);
	}

	/**
	 * Manifeste JSON, mis en cache : 6 h en cas de succès, 15 min sinon.
	 */
	private static function manifest(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$manifest = array();
		$response = wp_remote_get( self::MANIFEST_URL, array( 'timeout' => 10 ) );

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $decoded ) ) {
				$manifest = $decoded;
			}
		}

		/*
		 * Un manifeste sans version est un échec, quelle qu'en soit la cause :
		 * réseau coupé, 404, JSON malformé, ou branche `updates` pas encore
		 * publiée. On le distingue du succès sur cette seule clé, celle dont
		 * inject_update() a besoin — inutile de multiplier les codes de retour
		 * pour un traitement identique.
		 */
		$ttl = empty( $manifest['version'] ) ? self::CACHE_TTL_FAILURE : self::CACHE_TTL;

		set_transient( self::CACHE_KEY, $manifest, $ttl );

		return $manifest;
	}
}
