<?php
/**
 * Rendu serveur du bloc « Horaires & tarifs » : une carte par cours publié,
 * données de la saison active lues via les repositories (ADR-002 : pas de
 * REST public, rendu cacheable).
 *
 * Charte §06 : chaque carte porte un CTA — jamais de prix sans bouton.
 *
 * @package wp-jcmv
 */

use JCMV\Domain\AgeCalculator;
use JCMV\Domain\PricingRepository;
use JCMV\Domain\ScheduleRepository;
use JCMV\Domain\SeasonRepository;
use JCMV\Registration\PostTypes;
use JCMV\Registration\Taxonomies;

$jcmv_season = ( new SeasonRepository() )->active();

if ( ! $jcmv_season ) {
	if ( current_user_can( 'manage_jcmv_club' ) ) {
		echo '<p><em>' . esc_html__( 'Aucune saison active — activer une saison dans JCMV → Saisons. (Message visible uniquement par le bureau.)', 'wp-jcmv' ) . '</em></p>';
	}
	return;
}

$jcmv_courses = get_posts(
	array(
		'post_type'      => PostTypes::COURS,
		'post_status'    => 'publish',
		'numberposts'    => 100,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
	)
);

if ( ! $jcmv_courses ) {
	return;
}

$jcmv_weekdays = array( 1 => 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche' );

// Regroupe créneaux et tarifs par cours.
$jcmv_schedules = array();
foreach ( ( new ScheduleRepository() )->for_season( (int) $jcmv_season->id ) as $jcmv_row ) {
	$jcmv_schedules[ (int) $jcmv_row->course_id ][] = $jcmv_row;
}
$jcmv_pricing = array();
foreach ( ( new PricingRepository() )->for_season( (int) $jcmv_season->id ) as $jcmv_row ) {
	$jcmv_pricing[ (int) $jcmv_row->course_id ][] = $jcmv_row;
}

/**
 * « 17:30:00 » → « 17h30 ».
 */
$jcmv_format_time = static function ( string $time ): string {
	return str_replace( ':', 'h', substr( $time, 0, 5 ) );
};

/**
 * « 135.00 » → « 135,00 € ».
 */
$jcmv_format_price = static function ( float $amount ): string {
	return number_format( $amount, 2, ',', ' ' ) . ' €';
};

echo '<div class="jcmv-schedule-cards">';

foreach ( $jcmv_courses as $jcmv_course ) {
	$jcmv_course_id = (int) $jcmv_course->ID;
	$jcmv_slots     = $jcmv_schedules[ $jcmv_course_id ] ?? array();
	$jcmv_prices    = $jcmv_pricing[ $jcmv_course_id ] ?? array();

	if ( ! $jcmv_slots && ! $jcmv_prices ) {
		continue; // Cours sans données pour cette saison : rien à afficher.
	}

	// Badge d'âge : bornes agrégées des catégories du cours → années de naissance.
	$jcmv_age_label = '';
	$jcmv_terms     = get_the_terms( $jcmv_course_id, Taxonomies::CATEGORIE_AGE );
	if ( $jcmv_terms && ! is_wp_error( $jcmv_terms ) ) {
		$jcmv_age_min = PHP_INT_MAX;
		$jcmv_age_max = 0;
		foreach ( $jcmv_terms as $jcmv_term ) {
			$jcmv_min = (int) get_term_meta( $jcmv_term->term_id, 'age_min', true );
			$jcmv_max = (int) get_term_meta( $jcmv_term->term_id, 'age_max', true );
			if ( $jcmv_min > 0 ) {
				$jcmv_age_min = min( $jcmv_age_min, $jcmv_min );
			}
			$jcmv_age_max = max( $jcmv_age_max, $jcmv_max );
		}
		if ( PHP_INT_MAX !== $jcmv_age_min ) {
			$jcmv_age_label = AgeCalculator::label( (int) $jcmv_season->start_year, $jcmv_age_min, $jcmv_age_max );
		}
	}

	echo '<article class="jcmv-schedule-card">';

	echo '<header class="jcmv-schedule-card__head">';
	echo '<h3>' . esc_html( get_the_title( $jcmv_course ) ) . '</h3>';
	if ( $jcmv_age_label ) {
		echo '<span class="jcmv-age-badge">' . esc_html( $jcmv_age_label ) . '</span>';
	}
	echo '</header>';

	if ( $jcmv_slots ) {
		echo '<table class="jcmv-schedule-card__table"><tbody>';
		foreach ( $jcmv_slots as $jcmv_slot ) {
			echo '<tr>';
			echo '<td class="jcmv-schedule-card__day">' . esc_html( $jcmv_weekdays[ (int) $jcmv_slot->weekday ] ?? '' ) . '</td>';
			echo '<td>' . esc_html( $jcmv_format_time( $jcmv_slot->start_time ) . ' – ' . $jcmv_format_time( $jcmv_slot->end_time ) ) . '</td>';
			echo '<td class="jcmv-schedule-card__room">' . esc_html( get_the_title( (int) $jcmv_slot->location_id ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	foreach ( $jcmv_prices as $jcmv_price ) {
		echo '<div class="jcmv-schedule-card__price-row">';
		echo '<span class="jcmv-schedule-card__price-desc">' . esc_html( $jcmv_price->label . ( $jcmv_price->period ? ' ' . $jcmv_price->period : '' ) );
		if ( $jcmv_price->note ) {
			echo '<br><small>' . esc_html( $jcmv_price->note ) . '</small>';
		}
		echo '</span>';
		echo '<span class="jcmv-schedule-card__price-tag">' . esc_html( $jcmv_format_price( (float) $jcmv_price->amount ) ) . '</span>';
		echo '</div>';
	}

	echo '<div class="jcmv-schedule-card__actions">';
	echo '<a class="wp-block-button__link wp-element-button" href="#inscription">' . esc_html__( 'Je m\'inscris', 'wp-jcmv' ) . '</a>';
	echo '</div>';

	echo '</article>';
}

echo '</div>';
