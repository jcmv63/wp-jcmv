<?php
/**
 * Flux ICS d'abonnement au calendrier (ADR-004).
 *
 * Expose des URL stables, découplées du slug de The Events Calendar :
 *
 *   /agenda/tous.ics                 tous les événements
 *   /agenda/poussin.ics              une catégorie d'âge
 *   /agenda/poussin+benjamin.ics     plusieurs catégories
 *
 * Le séparateur est le `+` (le `-` est exclu : `mini-poussin` en contient
 * déjà un), la virgule est acceptée en alias. Attention, la sémantique est
 * l'inverse de celle des query vars natives de WordPress, où `+` signifie ET :
 * ici, `poussin+benjamin` réunit les deux catégories. C'est bien un OU — une
 * famille à deux enfants veut un calendrier, pas une intersection vide.
 *
 * La génération du fichier est déléguée à TEC (`generate_ical_feed( $ids,
 * false )` retourne la chaîne sans envoyer d'en-têtes ni appeler
 * `tribe_exit()`) : on ne réécrit ni VTIMEZONE, ni l'échappement RFC 5545.
 * Seules la sélection et les en-têtes sont à nous.
 *
 * @package wp-jcmv
 */

namespace JCMV\Front;

use JCMV\Registration\Taxonomies;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CalendarFeed {

	/** Base d'URL des flux. Voir ADR-004 pour le rejet de `/webcal/`. */
	public const BASE = 'agenda';

	/** Query var portant la liste de slugs demandée. */
	private const QUERY_VAR = 'jcmv_agenda';

	/**
	 * Slug réservé du flux global : aucune catégorie d'âge ne pourra le porter.
	 */
	public const ALL = 'tous';

	/** CPT de The Events Calendar. */
	private const TEC_CPT = 'tribe_events';

	/**
	 * Profondeur d'historique, en mois. Fenêtre glissante plutôt que découpage
	 * par saison : le flux ne se vide pas à cheval sur deux saisons lorsque les
	 * événements de la suivante ne sont pas encore saisis.
	 */
	private const PAST_MONTHS = 6;

	/** Durée de cache annoncée aux clients calendrier, en secondes. */
	private const CACHE_TTL = HOUR_IN_SECONDS;

	public static function register(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( self::class, 'add_query_var' ) );
		// Priorité 5 : passer avant le `do_ical_template()` de TEC, qui est
		// branché sur le même hook en priorité par défaut et se déclenche dès
		// qu'un `?ical` traîne dans la requête.
		add_action( 'template_redirect', array( self::class, 'maybe_render' ), 5 );
		// Deux crochets pour une seule intention, faute de pouvoir déboguer le
		// rendu de TEC d'ici : `tribe_template_done` coupe le plus tôt possible
		// (`Template.php:809`), `tribe_template_pre_html` sert de filet
		// (`Template.php:891`). Le second identifie le gabarit par son chemin
		// de fichier, insensible à la façon dont TEC normalise les noms.
		add_filter( 'tribe_template_done', array( self::class, 'skip_tec_ical_template' ), 10, 2 );
		add_filter( 'tribe_template_pre_html', array( self::class, 'hide_tec_dropdown' ), 10, 2 );
	}

	/**
	 * Le gabarit visé : la liste déroulante « S'abonner au calendrier » incluse
	 * par `views/v2/{month,list,day}.php`.
	 */
	private const TEC_ICAL_TEMPLATE = 'components/ical-link';

	/**
	 * Coupe le rendu du gabarit avant même sa résolution.
	 *
	 * @param mixed                $done Non-`null` pour annuler le rendu.
	 * @param array<string>|string $name Nom du gabarit, tel que passé à `template()`.
	 *
	 * @return mixed
	 */
	public static function skip_tec_ical_template( $done, $name ) {
		$slug = is_array( $name ) ? implode( '/', $name ) : (string) $name;

		return self::TEC_ICAL_TEMPLATE === $slug ? true : $done;
	}

	/**
	 * Retire la liste déroulante « S'abonner au calendrier » de TEC des vues
	 * calendrier, où le bloc `jcmv/abonnement-calendrier` la remplace — lui
	 * sait filtrer par catégorie d'âge, elle non. Deux dispositifs
	 * d'abonnement sur la même page ne feraient qu'égarer les familles.
	 *
	 * On identifie le gabarit par son chemin absolu, seule donnée qui ne
	 * dépende pas de la construction des espaces de noms de TEC. La fiche d'un
	 * événement passe par `blocks/event-links`, un fichier distinct : ses
	 * boutons « Ajouter au calendrier » sont préservés, comme le veut l'ADR-004.
	 *
	 * Le filtre global `tec_views_v2_use_subscribe_links` avait été écarté :
	 * il fait sortir `register()` avant la pose des crochets et emporterait
	 * aussi ceux de la fiche événement.
	 *
	 * @param string|null $html Court-circuit du rendu, `null` par défaut.
	 * @param string      $file Chemin complet du gabarit.
	 *
	 * @return string|null
	 */
	public static function hide_tec_dropdown( $html, $file ) {
		$cible = 'views/v2/' . self::TEC_ICAL_TEMPLATE . '.php';

		return str_ends_with( (string) $file, $cible ) ? '' : $html;
	}

	/**
	 * `top` : la règle est évaluée avant la résolution des pages. Une page de
	 * slug « agenda » ne masquerait donc pas les flux — seule une éventuelle
	 * page enfant `/agenda/quelque-chose/` le serait (ADR-004).
	 */
	public static function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^' . self::BASE . '/([^/]+)\.ics$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	public static function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * URL publique d'un flux.
	 *
	 * @param string[] $slugs  Slugs de catégories d'âge ; vide = flux global.
	 * @param bool     $webcal Schéma `webcal://` (abonnement) plutôt que https.
	 */
	public static function url( array $slugs = array(), bool $webcal = false ): string {
		$name = $slugs ? implode( '+', $slugs ) : self::ALL;
		$url  = home_url( '/' . self::BASE . '/' . $name . '.ics' );

		if ( $webcal ) {
			// `webcal://` fait ouvrir l'application calendrier au lieu de
			// télécharger un fichier figé. C'est toute la différence entre
			// s'abonner et exporter (ADR-004).
			$url = preg_replace( '#^https?://#', 'webcal://', $url );
		}

		return $url;
	}

	/**
	 * Les catégories d'âge proposables à l'abonnement, dans l'ordre des bornes
	 * d'âge (Baby Judo → Sénior) plutôt qu'alphabétique, qui placerait Cadet
	 * avant Éveil Judo. À défaut de borne, on retombe sur le nom.
	 *
	 * @return WP_Term[]
	 */
	public static function categories(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::CATEGORIE_AGE,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}

		// Le slug réservé du flux global ne peut pas désigner une catégorie.
		$terms = array_values(
			array_filter(
				$terms,
				static fn( WP_Term $term ): bool => self::ALL !== $term->slug
			)
		);

		usort(
			$terms,
			static function ( WP_Term $a, WP_Term $b ): int {
				$age_a = (int) get_term_meta( $a->term_id, 'age_min', true );
				$age_b = (int) get_term_meta( $b->term_id, 'age_min', true );

				return $age_a === $age_b
					? strnatcasecmp( $a->name, $b->name )
					: $age_a <=> $age_b;
			}
		);

		return $terms;
	}

	/**
	 * Sert le flux si la requête en vise un. Sinon, rend la main.
	 */
	public static function maybe_render(): void {
		$requested = get_query_var( self::QUERY_VAR );

		if ( '' === $requested || null === $requested ) {
			return;
		}

		// TEC absent ou désactivé : 404 franc plutôt qu'un fichier vide.
		if ( ! post_type_exists( self::TEC_CPT ) || ! function_exists( 'tribe' ) ) {
			self::not_found();
			return;
		}

		$slugs = self::parse_slugs( (string) $requested );

		if ( null === $slugs ) {
			self::not_found();
			return;
		}

		$events = self::query_events( $slugs );
		self::send( $events, $slugs );
	}

	/**
	 * Décode le segment d'URL en liste de slugs validés.
	 *
	 * @return string[]|null Liste de slugs, tableau vide pour le flux global,
	 *                       `null` si la demande est invalide (→ 404).
	 */
	private static function parse_slugs( string $requested ): ?array {
		$requested = strtolower( trim( $requested ) );

		if ( self::ALL === $requested ) {
			return array();
		}

		/*
		 * L'espace est un séparateur au même titre que le `+`, et ce n'est pas
		 * une coquetterie : WordPress construit la cible de réécriture en chaîne
		 * de requête (`index.php?jcmv_agenda=$matches[1]`) puis la passe à
		 * `parse_str()`, qui décode tout `+` en espace. Le segment
		 * `poussin+benjamin` nous arrive donc en « poussin benjamin ». La
		 * virgule est l'alias documenté, `%2B` la forme ré-encodée par certains
		 * clients calendrier.
		 */
		$asked = preg_split( '/[+,\s]+/', str_replace( '%2b', '+', $requested ), -1, PREG_SPLIT_NO_EMPTY );

		if ( ! $asked ) {
			return null;
		}

		$known = wp_list_pluck( self::categories(), 'slug' );
		$slugs = array_values( array_unique( array_intersect( $asked, $known ) ) );

		// Un seul slug inconnu invalide la demande : mieux vaut un 404 qu'un
		// calendrier silencieusement amputé, que personne ne remarquerait.
		return count( $slugs ) === count( array_unique( $asked ) ) ? $slugs : null;
	}

	/**
	 * Les événements du flux.
	 *
	 * @param string[] $slugs Vide = toutes catégories confondues.
	 *
	 * @return int[] IDs d'événements.
	 */
	private static function query_events( array $slugs ): array {
		$args = array(
			'post_type'              => self::TEC_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			// Clauses nommées plutôt qu'un couple meta_key/orderby : cela évite
			// la jointure en double que provoque un meta_key portant sur une
			// autre clé que celle filtrée.
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- volume de l'ordre de la centaine.
				'relation' => 'AND',
				'fin'      => array(
					'key'     => '_EventEndDate',
					'value'   => gmdate( 'Y-m-d H:i:s', strtotime( '-' . self::PAST_MONTHS . ' months' ) ),
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
				'debut'    => array(
					'key'     => '_EventStartDate',
					'compare' => 'EXISTS',
					'type'    => 'DATETIME',
				),
			),
			'orderby'                => array( 'debut' => 'ASC' ),
		);

		if ( $slugs ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- volume de l'ordre de la centaine.
				'relation' => 'OR',
				array(
					'taxonomy' => Taxonomies::CATEGORIE_AGE,
					'field'    => 'slug',
					'terms'    => $slugs,
				),
				// Un événement sans catégorie d'âge vaut « tous publics » et
				// figure donc dans tous les flux : AG, gala, tournoi ouvert
				// (ADR-004).
				array(
					'taxonomy' => Taxonomies::CATEGORIE_AGE,
					'operator' => 'NOT EXISTS',
				),
			);
		}

		return array_map( 'intval', get_posts( $args ) );
	}

	/**
	 * En-têtes puis corps du fichier. Ne rend jamais la main.
	 *
	 * @param int[]    $events IDs d'événements.
	 * @param string[] $slugs  Catégories demandées.
	 */
	private static function send( array $events, array $slugs ): void {
		/*
		 * Validateurs calculés AVANT le corps : c'est tout l'intérêt du 304.
		 * generate_ical_feed() est de loin l'appel le plus coûteux de cette
		 * route, et c'est la seule route du plugin qu'un logiciel relève en
		 * boucle. La calculer pour la jeter serait payer le prix fort pour
		 * n'économiser que la bande passante.
		 */
		$last_modified = self::last_modified( $events );

		/*
		 * JCMV_VERSION entre dans l'empreinte : une mise à jour qui change la
		 * composition du flux (nouvelle fenêtre d'historique, autre nom de
		 * calendrier) doit invalider les caches clients, même si aucun
		 * événement n'a bougé depuis.
		 */
		$etag = '"' . md5( $last_modified . '|' . implode( '+', $slugs ) . '|' . count( $events ) . '|' . JCMV_VERSION ) . '"';

		// Les validateurs accompagnent un 304 comme un 200 : envoyés d'abord.
		header( 'Cache-Control: public, max-age=' . self::CACHE_TTL );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );
		header( 'ETag: ' . $etag );

		if ( self::client_is_up_to_date( $etag, $last_modified ) ) {
			status_header( 304 );
			exit;
		}

		$name = self::calendar_name( $slugs );

		$rename = static fn(): string => $name;
		add_filter( 'tribe_ical_feed_calname', $rename );

		// `false` : on récupère la chaîne, TEC n'envoie ni en-tête ni exit.
		// Sur un jeu vide, generate_ical_feed() court-circuite et renvoie une
		// chaîne vide ; on sert alors un calendrier valide mais sans événement,
		// pour qu'un abonnement déjà pris ne casse pas.
		$body = $events
			? (string) tribe( 'tec.iCal' )->generate_ical_feed( $events, false )
			: '';

		remove_filter( 'tribe_ical_feed_calname', $rename );

		if ( '' === trim( $body ) ) {
			$body = self::empty_calendar( $name );
		}

		header( 'Content-Type: text/calendar; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . self::filename( $slugs ) . '"' );
		// Un fichier .ics n'a rien à faire dans un index de recherche.
		header( 'X-Robots-Tag: noindex' );

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- flux ICS, échappement RFC 5545 fait par TEC.
		exit;
	}

	/**
	 * Le client détient-il déjà cette version du flux ?
	 *
	 * RFC 9110 §13.1.3 : `If-None-Match` prime sur `If-Modified-Since`. Si le
	 * client présente une empreinte, elle fait autorité — on ne retombe pas sur
	 * la date, sous peine de renvoyer un 304 à un client qui détient autre
	 * chose.
	 *
	 * @param string $etag          Empreinte du flux, guillemets compris.
	 * @param int    $last_modified Horodatage de dernière modification.
	 */
	private static function client_is_up_to_date( string $etag, int $last_modified ): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- comparé octet à octet, jamais affiché ni stocké.
		$if_none_match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( (string) wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';

		if ( '' !== $if_none_match ) {
			foreach ( explode( ',', $if_none_match ) as $candidat ) {
				$candidat = trim( $candidat );

				// Un intermédiaire peut marquer l'empreinte comme validateur
				// faible (`W/"…"`) : la comparaison reste pertinente ici, le
				// flux ne variant pas octet à octet à empreinte égale.
				if ( str_starts_with( $candidat, 'W/' ) ) {
					$candidat = substr( $candidat, 2 );
				}

				if ( '*' === $candidat || hash_equals( $etag, $candidat ) ) {
					return true;
				}
			}

			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passé à strtotime(), jamais affiché.
		$depuis = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? (string) wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) : '';

		if ( '' === $depuis ) {
			return false;
		}

		$horodatage = strtotime( $depuis );

		return false !== $horodatage && $last_modified <= $horodatage;
	}

	/**
	 * Calendrier vide mais syntaxiquement valide : les clients gardent
	 * l'abonnement au lieu de le signaler en erreur.
	 */
	private static function empty_calendar( string $name ): string {
		return implode(
			"\r\n",
			array(
				'BEGIN:VCALENDAR',
				'VERSION:2.0',
				'PRODID:-//' . self::escape( get_bloginfo( 'name' ) ) . '//wp-jcmv//FR',
				'CALSCALE:GREGORIAN',
				'METHOD:PUBLISH',
				'X-WR-CALNAME:' . self::escape( $name ),
				'END:VCALENDAR',
				'',
			)
		);
	}

	/**
	 * Échappement RFC 5545 (utilisé seulement pour le calendrier vide : le
	 * reste du temps, c'est TEC qui s'en charge).
	 */
	private static function escape( string $text ): string {
		return str_replace(
			array( '\\', ';', ',', "\n" ),
			array( '\\\\', '\;', '\,', '\n' ),
			$text
		);
	}

	/**
	 * Nom affiché dans l'application calendrier, une fois l'abonnement pris.
	 * C'est souvent tout ce que la personne verra du flux : il doit dire le
	 * club *et* le filtre.
	 *
	 * @param string[] $slugs Catégories demandées.
	 */
	private static function calendar_name( array $slugs ): string {
		$site = get_bloginfo( 'name' );

		if ( ! $slugs ) {
			return sprintf(
				/* translators: %s : nom du site. */
				__( '%s — tous les événements', 'wp-jcmv' ),
				$site
			);
		}

		$names = array();
		foreach ( self::categories() as $term ) {
			if ( in_array( $term->slug, $slugs, true ) ) {
				$names[] = $term->name;
			}
		}

		return sprintf(
			/* translators: 1 : nom du site, 2 : liste de catégories d'âge. */
			__( '%1$s — %2$s', 'wp-jcmv' ),
			$site,
			implode( ', ', $names )
		);
	}

	/**
	 * @param string[] $slugs Catégories demandées.
	 */
	private static function filename( array $slugs ): string {
		return 'jcmv-' . ( $slugs ? implode( '-', $slugs ) : self::ALL ) . '.ics';
	}

	/**
	 * Date de dernière modification du jeu, pour `Last-Modified` et `ETag` :
	 * un client qui repasse sans changement côté club obtient un 304.
	 *
	 * @param int[] $events IDs d'événements.
	 */
	private static function last_modified( array $events ): int {
		if ( ! $events ) {
			return time();
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $events ), '%d' ) );

		$latest = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- agrégat sur clés primaires, non cacheable utilement.
			$wpdb->prepare(
				"SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE ID IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders générés au-dessus.
				...$events
			)
		);

		return $latest ? (int) strtotime( $latest . ' GMT' ) : time();
	}

	/**
	 * 404 propre. On se contente de marquer la requête et de rendre la main :
	 * le chargeur de templates, qui s'exécute après `template_redirect`, se
	 * chargera du rendu. Inclure soi-même `get_query_template( '404' )` ne
	 * fonctionnerait pas avec un thème de blocs comme `jcmv-theme`, dont le
	 * `404.html` n'est pas un template PHP.
	 */
	private static function not_found(): void {
		global $wp_query;

		$wp_query->set_404();
		set_query_var( self::QUERY_VAR, '' );
		status_header( 404 );
		nocache_headers();
	}
}
