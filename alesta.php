<?php
/**
 * Plugin Name:       Alesta
 * Description:       SEO and technical toolkit: XML sitemap, .htaccess (Gzip/cache/HTTPS), robots.txt, broken links, DB cleaner, GDPR fonts + banner, maintenance mode, floating contact widget, health check, debug manager, budget tracker. Same product family as Alesta AI.
 * Version:           1.8.1
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

define( 'ALESTA_VERSION', '1.8.1' );
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
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-maintenance-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-admin-maintenance.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/security/class-rgpd-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/security/class-admin-rgpd.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/communication/class-talk-to-me-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/communication/class-admin-talk-to-me.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-admin-health.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-admin-debug.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/settings/class-admin-budget.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-minify-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/performance/class-admin-minify.php';

// "Get Alesta AI Pro" link on the WP Plugins page, next to Deactivate — same
// pattern as Elementor's "Get Elementor Pro". Redirects to the pricing table
// anchor on alesta-ai.com in a new tab.
add_filter(
	'plugin_action_links_' . plugin_basename( ALESTA_PLUGIN_FILE ),
	function ( $links ) {
		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" style="color:#d97706;font-weight:700;">%s</a>',
			esc_url( 'https://www.alesta-ai.com/tarifs.html#tarifs' ),
			esc_html__( 'Get Alesta AI Pro', 'alesta' )
		);
		return $links;
	}
);

add_action( 'plugins_loaded', array( 'Alesta_Admin', 'init' ) );
add_action( 'plugins_loaded', array( 'Alesta_RGPD_Module', 'init' ) );
add_action( 'plugins_loaded', array( 'Alesta_Minify_Module', 'init' ) );
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
	new Alesta_Maintenance_Module();
	new Alesta_Admin_Maintenance();
	new Alesta_Admin_RGPD();
	new Alesta_TalkToMe_Module();
	new Alesta_Admin_TalkToMe();
	new Alesta_Admin_Health();
	new Alesta_Admin_Debug();
	new Alesta_Admin_Budget();
	new Alesta_Admin_Minify();
} );
