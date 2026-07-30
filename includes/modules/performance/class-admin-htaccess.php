<?php
defined('ABSPATH') || exit;

class Alesta_Admin_Htaccess {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(string $hook): void {
        $pages = ['alesta-ai-cache', 'alesta-ai-perf'];
        $match = false;
        foreach ($pages as $p) { if (strpos($hook, $p) !== false) { $match = true; break; } }
        if (!$match) return;

        $ver = ALESTA_VERSION . '.' . time();
        wp_enqueue_script('alesta-htaccess', plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/htaccess.js', ['jquery'], $ver, true);
        wp_enqueue_style('alesta-htaccess',  plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/htaccess.css', [], $ver);
        wp_localize_script('alesta-htaccess', 'AlestaHtaccess', [
            'ajax_url'     => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('alesta_htaccess_nonce'),
        ]);
    }

    public function render_page(string $active_tab = 'cache'): void {
        ?>
        <div class="wrap alesta-wrap" id="alesta-htaccess-wrap">

            <!-- Header -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;background:#1e3a5f;border-radius:8px;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="dashicons dashicons-performance" style="font-size:28px;color:#a0aec0;"></span>
                    <div>
                        <h1 style="color:#fff;margin:0;font-size:18px;">Performance & Optimisation</h1>
                        <p style="color:#94a3b8;margin:0;font-size:13px;">Optimisation du fichier .htaccess pour améliorer la vitesse du site</p>
                    </div>
                </div>
                <div id="htaccess-status-bar" style="font-size:12px;color:#94a3b8;">Chargement...</div>
            </div>

            <!-- Statut global .htaccess -->
            <div id="htaccess-global-status" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:20px;display:none;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                    <div style="display:flex;align-items:center;gap:20px;">
                        <div>
                            <div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">FICHIER .HTACCESS</div>
                            <div id="htaccess-file-status" style="font-size:13px;font-weight:600;"></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">ECRITURE</div>
                            <div id="htaccess-write-status" style="font-size:13px;font-weight:600;"></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">DERNIERE SAUVEGARDE</div>
                            <div id="htaccess-backup-date" style="font-size:13px;color:#374151;"></div>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button id="btn-backup" class="button" style="font-size:12px;">Sauvegarder maintenant</button>
                        <button id="btn-restore" class="button" style="font-size:12px;color:#991b1b;border-color:#fca5a5;" disabled>Restaurer la sauvegarde</button>
                    </div>
                </div>
            </div>

            <!-- Onglets -->
            <div style="display:flex;gap:0;margin-bottom:0;border-bottom:2px solid #e5e7eb;">
                <button class="htaccess-tab <?php echo esc_attr( $active_tab==='cache'?'htaccess-tab-active':'' ); ?>"
                    data-tab="cache" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:<?php echo esc_attr( $active_tab==='cache'?'600':'400' ); ?>;color:<?php echo esc_attr( $active_tab==='cache'?'#1e3a5f':'#6b7280' ); ?>;border-bottom:2px solid <?php echo esc_attr( $active_tab==='cache'?'#1e3a5f':'transparent' ); ?>;margin-bottom:-2px;">
                    Cache navigateur
                </button>
                <button class="htaccess-tab <?php echo esc_attr( $active_tab==='gzip'?'htaccess-tab-active':'' ); ?>"
                    data-tab="gzip" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:<?php echo esc_attr( $active_tab==='gzip'?'600':'400' ); ?>;color:<?php echo esc_attr( $active_tab==='gzip'?'#1e3a5f':'#6b7280' ); ?>;border-bottom:2px solid <?php echo esc_attr( $active_tab==='gzip'?'#1e3a5f':'transparent' ); ?>;margin-bottom:-2px;">
                    Compression GZIP
                </button>
                <button class="htaccess-tab <?php echo esc_attr( $active_tab==='https'?'htaccess-tab-active':'' ); ?>"
                    data-tab="https" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:<?php echo esc_attr( $active_tab==='https'?'600':'400' ); ?>;color:<?php echo esc_attr( $active_tab==='https'?'#1e3a5f':'#6b7280' ); ?>;border-bottom:2px solid <?php echo esc_attr( $active_tab==='https'?'#1e3a5f':'transparent' ); ?>;margin-bottom:-2px;">
                    HTTPS
                </button>
                <button class="htaccess-tab <?php echo esc_attr( $active_tab==='www'?'htaccess-tab-active':'' ); ?>"
                    data-tab="www" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:<?php echo esc_attr( $active_tab==='www'?'600':'400' ); ?>;color:<?php echo esc_attr( $active_tab==='www'?'#1e3a5f':'#6b7280' ); ?>;border-bottom:2px solid <?php echo esc_attr( $active_tab==='www'?'#1e3a5f':'transparent' ); ?>;margin-bottom:-2px;">
                    🌐 WWW
                </button>
            </div>

            <!-- Contenu onglets -->
            <div id="htaccess-tabs-content" style="background:#fff;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 8px 8px;padding:24px;">

                <!-- Onglet Cache -->
                <div id="tab-cache" class="htaccess-tab-content" style="display:<?php echo esc_attr( $active_tab==='cache'?'block':'none' ); ?>;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:24px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:300px;">
                            <h3 style="margin:0 0 8px;font-size:15px;color:#111827;">Cache navigateur</h3>
                            <p style="font-size:13px;color:#6b7280;margin:0 0 16px;line-height:1.6;">
                                Indique aux navigateurs des visiteurs de conserver les fichiers statiques en cache.
                                Les images, CSS et JS ne sont pas retelecharges a chaque visite - la page se charge instantanement pour les visiteurs qui reviennent.
                            </p>

                            <!-- Statut -->
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                                <span style="font-size:13px;color:#374151;">Statut :</span>
                                <span id="cache-status-badge" style="padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600;">Chargement...</span>
                            </div>

                            <!-- Options durees -->
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:16px;margin-bottom:16px;">
                                <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:12px;">DUREES DE CACHE</div>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                    <div>
                                        <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px;">Images</label>
                                        <select id="cache-img-duration" style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;">
                                            <option value="1 month">1 mois</option>
                                            <option value="6 months">6 mois</option>
                                            <option value="1 year" selected>1 an (recommande)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px;">CSS / JavaScript</label>
                                        <select id="cache-css-duration" style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;">
                                            <option value="1 week">1 semaine</option>
                                            <option value="1 month" selected>1 mois (recommande)</option>
                                            <option value="6 months">6 mois</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px;">Polices</label>
                                        <select id="cache-font-duration" style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;">
                                            <option value="6 months">6 mois</option>
                                            <option value="1 year" selected>1 an (recommande)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div style="display:flex;gap:8px;">
                                <button id="btn-apply-cache" class="button button-primary" style="font-size:13px;">Activer le cache navigateur</button>
                                <button id="btn-remove-cache" class="button" style="font-size:13px;color:#991b1b;border-color:#fca5a5;display:none;">Désactiver</button>
                            </div>
                        </div>

                        <!-- Preview code -->
                        <div style="flex:1;min-width:280px;">
                            <div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:8px;">APERCU DU CODE .HTACCESS</div>
                            <pre id="cache-preview" style="background:#1e2a3a;color:#a8d8a8;padding:16px;border-radius:6px;font-size:11px;overflow:auto;max-height:320px;line-height:1.5;margin:0;white-space:pre-wrap;"></pre>
                        </div>
                    </div>
                </div>

                <!-- Onglet GZIP -->
                <div id="tab-gzip" class="htaccess-tab-content" style="display:<?php echo esc_attr( $active_tab==='gzip'?'block':'none' ); ?>;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:24px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:300px;">
                            <h3 style="margin:0 0 8px;font-size:15px;color:#111827;">Compression GZIP</h3>
                            <p style="font-size:13px;color:#6b7280;margin:0 0 16px;line-height:1.6;">
                                Compresse les fichiers HTML, CSS et JavaScript avant de les envoyer au navigateur.
                                Reduit le poids des pages de 60 a 80% - impact direct sur le temps de chargement et le score Google PageSpeed.
                            </p>

                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                                <span style="font-size:13px;color:#374151;">Statut :</span>
                                <span id="gzip-status-badge" style="padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600;">Chargement...</span>
                            </div>

                            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:12px 14px;margin-bottom:16px;font-size:12px;color:#1e40af;">
                                La compression GZIP est automatiquement activée sur les serveurs SiteGround. Ces règles constituent une sécurité supplémentaire pour les navigateurs qui ne détectent pas automatiquement la compression.
                            </div>

                            <div style="display:flex;gap:8px;">
                                <button id="btn-apply-gzip" class="button button-primary" style="font-size:13px;">Activer la compression GZIP</button>
                                <button id="btn-remove-gzip" class="button" style="font-size:13px;color:#991b1b;border-color:#fca5a5;display:none;">Désactiver</button>
                            </div>
                        </div>

                        <div style="flex:1;min-width:280px;">
                            <div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:8px;">APERCU DU CODE .HTACCESS</div>
                            <pre id="gzip-preview" style="background:#1e2a3a;color:#a8d8a8;padding:16px;border-radius:6px;font-size:11px;overflow:auto;max-height:320px;line-height:1.5;margin:0;white-space:pre-wrap;"></pre>
                        </div>
                    </div>
                </div>

                <!-- Onglet HTTPS -->
                <div id="tab-https" class="htaccess-tab-content" style="display:<?php echo esc_attr( $active_tab==='https'?'block':'none' ); ?>;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:24px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:300px;">
                            <h3 style="margin:0 0 8px;font-size:15px;color:#111827;">Redirection HTTPS</h3>
                            <p style="font-size:13px;color:#6b7280;margin:0 0 16px;line-height:1.6;">
                                Force toutes les URLs HTTP vers HTTPS via une redirection 301.
                                Indispensable pour la sécurité et le SEO - Google pénalise les sites sans HTTPS.
                            </p>

                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                                <span style="font-size:13px;color:#374151;">Statut .htaccess :</span>
                                <span id="https-status-badge" style="padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600;">Chargement...</span>
                            </div>

                            <!-- Statut URL WordPress -->
                            <div id="https-url-alert" style="display:none;background:#fef9c3;border:1px solid #fde68a;border-radius:6px;padding:12px 14px;margin-bottom:16px;">
                                <div style="font-size:12px;font-weight:600;color:#713f12;margin-bottom:6px;">URL WordPress encore en HTTP</div>
                                <div style="font-size:12px;color:#713f12;margin-bottom:10px;">
                                    Votre URL WordPress est configurée en HTTP. La redirection .htaccess ne suffit pas - il faut aussi corriger l'URL dans les Réglages WordPress.
                                </div>
                                <button id="btn-fix-https-url" class="button" style="font-size:12px;background:#713f12;color:#fff;border-color:#713f12;">
                                    Corriger l'URL WordPress en HTTPS
                                </button>
                            </div>

                            <div style="display:flex;gap:8px;">
                                <button id="btn-apply-https" class="button button-primary" style="font-size:13px;">Activer la redirection HTTPS</button>
                                <button id="btn-remove-https" class="button" style="font-size:13px;color:#991b1b;border-color:#fca5a5;display:none;">Désactiver</button>
                            </div>
                        </div>

                        <div style="flex:1;min-width:280px;">
                            <div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:8px;">APERCU DU CODE .HTACCESS</div>
                            <pre id="https-preview" style="background:#1e2a3a;color:#a8d8a8;padding:16px;border-radius:6px;font-size:11px;overflow:auto;max-height:200px;line-height:1.5;margin:0;white-space:pre-wrap;"></pre>
                        </div>
                    </div>
                </div>

                <!-- ================================================================
                     Onglet WWW
                     ================================================================ -->
                <?php
                $siteurl     = get_option('siteurl', '');
                $homeurl     = get_option('home', '');
                $has_www     = (bool) preg_match('#^https?://www\.#i', $siteurl);
                $www_marker  = 'Alesta-WWW';
                // Use get_home_path() (official WP helper) for the WP root path.
                if ( ! function_exists( 'get_home_path' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
                }
                $htaccess_path       = get_home_path() . '.htaccess';
                $www_htaccess_active = file_exists( $htaccess_path )
                    && !empty(array_filter(extract_from_markers( $htaccess_path, $www_marker)));
                ?>
                <div id="tab-www" class="htaccess-tab-content" style="display:<?php echo esc_attr( $active_tab==='www'?'block':'none' ); ?>;">
                    <div style="display:flex;flex-direction:column;gap:24px;">

                        <!-- Section 1 : .htaccess WWW redirect -->
                        <div style="display:flex;align-items:flex-start;gap:24px;flex-wrap:wrap;">
                            <div style="flex:1;min-width:300px;">
                                <h3 style="margin:0 0 8px;font-size:15px;color:#111827;">🌐 Redirection WWW (.htaccess)</h3>
                                <p style="font-size:13px;color:#6b7280;margin:0 0 16px;line-height:1.6;">
                                    Force toutes les URLs sans <code>www</code> vers <code>www.votresite.com</code> via une redirection 301.
                                    Évite le contenu dupliqué et améliore la cohérence SEO.
                                </p>

                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                                    <span style="font-size:13px;color:#374151;">Statut .htaccess :</span>
                                    <span id="www-status-badge" style="padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600;background:<?php echo $www_htaccess_active ? '#dcfce7' : '#f3f4f6'; ?>;color:<?php echo $www_htaccess_active ? '#166534' : '#6b7280'; ?>;">
                                        <?php echo $www_htaccess_active ? '✅ Actif' : '⚫ Inactif'; ?>
                                    </span>
                                </div>

                                <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:6px;padding:12px 14px;margin-bottom:16px;font-size:12px;color:#713f12;line-height:1.6;">
                                    ⚠️ <strong>Important :</strong> N'activez pas la redirection WWW si votre certificat SSL ne couvre pas le sous-domaine <code>www</code>.
                                    Vérifiez d'abord que <code>https://www.<?php echo esc_html(preg_replace('#^https?://(www\.)?#i','',wp_parse_url($siteurl, PHP_URL_HOST))); ?></code> est accessible.
                                </div>

                                <div style="display:flex;gap:8px;">
                                    <button id="btn-apply-www" class="button button-primary" style="font-size:13px;<?php echo $www_htaccess_active ? 'display:none;' : ''; ?>">Activer la redirection WWW</button>
                                    <button id="btn-remove-www" class="button" style="font-size:13px;color:#991b1b;border-color:#fca5a5;<?php echo !$www_htaccess_active ? 'display:none;' : ''; ?>">Désactiver</button>
                                </div>
                            </div>

                            <div style="flex:1;min-width:280px;">
                                <div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:8px;">APERÇU DU CODE .HTACCESS</div>
                                <pre id="www-preview" style="background:#1e2a3a;color:#a8d8a8;padding:16px;border-radius:6px;font-size:11px;overflow:auto;max-height:200px;line-height:1.5;margin:0;white-space:pre-wrap;"><?php
                                if ($www_htaccess_active) {
                                    echo esc_html("# BEGIN Alesta-WWW\n<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteCond %{HTTP_HOST} !^www\\. [NC]\n    RewriteRule ^ https://www.%{HTTP_HOST}%{REQUEST_URI} [R=301,L]\n</IfModule>\n# END Alesta-WWW");
                                } else {
                                    echo '— Inactif —';
                                }
                                ?></pre>
                            </div>
                        </div>

                        <!-- Divider -->
                        <hr style="border:none;border-top:1px solid #e5e7eb;margin:0;">

                        <!-- Section 2 : URL WordPress -->
                        <div>
                            <h3 style="margin:0 0 8px;font-size:15px;color:#111827;">⚙️ URL WordPress avec WWW</h3>
                            <p style="font-size:13px;color:#6b7280;margin:0 0 16px;line-height:1.6;">
                                Modifie les réglages <strong>Adresse du site</strong> et <strong>Adresse WordPress</strong> dans la base de données
                                pour y ajouter (ou retirer) le préfixe <code>www</code>.
                            </p>

                            <!-- URL actuelle -->
                            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:14px 16px;margin-bottom:16px;">
                                <div style="font-size:11px;font-weight:700;color:#9ca3af;margin-bottom:10px;">CONFIGURATION ACTUELLE</div>
                                <div style="display:flex;flex-direction:column;gap:6px;font-size:13px;">
                                    <div style="display:flex;gap:10px;">
                                        <span style="color:#6b7280;min-width:180px;">Adresse WordPress (siteurl)</span>
                                        <code style="color:#1e3a5f;font-weight:600;"><?php echo esc_html($siteurl); ?></code>
                                        <?php if ($has_www): ?>
                                            <span style="background:#dcfce7;color:#166534;font-size:11px;padding:1px 8px;border-radius:10px;font-weight:600;">www ✅</span>
                                        <?php else: ?>
                                            <span style="background:#f3f4f6;color:#6b7280;font-size:11px;padding:1px 8px;border-radius:10px;">sans www</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:flex;gap:10px;">
                                        <span style="color:#6b7280;min-width:180px;">Adresse du site (home)</span>
                                        <code style="color:#1e3a5f;font-weight:600;"><?php echo esc_html($homeurl); ?></code>
                                    </div>
                                </div>
                            </div>

                            <!-- Alerte déconnexion -->
                            <div style="background:#fff3cd;border:2px solid #ffc107;border-radius:8px;padding:14px 16px;margin-bottom:16px;">
                                <div style="font-size:13px;font-weight:700;color:#856404;margin-bottom:6px;">
                                    ⚠️ Vous serez déconnecté après cette opération
                                </div>
                                <div style="font-size:12px;color:#856404;line-height:1.7;">
                                    WordPress invalide la session admin lorsque l'URL du site change.
                                    Vous serez automatiquement redirigé vers la page de connexion.
                                    <strong>Assurez-vous de connaître vos identifiants avant de continuer.</strong>
                                </div>
                            </div>

                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <?php if (!$has_www): ?>
                                <button id="btn-add-www-url" class="button button-primary" style="font-size:13px;">
                                    ➕ Ajouter www dans les URLs WordPress
                                </button>
                                <?php else: ?>
                                <button id="btn-remove-www-url" class="button" style="font-size:13px;color:#991b1b;border-color:#fca5a5;">
                                    ➖ Retirer www des URLs WordPress
                                </button>
                                <?php endif; ?>
                                <span id="www-url-msg" style="font-size:13px;align-self:center;"></span>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
        <?php
    }
}
