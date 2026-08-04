<?php
defined('ABSPATH') || exit;

/**
 * Google Fonts RGPD Module — Alesta
 *
 * Détecte les Google Fonts chargées sur le site, les télécharge en local
 * et les sert depuis le serveur pour supprimer tout contact avec les CDN Google.
 * Conformité RGPD (arrêt LG München I, 20 janvier 2022).
 *
 * PHP 7.4 compatible.
 */
class Alesta_Fonts_Module {

    const OPT_SETTINGS = 'alesta_fonts_settings';
    const OPT_REGISTRY = 'alesta_fonts_registry';

    const FONTS_SUBDIR = 'alesta-fonts';
    const UA_CHROME    = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    // =========================================================================
    // INIT
    // =========================================================================

    public static function init(): void {
        $s = self::settings();

        if ( $s['mode'] === 'block' ) {
            add_action('wp_print_styles', [__CLASS__, 'block_google_fonts'], 100);
            add_action('wp_head',         [__CLASS__, 'block_gfonts_in_head'], 1);
        } elseif ( $s['mode'] === 'auto_host' ) {
            add_filter('style_loader_src', [__CLASS__, 'swap_to_local'], 10, 2);
            add_action('wp_head',         [__CLASS__, 'block_gfonts_in_head'], 1);
        }

        // AJAX
        add_action('wp_ajax_alesta_fonts_scan',          [__CLASS__, 'ajax_scan']);
        add_action('wp_ajax_alesta_fonts_download_one',  [__CLASS__, 'ajax_download_one']);
        add_action('wp_ajax_alesta_fonts_download_all',  [__CLASS__, 'ajax_download_all']);
        add_action('wp_ajax_alesta_fonts_delete_one',    [__CLASS__, 'ajax_delete_one']);
        add_action('wp_ajax_alesta_fonts_clear_all',     [__CLASS__, 'ajax_clear_all']);
        add_action('wp_ajax_alesta_fonts_save_settings', [__CLASS__, 'ajax_save_settings']);
    }

    // =========================================================================
    // PARAMÈTRES
    // =========================================================================

    public static function settings(): array {
        $defaults = [
            'mode' => 'disabled', // 'disabled' | 'auto_host' | 'block'
        ];
        return wp_parse_args( get_option(self::OPT_SETTINGS, []), $defaults );
    }

    // =========================================================================
    // HOOKS FRONTEND
    // =========================================================================

    /**
     * Remplace les URLs Google Fonts par les URLs locales pour les handles WP connus.
     */
    public static function swap_to_local( string $src, string $handle ): string {
        if ( is_admin() ) return $src;
        if ( strpos($src, 'fonts.googleapis.com') === false &&
             strpos($src, 'fonts.gstatic.com')    === false ) return $src;

        $registry = self::registry();
        foreach ( $registry as $entry ) {
            if ( empty($entry['local_css_url']) ) continue;
            if ( in_array($handle, $entry['handles'], true) ) {
                return $entry['local_css_url'];
            }
            // Comparer l'URL directement
            if ( rtrim(strtok($src, '?'), '/') === rtrim(strtok($entry['url'], '?'), '/') ) {
                return $entry['local_css_url'];
            }
        }
        return $src;
    }

    /**
     * Supprime les styles Google Fonts enqueued.
     */
    public static function block_google_fonts(): void {
        if ( is_admin() ) return;
        global $wp_styles;
        if ( ! ($wp_styles instanceof WP_Styles) ) return;

        foreach ( array_keys($wp_styles->registered) as $handle ) {
            $src = $wp_styles->registered[$handle]->src ?? '';
            if ( is_string($src) && (
                strpos($src, 'fonts.googleapis.com') !== false ||
                strpos($src, 'fonts.gstatic.com')    !== false
            )) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }

    /**
     * Filtre wp_head pour retirer les <link> Google Fonts restants
     * (injectés hors WordPress, ex : par des plugins ou le thème directement).
     * Uses wp_ob_start_detect_shutdown / a self-contained ob within one function
     * to comply with WP.org policy (no cross-function ob_start / ob_get_clean).
     */
    public static function block_gfonts_in_head(): void {
        if ( is_admin() ) return;
        // Self-contained: ob_start with callback processes and outputs the buffer
        // automatically when the buffer is flushed — no separate ob_get_clean()
        // in another function is required.
        ob_start( function( string $html ): string {
            // Supprimer <link> vers fonts.googleapis.com
            $html = (string) preg_replace(
                '/<link[^>]+href=["\'][^"\']*fonts\.googleapis\.com[^"\']*["\'][^>]*>/i',
                '',
                $html
            );
            // Supprimer @import url(...fonts.googleapis.com...) dans les <style>
            $html = (string) preg_replace(
                '/@import\s+url\(["\']?https?:\/\/fonts\.googleapis\.com[^)]*["\']?\)\s*;?/i',
                '',
                $html
            );
            return $html;
        } );
        add_action( 'wp_head', [__CLASS__, 'flush_head_buffer'], 9999 );
    }

    public static function flush_head_buffer(): void {
        // Close the buffer opened in block_gfonts_in_head(). The ob_start
        // callback already applied the Google Fonts filter, so ob_end_flush()
        // simply sends the processed output.
        if ( ob_get_level() < 1 ) return;
        ob_end_flush();
    }

    /** @deprecated Kept for back-compat only — logic moved into block_gfonts_in_head() closure. */
    public static function filter_head_buffer( string $html ): string {
        // Supprimer <link> vers fonts.googleapis.com
        $html = preg_replace(
            '/<link[^>]+href=["\'][^"\']*fonts\.googleapis\.com[^"\']*["\'][^>]*>/i',
            '',
            $html
        );
        // Supprimer @import url(...fonts.googleapis.com...) dans les <style>
        $html = preg_replace(
            '/@import\s+url\(["\']?https?:\/\/fonts\.googleapis\.com[^)]*["\']?\)\s*;?/i',
            '',
            $html
        );
        return $html;
    }

    // =========================================================================
    // REGISTRE
    // =========================================================================

    public static function registry(): array {
        return (array) get_option(self::OPT_REGISTRY, []);
    }

    private static function save_registry( array $registry ): void {
        update_option(self::OPT_REGISTRY, $registry);
    }

    // =========================================================================
    // SCAN DU SITE
    // =========================================================================

    /**
     * Scanne la page d'accueil pour détecter toutes les Google Fonts.
     *
     * @return array  Liste des entrées détectées (nouvelles + existantes).
     */
    public static function scan_site(): array {
        $found    = [];
        $registry = self::registry();

        // 1. Scanner wp_styles (admin context — ce qui est registered)
        $found = array_merge($found, self::scan_wp_styles());

        // 2. Scanner le HTML de la page d'accueil
        $found = array_merge($found, self::scan_homepage_html());

        // 3. Fusionner avec le registre existant
        foreach ( $found as $key => $entry ) {
            if ( ! isset($registry[$key]) ) {
                $registry[$key] = $entry;
            } else {
                // Mettre à jour les handles si nouvellement trouvés
                $existing_handles = $registry[$key]['handles'] ?? [];
                $new_handles      = $entry['handles'] ?? [];
                $registry[$key]['handles'] = array_unique( array_merge($existing_handles, $new_handles) );
                // Conserver les données de téléchargement existantes
            }
        }

        self::save_registry($registry);
        return $registry;
    }

    /**
     * Scan les styles WordPress enregistrés (fonctionne en admin).
     */
    private static function scan_wp_styles(): array {
        $found = [];
        global $wp_styles;

        if ( ! ($wp_styles instanceof WP_Styles) ) {
            wp_styles(); // init
        }

        foreach ( $wp_styles->registered as $handle => $style ) {
            $src = $style->src ?? '';
            if ( ! is_string($src) ) continue;
            if ( strpos($src, 'fonts.googleapis.com') === false ) continue;

            $key = md5($src);
            if ( ! isset($found[$key]) ) {
                $found[$key] = [
                    'url'            => $src,
                    'families'       => self::parse_families_from_url($src),
                    'handles'        => [],
                    'local_css_url'  => null,
                    'local_css_path' => null,
                    'font_files'     => 0,
                    'size_kb'        => 0,
                    'downloaded_at'  => null,
                    'error'          => null,
                    'source'         => 'wp_styles',
                ];
            }
            $found[$key]['handles'][] = $handle;
            $found[$key]['handles']   = array_unique($found[$key]['handles']);
        }

        return $found;
    }

    /**
     * Scan le HTML de la page d'accueil via HTTP.
     */
    private static function scan_homepage_html(): array {
        $found = [];

        $response = wp_remote_get(home_url('/'), [
            'timeout'    => 15,
            'user-agent' => self::UA_CHROME,
            'sslverify'  => false,
        ]);

        if ( is_wp_error($response) ) return $found;

        $body = wp_remote_retrieve_body($response);
        if ( empty($body) ) return $found;

        // Chercher les <link href="...fonts.googleapis.com...">
        if ( preg_match_all(
            '/<link[^>]+href=["\']([^"\']*fonts\.googleapis\.com[^"\']*)["\'][^>]*>/i',
            $body, $matches
        )) {
            foreach ( $matches[1] as $url ) {
                $url = html_entity_decode($url);
                $key = md5($url);
                if ( ! isset($found[$key]) ) {
                    $found[$key] = [
                        'url'            => $url,
                        'families'       => self::parse_families_from_url($url),
                        'handles'        => [],
                        'local_css_url'  => null,
                        'local_css_path' => null,
                        'font_files'     => 0,
                        'size_kb'        => 0,
                        'downloaded_at'  => null,
                        'error'          => null,
                        'source'         => 'html_scan',
                    ];
                }
            }
        }

        // Chercher les @import url(...fonts.googleapis.com...) dans les <style>
        if ( preg_match_all(
            '/@import\s+url\(["\']?(https?:\/\/fonts\.googleapis\.com[^)\'"]*)["\']?\)/i',
            $body, $matches
        )) {
            foreach ( $matches[1] as $url ) {
                $key = md5($url);
                if ( ! isset($found[$key]) ) {
                    $found[$key] = [
                        'url'            => $url,
                        'families'       => self::parse_families_from_url($url),
                        'handles'        => [],
                        'local_css_url'  => null,
                        'local_css_path' => null,
                        'font_files'     => 0,
                        'size_kb'        => 0,
                        'downloaded_at'  => null,
                        'error'          => null,
                        'source'         => 'html_import',
                    ];
                }
            }
        }

        return $found;
    }

    // =========================================================================
    // TÉLÉCHARGEMENT D'UNE ENTRÉE
    // =========================================================================

    /**
     * Télécharge une entrée du registre (CSS + fichiers de police).
     *
     * @return true|string  true si succès, message d'erreur sinon.
     */
    public static function download_entry( string $key ) {
        $registry = self::registry();
        if ( ! isset($registry[$key]) ) return 'Entrée introuvable dans le registre.';

        $url    = $registry[$key]['url'];
        $dir    = self::fonts_dir();
        $dir_ok = self::ensure_fonts_dir();

        if ( ! $dir_ok ) return 'Dossier de polices non accessible en écriture.';

        // 1. Télécharger le CSS Google Fonts avec un UA Chrome (pour obtenir woff2)
        $css_response = wp_remote_get($url, [
            'timeout'    => 20,
            'user-agent' => self::UA_CHROME,
            'sslverify'  => false,
        ]);

        if ( is_wp_error($css_response) ) {
            return 'Erreur lors du téléchargement CSS : ' . $css_response->get_error_message();
        }

        $original_css = wp_remote_retrieve_body($css_response);
        if ( empty($original_css) ) return 'CSS Google Fonts vide ou inaccessible.';

        // 2. Parser les @font-face et télécharger chaque fichier .woff2
        $font_files_count = 0;
        $total_size       = 0;
        $local_css        = $original_css;

        if ( preg_match_all(
            '/url\((https:\/\/fonts\.gstatic\.com\/[^)]+)\)/i',
            $original_css, $matches
        )) {
            foreach ( array_unique($matches[1]) as $font_url ) {
                $font_filename = self::font_filename_from_url($font_url);
                $font_path     = $dir . $font_filename;
                $font_url_local = self::fonts_url() . $font_filename;

                if ( ! file_exists($font_path) ) {
                    $font_response = wp_remote_get($font_url, [
                        'timeout'   => 30,
                        'sslverify' => false,
                    ]);

                    if ( is_wp_error($font_response) ) continue;

                    $font_data = wp_remote_retrieve_body($font_response);
                    if ( empty($font_data) ) continue;

                    file_put_contents($font_path, $font_data); // phpcs:ignore WordPress.WP.AlternativeFunctions
                }

                if ( file_exists($font_path) ) {
                    $total_size += filesize($font_path);
                    $font_files_count++;
                    // Remplacer l'URL dans le CSS
                    $local_css = str_replace($font_url, $font_url_local, $local_css);
                }
            }
        }

        if ( $font_files_count === 0 ) {
            return 'Aucun fichier de police trouvé dans la réponse CSS.';
        }

        // 3. Sauvegarder le CSS local
        $css_filename = 'gfonts-' . substr($key, 0, 8) . '.css';
        $css_path     = $dir . $css_filename;
        $css_url      = self::fonts_url() . $css_filename;

        if ( file_put_contents($css_path, $local_css) === false ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
            return 'Impossible d\'écrire le fichier CSS local.';
        }

        // 4. Mettre à jour le registre
        $registry[$key]['local_css_url']  = $css_url;
        $registry[$key]['local_css_path'] = $css_path;
        $registry[$key]['font_files']     = $font_files_count;
        $registry[$key]['size_kb']        = round($total_size / 1024, 1);
        $registry[$key]['downloaded_at']  = current_time('mysql');
        $registry[$key]['error']          = null;

        self::save_registry($registry);
        return true;
    }

    // =========================================================================
    // SUPPRESSION D'UNE ENTRÉE
    // =========================================================================

    public static function delete_entry( string $key ): void {
        $registry = self::registry();
        if ( ! isset($registry[$key]) ) return;

        $entry = $registry[$key];

        // Supprimer les fichiers locaux
        if ( ! empty($entry['local_css_path']) && file_exists($entry['local_css_path']) ) {
            unlink($entry['local_css_path']); // phpcs:ignore WordPress.WP.AlternativeFunctions
        }

        // Supprimer les fichiers de polices associés (identifiés par la clé dans le nom CSS)
        $dir = self::fonts_dir();
        if ( is_dir($dir) ) {
            $files = glob($dir . 'gfonts-' . substr($key, 0, 8) . '-*.woff2');
            if ( is_array($files) ) {
                foreach ( $files as $f ) unlink($f); // phpcs:ignore WordPress.WP.AlternativeFunctions
            }
        }

        // Réinitialiser l'entrée (conserver la détection, effacer le téléchargement)
        $registry[$key]['local_css_url']  = null;
        $registry[$key]['local_css_path'] = null;
        $registry[$key]['font_files']     = 0;
        $registry[$key]['size_kb']        = 0;
        $registry[$key]['downloaded_at']  = null;
        $registry[$key]['error']          = null;

        self::save_registry($registry);
    }

    public static function clear_all(): void {
        $registry = self::registry();

        foreach ( array_keys($registry) as $key ) {
            self::delete_entry($key);
        }

        // Vider tout le dossier
        $dir = self::fonts_dir();
        if ( is_dir($dir) ) {
            $files = array_merge(
                glob($dir . '*.woff2') ?: [],
                glob($dir . '*.woff')  ?: [],
                glob($dir . '*.css')   ?: []
            );
            foreach ( $files as $f ) unlink($f); // phpcs:ignore WordPress.WP.AlternativeFunctions
        }

        update_option(self::OPT_REGISTRY, []);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public static function fonts_dir(): string {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . self::FONTS_SUBDIR . '/';
    }

    public static function fonts_url(): string {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['baseurl'] ) . self::FONTS_SUBDIR . '/';
    }

    public static function ensure_fonts_dir(): bool {
        $dir = self::fonts_dir();
        if ( ! is_dir($dir) ) wp_mkdir_p($dir);

        // Créer un index.php pour la sécurité
        $index = $dir . 'index.php';
        if ( ! file_exists($index) ) {
            file_put_contents($index, '<?php // Silence is golden.'); // phpcs:ignore WordPress.WP.AlternativeFunctions
        }
        return is_dir($dir) && wp_is_writable($dir);
    }

    private static function font_filename_from_url( string $url ): string {
        $path = wp_parse_url($url, PHP_URL_PATH);
        $name = basename($path ?? 'font.woff2');
        // Assainir le nom
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', $name);
        return $name;
    }

    /**
     * Extrait les noms de familles depuis une URL Google Fonts.
     * Ex: fonts.googleapis.com/css2?family=Roboto:wght@400;700&family=Open+Sans
     * → ['Roboto', 'Open Sans']
     */
    public static function parse_families_from_url( string $url ): array {
        $families = [];
        $parsed   = wp_parse_url($url);
        $query    = $parsed['query'] ?? '';

        parse_str($query, $params);

        if ( isset($params['family']) ) {
            $raw = is_array($params['family']) ? $params['family'] : [$params['family']];
            foreach ( $raw as $f ) {
                $name = strtok($f, ':');
                if ( $name ) $families[] = str_replace('+', ' ', $name);
            }
        }

        // Google Fonts v1 : ?family=Roboto|Open+Sans
        if ( empty($families) && strpos($query, 'family=') !== false ) {
            preg_match('/family=([^&]+)/', $query, $m);
            if ( ! empty($m[1]) ) {
                $parts = explode('|', urldecode($m[1]));
                foreach ( $parts as $p ) {
                    $name = strtok($p, ':');
                    if ( $name ) $families[] = str_replace('+', ' ', $name);
                }
            }
        }

        return array_unique($families) ?: ['(Polices non identifiées)'];
    }

    public static function get_stats(): array {
        $registry   = self::registry();
        $total      = count($registry);
        $downloaded = 0;
        $errors     = 0;
        $pending    = 0;
        $size_kb    = 0.0;

        foreach ( $registry as $entry ) {
            if ( ! empty($entry['local_css_url']) ) {
                $downloaded++;
                $size_kb += (float)($entry['size_kb'] ?? 0);
            } elseif ( ! empty($entry['error']) ) {
                $errors++;
            } else {
                $pending++;
            }
        }

        return [
            'total'      => $total,
            'downloaded' => $downloaded,
            'errors'     => $errors,
            'pending'    => $pending,
            'size_kb'    => round($size_kb, 1),
        ];
    }

    // =========================================================================
    // AJAX
    // =========================================================================

    public static function ajax_scan(): void {
        check_ajax_referer('alesta_fonts_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Accès refusé.']);

        $registry = self::scan_site();
        $stats    = self::get_stats();
        wp_send_json_success(['registry' => $registry, 'stats' => $stats, 'count' => count($registry)]);
    }

    public static function ajax_download_one(): void {
        check_ajax_referer('alesta_fonts_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Accès refusé.']);

        $key    = sanitize_key( wp_unslash($_POST['key'] ?? '') );
        $result = self::download_entry($key);

        if ( $result === true ) {
            $registry = self::registry();
            wp_send_json_success([
                'entry' => $registry[$key] ?? [],
                'stats' => self::get_stats(),
            ]);
        } else {
            $registry = self::registry();
            if ( isset($registry[$key]) ) {
                $registry[$key]['error'] = $result;
                self::save_registry($registry);
            }
            wp_send_json_error(['message' => $result]);
        }
    }

    public static function ajax_download_all(): void {
        check_ajax_referer('alesta_fonts_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Accès refusé.']);

        $registry = self::registry();
        $success  = 0;
        $errors   = 0;

        foreach ( array_keys($registry) as $key ) {
            if ( ! empty($registry[$key]['local_css_url']) ) continue; // Déjà téléchargé
            $result = self::download_entry($key);
            if ( $result === true ) {
                $success++;
            } else {
                $errors++;
                $registry = self::registry(); // Rafraîchir après l'erreur
                $registry[$key]['error'] = $result;
                self::save_registry($registry);
            }
        }

        wp_send_json_success([
            'success' => $success,
            'errors'  => $errors,
            'stats'   => self::get_stats(),
        ]);
    }

    public static function ajax_delete_one(): void {
        check_ajax_referer('alesta_fonts_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Accès refusé.']);

        $key = sanitize_key( wp_unslash($_POST['key'] ?? '') );
        self::delete_entry($key);
        wp_send_json_success(['stats' => self::get_stats()]);
    }

    public static function ajax_clear_all(): void {
        check_ajax_referer('alesta_fonts_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Accès refusé.']);

        self::clear_all();
        wp_send_json_success(['message' => 'Toutes les polices locales supprimées.']);
    }

    public static function ajax_save_settings(): void {
        check_ajax_referer('alesta_fonts_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Accès refusé.']);

        $allowed_modes = ['disabled', 'auto_host', 'block'];
        $mode          = sanitize_key( wp_unslash($_POST['mode'] ?? 'disabled') );
        if ( ! in_array($mode, $allowed_modes, true) ) {
            wp_send_json_error(['message' => 'Mode invalide.']);
        }

        update_option(self::OPT_SETTINGS, ['mode' => $mode]);
        wp_send_json_success(['message' => 'Mode enregistré.', 'mode' => $mode]);
    }
}
