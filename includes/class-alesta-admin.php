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
}
