<?php
/**
 * Where the Pro add-on's main file is installed.
 *
 * Freemius has to be told the add-on's plugin basename (parallel activation)
 * to know the paid version is active; until it knows, it hides license
 * activation entirely. The add-on normally lives in `ultimate-cursor-pro/`,
 * but WordPress 7.x installs an uploaded zip that has no top-level folder
 * into `<zip name>-<16 random chars>/`, and site owners rename folders too.
 * So the basename is looked up among the active plugins, by file name.
 *
 * @package ultimate-cursor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds the active Pro add-on's plugin basename.
 */
class Ultimate_Cursor_Pro_Locator {

	/**
	 * Where the add-on is installed when its zip is unpacked normally.
	 */
	const DEFAULT_BASENAME = 'ultimate-cursor-pro/ultimate-cursor-pro.php';

	/**
	 * The add-on's main file name, whatever folder it sits in.
	 */
	const MAIN_FILE = 'ultimate-cursor-pro.php';

	/**
	 * Basename of the active add-on, or the default when it is not active.
	 *
	 * @return string
	 */
	public static function find() {
		$active = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		return self::pick( $active );
	}

	/**
	 * Pick the add-on's basename from a list of active plugin basenames.
	 *
	 * The default location wins when it is active; otherwise the first
	 * `<folder>/ultimate-cursor-pro.php` one folder deep.
	 *
	 * @param array $active Active plugin basenames.
	 * @return string
	 */
	public static function pick( $active ) {
		$active = array_values( array_filter( (array) $active, 'is_string' ) );

		if ( in_array( self::DEFAULT_BASENAME, $active, true ) ) {
			return self::DEFAULT_BASENAME;
		}

		foreach ( $active as $basename ) {
			$parts = explode( '/', $basename );
			if ( 2 === count( $parts ) && '' !== $parts[0] && self::MAIN_FILE === $parts[1] ) {
				return $basename;
			}
		}

		return self::DEFAULT_BASENAME;
	}
}
