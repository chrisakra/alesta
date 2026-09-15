<?php
/**
 * Alesta — Admin menu and dashboard page.
 *
 * Sidebar layout mirrors the Alesta AI Free v1.2.7 blueprint. Functional
 * Free modules shipped in this release:
 *   01 SEO           → Sitemap XML
 *   04 Performance   → Gzip, Cache, HTTPS (.htaccess helper)
 *                    → Robots.txt editor
 *                    → Broken links scanner (4xx / 5xx)
 *                    → Scheduled DB Cleaner
 *                    → Google Fonts (GDPR self-hosting)
 *
 * Additional modules will be added block by block in future releases.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alesta_Admin {

	const MENU_SLUG  = 'alesta-ai';
	const CAPABILITY = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'tag_section_headers' ), 900 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_global_menu_assets' ) );
	}

	/**
	 * Sidebar menu icon — Greek letter phi (ϕ) rendered as an inline SVG.
	 */
	public static function menu_icon() {
		$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">';
		$svg .= '<text x="10" y="17" text-anchor="middle" font-family="Georgia,serif" font-size="19" fill="#a0aec0">&#x03C6;</text>';
		$svg .= '</svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Load the sidebar-menu CSS on EVERY admin page (menu is always visible).
	 */
	public static function enqueue_global_menu_assets() {
		wp_enqueue_style(
			'alesta-admin-menu',
			plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/admin-menu.css',
			array(),
			ALESTA_VERSION
		);
	}

	/**
	 * Load the dashboard CSS on Alesta AI admin pages only.
	 */
	public static function enqueue_assets( $hook ) {
		if ( strpos( (string) $hook, 'alesta-ai' ) === false ) {
			return;
		}
		wp_enqueue_style( 'alesta-admin', plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/admin.css', array(), ALESTA_VERSION );
		wp_enqueue_style( 'alesta-pro-promo', plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/pro-promo.css', array(), ALESTA_VERSION );
	}

	/**
	 * Register the top-level Alesta AI menu — dashboard, 2 sections, 2 modules.
	 */
	public static function register_menu() {
		add_menu_page(
			'Alesta AI',
			'Alesta AI',
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_dashboard' ),
			self::menu_icon(),
			30
		);

		// Dashboard (renames the auto-generated first submenu).
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Tableau de bord', 'alesta' ),
			__( 'Tableau de bord', 'alesta' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_dashboard' )
		);

		// Section header 01 SEO — inert via admin-menu.css.
		add_submenu_page(
			self::MENU_SLUG,
			'SEO',
			'SEO &amp; R&eacute;f&eacute;rencement',
			self::CAPABILITY,
			'alesta-ai-seo',
			array( __CLASS__, 'render_section_header' )
		);

		// Sitemap XML — functional module.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Sitemap XML', 'alesta' ),
			'- Sitemap XML',
			self::CAPABILITY,
			'alesta-ai-sitemap',
			function () {
				if ( class_exists( 'Alesta_Admin_Sitemap' ) ) {
					( new Alesta_Admin_Sitemap() )->render_page();
				}
			}
		);

		// Section header 04 Performance — inert via admin-menu.css.
		add_submenu_page(
			self::MENU_SLUG,
			'Performance',
			'Performance &amp; Optimisation',
			self::CAPABILITY,
			'alesta-ai-perf',
			array( __CLASS__, 'render_section_header' )
		);

		// Gzip, Cache, HTTPS (Htaccess) — functional module.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Optimisation Gzip, Cache, HTTPS', 'alesta' ),
			'- Optimisation Gzip, Cache, HTTPS',
			self::CAPABILITY,
			'alesta-ai-cache',
			function () {
				if ( class_exists( 'Alesta_Admin_Htaccess' ) ) {
					( new Alesta_Admin_Htaccess() )->render_page( 'cache' );
				}
			}
		);

		// Robots.txt — functional module.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Robots.txt', 'alesta' ),
			'- Robots.txt',
			self::CAPABILITY,
			'alesta-ai-robots',
			function () {
				if ( class_exists( 'Alesta_Admin_Robots' ) ) {
					( new Alesta_Admin_Robots() )->render_page();
				}
			}
		);

		// Broken links 4xx / 5xx — functional module.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Erreurs 4xx / 5xx', 'alesta' ),
			'- Erreurs 4xx / 5xx',
			self::CAPABILITY,
			'alesta-ai-links',
			function () {
				if ( class_exists( 'Alesta_Admin_Errors' ) ) {
					( new Alesta_Admin_Errors() )->render_page();
				}
			}
		);

		// DB Cleaner — functional module.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Nettoyeur BDD', 'alesta' ),
			'- Nettoyeur BDD planifié',
			self::CAPABILITY,
			'alesta-ai-db-cleaner',
			function () {
				if ( class_exists( 'Alesta_Admin_DB_Cleaner' ) ) {
					( new Alesta_Admin_DB_Cleaner() )->render_page();
				}
			}
		);

		// Google Fonts RGPD — functional module.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Google Fonts RGPD', 'alesta' ),
			'- Optimiseur Google Fonts RGPD',
			self::CAPABILITY,
			'alesta-ai-fonts',
			function () {
				if ( class_exists( 'Alesta_Admin_Fonts' ) ) {
					( new Alesta_Admin_Fonts() )->render_page();
				}
			}
		);

		// Mode maintenance — functional module.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Mode maintenance', 'alesta' ),
			'- Mode maintenance',
			self::CAPABILITY,
			'alesta-ai-maintenance',
			function () {
				if ( class_exists( 'Alesta_Admin_Maintenance' ) ) {
					( new Alesta_Admin_Maintenance() )->render_page();
				}
			}
		);

		// Minify HTML/CSS/JS — functional module.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Minification', 'alesta' ),
			'- Minification HTML/CSS/JS',
			self::CAPABILITY,
			'alesta-ai-minify',
			function () {
				if ( class_exists( 'Alesta_Admin_Minify' ) ) {
					( new Alesta_Admin_Minify() )->render_page();
				}
			}
		);

		// Health Check — functional module.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Santé du site', 'alesta' ),
			'- Santé du site',
			self::CAPABILITY,
			'alesta-ai-health',
			function () {
				if ( class_exists( 'Alesta_Admin_Health' ) ) {
					( new Alesta_Admin_Health() )->render_page();
				}
			}
		);

		// Debug Manager — functional module.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Gestionnaire debug', 'alesta' ),
			'- Gestionnaire debug',
			self::CAPABILITY,
			'alesta-ai-debug',
			function () {
				if ( class_exists( 'Alesta_Admin_Debug' ) ) {
					( new Alesta_Admin_Debug() )->render_page();
				}
			}
		);

		// Section header 05 Sécurité — inert via admin-menu.css.
		add_submenu_page(
			self::MENU_SLUG,
			'Sécurité',
			'S&eacute;curit&eacute; &amp; RGPD',
			self::CAPABILITY,
			'alesta-ai-security-section',
			array( __CLASS__, 'render_section_header' )
		);

		// Bannière RGPD — functional module.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Bannière RGPD', 'alesta' ),
			'- Bannière RGPD (cookies)',
			self::CAPABILITY,
			'alesta-ai-rgpd',
			function () {
				if ( class_exists( 'Alesta_Admin_RGPD' ) ) {
					( new Alesta_Admin_RGPD() )->render_page();
				}
			}
		);

		// Section header 06 Communication — inert via admin-menu.css.
		add_submenu_page(
			self::MENU_SLUG,
			'Communication',
			'Communication &amp; Contact',
			self::CAPABILITY,
			'alesta-ai-communication-section',
			array( __CLASS__, 'render_section_header' )
		);

		// Talk to Me — functional module (contact widget).
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Talk to Me', 'alesta' ),
			'- Talk to Me',
			self::CAPABILITY,
			'alesta-ai-talk-to-me',
			function () {
				if ( class_exists( 'Alesta_Admin_TalkToMe' ) ) {
					( new Alesta_Admin_TalkToMe() )->render_page();
				}
			}
		);

		// Section header 07 Réglages & Diagnostic — inert via admin-menu.css.
		add_submenu_page(
			self::MENU_SLUG,
			'Réglages',
			'R&eacute;glages &amp; Diagnostic',
			self::CAPABILITY,
			'alesta-ai-settings-section',
			array( __CLASS__, 'render_section_header' )
		);

		// Budget tracker — functional module.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Suivi consommation', 'alesta' ),
			'- Budget',
			self::CAPABILITY,
			'alesta-ai-budget',
			function () {
				if ( class_exists( 'Alesta_Admin_Budget' ) ) {
					( new Alesta_Admin_Budget() )->render_page();
				}
			}
		);

		// Section header 03 Médias & Images — visible in sidebar, inert via CSS.
		// Placed near the bottom of the sidebar because the current release has
		// no Free items in this section; only Pro teasers surface it on the
		// dashboard. Header still appears in the sidebar for parity with the
		// full Alesta AI blueprint sitemap.
		add_submenu_page(
			self::MENU_SLUG,
			'Médias',
			'M&eacute;dias &amp; Images',
			self::CAPABILITY,
			'alesta-ai-media-section',
			array( __CLASS__, 'render_section_header' )
		);

		// Section header 09 Réputation & Avis — same rationale (Pro-only for now).
		add_submenu_page(
			self::MENU_SLUG,
			'Réputation',
			'R&eacute;putation &amp; Avis',
			self::CAPABILITY,
			'alesta-ai-reputation-section',
			array( __CLASS__, 'render_section_header' )
		);

		// ══════════════════════════════════════════════════════════════════════
		//  MODULES PRO — pages teaser (Alesta_Promo::render), redirigent vers
		//  https://www.alesta-ai.com/tarifs.html#tarifs pour l'achat.
		//
		//  Tier: 'solo' → module inclus dans les plans Solo ET Pro
		//        'pro'  → module réservé au plan Pro uniquement
		//  Badge & status pill s'adaptent au tier (voir card_pro()).
		// ══════════════════════════════════════════════════════════════════════

		// 01 SEO
		self::register_pro_submenu( 'alesta-ai-pro-meta',         __( 'Title & Meta IA', 'alesta' ),           __( 'Génération en masse des titres SEO et meta-descriptions par Claude, avec audit score par page.', 'alesta' ),         "\xF0\x9F\x93\x9D", 'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-faq',          __( 'FAQ Schema', 'alesta' ),                __( 'Génération de rich snippets Google FAQ via JSON-LD, alimentée par Claude.', 'alesta' ),                              "\xE2\x9D\x93",     'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-schema',       __( 'Données structurées', 'alesta' ),       __( 'Article, Product, Organization, LocalBusiness… Claude détecte le type par page.', 'alesta' ),                        "\xF0\x9F\x8F\x97", 'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-keywords',     __( 'Mots-clés', 'alesta' ),                 __( 'Densité, synonymes LSI, analyse Claude.', 'alesta' ),                                                                "\xF0\x9F\x94\x91", 'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-llms',         __( 'LLMs.txt pour IA', 'alesta' ),          __( 'Fichier de découverte pour ChatGPT, Claude, Gemini, Perplexity.', 'alesta' ),                                        "\xF0\x9F\xA4\x96", 'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-ai-metadata',  __( 'AI Metadata Generator', 'alesta' ),     __( 'Balises meta spécifiques aux crawlers IA.', 'alesta' ),                                                              "\xF0\x9F\xA7\xA0", 'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-duplicates',   __( 'Détecteur contenu dupliqué', 'alesta' ), __( 'Analyse et alertes sur le contenu similaire.', 'alesta' ),                                                          "\xF0\x9F\x93\x8B", 'solo' );

		// 02 Contenu & Rédaction
		self::register_pro_submenu( 'alesta-ai-pro-chatbot',      __( 'Chatbot IA', 'alesta' ),                __( 'Widget conversationnel connecté à Claude Haiku : personnalisable (ton, périmètre, mémoire).', 'alesta' ),           "\xF0\x9F\x92\xAC", 'pro' );
		self::register_pro_submenu( 'alesta-ai-pro-translate',    __( 'Traduction IA', 'alesta' ),     __( 'Traduit articles et pages avec préservation du HTML, via Claude Opus — 5 langues en Solo, 20 langues dès Pro.', 'alesta' ),                    "\xF0\x9F\x8C\x90", 'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-improve',      __( 'Amélioration texte', 'alesta' ),        __( 'Reformuler, simplifier, enrichir vos contenus existants par Claude.', 'alesta' ),                                    "\xE2\x9C\xA8",     'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-summaries',    __( 'Résumés automatiques', 'alesta' ),      __( 'Extraits 2-3 phrases pour tous les articles et pages.', 'alesta' ),                                                  "\xF0\x9F\x93\x83", 'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-editorial',    __( 'Plan éditorial', 'alesta' ),            __( 'Calendrier d\'articles sur 1 à 3 mois via Claude.', 'alesta' ),                                                      "\xF0\x9F\x93\x85", 'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-moderation',   __( 'Modération commentaires', 'alesta' ),   __( 'Filtrage spam + toxicité par Claude (spam / toxique / légitime).', 'alesta' ),                                       "\xF0\x9F\x9B\xA1", 'pro' );

		// 03 Médias & Images
		self::register_pro_submenu( 'alesta-ai-pro-images',       __( 'Traitement images IA', 'alesta' ),      __( 'Titre, légende, alt, description générés par Claude (3 variantes par image).', 'alesta' ),                           "\xF0\x9F\x96\xBC", 'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-filenames',    __( 'Nommage fichiers SEO', 'alesta' ),      __( 'Audit SEO des noms de fichiers, suggestions Claude, renommage BDD ou physique.', 'alesta' ),                         "\xF0\x9F\x92\xBE", 'solo' );

		// 04 Performance & Optimisation
		self::register_pro_submenu( 'alesta-ai-pro-cwv',          __( 'Core Web Vitals', 'alesta' ),           __( 'Mesure LCP / INP / CLS en temps réel via l\'API Google PageSpeed Insights.', 'alesta' ),                             "\xF0\x9F\x93\x88", 'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-scripts',      __( 'Détecteur scripts bloquants', 'alesta' ), __( 'Identification et conseils defer/async par Claude.', 'alesta' ),                                                   "\xF0\x9F\x94\x80", 'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-redirects',    __( 'Redirections 404 auto', 'alesta' ),     __( 'Détection et suggestion IA des pages introuvables.', 'alesta' ),                                                     "\xF0\x9F\x94\x81", 'solo' );

		// 05 Sécurité & RGPD
		self::register_pro_submenu( 'alesta-ai-pro-security',     __( 'Audit sécurité IA', 'alesta' ),         __( 'Scan permissions, fichiers sensibles, versions WP/PHP/plugins, recommandations Claude.', 'alesta' ),                 "\xF0\x9F\x9B\xA1", 'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-activity',     __( 'Journal d\'activité', 'alesta' ),       __( 'Log des actions admin (posts, login, plugins) avec alertes suspectes.', 'alesta' ),                                  "\xF0\x9F\x93\x93", 'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-updates',      __( 'Mises à jour planifiées', 'alesta' ),   __( 'Auto-update WP + plugins selon fenêtre horaire choisie.', 'alesta' ),                                                "\xF0\x9F\x94\x84", 'pro' );
		self::register_pro_submenu( 'alesta-ai-pro-roles',        __( 'Rôles avancés', 'alesta' ),             __( 'Contrôle fin des permissions par rôle et par module.', 'alesta' ),                                                   "\xF0\x9F\x91\xA4", 'pro' );
		self::register_pro_submenu( 'alesta-ai-pro-bruteforce',   __( 'Brute Force', 'alesta' ),               __( 'Protection connexion : rate limiting + blocage IP après N tentatives.', 'alesta' ),                                  "\xF0\x9F\x9B\x91", 'solo' );

		// 08 Rapports client
		self::register_pro_submenu( 'alesta-ai-pro-pdf',          __( 'Rapport PDF SEO', 'alesta' ),           __( 'Génération A4 paysage : score global, meta manquants, breakdown par page — pour envoi client.', 'alesta' ),          "\xF0\x9F\x93\x84", 'pro' );

		// 09 Réputation & Avis
		self::register_pro_submenu( 'alesta-ai-pro-google-reviews', __( 'Avis Google', 'alesta' ),             __( 'Affichage et synchronisation des avis Google Business Profile (4 layouts : carousel, grille, liste, masonry).', 'alesta' ), "\xE2\xAD\x90",     'solo' );
		self::register_pro_submenu( 'alesta-ai-pro-trustpilot',   __( 'Avis Trustpilot', 'alesta' ),           __( 'Trustpilot Business API, sync WP-Cron, 4 layouts shortcode.', 'alesta' ),                                            "\xF0\x9F\x8C\x9F", 'solo' );
	}

	/**
	 * Register a Pro-feature teaser sub-menu. Rendering delegates to
	 * Alesta_Promo::render() which shows a static "Available in Alesta AI Pro"
	 * page with a single external button to alesta-ai.com/tarifs.html.
	 *
	 * All items are hidden from the sidebar (parent slug is a fake string) but
	 * remain reachable via their direct admin.php?page=... URL, so the
	 * dashboard "Découvrir" buttons work without cluttering the left menu.
	 */
	private static function register_pro_submenu( $slug, $name, $desc, $icon, $tier = 'solo' ) {
		add_submenu_page(
			// Fake parent = hides from sidebar but keeps the page reachable
			// via ?page=<slug>. Dashboard "Découvrir" buttons open these URLs.
			'alesta-ai-pro-hidden',
			$name,
			$name,
			self::CAPABILITY,
			$slug,
			function () use ( $name, $desc, $icon, $tier ) {
				if ( class_exists( 'Alesta_Promo' ) ) {
					Alesta_Promo::render( $name, $desc, $icon, $tier );
				}
			}
		);
	}

	/**
	 * Tags every section-header submenu with a CSS class (5th slot of the
	 * $submenu entry, rendered by WP as the <li> class) so admin-menu.css can
	 * target them by class instead of by href suffix. Runs late (priority
	 * 900) so the Pro addon's own headers (registered at 20) are tagged too.
	 */
	public static function tag_section_headers() {
		global $submenu;
		if ( empty( $submenu[ self::MENU_SLUG ] ) ) {
			return;
		}
		$known = array( 'alesta-ai-seo', 'alesta-ai-content', 'alesta-ai-media', 'alesta-ai-perf', 'alesta-ai-automation', 'alesta-ai-reports' );
		foreach ( $submenu[ self::MENU_SLUG ] as $i => $item ) {
			$slug = isset( $item[2] ) ? $item[2] : '';
			if ( substr( $slug, -8 ) === '-section' || in_array( $slug, $known, true ) ) {
				$submenu[ self::MENU_SLUG ][ $i ][4] = 'alesta-menu-section';
			}
		}
	}

	/**
	 * Non-clickable section header placeholder — the CSS makes the link inert;
	 * this fallback body renders only if someone lands on the URL directly.
	 */
	public static function render_section_header() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		echo '<div class="wrap"><p>' . esc_html__( 'Choisissez un module dans la barre latérale.', 'alesta' ) . '</p></div>';
	}

	/**
	 * Render the dashboard — cockpit header, stats and the 2 active sections.
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$total_images  = (int) wp_count_posts( 'attachment' )->inherit;
		$total_posts   = (int) wp_count_posts( 'post' )->publish;
		$total_pages   = (int) wp_count_posts( 'page' )->publish;
		$total_content = $total_posts + $total_pages;
		?>
		<div class="wrap alesta-wrap">

			<!-- Header cockpit -->
			<div style="display:flex;align-items:center;justify-content:space-between;padding:20px 26px;background:linear-gradient(135deg,#1e3a5f 0%,#0f2440 100%);border-radius:10px;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
				<div style="display:flex;align-items:center;gap:14px;">
					<span style="display:inline-flex;align-items:center;justify-content:center;width:50px;height:50px;background:rgba(255,255,255,.1);border-radius:12px;font-family:Georgia,serif;font-size:36px;line-height:1;color:#fff;">&#x03C6;</span>
					<div>
						<h1 style="color:#fff;margin:0;font-size:20px;font-weight:700;letter-spacing:-.3px;"><?php esc_html_e( 'Master AI Dashboard', 'alesta' ); ?></h1>
						<p style="color:#94a3b8;margin:0;font-size:13px;"><?php esc_html_e( 'Cockpit central — santé, performance, sécurité et visibilité IA en un seul écran', 'alesta' ); ?></p>
					</div>
				</div>
				<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
					<span class="alesta-badge" style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;">
						<?php
						/* translators: %s: plugin version, e.g. 1.3.0 */
						echo esc_html( sprintf( __( 'Alesta v%s', 'alesta' ), ALESTA_VERSION ) );
						?>
					</span>
				</div>
			</div>

			<!-- Key figures -->
			<div class="alesta-stats-row">
				<div class="alesta-stat" style="background:#f0f4ff;border:1px solid #e0e7ff;">
					<span style="color:#1e3a5f;"><?php echo esc_html( (string) $total_images ); ?></span>
					<small><?php esc_html_e( 'Images', 'alesta' ); ?></small>
				</div>
				<div class="alesta-stat" style="background:#f0fdf4;border:1px solid #d1fae5;">
					<span style="color:#065f46;"><?php echo esc_html( (string) $total_content ); ?></span>
					<small><?php esc_html_e( 'Pages &amp; articles', 'alesta' ); ?></small>
				</div>
			</div>

			<!-- 01 SEO & Référencement -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">01</span>
					<span class="alesta-section-title"><?php esc_html_e( 'SEO &amp; Référencement', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Optimisation on-page, balises, sitemap, visibilité IA', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php
					self::card_active(
						"\xF0\x9F\x97\xBA", // 🗺
						__( 'Sitemap XML', 'alesta' ),
						__( 'Générez le sitemap.xml et notifiez Google &amp; Bing automatiquement.', 'alesta' ),
						'alesta-ai-sitemap',
						__( 'Ouvrir', 'alesta' )
					);
					self::card_pro(
						"\xF0\x9F\x93\x9D", // 📝
						__( 'Title & Meta IA', 'alesta' ),
						__( 'Génération en masse des titres SEO et meta-descriptions par Claude, avec audit score.', 'alesta' ),
						'alesta-ai-pro-meta',
						'solo'
					);
					self::card_pro(
						"\xE2\x9D\x93", // ❓
						__( 'FAQ Schema', 'alesta' ),
						__( 'Rich snippets Google FAQ via JSON-LD, alimentés par Claude.', 'alesta' ),
						'alesta-ai-pro-faq',
						'solo'
					);
					self::card_pro(
						"\xF0\x9F\x8F\x97", // 🏗
						__( 'Données structurées', 'alesta' ),
						__( 'Article, Product, Organization, LocalBusiness… Claude détecte le type par page.', 'alesta' ),
						'alesta-ai-pro-schema',
						'solo'
					);
					self::card_pro(
						"\xF0\x9F\x94\x91", // 🔑
						__( 'Mots-clés', 'alesta' ),
						__( 'Densité, synonymes LSI, analyse Claude.', 'alesta' ),
						'alesta-ai-pro-keywords',
						'solo'
					);
					self::card_pro(
						"\xF0\x9F\xA4\x96", // 🤖
						__( 'LLMs.txt pour IA', 'alesta' ),
						__( 'Fichier de découverte pour ChatGPT, Claude, Gemini, Perplexity.', 'alesta' ),
						'alesta-ai-pro-llms',
						'solo'
					);
					self::card_pro(
						"\xF0\x9F\xA7\xA0", // 🧠
						__( 'AI Metadata Generator', 'alesta' ),
						__( 'Balises meta spécifiques aux crawlers IA.', 'alesta' ),
						'alesta-ai-pro-ai-metadata',
						'solo'
					);
					self::card_pro(
						"\xF0\x9F\x93\x8B", // 📋
						__( 'Détecteur contenu dupliqué', 'alesta' ),
						__( 'Analyse et alertes sur le contenu similaire.', 'alesta' ),
						'alesta-ai-pro-duplicates',
						'solo'
					);
					?>
				</div>
			</div>

			<!-- 02 Contenu & Rédaction (Pro-only) -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">02</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Contenu &amp; Rédaction', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Automatisation de la rédaction, traduction et support conversationnel via Claude', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php
					self::card_pro(
						"\xF0\x9F\x92\xAC", // 💬
						__( 'Chatbot IA', 'alesta' ),
						__( 'Widget conversationnel Claude Haiku : ton, périmètre et mémoire personnalisables.', 'alesta' ),
						'alesta-ai-pro-chatbot',
						'pro'
					);
					self::card_pro(
						"\xF0\x9F\x8C\x90", // 🌐
						__( 'Traduction IA', 'alesta' ),
						__( 'Traduit articles et pages avec préservation du HTML, via Claude Opus — 5 langues en Solo, 20 langues dès Pro.', 'alesta' ),
						'alesta-ai-pro-translate',
						'solo'
					);
					self::card_pro(
						"\xE2\x9C\xA8", // ✨
						__( 'Amélioration texte', 'alesta' ),
						__( 'Reformuler, simplifier, enrichir vos contenus existants par Claude.', 'alesta' ),
						'alesta-ai-pro-improve',
						'solo'
					);
					self::card_pro(
						"\xF0\x9F\x93\x83", // 📃
						__( 'Résumés automatiques', 'alesta' ),
						__( 'Extraits 2-3 phrases pour tous les articles et pages.', 'alesta' ),
						'alesta-ai-pro-summaries',
						'solo'
					);
					self::card_pro(
						"\xF0\x9F\x93\x85", // 📅
						__( 'Plan éditorial', 'alesta' ),
						__( 'Calendrier d\'articles sur 1 à 3 mois via Claude.', 'alesta' ),
						'alesta-ai-pro-editorial',
						'solo'
					);
					self::card_pro(
						"\xF0\x9F\x9B\xA1", // 🛡
						__( 'Modération commentaires', 'alesta' ),
						__( 'Filtrage spam + toxicité par Claude (spam / toxique / légitime).', 'alesta' ),
						'alesta-ai-pro-moderation',
						'pro'
					);
					?>
				</div>
			</div>

			<!-- 03 Médias & Images (Pro-only) -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">03</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Médias &amp; Images', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Attributs alt, titre, légende et noms de fichiers générés par Claude', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php
					self::card_pro(
						"\xF0\x9F\x96\xBC", // 🖼
						__( 'Traitement images IA', 'alesta' ),
						__( 'Titre, légende, alt, description générés par Claude (3 variantes par image).', 'alesta' ),
						'alesta-ai-pro-images',
						'solo'
					);
					self::card_pro(
						"\xF0\x9F\x92\xBE", // 💾
						__( 'Nommage fichiers SEO', 'alesta' ),
						__( 'Audit SEO des noms de fichiers, suggestions Claude, renommage BDD ou physique.', 'alesta' ),
						'alesta-ai-pro-filenames',
						'solo'
					);
					?>
				</div>
			</div>

			<!-- 04 Performance & Optimisation -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">04</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Performance &amp; Optimisation', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Vitesse, cache, compression et HTTPS via .htaccess', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php
					self::card_active(
						"\xE2\x9A\xA1", // ⚡
						__( 'Optimisation Gzip, Cache, HTTPS', 'alesta' ),
						__( '.htaccess : compression Gzip, cache navigateur, redirection HTTPS.', 'alesta' ),
						'alesta-ai-cache',
						__( 'Ouvrir', 'alesta' )
					);
					self::card_active(
						"\xF0\x9F\xA4\x96", // 🤖
						__( 'Robots.txt', 'alesta' ),
						__( 'Éditez, sauvegardez et restaurez robots.txt directement depuis WordPress.', 'alesta' ),
						'alesta-ai-robots',
						__( 'Ouvrir', 'alesta' )
					);
					self::card_active(
						"\xE2\x9A\xA0", // ⚠
						__( 'Erreurs 4xx / 5xx', 'alesta' ),
						__( 'Scanner de liens internes cassés (404, 500, redirections en boucle).', 'alesta' ),
						'alesta-ai-links',
						__( 'Ouvrir', 'alesta' )
					);
					self::card_active(
						"\xF0\x9F\x97\x91", // 🗑
						__( 'Nettoyeur BDD planifié', 'alesta' ),
						__( 'Nettoyage automatique : révisions, transients, spam, tables orphelines.', 'alesta' ),
						'alesta-ai-db-cleaner',
						__( 'Ouvrir', 'alesta' )
					);
					self::card_active(
						"\xF0\x9F\x87\xAA", // 🇪
						__( 'Google Fonts RGPD', 'alesta' ),
						__( 'Auto-hébergement des polices Google pour la conformité RGPD.', 'alesta' ),
						'alesta-ai-fonts',
						__( 'Ouvrir', 'alesta' )
					);
					self::card_active(
						"\xE2\x9C\x82", // ✂
						__( 'Minification', 'alesta' ),
						__( 'Minifie HTML, CSS et JS pour accélérer le site (avec bypass des page-builders).', 'alesta' ),
						'alesta-ai-minify',
						__( 'Ouvrir', 'alesta' )
					);
					self::card_pro(
						"\xF0\x9F\x93\x88", // 📈
						__( 'Core Web Vitals', 'alesta' ),
						__( 'Mesure LCP / INP / CLS en temps réel via l\'API Google PageSpeed Insights.', 'alesta' ),
						'alesta-ai-pro-cwv',
						'solo'
					);
					self::card_pro(
						"\xF0\x9F\x94\x80", // 🔀
						__( 'Détecteur scripts bloquants', 'alesta' ),
						__( 'Identification et conseils defer/async par Claude.', 'alesta' ),
						'alesta-ai-pro-scripts',
						'solo'
					);
					self::card_pro(
						"\xF0\x9F\x94\x81", // 🔁
						__( 'Redirections 404 auto', 'alesta' ),
						__( 'Détection et suggestion IA des pages introuvables.', 'alesta' ),
						'alesta-ai-pro-redirects',
						'solo'
					);
					?>
				</div>
			</div>

			<!-- 05 Sécurité & RGPD -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">05</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Sécurité &amp; RGPD', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Conformité cookies, consentement visiteurs, protection des données', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php
					self::card_active(
						"\xF0\x9F\x8D\xAA", // 🍪
						__( 'Bannière RGPD', 'alesta' ),
						__( 'Bannière de consentement cookies personnalisable (Accepter / Refuser / Configurer).', 'alesta' ),
						'alesta-ai-rgpd',
						__( 'Ouvrir', 'alesta' )
					);
					self::card_active(
						"\xF0\x9F\xA9\xBA", // 🩺
						__( 'Santé du site', 'alesta' ),
						__( 'Tableau de bord santé WP : PHP, SSL, disque, plugins, MySQL.', 'alesta' ),
						'alesta-ai-health',
						__( 'Ouvrir', 'alesta' )
					);
					self::card_pro(
						"\xF0\x9F\x9B\xA1", // 🛡
						__( 'Audit sécurité IA', 'alesta' ),
						__( 'Scan permissions, fichiers sensibles, versions WP/PHP/plugins + recommandations Claude.', 'alesta' ),
						'alesta-ai-pro-security',
						'solo'
					);
					self::card_pro(
						"\xF0\x9F\x93\x93", // 📓
						__( 'Journal d\'activité', 'alesta' ),
						__( 'Log des actions admin (posts, login, plugins) avec alertes suspectes.', 'alesta' ),
						'alesta-ai-pro-activity',
						'solo'
					);
					self::card_pro(
						"\xF0\x9F\x94\x84", // 🔄
						__( 'Mises à jour planifiées', 'alesta' ),
						__( 'Auto-update WP + plugins selon fenêtre horaire choisie.', 'alesta' ),
						'alesta-ai-pro-updates',
						'pro'
					);
					self::card_pro(
						"\xF0\x9F\x91\xA4", // 👤
						__( 'Rôles avancés', 'alesta' ),
						__( 'Contrôle fin des permissions par rôle et par module.', 'alesta' ),
						'alesta-ai-pro-roles',
						'pro'
					);
					self::card_pro(
						"\xF0\x9F\x9B\x91", // 🛑
						__( 'Brute Force', 'alesta' ),
						__( 'Protection connexion : rate limiting + blocage IP après N tentatives.', 'alesta' ),
						'alesta-ai-pro-bruteforce',
						'solo'
					);
					?>
				</div>
			</div>

			<!-- 06 Communication & Contact -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">06</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Communication &amp; Contact', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Widget contact multi-canaux : WhatsApp, Messenger, téléphone, email, SMS, Telegram, Instagram', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php
					self::card_active(
						"\xF0\x9F\x92\xAC", // 💬
						__( 'Talk to Me', 'alesta' ),
						__( 'Widget flottant contact multi-canaux avec horaires d\'ouverture par jour.', 'alesta' ),
						'alesta-ai-talk-to-me',
						__( 'Ouvrir', 'alesta' )
					);
					?>
				</div>
			</div>

			<!-- 07 Réglages & Diagnostic -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">07</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Réglages &amp; Diagnostic', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Suivi de la consommation et paramètres transverses', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php
					self::card_active(
						"\xF0\x9F\x9A\xA7", // 🚧
						__( 'Mode maintenance', 'alesta' ),
						__( 'Page maintenance / coming-soon avec logo, bouton et compte à rebours.', 'alesta' ),
						'alesta-ai-maintenance',
						__( 'Ouvrir', 'alesta' )
					);
					self::card_active(
						"\xF0\x9F\x90\x9E", // 🐞
						__( 'Gestionnaire debug', 'alesta' ),
						__( 'Toggle WP_DEBUG + lecture / analyse du debug.log en un clic.', 'alesta' ),
						'alesta-ai-debug',
						__( 'Ouvrir', 'alesta' )
					);
					self::card_active(
						"\xF0\x9F\x92\xB0", // 💰
						__( 'Suivi consommation', 'alesta' ),
						__( 'Tableau de suivi mensuel / quotidien des tokens (utilisé par les modules IA du Pro).', 'alesta' ),
						'alesta-ai-budget',
						__( 'Ouvrir', 'alesta' )
					);
					?>
				</div>
			</div>

			<!-- 08 Rapports client (Pro-only) -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">08</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Rapports client', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Export PDF prêt à envoyer à votre client — score global, breakdown par page', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php
					self::card_pro(
						"\xF0\x9F\x93\x84", // 📄
						__( 'Rapport PDF SEO', 'alesta' ),
						__( 'Génération A4 paysage : score global, meta manquants, breakdown par page — envoi client.', 'alesta' ),
						'alesta-ai-pro-pdf',
						'pro'
					);
					?>
				</div>
			</div>

			<!-- 09 Réputation & Avis (Pro-only) -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">09</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Réputation &amp; Avis', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Sync et affichage front-office des avis Google Business Profile et Trustpilot', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php
					self::card_pro(
						"\xE2\xAD\x90", // ⭐
						__( 'Avis Google', 'alesta' ),
						__( 'Affichage et synchronisation des avis Google Business Profile — 4 layouts shortcode.', 'alesta' ),
						'alesta-ai-pro-google-reviews',
						'solo'
					);
					self::card_pro(
						"\xF0\x9F\x8C\x9F", // 🌟
						__( 'Avis Trustpilot', 'alesta' ),
						__( 'Trustpilot Business API, sync WP-Cron, 4 layouts shortcode.', 'alesta' ),
						'alesta-ai-pro-trustpilot',
						'solo'
					);
					?>
				</div>
			</div>

		</div><!-- /wrap -->
		<?php
	}

	/**
	 * Render an "active" module card (linked to its admin page).
	 */
	private static function card_active( $icon, $name, $desc, $slug, $btn_label ) {
		$href = admin_url( 'admin.php?page=' . $slug );
		?>
		<div class="alesta-module-card alesta-module-active">
			<span class="amc-status amc-status-ok"><?php esc_html_e( '✓ Disponible', 'alesta' ); ?></span>
			<div class="amc-icon"><?php echo esc_html( $icon ); ?></div>
			<div class="amc-info">
				<div class="amc-name"><?php echo esc_html( $name ); ?></div>
				<div class="amc-desc"><?php echo esc_html( $desc ); ?></div>
			</div>
			<div class="amc-footer">
				<a href="<?php echo esc_url( $href ); ?>" class="button button-primary"><?php echo esc_html( $btn_label ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a "Pro" module card — same layout as card_active but with:
	 *   • lock icon status pill (amber) instead of green "✓ Disponible"
	 *   • small tier badge inline next to the module name (Solo outlined
	 *     for the entry-level plan, Pro filled for the top plan)
	 *   • button "Découvrir" that opens an internal teaser page which then
	 *     links out to alesta-ai.com/tarifs.html (via Alesta_Promo::render).
	 *
	 * $slug points to the teaser sub-menu registered by register_menu().
	 * $tier is 'solo' (default) or 'pro' — controls badge + status pill.
	 */
	private static function card_pro( $icon, $name, $desc, $slug, $tier = 'solo' ) {
		// Si Alesta AI Pro (addon) est actif, on redirige vers la vraie page Pro
		// (slug sans "-pro-" au milieu) et on rend la carte comme une carte
		// active verte. Sinon, comportement teaser normal (page interne + CTA
		// vers alesta-ai.com/tarifs.html#tarifs).
		$pro_active   = self::is_pro_active();
		$target_slug  = $pro_active ? self::pro_target_slug( $slug ) : $slug;
		$has_real_pro = $pro_active && self::submenu_page_exists( $target_slug );
		$href         = admin_url( 'admin.php?page=' . $target_slug );

		if ( $has_real_pro && ! self::pro_plan_covers( $tier ) ) {
			// Pro actif mais la licence ne couvre pas ce module (ex : Solo sur
			// un module Pro) : carte verrouillée qui ouvre la vraie page Pro
			// (elle affiche l'upsell avec le bon lien d'upgrade).
			$is_pro = ( $tier === 'pro' );
			?>
			<div class="alesta-module-card alesta-module-pro">
				<span class="<?php echo $is_pro ? 'amc-status amc-status-pro' : 'amc-status amc-status-solo'; ?>"><?php echo esc_html( $is_pro ? "ð Pro" : "ð Solo" ); ?></span>
				<div class="amc-icon"><?php echo esc_html( $icon ); ?></div>
				<div class="amc-info">
					<div class="amc-name">
						<?php echo esc_html( $name ); ?>
						<span class="<?php echo $is_pro ? 'alesta-pro-badge alesta-pro-badge--pro' : 'alesta-pro-badge'; ?>"><?php echo esc_html( $is_pro ? 'Pro' : 'Solo' ); ?></span>
					</div>
					<div class="amc-desc"><?php echo esc_html( $desc ); ?></div>
				</div>
				<div class="amc-footer">
					<a href="<?php echo esc_url( $href ); ?>" class="button alesta-btn-pro"><?php esc_html_e( 'Débloquer', 'alesta' ); ?></a>
				</div>
			</div>
			<?php
			return;
		}

		if ( $has_real_pro ) {
			// Pro actif ET la vraie page existe ET plan couvert : carte verte "Actif"
			?>
			<div class="alesta-module-card alesta-module-active">
				<span class="amc-status amc-status-ok"><?php esc_html_e( '✓ Actif Pro', 'alesta' ); ?></span>
				<div class="amc-icon"><?php echo esc_html( $icon ); ?></div>
				<div class="amc-info">
					<div class="amc-name"><?php echo esc_html( $name ); ?></div>
					<div class="amc-desc"><?php echo esc_html( $desc ); ?></div>
				</div>
				<div class="amc-footer">
					<a href="<?php echo esc_url( $href ); ?>" class="button button-primary"><?php esc_html_e( 'Ouvrir', 'alesta' ); ?></a>
				</div>
			</div>
			<?php
			return;
		}

		// Sinon : rendu teaser classique (locked, CTA vers tarifs)
		$is_pro     = ( $tier === 'pro' );
		$badge_cls  = $is_pro ? 'alesta-pro-badge alesta-pro-badge--pro' : 'alesta-pro-badge';
		$badge_lbl  = $is_pro ? 'Pro' : 'Solo';
		$status_cls = $is_pro ? 'amc-status amc-status-pro' : 'amc-status amc-status-solo';
		$status_txt = $is_pro ? "\xF0\x9F\x94\x92 Pro" : "\xF0\x9F\x94\x92 Solo";
		?>
		<div class="alesta-module-card alesta-module-pro">
			<span class="<?php echo esc_attr( $status_cls ); ?>"><?php echo esc_html( $status_txt ); ?></span>
			<div class="amc-icon"><?php echo esc_html( $icon ); ?></div>
			<div class="amc-info">
				<div class="amc-name">
					<?php echo esc_html( $name ); ?>
					<span class="<?php echo esc_attr( $badge_cls ); ?>"><?php echo esc_html( $badge_lbl ); ?></span>
				</div>
				<div class="amc-desc"><?php echo esc_html( $desc ); ?></div>
			</div>
			<div class="amc-footer">
				<a href="<?php echo esc_url( $href ); ?>" class="button alesta-btn-pro"><?php esc_html_e( 'Découvrir', 'alesta' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Detects whether the Alesta AI Pro addon is installed AND active.
	 * The Pro plugin (alesta-pro-open) declares the class Alesta_AI_Admin
	 * which does not exist in the Free plugin alone. Cached per-request.
	 */
	private static function is_pro_active() {
		static $cached = null;
		if ( $cached !== null ) {
			return $cached;
		}
		$cached = class_exists( 'Alesta_AI_Admin', false );
		return $cached;
	}

	/**
	 * Asks the Pro addon whether the current licence plan covers a tier
	 * ('solo' or 'pro'). Alesta_AI_License::can() exists in both Pro
	 * variants (Freemius + Galiance Open) ; if it is missing we assume
	 * everything is covered (legacy behaviour).
	 */
	private static function pro_plan_covers( $tier ) {
		if ( ! class_exists( 'Alesta_AI_License', false ) || ! method_exists( 'Alesta_AI_License', 'can' ) ) {
			return true;
		}
		try {
			return (bool) Alesta_AI_License::can( $tier === 'pro' ? 'pro' : 'solo' );
		} catch ( Throwable $e ) {
			return true;
		}
	}

	/**
	 * Maps a teaser slug (alesta-ai-pro-XXX) to the real Pro page slug
	 * (alesta-ai-XXX). Handles a few naming exceptions from the Pro plugin.
	 */
	private static function pro_target_slug( $teaser_slug ) {
		$exceptions = array(
			'alesta-ai-pro-moderation'     => 'alesta-ai-comments',
			'alesta-ai-pro-security'       => 'alesta-ai-security-audit',
			'alesta-ai-pro-bruteforce'     => 'alesta-ai-brute-force',
			'alesta-ai-pro-google-reviews' => 'alesta-ai-reviews',
			'alesta-ai-pro-trustpilot'     => 'alesta-ai-reviews-trustpilot',
		);
		if ( isset( $exceptions[ $teaser_slug ] ) ) {
			return $exceptions[ $teaser_slug ];
		}
		return str_replace( 'alesta-ai-pro-', 'alesta-ai-', $teaser_slug );
	}

	/**
	 * Tells whether a sub-menu is registered under 'alesta-ai'. Used to
	 * confirm that the Pro plugin actually created the target page before
	 * we send the user to it (avoids sending them to a 404 if the Pro is
	 * present but a specific module is missing).
	 */
	private static function submenu_page_exists( $slug ) {
		global $submenu;
		if ( empty( $submenu[ self::MENU_SLUG ] ) ) {
			return false;
		}
		foreach ( $submenu[ self::MENU_SLUG ] as $item ) {
			if ( isset( $item[2] ) && $item[2] === $slug ) {
				return true;
			}
		}
		return false;
	}
}
