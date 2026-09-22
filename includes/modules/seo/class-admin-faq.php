<?php
defined('ABSPATH') || exit;

/**
 * FAQ Schema — Interface admin (Alesta Free)
 *
 * Liste des pages / articles / produits avec génération, édition,
 * activation et suppression du schema FAQPage JSON-LD.
 * Guard slug attendu : `alesta-ai-faq` (identique à la Pro).
 *
 * Ne pas instancier si la classe Pro `Alesta_AI_Admin_FAQ` existe.
 *
 * PHP 7.4 compatible.
 */
class Alesta_Admin_FAQ {

    const PAGE_SLUG = 'alesta-ai-faq';

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, self::PAGE_SLUG) === false) return;

        $ver = ALESTA_VERSION;
        if (class_exists('Alesta_API')) {
            Alesta_API::enqueue_key_notice();
        }
        wp_enqueue_script('alesta-faq', plugin_dir_url(ALESTA_PLUGIN_FILE) . 'assets/faq.js', ['jquery'], $ver, true);
        wp_enqueue_style('alesta-faq',  plugin_dir_url(ALESTA_PLUGIN_FILE) . 'assets/faq.css', [], $ver);
        wp_localize_script('alesta-faq', 'AlestaFaq', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(Alesta_FAQ_Module::NONCE_ACTION),
            'i18n'     => [
                'api_ok'              => __('IA connectée', 'alesta'),
                'api_ko'              => __('API non configurée', 'alesta'),
                'api_unreachable'     => __('API injoignable', 'alesta'),
                /* translators: %d: nombre de lignes sélectionnées */
                'selected'            => __('%d sélectionnée(s)', 'alesta'),
                'faq_prefix'          => __('FAQ : ', 'alesta'),
                'edit_prefix'         => __('Modifier FAQ : ', 'alesta'),
                'analyzing'           => __('Claude analyse le contenu de la page...', 'alesta'),
                'generating'          => __('Génération en cours...', 'alesta'),
                'check_and_save'      => __('Vérifiez et sauvegardez les questions générées', 'alesta'),
                'edit_and_save'       => __('Modifiez et sauvegardez', 'alesta'),
                'unknown_error'       => __('Erreur inconnue', 'alesta'),
                'network_error'       => __('Erreur réseau.', 'alesta'),
                'save_error'          => __('Erreur lors de la sauvegarde.', 'alesta'),
                'preview_title'       => __('Aperçu JSON-LD (ce que verra Google)', 'alesta'),
                'no_question'         => __('(aucune question)', 'alesta'),
                'add_question'        => __('+ Ajouter une question', 'alesta'),
                'save_and_activate'   => __('Sauvegarder et activer', 'alesta'),
                'close'               => __('Fermer', 'alesta'),
                'remove'              => __('Supprimer', 'alesta'),
                /* translators: %d: numéro de la question */
                'question_n'          => __('Question %d', 'alesta'),
                'question_ph'         => __('Question du visiteur...', 'alesta'),
                'answer'              => __('Réponse', 'alesta'),
                'answer_ph'           => __('Réponse claire et concise...', 'alesta'),
                'add_at_least_one'    => __('Ajoutez au moins une question/réponse.', 'alesta'),
                'generate'            => __('Générer', 'alesta'),
                'edit'                => __('Modifier', 'alesta'),
                'delete'              => __('Supprimer', 'alesta'),
                'yes'                 => __('Oui', 'alesta'),
                'no'                  => __('Non', 'alesta'),
                /* translators: %s: date de génération */
                'generated_on'        => __('Généré le %s', 'alesta'),
                'confirm_delete'      => __('Supprimer le FAQ Schema de cette page ?', 'alesta'),
                'no_missing_visible'  => __('Aucune page sans FAQ visible.', 'alesta'),
                /* translators: %d: nombre de pages */
                'confirm_batch'       => __('Générer le FAQ Schema pour %d page(s) ?', 'alesta'),
                /* translators: %d: nombre de pages sélectionnées */
                'confirm_batch_sel'   => __("Générer le FAQ Schema pour %d page(s) sélectionnée(s) ?\n(Les pages ayant déjà une FAQ seront régénérées.)", 'alesta'),
                'no_selection'        => __('Aucune ligne sélectionnée.', 'alesta'),
                'no_eligible'         => __("Aucune des lignes sélectionnées n'a de FAQ générée - impossible d'activer / désactiver.\nUtilisez plutôt \"Générer pour la sélection\".", 'alesta'),
                'batch_missing'       => __('Générer manquants en lot', 'alesta'),
                'batch_selected'      => __('Générer pour la sélection', 'alesta'),
                'activate_selected'   => __('Activer la sélection', 'alesta'),
                'deactivate_selected' => __('Désactiver la sélection', 'alesta'),
            ],
        ]);
    }

    /**
     * Détection du plugin SEO tiers actif (badge d'information).
     */
    private function seo_plugin_label(): string {
        if (defined('WPSEO_VERSION')) return 'Yoast SEO';
        if (class_exists('RankMath', false)) return 'RankMath';
        if (get_option('alesta_seo_engine_active', false)) return __('SEO Engine Alesta', 'alesta');
        return '';
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'alesta'));
        }

        $post_types = ['page', 'post'];
        if (post_type_exists('product')) $post_types[] = 'product';

        /**
         * Nombre maximum de contenus listés sur la page FAQ Schema.
         *
         * @param int $max Valeur par défaut : 500.
         */
        $max_posts = (int) apply_filters('alesta_faq_max_posts', 500);

        $posts = get_posts([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $max_posts, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page -- listing admin, borné et filtrable.
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);

        $total    = count($posts);
        $with_faq = 0;
        $active   = 0;
        foreach ($posts as $p) {
            $faq = Alesta_FAQ_Module::get_faq($p->ID);
            if (!empty($faq['faqs'])) {
                $with_faq++;
                if (!empty($faq['active'])) $active++;
            }
        }

        $seo_label   = $this->seo_plugin_label();
        $date_format = get_option('date_format', 'd/m/Y');

        // Clé API du fournisseur IA (BYOK) : sans elle, aucune génération possible.
        $has_key      = ! class_exists('Alesta_API') || Alesta_API::has_stored_key();
        $settings_url = class_exists('Alesta_API')
            ? Alesta_API::settings_url()
            : admin_url('admin.php?page=alesta-ai-settings');
        ?>
        <div class="wrap alesta-wrap" id="alesta-faq-wrap">

            <?php if (!$has_key): ?>
            <div class="notice notice-warning" style="margin:1rem 0 0;">
                <p>
                    <strong><?php esc_html_e('Aucune clé API n\'est configurée pour le fournisseur IA sélectionné.', 'alesta'); ?></strong>
                    <?php esc_html_e('La génération des FAQ nécessite votre clé : rendez-vous dans Alesta AI &rarr; Configuration.', 'alesta'); ?>
                </p>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Configurer la clé API', 'alesta'); ?></a>
                </p>
            </div>
            <?php endif; ?>

            <!-- Header -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;background:#1e3a5f;border-radius:8px;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span style="font-size:28px;color:#a0aec0;font-family:Georgia,serif;">&phi;</span>
                    <div>
                        <h1 style="color:#fff;margin:0;font-size:18px;"><?php esc_html_e('FAQ Schema', 'alesta'); ?></h1>
                        <p style="color:#94a3b8;margin:0;font-size:13px;"><?php esc_html_e('Génération automatique des questions/réponses pour Google', 'alesta'); ?></p>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                    <span id="faq-api-status" style="font-size:12px;padding:4px 12px;background:#e5e7eb;color:#374151;border-radius:20px;border:1px solid #d1d5db;">
                        <?php esc_html_e('Vérification...', 'alesta'); ?>
                    </span>
                    <?php if ($seo_label): ?>
                    <span style="font-size:12px;padding:4px 12px;background:#d1fae5;color:#065f46;border-radius:20px;border:1px solid #6ee7b7;">&#10003; <?php echo esc_html($seo_label); ?></span>
                    <?php endif; ?>
                    <span style="font-size:12px;padding:4px 12px;background:#d1fae5;color:#065f46;border-radius:20px;border:1px solid #6ee7b7;">
                        Schema.org FAQPage
                    </span>
                </div>
            </div>

            <!-- Info banner -->
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:13px;color:#1e40af;">
                <strong><?php esc_html_e('Comment ça fonctionne :', 'alesta'); ?></strong>
                <?php esc_html_e('L\'IA génère des questions/réponses à partir du contenu de chaque page (avec votre propre clé API, Anthropic ou OpenAI). Le schema JSON-LD est injecté automatiquement dans le <head> de la page. Google peut alors afficher ces FAQ directement dans les résultats de recherche.', 'alesta'); ?>
            </div>

            <!-- Stats -->
            <div id="faq-stats" style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;">
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;text-align:center;">
                    <div class="faq-stat-value" style="font-size:28px;font-weight:700;color:#1e3a5f;"><?php echo esc_html($total); ?></div>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px;"><?php esc_html_e('Pages / Produits', 'alesta'); ?></div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;text-align:center;">
                    <div class="faq-stat-value" style="font-size:28px;font-weight:700;color:#065f46;"><?php echo esc_html($with_faq); ?></div>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px;"><?php esc_html_e('Avec FAQ générée', 'alesta'); ?></div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;text-align:center;">
                    <div class="faq-stat-value" style="font-size:28px;font-weight:700;color:#1d4ed8;"><?php echo esc_html($active); ?></div>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px;"><?php esc_html_e('Schemas actifs', 'alesta'); ?></div>
                </div>
            </div>

            <!-- Tableau -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                <div style="padding:12px 16px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <!-- Filtre type (checkboxes multi-select) -->
                    <div style="display:flex;align-items:center;gap:10px;padding:4px 10px;border:1px solid #d1d5db;border-radius:4px;background:#fff;">
                        <span style="font-size:11px;color:#6b7280;font-weight:600;letter-spacing:.3px;"><?php esc_html_e('TYPE :', 'alesta'); ?></span>
                        <label style="font-size:13px;color:#374151;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                            <input type="checkbox" class="faq-filter-type" value="page" checked> <?php esc_html_e('Pages', 'alesta'); ?>
                        </label>
                        <label style="font-size:13px;color:#374151;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                            <input type="checkbox" class="faq-filter-type" value="post" checked> <?php esc_html_e('Articles', 'alesta'); ?>
                        </label>
                        <?php if (post_type_exists('product')): ?>
                        <label style="font-size:13px;color:#374151;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                            <input type="checkbox" class="faq-filter-type" value="product" checked> <?php esc_html_e('Produits', 'alesta'); ?>
                        </label>
                        <?php endif; ?>
                    </div>
                    <select id="faq-filter-status" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;">
                        <option value="all"><?php esc_html_e('Tous statuts', 'alesta'); ?></option>
                        <option value="no_faq"><?php esc_html_e('Sans FAQ', 'alesta'); ?></option>
                        <option value="with_faq"><?php esc_html_e('Avec FAQ', 'alesta'); ?></option>
                        <option value="active"><?php esc_html_e('Actifs', 'alesta'); ?></option>
                    </select>
                    <input type="text" id="faq-search" placeholder="<?php esc_attr_e('Rechercher...', 'alesta'); ?>" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;width:200px;">

                    <!-- Barre d'actions sur sélection (masquée tant qu'aucune case cochée) -->
                    <div id="faq-selection-bar" style="display:none;margin-left:auto;align-items:center;gap:8px;">
                        <span id="faq-selection-count" style="font-size:12px;color:#1e3a5f;font-weight:600;"></span>
                        <button id="btn-faq-batch-selected" class="button" style="background:#1e3a5f;color:#fff;border-color:#1e3a5f;">
                            <?php esc_html_e('Générer pour la sélection', 'alesta'); ?>
                        </button>
                        <button id="btn-faq-activate-selected" class="button"><?php esc_html_e('Activer la sélection', 'alesta'); ?></button>
                        <button id="btn-faq-deactivate-selected" class="button"><?php esc_html_e('Désactiver la sélection', 'alesta'); ?></button>
                    </div>

                    <button id="btn-faq-batch" class="button" style="margin-left:auto;background:#1e3a5f;color:#fff;border-color:#1e3a5f;">
                        <?php esc_html_e('Générer manquants en lot', 'alesta'); ?>
                    </button>
                </div>

                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="padding:10px 16px;text-align:center;width:40px;border-bottom:1px solid #e5e7eb;">
                                <input type="checkbox" id="faq-check-all" title="<?php esc_attr_e('Tout sélectionner (visible)', 'alesta'); ?>">
                            </th>
                            <th style="padding:10px 16px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;"><?php esc_html_e('PAGE / PRODUIT', 'alesta'); ?></th>
                            <th style="padding:10px 16px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;width:100px;"><?php esc_html_e('TYPE', 'alesta'); ?></th>
                            <th style="padding:10px 16px;text-align:center;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;width:80px;"><?php esc_html_e('FAQ', 'alesta'); ?></th>
                            <th style="padding:10px 16px;text-align:center;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;width:80px;"><?php esc_html_e('ACTIF', 'alesta'); ?></th>
                            <th style="padding:10px 16px;text-align:center;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;width:200px;"><?php esc_html_e('ACTIONS', 'alesta'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="faq-tbody">
                    <?php foreach ($posts as $post):
                        $faq       = Alesta_FAQ_Module::get_faq($post->ID);
                        $has_faq   = !empty($faq['faqs']);
                        $is_active = !empty($faq['active']);
                        $count     = count($faq['faqs']);
                        $ts        = !empty($faq['date']) ? strtotime($faq['date']) : false;
                        $date      = $ts ? date_i18n($date_format, $ts) : '';
                        $status    = $has_faq ? ($is_active ? 'active' : 'with_faq') : 'no_faq';
                    ?>
                    <tr class="faq-row"
                        data-id="<?php echo esc_attr($post->ID); ?>"
                        data-type="<?php echo esc_attr($post->post_type); ?>"
                        data-status="<?php echo esc_attr($status); ?>">
                        <td style="padding:10px 16px;border-bottom:1px solid #f3f4f6;text-align:center;">
                            <input type="checkbox" class="faq-row-check" data-id="<?php echo esc_attr($post->ID); ?>">
                        </td>
                        <td style="padding:10px 16px;border-bottom:1px solid #f3f4f6;">
                            <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" target="_blank" rel="noopener"
                               style="font-weight:600;color:#1e3a5f;text-decoration:none;font-size:13px;">
                                <?php echo esc_html($post->post_title); ?>
                            </a>
                            <?php if ($date): ?>
                            <div class="faq-row-date" style="font-size:11px;color:#9ca3af;margin-top:2px;">
                                <?php
                                /* translators: %s: date de génération */
                                echo esc_html(sprintf(__('Généré le %s', 'alesta'), $date));
                                ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding:10px 16px;border-bottom:1px solid #f3f4f6;">
                            <span style="font-size:11px;padding:2px 8px;background:#f3f4f6;border-radius:3px;color:#374151;">
                                <?php echo esc_html($post->post_type); ?>
                            </span>
                        </td>
                        <td class="faq-cell-count" style="padding:10px 16px;border-bottom:1px solid #f3f4f6;text-align:center;">
                            <?php if ($has_faq): ?>
                            <span style="background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;">
                                <?php echo esc_html($count); ?> Q
                            </span>
                            <?php else: ?>
                            <span style="color:#d1d5db;font-size:12px;">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="faq-cell-active" style="padding:10px 16px;border-bottom:1px solid #f3f4f6;text-align:center;">
                            <?php if ($has_faq): ?>
                            <label style="display:inline-flex;align-items:center;cursor:pointer;gap:6px;">
                                <input type="checkbox"
                                    class="faq-toggle"
                                    data-id="<?php echo esc_attr($post->ID); ?>"
                                    <?php checked($is_active); ?>
                                    style="width:16px;height:16px;cursor:pointer;">
                                <span style="font-size:11px;color:#6b7280;"><?php echo $is_active ? esc_html__('Oui', 'alesta') : esc_html__('Non', 'alesta'); ?></span>
                            </label>
                            <?php else: ?>
                            <span style="color:#d1d5db;font-size:12px;">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="faq-cell-actions" style="padding:10px 16px;border-bottom:1px solid #f3f4f6;text-align:center;">
                            <div style="display:flex;justify-content:center;gap:4px;">
                                <button class="button button-small faq-btn-generate"
                                    data-id="<?php echo esc_attr($post->ID); ?>"
                                    data-title="<?php echo esc_attr($post->post_title); ?>"
                                    style="font-size:11px;">
                                    <?php esc_html_e('Générer', 'alesta'); ?>
                                </button>
                                <?php if ($has_faq): ?>
                                <button class="button button-small faq-btn-edit"
                                    data-id="<?php echo esc_attr($post->ID); ?>"
                                    data-title="<?php echo esc_attr($post->post_title); ?>"
                                    data-faqs="<?php echo esc_attr(wp_json_encode($faq['faqs'])); ?>"
                                    style="font-size:11px;">
                                    <?php esc_html_e('Modifier', 'alesta'); ?>
                                </button>
                                <button class="button button-small faq-btn-delete"
                                    data-id="<?php echo esc_attr($post->ID); ?>"
                                    style="font-size:11px;color:#991b1b;border-color:#fca5a5;">
                                    <?php esc_html_e('Supprimer', 'alesta'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal -->
            <div id="faq-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;padding:40px 20px;overflow-y:auto;">
                <div style="background:#fff;border-radius:10px;max-width:680px;margin:0 auto;padding:24px;position:relative;">
                    <button type="button" class="faq-modal-close"
                        style="position:absolute;top:12px;right:12px;background:none;border:none;font-size:22px;cursor:pointer;color:#9ca3af;">&times;</button>
                    <h3 id="faq-modal-title" style="margin:0 0 4px;font-size:16px;color:#1e3a5f;"></h3>
                    <p id="faq-modal-sub" style="margin:0 0 16px;font-size:12px;color:#9ca3af;"></p>
                    <div id="faq-modal-body"></div>
                </div>
            </div>

        </div>
        <?php
    }
}
