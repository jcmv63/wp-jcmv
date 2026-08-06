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
	/**
	 * Version dont les règles de réécriture sont en place. À incrémenter dès
	 * qu'une règle change (ADR-004).
	 */
	private const REWRITE_VERSION = '1';

	private const REWRITE_OPTION = 'jcmv_rewrite_version';

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
		Front\CalendarFeed::register();

		// Priorité tardive : les règles de CalendarFeed sont ajoutées sur `init`
		// en priorité par défaut, elles doivent exister avant le flush.
		add_action( 'init', array( self::class, 'maybe_flush_rewrites' ), 99 );

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
	 * Purge les règles de réécriture lorsque celles du plugin ont changé.
	 *
	 * Le hook d'activation ne suffit pas : il ne se déclenche pas lors d'une
	 * mise à jour par zip ou via l'updater. Sans ce garde-fou, les flux ICS
	 * renverraient 404 après une montée de version jusqu'à un passage manuel
	 * par Réglages → Permaliens (ADR-004).
	 */
	public static function maybe_flush_rewrites(): void {
		if ( get_option( self::REWRITE_OPTION ) === self::REWRITE_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, self::REWRITE_VERSION, false );
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

		// Idem : les règles doivent exister avant d'être écrites en base.
		Front\CalendarFeed::add_rewrite_rules();
		delete_option( self::REWRITE_OPTION );
		self::maybe_flush_rewrites();
	}
}
