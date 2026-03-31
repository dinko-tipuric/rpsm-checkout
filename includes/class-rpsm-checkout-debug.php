<?php
defined( 'ABSPATH' ) || exit;

/**
 * Centralni debug logger — standardni RPSM pattern.
 *
 * Format: [RPSM Checkout | YYYY-MM-DD HH:MM:SS | LEVEL | Klasa::metoda] Poruka | {"key":"value"}
 * Log:    wp-content/uploads/rpsm-checkout/debug-YYYY-MM.log
 * Rotacija: 5 MB → .old
 */
final class RPSM_Checkout_Debug {

	private const PLUGIN_LABEL = 'RPSM Checkout';
	private const MAX_SIZE     = 5 * 1024 * 1024; // 5 MB

	private static ?bool $is_debug = null;

	/* ── Enable check ──────────────────────────────────────────────── */

	public static function is_enabled(): bool {
		if ( null === self::$is_debug ) {
			self::$is_debug = ( '1' === get_option( RPSM_Checkout_Options::DEBUG_MODE, '0' ) );
		}
		return self::$is_debug;
	}

	public static function reset_cache(): void {
		self::$is_debug = null;
	}

	/* ── Convenience methods ───────────────────────────────────────── */

	public static function info( string $message, array $context = [], string $source = '' ): void {
		self::log( $message, $context, 'INFO', $source );
	}

	public static function debug( string $message, array $context = [], string $source = '' ): void {
		self::log( $message, $context, 'DEBUG', $source );
	}

	public static function warning( string $message, array $context = [], string $source = '' ): void {
		self::log( $message, $context, 'WARNING', $source );
	}

	public static function error( string $message, array $context = [], string $source = '' ): void {
		self::log( $message, $context, 'ERROR', $source );
	}

	/* ── Core logger ───────────────────────────────────────────────── */

	public static function log( string $message, array $context = [], string $level = 'INFO', string $source = '' ): void {

		if ( ! self::is_enabled() ) {
			return;
		}

		$file = self::get_log_path();
		self::maybe_rotate( $file );

		if ( '' === $source ) {
			$source = self::get_caller_info();
		}

		$timestamp = wp_date( 'Y-m-d H:i:s' );
		$entry     = sprintf( '[%s | %s | %s | %s] %s', self::PLUGIN_LABEL, $timestamp, $level, $source, $message );

		if ( ! empty( $context ) ) {
			$entry .= ' | ' . wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		$dir = dirname( $file );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $dir . '/.htaccess', "deny from all\n" );
		}

		file_put_contents( $file, $entry . "\n", FILE_APPEND | LOCK_EX );
	}

	/* ── Read / Clear ──────────────────────────────────────────────── */

	public static function read_log( int $lines = 200 ): string {
		$file = self::get_log_path();
		if ( ! file_exists( $file ) ) {
			return '';
		}
		$all = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		return implode( "\n", array_slice( array_reverse( $all ), 0, $lines ) );
	}

	public static function clear_log(): void {
		$file = self::get_log_path();
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
		$old = $file . '.old';
		if ( file_exists( $old ) ) {
			unlink( $old );
		}
	}

	/* ── Internals ─────────────────────────────────────────────────── */

	private static function get_log_path(): string {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/rpsm-checkout/debug-' . wp_date( 'Y-m' ) . '.log';
	}

	private static function maybe_rotate( string $file ): void {
		if ( file_exists( $file ) && filesize( $file ) > self::MAX_SIZE ) {
			$old = $file . '.old';
			if ( file_exists( $old ) ) {
				unlink( $old );
			}
			rename( $file, $old );
		}
	}

	private static function get_caller_info(): string {
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 4 );
		foreach ( $trace as $frame ) {
			$class = $frame['class'] ?? '';
			if ( $class && $class !== __CLASS__ ) {
				return $class . '::' . ( $frame['function'] ?? '?' );
			}
		}
		return $trace[2]['function'] ?? 'unknown';
	}
}
