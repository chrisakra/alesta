<?php
/**
 * Plugin Name:       Alesta
 * Description:       SEO and technical toolkit: XML sitemap with Google/Bing ping, .htaccess optimization (Gzip, browser cache, HTTPS), robots.txt editor, broken links scanner, scheduled database cleaner, and Google Fonts self-hosting (GDPR). Same product family as Alesta AI.
 * Version:           1.5.0
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

define( 'ALESTA_VERSION', '1.5.0' );
define( 'ALESTA_PLUGIN_FILE', __FILE__ );
define( 'ALESTA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once ALESTA_PLUGIN_DIR . 'includes/class-alesta-promo.php';
require_once ALESTA_PLUGIN_DIR . 'includes/class-alesta-admin.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/seo/class-sitemap-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/seo/class-admin-sitemap.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-htaccess-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-admin-htaccess.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-robots-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-admin-robots.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-errors-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-admin-errors.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-db-cleaner-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-admin-db-cleaner.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-fonts-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-admin-fonts.php';

add_action( 'plugins_loaded', array( 'Alesta_Admin', 'init' ) );
add_action( 'plugins_loaded', function () {
	new Alesta_Sitemap_Module();
	new Alesta_Admin_Sitemap();
	new Alesta_Htaccess_Module();
	new Alesta_Admin_Htaccess();
	new Alesta_Robots_Module();
	new Alesta_Admin_Robots();
	new Alesta_Errors_Module();
	new Alesta_Admin_Errors();
	new Alesta_DB_Cleaner_Module();
	new Alesta_Admin_DB_Cleaner();
	new Alesta_Fonts_Module();
	new Alesta_Admin_Fonts();
} );
