<?php
defined('ABSPATH') || exit;

class Alesta_Admin_Robots {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, 'alesta-ai-robots') === false) return;

        $ver = ALESTA_VERSION . '.' . time();
        wp_enqueue_script('alesta-robots', plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/robots.js', ['jquery'], $ver, true);
        wp_enqueue_style('alesta-robots',  plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/robots.css', [], $ver);
        wp_localize_script('alesta-robots', 'AlestaRobots', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('alesta_robots_nonce'),
        ]);
    }

    public function render_page(): void {
        ?>
        <div class="wrap alesta-wrap" id="alesta-robots-wrap">

            <!-- Header -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;background:#1e3a5f;border-radius:8px;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="dashicons dashicons-shield" style="font-size:28px;color:#a0aec0;"></span>
                    <div>
                        <h1 style="color:#fff;margin:0;font-size:18px;">Robots.txt</h1>
                        <p style="color:#94a3b8;margin:0;font-size:13px;">Controle des robots d'indexation des moteurs de recherche</p>
                    </div>
                </div>
                <div id="robots-status-bar" style="font-size:12px;color:#94a3b8;">Chargement...</div>
            </div>

            <!-- Statut global -->
            <div id="robots-global-status" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:20px;display:none;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                    <div style="display:flex;align-items:center;gap:24px;">
                        <div>
                            <div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">FICHIER ROBOTS.TXT</div>
                            <div id="robots-file-status" style="font-size:13px;font-weight:600;"></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">ECRITURE</div>
                            <div id="robots-write-status" style="font-size:13px;font-weight:600;"></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">DERNIERE SAUVEGARDE</div>
                            <div id="robots-backup-date" style="font-size:13px;color:#374151;"></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#9ca3af;margin-bottom:2px;">URL</div>
                            <div id="robots-url" style="font-size:13px;"></div>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button id="btn-robots-backup" class="button" style="font-size:12px;">Sauvegarder</button>
                        <button id="btn-robots-restore" class="button" style="font-size:12px;color:#991b1b;border-color:#fca5a5;" disabled>Restaurer</button>
                        <button id="btn-robots-ping" class="button" style="font-size:12px;">Verifier accessibilite</button>
                    </div>
                </div>
            </div>

            <!-- Alerte WordPress virtuel -->
            <div id="robots-virtual-notice" style="display:none;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#1e40af;">
                <strong>Note :</strong> Aucun fichier robots.txt physique n'existe. WordPress genere un robots.txt virtuel a la volee.
                En enregistrant via ce module, un fichier physique sera cree et aura la priorite sur le virtuel.
            </div>

            <!-- Resultat ping -->
            <div id="robots-ping-result" style="display:none;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;margin-bottom:20px;font-size:13px;"></div>

            <!-- Editeur -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

                <!-- Colonne gauche : editeur -->
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <h3 style="margin:0;font-size:15px;color:#111827;">Editeur robots.txt</h3>
                        <button id="btn-robots-reset" class="button" style="font-size:12px;">Reinitialiser par defaut</button>
                    </div>
                    <p style="font-size:13px;color:#6b7280;margin:0 0 12px;line-height:1.6;">
                        Indiquez aux robots d'indexation quelles pages explorer ou ignorer.
                        La directive <code>Disallow: /wp-admin/</code> est recommandee pour tous les sites.
                    </p>
                    <textarea id="robots-editor"
                        style="width:100%;height:320px;font-family:monospace;font-size:12px;line-height:1.6;padding:12px;border:1px solid #d1d5db;border-radius:6px;resize:vertical;box-sizing:border-box;"
                        placeholder="Chargement..."></textarea>
                    <div style="display:flex;gap:8px;margin-top:12px;">
                        <button id="btn-robots-save" class="button button-primary" style="font-size:13px;">Enregistrer robots.txt</button>
                    </div>
                    <div id="robots-feedback" style="margin-top:10px;font-size:13px;display:none;"></div>
                </div>

                <!-- Colonne droite : contenu par defaut + aide -->
                <div>
                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-bottom:16px;">
                        <h3 style="margin:0 0 10px;font-size:14px;color:#111827;">Contenu recommande</h3>
                        <pre id="robots-default-preview" style="background:#1e2a3a;color:#a8d8a8;padding:14px;border-radius:6px;font-size:11px;overflow:auto;max-height:200px;line-height:1.6;margin:0;white-space:pre-wrap;"></pre>
                        <button id="btn-use-default" class="button" style="margin-top:10px;font-size:12px;">Utiliser ce contenu</button>
                    </div>
                    <div style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:16px;">
                        <div style="font-size:12px;font-weight:600;color:#713f12;margin-bottom:8px;">DIRECTIVES UTILES</div>
                        <ul style="margin:0;padding:0 0 0 16px;font-size:12px;color:#713f12;line-height:1.8;">
                            <li><code>User-agent: *</code> — Tous les robots</li>
                            <li><code>User-agent: Googlebot</code> — Google uniquement</li>
                            <li><code>Disallow: /page/</code> — Bloquer un repertoire</li>
                            <li><code>Allow: /page/accueil</code> — Autoriser une URL</li>
                            <li><code>Sitemap: URL</code> — Indiquer le sitemap</li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }
}
