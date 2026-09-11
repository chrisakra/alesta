<?php
defined('ABSPATH') || exit;

/**
 * Health Check Dashboard — Alesta
 * Affiche l'état de santé complet de l'installation WordPress.
 */
class Alesta_Admin_Health {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'alesta-ai-health' ) === false ) return;
        $ver = ALESTA_VERSION . '.' . time();
        wp_enqueue_style( 'alesta-health',  plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/health-admin.css', [], $ver );
        wp_enqueue_script( 'alesta-health', plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/health-admin.js',  ['jquery'], $ver, true );
    }

    // =========================================================================
    // RENDU DE LA PAGE
    // =========================================================================

    public function render_page(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__('Accès refusé.', 'alesta') );
        }

        $groups = $this->run_all_checks();
        $stats  = $this->compute_stats($groups);
        ?>
        <div class="wrap" id="alesta-health-wrap">

            <!-- ── En-tête ── -->
            <div class="alesta-health-header">
                <div class="alesta-health-header-left">
                    <h1>🩺 Health Check Dashboard</h1>
                    <p class="alesta-health-subtitle">
                        État de santé de votre installation WordPress —
                        <?php echo esc_html( get_bloginfo('name') ); ?>
                    </p>
                </div>
                <div class="alesta-health-score score-<?php echo esc_attr($stats['status']); ?>">
                    <span class="score-number"><?php echo esc_html($stats['score']); ?></span>
                    <span class="score-percent">%</span>
                    <span class="score-label"><?php echo esc_html($stats['label']); ?></span>
                </div>
            </div>

            <!-- ── Bandeau stats ── -->
            <div class="alesta-health-statbar">
                <div class="hstat hstat-ok">
                    <span class="hstat-icon">✅</span>
                    <span class="hstat-num"><?php echo esc_html($stats['ok']); ?></span>
                    <span class="hstat-lbl">OK</span>
                </div>
                <div class="hstat hstat-warn">
                    <span class="hstat-icon">⚠️</span>
                    <span class="hstat-num"><?php echo esc_html($stats['warn']); ?></span>
                    <span class="hstat-lbl">Attention</span>
                </div>
                <div class="hstat hstat-error">
                    <span class="hstat-icon">❌</span>
                    <span class="hstat-num"><?php echo esc_html($stats['error']); ?></span>
                    <span class="hstat-lbl">Problème</span>
                </div>
                <div class="hstat hstat-total">
                    <span class="hstat-icon">📋</span>
                    <span class="hstat-num"><?php echo esc_html($stats['total']); ?></span>
                    <span class="hstat-lbl">Vérifications</span>
                </div>
                <div class="hstat-refresh">
                    <button id="btn-health-refresh" class="button button-secondary">
                        🔄 Actualiser
                    </button>
                    <span class="hstat-date">
                        Analysé le <?php echo esc_html( date_i18n('d/m/Y à H:i') ); ?>
                    </span>
                </div>
            </div>

            <!-- ── Grille de cartes ── -->
            <div class="alesta-health-grid">
                <?php foreach ($groups as $group) : ?>
                <div class="alesta-health-card">
                    <div class="health-card-title">
                        <span class="health-card-icon"><?php echo esc_html($group['icon']); ?></span>
                        <?php echo esc_html($group['title']); ?>
                        <span class="health-card-badge">
                            <?php
                            $g_ok    = count(array_filter($group['items'], fn($i) => $i['status'] === 'ok'));
                            $g_total = count($group['items']);
                            echo esc_html($g_ok . '/' . $g_total);
                            ?>
                        </span>
                    </div>
                    <div class="health-card-body">
                        <?php foreach ($group['items'] as $item) : ?>
                        <div class="health-check-item health-check-<?php echo esc_attr($item['status']); ?>">
                            <span class="health-check-dot"></span>
                            <div class="health-check-content">
                                <div class="health-check-top">
                                    <span class="health-check-label"><?php echo esc_html($item['label']); ?></span>
                                    <span class="health-check-value"><?php echo esc_html($item['value']); ?></span>
                                </div>
                                <?php if ( ! empty($item['note']) ) : ?>
                                <div class="health-check-note"><?php echo esc_html($item['note']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div><!-- /.alesta-health-grid -->

        </div><!-- /#alesta-health-wrap -->
        <?php
    }

    // =========================================================================
    // ORCHESTRATION DES VÉRIFICATIONS
    // =========================================================================

    /**
     * @return array<int, array{icon:string, title:string, items:array}>
     */
    private function run_all_checks(): array {
        return [
            $this->group_wordpress(),
            $this->group_php(),
            $this->group_database(),
            $this->group_security(),
            $this->group_performance(),
            $this->group_filesystem(),
        ];
    }

    /**
     * @param array<int, array{icon:string, title:string, items:array}> $groups
     * @return array{ok:int, warn:int, error:int, total:int, score:int, status:string, label:string}
     */
    private function compute_stats( array $groups ): array {
        $ok = $warn = $error = 0;
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                if ($item['status'] === 'ok')        $ok++;
                elseif ($item['status'] === 'warn')  $warn++;
                else                                 $error++;
            }
        }
        $total = $ok + $warn + $error;
        $score = $total > 0 ? (int) round(($ok + $warn * 0.5) / $total * 100) : 100;

        if ($score >= 85)      { $status = 'good'; $label = 'Excellent'; }
        elseif ($score >= 65)  { $status = 'warn'; $label = 'Bon';       }
        else                   { $status = 'bad';  $label = 'À revoir';  }

        return compact('ok', 'warn', 'error', 'total', 'score', 'status', 'label');
    }

    // =========================================================================
    // GROUPE 1 — WORDPRESS
    // =========================================================================

    private function group_wordpress(): array {
        global $wp_version;

        /* Dernière version disponible via le transient natif de WP */
        $latest    = $this->get_wp_latest_version();
        $is_latest = version_compare($wp_version, $latest, '>=');

        /* Mises à jour auto */
        $auto_up = defined('WP_AUTO_UPDATE_CORE') && WP_AUTO_UPDATE_CORE;

        /* Extensions actives */
        $active_count  = count(get_option('active_plugins', []));

        /* Extensions en attente de mise à jour */
        $plugin_updates = get_site_transient('update_plugins');
        $plugin_upd_count = 0;
        if ($plugin_updates && ! empty($plugin_updates->response)) {
            $plugin_upd_count = count($plugin_updates->response);
        }

        /* Thème enfant */
        $has_child = (get_stylesheet() !== get_template());

        return [
            'icon'  => '🔷',
            'title' => 'WordPress',
            'items' => [
                [
                    'label'  => 'Version WordPress',
                    'value'  => $wp_version . ( $is_latest ? ' (à jour)' : ' → ' . $latest . ' dispo' ),
                    'status' => $is_latest ? 'ok' : 'warn',
                    'note'   => $is_latest ? '' : 'Une mise à jour WordPress est disponible.',
                ],
                [
                    'label'  => 'Mises à jour auto du core',
                    'value'  => $auto_up ? 'Activées' : 'Désactivées',
                    'status' => $auto_up ? 'ok' : 'warn',
                    'note'   => '',
                ],
                [
                    'label'  => 'Extensions en attente de MAJ',
                    'value'  => $plugin_upd_count > 0 ? $plugin_upd_count . ' extension(s)' : 'Aucune',
                    'status' => $plugin_upd_count === 0 ? 'ok' : ($plugin_upd_count <= 3 ? 'warn' : 'error'),
                    'note'   => $plugin_upd_count > 0 ? 'Mettez à jour vos extensions régulièrement.' : '',
                ],
                [
                    'label'  => 'Extensions actives',
                    'value'  => $active_count . ' extension(s)',
                    'status' => $active_count <= 20 ? 'ok' : ($active_count <= 35 ? 'warn' : 'error'),
                    'note'   => $active_count > 20 ? 'Trop d\'extensions peut ralentir le site.' : '',
                ],
                [
                    'label'  => 'Thème enfant',
                    'value'  => $has_child ? 'Utilisé' : 'Non utilisé',
                    'status' => $has_child ? 'ok' : 'warn',
                    'note'   => ! $has_child ? 'Un thème enfant protège vos personnalisations.' : '',
                ],
            ],
        ];
    }

    private function get_wp_latest_version(): string {
        $update = get_site_transient('update_core');
        if ($update && ! empty($update->updates)) {
            foreach ($update->updates as $u) {
                if (isset($u->current) && in_array($u->response, ['upgrade', 'latest'], true)) {
                    return $u->current;
                }
            }
        }
        return get_bloginfo('version');
    }

    // =========================================================================
    // GROUPE 2 — PHP & SERVEUR
    // =========================================================================

    private function group_php(): array {
        $php_ver    = PHP_VERSION;
        $php_status = version_compare($php_ver, '8.1', '>=') ? 'ok'
                    : (version_compare($php_ver, '8.0', '>=') ? 'warn' : 'error');

        $mem_raw    = ini_get('memory_limit');
        $mem_mb     = $this->parse_ini_size($mem_raw);
        $mem_status = $mem_mb >= 256 ? 'ok' : ($mem_mb >= 128 ? 'warn' : 'error');

        $max_exec    = (int) ini_get('max_execution_time');
        $exec_status = $max_exec === 0 || $max_exec >= 60 ? 'ok' : ($max_exec >= 30 ? 'warn' : 'error');

        $upload_raw    = ini_get('upload_max_filesize');
        $upload_mb     = $this->parse_ini_size($upload_raw);
        $upload_status = $upload_mb >= 64 ? 'ok' : ($upload_mb >= 32 ? 'warn' : 'error');

        $required_exts = ['curl', 'json', 'mbstring', 'openssl', 'zip', 'gd', 'intl'];
        $missing_exts  = [];
        foreach ($required_exts as $ext) {
            if ( ! extension_loaded($ext) ) $missing_exts[] = $ext;
        }

        return [
            'icon'  => '⚙️',
            'title' => 'PHP & Serveur',
            'items' => [
                [
                    'label'  => 'Version PHP',
                    'value'  => $php_ver,
                    'status' => $php_status,
                    'note'   => $php_status === 'ok' ? ''
                              : ($php_status === 'warn' ? 'PHP 8.1+ recommandé.' : 'Version obsolète, risque sécurité majeur.'),
                ],
                [
                    'label'  => 'Limite mémoire',
                    'value'  => $mem_raw,
                    'status' => $mem_status,
                    'note'   => $mem_status !== 'ok' ? '256M ou plus recommandé.' : '',
                ],
                [
                    'label'  => 'Temps d\'exécution max',
                    'value'  => $max_exec === 0 ? 'Illimité' : $max_exec . 's',
                    'status' => $exec_status,
                    'note'   => $exec_status !== 'ok' ? '60s minimum recommandé pour les imports.' : '',
                ],
                [
                    'label'  => 'Upload max fichier',
                    'value'  => $upload_raw,
                    'status' => $upload_status,
                    'note'   => $upload_status !== 'ok' ? '64M recommandé pour les médias.' : '',
                ],
                [
                    'label'  => 'Extensions PHP',
                    'value'  => empty($missing_exts) ? 'Toutes présentes' : 'Manquantes : ' . implode(', ', $missing_exts),
                    'status' => empty($missing_exts) ? 'ok' : 'error',
                    'note'   => '',
                ],
            ],
        ];
    }

    // =========================================================================
    // GROUPE 3 — BASE DE DONNÉES
    // =========================================================================

    private function group_database(): array {
        global $wpdb;

        $db_version = (string) $wpdb->get_var('SELECT VERSION()'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $is_maria   = stripos($db_version, 'mariadb') !== false;
        $db_label   = $is_maria ? 'MariaDB' : 'MySQL';
        $db_ok      = $is_maria
            ? version_compare($db_version, '10.3', '>=')
            : version_compare($db_version, '5.7', '>=');

        /* Taille BDD */
        $db_name  = DB_NAME;
        $size_raw = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = %s",
                $db_name
            )
        );
        $size_mb = round($size_raw / 1024 / 1024, 2);

        /* Nombre de tables */
        $table_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema = %s",
                $db_name
            )
        );

        /* Révisions d'articles */
        $revisions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
        );

        /* Transients expirés */
        $expired_transients = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
                '_transient_timeout_%',
                time()
            )
        );

        return [
            'icon'  => '🗄️',
            'title' => 'Base de données',
            'items' => [
                [
                    'label'  => 'Version ' . $db_label,
                    'value'  => $db_version,
                    'status' => $db_ok ? 'ok' : 'warn',
                    'note'   => ! $db_ok ? 'Version ancienne, mise à jour recommandée.' : '',
                ],
                [
                    'label'  => 'Taille de la BDD',
                    'value'  => $size_mb . ' Mo',
                    'status' => $size_mb < 200 ? 'ok' : ($size_mb < 500 ? 'warn' : 'error'),
                    'note'   => $size_mb >= 200 ? 'BDD volumineuse, nettoyage recommandé.' : '',
                ],
                [
                    'label'  => 'Nombre de tables',
                    'value'  => $table_count . ' tables',
                    'status' => 'ok',
                    'note'   => '',
                ],
                [
                    'label'  => 'Révisions d\'articles',
                    'value'  => number_format($revisions) . ' révisions',
                    'status' => $revisions < 500 ? 'ok' : ($revisions < 2000 ? 'warn' : 'error'),
                    'note'   => $revisions >= 500 ? 'Utilisez le Nettoyeur BDD pour supprimer les révisions.' : '',
                ],
                [
                    'label'  => 'Transients expirés',
                    'value'  => $expired_transients . ' entrée(s)',
                    'status' => $expired_transients < 100 ? 'ok' : ($expired_transients < 500 ? 'warn' : 'error'),
                    'note'   => $expired_transients >= 100 ? 'Nettoyez les transients expirés.' : '',
                ],
            ],
        ];
    }

    // =========================================================================
    // GROUPE 4 — SÉCURITÉ
    // =========================================================================

    private function group_security(): array {
        $https = is_ssl();

        $file_edit_disabled = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;

        $debug_on = defined('WP_DEBUG') && WP_DEBUG;

        if ( ! function_exists( 'get_home_path' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
        }
        $home_path     = trailingslashit( get_home_path() );
        $readme_exists = file_exists( $home_path . 'readme.html' );

        $config_above = ! file_exists( $home_path . 'wp-config.php' )
                     && file_exists( dirname( rtrim( $home_path, '/\\' ) ) . '/wp-config.php' );

        /* Clés de sécurité définies */
        $salt_keys    = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY'];
        $salts_ok     = true;
        foreach ($salt_keys as $k) {
            if ( ! defined($k) || strlen(constant($k)) < 20 ) {
                $salts_ok = false;
                break;
            }
        }

        /* Connexions SSL BDD */
        $db_ssl = defined('DB_SSL') && DB_SSL;

        return [
            'icon'  => '🔒',
            'title' => 'Sécurité',
            'items' => [
                [
                    'label'  => 'HTTPS actif',
                    'value'  => $https ? 'Oui' : 'Non',
                    'status' => $https ? 'ok' : 'error',
                    'note'   => ! $https ? 'HTTPS est indispensable pour la sécurité.' : '',
                ],
                [
                    'label'  => 'Éditeur de fichiers WP',
                    'value'  => $file_edit_disabled ? 'Désactivé' : 'Activé',
                    'status' => $file_edit_disabled ? 'ok' : 'warn',
                    'note'   => ! $file_edit_disabled ? 'Ajoutez DISALLOW_FILE_EDIT true dans wp-config.php.' : '',
                ],
                [
                    'label'  => 'WP_DEBUG en production',
                    'value'  => $debug_on ? 'Activé ⚠️' : 'Désactivé',
                    'status' => $debug_on ? 'warn' : 'ok',
                    'note'   => $debug_on ? 'Désactivez WP_DEBUG sur un site en production.' : '',
                ],
                [
                    'label'  => 'readme.html',
                    'value'  => $readme_exists ? 'Présent' : 'Absent',
                    'status' => $readme_exists ? 'warn' : 'ok',
                    'note'   => $readme_exists ? 'Expose la version WP. Supprimez ce fichier.' : '',
                ],
                [
                    'label'  => 'Clés de sécurité WordPress',
                    'value'  => $salts_ok ? 'Configurées' : 'Manquantes / trop courtes',
                    'status' => $salts_ok ? 'ok' : 'error',
                    'note'   => ! $salts_ok ? 'Régénérez vos clés via le générateur officiel WordPress.' : '',
                ],
                [
                    'label'  => 'wp-config.php hors racine',
                    'value'  => $config_above ? 'Oui' : 'Dans la racine web',
                    'status' => $config_above ? 'ok' : 'warn',
                    'note'   => ! $config_above ? 'Déplacer wp-config.php un niveau au-dessus est plus sûr.' : '',
                ],
            ],
        ];
    }

    // =========================================================================
    // GROUPE 5 — PERFORMANCE
    // =========================================================================

    private function group_performance(): array {
        $object_cache = wp_using_ext_object_cache();

        $active_plugins  = get_option('active_plugins', []);

        /* Plugin de cache */
        $cache_plugins = [
            'w3-total-cache/w3-total-cache.php',
            'wp-super-cache/wp-cache.php',
            'wp-rocket/wp-rocket.php',
            'litespeed-cache/litespeed-cache.php',
            'wp-fastest-cache/wpFastestCache.php',
            'cache-enabler/cache-enabler.php',
            'hummingbird-performance/wp-hummingbird.php',
        ];
        $has_cache = false;
        foreach ($cache_plugins as $p) {
            if (in_array($p, $active_plugins, true)) { $has_cache = true; break; }
        }

        /* WP Cron natif vs système */
        $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;

        /* Plugin d'optimisation d'images */
        $img_plugins = [
            'imagify/imagify.php',
            'ewww-image-optimizer/ewww-image-optimizer.php',
            'shortpixel-image-optimiser/wp-shortpixel.php',
            'smush/wp-smush.php',
            'tiny-compress-images/tiny-compress-images.php',
            'optimole-wp/optimole-wp.php',
        ];
        $has_img_opt = false;
        foreach ($img_plugins as $p) {
            if (in_array($p, $active_plugins, true)) { $has_img_opt = true; break; }
        }

        /* GZIP/Brotli via extension PHP */
        $gzip_ok = extension_loaded('zlib');

        /* OPcache PHP */
        $opcache_ok = function_exists('opcache_get_status') && opcache_get_status(false) !== false;

        return [
            'icon'  => '⚡',
            'title' => 'Performance',
            'items' => [
                [
                    'label'  => 'Cache objet externe',
                    'value'  => $object_cache ? 'Actif (Redis/Memcached)' : 'Inactif',
                    'status' => $object_cache ? 'ok' : 'warn',
                    'note'   => ! $object_cache ? 'Redis ou Memcached améliore les performances.' : '',
                ],
                [
                    'label'  => 'Plugin de cache',
                    'value'  => $has_cache ? 'Détecté' : 'Aucun',
                    'status' => $has_cache ? 'ok' : 'warn',
                    'note'   => ! $has_cache ? 'WP Rocket, LiteSpeed Cache ou W3TC recommandé.' : '',
                ],
                [
                    'label'  => 'OPcache PHP',
                    'value'  => $opcache_ok ? 'Actif' : 'Inactif',
                    'status' => $opcache_ok ? 'ok' : 'warn',
                    'note'   => ! $opcache_ok ? 'Activez OPcache pour accélérer l\'exécution PHP.' : '',
                ],
                [
                    'label'  => 'WP Cron',
                    'value'  => $cron_disabled ? 'Cron système (recommandé)' : 'WP Cron natif',
                    'status' => $cron_disabled ? 'ok' : 'warn',
                    'note'   => ! $cron_disabled ? 'Un cron système est plus fiable et performant.' : '',
                ],
                [
                    'label'  => 'Plugin optimisation images',
                    'value'  => $has_img_opt ? 'Détecté' : 'Aucun',
                    'status' => $has_img_opt ? 'ok' : 'warn',
                    'note'   => ! $has_img_opt ? 'Imagify, Smush ou ShortPixel recommandé.' : '',
                ],
                [
                    'label'  => 'Extension zlib (GZIP)',
                    'value'  => $gzip_ok ? 'Disponible' : 'Non disponible',
                    'status' => $gzip_ok ? 'ok' : 'warn',
                    'note'   => ! $gzip_ok ? 'Activez zlib pour la compression GZIP.' : '',
                ],
            ],
        ];
    }

    // =========================================================================
    // GROUPE 6 — SYSTÈME DE FICHIERS
    // =========================================================================

    private function group_filesystem(): array {
        $upload_dir = wp_upload_dir();

        $uploads_writable = wp_is_writable($upload_dir['basedir']);
        // The Health Check reports whether wp-content/ itself is writable.
        $content_writable = wp_is_writable( WP_CONTENT_DIR ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_is_writable_wp_is_writable
        // Health Check looks at well-known files served from the WP root.
        // get_home_path() is already loaded above in group_security().
        if ( ! function_exists( 'get_home_path' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
        }
        $wp_root         = trailingslashit( get_home_path() );
        $htaccess_exists = file_exists( $wp_root . '.htaccess' );
        $robots_exists   = file_exists( $wp_root . 'robots.txt' );
        $llms_exists     = file_exists( $wp_root . 'llms.txt' );

        /* Taille du dossier uploads (via transient 1h pour éviter un scan lent) */
        $uploads_mb = get_transient('alesta_health_uploads_size');
        if ($uploads_mb === false) {
            $uploads_mb = round($this->dir_size($upload_dir['basedir']) / 1024 / 1024, 1);
            set_transient('alesta_health_uploads_size', $uploads_mb, HOUR_IN_SECONDS);
        }

        return [
            'icon'  => '📁',
            'title' => 'Fichiers & Dossiers',
            'items' => [
                [
                    'label'  => 'Dossier uploads',
                    'value'  => $uploads_writable ? 'Accessible en écriture' : 'Non accessible',
                    'status' => $uploads_writable ? 'ok' : 'error',
                    'note'   => ! $uploads_writable ? 'Vérifiez les permissions (755 dossier / 644 fichiers).' : '',
                ],
                [
                    'label'  => 'Taille du dossier uploads',
                    'value'  => $uploads_mb . ' Mo',
                    'status' => $uploads_mb < 1000 ? 'ok' : ($uploads_mb < 3000 ? 'warn' : 'error'),
                    'note'   => $uploads_mb >= 1000 ? 'Dossier volumineux, pensez à archiver les anciens médias.' : '',
                ],
                [
                    'label'  => 'Dossier wp-content',
                    'value'  => $content_writable ? 'Accessible en écriture' : 'Non accessible',
                    'status' => $content_writable ? 'ok' : 'warn',
                    'note'   => '',
                ],
                [
                    'label'  => '.htaccess',
                    'value'  => $htaccess_exists ? 'Présent' : 'Absent',
                    'status' => $htaccess_exists ? 'ok' : 'warn',
                    'note'   => ! $htaccess_exists ? 'Régénérez via Réglages > Permaliens.' : '',
                ],
                [
                    'label'  => 'robots.txt',
                    'value'  => $robots_exists ? 'Présent' : 'Absent',
                    'status' => $robots_exists ? 'ok' : 'warn',
                    'note'   => ! $robots_exists ? 'Créez un robots.txt pour guider les crawlers.' : '',
                ],
                [
                    'label'  => 'llms.txt (IA)',
                    'value'  => $llms_exists ? 'Présent' : 'Absent',
                    'status' => $llms_exists ? 'ok' : 'warn',
                    'note'   => ! $llms_exists ? 'Fichier optionnel décrivant votre site pour les moteurs IA (Claude, GPT, Perplexity).' : '',
                ],
            ],
        ];
    }

    // =========================================================================
    // UTILITAIRES
    // =========================================================================

    /**
     * Convertit une valeur ini (128M, 2G…) en mégaoctets.
     */
    private function parse_ini_size( string $size ): int {
        $size = trim($size);
        if (empty($size) || $size === '-1') return 9999;
        $unit = strtolower(substr($size, -1));
        $val  = (int) $size;
        if ($unit === 'g') return $val * 1024;
        if ($unit === 'm') return $val;
        if ($unit === 'k') return (int) ($val / 1024);
        return $val;
    }

    /**
     * Taille récursive d'un répertoire (mis en cache via transient).
     */
    private function dir_size( string $dir ): int {
        if ( ! is_dir($dir) ) return 0;
        $size     = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) $size += $file->getSize();
        }
        return $size;
    }
}
