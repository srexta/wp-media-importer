<?php
/**
 * Unsplash source — https://unsplash.com/documentation
 *
 * @package WPMediaImporter
 */

declare( strict_types = 1 );

namespace WPMediaImporter;

defined( 'ABSPATH' ) || exit;

/**
 * Unsplash photos. Auth is "Client-ID <access key>" in the Authorization header.
 *
 * API rules followed here: credit the photographer with UTM-tagged links, and
 * ping the photo's download_location when it is imported.
 */
class Unsplash extends Provider {

	/**
	 * API root.
	 *
	 * @var string
	 */
	private const API = 'https://api.unsplash.com';

	/**
	 * UTM params Unsplash requires on attribution links.
	 *
	 * @var string
	 */
	private const UTM = 'utm_source=wp-media-importer&utm_medium=referral';

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'unsplash';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Unsplash';
	}

	/**
	 * {@inheritDoc}
	 */
	public function site_url(): string {
		return 'https://unsplash.com/?' . self::UTM;
	}

	/**
	 * {@inheritDoc}
	 */
	public function key_url(): string {
		return 'https://unsplash.com/oauth/applications';
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
				self::API . '/search/photos'
			),
			$this->headers()
		);

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		return array(
			'photos'   => array_map( array( $this, 'normalise' ), $body['results'] ?? array() ),
			'has_more' => $page < (int) ( $body['total_pages'] ?? 0 ),
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

		$photo['download_url']      = (string) ( $body['urls']['full'] ?? '' );
		$photo['download_location'] = (string) ( $body['links']['download_location'] ?? '' );
		/* translators: %s: photographer name. */
		$photo['credit'] = sprintf( __( 'Photo by %s on Unsplash', 'wp-media-importer' ), $photo['author'] );

		return $photo;
	}

	/**
	 * Tell Unsplash the photo was downloaded (required by their API guidelines).
	 *
	 * @param array<string, mixed> $photo Normalised photo from get().
	 *
	 * @return void
	 */
	public function on_import( array $photo ): void {
		if ( ! empty( $photo['download_location'] ) ) {
			wp_remote_get(
				$photo['download_location'],
				array(
					'timeout'  => 5,
					'blocking' => false,
					'headers'  => $this->headers(),
				)
			);
		}
	}

	/**
	 * Auth header.
	 *
	 * @return array<string, string>
	 */
	private function headers(): array {
		return array(
			'Authorization'  => 'Client-ID ' . $this->api_key(),
			'Accept-Version' => 'v1',
		);
	}

	/**
	 * Map an Unsplash photo to the shared shape.
	 *
	 * @param array<string, mixed> $raw Unsplash photo.
	 *
	 * @return array<string, mixed>
	 */
	public function normalise( array $raw ): array {
		$author_url = (string) ( $raw['user']['links']['html'] ?? '' );

		return array(
			'id'         => (string) ( $raw['id'] ?? '' ),
			'alt'        => (string) ( $raw['alt_description'] ?? $raw['description'] ?? '' ),
			'author'     => (string) ( $raw['user']['name'] ?? '' ),
			'author_url' => $author_url ? $author_url . '?' . self::UTM : '',
			'page_url'   => (string) ( $raw['links']['html'] ?? '' ),
			'thumb'      => (string) ( $raw['urls']['small'] ?? '' ),
			'width'      => (int) ( $raw['width'] ?? 0 ),
			'height'     => (int) ( $raw['height'] ?? 0 ),
		);
	}
}
