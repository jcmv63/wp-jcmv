<?php
/**
 * Point d'entrée du plugin : câblage des modules.
 *
 * @package wp-jcmv
 */

namespace JCMV;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	/**
	 * Câble les différents modules du plugin (hook plugins_loaded).
	 */
	public static function boot(): void {
		// Rejoue les migrations si le plugin a été mis à jour sans réactivation
		// (mise à jour par zip ou par Plugin Update Checker).
		if ( is_admin() ) {
			Domain\Schema::maybe_migrate();
		}

		add_action( 'init', array( Registration\PostTypes::class, 'register' ) );
		add_action( 'init', array( Registration\Taxonomies::class, 'register' ) );
		Registration\ImageSizes::register();
		Registration\DeletionGuard::register();
		Rest\SeasonsController::register();
		Front\Blocks::register();

		if ( is_admin() ) {
			Admin\Menu::register();
			Admin\SaisonsPage::register();
			Admin\TermFields::register();
			Admin\LieuMetabox::register();
			Admin\PartenaireMetabox::register();
			Updater::register();
		}
	}

	/**
	 * Activation : schéma, référentiels, capability.
	 * Les CPT/taxonomies sont enregistrés manuellement car init est déjà
	 * passé au moment où le hook d'activation s'exécute.
	 */
	public static function activate(): void {
		Domain\Schema::migrate();
		Registration\ImageSizes::add();
		Registration\PostTypes::register();
		Registration\Taxonomies::register();
		Domain\Seed::run();
		Registration\Capabilities::grant();
	}
}
