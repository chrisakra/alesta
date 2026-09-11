<?php
defined('ABSPATH') || exit;

/**
 * Mode Maintenance — Interface admin (Alesta)
 * Rendu de la page de réglages : apparence, contenu, avancé.
 */
class Alesta_Admin_Maintenance {

    public function render_page(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__('Accès refusé.', 'alesta') );
        }

        $module  = new Alesta_Maintenance_Module();
        $s       = $module->get_settings();
        $enabled = ! empty($s['enabled']);

        $assets_url = plugin_dir_url( ALESTA_PLUGIN_FILE );
        wp_enqueue_style( 'alesta-maintenance-admin',  $assets_url . 'assets/maintenance-admin.css', [], ALESTA_VERSION );
        wp_enqueue_script( 'alesta-maintenance-admin', $assets_url . 'assets/maintenance-admin.js', ['jquery'], ALESTA_VERSION, true );
        wp_enqueue_media();
        wp_localize_script('alesta-maintenance-admin', 'AlestaMaint', [
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce( Alesta_Maintenance_Module::NONCE ),
            'settings'    => $s,
            'preview_url' => home_url('/?alesta_maint_preview=1'),
        ]);

        // Rôles WP disponibles.
        $all_roles = wp_roles()->get_names();
        ?>
        <div class="wrap" id="alesta-maint-wrap">

            <!-- HEADER -->
            <div class="maint-header <?php echo $enabled ? 'maint-header--active' : ''; ?>" id="maint-header">
                <div class="maint-header-left">
                    <span class="dashicons dashicons-hammer maint-header-icon"></span>
                    <div>
                        <h1>Mode Maintenance</h1>
                        <p>Affichez une page personnalisée pendant vos interventions, sans bloquer l'accès admin.</p>
                    </div>
                </div>
                <div class="maint-header-right">
                    <span id="maint-status-label" class="maint-status-label <?php echo $enabled ? 'maint-status--on' : 'maint-status--off'; ?>">
                        <?php echo $enabled ? '🟢 Mode actif' : '⚫ Mode inactif'; ?>
                    </span>
                    <label class="maint-master-toggle">
                        <input type="checkbox" id="maint-enabled" <?php checked($enabled); ?>>
                        <div class="maint-toggle-track"><div class="maint-toggle-thumb"></div></div>
                        <span id="maint-toggle-label" class="maint-toggle-label">
                            <?php echo $enabled ? 'Désactiver' : 'Activer'; ?>
                        </span>
                    </label>
                    <button id="maint-preview-btn" class="button maint-btn-preview" target="_blank">
                        👁 Prévisualiser
                    </button>
                    <button id="maint-save-btn" class="button button-primary maint-btn-save">
                        💾 Enregistrer
                    </button>
                    <span id="maint-save-msg" class="maint-save-msg"></span>
                </div>
            </div>

            <!-- ALERTE SI ACTIF -->
            <div id="maint-alert" class="maint-alert <?php echo $enabled ? '' : 'hidden'; ?>">
                ⚠ <strong>Le mode maintenance est actuellement actif.</strong>
                Les visiteurs voient la page de maintenance. Vous y accédez grâce à votre rôle administrateur.
            </div>

            <!-- ONGLETS -->
            <div class="maint-tabs">
                <button class="maint-tab active" data-tab="apparence">🎨 Apparence</button>
                <button class="maint-tab" data-tab="contenu">✏ Contenu</button>
                <button class="maint-tab" data-tab="avance">⚙ Avancé</button>
            </div>

            <!-- ══════════ TAB : APPARENCE ══════════ -->
            <div class="maint-tab-panel active" id="tab-apparence">
                <div class="maint-layout">
                    <div class="maint-config-col">

                        <!-- Fond -->
                        <div class="maint-section">
                            <div class="maint-section-title">Arrière-plan</div>
                            <div class="maint-bg-toggle">
                                <label class="maint-bg-opt <?php echo $s['bg_type'] === 'color' ? 'active' : ''; ?>">
                                    <input type="radio" name="bg_type" value="color" <?php checked($s['bg_type'], 'color'); ?>>
                                    <span>🎨 Couleur unie</span>
                                </label>
                                <label class="maint-bg-opt <?php echo $s['bg_type'] === 'image' ? 'active' : ''; ?>">
                                    <input type="radio" name="bg_type" value="image" <?php checked($s['bg_type'], 'image'); ?>>
                                    <span>🖼 Image</span>
                                </label>
                            </div>

                            <div id="bg-color-row" class="maint-field <?php echo $s['bg_type'] !== 'color' ? 'hidden' : ''; ?>">
                                <label>Couleur de fond</label>
                                <div class="maint-color-inputs">
                                    <input type="color" id="maint-bg-color" class="maint-color-picker" value="<?php echo esc_attr($s['bg_color']); ?>">
                                    <input type="text"  class="maint-color-hex" data-for="maint-bg-color" value="<?php echo esc_attr($s['bg_color']); ?>" maxlength="7">
                                </div>
                            </div>
                            <div id="bg-image-row" class="maint-field <?php echo $s['bg_type'] !== 'image' ? 'hidden' : ''; ?>">
                                <label>Image de fond</label>
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <input type="text" id="maint-bg-image-url" value="<?php echo esc_attr($s['bg_image_url']); ?>" placeholder="https://...">
                                    <button type="button" id="maint-bg-image-btn" class="button" style="flex-shrink:0;">📂 Médiathèque</button>
                                </div>
                            </div>

                            <!-- Préréglages -->
                            <div class="maint-presets">
                                <span class="maint-presets-label">Thèmes :</span>
                                <button class="maint-preset" data-bg="#0f172a" data-text="#ffffff" data-accent="#3b82f6">🌑 Nuit</button>
                                <button class="maint-preset" data-bg="#1e3a5f" data-text="#ffffff" data-accent="#60a5fa">🔵 Marine</button>
                                <button class="maint-preset" data-bg="#064e3b" data-text="#ffffff" data-accent="#10b981">🟢 Forêt</button>
                                <button class="maint-preset" data-bg="#1c1917" data-text="#fafaf9" data-accent="#f59e0b">🟠 Charbon</button>
                                <button class="maint-preset" data-bg="#fafafa" data-text="#111827" data-accent="#6366f1">⬜ Clair</button>
                            </div>
                        </div>

                        <!-- Couleurs texte -->
                        <div class="maint-section">
                            <div class="maint-section-title">Couleurs</div>
                            <div class="maint-colors-grid">
                                <div class="maint-color-row">
                                    <div class="maint-color-label">Couleur du texte</div>
                                    <div class="maint-color-inputs">
                                        <input type="color" id="maint-text-color" class="maint-color-picker" value="<?php echo esc_attr($s['text_color']); ?>">
                                        <input type="text"  class="maint-color-hex" data-for="maint-text-color" value="<?php echo esc_attr($s['text_color']); ?>" maxlength="7">
                                    </div>
                                </div>
                                <div class="maint-color-row">
                                    <div class="maint-color-label">Couleur accent</div>
                                    <div class="maint-color-inputs">
                                        <input type="color" id="maint-accent-color" class="maint-color-picker" value="<?php echo esc_attr($s['accent_color']); ?>">
                                        <input type="text"  class="maint-color-hex" data-for="maint-accent-color" value="<?php echo esc_attr($s['accent_color']); ?>" maxlength="7">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Logo -->
                        <div class="maint-section">
                            <div class="maint-section-title">Logo</div>
                            <div class="maint-logo-row">
                                <div class="maint-logo-preview" id="maint-logo-preview">
                                    <?php if ($s['logo_url']): ?>
                                        <img src="<?php echo esc_url($s['logo_url']); ?>" alt="Logo">
                                    <?php else: ?>
                                        <span>Aucun logo</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button type="button" id="maint-logo-btn" class="button">📂 Choisir le logo</button>
                                    <?php if ($s['logo_url']): ?>
                                        <button type="button" id="maint-logo-remove" class="button" style="margin-left:6px;">🗑</button>
                                    <?php endif; ?>
                                    <input type="hidden" id="maint-logo-url" value="<?php echo esc_attr($s['logo_url']); ?>">
                                    <div class="maint-field-note" style="margin-top:6px;">PNG/SVG fond transparent recommandé.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Prévisualisation live -->
                    <div class="maint-preview-col" id="maint-live-preview">
                        <div class="maint-preview-header">
                            <span>Prévisualisation</span>
                        </div>
                        <div class="maint-preview-screen">
                            <div class="maint-preview-mock" id="maint-mock"
                                 style="background-color:<?php echo esc_attr($s['bg_color']); ?>;">
                                <?php if ($s['logo_url']): ?>
                                    <img src="<?php echo esc_url($s['logo_url']); ?>" alt="Logo" class="mock-logo">
                                <?php endif; ?>
                                <div class="mock-icon">🔧</div>
                                <div class="mock-headline" id="mock-headline" style="color:<?php echo esc_attr($s['text_color']); ?>;">
                                    <?php echo esc_html($s['headline'] ?: 'Nous revenons bientôt'); ?>
                                </div>
                                <div class="mock-message" id="mock-message" style="color:<?php echo esc_attr($s['text_color']); ?>;">
                                    <?php echo esc_html(mb_substr($s['message'] ?: 'Site en maintenance.', 0, 100)); ?>
                                </div>
                                <div class="mock-bar">
                                    <div class="mock-bar-fill" id="mock-bar-fill" style="background:<?php echo esc_attr($s['accent_color']); ?>;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════ TAB : CONTENU ══════════ -->
            <div class="maint-tab-panel" id="tab-contenu">
                <div class="maint-section">
                    <div class="maint-section-title">Textes de la page</div>
                    <div class="maint-field">
                        <label>Titre de l'onglet (balise &lt;title&gt;)</label>
                        <input type="text" id="maint-title" value="<?php echo esc_attr($s['title']); ?>" placeholder="Site en maintenance">
                    </div>
                    <div class="maint-field">
                        <label>Accroche principale</label>
                        <input type="text" id="maint-headline" value="<?php echo esc_attr($s['headline']); ?>" placeholder="Nous revenons bientôt">
                        <div class="maint-field-note">Titre grand format affiché au centre de la page.</div>
                    </div>
                    <div class="maint-field">
                        <label>Message</label>
                        <textarea id="maint-message" rows="4" placeholder="Notre site est en cours de maintenance…"><?php echo esc_textarea($s['message']); ?></textarea>
                    </div>
                    <div class="maint-field">
                        <label>Email de contact</label>
                        <input type="email" id="maint-contact-email" value="<?php echo esc_attr($s['contact_email']); ?>" placeholder="contact@votresite.com">
                        <div class="maint-field-note">Affiché comme lien mailto sur la page. Laisser vide pour masquer.</div>
                    </div>
                </div>

                <div class="maint-section">
                    <div class="maint-section-title">⏱ Compte à rebours</div>
                    <label class="maint-switch-row">
                        <input type="checkbox" id="maint-countdown-enabled" <?php checked($s['countdown_enabled']); ?>>
                        <span>Afficher un compte à rebours</span>
                    </label>
                    <div id="maint-countdown-row" class="maint-field" style="margin-top:12px;<?php echo empty($s['countdown_enabled']) ? 'display:none;' : ''; ?>">
                        <label>Date et heure de retour</label>
                        <input type="datetime-local" id="maint-countdown-date" value="<?php echo esc_attr(str_replace(' ', 'T', $s['countdown_date'])); ?>">
                    </div>
                </div>

                <div class="maint-section">
                    <div class="maint-section-title">🌐 Réseaux sociaux</div>
                    <div class="maint-field-row">
                        <div class="maint-field">
                            <label>Twitter / X</label>
                            <input type="url" id="maint-social-twitter" value="<?php echo esc_attr($s['social_twitter']); ?>" placeholder="https://twitter.com/...">
                        </div>
                        <div class="maint-field">
                            <label>Facebook</label>
                            <input type="url" id="maint-social-facebook" value="<?php echo esc_attr($s['social_facebook']); ?>" placeholder="https://facebook.com/...">
                        </div>
                    </div>
                    <div class="maint-field-row">
                        <div class="maint-field">
                            <label>Instagram</label>
                            <input type="url" id="maint-social-instagram" value="<?php echo esc_attr($s['social_instagram']); ?>" placeholder="https://instagram.com/...">
                        </div>
                        <div class="maint-field">
                            <label>LinkedIn</label>
                            <input type="url" id="maint-social-linkedin" value="<?php echo esc_attr($s['social_linkedin']); ?>" placeholder="https://linkedin.com/...">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════ TAB : AVANCÉ ══════════ -->
            <div class="maint-tab-panel" id="tab-avance">

                <div class="maint-section">
                    <div class="maint-section-title">👤 Accès autorisés</div>
                    <div class="maint-field">
                        <label>Rôles WordPress pouvant voir le site normalement</label>
                        <div class="maint-roles-grid">
                            <?php foreach ($all_roles as $role_slug => $role_name): ?>
                                <label class="maint-role-opt">
                                    <input type="checkbox" name="allowed_roles[]" value="<?php echo esc_attr($role_slug); ?>"
                                        <?php checked(in_array($role_slug, (array) $s['allowed_roles'], true)); ?>
                                        <?php echo $role_slug === 'administrator' ? 'disabled checked' : ''; ?>>
                                    <span><?php echo esc_html($role_name); ?></span>
                                    <?php if ($role_slug === 'administrator'): ?><span class="maint-role-locked">Toujours autorisé</span><?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="maint-field">
                        <label>Adresses IP autorisées (une par ligne)</label>
                        <textarea id="maint-allowed-ips" rows="4" placeholder="192.168.1.1&#10;203.0.113.42"><?php echo esc_textarea($s['allowed_ips']); ?></textarea>
                        <div class="maint-field-note">Votre IP actuelle : <strong><?php echo esc_html(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'inconnue'))); ?></strong></div>
                    </div>
                </div>

                <div class="maint-section">
                    <div class="maint-section-title">🔗 Paramètre de contournement</div>
                    <div class="maint-field">
                        <label>Paramètre URL secret</label>
                        <input type="text" id="maint-bypass-param" value="<?php echo esc_attr($s['bypass_param']); ?>" placeholder="preview_secret">
                        <div class="maint-field-note">
                            Ajouter <code>?<?php echo esc_html($s['bypass_param'] ?: 'votre_parametre'); ?>=1</code> à l'URL pour accéder au site sans se connecter. Utile pour montrer le site à un client.
                        </div>
                    </div>
                </div>

                <div class="maint-section">
                    <div class="maint-section-title">🔍 SEO</div>
                    <div class="maint-field">
                        <label>Balise meta robots</label>
                        <select id="maint-meta-robots">
                            <option value="noindex" <?php selected($s['meta_robots'], 'noindex'); ?>>noindex, nofollow (recommandé)</option>
                            <option value="index"   <?php selected($s['meta_robots'], 'index'); ?>>index, follow</option>
                            <option value="none"    <?php selected($s['meta_robots'], 'none'); ?>>none</option>
                        </select>
                        <div class="maint-field-note">
                            Le serveur retourne automatiquement un code HTTP 503 avec header <code>Retry-After: 3600</code> pour indiquer aux moteurs de recherche de revenir plus tard.
                        </div>
                    </div>
                </div>

            </div><!-- /tab-avance -->

        </div><!-- /#alesta-maint-wrap -->
        <?php
    }
}
