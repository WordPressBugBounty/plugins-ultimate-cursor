<?php

/**
 * License Gate — single server-side authority for premium feature enforcement.
 *
 * Every premium-gated setting (cursor AND background) must be registered here.
 * The gate is applied at three choke points:
 *   1. REST writes (class-rest.php) — via strip_premium_input(): premium
 *      fields are removed from the INCOMING payload only. Premium data
 *      already stored in the DB stays dormant across a license lapse and
 *      comes back when the license returns; a free save never destroys it.
 *   2. Frontend enqueues (class-assets.php) — via sanitize(): full strip on
 *      output, so dormant values never reach the public site unlicensed.
 *   3. Admin data output (class-assets.php) — via sanitize(): same full strip.
 *
 * Client-side (React) gating is presentation only and must never be the sole guard.
 *
 * @package ultimate-cursor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ultimate_Cursor_License_Gate
 */
class Ultimate_Cursor_License_Gate {

	/**
	 * Per-request cache of is_premium_active().
	 *
	 * A static property (not a `static` local) so it can be flushed when the
	 * license state changes mid-process — e.g. right after license activation,
	 * or between unit tests.
	 *
	 * @var bool|null
	 */
	private static $premium_active = null;

	/**
	 * Flush the cached is_premium_active() result.
	 */
	public static function reset_premium_active_cache() {
		self::$premium_active = null;
	}

	/**
	 * Check if the user has a valid premium license.
	 *
	 * This is the SINGLE SOURCE OF TRUTH for premium feature gating.
	 * It verifies BOTH conditions:
	 *   1. The pro plugin class is loaded (plugin is active)
	 *   2. Freemius reports a valid license or trial
	 *
	 * @return bool True only if pro plugin is active AND license is valid.
	 */
	public static function is_premium_active() {
		// Cache the result to avoid repeated Freemius calls within a single request.
		if ( self::$premium_active !== null ) {
			return self::$premium_active;
		}
		$result = null;

		/**
		 * Fast-path filter so a verified pro build can short-circuit the
		 * Freemius lookups (and unit tests can flip the gate).
		 *
		 * @param bool $active Whether premium is active. Default false.
		 */
		if ( apply_filters( 'ultimate_cursor_is_premium_active', false ) ) {
			self::$premium_active = true;
			return self::$premium_active;
		}

		// Condition 1: Pro plugin must be active.
		if ( ! class_exists( 'Ultimate_Cursor_Pro' ) ) {
			self::$premium_active = false;
			return self::$premium_active;
		}

		// Condition 2: Freemius must confirm a valid license.
		try {
			$fs = null;

			if ( function_exists( 'ultimate_cursor_pro_fs' ) ) {
				$fs = ultimate_cursor_pro_fs();
			}

			if ( ! $fs && function_exists( 'ultimate_cursor_fs' ) ) {
				$fs = ultimate_cursor_fs();
			}

			if ( ! $fs ) {
				self::$premium_active = false;
				return self::$premium_active;
			}

			// can_use_premium_code() covers both paid licenses and trials.
			$result = (bool) $fs->can_use_premium_code();
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging gated behind WP_DEBUG for diagnostics only.
				error_log( 'Ultimate Cursor: Premium validation error - ' . $e->getMessage() );
			}
			$result = false;
		}

		self::$premium_active = $result;

		return self::$premium_active;
	}

	/**
	 * Premium-only setting keys per settings group.
	 *
	 * These keys are stripped entirely when no valid license exists.
	 *
	 * @param string $group Settings group: 'cursor' or 'background'.
	 * @return array
	 */
	public static function get_premium_keys( $group ) {
		$keys = array(
			'cursor'     => array(
				'enableMultipleCursors',
				'cursorConfigurations',
				// Image cursor hotspot (tip alignment) is a premium feature.
				'imageHotspotPreset',
				'imageHotspotX',
				'imageHotspotY',
				// Circular text background is a premium text-cursor feature
				// (locked in the admin UI; absent values fall back to the
				// frontend component defaults).
				'circularBackground',
				'circularBackgroundSize',
				// Interactive Hover is a fully premium feature: the enable
				// toggle, scale, speed and colors are all premium, alongside
				// the advanced knobs below. Absent values = feature off in the
				// JS runtime (never reverted, so dormant values survive a lapse).
				'hoverEnabled',
				'hoverScale',
				'hoverSpeed',
				'hoverColor',
				'hoverTextColor',
				'hoverMagneticStrength',
				'hoverGlowSize',
				'hoverGlowColor',
				'hoverLabelsEnabled',
				'hoverBlendMode',
				'hoverCustomSelectors',
			),
			'background' => array(
				'enableMultipleBackgrounds',
				'backgroundConfigurations',
				// The "Display Settings" card (position / z-index / opacity)
				// is premium. The frontend renderer falls back to
				// fixed / -1 / 1 when these are absent (renderer.js).
				'position',
				'zIndex',
				'opacity',
			),
		);

		$group_keys = isset( $keys[ $group ] ) ? $keys[ $group ] : array();

		/**
		 * Filter the premium-only setting keys for a settings group.
		 *
		 * @param array  $group_keys Premium-only keys.
		 * @param string $group      Settings group.
		 */
		return apply_filters( 'ultimate_cursor_premium_keys', $group_keys, $group );
	}

	/**
	 * Premium-only setting values per settings group.
	 *
	 * Format: field => array( 'blocked' => array of blocked values, 'default' => safe replacement ).
	 *
	 * @param string $group Settings group: 'cursor' or 'background'.
	 * @return array
	 */
	public static function get_premium_values( $group ) {
		$values = array(
			'cursor'     => array(
				'cursorScope' => array(
					'blocked' => array( 'specific-pages', 'css-selectors', 'html-elements' ),
					'default' => 'entire-website',
				),
				// Shapes 6-25 are premium; 1-5 are free.
				'cursorShape' => array(
					'blocked' => array( '6.svg', '7.svg', '8.svg', '9.svg', '10.svg', '11.svg', '12.svg', '13.svg', '14.svg', '15.svg', '16.svg', '17.svg', '18.svg', '19.svg', '20.svg', '21.svg', '22.svg', '23.svg', '24.svg', '25.svg' ),
					'default' => '1.svg',
				),
			),
			'background' => array(
				'scope' => array(
					'blocked' => array( 'specific-pages', 'css-selector' ),
					'default' => 'entire-website',
				),
			),
		);

		$group_values = isset( $values[ $group ] ) ? $values[ $group ] : array();

		/**
		 * Filter the premium-only setting values for a settings group.
		 *
		 * @param array  $group_values Premium-only value rules.
		 * @param string $group        Settings group.
		 */
		return apply_filters( 'ultimate_cursor_premium_values', $group_values, $group );
	}

	/**
	 * Premium-only per-effect cursor config fields.
	 *
	 * Every animated-effect customization control in the admin UI is
	 * premium-locked (free users run each effect with its built-in
	 * defaults); this map is the server-side mirror of those locks.
	 * Field names were collision-checked against the free UI: text
	 * cursors use `fontSize` (not `size`), BubbleCursor styling and
	 * `imageSize` are free and intentionally absent here.
	 *
	 * Both gates strip the UNION of these fields (not just the payload's
	 * effect) — a payload that omits `effect` must not sneak premium
	 * knobs past the gate. Unset (never revert) so frontend components
	 * fall back to their built-in free defaults and dormant stored pro
	 * values survive a license lapse.
	 *
	 * @return array effect => array of premium field names.
	 */
	public static function get_premium_cursor_effect_fields() {
		$fields = array(
			'CharacterCursor' => array( 'characters', 'speed', 'delay', 'charactersColors' ),
			'RainbowCursor'   => array( 'length', 'size', 'trailSpeed', 'blur', 'colors' ),
			'SnowFlake'       => array( 'snowflakeEmojis', 'snowflakeSize', 'snowflakeCount', 'snowflakeLifespan' ),
			'TrailCursor'     => array( 'trailEmoji', 'trailEmojiSize', 'trailParticleCount', 'trailSpeed', 'trailOpacity' ),
			'SplashCursor'    => array( 'splashRadius', 'splashForce', 'splashColors' ),
			'ClickSpark'      => array( 'sparkSize', 'sparkCount', 'duration', 'sparkColor', 'easing', 'extraScale', 'sparkRadius' ),
			'ClickParticles'  => array( 'particleSpeed', 'particleColor' ),
		);

		/**
		 * Filter the premium-only per-effect cursor config fields.
		 *
		 * @param array $fields effect => array of field names.
		 */
		return apply_filters( 'ultimate_cursor_premium_cursor_effect_fields', $fields );
	}

	/**
	 * Premium-only per-animation-type background config fields.
	 *
	 * When no valid license exists these fields are reset to their free defaults
	 * (mirrors the disabled controls in the React background editor).
	 *
	 * @return array animationType => array( field => free default ).
	 */
	public static function get_premium_background_type_fields() {
		$fields = array(
			'antigravity'  => array(
				'magnetRadius'  => 10,
				'ringRadius'    => 10,
				'fieldStrength' => 10,
				'waveSpeed'     => 0.4,
				'waveAmplitude' => 1,
				'lerpSpeed'     => 0.1,
				'pulseSpeed'    => 3,
				'depthFactor'   => 1,
				'rotationSpeed' => 0,
				'autoAnimate'   => false,
			),
			'light-pillar' => array(
				'intensity'      => 1,
				'rotationSpeed'  => 0.3,
				'glowAmount'     => 0.005,
				'noiseIntensity' => 0.5,
				'interactive'    => false,
				'mixBlendMode'   => 'screen',
				'quality'        => 'high',
			),
		);

		/**
		 * Filter the premium-only per-type background config fields.
		 *
		 * @param array $fields animationType => array( field => free default ).
		 */
		return apply_filters( 'ultimate_cursor_premium_background_type_fields', $fields );
	}

	/**
	 * Drop premium-only fields from an incoming REST payload when no valid
	 * license is active.
	 *
	 * Blocks injection via direct API calls WITHOUT touching premium values
	 * already stored in the DB (those merge through untouched and stay
	 * dormant until the license returns). Blocked-value fields (scopes,
	 * premium shapes) are unset rather than reverted so a free save never
	 * overwrites a stored pro value; premium per-type background knobs are
	 * likewise unset rather than reset.
	 *
	 * @param array  $settings Incoming (already schema-sanitized) payload.
	 * @param string $group    Settings group: 'cursor' or 'background'.
	 * @return array
	 */
	public static function strip_premium_input( $settings, $group = 'cursor' ) {
		if ( ! is_array( $settings ) || self::is_premium_active() ) {
			return $settings;
		}

		// Premium-only keys: never enter storage from an unlicensed request.
		foreach ( self::get_premium_keys( $group ) as $key ) {
			unset( $settings[ $key ] );
		}

		// Premium-only values: unset (not revert) so the stored value survives.
		foreach ( self::get_premium_values( $group ) as $field => $rule ) {
			if ( isset( $settings[ $field ] ) && in_array( $settings[ $field ], $rule['blocked'], true ) ) {
				unset( $settings[ $field ] );
			}
		}

		// Premium per-type background config fields: same unset-not-reset rule.
		// The UNION across all types is stripped (not just the payload's
		// animationType) — otherwise a payload that omits animationType could
		// sneak premium knobs past the gate into a stored config.
		if ( 'background' === $group ) {
			foreach ( self::get_premium_background_type_fields() as $type_fields ) {
				foreach ( array_keys( $type_fields ) as $field ) {
					unset( $settings[ $field ] );
				}
			}
		}

		// Premium per-effect cursor config fields: union unset, same rationale.
		if ( 'cursor' === $group ) {
			foreach ( self::get_premium_cursor_effect_fields() as $effect_fields ) {
				foreach ( $effect_fields as $field ) {
					unset( $settings[ $field ] );
				}
			}
		}

		return $settings;
	}

	/**
	 * Sanitize a settings array by stripping/reverting premium-only data
	 * when no valid license exists.
	 *
	 * OUTPUT gate: used on admin localize + frontend enqueue paths. For REST
	 * input use strip_premium_input() instead — this method reverts values
	 * and would clobber dormant premium data if run against storage.
	 *
	 * @param array  $settings The settings array to sanitize.
	 * @param string $group    Settings group: 'cursor' or 'background'.
	 * @return array Sanitized settings.
	 */
	public static function sanitize( $settings, $group = 'cursor' ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		// If premium is active, allow everything.
		if ( self::is_premium_active() ) {
			return $settings;
		}

		// Strip premium-only keys.
		foreach ( self::get_premium_keys( $group ) as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				unset( $settings[ $key ] );
			}
		}

		// Revert premium-only values to their safe defaults.
		foreach ( self::get_premium_values( $group ) as $field => $rule ) {
			if ( isset( $settings[ $field ] ) && in_array( $settings[ $field ], $rule['blocked'], true ) ) {
				$settings[ $field ] = $rule['default'];
			}
		}

		if ( 'cursor' === $group ) {
			// Force disable multiple cursors.
			$settings['enableMultipleCursors'] = false;

			// Unset premium per-effect fields (union across effects) so the
			// frontend components fall back to their built-in free defaults.
			// Unset — not revert — because the free defaults live in the JS
			// components; duplicating them here would rot.
			foreach ( self::get_premium_cursor_effect_fields() as $effect_fields ) {
				foreach ( $effect_fields as $field ) {
					unset( $settings[ $field ] );
				}
			}
		}

		if ( 'background' === $group ) {
			// Force disable multiple backgrounds.
			$settings['enableMultipleBackgrounds'] = false;

			// Reset premium per-type config fields to their free defaults.
			$type_fields = self::get_premium_background_type_fields();
			$type        = isset( $settings['animationType'] ) ? $settings['animationType'] : '';

			if ( isset( $type_fields[ $type ] ) ) {
				foreach ( $type_fields[ $type ] as $field => $default ) {
					if ( isset( $settings[ $field ] ) ) {
						$settings[ $field ] = $default;
					}
				}
			}
		}

		return $settings;
	}
}
