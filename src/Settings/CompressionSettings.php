<?php
/**
 * Compression settings storage and sanitization.
 *
 * @package VacuumImageOptimizer\Settings
 */

namespace VacuumImageOptimizer\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the Phase 4 compression profile and quality settings.
 */
class CompressionSettings {

	/**
	 * Main plugin settings option name.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'vacimg_settings';

	/**
	 * Settings API group name for the Compression tab.
	 *
	 * @var string
	 */
	public const OPTION_GROUP = 'vacimg_compression_settings';

	/**
	 * Default compression profile.
	 *
	 * @var string
	 */
	private const DEFAULT_PROFILE = 'balanced';

	/**
	 * Default upload automation mode.
	 *
	 * @var string
	 */
	private const DEFAULT_AUTO_OPTIMIZE_MODE = 'queue';

	/**
	 * Supported upload automation modes.
	 *
	 * @var array<int, string>
	 */
	private const AUTO_OPTIMIZE_MODES = [ 'immediate', 'queue' ];

	/**
	 * Default AVIF quality.
	 *
	 * @var int
	 */
	private const DEFAULT_AVIF_QUALITY = 60;

	/**
	 * Default frontend delivery preferred format.
	 *
	 * @var string
	 */
	private const DEFAULT_PREFERRED_FORMAT = 'auto';

	/**
	 * Supported frontend delivery preferred formats.
	 *
	 * @var array<int, string>
	 */
	private const PREFERRED_FORMATS = [ 'auto', 'avif', 'webp' ];

	/**
	 * Default backup retention period in days (0 = keep backups indefinitely).
	 *
	 * @var int
	 */
	private const DEFAULT_BACKUP_RETENTION_DAYS = 0;

	/**
	 * Maximum supported backup retention period in days (~10 years).
	 *
	 * @var int
	 */
	private const MAX_BACKUP_RETENTION_DAYS = 3650;

	/**
	 * Default interface language (follow the WordPress locale).
	 *
	 * @var string
	 */
	private const DEFAULT_INTERFACE_LANGUAGE = 'wordpress';

	/**
	 * Supported interface language values (locale codes + the WordPress default).
	 *
	 * @var array<int, string>
	 */
	private const INTERFACE_LANGUAGES = [
		'wordpress',
		'en_US',
		'tr_TR',
		'de_DE',
		'fr_FR',
		'es_ES',
		'it_IT',
		'pt_PT',
		'ru_RU',
		'nl_NL',
		'pl_PL',
	];

	/**
	 * Supported profile quality defaults.
	 *
	 * @var array<string, int>
	 */
	private const PROFILES = [
		'lossless'   => 95,
		'balanced'   => 82,
		'aggressive' => 75,
		'ultra'      => 65,
	];

	/**
	 * Register Settings API storage.
	 *
	 * @return void
	 */
	public function register(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => self::get_defaults(),
				'show_in_rest'      => false,
			]
		);
	}

	/**
	 * Get profile definitions.
	 *
	 * @return array<string, int>
	 */
	public static function get_profiles(): array {
		return self::PROFILES;
	}

	/**
	 * Get default settings.
	 *
	 * @return array{quality: int, profile: string, auto_optimize_uploads: bool, auto_optimize_mode: string, enable_avif: bool, avif_quality: int}
	 */
	public static function get_defaults(): array {
		return [
			'quality'               => self::PROFILES[ self::DEFAULT_PROFILE ],
			'profile'               => self::DEFAULT_PROFILE,
			'enable_webp'           => true,
			'auto_optimize_uploads' => false,
			'auto_optimize_mode'    => self::DEFAULT_AUTO_OPTIMIZE_MODE,
			'enable_avif'           => false,
			'avif_quality'          => self::DEFAULT_AVIF_QUALITY,
			'enable_frontend_delivery' => false,
			'preferred_format'      => self::DEFAULT_PREFERRED_FORMAT,
			'enable_backups'        => true,
			'backup_retention_days' => self::DEFAULT_BACKUP_RETENTION_DAYS,
			'enable_lazy_loading'   => true,
			'safe_mode'             => true,
			'max_width'             => 0,
			'max_height'            => 0,
			'exclude_mime_types'    => [],
			'min_file_size'         => 0,
			'max_file_size'         => 0,
			'exclude_filename_patterns' => '',
			'exclude_path_patterns' => '',
			'exclude_gif'           => true,
			'exclude_svg'           => true,
			'interface_language'    => self::DEFAULT_INTERFACE_LANGUAGE,
		];
	}

	/**
	 * Get normalized compression settings.
	 *
	 * @return array{quality: int, profile: string, auto_optimize_uploads: bool, auto_optimize_mode: string, enable_avif: bool, avif_quality: int}
	 */
	public static function get(): array {
		$settings = get_option( self::OPTION_NAME, [] );

		if ( ! is_array( $settings ) ) {
			return self::get_defaults();
		}

		return self::normalize( $settings );
	}

	/**
	 * Get the current profile key.
	 *
	 * @return string
	 */
	public static function get_profile(): string {
		$settings = self::get();

		return $settings['profile'];
	}

	/**
	 * Get the current quality value.
	 *
	 * @return int
	 */
	public static function get_quality(): int {
		$settings = self::get();

		return $settings['quality'];
	}

	/**
	 * Check whether WebP generation/optimization is enabled.
	 *
	 * @return bool
	 */
	public static function is_webp_enabled(): bool {
		$settings = self::get();

		return (bool) $settings['enable_webp'];
	}

	/**
	 * Check whether automatic optimization for new uploads is enabled.
	 *
	 * @return bool
	 */
	public static function is_auto_optimize_uploads_enabled(): bool {
		$settings = self::get();

		return (bool) $settings['auto_optimize_uploads'];
	}

	/**
	 * Get the upload automation mode.
	 *
	 * @return string
	 */
	public static function get_auto_optimize_mode(): string {
		$settings = self::get();

		return $settings['auto_optimize_mode'];
	}

	/**
	 * Check whether AVIF generation is enabled.
	 *
	 * @return bool
	 */
	public static function is_avif_enabled(): bool {
		$settings = self::get();

		return (bool) $settings['enable_avif'];
	}

	/**
	 * Get the configured AVIF quality.
	 *
	 * @return int
	 */
	public static function get_avif_quality(): int {
		$settings = self::get();

		return $settings['avif_quality'];
	}

	/**
	 * Check whether frontend delivery is enabled.
	 *
	 * @return bool
	 */
	public static function is_frontend_delivery_enabled(): bool {
		$settings = self::get();

		return (bool) $settings['enable_frontend_delivery'];
	}

	/**
	 * Get the configured frontend delivery preferred format.
	 *
	 * @return string
	 */
	public static function get_preferred_format(): string {
		$settings = self::get();

		return $settings['preferred_format'];
	}

	/**
	 * Get the supported frontend delivery preferred formats.
	 *
	 * @return array<int, string>
	 */
	public static function get_preferred_formats(): array {
		return self::PREFERRED_FORMATS;
	}

	/**
	 * Check whether original backups are enabled.
	 *
	 * @return bool
	 */
	public static function is_backups_enabled(): bool {
		$settings = self::get();

		return (bool) $settings['enable_backups'];
	}

	/**
	 * Get the configured backup retention period in days (0 = keep indefinitely).
	 *
	 * Filterable via "vacimg_backup_retention_days" for advanced/headless setups.
	 *
	 * @return int
	 */
	public static function get_backup_retention_days(): int {
		$settings = self::get();
		$days     = self::clamp_retention_days( absint( $settings['backup_retention_days'] ) );

		/**
		 * Filter the backup retention period (in days).
		 *
		 * @param int $days Retention period in days. 0 disables automatic cleanup.
		 */
		$days = (int) apply_filters( 'vacimg_backup_retention_days', $days );

		return self::clamp_retention_days( max( 0, $days ) );
	}

	/**
	 * Check whether automatic backup retention/cleanup is enabled.
	 *
	 * @return bool
	 */
	public static function is_backup_retention_enabled(): bool {
		return self::get_backup_retention_days() > 0;
	}

	/**
	 * Check whether native frontend lazy loading is enabled.
	 *
	 * @return bool
	 */
	public static function is_lazy_loading_enabled(): bool {
		$settings = self::get();

		return (bool) $settings['enable_lazy_loading'];
	}

	/**
	 * Check whether Safe Mode is enabled.
	 *
	 * @return bool
	 */
	public static function is_safe_mode_enabled(): bool {
		$settings = self::get();

		return (bool) $settings['safe_mode'];
	}

	/**
	 * Get configured resize limits. Zero means preserve original dimension.
	 *
	 * @return array{max_width: int, max_height: int}
	 */
	public static function get_resize_limits(): array {
		$settings = self::get();

		return [
			'max_width'  => absint( $settings['max_width'] ),
			'max_height' => absint( $settings['max_height'] ),
		];
	}

	/**
	 * Get excluded MIME types.
	 *
	 * @return array<int, string>
	 */
	public static function get_excluded_mime_types(): array {
		$settings = self::get();

		return array_values( array_filter( array_map( 'sanitize_mime_type', (array) $settings['exclude_mime_types'] ) ) );
	}

	/**
	 * Get minimum file size exclusion threshold.
	 *
	 * @return int
	 */
	public static function get_min_file_size(): int {
		$settings = self::get();

		return absint( $settings['min_file_size'] );
	}

	/**
	 * Get maximum file size exclusion threshold.
	 *
	 * @return int
	 */
	public static function get_max_file_size(): int {
		$settings = self::get();

		return absint( $settings['max_file_size'] );
	}

	/**
	 * Get filename exclusion patterns.
	 *
	 * @return array<int, string>
	 */
	public static function get_filename_patterns(): array {
		$settings = self::get();

		return self::split_patterns( (string) $settings['exclude_filename_patterns'] );
	}

	/**
	 * Get path exclusion patterns.
	 *
	 * @return array<int, string>
	 */
	public static function get_path_patterns(): array {
		$settings = self::get();

		return self::split_patterns( (string) $settings['exclude_path_patterns'] );
	}

	/**
	 * Check whether animated GIF files are excluded.
	 *
	 * @return bool
	 */
	public static function is_gif_excluded(): bool {
		$settings = self::get();

		return (bool) $settings['exclude_gif'];
	}

	/**
	 * Check whether SVG files are excluded.
	 *
	 * @return bool
	 */
	public static function is_svg_excluded(): bool {
		$settings = self::get();

		return (bool) $settings['exclude_svg'];
	}

	/**
	 * Get the stored interface language value (locale code or "wordpress").
	 *
	 * @return string
	 */
	public static function get_interface_language(): string {
		$settings = self::get();

		return $settings['interface_language'];
	}

	/**
	 * Get the list of supported interface language values.
	 *
	 * @return array<int, string>
	 */
	public static function get_interface_languages(): array {
		return self::INTERFACE_LANGUAGES;
	}

	/**
	 * Get a map of language value => native label.
	 *
	 * @return array<string, string>
	 */
	public static function get_language_labels(): array {
		return [
			'wordpress' => __( 'WordPress Default', 'vacuum-image-optimizer' ),
			'en_US'     => __( 'English', 'vacuum-image-optimizer' ),
			'tr_TR'     => 'Türkçe',
			'de_DE'     => 'Deutsch',
			'fr_FR'     => 'Français',
			'es_ES'     => 'Español',
			'it_IT'     => 'Italiano',
			'pt_PT'     => 'Português',
			'ru_RU'     => 'Русский',
			'nl_NL'     => 'Nederlands',
			'pl_PL'     => 'Polski',
		];
	}

	/**
	 * Get the native label for the currently selected interface language.
	 *
	 * @return string
	 */
	public static function get_current_language_label(): string {
		$labels   = self::get_language_labels();
		$language = self::get_interface_language();

		return $labels[ $language ] ?? $labels['wordpress'];
	}

	/**
	 * Resolve the effective locale used by the plugin interface.
	 *
	 * @return string
	 */
	public static function get_resolved_locale(): string {
		$language = self::get_interface_language();

		if ( 'wordpress' === $language ) {
			return determine_locale();
		}

		return $language;
	}

	/**
	 * Determine whether the interface language is a plugin override.
	 *
	 * @return bool
	 */
	public static function is_language_overridden(): bool {
		return 'wordpress' !== self::get_interface_language();
	}

	/**
	 * Determine whether a MIME type is excluded from eligibility detection.
	 *
	 * @param string $mime_type Attachment MIME type.
	 * @return bool
	 */
	public static function is_mime_excluded( string $mime_type ): bool {
		if ( in_array( $mime_type, self::get_excluded_mime_types(), true ) ) {
			return true;
		}

		if ( 'image/gif' === $mime_type ) {
			return self::is_gif_excluded();
		}

		if ( 'image/svg+xml' === $mime_type ) {
			return self::is_svg_excluded();
		}

		return false;
	}

	/**
	 * Get a human-readable profile label.
	 *
	 * @param string $profile Profile key.
	 * @return string
	 */
	public static function get_profile_label( string $profile ): string {
		$labels = [
			'lossless'   => __( 'Lossless', 'vacuum-image-optimizer' ),
			'balanced'   => __( 'Balanced', 'vacuum-image-optimizer' ),
			'aggressive' => __( 'Aggressive', 'vacuum-image-optimizer' ),
			'ultra'      => __( 'Ultra', 'vacuum-image-optimizer' ),
		];

		return $labels[ $profile ] ?? $labels[ self::DEFAULT_PROFILE ];
	}

	/**
	 * Sanitize Settings API input.
	 *
	 * @param mixed $input Raw settings input.
	 * @return array{quality: int, profile: string, auto_optimize_uploads: bool, auto_optimize_mode: string}
	 */
	public function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : [];
		$current  = self::get();
		$settings = $current;

		// Merge only the fields present in this submission so partial per-tab
		// settings forms never reset values managed on a different tab. Each
		// boolean checkbox ships a hidden "0" companion so its key is always
		// present when its own form is the one being saved.
		if ( array_key_exists( 'profile', $input ) ) {
			$profile = sanitize_key( (string) $input['profile'] );
			$settings['profile'] = array_key_exists( $profile, self::PROFILES ) ? $profile : $current['profile'];
		}

		if ( array_key_exists( 'quality', $input ) && is_numeric( $input['quality'] ) ) {
			$settings['quality'] = absint( $input['quality'] );
		}

		if ( array_key_exists( 'enable_webp', $input ) ) {
			$settings['enable_webp'] = ! empty( $input['enable_webp'] );
		}

		if ( array_key_exists( 'auto_optimize_uploads', $input ) ) {
			$settings['auto_optimize_uploads'] = ! empty( $input['auto_optimize_uploads'] );
		}

		if ( array_key_exists( 'auto_optimize_mode', $input ) ) {
			$mode = sanitize_key( (string) $input['auto_optimize_mode'] );
			$settings['auto_optimize_mode'] = in_array( $mode, self::AUTO_OPTIMIZE_MODES, true ) ? $mode : $current['auto_optimize_mode'];
		}

		if ( array_key_exists( 'enable_avif', $input ) ) {
			$settings['enable_avif'] = ! empty( $input['enable_avif'] );
		}

		if ( array_key_exists( 'avif_quality', $input ) && is_numeric( $input['avif_quality'] ) ) {
			$settings['avif_quality'] = absint( $input['avif_quality'] );
		}

		if ( array_key_exists( 'enable_frontend_delivery', $input ) ) {
			$settings['enable_frontend_delivery'] = ! empty( $input['enable_frontend_delivery'] );
		}

		if ( array_key_exists( 'preferred_format', $input ) ) {
			$pf = sanitize_key( (string) $input['preferred_format'] );
			$settings['preferred_format'] = in_array( $pf, self::PREFERRED_FORMATS, true ) ? $pf : $current['preferred_format'];
		}

		if ( array_key_exists( 'enable_backups', $input ) ) {
			$settings['enable_backups'] = ! empty( $input['enable_backups'] );
		}

		if ( array_key_exists( 'backup_retention_days', $input ) && is_numeric( $input['backup_retention_days'] ) ) {
			$settings['backup_retention_days'] = self::clamp_retention_days( absint( $input['backup_retention_days'] ) );
		}

		if ( array_key_exists( 'enable_lazy_loading', $input ) ) {
			$settings['enable_lazy_loading'] = ! empty( $input['enable_lazy_loading'] );
		}

		if ( array_key_exists( 'safe_mode', $input ) ) {
			$settings['safe_mode'] = ! empty( $input['safe_mode'] );
		}

		if ( array_key_exists( 'max_width', $input ) && is_numeric( $input['max_width'] ) ) {
			$settings['max_width'] = absint( $input['max_width'] );
		}

		if ( array_key_exists( 'max_height', $input ) && is_numeric( $input['max_height'] ) ) {
			$settings['max_height'] = absint( $input['max_height'] );
		}

		if ( array_key_exists( 'exclude_mime_types', $input ) ) {
			$settings['exclude_mime_types'] = self::sanitize_mime_list( $input['exclude_mime_types'] );
		}

		if ( array_key_exists( 'min_file_size', $input ) && is_numeric( $input['min_file_size'] ) ) {
			$settings['min_file_size'] = absint( $input['min_file_size'] );
		}

		if ( array_key_exists( 'max_file_size', $input ) && is_numeric( $input['max_file_size'] ) ) {
			$settings['max_file_size'] = absint( $input['max_file_size'] );
		}

		if ( array_key_exists( 'exclude_filename_patterns', $input ) ) {
			$settings['exclude_filename_patterns'] = self::sanitize_pattern_text( (string) $input['exclude_filename_patterns'] );
		}

		if ( array_key_exists( 'exclude_path_patterns', $input ) ) {
			$settings['exclude_path_patterns'] = self::sanitize_pattern_text( (string) $input['exclude_path_patterns'] );
		}

		if ( array_key_exists( 'exclude_gif', $input ) ) {
			$settings['exclude_gif'] = ! empty( $input['exclude_gif'] );
		}

		if ( array_key_exists( 'exclude_svg', $input ) ) {
			$settings['exclude_svg'] = ! empty( $input['exclude_svg'] );
		}

		if ( array_key_exists( 'interface_language', $input ) ) {
			$language = sanitize_text_field( (string) $input['interface_language'] );
			$settings['interface_language'] = in_array( $language, self::INTERFACE_LANGUAGES, true ) ? $language : $current['interface_language'];
		}

		return self::normalize( $settings );
	}

	/**
	 * Normalize stored settings to the Phase 4 option structure.
	 *
	 * @param array<mixed> $settings Raw stored settings.
	 * @return array{quality: int, profile: string, auto_optimize_uploads: bool, auto_optimize_mode: string, enable_avif: bool, avif_quality: int}
	 */
	private static function normalize( array $settings ): array {
		$profile = isset( $settings['profile'] ) ? sanitize_key( (string) $settings['profile'] ) : '';

		if ( '' === $profile && isset( $settings['compression_profile'] ) ) {
			$profile = sanitize_key( (string) $settings['compression_profile'] );
		}

		if ( ! array_key_exists( $profile, self::PROFILES ) ) {
			$profile = self::DEFAULT_PROFILE;
		}

		$quality = isset( $settings['quality'] ) && is_numeric( $settings['quality'] )
			? absint( $settings['quality'] )
			: self::PROFILES[ $profile ];

		$enable_webp = array_key_exists( 'enable_webp', $settings ) ? ! empty( $settings['enable_webp'] ) : true;

		$auto_optimize_uploads = ! empty( $settings['auto_optimize_uploads'] );
		$auto_optimize_mode    = isset( $settings['auto_optimize_mode'] ) ? sanitize_key( (string) $settings['auto_optimize_mode'] ) : self::DEFAULT_AUTO_OPTIMIZE_MODE;

		if ( ! in_array( $auto_optimize_mode, self::AUTO_OPTIMIZE_MODES, true ) ) {
			$auto_optimize_mode = self::DEFAULT_AUTO_OPTIMIZE_MODE;
		}

		$enable_avif  = ! empty( $settings['enable_avif'] );
		$avif_quality = isset( $settings['avif_quality'] ) && is_numeric( $settings['avif_quality'] )
			? absint( $settings['avif_quality'] )
			: self::DEFAULT_AVIF_QUALITY;

		$enable_frontend_delivery = ! empty( $settings['enable_frontend_delivery'] );
		$preferred_format         = isset( $settings['preferred_format'] ) ? sanitize_key( (string) $settings['preferred_format'] ) : self::DEFAULT_PREFERRED_FORMAT;

		if ( ! in_array( $preferred_format, self::PREFERRED_FORMATS, true ) ) {
			$preferred_format = self::DEFAULT_PREFERRED_FORMAT;
		}

		$backup_retention_days = isset( $settings['backup_retention_days'] ) && is_numeric( $settings['backup_retention_days'] )
			? self::clamp_retention_days( absint( $settings['backup_retention_days'] ) )
			: self::DEFAULT_BACKUP_RETENTION_DAYS;

		// Defaults for these differ from "false", so honor stored keys explicitly.
		$enable_backups      = array_key_exists( 'enable_backups', $settings ) ? ! empty( $settings['enable_backups'] ) : true;
		$enable_lazy_loading = array_key_exists( 'enable_lazy_loading', $settings ) ? ! empty( $settings['enable_lazy_loading'] ) : true;
		$safe_mode           = array_key_exists( 'safe_mode', $settings ) ? ! empty( $settings['safe_mode'] ) : true;
		if ( $safe_mode ) {
			$enable_backups = true;
		}
		$exclude_gif         = array_key_exists( 'exclude_gif', $settings ) ? ! empty( $settings['exclude_gif'] ) : true;
		$exclude_svg         = array_key_exists( 'exclude_svg', $settings ) ? ! empty( $settings['exclude_svg'] ) : true;
		$exclude_mime_types  = isset( $settings['exclude_mime_types'] ) ? self::sanitize_mime_list( $settings['exclude_mime_types'] ) : [];
		$min_file_size       = isset( $settings['min_file_size'] ) && is_numeric( $settings['min_file_size'] ) ? absint( $settings['min_file_size'] ) : 0;
		$max_file_size       = isset( $settings['max_file_size'] ) && is_numeric( $settings['max_file_size'] ) ? absint( $settings['max_file_size'] ) : 0;
		$max_width           = isset( $settings['max_width'] ) && is_numeric( $settings['max_width'] ) ? absint( $settings['max_width'] ) : 0;
		$max_height          = isset( $settings['max_height'] ) && is_numeric( $settings['max_height'] ) ? absint( $settings['max_height'] ) : 0;
		$filename_patterns   = isset( $settings['exclude_filename_patterns'] ) ? self::sanitize_pattern_text( (string) $settings['exclude_filename_patterns'] ) : '';
		$path_patterns       = isset( $settings['exclude_path_patterns'] ) ? self::sanitize_pattern_text( (string) $settings['exclude_path_patterns'] ) : '';

		$interface_language = isset( $settings['interface_language'] ) ? sanitize_text_field( (string) $settings['interface_language'] ) : self::DEFAULT_INTERFACE_LANGUAGE;
		if ( ! in_array( $interface_language, self::INTERFACE_LANGUAGES, true ) ) {
			$interface_language = self::DEFAULT_INTERFACE_LANGUAGE;
		}

		return [
				'quality'               => self::clamp_quality( $quality ),
				'profile'               => $profile,
				'enable_webp'           => $enable_webp,
				'auto_optimize_uploads' => $auto_optimize_uploads,
				'auto_optimize_mode'    => $auto_optimize_mode,
				'enable_avif'           => $enable_avif,
			'avif_quality'          => self::clamp_avif_quality( $avif_quality ),
			'enable_frontend_delivery' => $enable_frontend_delivery,
			'preferred_format'      => $preferred_format,
				'enable_backups'        => $enable_backups,
				'backup_retention_days' => $backup_retention_days,
				'enable_lazy_loading'   => $enable_lazy_loading,
				'safe_mode'             => $safe_mode,
				'max_width'             => $max_width,
				'max_height'            => $max_height,
				'exclude_mime_types'    => $exclude_mime_types,
				'min_file_size'         => $min_file_size,
				'max_file_size'         => $max_file_size,
				'exclude_filename_patterns' => $filename_patterns,
				'exclude_path_patterns' => $path_patterns,
				'exclude_gif'           => $exclude_gif,
				'exclude_svg'           => $exclude_svg,
				'interface_language'    => $interface_language,
		];
	}

	/**
	 * Clamp quality to the valid WebP range.
	 *
	 * @param int $quality Quality value.
	 * @return int
	 */
	private static function clamp_quality( int $quality ): int {
		return max( 1, min( 100, $quality ) );
	}

	/**
	 * Clamp a backup retention value to the supported range.
	 *
	 * @param int $days Retention period in days.
	 * @return int
	 */
	private static function clamp_retention_days( int $days ): int {
		return max( 0, min( self::MAX_BACKUP_RETENTION_DAYS, $days ) );
	}

	/**
	 * Clamp AVIF quality to the valid range.
	 *
	 * @param int $quality Quality value.
	 * @return int
	 */
	private static function clamp_avif_quality( int $quality ): int {
		return max( 0, min( 100, $quality ) );
	}

	/**
	 * Sanitize a MIME type list from an array or textarea string.
	 *
	 * @param mixed $value Raw MIME list.
	 * @return array<int, string>
	 */
	private static function sanitize_mime_list( $value ): array {
		$items = is_array( $value ) ? $value : preg_split( '/[\r\n,]+/', (string) $value );
		$items = false === $items ? [] : $items;

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( $item ): string => sanitize_mime_type( trim( (string) $item ) ),
						$items
					)
				)
			)
		);
	}

	/**
	 * Sanitize newline-separated filename/path patterns.
	 *
	 * @param string $value Raw textarea value.
	 * @return string
	 */
	private static function sanitize_pattern_text( string $value ): string {
		$patterns = self::split_patterns( $value );

		return implode( "\n", $patterns );
	}

	/**
	 * Split textarea patterns into sanitized lines.
	 *
	 * @param string $value Raw textarea value.
	 * @return array<int, string>
	 */
	private static function split_patterns( string $value ): array {
		$lines = preg_split( '/\r\n|\r|\n/', $value );
		$lines = false === $lines ? [] : $lines;

		return array_values(
			array_filter(
				array_map(
					static function ( string $line ): string {
						$line = trim( wp_strip_all_tags( $line ) );
						return substr( $line, 0, 160 );
					},
					$lines
				)
			)
		);
	}
}
