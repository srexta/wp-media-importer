<?php
/**
 * Registry of photo sources.
 *
 * @package WPMediaImporter
 */

declare( strict_types = 1 );

namespace WPMediaImporter;

defined( 'ABSPATH' ) || exit;

/**
 * Add a source by appending to the list below, or from another plugin:
 *
 *     add_filter( 'wpmi_providers', fn( $p ) => $p + array( 'flickr' => My_Flickr::class ) );
 */
class Providers {

	/**
	 * Every registered provider, keyed by slug.
	 *
	 * @return array<string, Provider>
	 */
	public static function all(): array {
		static $providers = null;

		if ( null === $providers ) {
			$providers = array();
			$classes   = apply_filters(
				'wpmi_providers',
				array(
					'pexels'    => Pexels::class,
					'pixabay'   => Pixabay::class,
					'unsplash'  => Unsplash::class,
					'openverse' => Openverse::class,
				)
			);

			foreach ( $classes as $slug => $class ) {
				if ( is_subclass_of( $class, Provider::class ) ) {
					$providers[ $slug ] = new $class();
				}
			}
		}

		return $providers;
	}

	/**
	 * One provider, or null if the slug is unknown.
	 *
	 * @param string $slug Provider slug.
	 *
	 * @return Provider|null
	 */
	public static function get( string $slug ): ?Provider {
		return self::all()[ $slug ] ?? null;
	}

	/**
	 * Providers ready to use: key saved, or no key needed.
	 *
	 * @return array<string, Provider>
	 */
	public static function enabled(): array {
		return array_filter(
			self::all(),
			static function ( Provider $provider ): bool {
				return ! $provider->requires_key() || '' !== Settings::get_key( $provider->slug() );
			}
		);
	}

	/**
	 * Settings-screen view of every provider. Never includes a full key.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_settings(): array {
		$out = array();

		foreach ( self::all() as $slug => $provider ) {
			$out[] = array(
				'slug'        => $slug,
				'label'       => $provider->label(),
				'keyUrl'      => $provider->key_url(),
				'masked'      => Settings::masked_key( $slug ),
				'requiresKey' => $provider->requires_key(),
			);
		}

		return $out;
	}
}
