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

	/** Rôles recevant la capability à l'activation. */
	private const ROLES = array( 'administrator', 'editor' );

	public static function grant(): void {
		foreach ( self::ROLES as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( self::MANAGE ) ) {
				$role->add_cap( self::MANAGE );
			}
		}
	}
}
