<?php
defined('ABSPATH') || exit;

class Alesta_Errors_Module {

    const RESULTS_KEY   = 'alesta_errors_scan_results';
    const SCAN_DATE_KEY = 'alesta_errors_scan_date';

    public function __construct() {
        add_action('wp_ajax_alesta_errors_scan',      [$this, 'ajax_scan']);
        add_action('wp_ajax_alesta_errors_scan_post', [$this, 'ajax_scan_post']);
        add_action('wp_ajax_alesta_errors_test_url',  [$this, 'ajax_test_url']);
        add_action('wp_ajax_alesta_errors_fix',       [$this, 'ajax_fix']);
    }

    // =========================================================================
    // AJAX : Etape 1 - Retourner la liste des posts a scanner
    // =========================================================================
    public function ajax_scan(): void {
        check_ajax_referer('alesta_errors_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $posts = get_posts([
            'post_type'      => ['post', 'page', 'product'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        // Reinitialiser le rapport
        update_option(self::RESULTS_KEY, [
            'errors'        => [],
            'total_checked' => 0,
            'total_errors'  => 0,
            'total_ok'      => 0,
            'pages_scanned' => count($posts),
            'date'          => current_time('mysql'),
        ]);

        wp_send_json_success([
            'post_ids' => array_values($posts),
            'total'    => count($posts),
        ]);
    }

    // =========================================================================
    // AJAX : Etape 2 - Scanner les liens d'un seul post
    // =========================================================================
    public function ajax_scan_post(): void {
        check_ajax_referer('alesta_errors_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $post_id = absint( isset( $_POST['post_id'] ) ? wp_unslash( $_POST['post_id'] ) : 0 );
        if (!$post_id) wp_send_json_success(['checked' => 0, 'errors_found' => 0]);

        $post    = get_post($post_id);
        if (!$post) wp_send_json_success(['checked' => 0, 'errors_found' => 0]);

        $url     = get_permalink($post_id);
        $errors  = [];
        $checked = 0;
        $ok      = 0;

        // Contenu WordPress standard + donnees Elementor
        $raw_content = $post->post_content;
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        if (!empty($elementor_data)) {
            // Extraire toutes les URLs du JSON Elementor
            preg_match_all('#"url"\s*:\s*"([^"]+)"#', $elementor_data, $el_m);
            foreach ($el_m[1] ?? [] as $el_url) {
                $el_url = stripslashes($el_url);
                if (strpos($el_url, 'http') === 0) {
                    $raw_content .= ' href="' . esc_url($el_url) . '"';
                }
            }
        }
        preg_match_all('#href=["\']([^"\']+)["\']#i', $raw_content, $matches);
        $links = array_unique($matches[1] ?? []);

        foreach ($links as $link) {
            // Ignorer anchors, tel:, mailto:, javascript:, variables
            if (preg_match('#^(#|tel:|mailto:|javascript:|{|\?|&)#i', $link)) continue;
            // Construire URL absolue si relative
            if (strpos($link, 'http') !== 0) {
                $link = rtrim(get_site_url(), '/') . '/' . ltrim($link, '/');
            }
            $checked++;
            $result = $this->check_url($link);
            if ($result['code'] >= 400 || $result['code'] === 0) {
                $errors[] = [
                    'source_id'    => $post_id,
                    'source_title' => $post->post_title,
                    'source_type'  => $post->post_type,
                    'source_url'   => $url,
                    'broken_url'   => $link,
                    'code'         => $result['code'],
                    'message'      => $result['message'],
                ];
            } else {
                $ok++;
            }
        }

        // Mettre a jour le rapport cumule
        $report = get_option(self::RESULTS_KEY, [
            'errors' => [], 'total_checked' => 0,
            'total_errors' => 0, 'total_ok' => 0, 'pages_scanned' => 0,
        ]);
        $report['errors']        = array_merge($report['errors'] ?? [], $errors);
        $report['total_checked'] = ($report['total_checked'] ?? 0) + $checked;
        $report['total_errors']  = count($report['errors']);
        $report['total_ok']      = ($report['total_ok'] ?? 0) + $ok;
        $report['date']          = current_time('mysql');
        update_option(self::RESULTS_KEY, $report);

        wp_send_json_success([
            'errors_found' => count($errors),
            'checked'      => $checked,
        ]);
    }

    // =========================================================================
    // AJAX : Tester une URL unique
    // =========================================================================
    public function ajax_test_url(): void {
        check_ajax_referer('alesta_errors_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $url = esc_url_raw( isset( $_POST['url'] ) ? wp_unslash( $_POST['url'] ) : '' );
        if (!$url) wp_send_json_error(['message' => 'URL manquante']);

        wp_send_json_success($this->check_url($url));
    }

    // =========================================================================
    // AJAX : Corriger un lien casse dans un post
    // =========================================================================
    public function ajax_fix(): void {
        check_ajax_referer('alesta_errors_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $post_id = absint( isset( $_POST['post_id'] ) ? wp_unslash( $_POST['post_id'] ) : 0 );
        $old_url = esc_url_raw( isset( $_POST['old_url'] ) ? wp_unslash( $_POST['old_url'] ) : '' );
        $new_url = esc_url_raw( isset( $_POST['new_url'] ) ? wp_unslash( $_POST['new_url'] ) : '' );

        if (!$post_id || !$old_url || !$new_url) {
            wp_send_json_error(['message' => 'Donnees manquantes']);
        }

        $post = get_post($post_id);
        if (!$post) wp_send_json_error(['message' => 'Post introuvable']);

        $fixed = false;

        // 1. Remplacer dans post_content (Gutenberg / classic editor)
        $new_content = str_replace($old_url, $new_url, $post->post_content);
        if ($new_content !== $post->post_content) {
            wp_update_post(['ID' => $post_id, 'post_content' => $new_content]);
            $fixed = true;
        }

        // 2. Remplacer dans _elementor_data (Elementor)
        $el_data = get_post_meta($post_id, '_elementor_data', true);
        if (!empty($el_data)) {
            // Elementor echappe les URLs en JSON, tester les deux formes
            $new_el = str_replace(
                [addslashes($old_url), $old_url],
                [addslashes($new_url), $new_url],
                $el_data
            );
            if ($new_el !== $el_data) {
                update_post_meta($post_id, '_elementor_data', wp_slash($new_el));
                delete_post_meta($post_id, '_elementor_css'); // Vider cache CSS Elementor
                $fixed = true;
            }
        }

        if (!$fixed) {
            wp_send_json_error(['message' => 'URL non trouvee (ni dans WordPress ni dans Elementor)']);
        }

        wp_send_json_success(['message' => 'Lien corrige avec succes']);
    }

    // =========================================================================
    // Helper : Verifier le statut HTTP d'une URL
    // =========================================================================
    private function check_url(string $url): array {
        $response = wp_remote_head($url, [
            'timeout'     => 8,
            'redirection' => 5,
            'user-agent'  => 'Alesta-Scanner/1.0',
            'sslverify'   => false,
        ]);

        if (is_wp_error($response)) {
            return ['url' => $url, 'code' => 0, 'message' => $response->get_error_message()];
        }

        return [
            'url'     => $url,
            'code'    => (int)wp_remote_retrieve_response_code($response),
            'message' => wp_remote_retrieve_response_message($response),
        ];
    }

    // =========================================================================
    // Helper : Lire le dernier rapport
    // =========================================================================
    public static function get_last_report(): array {
        $report = get_option(self::RESULTS_KEY, []);
        return is_array($report) ? $report : [];
    }
}
