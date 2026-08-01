<?php
defined('ABSPATH') || exit;

class Alesta_Admin_Errors {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, 'alesta-ai-links') === false) return;
        $ver = ALESTA_VERSION . '.' . time();
        wp_enqueue_script('alesta-errors', plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/errors.js', ['jquery'], $ver, true);
        wp_localize_script('alesta-errors', 'AlestaErrors', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('alesta_errors_nonce'),
        ]);
    }

    public function render_page(): void {
        $report    = Alesta_Errors_Module::get_last_report();
        $has_report = !empty($report);
        $errors    = $report['errors']   ?? [];
        $scan_date = $report['date']     ?? '';
        $total_err = $report['total_errors']   ?? 0;
        $total_ok  = $report['total_ok']       ?? 0;
        $total_chk = $report['total_checked']  ?? 0;
        $pages_scanned = $report['pages_scanned'] ?? 0;

        // Grouper les erreurs par code
        $by_code = [];
        foreach ($errors as $e) {
            $by_code[$e['code']] = ($by_code[$e['code']] ?? 0) + 1;
        }
        ?>
        <div class="wrap alesta-wrap" id="alesta-errors-wrap">

            <!-- Header -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;background:#1e3a5f;border-radius:8px;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="dashicons dashicons-warning" style="font-size:28px;color:#a0aec0;"></span>
                    <div>
                        <h1 style="color:#fff;margin:0;font-size:18px;">Erreurs 4xx / 5xx</h1>
                        <p style="color:#94a3b8;margin:0;font-size:13px;">Detection des liens cassés et erreurs HTTP sur toutes vos pages</p>
                    </div>
                </div>
                <?php if ($scan_date): ?>
                <span style="font-size:11px;color:#94a3b8;">Dernier scan : <?php echo esc_html(gmdate('d/m/Y H:i', strtotime($scan_date))); ?></span>
                <?php endif; ?>
            </div>

            <!-- Bouton scan + stats -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-bottom:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
                    <div>
                        <h3 style="margin:0 0 4px;font-size:14px;color:#111827;">Scanner les erreurs HTTP</h3>
                        <p style="margin:0;font-size:12px;color:#6b7280;">Analyse tous les liens dans vos pages, articles et produits WooCommerce.</p>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <div id="scan-progress" style="display:none;font-size:12px;color:#6b7280;"></div>
                        <button id="btn-scan" class="button button-primary" style="font-size:13px;">
                            Lancer le scan
                        </button>
                    </div>
                </div>

                <!-- Barre de progression -->
                <div id="scan-progress-bar" style="display:none;margin-top:16px;">
                    <div style="background:#f3f4f6;border-radius:4px;height:8px;">
                        <div id="scan-bar-fill" style="background:#1e3a5f;height:8px;border-radius:4px;width:0%;transition:width .3s;"></div>
                    </div>
                    <p id="scan-bar-text" style="margin:6px 0 0;font-size:12px;color:#6b7280;"></p>
                </div>
            </div>

            <?php if ($has_report): ?>

            <!-- Stats -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;text-align:center;">
                    <div style="font-size:28px;font-weight:700;color:#1e3a5f;"><?php echo esc_html( $pages_scanned ); ?></div>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px;">Pages analysees</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;text-align:center;">
                    <div style="font-size:28px;font-weight:700;color:#374151;"><?php echo esc_html( $total_chk ); ?></div>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px;">Liens verifies</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;text-align:center;">
                    <div style="font-size:28px;font-weight:700;color:#065f46;"><?php echo esc_html( $total_ok ); ?></div>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px;">Liens OK</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;text-align:center;">
                    <div style="font-size:28px;font-weight:700;color:<?php echo esc_attr( $total_err > 0 ? '#991b1b' : '#065f46' ); ?>;"><?php echo esc_html( $total_err ); ?></div>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px;">Erreurs détectées</div>
                </div>
            </div>

            <?php if ($total_err === 0): ?>
            <!-- Aucune erreur -->
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:40px;text-align:center;">
                <div style="font-size:40px;margin-bottom:12px;">&#10003;</div>
                <h3 style="color:#065f46;margin:0 0 8px;">Aucune erreur détectée</h3>
                <p style="color:#6b7280;font-size:13px;margin:0;">Tous les <?php echo esc_html( $total_chk ); ?> liens verifies retournent un code HTTP valide.</p>
            </div>

            <?php else: ?>

            <!-- Résumé par code -->
            <?php if (!empty($by_code)): ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
                <?php foreach ($by_code as $code => $count):
                    $c = $code >= 500 ? '#991b1b' : ($code >= 400 ? '#713f12' : '#374151');
                    $bg = $code >= 500 ? '#fee2e2' : ($code >= 400 ? '#fef9c3' : '#f3f4f6');
                ?>
                <span style="background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $c ); ?>;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:600;">
                    <?php echo esc_html( $code ); ?> - <?php echo esc_html( $count ); ?> erreur<?php echo esc_html( $count > 1 ? 's' : '' ); ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Tableau des erreurs -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                <!-- Filtres -->
                <div style="padding:12px 16px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:8px;">
                    <select id="err-filter-code" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;">
                        <option value="all">Tous les codes</option>
                        <option value="4xx">Erreurs 4xx</option>
                        <option value="5xx">Erreurs 5xx</option>
                        <option value="0">Timeout / Inaccessible</option>
                    </select>
                    <select id="err-filter-type" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;">
                        <option value="all">Tous les types</option>
                        <option value="page">Pages</option>
                        <option value="post">Articles</option>
                        <option value="product">Produits</option>
                    </select>
                    <input type="text" id="err-search" placeholder="Rechercher une URL..."
                        style="padding:6px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;width:220px;">
                    <span id="err-count" style="margin-left:auto;font-size:12px;color:#6b7280;"></span>
                </div>

                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="padding:10px 16px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;width:80px;">CODE</th>
                            <th style="padding:10px 16px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">URL CASSEE</th>
                            <th style="padding:10px 16px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">PAGE SOURCE</th>
                            <th style="padding:10px 16px;text-align:center;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;width:140px;">ACTION</th>
                        </tr>
                    </thead>
                    <tbody id="err-tbody">
                    <?php foreach ($errors as $i => $err):
                        $code  = $err['code'];
                        $cc    = $code >= 500 ? '#991b1b' : ($code >= 400 ? '#713f12' : '#374151');
                        $cbg   = $code >= 500 ? '#fee2e2' : ($code >= 400 ? '#fef9c3' : '#f3f4f6');
                        $fam   = $code >= 500 ? '5xx' : ($code >= 400 ? '4xx' : '0');
                    ?>
                    <tr class="err-row"
                        data-code="<?php echo esc_attr($fam); ?>"
                        data-type="<?php echo esc_attr($err['source_type']); ?>"
                        data-url="<?php echo esc_attr(strtolower($err['broken_url'])); ?>">
                        <td style="padding:10px 16px;border-bottom:1px solid #f3f4f6;">
                            <span style="background:<?php echo esc_attr( $cbg ); ?>;color:<?php echo esc_attr( $cc ); ?>;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;">
                                <?php echo esc_html( $code ?: 'ERR' ); ?>
                            </span>
                        </td>
                        <td style="padding:10px 16px;border-bottom:1px solid #f3f4f6;">
                            <div style="font-size:12px;color:#374151;word-break:break-all;margin-bottom:4px;">
                                <?php echo esc_html($err['broken_url']); ?>
                            </div>
                            <div style="font-size:11px;color:#9ca3af;"><?php echo esc_html($err['message']); ?></div>
                        </td>
                        <td style="padding:10px 16px;border-bottom:1px solid #f3f4f6;">
                            <a href="<?php echo esc_url($err['source_url']); ?>" target="_blank"
                               style="font-size:12px;color:#1e3a5f;text-decoration:none;font-weight:500;">
                                <?php echo esc_html($err['source_title']); ?>
                            </a>
                            <div style="font-size:11px;color:#9ca3af;margin-top:2px;">
                                <span style="background:#f3f4f6;padding:1px 6px;border-radius:3px;"><?php echo esc_html($err['source_type']); ?></span>
                                <a href="<?php echo esc_url(get_edit_post_link($err['source_id'])); ?>" style="color:#6b7280;margin-left:6px;font-size:11px;">Modifier</a>
                            </div>
                        </td>
                        <td style="padding:10px 16px;border-bottom:1px solid #f3f4f6;text-align:center;">
                            <button class="button button-small err-btn-fix"
                                data-post-id="<?php echo esc_attr( $err['source_id'] ); ?>"
                                data-old-url="<?php echo esc_attr($err['broken_url']); ?>"
                                style="font-size:11px;">
                                Corriger
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php endif; // total_err > 0 ?>
            <?php endif; // has_report ?>

            <!-- Modal correction -->
            <div id="err-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;padding:40px 20px;overflow-y:auto;">
                <div style="background:#fff;border-radius:10px;max-width:560px;margin:0 auto;padding:24px;position:relative;">
                    <button id="err-modal-close" style="position:absolute;top:12px;right:12px;background:none;border:none;font-size:22px;cursor:pointer;color:#9ca3af;">&times;</button>
                    <h3 style="margin:0 0 4px;font-size:16px;color:#1e3a5f;">Corriger ce lien</h3>
                    <p style="margin:0 0 16px;font-size:12px;color:#9ca3af;">Remplacer l'URL cassee par une nouvelle URL valide dans le contenu de la page.</p>
                    <div id="err-modal-body"></div>
                </div>
            </div>

        </div>
        <?php
    }
}
