<?php
/**
 * Media Library registration for generated WebP/AVIF derivatives.
 *
 * @package VacuumImageOptimizer\Media
 */

namespace VacuumImageOptimizer\Media;

defined( 'ABSPATH' ) || exit;

/**
 * Creates (and de-duplicates) Media Library attachments for generated derivatives.
 *
 * A single source attachment maps to at most one WebP attachment and one AVIF
 * attachment. The mapping is stored on the source attachment so repeated
 * generation (manual, queue, or upload automation) always reuses the same item.
 */
class DerivativeLibrary {

	/**
	 * Supported derivative formats mapped to their MIME types.
	 *
	 * @var array<string, string>
	 */
	private const FORMAT_MIME_TYPES = [
		'webp' => 'image/webp',
		'avif' => 'image/avif',
	];

	/**
	 * Supported derivative formats mapped to their human labels.
	 *
	 * @var array<string, string>
	 */
	private const FORMAT_LABELS = [
		'webp' => 'WebP',
		'avif' => 'AVIF',
	];

	/**
	 * Ensure a Media Library attachment exists for a generated derivative.
	 *
	 * @param int    $source_id Source (original) attachment ID.
	 * @param string $file_path Absolute path to the generated derivative file.
	 * @param string $format    Derivative format: 'webp' or 'avif'.
	 * @return int The derivative attachment ID, or 0 on failure.
	 */
	public function ensure_attachment( int $source_id, string $file_path, string $format ): int {
		$format    = sanitize_key( $format );
		$source_id = absint( $source_id );

		if ( ! isset( self::FORMAT_MIME_TYPES[ $format ] ) || 0 === $source_id || '' === $file_path ) {
			return 0;
		}

		$file_path = wp_normalize_path( $file_path );
		if ( ! file_exists( $file_path ) || ! is_file( $file_path ) ) {
			return 0;
		}

		$link_key = $this->get_link_meta_key( $format );

		// Reuse the existing derivative attachment when one is already linked.
		$existing = absint( get_post_meta( $source_id, $link_key, true ) );
		if ( $existing > 0 && $this->is_live_attachment( $existing ) ) {
			return $existing;
		}

		$relative = $this->to_relative_uploads_path( $file_path );
		if ( '' === $relative ) {
			return 0;
		}

		// Reuse an attachment that already points at this file (avoid duplicates).
		$found = $this->find_attachment_by_file( $relative );
		if ( $found > 0 ) {
			update_post_meta( $source_id, $link_key, $found );
			$this->mark_derivative( $found, $source_id, $format );

			return $found;
		}

		$attachment_id = $this->insert_attachment( $source_id, $file_path, $relative, $format );
		if ( 0 === $attachment_id ) {
			return 0;
		}

		$this->mark_derivative( $attachment_id, $source_id, $format );
		$this->store_basic_metadata( $attachment_id, $file_path, $relative );
		update_post_meta( $source_id, $link_key, $attachment_id );

		return $attachment_id;
	}

	/**
	 * Build the source-side meta key that links to a derivative attachment.
	 *
	 * @param string $format Derivative format.
	 * @return string
	 */
	private function get_link_meta_key( string $format ): string {
		return '_vacimg_' . $format . '_attachment_id';
	}

	/**
	 * Insert the derivative attachment record.
	 *
	 * @param int    $source_id Source attachment ID.
	 * @param string $file_path Absolute derivative file path.
	 * @param string $relative  Uploads-relative derivative path.
	 * @param string $format    Derivative format.
	 * @return int
	 */
	private function insert_attachment( int $source_id, string $file_path, string $relative, string $format ): int {
		$source_title = get_the_title( $source_id );
		if ( '' === $source_title ) {
			$source_title = (string) pathinfo( $file_path, PATHINFO_FILENAME );
		}

		$title = sprintf(
			/* translators: 1: original media title, 2: derivative format label (WebP/AVIF). */
			__( '%1$s (%2$s)', 'vacuum-image-optimizer' ),
			$source_title,
			self::FORMAT_LABELS[ $format ]
		);

		$uploads = wp_get_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? untrailingslashit( (string) $uploads['baseurl'] ) : '';
		$guid    = '' !== $baseurl ? trailingslashit( $baseurl ) . $relative : '';

		$attachment = [
			'guid'           => $guid,
			'post_mime_type' => self::FORMAT_MIME_TYPES[ $format ],
			'post_title'     => $title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		$attachment_id = wp_insert_attachment( $attachment, $file_path, 0, true );

		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}

		return absint( $attachment_id );
	}

	/**
	 * Store the plugin's derivative tracking metadata.
	 *
	 * @param int    $attachment_id Derivative attachment ID.
	 * @param int    $source_id Source attachment ID.
	 * @param string $format Derivative format.
	 * @return void
	 */
	private function mark_derivative( int $attachment_id, int $source_id, string $format ): void {
		update_post_meta( $attachment_id, '_vacimg_source_attachment_id', absint( $source_id ) );
		update_post_meta( $attachment_id, '_vacimg_generated_by', sanitize_key( $format ) );
	}

	/**
	 * Store lightweight attachment metadata so the item renders in the grid.
	 *
	 * Intentionally avoids generating intermediate sizes to keep this fast and
	 * to avoid creating extra derivative thumbnail files.
	 *
	 * @param int    $attachment_id Derivative attachment ID.
	 * @param string $file_path Absolute derivative file path.
	 * @param string $relative  Uploads-relative derivative path.
	 * @return void
	 */
	private function store_basic_metadata( int $attachment_id, string $file_path, string $relative ): void {
		$metadata = [
			'file'       => $relative,
			'sizes'      => [],
			'image_meta' => [],
		];

		$dimensions = @getimagesize( $file_path ); // phpcs:ignore WordPress.PHP.NoSilentErrors.Discouraged
		if ( is_array( $dimensions ) ) {
			$metadata['width']  = absint( $dimensions[0] ?? 0 );
			$metadata['height'] = absint( $dimensions[1] ?? 0 );
		}

		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	/**
	 * Determine whether an attachment ID refers to a live (non-trashed) attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function is_live_attachment( int $attachment_id ): bool {
		$post = get_post( $attachment_id );

		return $post instanceof \WP_Post
			&& 'attachment' === $post->post_type
			&& 'trash' !== $post->post_status;
	}

	/**
	 * Find an existing attachment that points at an uploads-relative file.
	 *
	 * @param string $relative Uploads-relative file path.
	 * @return int
	 */
	private function find_attachment_by_file( string $relative ): int {
		global $wpdb;

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				'_wp_attached_file',
				$relative
			)
		);

		return absint( $post_id );
	}

	/**
	 * Convert an absolute path to an uploads-relative path.
	 *
	 * @param string $path Absolute file path.
	 * @return string
	 */
	private function to_relative_uploads_path( string $path ): string {
		$uploads = wp_get_upload_dir();
		$basedir = isset( $uploads['basedir'] ) ? wp_normalize_path( (string) $uploads['basedir'] ) : '';
		$path    = wp_normalize_path( $path );

		if ( '' === $basedir || 0 !== strpos( $path, trailingslashit( $basedir ) ) ) {
			return '';
		}

		return ltrim( substr( $path, strlen( trailingslashit( $basedir ) ) ), '/' );
	}
}
