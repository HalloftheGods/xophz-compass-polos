<?php

/**
 * The core plugin orchestrator class.
 *
 * @since      1.0.0
 * @package    Xophz_Compass_Polos
 * @subpackage Xophz_Compass_Polos/includes
 */

class Xophz_Compass_Polos {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->plugin_name = 'xophz-compass-polos';
		$this->version     = XOPHZ_COMPASS_POLOS_VERSION;

		$this->load_dependencies();
	}

	/**
	 * Load the required dependencies.
	 */
	private function load_dependencies() {
		require_once XOPHZ_COMPASS_POLOS_PATH . 'includes/class-xophz-compass-polos-engine.php';
		require_once XOPHZ_COMPASS_POLOS_PATH . 'includes/class-xophz-compass-polos-api.php';
	}

	/**
	 * Run the loader to execute all of the hooks.
	 */
	public function run() {
		$api = new Xophz_Compass_Polos_API();
		add_action( 'rest_api_init', array( $api, 'register_routes' ) );

		// Register with Event Horizon Sparks Registry
		add_filter( 'xophz_register_sparks', array( $this, 'register_spark' ) );
		add_filter( 'xophz_get_spark_manifest', array( $this, 'get_spark_manifest' ), 10, 2 );

		// Register submenu with My COMPASS WordPress admin
		add_action( 'admin_menu', array( $this, 'add_to_menu' ) );
	}

	/**
	 * Add POLOS to the My COMPASS WordPress admin sidebar submenu.
	 */
	public function add_to_menu() {
		if ( class_exists( 'Xophz_Compass' ) ) {
			Xophz_Compass::add_submenu( 'xophz-compass-polos' );
		}
	}

	/**
	 * Register POLOS spark in the global sparks list.
	 */
	public function register_spark( $sparks ) {
		$sparks['polos'] = array(
			'id'          => 'polos',
			'title'       => __( 'POLOS', 'xophz-compass-polos' ),
			'description' => __( 'Fractal Consensus & Navigational Governance', 'xophz-compass-polos' ),
			'icon'        => 'fal fa-drafting-compass',
			'color'       => '#62c9ff',
			'categories'  => array( 'productivity', 'portal', 'core' ),
			'version'     => $this->version,
			'author'      => 'Hall of the Gods, Inc.'
		);
		$sparks['u-polos'] = $sparks['polos'];
		return $sparks;
	}

	/**
	 * Return structural manifest for rendering POLOS in YouMeOS.
	 */
	public function get_spark_manifest( $manifest, $spark_id ) {
		if ( 'polos' !== $spark_id && 'u-polos' !== $spark_id ) {
			return $manifest;
		}

		return array(
			'id' => 'u-polos',
			'meta' => array(
				'title' => 'POLOS',
				'icon' => 'fal fa-drafting-compass',
				'color' => '#62c9ff',
				'dimensions' => array(
					'width' => 960,
					'height' => 680
				)
			)
		);
	}

	/**
	 * Get the plugin name.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Get the version.
	 */
	public function get_version() {
		return $this->version;
	}
}
