<?php
/**
 * Pexels source — https://www.pexels.com/api/documentation/
 *
 * @package WPMediaImporter
 */

declare( strict_types = 1 );

namespace WPMediaImporter;

defined( 'ABSPATH' ) || exit;

/**
 * Pexels photos. Auth is the raw key in the Authorization header.
 */
class Pexels extends Provider {

	/**
	 * API root.
	 *
	 * @var string
	 */
	private const API = 'https://api.pexels.com/v1';

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'pexels';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Pexels';
	}

	/**
	 * {@inheritDoc}
	 */
	public function site_url(): string {
		return 'https://www.pexels.com';
	}

	/**
	 * {@inheritDoc}
	 */
	public function key_url(): string {
		return 'https://www.pexels.com/api/new/';
	}

	/**
	 * {@inheritDoc}
	 */
	public function search( string $query, int $page ) {
		$body = $this->request(
			add_query_arg(
				array(
					'query'    => rawurlencode( $query ),
					'page'     => $page,
					'per_page' => 24,
				),
				self::API . '/search'
			),
			$this->headers()
		);

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		return array(
			'photos'   => array_map( array( $this, 'normalise' ), $body['photos'] ?? array() ),
			'has_more' => ! empty( $body['next_page'] ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get( string $id ) {
		$body = $this->request( self::API . '/photos/' . rawurlencode( $id ), $this->headers() );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$photo = $this->normalise( $body );

		$photo['download_url'] = (string) ( $body['src']['large2x'] ?? $body['src']['original'] ?? '' );
		/* translators: %s: photographer name. */
		$photo['credit'] = sprintf( __( 'Photo by %s on Pexels', 'wp-media-importer' ), $photo['author'] );

		return $photo;
	}

	/**
	 * Auth header.
	 *
	 * @return array<string, string>
	 */
	private function headers(): array {
		return array( 'Authorization' => $this->api_key() );
	}

	/**
	 * Map a Pexels photo object to the shared shape.
	 *
	 * @param array<string, mixed> $raw Pexels photo.
	 *
	 * @return array<string, mixed>
	 */
	public function normalise( array $raw ): array {
		return array(
			'id'         => (string) ( $raw['id'] ?? '' ),
			'alt'        => (string) ( $raw['alt'] ?? '' ),
			'author'     => (string) ( $raw['photographer'] ?? '' ),
			'author_url' => (string) ( $raw['photographer_url'] ?? '' ),
			'page_url'   => (string) ( $raw['url'] ?? '' ),
			'thumb'      => (string) ( $raw['src']['medium'] ?? '' ),
			'width'      => (int) ( $raw['width'] ?? 0 ),
			'height'     => (int) ( $raw['height'] ?? 0 ),
		);
	}
}
