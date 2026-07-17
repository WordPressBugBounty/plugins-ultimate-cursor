<?php

/**
 * Settings schema — allowlist + per-field sanitization for REST writes.
 *
 * Field lists live in the JSON manifests (classes/cursor-field-manifest.json,
 * classes/background-field-manifest.json) so the allowlist has ONE source of
 * truth. Unknown keys are DROPPED on write; keys already stored in the DB
 * survive (writes merge into the stored option), so legacy data is never
 * destroyed — it just can't be (re)written unless it's in the manifest.
 *
 * The full storage pipeline for a REST write is prepare_for_storage():
 *   1. schema allowlist + typed sanitization (this class)
 *   2. premium input strip (Ultimate_Cursor_License_Gate::strip_premium_input)
 *   3. merge into the stored option
 *   4. data-integrity guard (multiple-mode flag without configs is coerced off)
 *
 * Pure logic by design: no WordPress classes, only core sanitization
 * functions (stubbed in tests/bootstrap.php), so the exact storage path is
 * unit-testable without a WordPress install.
 *
 * @package ultimate-cursor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ultimate_Cursor_Settings_Schema
 */
class Ultimate_Cursor_Settings_Schema {

	/**
	 * Maximum entries accepted in a configurations array.
	 */
	const MAX_CONFIGS = 100;

	/**
	 * Maximum entries accepted in a list field (colors, emojis, …).
	 */
	const MAX_LIST_ITEMS = 50;

	/**
	 * Per-request manifest cache, keyed by group.
	 *
	 * A static property (not a `static` local) so tests can flush it.
	 *
	 * @var array
	 */
	private static $manifests = array();

	/**
	 * Flush the manifest cache (used between unit tests).
	 */
	public static function reset_cache() {
		self::$manifests = array();
	}

	/**
	 * Run the full REST-write storage pipeline.
	 *
	 * @param mixed  $input    Raw `settings` param from the REST request.
	 * @param array  $current  Currently stored option value.
	 * @param string $group    Settings group: 'cursor' or 'background'.
	 * @return array The value to store.
	 */
	public static function prepare_for_storage( $input, $current, $group ) {
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		// 1. Allowlist + typed sanitization. Unknown keys are dropped.
		$clean = self::sanitize_against_schema( $input, self::get_top_level_schema( $group ) );

		// 2. SERVER-SIDE PREMIUM GATE (input only): premium fields are removed
		// from the incoming payload when there is no valid license, so direct
		// API calls can't inject them. Premium data already stored in the DB is
		// intentionally left untouched — it stays dormant and comes back when
		// the license returns. The output paths (admin localize + frontend
		// enqueue in class-assets.php) run the full License Gate sanitize, so
		// dormant values never reach the browser without a license.
		$clean = Ultimate_Cursor_License_Gate::strip_premium_input( $clean, $group );

		// 3. Merge into the stored option (partial writes are supported).
		$merged = array_merge( $current, $clean );

		// 4. Data-integrity guard: "multiple" mode without at least one
		// configuration is a broken state (the frontend would render nothing).
		// It can arise when the input gate strips the configurations while the
		// flag survives the merge. Coerce the flag off so the legacy single
		// top-level config is used instead.
		$flag_key    = ( 'background' === $group ) ? 'enableMultipleBackgrounds' : 'enableMultipleCursors';
		$configs_key = ( 'background' === $group ) ? 'backgroundConfigurations' : 'cursorConfigurations';
		if ( ! empty( $merged[ $flag_key ] ) && empty( $merged[ $configs_key ] ) ) {
			$merged[ $flag_key ] = false;
		}

		return $merged;
	}

	/**
	 * Allowlist schema for the option root: config fields (legacy single
	 * format stores them at the top level) plus the structural top-level keys.
	 *
	 * @param string $group Settings group: 'cursor' or 'background'.
	 * @return array field => sanitizer callback.
	 */
	public static function get_top_level_schema( $group ) {
		$manifest = self::get_manifest( $group );
		$schema   = self::build_schema( array_merge( $manifest['config'], $manifest['topLevel'] ), $group );

		/**
		 * Filter the top-level settings allowlist for a group.
		 *
		 * Add-ons (the pro plugin) can register extra fields (and their
		 * sanitizer callbacks) here so those fields survive the allowlist.
		 *
		 * @param array  $schema field => callable.
		 * @param string $group  Settings group.
		 */
		return apply_filters( "ultimate_cursor_{$group}_settings_schema", $schema, $group );
	}

	/**
	 * Allowlist schema for one configurations[] entry: config fields plus the
	 * per-entry identity keys.
	 *
	 * @param string $group Settings group: 'cursor' or 'background'.
	 * @return array field => sanitizer callback.
	 */
	public static function get_config_schema( $group ) {
		$manifest = self::get_manifest( $group );
		$schema   = self::build_schema( $manifest['config'], $group );

		// Identity keys exist only inside configuration entries — the legacy
		// single format maps `name` to cursorName/backgroundName at the root.
		$schema['id']   = array( __CLASS__, 'sanitize_token' );
		$schema['name'] = 'sanitize_text_field';

		/**
		 * Filter the per-configuration allowlist for a group.
		 *
		 * @param array  $schema field => callable.
		 * @param string $group  Settings group.
		 */
		return apply_filters( "ultimate_cursor_{$group}_config_schema", $schema, $group );
	}

	/**
	 * Apply per-field callbacks; drop keys not in the schema.
	 *
	 * @param mixed $input  Raw input (any non-array becomes array()).
	 * @param array $schema field => callback.
	 * @return array
	 */
	public static function sanitize_against_schema( $input, $schema ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$clean = array();
		foreach ( $schema as $key => $callback ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$clean[ $key ] = call_user_func( $callback, $input[ $key ] );
		}

		return $clean;
	}

	/**
	 * Load and cache a group's manifest.
	 *
	 * @param string $group Settings group: 'cursor' or 'background'.
	 * @return array { topLevel: array, config: array }
	 */
	private static function get_manifest( $group ) {
		if ( isset( self::$manifests[ $group ] ) ) {
			return self::$manifests[ $group ];
		}

		$file     = ( 'background' === $group ) ? 'background-field-manifest.json' : 'cursor-field-manifest.json';
		$path     = __DIR__ . '/' . $file;
		$manifest = array(
			'topLevel' => array(),
			'config'   => array(),
		);

		if ( file_exists( $path ) ) {
			// wp_json_file_decode() was introduced in WP 6.2; fall back for older WP / unit tests.
			if ( function_exists( 'wp_json_file_decode' ) ) {
				$decoded = wp_json_file_decode( $path, array( 'associative' => true ) );
			} else {
				$decoded = json_decode( file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file, WP_Filesystem is not warranted.
			}
			if ( is_array( $decoded ) ) {
				foreach ( array( 'topLevel', 'config' ) as $section ) {
					if ( ! empty( $decoded[ $section ] ) && is_array( $decoded[ $section ] ) ) {
						$manifest[ $section ] = $decoded[ $section ];
					}
				}
			}
		}

		self::$manifests[ $group ] = $manifest;

		return $manifest;
	}

	/**
	 * Map manifest type keywords to sanitizer callbacks.
	 *
	 * @param array  $fields field => type keyword.
	 * @param string $group  Settings group (needed by the 'configs' type).
	 * @return array field => callable.
	 */
	private static function build_schema( $fields, $group ) {
		$schema = array();

		foreach ( $fields as $field => $type ) {
			switch ( $type ) {
				case 'text':
					$schema[ $field ] = 'sanitize_text_field';
					break;
				case 'text-long':
					$schema[ $field ] = array( __CLASS__, 'sanitize_text_long' );
					break;
				case 'bool':
					$schema[ $field ] = 'rest_sanitize_boolean';
					break;
				case 'int':
					$schema[ $field ] = array( __CLASS__, 'sanitize_int' );
					break;
				case 'float':
					$schema[ $field ] = array( __CLASS__, 'sanitize_float' );
					break;
				case 'token':
					$schema[ $field ] = array( __CLASS__, 'sanitize_token' );
					break;
				case 'token-null':
					$schema[ $field ] = array( __CLASS__, 'sanitize_nullable_token' );
					break;
				case 'color':
					$schema[ $field ] = array( __CLASS__, 'sanitize_color' );
					break;
				case 'color-list':
					$schema[ $field ] = array( __CLASS__, 'sanitize_color_list' );
					break;
				case 'text-list':
					$schema[ $field ] = array( __CLASS__, 'sanitize_text_list' );
					break;
				case 'url':
					$schema[ $field ] = 'esc_url_raw';
					break;
				case 'configs':
					$schema[ $field ] = function ( $value ) use ( $group ) {
						return self::sanitize_configs( $value, $group );
					};
					break;
				// Unknown type keyword: leave the field out of the schema
				// (fail closed) rather than guessing a sanitizer.
			}
		}

		return $schema;
	}

	/**
	 * Sanitize a configurations[] array: each entry runs through the
	 * per-config schema; non-array entries are dropped.
	 *
	 * @param mixed  $value Raw configurations value.
	 * @param string $group Settings group.
	 * @return array
	 */
	public static function sanitize_configs( $value, $group ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$schema = self::get_config_schema( $group );
		$clean  = array();
		$count  = 0;

		foreach ( $value as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			if ( ++$count > self::MAX_CONFIGS ) {
				break;
			}
			$clean[] = self::sanitize_against_schema( $entry, $schema );
		}

		return $clean;
	}

	/*
	------------------------------------------------------------------
	 * Field sanitizers
	 * ----------------------------------------------------------------
	 */

	/**
	 * Enum-ish identifier: letters, digits, underscore, dash, dot.
	 * Case is PRESERVED (effect names like 'BubbleCursor' and shape files
	 * like '1.svg' must survive — sanitize_key() would destroy them).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_token( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = preg_replace( '/[^A-Za-z0-9_\.\-]/', '', (string) $value );
		return self::truncate( $value, 64 );
	}

	/**
	 * Token that accepts null/'' as null (e.g. cursorType when deactivated).
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	public static function sanitize_nullable_token( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		return self::sanitize_token( $value );
	}

	/**
	 * Free-form text capped at 2k chars so an admin can't park multi-MB
	 * payloads in selector / pages-list fields.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_text_long( $value ) {
		return self::truncate( sanitize_text_field( (string) $value ), 2000 );
	}

	/**
	 * Integer cast.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitize_int( $value ) {
		return (int) $value;
	}

	/**
	 * Float cast.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	public static function sanitize_float( $value ) {
		return (float) $value;
	}

	/**
	 * Accept hex colors via sanitize_hex_color. For 'transparent' and the
	 * rgba()/named colors the renderers also accept, fall back to a
	 * length-capped sanitized string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_color( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( '' === $value || 'transparent' === $value ) {
			return $value;
		}
		$hex = sanitize_hex_color( $value );
		if ( is_string( $hex ) && '' !== $hex ) {
			return $hex;
		}
		return self::truncate( sanitize_text_field( $value ), 64 );
	}

	/**
	 * List of colors (splash colors, character colors, …).
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public static function sanitize_color_list( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$clean = array();
		foreach ( array_slice( array_values( $value ), 0, self::MAX_LIST_ITEMS ) as $entry ) {
			$color = self::sanitize_color( $entry );
			if ( '' !== $color ) {
				$clean[] = $color;
			}
		}
		return $clean;
	}

	/**
	 * List of short strings (emoji lists, character/word lists).
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public static function sanitize_text_list( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$clean = array();
		foreach ( array_slice( array_values( $value ), 0, self::MAX_LIST_ITEMS ) as $entry ) {
			if ( ! is_scalar( $entry ) ) {
				continue;
			}
			$text = self::truncate( sanitize_text_field( (string) $entry ), 64 );
			if ( '' !== $text ) {
				$clean[] = $text;
			}
		}
		return $clean;
	}

	/**
	 * Multibyte-safe truncation with a plain substr fallback for hosts
	 * without the mbstring extension.
	 *
	 * @param string $value  Already-sanitized string.
	 * @param int    $length Maximum length in characters.
	 * @return string
	 */
	private static function truncate( $value, $length ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}
		return substr( $value, 0, $length );
	}
}
