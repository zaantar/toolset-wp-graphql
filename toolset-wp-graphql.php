<?php
/**
 * Plugin Name: Toolset WPGraphQL
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     PLUGIN DESCRIPTION HERE
 * Author: zaantar
 * Author URI: http://zaantar.eu
 * Text Domain: toolset-wp-graphql
 * Domain Path: /languages
 * Version: 0.1.0
 */

// CAREFUL: THIS FILE NEEDS TO BE COMPATIBLE WITH PHP 5.3+

if ( PHP_VERSION_ID < 70100 ) {
	wp_die( 'This plugin requires PHP 7.1 or higher.' );
}

require_once __DIR__ . '/vendor/autoload.php';

$controller = new \OTGS\Toolset\WpGraphQl\Main();
$controller->initialize();
