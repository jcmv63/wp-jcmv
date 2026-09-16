<?php
/**
 * Événements « toute la journée » dans les exports iCal de TEC.
 *
 * LE SYMPTÔME. Un événement coché « Évènement sur toute la journée » sortait
 * dans les flux ICS avec une partie horaire :
 *
 *   DTSTART;TZID=Europe/Paris:20260926T000000
 *   DTEND;TZID=Europe/Paris:20260926T235959
 *
 * au lieu de la forme RFC 5545 (§3.6.1) d'un événement journée entière :
 *
 *   DTSTART;VALUE=DATE:20260926
 *   DTEND;VALUE=DATE:20260927
 *
 * Les applications calendrier l'affichaient alors comme un créneau de
 * 00:00 à 23:59, qui occupe toute la grille horaire, au lieu d'un bandeau en
 * tête de journée ; sur un appareil réglé sur un autre fuseau, il débordait
 * même sur deux jours.
 *
 * LA CAUSE est une incohérence interne à TEC (constatée en 6.17.4) :
 *
 * - le module éditeur déclare `_EventAllDay` comme méta booléenne
 *   (`src/Tribe/Editor/Meta.php` : `register_meta( …, $this->boolean() )`),
 *   avec `filter_var( FILTER_VALIDATE_BOOLEAN )` pour nettoyage. WordPress
 *   applique ce nettoyage à TOUTE écriture de la méta, pas seulement à la
 *   REST : le `'yes'` envoyé par le formulaire est stocké `'1'`. En
 *   production, les 27 événements journée entière portaient tous `1` ;
 * - l'export iCal (`src/Tribe/iCal.php`, `get_ical_output_for_an_event()`)
 *   exige pourtant la chaîne exacte : `'yes' === get_post_meta(…)`. Le test
 *   n'est donc jamais vrai ;
 * - partout ailleurs, TEC est tolérant (`tribe_event_is_all_day()` accepte
 *   `yes`, `true` et `1`) : l'admin et le site affichaient juste, seul le
 *   flux mentait.
 *
 * LE CORRECTIF réécrit DTSTART/DTEND via le filtre public
 * `tribe_ical_feed_item`, avec le test tolérant de TEC. Réécrire la méta en
 * base ne servirait à rien — la sauvegarde suivante remettrait `1` — et
 * forcer `'yes'` au nettoyage contredirait la déclaration booléenne dont
 * dépend l'éditeur de blocs.
 *
 * Le filtre s'applique à tous les exports de TEC, donc à nos flux
 * `/agenda/*.ics` (ADR-004) comme au bouton « Ajouter au calendrier » de la
 * fiche événement.
 *
 * Les dates viennent des métas locales `_EventStartDate` / `_EventEndDate`
 * (`Y-m-d H:i:s`, heure du fuseau de l'événement) : seule leur partie date
 * compte, sans conversion de fuseau — c'est précisément ce qu'est une date
 * « flottante » en iCalendar. DTEND est exclusive (RFC 5545 §3.6.1) : c'est
 * le lendemain du dernier jour, ce qui couvre aussi les événements sur
 * plusieurs jours.
 *
 * L'UID n'est pas touché : les clients déjà abonnés mettent à jour
 * l'événement existant au lieu de le dupliquer.
 *
 * Le jour où TEC corrigera son test, ce filtre deviendra inerte (il laisse
 * passer un DTSTART déjà en `VALUE=DATE`) et pourra être retiré.
 *
 * @package wp-jcmv
 */

namespace JCMV\Front;

use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IcalAllDay {

	public static function register(): void {
		add_filter( 'tribe_ical_feed_item', array( self::class, 'fix_dates' ), 10, 2 );
	}

	/**
	 * Remet un événement journée entière au format `VALUE=DATE`.
	 *
	 * @param mixed $item       Lignes du VEVENT, indexées par propriété.
	 * @param mixed $event_post Événement (WP_Post attendu).
	 *
	 * @return mixed
	 */
	public static function fix_dates( $item, $event_post ) {
		if ( ! is_array( $item ) || ! function_exists( 'tribe_event_is_all_day' ) ) {
			return $item;
		}

		$post = get_post( $event_post );

		if ( ! $post || ! tribe_event_is_all_day( $post->ID ) ) {
			return $item;
		}

		// TEC a déjà produit la bonne forme : rien à faire.
		if ( isset( $item['DTSTART'] ) && str_starts_with( (string) $item['DTSTART'], 'DTSTART;VALUE=DATE:' ) ) {
			return $item;
		}

		$start = self::day( get_post_meta( $post->ID, '_EventStartDate', true ) );
		$end   = self::day( get_post_meta( $post->ID, '_EventEndDate', true ) );

		// Dates illisibles : mieux vaut la sortie de TEC, imparfaite mais
		// datée, qu'un VEVENT sans DTSTART.
		if ( null === $start ) {
			return $item;
		}

		if ( null === $end || $end < $start ) {
			$end = $start;
		}

		// Réaffecter des clés existantes conserve leur position dans le tableau,
		// donc l'ordre des lignes du VEVENT.
		$item['DTSTART'] = 'DTSTART;VALUE=DATE:' . $start->format( 'Ymd' );
		$item['DTEND']   = 'DTEND;VALUE=DATE:' . $end->modify( '+1 day' )->format( 'Ymd' );

		return $item;
	}

	/**
	 * Partie date d'une méta `Y-m-d H:i:s`, à minuit UTC pour que l'ajout d'un
	 * jour ne subisse aucun changement d'heure.
	 *
	 * @param mixed $value Valeur brute de la méta.
	 */
	private static function day( $value ): ?DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat(
			'!Y-m-d',
			substr( (string) $value, 0, 10 ),
			new DateTimeZone( 'UTC' )
		);

		return false === $date ? null : $date;
	}
}
