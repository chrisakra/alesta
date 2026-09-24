<?php
/**
 * Plugin Name:       Alesta
 * Description:       SEO and technical toolkit: AI title & meta descriptions + SEO audit, FAQ schema, XML sitemap, .htaccess (Gzip/cache/HTTPS), robots.txt, broken links, DB cleaner, GDPR fonts + banner, maintenance mode, brute-force protection, floating contact widget, health check, debug manager, budget tracker. Same product family as Alesta AI.
 * Version:           1.9.0
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

define( 'ALESTA_VERSION', '1.9.0' );
define( 'ALESTA_PLUGIN_FILE', __FILE__ );
define( 'ALESTA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once ALESTA_PLUGIN_DIR . 'includes/class-alesta-net.php';
require_once ALESTA_PLUGIN_DIR . 'includes/class-alesta-promo.php';
require_once ALESTA_PLUGIN_DIR . 'includes/class-alesta-admin.php';
require_once ALESTA_PLUGIN_DIR . 'includes/class-alesta-review-prompt.php';

// Infrastructure IA (v1.9.0) — coffre de clé API + client Claude. Noms de
// classes uniques (Alesta_Key_Vault / Alesta_API), donc toujours chargés,
// même quand l'addon Alesta AI Pro est actif.
require_once ALESTA_PLUGIN_DIR . 'includes/class-alesta-key-vault.php';
require_once ALESTA_PLUGIN_DIR . 'includes/class-alesta-api.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/settings/class-admin-settings.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/seo/class-sitemap-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/seo/class-admin-sitemap.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/seo/class-meta-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/seo/class-admin-meta.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/seo/class-audit.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/seo/class-admin-audit.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/seo/class-seo-meta-box.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/seo/class-faq-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/seo/class-admin-faq.php';
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
require_once ALESTA_PLUGIN_DIR . 'includes/modules/security/class-brute-force-module.php';
require_once ALESTA_PLUGIN_DIR . 'includes/modules/security/class-admin-brute-force.php';
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

register_activation_hook( ALESTA_PLUGIN_FILE, array( 'Alesta_Review_Prompt', 'on_activation' ) );
add_action( 'admin_init', array( 'Alesta_Review_Prompt', 'handle_fallback' ) );
add_action( 'plugins_loaded', function () { new Alesta_Review_Prompt(); } );

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

// Modules v1.9.0 (Title & Meta IA + Audit SEO, FAQ Schema, Brute Force,
// Configuration clé API). Priorité 20 : WordPress charge l'addon Alesta AI
// Pro (dossier alesta-ai-premium/, alesta-ai-pro/ ou alesta-pro-open/) AVANT
// le Free (alesta/), donc ses classes sont déjà déclarées ici. Quand la Pro
// fournit un module, le Free n'enregistre pas sa propre copie.
add_action( 'plugins_loaded', function () {
	// Page Configuration (clé API Anthropic + modèle) — la Pro a la sienne.
	if ( ! class_exists( 'Alesta_AI_Admin', false ) && ! class_exists( 'Alesta_AI_API', false ) ) {
		new Alesta_Admin_Settings();
	}

	// Title & Meta IA : AJAX + sortie <head> (title, meta, OG, canonical, robots).
	if ( ! class_exists( 'Alesta_AI_Meta_Module', false ) ) {
		new Alesta_Meta_Module();
		new Alesta_Admin_Meta();
	}

	// Audit SEO (onglet de la page Title & Meta).
	if ( ! class_exists( 'Alesta_AI_Admin_Audit', false ) && ! class_exists( 'Alesta_AI_Audit', false ) ) {
		new Alesta_Admin_Audit();
	}

	// Meta box SEO par article / page.
	if ( ! class_exists( 'Alesta_AI_SEO_Meta_Box', false ) ) {
		new Alesta_SEO_Meta_Box();
	}

	// FAQ Schema (JSON-LD FAQPage).
	if ( ! class_exists( 'Alesta_AI_FAQ_Module', false ) ) {
		new Alesta_FAQ_Module();
	}
	if ( is_admin() && ! class_exists( 'Alesta_AI_Admin_FAQ', false ) ) {
		new Alesta_Admin_FAQ();
	}

	// Protection Brute Force (login).
	if ( ! class_exists( 'Alesta_AI_Brute_Force_Module', false ) && ! class_exists( 'Alesta_AI_Admin_Brute_Force', false ) ) {
		new Alesta_Brute_Force_Module();
		new Alesta_Admin_Brute_Force();
	}
}, 20 );
