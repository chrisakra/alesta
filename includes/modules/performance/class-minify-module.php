<?php
defined('ABSPATH') || exit;

/**
 * Minify Module — Alesta
 *
 * Gère la minification CSS, JS, HTML et les preload CSS hints.
 * PHP 7.4 compatible. Aucun accès Freemius / hooks Pro.
 */
class Alesta_Minify_Module {

    const OPT       = 'alesta_minify_settings';
    // WP_CONTENT_DIR / WP_CONTENT_URL are the canonical constants for the
    // wp-content directory. The minify cache MUST live under wp-content/cache/
    // (same as W3TC, WP Rocket) — wp_upload_dir() would be wrong for build
    // artefacts. phpcs:ignore covers compile-time class-constant requirement.
    const CACHE_DIR = WP_CONTENT_DIR . '/cache/alesta-minify/'; // phpcs:ignore WordPressVIPMinimum.Constants.ConstantString.NotCheckingConstantValue
    const CACHE_URL = WP_CONTENT_URL  . '/cache/alesta-minify/'; // phpcs:ignore WordPressVIPMinimum.Constants.ConstantString.NotCheckingConstantValue

    // =========================================================================
    // INIT
    // =========================================================================

    public static function init(): void {
        $s = self::settings();

        if ( ! empty($s['css_enabled']) ) {
            add_filter('style_loader_src',  [__CLASS__, 'maybe_minify_css_src'],  20, 2);
        }
        if ( ! empty($s['js_enabled']) ) {
            add_filter('script_loader_src', [__CLASS__, 'maybe_minify_js_src'],   20, 2);
        }
        if ( ! empty($s['html_enabled']) ) {
            add_action('template_redirect', [__CLASS__, 'start_html_buffer'],     1);
        }
        if ( ! empty($s['preload_enabled']) ) {
            add_action('wp_head',           [__CLASS__, 'inject_preloads'],        1);
        }

        // AJAX
        add_action('wp_ajax_alesta_minify_toggle',      [__CLASS__, 'ajax_toggle']);
        add_action('wp_ajax_alesta_minify_save',        [__CLASS__, 'ajax_save']);
        add_action('wp_ajax_alesta_minify_clear_cache', [__CLASS__, 'ajax_clear_cache']);
        add_action('wp_ajax_alesta_minify_get_stats',   [__CLASS__, 'ajax_get_stats']);
    }

    // =========================================================================
    // PARAMÈTRES
    // =========================================================================

    public static function settings(): array {
        $defaults = [
            'css_enabled'            => false,
            'css_excludes'           => '',
            'js_enabled'             => false,
            'js_excludes'            => '',
            'html_enabled'           => false,
            'html_remove_comments'   => true,
            'html_remove_whitespace' => true,
            'preload_enabled'        => false,
            'preload_mode'           => 'all',   // 'all' ou 'manual'
            'preload_handles'        => '',       // virgule-séparés
            'preload_excludes'       => '',
        ];
        return wp_parse_args( get_option(self::OPT, []), $defaults );
    }

    // =========================================================================
    // BYPASS PAGE-BUILDERS (safety critique)
    // =========================================================================

    /**
     * Détecte l'édition en direct via un page-builder pour désactiver toute
     * transformation qui pourrait casser l'aperçu. Frontend uniquement.
     */
    public static function is_page_builder_request(): bool {
        // Superglobales lues uniquement pour la détection — pas de traitement.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['elementor-preview'] ) )            return true;
        if ( isset( $_GET['et_fb'] ) )                        return true; // Divi
        if ( isset( $_GET['bricks'] ) && $_GET['bricks'] === 'run' ) return true;
        if ( isset( $_GET['ct_builder'] ) )                   return true; // Oxygen
        if ( isset( $_GET['fl_builder'] ) )                   return true; // Beaver
        if ( isset( $_GET['vc_editable'] ) )                  return true; // WPBakery
        if ( isset( $_GET['brizy-edit'] ) )                   return true;
        if ( isset( $_GET['brizy-edit-iframe'] ) )            return true;
        if ( isset( $_GET['tve'] ) )                          return true; // Thrive
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ( function_exists('is_customize_preview') && is_customize_preview() ) return true;

        return false;
    }

    // =========================================================================
    // FILTRE CSS SRC
    // =========================================================================

    public static function maybe_minify_css_src( string $src, string $handle ): string {
        if ( is_admin() || wp_doing_ajax() ) return $src;
        if ( self::is_page_builder_request() ) return $src;

        $s = self::settings();
        if ( self::is_excluded($handle, $s['css_excludes']) ) return $src;

        // Ignorer les fichiers déjà minifiés
        if ( strpos(strtok($src, '?'), '.min.css') !== false ) return $src;

        $local = self::src_to_local_path($src);
        if ( ! $local ) return $src;

        $cached_path = self::get_cache_path($local, 'css');
        $cached_url  = self::CACHE_URL . basename($cached_path);

        if ( ! file_exists($cached_path) || filemtime($local) > filemtime($cached_path) ) {
            $content = file_get_contents($local); // phpcs:ignore WordPress.WP.AlternativeFunctions
            if ( $content === false ) return $src;

            $base_url = dirname( strtok($src, '?') ) . '/';
            $minified = self::minify_css( self::fix_relative_urls($content, $base_url) );

            if ( ! self::ensure_cache_dir() ) return $src;
            file_put_contents($cached_path, $minified); // phpcs:ignore WordPress.WP.AlternativeFunctions
        }

        return $cached_url . '?v=' . filemtime($cached_path);
    }

    // =========================================================================
    // FILTRE JS SRC
    // =========================================================================

    public static function maybe_minify_js_src( string $src, string $handle ): string {
        if ( is_admin() || wp_doing_ajax() ) return $src;
        if ( self::is_page_builder_request() ) return $src;

        $s = self::settings();
        if ( self::is_excluded($handle, $s['js_excludes']) ) return $src;

        // Ignorer jQuery natif et scripts WP sensibles
        $skip_handles = ['jquery', 'jquery-core', 'jquery-migrate', 'wp-embed', 'wp-polyfill'];
        if ( in_array($handle, $skip_handles, true) ) return $src;

        $raw = strtok($src, '?');
        if ( strpos($raw, '.min.js') !== false ) return $src;

        $local = self::src_to_local_path($src);
        if ( ! $local ) return $src;

        $cached_path = self::get_cache_path($local, 'js');
        $cached_url  = self::CACHE_URL . basename($cached_path);

        if ( ! file_exists($cached_path) || filemtime($local) > filemtime($cached_path) ) {
            $content = file_get_contents($local); // phpcs:ignore WordPress.WP.AlternativeFunctions
            if ( $content === false ) return $src;

            $minified = self::minify_js($content);
            if ( ! self::ensure_cache_dir() ) return $src;
            file_put_contents($cached_path, $minified); // phpcs:ignore WordPress.WP.AlternativeFunctions
        }

        return $cached_url . '?v=' . filemtime($cached_path);
    }

    // =========================================================================
    // BUFFER HTML
    // =========================================================================

    public static function start_html_buffer(): void {
        if ( is_admin() || wp_doing_ajax() ) return;
        if ( self::is_page_builder_request() ) return;

        // Sécurité PCRE : sur des pages HTML volumineuses, les limites par
        // défaut de PHP peuvent tronquer les regex. On les élève si trop bas.
        if ( (int) ini_get('pcre.backtrack_limit') < 5000000 ) {
            @ini_set('pcre.backtrack_limit', '5000000'); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.IniSet, WordPress.PHP.DevelopmentFunctions, Squiz.PHP.DiscouragedFunctions
        }
        if ( (int) ini_get('pcre.recursion_limit') < 5000000 ) {
            @ini_set('pcre.recursion_limit', '5000000'); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.IniSet, WordPress.PHP.DevelopmentFunctions, Squiz.PHP.DiscouragedFunctions
        }

        $settings = self::settings();
        // Self-contained: ob_start with callback processes the full HTML page
        // through the minifier and returns it — no separate ob_get_clean() in
        // another function, complying with WP.org output-buffering policy.
        ob_start( function( string $html ) use ( $settings ): string {
            if ( $html === '' ) return $html;
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML already escaped by WordPress; minifier only removes whitespace/comments
            return self::minify_html( $html, $settings );
        } );
        add_action( 'shutdown', [__CLASS__, 'end_html_buffer'], 0 );
    }

    /**
     * Closes the output buffer opened in start_html_buffer().
     * The ob_start callback already processed the HTML, so ob_end_flush()
     * simply sends the minified output.
     * Hooked to 'shutdown' at priority 0 so it fires before WordPress flushes
     * its own buffers.
     */
    public static function end_html_buffer(): void {
        if ( ob_get_level() < 1 ) return;
        ob_end_flush();
    }

    // =========================================================================
    // PRELOAD CSS HINTS
    // =========================================================================

    public static function inject_preloads(): void {
        if ( self::is_page_builder_request() ) return;

        $s = self::settings();

        global $wp_styles;
        if ( ! ($wp_styles instanceof WP_Styles) ) return;

        $excludes = array_filter( array_map('trim', explode(',', $s['preload_excludes'])) );

        if ( $s['preload_mode'] === 'all' ) {
            // Précharger tous les CSS enqueued (sauf exclusions)
            foreach ( $wp_styles->queue as $handle ) {
                if ( in_array($handle, $excludes, true) ) continue;
                self::echo_preload_link($handle, $wp_styles);
            }
        } else {
            // Mode manuel : handles saisis
            $handles = array_filter( array_map('trim', explode(',', $s['preload_handles'])) );
            foreach ( $handles as $handle ) {
                if ( in_array($handle, $excludes, true) ) continue;
                self::echo_preload_link($handle, $wp_styles);
            }
        }
    }

    private static function echo_preload_link( string $handle, WP_Styles $wp_styles ): void {
        if ( ! isset($wp_styles->registered[$handle]) ) return;
        $style = $wp_styles->registered[$handle];
        $src   = is_string($style->src) ? $style->src : '';
        if ( empty($src) ) return;
        if ( strpos($src, '//') === 0 ) $src = 'https:' . $src;
        if ( strpos($src, 'http') !== 0 ) $src = site_url( $src );
        echo '<link rel="preload" as="style" href="' . esc_url($src) . '">' . "\n";
    }

    // =========================================================================
    // MINIFICATEUR CSS
    // =========================================================================

    public static function minify_css( string $css ): string {
        // Supprimer commentaires /* ... */
        $css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
        // Supprimer @charset redondant (garder le premier)
        $charsets = [];
        $css = preg_replace_callback('/@charset\s+["\'][^"\']+["\']\s*;/i', function($m) use (&$charsets) {
            if (empty($charsets)) { $charsets[] = $m[0]; return $m[0]; }
            return '';
        }, $css);
        // Normaliser espaces blancs
        $css = preg_replace( '/\s+/', ' ', $css );
        // Supprimer espaces autour des caractères structuraux
        $css = preg_replace( '/\s*([\{\}:;,>~\+])\s*/', '$1', $css );
        // Supprimer point-virgule avant }
        $css = str_replace( ';}', '}', $css );
        // Supprimer espace dans les parenthèses
        $css = preg_replace( '/\(\s+/', '(', $css );
        $css = preg_replace( '/\s+\)/', ')', $css );
        // Supprimer 0 inutile devant décimale (0.5 → .5)
        $css = preg_replace( '/(:|\s)0\.(\d+)/', '$1.$2', $css );
        // Supprimer unité sur valeur 0 (0px → 0)
        $css = preg_replace( '/\b0(px|em|rem|%|pt|vh|vw)\b/', '0', $css );
        return trim($css);
    }

    /**
     * Convertit les URLs relatives en URLs absolues dans le CSS
     * pour éviter les chemins cassés une fois le fichier déplacé en cache.
     */
    private static function fix_relative_urls( string $css, string $base_url ): string {
        return preg_replace_callback(
            '/url\(\s*[\'"]?([^\'"\)\s]+)[\'"]?\s*\)/i',
            function ( $m ) use ( $base_url ) {
                $url = $m[1];
                // Conserver : absolu, data URI, racine-relatif, protocole-relatif
                if ( preg_match( '/^(https?:|data:|\/\/|\/)/', $url ) ) {
                    return $m[0];
                }
                return 'url("' . $base_url . $url . '")';
            },
            $css
        );
    }

    // =========================================================================
    // MINIFICATEUR JS (conservateur)
    // =========================================================================

    public static function minify_js( string $js ): string {
        // Préserver les chaînes de caractères pour ne pas y toucher
        $preserved = [];
        $idx       = 0;

        $js = preg_replace_callback(
            '/(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|`(?:[^`\\\\]|\\\\.)*`)/s',
            function ( $m ) use ( &$preserved, &$idx ) {
                $key            = '__ALESTASTR' . $idx . '__';
                $preserved[$key] = $m[0];
                $idx++;
                return $key;
            },
            $js
        );

        // Supprimer commentaires /* ... */
        $js = preg_replace( '/\/\*[\s\S]*?\*\//', '', $js );
        // Supprimer commentaires // (mais pas les URL http://)
        $js = preg_replace( '/(?<![\'":])\/\/(?![\/:]).*$/m', '', $js );
        // Réduire les espaces/tabulations en un seul espace
        $js = preg_replace( '/[ \t]+/', ' ', $js );
        // Supprimer espaces en début et fin de ligne
        $js = preg_replace( '/^[ \t]+|[ \t]+$/m', '', $js );
        // Supprimer lignes vides
        $js = preg_replace( '/\n{2,}/', "\n", $js );

        // Restaurer les chaînes
        foreach ( $preserved as $key => $val ) {
            $js = str_replace( $key, $val, $js );
        }

        return trim($js);
    }

    // =========================================================================
    // MINIFICATEUR HTML
    // =========================================================================

    public static function minify_html( string $html, array $s = [] ): string {
        if ( empty($s) ) $s = self::settings();

        $preserved = [];
        $idx       = 0;

        // Préserver : <script>, <style>, <pre>, <textarea>
        $html = preg_replace_callback(
            '/<(script|style|pre|textarea)(\s[^>]*)?>[\s\S]*?<\/\1>/i',
            function ( $m ) use ( &$preserved, &$idx ) {
                $key             = '<!--ALESTAGARD' . $idx . '-->';
                $preserved[$key] = $m[0];
                $idx++;
                return $key;
            },
            $html
        );

        if ( ! empty($s['html_remove_comments']) ) {
            // Supprimer commentaires HTML sauf : IE conditionnels, noindex WordPress
            $html = preg_replace(
                '/<!--(?!\[if|\s*noindex|\s*\/noindex|\s*wp:)[\s\S]*?-->/',
                '',
                $html
            );
        }

        if ( ! empty($s['html_remove_whitespace']) ) {
            // Réduire les espaces entre balises
            $html = preg_replace( '/>\s{2,}</', '> <', $html );
            // Supprimer espaces et lignes vides en début de ligne
            $html = preg_replace( '/^\s+/m', '', $html );
        }

        // Restaurer les blocs préservés
        foreach ( $preserved as $key => $val ) {
            $html = str_replace( $key, $val, $html );
        }

        return $html;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Convertit une URL locale en chemin absolu sur le serveur.
     */
    private static function src_to_local_path( string $src ): string {
        $src  = strtok($src, '?'); // retirer query string
        $root = site_url();

        // Uniquement les fichiers locaux
        if ( strpos($src, $root) === 0 ) {
            $relative = substr($src, strlen($root));
        } elseif ( strpos($src, '//') === 0 ) {
            // Protocol-relative — supposer local si même domaine
            $domain = wp_parse_url($root, PHP_URL_HOST);
            if ( strpos($src, '//' . $domain) === 0 ) {
                $relative = substr($src, strlen('//' . $domain));
            } else {
                return '';
            }
        } else {
            return ''; // Externe
        }

        // Resolving a public CSS/JS URL back to its on-disk location. The file
        // could live anywhere under the WordPress root (theme, plugin, mu-plugin,
        // uploads). get_home_path() is the official WP helper for this — ABSPATH
        // would also work but triggers PHPCS. wp_upload_dir() only covers /uploads/.
        if ( ! function_exists( 'get_home_path' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
        }
        $path = rtrim( get_home_path(), '/\\' ) . '/' . ltrim($relative, '/');
        return ( file_exists($path) && is_file($path) ) ? $path : '';
    }

    private static function get_cache_path( string $local_path, string $ext ): string {
        $hash = substr( md5($local_path), 0, 8 );
        $name = pathinfo($local_path, PATHINFO_FILENAME);
        // Nettoyer le nom (enlever ".min" si présent)
        $name = str_replace('.min', '', $name);
        return self::CACHE_DIR . $name . '-' . $hash . '.min.' . $ext;
    }

    public static function ensure_cache_dir(): bool {
        if ( ! is_dir(self::CACHE_DIR) ) {
            wp_mkdir_p(self::CACHE_DIR);
        }
        return is_dir(self::CACHE_DIR) && wp_is_writable(self::CACHE_DIR);
    }

    private static function is_excluded( string $handle, string $excludes ): bool {
        if ( empty(trim($excludes)) ) return false;
        $list = array_filter( array_map('trim', explode(',', $excludes)) );
        foreach ( $list as $exc ) {
            if ( strpos($handle, $exc) !== false ) return true;
        }
        return false;
    }

    // =========================================================================
    // STATS CACHE
    // =========================================================================

    public static function get_stats(): array {
        if ( ! is_dir(self::CACHE_DIR) ) {
            return ['css_files' => 0, 'js_files' => 0, 'total_files' => 0, 'total_size' => '0 Ko'];
        }

        $css_files = glob(self::CACHE_DIR . '*.min.css');
        $js_files  = glob(self::CACHE_DIR . '*.min.js');
        $css_count = is_array($css_files) ? count($css_files) : 0;
        $js_count  = is_array($js_files)  ? count($js_files)  : 0;

        $size = 0;
        $all  = array_merge(
            is_array($css_files) ? $css_files : [],
            is_array($js_files)  ? $js_files  : []
        );
        foreach ( $all as $f ) $size += filesize($f);

        return [
            'css_files'   => $css_count,
            'js_files'    => $js_count,
            'total_files' => $css_count + $js_count,
            'total_size'  => round($size / 1024, 1) . ' Ko',
        ];
    }

    private static function do_clear_cache(): int {
        if ( ! is_dir(self::CACHE_DIR) ) return 0;
        $files = glob(self::CACHE_DIR . '*.min.*');
        $count = 0;
        if ( is_array($files) ) {
            foreach ( $files as $f ) {
                if ( unlink($f) ) $count++; // phpcs:ignore WordPress.WP.AlternativeFunctions
            }
        }
        return $count;
    }

    // =========================================================================
    // AJAX
    // =========================================================================

    public static function ajax_toggle(): void {
        check_ajax_referer('alesta_minify_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Accès refusé.']);

        $type    = sanitize_key( wp_unslash($_POST['type']  ?? '') );
        $value   = ! empty( sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) ) );
        $allowed = ['css_enabled', 'js_enabled', 'html_enabled', 'preload_enabled'];

        if ( ! in_array($type, $allowed, true) ) wp_send_json_error(['message' => 'Type invalide.']);

        $s        = self::settings();
        $s[$type] = $value;
        update_option(self::OPT, $s);
        wp_send_json_success();
    }

    public static function ajax_save(): void {
        check_ajax_referer('alesta_minify_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Accès refusé.']);

        $type = sanitize_key( wp_unslash($_POST['type'] ?? '') );
        $s    = self::settings();

        switch ( $type ) {
            case 'css':
                $s['css_excludes'] = sanitize_textarea_field( wp_unslash($_POST['excludes'] ?? '') );
                break;
            case 'js':
                $s['js_excludes'] = sanitize_textarea_field( wp_unslash($_POST['excludes'] ?? '') );
                break;
            case 'html':
                $s['html_remove_comments']   = ! empty( sanitize_text_field( wp_unslash( $_POST['remove_comments']   ?? '' ) ) );
                $s['html_remove_whitespace'] = ! empty( sanitize_text_field( wp_unslash( $_POST['remove_whitespace'] ?? '' ) ) );
                break;
            case 'preload':
                $s['preload_mode']     = sanitize_key( wp_unslash($_POST['preload_mode'] ?? 'all') );
                $s['preload_handles']  = sanitize_text_field( wp_unslash($_POST['preload_handles'] ?? '') );
                $s['preload_excludes'] = sanitize_text_field( wp_unslash($_POST['preload_excludes'] ?? '') );
                break;
            default:
                wp_send_json_error(['message' => 'Type inconnu.']);
        }

        update_option(self::OPT, $s);
        self::do_clear_cache();
        wp_send_json_success(['message' => 'Paramètres enregistrés. Cache vidé.']);
    }

    public static function ajax_clear_cache(): void {
        check_ajax_referer('alesta_minify_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Accès refusé.']);

        $count = self::do_clear_cache();
        $stats = self::get_stats();
        wp_send_json_success([
            'message' => $count . ' fichier(s) supprimé(s).',
            'stats'   => $stats,
        ]);
    }

    public static function ajax_get_stats(): void {
        check_ajax_referer('alesta_minify_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error();
        wp_send_json_success( self::get_stats() );
    }
}
