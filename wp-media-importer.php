<?php
/**
 * Plugin Name:       WP Media Importer
 * Description:       Search free stock photo sites (Pexels, more soon) and import photos straight into the media library.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Sagar Shrestha
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-media-importer
 * Domain Path:       /languages
 *
 * @package WPMediaImporter
 */

declare( strict_types = 1 );

namespace WPMediaImporter;

defined( 'ABSPATH' ) || exit;

define( 'WPMI_VERSION', '1.0.0' );
define( 'WPMI_PLUGIN_FILE', __FILE__ );
define( 'WPMI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPMI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPMI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WPMI_PLUGIN_DIR . 'includes/class-plugin.php';

( new Plugin() )->init();
