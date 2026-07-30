<?php
defined('ABSPATH') || exit;

class Alesta_Htaccess_Module {

    const BACKUP_KEY      = 'alesta_htaccess_backup';
    const BACKUP_DATE_KEY = 'alesta_htaccess_backup_date';

    /**
     * Returns the absolute path to .htaccess at the WordPress root.
     * Uses get_home_path() (the official WP helper) instead of ABSPATH directly.
     * Requires wp-admin/includes/file.php for get_home_path().
     */
    private static function htaccess_path(): string {
        if ( ! function_exists( 'get_home_path' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
        }
        return get_home_path() . '.htaccess';
    }

    public function __construct() {
        add_action('wp_ajax_alesta_htaccess_read',          [$this, 'ajax_read']);
        add_action('wp_ajax_alesta_htaccess_apply_cache',   [$this, 'ajax_apply_cache']);
        add_action('wp_ajax_alesta_htaccess_apply_gzip',    [$this, 'ajax_apply_gzip']);
        add_action('wp_ajax_alesta_htaccess_apply_https',   [$this, 'ajax_apply_https']);
        add_action('wp_ajax_alesta_htaccess_remove',        [$this, 'ajax_remove']);
        add_action('wp_ajax_alesta_htaccess_backup',        [$this, 'ajax_backup']);
        add_action('wp_ajax_alesta_htaccess_restore',       [$this, 'ajax_restore']);
        add_action('wp_ajax_alesta_htaccess_fix_https_url', [$this, 'ajax_fix_https_url']);
        add_action('wp_ajax_alesta_htaccess_apply_www',    [$this, 'ajax_apply_www']);
        add_action('wp_ajax_alesta_htaccess_switch_www',   [$this, 'ajax_switch_www']);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function can_write(): bool {
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            // Loading a WP core admin include — ABSPATH . 'wp-admin/includes/'
            // is the documented way to reach WP_Filesystem(). WordPress core
            // itself uses this exact idiom.
            require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
            WP_Filesystem();
        }
        return $wp_filesystem->exists( self::htaccess_path() ) && $wp_filesystem->is_writable( self::htaccess_path() );
    }

    private function is_active(string $marker): bool {
        if (!file_exists(self::htaccess_path())) return false;
        $lines = extract_from_markers(self::htaccess_path(), $marker);
        return !empty(array_filter($lines));
    }

    private function make_backup(): void {
        if (!file_exists(self::htaccess_path())) return;
        // Direct file_get_contents() on .htaccess at the WP root — WP_Filesystem
        // requires FTP creds in non-direct setups, which we cannot prompt for
        // from an AJAX handler. Plain read of a known root file.
        $content = file_get_contents(self::htaccess_path()); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        update_option(self::BACKUP_KEY, $content);
        update_option(self::BACKUP_DATE_KEY, current_time('mysql'));
    }

    private function read_htaccess(): string {
        if (!file_exists(self::htaccess_path())) return '';
        // Same rationale as make_backup() above — read-only access to the
        // WP-root .htaccess file. WordPress core uses the same pattern in
        // wp-admin/includes/misc.php (got_url_rewrite_module()).
        return file_get_contents(self::htaccess_path()); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    }

    // =========================================================================
    // Regles htaccess
    // =========================================================================

    private function rules_cache(array $opts = []): array {
        $img_duration  = $opts['img']   ?? '1 year';
        $css_duration  = $opts['css']   ?? '1 month';
        $font_duration = $opts['fonts'] ?? '1 year';

        return [
            '<IfModule mod_expires.c>',
            '    ExpiresActive On',
            '    # Images',
            '    ExpiresByType image/jpeg                 "access plus ' . $img_duration . '"',
            '    ExpiresByType image/png                  "access plus ' . $img_duration . '"',
            '    ExpiresByType image/gif                  "access plus ' . $img_duration . '"',
            '    ExpiresByType image/webp                 "access plus ' . $img_duration . '"',
            '    ExpiresByType image/svg+xml              "access plus ' . $img_duration . '"',
            '    ExpiresByType image/x-icon               "access plus ' . $img_duration . '"',
            '    # CSS et JavaScript',
            '    ExpiresByType text/css                   "access plus ' . $css_duration . '"',
            '    ExpiresByType application/javascript     "access plus ' . $css_duration . '"',
            '    ExpiresByType text/javascript            "access plus ' . $css_duration . '"',
            '    # Polices',
            '    ExpiresByType font/woff2                 "access plus ' . $font_duration . '"',
            '    ExpiresByType font/woff                  "access plus ' . $font_duration . '"',
            '    ExpiresByType application/x-font-woff   "access plus ' . $font_duration . '"',
            '</IfModule>',
            '<IfModule mod_headers.c>',
            '    <FilesMatch "\.(jpg|jpeg|png|gif|webp|svg|ico)$">',
            '        Header set Cache-Control "max-age=31536000, public"',
            '    </FilesMatch>',
            '    <FilesMatch "\.(css|js)$">',
            '        Header set Cache-Control "max-age=2592000, public"',
            '    </FilesMatch>',
            '</IfModule>',
        ];
    }

    private function rules_gzip(): array {
        return [
            '<IfModule mod_deflate.c>',
            '    AddOutputFilterByType DEFLATE text/plain',
            '    AddOutputFilterByType DEFLATE text/html',
            '    AddOutputFilterByType DEFLATE text/xml',
            '    AddOutputFilterByType DEFLATE text/css',
            '    AddOutputFilterByType DEFLATE text/javascript',
            '    AddOutputFilterByType DEFLATE application/xml',
            '    AddOutputFilterByType DEFLATE application/xhtml+xml',
            '    AddOutputFilterByType DEFLATE application/javascript',
            '    AddOutputFilterByType DEFLATE application/x-javascript',
            '    AddOutputFilterByType DEFLATE application/json',
            '    AddOutputFilterByType DEFLATE image/svg+xml',
            '</IfModule>',
        ];
    }

    private function rules_https(): array {
        return [
            '<IfModule mod_rewrite.c>',
            '    RewriteEngine On',
            '    RewriteCond %{HTTPS} off',
            '    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]',
            '</IfModule>',
        ];
    }

    // =========================================================================
    // AJAX : Lire l'etat actuel
    // =========================================================================
    public function ajax_read(): void {
        check_ajax_referer('alesta_htaccess_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $htaccess_content = $this->read_htaccess();
        $backup_date      = get_option(self::BACKUP_DATE_KEY, '');
        $has_backup       = !empty(get_option(self::BACKUP_KEY, ''));
        $site_url         = get_option('siteurl', '');
        $home_url         = get_option('home', '');

        wp_send_json_success([
            'can_write'    => $this->can_write(),
            'exists'       => file_exists(self::htaccess_path()),
            'size'         => file_exists(self::htaccess_path()) ? filesize(self::htaccess_path()) : 0,
            'cache_active' => $this->is_active('Alesta AI - Cache navigateur'),
            'gzip_active'  => $this->is_active('Alesta AI - Compression GZIP'),
            'https_active' => $this->is_active('Alesta AI - HTTPS'),
            'backup_date'  => $backup_date,
            'has_backup'   => $has_backup,
            'site_url'     => $site_url,
            'home_url'     => $home_url,
            'is_https'     => strpos($site_url, 'https://') === 0,
            'preview' => [
                'cache' => implode("\n", $this->rules_cache()),
                'gzip'  => implode("\n", $this->rules_gzip()),
                'https' => implode("\n", $this->rules_https()),
            ],
        ]);
    }

    // =========================================================================
    // AJAX : Appliquer cache navigateur
    // =========================================================================
    public function ajax_apply_cache(): void {
        check_ajax_referer('alesta_htaccess_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        if (!$this->can_write()) {
            wp_send_json_error(['message' => 'Le fichier .htaccess n\'est pas accessible en écriture.']);
        }

        $opts = [
            'img'   => sanitize_text_field( isset( $_POST['img_duration'] ) ? wp_unslash( $_POST['img_duration'] ) : '1 year' ),
            'css'   => sanitize_text_field( isset( $_POST['css_duration'] ) ? wp_unslash( $_POST['css_duration'] ) : '1 month' ),
            'fonts' => sanitize_text_field( isset( $_POST['font_duration'] ) ? wp_unslash( $_POST['font_duration'] ) : '1 year' ),
        ];

        $this->make_backup();
        $result = insert_with_markers(self::htaccess_path(), 'Alesta AI - Cache navigateur', $this->rules_cache($opts));

        if (!$result) wp_send_json_error(['message' => 'Échec de l\'écriture dans .htaccess']);
        wp_send_json_success(['message' => 'Cache navigateur active avec succes']);
    }

    // =========================================================================
    // AJAX : Appliquer GZIP
    // =========================================================================
    public function ajax_apply_gzip(): void {
        check_ajax_referer('alesta_htaccess_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        if (!$this->can_write()) {
            wp_send_json_error(['message' => 'Le fichier .htaccess n\'est pas accessible en écriture.']);
        }

        $this->make_backup();
        $result = insert_with_markers(self::htaccess_path(), 'Alesta AI - Compression GZIP', $this->rules_gzip());

        if (!$result) wp_send_json_error(['message' => 'Échec de l\'écriture dans .htaccess']);
        wp_send_json_success(['message' => 'Compression GZIP activee avec succes']);
    }

    // =========================================================================
    // AJAX : Appliquer HTTPS
    // =========================================================================
    public function ajax_apply_https(): void {
        check_ajax_referer('alesta_htaccess_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        if (!$this->can_write()) {
            wp_send_json_error(['message' => 'Le fichier .htaccess n\'est pas accessible en écriture.']);
        }

        $this->make_backup();
        $result = insert_with_markers(self::htaccess_path(), 'Alesta AI - HTTPS', $this->rules_https());

        if (!$result) wp_send_json_error(['message' => 'Échec de l\'écriture dans .htaccess']);
        wp_send_json_success(['message' => 'Redirection HTTPS activee avec succes']);
    }

    // =========================================================================
    // AJAX : Supprimer un bloc
    // =========================================================================
    public function ajax_remove(): void {
        check_ajax_referer('alesta_htaccess_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $marker = sanitize_text_field( isset( $_POST['marker'] ) ? wp_unslash( $_POST['marker'] ) : '' );
        $allowed = [
            'Alesta AI - Cache navigateur',
            'Alesta AI - Compression GZIP',
            'Alesta AI - HTTPS',
        ];
        if (!in_array($marker, $allowed)) {
            wp_send_json_error(['message' => 'Marqueur non autorise']);
        }

        if (!$this->can_write()) {
            wp_send_json_error(['message' => 'Le fichier .htaccess n\'est pas accessible en écriture.']);
        }

        $this->make_backup();
        // Passer un tableau vide supprime le bloc mais garde les balises vides
        insert_with_markers(self::htaccess_path(), $marker, []);
        wp_send_json_success(['message' => 'Regle supprimee du .htaccess']);
    }

    // =========================================================================
    // AJAX : Sauvegarder manuellement
    // =========================================================================
    public function ajax_backup(): void {
        check_ajax_referer('alesta_htaccess_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $this->make_backup();
        wp_send_json_success([
            'message' => 'Sauvegarde effectuee',
            'date'    => get_option(self::BACKUP_DATE_KEY),
        ]);
    }

    // =========================================================================
    // AJAX : Restaurer la sauvegarde
    // =========================================================================
    public function ajax_restore(): void {
        check_ajax_referer('alesta_htaccess_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $backup = get_option(self::BACKUP_KEY, '');
        if (empty($backup)) {
            wp_send_json_error(['message' => 'Aucune sauvegarde disponible']);
        }

        if (!$this->can_write()) {
            wp_send_json_error(['message' => 'Le fichier .htaccess n\'est pas accessible en écriture.']);
        }

        // Restore: write the previously backed-up .htaccess back to its
        // canonical root location. can_write() above already verified that
        // we have write access — no WP_Filesystem indirection needed.
        file_put_contents(self::htaccess_path(), $backup); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
        wp_send_json_success(['message' => 'Sauvegarde restauree avec succes']);
    }

    // =========================================================================
    // AJAX : Corriger l'URL WordPress en HTTPS
    // =========================================================================
    public function ajax_fix_https_url(): void {
        check_ajax_referer('alesta_htaccess_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $siteurl = get_option('siteurl', '');
        $homeurl = get_option('home', '');

        $new_site = str_replace('http://', 'https://', $siteurl);
        $new_home = str_replace('http://', 'https://', $homeurl);

        update_option('siteurl', $new_site);
        update_option('home',    $new_home);

        wp_send_json_success([
            'message'  => 'URL WordPress mise a jour en HTTPS',
            'siteurl'  => $new_site,
            'home'     => $new_home,
        ]);
    }

    // =========================================================================
    // WWW — .htaccess redirect
    // =========================================================================

    private function rules_www(): array {
        return [
            '<IfModule mod_rewrite.c>',
            '    RewriteEngine On',
            '    RewriteCond %{HTTP_HOST} !^www\. [NC]',
            '    RewriteRule ^ https://www.%{HTTP_HOST}%{REQUEST_URI} [R=301,L]',
            '</IfModule>',
        ];
    }

    public function ajax_apply_www(): void {
        check_ajax_referer('alesta_htaccess_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Accès refusé.');

        $remove = ! empty( sanitize_text_field( wp_unslash( $_POST['remove'] ?? '' ) ) );
        $marker = 'Alesta-WWW';

        if ($remove) {
            $result = insert_with_markers(self::htaccess_path(), $marker, []);
        } else {
            $this->make_backup();
            $result = insert_with_markers(self::htaccess_path(), $marker, $this->rules_www());
        }

        if (!$result) wp_send_json_error('Impossible d\'écrire dans .htaccess.');

        $preview = '';
        if (!$remove) {
            $preview = "# BEGIN Alesta-WWW\n"
                     . implode("\n", $this->rules_www())
                     . "\n# END Alesta-WWW";
        }

        wp_send_json_success([
            'message' => $remove ? 'Redirection WWW supprimée.' : 'Redirection WWW activée.',
            'active'  => !$remove,
            'preview' => $preview,
        ]);
    }

    public function ajax_switch_www(): void {
        check_ajax_referer('alesta_htaccess_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Accès refusé.');

        $mode = sanitize_text_field(wp_unslash($_POST['mode'] ?? 'add')); // add | remove

        $siteurl = get_option('siteurl', '');
        $homeurl = get_option('home',    '');

        if ($mode === 'add') {
            // Add www: https://example.com → https://www.example.com
            $new_site = preg_replace('#^(https?://)(?!www\.)#i', '$1www.', $siteurl);
            $new_home = preg_replace('#^(https?://)(?!www\.)#i', '$1www.', $homeurl);
        } else {
            // Remove www: https://www.example.com → https://example.com
            $new_site = preg_replace('#^(https?://)www\.#i', '$1', $siteurl);
            $new_home = preg_replace('#^(https?://)www\.#i', '$1', $homeurl);
        }

        if ($new_site === $siteurl && $new_home === $homeurl) {
            wp_send_json_error('L\'URL est déjà correctement configurée, aucune modification nécessaire.');
        }

        update_option('siteurl', $new_site);
        update_option('home',    $new_home);

        wp_send_json_success([
            'message'  => 'URLs WordPress mises à jour. Vous allez être déconnecté.',
            'siteurl'  => $new_site,
            'home'     => $new_home,
            'login_url'=> wp_login_url(),
        ]);
    }
}
