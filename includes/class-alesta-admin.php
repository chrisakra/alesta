<?php
/**
 * Alesta — Admin menu and dashboard page.
 *
 * Minimal footprint: single "01 SEO & Référencement" section with exactly
 * two cards — SEO Meta Tags (Free, active) and Title & Meta + Audit SEO
 * (Solo promo). No other module or section appears.
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
	 * Register the top-level Alesta AI menu — dashboard + one SEO section +
	 * the single active module and the single Solo promo.
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

		// Section header — inert via admin-menu.css.
		add_submenu_page(
			self::MENU_SLUG,
			'SEO',
			'SEO &amp; R&eacute;f&eacute;rencement',
			self::CAPABILITY,
			'alesta-ai-seo',
			array( __CLASS__, 'render_section_header' )
		);

		// Active module: SEO Meta Tags → native post editor.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'SEO Meta Tags', 'alesta' ),
			'- SEO Meta Tags',
			self::CAPABILITY,
			'edit.php',
			null
		);

		// Solo promo: Title & Meta + Audit SEO.
		add_submenu_page(
			self::MENU_SLUG,
			'Title &amp; Meta',
			'- Title &amp; Meta + Audit SEO <span class="alesta-pro-pill">Solo</span>',
			self::CAPABILITY,
			'alesta-ai-meta',
			function () {
				Alesta_Promo::render(
					'Title & Meta + Audit SEO',
					__( 'Génération en lot des titres et méta-descriptions par Claude, et audit SEO complet avec score.', 'alesta' ),
					"\xF0\x9F\x93\x9D"
				);
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
	 * Render the dashboard — cockpit header, stats, and the single SEO section.
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
						/* translators: %s: plugin version, e.g. 1.1.1 */
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
					<span class="alesta-section-desc"><?php esc_html_e( 'Optimisation on-page, balises, mots-clés', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php
					self::card_active(
						"\xF0\x9F\x93\x9D",
						__( 'SEO Meta Tags', 'alesta' ),
						__( 'Éditez le titre SEO et la méta-description de chaque page ou article. Open Graph et Twitter Card générés automatiquement.', 'alesta' ),
						'edit.php',
						__( 'Modifier les articles', 'alesta' )
					);
					self::card_promo(
						"\xF0\x9F\x93\x9D",
						__( 'Title &amp; Meta + Audit SEO', 'alesta' ),
						__( 'Génération en lot par Claude et audit SEO complet avec score', 'alesta' ),
						'alesta-ai-meta',
						'solo'
					);
					?>
				</div>
			</div>

		</div><!-- /wrap -->
		<?php
	}

	/**
	 * Render an "active" module card (linked to its real page).
	 */
	private static function card_active( $icon, $name, $desc, $target, $btn_label ) {
		$href = ( strpos( $target, '.php' ) !== false )
			? admin_url( $target )
			: admin_url( 'admin.php?page=' . $target );
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
	 * Render a "promo" module card (Solo/Pro pill + button to promo page).
	 */
	private static function card_promo( $icon, $name, $desc, $slug, $tier = 'solo' ) {
		$badge = Alesta_Promo::dashboard_badge( $tier );
		?>
		<div class="alesta-module-card alesta-module-active">
			<span class="amc-status amc-status-ok"><?php esc_html_e( '✓ Disponible', 'alesta' ); ?></span>
			<div class="amc-icon"><?php echo esc_html( $icon ); ?></div>
			<div class="amc-info">
				<div class="amc-name"><?php echo esc_html( $name ); ?> <?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div class="amc-desc"><?php echo esc_html( $desc ); ?></div>
			</div>
			<div class="amc-footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="button button-primary"><?php esc_html_e( 'Découvrir', 'alesta' ); ?></a>
			</div>
		</div>
		<?php
	}
}
