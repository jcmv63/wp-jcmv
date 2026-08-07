<?php
/**
 * Plugin Name:       JCMV — Gestion du club
 * Description:       Cours, créneaux, tarifs, lieux, catégories d'âge et boutique.
 * Version:           0.4.0
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Author:            Alban
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-jcmv
 *
 * @package wp-jcmv
 *
 * Note : pas de fichier uninstall.php volontairement — les données du club
 * (saisons, créneaux, tarifs) ne doivent jamais être détruites par une
 * désinstallation accidentelle du plugin.
 */

namespace JCMV;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JCMV_VERSION', '0.4.0' );
define( 'JCMV_PLUGIN_FILE', __FILE__ );
define( 'JCMV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JCMV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader minimaliste (pas de Composer — règle « pas d'usine à gaz ») :
 * JCMV\Domain\Schema → src/Domain/Schema.php
 */
spl_autoload_register(
	static function ( string $class ): void {
		if ( ! str_starts_with( $class, 'JCMV\\' ) ) {
			return;
		}
		$path = JCMV_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', substr( $class, 5 ) ) . '.php';
		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
