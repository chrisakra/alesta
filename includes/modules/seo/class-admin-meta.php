<?php
defined('ABSPATH') || exit;

/**
 * Title & Meta IA + Audit SEO — page admin (Alesta Free).
 *
 * Page fusionnée avec deux onglets : "Title & Meta" (rapport + génération
 * Claude + édition inline) et "Audit SEO" (rendu par Alesta_Admin_Audit).
 * Slug de menu : alesta-ai-meta (identique au Pro).
 *
 * Porté depuis Alesta AI Pro (Alesta_AI_Admin_Meta), sans gating de plan.
 */
class Alesta_Admin_Meta {

    /** @var bool Hooks enregistrés une seule fois. */
    private static $hooked = false;

    public function __construct() {
        if ( self::$hooked ) {
            return;
        }
        self::$hooked = true;
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(string $hook): void {
        if ( strpos($hook, 'alesta-ai-meta') === false ) {
            return;
        }
        $url = plugin_dir_url(ALESTA_PLUGIN_FILE);
        $ver = ALESTA_VERSION;

        wp_enqueue_style('alesta-meta',   $url . 'assets/meta.css',  [], $ver);
        wp_enqueue_style('alesta-audit',  $url . 'assets/audit.css', [], $ver);
        if ( class_exists('Alesta_API') ) {
            Alesta_API::enqueue_key_notice();
        }
        wp_enqueue_script('alesta-meta',  $url . 'assets/meta.js',  ['jquery'], $ver, true);
        wp_enqueue_script('alesta-audit', $url . 'assets/audit.js', ['jquery', 'alesta-meta'], $ver, true);

        $nonce_name = class_exists('Alesta_Meta_Module') ? Alesta_Meta_Module::NONCE : 'alesta_meta_nonce';
        wp_localize_script('alesta-meta', 'AlestaMeta', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce($nonce_name),
            'site_name' => get_bloginfo('name'),
            'i18n'      => [
                'select_type'     => __('Sélectionnez au moins un type de contenu.', 'alesta'),
                'select_check'    => __('Sélectionnez au moins une vérification.', 'alesta'),
                'analyzing'       => __('Analyse en cours...', 'alesta'),
                'regenerate'      => __('Régénérer le rapport', 'alesta'),
                'confirm_report'  => __('Régénérer le rapport ? (Gratuit — aucun appel Claude)', 'alesta'),
                'error'           => __('Erreur', 'alesta'),
                'network_error'   => __('Erreur réseau.', 'alesta'),
                'unknown'         => __('inconnue', 'alesta'),
                'no_result'       => __('Aucun résultat', 'alesta'),
                /* translators: 1: first index, 2: last index, 3: total */
                'showing'         => __('Affichage %1$s-%2$s sur %3$s', 'alesta'),
                'no_missing'      => __('Aucune page avec erreur critique.', 'alesta'),
                /* translators: %s: number of pages */
                'confirm_batch'   => __('Générer et appliquer automatiquement le title + meta pour %s page(s) ? Cette action consomme des crédits chez votre fournisseur IA.', 'alesta'),
                'fill_fields'     => __('Saisissez un title ou une meta avant de sauvegarder.', 'alesta'),
                'saved'           => __('Sauvegardé !', 'alesta'),
                'save'            => __('Sauvegarder', 'alesta'),
                'confirm_revert'  => __('Restaurer les anciennes valeurs pour cette page ?', 'alesta'),
                'no_history'      => __('Aucun historique trouvé.', 'alesta'),
                'generating_for'  => __('Génération pour : ', 'alesta'),
                'claude_analyzes' => __('Claude analyse le contenu...', 'alesta'),
                'select_both'     => __('Sélectionnez un title et une meta.', 'alesta'),
                /* translators: %s: target (Yoast SEO / RankMath / Alesta) */
                'saved_in'        => __('Enregistré dans %s', 'alesta'),
                'modified'        => __('Modifié', 'alesta'),
                'run_audit'       => __('Lancer l\'audit', 'alesta'),
                'last_audit'      => __('Dernier audit : ', 'alesta'),
                'generate'        => __('Générer', 'alesta'),
                'loading'         => __('Chargement…', 'alesta'),
                'apply'           => __('Appliquer', 'alesta'),
                'selected'        => __('Sélectionné', 'alesta'),
                'applied'         => __('Appliqué !', 'alesta'),
                'apply_selection' => __('Appliquer la sélection (titre + meta + mot-clé)', 'alesta'),
                'saving'          => __('Enregistrement…', 'alesta'),
            ],
        ]);
    }

    public function render_page(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__('Accès refusé.', 'alesta') );
        }

        $report     = get_option('alesta_ai_meta_report', null);
        $history    = get_option('alesta_ai_meta_history', []);
        $has_report = is_array($report) && ! empty($report['items']);
        $history_n  = is_array($history) ? count($history) : 0;

        $usage = ['total_cost_usd' => 0.0, 'calls' => 0];
        if ( is_callable(['Alesta_API', 'get_usage_stats']) ) {
            $usage = array_merge($usage, (array) Alesta_API::get_usage_stats());
        }

        $nonce_name = class_exists('Alesta_Meta_Module') ? Alesta_Meta_Module::NONCE : 'alesta_meta_nonce';
        $target     = class_exists('Alesta_Meta_Module') ? Alesta_Meta_Module::target_label() : 'Alesta';

        // Clé API du fournisseur IA (BYOK) : la génération en a besoin, pas l'analyse.
        $has_key = true;
        if ( class_exists('Alesta_API') && method_exists('Alesta_API', 'has_key') ) {
            $has_key = ( new Alesta_API() )->has_key();
        }
        $settings_url = class_exists('Alesta_API')
            ? Alesta_API::settings_url()
            : admin_url('admin.php?page=alesta-ai-settings');
        ?>
        <div class="wrap alesta-wrap" id="alesta-meta-wrap">

            <?php if ( ! $has_key ) : ?>
            <div class="notice notice-warning" style="margin:1rem 0 0;">
                <p>
                    <strong><?php esc_html_e('Aucune clé API n\'est configurée pour le fournisseur IA sélectionné.', 'alesta'); ?></strong>
                    <?php esc_html_e('L\'analyse du site fonctionne, mais la génération par l\'IA nécessite votre clé : rendez-vous dans Alesta AI &rarr; Configuration.', 'alesta'); ?>
                </p>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e('Configurer la clé API', 'alesta'); ?></a>
                </p>
            </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="alesta-header">
                <div class="alesta-logo">
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;background:#1e3a5f;border-radius:9px;font-size:13px;font-weight:700;color:#fff;font-family:monospace;">SEO</span>
                    <div>
                        <h1><?php esc_html_e('Title & Meta', 'alesta'); ?></h1>
                        <p><?php esc_html_e('Analyse et génération des balises SEO par Claude', 'alesta'); ?></p>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <?php if ( $has_report && ! empty($report['generated_at']) ) : ?>
                    <span style="font-size:12px;color:#9ca3af;">
                        <?php
                        /* translators: %s: date and time */
                        echo esc_html( sprintf( __('Rapport du %s', 'alesta'), gmdate('d/m/Y H:i', (int) strtotime($report['generated_at'])) ) );
                        ?>
                    </span>
                    <?php endif; ?>
                    <span style="font-size:11px;padding:4px 10px;background:#f0fdf4;color:#065f46;border-radius:20px;border:1px solid #d1fae5;">
                        <?php
                        /* translators: 1: cost in USD, 2: number of API calls */
                        echo esc_html( sprintf( __('$%1$s utilisés · %2$s appels', 'alesta'), number_format((float) $usage['total_cost_usd'], 4, ',', ' '), number_format((int) $usage['calls']) ) );
                        ?>
                    </span>
                    <span style="font-size:11px;padding:4px 10px;background:#eff6ff;color:#1e40af;border-radius:20px;border:1px solid #bfdbfe;">
                        <?php
                        /* translators: %s: Yoast SEO / RankMath / Alesta */
                        echo esc_html( sprintf( __('Écriture : %s', 'alesta'), $target ) );
                        ?>
                    </span>
                </div>
            </div>

            <!-- Onglets de page -->
            <div class="am-page-tabs">
                <button type="button" class="am-page-tab active" data-tab="meta">&#128221; <?php esc_html_e('Title & Meta', 'alesta'); ?></button>
                <button type="button" class="am-page-tab" data-tab="audit">&#128269; <?php esc_html_e('Audit SEO', 'alesta'); ?></button>
            </div>

            <!-- ── Onglet Title & Meta ── -->
            <div id="am-tab-meta" class="am-tab-pane">

            <!-- Options bar -->
            <div class="am-options-bar">
                <div class="am-options-group">
                    <label class="am-label"><?php esc_html_e('Types', 'alesta'); ?></label>
                    <div style="display:flex;gap:8px;">
                        <label class="am-check"><input type="checkbox" class="opt-type" value="page" checked> <?php esc_html_e('Pages', 'alesta'); ?></label>
                        <label class="am-check"><input type="checkbox" class="opt-type" value="post" checked> <?php esc_html_e('Articles', 'alesta'); ?></label>
                        <?php if ( post_type_exists('product') ) : ?>
                        <label class="am-check"><input type="checkbox" class="opt-type" value="product"> <?php esc_html_e('Produits', 'alesta'); ?></label>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="am-options-group">
                    <label class="am-label" for="opt-tone"><?php esc_html_e('Ton Claude', 'alesta'); ?></label>
                    <select id="opt-tone" class="am-select">
                        <option value="professionnel"><?php esc_html_e('Professionnel', 'alesta'); ?></option>
                        <option value="commercial"><?php esc_html_e('Commercial', 'alesta'); ?></option>
                        <option value="informatif"><?php esc_html_e('Informatif', 'alesta'); ?></option>
                        <option value="accrocheur"><?php esc_html_e('Accrocheur', 'alesta'); ?></option>
                    </select>
                </div>
                <div class="am-options-group">
                    <label class="am-label" for="opt-lang"><?php esc_html_e('Langue', 'alesta'); ?></label>
                    <select id="opt-lang" class="am-select">
                        <option value="fr"><?php esc_html_e('Français', 'alesta'); ?></option>
                        <option value="en"><?php esc_html_e('Anglais', 'alesta'); ?></option>
                        <option value="es"><?php esc_html_e('Espagnol', 'alesta'); ?></option>
                        <option value="de"><?php esc_html_e('Allemand', 'alesta'); ?></option>
                        <option value="it"><?php esc_html_e('Italien', 'alesta'); ?></option>
                    </select>
                </div>
                <div class="am-options-group">
                    <label class="am-label" for="opt-length"><?php esc_html_e('Longueur title', 'alesta'); ?></label>
                    <select id="opt-length" class="am-select">
                        <option value="short"><?php esc_html_e('Court (45-55)', 'alesta'); ?></option>
                        <option value="standard" selected><?php esc_html_e('Standard (55-60)', 'alesta'); ?></option>
                        <option value="long"><?php esc_html_e('Long (60-70)', 'alesta'); ?></option>
                    </select>
                </div>
                <div class="am-options-group">
                    <label class="am-label" for="opt-keyword"><?php esc_html_e('Mot-clé imposé', 'alesta'); ?></label>
                    <input type="text" id="opt-keyword" class="am-input" placeholder="<?php esc_attr_e('Ex : hébergement web', 'alesta'); ?>">
                </div>
                <div class="am-options-group">
                    <label class="am-check">
                        <input type="checkbox" id="opt-addsite">
                        <span><?php esc_html_e('Ajouter', 'alesta'); ?> | <?php echo esc_html(get_bloginfo('name')); ?></span>
                    </label>
                </div>
                <div class="am-options-group" style="margin-left:auto;">
                    <button type="button" id="btn-run-report" class="button button-primary">
                        <?php echo esc_html( $has_report ? __('Régénérer le rapport', 'alesta') : __('Analyser le site', 'alesta') ); ?>
                    </button>
                    <?php if ( $has_report ) : ?>
                    <a id="btn-export-csv" href="<?php echo esc_url( admin_url('admin-ajax.php?action=alesta_meta_csv&nonce=' . wp_create_nonce($nonce_name)) ); ?>" class="button">
                        <?php esc_html_e('Exporter CSV', 'alesta'); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( $has_report ) : ?>
            <!-- Filters -->
            <div class="am-filters">
                <button type="button" class="am-filter-btn active" data-filter="all"><?php esc_html_e('Tout', 'alesta'); ?> (<?php echo esc_html( (int) $report['summary']['total'] ); ?>)</button>
                <button type="button" class="am-filter-btn am-f-error" data-filter="error">
                    <?php esc_html_e('Erreurs', 'alesta'); ?> (<?php echo esc_html( (int) $report['summary']['missing'] ); ?>)
                </button>
                <button type="button" class="am-filter-btn am-f-warning" data-filter="warning">
                    <?php esc_html_e('Avertissements', 'alesta'); ?> (<?php echo esc_html( (int) $report['summary']['too_short'] ); ?>)
                </button>
                <button type="button" class="am-filter-btn am-f-ok" data-filter="ok">
                    OK (<?php echo esc_html( (int) $report['summary']['ok'] ); ?>)
                </button>
                <button type="button" class="am-filter-btn" data-filter="duplicate" style="margin-left:4px;">
                    <?php esc_html_e('Doublons', 'alesta'); ?> (<?php echo esc_html( (int) $report['summary']['duplicate'] ); ?>)
                </button>
                <div style="margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <select id="am-filter-score" class="am-input" style="width:160px;">
                        <option value="all"><?php esc_html_e('Tous les scores', 'alesta'); ?></option>
                        <option value="lt50"><?php esc_html_e('Score < 50', 'alesta'); ?></option>
                        <option value="50-80"><?php esc_html_e('Score 50 - 80', 'alesta'); ?></option>
                        <option value="gt80"><?php esc_html_e('Score > 80', 'alesta'); ?></option>
                    </select>
                    <select id="am-per-page" class="am-input" style="width:90px;">
                        <option value="10">10 / page</option>
                        <option value="25" selected>25 / page</option>
                        <option value="50">50 / page</option>
                        <option value="100000"><?php esc_html_e('Tous', 'alesta'); ?></option>
                    </select>
                    <input type="text" id="am-search" placeholder="<?php esc_attr_e('Rechercher...', 'alesta'); ?>" class="am-input" style="width:160px;">
                    <button type="button" id="btn-batch-missing" class="button">
                        <?php esc_html_e('Générer les manquants en lot', 'alesta'); ?>
                    </button>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="am-summary">
                <?php
                $score_avg = 0;
                if ( (int) $report['summary']['total'] > 0 ) {
                    $score_avg = (int) round(array_sum(array_column($report['items'], 'score')) / (int) $report['summary']['total']);
                }
                $score_color = $score_avg >= 80 ? '#065f46' : ($score_avg >= 50 ? '#713f12' : '#991b1b');
                ?>
                <div class="am-stat-card" style="border-top:3px solid <?php echo esc_attr($score_color); ?>;">
                    <div class="am-stat-val" style="color:<?php echo esc_attr($score_color); ?>;"><?php echo esc_html($score_avg); ?>/100</div>
                    <div class="am-stat-lbl"><?php esc_html_e('Score SEO moyen', 'alesta'); ?></div>
                </div>
                <div class="am-stat-card" style="border-top:3px solid #ef4444;">
                    <div class="am-stat-val" style="color:#991b1b;"><?php echo esc_html( (int) $report['summary']['missing'] ); ?></div>
                    <div class="am-stat-lbl"><?php esc_html_e('Erreurs critiques', 'alesta'); ?></div>
                </div>
                <div class="am-stat-card" style="border-top:3px solid #f59e0b;">
                    <div class="am-stat-val" style="color:#713f12;"><?php echo esc_html( (int) $report['summary']['too_short'] ); ?></div>
                    <div class="am-stat-lbl"><?php esc_html_e('Avertissements', 'alesta'); ?></div>
                </div>
                <div class="am-stat-card" style="border-top:3px solid #10b981;">
                    <div class="am-stat-val" style="color:#065f46;"><?php echo esc_html( (int) $report['summary']['ok'] ); ?></div>
                    <div class="am-stat-lbl"><?php esc_html_e('Pages optimisées', 'alesta'); ?></div>
                </div>
                <div class="am-stat-card" style="border-top:3px solid #6366f1;">
                    <div class="am-stat-val" style="color:#4338ca;"><?php echo esc_html($history_n); ?></div>
                    <div class="am-stat-lbl"><?php esc_html_e('Modifications (historique)', 'alesta'); ?></div>
                </div>
            </div>

            <!-- Batch progress -->
            <div id="am-batch-progress" style="display:none;margin:1rem 0;padding:14px 18px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <strong style="font-size:13px;color:#1e40af;"><?php esc_html_e('Génération en lot en cours...', 'alesta'); ?></strong>
                    <span id="am-batch-count" style="font-size:12px;color:#3b82f6;">0 / 0</span>
                </div>
                <div style="height:6px;background:#dbeafe;border-radius:3px;overflow:hidden;">
                    <div id="am-batch-fill" style="height:100%;background:#2563eb;border-radius:3px;width:0%;transition:width .3s;"></div>
                </div>
            </div>

            <!-- Table -->
            <div class="am-table-wrap">
                <table class="am-table" id="am-table">
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" id="am-select-all"></th>
                            <th><?php esc_html_e('Page', 'alesta'); ?></th>
                            <th style="width:80px;"><?php esc_html_e('Score', 'alesta'); ?></th>
                            <th style="width:120px;"><?php esc_html_e('Statut title', 'alesta'); ?></th>
                            <th style="width:120px;"><?php esc_html_e('Statut meta', 'alesta'); ?></th>
                            <th style="width:160px;"><?php esc_html_e('Actions', 'alesta'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="am-tbody">
                    <?php foreach ($report['items'] as $item) : ?>
                        <?php
                        $item_score = (int) ($item['score'] ?? 0);
                        $title_len  = (int) ($item['title_len'] ?? 0);
                        $meta_len   = (int) ($item['meta_len'] ?? 0);
                        $score_c    = $item_score >= 80 ? '#065f46' : ($item_score >= 50 ? '#713f12' : '#991b1b');
                        $score_b    = $item_score >= 80 ? '#d1fae5' : ($item_score >= 50 ? '#fef9c3' : '#fee2e2');
                        $title_cc   = ( $title_len >= 30 && $title_len <= 60 ) ? '#065f46' : ( $title_len === 0 ? '#9ca3af' : '#991b1b' );
                        $meta_cc    = ( $meta_len >= 120 && $meta_len <= 160 ) ? '#065f46' : ( $meta_len === 0 ? '#9ca3af' : '#991b1b' );
                        $post_id    = (int) ($item['post_id'] ?? 0);
                        ?>
                        <tr class="am-row" data-status="<?php echo esc_attr($item['status'] ?? 'ok'); ?>" data-duplicate="<?php echo ( ($item['title_status'] ?? '') === 'duplicate' ) ? '1' : '0'; ?>" data-id="<?php echo esc_attr($post_id); ?>" data-type="<?php echo esc_attr($item['post_type'] ?? ''); ?>" data-score="<?php echo esc_attr($item_score); ?>">
                            <td><input type="checkbox" class="am-row-check" value="<?php echo esc_attr($post_id); ?>"></td>
                            <td>
                                <div class="am-page-name">
                                    <a href="<?php echo esc_url($item['url'] ?? ''); ?>" target="_blank" rel="noopener"><?php echo esc_html($item['post_title'] ?? ''); ?></a>
                                    <span class="am-type-badge"><?php echo esc_html($item['post_type'] ?? ''); ?></span>
                                    <?php if ( ! empty($item['applied']) ) : ?>
                                    <span class="am-applied-badge"><?php esc_html_e('Modifié', 'alesta'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="am-page-meta">
                                    <div class="am-inline-field">
                                        <label class="am-inline-label">T:</label>
                                        <input type="text"
                                            class="am-inline-title"
                                            data-id="<?php echo esc_attr($post_id); ?>"
                                            value="<?php echo esc_attr($item['title'] ?? ''); ?>"
                                            placeholder="<?php esc_attr_e('Title SEO...', 'alesta'); ?>">
                                        <span class="am-char-count am-title-count" style="color:<?php echo esc_attr($title_cc); ?>"><?php echo esc_html($title_len); ?> car.</span>
                                    </div>
                                    <div class="am-inline-field" style="margin-top:4px;">
                                        <label class="am-inline-label">M:</label>
                                        <textarea
                                            class="am-inline-meta"
                                            data-id="<?php echo esc_attr($post_id); ?>"
                                            rows="2"
                                            placeholder="<?php esc_attr_e('Meta description...', 'alesta'); ?>"><?php echo esc_textarea($item['meta'] ?? ''); ?></textarea>
                                        <span class="am-char-count am-meta-count" style="color:<?php echo esc_attr($meta_cc); ?>"><?php echo esc_html($meta_len); ?> car.</span>
                                    </div>
                                    <div class="am-inline-field" style="margin-top:4px;">
                                        <label class="am-inline-label">K:</label>
                                        <input type="text"
                                            class="am-inline-keyword"
                                            data-id="<?php echo esc_attr($post_id); ?>"
                                            value="<?php echo esc_attr( (string) get_post_meta($post_id, '_alesta_focus_keyword', true) ); ?>"
                                            placeholder="<?php esc_attr_e('Mot-clé principal (focus keyphrase)...', 'alesta'); ?>">
                                        <span style="min-width:44px;"></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:<?php echo esc_attr($score_b); ?>;color:<?php echo esc_attr($score_c); ?>;">
                                    <?php echo esc_html($item_score); ?>
                                </span>
                            </td>
                            <td><?php echo wp_kses_post( $this->status_badge((string) ($item['title_status'] ?? 'ok')) ); ?></td>
                            <td><?php echo wp_kses_post( $this->status_badge((string) ($item['meta_status'] ?? 'ok')) ); ?></td>
                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                    <button type="button" class="button am-btn-generate" data-id="<?php echo esc_attr($post_id); ?>" data-title="<?php echo esc_attr($item['post_title'] ?? ''); ?>">
                                        <?php esc_html_e('Générer', 'alesta'); ?>
                                    </button>
                                    <button type="button" class="button am-btn-save-manual" data-id="<?php echo esc_attr($post_id); ?>" style="background:#1e3a5f;color:#fff;border-color:#1e3a5f;">
                                        <?php esc_html_e('Sauvegarder', 'alesta'); ?>
                                    </button>
                                    <?php if ( ! empty($item['applied']) ) : ?>
                                    <button type="button" class="button am-btn-revert" data-id="<?php echo esc_attr($post_id); ?>">
                                        <?php esc_html_e('Annuler', 'alesta'); ?>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div id="am-pagination" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid #e5e7eb;background:#f9fafb;">
                <div id="am-pagination-info" style="font-size:12px;color:#6b7280;"></div>
                <div id="am-pagination-btns" style="display:flex;gap:4px;flex-wrap:wrap;"></div>
            </div>

            <?php else : ?>
            <!-- Empty state -->
            <div class="am-empty-state">
                <div style="font-size:56px;margin-bottom:1rem;">&#128269;</div>
                <h2><?php esc_html_e('Aucun rapport disponible', 'alesta'); ?></h2>
                <p><?php esc_html_e('Lancez une première analyse pour voir le statut SEO de toutes vos pages.', 'alesta'); ?></p>
                <p style="font-size:12px;color:#9ca3af;margin-top:8px;"><?php esc_html_e('Le rapport est sauvegardé — l\'analyse est gratuite, seule la génération par l\'IA utilise votre clé API.', 'alesta'); ?></p>
                <button type="button" id="btn-run-report-empty" class="button button-primary button-large" style="margin-top:1.5rem;">
                    <?php esc_html_e('Analyser le site maintenant', 'alesta'); ?>
                </button>
            </div>
            <?php endif; ?>

            </div><!-- /am-tab-meta -->

            <!-- ── Onglet Audit SEO ── -->
            <div id="am-tab-audit" class="am-tab-pane" style="display:none;">
                <?php if ( class_exists('Alesta_Admin_Audit') ) : ?>
                    <?php Alesta_Admin_Audit::render_audit_tab(); ?>
                <?php else : ?>
                    <p style="color:#6b7280;padding:2rem;"><?php esc_html_e('Module d\'audit non disponible.', 'alesta'); ?></p>
                <?php endif; ?>
            </div><!-- /am-tab-audit -->

        </div><!-- /alesta-meta-wrap -->

        <!-- Modal génération -->
        <div id="am-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;">
            <div class="am-modal-box">
                <div class="am-modal-header">
                    <div>
                        <div class="am-modal-title" id="am-modal-title"><?php esc_html_e('Génération en cours...', 'alesta'); ?></div>
                        <div class="am-modal-sub" id="am-modal-sub"></div>
                    </div>
                    <button type="button" id="am-modal-close" class="button">&#10005;</button>
                </div>
                <div id="am-modal-body" class="am-modal-body">
                    <div class="am-loader"><?php esc_html_e('Analyse de la page par Claude...', 'alesta'); ?></div>
                </div>
            </div>
        </div>

        <?php
    }

    private function status_badge(string $status): string {
        $map = [
            'ok'        => ['bg' => '#d1fae5', 'color' => '#065f46', 'label' => 'OK'],
            'missing'   => ['bg' => '#fee2e2', 'color' => '#991b1b', 'label' => __('Absent', 'alesta')],
            'short'     => ['bg' => '#fef9c3', 'color' => '#713f12', 'label' => __('Trop court', 'alesta')],
            'long'      => ['bg' => '#fef9c3', 'color' => '#713f12', 'label' => __('Trop long', 'alesta')],
            'duplicate' => ['bg' => '#ede9fe', 'color' => '#4338ca', 'label' => __('Doublon', 'alesta')],
        ];
        $s = $map[$status] ?? ['bg' => '#f3f4f6', 'color' => '#6b7280', 'label' => ucfirst($status)];
        return '<span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:500;background:' . esc_attr($s['bg']) . ';color:' . esc_attr($s['color']) . '">' . esc_html($s['label']) . '</span>';
    }
}
