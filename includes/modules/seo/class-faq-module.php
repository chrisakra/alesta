<?php
defined('ABSPATH') || exit;

/**
 * FAQ Schema — Module (Alesta Free)
 *
 * Génère des questions/réponses via Claude (clé API de l'utilisateur, BYOK)
 * et injecte le JSON-LD FAQPage dans le <head> des pages publiques.
 *
 * Données partagées avec la version Pro (même clé post meta `_alesta_faq_schema`)
 * afin qu'un passage Free -> Pro conserve les FAQ générées.
 *
 * Ne pas instancier si la classe Pro `Alesta_AI_FAQ_Module` existe.
 *
 * PHP 7.4 compatible.
 */
class Alesta_FAQ_Module {

    /** Clé post meta (identique à la Pro : données partagées). */
    const META_KEY = '_alesta_faq_schema';

    /** Action du nonce AJAX propre au module Free. */
    const NONCE_ACTION = 'alesta_faq_nonce';

    public function __construct() {
        add_action('wp_ajax_alesta_faq_schema_generate', [$this, 'ajax_generate']);
        add_action('wp_ajax_alesta_faq_schema_save',     [$this, 'ajax_save']);
        add_action('wp_ajax_alesta_faq_schema_delete',   [$this, 'ajax_delete']);
        add_action('wp_ajax_alesta_faq_schema_toggle',   [$this, 'ajax_toggle']);
        add_action('wp_ajax_alesta_faq_schema_test_api', [$this, 'ajax_test_api']);
        // Injection JSON-LD dans le <head> des pages publiques
        add_action('wp_head', [$this, 'output_faq_schema'], 5);
    }

    // =========================================================================
    // Injection JSON-LD dans le <head>
    // =========================================================================
    public function output_faq_schema(): void {
        if (!is_singular()) return;
        $post_id = get_the_ID();
        if (!$post_id) return;

        $data = get_post_meta($post_id, self::META_KEY, true);
        if (!is_array($data) || empty($data['active']) || empty($data['faqs']) || !is_array($data['faqs'])) return;

        $items = [];
        foreach ($data['faqs'] as $faq) {
            if (!is_array($faq) || empty($faq['q']) || empty($faq['a'])) continue;
            $items[] = [
                '@type'          => 'Question',
                'name'           => wp_strip_all_tags($faq['q']),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags($faq['a']),
                ],
            ];
        }
        if (empty($items)) return;

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $items,
        ];

        // wp_json_encode produit un JSON sûr ; les balises </script> sont neutralisées
        // par l'échappement des slashes (JSON_UNESCAPED_SLASHES volontairement absent).
        $json = wp_json_encode($schema, JSON_UNESCAPED_UNICODE);
        if (!$json) return;
        echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON généré par wp_json_encode.
    }

    // =========================================================================
    // Vérifications communes aux handlers AJAX
    // =========================================================================
    private function check_request(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Accès refusé.', 'alesta')]);
        }
    }

    private function get_post_id(): int {
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié dans check_request().
        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(['message' => __('Identifiant de contenu manquant ou invalide.', 'alesta')]);
        }
        return $post_id;
    }

    // =========================================================================
    // AJAX : Test de connexion API (badge de statut sur la page admin)
    // =========================================================================
    public function ajax_test_api(): void {
        $this->check_request();

        if (!class_exists('Alesta_API')) {
            wp_send_json_error(['message' => __('Client API Alesta indisponible.', 'alesta')]);
        }

        $api = new Alesta_API();
        if (!method_exists($api, 'test_connection')) {
            wp_send_json_success(['message' => __('Client API charge.', 'alesta')]);
        }

        $result = $api->test_connection();
        if (is_wp_error($result)) {
            wp_send_json_error(Alesta_API::error_payload($result));
        }
        wp_send_json_success(['message' => __('Connexion OK', 'alesta')]);
    }

    // =========================================================================
    // AJAX : Générer FAQ via Claude
    // =========================================================================
    public function ajax_generate(): void {
        $this->check_request();
        $post_id = $this->get_post_id();

        if (!class_exists('Alesta_API')) {
            wp_send_json_error(['message' => __('Client API Alesta indisponible.', 'alesta')]);
        }

        $post    = get_post($post_id);
        $content = wp_strip_all_tags($post->post_content);
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $excerpt = mb_substr(trim(preg_replace('/\s+/u', ' ', $content)), 0, 1500);
        $type    = $post->post_type;

        // Infos produit WooCommerce
        $product_info = '';
        if ($type === 'product' && function_exists('wc_get_product')) {
            $product = wc_get_product($post_id);
            if ($product) {
                $price  = $product->get_price();
                $symbol = function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8') : '';
                $cats   = wp_get_post_terms($post_id, 'product_cat', ['fields' => 'names']);
                if (is_wp_error($cats)) $cats = [];
                $product_info  = 'Prix: ' . $price . $symbol . '. ';
                $product_info .= 'Categories: ' . implode(', ', $cats) . '. ';
                if ($product->get_short_description()) {
                    $product_info .= 'Description courte: ' . wp_strip_all_tags($product->get_short_description()) . '.';
                }
            }
        }

        $prompt = "Tu es un expert SEO. Genere 4 questions/reponses FAQ pertinentes pour cette page.\n\n"
                . "Titre: " . html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8') . "\n"
                . "Type: " . $type . "\n"
                . ($product_info ? $product_info . "\n" : '')
                . ($excerpt ? "Contenu:\n" . $excerpt . "\n\n" : '')
                . "Regles:\n"
                . "- Questions naturelles qu'un visiteur poserait vraiment\n"
                . "- Reponses claires entre 40 et 100 mots\n"
                . "- Pas de contenu promotionnel excessif\n"
                . "- En francais\n\n"
                . "Retourne uniquement ce JSON (sans markdown):\n"
                . '{"faqs":[{"q":"Question 1?","a":"Reponse 1."},{"q":"Question 2?","a":"Reponse 2."},{"q":"Question 3?","a":"Reponse 3."},{"q":"Question 4?","a":"Reponse 4."}]}';

        /**
         * Permet de modifier le prompt de génération FAQ.
         *
         * @param string  $prompt  Prompt envoyé à Claude.
         * @param WP_Post $post    Contenu concerné.
         */
        $prompt = apply_filters('alesta_faq_prompt', $prompt, $post);

        $api      = new Alesta_API();
        $response = $api->ask($prompt, 800);
        if (is_wp_error($response)) {
            wp_send_json_error(Alesta_API::error_payload($response));
        }

        if (is_string($response) && preg_match('#\{.+\}#s', $response, $m)) {
            $decoded = json_decode($m[0], true);
            if (!empty($decoded['faqs']) && is_array($decoded['faqs'])) {
                $faqs = [];
                foreach ($decoded['faqs'] as $faq) {
                    if (!is_array($faq)) continue;
                    $q = sanitize_text_field((string) ($faq['q'] ?? ''));
                    $a = sanitize_textarea_field((string) ($faq['a'] ?? ''));
                    if ($q && $a) $faqs[] = ['q' => $q, 'a' => $a];
                }
                if ($faqs) {
                    wp_send_json_success(['faqs' => $faqs]);
                }
            }
        }
        wp_send_json_error(['message' => __('Réponse Claude invalide.', 'alesta')]);
    }

    // =========================================================================
    // AJAX : Sauvegarder les FAQ
    // =========================================================================
    public function ajax_save(): void {
        $this->check_request();
        $post_id = $this->get_post_id();

        $faqs_raw = [];
        if (isset($_POST['faqs']) && is_array($_POST['faqs'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié dans check_request().
            $faqs_raw = map_deep(wp_unslash($_POST['faqs']), 'sanitize_textarea_field'); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitisé via map_deep.
        }

        $faqs = [];
        foreach ($faqs_raw as $faq) {
            if (!is_array($faq)) continue;
            $q = sanitize_text_field((string) ($faq['q'] ?? ''));
            $a = sanitize_textarea_field((string) ($faq['a'] ?? ''));
            if ($q && $a) $faqs[] = ['q' => $q, 'a' => $a];
        }

        $existing = get_post_meta($post_id, self::META_KEY, true);
        if (!is_array($existing)) $existing = [];

        $data = [
            'faqs'   => $faqs,
            'active' => isset($existing['active']) ? (bool) $existing['active'] : true,
            'date'   => current_time('mysql'),
        ];
        update_post_meta($post_id, self::META_KEY, $data);

        wp_send_json_success([
            'message' => __('FAQ sauvegardée.', 'alesta'),
            'count'   => count($faqs),
            'active'  => $data['active'],
        ]);
    }

    // =========================================================================
    // AJAX : Supprimer les FAQ d'un post
    // =========================================================================
    public function ajax_delete(): void {
        $this->check_request();
        $post_id = $this->get_post_id();

        delete_post_meta($post_id, self::META_KEY);
        wp_send_json_success(['message' => __('FAQ supprimée.', 'alesta')]);
    }

    // =========================================================================
    // AJAX : Activer / désactiver le schema sur une page
    // =========================================================================
    public function ajax_toggle(): void {
        $this->check_request();
        $post_id = $this->get_post_id();
        $active  = !empty($_POST['active']) && '0' !== $_POST['active']; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- nonce vérifié dans check_request(), valeur convertie en booléen.

        $data = get_post_meta($post_id, self::META_KEY, true);
        if (!is_array($data)) $data = [];
        $data['active'] = $active;
        update_post_meta($post_id, self::META_KEY, $data);

        wp_send_json_success(['active' => $active]);
    }

    // =========================================================================
    // Helper
    // =========================================================================
    public static function get_faq(int $post_id): array {
        $data = get_post_meta($post_id, self::META_KEY, true);
        if (!is_array($data)) return ['faqs' => [], 'active' => false, 'date' => ''];
        return [
            'faqs'   => isset($data['faqs']) && is_array($data['faqs']) ? $data['faqs'] : [],
            'active' => !empty($data['active']),
            'date'   => isset($data['date']) ? (string) $data['date'] : '',
        ];
    }
}
