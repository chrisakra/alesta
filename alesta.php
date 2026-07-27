<?php
/**
 * Plugin Name:       Alesta
 * Plugin URI:        https://www.alesta-ai.com
 * Description:       Minimalist SEO: edit the title and meta description of each page or post, plus Open Graph for a nice social share.
 * Version:           1.0.1
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

define( 'ALESTA_VERSION', '1.0.1' );
define( 'ALESTA_PLUGIN_FILE', __FILE__ );
define( 'ALESTA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once ALESTA_PLUGIN_DIR . 'includes/class-alesta-meta.php';

add_action( 'plugins_loaded', array( 'Alesta_Meta', 'init' ) );
