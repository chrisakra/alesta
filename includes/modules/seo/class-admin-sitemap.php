<?php
defined('ABSPATH') || exit;

class Alesta_Admin_Sitemap {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, 'alesta-ai-sitemap') === false) return;

        $ver = ALESTA_VERSION . '.' . time();
        wp_enqueue_script('alesta-sitemap', plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/sitemap.js', ['jquery'], $ver, true);
        wp_enqueue_style('alesta-sitemap',  plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/sitemap.css', [], $ver);
        wp_localize_script('alesta-sitemap', 'AlestaSitemap', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('alesta_sitemap_nonce'),
        ]);
    }

    public function render_page(): void {
        ?>
        <div class="wrap alesta-wrap" id="alesta-sitemap-wrap">

            <!-- Header -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;background:#1e3a5f;border-radius:8px;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="dashicons dashicons-networking" style="font-size:28px;color:#a0aec0;"></span>
                    <div>
                        <h1 style="color:#fff;margin:0;font-size:18px;">Sitemap XML</h1>
                        <p style="color:#94a3b8;margin:0;font-size:13px;">Generation et soumission du plan du site aux moteurs de recherche</p>
                    </div>
                </div>
                <div id="sitemap-status-bar" style="font-size:12px;color:#94a3b8;">Chargement...</div>
            </div>

            <!-- Statut global -->
            <div id="sitemap-global-status" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:20px;display:none;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                    <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
                        <div>
                            <div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">FICHIER SITEMAP.XML</div>
                            <div id="sitemap-file-status" style="font-size:13px;font-weight:600;"></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">DERNIERE GENERATION</div>
                            <div id="sitemap-gen-date" style="font-size:13px;color:#374151;"></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">DERNIER PING</div>
                            <div id="sitemap-ping-date" style="font-size:13px;color:#374151;"></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">URL</div>
                            <div id="sitemap-url" style="font-size:13px;"></div>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button id="btn-sitemap-ping" class="button" style="font-size:12px;" disabled>Notifier Google &amp; Bing</button>
                        <button id="btn-sitemap-delete" class="button" style="font-size:12px;color:#991b1b;border-color:#fca5a5;" disabled>Supprimer</button>
                    </div>
                </div>
            </div>

            <!-- Resultats ping -->
            <div id="sitemap-ping-result" style="display:none;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;margin-bottom:20px;"></div>

            <!-- Corps principal : Options + Stats -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

                <!-- Colonne gauche : options de generation -->
                <div>
                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-bottom:16px;">
                        <h3 style="margin:0 0 16px;font-size:15px;color:#111827;">Options de generation</h3>

                        <!-- Contenu : post types -->
                        <div style="margin-bottom:20px;">
                            <div style="font-size:11px;font-weight:600;color:#6b7280;letter-spacing:.05em;margin-bottom:10px;">CONTENU</div>
                            <div id="opt-post-types" style="display:flex;flex-direction:column;gap:8px;">
                                <div style="color:#9ca3af;font-size:13px;">Chargement...</div>
                            </div>
                        </div>

                        <hr style="border:none;border-top:1px solid #f3f4f6;margin:0 0 16px;">

                        <!-- Enrichissements -->
                        <div style="margin-bottom:20px;">
                            <div style="font-size:11px;font-weight:600;color:#6b7280;letter-spacing:.05em;margin-bottom:10px;">ENRICHISSEMENTS</div>
                            <div style="display:flex;flex-direction:column;gap:10px;">

                                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
                                    <input type="checkbox" id="opt-images" style="margin-top:2px;width:15px;height:15px;">
                                    <div>
                                        <div style="font-size:13px;font-weight:500;color:#374151;">Images</div>
                                        <div style="font-size:12px;color:#9ca3af;">Miniatures, galeries WooCommerce, images du contenu</div>
                                    </div>
                                </label>

                                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
                                    <input type="checkbox" id="opt-videos" style="margin-top:2px;width:15px;height:15px;">
                                    <div>
                                        <div style="font-size:13px;font-weight:500;color:#374151;">Vidéos</div>
                                        <div style="font-size:12px;color:#9ca3af;">YouTube, Vimeo, MP4 auto-hébergés — detectés automatiquement dans le contenu</div>
                                    </div>
                                </label>

                                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
                                    <input type="checkbox" id="opt-taxonomies" style="margin-top:2px;width:15px;height:15px;">
                                    <div>
                                        <div style="font-size:13px;font-weight:500;color:#374151;">Categories et tags</div>
                                        <div style="font-size:12px;color:#9ca3af;">Pages d'archives par categorie, tag, categorie produit...</div>
                                    </div>
                                </label>

                                <!-- Sous-options taxonomies -->
                                <div id="opt-taxonomies-list" style="display:none;margin-left:25px;padding:12px;background:#f8fafc;border-radius:6px;border:1px solid #e5e7eb;">
                                    <div style="font-size:11px;font-weight:600;color:#9ca3af;margin-bottom:8px;">TAXONOMIES A INCLURE</div>
                                    <div id="opt-tax-checkboxes" style="display:flex;flex-direction:column;gap:6px;">
                                        <div style="color:#9ca3af;font-size:12px;">Chargement...</div>
                                    </div>
                                </div>

                                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
                                    <input type="checkbox" id="opt-authors" style="margin-top:2px;width:15px;height:15px;">
                                    <div>
                                        <div style="font-size:13px;font-weight:500;color:#374151;">Pages auteurs</div>
                                        <div style="font-size:12px;color:#9ca3af;" id="opt-authors-count">Archives par auteur</div>
                                    </div>
                                </label>

                            </div>
                        </div>

                        <!-- Bouton generer -->
                        <button id="btn-sitemap-generate" class="button button-primary" style="width:100%;padding:10px;font-size:14px;height:auto;">
                            Generer le sitemap
                        </button>
                        <div id="sitemap-feedback" style="display:none;margin-top:12px;font-size:13px;padding:10px 14px;border-radius:6px;"></div>
                    </div>

                    <!-- Priorites appliquees -->
                    <div style="background:#f0fdf4;border:1px solid #d1fae5;border-radius:8px;padding:14px 16px;font-size:12px;color:#065f46;">
                        <strong>Priorites appliquees :</strong><br>
                        Accueil : 1.0 &nbsp;·&nbsp; Pages : 0.8 &nbsp;·&nbsp; Produits : 0.7 &nbsp;·&nbsp; Articles : 0.6<br>
                        Categories produits : 0.6 &nbsp;·&nbsp; Autres categories : 0.4 &nbsp;·&nbsp; Auteurs : 0.4
                    </div>
                </div>

                <!-- Colonne droite : compteurs + info -->
                <div>
                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-bottom:16px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                            <h3 style="margin:0;font-size:15px;color:#111827;">URLs incluses</h3>
                            <div id="count-total-badge" style="font-size:22px;font-weight:700;color:#1e3a5f;">-</div>
                        </div>
                        <div id="sitemap-counts-list" style="display:flex;flex-direction:column;gap:6px;">
                            <div style="color:#9ca3af;font-size:13px;">Chargement...</div>
                        </div>
                    </div>

                    <div id="sitemap-native-notice" style="display:none;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px 16px;font-size:13px;color:#1e40af;margin-bottom:16px;">
                        <strong>Sitemap WordPress natif :</strong> WordPress 5.5+ genere deja un sitemap a
                        <span id="sitemap-wp-native-url"></span>. Le fichier Alesta vous donne plus de controle (images, priorites, taxonomies personnalisees).
                    </div>

                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
                        <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:8px;">EXTENSION IMAGE (Google)</div>
                        <p style="font-size:12px;color:#6b7280;margin:0;line-height:1.6;">
                            Quand les images sont activees, le fichier XML inclut la balise <code>image:image</code> pour chaque page.
                            Cela aide Google Images a decouvrir et indexer vos photos de produits.
                        </p>
                    </div>
                </div>

            </div>

            <!-- Paramètres avancés -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-top:20px;">
                <h3 style="margin:0 0 16px;font-size:15px;color:#111827;">Parametres avances</h3>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

                    <!-- Mise à jour automatique -->
                    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
                        <div style="display:flex;align-items:flex-start;gap:12px;">
                            <div style="padding-top:2px;">
                                <input type="checkbox" id="opt-auto-regen" style="width:16px;height:16px;cursor:pointer;">
                            </div>
                            <div>
                                <div style="font-size:13px;font-weight:600;color:#111827;margin-bottom:4px;">Mise a jour automatique</div>
                                <div style="font-size:12px;color:#6b7280;line-height:1.5;">
                                    Le sitemap est régénérée automatiquement quand un article ou une page est publié, modifié ou supprimé.
                                    <br><span style="color:#9ca3af;font-size:11px;">Delai de 5 minutes entre deux regenerations.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Désactivation sitemap natif -->
                    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
                        <div style="display:flex;align-items:flex-start;gap:12px;">
                            <div style="padding-top:2px;">
                                <input type="checkbox" id="opt-disable-native" style="width:16px;height:16px;cursor:pointer;">
                            </div>
                            <div>
                                <div style="font-size:13px;font-weight:600;color:#111827;margin-bottom:4px;">Désactiver le sitemap natif</div>
                                <div style="font-size:12px;color:#6b7280;line-height:1.5;">
                                    Désactive le sitemap généré par WordPress (wp-sitemap.xml) et le sitemap de Yoast SEO s'il est actif.
                                    <br><span id="native-yoast-badge" style="display:none;margin-top:4px;display:inline-block;font-size:11px;background:#eff6ff;color:#1e40af;padding:2px 8px;border-radius:20px;border:1px solid #bfdbfe;">Yoast SEO detecte</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div style="margin-top:16px;display:flex;align-items:center;gap:12px;">
                    <button id="btn-sitemap-save-settings" class="button button-primary" style="font-size:13px;">
                        Enregistrer les parametres
                    </button>
                    <div id="settings-feedback" style="display:none;font-size:13px;"></div>
                </div>
            </div>

        </div>
        <?php
    }
}
