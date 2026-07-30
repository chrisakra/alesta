<?php
/**
 * Robots.txt module — admin UI.
 *
 * Ported from Alesta AI Free v1.2.7. Adapted for the "alesta" WordPress.org
 * slug: text-domain migrated, constants renamed, strings translated to English.
 */
defined( 'ABSPATH' ) || exit;

class Alesta_Admin_Robots {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'alesta-robots' ) === false ) {
			return;
		}

		$ver = ALESTA_VERSION;
		$url = plugin_dir_url( ALESTA_PLUGIN_FILE );
		wp_enqueue_script( 'alesta-robots', $url . 'assets/robots.js', array( 'jquery' ), $ver, true );
		wp_enqueue_style( 'alesta-robots',  $url . 'assets/robots.css', array(), $ver );
		wp_localize_script( 'alesta-robots', 'AlestaConfig', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'alesta_nonce' ),
			'i18n'     => array(
				'load_error'        => __( 'Read error', 'alesta' ),
				'loaded'            => __( 'robots.txt loaded', 'alesta' ),
				'physical_file'     => __( 'Physical file present', 'alesta' ),
				'virtual_file'      => __( 'No file (WordPress virtual)', 'alesta' ),
				'writable'          => __( 'Writable', 'alesta' ),
				'read_only'         => __( 'Read only', 'alesta' ),
				'no_backup'         => __( 'No backup', 'alesta' ),
				'save_btn'          => __( 'Save robots.txt', 'alesta' ),
				'reset_btn'         => __( 'Reset to default', 'alesta' ),
				'backup_btn'        => __( 'Backup', 'alesta' ),
				'restore_btn'       => __( 'Restore', 'alesta' ),
				'ping_btn'          => __( 'Check accessibility', 'alesta' ),
				'confirm_reset'     => __( 'Reset robots.txt to default content? The current content will be backed up.', 'alesta' ),
				'confirm_restore'   => __( 'Restore robots.txt from backup?', 'alesta' ),
				'unknown_error'     => __( 'Unknown error', 'alesta' ),
				'network_error'     => __( 'Network error.', 'alesta' ),
				/* translators: %d: HTTP response code (e.g. 200) */
				'ping_ok'           => __( 'robots.txt is accessible (HTTP %d)', 'alesta' ),
				/* translators: %d: HTTP response code (e.g. 404, 500) */
				'ping_ko'           => __( 'HTTP error %d', 'alesta' ),
				'ping_error_prefix' => __( 'Error:', 'alesta' ),
				'read_only_hint'    => __( 'File is not writable', 'alesta' ),
			),
		) );
	}

	public function render_page(): void {
		?>
		<div class="wrap alesta-wrap" id="alesta-robots-wrap">

			<div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;background:#1e3a5f;border-radius:8px;margin-bottom:20px;">
				<div style="display:flex;align-items:center;gap:12px;">
					<span class="dashicons dashicons-shield" style="font-size:28px;color:#a0aec0;"></span>
					<div>
						<h1 style="color:#fff;margin:0;font-size:18px;">Robots.txt</h1>
						<p style="color:#94a3b8;margin:0;font-size:13px;"><?php esc_html_e( 'Control search engine indexing robots', 'alesta' ); ?></p>
					</div>
				</div>
				<div id="robots-status-bar" style="font-size:12px;color:#94a3b8;"><?php esc_html_e( 'Loading...', 'alesta' ); ?></div>
			</div>

			<div id="robots-global-status" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:20px;display:none;">
				<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
					<div style="display:flex;align-items:center;gap:24px;">
						<div>
							<div style="font-size:11px;color:#9ca3af;margin-bottom:2px;"><?php esc_html_e( 'ROBOTS.TXT FILE', 'alesta' ); ?></div>
							<div id="robots-file-status" style="font-size:13px;font-weight:600;"></div>
						</div>
						<div>
							<div style="font-size:11px;color:#9ca3af;margin-bottom:2px;"><?php esc_html_e( 'WRITABLE', 'alesta' ); ?></div>
							<div id="robots-write-status" style="font-size:13px;font-weight:600;"></div>
						</div>
						<div>
							<div style="font-size:11px;color:#9ca3af;margin-bottom:2px;"><?php esc_html_e( 'LAST BACKUP', 'alesta' ); ?></div>
							<div id="robots-backup-date" style="font-size:13px;color:#374151;"></div>
						</div>
						<div>
							<div style="font-size:11px;color:#9ca3af;margin-bottom:2px;"><?php esc_html_e( 'URL', 'alesta' ); ?></div>
							<div id="robots-url" style="font-size:13px;"></div>
						</div>
					</div>
					<div style="display:flex;gap:8px;">
						<button id="btn-robots-backup" class="button" style="font-size:12px;"><?php esc_html_e( 'Backup', 'alesta' ); ?></button>
						<button id="btn-robots-restore" class="button" style="font-size:12px;color:#991b1b;border-color:#fca5a5;" disabled><?php esc_html_e( 'Restore', 'alesta' ); ?></button>
						<button id="btn-robots-ping" class="button" style="font-size:12px;"><?php esc_html_e( 'Check accessibility', 'alesta' ); ?></button>
					</div>
				</div>
			</div>

			<div id="robots-virtual-notice" style="display:none;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#1e40af;">
				<strong><?php esc_html_e( 'Note:', 'alesta' ); ?></strong> <?php esc_html_e( 'No physical robots.txt file exists. WordPress generates a virtual robots.txt on-the-fly. Saving through this module will create a physical file that takes precedence over the virtual one.', 'alesta' ); ?>
			</div>

			<div id="robots-ping-result" style="display:none;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;margin-bottom:20px;font-size:13px;"></div>

			<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

				<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;">
					<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
						<h3 style="margin:0;font-size:15px;color:#111827;"><?php esc_html_e( 'Robots.txt editor', 'alesta' ); ?></h3>
						<button id="btn-robots-reset" class="button" style="font-size:12px;"><?php esc_html_e( 'Reset to default', 'alesta' ); ?></button>
					</div>
					<p style="font-size:13px;color:#6b7280;margin:0 0 12px;line-height:1.6;">
						<?php esc_html_e( 'Tell search engine robots which pages to crawl or ignore. The "Disallow: /wp-admin/" directive is recommended for all sites.', 'alesta' ); ?>
					</p>
					<textarea id="robots-editor"
						style="width:100%;height:320px;font-family:monospace;font-size:12px;line-height:1.6;padding:12px;border:1px solid #d1d5db;border-radius:6px;resize:vertical;box-sizing:border-box;"
						placeholder="<?php esc_attr_e( 'Loading...', 'alesta' ); ?>"></textarea>
					<div style="display:flex;gap:8px;margin-top:12px;">
						<button id="btn-robots-save" class="button button-primary" style="font-size:13px;"><?php esc_html_e( 'Save robots.txt', 'alesta' ); ?></button>
					</div>
					<div id="robots-feedback" style="margin-top:10px;font-size:13px;display:none;"></div>
				</div>

				<div>
					<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-bottom:16px;">
						<h3 style="margin:0 0 10px;font-size:14px;color:#111827;"><?php esc_html_e( 'Recommended content', 'alesta' ); ?></h3>
						<pre id="robots-default-preview" style="background:#1e2a3a;color:#a8d8a8;padding:14px;border-radius:6px;font-size:11px;overflow:auto;max-height:200px;line-height:1.6;margin:0;white-space:pre-wrap;"></pre>
						<button id="btn-use-default" class="button" style="margin-top:10px;font-size:12px;"><?php esc_html_e( 'Use this content', 'alesta' ); ?></button>
					</div>
					<div style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:16px;">
						<div style="font-size:12px;font-weight:600;color:#713f12;margin-bottom:8px;"><?php esc_html_e( 'USEFUL DIRECTIVES', 'alesta' ); ?></div>
						<ul style="margin:0;padding:0 0 0 16px;font-size:12px;color:#713f12;line-height:1.8;">
							<li><code>User-agent: *</code> — <?php esc_html_e( 'All robots', 'alesta' ); ?></li>
							<li><code>User-agent: Googlebot</code> — <?php esc_html_e( 'Google only', 'alesta' ); ?></li>
							<li><code>Disallow: /page/</code> — <?php esc_html_e( 'Block a directory', 'alesta' ); ?></li>
							<li><code>Allow: /page/home</code> — <?php esc_html_e( 'Allow a URL', 'alesta' ); ?></li>
							<li><code>Sitemap: URL</code> — <?php esc_html_e( 'Specify the sitemap', 'alesta' ); ?></li>
						</ul>
					</div>
				</div>

			</div>
		</div>
		<?php
	}
}
