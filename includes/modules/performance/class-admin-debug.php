<?php
defined('ABSPATH') || exit;

/**
 * Debug Manager — Interface admin (Alesta)
 *
 * Toggle WP_DEBUG / WP_DEBUG_LOG via wp-config.php + visionneuse debug.log.
 */
class Alesta_Admin_Debug {

    public function __construct() {
        add_action('admin_enqueue_scripts',            [$this, 'enqueue_assets']);
        add_action('admin_menu',                       [$this, 'update_menu_badge'], 999);
        add_action('wp_ajax_alesta_debug_toggle',      [$this, 'ajax_toggle']);
        add_action('wp_ajax_alesta_debug_get_log',     [$this, 'ajax_get_log']);
        add_action('wp_ajax_alesta_debug_clear_log',   [$this, 'ajax_clear_log']);
        add_action('wp_ajax_alesta_debug_analyze',     [$this, 'ajax_analyze']);
    }

    // -------------------------------------------------------------------------
    // BADGE MENU
    // -------------------------------------------------------------------------

    public function update_menu_badge(): void {
        global $submenu;
        if (empty($submenu['alesta-ai'])) return;

        $debug_on = defined('WP_DEBUG') && WP_DEBUG;
        $label    = $this->build_menu_label($debug_on);

        foreach ($submenu['alesta-ai'] as &$item) {
            if (isset($item[2]) && $item[2] === 'alesta-ai-debug') {
                $item[0] = $label;
                break;
            }
        }
        unset($item);
    }

    private function build_menu_label(bool $debug_on): string {
        if (!$debug_on) {
            return '- Debug Manager <span style="display:inline-block;width:8px;height:8px;'
                 . 'background:#22c55e;border-radius:50%;vertical-align:middle;margin-left:4px;" title="Debug inactif"></span>';
        }

        $lines = $this->get_cached_log_line_count();
        $dot   = '<span style="display:inline-block;width:8px;height:8px;background:#ef4444;'
               . 'border-radius:50%;vertical-align:middle;margin-left:4px;"></span>';

        if ($lines > 0) {
            return '- Debug Manager ' . $dot
                 . ' <span style="font-size:10px;color:#ef4444;" title="'
                 . esc_attr($lines . ' lignes dans debug.log') . '">'
                 . esc_html((string) $lines) . '</span>';
        }

        return '- Debug Manager ' . $dot;
    }

    private function get_cached_log_line_count(): int {
        $cached = get_transient('alesta_debug_badge_count');
        if ($cached !== false) {
            return (int) $cached;
        }

        // WP_DEBUG_LOG writes to wp-content/debug.log by default — this is
        // the canonical location used by WordPress core itself.
        $log_path = WP_CONTENT_DIR . '/debug.log'; // phpcs:ignore WordPressVIPMinimum.Constants.ConstantString.NotCheckingConstantValue -- WP_CONTENT_DIR is the canonical location for debug.log set by WordPress core
        $count    = 0;

        if ($this->init_filesystem()) {
            global $wp_filesystem;
            if ($wp_filesystem->exists($log_path)) {
                $raw = $wp_filesystem->get_contents($log_path);
                if ($raw !== false && $raw !== '') {
                    $count = substr_count($raw, "\n");
                }
            }
        }

        set_transient('alesta_debug_badge_count', $count, 60);
        return $count;
    }

    // -------------------------------------------------------------------------
    // ASSETS
    // -------------------------------------------------------------------------

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, 'alesta-ai-debug') === false) return;

        $ver = ALESTA_VERSION . '.' . time();
        if ( class_exists('Alesta_API') ) {
            Alesta_API::enqueue_key_notice();
        }
        wp_enqueue_script('alesta-debug', plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/debug-admin.js',  ['jquery'], $ver, true);
        wp_enqueue_style('alesta-debug',  plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/debug-admin.css', [], $ver);
        wp_localize_script('alesta-debug', 'AlestaDebug', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('alesta_debug_nonce'),
            'debug_on'  => defined('WP_DEBUG') && WP_DEBUG,
            'has_api'   => class_exists('Alesta_API') && Alesta_API::has_stored_key(),
            'settings_url' => admin_url('admin.php?page=alesta-ai-settings'),
        ]);
    }

    // -------------------------------------------------------------------------
    // PAGE
    // -------------------------------------------------------------------------

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'alesta'));
        }

        $debug_on   = defined('WP_DEBUG') && WP_DEBUG;
        $debug_log  = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
        $log_path   = WP_CONTENT_DIR . '/debug.log'; // phpcs:ignore WordPressVIPMinimum.Constants.ConstantString.NotCheckingConstantValue -- WP_CONTENT_DIR is the canonical location for debug.log set by WordPress core
        $log_exists = file_exists($log_path);
        ?>
        <div class="wrap alesta-wrap" id="alesta-debug-wrap">

            <!-- Header -->
            <div class="alesta-debug-header">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="dashicons dashicons-warning" style="font-size:28px;color:#a0aec0;"></span>
                    <div>
                        <h1 style="color:#fff;margin:0;font-size:18px;">Debug Manager</h1>
                        <p style="color:#94a3b8;margin:0;font-size:13px;">Gestion du mode débogage WordPress</p>
                    </div>
                </div>
                <div class="alesta-debug-pill <?php echo $debug_on ? 'pill-red' : 'pill-green'; ?>">
                    <?php echo $debug_on ? '● DEBUG ACTIF' : '● DEBUG INACTIF'; ?>
                </div>
            </div>

            <!-- Constantes -->
            <div class="alesta-debug-card">
                <div class="alesta-debug-card-title">
                    <span class="dashicons dashicons-admin-settings"></span>
                    Constantes de débogage (wp-config.php)
                </div>
                <div class="alesta-debug-card-body">
                    <table class="alesta-debug-const-table">
                        <tbody>
                            <tr>
                                <td><code>WP_DEBUG</code></td>
                                <td>
                                    <span class="alesta-bool <?php echo $debug_on ? 'bool-true' : 'bool-false'; ?>">
                                        <?php echo $debug_on ? 'true' : 'false'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><code>WP_DEBUG_LOG</code></td>
                                <td>
                                    <span class="alesta-bool <?php echo $debug_log ? 'bool-true' : 'bool-false'; ?>">
                                        <?php echo $debug_log ? 'true' : 'false'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><code>WP_DEBUG_DISPLAY</code></td>
                                <td>
                                    <span class="alesta-bool bool-false">false</span>
                                    <span class="alesta-debug-forced-label">toujours forcé — rien n'est affiché aux visiteurs</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="alesta-debug-notice">
                        Le basculement modifie <strong>wp-config.php</strong> via l'API WordPress (WP_Filesystem).
                        La page se recharge automatiquement pour refléter les nouveaux paramètres.
                        <code>WP_DEBUG_DISPLAY</code> reste toujours à <code>false</code> : aucune erreur n'est visible aux visiteurs.
                    </p>

                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <button id="btn-debug-toggle"
                            data-action="<?php echo $debug_on ? 'disable' : 'enable'; ?>"
                            class="button <?php echo $debug_on ? 'alesta-btn-danger' : 'button-primary'; ?>">
                            <?php echo $debug_on ? 'Désactiver WP_DEBUG' : 'Activer WP_DEBUG'; ?>
                        </button>
                        <span class="spinner" id="toggle-spinner" style="float:none;margin:0;visibility:hidden;"></span>
                        <span id="toggle-msg" style="font-size:13px;display:none;"></span>
                    </div>
                </div>
            </div>

            <!-- Visionneuse log -->
            <div class="alesta-debug-card">
                <div class="alesta-debug-card-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                    <span>
                        <span class="dashicons dashicons-media-text"></span>
                        Visionneuse — <code>wp-content/debug.log</code>
                        <span class="alesta-debug-log-note">(200 dernières lignes, les plus récentes en premier)</span>
                    </span>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <button id="btn-refresh-log" class="button" style="font-size:12px;">
                            <span class="dashicons dashicons-update" style="font-size:14px;line-height:1.8;vertical-align:middle;"></span>
                            Rafraîchir
                        </button>
                        <button id="btn-analyze-log" class="button" style="font-size:12px;background:#667eea;color:#fff;border-color:#5a67d8;"
                            <?php echo !$log_exists ? 'disabled' : ''; ?>>
                            🤖 Analyser avec Claude
                        </button>
                        <button id="btn-clear-log" class="button alesta-btn-danger" style="font-size:12px;"
                            <?php echo !$log_exists ? 'disabled' : ''; ?>>
                            Vider le log
                        </button>
                    </div>
                </div>
                <div id="log-wrap">
                    <?php if ($log_exists) : ?>
                        <div id="log-loading" style="padding:16px 20px;font-size:13px;color:#6b7280;">Chargement…</div>
                        <pre id="log-content" class="alesta-debug-log" style="display:none;"></pre>
                    <?php else : ?>
                        <div class="alesta-debug-empty">
                            <span class="dashicons dashicons-yes-alt" style="color:#22c55e;font-size:22px;vertical-align:middle;"></span>
                            Aucun fichier debug.log — aucune erreur enregistrée.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Zone d'analyse Claude -->
                <div id="claude-analysis-wrap" style="display:none;border-top:1px solid #e5e7eb;">
                    <div style="padding:16px 20px;background:#f8fafc;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                            <div style="font-size:13px;font-weight:700;color:#1d2327;">🤖 Analyse Claude</div>
                            <button id="btn-close-analysis" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:16px;">✕</button>
                        </div>
                        <div id="claude-analysis-loading" style="display:none;text-align:center;padding:20px;color:#6b7280;font-size:13px;">
                            <div style="display:inline-block;width:24px;height:24px;border:3px solid #e2e8f0;border-top-color:#667eea;border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:8px;"></div>
                            Claude analyse vos erreurs…
                        </div>
                        <div id="claude-analysis-content" style="font-size:13px;color:#374151;line-height:1.8;white-space:pre-wrap;"></div>
                    </div>
                </div>
            </div>

        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX — Toggle WP_DEBUG
    // -------------------------------------------------------------------------

    public function ajax_toggle(): void {
        check_ajax_referer('alesta_debug_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Accès refusé.'], 403);
        }

        $action = isset($_POST['debug_action']) ? sanitize_text_field(wp_unslash($_POST['debug_action'])) : '';
        if (!in_array($action, ['enable', 'disable'], true)) {
            wp_send_json_error(['message' => 'Action invalide.']);
        }

        $enable = $action === 'enable';
        $result = $this->write_debug_constants($enable);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        delete_transient('alesta_debug_badge_count');

        wp_send_json_success([
            'message' => $enable
                ? 'WP_DEBUG activé. Rechargement en cours…'
                : 'WP_DEBUG désactivé. Rechargement en cours…',
        ]);
    }

    // -------------------------------------------------------------------------
    // AJAX — Lire le log
    // -------------------------------------------------------------------------

    public function ajax_get_log(): void {
        check_ajax_referer('alesta_debug_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Accès refusé.'], 403);
        }

        if (!$this->init_filesystem()) {
            wp_send_json_error(['message' => "Impossible d'initialiser le système de fichiers."]);
        }

        global $wp_filesystem;
        // WP_DEBUG_LOG writes to wp-content/debug.log by default — this is
        // the canonical location used by WordPress core itself.
        $log_path = WP_CONTENT_DIR . '/debug.log'; // phpcs:ignore WordPressVIPMinimum.Constants.ConstantString.NotCheckingConstantValue -- WP_CONTENT_DIR is the canonical location for debug.log set by WordPress core

        if (!$wp_filesystem->exists($log_path)) {
            wp_send_json_success(['exists' => false, 'content' => '', 'total' => 0]);
        }

        $raw = $wp_filesystem->get_contents($log_path);
        if ($raw === false) {
            wp_send_json_error(['message' => 'Impossible de lire debug.log.']);
        }

        $all_lines = array_values(array_filter(explode("\n", $raw), function($l) { return $l !== ''; }));
        $total     = count($all_lines);
        $last200   = array_reverse(array_slice($all_lines, -200));

        set_transient('alesta_debug_badge_count', $total, 60);

        wp_send_json_success([
            'exists'  => true,
            'content' => esc_html(implode("\n", $last200)),
            'total'   => $total,
        ]);
    }

    // -------------------------------------------------------------------------
    // AJAX — Vider le log
    // -------------------------------------------------------------------------

    public function ajax_clear_log(): void {
        check_ajax_referer('alesta_debug_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Accès refusé.'], 403);
        }

        if (!$this->init_filesystem()) {
            wp_send_json_error(['message' => "Impossible d'initialiser le système de fichiers."]);
        }

        global $wp_filesystem;
        // WP_DEBUG_LOG writes to wp-content/debug.log by default — this is
        // the canonical location used by WordPress core itself.
        $log_path = WP_CONTENT_DIR . '/debug.log'; // phpcs:ignore WordPressVIPMinimum.Constants.ConstantString.NotCheckingConstantValue -- WP_CONTENT_DIR is the canonical location for debug.log set by WordPress core

        if (!$wp_filesystem->exists($log_path)) {
            wp_send_json_success(['message' => 'Fichier debug.log inexistant.']);
        }

        if (!$wp_filesystem->put_contents($log_path, '', FS_CHMOD_FILE)) {
            wp_send_json_error(['message' => 'Impossible de vider debug.log.']);
        }

        delete_transient('alesta_debug_badge_count');
        wp_send_json_success(['message' => 'debug.log vidé avec succès.']);
    }

    // -------------------------------------------------------------------------
    // AJAX — Analyse Claude
    // -------------------------------------------------------------------------

    public function ajax_analyze(): void {
        check_ajax_referer('alesta_debug_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Accès refusé.'], 403);

        if (!class_exists('Alesta_API')) {
            wp_send_json_error(['message' => __('Client API Alesta indisponible.', 'alesta')]);
        }

        $api = new Alesta_API();
        if (!$api->has_key()) {
            wp_send_json_error(Alesta_API::error_payload(Alesta_API::missing_key_error()));
        }

        // Read log
        if (!$this->init_filesystem()) {
            wp_send_json_error(['message' => "Impossible d'initialiser le système de fichiers."]);
        }
        global $wp_filesystem;
        // WP_DEBUG_LOG writes to wp-content/debug.log by default — this is
        // the canonical location used by WordPress core itself.
        $log_path = WP_CONTENT_DIR . '/debug.log'; // phpcs:ignore WordPressVIPMinimum.Constants.ConstantString.NotCheckingConstantValue -- WP_CONTENT_DIR is the canonical location for debug.log set by WordPress core

        if (!$wp_filesystem->exists($log_path)) {
            wp_send_json_error(['message' => 'Fichier debug.log introuvable.']);
        }

        $raw   = $wp_filesystem->get_contents($log_path);
        $lines = array_values(array_filter(explode("\n", $raw ?: ''), function($l) { return trim($l) !== ''; }));
        $last  = array_slice($lines, -100); // dernières 100 lignes max

        if (empty($last)) {
            wp_send_json_error(['message' => 'Le fichier debug.log est vide.']);
        }

        $log_text = implode("\n", $last);
        $site     = get_bloginfo('name');
        $wp_ver   = get_bloginfo('version');
        $php_ver  = PHP_VERSION;

        $prompt = "Tu es un expert WordPress et PHP. Analyse le fichier debug.log ci-dessous provenant du site \"{$site}\" (WordPress {$wp_ver}, PHP {$php_ver}).\n\n"
                . "Pour chaque type d'erreur identifié, fournis :\n"
                . "1. 🔴 **Type d'erreur** — nom clair et compréhensible\n"
                . "2. 📍 **Origine** — fichier et ligne concernés\n"
                . "3. 💡 **Explication** — cause probable en français simple\n"
                . "4. 🔧 **Solution recommandée** — étapes concrètes pour corriger\n\n"
                . "Si plusieurs erreurs sont similaires, regroupe-les. Priorise par sévérité (fatales d'abord).\n"
                . "Termine par un **Résumé général** de l'état de santé du site.\n\n"
                . "Réponds entièrement en français, de façon claire et structurée.\n\n"
                . "--- CONTENU DU DEBUG.LOG (dernières 100 lignes) ---\n"
                . $log_text;

        // L'appel passe par le client commun Alesta_API : il route vers le
        // fournisseur choisi par l'administrateur (Anthropic ou OpenAI), avec
        // SA propre clé (BYOK), et comptabilise les tokens dans le Budget API.
        $response = $api->ask($prompt, 2048);

        if (is_wp_error($response)) {
            wp_send_json_error(Alesta_API::error_payload($response));
        }

        $analysis = sanitize_textarea_field((string) $response);
        if (!$analysis) {
            wp_send_json_error(['message' => __('Réponse vide du fournisseur IA.', 'alesta')]);
        }

        wp_send_json_success([
            'analysis'       => $analysis,
            'lines_analyzed' => count($last),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function init_filesystem(): bool {
        if (!function_exists('WP_Filesystem')) {
            // Loading a WP core admin include — ABSPATH . 'wp-admin/includes/'
            // is the documented way to reach WP_Filesystem().
            require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
        }
        return (bool) WP_Filesystem();
    }

    /**
     * Lit wp-config.php et bascule WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY.
     *
     * @return true|\WP_Error
     */
    private function write_debug_constants(bool $enable) {
        if (!$this->init_filesystem()) {
            return new WP_Error('fs_error', "Impossible d'initialiser le système de fichiers.");
        }

        global $wp_filesystem;

        // wp-config.php is located either at the WordPress root or one level
        // above (the recommended hardened layout). get_home_path() is the
        // official WP helper for the WP root path.
        if ( ! function_exists( 'get_home_path' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
        }
        $config_path = get_home_path() . 'wp-config.php';
        if (!$wp_filesystem->exists($config_path)) {
            $config_path = dirname( rtrim( get_home_path(), '/\\' ) ) . '/wp-config.php';
            if (!$wp_filesystem->exists($config_path)) {
                return new WP_Error('config_not_found', 'wp-config.php introuvable.');
            }
        }

        $content = $wp_filesystem->get_contents($config_path);
        if ($content === false) {
            return new WP_Error('read_error', 'Lecture de wp-config.php impossible.');
        }
        $original = $content;

        $bool    = $enable ? 'true' : 'false';
        $content = $this->upsert_constant($content, 'WP_DEBUG',         $bool);
        $content = $this->upsert_constant($content, 'WP_DEBUG_LOG',     $bool);
        $content = $this->upsert_constant($content, 'WP_DEBUG_DISPLAY', 'false');

        // ALESTA-17 : ne jamais committer un wp-config.php syntaxiquement cassé
        // (écran blanc sur tout le site). On valide le contenu produit…
        if ( ! $this->is_valid_php($content) ) {
            return new WP_Error('invalid_config', 'La modification produirait un wp-config.php invalide — écriture annulée.');
        }
        // …et on garde une copie de secours restaurable avant d'écrire.
        $this->backup_config($config_path, $original);

        if (!$wp_filesystem->put_contents($config_path, $content, FS_CHMOD_FILE)) {
            return new WP_Error('write_error', 'Écriture de wp-config.php impossible.');
        }

        // Le journal par défaut (wp-content/debug.log) est téléchargeable :
        // on pose une protection Apache si aucune n'existe (ALESTA-17).
        if ($enable) {
            $this->protect_debug_log();
        }

        return true;
    }

    /**
     * Le code produit est-il du PHP valide ? Empêche d'écrire un wp-config.php
     * cassé. Si token_get_all n'est pas disponible, on ne bloque pas.
     */
    private function is_valid_php(string $code): bool {
        if ( ! function_exists('token_get_all') || ! defined('TOKEN_PARSE') ) {
            return true;
        }
        try {
            token_get_all($code, TOKEN_PARSE);
            return true;
        } catch (\ParseError $e) {
            return false;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Copie de secours de wp-config.php. Le fichier porte l'extension .php : s'il
     * est demandé par le web il est EXÉCUTÉ (donc n'affiche rien), jamais servi
     * en clair — même protection que wp-config.php lui-même. (ALESTA-17)
     */
    private function backup_config(string $config_path, string $original): void {
        global $wp_filesystem;
        if ( ! is_string($original) || '' === $original ) {
            return;
        }
        $backup = dirname($config_path) . '/wp-config-alesta-backup.php';
        $stamp  = gmdate('Y-m-d H:i:s');
        $header = "<?php /* Sauvegarde Alesta AI de wp-config.php — " . $stamp . " UTC. "
            . "Pour restaurer : renommer ce fichier en wp-config.php. */ ?>\n";
        $wp_filesystem->put_contents($backup, $header . $original, FS_CHMOD_FILE);
    }

    /**
     * Interdit l'accès web à wp-content/debug.log si aucune règle n'existe déjà.
     * Sans effet sous nginx (qui ignore .htaccess) — défense en profondeur Apache.
     */
    private function protect_debug_log(): void {
        global $wp_filesystem;
        if ( ! defined('WP_CONTENT_DIR') ) {
            return;
        }
        $ht = WP_CONTENT_DIR . '/.htaccess';
        if ( $wp_filesystem->exists($ht) ) {
            return; // ne pas écraser une configuration existante.
        }
        $rules = "# Alesta AI (ALESTA-17) : interdit l'accès web au journal de debug\n"
            . "<FilesMatch \"^debug\\.log$\">\n"
            . "  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n"
            . "  <IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n  </IfModule>\n"
            . "</FilesMatch>\n";
        $wp_filesystem->put_contents($ht, $rules, FS_CHMOD_FILE);
    }

    private function upsert_constant(string $content, string $name, string $value): string {
        $pattern = '/^(\h*define\s*\(\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*,\s*)(true|false)(\s*\)\s*;)/mi';

        if (preg_match($pattern, $content)) {
            return (string) preg_replace($pattern, '${1}' . $value . '${3}', $content);
        }

        $new_line    = "define( '" . $name . "', " . $value . " );\n";
        $stop_marker = "/* That's all, stop editing!";

        if (strpos($content, $stop_marker) !== false) {
            return str_replace($stop_marker, $new_line . $stop_marker, $content);
        }

        if (strpos($content, '?>') !== false) {
            return str_replace('?>', $new_line . '?>', $content);
        }

        return $content . $new_line;
    }
}
