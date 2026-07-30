<?php
/**
 * Robots.txt module — backend logic (AJAX handlers).
 *
 * Ported from Alesta AI Free v1.2.7. Adapted for the "alesta" WordPress.org
 * slug: text-domain migrated, constants renamed, strings translated to English.
 */
defined( 'ABSPATH' ) || exit;

class Alesta_Robots_Module {

	const BACKUP_KEY      = 'alesta_robots_backup';
	const BACKUP_DATE_KEY = 'alesta_robots_backup_date';

	/**
	 * Returns the absolute path to robots.txt at the WordPress root.
	 */
	private static function robots_path(): string {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
		}
		return get_home_path() . 'robots.txt';
	}

	public function __construct() {
		add_action( 'wp_ajax_alesta_robots_read',    array( $this, 'ajax_read' ) );
		add_action( 'wp_ajax_alesta_robots_save',    array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_alesta_robots_reset',   array( $this, 'ajax_reset' ) );
		add_action( 'wp_ajax_alesta_robots_backup',  array( $this, 'ajax_backup' ) );
		add_action( 'wp_ajax_alesta_robots_restore', array( $this, 'ajax_restore' ) );
		add_action( 'wp_ajax_alesta_robots_ping',    array( $this, 'ajax_ping' ) );
	}

	// =========================================================================
	// HELPERS
	// =========================================================================

	private function can_write(): bool {
		$path = self::robots_path();
		if ( ! file_exists( $path ) ) {
			return is_writable( get_home_path() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		}
		return is_writable( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
	}

	private function read_robots(): string {
		if ( ! file_exists( self::robots_path() ) ) {
			return '';
		}
		$content = file_get_contents( self::robots_path() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return $content !== false ? $content : '';
	}

	private function make_backup(): void {
		$content = $this->read_robots();
		if ( empty( $content ) ) {
			return;
		}
		update_option( self::BACKUP_KEY, $content );
		update_option( self::BACKUP_DATE_KEY, current_time( 'mysql' ) );
	}

	private function default_content(): string {
		$sitemap_url = home_url( '/sitemap.xml' );
		return "User-agent: *\n"
			. "Disallow: /wp-admin/\n"
			. "Allow: /wp-admin/admin-ajax.php\n"
			. "\n"
			. 'Sitemap: ' . $sitemap_url . "\n";
	}

	private function is_virtual_robots(): bool {
		// WordPress generates a virtual robots.txt if no physical file exists.
		return ! file_exists( self::robots_path() );
	}

	// =========================================================================
	// AJAX : Read current state
	// =========================================================================
	public function ajax_read(): void {
		check_ajax_referer( 'alesta_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		wp_send_json_success( array(
			'exists'      => file_exists( self::robots_path() ),
			'can_write'   => $this->can_write(),
			'is_virtual'  => $this->is_virtual_robots(),
			'content'     => $this->read_robots(),
			'default'     => $this->default_content(),
			'backup_date' => get_option( self::BACKUP_DATE_KEY, '' ),
			'has_backup'  => ! empty( get_option( self::BACKUP_KEY, '' ) ),
			'url'         => home_url( '/robots.txt' ),
		) );
	}

	// =========================================================================
	// AJAX : Save content
	// =========================================================================
	public function ajax_save(): void {
		check_ajax_referer( 'alesta_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		if ( ! $this->can_write() ) {
			wp_send_json_error( array( 'message' => __( 'The robots.txt file is not writable.', 'alesta' ) ) );
		}

		$content = isset( $_POST['content'] ) ? wp_strip_all_tags( wp_unslash( $_POST['content'] ) ) : '';

		$this->make_backup();
		$result = file_put_contents( self::robots_path(), $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to write robots.txt.', 'alesta' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'robots.txt saved successfully.', 'alesta' ) ) );
	}

	// =========================================================================
	// AJAX : Reset to default content
	// =========================================================================
	public function ajax_reset(): void {
		check_ajax_referer( 'alesta_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		if ( ! $this->can_write() ) {
			wp_send_json_error( array( 'message' => __( 'The robots.txt file is not writable.', 'alesta' ) ) );
		}

		$this->make_backup();
		$content = $this->default_content();
		file_put_contents( self::robots_path(), $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents

		wp_send_json_success( array(
			'message' => __( 'robots.txt reset to default values.', 'alesta' ),
			'content' => $content,
		) );
	}

	// =========================================================================
	// AJAX : Manual backup
	// =========================================================================
	public function ajax_backup(): void {
		check_ajax_referer( 'alesta_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$this->make_backup();
		wp_send_json_success( array(
			'message' => __( 'Backup completed.', 'alesta' ),
			'date'    => get_option( self::BACKUP_DATE_KEY ),
		) );
	}

	// =========================================================================
	// AJAX : Restore backup
	// =========================================================================
	public function ajax_restore(): void {
		check_ajax_referer( 'alesta_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$backup = get_option( self::BACKUP_KEY, '' );
		if ( empty( $backup ) ) {
			wp_send_json_error( array( 'message' => __( 'No backup available.', 'alesta' ) ) );
		}

		if ( ! $this->can_write() ) {
			wp_send_json_error( array( 'message' => __( 'The robots.txt file is not writable.', 'alesta' ) ) );
		}

		file_put_contents( self::robots_path(), $backup ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		wp_send_json_success( array(
			'message' => __( 'Backup restored successfully.', 'alesta' ),
			'content' => $backup,
		) );
	}

	// =========================================================================
	// AJAX : Check file accessibility
	// =========================================================================
	public function ajax_ping(): void {
		check_ajax_referer( 'alesta_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$response = wp_remote_get( home_url( '/robots.txt' ), array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		wp_send_json_success( array(
			'code'    => $code,
			'ok'      => ( $code === 200 ),
			'preview' => mb_substr( $body, 0, 500 ),
		) );
	}
}
