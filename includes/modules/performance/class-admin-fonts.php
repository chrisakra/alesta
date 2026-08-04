<?php
defined('ABSPATH') || exit;

/**
 * Google Fonts RGPD — Interface admin (Alesta)
 */
class Alesta_Admin_Fonts {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue( string $hook ): void {
        if ( strpos($hook, 'alesta-ai-fonts') === false ) return;
        $ver = ALESTA_VERSION . '.' . time();
        wp_enqueue_script('alesta-fonts', plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/fonts-rgpd.js', ['jquery'], $ver, true);
        wp_enqueue_style('alesta-fonts',  plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/fonts-rgpd.css', [], $ver);
        wp_localize_script('alesta-fonts', 'AlestaFonts', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('alesta_fonts_nonce'),
        ]);
    }

    // =========================================================================
    // PAGE
    // =========================================================================

    public function render_page(): void {
        if ( ! current_user_can('manage_options') ) wp_die( esc_html__('Accès refusé.', 'alesta') );

        $s        = Alesta_Fonts_Module::settings();
        $registry = Alesta_Fonts_Module::registry();
        $stats    = Alesta_Fonts_Module::get_stats();
        $dir_ok   = Alesta_Fonts_Module::ensure_fonts_dir();
        $fonts_dir = Alesta_Fonts_Module::fonts_dir();
        ?>
        <div class="wrap" id="fonts-wrap">

            <!-- ── En-tête ── -->
            <div class="gf-header">
                <div style="display:flex;align-items:center;gap:14px;">
                    <div style="font-size:32px;">Aa</div>
                    <div>
                        <h1 class="gf-title">Optimiseur Google Fonts RGPD</h1>
                        <p class="gf-subtitle">Auto-hébergez vos polices Google Fonts pour supprimer tout transfert de données vers Google — conformité RGPD (LG München, jan. 2022).</p>
                    </div>
                </div>
                <!-- Badge mode actif -->
                <div class="gf-mode-badge gf-mode-<?php echo esc_attr($s['mode']); ?>">
                    <?php
                    $mode_labels = ['disabled' => '⏸ Désactivé', 'auto_host' => '✅ Auto-hébergement', 'block' => '🚫 Blocage total'];
                    echo esc_html($mode_labels[$s['mode']] ?? '⏸ Désactivé');
                    ?>
                </div>
            </div>

            <!-- ── Alerte RGPD ── -->
            <div class="gf-rgpd-alert">
                <span class="gf-rgpd-icon">⚖️</span>
                <div>
                    <strong>Risque RGPD identifié.</strong>
                    Chaque chargement de Google Fonts depuis <code>fonts.googleapis.com</code> transmet l'adresse IP du visiteur à Google, sans consentement explicite.
                    Le Tribunal de Munich a condamné un site web pour ce motif en janvier 2022.
                    <strong>Solution :</strong> héberger les polices localement.
                </div>
            </div>

            <!-- ── Mode ── -->
            <div class="gf-card gf-mode-card">
                <div class="gf-card-title">🎛 Mode de fonctionnement</div>
                <div class="gf-modes-grid">

                    <?php foreach ([
                        'disabled'  => ['icon' => '⏸', 'label' => 'Désactivé', 'desc' => 'Le plugin n\'intervient pas. Les Google Fonts sont chargées normalement depuis les serveurs Google.', 'color' => '#6b7280'],
                        'auto_host' => ['icon' => '✅', 'label' => 'Auto-hébergement', 'desc' => 'Les Google Fonts détectées sont remplacées par vos copies locales. Zéro contact avec Google pour les visiteurs.', 'color' => '#16a34a'],
                        'block'     => ['icon' => '🚫', 'label' => 'Blocage total', 'desc' => 'Toutes les Google Fonts sont supprimées du chargement. Le site utilisera les polices système de secours.', 'color' => '#dc2626'],
                    ] as $mode_key => $mode_info) : ?>
                    <label class="gf-mode-option <?php echo $s['mode'] === $mode_key ? 'gf-mode-active' : ''; ?>"
                           data-mode="<?php echo esc_attr($mode_key); ?>"
                           style="--mode-color:<?php echo esc_attr($mode_info['color']); ?>;">
                        <input type="radio" name="gf_mode" value="<?php echo esc_attr($mode_key); ?>"
                               <?php checked($s['mode'], $mode_key); ?> style="display:none;">
                        <div class="gf-mode-icon"><?php echo esc_html($mode_info['icon']); ?></div>
                        <div class="gf-mode-label"><?php echo esc_html($mode_info['label']); ?></div>
                        <div class="gf-mode-desc"><?php echo esc_html($mode_info['desc']); ?></div>
                    </label>
                    <?php endforeach; ?>

                </div>
                <div style="display:flex;gap:8px;align-items:center;margin-top:14px;">
                    <button id="btn-save-mode" class="button button-primary">💾 Appliquer le mode</button>
                    <span class="spinner" id="spinner-mode" style="float:none;margin:0;"></span>
                    <span id="msg-mode" class="gf-msg"></span>
                </div>
            </div>

            <!-- ── Stats ── -->
            <div class="gf-statbar">
                <div class="gf-stat">
                    <span class="gf-stat-val" id="gf-stat-total"><?php echo esc_html($stats['total']); ?></span>
                    <span class="gf-stat-lbl">Requêtes détectées</span>
                </div>
                <div class="gf-stat">
                    <span class="gf-stat-val gf-green" id="gf-stat-downloaded"><?php echo esc_html($stats['downloaded']); ?></span>
                    <span class="gf-stat-lbl">✅ Hébergées localement</span>
                </div>
                <div class="gf-stat">
                    <span class="gf-stat-val gf-orange" id="gf-stat-pending"><?php echo esc_html($stats['pending']); ?></span>
                    <span class="gf-stat-lbl">⏳ En attente</span>
                </div>
                <div class="gf-stat">
                    <span class="gf-stat-val gf-red" id="gf-stat-errors"><?php echo esc_html($stats['errors']); ?></span>
                    <span class="gf-stat-lbl">❌ Erreurs</span>
                </div>
                <div class="gf-stat">
                    <span class="gf-stat-val gf-blue" id="gf-stat-size"><?php echo esc_html($stats['size_kb']); ?> Ko</span>
                    <span class="gf-stat-lbl">💾 Taille locale</span>
                </div>
            </div>

            <!-- ── Actions en lot ── -->
            <div class="gf-actions-bar">
                <div class="gf-actions-left">
                    <button id="btn-scan" class="button button-primary">
                        🔍 Scanner le site
                    </button>
                    <button id="btn-download-all" class="button"
                            <?php echo $stats['pending'] === 0 ? 'disabled' : ''; ?>>
                        ⬇️ Télécharger toutes les polices (<?php echo esc_html($stats['pending']); ?>)
                    </button>
                    <button id="btn-clear-all" class="button"
                            <?php echo $stats['total'] === 0 ? 'disabled' : ''; ?>
                            style="color:#991b1b;border-color:#fca5a5;">
                        🗑 Supprimer les fichiers locaux
                    </button>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="spinner" id="spinner-actions" style="float:none;margin:0;"></span>
                    <span id="msg-actions" class="gf-msg"></span>
                </div>
            </div>

            <!-- ── Dossier local ── -->
            <div class="gf-info-bar">
                <span class="gf-info-icon"><?php echo $dir_ok ? '✅' : '❌'; ?></span>
                <div>
                    <strong>Dossier de stockage :</strong>
                    <?php
                    // Display the storage folder as a relative path for readability.
                    // get_home_path() is used to strip the WP root prefix cosmetically.
                    if ( ! function_exists( 'get_home_path' ) ) {
                        require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
                    }
                    ?>
                    <code><?php echo esc_html( str_replace( get_home_path(), '', $fonts_dir ) ); ?></code>
                    <?php echo $dir_ok ? '<span style="color:#16a34a;margin-left:8px;">Accessible en écriture</span>' : '<span style="color:#dc2626;margin-left:8px;">Non accessible — vérifiez les permissions</span>'; ?>
                </div>
            </div>

            <!-- ── Liste des polices détectées ── -->
            <?php if ( empty($registry) ) : ?>
            <div class="gf-empty">
                <div style="font-size:48px;margin-bottom:12px;">🔍</div>
                <h3>Aucune police détectée</h3>
                <p>Cliquez sur <strong>"Scanner le site"</strong> pour détecter automatiquement les Google Fonts utilisées sur votre site.</p>
                <button id="btn-scan-empty" class="button button-primary" style="margin-top:8px;">🔍 Scanner maintenant</button>
            </div>
            <?php else : ?>

            <div class="gf-fonts-list" id="gf-fonts-list">
                <?php foreach ($registry as $key => $entry) : ?>
                <?php $this->render_font_card($key, $entry); ?>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>

        </div><!-- #fonts-wrap -->
        <?php
    }

    // =========================================================================
    // CARTE D'UNE POLICE
    // =========================================================================

    private function render_font_card( string $key, array $entry ): void {
        $is_downloaded = ! empty($entry['local_css_url']);
        $has_error     = ! empty($entry['error']);
        $families      = $entry['families'] ?? ['Famille inconnue'];
        $handles       = $entry['handles']  ?? [];
        $source_map    = ['wp_styles' => 'WordPress enqueue', 'html_scan' => 'Scan HTML', 'html_import' => '@import CSS'];
        $source_label  = $source_map[ $entry['source'] ?? '' ] ?? 'Détecté';

        $status_class = $is_downloaded ? 'gf-status-ok' : ( $has_error ? 'gf-status-error' : 'gf-status-pending' );
        $status_text  = $is_downloaded ? '✅ Hébergé localement' : ( $has_error ? '❌ Erreur' : '⏳ En attente' );
        ?>
        <div class="gf-font-card <?php echo esc_attr($status_class); ?>"
             id="gf-card-<?php echo esc_attr($key); ?>"
             data-key="<?php echo esc_attr($key); ?>">

            <!-- En-tête carte -->
            <div class="gf-font-card-header">
                <div class="gf-font-info">
                    <div class="gf-font-families">
                        <?php foreach ($families as $fam) : ?>
                        <span class="gf-family-badge"><?php echo esc_html($fam); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="gf-font-meta">
                        <span class="gf-source-tag"><?php echo esc_html($source_label); ?></span>
                        <?php if ($handles) : ?>
                        <span class="gf-handles-tag">Handles WP : <?php echo esc_html(implode(', ', $handles)); ?></span>
                        <?php endif; ?>
                        <?php if ($is_downloaded) : ?>
                        <span class="gf-downloaded-info">
                            <?php echo esc_html($entry['font_files']); ?> fichier(s) —
                            <?php echo esc_html($entry['size_kb']); ?> Ko —
                            <?php echo esc_html( date_i18n('d/m/Y H:i', strtotime($entry['downloaded_at'])) ); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="gf-font-actions">
                    <span class="gf-status-badge <?php echo esc_attr($status_class); ?>"
                          id="gf-status-<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($status_text); ?>
                    </span>
                    <?php if ( ! $is_downloaded ) : ?>
                    <button class="button button-primary gf-btn-download"
                            data-key="<?php echo esc_attr($key); ?>">
                        ⬇️ Télécharger
                    </button>
                    <?php else : ?>
                    <button class="button gf-btn-redownload"
                            data-key="<?php echo esc_attr($key); ?>">
                        🔄 Re-télécharger
                    </button>
                    <?php endif; ?>
                    <button class="button gf-btn-delete"
                            data-key="<?php echo esc_attr($key); ?>"
                            title="Supprimer les fichiers locaux">🗑</button>
                </div>
            </div>

            <!-- URL originale -->
            <div class="gf-font-url">
                <span class="gf-url-label">URL Google :</span>
                <code class="gf-url-val"><?php echo esc_html( mb_substr($entry['url'], 0, 120) . (strlen($entry['url']) > 120 ? '…' : '') ); ?></code>
            </div>

            <!-- URL locale (si téléchargé) -->
            <?php if ($is_downloaded) : ?>
            <div class="gf-font-url gf-font-local">
                <span class="gf-url-label">URL locale :</span>
                <code class="gf-url-val" style="color:#16a34a;"><?php echo esc_html($entry['local_css_url']); ?></code>
            </div>
            <?php endif; ?>

            <!-- Message d'erreur -->
            <?php if ($has_error) : ?>
            <div class="gf-error-msg">
                ⚠️ <?php echo esc_html($entry['error']); ?>
            </div>
            <?php endif; ?>

            <!-- Spinner -->
            <div class="gf-card-spinner" id="gf-spinner-<?php echo esc_attr($key); ?>" style="display:none;">
                <span class="spinner is-active" style="float:none;margin-right:8px;"></span>
                Téléchargement en cours…
            </div>

        </div>
        <?php
    }
}
