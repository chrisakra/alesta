<?php
defined('ABSPATH') || exit;

/**
 * Minify & Preload — Interface admin (Alesta)
 *
 * Page single-view avec 4 sections empilées : CSS, JS, HTML, Preload CSS.
 * Aucun accès Freemius / hooks Pro. Toutes les chaînes utilisateur en FR.
 */
class Alesta_Admin_Minify {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue( string $hook ): void {
        if ( strpos($hook, 'alesta-ai-minify') === false ) return;

        $ver = ALESTA_VERSION . '.' . time();
        wp_enqueue_style(
            'alesta-minify-admin',
            plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/minify-admin.css',
            [],
            $ver
        );
        wp_enqueue_script(
            'alesta-minify-admin',
            plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/minify-admin.js',
            ['jquery'],
            $ver,
            true
        );
        wp_localize_script('alesta-minify-admin', 'AlestaMinify', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('alesta_minify_nonce'),
        ]);
    }

    // =========================================================================
    // PAGE
    // =========================================================================

    public function render_page(): void {
        if ( ! current_user_can('manage_options') ) wp_die( esc_html__('Accès refusé.', 'alesta') );

        $s        = Alesta_Minify_Module::settings();
        $stats    = Alesta_Minify_Module::get_stats();
        $dir_ok   = Alesta_Minify_Module::ensure_cache_dir();
        $css_on   = ! empty($s['css_enabled']);
        $js_on    = ! empty($s['js_enabled']);
        $html_on  = ! empty($s['html_enabled']);
        $prel_on  = ! empty($s['preload_enabled']);
        ?>
        <div class="wrap" id="minify-wrap">

            <!-- ── En-tête ── -->
            <div class="mnf-header">
                <div style="display:flex;align-items:center;gap:14px;">
                    <span class="dashicons dashicons-editor-code" style="font-size:32px;color:#a0aec0;"></span>
                    <div>
                        <h1 class="mnf-title">Minification &amp; Preload</h1>
                        <p class="mnf-subtitle">Réduit le poids des fichiers CSS, JS, HTML et prépare les CSS critiques via des hints <code>preload</code>.</p>
                    </div>
                </div>
                <div class="mnf-status-bar">
                    Cache : <?php echo (int) $stats['total_files']; ?> fichier(s) — <?php echo esc_html($stats['total_size']); ?>
                </div>
            </div>

            <!-- ── Avertissement ── -->
            <div class="mnf-warn">
                <span class="mnf-warn-icon">⚠</span>
                <div>
                    <strong>Avertissement.</strong>
                    La minification peut casser certains thèmes ou plugins mal codés.
                    Testez chaque bascule <em>une par une</em> et vérifiez votre site après activation.
                    En cas de souci, désactivez la bascule concernée et videz le cache.
                </div>
            </div>

            <!-- ================================================================
                 Section 1 — Minify CSS
                 ================================================================ -->
            <div class="mnf-card">
                <div class="mnf-card-body">
                    <div class="mnf-col-main">
                        <h3 class="mnf-h3">🎨 Minification CSS</h3>
                        <p class="mnf-lead">
                            Supprime les commentaires, espaces et caractères superflus des fichiers CSS chargés sur le frontend.
                            Les fichiers minifiés sont mis en cache dans <code>wp-content/cache/alesta-minify/</code>.
                            Les fichiers originaux ne sont jamais modifiés.
                        </p>

                        <!-- Toggle -->
                        <div class="mnf-toggle-row">
                            <span class="mnf-toggle-lbl">Activer la minification CSS :</span>
                            <label class="mnf-toggle">
                                <input type="checkbox" class="mnf-switch" data-type="css_enabled" <?php checked($css_on); ?>>
                                <span class="mnf-slider <?php echo $css_on ? 'on' : ''; ?>">
                                    <span class="mnf-knob"></span>
                                </span>
                            </label>
                            <span class="mnf-status-label" data-type="css_enabled"><?php echo $css_on ? 'Actif' : 'Inactif'; ?></span>
                        </div>

                        <!-- Exclusions -->
                        <div class="mnf-box">
                            <div class="mnf-box-title">EXCLUSIONS (handles séparés par virgule)</div>
                            <textarea id="minify-css-excludes" class="mnf-textarea" rows="3"
                                placeholder="elementor-frontend, woocommerce-layout, mon-theme-style"><?php echo esc_textarea($s['css_excludes']); ?></textarea>
                            <p class="mnf-hint">Les CSS déjà minifiés (.min.css) sont automatiquement ignorés.</p>
                        </div>

                        <div class="mnf-actions">
                            <button id="btn-save-css" class="button button-primary">💾 Enregistrer</button>
                            <button id="btn-clear-minify-cache" class="button">🗑 Vider le cache</button>
                            <span class="spinner" id="spinner-css"></span>
                            <span id="msg-css" class="mnf-msg"></span>
                        </div>
                    </div>

                    <div class="mnf-col-side">
                        <div class="mnf-stats-box">
                            <div class="mnf-stats-title">CACHE MINIFICATION</div>
                            <div class="mnf-stat">
                                <div class="mnf-stat-lbl">Fichiers CSS en cache</div>
                                <div class="mnf-stat-val" id="stat-css-files"><?php echo esc_html($stats['css_files']); ?></div>
                            </div>
                            <div class="mnf-stat">
                                <div class="mnf-stat-lbl">Fichiers JS en cache</div>
                                <div class="mnf-stat-val" id="stat-js-files"><?php echo esc_html($stats['js_files']); ?></div>
                            </div>
                            <div class="mnf-stat">
                                <div class="mnf-stat-lbl">Taille totale cache</div>
                                <div class="mnf-stat-val small" id="stat-total-size"><?php echo esc_html($stats['total_size']); ?></div>
                            </div>
                            <div class="mnf-stats-footer">
                                <div class="mnf-stat-lbl">Dossier cache</div>
                                <code>wp-content/cache/alesta-minify/</code>
                                <div class="mnf-writable <?php echo $dir_ok ? 'ok' : 'ko'; ?>">
                                    <?php echo $dir_ok ? '✅ Accessible en écriture' : '❌ Non accessible'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================================================================
                 Section 2 — Minify JS
                 ================================================================ -->
            <div class="mnf-card">
                <div class="mnf-card-body">
                    <div class="mnf-col-main">
                        <h3 class="mnf-h3">⚡ Minification JavaScript</h3>
                        <p class="mnf-lead">
                            Supprime les commentaires et réduit les espaces dans les fichiers JavaScript.
                            Approche conservatrice : les chaînes de caractères et le code fonctionnel ne sont pas altérés.
                            jQuery, jQuery Migrate et les scripts WordPress core sont exclus automatiquement.
                        </p>

                        <div class="mnf-toggle-row">
                            <span class="mnf-toggle-lbl">Activer la minification JS :</span>
                            <label class="mnf-toggle">
                                <input type="checkbox" class="mnf-switch" data-type="js_enabled" <?php checked($js_on); ?>>
                                <span class="mnf-slider <?php echo $js_on ? 'on' : ''; ?>">
                                    <span class="mnf-knob"></span>
                                </span>
                            </label>
                            <span class="mnf-status-label" data-type="js_enabled"><?php echo $js_on ? 'Actif' : 'Inactif'; ?></span>
                        </div>

                        <div class="mnf-tip">
                            ⚠ <strong>Conseil :</strong> testez sur un environnement de staging avant d'activer en production.
                            En cas de problème, videz le cache ou désactivez pour revenir au comportement normal.
                            Les fichiers <code>.min.js</code> déjà minifiés sont automatiquement ignorés.
                        </div>

                        <div class="mnf-box">
                            <div class="mnf-box-title">EXCLUSIONS (handles séparés par virgule)</div>
                            <textarea id="minify-js-excludes" class="mnf-textarea" rows="3"
                                placeholder="elementor, wc-cart, mon-script-custom"><?php echo esc_textarea($s['js_excludes']); ?></textarea>
                            <p class="mnf-hint">Toujours exclus : jquery, jquery-core, jquery-migrate, wp-embed, wp-polyfill.</p>
                        </div>

                        <div class="mnf-actions">
                            <button id="btn-save-js" class="button button-primary">💾 Enregistrer</button>
                            <button class="button btn-clear-minify-cache-js">🗑 Vider le cache</button>
                            <span class="spinner" id="spinner-js"></span>
                            <span id="msg-js" class="mnf-msg"></span>
                        </div>
                    </div>

                    <div class="mnf-col-side">
                        <div class="mnf-info-box">
                            <strong>Bypass automatique</strong>
                            <p>Détection active des page-builders — la minification est désactivée pendant les éditions en direct :</p>
                            <ul class="mnf-list">
                                <li>Elementor</li>
                                <li>Divi</li>
                                <li>Oxygen</li>
                                <li>Beaver Builder</li>
                                <li>Bricks</li>
                                <li>WPBakery</li>
                                <li>Brizy / Thrive</li>
                                <li>Customizer WP</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================================================================
                 Section 3 — Minify HTML
                 ================================================================ -->
            <div class="mnf-card">
                <div class="mnf-card-body">
                    <div class="mnf-col-main">
                        <h3 class="mnf-h3">📄 Minification HTML</h3>
                        <p class="mnf-lead">
                            Réduit le poids des pages HTML à la volée via un buffer PHP (<code>ob_start</code>).
                            Les blocs <code>&lt;script&gt;</code>, <code>&lt;style&gt;</code>, <code>&lt;pre&gt;</code> et <code>&lt;textarea&gt;</code>
                            sont préservés intégralement. L'admin et les requêtes AJAX sont exclus automatiquement.
                        </p>

                        <div class="mnf-toggle-row">
                            <span class="mnf-toggle-lbl">Activer la minification HTML :</span>
                            <label class="mnf-toggle">
                                <input type="checkbox" class="mnf-switch" data-type="html_enabled" <?php checked($html_on); ?>>
                                <span class="mnf-slider <?php echo $html_on ? 'on' : ''; ?>">
                                    <span class="mnf-knob"></span>
                                </span>
                            </label>
                            <span class="mnf-status-label" data-type="html_enabled"><?php echo $html_on ? 'Actif' : 'Inactif'; ?></span>
                        </div>

                        <div class="mnf-box">
                            <div class="mnf-box-title">OPTIONS</div>
                            <label class="mnf-check">
                                <input type="checkbox" id="html-remove-comments" <?php checked( ! empty($s['html_remove_comments']) ); ?>>
                                <span><strong>Supprimer les commentaires HTML</strong><br>
                                <span class="mnf-hint">Les commentaires conditionnels IE et les blocs WordPress (<code>&lt;!--wp:--&gt;</code>) sont conservés.</span></span>
                            </label>
                            <label class="mnf-check">
                                <input type="checkbox" id="html-remove-whitespace" <?php checked( ! empty($s['html_remove_whitespace']) ); ?>>
                                <span><strong>Réduire les espaces entre balises</strong><br>
                                <span class="mnf-hint">Compresse les sauts de ligne et indentations entre les éléments HTML.</span></span>
                            </label>
                        </div>

                        <div class="mnf-actions">
                            <button id="btn-save-html" class="button button-primary">💾 Enregistrer</button>
                            <span class="spinner" id="spinner-html"></span>
                            <span id="msg-html" class="mnf-msg"></span>
                        </div>
                    </div>

                    <div class="mnf-col-side">
                        <div class="mnf-info-box">
                            <strong>CE QUI EST PRÉSERVÉ</strong>
                            <ul class="mnf-list checks">
                                <li>&lt;script&gt; … &lt;/script&gt;</li>
                                <li>&lt;style&gt; … &lt;/style&gt;</li>
                                <li>&lt;pre&gt; … &lt;/pre&gt;</li>
                                <li>&lt;textarea&gt; … &lt;/textarea&gt;</li>
                                <li>Commentaires IE &lt;!--[if …]&gt;</li>
                                <li>Balises noindex WordPress</li>
                            </ul>
                            <div class="mnf-info-footer">
                                Gain typique : <strong>5 — 15 %</strong> sur le poids HTML.<br>
                                Aucun fichier créé — traitement à la volée.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================================================================
                 Section 4 — Preload CSS
                 ================================================================ -->
            <div class="mnf-card">
                <div class="mnf-card-body">
                    <div class="mnf-col-main">
                        <h3 class="mnf-h3">🚀 Preload CSS</h3>
                        <p class="mnf-lead">
                            Injecte des balises <code>&lt;link rel="preload" as="style"&gt;</code> dans le <code>&lt;head&gt;</code> pour signaler
                            au navigateur de charger les feuilles de style en priorité. Améliore le LCP et réduit le blocage du rendu.
                        </p>

                        <div class="mnf-toggle-row">
                            <span class="mnf-toggle-lbl">Activer les hints Preload CSS :</span>
                            <label class="mnf-toggle">
                                <input type="checkbox" class="mnf-switch" data-type="preload_enabled" <?php checked($prel_on); ?>>
                                <span class="mnf-slider <?php echo $prel_on ? 'on' : ''; ?>">
                                    <span class="mnf-knob"></span>
                                </span>
                            </label>
                            <span class="mnf-status-label" data-type="preload_enabled"><?php echo $prel_on ? 'Actif' : 'Inactif'; ?></span>
                        </div>

                        <div class="mnf-box">
                            <div class="mnf-box-title">MODE</div>
                            <label class="mnf-check">
                                <input type="radio" name="preload_mode" value="all" <?php checked($s['preload_mode'], 'all'); ?> id="preload-mode-all">
                                <span><strong>Automatique</strong> — précharger tous les CSS enqueued<br>
                                <span class="mnf-hint">Utilise les exclusions pour affiner.</span></span>
                            </label>
                            <label class="mnf-check">
                                <input type="radio" name="preload_mode" value="manual" <?php checked($s['preload_mode'], 'manual'); ?> id="preload-mode-manual">
                                <span><strong>Manuel</strong> — spécifier les handles à précharger<br>
                                <span class="mnf-hint">Idéal pour cibler uniquement les CSS critiques.</span></span>
                            </label>
                        </div>

                        <div id="preload-manual-section" class="mnf-box" style="display:<?php echo $s['preload_mode'] === 'manual' ? 'block' : 'none'; ?>;">
                            <label class="mnf-box-title" for="preload-handles">Handles à précharger (séparés par virgule)</label>
                            <input type="text" id="preload-handles" class="mnf-input"
                                   value="<?php echo esc_attr($s['preload_handles']); ?>"
                                   placeholder="my-theme-style, woocommerce-general" />
                            <p class="mnf-hint">Le handle est le 1er paramètre de <code>wp_enqueue_style('handle', ...)</code>.</p>
                        </div>

                        <div class="mnf-box">
                            <label class="mnf-box-title" for="preload-excludes">Exclusions (handles séparés par virgule)</label>
                            <input type="text" id="preload-excludes" class="mnf-input"
                                   value="<?php echo esc_attr($s['preload_excludes']); ?>"
                                   placeholder="dashicons, admin-bar" />
                        </div>

                        <div class="mnf-actions">
                            <button id="btn-save-preload" class="button button-primary">💾 Enregistrer</button>
                            <span class="spinner" id="spinner-preload"></span>
                            <span id="msg-preload" class="mnf-msg"></span>
                        </div>
                    </div>

                    <div class="mnf-col-side">
                        <div class="mnf-info-box blue">
                            <strong>Comment ça fonctionne ?</strong>
                            <p>Le navigateur reçoit un hint <code>preload</code> avant même de parser le HTML.
                            Il commence à télécharger le CSS en parallèle, réduisant le blocage du rendu (render-blocking).</p>
                            <pre class="mnf-code">&lt;link rel="preload"
     as="style"
     href="style.css"&gt;</pre>
                        </div>
                        <div class="mnf-info-box green">
                            <strong>Impact attendu</strong>
                            <ul class="mnf-list checks">
                                <li>Amélioration du LCP</li>
                                <li>Réduction du blocage du rendu</li>
                                <li>Score PageSpeed amélioré</li>
                                <li>Compatible CDN et cache</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <?php
    }
}
