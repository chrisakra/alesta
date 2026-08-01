<?php
defined('ABSPATH') || exit;

class Alesta_Robots_Module {

    const BACKUP_KEY      = 'alesta_robots_backup';
    const BACKUP_DATE_KEY = 'alesta_robots_backup_date';

    /**
     * Returns the absolute path to robots.txt at the WordPress root.
     * Uses get_home_path() (official WP helper) instead of ABSPATH directly.
     */
    private static function robots_path(): string {
        if ( ! function_exists( 'get_home_path' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
        }
        return get_home_path() . 'robots.txt';
    }

    public function __construct() {
        add_action('wp_ajax_alesta_robots_read',    [$this, 'ajax_read']);
        add_action('wp_ajax_alesta_robots_save',    [$this, 'ajax_save']);
        add_action('wp_ajax_alesta_robots_reset',   [$this, 'ajax_reset']);
        add_action('wp_ajax_alesta_robots_backup',  [$this, 'ajax_backup']);
        add_action('wp_ajax_alesta_robots_restore', [$this, 'ajax_restore']);
        add_action('wp_ajax_alesta_robots_ping',    [$this, 'ajax_ping']);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function can_write(): bool {
        $path = self::robots_path(); // get_home_path() already loaded inside
        if (!file_exists($path)) {
            return is_writable( get_home_path() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
        }
        return is_writable($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
    }

    private function read_robots(): string {
        if (!file_exists(self::robots_path())) return '';
        // Direct read of robots.txt at the WP root. WP_Filesystem would need
        // FTP creds in non-direct setups, which we cannot prompt for from an
        // AJAX handler. Read-only access to a known root file.
        $content = file_get_contents(self::robots_path()); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        return $content !== false ? $content : '';
    }

    private function make_backup(): void {
        $content = $this->read_robots();
        if (empty($content)) return;
        update_option(self::BACKUP_KEY, $content);
        update_option(self::BACKUP_DATE_KEY, current_time('mysql'));
    }

    private function default_content(): string {
        $sitemap_url = home_url('/sitemap.xml');
        return "User-agent: *\n"
             . "Disallow: /wp-admin/\n"
             . "Allow: /wp-admin/admin-ajax.php\n"
             . "\n"
             . "Sitemap: " . $sitemap_url . "\n";
    }

    private function is_virtual_robots(): bool {
        // WordPress genere un robots.txt virtuel si aucun fichier physique n'existe
        return !file_exists(self::robots_path());
    }

    // =========================================================================
    // AJAX : Lire l'etat actuel
    // =========================================================================
    public function ajax_read(): void {
        check_ajax_referer('alesta_robots_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        wp_send_json_success([
            'exists'      => file_exists(self::robots_path()),
            'can_write'   => $this->can_write(),
            'is_virtual'  => $this->is_virtual_robots(),
            'content'     => $this->read_robots(),
            'default'     => $this->default_content(),
            'backup_date' => get_option(self::BACKUP_DATE_KEY, ''),
            'has_backup'  => !empty(get_option(self::BACKUP_KEY, '')),
            'url'         => home_url('/robots.txt'),
        ]);
    }

    // =========================================================================
    // AJAX : Enregistrer le contenu
    // =========================================================================
    public function ajax_save(): void {
        check_ajax_referer('alesta_robots_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        if (!$this->can_write()) {
            wp_send_json_error(['message' => 'Le fichier robots.txt n\'est pas accessible en écriture.']);
        }

        $content = isset($_POST['content']) ? wp_strip_all_tags(wp_unslash($_POST['content'])) : '';

        $this->make_backup();
        // can_write() above already confirmed write access — direct write to
        // the WP-root robots.txt is the simplest reliable path.
        $result = file_put_contents(self::robots_path(), $content); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents

        if ($result === false) {
            wp_send_json_error(['message' => 'Échec de l\'écriture du robots.txt.']);
        }

        wp_send_json_success(['message' => 'robots.txt enregistre avec succes.']);
    }

    // =========================================================================
    // AJAX : Reinitialiser au contenu par defaut
    // =========================================================================
    public function ajax_reset(): void {
        check_ajax_referer('alesta_robots_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        if (!$this->can_write()) {
            wp_send_json_error(['message' => 'Le fichier robots.txt n\'est pas accessible en écriture.']);
        }

        $this->make_backup();
        $content = $this->default_content();
        file_put_contents(self::robots_path(), $content); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents -- writability checked above via can_write()

        wp_send_json_success([
            'message' => 'robots.txt reinitialise avec les valeurs par defaut.',
            'content' => $content,
        ]);
    }

    // =========================================================================
    // AJAX : Sauvegarder manuellement
    // =========================================================================
    public function ajax_backup(): void {
        check_ajax_referer('alesta_robots_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $this->make_backup();
        wp_send_json_success([
            'message' => 'Sauvegarde effectuee.',
            'date'    => get_option(self::BACKUP_DATE_KEY),
        ]);
    }

    // =========================================================================
    // AJAX : Restaurer la sauvegarde
    // =========================================================================
    public function ajax_restore(): void {
        check_ajax_referer('alesta_robots_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $backup = get_option(self::BACKUP_KEY, '');
        if (empty($backup)) {
            wp_send_json_error(['message' => 'Aucune sauvegarde disponible.']);
        }

        if (!$this->can_write()) {
            wp_send_json_error(['message' => 'Le fichier robots.txt n\'est pas accessible en écriture.']);
        }

        file_put_contents(self::robots_path(), $backup); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents -- writability checked above via can_write()
        wp_send_json_success([
            'message' => 'Sauvegarde restauree avec succes.',
            'content' => $backup,
        ]);
    }

    // =========================================================================
    // AJAX : Verifier l'accessibilite du fichier
    // =========================================================================
    public function ajax_ping(): void {
        check_ajax_referer('alesta_robots_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $response = wp_remote_get(home_url('/robots.txt'), ['timeout' => 10]);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        wp_send_json_success([
            'code'    => $code,
            'ok'      => ($code === 200),
            'preview' => mb_substr($body, 0, 500),
        ]);
    }
}
