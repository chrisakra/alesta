<?php
/**
 * Alesta — Invitation à laisser un avis sur WordPress.org.
 *
 * Règles que cette classe s'impose (directives WordPress.org + bon sens) :
 *   • jamais bloquante : aucun module n'est conditionné à un avis ;
 *   • jamais de contrepartie : on ne "paie" pas un avis ;
 *   • affichée uniquement sur les pages Alesta AI, jamais dans tout l'admin ;
 *   • seulement après une valeur réellement rendue (modules utilisés) ET
 *     10 jours d'utilisation ;
 *   • trois sorties : noter / plus tard (30 j, 2 fois max) / non merci ;
 *   • état stocké par UTILISATEUR (user meta), pour qu'un refus n'impose rien
 *     aux autres administrateurs du site.
 *
 * Prévisualisation (pour l'auteur) : ajouter &alesta_review_preview=1 à l'URL
 * d'une page Alesta AI. Affiche le bandeau sans rien enregistrer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alesta_Review_Prompt {

	/** Meta utilisateur : état du bandeau. */
	const USER_META = 'alesta_review_prompt';

	/** Option site : date de première activation (timestamp). */
	const OPT_INSTALLED = 'alesta_installed_at';

	/** Jours d'utilisation avant la première demande. */
	const MIN_DAYS = 10;

	/** Nombre minimum de modules utilisés avant de demander. */
	const MIN_SIGNALS = 2;

	/** Durée d'un report (jours) et nombre maximum de reports. */
	const SNOOZE_DAYS = 30;
	const MAX_SNOOZE  = 2;

	const REVIEW_URL = 'https://wordpress.org/support/plugin/alesta/reviews/#new-post';

	public function __construct() {
		add_action( 'admin_init', array( __CLASS__, 'remember_install_date' ) );
		add_action( 'wp_ajax_alesta_review_prompt', array( __CLASS__, 'ajax_update' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
		add_filter( 'admin_footer_text', array( __CLASS__, 'admin_footer_text' ) );
	}

	/**
	 * Date d'installation : posée à l'activation pour les nouvelles
	 * installations, et au premier chargement de l'admin pour celles qui
	 * existaient déjà avant cette version (sinon elles seraient toutes
	 * éligibles le jour de la mise à jour).
	 */
	public static function remember_install_date() {
		if ( ! get_option( self::OPT_INSTALLED ) ) {
			add_option( self::OPT_INSTALLED, time(), '', false );
		}
	}

	/** Appelée par register_activation_hook(). */
	public static function on_activation() {
		self::remember_install_date();
	}

	// ---------------------------------------------------------------------
	// AFFICHAGE
	// ---------------------------------------------------------------------

	/**
	 * Rend le bandeau si toutes les conditions sont réunies. Appelée en haut
	 * du tableau de bord Alesta AI (pas via admin_notices : on ne pollue pas
	 * les écrans des autres extensions).
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$preview = self::is_preview();
		if ( ! $preview && ! self::should_show() ) {
			return;
		}

		$signals = self::signals();
		$intro   = self::intro_sentence( $signals );
		$state   = self::state();
		$snoozed = (int) $state['snoozes'];
		?>
		<div class="alesta-review-prompt" id="alesta-review-prompt">
			<span class="arp-star" aria-hidden="true">&#x2605;</span>
			<div class="arp-body">
				<p class="arp-title"><?php esc_html_e( 'On a mis beaucoup de cœur ❤️ dans Alesta AI', 'alesta' ); ?></p>
				<p class="arp-text">
					<?php esc_html_e( 'Des mois de développement, des centaines d\'heures de tests — et une extension qu\'on garde gratuite, sans publicité ni limite artificielle.', 'alesta' ); ?>
					<?php echo esc_html( $intro ); ?>
					<?php esc_html_e( 'Si elle vous fait gagner du temps, un avis sur WordPress.org prend une minute et nous aide énormément à la faire connaître.', 'alesta' ); ?>
				</p>
				<p class="arp-signature"><?php esc_html_e( '— Christian, Alesta Computer', 'alesta' ); ?></p>
				<?php if ( $preview ) : ?>
					<p class="arp-preview"><?php esc_html_e( 'Prévisualisation : ce bandeau n\'est pas encore affiché à vos utilisateurs.', 'alesta' ); ?></p>
				<?php endif; ?>
				<p class="arp-actions">
					<a class="button button-primary" href="<?php echo esc_url( self::REVIEW_URL ); ?>" target="_blank" rel="noopener noreferrer" data-alesta-review="rated">
						<?php esc_html_e( 'Avec plaisir, je laisse un avis', 'alesta' ); ?>
					</a>
					<?php if ( $snoozed < self::MAX_SNOOZE ) : ?>
						<a class="button" href="<?php echo esc_url( self::fallback_url( 'snooze' ) ); ?>" data-alesta-review="snooze">
							<?php esc_html_e( 'Plus tard', 'alesta' ); ?>
						</a>
					<?php endif; ?>
					<a class="button-link arp-dismiss" href="<?php echo esc_url( self::fallback_url( 'dismiss' ) ); ?>" data-alesta-review="dismiss">
						<?php esc_html_e( 'Non merci', 'alesta' ); ?>
					</a>
				</p>
			</div>
			<button type="button" class="arp-close" data-alesta-review="dismiss" aria-label="<?php esc_attr_e( 'Ne plus afficher', 'alesta' ); ?>">&times;</button>
		</div>
		<style>
		.alesta-review-prompt{position:relative;display:flex;gap:14px;align-items:flex-start;margin:0 0 20px;padding:16px 44px 16px 18px;background:#fff;border:1px solid #dce6f3;border-left:4px solid #4A90D9;border-radius:8px;box-shadow:0 1px 2px rgba(15,20,28,.05);}
		.alesta-review-prompt .arp-star{font-size:22px;line-height:1.2;color:#f0b429;}
		.alesta-review-prompt p{margin:0 0 6px;}
		.alesta-review-prompt .arp-title{font-size:14px;font-weight:600;color:#0f141c;}
		.alesta-review-prompt .arp-text{font-size:13px;color:#475569;max-width:70ch;}
		.alesta-review-prompt .arp-signature{font-size:13px;color:#6a7388;font-style:italic;}
		.alesta-review-prompt .arp-preview{font-size:12px;color:#b45309;font-style:italic;}
		.alesta-review-prompt .arp-actions{margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
		.alesta-review-prompt .arp-dismiss{color:#6a7388;text-decoration:none;}
		.alesta-review-prompt .arp-dismiss:hover{color:#0f141c;}
		.alesta-review-prompt .arp-close{position:absolute;top:8px;right:8px;border:0;background:none;color:#9aa4b8;font-size:20px;line-height:1;cursor:pointer;padding:4px 8px;}
		.alesta-review-prompt .arp-close:hover{color:#0f141c;}
		</style>
		<script>
		(function () {
			var box = document.getElementById('alesta-review-prompt');
			if (!box) { return; }
			var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce = <?php echo wp_json_encode( wp_create_nonce( 'alesta_review_prompt' ) ); ?>;
			var preview = <?php echo $preview ? 'true' : 'false'; ?>;
			box.addEventListener('click', function (e) {
				var el = e.target.closest('[data-alesta-review]');
				if (!el) { return; }
				var action = el.getAttribute('data-alesta-review');
				if (action !== 'rated') { e.preventDefault(); }
				if (!preview) {
					var body = new FormData();
					body.append('action', 'alesta_review_prompt');
					body.append('nonce', nonce);
					body.append('choice', action);
					fetch(ajax, { method: 'POST', body: body, credentials: 'same-origin' });
				}
				box.style.display = 'none';
			});
		}());
		</script>
		<?php
	}

	/** Conditions d'affichage (hors prévisualisation). */
	private static function should_show() {
		$state = self::state();

		if ( ! empty( $state['dismissed'] ) ) {
			return false;
		}
		if ( ! empty( $state['snoozed_until'] ) && time() < (int) $state['snoozed_until'] ) {
			return false;
		}

		$installed = (int) get_option( self::OPT_INSTALLED, 0 );
		if ( ! $installed || ( time() - $installed ) < self::MIN_DAYS * DAY_IN_SECONDS ) {
			return false;
		}

		return count( self::signals() ) >= self::MIN_SIGNALS;
	}

	/**
	 * Modules effectivement utilisés. On s'appuie sur les options déjà
	 * écrites par les modules : aucune collecte supplémentaire.
	 *
	 * @return array Libellés des actions réussies.
	 */
	private static function signals() {
		$found = array();

		if ( get_option( 'alesta_sitemap_last_gen' ) ) {
			$found[] = __( 'votre sitemap XML est généré', 'alesta' );
		}

		$cleaner = get_option( 'alesta_db_cleaner_last_result' );
		if ( ! empty( $cleaner ) ) {
			$deleted = 0;
			if ( is_array( $cleaner ) ) {
				foreach ( $cleaner as $value ) {
					if ( is_numeric( $value ) ) {
						$deleted += (int) $value;
					}
				}
			}
			$found[] = $deleted > 0
				/* translators: %s : nombre d'entrées supprimées. */
				? sprintf( __( '%s entrée(s) nettoyée(s) en base', 'alesta' ), number_format_i18n( $deleted ) )
				: __( 'votre base de données est nettoyée', 'alesta' );
		}

		if ( get_option( 'alesta_errors_scan_results' ) ) {
			$found[] = __( 'vos liens cassés sont surveillés', 'alesta' );
		}
		if ( get_option( 'alesta_ai_meta_history' ) ) {
			$found[] = __( 'vos balises SEO sont générées par l\'IA', 'alesta' );
		}
		if ( get_option( 'alesta_brute_force_banned_log' ) ) {
			$found[] = __( 'vos tentatives de connexion sont filtrées', 'alesta' );
		}

		return $found;
	}

	/** Phrase d'accroche personnalisée selon les modules utilisés. */
	private static function intro_sentence( array $signals ) {
		if ( empty( $signals ) ) {
			return __( 'Elle travaille pour ce site au quotidien.', 'alesta' );
		}

		$list = array_slice( $signals, 0, 2 );
		/* translators: %s : liste des actions réalisées par le plugin sur ce site. */
		return sprintf( __( 'Ici, %s.', 'alesta' ), implode( __( ' et ', 'alesta' ), $list ) );
	}

	// ---------------------------------------------------------------------
	// ÉTAT (par utilisateur)
	// ---------------------------------------------------------------------

	private static function state() {
		$state = get_user_meta( get_current_user_id(), self::USER_META, true );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		return wp_parse_args(
			$state,
			array(
				'dismissed'     => 0,
				'snoozed_until' => 0,
				'snoozes'       => 0,
			)
		);
	}

	private static function save_state( array $state ) {
		update_user_meta( get_current_user_id(), self::USER_META, $state );
	}

	/**
	 * Applique un choix. Partagé par l'AJAX et le repli sans JavaScript.
	 *
	 * @param string $choice rated | snooze | dismiss.
	 */
	private static function apply_choice( $choice ) {
		$state = self::state();

		if ( 'snooze' === $choice && (int) $state['snoozes'] < self::MAX_SNOOZE ) {
			$state['snoozed_until'] = time() + self::SNOOZE_DAYS * DAY_IN_SECONDS;
			$state['snoozes']       = (int) $state['snoozes'] + 1;
		} else {
			// "rated" et "dismiss" : on ne redemande plus jamais.
			$state['dismissed'] = 1;
		}

		self::save_state( $state );
	}

	public static function ajax_update() {
		check_ajax_referer( 'alesta_review_prompt', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'alesta' ) ), 403 );
		}

		$choice = isset( $_POST['choice'] ) ? sanitize_key( wp_unslash( $_POST['choice'] ) ) : '';
		if ( ! in_array( $choice, array( 'rated', 'snooze', 'dismiss' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Choix invalide.', 'alesta' ) ), 400 );
		}

		self::apply_choice( $choice );
		wp_send_json_success();
	}

	/**
	 * Repli sans JavaScript : lien GET signé traité au chargement de la page.
	 * Branché sur admin_init via handle_fallback().
	 */
	private static function fallback_url( $choice ) {
		return wp_nonce_url(
			add_query_arg( 'alesta_review', $choice, self::current_admin_url() ),
			'alesta_review_' . $choice
		);
	}

	public static function handle_fallback() {
		if ( empty( $_GET['alesta_review'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$choice = sanitize_key( wp_unslash( $_GET['alesta_review'] ) );
		if ( ! in_array( $choice, array( 'rated', 'snooze', 'dismiss' ), true ) ) {
			return;
		}
		check_admin_referer( 'alesta_review_' . $choice );

		self::apply_choice( $choice );
		wp_safe_redirect( remove_query_arg( array( 'alesta_review', '_wpnonce' ), self::current_admin_url() ) );
		exit;
	}

	// ---------------------------------------------------------------------
	// RAPPELS DISCRETS (toujours visibles, jamais intrusifs)
	// ---------------------------------------------------------------------

	/** Lien "Noter ce plugin" dans la ligne du plugin (page Extensions). */
	public static function plugin_row_meta( $links, $file ) {
		if ( plugin_basename( ALESTA_PLUGIN_FILE ) !== $file ) {
			return $links;
		}
		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s %s</a>',
			esc_url( self::REVIEW_URL ),
			'&#x2605;',
			esc_html__( 'Noter ce plugin', 'alesta' )
		);
		return $links;
	}

	/** Pied de page des écrans Alesta AI uniquement. */
	public static function admin_footer_text( $text ) {
		if ( ! self::is_alesta_screen() ) {
			return $text;
		}
		return sprintf(
			/* translators: %s : lien vers la page d'avis WordPress.org. */
			esc_html__( 'Alesta AI est développé en France et restera gratuit. %s', 'alesta' ),
			sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( self::REVIEW_URL ),
				esc_html__( 'Laissez un avis ★★★★★', 'alesta' )
			)
		);
	}

	// ---------------------------------------------------------------------
	// OUTILS
	// ---------------------------------------------------------------------

	private static function is_preview() {
		return ! empty( $_GET['alesta_review_preview'] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	private static function is_alesta_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && false !== strpos( (string) $screen->id, 'alesta-ai' );
	}

	private static function current_admin_url() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : Alesta_Admin::MENU_SLUG; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return admin_url( 'admin.php?page=' . $page );
	}
}
