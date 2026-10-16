<?php
/**
 * Pixabay source — https://pixabay.com/api/docs/
 *
 * @package WPMediaImporter
 */

declare( strict_types = 1 );

namespace WPMediaImporter;

defined( 'ABSPATH' ) || exit;

/**
 * Pixabay photos. Auth is the key in the `key` query param.
 */
class Pixabay extends Provider {

	/**
	 * API root.
	 *
	 * @var string
	 */
	private const API = 'https://pixabay.com/api/';

	/**
	 * Results per page.
	 *
	 * @var int
	 */
	private const PER_PAGE = 24;

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'pixabay';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Pixabay';
	}

	/**
	 * {@inheritDoc}
	 */
	public function site_url(): string {
		return 'https://pixabay.com';
	}

	/**
	 * {@inheritDoc}
	 */
	public function key_url(): string {
		return 'https://pixabay.com/api/docs/#api_search_images';
	}

	/**
	 * {@inheritDoc}
	 */
	public function search( string $query, int $page ) {
		$body = $this->request(
			$this->url(
				array(
					// Pixabay rejects queries over 100 characters.
					'q'          => rawurlencode( mb_substr( $query, 0, 100 ) ),
					'page'       => $page,
					'per_page'   => self::PER_PAGE,
					'image_type' => 'photo',
					'safesearch' => 'true',
				)
			)
		);

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		return array(
			'photos'   => array_map( array( $this, 'normalise' ), $body['hits'] ?? array() ),
			'has_more' => $page * self::PER_PAGE < (int) ( $body['totalHits'] ?? 0 ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get( string $id ) {
		$body = $this->request( $this->url( array( 'id' => rawurlencode( $id ) ) ) );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$raw   = $body['hits'][0] ?? array();
		$photo = $this->normalise( $raw );

		$photo['download_url'] = (string) ( $raw['largeImageURL'] ?? '' );
		/* translators: %s: photographer name. */
		$photo['credit'] = sprintf( __( 'Image by %s from Pixabay', 'wp-media-importer' ), $photo['author'] );

		return $photo;
	}

	/**
	 * API URL with the key attached.
	 *
	 * @param array<string, mixed> $args Already-encoded query args.
	 *
	 * @return string
	 */
	private function url( array $args ): string {
		return add_query_arg( array( 'key' => rawurlencode( $this->api_key() ) ) + $args, self::API );
	}

	/**
	 * Map a Pixabay hit to the shared shape. Pixabay has no alt text, so tags stand in.
	 *
	 * @param array<string, mixed> $raw Pixabay hit.
	 *
	 * @return array<string, mixed>
	 */
	public function normalise( array $raw ): array {
		$user = (string) ( $raw['user'] ?? '' );

		return array(
			'id'         => (string) ( $raw['id'] ?? '' ),
			'alt'        => (string) ( $raw['tags'] ?? '' ),
			'author'     => $user,
			'author_url' => sprintf( 'https://pixabay.com/users/%s-%d/', rawurlencode( $user ), (int) ( $raw['user_id'] ?? 0 ) ),
			'page_url'   => (string) ( $raw['pageURL'] ?? '' ),
			'thumb'      => (string) ( $raw['webformatURL'] ?? '' ),
			'width'      => (int) ( $raw['imageWidth'] ?? 0 ),
			'height'     => (int) ( $raw['imageHeight'] ?? 0 ),
		);
	}
}
