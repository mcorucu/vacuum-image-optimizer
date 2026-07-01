<?php
/**
 * Single-image WebP generation engine.
 *
 * @package VacuumImageOptimizer\Engine
 */

namespace VacuumImageOptimizer\Engine;

use VacuumImageOptimizer\Backup\BackupManager;
use VacuumImageOptimizer\Media\DerivativeLibrary;
use VacuumImageOptimizer\Settings\CompressionSettings;
use VacuumImageOptimizer\Utils\ImageFormat;

defined( 'ABSPATH' ) || exit;

/**
 * Generates a WebP derivative for one attachment without modifying originals.
 */
class WebPGenerator {

	/**
	 * Supported source MIME types.
	 *
	 * @var array<int, string>
	 */
	private const SUPPORTED_MIME_TYPES = [
		'image/jpeg',
		'image/png',
		'image/webp',
	];

	/**
	 * WebP quality used by both engines.
	 *
	 * @var int
	 */
	private int $quality;

	/**
	 * Backup manager.
	 *
	 * @var BackupManager
	 */
	private BackupManager $backup_manager;

	/**
	 * Constructor.
	 *
	 * @param BackupManager|null $backup_manager Optional backup manager.
	 * @param int                $quality WebP quality.
	 */
	public function __construct( ?BackupManager $backup_manager = null, ?int $quality = null ) {
		$this->backup_manager = $backup_manager ?? new BackupManager();
		$this->quality        = max( 1, min( 100, $quality ?? CompressionSettings::get_quality() ) );
	}

	/**
	 * Delete the existing WebP derivative for an attachment while keeping backups intact.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function delete_existing_webp( int $attachment_id ): bool {
		if ( ImageFormat::is_webp_mime( ImageFormat::get_attachment_mime_type( $attachment_id ) ) ) {
			return true;
		}

		$webp_path = get_post_meta( $attachment_id, '_vacimg_webp_path', true );
		$webp_path = is_string( $webp_path ) ? wp_normalize_path( $webp_path ) : '';

		if ( '' === $webp_path ) {
			$source_path = get_attached_file( $attachment_id );
			$source_path = is_string( $source_path ) ? wp_normalize_path( $source_path ) : '';

			if ( '' !== $source_path ) {
				$webp_path = $this->build_webp_path( $source_path );
			}
		}

		if ( '' !== $webp_path && file_exists( $webp_path ) && is_file( $webp_path ) ) {
			return wp_delete_file( $webp_path );
		}

		return true;
	}

	/**
	 * Generate a WebP file for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, bool|float|int|string>
	 */
	public function generate( int $attachment_id ): array {
		$attachment_id = absint( $attachment_id );
		$result        = $this->get_base_result( $attachment_id );

		if ( 0 === $attachment_id ) {
			return $this->fail( $result, __( 'Invalid attachment ID.', 'vacuum-image-optimizer' ) );
		}

		if ( ! CompressionSettings::is_webp_enabled() ) {
			return $this->fail( $result, __( 'WebP generation is disabled in settings.', 'vacuum-image-optimizer' ), 'skipped' );
		}

		$post = get_post( $attachment_id );
		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return $this->fail( $result, __( 'Attachment could not be found.', 'vacuum-image-optimizer' ) );
		}

		$mime_type = ImageFormat::get_attachment_mime_type( $attachment_id );
		if ( ! ImageFormat::is_supported_source_attachment( $attachment_id ) || ! in_array( $mime_type, self::SUPPORTED_MIME_TYPES, true ) ) {
			return $this->fail( $result, ImageFormat::get_skip_reason( $attachment_id ), 'unsupported' );
		}

		$source_path = get_attached_file( $attachment_id );
		if ( ! is_string( $source_path ) || '' === $source_path ) {
			return $this->fail( $result, __( 'The original image path could not be resolved.', 'vacuum-image-optimizer' ) );
		}

		$source_path           = wp_normalize_path( $source_path );
		$result['source_path'] = $source_path;

		if ( ! file_exists( $source_path ) || ! is_file( $source_path ) || ! is_readable( $source_path ) ) {
			return $this->fail( $result, __( 'The original image file is missing or unreadable.', 'vacuum-image-optimizer' ) );
		}

		$source_size = filesize( $source_path );
		if ( false === $source_size ) {
			return $this->fail( $result, __( 'The original image file size could not be read.', 'vacuum-image-optimizer' ) );
		}

		$result['source_size'] = (int) $source_size;

		if ( ImageFormat::is_webp_mime( $mime_type ) ) {
			return $this->optimize_webp_source( $attachment_id, $source_path, (int) $source_size, $result );
		}

		$webp_path             = $this->build_webp_path( $source_path );

		if ( '' === $webp_path ) {
			return $this->fail( $result, __( 'The WebP output path could not be created.', 'vacuum-image-optimizer' ) );
		}

		$result['webp_path'] = $webp_path;

		// Skip backup creation entirely when backups are disabled; existing backups are left untouched.
		if ( CompressionSettings::is_backups_enabled() ) {
			$backup_path = $this->ensure_backup_copy( $attachment_id, $source_path );
			if ( '' === $backup_path ) {
				return $this->fail( $result, __( 'The original image backup could not be created.', 'vacuum-image-optimizer' ) );
			}

			update_post_meta( $attachment_id, '_vacimg_backup_path', $backup_path );
		}

		if ( file_exists( $webp_path ) && is_readable( $webp_path ) ) {
			$webp_size = filesize( $webp_path );
			if ( false !== $webp_size ) {
				$result = $this->complete_result( $result, (int) $webp_size, 'existing', __( 'Existing WebP file found. Metadata was refreshed.', 'vacuum-image-optimizer' ) );
				$this->save_success_metadata( $result );

				return $result;
			}
		}

		$engine_errors = [];
		$engine_used   = '';
		$generated     = false;

		if ( $this->supports_imagick_webp() ) {
			try {
				$generated = $this->generate_with_imagick( $source_path, $webp_path );
				if ( $generated ) {
					$engine_used = 'imagick';
				}
			} catch ( \Throwable $throwable ) {
				$engine_errors[] = $throwable->getMessage();
			}
		}

		if ( ! $generated && $this->supports_gd_webp( $mime_type ) ) {
			try {
				$generated = $this->generate_with_gd( $source_path, $webp_path, $mime_type );
				if ( $generated ) {
					$engine_used = 'gd';
				}
			} catch ( \Throwable $throwable ) {
				$engine_errors[] = $throwable->getMessage();
			}
		}

		if ( ! $generated ) {
			$message = empty( $engine_errors )
				? __( 'No available image engine can generate WebP on this server.', 'vacuum-image-optimizer' )
				: __( 'WebP generation failed with the available image engines.', 'vacuum-image-optimizer' );

			return $this->fail( $result, $message );
		}

		$webp_size = filesize( $webp_path );
		if ( false === $webp_size ) {
			return $this->fail( $result, __( 'The generated WebP file size could not be read.', 'vacuum-image-optimizer' ) );
		}

		$result = $this->complete_result( $result, (int) $webp_size, $engine_used, __( 'WebP image generated successfully.', 'vacuum-image-optimizer' ) );
		$this->save_success_metadata( $result );

		return $result;
	}

	/**
	 * Get supported MIME types.
	 *
	 * @return array<int, string>
	 */
	public static function get_supported_mime_types(): array {
		return ImageFormat::get_supported_source_mime_types();
	}

	/**
	 * Build the default result shape.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, bool|float|int|string>
	 */
	private function get_base_result( int $attachment_id ): array {
		return [
			'success'         => false,
			'attachment_id'   => $attachment_id,
			'source_path'     => '',
			'webp_path'       => '',
			'source_size'     => 0,
			'webp_size'       => 0,
			'savings_bytes'   => 0,
			'savings_percent' => 0.0,
			'engine_used'     => '',
			'mode'            => 'derivative',
			'message'         => '',
		];
	}

	/**
	 * Mark a result as failed and save attachment status.
	 *
	 * @param array<string, bool|float|int|string> $result Result data.
	 * @param string                               $message Failure message.
	 * @param string                               $status Attachment status.
	 * @return array<string, bool|float|int|string>
	 */
	private function fail( array $result, string $message, string $status = 'error' ): array {
		$result['success'] = false;
		$result['message'] = $message;

		$attachment_id = absint( $result['attachment_id'] );
		if ( $attachment_id > 0 ) {
			update_post_meta( $attachment_id, '_vacimg_status', sanitize_key( $status ) );
			update_post_meta( $attachment_id, '_vacimg_error_message', sanitize_text_field( $message ) );
		}

		return $result;
	}

	/**
	 * Complete a successful result.
	 *
	 * @param array<string, bool|float|int|string> $result Result data.
	 * @param int                                  $webp_size WebP file size.
	 * @param string                               $engine_used Engine label.
	 * @param string                               $message Success message.
	 * @return array<string, bool|float|int|string>
	 */
	private function complete_result( array $result, int $webp_size, string $engine_used, string $message ): array {
		$source_size     = max( 0, (int) $result['source_size'] );
		$savings_bytes   = max( 0, $source_size - $webp_size );
		$savings_percent = $source_size > 0 ? round( ( $savings_bytes / $source_size ) * 100, 2 ) : 0.0;

		$result['success']         = true;
		$result['webp_size']       = $webp_size;
		$result['savings_bytes']   = $savings_bytes;
		$result['savings_percent'] = $savings_percent;
		$result['engine_used']     = $engine_used;
		$result['message']         = $message;

		return $result;
	}

	/**
	 * Save successful WebP metadata to the attachment.
	 *
	 * @param array<string, bool|float|int|string> $result Result data.
	 * @return void
	 */
	private function save_success_metadata( array $result ): void {
		$attachment_id = absint( $result['attachment_id'] );
		if ( 0 === $attachment_id ) {
			return;
		}

		$webp_url = $this->path_to_upload_url( (string) $result['webp_path'] );
		$is_source_optimization = 'source' === (string) ( $result['mode'] ?? '' );

		update_post_meta( $attachment_id, '_vacimg_webp_path', (string) $result['webp_path'] );
		update_post_meta( $attachment_id, '_vacimg_webp_url', $webp_url );
		update_post_meta( $attachment_id, '_vacimg_source_size', absint( $result['source_size'] ) );
		update_post_meta( $attachment_id, '_vacimg_webp_size', absint( $result['webp_size'] ) );
		update_post_meta( $attachment_id, '_vacimg_savings_bytes', absint( $result['savings_bytes'] ) );
		update_post_meta( $attachment_id, '_vacimg_savings_percent', (float) $result['savings_percent'] );
		update_post_meta( $attachment_id, '_vacimg_engine_used', sanitize_key( (string) $result['engine_used'] ) );
		update_post_meta( $attachment_id, '_vacimg_optimized_at', current_time( 'mysql' ) );
		update_post_meta( $attachment_id, '_vacimg_status', 'optimized' );
		delete_post_meta( $attachment_id, '_vacimg_error_message' );

		if ( $is_source_optimization ) {
			update_post_meta( $attachment_id, '_vacimg_webp_source_optimized', absint( $result['savings_bytes'] ) > 0 ? 1 : 0 );
			return;
		}

		( new DerivativeLibrary() )->ensure_attachment( $attachment_id, (string) $result['webp_path'], 'webp' );
	}

	/**
	 * Recompress an existing WebP source file and replace it only when smaller.
	 *
	 * @param int                                  $attachment_id Attachment ID.
	 * @param string                               $source_path Source WebP path.
	 * @param int                                  $source_size Original source size.
	 * @param array<string, bool|float|int|string> $result Base result.
	 * @return array<string, bool|float|int|string>
	 */
	private function optimize_webp_source( int $attachment_id, string $source_path, int $source_size, array $result ): array {
		$backup_path = $this->ensure_backup_copy( $attachment_id, $source_path );
		if ( '' === $backup_path ) {
			return $this->fail( $result, __( 'The original WebP backup could not be created.', 'vacuum-image-optimizer' ) );
		}

		update_post_meta( $attachment_id, '_vacimg_backup_path', $backup_path );

		$temp_path = $this->get_unique_path( $source_path . '.vacimg-tmp.webp' );
		if ( '' === $temp_path ) {
			return $this->fail( $result, __( 'A temporary WebP optimization path could not be created.', 'vacuum-image-optimizer' ) );
		}

		$engine_errors = [];
		$engine_used   = '';
		$generated     = false;

		if ( $this->supports_imagick_webp() ) {
			try {
				$generated = $this->generate_with_imagick( $source_path, $temp_path );
				if ( $generated ) {
					$engine_used = 'imagick';
				}
			} catch ( \Throwable $throwable ) {
				$engine_errors[] = $throwable->getMessage();
			}
		}

		if ( ! $generated && $this->supports_gd_webp( 'image/webp' ) ) {
			try {
				$generated = $this->generate_with_gd( $source_path, $temp_path, 'image/webp' );
				if ( $generated ) {
					$engine_used = 'gd';
				}
			} catch ( \Throwable $throwable ) {
				$engine_errors[] = $throwable->getMessage();
			}
		}

		if ( ! $generated ) {
			$this->delete_temp_file( $temp_path );
			$message = empty( $engine_errors )
				? __( 'No available image engine can optimize WebP source files on this server.', 'vacuum-image-optimizer' )
				: __( 'WebP source optimization failed with the available image engines.', 'vacuum-image-optimizer' );

			return $this->fail( $result, $message );
		}

		$temp_size = filesize( $temp_path );
		if ( false === $temp_size ) {
			$this->delete_temp_file( $temp_path );
			return $this->fail( $result, __( 'The optimized WebP file size could not be read.', 'vacuum-image-optimizer' ) );
		}

		$result['webp_path'] = $source_path;
		$result['mode']      = 'source';

		if ( (int) $temp_size >= $source_size ) {
			$this->delete_temp_file( $temp_path );
			$result = $this->complete_result( $result, $source_size, $engine_used, __( 'WebP source already appears optimized; the original file was left unchanged.', 'vacuum-image-optimizer' ) );
			$this->save_success_metadata( $result );

			return $result;
		}

		if ( ! copy( $temp_path, $source_path ) ) {
			$this->delete_temp_file( $temp_path );
			return $this->fail( $result, __( 'The optimized WebP file could not replace the source image.', 'vacuum-image-optimizer' ) );
		}

		$this->delete_temp_file( $temp_path );

		$result = $this->complete_result( $result, (int) $temp_size, $engine_used, __( 'WebP source image optimized successfully.', 'vacuum-image-optimizer' ) );
		$this->save_success_metadata( $result );

		return $result;
	}

	/**
	 * Build the WebP path next to the original image.
	 *
	 * @param string $source_path Source image path.
	 * @return string
	 */
	private function build_webp_path( string $source_path ): string {
		$directory = wp_normalize_path( dirname( $source_path ) );
		$filename  = pathinfo( $source_path, PATHINFO_FILENAME );

		if ( '' === $directory || '' === $filename ) {
			return '';
		}

		return trailingslashit( $directory ) . sanitize_file_name( $filename ) . '.webp';
	}

	/**
	 * Create a backup copy of the source image if one does not already exist.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $source_path Source image path.
	 * @return string
	 */
	private function ensure_backup_copy( int $attachment_id, string $source_path ): string {
		$existing_backup = get_post_meta( $attachment_id, '_vacimg_backup_path', true );
		if ( is_string( $existing_backup ) && '' !== $existing_backup && file_exists( $existing_backup ) && is_readable( $existing_backup ) ) {
			return wp_normalize_path( $existing_backup );
		}

		$backup_path = $this->build_backup_path( $attachment_id, $source_path );
		if ( '' === $backup_path ) {
			return '';
		}

		$backup_path = $this->get_unique_path( $backup_path );
		$directory   = dirname( $backup_path );

		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return '';
		}

		if ( ! $this->backup_manager->is_valid_backup_path( $backup_path ) ) {
			return '';
		}

		return copy( $source_path, $backup_path ) ? wp_normalize_path( $backup_path ) : '';
	}

	/**
	 * Build a backup path under uploads/vacimg-backups while preserving upload relative path when possible.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $source_path Source image path.
	 * @return string
	 */
	private function build_backup_path( int $attachment_id, string $source_path ): string {
		if ( ! $this->backup_manager->ensure_backup_directory() ) {
			return '';
		}

		$uploads          = wp_upload_dir();
		$uploads_basedir  = isset( $uploads['basedir'] ) ? wp_normalize_path( (string) $uploads['basedir'] ) : '';
		$backup_directory = wp_normalize_path( $this->backup_manager->get_backup_directory() );
		$source_path      = wp_normalize_path( $source_path );

		if ( '' !== $uploads_basedir && 0 === strpos( $source_path, trailingslashit( $uploads_basedir ) ) ) {
			$relative = ltrim( substr( $source_path, strlen( trailingslashit( $uploads_basedir ) ) ), '/' );
			$parts    = array_filter( array_map( 'sanitize_file_name', explode( '/', $relative ) ) );

			if ( ! empty( $parts ) ) {
				return trailingslashit( $backup_directory ) . implode( '/', $parts );
			}
		}

		return wp_normalize_path( $this->backup_manager->create_backup_path( $attachment_id, $source_path ) );
	}

	/**
	 * Get a unique path without overwriting an existing file.
	 *
	 * @param string $path Desired path.
	 * @return string
	 */
	private function get_unique_path( string $path ): string {
		if ( ! file_exists( $path ) ) {
			return $path;
		}

		$directory = dirname( $path );
		$filename  = pathinfo( $path, PATHINFO_FILENAME );
		$extension = pathinfo( $path, PATHINFO_EXTENSION );

		for ( $index = 1; $index < 1000; $index++ ) {
			$candidate = trailingslashit( $directory ) . $filename . '-' . $index;
			if ( '' !== $extension ) {
				$candidate .= '.' . $extension;
			}

			if ( ! file_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Convert an upload path to its URL.
	 *
	 * @param string $path Absolute path.
	 * @return string
	 */
	private function path_to_upload_url( string $path ): string {
		$uploads = wp_upload_dir();
		$basedir = isset( $uploads['basedir'] ) ? wp_normalize_path( (string) $uploads['basedir'] ) : '';
		$baseurl = isset( $uploads['baseurl'] ) ? untrailingslashit( (string) $uploads['baseurl'] ) : '';
		$path    = wp_normalize_path( $path );

		if ( '' === $basedir || '' === $baseurl || 0 !== strpos( $path, trailingslashit( $basedir ) ) ) {
			return '';
		}

		$relative = ltrim( substr( $path, strlen( trailingslashit( $basedir ) ) ), '/' );

		return trailingslashit( $baseurl ) . str_replace( '%2F', '/', rawurlencode( $relative ) );
	}

	/**
	 * Check Imagick WebP write support.
	 *
	 * @return bool
	 */
	private function supports_imagick_webp(): bool {
		if ( ! class_exists( '\\Imagick' ) ) {
			return false;
		}

		$formats = \Imagick::queryFormats( 'WEBP' );

		return ! empty( $formats );
	}

	/**
	 * Check GD WebP support for the source MIME type.
	 *
	 * @param string $mime_type Source MIME type.
	 * @return bool
	 */
	private function supports_gd_webp( string $mime_type ): bool {
		if ( ! function_exists( 'imagewebp' ) ) {
			return false;
		}

		if ( 'image/jpeg' === $mime_type ) {
			return function_exists( 'imagecreatefromjpeg' );
		}

		if ( 'image/png' === $mime_type ) {
			return function_exists( 'imagecreatefrompng' );
		}

		if ( 'image/webp' === $mime_type ) {
			return function_exists( 'imagecreatefromwebp' );
		}

		return false;
	}

	/**
	 * Generate WebP using Imagick.
	 *
	 * @param string $source_path Source image path.
	 * @param string $webp_path WebP output path.
	 * @return bool
	 */
	private function generate_with_imagick( string $source_path, string $webp_path ): bool {
		$image = new \Imagick( $source_path );

		if ( $image->getNumberImages() > 1 ) {
			$image->setIteratorIndex( 0 );
		}

		$image->setImageFormat( 'WEBP' );
		$image->setImageCompressionQuality( $this->quality );

		if ( method_exists( $image, 'stripImage' ) ) {
			$image->stripImage();
		}

		$written = $image->writeImage( $webp_path );
		$image->clear();
		$image->destroy();

		return $written && file_exists( $webp_path );
	}

	/**
	 * Generate WebP using GD.
	 *
	 * @param string $source_path Source image path.
	 * @param string $webp_path WebP output path.
	 * @param string $mime_type Source MIME type.
	 * @return bool
	 */
	private function generate_with_gd( string $source_path, string $webp_path, string $mime_type ): bool {
		if ( 'image/jpeg' === $mime_type ) {
			$image = imagecreatefromjpeg( $source_path );
		} elseif ( 'image/webp' === $mime_type ) {
			$image = imagecreatefromwebp( $source_path );
		} else {
			$image = imagecreatefrompng( $source_path );
		}

		if ( false === $image ) {
			return false;
		}

		if ( 'image/png' === $mime_type ) {
			if ( function_exists( 'imagepalettetotruecolor' ) ) {
				imagepalettetotruecolor( $image );
			}

			imagealphablending( $image, false );
			imagesavealpha( $image, true );
		}

		$written = imagewebp( $image, $webp_path, $this->quality );
		imagedestroy( $image );

		return $written && file_exists( $webp_path );
	}

	/**
	 * Delete a temporary file created by the optimizer.
	 *
	 * @param string $path Temporary path.
	 * @return void
	 */
	private function delete_temp_file( string $path ): void {
		if ( '' !== $path && file_exists( $path ) && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
