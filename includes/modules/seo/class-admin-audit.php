<?php
defined('ABSPATH') || exit;

/**
 * Audit SEO — handlers AJAX + onglet "Audit SEO" de la page Title & Meta
 * (Alesta Free). Porté depuis Alesta AI Pro (Alesta_AI_Admin_Audit).
 *
 * Le dernier audit est persisté dans l'option 'alesta_ai_last_audit'
 * (même clé que le Pro).
 */
class Alesta_Admin_Audit {

    const LAST_AUDIT_KEY = 'alesta_ai_last_audit';

    /** @var bool Hooks enregistrés une seule fois. */
    private static $hooked = false;

    public function __construct() {
        if ( self::$hooked ) {
            return;
        }
        self::$hooked = true;

        // Noms d'actions distincts de ceux du Pro (alesta_run_audit, ...).
        add_action('wp_ajax_alesta_audit_run',      [$this, 'ajax_run_audit']);
        add_action('wp_ajax_alesta_audit_one',      [$this, 'ajax_audit_post']);
        add_action('wp_ajax_alesta_audit_generate', [$this, 'ajax_generate_seo']);
        add_action('wp_ajax_alesta_audit_apply',    [$this, 'ajax_apply_seo']);
        add_action('wp_ajax_alesta_audit_last',     [$this, 'ajax_get_last_audit']);

        // Note : l'injection des balises SEO (title, meta, canonical, robots)
        // est gérée par Alesta_Meta_Module. Cette classe n'accroche pas wp_head.
    }

    private function nonce_name(): string {
        return class_exists('Alesta_Meta_Module') ? Alesta_Meta_Module::NONCE : 'alesta_meta_nonce';
    }

    private function auditor(): Alesta_Audit {
        if ( ! class_exists('Alesta_Audit') ) {
            require_once ALESTA_PLUGIN_DIR . 'includes/modules/seo/class-audit.php';
        }
        return new Alesta_Audit();
    }

    // ── Onglet Audit SEO ──────────────────────────────────────────────────────

    /**
     * Contenu de l'onglet Audit SEO, sans le wrapping .wrap/.alesta-wrap.
     * Appelé depuis la page fusionnée Title & Meta.
     */
    public static function render_audit_tab(): void {
        ?>
            <div class="alesta-header">
                <div class="alesta-logo">
                    <span class="dashicons dashicons-chart-bar" style="font-size:32px;width:32px;height:32px;color:#1e3a5f;"></span>
                    <div>
                        <h1><?php esc_html_e('Audit du site', 'alesta'); ?></h1>
                        <p><?php esc_html_e('Analyse SEO, liens cassés, images, contenu et performance', 'alesta'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Options -->
            <div class="alesta-card" style="flex-direction:column;align-items:flex-start;gap:14px;margin-bottom:1.5rem;">
                <strong><?php esc_html_e('Périmètre de l\'audit', 'alesta'); ?></strong>
                <div style="display:flex;gap:24px;flex-wrap:wrap;">
                    <div>
                        <div style="font-size:12px;color:#6b7280;margin-bottom:6px;"><?php esc_html_e('Types de contenu', 'alesta'); ?></div>
                        <label class="alesta-toggle"><input type="checkbox" class="audit-type" value="page" checked> <?php esc_html_e('Pages', 'alesta'); ?></label>
                        <label class="alesta-toggle" style="margin-top:4px;"><input type="checkbox" class="audit-type" value="post" checked> <?php esc_html_e('Articles', 'alesta'); ?></label>
                        <?php if ( post_type_exists('product') ) : ?>
                        <label class="alesta-toggle" style="margin-top:4px;"><input type="checkbox" class="audit-type" value="product"> <?php esc_html_e('Produits', 'alesta'); ?></label>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-size:12px;color:#6b7280;margin-bottom:6px;"><?php esc_html_e('Vérifications', 'alesta'); ?></div>
                        <label class="alesta-toggle"><input type="checkbox" class="audit-check" value="seo" checked> <?php esc_html_e('SEO (title, meta, H1/H2)', 'alesta'); ?></label>
                        <label class="alesta-toggle" style="margin-top:4px;"><input type="checkbox" class="audit-check" value="images" checked> <?php esc_html_e('Images sans alt', 'alesta'); ?></label>
                        <label class="alesta-toggle" style="margin-top:4px;"><input type="checkbox" class="audit-check" value="content" checked> <?php esc_html_e('Contenu trop court', 'alesta'); ?></label>
                        <label class="alesta-toggle" style="margin-top:4px;"><input type="checkbox" class="audit-check" value="links" checked> <?php esc_html_e('Liens cassés', 'alesta'); ?></label>
                        <label class="alesta-toggle" style="margin-top:4px;"><input type="checkbox" class="audit-check" value="performance" checked> <?php esc_html_e('Performance', 'alesta'); ?></label>
                    </div>
                </div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button id="btn-run-audit" class="button button-primary button-large">&#128269; <?php esc_html_e('Lancer l\'audit', 'alesta'); ?></button>
                    <span id="audit-status" style="font-size:13px;color:#6b7280;display:none;"><?php esc_html_e('Analyse en cours...', 'alesta'); ?></span>
                    <span id="last-audit-info" style="font-size:12px;color:#6b7280;display:none;margin-left:6px;"></span>
                </div>
            </div>

            <!-- Résultats -->
            <div id="audit-results" style="display:none;">

                <!-- Score global -->
                <div class="alesta-stats-row" id="audit-summary"></div>

                <!-- Onglets -->
                <div class="audit-tabs" style="margin:1.5rem 0 0;">
                    <button class="audit-tab active" data-tab="seo"><?php esc_html_e('SEO', 'alesta'); ?></button>
                    <button class="audit-tab" data-tab="images"><?php esc_html_e('Images', 'alesta'); ?></button>
                    <button class="audit-tab" data-tab="content"><?php esc_html_e('Contenu', 'alesta'); ?></button>
                    <button class="audit-tab" data-tab="links"><?php esc_html_e('Liens', 'alesta'); ?></button>
                    <button class="audit-tab" data-tab="performance"><?php esc_html_e('Performance', 'alesta'); ?></button>
                </div>

                <div id="tab-seo"         class="audit-tab-content active"></div>
                <div id="tab-images"      class="audit-tab-content" style="display:none;"></div>
                <div id="tab-content"     class="audit-tab-content" style="display:none;"></div>
                <div id="tab-links"       class="audit-tab-content" style="display:none;"></div>
                <div id="tab-performance" class="audit-tab-content" style="display:none;"></div>
            </div>

        <!-- Modal suggestions Claude -->
        <div id="seo-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:12px;padding:1.5rem;width:620px;max-width:95vw;max-height:90vh;overflow-y:auto;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                    <strong id="modal-title" style="font-size:15px;"></strong>
                    <button type="button" id="seo-modal-close" class="button">&#10005;</button>
                </div>
                <div id="modal-content"></div>
            </div>
        </div>
        <?php
    }

    // ── AJAX : lancer l'audit ─────────────────────────────────────────────────

    public function ajax_run_audit(): void {
        check_ajax_referer($this->nonce_name(), 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => __('Accès refusé.', 'alesta')]);
        }

        if ( function_exists('set_time_limit') ) {
            set_time_limit(300); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- audit long (vérification des liens)
        }

        $post_types = isset($_POST['post_types']) ? array_map('sanitize_key', (array) wp_unslash($_POST['post_types'])) : ['page', 'post'];
        $checks     = isset($_POST['checks'])     ? array_map('sanitize_key', (array) wp_unslash($_POST['checks']))     : ['seo', 'images', 'content', 'links', 'performance'];

        $results = $this->auditor()->run(['post_types' => $post_types, 'checks' => $checks]);

        // Persistance : le dernier audit se réaffiche au retour sur la page.
        update_option(self::LAST_AUDIT_KEY, [
            'timestamp'  => current_time('mysql'),
            'post_types' => $post_types,
            'checks'     => $checks,
            'results'    => $results,
        ], false);

        wp_send_json_success($results);
    }

    // ── AJAX : récupérer le dernier audit persisté ───────────────────────────

    public function ajax_get_last_audit(): void {
        check_ajax_referer($this->nonce_name(), 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => __('Accès refusé.', 'alesta')]);
        }

        $last = get_option(self::LAST_AUDIT_KEY);
        if ( ! is_array($last) || empty($last['results']) ) {
            wp_send_json_success(null);
        }
        wp_send_json_success($last);
    }

    // ── AJAX : générer suggestions SEO Claude pour 1 post ────────────────────

    public function ajax_generate_seo(): void {
        check_ajax_referer($this->nonce_name(), 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => __('Accès refusé.', 'alesta')]);
        }

        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if ( ! $post_id ) {
            wp_send_json_error(['message' => __('ID manquant.', 'alesta')]);
        }

        $result = $this->auditor()->generate_seo_suggestions($post_id);
        if ( is_wp_error($result) ) {
            wp_send_json_error(Alesta_API::error_payload($result));
        }
        wp_send_json_success($result);
    }

    // ── AJAX : appliquer title + meta ─────────────────────────────────────────

    public function ajax_apply_seo(): void {
        check_ajax_referer($this->nonce_name(), 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => __('Accès refusé.', 'alesta')]);
        }

        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $title   = sanitize_text_field( isset($_POST['title'])   ? wp_unslash($_POST['title'])   : '' );
        $meta    = sanitize_text_field( isset($_POST['meta'])    ? wp_unslash($_POST['meta'])    : '' );
        $keyword = sanitize_text_field( isset($_POST['keyword']) ? wp_unslash($_POST['keyword']) : '' );

        if ( ! $post_id ) {
            wp_send_json_error(['message' => __('ID manquant.', 'alesta')]);
        }

        $this->auditor()->apply_seo_fields($post_id, $title, $meta, $keyword);

        wp_send_json_success(['message' => __('Modifications appliquées.', 'alesta')]);
    }

    // ── AJAX : ré-audit d'un seul post (rafraîchissement d'une ligne) ────────

    public function ajax_audit_post(): void {
        check_ajax_referer($this->nonce_name(), 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => __('Accès refusé.', 'alesta')]);
        }

        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $post    = $post_id ? get_post($post_id) : null;
        if ( ! $post || ! in_array($post->post_type, ['page', 'post', 'product'], true) ) {
            wp_send_json_error(['message' => __('Contenu invalide.', 'alesta')]);
        }

        $seo = [];
        $this->auditor()->audit_seo($post, $seo);
        $updated = $seo[0] ?? [];

        // Propager la mise à jour dans le dernier audit persisté.
        if ( $updated ) {
            $last = get_option(self::LAST_AUDIT_KEY);
            if ( is_array($last) && ! empty($last['results']['seo']) && is_array($last['results']['seo']) ) {
                $found = false;
                foreach ($last['results']['seo'] as $idx => $it) {
                    if ( (int) ($it['post_id'] ?? 0) === $post_id ) {
                        $last['results']['seo'][$idx] = $updated;
                        $found = true;
                        break;
                    }
                }
                if ( ! $found ) {
                    $last['results']['seo'][] = $updated;
                }
                $last['results']['summary'] = Alesta_Audit::build_summary($last['results']);
                update_option(self::LAST_AUDIT_KEY, $last, false);
            }
        }

        wp_send_json_success($updated);
    }
}
