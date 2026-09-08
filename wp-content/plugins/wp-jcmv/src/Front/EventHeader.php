<?php
/**
 * Bandeau de titre et barre d'actions de la fiche événement.
 *
 * La fiche de TEC ouvre sur un titre nu, une date sans année et un lien de
 * retour minuscule ; la seule chose vraiment visible y est « Ajouter au
 * calendrier », qui est l'action la moins utile. Un licencié qui ouvre cette
 * page a quatre questions — c'est pour qui, c'est quand, c'est où, qu'est-ce
 * que je dois faire — et aucune n'avait de réponse au-dessus de la ligne de
 * flottaison.
 *
 * Le bandeau reprend donc le traitement de l'archive Événements (anthracite,
 * titre en Oswald capitales) pour que la fiche appartienne visiblement au même
 * site, et porte les quatre réponses. La barre d'actions qui suit n'a qu'une
 * action principale.
 *
 * AUCUN GABARIT COPIÉ. `single-event.php` expose trois filtres autour de son
 * titre — `..._title_classes`, `..._title_html_before`, `..._title_html_after` —
 * et ils suffisent : le premier remplace la classe du `<h1>`, le deuxième ouvre
 * le bandeau avant lui, le troisième le referme après et enchaîne la barre.
 * Le `<h1>` de TEC reste le `<h1>` de la page, avec son contenu et sa place
 * dans le plan du document.
 *
 * TROIS ÉLÉMENTS DE TEC SONT MASQUÉS en CSS parce que ce bandeau les remplace,
 * et seulement pour cette raison : son lien de retour (`.tribe-events-back`),
 * sa ligne de date (`.tribe-events-schedule`) et son menu « Ajouter au
 * calendrier ». Les trois réapparaissent en supprimant la règle correspondante
 * dans components.css.
 *
 * Le menu de TEC proposait Google Agenda, iCalendar et Outlook derrière un
 * dépliant ; il est remplacé par un lien unique vers le fichier .ics de
 * l'événement, que les trois savent ouvrir. C'est un choix : on perd l'ajout
 * en un clic à Google Agenda sur ordinateur, on gagne un bouton lisible et une
 * barre d'actions cohérente. Revenir en arrière tient en une ligne.
 *
 * Vérifié sur TEC 6.17.1.
 *
 * @package wp-jcmv
 */

namespace JCMV\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EventHeader {

	public static function register(): void {
		add_filter( 'tribe_events_single_event_title_classes', array( self::class, 'title_classes' ), 10, 2 );
		add_filter( 'tribe_events_single_event_title_html_before', array( self::class, 'before_title' ), 10, 2 );
		add_filter( 'tribe_events_single_event_title_html_after', array( self::class, 'after_title' ), 10, 2 );
	}

	/**
	 * Ajoute notre classe à celles de TEC plutôt que de les remplacer : le
	 * plugin s'en sert ailleurs, et rien ne dit qu'il ne s'en servira pas plus.
	 *
	 * @param array<string> $classes  Classes proposées par TEC.
	 * @param int|null      $event_id Événement.
	 * @return array<string>
	 */
	public static function title_classes( $classes, $event_id = null ): array {
		$classes = is_array( $classes ) ? $classes : array();

		$classes[] = 'jcmv-event-header__title';

		return $classes;
	}

	/**
	 * Ouvre le bandeau, pose le retour et les badges, puis laisse venir le `<h1>`.
	 *
	 * @param string   $before   Ouverture du `<h1>` produite par TEC.
	 * @param int|null $event_id Événement.
	 */
	public static function before_title( $before, $event_id = null ): string {
		$retour = sprintf(
			'<a class="jcmv-event-header__back" href="%s">%s<span>%s</span></a>',
			esc_url( tribe_get_events_link() ),
			self::icone( 'chevron' ),
			esc_html__( 'Tous les événements', 'wp-jcmv' )
		);

		return '<div class="jcmv-event-header"><div class="jcmv-event-header__inner">'
			. $retour
			. self::badges( $event_id )
			. (string) $before;
	}

	/**
	 * Referme le `<h1>`, ajoute date et lieu, referme le bandeau, puis la barre.
	 *
	 * @param string   $after    Fermeture du `<h1>` produite par TEC.
	 * @param int|null $event_id Événement.
	 */
	public static function after_title( $after, $event_id = null ): string {
		$faits = array();

		$date = self::date_longue( $event_id );
		if ( '' !== $date ) {
			$faits[] = sprintf(
				'<span class="jcmv-event-header__fact">%s<span>%s</span></span>',
				self::icone( 'calendrier' ),
				esc_html( $date )
			);
		}

		$lieu = self::lieu( $event_id );
		if ( '' !== $lieu ) {
			$faits[] = sprintf(
				'<span class="jcmv-event-header__fact jcmv-event-header__fact--secondaire">%s<span>%s</span></span>',
				self::icone( 'epingle' ),
				esc_html( $lieu )
			);
		}

		return (string) $after
			. ( array() === $faits ? '' : '<div class="jcmv-event-header__facts">' . implode( '', $faits ) . '</div>' )
			. '</div></div>'
			. self::barre_actions( $event_id );
	}

	/**
	 * Badges de catégorie d'âge — la première chose qu'un parent cherche.
	 *
	 * La catégorie d'ÉVÉNEMENT n'y figure pas volontairement : elle reste une
	 * ligne du pied de fiche. Deux badges côte à côte dont un seul sert de
	 * filtre, c'est un badge de trop.
	 *
	 * @param int|null $event_id Événement.
	 */
	private static function badges( $event_id ): string {
		$noms = EventAgeMeta::names_for( $event_id );

		if ( array() === $noms ) {
			return '';
		}

		$items = '';
		foreach ( $noms as $nom ) {
			$items .= sprintf( '<li class="jcmv-badge">%s</li>', esc_html( $nom ) );
		}

		return sprintf( '<ul class="jcmv-event-ages jcmv-event-ages--header">%s</ul>', $items );
	}

	/**
	 * Date en toutes lettres, jour de la semaine compris.
	 *
	 * Le format est écrit ici plutôt que laissé au réglage d'affichage de TEC :
	 * ce réglage sert aussi au calendrier et aux listes, où « samedi 26
	 * septembre 2026 » serait trop long. Le bandeau, lui, a la place et le
	 * besoin — c'est l'information qu'on vient chercher.
	 *
	 * @param int|null $event_id Événement.
	 */
	private static function date_longue( $event_id ): string {
		$format = 'l j F Y';
		$debut  = (string) tribe_get_start_date( $event_id, false, $format );

		if ( '' === $debut ) {
			return '';
		}

		if ( ! tribe_event_is_multiday( $event_id ) ) {
			return self::capitale( $debut );
		}

		$fin = (string) tribe_get_display_end_date( $event_id, false, $format );

		return '' === $fin
			? self::capitale( $debut )
			: sprintf(
				/* translators: 1: date de début, 2: date de fin. */
				__( 'Du %1$s au %2$s', 'wp-jcmv' ),
				$debut,
				$fin
			);
	}

	/**
	 * Lieu résumé du bandeau : la ville, et le département entre parenthèses.
	 *
	 * Le nom de la salle en est volontairement absent. En haut de fiche, la
	 * question est « est-ce que je peux y aller ? », et c'est la ville qui y
	 * répond — « Halle Polyvalente » ne dit rien à qui n'y est jamais allé, et
	 * allongeait la ligne d'autant. Le nom complet reste dans le bloc Lieu du
	 * pied, à côté de l'adresse, là où on le cherche vraiment.
	 *
	 * Le numéro de département n'est ajouté que sur un code postal à cinq
	 * chiffres — un code étranger n'en donnerait pas un.
	 *
	 * @param int|null $event_id Événement.
	 */
	private static function lieu( $event_id ): string {
		if ( ! tribe_get_venue_id( $event_id ) ) {
			return '';
		}

		$ville = trim( wp_specialchars_decode( (string) tribe_get_city( $event_id ), ENT_QUOTES ) );

		if ( '' === $ville ) {
			return '';
		}

		$code = trim( (string) tribe_get_zip( $event_id ) );

		return 1 === preg_match( '/^\d{5}$/', $code )
			? $ville . ' (' . substr( $code, 0, 2 ) . ')'
			: $ville;
	}

	/**
	 * Barre d'actions : une action principale, deux secondaires.
	 *
	 * Le libellé du document vient de `EventLinks` : il se déduit de
	 * l'extension de l'URL saisie, et personne n'écrit « convocation » en dur
	 * pour un champ qui peut contenir autre chose.
	 *
	 * @param int|null $event_id Événement.
	 */
	private static function barre_actions( $event_id ): string {
		$actions = '';

		$document = (string) tribe_get_event_website_url( $event_id );
		if ( '' !== $document ) {
			$actions .= sprintf(
				'<a class="jcmv-event-actions__primary" href="%s" target="_blank" rel="noopener noreferrer" title="%s">%s<span>%s</span></a>',
				esc_url( $document ),
				esc_attr__( 'Ouvrir le document dans une nouvelle fenêtre', 'wp-jcmv' ),
				self::icone( 'telecharger' ),
				esc_html( EventLinks::website_label( '', $event_id ) )
			);
		}

		$ics = (string) tribe_get_single_ical_link();
		if ( '' !== $ics ) {
			$actions .= sprintf(
				'<a class="jcmv-event-actions__secondary" href="%s">%s<span>%s</span></a>',
				esc_url( $ics ),
				self::icone( 'agenda' ),
				esc_html__( 'Ajouter à mon agenda', 'wp-jcmv' )
			);
		}

		$itineraire = (string) tribe_get_map_link( $event_id );
		if ( '' !== $itineraire ) {
			$actions .= sprintf(
				'<a class="jcmv-event-actions__link" href="%s" target="_blank" rel="noopener noreferrer" title="%s">%s<span>%s</span></a>',
				esc_url( $itineraire ),
				esc_attr__( 'Ouvrir l\'itinéraire dans une nouvelle fenêtre', 'wp-jcmv' ),
				self::icone( 'epingle' ),
				esc_html__( 'Itinéraire', 'wp-jcmv' )
			);
		}

		if ( '' === $actions ) {
			return '';
		}

		return '<div class="jcmv-event-actions"><div class="jcmv-event-actions__inner">' . $actions . '</div></div>';
	}

	/**
	 * Majuscule initiale d'une date localisée.
	 *
	 * `date_i18n()` rend « samedi 26 septembre 2026 » — la minuscule est juste
	 * en français au fil du texte, pas en tête de ligne. `ucfirst()` ne suffit
	 * pas : il ne connaît pas l'UTF-8 et abîmerait un « août » ou un « été ».
	 *
	 * @param string $texte Date déjà formatée.
	 */
	private static function capitale( string $texte ): string {
		if ( '' === $texte ) {
			return $texte;
		}

		return mb_strtoupper( mb_substr( $texte, 0, 1 ) ) . mb_substr( $texte, 1 );
	}

	/**
	 * Icônes en SVG inline : elles suivent la couleur du texte et n'ajoutent
	 * aucune requête. `aria-hidden` parce qu'elles doublent un libellé visible.
	 *
	 * @param string $nom Identifiant de l'icône.
	 */
	private static function icone( string $nom ): string {
		$traces = array(
			'chevron'     => '<path d="M15 6 9 12l6 6"/>',
			'calendrier'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
			'epingle'     => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="2.6"/>',
			'telecharger' => '<path d="M12 3v12m0 0 4.5-4.5M12 15l-4.5-4.5"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
			'agenda'      => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M12 14v4M10 16h4"/>',
		);

		if ( ! isset( $traces[ $nom ] ) ) {
			return '';
		}

		return '<svg class="jcmv-icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"'
			. ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. $traces[ $nom ]
			. '</svg>';
	}
}
