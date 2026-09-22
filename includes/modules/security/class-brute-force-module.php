<?php
defined('ABSPATH') || exit;

/**
 * Protection Brute Force — Module (Alesta)
 *
 * Bloque activement les attaques par force brute sur les endpoints de login
 * WordPress (wp-login.php + xmlrpc.php). Pattern fail2ban classique :
 *
 *   1. Hook wp_login_failed : incrémente un compteur par IP en transient
 *   2. Si compteur >= seuil dans la fenêtre temporelle → BAN l'IP
 *   3. Hook authenticate (priority 30) : si IP bannie → WP_Error qui empêche login
 *   4. Hook login_init : si requête sur wp-login.php depuis IP bannie → 429
 *   5. Hook wp_login (succès) : reset le compteur pour cette IP
 *   6. Action 'alesta_brute_force_ban' déclenchée à chaque ban (1 mail PAR BAN
 *      à l'admin si la notification est activée, jamais 1 mail par tentative)
 *
 * Stockage (identique à Alesta AI Pro — un upgrade Free → Pro conserve tout) :
 *   - Réglages          : option `alesta_brute_force_settings`
 *   - Journal des bans  : option `alesta_brute_force_banned_log` (100 dernières)
 *   - Compteur tentative: transient `alesta_bf_attempt_<sha256(ip)>` TTL = window
 *   - IP bannie         : transient `alesta_bf_block_<sha256(ip)>` TTL = ban_duration
 *
 * Les IPs sont hashées en sha256 avant stockage en transient (RGPD : pas
 * d'IP en clair dans les transients). Le journal admin (option non publique)
 * garde l'IP en clair pour permettre le déblocage manuel.
 *
 * Paramètres par défaut (style fail2ban standard) :
 *   - Seuil : 5 échecs
 *   - Fenêtre : 5 minutes (300s)
 *   - Durée ban : 15 minutes (900s)
 *
 * Lorsque l'addon Alesta AI Pro est actif, c'est SA classe
 * Alesta_AI_Brute_Force_Module qui prend le relais : le loader du Free ne doit
 * pas instancier ce module si class_exists('Alesta_AI_Brute_Force_Module').
 *
 * PHP 7.4 compatible.
 */
class Alesta_Brute_Force_Module {

    const OPT_SETTINGS   = 'alesta_brute_force_settings';
    const OPT_BANNED_LOG = 'alesta_brute_force_banned_log';
    const PREFIX_ATTEMPT = 'alesta_bf_attempt_';
    const PREFIX_BLOCK   = 'alesta_bf_block_';
    const NOTIFY_LOCK    = 'alesta_bf_notify_lock';
    const NONCE_UNBAN    = 'alesta_bf_unban';

    /**
     * Constructeur — hooks dans WordPress.
     */
    public function __construct() {
        // AJAX admin pour unban manuel (enregistré même si la protection est
        // désactivée : on doit pouvoir débloquer une IP résiduelle).
        add_action('wp_ajax_alesta_bf_unban', [$this, 'ajax_unban']);

        $s = self::settings();
        if ( empty($s['enabled']) ) return;

        // Priority 30 (entre 20 et 99) = après l'authenticate WP core (10) mais
        // avant qu'aucun autre plugin de sécurité ne mute le résultat.
        add_filter('authenticate',    [$this, 'check_blocked_ip'],          30, 3);
        add_action('wp_login_failed', [$this, 'on_login_failed'],            5, 1);
        add_action('wp_login',        [$this, 'on_login_success'],           5, 2);
        // Block hard avant que le formulaire login ne soit même rendu.
        add_action('login_init',      [$this, 'block_login_form_if_banned'], 1);
        // xmlrpc : bloquer toutes les requêtes XML-RPC depuis IP bannie.
        add_action('xmlrpc_call',     [$this, 'block_xmlrpc_if_banned'],     1);

        // Notification e-mail : 1 mail par ban (le module Alerts de la Pro
        // fait la même chose ; s'il est chargé, on le laisse faire).
        if ( ! empty($s['notify_email']) && ! class_exists('Alesta_AI_Alerts_Module', false) ) {
            add_action('alesta_brute_force_ban', [$this, 'notify_admin'], 10, 3);
        }
    }

    // =========================================================================
    // PARAMÈTRES
    // =========================================================================

    public static function settings(): array {
        $defaults = [
            'enabled'        => true,
            'threshold'      => 5,
            'window_seconds' => 300,   // 5 min
            'ban_seconds'    => 900,   // 15 min
            'whitelist'      => [],    // array d'IPs littérales (pas CIDR)
            'notify_email'   => true,  // 1 mail admin par ban
        ];
        $s = get_option( self::OPT_SETTINGS, [] );
        return wp_parse_args( is_array($s) ? $s : [], $defaults );
    }

    public static function save_settings( array $patch ): bool {
        $current = self::settings();
        $new = wp_parse_args( $patch, $current );
        // Sanitize numerics.
        $new['threshold']      = max( 2,  min( 100,   (int) $new['threshold'] ) );
        $new['window_seconds'] = max( 60, min( 3600,  (int) $new['window_seconds'] ) );
        $new['ban_seconds']    = max( 60, min( 86400, (int) $new['ban_seconds'] ) );
        $new['enabled']        = ! empty($new['enabled']);
        $new['notify_email']   = ! empty($new['notify_email']);
        // Whitelist : strip non-IP, max 50 entrées.
        $whitelist = array_slice( array_filter( (array) $new['whitelist'], function($ip) {
            return is_string($ip) && filter_var( trim($ip), FILTER_VALIDATE_IP );
        } ), 0, 50 );
        $new['whitelist'] = array_values( array_unique( array_map( 'trim', $whitelist ) ) );

        // update_option() renvoie false si la valeur est inchangée : on
        // considère cela comme un succès.
        if ( is_array( get_option( self::OPT_SETTINGS, null ) ) && $current === $new ) return true;
        return update_option( self::OPT_SETTINGS, $new );
    }

    // =========================================================================
    // DÉTECTION & BLOCAGE
    // =========================================================================

    /**
     * Filter authenticate (priority 30) : retourne WP_Error si IP bannie.
     * Bloque même si le username/password seraient corrects.
     */
    public function check_blocked_ip( $user, $username, $password ) {
        $ip = self::client_ip();
        if ( ! $ip ) return $user;
        if ( self::is_whitelisted($ip) ) return $user;
        if ( ! self::is_blocked($ip) ) return $user;

        // IP bannie — retourne une WP_Error explicite.
        return new WP_Error(
            'too_many_attempts',
            '<strong>' . esc_html__( 'Accès temporairement bloqué.', 'alesta' ) . '</strong> '
            . esc_html__( 'Trop de tentatives de connexion depuis votre adresse IP. Réessayez dans quelques minutes.', 'alesta' )
        );
    }

    /**
     * Hook wp_login_failed — incrémente le compteur et BAN si seuil dépassé.
     */
    public function on_login_failed( $username ): void {
        $ip = self::client_ip();
        if ( ! $ip || self::is_whitelisted($ip) ) return;

        $s     = self::settings();
        $key   = self::PREFIX_ATTEMPT . self::hash_ip($ip);
        $count = (int) get_transient($key);
        $count++;

        // Refresh TTL à chaque tentative (= fenêtre glissante).
        set_transient( $key, $count, (int) $s['window_seconds'] );

        if ( $count >= (int) $s['threshold'] ) {
            self::ban_ip( $ip, $count, (string) $username );
            delete_transient( $key );
        }
    }

    /**
     * Hook wp_login (succès) — reset le compteur de l'IP.
     */
    public function on_login_success( $user_login, $user ): void {
        $ip = self::client_ip();
        if ( ! $ip ) return;
        delete_transient( self::PREFIX_ATTEMPT . self::hash_ip($ip) );
    }

    /**
     * Hard block sur la page wp-login.php elle-même (avant rendu du formulaire).
     */
    public function block_login_form_if_banned(): void {
        $ip = self::client_ip();
        if ( ! $ip || self::is_whitelisted($ip) ) return;
        if ( ! self::is_blocked($ip) ) return;

        status_header(429);
        nocache_headers();
        wp_die(
            esc_html__( 'Trop de tentatives de connexion depuis votre adresse IP. Veuillez réessayer dans quelques minutes.', 'alesta' ),
            esc_html__( 'Accès temporairement bloqué', 'alesta' ),
            [ 'response' => 429, 'back_link' => false ]
        );
    }

    /**
     * Bloque les appels XML-RPC depuis IP bannie (vecteur d'attaque classique).
     * wp_die() en contexte XML-RPC renvoie un fault XML-RPC propre + HTTP 429.
     */
    public function block_xmlrpc_if_banned(): void {
        $ip = self::client_ip();
        if ( ! $ip || self::is_whitelisted($ip) ) return;
        if ( ! self::is_blocked($ip) ) return;

        status_header(429);
        nocache_headers();
        wp_die(
            esc_html__( 'Too Many Requests', 'alesta' ),
            esc_html__( 'Accès temporairement bloqué', 'alesta' ),
            [ 'response' => 429 ]
        );
    }

    // =========================================================================
    // GESTION DES BANS
    // =========================================================================

    /**
     * Pose le ban et déclenche l'action 'alesta_brute_force_ban' pour les hooks
     * externes (notification e-mail, module Alerts de la Pro, etc.).
     */
    private static function ban_ip( string $ip, int $count, string $username ): void {
        $s = self::settings();
        set_transient( self::PREFIX_BLOCK . self::hash_ip($ip), time(), (int) $s['ban_seconds'] );

        // Log dans une option (max 100 dernières entrées) pour affichage admin.
        $log = get_option( self::OPT_BANNED_LOG, [] );
        if ( ! is_array($log) ) $log = [];
        array_unshift( $log, [
            'ip_hash'    => substr( self::hash_ip($ip), 0, 12 ),
            'ip'         => $ip, // gardé en clair côté admin uniquement (option non publique)
            'banned_at'  => time(),
            'expires_at' => time() + (int) $s['ban_seconds'],
            'attempts'   => $count,
            'last_user'  => substr( $username, 0, 60 ),
        ] );
        $log = array_slice( $log, 0, 100 );
        update_option( self::OPT_BANNED_LOG, $log, false );

        /**
         * Déclenchée à chaque ban.
         *
         * @param string $ip       L'IP bannie (en clair, niveau plugin uniquement)
         * @param int    $count    Nombre de tentatives qui ont déclenché le ban
         * @param string $username Dernier username essayé
         */
        do_action( 'alesta_brute_force_ban', $ip, $count, $username );
    }

    public static function is_blocked( string $ip ): bool {
        return false !== get_transient( self::PREFIX_BLOCK . self::hash_ip($ip) );
    }

    public static function is_whitelisted( string $ip ): bool {
        $s = self::settings();
        return in_array( $ip, (array) $s['whitelist'], true );
    }

    /**
     * Liste des bans actifs (= dont le transient n'a pas expiré).
     */
    public static function active_bans(): array {
        $log = get_option( self::OPT_BANNED_LOG, [] );
        if ( ! is_array($log) ) return [];
        $now = time();
        return array_values( array_filter( $log, function($entry) use ($now) {
            return isset($entry['expires_at'], $entry['ip'])
                && $entry['expires_at'] > $now
                && self::is_blocked( (string) $entry['ip'] );
        } ) );
    }

    /**
     * Historique complet des bans (100 derniers, actifs ou expirés).
     */
    public static function banned_log(): array {
        $log = get_option( self::OPT_BANNED_LOG, [] );
        return is_array($log) ? array_values($log) : [];
    }

    /**
     * Unban manuel d'une IP (depuis l'admin). Marque aussi l'entrée du journal
     * comme expirée pour qu'elle disparaisse de la liste des bans actifs.
     */
    public static function unban( string $ip ): bool {
        $ok  = delete_transient( self::PREFIX_BLOCK . self::hash_ip($ip) );
        $log = get_option( self::OPT_BANNED_LOG, [] );
        if ( is_array($log) ) {
            $now = time();
            foreach ( $log as &$entry ) {
                if ( isset($entry['ip'], $entry['expires_at']) && $entry['ip'] === $ip && $entry['expires_at'] > $now ) {
                    $entry['expires_at'] = $now;
                }
            }
            unset($entry);
            update_option( self::OPT_BANNED_LOG, $log, false );
        }
        return $ok;
    }

    // =========================================================================
    // NOTIFICATION E-MAIL
    // =========================================================================

    /**
     * Hook 'alesta_brute_force_ban' — 1 mail à l'admin par ban, avec un
     * garde-fou anti-flood : au maximum 1 mail toutes les 2 heures (même
     * comportement que le module Alerts de la Pro).
     */
    public function notify_admin( string $ip, int $count, string $username ): void {
        if ( false !== get_transient( self::NOTIFY_LOCK ) ) return;
        set_transient( self::NOTIFY_LOCK, time(), 2 * HOUR_IN_SECONDS );

        $s           = self::settings();
        $ban_minutes = (int) round( ((int) $s['ban_seconds']) / 60 );
        $site        = wp_specialchars_decode( get_bloginfo('name'), ENT_QUOTES );
        $email       = get_option('admin_email');
        if ( ! is_email($email) ) return;

        $subject = sprintf(
            /* translators: %s : nom du site */
            __( '[Alesta] Alerte sécurité HAUTE — %s', 'alesta' ),
            $site
        );
        $msg = sprintf(
            /* translators: %1$d nombre de tentatives, %2$s IP bannie, %3$d durée du ban en minutes */
            __( '%1$d tentatives de connexion échouées depuis %2$s. IP automatiquement bannie pendant %3$d minutes.', 'alesta' ),
            $count,
            $ip,
            $ban_minutes
        );
        $body = __( 'Bonjour,', 'alesta' ) . "\n\n"
              . __( 'Une alerte a été détectée sur votre site WordPress :', 'alesta' ) . "\n\n"
              . __( 'Site', 'alesta' )     . '    : ' . $site . ' (' . home_url('/') . ")\n"
              . __( 'Type', 'alesta' )     . '    : brute_force_ban' . "\n"
              . __( 'Message', 'alesta' )  . ' : ' . $msg . "\n"
              . __( 'Dernier identifiant essayé', 'alesta' ) . ' : ' . $username . "\n"
              . __( 'Date', 'alesta' )     . '    : ' . current_time('d/m/Y H:i') . "\n\n"
              . __( 'Connectez-vous à votre tableau de bord pour agir :', 'alesta' ) . "\n"
              . admin_url('admin.php?page=alesta-ai-brute-force') . "\n\n"
              . "— Alesta\n";

        wp_mail( $email, $subject, $body );
    }

    // =========================================================================
    // AJAX
    // =========================================================================

    public function ajax_unban(): void {
        check_ajax_referer( self::NONCE_UNBAN, 'nonce' );
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error( [ 'message' => __( 'Accès refusé.', 'alesta' ) ], 403 );
        }
        $ip = isset($_POST['ip']) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            wp_send_json_error( [ 'message' => __( 'IP invalide.', 'alesta' ) ], 400 );
        }
        $ok = self::unban( $ip );
        wp_send_json_success( [ 'unbanned' => $ok, 'ip' => $ip ] );
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Hash SHA-256 de l'IP pour stockage (RGPD : pas d'IP en clair en transient).
     */
    private static function hash_ip( string $ip ): string {
        return hash( 'sha256', $ip );
    }

    /**
     * Récupère l'IP client réelle (gère X-Forwarded-For pour reverse-proxy /
     * Cloudflare / load balancer). Prend la PREMIÈRE IP de la chaîne X-FF
     * (= le client originel, pas le dernier proxy).
     */
    public static function client_ip(): string {
        $candidates = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ];
        foreach ( $candidates as $k ) {
            if ( empty( $_SERVER[$k] ) ) continue;
            $raw = sanitize_text_field( wp_unslash( $_SERVER[$k] ) );
            // X-Forwarded-For peut être "client, proxy1, proxy2".
            $first = trim( explode( ',', $raw )[0] );
            if ( filter_var( $first, FILTER_VALIDATE_IP ) ) return $first;
        }
        return '';
    }
}
