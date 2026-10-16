<?php
/**
 * Script and style enqueueing driven by the webpack asset manifests.
 *
 * @package WPMediaImporter
 */

declare( strict_types = 1 );

namespace WPMediaImporter;

defined( 'ABSPATH' ) || exit;

/**
 * Loads built assets from /build.
 */
class Assets {

	/**
	 * Hook enqueue callbacks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_action( 'wp_enqueue_media', array( $this, 'enqueue_importer' ) );
	}

	/**
	 * Settings bundle on our screen; importer on Media → Library.
	 *
	 * @param string $hook_suffix Current admin page.
	 *
	 * @return void
	 */
	public function enqueue_admin( string $hook_suffix ): void {
		if ( 'settings_page_' . Admin_Page::MENU_SLUG === $hook_suffix ) {
			if ( $this->enqueue_entry( 'wpmi-settings', 'settings' ) ) {
				$this->boot(
					'wpmi-settings',
					'wpmiSettings',
					array(
						'restPath'      => '/' . Rest_Controller::REST_NAMESPACE . '/settings',
						'providers'     => Providers::for_settings(),
						'image'         => Settings::image(),
						'webpSupported' => wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ),
					)
				);
			}
			return;
		}

		// List mode of Media → Library has no media browser; it gets header buttons instead.
		if ( 'upload.php' === $hook_suffix ) {
			$this->enqueue_importer();
		}
	}

	/**
	 * Importer bundle, wherever WordPress loads its media modal (post editors,
	 * Media → Library grid, any plugin calling wp_enqueue_media()) or on upload.php.
	 * Safe to call more than once.
	 *
	 * @return void
	 */
	public function enqueue_importer(): void {
		if ( wp_script_is( 'wpmi-importer' ) || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$providers = array();

		foreach ( Providers::enabled() as $slug => $provider ) {
			$providers[] = array(
				'slug'    => $slug,
				'label'   => $provider->label(),
				'siteUrl' => $provider->site_url(),
			);
		}

		// Load after media-views (when present) so the toolbar hook is in place before any frame is built.
		$deps = wp_script_is( 'media-views' ) ? array( 'media-views' ) : array();

		if ( ! $providers || ! $this->enqueue_entry( 'wpmi-importer', 'importer', $deps ) ) {
			return;
		}

		$this->boot(
			'wpmi-importer',
			'wpmiImporter',
			array(
				'restNamespace' => Rest_Controller::REST_NAMESPACE,
				'providers'     => $providers,
				'maxSelection'  => (int) Settings::image()['max_selection'],
			)
		);
	}

	/**
	 * Expose boot data to a bundle as window.<name>.
	 *
	 * @param string               $handle Script handle.
	 * @param string               $name   Global name.
	 * @param array<string, mixed> $data   Data.
	 *
	 * @return void
	 */
	private function boot( string $handle, string $name, array $data ): void {
		wp_set_script_translations( $handle, 'wp-media-importer', WPMI_PLUGIN_DIR . 'languages' );
		wp_add_inline_script( $handle, sprintf( 'window.%s = %s;', $name, wp_json_encode( $data ) ), 'before' );
	}

	/**
	 * Enqueue a built entry point plus its stylesheet, using the generated .asset.php file.
	 *
	 * @param string $handle Script handle.
	 * @param string             $entry  Entry point name (matches the file name in /build).
	 * @param array<int, string> $deps   Extra script dependencies.
	 *
	 * @return bool False when the bundle has not been built.
	 */
	private function enqueue_entry( string $handle, string $entry, array $deps = array() ): bool {
		$script_path = WPMI_PLUGIN_DIR . "build/{$entry}.js";
		$asset_path  = WPMI_PLUGIN_DIR . "build/{$entry}.asset.php";

		if ( ! file_exists( $script_path ) ) {
			return false;
		}

		$asset = file_exists( $asset_path )
			? require $asset_path
			: array(
				'dependencies' => array(),
				'version'      => WPMI_VERSION,
			);

		wp_enqueue_script(
			$handle,
			WPMI_PLUGIN_URL . "build/{$entry}.js",
			array_merge( $asset['dependencies'], $deps ),
			$asset['version'],
			true
		);

		foreach ( array( "build/{$entry}.css", "build/style-{$entry}.css" ) as $relative ) {
			if ( file_exists( WPMI_PLUGIN_DIR . $relative ) ) {
				wp_enqueue_style(
					$handle . '-' . basename( $relative, '.css' ),
					WPMI_PLUGIN_URL . $relative,
					array( 'wp-components' ),
					$asset['version']
				);
			}
		}

		return true;
	}
}
