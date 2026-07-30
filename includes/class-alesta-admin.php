<?php
/**
 * Alesta — Admin menu and dashboard page.
 *
 * Registers the top-level "Alesta AI" menu in the WordPress admin
 * sidebar and renders a lightweight dashboard listing the modules
 * shipped with the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alesta_Admin {

	const MENU_SLUG = 'alesta-ai';
	const CAPABILITY = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Register the top-level Alesta AI menu + module submenus.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Alesta AI', 'alesta' ),
			__( 'Alesta AI', 'alesta' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-star-filled',
			100
		);

		// Rename the auto-generated first submenu (which mirrors the parent slug).
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'alesta' ),
			__( 'Dashboard', 'alesta' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_dashboard' )
		);

		// Robots.txt module submenu.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Robots.txt', 'alesta' ),
			__( 'Robots.txt', 'alesta' ),
			self::CAPABILITY,
			'alesta-robots',
			array( __CLASS__, 'render_robots' )
		);
	}

	/**
	 * Render the Robots.txt admin page (delegates to the module class).
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
	 * Render the dashboard page: intro, active modules, coming soon.
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Alesta AI', 'alesta' ); ?></h1>
			<p style="max-width:720px;font-size:14px;color:#3c434a;">
				<?php esc_html_e( 'Alesta AI helps you optimize your WordPress site with a suite of lightweight, professional modules.', 'alesta' ); ?>
			</p>

			<h2 style="margin-top:24px;"><?php esc_html_e( 'Active modules', 'alesta' ); ?></h2>
			<div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:12px;">
				<div style="flex:0 1 320px;background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:16px;">
					<h3 style="margin:0 0 8px;font-size:15px;">
						<?php esc_html_e( 'SEO Meta Tags', 'alesta' ); ?>
						<span style="display:inline-block;margin-left:6px;padding:2px 8px;font-size:11px;font-weight:600;color:#00733a;background:#e6f6ec;border-radius:10px;vertical-align:middle;">
							<?php esc_html_e( 'Active', 'alesta' ); ?>
						</span>
					</h3>
					<p style="margin:0 0 12px;color:#50575e;font-size:13px;">
						<?php esc_html_e( 'Edit the SEO title and meta description of every page or post. Open Graph and Twitter Card are generated automatically.', 'alesta' ); ?>
					</p>
					<a href="https://wordpress.org/plugins/alesta/" target="_blank" rel="noopener noreferrer" style="font-size:13px;">
						<?php esc_html_e( 'Documentation', 'alesta' ); ?>
					</a>
				</div>
				<div style="flex:0 1 320px;background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:16px;">
					<h3 style="margin:0 0 8px;font-size:15px;">
						<?php esc_html_e( 'Robots.txt', 'alesta' ); ?>
						<span style="display:inline-block;margin-left:6px;padding:2px 8px;font-size:11px;font-weight:600;color:#00733a;background:#e6f6ec;border-radius:10px;vertical-align:middle;">
							<?php esc_html_e( 'Active', 'alesta' ); ?>
						</span>
					</h3>
					<p style="margin:0 0 12px;color:#50575e;font-size:13px;">
						<?php esc_html_e( 'Edit your robots.txt directly from WordPress admin: backup, restore, and check accessibility with a click.', 'alesta' ); ?>
					</p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-robots' ) ); ?>" style="font-size:13px;">
						<?php esc_html_e( 'Open module', 'alesta' ); ?>
					</a>
				</div>
			</div>

			<h2 style="margin-top:32px;color:#8c8f94;"><?php esc_html_e( 'Coming soon', 'alesta' ); ?></h2>
			<p style="color:#8c8f94;font-size:13px;">
				<?php esc_html_e( 'More modules will be added in future versions.', 'alesta' ); ?>
			</p>
		</div>
		<?php
	}
}
