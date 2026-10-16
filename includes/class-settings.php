<?php
/**
 * API key storage.
 *
 * @package WPMediaImporter
 */

declare( strict_types = 1 );

namespace WPMediaImporter;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the single option row:
 *
 *     { keys: { <provider slug>: <api key> }, image: { max_width, max_height, quality, format, max_selection } }
 */
class Settings {

	/**
	 * Option name in wp_options.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'wpmi_settings';

	/**
	 * Capability required to manage API keys.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Import defaults. 0 width/height = no size limit; max_selection = photos per import.
	 *
	 * @var array<string, int|string>
	 */
	public const IMAGE_DEFAULTS = array(
		'max_width'     => 1920,
		'max_height'    => 1920,
		'quality'       => 82,
		'format'        => 'original',
		'max_selection' => 5,
	);

	/**
	 * The whole option row.
	 *
	 * @return array<string, mixed>
	 */
	private static function all(): array {
		$settings = get_option( self::OPTION_KEY, array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Image processing settings merged over the defaults.
	 *
	 * @return array<string, int|string>
	 */
	public static function image(): array {
		return array_merge( self::IMAGE_DEFAULTS, (array) ( self::all()['image'] ?? array() ) );
	}

	/**
	 * Validate and store image processing settings. Unknown keys are dropped.
	 *
	 * @param array<string, mixed> $input Raw values.
	 *
	 * @return void
	 */
	public static function set_image( array $input ): void {
		$image = array_merge( self::image(), array_intersect_key( $input, self::IMAGE_DEFAULTS ) );

		$settings          = self::all();
		$settings['image'] = array(
			'max_width'     => min( absint( $image['max_width'] ), 10000 ),
			'max_height'    => min( absint( $image['max_height'] ), 10000 ),
			'quality'       => max( 1, min( 100, absint( $image['quality'] ) ) ),
			'format'        => 'webp' === $image['format'] ? 'webp' : 'original',
			'max_selection' => max( 1, min( 50, absint( $image['max_selection'] ) ) ),
		);

		update_option( self::OPTION_KEY, $settings, false );
	}

	/**
	 * Saved API key for a provider.
	 *
	 * @param string $slug Provider slug.
	 *
	 * @return string
	 */
	public static function get_key( string $slug ): string {
		return (string) ( self::all()['keys'][ $slug ] ?? '' );
	}

	/**
	 * Store or clear one provider's key. An empty string clears it.
	 *
	 * @param string $slug Provider slug.
	 * @param string $key  API key.
	 *
	 * @return void
	 */
	public static function set_key( string $slug, string $key ): void {
		$settings = self::all();

		$settings['keys'][ $slug ] = sanitize_text_field( $key );

		// Not autoloaded: only our screens and REST routes read it.
		update_option( self::OPTION_KEY, $settings, false );
	}

	/**
	 * Key with all but the last four characters hidden, for display.
	 *
	 * @param string $slug Provider slug.
	 *
	 * @return string Empty when no key is saved.
	 */
	public static function masked_key( string $slug ): string {
		$key = self::get_key( $slug );

		return '' === $key ? '' : str_repeat( '•', 8 ) . substr( $key, -4 );
	}
}
