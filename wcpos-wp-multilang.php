<?php
/**
 * Plugin Name: WCPOS WP Multilang Integration
 * Description: WP Multilang language filtering for WCPOS, including fast-sync route coverage and per-store language support in WCPOS Pro.
 * Version: 0.1.1
 * Author: kilbot
 * Update URI:  https://github.com/wcpos/wcpos-wp-multilang
 * Requires Plugins: woocommerce, wp-multilang
 * Text Domain: wcpos-wp-multilang
 */

namespace WCPOS\WPMultilang;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSION = '0.1.1';

require_once __DIR__ . '/includes/class-plugin.php';

Plugin::instance();
