<?php
defined('ABSPATH') || exit;

/**
 * Mode Maintenance — Module (Alesta)
 * Logique métier : sauvegarde des réglages, interception frontend, rendu de la page 503.
 */
class Alesta_Maintenance_Module {

    const OPTION = 'alesta_maintenance_settings';
    const NONCE  = 'alesta_maintenance_nonce';

    public function __construct() {
        add_action('wp_ajax_alesta_maintenance_save',   [$this, 'ajax_save']);
        add_action('wp_ajax_alesta_maintenance_toggle', [$this, 'ajax_toggle']);

        // Frontend : interception des requêtes.
        add_action('template_redirect', [$this, 'maybe_show_maintenance'], 1);
    }

    /* ═══════════════════════════════════════════════════════
       AJAX : Sauvegarder les réglages
    ═══════════════════════════════════════════════════════ */
    public function ajax_save(): void {
        check_ajax_referer(self::NONCE, 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Accès refusé.');
        }

        $s = $this->get_settings();

        $s['title']         = sanitize_text_field( wp_unslash( $_POST['title']         ?? '' ) );
        $s['headline']      = sanitize_text_field( wp_unslash( $_POST['headline']      ?? '' ) );
        $s['message']       = sanitize_textarea_field( wp_unslash( $_POST['message']   ?? '' ) );
        $s['contact_email'] = sanitize_email( wp_unslash( $_POST['contact_email']      ?? '' ) );
        $s['logo_url']      = esc_url_raw( wp_unslash( $_POST['logo_url']              ?? '' ) );
        $s['bg_type']       = sanitize_key( wp_unslash( $_POST['bg_type']              ?? 'color' ) );
        $s['bg_color']      = sanitize_hex_color( wp_unslash( $_POST['bg_color']       ?? '#0f172a' ) ) ?: '#0f172a';
        $s['bg_image_url']  = esc_url_raw( wp_unslash( $_POST['bg_image_url']          ?? '' ) );
        $s['text_color']    = sanitize_hex_color( wp_unslash( $_POST['text_color']     ?? '#ffffff' ) ) ?: '#ffffff';
        $s['accent_color']  = sanitize_hex_color( wp_unslash( $_POST['accent_color']   ?? '#3b82f6' ) ) ?: '#3b82f6';
        $s['countdown_enabled'] = ! empty( sanitize_text_field( wp_unslash( $_POST['countdown_enabled'] ?? '' ) ) );
        $s['countdown_date']    = sanitize_text_field( wp_unslash( $_POST['countdown_date']    ?? '' ) );
        $s['social_twitter']    = esc_url_raw( wp_unslash( $_POST['social_twitter']    ?? '' ) );
        $s['social_facebook']   = esc_url_raw( wp_unslash( $_POST['social_facebook']   ?? '' ) );
        $s['social_instagram']  = esc_url_raw( wp_unslash( $_POST['social_instagram']  ?? '' ) );
        $s['social_linkedin']   = esc_url_raw( wp_unslash( $_POST['social_linkedin']   ?? '' ) );
        $s['allowed_ips']       = sanitize_textarea_field( wp_unslash( $_POST['allowed_ips']   ?? '' ) );
        $s['allowed_roles']     = array_map('sanitize_key', (array) wp_unslash($_POST['allowed_roles'] ?? ['administrator']));
        $s['bypass_param']      = sanitize_key( wp_unslash( $_POST['bypass_param']     ?? '' ) );
        $s['meta_robots']       = sanitize_key( wp_unslash( $_POST['meta_robots']      ?? 'noindex' ) );

        update_option(self::OPTION, $s, false);
        wp_send_json_success(['msg' => 'Réglages enregistrés.', 'enabled' => (bool) $s['enabled']]);
    }

    /* ═══════════════════════════════════════════════════════
       AJAX : Activer / Désactiver rapidement
    ═══════════════════════════════════════════════════════ */
    public function ajax_toggle(): void {
        check_ajax_referer(self::NONCE, 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Accès refusé.');
        }

        $s            = $this->get_settings();
        $s['enabled'] = ! (bool) $s['enabled'];
        update_option(self::OPTION, $s, false);
        wp_send_json_success([
            'enabled' => $s['enabled'],
            'msg'     => $s['enabled'] ? 'Mode maintenance activé.' : 'Mode maintenance désactivé.',
        ]);
    }

    /* ═══════════════════════════════════════════════════════
       Frontend : affichage de la page de maintenance
    ═══════════════════════════════════════════════════════ */
    public function maybe_show_maintenance(): void {
        $s = $this->get_settings();
        if ( empty($s['enabled']) ) return;

        // Ne jamais bloquer /wp-admin/ et /wp-login.php.
        if ( is_admin() ) return;
        $req = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( strpos($req, '/wp-login.php') !== false ) return;

        // Paramètre de contournement.
        if ( ! empty($s['bypass_param']) && isset($_GET[ sanitize_key($s['bypass_param']) ]) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule, pas d'action
            setcookie('alesta_maint_bypass', '1', time() + 3600, '/');
            return;
        }
        if ( isset($_COOKIE['alesta_maint_bypass']) ) return;

        // Utilisateurs connectés avec rôle autorisé.
        if ( is_user_logged_in() ) {
            $user  = wp_get_current_user();
            $roles = (array) ($user->roles ?? []);
            foreach ($roles as $role) {
                if ( in_array($role, (array) $s['allowed_roles'], true) ) return;
            }
        }

        // IPs autorisées.
        $client_ip   = $this->get_client_ip();
        $allowed_ips = array_filter(array_map('trim', explode("\n", $s['allowed_ips'] ?? '')));
        if ( in_array($client_ip, $allowed_ips, true) ) return;

        // Affichage maintenance.
        status_header(503);
        header('Retry-After: 3600');
        nocache_headers();

        $this->render_maintenance_page($s);
        exit;
    }

    /* ═══════════════════════════════════════════════════════
       Rendu de la page de maintenance
    ═══════════════════════════════════════════════════════ */
    public function render_maintenance_page(array $s): void {
        $bg_color   = esc_attr($s['bg_color']     ?? '#0f172a');
        $text_color = esc_attr($s['text_color']   ?? '#ffffff');
        $accent     = esc_attr($s['accent_color'] ?? '#3b82f6');
        $title      = esc_html($s['title']    ?: 'Site en maintenance');
        $headline   = esc_html($s['headline'] ?: 'Nous revenons bientôt');
        $message    = nl2br(esc_html($s['message'] ?: 'Notre site est actuellement en cours de maintenance. Nous nous excusons pour la gêne occasionnée.'));
        $logo_url   = esc_url($s['logo_url']   ?? '');
        $email      = sanitize_email($s['contact_email'] ?? '');
        $countdown  = ! empty($s['countdown_enabled']) && ! empty($s['countdown_date']);
        $cd_date    = esc_attr($s['countdown_date'] ?? '');
        // Whitelist allowed robots directives. Anything else falls back to noindex.
        $allowed_robots = ['noindex', 'index', 'noindex,nofollow', 'index,follow'];
        $meta_robots    = in_array($s['meta_robots'] ?? '', $allowed_robots, true) ? $s['meta_robots'] : 'noindex';

        $has_bg_image = $s['bg_type'] === 'image' && ! empty($s['bg_image_url']);
        $bg_image_url = $has_bg_image ? esc_url($s['bg_image_url']) : '';

        $socials = [];
        if ( ! empty($s['social_twitter']) )   $socials[] = ['url' => $s['social_twitter'],   'icon' => '𝕏',  'label' => 'Twitter/X'];
        if ( ! empty($s['social_facebook']) )  $socials[] = ['url' => $s['social_facebook'],  'icon' => 'f',  'label' => 'Facebook'];
        if ( ! empty($s['social_instagram']) ) $socials[] = ['url' => $s['social_instagram'], 'icon' => '◎', 'label' => 'Instagram'];
        if ( ! empty($s['social_linkedin']) )  $socials[] = ['url' => $s['social_linkedin'],  'icon' => 'in', 'label' => 'LinkedIn'];

        // Enregistrement des assets : la page est servie en standalone (status 503 + exit),
        // wp_head/wp_footer ne sont pas appelés -> on imprime manuellement via wp_print_*.
        $assets_url = plugin_dir_url( ALESTA_PLUGIN_FILE );
        wp_register_style( 'alesta-maint', $assets_url . 'assets/maintenance-page.css', [], ALESTA_VERSION );
        if ( $countdown ) {
            wp_register_script( 'alesta-maint-countdown', $assets_url . 'assets/maintenance-page.js', [], ALESTA_VERSION, true );
        }
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $title ); ?></title>
<meta name="robots" content="<?php echo esc_attr($meta_robots); ?>">
<?php wp_print_styles( 'alesta-maint' ); ?>
</head>
<?php // $bg_color/$text_color/$accent are pre-escaped via esc_attr() at the top of this function; $bg_image_url via esc_url(). The conditional class is a static string. ?>
<body class="<?php echo $has_bg_image ? 'has-bg-image' : ''; ?>" style="--maint-bg:<?php echo $bg_color; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_attr'd ?>;--maint-text:<?php echo $text_color; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_attr'd ?>;--maint-accent:<?php echo $accent; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_attr'd ?>;<?php if ($has_bg_image): ?>background-image:url('<?php echo $bg_image_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_url'd ?>');<?php endif; ?>">
<div class="maint-wrap">

    <?php if ($logo_url): ?>
        <div class="maint-logo"><img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo"></div>
    <?php endif; ?>

    <div class="maint-icon">🔧</div>

    <h1 class="maint-headline"><?php echo esc_html( $headline ); ?></h1>
    <p class="maint-message"><?php echo wp_kses_post( $message ); ?></p>

    <div class="maint-bar"><div class="maint-bar-fill"></div></div>

    <?php if ($countdown): ?>
        <div class="maint-countdown" id="maint-cd" data-target="<?php echo $cd_date; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_attr'd ?>">
            <div class="cd-block"><div class="cd-num" id="cd-days">00</div><div class="cd-label">Jours</div></div>
            <div class="cd-block"><div class="cd-num" id="cd-hours">00</div><div class="cd-label">Heures</div></div>
            <div class="cd-block"><div class="cd-num" id="cd-minutes">00</div><div class="cd-label">Minutes</div></div>
            <div class="cd-block"><div class="cd-num" id="cd-seconds">00</div><div class="cd-label">Secondes</div></div>
        </div>
        <?php wp_print_scripts( 'alesta-maint-countdown' ); ?>
    <?php endif; ?>

    <?php if ($email): ?>
        <p class="maint-contact">
            Une question urgente ? <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
        </p>
    <?php endif; ?>

    <?php if (!empty($socials)): ?>
        <div class="maint-socials">
            <?php foreach ($socials as $soc): ?>
                <a href="<?php echo esc_url($soc['url']); ?>" target="_blank" rel="noopener" class="maint-social-btn" title="<?php echo esc_attr($soc['label']); ?>">
                    <?php echo esc_html($soc['icon']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
<div class="maint-footer"><?php echo esc_html(get_bloginfo('name')); ?> — <?php echo esc_html(home_url('/')); ?></div>
</body>
</html>
        <?php
    }

    /* ── Helpers ── */
    public function get_settings(): array {
        $defaults = [
            'enabled'           => false,
            'title'             => 'Site en maintenance',
            'headline'          => 'Nous revenons bientôt',
            'message'           => 'Notre site est actuellement en cours de maintenance.\nNous nous excusons pour la gêne occasionnée.',
            'contact_email'     => get_option('admin_email'),
            'logo_url'          => '',
            'bg_type'           => 'color',
            'bg_color'          => '#0f172a',
            'bg_image_url'      => '',
            'text_color'        => '#ffffff',
            'accent_color'      => '#3b82f6',
            'countdown_enabled' => false,
            'countdown_date'    => '',
            'social_twitter'    => '',
            'social_facebook'   => '',
            'social_instagram'  => '',
            'social_linkedin'   => '',
            'allowed_ips'       => '',
            'allowed_roles'     => ['administrator'],
            'bypass_param'      => '',
            'meta_robots'       => 'noindex',
        ];
        $saved = get_option(self::OPTION, []);
        return wp_parse_args(is_array($saved) ? $saved : [], $defaults);
    }

    private function get_client_ip(): string {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if ( ! empty($_SERVER[$key]) ) {
                $ip = trim(explode(',', sanitize_text_field( wp_unslash( $_SERVER[$key] ) ))[0]);
                if ( filter_var($ip, FILTER_VALIDATE_IP) ) return $ip;
            }
        }
        return '';
    }
}
