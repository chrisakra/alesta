<?php
defined('ABSPATH') || exit;

/**
 * Bannière RGPD souveraine — Module frontend (Alesta)
 *
 * Injecte une bannière de consentement cookies 100 % auto-hébergée
 * (aucun service tiers). Trois catégories personnalisables
 * (Analytiques, Marketing, Préférences) en plus des cookies Nécessaires.
 *
 * PHP 7.4 compatible.
 */
class Alesta_RGPD_Module {

    const OPT_SETTINGS = 'alesta_rgpd_settings';

    // =========================================================================
    // INIT
    // =========================================================================

    public static function init(): void {
        $s = self::get_settings();
        if ( empty($s['enabled']) ) return;

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_footer',          [__CLASS__, 'inject_banner'], 100);
    }

    // =========================================================================
    // ASSETS FRONTEND
    // =========================================================================

    public static function enqueue_assets(): void {
        $s = self::get_settings();
        if ( empty($s['enabled']) ) return;

        $base = plugin_dir_url( ALESTA_PLUGIN_FILE );

        wp_enqueue_style ( 'alesta-rgpd', $base . 'assets/rgpd-banner.css', [], ALESTA_VERSION );
        wp_enqueue_script( 'alesta-rgpd', $base . 'assets/rgpd-banner.js',  [], ALESTA_VERSION, true );

        wp_localize_script('alesta-rgpd', 'AlestaRGPD', [
            'lifetime'       => (int) $s['cookie_lifetime'],
            'hasAnalytics'   => ! empty($s['cat_analytics_label']),
            'hasMarketing'   => ! empty($s['cat_marketing_label']),
            'hasPreferences' => ! empty($s['cat_preferences_label']),
        ]);

        // Variables CSS couleurs (injectées en inline pour appliquer les réglages).
        $vars = '--rgpd-bg:'      . esc_attr($s['color_bg'])             . ';'
              . '--rgpd-text:'    . esc_attr($s['color_text'])           . ';'
              . '--rgpd-accent:'  . esc_attr($s['color_accent'])         . ';'
              . '--rgpd-acc-txt:' . esc_attr($s['color_accent_text'])    . ';'
              . '--rgpd-sec:'     . esc_attr($s['color_secondary'])      . ';'
              . '--rgpd-sec-txt:' . esc_attr($s['color_secondary_text']) . ';'
              . '--rgpd-border:'  . esc_attr($s['color_border'])         . ';';

        wp_add_inline_style('alesta-rgpd', ':root{' . $vars . '}');
    }

    // =========================================================================
    // INJECTION HTML
    // =========================================================================

    public static function inject_banner(): void {
        $s = self::get_settings();
        if ( empty($s['enabled']) ) return;

        $layout   = sanitize_html_class($s['layout']);
        $position = sanitize_html_class($s['position']);

        // Catégories pour le panneau détaillé.
        $cats = [];
        foreach (['analytics', 'marketing', 'preferences'] as $k) {
            $label = $s['cat_' . $k . '_label'] ?? '';
            $desc  = $s['cat_' . $k . '_desc']  ?? '';
            if ( ! empty($label) ) {
                $cats[] = ['key' => $k, 'label' => $label, 'desc' => $desc];
            }
        }

        self::render_banner_html($s, $layout, $position, $cats);
    }

    private static function render_banner_html(array $s, string $layout, string $position, array $cats): void {
        ?>
        <div id="alesta-rgpd-banner"
             class="alesta-rgpd alesta-rgpd--<?php echo esc_attr($layout); ?> alesta-rgpd--<?php echo esc_attr($position); ?>"
             role="dialog"
             aria-label="<?php echo esc_attr($s['title']); ?>"
             aria-modal="true">

            <?php if ($layout === 'popup') : ?>
            <div class="alesta-rgpd__overlay" id="alesta-rgpd-overlay"></div>
            <?php endif; ?>

            <div class="alesta-rgpd__box">

                <!-- En-tête -->
                <div class="alesta-rgpd__header">
                    <span class="alesta-rgpd__icon" aria-hidden="true">&#127850;</span>
                    <p class="alesta-rgpd__title"><?php echo esc_html($s['title']); ?></p>
                </div>

                <!-- Description -->
                <div class="alesta-rgpd__body">
                    <p class="alesta-rgpd__desc"><?php echo esc_html($s['description']); ?></p>
                    <?php if ( ! empty($s['policy_url']) ) : ?>
                    <a href="<?php echo esc_url($s['policy_url']); ?>"
                       target="_blank" rel="noopener noreferrer"
                       class="alesta-rgpd__link">
                        <?php echo esc_html($s['policy_label']); ?>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Panneau personnalisation -->
                <div class="alesta-rgpd__panel" id="alesta-rgpd-panel" aria-hidden="true">
                    <!-- Catégorie nécessaires (toujours actif) -->
                    <div class="alesta-rgpd__cat">
                        <div class="alesta-rgpd__cat-info">
                            <span class="alesta-rgpd__cat-name">Nécessaires</span>
                            <span class="alesta-rgpd__cat-desc">Indispensables au fonctionnement du site. Ne peuvent pas être désactivés.</span>
                        </div>
                        <span class="alesta-rgpd__toggle alesta-rgpd__toggle--on alesta-rgpd__toggle--locked"
                              title="Toujours actif"></span>
                    </div>
                    <?php foreach ($cats as $cat) : ?>
                    <div class="alesta-rgpd__cat">
                        <div class="alesta-rgpd__cat-info">
                            <span class="alesta-rgpd__cat-name"><?php echo esc_html($cat['label']); ?></span>
                            <span class="alesta-rgpd__cat-desc"><?php echo esc_html($cat['desc']); ?></span>
                        </div>
                        <button class="alesta-rgpd__toggle"
                                data-category="<?php echo esc_attr($cat['key']); ?>"
                                role="switch"
                                aria-checked="false"
                                aria-label="<?php echo esc_attr($cat['label']); ?>"></button>
                    </div>
                    <?php endforeach; ?>
                    <div class="alesta-rgpd__panel-footer">
                        <button class="alesta-rgpd__btn alesta-rgpd__btn--primary" id="alesta-rgpd-save">
                            <?php echo esc_html($s['btn_save']); ?>
                        </button>
                    </div>
                </div>

                <!-- Pied : boutons -->
                <div class="alesta-rgpd__footer">
                    <?php if ( ! empty($s['show_customize']) ) : ?>
                    <button class="alesta-rgpd__btn alesta-rgpd__btn--link"
                            id="alesta-rgpd-customize">
                        <?php echo esc_html($s['btn_customize']); ?>
                    </button>
                    <?php endif; ?>
                    <div class="alesta-rgpd__actions">
                        <?php if ( ! empty($s['show_reject']) ) : ?>
                        <button class="alesta-rgpd__btn alesta-rgpd__btn--secondary"
                                id="alesta-rgpd-reject">
                            <?php echo esc_html($s['btn_reject']); ?>
                        </button>
                        <?php endif; ?>
                        <button class="alesta-rgpd__btn alesta-rgpd__btn--primary"
                                id="alesta-rgpd-accept">
                            <?php echo esc_html($s['btn_accept']); ?>
                        </button>
                    </div>
                </div>

            </div><!-- /.alesta-rgpd__box -->
        </div><!-- /#alesta-rgpd-banner -->
        <?php
    }

    // =========================================================================
    // PARAMÈTRES
    // =========================================================================

    /**
     * Retourne les réglages fusionnés avec les valeurs par défaut.
     */
    public static function get_settings(): array {
        $saved = get_option(self::OPT_SETTINGS, []);
        return array_merge([
            'enabled'              => false,
            'position'             => 'bottom',
            'layout'               => 'bar',
            'color_bg'             => '#ffffff',
            'color_text'           => '#1f2937',
            'color_accent'         => '#1e3a5f',
            'color_accent_text'    => '#ffffff',
            'color_secondary'      => '#f3f4f6',
            'color_secondary_text' => '#374151',
            'color_border'         => '#e5e7eb',
            'title'                => 'Nous respectons votre vie privée',
            'description'          => 'Nous utilisons des cookies pour améliorer votre expérience de navigation, mesurer l\'audience et personnaliser les contenus. Vous pouvez choisir les catégories que vous autorisez.',
            'btn_accept'           => 'Tout accepter',
            'btn_reject'           => 'Tout refuser',
            'btn_customize'        => 'Personnaliser',
            'btn_save'             => 'Enregistrer mes choix',
            'policy_url'           => '',
            'policy_label'         => 'Politique de confidentialité',
            'cookie_lifetime'      => 365,
            'show_reject'          => true,
            'show_customize'       => true,
            'cat_analytics_label'  => 'Analytiques',
            'cat_analytics_desc'   => 'Mesurent l\'audience et les statistiques de navigation (ex. Google Analytics).',
            'cat_marketing_label'  => 'Marketing',
            'cat_marketing_desc'   => 'Permettent de diffuser des publicités personnalisées et de suivre les conversions.',
            'cat_preferences_label'=> 'Préférences',
            'cat_preferences_desc' => 'Mémorisent vos préférences de navigation (langue, région, mise en page…).',
        ], $saved);
    }

    /**
     * Sauvegarde les réglages après sanitisation.
     */
    public static function save_settings(array $data): void {
        update_option(self::OPT_SETTINGS, [
            'enabled'              => ! empty($data['enabled']),
            'position'             => in_array($data['position'] ?? '', ['bottom','top','bottom-left','bottom-right','center'], true) ? $data['position'] : 'bottom',
            'layout'               => in_array($data['layout']   ?? '', ['bar','popup','corner'], true) ? $data['layout']   : 'bar',
            'color_bg'             => sanitize_hex_color($data['color_bg']             ?? '#ffffff') ?: '#ffffff',
            'color_text'           => sanitize_hex_color($data['color_text']           ?? '#1f2937') ?: '#1f2937',
            'color_accent'         => sanitize_hex_color($data['color_accent']         ?? '#1e3a5f') ?: '#1e3a5f',
            'color_accent_text'    => sanitize_hex_color($data['color_accent_text']    ?? '#ffffff') ?: '#ffffff',
            'color_secondary'      => sanitize_hex_color($data['color_secondary']      ?? '#f3f4f6') ?: '#f3f4f6',
            'color_secondary_text' => sanitize_hex_color($data['color_secondary_text'] ?? '#374151') ?: '#374151',
            'color_border'         => sanitize_hex_color($data['color_border']         ?? '#e5e7eb') ?: '#e5e7eb',
            'title'                => sanitize_text_field($data['title']         ?? ''),
            'description'          => sanitize_textarea_field($data['description'] ?? ''),
            'btn_accept'           => sanitize_text_field($data['btn_accept']    ?? ''),
            'btn_reject'           => sanitize_text_field($data['btn_reject']    ?? ''),
            'btn_customize'        => sanitize_text_field($data['btn_customize'] ?? ''),
            'btn_save'             => sanitize_text_field($data['btn_save']      ?? ''),
            'policy_url'           => esc_url_raw($data['policy_url']            ?? ''),
            'policy_label'         => sanitize_text_field($data['policy_label']  ?? ''),
            'cookie_lifetime'      => max(1, min(3650, (int)($data['cookie_lifetime'] ?? 365))),
            'show_reject'          => ! empty($data['show_reject']),
            'show_customize'       => ! empty($data['show_customize']),
            'cat_analytics_label'  => sanitize_text_field($data['cat_analytics_label']   ?? ''),
            'cat_analytics_desc'   => sanitize_text_field($data['cat_analytics_desc']    ?? ''),
            'cat_marketing_label'  => sanitize_text_field($data['cat_marketing_label']   ?? ''),
            'cat_marketing_desc'   => sanitize_text_field($data['cat_marketing_desc']    ?? ''),
            'cat_preferences_label'=> sanitize_text_field($data['cat_preferences_label'] ?? ''),
            'cat_preferences_desc' => sanitize_text_field($data['cat_preferences_desc']  ?? ''),
        ]);
    }
}
