<?php
/**
 * Openverse source — https://api.openverse.org/v1/
 *
 * @package WPMediaImporter
 */

declare( strict_types = 1 );

namespace WPMediaImporter;

defined( 'ABSPATH' ) || exit;

/**
 * Openverse (WordPress.org's openly licensed media search). Works without a
 * key at the anonymous rate limit. Results are limited to licences that
 * allow commercial use.
 */
class Openverse extends Provider {

	/**
	 * API root.
	 *
	 * @var string
	 */
	private const API = 'https://api.openverse.org/v1/images/';

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'openverse';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Openverse';
	}

	/**
	 * {@inheritDoc}
	 */
	public function site_url(): string {
		return 'https://openverse.org';
	}

	/**
	 * {@inheritDoc}
	 */
	public function key_url(): string {
		return '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function requires_key(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function search( string $query, int $page ) {
		$body = $this->request(
			add_query_arg(
				array(
					'q'            => rawurlencode( $query ),
					'page'         => $page,
					'page_size'    => 20, // Anonymous maximum.
					'license_type' => 'commercial',
				),
				self::API
			)
		);

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		return array(
			'photos'   => array_map( array( $this, 'normalise' ), $body['results'] ?? array() ),
			'has_more' => $page < (int) ( $body['page_count'] ?? 0 ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get( string $id ) {
		$body = $this->request( self::API . rawurlencode( $id ) . '/' );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$photo = $this->normalise( $body );

		$photo['download_url'] = (string) ( $body['url'] ?? '' );
		// Openverse supplies the full CC attribution (title, creator, licence).
		$photo['credit'] = (string) ( $body['attribution'] ?? '' );

		return $photo;
	}

	/**
	 * Map an Openverse result to the shared shape.
	 *
	 * @param array<string, mixed> $raw Openverse image.
	 *
	 * @return array<string, mixed>
	 */
	public function normalise( array $raw ): array {
		return array(
			'id'         => (string) ( $raw['id'] ?? '' ),
			'alt'        => (string) ( $raw['title'] ?? '' ),
			'author'     => (string) ( $raw['creator'] ?? '' ),
			'author_url' => (string) ( $raw['creator_url'] ?? '' ),
			'page_url'   => (string) ( $raw['foreign_landing_url'] ?? '' ),
			'thumb'      => (string) ( $raw['thumbnail'] ?? '' ),
			'width'      => (int) ( $raw['width'] ?? 0 ),
			'height'     => (int) ( $raw['height'] ?? 0 ),
		);
	}
}
