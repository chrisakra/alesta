<?php
defined('ABSPATH') || exit;

/**
 * Talk to Me — Interface admin (Alesta)
 *
 * Page de réglages du widget flottant multi-canaux.
 * Guard slug attendu : `alesta-ai-talk-to-me`.
 *
 * PHP 7.4 compatible.
 */
class Alesta_Admin_TalkToMe {

    const NONCE_ACTION = 'alesta_ttm_admin';
    const PAGE_SLUG    = 'alesta-ai-talk-to-me';

    public function __construct() {
        add_action('wp_ajax_alesta_ttm_save', [$this, 'ajax_save']);
        add_action('admin_enqueue_scripts',   [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets( string $hook ): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture d'un slug admin, pas d'action sur formulaire
        $page = isset($_GET['page']) ? sanitize_key( wp_unslash($_GET['page']) ) : '';
        if ( $page !== self::PAGE_SLUG ) return;

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Media library (avatar picker)
        wp_enqueue_media();

        wp_enqueue_style(
            'alesta-ttm-admin',
            plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/talk-to-me-admin.css',
            [],
            ALESTA_VERSION
        );
        wp_enqueue_script(
            'alesta-ttm-admin',
            plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/talk-to-me-admin.js',
            ['jquery', 'wp-color-picker', 'jquery-ui-sortable'],
            ALESTA_VERSION,
            true
        );
        wp_localize_script('alesta-ttm-admin', 'AlestaTtm', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(self::NONCE_ACTION),
            'i18n'     => [
                'saving' => __('Enregistrement…', 'alesta'),
                'saved'  => __('Réglages enregistrés.', 'alesta'),
                'error'  => __('Erreur lors de l\'enregistrement.', 'alesta'),
            ],
        ]);
    }

    // =========================================================================
    // PAGE
    // =========================================================================

    public function render_page(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die(esc_html__('Accès refusé.', 'alesta'));
        }

        $s = Alesta_TalkToMe_Module::get_settings();
        ?>
        <div class="wrap alesta-wrap alesta-ttm-wrap">

            <div class="alesta-ttm-header">
                <span class="alesta-ttm-header-icon">💬</span>
                <div>
                    <h1><?php esc_html_e('Talk to Me', 'alesta'); ?></h1>
                    <p><?php esc_html_e('Bouton flottant de contact multi-canal sur l\'ensemble de votre site.', 'alesta'); ?></p>
                </div>
                <label class="alesta-ttm-toggle">
                    <input type="checkbox" id="ttm-enabled" <?php checked(!empty($s['enabled'])); ?>>
                    <span class="alesta-ttm-toggle-slider"></span>
                    <span class="alesta-ttm-toggle-lbl"><?php esc_html_e('Activer le widget', 'alesta'); ?></span>
                </label>
            </div>

            <div class="alesta-ttm-tabs" role="tablist">
                <button type="button" class="alesta-ttm-tab is-active" data-tab="channels"   role="tab"><?php esc_html_e('Canaux', 'alesta'); ?></button>
                <button type="button" class="alesta-ttm-tab"           data-tab="appearance" role="tab"><?php esc_html_e('Apparence', 'alesta'); ?></button>
                <button type="button" class="alesta-ttm-tab"           data-tab="display"    role="tab"><?php esc_html_e('Affichage', 'alesta'); ?></button>
            </div>

            <!-- ─────────── Onglet 1 : Canaux ─────────── -->
            <div class="alesta-ttm-panel is-active" data-panel="channels">
                <p class="description" style="margin-bottom:1rem;">
                    <?php esc_html_e('Activez les canaux que vous voulez proposer. Glissez-déposez pour réordonner.', 'alesta'); ?>
                </p>
                <ul id="ttm-channels" class="alesta-ttm-channels-admin">
                    <?php
                    $sorted = $s['channels'];
                    uasort($sorted, function( $a, $b ) {
                        return ( (int) ($a['order'] ?? 99) ) <=> ( (int) ($b['order'] ?? 99) );
                    });
                    foreach ( $sorted as $key => $c ) :
                        $this->render_channel_row($key, $c);
                    endforeach;
                    ?>
                </ul>
            </div>

            <!-- ─────────── Onglet 2 : Apparence ─────────── -->
            <div class="alesta-ttm-panel" data-panel="appearance">
                <div class="alesta-ttm-grid-2">

                    <div class="alesta-ttm-card">
                        <h3><?php esc_html_e('Mode d\'affichage', 'alesta'); ?></h3>
                        <div class="alesta-ttm-mode-grid" role="radiogroup">
                            <label class="alesta-ttm-mode <?php echo $s['mode'] === 'menu' ? 'is-active' : ''; ?>" data-mode="menu">
                                <input type="radio" name="ttm-mode" value="menu" <?php checked($s['mode'], 'menu'); ?>>
                                <span class="alesta-ttm-mode-thumb alesta-ttm-mode-thumb--menu"></span>
                                <span class="alesta-ttm-mode-name"><?php esc_html_e('Menu déployable', 'alesta'); ?></span>
                                <small><?php esc_html_e('Un seul bouton, panneau au clic', 'alesta'); ?></small>
                            </label>
                            <label class="alesta-ttm-mode <?php echo $s['mode'] === 'stack' ? 'is-active' : ''; ?>" data-mode="stack">
                                <input type="radio" name="ttm-mode" value="stack" <?php checked($s['mode'], 'stack'); ?>>
                                <span class="alesta-ttm-mode-thumb alesta-ttm-mode-thumb--stack"></span>
                                <span class="alesta-ttm-mode-name"><?php esc_html_e('Boutons empilés', 'alesta'); ?></span>
                                <small><?php esc_html_e('Un bouton par canal, à droite', 'alesta'); ?></small>
                            </label>
                        </div>
                    </div>

                    <div class="alesta-ttm-card">
                        <h3><?php esc_html_e('Position', 'alesta'); ?></h3>
                        <div class="alesta-ttm-position-grid" role="radiogroup">
                            <label class="alesta-ttm-pos <?php echo $s['position'] === 'bottom-left' ? 'is-active' : ''; ?>" data-pos="bottom-left">
                                <input type="radio" name="ttm-position" value="bottom-left" <?php checked($s['position'], 'bottom-left'); ?>>
                                <span class="alesta-ttm-pos-thumb alesta-ttm-pos-thumb--bl"></span>
                                <span><?php esc_html_e('Bas-gauche', 'alesta'); ?></span>
                            </label>
                            <label class="alesta-ttm-pos <?php echo $s['position'] === 'bottom-right' ? 'is-active' : ''; ?>" data-pos="bottom-right">
                                <input type="radio" name="ttm-position" value="bottom-right" <?php checked($s['position'], 'bottom-right'); ?>>
                                <span class="alesta-ttm-pos-thumb alesta-ttm-pos-thumb--br"></span>
                                <span><?php esc_html_e('Bas-droite', 'alesta'); ?></span>
                            </label>
                        </div>
                    </div>

                    <div class="alesta-ttm-card">
                        <h3><?php esc_html_e('Couleur principale', 'alesta'); ?></h3>
                        <input type="text" id="ttm-color" class="alesta-ttm-color"
                               value="<?php echo esc_attr($s['main_color']); ?>"
                               data-default-color="#e8890c">
                    </div>

                    <div class="alesta-ttm-card">
                        <h3><?php esc_html_e('Animation d\'entrée', 'alesta'); ?></h3>
                        <select id="ttm-animation">
                            <option value="none"   <?php selected($s['animation'], 'none');   ?>><?php esc_html_e('Aucune', 'alesta'); ?></option>
                            <option value="fade"   <?php selected($s['animation'], 'fade');   ?>><?php esc_html_e('Fondu', 'alesta'); ?></option>
                            <option value="bounce" <?php selected($s['animation'], 'bounce'); ?>><?php esc_html_e('Rebond', 'alesta'); ?></option>
                            <option value="slide"  <?php selected($s['animation'], 'slide');  ?>><?php esc_html_e('Glissement', 'alesta'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="alesta-ttm-card">
                    <h3><?php esc_html_e('Profil affiché', 'alesta'); ?></h3>
                    <div class="alesta-ttm-grid-2">
                        <div class="alesta-ttm-field">
                            <label for="ttm-main-label"><?php esc_html_e('Label du bouton', 'alesta'); ?></label>
                            <input type="text" id="ttm-main-label" value="<?php echo esc_attr($s['main_label']); ?>" placeholder="Discutons">
                        </div>
                        <div class="alesta-ttm-field">
                            <label for="ttm-avatar-name"><?php esc_html_e('Nom affiché (optionnel)', 'alesta'); ?></label>
                            <input type="text" id="ttm-avatar-name" value="<?php echo esc_attr($s['avatar_name']); ?>" placeholder="Sophie">
                        </div>
                        <div class="alesta-ttm-field">
                            <label for="ttm-avatar-url"><?php esc_html_e('Photo (URL ou ID média)', 'alesta'); ?></label>
                            <div style="display:flex;gap:6px;">
                                <input type="text" id="ttm-avatar-url" value="<?php echo esc_attr($s['avatar_url']); ?>" placeholder="https://…" style="flex:1;">
                                <button type="button" id="ttm-avatar-pick" class="button"><?php esc_html_e('Bibliothèque', 'alesta'); ?></button>
                            </div>
                        </div>
                        <div class="alesta-ttm-field">
                            <label for="ttm-avatar-status"><?php esc_html_e('Statut affiché', 'alesta'); ?></label>
                            <input type="text" id="ttm-avatar-status" value="<?php echo esc_attr($s['avatar_status']); ?>" placeholder="Habituellement en ligne">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─────────── Onglet 3 : Affichage ─────────── -->
            <div class="alesta-ttm-panel" data-panel="display">
                <div class="alesta-ttm-card">
                    <h3><?php esc_html_e('Plateformes', 'alesta'); ?></h3>
                    <label class="alesta-ttm-check">
                        <input type="checkbox" id="ttm-show-desktop" <?php checked(!empty($s['show_desktop'])); ?>>
                        <?php esc_html_e('Afficher sur ordinateur', 'alesta'); ?>
                    </label>
                    <label class="alesta-ttm-check">
                        <input type="checkbox" id="ttm-show-mobile" <?php checked(!empty($s['show_mobile'])); ?>>
                        <?php esc_html_e('Afficher sur mobile', 'alesta'); ?>
                    </label>
                </div>

                <div class="alesta-ttm-card">
                    <h3><?php esc_html_e('Pages', 'alesta'); ?></h3>
                    <select id="ttm-page-filter">
                        <option value="all"     <?php selected($s['page_filter'], 'all');     ?>><?php esc_html_e('Sur tout le site', 'alesta'); ?></option>
                        <option value="include" <?php selected($s['page_filter'], 'include'); ?>><?php esc_html_e('Uniquement sur certaines pages', 'alesta'); ?></option>
                        <option value="exclude" <?php selected($s['page_filter'], 'exclude'); ?>><?php esc_html_e('Sur tout le site sauf certaines pages', 'alesta'); ?></option>
                    </select>
                    <div id="ttm-page-ids-wrap" style="margin-top:.85rem;<?php echo $s['page_filter'] === 'all' ? 'display:none;' : ''; ?>">
                        <label for="ttm-page-ids"><?php esc_html_e('IDs des pages (séparés par des virgules)', 'alesta'); ?></label>
                        <input type="text" id="ttm-page-ids" value="<?php echo esc_attr( implode(',', (array) $s['page_ids']) ); ?>" placeholder="12, 45, 78">
                        <p class="description"><?php esc_html_e('Vous trouvez l\'ID dans l\'URL d\'édition de la page (post=ID).', 'alesta'); ?></p>
                    </div>
                </div>

                <div class="alesta-ttm-card">
                    <h3><?php esc_html_e('Heures de disponibilité', 'alesta'); ?></h3>
                    <label class="alesta-ttm-check">
                        <input type="checkbox" id="ttm-hours-enabled" <?php checked(!empty($s['hours_enabled'])); ?>>
                        <?php esc_html_e('Définir des heures d\'ouverture', 'alesta'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Hors créneau : le widget affiche "Hors ligne" et le message ci-dessous.', 'alesta'); ?></p>

                    <div id="ttm-hours-wrap" style="<?php echo empty($s['hours_enabled']) ? 'display:none;' : ''; ?>">
                        <table class="alesta-ttm-hours">
                            <?php
                            $days = [
                                'mon' => __('Lundi', 'alesta'),
                                'tue' => __('Mardi', 'alesta'),
                                'wed' => __('Mercredi', 'alesta'),
                                'thu' => __('Jeudi', 'alesta'),
                                'fri' => __('Vendredi', 'alesta'),
                                'sat' => __('Samedi', 'alesta'),
                                'sun' => __('Dimanche', 'alesta'),
                            ];
                            foreach ( $days as $key => $label ):
                                $h = $s['hours'][ $key ] ?? ['enabled' => true, 'start' => '09:00', 'end' => '18:00'];
                            ?>
                                <tr data-day="<?php echo esc_attr($key); ?>">
                                    <td>
                                        <label class="alesta-ttm-check" style="margin:0;">
                                            <input type="checkbox" class="ttm-day-enabled" <?php checked(!empty($h['enabled'])); ?>>
                                            <strong><?php echo esc_html($label); ?></strong>
                                        </label>
                                    </td>
                                    <td><input type="time" class="ttm-day-start" value="<?php echo esc_attr($h['start']); ?>"></td>
                                    <td><span style="color:#9ca3af;">→</span></td>
                                    <td><input type="time" class="ttm-day-end"   value="<?php echo esc_attr($h['end']); ?>"></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>

                        <div class="alesta-ttm-field" style="margin-top:1rem;">
                            <label for="ttm-offline-msg"><?php esc_html_e('Message hors ligne', 'alesta'); ?></label>
                            <textarea id="ttm-offline-msg" rows="2"><?php echo esc_textarea($s['offline_message']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alesta-ttm-actions">
                <button type="button" id="ttm-save" class="button button-primary"><?php esc_html_e('Enregistrer', 'alesta'); ?></button>
                <span id="ttm-feedback" class="alesta-ttm-feedback"></span>
            </div>

        </div>
        <?php
    }

    private function render_channel_row( string $key, array $c ): void {
        $is_custom = ($key === 'custom');
        $labels = [
            'whatsapp'  => ['WhatsApp',   'numéro avec indicatif (ex : +33612345678)'],
            'messenger' => ['Messenger',  'username Facebook (sans @)'],
            'phone'     => ['Téléphone',  'numéro (ex : +33123456789)'],
            'sms'       => ['SMS',        'numéro mobile'],
            'email'     => ['Email',      'adresse e-mail'],
            'telegram'  => ['Telegram',   'username (sans @)'],
            'instagram' => ['Instagram',  'username (sans @)'],
            'custom'    => ['Lien custom','URL complète (https://…)'],
        ];
        [$lbl, $ph] = $labels[ $key ];
        $color = [
            'whatsapp' => '#25D366', 'messenger' => '#0084FF', 'phone'    => '#1e3a5f',
            'sms'      => '#fb923c', 'email'     => '#6b7280', 'telegram' => '#229ED9',
            'instagram'=> '#E4405F', 'custom'    => '#e8890c',
        ][ $key ];
        ?>
        <li class="alesta-ttm-channel-row" data-channel="<?php echo esc_attr($key); ?>">
            <span class="alesta-ttm-handle dashicons dashicons-menu" aria-hidden="true"></span>
            <span class="alesta-ttm-channel-icon" style="background:<?php echo esc_attr($color); ?>">
                <?php echo Alesta_TalkToMe_Module::channel_icon_svg($key); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <div class="alesta-ttm-channel-body">
                <div class="alesta-ttm-channel-head">
                    <strong><?php echo esc_html($lbl); ?></strong>
                    <label class="alesta-ttm-toggle alesta-ttm-toggle--small">
                        <input type="checkbox" class="ttm-ch-enabled" <?php checked(!empty($c['enabled'])); ?>>
                        <span class="alesta-ttm-toggle-slider"></span>
                    </label>
                </div>
                <div class="alesta-ttm-channel-fields">
                    <input type="text" class="ttm-ch-value" value="<?php echo esc_attr((string) ($c['value'] ?? '')); ?>" placeholder="<?php echo esc_attr($ph); ?>">
                    <?php if ( in_array($key, ['whatsapp','sms','email'], true) ): ?>
                        <?php if ($key === 'email'): ?>
                            <input type="text" class="ttm-ch-subject" value="<?php echo esc_attr((string) ($c['subject'] ?? '')); ?>" placeholder="<?php esc_attr_e('Sujet (optionnel)', 'alesta'); ?>">
                        <?php endif; ?>
                        <input type="text" class="ttm-ch-message" value="<?php echo esc_attr((string) ($c['message'] ?? '')); ?>" placeholder="<?php esc_attr_e('Message pré-rempli (optionnel)', 'alesta'); ?>">
                    <?php endif; ?>
                    <?php if ($is_custom): ?>
                        <input type="text" class="ttm-ch-label" value="<?php echo esc_attr((string) ($c['label'] ?? '')); ?>" placeholder="<?php esc_attr_e('Label affiché (ex : Réservation)', 'alesta'); ?>">
                    <?php endif; ?>
                </div>
            </div>
        </li>
        <?php
    }

    // =========================================================================
    // AJAX
    // =========================================================================

    public function ajax_save(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => __('Accès refusé.', 'alesta')]);

        // Le frontend POST l'arbre complet des réglages sous forme de chaîne
        // JSON dans $_POST['data']. On wp_unslash() puis decode, ensuite chaque
        // champ est sanitisé INDIVIDUELLEMENT ci-dessous (sanitize_text_field /
        // esc_url_raw / whitelist in_array / sanitize_color / intval, etc.).
        // Pattern documenté et approuvé WP.org pour les payloads structurés :
        // https://developer.wordpress.org/apis/security/data-validation/
        // phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce vérifié plus haut ; payload JSON-decodé puis sanitisé champ par champ ci-dessous
        $raw = isset($_POST['data']) ? wp_unslash($_POST['data']) : '';
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if ( ! is_array($data) ) wp_send_json_error(['message' => __('Payload invalide.', 'alesta')]);
        // phpcs:enable

        $defaults = Alesta_TalkToMe_Module::defaults();
        $clean    = $defaults;

        $clean['enabled']         = ! empty($data['enabled']);
        $clean['mode']            = in_array($data['mode'] ?? '', ['menu','stack'], true) ? $data['mode'] : 'menu';
        $clean['position']        = in_array($data['position'] ?? '', ['bottom-right','bottom-left'], true) ? $data['position'] : 'bottom-right';
        $clean['main_color']      = Alesta_TalkToMe_Module::sanitize_color((string) ($data['main_color'] ?? '#e8890c'));
        $clean['main_label']      = sanitize_text_field((string) ($data['main_label']    ?? ''));
        $clean['avatar_url']      = esc_url_raw((string) ($data['avatar_url']            ?? ''));
        $clean['avatar_name']     = sanitize_text_field((string) ($data['avatar_name']   ?? ''));
        $clean['avatar_status']   = sanitize_text_field((string) ($data['avatar_status'] ?? ''));
        $clean['animation']       = in_array($data['animation'] ?? '', ['none','fade','bounce','slide'], true) ? $data['animation'] : 'fade';
        $clean['show_mobile']     = ! empty($data['show_mobile']);
        $clean['show_desktop']    = ! empty($data['show_desktop']);
        $clean['page_filter']     = in_array($data['page_filter'] ?? '', ['all','include','exclude'], true) ? $data['page_filter'] : 'all';
        $clean['page_ids']        = array_values(array_filter(array_map('intval', (array) ($data['page_ids'] ?? []))));
        $clean['hours_enabled']   = ! empty($data['hours_enabled']);
        $clean['offline_message'] = sanitize_textarea_field((string) ($data['offline_message'] ?? ''));
        // Version Free : hide_branding est TOUJOURS forcé à false pour conserver
        // la mention "Propulsé par Alesta" (publicité passive). Le schéma est
        // préservé pour permettre une future upgrade Pro sans migration.
        $clean['hide_branding']   = false;

        if ( isset($data['hours']) && is_array($data['hours']) ) {
            foreach ( $clean['hours'] as $day => $_default ) {
                $h = $data['hours'][ $day ] ?? [];
                $clean['hours'][ $day ] = [
                    'enabled' => ! empty($h['enabled']),
                    'start'   => preg_match('/^\d{2}:\d{2}$/', (string) ($h['start'] ?? '')) ? $h['start'] : '09:00',
                    'end'     => preg_match('/^\d{2}:\d{2}$/', (string) ($h['end']   ?? '')) ? $h['end']   : '18:00',
                ];
            }
        }

        if ( isset($data['channels']) && is_array($data['channels']) ) {
            foreach ( $clean['channels'] as $key => $_default ) {
                $c = $data['channels'][ $key ] ?? [];
                $clean['channels'][ $key ] = [
                    'enabled' => ! empty($c['enabled']),
                    'value'   => sanitize_text_field((string) ($c['value']   ?? '')),
                    'message' => sanitize_textarea_field((string) ($c['message'] ?? '')),
                    'subject' => sanitize_text_field((string) ($c['subject'] ?? '')),
                    'label'   => sanitize_text_field((string) ($c['label']   ?? '')),
                    'order'   => max(1, min(20, (int) ($c['order'] ?? 99))),
                ];
            }
        }

        update_option(Alesta_TalkToMe_Module::OPT_SETTINGS, $clean, false);

        wp_send_json_success(['message' => __('Réglages enregistrés.', 'alesta')]);
    }
}
