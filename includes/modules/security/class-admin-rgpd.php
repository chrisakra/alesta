<?php
defined('ABSPATH') || exit;

/**
 * Bannière RGPD — Page admin (Alesta)
 *
 * Interface de configuration : apparence, textes, catégories, avancé.
 * Prévisualisation live intégrée. Sauvegarde AJAX.
 *
 * PHP 7.4 compatible.
 */
class Alesta_Admin_RGPD {

    public function __construct() {
        add_action('admin_enqueue_scripts',    [$this, 'enqueue']);
        add_action('wp_ajax_alesta_rgpd_save', [$this, 'ajax_save']);
    }

    // =========================================================================
    // ASSETS
    // =========================================================================

    public function enqueue( string $hook ): void {
        if ( strpos($hook, 'alesta-ai-rgpd') === false ) return;

        $base = plugin_dir_url( ALESTA_PLUGIN_FILE );
        $ver  = ALESTA_VERSION . '.' . time();

        wp_enqueue_style ('alesta-rgpd-admin', $base . 'assets/rgpd-admin.css', [], $ver);
        wp_enqueue_script('alesta-rgpd-admin', $base . 'assets/rgpd-admin.js', ['jquery'], $ver, true);

        wp_localize_script('alesta-rgpd-admin', 'AlestaRgpdAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('alesta_rgpd_nonce'),
            'settings' => Alesta_RGPD_Module::get_settings(),
        ]);
    }

    // =========================================================================
    // AJAX
    // =========================================================================

    public function ajax_save(): void {
        check_ajax_referer('alesta_rgpd_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Accès refusé.');

        Alesta_RGPD_Module::save_settings([
            'enabled'              => sanitize_text_field(wp_unslash($_POST['enabled']              ?? '')),
            'position'             => sanitize_text_field(wp_unslash($_POST['position']             ?? 'bottom')),
            'layout'               => sanitize_text_field(wp_unslash($_POST['layout']               ?? 'bar')),
            'color_bg'             => sanitize_hex_color(wp_unslash($_POST['color_bg']             ?? '#ffffff')),
            'color_text'           => sanitize_hex_color(wp_unslash($_POST['color_text']           ?? '#1f2937')),
            'color_accent'         => sanitize_hex_color(wp_unslash($_POST['color_accent']         ?? '#1e3a5f')),
            'color_accent_text'    => sanitize_hex_color(wp_unslash($_POST['color_accent_text']    ?? '#ffffff')),
            'color_secondary'      => sanitize_hex_color(wp_unslash($_POST['color_secondary']      ?? '#f3f4f6')),
            'color_secondary_text' => sanitize_hex_color(wp_unslash($_POST['color_secondary_text'] ?? '#374151')),
            'color_border'         => sanitize_hex_color(wp_unslash($_POST['color_border']         ?? '#e5e7eb')),
            'title'                => sanitize_text_field(wp_unslash($_POST['title']               ?? '')),
            'description'          => sanitize_textarea_field(wp_unslash($_POST['description']     ?? '')),
            'btn_accept'           => sanitize_text_field(wp_unslash($_POST['btn_accept']          ?? '')),
            'btn_reject'           => sanitize_text_field(wp_unslash($_POST['btn_reject']          ?? '')),
            'btn_customize'        => sanitize_text_field(wp_unslash($_POST['btn_customize']       ?? '')),
            'btn_save'             => sanitize_text_field(wp_unslash($_POST['btn_save']            ?? '')),
            'policy_url'           => esc_url_raw(wp_unslash($_POST['policy_url']                  ?? '')),
            'policy_label'         => sanitize_text_field(wp_unslash($_POST['policy_label']        ?? '')),
            'cookie_lifetime'      => absint(wp_unslash($_POST['cookie_lifetime']                  ?? 365)),
            'show_reject'          => sanitize_text_field(wp_unslash($_POST['show_reject']         ?? '')),
            'show_customize'       => sanitize_text_field(wp_unslash($_POST['show_customize']      ?? '')),
            'cat_analytics_label'  => sanitize_text_field(wp_unslash($_POST['cat_analytics_label']    ?? '')),
            'cat_analytics_desc'   => sanitize_textarea_field(wp_unslash($_POST['cat_analytics_desc'] ?? '')),
            'cat_marketing_label'  => sanitize_text_field(wp_unslash($_POST['cat_marketing_label']    ?? '')),
            'cat_marketing_desc'   => sanitize_textarea_field(wp_unslash($_POST['cat_marketing_desc'] ?? '')),
            'cat_preferences_label'=> sanitize_text_field(wp_unslash($_POST['cat_preferences_label']  ?? '')),
            'cat_preferences_desc' => sanitize_textarea_field(wp_unslash($_POST['cat_preferences_desc'] ?? '')),
        ]);

        wp_send_json_success(['msg' => 'Réglages enregistrés.']);
    }

    // =========================================================================
    // PAGE
    // =========================================================================

    public function render_page(): void {
        if ( ! current_user_can('manage_options') ) wp_die( esc_html__('Accès refusé.', 'alesta') );
        $s = Alesta_RGPD_Module::get_settings();
        ?>
        <div class="wrap" id="alesta-rgpd-wrap">

            <!-- ── En-tête ── -->
            <div class="rgpd-header">
                <div class="rgpd-header-left">
                    <span class="dashicons dashicons-shield rgpd-header-icon"></span>
                    <div>
                        <h1>Bannière RGPD souveraine</h1>
                        <p>Gestion du consentement cookies — 100 % hébergée, sans service tiers</p>
                    </div>
                </div>
                <div class="rgpd-header-right">
                    <label class="rgpd-master-toggle" title="Activer / désactiver la bannière">
                        <input type="checkbox" id="rgpd-enabled" <?php checked($s['enabled']); ?>>
                        <span class="rgpd-toggle-track">
                            <span class="rgpd-toggle-thumb"></span>
                        </span>
                        <span class="rgpd-toggle-label" id="rgpd-enabled-label">
                            <?php echo $s['enabled'] ? 'Bannière active' : 'Bannière inactive'; ?>
                        </span>
                    </label>
                    <button class="button button-primary" id="rgpd-btn-save">Enregistrer</button>
                    <span id="rgpd-save-msg"></span>
                </div>
            </div>

            <div class="rgpd-layout">

                <!-- ── Colonne gauche : formulaire ── -->
                <div class="rgpd-form-col">

                    <!-- Onglets -->
                    <div class="rgpd-tabs">
                        <button class="rgpd-tab active" data-tab="appearance">&#127912; Apparence</button>
                        <button class="rgpd-tab" data-tab="texts">&#9999;&#65039; Textes</button>
                        <button class="rgpd-tab" data-tab="categories">&#128465; Catégories</button>
                        <button class="rgpd-tab" data-tab="advanced">&#9881;&#65039; Avancé</button>
                    </div>

                    <!-- ── Onglet Apparence ── -->
                    <div class="rgpd-tab-panel active" id="tab-appearance">

                        <!-- Mise en page -->
                        <div class="rgpd-section">
                            <div class="rgpd-section-title">Mise en page</div>
                            <div class="rgpd-layout-grid">
                                <?php
                                $layouts = [
                                    'bar'    => ['label' => 'Barre',    'icon' => '▬', 'desc' => 'Bande pleine largeur'],
                                    'popup'  => ['label' => 'Popup',    'icon' => '⧉', 'desc' => 'Fenêtre centrée'],
                                    'corner' => ['label' => 'Floating', 'icon' => '◱', 'desc' => 'Carte en coin'],
                                ];
                                foreach ($layouts as $key => $l) : ?>
                                <label class="rgpd-layout-opt <?php echo $s['layout'] === $key ? 'active' : ''; ?>">
                                    <input type="radio" name="layout" value="<?php echo esc_attr($key); ?>" <?php checked($s['layout'], $key); ?>>
                                    <span class="rgpd-layout-icon"><?php echo esc_html($l['icon']); ?></span>
                                    <span class="rgpd-layout-name"><?php echo esc_html($l['label']); ?></span>
                                    <span class="rgpd-layout-desc"><?php echo esc_html($l['desc']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Position -->
                        <div class="rgpd-section">
                            <div class="rgpd-section-title">Position</div>
                            <div class="rgpd-pos-grid">
                                <?php
                                $positions = [
                                    'bottom'       => 'Bas (centré)',
                                    'top'          => 'Haut (centré)',
                                    'bottom-left'  => 'Bas gauche',
                                    'bottom-right' => 'Bas droite',
                                    'center'       => 'Centre écran',
                                ];
                                foreach ($positions as $key => $label) : ?>
                                <label class="rgpd-pos-opt <?php echo $s['position'] === $key ? 'active' : ''; ?>">
                                    <input type="radio" name="position" value="<?php echo esc_attr($key); ?>" <?php checked($s['position'], $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Couleurs -->
                        <div class="rgpd-section">
                            <div class="rgpd-section-title">Palette de couleurs</div>

                            <!-- Préréglages -->
                            <div class="rgpd-presets">
                                <span class="rgpd-presets-label">Préréglages :</span>
                                <button type="button" class="rgpd-preset" data-preset="light"
                                    data-bg="#ffffff" data-text="#1f2937" data-accent="#1e3a5f"
                                    data-accent-text="#ffffff" data-sec="#f3f4f6" data-sec-txt="#374151" data-border="#e5e7eb">
                                    &#9728;&#65039; Clair
                                </button>
                                <button type="button" class="rgpd-preset" data-preset="dark"
                                    data-bg="#1f2937" data-text="#f9fafb" data-accent="#3b82f6"
                                    data-accent-text="#ffffff" data-sec="#374151" data-sec-txt="#d1d5db" data-border="#4b5563">
                                    &#127769; Sombre
                                </button>
                                <button type="button" class="rgpd-preset" data-preset="green"
                                    data-bg="#f0fdf4" data-text="#064e3b" data-accent="#059669"
                                    data-accent-text="#ffffff" data-sec="#d1fae5" data-sec-txt="#065f46" data-border="#a7f3d0">
                                    &#127807; Nature
                                </button>
                                <button type="button" class="rgpd-preset" data-preset="warm"
                                    data-bg="#fffbeb" data-text="#1c1917" data-accent="#d97706"
                                    data-accent-text="#ffffff" data-sec="#fef3c7" data-sec-txt="#92400e" data-border="#fcd34d">
                                    &#128262; Chaud
                                </button>
                            </div>

                            <div class="rgpd-colors-grid">
                                <?php
                                $colors = [
                                    'color_bg'             => 'Fond de la bannière',
                                    'color_text'           => 'Texte principal',
                                    'color_accent'         => 'Bouton principal — fond',
                                    'color_accent_text'    => 'Bouton principal — texte',
                                    'color_secondary'      => 'Bouton secondaire — fond',
                                    'color_secondary_text' => 'Bouton secondaire — texte',
                                    'color_border'         => 'Bordure / séparateurs',
                                ];
                                foreach ($colors as $key => $label) : ?>
                                <div class="rgpd-color-row">
                                    <label class="rgpd-color-label" for="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($label); ?>
                                    </label>
                                    <div class="rgpd-color-inputs">
                                        <input type="color"
                                               id="<?php echo esc_attr($key); ?>"
                                               name="<?php echo esc_attr($key); ?>"
                                               value="<?php echo esc_attr($s[$key]); ?>"
                                               class="rgpd-color-picker">
                                        <input type="text"
                                               class="rgpd-color-hex"
                                               value="<?php echo esc_attr($s[$key]); ?>"
                                               maxlength="7"
                                               data-for="<?php echo esc_attr($key); ?>">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ── Onglet Textes ── -->
                    <div class="rgpd-tab-panel" id="tab-texts">
                        <div class="rgpd-section">
                            <div class="rgpd-section-title">Contenu de la bannière</div>
                            <div class="rgpd-field">
                                <label>Titre</label>
                                <input type="text" id="rgpd-title" name="title"
                                       value="<?php echo esc_attr($s['title']); ?>">
                            </div>
                            <div class="rgpd-field">
                                <label>Description</label>
                                <textarea id="rgpd-description" name="description" rows="3"><?php echo esc_textarea($s['description']); ?></textarea>
                            </div>
                            <div class="rgpd-field-row">
                                <div class="rgpd-field">
                                    <label>Lien politique de confidentialité (URL)</label>
                                    <input type="url" name="policy_url" id="rgpd-policy-url"
                                           value="<?php echo esc_attr($s['policy_url']); ?>"
                                           placeholder="https://monsite.fr/politique-confidentialite">
                                </div>
                                <div class="rgpd-field">
                                    <label>Texte du lien</label>
                                    <input type="text" name="policy_label" id="rgpd-policy-label"
                                           value="<?php echo esc_attr($s['policy_label']); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="rgpd-section">
                            <div class="rgpd-section-title">Libellés des boutons</div>
                            <div class="rgpd-field-row">
                                <div class="rgpd-field">
                                    <label>&#9989; Tout accepter</label>
                                    <input type="text" name="btn_accept" id="rgpd-btn-accept"
                                           value="<?php echo esc_attr($s['btn_accept']); ?>">
                                </div>
                                <div class="rgpd-field">
                                    <label>&#10060; Tout refuser</label>
                                    <input type="text" name="btn_reject" id="rgpd-btn-reject"
                                           value="<?php echo esc_attr($s['btn_reject']); ?>">
                                </div>
                                <div class="rgpd-field">
                                    <label>&#9881;&#65039; Personnaliser</label>
                                    <input type="text" name="btn_customize" id="rgpd-btn-customize"
                                           value="<?php echo esc_attr($s['btn_customize']); ?>">
                                </div>
                                <div class="rgpd-field">
                                    <label>&#128190; Enregistrer les choix</label>
                                    <input type="text" name="btn_save" id="rgpd-btn-save-label"
                                           value="<?php echo esc_attr($s['btn_save']); ?>">
                                </div>
                            </div>
                            <div class="rgpd-field-row">
                                <label class="rgpd-switch-row">
                                    <input type="checkbox" name="show_reject" id="rgpd-show-reject" <?php checked($s['show_reject']); ?>>
                                    <span>Afficher le bouton "Tout refuser"</span>
                                </label>
                                <label class="rgpd-switch-row">
                                    <input type="checkbox" name="show_customize" id="rgpd-show-customize" <?php checked($s['show_customize']); ?>>
                                    <span>Afficher le bouton "Personnaliser"</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- ── Onglet Catégories ── -->
                    <div class="rgpd-tab-panel" id="tab-categories">
                        <div class="rgpd-section">
                            <div class="rgpd-section-title">Catégories de cookies</div>
                            <p class="rgpd-section-note">
                                La catégorie <strong>Nécessaires</strong> est toujours active et non modifiable.
                                Personnalisez les autres catégories selon vos besoins.
                            </p>

                            <!-- Catégorie nécessaires (non éditable) -->
                            <div class="rgpd-cat-card rgpd-cat-locked">
                                <div class="rgpd-cat-badge">&#128274; Toujours actif</div>
                                <div class="rgpd-field">
                                    <label>Nom</label>
                                    <input type="text" value="Nécessaires" disabled>
                                </div>
                                <div class="rgpd-field">
                                    <label>Description</label>
                                    <input type="text" value="Indispensables au fonctionnement du site." disabled>
                                </div>
                            </div>

                            <?php
                            $editable_cats = [
                                'analytics'   => ['icon' => '&#128200;', 'default_name' => 'Analytiques'],
                                'marketing'   => ['icon' => '&#128227;', 'default_name' => 'Marketing'],
                                'preferences' => ['icon' => '&#9881;&#65039;', 'default_name' => 'Préférences'],
                            ];
                            foreach ($editable_cats as $key => $meta) : ?>
                            <div class="rgpd-cat-card">
                                <div class="rgpd-cat-title"><?php echo wp_kses_post($meta['icon']); ?> <?php echo esc_html($meta['default_name']); ?></div>
                                <div class="rgpd-field-row">
                                    <div class="rgpd-field">
                                        <label>Nom affiché</label>
                                        <input type="text"
                                               name="cat_<?php echo esc_attr($key); ?>_label"
                                               value="<?php echo esc_attr($s['cat_' . $key . '_label']); ?>">
                                    </div>
                                    <div class="rgpd-field" style="flex:2;">
                                        <label>Description</label>
                                        <input type="text"
                                               name="cat_<?php echo esc_attr($key); ?>_desc"
                                               value="<?php echo esc_attr($s['cat_' . $key . '_desc']); ?>">
                                    </div>
                                </div>
                                <p class="rgpd-cat-note">
                                    Laissez le nom vide pour masquer cette catégorie dans la bannière.
                                </p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ── Onglet Avancé ── -->
                    <div class="rgpd-tab-panel" id="tab-advanced">
                        <div class="rgpd-section">
                            <div class="rgpd-section-title">Durée du consentement</div>
                            <div class="rgpd-field" style="max-width:260px;">
                                <label for="rgpd-lifetime">
                                    Durée de validité du cookie : <strong><span id="rgpd-lifetime-val"><?php echo esc_html($s['cookie_lifetime']); ?></span> jours</strong>
                                </label>
                                <input type="range" id="rgpd-lifetime" name="cookie_lifetime"
                                       min="30" max="730" step="30"
                                       value="<?php echo esc_attr($s['cookie_lifetime']); ?>">
                                <p class="rgpd-field-note">Recommandé : 365 jours (1 an). Maximum légal : 13 mois.</p>
                            </div>
                        </div>
                        <div class="rgpd-section">
                            <div class="rgpd-section-title">Intégration GTM / Analytics</div>
                            <p class="rgpd-section-note">
                                Alesta émet un événement JavaScript <code>alestaRgpdConsent</code> à chaque validation du consentement.
                                Utilisez cet événement dans Google Tag Manager pour conditionner le chargement de vos scripts.
                            </p>
                            <div class="rgpd-code-block">
<pre>window.addEventListener('alestaRgpdConsent', function(e) {
  var consent = e.detail;
  // consent.necessary    → true (toujours)
  // consent.analytics    → true / false
  // consent.marketing    → true / false
  // consent.preferences  → true / false
  // consent.timestamp    → Date.now()
});</pre>
                            </div>
                        </div>
                        <div class="rgpd-section">
                            <div class="rgpd-section-title">Réinitialisation</div>
                            <p class="rgpd-section-note">
                                Pour tester la bannière, supprimez le cookie <code>alesta_rgpd_consent</code> dans les outils développeur de votre navigateur (Application → Cookies).
                            </p>
                        </div>
                    </div>

                </div><!-- /.rgpd-form-col -->

                <!-- ── Colonne droite : prévisualisation ── -->
                <div class="rgpd-preview-col">
                    <div class="rgpd-preview-header">
                        <span>&#128065; Prévisualisation</span>
                        <div class="rgpd-preview-device-btns">
                            <button class="rgpd-device-btn active" data-device="desktop" title="Desktop">&#128421;</button>
                            <button class="rgpd-device-btn" data-device="mobile" title="Mobile">&#128241;</button>
                        </div>
                    </div>
                    <div class="rgpd-preview-screen" id="rgpd-preview-screen">
                        <div class="rgpd-preview-browser">
                            <div class="rgpd-preview-bar">
                                <span class="rgpd-preview-dot"></span>
                                <span class="rgpd-preview-dot"></span>
                                <span class="rgpd-preview-dot"></span>
                            </div>
                            <div class="rgpd-preview-content" id="rgpd-preview-content">
                                <!-- Contenu simulé -->
                                <div class="rgpd-preview-page">
                                    <div class="rgpd-fake-header"></div>
                                    <div class="rgpd-fake-content">
                                        <div class="rgpd-fake-line w80"></div>
                                        <div class="rgpd-fake-line w60"></div>
                                        <div class="rgpd-fake-line w90"></div>
                                        <div class="rgpd-fake-line w50"></div>
                                    </div>
                                </div>
                                <!-- Bannière preview -->
                                <div id="rgpd-preview-banner" class="rgpd-preview-banner alesta-rgpd-preview">
                                    <div class="alesta-rgpd__box">
                                        <div class="alesta-rgpd__header">
                                            <span class="alesta-rgpd__icon">&#127850;</span>
                                            <p class="alesta-rgpd__title" id="prev-title"><?php echo esc_html($s['title']); ?></p>
                                        </div>
                                        <div class="alesta-rgpd__body">
                                            <p class="alesta-rgpd__desc" id="prev-desc"><?php echo esc_html(mb_substr($s['description'], 0, 120)); ?>…</p>
                                        </div>
                                        <div class="alesta-rgpd__footer">
                                            <button class="alesta-rgpd__btn alesta-rgpd__btn--link" id="prev-btn-customize"><?php echo esc_html($s['btn_customize']); ?></button>
                                            <div class="alesta-rgpd__actions">
                                                <button class="alesta-rgpd__btn alesta-rgpd__btn--secondary" id="prev-btn-reject"><?php echo esc_html($s['btn_reject']); ?></button>
                                                <button class="alesta-rgpd__btn alesta-rgpd__btn--primary" id="prev-btn-accept"><?php echo esc_html($s['btn_accept']); ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /.rgpd-preview-col -->

            </div><!-- /.rgpd-layout -->
        </div><!-- /#alesta-rgpd-wrap -->
        <?php
    }
}
