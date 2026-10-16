<?php
/**
 * Plugin bootstrap.
 *
 * @package WPMediaImporter
 */

declare( strict_types = 1 );

namespace WPMediaImporter;

defined( 'ABSPATH' ) || exit;

require_once WPMI_PLUGIN_DIR . 'includes/class-settings.php';
require_once WPMI_PLUGIN_DIR . 'includes/providers/class-provider.php';
require_once WPMI_PLUGIN_DIR . 'includes/providers/class-pexels.php';
require_once WPMI_PLUGIN_DIR . 'includes/providers/class-pixabay.php';
require_once WPMI_PLUGIN_DIR . 'includes/providers/class-unsplash.php';
require_once WPMI_PLUGIN_DIR . 'includes/providers/class-openverse.php';
require_once WPMI_PLUGIN_DIR . 'includes/class-providers.php';
require_once WPMI_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once WPMI_PLUGIN_DIR . 'includes/class-rest-controller.php';
require_once WPMI_PLUGIN_DIR . 'includes/class-assets.php';

/**
 * Wires every module together.
 */
class Plugin {

	/**
	 * Boot all modules.
	 *
	 * @return void
	 */
	public function init(): void {
		( new Admin_Page() )->init();
		( new Rest_Controller() )->init();
		( new Assets() )->init();

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-media-importer',
			false,
			dirname( WPMI_PLUGIN_BASENAME ) . '/languages'
		);
	}
}
