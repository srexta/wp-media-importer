<?php
/**
 * Base class every stock photo source extends.
 *
 * @package WPMediaImporter
 */

declare( strict_types = 1 );

namespace WPMediaImporter;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A photo source. search() and get() return the normalised photo shape:
 *
 *     id, alt, author, author_url, page_url, thumb, width, height
 *
 * get() adds download_url and credit (used as the attachment caption).
 */
abstract class Provider {

	/**
	 * Unique slug, used in REST routes and the option row.
	 *
	 * @return string
	 */
	abstract public function slug(): string;

	/**
	 * Human name, e.g. "Pexels".
	 *
	 * @return string
	 */
	abstract public function label(): string;

	/**
	 * Provider home page, linked as "Photos provided by …".
	 *
	 * @return string
	 */
	abstract public function site_url(): string;

	/**
	 * Where users sign up for an API key.
	 *
	 * @return string
	 */
	abstract public function key_url(): string;

	/**
	 * Whether an API key must be saved before this source can be used.
	 *
	 * @return bool
	 */
	public function requires_key(): bool {
		return true;
	}

	/**
	 * Search photos.
	 *
	 * @param string $query Search terms.
	 * @param int    $page  1-based page.
	 *
	 * @return array{photos: array<int, array<string, mixed>>, has_more: bool}|WP_Error
	 */
	abstract public function search( string $query, int $page );

	/**
	 * Fetch one photo by id, with download_url and credit.
	 *
	 * @param string $id Provider photo id.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	abstract public function get( string $id );

	/**
	 * Called after a photo is imported. Some APIs (Unsplash) require a ping here.
	 *
	 * @param array<string, mixed> $photo Normalised photo from get().
	 *
	 * @return void
	 */
	public function on_import( array $photo ): void {}

	/**
	 * Saved API key.
	 *
	 * @return string
	 */
	protected function api_key(): string {
		return Settings::get_key( $this->slug() );
	}

	/**
	 * GET a JSON endpoint and decode it.
	 *
	 * @param string                $url     Full URL.
	 * @param array<string, string> $headers Extra headers.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	protected function request( string $url, array $headers = array() ) {
		if ( $this->requires_key() && '' === $this->api_key() ) {
			return new WP_Error(
				'wpmi_no_key',
				/* translators: %s: provider name. */
				sprintf( __( 'Add your %s API key in Settings → Media Importer first.', 'wp-media-importer' ), $this->label() ),
				array( 'status' => 400 )
			);
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) ) {
			return new WP_Error(
				'wpmi_provider_error',
				/* translators: 1: provider name, 2: HTTP status code. */
				sprintf( __( '%1$s returned an error (HTTP %2$d). Check your API key.', 'wp-media-importer' ), $this->label(), $code ),
				array( 'status' => 502 )
			);
		}

		return $body;
	}
}
