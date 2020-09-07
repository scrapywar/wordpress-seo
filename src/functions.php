<?php
/**
 * WPSEO plugin file.
 *
 * @package WPSEO\Internals
 */

if ( ! defined( 'WPSEO_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

use Yoast\WP\SEO\Main;

require_once WPSEO_PATH . 'vendor_prefixed/guzzlehttp/guzzle/src/functions.php';
require_once WPSEO_PATH . 'vendor_prefixed/guzzlehttp/psr7/src/functions_include.php';
require_once WPSEO_PATH . 'vendor_prefixed/guzzlehttp/promises/src/functions_include.php';

/**
 * Retrieves the main instance.
 *
 * @phpcs:disable WordPress.NamingConventions -- Should probably be renamed, but leave for now.
 *
 * @return Main The main instance.
 */
function YoastSEO() {
	// phpcs:enable

	static $main;

	if ( $main === null ) {
		$main = new Main();
		$main->load();
	}

	return $main;
}
