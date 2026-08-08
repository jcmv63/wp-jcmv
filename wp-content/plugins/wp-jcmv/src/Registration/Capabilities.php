<?php
/**
 * Capability du module club (ADR-002) : manage_jcmv_club.
 *
 * Accordée aux rôles administrateur et éditeur (le bureau). Protège le menu
 * JCMV, la future page Saisons et les endpoints REST jcmv/v1.
 *
 * @package wp-jcmv
 */

namespace JCMV\Registration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Capabilities {

	public const MANAGE = 'manage_jcmv_club';

	/**
	 * Version de l'attribution. À incrémenter dès que ROLES change — ou pour
	 * forcer une réattribution après un incident.
	 *
	 * Même dispositif que Plugin::REWRITE_VERSION, et pour la même raison : le
	 * hook d'activation ne se déclenche pas lors d'une mise à jour par zip ou
	 * par l'updater. Sans ce garde-fou, ajouter un rôle à ROLES n'avait d'effet
	 * qu'après une désactivation/réactivation — manœuvre que le bureau ne fera
	 * pas, et qui rejoue le seed au passage.
	 */
	private const VERSION = '1';

	private const OPTION = 'jcmv_caps_version';

	/** Rôles recevant la capability (le bureau). */
	private const ROLES = array( 'administrator', 'editor' );

	/**
	 * Rejoue l'attribution si la version stockée est en retard.
	 *
	 * Volontairement gardé par une version, et non rejoué à chaque chargement :
	 * un administrateur qui retire délibérément la capability aux éditeurs — via
	 * un gestionnaire de rôles, parce qu'il ne veut pas qu'ils touchent au club —
	 * doit voir son choix tenir. Le réimposer à chaque page ferait de ce réglage
	 * un bug indiagnosticable. La contrepartie assumée : une capability retirée
	 * par erreur n'est pas réparée toute seule, elle attend le prochain
	 * incrément de VERSION.
	 */
	public static function maybe_grant(): void {
		if ( get_option( self::OPTION ) === self::VERSION ) {
			return;
		}

		self::grant();
	}

	public static function grant(): void {
		foreach ( self::ROLES as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( self::MANAGE ) ) {
				$role->add_cap( self::MANAGE );
			}
		}

		update_option( self::OPTION, self::VERSION, false );
	}
}
