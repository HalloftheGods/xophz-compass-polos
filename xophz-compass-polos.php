<?php

/**
 * The plugin bootstrap file for Xophz POLOS.
 *
 * @link              https://hallofthegods.com/
 * @since             1.0.0
 * @package           Xophz_Compass_Polos
 *
 * @wordpress-plugin
 * Category:          Command Deck
 * Group:             Governance
 * Plugin Name:       Xophz POLOS 
 * Plugin URI:        https://github.com/HalloftheGods/xophz-compass-polos
 * Description:       Multi-scale fractal consensus engine with quadratic voting, liquid proxy delegation, Circle Web-of-Trust, and federated w⁴ cross-node governance.
 * Version:           26.9.5
 * Author:            Hall of the Gods, Inc.
 * Author URI:        https://hallofthegods.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       xophz-compass-polos
 * Domain Path:       /languages
 * Update URI:        https://github.com/HalloftheGods/xophz-compass-polos
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'XOPHZ_COMPASS_POLOS_VERSION', '26.9.5' );
define( 'XOPHZ_COMPASS_POLOS_PATH', plugin_dir_path( __FILE__ ) );
define( 'XOPHZ_COMPASS_POLOS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Activation hook handler.
 */
function activate_xophz_compass_polos() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-xophz-compass-polos-activator.php';
	Xophz_Compass_Polos_Activator::activate();
}

/**
 * Deactivation hook handler.
 */
function deactivate_xophz_compass_polos() {
	delete_transient( 'polos_federated_peers_cache' );
	delete_transient( 'polos_quorum_stats_cache' );
}

register_activation_hook( __FILE__, 'activate_xophz_compass_polos' );
register_deactivation_hook( __FILE__, 'deactivate_xophz_compass_polos' );

/**
 * Register with COMPASS performance widgets.
 */
add_filter( 'compass_perform_widgets', function( $widgets ) {
	$widgets[] = array(
		'key'           => 'polos-consensus-overview',
		'plugin'        => 'xophz-compass-polos',
		'title'         => 'POLOS Consensus',
		'icon'          => 'fal fa-drafting-compass',
		'color'         => '#62c9ff',
		'gradient'      => 'linear-gradient(135deg, rgba(98, 201, 255, 0.25) 0%, rgba(13, 27, 42, 0.6) 100%)',
		'description'   => 'Fractal Governance & Federated w⁴ Quorum Engine',
		'route'         => 'polos',
		'tier'          => 'pi',
	);
	return $widgets;
} );

/**
 * Core plugin class bootstrap.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-xophz-compass-polos.php';

function run_xophz_compass_polos() {
	$plugin = new Xophz_Compass_Polos();
	$plugin->run();
}

add_action( 'plugins_loaded', 'run_xophz_compass_polos' );
