<?php
/**
 * Plugin Name:       Alesta
 * Description:       Minimalist SEO: edit the title and meta description of each page or post, plus Open Graph for a nice social share.
 * Version:           1.1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Alesta AI
 * Author URI:        https://www.alesta-ai.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       alesta
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ALESTA_VERSION', '1.1.1' );
define( 'ALESTA_PLUGIN_FILE', __FILE__ );
define( 'ALESTA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once ALESTA_PLUGIN_DIR . 'includes/class-alesta-meta.php';
require_once ALESTA_PLUGIN_DIR . 'includes/class-alesta-promo.php';
require_once ALESTA_PLUGIN_DIR . 'includes/class-alesta-admin.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-robots-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-admin-robots.php';

add_action( 'plugins_loaded', array( 'Alesta_Meta', 'init' ) );
add_action( 'plugins_loaded', array( 'Alesta_Admin', 'init' ) );
add_action( 'plugins_loaded', function() {
	new Alesta_Robots_Module();
	new Alesta_Admin_Robots();
} );
