<?php

/*
Plugin Name: Purple DS HUB - NoSQL
Plugin URI:  https://sprylab.com
Description: NoSQL sync plugin for Purple DS HUB.
Version:     0.9
Author:      sprylab technologies
Author URI:  https://sprylab.com
License:     GPL V3.
License URI: http://www.gnu.org/licenses/
Text Domain: purpledshub-hype
Domain Path: /languages
 */

// exit.
defined( 'ABSPATH' ) || exit;


require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/inc/autoloader-class.php';
\NoSQL\Inc\Autoloader::run();

/**
 * Adds the debug function
 *
 * @param mixed $var The value to be printed.
 */
function debug( $var ) {
	error_log( print_r( $var, true ) );
}

$init_connection = new \NoSQL\Inc\Mongodb\Init_Connection();

$settings = new \NoSQL\Inc\Mongodb\Nosql_Settings();
$settings->init_hooks();


/*
 * Comment out to activate GraphQL Endpoint as a plugin.
$graphql = new \NoSQL\Inc\Server\Graphql_Init();*/

wp_enqueue_script(
	'purple-gutenbergid-script',
	plugins_url( 'inc/mongodb/purpleId.js', __FILE__ ),
	array(),
	filemtime( plugin_dir_path( __FILE__ ) . 'inc/mongodb/purpleId.js' ),
	true
);
