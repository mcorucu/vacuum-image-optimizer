<?php
/**
 * Single-image AVIF generation engine.
 *
 * @package VacuumImageOptimizer\Engine
 */

namespace VacuumImageOptimizer\Engine;

use VacuumImageOptimizer\Backup\BackupManager;
use VacuumImageOptimizer\Media\DerivativeLibrary;
use VacuumImageOptimizer\Settings\CompressionSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Generates an AVIF derivative for one attachment as a parallel format to WebP.
 *
 * This engine never modifies or deletes the original image and stores its own
 * AVIF-specific metadata so existing WebP metadata is preserved.
 */
class AVIFGenerator {

	/**
	 * Supported source MIME types.
	 *
	 * @var array<int, string>
	 */
	private const SUPPORTED_MIME_TYPES = [
		'image/jpeg',
		'image/png',
	];

	/**
	 * AVIF quality used by both engines.
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
	 * @param int|null           $quality AVIF quality.
	 */
	public function __construct( ?BackupManager $backup_manager = null, ?int $quality = null ) {
		$this->backup_manager = $backup_manager ?? new BackupManager();
		$this->quality        = max( 0, min( 100, $quality ?? CompressionSettings::get_avif_quality() ) );
	}

	/**
	 * Delete the existing AVIF derivative for an attachment while keeping originals intact.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function delete_existing_avif( int $attachment_id ): bool {
		$avif_path = get_post_meta( $attachment_id, '_vio_avif_path', true );
		$avif_path = is_string( $avif_path ) ? wp_normalize_path( $avif_path ) : '';

		if ( '' === $avif_path ) {
			$source_path = get_attached_file( $attachment_id );
			$source_path = is_string( $source_path ) ? wp_normalize_path( $source_path ) : '';

			if ( '' !== $source_path ) {
				$avif_path = $this->build_avif_path( $source_path );
			}
		}

		if ( '' !== $avif_path && file_exists( $avif_path ) && is_file( $avif_path ) ) {
			return wp_delete_file( $avif_path );
		}

		return true;
	}

	/**
	 * Generate an AVIF file for an attachment.
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

		$post = get_post( $attachment_id );
		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return $this->fail( $result, __( 'Attachment could not be found.', 'vacuum-image-optimizer' ) );
		}

		$mime_type = (string) get_post_mime_type( $attachment_id );
		if ( ! wp_attachment_is_image( $attachment_id ) || ! in_array( $mime_type, self::SUPPORTED_MIME_TYPES, true ) ) {
			return $this->fail( $result, __( 'This attachment format is not supported for AVIF generation.', 'vacuum-image-optimizer' ) );
		}

		if ( ! $this->supports_imagick_avif() && ! $this->supports_gd_avif( $mime_type ) ) {
			return $this->fail( $result, __( 'AVIF generation is not available on this server.', 'vacuum-image-optimizer' ) );
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
		$avif_path             = $this->build_avif_path( $source_path );

		if ( '' === $avif_path ) {
			return $this->fail( $result, __( 'The AVIF output path could not be created.', 'vacuum-image-optimizer' ) );
		}

		$result['avif_path'] = $avif_path;

		// Do not overwrite an existing AVIF file; refresh metadata instead.
		if ( file_exists( $avif_path ) && is_readable( $avif_path ) ) {
			$avif_size = filesize( $avif_path );
			if ( false !== $avif_size ) {
				$result = $this->complete_result( $result, (int) $avif_size, 'existing', __( 'Existing AVIF file found. Metadata was refreshed.', 'vacuum-image-optimizer' ) );
				$this->save_success_metadata( $result );

				return $result;
			}
		}

		$engine_errors = [];
		$engine_used   = '';
		$generated     = false;

		if ( $this->supports_imagick_avif() ) {
			try {
				$generated = $this->generate_with_imagick( $source_path, $avif_path );
				if ( $generated ) {
					$engine_used = 'imagick';
				}
			} catch ( \Throwable $throwable ) {
				$engine_errors[] = $throwable->getMessage();
			}
		}

		if ( ! $generated && $this->supports_gd_avif( $mime_type ) ) {
			try {
				$generated = $this->generate_with_gd( $source_path, $avif_path, $mime_type );
				if ( $generated ) {
					$engine_used = 'gd';
				}
			} catch ( \Throwable $throwable ) {
				$engine_errors[] = $throwable->getMessage();
			}
		}

		if ( ! $generated ) {
			$message = empty( $engine_errors )
				? __( 'No available image engine can generate AVIF on this server.', 'vacuum-image-optimizer' )
				: __( 'AVIF generation failed with the available image engines.', 'vacuum-image-optimizer' );

			return $this->fail( $result, $message );
		}

		$avif_size = filesize( $avif_path );
		if ( false === $avif_size ) {
			return $this->fail( $result, __( 'The generated AVIF file size could not be read.', 'vacuum-image-optimizer' ) );
		}

		$result = $this->complete_result( $result, (int) $avif_size, $engine_used, __( 'AVIF image generated successfully.', 'vacuum-image-optimizer' ) );
		$this->save_success_metadata( $result );

		return $result;
	}

	/**
	 * Get supported MIME types.
	 *
	 * @return array<int, string>
	 */
	public static function get_supported_mime_types(): array {
		return self::SUPPORTED_MIME_TYPES;
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
			'avif_path'       => '',
			'source_size'     => 0,
			'avif_size'       => 0,
			'savings_bytes'   => 0,
			'savings_percent' => 0.0,
			'engine_used'     => '',
			'message'         => '',
		];
	}

	/**
	 * Mark a result as failed and store an AVIF-specific error without touching WebP status.
	 *
	 * @param array<string, bool|float|int|string> $result Result data.
	 * @param string                               $message Failure message.
	 * @return array<string, bool|float|int|string>
	 */
	private function fail( array $result, string $message ): array {
		$result['success'] = false;
		$result['message'] = $message;

		$attachment_id = absint( $result['attachment_id'] );
		if ( $attachment_id > 0 ) {
			update_post_meta( $attachment_id, '_vio_avif_error_message', sanitize_text_field( $message ) );
		}

		return $result;
	}

	/**
	 * Complete a successful result.
	 *
	 * @param array<string, bool|float|int|string> $result Result data.
	 * @param int                                  $avif_size AVIF file size.
	 * @param string                               $engine_used Engine label.
	 * @param string                               $message Success message.
	 * @return array<string, bool|float|int|string>
	 */
	private function complete_result( array $result, int $avif_size, string $engine_used, string $message ): array {
		$source_size     = max( 0, (int) $result['source_size'] );
		$savings_bytes   = max( 0, $source_size - $avif_size );
		$savings_percent = $source_size > 0 ? round( ( $savings_bytes / $source_size ) * 100, 2 ) : 0.0;

		$result['success']         = true;
		$result['avif_size']       = $avif_size;
		$result['savings_bytes']   = $savings_bytes;
		$result['savings_percent'] = $savings_percent;
		$result['engine_used']     = $engine_used;
		$result['message']         = $message;

		return $result;
	}

	/**
	 * Save successful AVIF metadata to the attachment without removing WebP metadata.
	 *
	 * @param array<string, bool|float|int|string> $result Result data.
	 * @return void
	 */
	private function save_success_metadata( array $result ): void {
		$attachment_id = absint( $result['attachment_id'] );
		if ( 0 === $attachment_id ) {
			return;
		}

		$avif_url = $this->path_to_upload_url( (string) $result['avif_path'] );

		update_post_meta( $attachment_id, '_vio_avif_path', (string) $result['avif_path'] );
		update_post_meta( $attachment_id, '_vio_avif_url', $avif_url );
		update_post_meta( $attachment_id, '_vio_avif_size', absint( $result['avif_size'] ) );
		update_post_meta( $attachment_id, '_vio_avif_savings_bytes', absint( $result['savings_bytes'] ) );
		update_post_meta( $attachment_id, '_vio_avif_savings_percent', (float) $result['savings_percent'] );
		update_post_meta( $attachment_id, '_vio_avif_engine_used', sanitize_key( (string) $result['engine_used'] ) );
		update_post_meta( $attachment_id, '_vio_avif_generated_at', current_time( 'mysql' ) );
		delete_post_meta( $attachment_id, '_vio_avif_error_message' );

		// Register (or reuse) a Media Library item for the generated AVIF file.
		( new DerivativeLibrary() )->ensure_attachment( $attachment_id, (string) $result['avif_path'], 'avif' );
	}

	/**
	 * Build the AVIF path next to the original image.
	 *
	 * @param string $source_path Source image path.
	 * @return string
	 */
	private function build_avif_path( string $source_path ): string {
		$directory = wp_normalize_path( dirname( $source_path ) );
		$filename  = pathinfo( $source_path, PATHINFO_FILENAME );

		if ( '' === $directory || '' === $filename ) {
			return '';
		}

		return trailingslashit( $directory ) . sanitize_file_name( $filename ) . '.avif';
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
	 * Check Imagick AVIF write support.
	 *
	 * @return bool
	 */
	private function supports_imagick_avif(): bool {
		if ( ! class_exists( '\\Imagick' ) ) {
			return false;
		}

		$formats = \Imagick::queryFormats( 'AVIF' );

		return ! empty( $formats );
	}

	/**
	 * Check GD AVIF support for the source MIME type.
	 *
	 * @param string $mime_type Source MIME type.
	 * @return bool
	 */
	private function supports_gd_avif( string $mime_type ): bool {
		if ( ! function_exists( 'imageavif' ) ) {
			return false;
		}

		if ( 'image/jpeg' === $mime_type ) {
			return function_exists( 'imagecreatefromjpeg' );
		}

		if ( 'image/png' === $mime_type ) {
			return function_exists( 'imagecreatefrompng' );
		}

		return false;
	}

	/**
	 * Generate AVIF using Imagick.
	 *
	 * @param string $source_path Source image path.
	 * @param string $avif_path AVIF output path.
	 * @return bool
	 */
	private function generate_with_imagick( string $source_path, string $avif_path ): bool {
		$image = new \Imagick( $source_path );

		if ( $image->getNumberImages() > 1 ) {
			$image->setIteratorIndex( 0 );
		}

		$image->setImageFormat( 'AVIF' );
		$image->setImageCompressionQuality( $this->quality );

		if ( method_exists( $image, 'stripImage' ) ) {
			$image->stripImage();
		}

		$written = $image->writeImage( $avif_path );
		$image->clear();
		$image->destroy();

		return $written && file_exists( $avif_path );
	}

	/**
	 * Generate AVIF using GD.
	 *
	 * @param string $source_path Source image path.
	 * @param string $avif_path AVIF output path.
	 * @param string $mime_type Source MIME type.
	 * @return bool
	 */
	private function generate_with_gd( string $source_path, string $avif_path, string $mime_type ): bool {
		$image = 'image/jpeg' === $mime_type ? imagecreatefromjpeg( $source_path ) : imagecreatefrompng( $source_path );

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

		$written = imageavif( $image, $avif_path, $this->quality );
		imagedestroy( $image );

		return $written && file_exists( $avif_path );
	}
}
