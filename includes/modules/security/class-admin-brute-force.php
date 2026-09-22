<?php
defined('ABSPATH') || exit;

/**
 * Protection Brute Force — Page admin (Alesta)
 *
 * Configuration et monitoring du module Alesta_Brute_Force_Module :
 *   - Activer / désactiver la protection
 *   - Régler le seuil, la fenêtre temporelle, la durée du ban
 *   - Whitelist d'IPs (pour ne jamais se bloquer soi-même)
 *   - Notification e-mail à l'admin (1 mail par ban)
 *   - Liste des IPs actuellement bannies + bouton "Débloquer"
 *   - Historique des 100 derniers bans (date, tentatives, dernier identifiant)
 *
 * Lorsque l'addon Alesta AI Pro est actif, sa page Alesta_AI_Admin_Brute_Force
 * (même slug alesta-ai-brute-force) remplace celle-ci : le loader du Free ne
 * doit pas instancier cette classe ni enregistrer le sous-menu si
 * class_exists('Alesta_AI_Admin_Brute_Force').
 *
 * PHP 7.4 compatible.
 */
class Alesta_Admin_Brute_Force {

    const NONCE_SAVE = 'alesta_bf_save';
    const PAGE_SLUG  = 'alesta-ai-brute-force';

    public function __construct() {
        add_action('admin_enqueue_scripts',              [$this, 'enqueue']);
        add_action('wp_ajax_alesta_bf_save_settings',    [$this, 'ajax_save_settings']);
    }

    // =========================================================================
    // ASSETS
    // =========================================================================

    public function enqueue( string $hook ): void {
        if ( strpos( $hook, self::PAGE_SLUG ) === false ) return;

        $base = plugin_dir_url( ALESTA_PLUGIN_FILE );
        $ver  = ALESTA_VERSION;

        wp_enqueue_style ( 'alesta-brute-force-admin', $base . 'assets/brute-force-admin.css', [], $ver );
        wp_enqueue_script( 'alesta-brute-force-admin', $base . 'assets/brute-force-admin.js', ['jquery'], $ver, true );

        wp_localize_script( 'alesta-brute-force-admin', 'AlestaBruteForce', [
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce_save'  => wp_create_nonce( self::NONCE_SAVE ),
            'nonce_unban' => wp_create_nonce( Alesta_Brute_Force_Module::NONCE_UNBAN ),
            'client_ip'   => Alesta_Brute_Force_Module::client_ip(),
            'i18n'        => [
                'saving'        => __( 'Enregistrement…', 'alesta' ),
                'saved'         => __( 'Paramètres enregistrés.', 'alesta' ),
                'error'         => __( 'Erreur', 'alesta' ),
                'network_error' => __( 'Erreur réseau', 'alesta' ),
                /* translators: %s : adresse IP */
                'confirm_unban' => __( 'Débloquer l\'IP %s immédiatement ?', 'alesta' ),
                'unban'         => __( 'Débloquer', 'alesta' ),
                /* translators: %s : message d'erreur */
                'unban_failed'  => __( 'Échec : %s', 'alesta' ),
                'ip_added'      => __( 'IP ajoutée à la whitelist — pensez à enregistrer.', 'alesta' ),
                'ip_present'    => __( 'Cette IP est déjà dans la whitelist.', 'alesta' ),
            ],
        ] );
    }

    // =========================================================================
    // AJAX
    // =========================================================================

    public function ajax_save_settings(): void {
        check_ajax_referer( self::NONCE_SAVE, 'nonce' );
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error( [ 'message' => __( 'Accès refusé.', 'alesta' ) ], 403 );
        }

        $enabled        = ! empty( $_POST['enabled'] );
        $notify_email   = ! empty( $_POST['notify_email'] );
        $threshold      = isset( $_POST['threshold'] )      ? absint( wp_unslash( $_POST['threshold'] ) )      : 5;
        $window_seconds = isset( $_POST['window_seconds'] ) ? absint( wp_unslash( $_POST['window_seconds'] ) ) : 300;
        $ban_seconds    = isset( $_POST['ban_seconds'] )    ? absint( wp_unslash( $_POST['ban_seconds'] ) )    : 900;

        // Whitelist : IPs séparées par retour à la ligne ou virgule dans une textarea.
        $whitelist_raw = isset( $_POST['whitelist'] ) ? sanitize_textarea_field( wp_unslash( $_POST['whitelist'] ) ) : '';
        $whitelist     = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $whitelist_raw ) ) );

        $ok = Alesta_Brute_Force_Module::save_settings( [
            'enabled'        => $enabled,
            'notify_email'   => $notify_email,
            'threshold'      => $threshold,
            'window_seconds' => $window_seconds,
            'ban_seconds'    => $ban_seconds,
            'whitelist'      => $whitelist,
        ] );

        if ( $ok ) {
            wp_send_json_success( [ 'message' => __( 'Paramètres enregistrés.', 'alesta' ) ] );
        }
        wp_send_json_error( [ 'message' => __( 'Échec de l\'enregistrement.', 'alesta' ) ] );
    }

    // =========================================================================
    // RENDU DE LA PAGE
    // =========================================================================

    public function render_page(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__( 'Accès refusé.', 'alesta' ) );
        }
        if ( ! class_exists('Alesta_Brute_Force_Module') ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Module Brute Force non disponible.', 'alesta' ) . '</p></div>';
            return;
        }

        $s             = Alesta_Brute_Force_Module::settings();
        $active_bans   = Alesta_Brute_Force_Module::active_bans();
        $history       = Alesta_Brute_Force_Module::banned_log();
        $whitelist_str = implode( "\n", (array) $s['whitelist'] );
        $client_ip     = Alesta_Brute_Force_Module::client_ip();
        $is_enabled    = ! empty( $s['enabled'] );
        $nb_active     = count( $active_bans );
        ?>
        <div class="wrap alesta-wrap" id="alesta-bf-wrap">

            <!-- ── En-tête ── -->
            <div class="alesta-bf-header">
                <div class="alesta-bf-header-left">
                    <span class="alesta-bf-header-icon"><?php echo "\xF0\x9F\x9B\xA1\xEF\xB8\x8F"; ?></span>
                    <div>
                        <h1><?php esc_html_e( 'Protection Brute Force', 'alesta' ); ?></h1>
                        <p class="alesta-bf-subtitle"><?php esc_html_e( 'Blocage automatique des IPs après tentatives de connexion répétées (fail2ban-like)', 'alesta' ); ?></p>
                    </div>
                </div>
                <span class="alesta-bf-status <?php echo $is_enabled ? 'is-on' : 'is-off'; ?>">
                    <?php echo $is_enabled ? esc_html__( 'Protection ACTIVE', 'alesta' ) : esc_html__( 'Protection INACTIVE', 'alesta' ); ?>
                </span>
            </div>

            <!-- ── Info IP courante ── -->
            <?php if ( $client_ip ) : ?>
            <div class="alesta-bf-notice">
                <?php
                printf(
                    /* translators: %s : adresse IP courante (en gras) */
                    esc_html__( 'Votre IP actuelle : %s — pensez à l\'ajouter à la whitelist ci-dessous pour ne jamais vous bloquer vous-même par erreur.', 'alesta' ),
                    '<strong>' . esc_html( $client_ip ) . '</strong>'
                );
                ?>
                <button type="button" class="button button-small" id="bf-add-my-ip"><?php esc_html_e( 'Ajouter mon IP à la whitelist', 'alesta' ); ?></button>
            </div>
            <?php endif; ?>

            <!-- ── Paramètres ── -->
            <div class="alesta-bf-card">
                <div class="alesta-bf-card-head">
                    <span class="alesta-bf-card-icon"><?php echo "\xE2\x9A\x99\xEF\xB8\x8F"; ?></span>
                    <div>
                        <div class="alesta-bf-card-title"><?php esc_html_e( 'Paramètres de blocage', 'alesta' ); ?></div>
                        <div class="alesta-bf-card-desc"><?php esc_html_e( 'Standards fail2ban recommandés : 5 tentatives en 5 min → ban 15 min', 'alesta' ); ?></div>
                    </div>
                </div>

                <div class="alesta-bf-toggles">
                    <label class="alesta-bf-check">
                        <input type="checkbox" id="bf-enabled" <?php checked( $is_enabled ); ?>>
                        <span><?php esc_html_e( 'Activer la protection', 'alesta' ); ?></span>
                    </label>
                    <label class="alesta-bf-check">
                        <input type="checkbox" id="bf-notify" <?php checked( ! empty( $s['notify_email'] ) ); ?>>
                        <span>
                            <?php
                            printf(
                                /* translators: %s : adresse e-mail admin */
                                esc_html__( 'M\'avertir par e-mail à chaque ban (%s, max. 1 mail / 2 h)', 'alesta' ),
                                esc_html( get_option('admin_email') )
                            );
                            ?>
                        </span>
                    </label>
                </div>

                <div class="alesta-bf-grid">
                    <div>
                        <label for="bf-threshold"><?php esc_html_e( 'Seuil (tentatives)', 'alesta' ); ?></label>
                        <input type="number" id="bf-threshold" value="<?php echo esc_attr( $s['threshold'] ); ?>" min="2" max="100">
                        <small><?php esc_html_e( 'Nombre d\'échecs avant ban', 'alesta' ); ?></small>
                    </div>
                    <div>
                        <label for="bf-window"><?php esc_html_e( 'Fenêtre (secondes)', 'alesta' ); ?></label>
                        <input type="number" id="bf-window" value="<?php echo esc_attr( $s['window_seconds'] ); ?>" min="60" max="3600">
                        <small><?php esc_html_e( 'Période de comptage', 'alesta' ); ?></small>
                    </div>
                    <div>
                        <label for="bf-ban"><?php esc_html_e( 'Durée ban (secondes)', 'alesta' ); ?></label>
                        <input type="number" id="bf-ban" value="<?php echo esc_attr( $s['ban_seconds'] ); ?>" min="60" max="86400">
                        <small><?php esc_html_e( 'Durée du blocage', 'alesta' ); ?></small>
                    </div>
                </div>

                <div class="alesta-bf-field">
                    <label for="bf-whitelist"><?php esc_html_e( 'Whitelist d\'IPs (une par ligne)', 'alesta' ); ?></label>
                    <textarea id="bf-whitelist" rows="4" placeholder="192.168.1.42&#10;203.0.113.5"><?php echo esc_textarea( $whitelist_str ); ?></textarea>
                    <small><?php esc_html_e( 'Ces IPs ne seront JAMAIS bannies. Ajoutez la vôtre, votre VPN, vos collègues admin… (50 max.)', 'alesta' ); ?></small>
                </div>

                <div class="alesta-bf-actions">
                    <button type="button" id="bf-save-btn" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'alesta' ); ?></button>
                    <span id="bf-save-feedback" class="alesta-bf-feedback" hidden></span>
                </div>
            </div>

            <!-- ── IPs bannies actuellement ── -->
            <div class="alesta-bf-card">
                <div class="alesta-bf-card-head">
                    <span class="alesta-bf-card-icon"><?php echo "\xF0\x9F\x9A\xAB"; ?></span>
                    <div>
                        <div class="alesta-bf-card-title"><?php esc_html_e( 'IPs bannies actuellement', 'alesta' ); ?></div>
                        <div class="alesta-bf-card-desc">
                            <?php
                            printf(
                                /* translators: %d : nombre de bans actifs */
                                esc_html( _n( '%d ban actif', '%d bans actifs', $nb_active, 'alesta' ) ),
                                (int) $nb_active
                            );
                            ?>
                        </div>
                    </div>
                </div>

                <?php if ( empty( $active_bans ) ) : ?>
                    <p class="alesta-bf-empty"><?php esc_html_e( 'Aucune IP bannie actuellement. Votre site n\'est pas sous attaque.', 'alesta' ); ?></p>
                <?php else : ?>
                    <table class="alesta-bf-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'IP', 'alesta' ); ?></th>
                                <th><?php esc_html_e( 'Tentatives', 'alesta' ); ?></th>
                                <th><?php esc_html_e( 'Dernier identifiant', 'alesta' ); ?></th>
                                <th><?php esc_html_e( 'Banni', 'alesta' ); ?></th>
                                <th><?php esc_html_e( 'Expire', 'alesta' ); ?></th>
                                <th><?php esc_html_e( 'Action', 'alesta' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $active_bans as $ban ) :
                                $remaining_min = (int) ceil( max( 0, (int) $ban['expires_at'] - time() ) / 60 );
                            ?>
                            <tr>
                                <td class="alesta-bf-mono"><?php echo esc_html( $ban['ip'] ); ?></td>
                                <td><strong><?php echo esc_html( (int) ( $ban['attempts'] ?? 0 ) ); ?></strong></td>
                                <td class="alesta-bf-mono"><?php echo esc_html( $ban['last_user'] ?? '' ); ?></td>
                                <td class="alesta-bf-muted">
                                    <?php
                                    printf(
                                        /* translators: %s : durée écoulée (ex. "3 min") */
                                        esc_html__( 'il y a %s', 'alesta' ),
                                        esc_html( human_time_diff( (int) $ban['banned_at'], time() ) )
                                    );
                                    ?>
                                </td>
                                <td class="alesta-bf-muted">
                                    <?php
                                    printf(
                                        /* translators: %d : minutes restantes */
                                        esc_html__( 'dans %d min', 'alesta' ),
                                        (int) $remaining_min
                                    );
                                    ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small bf-unban-btn" data-ip="<?php echo esc_attr( $ban['ip'] ); ?>">
                                        <?php esc_html_e( 'Débloquer', 'alesta' ); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- ── Historique des bans ── -->
            <?php if ( ! empty( $history ) ) : ?>
            <details class="alesta-bf-card alesta-bf-history">
                <summary>
                    <span class="alesta-bf-card-icon"><?php echo "\xF0\x9F\x93\x9C"; ?></span>
                    <span class="alesta-bf-card-title">
                        <?php
                        printf(
                            /* translators: %d : nombre d'entrées */
                            esc_html__( 'Historique des bans (%d derniers)', 'alesta' ),
                            count( $history )
                        );
                        ?>
                    </span>
                </summary>
                <table class="alesta-bf-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'alesta' ); ?></th>
                            <th><?php esc_html_e( 'IP', 'alesta' ); ?></th>
                            <th><?php esc_html_e( 'Tentatives', 'alesta' ); ?></th>
                            <th><?php esc_html_e( 'Dernier identifiant', 'alesta' ); ?></th>
                            <th><?php esc_html_e( 'Statut', 'alesta' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $history as $entry ) :
                            $still_active = isset( $entry['expires_at'] ) && (int) $entry['expires_at'] > time();
                        ?>
                        <tr>
                            <td class="alesta-bf-muted"><?php echo esc_html( date_i18n( 'd/m/Y H:i', (int) ( $entry['banned_at'] ?? 0 ) ) ); ?></td>
                            <td class="alesta-bf-mono"><?php echo esc_html( $entry['ip'] ?? '' ); ?></td>
                            <td><?php echo esc_html( (int) ( $entry['attempts'] ?? 0 ) ); ?></td>
                            <td class="alesta-bf-mono"><?php echo esc_html( $entry['last_user'] ?? '' ); ?></td>
                            <td>
                                <span class="alesta-bf-pill <?php echo $still_active ? 'is-active' : 'is-expired'; ?>">
                                    <?php echo $still_active ? esc_html__( 'Actif', 'alesta' ) : esc_html__( 'Expiré', 'alesta' ); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
            <?php endif; ?>

            <!-- ── Footer info ── -->
            <p class="alesta-bf-footer">
                <strong><?php esc_html_e( 'Comment ça marche ?', 'alesta' ); ?></strong>
                <?php esc_html_e( 'Quand une adresse IP atteint le seuil de tentatives de connexion ratées dans la fenêtre temporelle, elle est automatiquement ajoutée à la liste de blocage pour la durée configurée.', 'alesta' ); ?>
                <?php
                printf(
                    /* translators: %1$s : wp-login.php, %2$s : HTTP 429 */
                    esc_html__( 'Pendant ce temps, la page %1$s répond %2$s aux requêtes de cette IP.', 'alesta' ),
                    '<code>wp-login.php</code>',
                    '<code>HTTP 429</code>'
                );
                ?>
                <?php esc_html_e( 'Les appels XML-RPC sont également bloqués (vecteur d\'attaque classique). Une connexion réussie remet le compteur à zéro pour cette IP.', 'alesta' ); ?>
                <?php esc_html_e( 'Les IPs sont stockées hachées (SHA-256) dans les transients, conformément au RGPD.', 'alesta' ); ?>
            </p>

        </div>
        <?php
    }
}
