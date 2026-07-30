<?php
/**
 * Alesta — Admin menu and dashboard page.
 *
 * Registers the top-level "Alesta AI" menu (phi ϕ logo) with the full
 * catalogue of sections and modules — active ones open their real UI,
 * Pro ones open a static promotional page pointing to alesta-ai.com.
 *
 * Layout and section list are ported from Alesta AI Free v1.2.7 so that
 * the plugin looks and feels like the same product family.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alesta_Admin {

	const MENU_SLUG = 'alesta-ai';
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
		if ( strpos( (string) $hook, 'alesta-ai' ) === false && strpos( (string) $hook, 'alesta-robots' ) === false ) {
			return;
		}
		wp_enqueue_style( 'alesta-admin', plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/admin.css', array(), ALESTA_VERSION );
		wp_enqueue_style( 'alesta-pro-promo', plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/pro-promo.css', array(), ALESTA_VERSION );
	}

	/**
	 * Register the top-level Alesta AI menu with the full section + module catalogue.
	 * Active modules link to their real UI. Pro modules link to a promo page.
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
		add_submenu_page( self::MENU_SLUG, __( 'Dashboard', 'alesta' ), __( 'Dashboard', 'alesta' ), self::CAPABILITY, self::MENU_SLUG, array( __CLASS__, 'render_dashboard' ) );

		// 01 SEO — section header + active + Pro promos.
		add_submenu_page( self::MENU_SLUG, 'SEO', 'SEO &amp; Search', self::CAPABILITY, 'alesta-ai-seo', array( __CLASS__, 'render_section_header' ) );
		add_submenu_page( self::MENU_SLUG, __( 'SEO Meta Tags', 'alesta' ), '- SEO Meta Tags', self::CAPABILITY, 'edit.php', null );
		add_submenu_page( self::MENU_SLUG, 'Title &amp; Meta', '- Title &amp; Meta + SEO Audit <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-meta', function() { Alesta_Promo::render( 'Title & Meta + SEO Audit', 'Bulk generation of titles and meta descriptions by Claude, plus a full SEO audit with score.', "\xF0\x9F\x93\x9D" ); } );
		add_submenu_page( self::MENU_SLUG, 'FAQ Schema', '- FAQ Schema <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-faq', function() { Alesta_Promo::render( 'FAQ Schema JSON-LD', 'Automatic FAQ structured data for Google rich snippets.', "\xE2\x9D\x93" ); } );
		add_submenu_page( self::MENU_SLUG, 'Keywords', '- Keywords <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-keywords', function() { Alesta_Promo::render( 'Keyword analysis', 'Density, LSI synonyms and keyword research powered by Claude.', "\xF0\x9F\x94\x91" ); } );
		add_submenu_page( self::MENU_SLUG, 'Sitemap XML', '- Sitemap XML <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-sitemap', function() { Alesta_Promo::render( 'XML Sitemap', 'Generate sitemap.xml and notify Google and Bing.', "\xF0\x9F\x97\xBA" ); } );
		add_submenu_page( self::MENU_SLUG, 'LLMs.txt', '- LLMs.txt for AI <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-llms', function() { Alesta_Promo::render( 'LLMs.txt Generator', 'Improve visibility on AI engines: ChatGPT, Claude, Gemini and others.', "\xF0\x9F\xA4\x96" ); } );
		add_submenu_page( self::MENU_SLUG, 'AI Metadata', '- AI Metadata Generator <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-ai-metadata', function() { Alesta_Promo::render( 'AI Metadata Generator', 'Meta tags optimised for AI crawlers.', "\xF0\x9F\xA7\xA0" ); } );
		add_submenu_page( self::MENU_SLUG, 'Duplicates', '- Duplicate content detector <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-duplicates', function() { Alesta_Promo::render( 'Duplicate content detector', 'Detects similar content across your site and alerts you.', "\xF0\x9F\x93\x8B" ); } );
		add_submenu_page( self::MENU_SLUG, 'Schema', '- Structured data <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', self::CAPABILITY, 'alesta-ai-schema', function() { Alesta_Promo::render( 'Structured data', 'Article, Product, Organization, LocalBusiness… Claude picks the right type per page.', "\xF0\x9F\x8F\xB7" ); } );

		// 02 Content.
		add_submenu_page( self::MENU_SLUG, 'Content', 'Content &amp; Writing', self::CAPABILITY, 'alesta-ai-content', array( __CLASS__, 'render_section_header' ) );
		add_submenu_page( self::MENU_SLUG, 'Improve', '- Text improvement <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-improve', function() { Alesta_Promo::render( 'Text improvement', 'Rewrite, simplify or enrich existing content with Claude.', "\xE2\x9C\xA8" ); } );
		add_submenu_page( self::MENU_SLUG, 'Editorial', '- Editorial calendar <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-editorial', function() { Alesta_Promo::render( 'Editorial calendar', 'Content plan generated over 1 to 3 months.', "\xF0\x9F\x93\x85" ); } );
		add_submenu_page( self::MENU_SLUG, 'Summaries', '- Auto summaries <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-summaries', function() { Alesta_Promo::render( 'Automatic summaries', '2-3 sentence excerpts for all your posts and pages.', "\xF0\x9F\x93\x84" ); } );
		add_submenu_page( self::MENU_SLUG, 'Comments', '- Comments <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', self::CAPABILITY, 'alesta-ai-comments', function() { Alesta_Promo::render( 'AI comment moderation', 'Automatic classification by Claude: spam, toxic, legitimate.', "\xF0\x9F\x92\xAC" ); } );
		add_submenu_page( self::MENU_SLUG, 'Tags', '- Auto tags <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-tags', function() { Alesta_Promo::render( 'Automatic tags', 'Categories and tags applied automatically by Claude.', "\xF0\x9F\x8F\xB7" ); } );

		// 03 Media.
		add_submenu_page( self::MENU_SLUG, 'Media', 'Media &amp; Images', self::CAPABILITY, 'alesta-ai-media', array( __CLASS__, 'render_section_header' ) );
		add_submenu_page( self::MENU_SLUG, 'Images', '- Image processing <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-images', function() { Alesta_Promo::render( 'AI image processing', 'Alt text, title, caption and description generated by Claude.', "\xF0\x9F\x96\xBC" ); } );
		add_submenu_page( self::MENU_SLUG, 'Filenames', '- SEO filenames <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-filenames', function() { Alesta_Promo::render( 'SEO filename optimisation', 'SEO audit and renaming of image files.', "\xF0\x9F\x92\xBE" ); } );
		add_submenu_page( self::MENU_SLUG, 'WebP', '- WebP conversion <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-webp', function() { Alesta_Promo::render( 'WebP conversion', 'Automatic image optimisation to WebP.', "\xE2\x9A\xA1" ); } );

		// 04 Performance — active Robots.txt + Pro promos.
		add_submenu_page( self::MENU_SLUG, 'Performance', 'Performance &amp; Technical', self::CAPABILITY, 'alesta-ai-perf', array( __CLASS__, 'render_section_header' ) );
		add_submenu_page( self::MENU_SLUG, __( 'Robots.txt', 'alesta' ), '- Robots.txt', self::CAPABILITY, 'alesta-robots', array( __CLASS__, 'render_robots' ) );
		add_submenu_page( self::MENU_SLUG, 'Htaccess', '- Gzip, Cache, HTTPS <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-cache', function() { Alesta_Promo::render( 'Gzip, Cache, HTTPS', '.htaccess-based compression, browser cache, HTTPS redirect.', "\xE2\x9A\xA1" ); } );
		add_submenu_page( self::MENU_SLUG, 'Broken links', '- Broken links 4xx / 5xx <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-links', function() { Alesta_Promo::render( 'Broken links', 'Scan internal links for 4xx / 5xx errors.', "\xE2\x9A\xA0" ); } );
		add_submenu_page( self::MENU_SLUG, 'DB Cleaner', '- Scheduled DB cleaner <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-db-cleaner', function() { Alesta_Promo::render( 'Scheduled database cleaner', 'Revisions, transients, spam — cleaned automatically.', "\xF0\x9F\x97\x91" ); } );
		add_submenu_page( self::MENU_SLUG, 'Fonts', '- Google Fonts GDPR <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-fonts', function() { Alesta_Promo::render( 'Google Fonts GDPR', 'Self-hosted Google Fonts for GDPR compliance.', "\xF0\x9F\x87\xAA" ); } );
		add_submenu_page( self::MENU_SLUG, 'Maintenance', '- Maintenance mode <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-maintenance', function() { Alesta_Promo::render( 'Maintenance mode', 'Custom maintenance page with admin bypass.', "\xF0\x9F\x9A\xA7" ); } );
		add_submenu_page( self::MENU_SLUG, 'Web Vitals', '- Core Web Vitals <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-cwv', function() { Alesta_Promo::render( 'Core Web Vitals monitor', 'LCP, CLS and INP in real time via the PageSpeed Insights API.', "\xF0\x9F\x93\x8A" ); } );
		add_submenu_page( self::MENU_SLUG, 'Perf Audit', '- Performance audit <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-perf-audit', function() { Alesta_Promo::render( 'Performance audit', 'In-depth analysis with Claude recommendations.', "\xF0\x9F\x94\x8D" ); } );
		add_submenu_page( self::MENU_SLUG, 'Redirects', '- Auto 404 redirects <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-redirects', function() { Alesta_Promo::render( 'Smart 404 redirects', 'AI-suggested redirects for missing pages.', "\xF0\x9F\x94\x84" ); } );

		// 05 AI &amp; Automation.
		add_submenu_page( self::MENU_SLUG, 'AI', 'AI &amp; Automation', self::CAPABILITY, 'alesta-ai-automation', array( __CLASS__, 'render_section_header' ) );
		add_submenu_page( self::MENU_SLUG, 'Translate', '- AI Translation <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', self::CAPABILITY, 'alesta-ai-translate', function() { Alesta_Promo::render( 'AI Translation', '20 languages supported via Claude Opus.', "\xF0\x9F\x8C\x90" ); } );

		// 06 Security.
		add_submenu_page( self::MENU_SLUG, 'Security', 'Security &amp; Compliance', self::CAPABILITY, 'alesta-ai-security-section', array( __CLASS__, 'render_section_header' ) );
		add_submenu_page( self::MENU_SLUG, 'Health Check', '- Health Check Dashboard <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-health', function() { Alesta_Promo::render( 'Health Check Dashboard', 'Detailed view: PHP, SSL, disk, plugins, MySQL.', "\xF0\x9F\x92\x8A" ); } );
		add_submenu_page( self::MENU_SLUG, 'GDPR Banner', '- GDPR Cookie Banner <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-rgpd', function() { Alesta_Promo::render( 'GDPR Cookie Banner', 'Sovereign, dependency-free consent banner.', "\xF0\x9F\x8F\xB3" ); } );
		add_submenu_page( self::MENU_SLUG, 'Security Audit', '- Passive security audit <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-security-audit', function() { Alesta_Promo::render( 'Passive security audit', 'Exposed files, login attempts, permissions.', "\xF0\x9F\x9B\xA1" ); } );
		add_submenu_page( self::MENU_SLUG, 'Activity Log', '- Admin activity log <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-activity', function() { Alesta_Promo::render( 'Admin activity log', 'Complete history of administrator actions.', "\xF0\x9F\x93\x96" ); } );
		add_submenu_page( self::MENU_SLUG, 'Updates', '- Scheduled updates <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', self::CAPABILITY, 'alesta-ai-updates', function() { Alesta_Promo::render( 'Scheduled updates', 'Plugins, themes and WordPress core via WP Cron.', "\xF0\x9F\x93\xA6" ); } );

		// 07 Reports.
		add_submenu_page( self::MENU_SLUG, 'Reports', 'Reports', self::CAPABILITY, 'alesta-ai-reports', array( __CLASS__, 'render_section_header' ) );
		add_submenu_page( self::MENU_SLUG, 'Dashboard SEO', '- SEO Dashboard <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', self::CAPABILITY, 'alesta-ai-dashboard-seo', function() { Alesta_Promo::render( 'Global SEO dashboard', 'SEO score of every page at a glance.', "\xF0\x9F\x93\x88" ); } );
		add_submenu_page( self::MENU_SLUG, 'PDF Report', '- Monthly PDF report <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', self::CAPABILITY, 'alesta-ai-pdf', function() { Alesta_Promo::render( 'Monthly PDF report', 'Automatic summary written by Claude.', "\xF0\x9F\x93\x91" ); } );
		add_submenu_page( self::MENU_SLUG, 'Alerts', '- Automatic alerts <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', self::CAPABILITY, 'alesta-ai-alerts', function() { Alesta_Promo::render( 'Automatic alerts', '7 monitoring types sent by email.', "\xF0\x9F\x94\x94" ); } );

		// 08 Reviews.
		add_submenu_page( self::MENU_SLUG, 'Reviews', 'Reviews', self::CAPABILITY, 'alesta-ai-reviews-section', array( __CLASS__, 'render_section_header' ) );
		add_submenu_page( self::MENU_SLUG, 'Google Reviews', '- Google <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-reviews', function() { Alesta_Promo::render( 'Google Reviews', 'Automatic retrieval of your Google reviews with 4 display templates.', "\xE2\xAD\x90" ); } );
		add_submenu_page( self::MENU_SLUG, 'Trustpilot Reviews', '- Trustpilot <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-reviews-trustpilot', function() { Alesta_Promo::render( 'Trustpilot Reviews', 'Automatic retrieval of your Trustpilot reviews — coming soon.', "\xF0\x9F\x93\x9D" ); } );

		// 09 Communication.
		add_submenu_page( self::MENU_SLUG, 'Communication', 'Communication', self::CAPABILITY, 'alesta-ai-communication-section', array( __CLASS__, 'render_section_header' ) );
		add_submenu_page( self::MENU_SLUG, 'Talk to Me', '- Talk to Me <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-talk-to-me', function() { Alesta_Promo::render( 'Talk to Me', 'Floating multi-channel contact button: WhatsApp, Messenger, phone, email, Telegram, Instagram…', "\xF0\x9F\x92\xAC" ); } );
		add_submenu_page( self::MENU_SLUG, 'Chatbot', '- AI Chatbot <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', self::CAPABILITY, 'alesta-ai-chatbot', function() { Alesta_Promo::render( 'Visitor AI chatbot', 'Front-end widget powered by Claude Haiku.', "\xF0\x9F\x97\xA3" ); } );

		// 10 Settings.
		add_submenu_page( self::MENU_SLUG, 'Settings', 'Settings', self::CAPABILITY, 'alesta-ai-settings-section', array( __CLASS__, 'render_section_header' ) );
		add_submenu_page( self::MENU_SLUG, 'API Config', '- API Configuration <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-settings', function() { Alesta_Promo::render( 'API Configuration', 'Anthropic API key and Claude model selection.', "\xE2\x9A\x99" ); } );
		add_submenu_page( self::MENU_SLUG, 'Debug', '- Debug Manager <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-debug', function() { Alesta_Promo::render( 'Debug Manager', 'Toggle WP_DEBUG, view debug.log.', "\xF0\x9F\x90\x9E" ); } );
		add_submenu_page( self::MENU_SLUG, 'Budget', '- API Budget <span class="alesta-pro-pill">Solo</span>', self::CAPABILITY, 'alesta-ai-budget', function() { Alesta_Promo::render( 'API Budget', 'Monthly token limit for the Anthropic API.', "\xF0\x9F\x92\xB0" ); } );
		add_submenu_page( self::MENU_SLUG, 'Roles', '- Roles &amp; Access <span class="alesta-pro-pill alesta-pro-pill--pro">Pro</span>', self::CAPABILITY, 'alesta-ai-roles', function() { Alesta_Promo::render( 'Roles &amp; Access', 'Per-role permission matrix on every module.', "\xF0\x9F\x91\xA5" ); } );

		// Bottom CTA — "Upgrade to Pro".
		add_submenu_page(
			self::MENU_SLUG,
			'Upgrade to Pro',
			'<span class="alesta-menu-upgrade">\xE2\x9A\xA1 Upgrade to Pro</span>',
			self::CAPABILITY,
			'https://www.alesta-ai.com/tarifs.html',
			null
		);
	}

	/**
	 * Non-clickable section header placeholder (submenu style forces it to look like a heading).
	 * Never actually rendered — the CSS makes the link inert.
	 */
	public static function render_section_header() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		echo '<div class="wrap"><p>' . esc_html__( 'Please pick a module in the sidebar.', 'alesta' ) . '</p></div>';
	}

	/**
	 * Render the Robots.txt admin page.
	 */
	public static function render_robots() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		if ( class_exists( 'Alesta_Admin_Robots' ) ) {
			( new Alesta_Admin_Robots() )->render_page();
		}
	}

	/**
	 * Render the dashboard — cockpit header + all 10 module sections.
	 * Full visual layout ported from Alesta AI Free v1.2.7.
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$total_images  = (int) wp_count_posts( 'attachment' )->inherit;
		$total_posts   = (int) wp_count_posts( 'post' )->publish;
		$total_pages   = (int) wp_count_posts( 'page' )->publish;
		$total_content = $total_posts + $total_pages;

		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
		}
		$home_path     = trailingslashit( get_home_path() );
		$robots_exists = file_exists( $home_path . 'robots.txt' );

		?>
		<div class="wrap alesta-wrap">

			<!-- Header cockpit -->
			<div style="display:flex;align-items:center;justify-content:space-between;padding:20px 26px;background:linear-gradient(135deg,#1e3a5f 0%,#0f2440 100%);border-radius:10px;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
				<div style="display:flex;align-items:center;gap:14px;">
					<span style="display:inline-flex;align-items:center;justify-content:center;width:50px;height:50px;background:rgba(255,255,255,.1);border-radius:12px;font-family:Georgia,serif;font-size:36px;line-height:1;color:#fff;">&#x03C6;</span>
					<div>
						<h1 style="color:#fff;margin:0;font-size:20px;font-weight:700;letter-spacing:-.3px;"><?php esc_html_e( 'Master AI Dashboard', 'alesta' ); ?></h1>
						<p style="color:#94a3b8;margin:0;font-size:13px;"><?php esc_html_e( 'Central cockpit — health, performance, security and AI visibility in a single screen', 'alesta' ); ?></p>
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
					<small><?php esc_html_e( 'Pages &amp; posts', 'alesta' ); ?></small>
				</div>
			</div>

			<!-- 01 SEO -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">01</span>
					<span class="alesta-section-title"><?php esc_html_e( 'SEO &amp; Search visibility', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'On-page optimisation, tags, keywords, structured data, AI visibility', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php self::card_active( "\xF0\x9F\x93\x9D", __( 'SEO Meta Tags', 'alesta' ), __( 'Edit SEO title and meta description per page or post. Open Graph and Twitter Card generated automatically.', 'alesta' ), 'edit.php', __( 'Edit posts', 'alesta' ) ); ?>
					<?php self::card_promo( "\xF0\x9F\x93\x9D", __( 'Title &amp; Meta + SEO Audit', 'alesta' ), __( 'Bulk generation by Claude and SEO score audit', 'alesta' ), 'alesta-ai-meta', 'solo' ); ?>
					<?php self::card_promo( "\xE2\x9D\x93", __( 'FAQ Schema', 'alesta' ), __( 'Rich snippets Google via JSON-LD', 'alesta' ), 'alesta-ai-faq', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x8F\xB7", __( 'Structured data', 'alesta' ), __( 'Article, Product, Organization, LocalBusiness…', 'alesta' ), 'alesta-ai-schema', 'pro' ); ?>
					<?php self::card_promo( "\xF0\x9F\x94\x91", __( 'Keywords', 'alesta' ), __( 'Density, LSI synonyms, Claude analysis', 'alesta' ), 'alesta-ai-keywords', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x97\xBA", __( 'XML Sitemap', 'alesta' ), __( 'Generate sitemap.xml and notify Google &amp; Bing', 'alesta' ), 'alesta-ai-sitemap', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\xA4\x96", __( 'LLMs.txt for AI', 'alesta' ), __( 'Discovery file for ChatGPT, Claude, Gemini…', 'alesta' ), 'alesta-ai-llms', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\xA7\xA0", __( 'AI Metadata Generator', 'alesta' ), __( 'Meta tags dedicated to AI crawlers', 'alesta' ), 'alesta-ai-ai-metadata', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x93\x8B", __( 'Duplicate content detector', 'alesta' ), __( 'Analysis and alerts on similar content', 'alesta' ), 'alesta-ai-duplicates', 'solo' ); ?>
				</div>
			</div>

			<!-- 02 Content -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">02</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Content &amp; Writing', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Creation, improvement and enrichment of content', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php self::card_promo( "\xE2\x9C\xA8", __( 'Text improvement', 'alesta' ), __( 'Rewrite, simplify, enrich with Claude', 'alesta' ), 'alesta-ai-improve', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x93\x84", __( 'Automatic summaries', 'alesta' ), __( '2-3 sentence excerpts for all posts and pages', 'alesta' ), 'alesta-ai-summaries', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x8C\x90", __( 'AI Translation', 'alesta' ), __( '20 languages via Claude Opus', 'alesta' ), 'alesta-ai-translate', 'pro' ); ?>
					<?php self::card_promo( "\xF0\x9F\x93\x85", __( 'Editorial calendar', 'alesta' ), __( 'Content plan over 1 to 3 months', 'alesta' ), 'alesta-ai-editorial', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x92\xAC", __( 'AI comment moderation', 'alesta' ), __( 'Spam, toxic, legitimate — classified by Claude', 'alesta' ), 'alesta-ai-comments', 'pro' ); ?>
					<?php self::card_promo( "\xF0\x9F\x8F\xB7", __( 'Automatic tags', 'alesta' ), __( 'Categories and tags applied by Claude', 'alesta' ), 'alesta-ai-tags', 'solo' ); ?>
				</div>
			</div>

			<!-- 03 Media -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">03</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Media &amp; Images', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Metadata, accessibility and image optimisation', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php self::card_promo( "\xF0\x9F\x96\xBC", __( 'AI image processing', 'alesta' ), __( 'Title, caption, alt, description by Claude', 'alesta' ), 'alesta-ai-images', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x92\xBE", __( 'SEO filenames', 'alesta' ), __( 'SEO audit and renaming of image files', 'alesta' ), 'alesta-ai-filenames', 'solo' ); ?>
					<?php self::card_promo( "\xE2\x9A\xA1", __( 'WebP conversion', 'alesta' ), __( 'Automatic image optimisation to WebP', 'alesta' ), 'alesta-ai-webp', 'solo' ); ?>
				</div>
			</div>

			<!-- 04 Performance -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">04</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Performance &amp; Technical', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Speed, cache, compression, DB, links and redirects', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php self::card_active( "\xF0\x9F\xA4\x96", __( 'Robots.txt', 'alesta' ), __( 'Edit indexing rules for search engine crawlers — with backup, restore and accessibility check.', 'alesta' ), 'admin.php?page=alesta-robots', __( 'Open', 'alesta' ) ); ?>
					<?php self::card_promo( "\xE2\x9A\xA1", __( 'Gzip, Cache, HTTPS', 'alesta' ), __( '.htaccess-based compression, browser cache, HTTPS redirect', 'alesta' ), 'alesta-ai-cache', 'solo' ); ?>
					<?php self::card_promo( "\xE2\x9A\xA0", __( 'Broken links 4xx / 5xx', 'alesta' ), __( 'Scan internal broken links', 'alesta' ), 'alesta-ai-links', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x93\x8A", __( 'Core Web Vitals', 'alesta' ), __( 'LCP, CLS, INP in real time via PageSpeed API', 'alesta' ), 'alesta-ai-cwv', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x97\x91", __( 'Scheduled DB cleaner', 'alesta' ), __( 'Revisions, transients, spam — automatic cleanup', 'alesta' ), 'alesta-ai-db-cleaner', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x94\x84", __( 'Smart 404 redirects', 'alesta' ), __( 'AI-suggested redirects for missing pages', 'alesta' ), 'alesta-ai-redirects', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x87\xAA", __( 'Google Fonts GDPR', 'alesta' ), __( 'Self-hosted fonts for GDPR compliance', 'alesta' ), 'alesta-ai-fonts', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x9A\xA7", __( 'Maintenance mode', 'alesta' ), __( 'Custom maintenance page with admin bypass', 'alesta' ), 'alesta-ai-maintenance', 'solo' ); ?>
				</div>
			</div>

			<!-- 05 AI -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">05</span>
					<span class="alesta-section-title"><?php esc_html_e( 'AI &amp; Automation', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Translation, moderation, tags, chatbot, maintenance', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php self::card_promo( "\xF0\x9F\x8C\x90", __( 'Automatic translation', 'alesta' ), __( '20 languages supported — Claude Opus, adjustable tone', 'alesta' ), 'alesta-ai-translate', 'pro' ); ?>
					<?php self::card_promo( "\xF0\x9F\x92\xAC", __( 'Comment moderation', 'alesta' ), __( 'Spam, toxic, legitimate — classified by Claude', 'alesta' ), 'alesta-ai-comments', 'pro' ); ?>
					<?php self::card_promo( "\xF0\x9F\x8F\xB7", __( 'Automatic tags', 'alesta' ), __( 'Categories and tags applied by Claude', 'alesta' ), 'alesta-ai-tags', 'solo' ); ?>
				</div>
			</div>

			<!-- 06 Security -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">06</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Security &amp; Compliance', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Site health, security audit, GDPR, log and updates', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php self::card_promo( "\xF0\x9F\x92\x8A", __( 'Health Check Dashboard', 'alesta' ), __( 'Detailed view: PHP, SSL, disk, plugins, MySQL', 'alesta' ), 'alesta-ai-health', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x9B\xA1", __( 'Passive security audit', 'alesta' ), __( 'Exposed files, login attempts, permissions', 'alesta' ), 'alesta-ai-security-audit', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x8F\xB3", __( 'GDPR Cookie Banner', 'alesta' ), __( 'GDPR-compliant consent without external dependencies', 'alesta' ), 'alesta-ai-rgpd', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x93\x96", __( 'Admin activity log', 'alesta' ), __( 'History of admin actions with filters, CSV export', 'alesta' ), 'alesta-ai-activity', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x93\xA6", __( 'Scheduled updates', 'alesta' ), __( 'WP Cron: plugins, themes, WordPress core', 'alesta' ), 'alesta-ai-updates', 'pro' ); ?>
				</div>
			</div>

			<!-- 07 Reports -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">07</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Reports &amp; Dashboard', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Global view, statistics and alerts', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php self::card_promo( "\xF0\x9F\x93\x88", __( 'Global SEO dashboard', 'alesta' ), __( 'SEO score of all pages at a glance', 'alesta' ), 'alesta-ai-dashboard-seo', 'pro' ); ?>
					<?php self::card_promo( "\xF0\x9F\x93\x91", __( 'Monthly PDF report', 'alesta' ), __( 'Automatic summary by Claude', 'alesta' ), 'alesta-ai-pdf', 'pro' ); ?>
					<?php self::card_promo( "\xF0\x9F\x94\x94", __( 'Alerts &amp; Notifications', 'alesta' ), __( '7 monitoring types (login, admin, plugins, disk, site down…)', 'alesta' ), 'alesta-ai-alerts', 'pro' ); ?>
				</div>
			</div>

			<!-- 08 Reviews -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">08</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Reviews &amp; Reputation', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Automatic retrieval of Google, Trustpilot and other reviews', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php self::card_promo( "\xE2\xAD\x90", __( 'Google Reviews', 'alesta' ), __( 'Display your Google reviews via shortcode — 4 templates (carousel, grid, list, masonry)', 'alesta' ), 'alesta-ai-reviews', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x93\x9D", __( 'Trustpilot Reviews', 'alesta' ), __( 'Automatic retrieval of Trustpilot reviews — coming soon', 'alesta' ), 'alesta-ai-reviews-trustpilot', 'solo' ); ?>
				</div>
			</div>

			<!-- 09 Communication -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">09</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Communication', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'Floating contact buttons and chatbot to engage visitors', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php self::card_promo( "\xF0\x9F\x92\xAC", __( 'Talk to Me', 'alesta' ), __( 'Floating multi-channel button: WhatsApp, Messenger, phone, email, Telegram, Instagram…', 'alesta' ), 'alesta-ai-talk-to-me', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x97\xA3", __( 'Visitor AI chatbot', 'alesta' ), __( 'Front-end widget powered by Claude Haiku, customisable', 'alesta' ), 'alesta-ai-chatbot', 'pro' ); ?>
				</div>
			</div>

			<!-- 10 Settings -->
			<div class="alesta-section-block">
				<div class="alesta-section-heading">
					<span class="alesta-section-num">10</span>
					<span class="alesta-section-title"><?php esc_html_e( 'Settings &amp; Administration', 'alesta' ); ?></span>
					<span class="alesta-section-desc"><?php esc_html_e( 'API configuration, debug, roles and token budget', 'alesta' ); ?></span>
				</div>
				<div class="alesta-cards">
					<?php self::card_promo( "\xE2\x9A\x99", __( 'API Configuration', 'alesta' ), __( 'Anthropic API key, Claude model', 'alesta' ), 'alesta-ai-settings', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x90\x9E", __( 'Debug Manager', 'alesta' ), __( 'WP_DEBUG toggle, debug.log viewer', 'alesta' ), 'alesta-ai-debug', 'solo' ); ?>
					<?php self::card_promo( "\xF0\x9F\x91\xA5", __( 'Roles &amp; Access', 'alesta' ), __( 'Per-role permission matrix on every module', 'alesta' ), 'alesta-ai-roles', 'pro' ); ?>
					<?php self::card_promo( "\xF0\x9F\x92\xB0", __( 'API Budget', 'alesta' ), __( 'Monthly token limit for the Anthropic API', 'alesta' ), 'alesta-ai-budget', 'solo' ); ?>
				</div>
			</div>

		</div><!-- /wrap -->
		<?php
	}

	/**
	 * Render an "active" module card (linked to its real page).
	 */
	private static function card_active( $icon, $name, $desc, $target, $btn_label ) {
		$href = ( strpos( $target, '.php' ) !== false || strpos( $target, 'admin.php' ) === 0 )
			? admin_url( $target )
			: admin_url( 'admin.php?page=' . $target );
		?>
		<div class="alesta-module-card alesta-module-active">
			<span class="amc-status amc-status-ok"><?php esc_html_e( '✓ Available', 'alesta' ); ?></span>
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
	 * Render a "promo" module card (Pro/Solo pill + button to promo page).
	 */
	private static function card_promo( $icon, $name, $desc, $slug, $tier = 'solo' ) {
		$badge = Alesta_Promo::dashboard_badge( $tier );
		?>
		<div class="alesta-module-card alesta-module-active">
			<span class="amc-status amc-status-ok"><?php esc_html_e( '✓ Available', 'alesta' ); ?></span>
			<div class="amc-icon"><?php echo esc_html( $icon ); ?></div>
			<div class="amc-info">
				<div class="amc-name"><?php echo esc_html( $name ); ?> <?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div class="amc-desc"><?php echo esc_html( $desc ); ?></div>
			</div>
			<div class="amc-footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="button button-primary"><?php esc_html_e( 'Discover', 'alesta' ); ?></a>
			</div>
		</div>
		<?php
	}
}
