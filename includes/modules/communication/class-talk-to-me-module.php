<?php
defined('ABSPATH') || exit;

/**
 * Talk to Me — Alesta
 *
 * Bouton flottant de contact multi-canaux affiché sur l'ensemble du site.
 * Canaux supportés : WhatsApp, Messenger, téléphone, email, SMS, Telegram,
 * Instagram DM, lien custom. Deux modes d'affichage (menu déployable /
 * boutons empilés), ciblage par page et heures d'ouverture configurables.
 *
 * PHP 7.4 compatible.
 */
class Alesta_TalkToMe_Module {

    const OPT_SETTINGS = 'alesta_ttm_settings';

    /** Canaux supportés par le widget, dans l'ordre par défaut. */
    const CHANNELS = ['whatsapp', 'messenger', 'phone', 'email', 'sms', 'telegram', 'instagram', 'custom'];

    // =========================================================================
    // INIT
    // =========================================================================

    public function __construct() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue']);
        add_action('wp_footer',          [__CLASS__, 'maybe_render'], 99);
    }

    // =========================================================================
    // PARAMÈTRES
    // =========================================================================

    /** Payload d'options par défaut — sert aussi de schéma. */
    public static function defaults(): array {
        $hours_default = [];
        foreach ( ['mon','tue','wed','thu','fri','sat','sun'] as $day ) {
            $hours_default[ $day ] = [
                'enabled' => in_array($day, ['sat','sun'], true) ? false : true,
                'start'   => '09:00',
                'end'     => '18:00',
            ];
        }

        $channels = [];
        foreach ( self::CHANNELS as $i => $key ) {
            $channels[ $key ] = [
                'enabled' => false,
                'value'   => '',  // téléphone / username / url / email
                'message' => '',  // message pré-rempli (WA / SMS / Email)
                'subject' => '',  // email uniquement
                'label'   => '',  // pour le canal "custom"
                'order'   => $i + 1,
            ];
        }

        return [
            'enabled'         => false,
            'mode'            => 'menu',         // menu | stack
            'position'        => 'bottom-right', // bottom-right | bottom-left
            'main_color'      => '#e8890c',
            'main_label'      => 'Discutons',
            'avatar_url'      => '',
            'avatar_name'     => '',
            'avatar_status'   => 'Habituellement en ligne',
            'animation'       => 'fade',   // none | fade | bounce | slide
            'show_mobile'     => true,
            'show_desktop'    => true,
            'page_filter'     => 'all',    // all | include | exclude
            'page_ids'        => [],       // post IDs quand filter != all
            'hours_enabled'   => false,
            'hours'           => $hours_default,
            'offline_message' => 'Nous sommes actuellement fermés. Laissez-nous un message, nous reviendrons vite vers vous.',
            'hide_branding'   => false,    // toujours false en version Free (branding conservé)
            'channels'        => $channels,
        ];
    }

    /** Retourne les réglages mergés (defaults + valeurs sauvegardées). */
    public static function get_settings(): array {
        $saved = get_option(self::OPT_SETTINGS, []);
        if ( ! is_array($saved) ) $saved = [];
        $defaults = self::defaults();
        // Shallow merge puis deep-merge de `channels` et `hours`.
        $merged = array_merge($defaults, $saved);
        $merged['channels'] = array_replace_recursive($defaults['channels'], $saved['channels'] ?? []);
        $merged['hours']    = array_replace_recursive($defaults['hours'],    $saved['hours']    ?? []);
        return $merged;
    }

    // =========================================================================
    // ENQUEUE FRONT
    // =========================================================================

    /**
     * Enqueue les assets front tôt (avant le footer) uniquement si le widget
     * va effectivement s'afficher — garde la page légère quand widget off/vide.
     */
    public static function maybe_enqueue(): void {
        if ( ! self::should_render() ) return;

        wp_enqueue_style(
            'alesta-ttm',
            plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/talk-to-me.css',
            [],
            ALESTA_VERSION
        );
        wp_enqueue_script(
            'alesta-ttm',
            plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/talk-to-me.js',
            [],
            ALESTA_VERSION,
            true
        );
    }

    /**
     * Source unique de vérité pour la question "faut-il afficher le widget ici ?",
     * partagée entre l'enqueue (tôt) et le rendu HTML (footer).
     */
    private static function should_render(): bool {
        $s = self::get_settings();
        if ( empty($s['enabled']) )                 return false;
        if ( is_admin() || is_feed() || is_404() )  return false;
        if ( ! self::has_active_channel($s) )       return false;
        if ( ! self::page_target_match($s) )        return false;
        return true;
    }

    // =========================================================================
    // RENDU
    // =========================================================================

    /**
     * Décide si le widget doit s'afficher sur la requête courante.
     * Le filtre mobile/desktop est traité côté client via CSS pour permettre
     * le cache de page complète.
     */
    public static function maybe_render(): void {
        if ( ! self::should_render() ) return;
        self::render_widget( self::get_settings() );
    }

    private static function has_active_channel( array $s ): bool {
        foreach ( $s['channels'] as $c ) {
            if ( ! empty($c['enabled']) && trim((string) ($c['value'] ?? '')) !== '' ) return true;
        }
        return false;
    }

    private static function page_target_match( array $s ): bool {
        $filter = (string) ($s['page_filter'] ?? 'all');
        if ( $filter === 'all' ) return true;

        $current_id = (int) ( is_singular() ? get_queried_object_id() : 0 );
        $ids        = array_map('intval', (array) ($s['page_ids'] ?? []));

        if ( $filter === 'include' ) return $current_id && in_array($current_id, $ids, true);
        if ( $filter === 'exclude' ) return ! ( $current_id && in_array($current_id, $ids, true) );

        return true;
    }

    /**
     * Calcule le statut online/offline depuis les heures d'ouverture configurées,
     * en utilisant le fuseau horaire du site WordPress.
     */
    private static function is_online( array $s ): bool {
        if ( empty($s['hours_enabled']) ) return true; // toujours online quand les heures ne sont pas appliquées

        try {
            $tz   = wp_timezone();
            $now  = new DateTimeImmutable('now', $tz);
            $day  = strtolower( substr( $now->format('D'), 0, 3 ) ); // mon..sun
            $hours= $s['hours'][ $day ] ?? null;
            if ( ! is_array($hours) || empty($hours['enabled']) ) return false;

            $start = DateTimeImmutable::createFromFormat('H:i', (string) $hours['start'], $tz);
            $end   = DateTimeImmutable::createFromFormat('H:i', (string) $hours['end'],   $tz);
            if ( ! $start || ! $end ) return false;

            // Bind start/end à aujourd'hui.
            $start = $now->setTime( (int) $start->format('H'), (int) $start->format('i') );
            $end   = $now->setTime( (int) $end->format('H'),   (int) $end->format('i')   );

            return ( $now >= $start && $now <= $end );
        } catch ( Exception $e ) {
            return true; // fail-open : ne jamais cacher le widget sur un glitch de date.
        }
    }

    private static function render_widget( array $s ): void {
        // Assets déjà enqueuées via maybe_enqueue() sur wp_enqueue_scripts.
        $is_online    = self::is_online($s);
        $position     = in_array($s['position'], ['bottom-right','bottom-left'], true) ? $s['position'] : 'bottom-right';
        $mode         = in_array($s['mode'], ['menu','stack'], true) ? $s['mode'] : 'menu';
        $animation    = in_array($s['animation'], ['none','fade','bounce','slide'], true) ? $s['animation'] : 'fade';
        $main_color   = self::sanitize_color((string) $s['main_color'], '#e8890c');
        $main_label   = (string) $s['main_label'];
        $avatar_url   = (string) $s['avatar_url'];
        $avatar_name  = (string) $s['avatar_name'];
        $avatar_status= $is_online ? (string) $s['avatar_status'] : 'Hors ligne';
        $offline_msg  = (string) $s['offline_message'];

        // Canaux actifs, triés.
        $channels = [];
        foreach ( self::CHANNELS as $key ) {
            $c = $s['channels'][ $key ] ?? null;
            if ( ! is_array($c) || empty($c['enabled']) ) continue;
            if ( trim((string) ($c['value'] ?? '')) === '' )       continue;
            $channels[] = [ 'key' => $key ] + $c;
        }
        usort($channels, function( $a, $b ) {
            return ( (int) ($a['order'] ?? 99) ) <=> ( (int) ($b['order'] ?? 99) );
        });

        $root_classes = [
            'alesta-ttm',
            'alesta-ttm--' . $position,
            'alesta-ttm--' . $mode,
            'alesta-ttm--anim-' . $animation,
            $is_online ? 'is-online' : 'is-offline',
            empty($s['show_mobile'])  ? 'alesta-ttm--no-mobile'  : '',
            empty($s['show_desktop']) ? 'alesta-ttm--no-desktop' : '',
        ];
        $root_classes = array_filter($root_classes);
        ?>
        <div class="<?php echo esc_attr( implode(' ', $root_classes) ); ?>"
             style="--alesta-ttm-color: <?php echo esc_attr($main_color); ?>;"
             aria-live="polite">

            <div class="alesta-ttm__panel" role="dialog" aria-hidden="true" aria-label="<?php esc_attr_e('Talk to Me', 'alesta'); ?>">
                <div class="alesta-ttm__panel-head">
                    <?php if ( $avatar_url !== '' ) : ?>
                        <img class="alesta-ttm__avatar" src="<?php echo esc_url($avatar_url); ?>" alt="" loading="lazy">
                    <?php elseif ( $avatar_name !== '' ) : ?>
                        <span class="alesta-ttm__avatar alesta-ttm__avatar--initials">
                            <?php echo esc_html( self::initials($avatar_name) ); ?>
                        </span>
                    <?php endif; ?>
                    <div class="alesta-ttm__panel-meta">
                        <?php if ( $avatar_name !== '' ) : ?>
                            <div class="alesta-ttm__panel-name"><?php echo esc_html($avatar_name); ?></div>
                        <?php endif; ?>
                        <div class="alesta-ttm__panel-status">
                            <span class="alesta-ttm__dot alesta-ttm__dot--<?php echo $is_online ? 'online' : 'offline'; ?>"></span>
                            <?php echo esc_html($avatar_status); ?>
                        </div>
                    </div>
                    <button type="button" class="alesta-ttm__panel-close" aria-label="<?php esc_attr_e('Fermer', 'alesta'); ?>">&times;</button>
                </div>

                <?php if ( ! $is_online && $offline_msg !== '' ) : ?>
                    <div class="alesta-ttm__offline"><?php echo esc_html($offline_msg); ?></div>
                <?php endif; ?>

                <ul class="alesta-ttm__channels">
                    <?php foreach ( $channels as $c ) : ?>
                        <?php
                        $url   = self::build_channel_url($c);
                        if ( $url === '' ) continue;
                        $label = self::channel_label($c);
                        $color = self::channel_color($c['key']);
                        ?>
                        <li>
                            <a class="alesta-ttm__channel alesta-ttm__channel--<?php echo esc_attr($c['key']); ?>"
                               href="<?php echo esc_url($url); ?>"
                               target="_blank" rel="noopener noreferrer nofollow"
                               style="--alesta-ttm-channel-color: <?php echo esc_attr($color); ?>;">
                                <span class="alesta-ttm__channel-icon" aria-hidden="true">
                                    <?php echo self::channel_icon_svg($c['key']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </span>
                                <span class="alesta-ttm__channel-label"><?php echo esc_html($label); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ( empty($s['hide_branding']) ) : ?>
                    <a class="alesta-ttm__branding"
                       href="https://www.alesta-ai.com" target="_blank" rel="noopener noreferrer">
                        <span class="alesta-ttm__branding-logo" aria-hidden="true">&#x03C6;</span>
                        <span><?php esc_html_e('Propulsé par', 'alesta'); ?> <strong>Alesta</strong></span>
                    </a>
                <?php endif; ?>
            </div>

            <button type="button" class="alesta-ttm__main"
                    aria-haspopup="dialog" aria-expanded="false"
                    aria-label="<?php echo esc_attr( $main_label !== '' ? $main_label : __('Ouvrir', 'alesta') ); ?>">
                <span class="alesta-ttm__main-icon" aria-hidden="true">
                    <?php echo self::chat_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </span>
                <?php if ( $mode === 'menu' && $main_label !== '' ) : ?>
                    <span class="alesta-ttm__main-label"><?php echo esc_html($main_label); ?></span>
                <?php endif; ?>
                <span class="alesta-ttm__close-icon" aria-hidden="true">&times;</span>
            </button>

            <?php if ( $mode === 'stack' ) : ?>
                <ul class="alesta-ttm__stack">
                    <?php foreach ( $channels as $c ) : ?>
                        <?php
                        $url   = self::build_channel_url($c);
                        if ( $url === '' ) continue;
                        $label = self::channel_label($c);
                        $color = self::channel_color($c['key']);
                        ?>
                        <li>
                            <a class="alesta-ttm__stack-btn alesta-ttm__stack-btn--<?php echo esc_attr($c['key']); ?>"
                               href="<?php echo esc_url($url); ?>"
                               target="_blank" rel="noopener noreferrer nofollow"
                               aria-label="<?php echo esc_attr($label); ?>"
                               style="background:<?php echo esc_attr($color); ?>;">
                                <?php echo self::channel_icon_svg($c['key']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ( $mode === 'stack' && empty($s['hide_branding']) ) : ?>
                <a class="alesta-ttm__branding alesta-ttm__branding--stack"
                   href="https://www.alesta-ai.com" target="_blank" rel="noopener noreferrer">
                    <span class="alesta-ttm__branding-logo" aria-hidden="true">&#x03C6;</span>
                    <span><?php esc_html_e('Propulsé par', 'alesta'); ?> <strong>Alesta</strong></span>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    // =========================================================================
    // CANAUX — URL builders, icônes, couleurs
    // =========================================================================

    private static function build_channel_url( array $c ): string {
        $key   = (string) $c['key'];
        $value = trim((string) ($c['value'] ?? ''));
        $msg   = (string) ($c['message'] ?? '');

        switch ( $key ) {
            case 'whatsapp':
                $phone = preg_replace('/[^0-9]/', '', $value);
                if ( $phone === '' ) return '';
                $url   = 'https://wa.me/' . $phone;
                if ( $msg !== '' ) $url .= '?text=' . rawurlencode($msg);
                return $url;

            case 'messenger':
                $u = ltrim($value, '@');
                return $u !== '' ? 'https://m.me/' . rawurlencode($u) : '';

            case 'phone':
                $p = preg_replace('/[^0-9+]/', '', $value);
                return $p !== '' ? 'tel:' . $p : '';

            case 'sms':
                $p = preg_replace('/[^0-9+]/', '', $value);
                if ( $p === '' ) return '';
                $url = 'sms:' . $p;
                if ( $msg !== '' ) $url .= '?body=' . rawurlencode($msg);
                return $url;

            case 'email':
                if ( ! is_email($value) ) return '';
                $url   = 'mailto:' . $value;
                $query = [];
                if ( ! empty($c['subject']) ) $query['subject'] = $c['subject'];
                if ( $msg !== '' )            $query['body']    = $msg;
                if ( $query ) $url .= '?' . http_build_query($query);
                return $url;

            case 'telegram':
                $u = ltrim($value, '@');
                return $u !== '' ? 'https://t.me/' . rawurlencode($u) : '';

            case 'instagram':
                $u = ltrim($value, '@');
                return $u !== '' ? 'https://ig.me/m/' . rawurlencode($u) : '';

            case 'custom':
                return ( filter_var($value, FILTER_VALIDATE_URL) ) ? $value : '';
        }
        return '';
    }

    private static function channel_label( array $c ): string {
        $custom = (string) ($c['label'] ?? '');
        if ( $custom !== '' ) return $custom;
        return [
            'whatsapp'  => 'WhatsApp',
            'messenger' => 'Messenger',
            'phone'     => 'Téléphone',
            'sms'       => 'SMS',
            'email'     => 'Email',
            'telegram'  => 'Telegram',
            'instagram' => 'Instagram',
            'custom'    => 'Lien',
        ][ $c['key'] ] ?? '';
    }

    private static function channel_color( string $key ): string {
        return [
            'whatsapp'  => '#25D366',
            'messenger' => '#0084FF',
            'phone'     => '#1e3a5f',
            'sms'       => '#fb923c',
            'email'     => '#6b7280',
            'telegram'  => '#229ED9',
            'instagram' => '#E4405F',
            'custom'    => '#e8890c',
        ][ $key ] ?? '#e8890c';
    }

    /** Icônes brand-style inline (blanc-sur-couleur). Auto-contenues, sans fonts. */
    public static function channel_icon_svg( string $key ): string {
        $svg = [
            'whatsapp'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 0 0 1.51 5.26l-.999 3.648 3.978-1.607zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413z"/></svg>',
            'messenger' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 4.974 0 11.111c0 3.498 1.744 6.614 4.469 8.652V24l4.088-2.242c1.092.301 2.246.464 3.443.464 6.627 0 12-4.974 12-11.111S18.627 0 12 0zm1.191 14.963l-3.055-3.26-5.963 3.26L10.732 8l3.131 3.259L19.752 8l-6.561 6.963z"/></svg>',
            'phone'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 0 0-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/></svg>',
            'sms'       => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM7 9h2v2H7V9zm4 0h2v2h-2V9zm4 0h2v2h-2V9z"/></svg>',
            'email'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
            'telegram'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
            'instagram' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>',
            'custom'    => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>',
        ];
        return $svg[ $key ] ?? '';
    }

    private static function chat_icon_svg(): string {
        return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
             . '<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/>'
             . '</svg>';
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private static function initials( string $name ): string {
        $parts    = preg_split('/\s+/', trim($name));
        $initials = '';
        foreach ( (array) $parts as $p ) {
            if ( $p !== '' ) $initials .= mb_strtoupper(mb_substr($p, 0, 1));
            if ( mb_strlen($initials) >= 2 ) break;
        }
        return $initials !== '' ? $initials : '?';
    }

    public static function sanitize_color( string $value, string $default = '#e8890c' ): string {
        $value = sanitize_hex_color($value);
        return $value ?: $default;
    }
}
