<?php
/**
 * Query result caching (HTML / AJAX payloads) — transients + registry + FIFO (LRU touch).
 *
 * @package Query_Forge
 * @since   1.3.3
 */

namespace Query_Forge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Caches rendered output keyed by logic JSON + pagination + render context — not WP_Query objects.
 *
 * Debugging (wp-config.php):
 * - QUERY_FORGE_DEBUG_CACHE — log cache decisions to PHP error_log (see debug_log()).
 * - QUERY_FORGE_CACHE_FORCE_WRITE — only with QUERY_FORGE_DEBUG_CACHE true: skip bypass so transients
 *   are written even when WP_DEBUG is on or you are logged in as admin (local testing only; remove in production).
 */
class QF_Query_Cache {

	const MAX_ENTRIES     = 200;
	const REGISTRY_OPTION = 'qf_cache_registry';
	const FIFO_OPTION     = 'qf_cache_fifo';

	/**
	 * Memoized result for should_bypass() within one request (avoids duplicate debug_log lines).
	 *
	 * @var bool|null
	 */
	private static $bypass_memo = null;

	/**
	 * Log a line when QUERY_FORGE_DEBUG_CACHE is true.
	 *
	 * @since 1.3.3
	 * @param string $message Message (no trailing newline).
	 */
	public static function debug_log( $message ) {
		if ( ! self::is_debug_enabled() ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional opt-in debug channel.
		error_log( '[Query Forge cache] ' . $message );
	}

	/**
	 * @return bool
	 */
	private static function is_debug_enabled() {
		return defined( 'QUERY_FORGE_DEBUG_CACHE' ) && QUERY_FORGE_DEBUG_CACHE;
	}

	/**
	 * When both QUERY_FORGE_DEBUG_CACHE and QUERY_FORGE_CACHE_FORCE_WRITE are true, bypass rules are skipped so you can verify transients while logged in / WP_DEBUG.
	 *
	 * @return bool
	 */
	private static function is_force_write_for_debug() {
		return self::is_debug_enabled()
			&& defined( 'QUERY_FORGE_CACHE_FORCE_WRITE' )
			&& QUERY_FORGE_CACHE_FORCE_WRITE;
	}

	/**
	 * Read target.cache_duration from schema (seconds). 0 = off.
	 *
	 * @param string $json_data Logic JSON.
	 * @return int>=0
	 */
	public static function get_cache_ttl_from_logic( $json_data ) {
		if ( ! is_string( $json_data ) || '' === $json_data ) {
			return 0;
		}
		$json_data = preg_replace('/u([0-9a-fA-F]{4})/', '\\\\u$1', $json_data);
		$data = json_decode( $json_data, true );
		if ( ! is_array( $data ) || empty( $data['target'] ) || ! is_array( $data['target'] ) ) {
			return 0;
		}
		$raw = $data['target']['cache_duration'] ?? $data['target']['cacheDuration'] ?? 0;
		$ttl = is_numeric( $raw ) ? (int) $raw : 0;
		return max( 0, $ttl );
	}

	/**
	 * Stable md5 for registry / flush (logic only).
	 *
	 * @param string $json_data Logic JSON.
	 * @return string 32-char hex.
	 */
	public static function logic_hash( $json_data ) {
		return md5( is_string( $json_data ) ? $json_data : '' );
	}

	/**
	 * Full transient name (option-safe length).
	 *
	 * @param string $logic_json    Logic JSON.
	 * @param int    $paged         Page.
	 * @param int    $posts_per_page Posts per page.
	 * @param string $context_hash  Hash of render context (attrs, etc.).
	 * @param string $kind          'html' or 'ajax'.
	 * @return string
	 */
	public static function build_cache_key( $logic_json, $paged, $posts_per_page, $context_hash, $kind = 'html' ) {
		$paged = max( 1, absint( $paged ) );
		$ppp   = max( 1, absint( $posts_per_page ) );
		$kind  = ( 'ajax' === $kind ) ? 'ajax' : 'html';
		$base  = $logic_json . '|' . $paged . '|' . $ppp . '|' . $context_hash . '|' . $kind;
		return 'qf_qr_' . md5( $base );
	}

	/**
	 * Whether to skip caching (WP_DEBUG, capability, filter).
	 *
	 * @return bool
	 */
	public static function should_bypass() {
		if ( null !== self::$bypass_memo ) {
			return self::$bypass_memo;
		}
		if ( self::is_force_write_for_debug() ) {
			self::debug_log( 'Bypass skipped: QUERY_FORGE_CACHE_FORCE_WRITE is enabled with QUERY_FORGE_DEBUG_CACHE (testing only).' );
			self::$bypass_memo = false;
			return self::$bypass_memo;
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			self::debug_log( 'Bypass: WP_DEBUG is true (no transients stored). Use incognito / subscriber, or set QUERY_FORGE_CACHE_FORCE_WRITE with QUERY_FORGE_DEBUG_CACHE for local testing.' );
			self::$bypass_memo = true;
			return self::$bypass_memo;
		}
		/**
		 * Bypass query result caching.
		 *
		 * @since 1.3.3
		 * @param bool $bypass Default false.
		 */
		if ( apply_filters( 'query_forge_bypass_query_cache', false ) ) {
			self::debug_log( 'Bypass: filter query_forge_bypass_query_cache returned true.' );
			self::$bypass_memo = true;
			return self::$bypass_memo;
		}
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			self::debug_log( 'Bypass: logged-in user has manage_options (no transients). Test logged out or use QUERY_FORGE_CACHE_FORCE_WRITE + QUERY_FORGE_DEBUG_CACHE on local only.' );
			self::$bypass_memo = true;
			return self::$bypass_memo;
		}
		self::$bypass_memo = false;
		return self::$bypass_memo;
	}

	/**
	 * Get cached string or null.
	 *
	 * @param string $transient_key Key from build_cache_key.
	 * @param string $logic_hash    md5(logic_json) for registry touch.
	 * @return string|null
	 */
	public static function get( $transient_key, $logic_hash ) {
		$val = get_transient( $transient_key );
		if ( false === $val || ! is_string( $val ) ) {
			self::debug_log( 'GET miss transient=' . $transient_key );
			return null;
		}
		self::debug_log( 'GET hit transient=' . $transient_key . ' bytes=' . strlen( $val ) );
		self::fifo_touch( $transient_key, $logic_hash );
		return $val;
	}

	/**
	 * Get cached array (AJAX payload) or null.
	 *
	 * @param string $transient_key Key.
	 * @param string $logic_hash    md5(logic_json).
	 * @return array|null
	 */
	public static function get_array( $transient_key, $logic_hash ) {
		$val = get_transient( $transient_key );
		if ( false === $val || ! is_string( $val ) ) {
			self::debug_log( 'GET_ARRAY miss transient=' . $transient_key );
			return null;
		}
		$data = json_decode( $val, true );
		if ( ! is_array( $data ) ) {
			self::debug_log( 'GET_ARRAY invalid JSON transient=' . $transient_key );
			return null;
		}
		self::debug_log( 'GET_ARRAY hit transient=' . $transient_key );
		self::fifo_touch( $transient_key, $logic_hash );
		return $data;
	}

	/**
	 * Store string payload.
	 *
	 * @param string $transient_key Key.
	 * @param string $logic_hash    md5(logic_json).
	 * @param string $value         HTML or serialized.
	 * @param int    $ttl           Seconds.
	 */
	public static function set( $transient_key, $logic_hash, $value, $ttl ) {
		$ttl = max( 1, absint( $ttl ) );
		set_transient( $transient_key, $value, $ttl );
		self::register_and_fifo( $transient_key, $logic_hash );
		$bytes = is_string( $value ) ? strlen( $value ) : 0;
		self::debug_log( 'SET transient=' . $transient_key . ' ttl=' . $ttl . 's bytes=' . $bytes . ' (option names in DB: _transient_' . $transient_key . ')' );
	}

	/**
	 * Store AJAX success payload as JSON (depth-limited).
	 *
	 * @param string $transient_key Key.
	 * @param string $logic_hash    md5(logic_json).
	 * @param array  $payload       Data array.
	 * @param int    $ttl           Seconds.
	 * @return bool True if stored.
	 */
	public static function set_array( $transient_key, $logic_hash, array $payload, $ttl ) {
		if ( self::array_depth( $payload ) > 10 ) {
			self::debug_log( 'SET_ARRAY skipped: payload too deeply nested transient=' . $transient_key );
			return false;
		}
		$json = wp_json_encode( $payload );
		if ( ! is_string( $json ) ) {
			return false;
		}
		self::set( $transient_key, $logic_hash, $json, $ttl );
		return true;
	}

	/**
	 * Flush all transients registered for a logic hash.
	 *
	 * @param string $logic_hash md5(logic_json).
	 */
	public static function flush_by_logic_hash( $logic_hash ) {
		$logic_hash = preg_replace( '/[^a-f0-9]/', '', (string) $logic_hash );
		if ( strlen( $logic_hash ) !== 32 ) {
			return;
		}
		$registry = self::get_registry();
		if ( empty( $registry[ $logic_hash ] ) || ! is_array( $registry[ $logic_hash ] ) ) {
			return;
		}
		foreach ( $registry[ $logic_hash ] as $key ) {
			delete_transient( $key );
			self::fifo_remove( $key );
		}
		unset( $registry[ $logic_hash ] );
		update_option( self::REGISTRY_OPTION, $registry, false );
	}

	/**
	 * Clear entire cache (all registered keys).
	 */
	public static function flush_all() {
		$registry = self::get_registry();
		foreach ( $registry as $keys ) {
			if ( ! is_array( $keys ) ) {
				continue;
			}
			foreach ( $keys as $key ) {
				delete_transient( $key );
			}
		}
		delete_option( self::REGISTRY_OPTION );
		delete_option( self::FIFO_OPTION );
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	private static function get_registry() {
		$r = get_option( self::REGISTRY_OPTION, [] );
		return is_array( $r ) ? $r : [];
	}

	/**
	 * @return string[]
	 */
	private static function get_fifo() {
		$f = get_option( self::FIFO_OPTION, [] );
		return is_array( $f ) ? $f : [];
	}

	/**
	 * Register key under logic hash and maintain FIFO with LRU dedupe.
	 *
	 * @param string $transient_key Full transient name.
	 * @param string $logic_hash    32-char hex.
	 */
	private static function register_and_fifo( $transient_key, $logic_hash ) {
		$registry = self::get_registry();
		if ( ! isset( $registry[ $logic_hash ] ) || ! is_array( $registry[ $logic_hash ] ) ) {
			$registry[ $logic_hash ] = [];
		}
		if ( ! in_array( $transient_key, $registry[ $logic_hash ], true ) ) {
			$registry[ $logic_hash ][] = $transient_key;
		}
		update_option( self::REGISTRY_OPTION, $registry, false );

		self::fifo_touch( $transient_key, $logic_hash );
	}

	/**
	 * Move key to MRU end; evict LRU if over capacity.
	 *
	 * @param string $transient_key Key.
	 * @param string $logic_hash    For eviction cleanup.
	 */
	private static function fifo_touch( $transient_key, $logic_hash ) {
		$fifo = self::get_fifo();
		$idx  = array_search( $transient_key, $fifo, true );
		if ( false !== $idx ) {
			array_splice( $fifo, $idx, 1 );
		}
		$fifo[] = $transient_key;

		while ( count( $fifo ) > self::MAX_ENTRIES ) {
			$evict = array_shift( $fifo );
			if ( $evict ) {
				delete_transient( $evict );
				self::registry_remove_key( $evict );
			}
		}
		update_option( self::FIFO_OPTION, $fifo, false );
	}

	/**
	 * @param string $transient_key Key.
	 */
	private static function fifo_remove( $transient_key ) {
		$fifo = self::get_fifo();
		$idx  = array_search( $transient_key, $fifo, true );
		if ( false !== $idx ) {
			array_splice( $fifo, $idx, 1 );
			update_option( self::FIFO_OPTION, $fifo, false );
		}
	}

	/**
	 * Remove one key from every registry bucket.
	 *
	 * @param string $transient_key Key.
	 */
	private static function registry_remove_key( $transient_key ) {
		$registry = self::get_registry();
		foreach ( $registry as $h => $keys ) {
			if ( ! is_array( $keys ) ) {
				continue;
			}
			$registry[ $h ] = array_values( array_diff( $keys, [ $transient_key ] ) );
			if ( empty( $registry[ $h ] ) ) {
				unset( $registry[ $h ] );
			}
		}
		update_option( self::REGISTRY_OPTION, $registry, false );
	}

	/**
	 * Max nesting depth of array.
	 *
	 * @param array $arr Array.
	 * @param int   $d   Current depth.
	 * @return int
	 */
	private static function array_depth( array $arr, $d = 1 ) {
		$max = $d;
		foreach ( $arr as $v ) {
			if ( is_array( $v ) ) {
				$max = max( $max, self::array_depth( $v, $d + 1 ) );
			}
		}
		return $max;
	}
}
