<?php
/**
 * REST routes: save keys, search a provider, import a photo.
 *
 * @package WPMediaImporter
 */

declare( strict_types = 1 );

namespace WPMediaImporter;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under wp-media-importer/v1. All provider calls happen here so the
 * API key never reaches the browser.
 */
class Rest_Controller {

	/**
	 * Route namespace.
	 *
	 * @var string
	 */
	public const REST_NAMESPACE = 'wp-media-importer/v1';

	/**
	 * Hook route registration.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register every route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_settings' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'keys'  => array(
						'type'                 => 'object',
						'additionalProperties' => array( 'type' => 'string' ),
					),
					'image' => array(
						'type'       => 'object',
						'properties' => array(
							'max_width'     => array( 'type' => 'integer' ),
							'max_height'    => array( 'type' => 'integer' ),
							'quality'       => array( 'type' => 'integer' ),
							'format'        => array(
								'type' => 'string',
								'enum' => array( 'original', 'webp' ),
							),
							'max_selection' => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/(?P<provider>[a-z0-9_-]+)/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => array( $this, 'can_upload' ),
				'args'                => array(
					'query' => array(
						'type'      => 'string',
						'required'  => true,
						'minLength' => 1,
					),
					'page'  => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/(?P<provider>[a-z0-9_-]+)/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( $this, 'can_upload' ),
				'args'                => array(
					'id' => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[A-Za-z0-9_-]+$',
					),
				),
			)
		);
	}

	/**
	 * Only admins may change API keys.
	 *
	 * @return true|WP_Error
	 */
	public function can_manage() {
		return current_user_can( Settings::CAPABILITY ) ? true : $this->forbidden();
	}

	/**
	 * Anyone who can upload media may search and import.
	 *
	 * @return true|WP_Error
	 */
	public function can_upload() {
		return current_user_can( 'upload_files' ) ? true : $this->forbidden();
	}

	/**
	 * Save keys for the providers present in the payload ("" clears a key)
	 * and/or the image processing settings.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return array<string, mixed>
	 */
	public function update_settings( WP_REST_Request $request ): array {
		foreach ( (array) $request['keys'] as $slug => $key ) {
			if ( Providers::get( (string) $slug ) ) {
				Settings::set_key( (string) $slug, trim( (string) $key ) );
			}
		}

		if ( is_array( $request['image'] ) ) {
			Settings::set_image( $request['image'] );
		}

		return array(
			'providers' => Providers::for_settings(),
			'image'     => Settings::image(),
		);
	}

	/**
	 * Proxy a search to the provider.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function search( WP_REST_Request $request ) {
		$provider = $this->provider( $request );

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		return $provider->search( (string) $request['query'], (int) $request['page'] );
	}

	/**
	 * Download one photo into the media library.
	 *
	 * The download URL comes from the provider API, never from the client.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function import( WP_REST_Request $request ) {
		$provider = $this->provider( $request );

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		$slug = $provider->slug();
		$id   = (string) $request['id'];

		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-off lookup on import.
					array(
						'key'   => '_wpmi_source',
						'value' => $slug,
					),
					array(
						'key'   => '_wpmi_source_id',
						'value' => $id,
					),
				),
			)
		);

		if ( $existing ) {
			return array(
				'id'       => (int) $existing[0],
				'existing' => true,
			);
		}

		$photo = $provider->get( $id );

		if ( is_wp_error( $photo ) ) {
			return $photo;
		}

		if ( empty( $photo['download_url'] ) ) {
			return new WP_Error( 'wpmi_no_download', __( 'This photo has no downloadable file.', 'wp-media-importer' ), array( 'status' => 502 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $photo['download_url'], 60 );

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$ext = pathinfo( (string) wp_parse_url( $photo['download_url'], PHP_URL_PATH ), PATHINFO_EXTENSION );
		$ext = $ext ? strtolower( $ext ) : 'jpg';

		list( $tmp, $ext ) = $this->process_image( $tmp, $ext );

		$title = '' !== $photo['alt'] ? $photo['alt'] : sprintf( '%s %s', $provider->label(), $id );

		$attachment_id = media_handle_sideload(
			array(
				'name'     => sanitize_file_name( sprintf( '%s-%s.%s', $slug, $id, $ext ) ),
				'tmp_name' => $tmp,
			),
			0,
			null,
			array(
				'post_title'   => $title,
				'post_excerpt' => $photo['credit'],
			)
		);

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return $attachment_id;
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $photo['alt'] );
		update_post_meta( $attachment_id, '_wpmi_source', $slug );
		update_post_meta( $attachment_id, '_wpmi_source_id', $id );

		$provider->on_import( $photo );

		return array(
			'id'       => $attachment_id,
			'existing' => false,
		);
	}

	/**
	 * Resize, re-compress and optionally convert a downloaded image per the
	 * Image processing settings. On any editor failure the original file is
	 * kept, so an import never fails because of optimisation.
	 *
	 * @param string $file Temp file path.
	 * @param string $ext  Its extension.
	 *
	 * @return array{0: string, 1: string} New path and extension.
	 */
	private function process_image( string $file, string $ext ): array {
		$image  = Settings::image();
		$editor = wp_get_image_editor( $file );

		if ( is_wp_error( $editor ) ) {
			return array( $file, $ext );
		}

		$editor->set_quality( (int) $image['quality'] );

		if ( $image['max_width'] || $image['max_height'] ) {
			// Fits inside the box, never upscales. Errors here just mean "already small enough".
			$editor->resize( $image['max_width'] ? $image['max_width'] : null, $image['max_height'] ? $image['max_height'] : null );
		}

		$webp    = 'webp' === $image['format'] && wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
		$out_ext = $webp ? 'webp' : $ext;
		$saved   = $editor->save( $file . '-wpmi.' . $out_ext, $webp ? 'image/webp' : null );

		if ( is_wp_error( $saved ) ) {
			return array( $file, $ext );
		}

		wp_delete_file( $file );

		return array( $saved['path'], $out_ext );
	}

	/**
	 * Resolve the provider from the route.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return Provider|WP_Error
	 */
	private function provider( WP_REST_Request $request ) {
		$provider = Providers::get( (string) $request['provider'] );

		return $provider ? $provider : new WP_Error(
			'wpmi_unknown_provider',
			__( 'Unknown photo source.', 'wp-media-importer' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Standard 401/403.
	 *
	 * @return WP_Error
	 */
	private function forbidden(): WP_Error {
		return new WP_Error(
			'wpmi_forbidden',
			__( 'You are not allowed to do that.', 'wp-media-importer' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
