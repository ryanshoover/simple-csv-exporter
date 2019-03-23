<?php
/**
 * Simple CSV Exporter
 *
 * @package     simple-csv-exporter
 * @author      ryanshoover
 * @license     Proprietary
 *
 * @wordpress-plugin
 * Plugin Name: Simple CSV Exporter
 * Plugin URI:  https://ryan.hoover.ws
 * Description: Export posts from WordPress to CSV files
 * Version:     1.0.0
 * Author:      ryanshoover
 * Author URI:  https://ryan.hoover.ws
 * Text Domain: simple-csv-exporter
 * License:     Proprietary
 */

namespace SimpleCSVExporter;

spl_autoload_register(
	function ( $class ) {
		if ( strncmp( __NAMESPACE__, $class, strlen( __NAMESPACE__ ) ) !== 0 ) {
			return;
		}
		$filename = str_replace( [ '\\', '_' ], [ '/', '-' ], strtolower( substr( $class, strlen( __NAMESPACE__ ) + 1 ) ) ) . '.php';
		$file     = __DIR__ . '/inc/' . preg_replace( '/([\w-]+)\.php/', 'class-$1.php', $filename );
		if ( realpath( $file ) === $file && file_exists( $file ) ) {
			require $file;
		}
	}
);

add_action(
	'plugins_loaded',
	function() {
		if ( is_admin() ) {
			Admin::hooks();
		}
	}
);
