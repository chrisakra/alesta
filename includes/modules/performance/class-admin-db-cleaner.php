<?php
defined('ABSPATH') || exit;

/**
 * Nettoyeur BDD — Interface admin (Alesta)
 */
class Alesta_Admin_DB_Cleaner {

    const OPT_SCHEDULE      = 'alesta_db_cleaner_schedule';      /* disabled|daily|weekly|monthly */
    const OPT_LAST_RUN      = 'alesta_db_cleaner_last_run';
    const OPT_LAST_RESULT   = 'alesta_db_cleaner_last_result';
    const OPT_SCHEDULE_CATS = 'alesta_db_cleaner_schedule_cats'; /* array of category keys */
    const CRON_HOOK         = 'alesta_db_cleaner_cron';

    public function __construct() {
        add_action('admin_enqueue_scripts',              [$this, 'enqueue_assets']);
        add_action('wp_ajax_alesta_db_analyze',          [$this, 'ajax_analyze']);
        add_action('wp_ajax_alesta_db_clean_category',   [$this, 'ajax_clean_category']);
        add_action('wp_ajax_alesta_db_clean_all',        [$this, 'ajax_clean_all']);
        add_action('wp_ajax_alesta_db_save_schedule',    [$this, 'ajax_save_schedule']);
        add_action(self::CRON_HOOK,                      [$this, 'run_scheduled_clean']);
        add_filter('cron_schedules',                     [$this, 'add_monthly_schedule']);
    }

    // =========================================================================
    // ASSETS
    // =========================================================================

    public function enqueue_assets( string $hook ): void {
        if ( strpos($hook, 'alesta-ai-db-cleaner') === false ) return;
        $ver = ALESTA_VERSION . '.' . time();
        wp_enqueue_style( 'alesta-db-cleaner',  plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/db-cleaner.css', [], $ver );
        wp_enqueue_script( 'alesta-db-cleaner', plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/db-cleaner.js',  ['jquery'], $ver, true );
        wp_localize_script('alesta-db-cleaner', 'AlestaDbCleaner', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('alesta_db_nonce'),
        ]);
    }

    // =========================================================================
    // PAGE
    // =========================================================================

    public function render_page(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__('Accès refusé.', 'alesta') );
        }

        $schedule      = get_option(self::OPT_SCHEDULE, 'disabled');
        $last_run      = get_option(self::OPT_LAST_RUN, '');
        $last_result   = get_option(self::OPT_LAST_RESULT, []);
        $next_run      = wp_next_scheduled(self::CRON_HOOK);
        $schedule_cats = get_option(self::OPT_SCHEDULE_CATS, array_keys(Alesta_DB_Cleaner_Module::categories()));
        $categories    = Alesta_DB_Cleaner_Module::categories();
        ?>
        <div class="wrap" id="alesta-db-wrap">

            <!-- ── En-tête ── -->
            <div class="alesta-db-header">
                <div>
                    <h1>🧹 Nettoyeur BDD</h1>
                    <p class="alesta-db-subtitle">Analysez et nettoyez votre base de données WordPress en toute sécurité.</p>
                </div>
                <div class="alesta-db-header-actions">
                    <button id="btn-analyze" class="button button-secondary">🔍 Analyser la BDD</button>
                    <button id="btn-clean-all" class="button button-primary" disabled>🧹 Tout nettoyer</button>
                </div>
            </div>

            <!-- ── Bandeau statut ── -->
            <div class="alesta-db-statbar">
                <div class="db-stat">
                    <span class="db-stat-label">DERNIER NETTOYAGE</span>
                    <span class="db-stat-value" id="stat-last-run">
                        <?php echo $last_run ? esc_html( date_i18n('d/m/Y à H:i', (int) $last_run) ) : '—'; ?>
                    </span>
                </div>
                <div class="db-stat">
                    <span class="db-stat-label">PROCHAIN NETTOYAGE AUTO</span>
                    <span class="db-stat-value">
                        <?php echo $next_run ? esc_html( date_i18n('d/m/Y à H:i', $next_run) ) : '—'; ?>
                    </span>
                </div>
                <div class="db-stat">
                    <span class="db-stat-label">ÉLÉMENTS TROUVÉS</span>
                    <span class="db-stat-value" id="stat-total-count">—</span>
                </div>
                <div class="db-stat">
                    <span class="db-stat-label">ESPACE RÉCUPÉRABLE</span>
                    <span class="db-stat-value" id="stat-total-size">—</span>
                </div>
            </div>

            <!-- ── Message global ── -->
            <div id="db-global-msg" class="alesta-db-global-msg" style="display:none;"></div>

            <!-- ── Grille catégories ── -->
            <div class="alesta-db-grid" id="alesta-db-grid">
                <?php foreach ($categories as $key => $cat) : ?>
                <div class="alesta-db-card" data-key="<?php echo esc_attr($key); ?>">
                    <div class="db-card-header">
                        <span class="db-card-icon"><?php echo esc_html($cat['icon']); ?></span>
                        <div class="db-card-title-wrap">
                            <span class="db-card-title"><?php echo esc_html($cat['label']); ?></span>
                            <span class="db-card-desc"><?php echo esc_html($cat['description']); ?></span>
                        </div>
                        <span class="db-card-badge" id="badge-<?php echo esc_attr($key); ?>">—</span>
                    </div>
                    <div class="db-card-footer">
                        <span class="db-card-size" id="size-<?php echo esc_attr($key); ?>"></span>
                        <span class="db-card-msg" id="msg-<?php echo esc_attr($key); ?>"></span>
                        <button class="button button-small btn-clean-cat"
                                data-key="<?php echo esc_attr($key); ?>"
                                disabled>
                            Nettoyer
                        </button>
                        <span class="db-spinner spinner" id="spin-<?php echo esc_attr($key); ?>"></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ── Planification ── -->
            <div class="alesta-db-schedule-card">
                <div class="db-schedule-title">⏱️ Nettoyage automatique planifié</div>
                <div class="db-schedule-body">
                    <p class="db-schedule-desc">
                        Définissez une fréquence de nettoyage automatique et les catégories concernées.
                    </p>

                    <!-- Fréquence -->
                    <div class="db-schedule-row">
                        <label class="db-schedule-label">Fréquence :</label>
                        <div class="db-schedule-options">
                            <?php
                            $opts = [
                                'disabled' => '🚫 Désactivé',
                                'daily'    => '📅 Quotidien',
                                'weekly'   => '📅 Hebdomadaire',
                                'monthly'  => '📅 Mensuel',
                            ];
                            foreach ($opts as $val => $lbl) :
                            ?>
                            <label class="db-radio-label">
                                <input type="radio" name="db_schedule" value="<?php echo esc_attr($val); ?>"
                                    <?php checked($schedule, $val); ?>>
                                <?php echo esc_html($lbl); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Catégories à nettoyer automatiquement -->
                    <div class="db-schedule-row db-schedule-cats-row">
                        <label class="db-schedule-label">Catégories :</label>
                        <div class="db-schedule-cats">
                            <?php foreach ($categories as $key => $cat) : ?>
                            <label class="db-cat-checkbox-label">
                                <input type="checkbox" name="db_schedule_cats[]"
                                    value="<?php echo esc_attr($key); ?>"
                                    <?php checked(in_array($key, (array) $schedule_cats, true)); ?>>
                                <?php echo esc_html($cat['icon'] . ' ' . $cat['label']); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Prochain nettoyage + dernier résultat -->
                    <?php if ($last_result && is_array($last_result)) :
                        $last_total = array_sum($last_result);
                    ?>
                    <div class="db-schedule-last-result">
                        <span class="db-last-result-label">Dernier résultat automatique :</span>
                        <span class="db-last-result-value">
                            <?php echo esc_html($last_total); ?> élément(s) supprimé(s)
                            <?php if ($last_run) : ?>
                                — <?php echo esc_html(date_i18n('d/m/Y à H:i', (int) $last_run)); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <div class="db-schedule-actions">
                        <button id="btn-save-schedule" class="button button-secondary">Enregistrer la planification</button>
                        <span class="spinner" id="spin-schedule" style="float:none;"></span>
                        <span id="msg-schedule" class="db-inline-msg"></span>
                    </div>
                </div>
            </div>

        </div><!-- /#alesta-db-wrap -->
        <?php
    }

    // =========================================================================
    // AJAX — ANALYSER
    // =========================================================================

    public function ajax_analyze(): void {
        check_ajax_referer('alesta_db_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Accès refusé.']);
        }

        $data = Alesta_DB_Cleaner_Module::analyze();

        $total_count = 0;
        $total_size  = 0.0;
        foreach ($data as $item) {
            $total_count += $item['count'];
            $total_size  += $item['size_kb'];
        }

        wp_send_json_success([
            'categories'  => $data,
            'total_count' => $total_count,
            'total_size'  => $total_size,
        ]);
    }

    // =========================================================================
    // AJAX — NETTOYER UNE CATÉGORIE
    // =========================================================================

    public function ajax_clean_category(): void {
        check_ajax_referer('alesta_db_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Accès refusé.']);
        }

        $category = isset($_POST['category']) ? sanitize_key(wp_unslash($_POST['category'])) : '';
        $cats     = Alesta_DB_Cleaner_Module::categories();

        if ( ! isset($cats[$category]) ) {
            wp_send_json_error(['message' => 'Catégorie invalide.']);
        }

        $deleted = Alesta_DB_Cleaner_Module::clean($category);
        update_option(self::OPT_LAST_RUN, time());

        wp_send_json_success([
            'deleted' => $deleted,
            'message' => $deleted > 0
                ? $deleted . ' élément(s) supprimé(s).'
                : 'Rien à supprimer.',
        ]);
    }

    // =========================================================================
    // AJAX — TOUT NETTOYER
    // =========================================================================

    public function ajax_clean_all(): void {
        check_ajax_referer('alesta_db_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Accès refusé.']);
        }

        $results = Alesta_DB_Cleaner_Module::clean_all();
        update_option(self::OPT_LAST_RUN, time());

        $total = array_sum($results);

        wp_send_json_success([
            'results'  => $results,
            'total'    => $total,
            'last_run' => date_i18n('d/m/Y à H:i', time()),
            'message'  => $total > 0
                ? '✅ ' . $total . ' élément(s) supprimé(s) au total.'
                : '✅ La base de données est déjà propre.',
        ]);
    }

    // =========================================================================
    // AJAX — ENREGISTRER LA PLANIFICATION
    // =========================================================================

    public function ajax_save_schedule(): void {
        check_ajax_referer('alesta_db_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Accès refusé.']);
        }

        $schedule = isset($_POST['schedule']) ? sanitize_key(wp_unslash($_POST['schedule'])) : 'disabled';
        $allowed  = ['disabled', 'daily', 'weekly', 'monthly'];
        if ( ! in_array($schedule, $allowed, true) ) {
            $schedule = 'disabled';
        }

        /* Catégories sélectionnées */
        $valid_cats  = array_keys(Alesta_DB_Cleaner_Module::categories());
        $raw_cats    = isset($_POST['schedule_cats']) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['schedule_cats'] ) ) : [];
        $clean_cats  = array_values(array_filter(
            array_map('sanitize_key', $raw_cats),
            fn($k) => in_array($k, $valid_cats, true)
        ));
        update_option(self::OPT_SCHEDULE_CATS, $clean_cats ?: $valid_cats);
        update_option(self::OPT_SCHEDULE, $schedule);

        /* Annuler l'ancien cron puis replanifier si besoin */
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }

        $next = '';
        if ($schedule !== 'disabled') {
            wp_schedule_event(time(), $schedule, self::CRON_HOOK);
            $next_ts = wp_next_scheduled(self::CRON_HOOK);
            $next    = $next_ts ? date_i18n('d/m/Y à H:i', $next_ts) : '';
        }

        wp_send_json_success([
            'message'  => 'Planification enregistrée.',
            'next_run' => $next,
        ]);
    }

    // =========================================================================
    // CRON
    // =========================================================================

    public function run_scheduled_clean(): void {
        $valid_cats    = array_keys(Alesta_DB_Cleaner_Module::categories());
        $schedule_cats = get_option(self::OPT_SCHEDULE_CATS, $valid_cats);
        $schedule_cats = array_filter((array) $schedule_cats, fn($k) => in_array($k, $valid_cats, true));

        $results = [];
        foreach ($schedule_cats as $key) {
            $results[$key] = Alesta_DB_Cleaner_Module::clean($key);
        }

        update_option(self::OPT_LAST_RUN, time());
        update_option(self::OPT_LAST_RESULT, $results);
    }

    /**
     * Ajoute l'intervalle "mensuel" au planificateur WP.
     */
    public function add_monthly_schedule( array $schedules ): array {
        if ( ! isset($schedules['monthly']) ) {
            $schedules['monthly'] = [
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => 'Une fois par mois',
            ];
        }
        return $schedules;
    }
}
