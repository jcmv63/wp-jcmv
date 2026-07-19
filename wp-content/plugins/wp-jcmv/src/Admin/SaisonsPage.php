<?php
/**
 * Page « Saisons » : montage de l'app REST + JS (ADR-002, niveau 3).
 *
 * Le bundle est produit par @wordpress/scripts dans admin-ui/build/
 * (npm run build). Les dépendances (wp-element, wp-components, wp-api-fetch)
 * sont lues dans index.asset.php ; le nonce REST est injecté automatiquement
 * par WordPress via la dépendance wp-api-fetch.
 *
 * @package wp-jcmv
 */

namespace JCMV\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SaisonsPage {

	private const HOOK = 'toplevel_page_' . Menu::SLUG;

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function enqueue( string $hook ): void {
		if ( self::HOOK !== $hook ) {
			return;
		}

		$asset_path = JCMV_PLUGIN_DIR . 'admin-ui/build/index.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return; // Bundle absent : Menu::render_app affiche la marche à suivre.
		}

		$asset = require $asset_path;

		wp_enqueue_script(
			'jcmv-saisons-app',
			JCMV_PLUGIN_URL . 'admin-ui/build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style( 'wp-components' );

		if ( file_exists( JCMV_PLUGIN_DIR . 'admin-ui/build/index.css' ) ) {
			wp_enqueue_style(
				'jcmv-saisons-app',
				JCMV_PLUGIN_URL . 'admin-ui/build/index.css',
				array( 'wp-components' ),
				$asset['version']
			);
		}
	}

	public static function is_built(): bool {
		return file_exists( JCMV_PLUGIN_DIR . 'admin-ui/build/index.asset.php' );
	}
}
