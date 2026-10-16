<?php
/**
 * Settings screen.
 *
 * @package WPMediaImporter
 */

declare( strict_types = 1 );

namespace WPMediaImporter;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Settings → Media Importer, which React renders into.
 */
class Admin_Page {

	/**
	 * Menu slug.
	 *
	 * @var string
	 */
	public const MENU_SLUG = 'wp-media-importer';

	/**
	 * Hook into the admin.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_filter( 'plugin_action_links_' . WPMI_PLUGIN_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Settings URL.
	 *
	 * @return string
	 */
	public static function url(): string {
		return admin_url( 'options-general.php?page=' . self::MENU_SLUG );
	}

	/**
	 * Add the page under Settings.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_options_page(
			__( 'Media Importer', 'wp-media-importer' ),
			__( 'Media Importer', 'wp-media-importer' ),
			Settings::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Settings link on the plugins list table.
	 *
	 * @param array<int, string> $links Existing links.
	 *
	 * @return array<int, string>
	 */
	public function action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( self::url() ),
				esc_html__( 'Settings', 'wp-media-importer' )
			)
		);

		return $links;
	}

	/**
	 * Print the container React renders into.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Settings::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-media-importer' ) );
		}

		printf(
			'<div id="wpmi-settings-root" class="wpmi-root"><p>%s</p></div>',
			esc_html__( 'Loading…', 'wp-media-importer' )
		);
	}
}
