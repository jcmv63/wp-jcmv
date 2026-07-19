<?php
/**
 * Rendu serveur du bloc « Licence & adhésion » : l'encart récapitulatif du
 * coût fixe annuel (charte §06 — éviter au parent de faire l'addition).
 *
 * @package wp-jcmv
 */

use JCMV\Domain\SeasonRepository;

$jcmv_season = ( new SeasonRepository() )->active();

if ( ! $jcmv_season ) {
	return;
}

$jcmv_licence  = (float) $jcmv_season->licence_amount;
$jcmv_adhesion = (float) $jcmv_season->adhesion_amount;

if ( $jcmv_licence <= 0 && $jcmv_adhesion <= 0 ) {
	return;
}

$jcmv_format = static function ( float $amount ): string {
	return number_format( $amount, 2, ',', ' ' ) . ' €';
};

$jcmv_details = array();

$jcmv_detail_licence = 'Licence FFJDA ' . $jcmv_format( $jcmv_licence );
if ( $jcmv_season->licence_note ) {
	$jcmv_detail_licence .= ' (' . $jcmv_season->licence_note . ')';
}
$jcmv_details[] = $jcmv_detail_licence;

$jcmv_detail_adhesion = 'Adhésion club ' . $jcmv_format( $jcmv_adhesion );
if ( $jcmv_season->adhesion_note ) {
	$jcmv_detail_adhesion .= ' (' . $jcmv_season->adhesion_note . ')';
}
$jcmv_details[] = $jcmv_detail_adhesion;

?>
<div class="jcmv-recap-box">
	<div>
		<span class="jcmv-recap-box__label"><?php esc_html_e( 'Coût fixe annuel (hors cours)', 'wp-jcmv' ); ?></span>
		<p class="jcmv-recap-box__details"><?php echo esc_html( implode( ' + ', $jcmv_details ) ); ?></p>
	</div>
	<span class="jcmv-recap-box__total"><?php echo esc_html( $jcmv_format( $jcmv_licence + $jcmv_adhesion ) ); ?></span>
</div>
