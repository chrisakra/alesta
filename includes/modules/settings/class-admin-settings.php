<?php
/**
 * Alesta — Page admin « Configuration » (fournisseur IA, clé API et modèle).
 *
 * Slug : alesta-ai-settings (identique à la Pro). Quand la Pro est active,
 * elle fournit sa propre page « Configuration » : le loader du Free ne doit
 * alors PAS instancier cette classe (guard class_exists('Alesta_AI_Admin')).
 *
 * Les clés sont stockées chiffrées via Alesta_Key_Vault (un slot par
 * fournisseur, option Anthropic partagée avec la Pro), le modèle Anthropic
 * dans alesta_ai_model (idem), le modèle OpenAI dans alesta_ai_openai_model
 * et le fournisseur actif dans alesta_ai_provider. Rien à ressaisir après
 * upgrade, et changer de fournisseur ne supprime pas l'autre clé.
 *
 * @package Alesta
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alesta_Admin_Settings {

	const PAGE_SLUG = 'alesta-ai-settings';
	const NONCE     = 'alesta_settings_nonce';

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_alesta_settings_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_alesta_test_api_key', array( $this, 'ajax_test_api_key' ) );
		add_action( 'wp_ajax_alesta_settings_delete_key', array( $this, 'ajax_delete_key' ) );
		add_action( 'wp_ajax_alesta_settings_refresh_models', array( $this, 'ajax_refresh_models' ) );
	}

	// =========================================================================
	// ASSETS
	// =========================================================================

	/**
	 * Charge le JS de la page Configuration uniquement.
	 *
	 * @param string $hook Hook suffix de la page courante.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, self::PAGE_SLUG ) === false || strpos( $hook, self::PAGE_SLUG . '-section' ) !== false ) {
			return;
		}
		wp_enqueue_script(
			'alesta-settings',
			plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/settings-admin.js',
			array( 'jquery' ),
			ALESTA_VERSION,
			true
		);
		wp_localize_script(
			'alesta-settings',
			'AlestaSettings',
			array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE ),
				'providers' => array_keys( Alesta_API::get_providers() ),
				'i18n'      => array(
					'saving'         => __( 'Enregistrement…', 'alesta' ),
					'save'           => __( 'Enregistrer', 'alesta' ),
					'testing'        => __( 'Test en cours…', 'alesta' ),
					'test'           => __( 'Tester la clé', 'alesta' ),
					'refreshing'     => __( 'Récupération…', 'alesta' ),
					'refresh'        => __( 'Rafraîchir la liste', 'alesta' ),
					'network_error'  => __( 'Erreur réseau. Réessayez.', 'alesta' ),
					'unknown_error'  => __( 'Erreur inconnue.', 'alesta' ),
					'confirm_delete' => __( 'Supprimer la clé API enregistrée ? Les fonctions IA du plugin ne fonctionneront plus tant qu\'une nouvelle clé n\'est pas saisie.', 'alesta' ),
					'connected'      => __( 'IA connectée', 'alesta' ),
					'not_configured' => __( 'IA non configurée', 'alesta' ),
				),
			)
		);
	}

	// =========================================================================
	// AJAX
	// =========================================================================

	/**
	 * Vérifie nonce + capacité, sinon termine la requête.
	 */
	private function guard(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'alesta' ) ) );
		}
	}

	/**
	 * Lit le fournisseur envoyé en POST (défaut : le fournisseur enregistré).
	 *
	 * @return string
	 */
	private function read_posted_provider(): string {
		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié dans guard().
		return '' !== $provider ? Alesta_API::normalize_provider( $provider ) : Alesta_API::get_provider();
	}

	/**
	 * Lit et valide un identifiant de modèle envoyé en POST.
	 *
	 * La liste des modèles est dynamique (API du fournisseur) : on valide donc
	 * le format plutôt qu'une liste blanche figée qui vieillirait.
	 *
	 * @param string $field Nom du champ POST.
	 * @return string Modèle valide ou chaîne vide.
	 */
	private function read_posted_model( string $field = 'model' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié dans guard().
		$model = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		$model = trim( $model );
		return preg_match( '/^[A-Za-z0-9._:\/-]{1,80}$/', $model ) ? $model : '';
	}

	/**
	 * Lit une clé envoyée en POST (chaîne vide = conserver la clé actuelle).
	 *
	 * @param string $field Nom du champ POST.
	 * @return string
	 */
	private function read_posted_key( string $field = 'api_key' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié dans guard().
		$key = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		return trim( $key );
	}

	/**
	 * Nom du champ POST contenant la clé d'un fournisseur.
	 *
	 * @param string $provider Fournisseur.
	 * @return string
	 */
	private function key_field( string $provider ): string {
		return ( Alesta_API::PROVIDER_OPENAI === $provider ) ? 'openai_key' : 'api_key';
	}

	/**
	 * Nom du champ POST contenant le modèle d'un fournisseur.
	 *
	 * @param string $provider Fournisseur.
	 * @return string
	 */
	private function model_field( string $provider ): string {
		return ( Alesta_API::PROVIDER_OPENAI === $provider ) ? 'openai_model' : 'model';
	}

	/**
	 * Vérifie la forme d'une clé selon le fournisseur.
	 *
	 * @param string $provider Fournisseur.
	 * @param string $key      Clé saisie.
	 * @return true|string True si valide, message d'erreur sinon.
	 */
	private function validate_key_format( string $provider, string $key ) {
		if ( Alesta_API::PROVIDER_OPENAI === $provider ) {
			return strpos( $key, 'sk-' ) === 0
				? true
				: __( 'Format de clé invalide : une clé OpenAI commence par « sk- ».', 'alesta' );
		}
		return strpos( $key, 'sk-ant-' ) === 0
			? true
			: __( 'Format de clé invalide : une clé Anthropic commence par « sk-ant- ».', 'alesta' );
	}

	/**
	 * Enregistre le fournisseur, la (ou les) clé(s) fournie(s) et les modèles.
	 */
	public function ajax_save(): void {
		$this->guard();

		$provider = $this->read_posted_provider();

		// Les deux clés coexistent : on n'enregistre que celles réellement saisies.
		foreach ( array( Alesta_API::PROVIDER_ANTHROPIC, Alesta_API::PROVIDER_OPENAI ) as $slot_provider ) {
			$key = $this->read_posted_key( $this->key_field( $slot_provider ) );
			if ( '' === $key ) {
				continue;
			}

			$valid = $this->validate_key_format( $slot_provider, $key );
			if ( true !== $valid ) {
				wp_send_json_error( array( 'message' => $valid ) );
			}

			if ( ! Alesta_Key_Vault::set( $key, Alesta_API::key_slot( $slot_provider ) ) ) {
				wp_send_json_error( array( 'message' => __( 'Impossible de chiffrer la clé. Vérifiez que AUTH_KEY est bien défini dans wp-config.php (32 caractères minimum).', 'alesta' ) ) );
			}
		}

		$model = $this->read_posted_model( 'model' );
		if ( '' !== $model ) {
			update_option( Alesta_API::MODEL_OPTION, $model );
		}

		$openai_model = $this->read_posted_model( 'openai_model' );
		if ( '' !== $openai_model ) {
			update_option( Alesta_API::OPENAI_MODEL_OPTION, $openai_model );
		}

		update_option( Alesta_API::PROVIDER_OPTION, $provider );

		wp_send_json_success(
			array(
				'message'  => __( 'Réglages enregistrés.', 'alesta' ),
				'provider' => $provider,
				'has_key'  => Alesta_Key_Vault::has_key( Alesta_API::key_slot( $provider ) ),
				'masked'   => (string) ( Alesta_Key_Vault::get_masked( Alesta_API::key_slot( $provider ) ) ?? '' ),
			)
		);
	}

	/**
	 * Teste la clé du fournisseur sélectionné : celle envoyée en POST (test
	 * avant enregistrement) ou, à défaut, la clé stockée. Mini appel ask().
	 */
	public function ajax_test_api_key(): void {
		$this->guard();

		$provider = $this->read_posted_provider();
		$key      = $this->read_posted_key( $this->key_field( $provider ) );
		$model    = $this->read_posted_model( $this->model_field( $provider ) );

		if ( '' === $key && ! Alesta_Key_Vault::has_key( Alesta_API::key_slot( $provider ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Aucune clé à tester : collez une clé API ci-dessus.', 'alesta' ) ) );
		}

		$api    = new Alesta_API( '' !== $key ? $key : null, '' !== $model ? $model : null, $provider );
		$result = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( Alesta_API::error_payload( $result ) );
		}

		$providers = Alesta_API::get_providers();

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: provider label (Anthropic / OpenAI) */
					__( 'Connexion réussie : %s répond.', 'alesta' ),
					(string) ( $providers[ $provider ] ?? $provider )
				),
			)
		);
	}

	/**
	 * Supprime la clé stockée du fournisseur sélectionné (l'autre est conservée).
	 */
	public function ajax_delete_key(): void {
		$this->guard();
		$provider = $this->read_posted_provider();
		Alesta_Key_Vault::delete( Alesta_API::key_slot( $provider ) );
		wp_send_json_success( array( 'message' => __( 'Clé API supprimée.', 'alesta' ) ) );
	}

	/**
	 * Rafraîchit la liste des modèles du fournisseur (appel réel à son API,
	 * mise en cache 24 h).
	 */
	public function ajax_refresh_models(): void {
		$this->guard();

		$provider = $this->read_posted_provider();
		$key      = $this->read_posted_key( $this->key_field( $provider ) );

		$models = Alesta_API::fetch_models( $provider, $key, true );
		if ( is_wp_error( $models ) ) {
			wp_send_json_error( Alesta_API::error_payload( $models ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of models returned by the provider */
					_n( '%d modèle récupéré.', '%d modèles récupérés.', count( $models ), 'alesta' ),
					count( $models )
				),
				'count'   => count( $models ),
				// Même forme id => libellé que le rendu de la page, pour que le
				// JS puisse reconstruire le <select> sans recharger.
				'models'  => Alesta_API::get_models( $provider ),
			)
		);
	}

	// =========================================================================
	// PAGE
	// =========================================================================

	/**
	 * Rend le bloc d'un fournisseur (clé + modèle + actions).
	 *
	 * @param string $provider Fournisseur ('anthropic' ou 'openai').
	 */
	private function render_provider_block( string $provider ): void {
		$is_openai = ( Alesta_API::PROVIDER_OPENAI === $provider );
		$slot      = Alesta_API::key_slot( $provider );
		$has_key   = Alesta_Key_Vault::has_key( $slot );
		$masked    = (string) ( Alesta_Key_Vault::get_masked( $slot ) ?? '' );
		$model     = Alesta_API::get_model( $provider );
		$models    = Alesta_API::get_models( $provider );

		$label       = $is_openai ? __( 'Clé API OpenAI', 'alesta' ) : __( 'Clé API Anthropic', 'alesta' );
		$console_url = $is_openai ? 'https://platform.openai.com/api-keys' : 'https://console.anthropic.com/settings/keys';
		$console_lbl = $is_openai ? 'platform.openai.com/api-keys' : 'console.anthropic.com';
		$prefix      = $is_openai ? 'sk-...' : 'sk-ant-...';
		$title       = $is_openai ? __( 'API OpenAI (ChatGPT)', 'alesta' ) : __( 'API Claude (Anthropic)', 'alesta' );
		$badge       = $is_openai ? 'AI' : 'C';

		if ( $has_key && '' !== $masked ) {
			$placeholder = $masked . ' — ' . __( 'laisser vide pour conserver', 'alesta' );
		} else {
			$placeholder = $prefix;
		}
		?>
		<div class="alesta-provider-block" data-provider="<?php echo esc_attr( $provider ); ?>" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-bottom:16px;">
			<div style="display:flex;align-items:center;gap:10px;padding-bottom:12px;margin-bottom:16px;border-bottom:1px solid #e5e7eb;">
				<span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:#1e3a5f;border-radius:8px;font-size:13px;color:#fff;font-weight:700;"><?php echo esc_html( $badge ); ?></span>
				<div>
					<div style="font-weight:600;font-size:14px;color:#111827;"><?php echo esc_html( $title ); ?></div>
					<div style="font-size:12px;color:#6b7280;"><?php esc_html_e( 'Title & Meta IA, Audit SEO, FAQ Schema', 'alesta' ); ?></div>
				</div>
				<?php if ( $has_key ) : ?>
				<span style="margin-left:auto;font-size:12px;padding:3px 10px;border-radius:20px;background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;"><?php esc_html_e( 'Clé enregistrée', 'alesta' ); ?></span>
				<?php endif; ?>
			</div>

			<div style="margin-bottom:16px;">
				<label for="alesta-key-<?php echo esc_attr( $provider ); ?>" style="display:block;font-weight:500;margin-bottom:6px;color:#374151;"><?php echo esc_html( $label ); ?></label>
				<div style="display:flex;gap:8px;">
					<input type="password" id="alesta-key-<?php echo esc_attr( $provider ); ?>" class="regular-text alesta-key-input" style="flex:1;"
						value="" autocomplete="new-password" spellcheck="false"
						placeholder="<?php echo esc_attr( $placeholder ); ?>">
					<button type="button" class="button alesta-toggle-key" data-target="alesta-key-<?php echo esc_attr( $provider ); ?>" title="<?php esc_attr_e( 'Afficher / masquer', 'alesta' ); ?>">
						<span class="dashicons dashicons-visibility" style="vertical-align:middle;"></span>
					</button>
				</div>
				<p class="description" style="margin-top:6px;">
					<?php
					printf(
						/* translators: %s: link to the provider API keys page */
						esc_html__( 'Obtenez une clé sur %s (compte requis, facturation à l\'usage).', 'alesta' ),
						'<a href="' . esc_url( $console_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $console_lbl ) . '</a>'
					);
					?>
					<?php if ( $has_key ) : ?>
						<br><strong style="color:#065f46;"><?php esc_html_e( 'Clé déjà configurée et chiffrée (AES-256-GCM).', 'alesta' ); ?></strong>
						<?php esc_html_e( 'Laissez vide pour la conserver, ou collez une nouvelle clé pour la remplacer.', 'alesta' ); ?>
					<?php else : ?>
						<br><?php esc_html_e( 'La clé est chiffrée dans la base de données (AES-256-GCM avec AUTH_KEY), jamais stockée en clair.', 'alesta' ); ?>
					<?php endif; ?>
				</p>
			</div>

			<div style="margin-bottom:16px;">
				<label for="alesta-model-<?php echo esc_attr( $provider ); ?>" style="display:block;font-weight:500;margin-bottom:6px;color:#374151;"><?php esc_html_e( 'Modèle', 'alesta' ); ?></label>
				<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
					<?php if ( ! empty( $models ) ) : ?>
						<select id="alesta-model-<?php echo esc_attr( $provider ); ?>" class="alesta-model-input" data-provider="<?php echo esc_attr( $provider ); ?>" style="flex:1;min-width:260px;max-width:420px;">
							<?php foreach ( $models as $id => $model_label ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $model, $id ); ?>><?php echo esc_html( $model_label ); ?></option>
							<?php endforeach; ?>
							<?php if ( '' !== $model && ! array_key_exists( $model, $models ) ) : ?>
								<option value="<?php echo esc_attr( $model ); ?>" selected><?php echo esc_html( $model ); ?></option>
							<?php endif; ?>
						</select>
					<?php else : ?>
						<input type="text" id="alesta-model-<?php echo esc_attr( $provider ); ?>" class="regular-text alesta-model-input" data-provider="<?php echo esc_attr( $provider ); ?>" style="flex:1;min-width:260px;max-width:420px;"
							value="<?php echo esc_attr( $model ); ?>" spellcheck="false"
							placeholder="<?php esc_attr_e( 'Identifiant de modèle', 'alesta' ); ?>">
					<?php endif; ?>
					<button type="button" class="button alesta-btn-refresh-models" data-provider="<?php echo esc_attr( $provider ); ?>"><?php esc_html_e( 'Rafraîchir la liste', 'alesta' ); ?></button>
				</div>
				<p class="description" style="margin-top:6px;">
					<?php if ( ! empty( $models ) ) : ?>
						<?php esc_html_e( 'Liste récupérée auprès du fournisseur et mise en cache 24 h. « Rafraîchir la liste » interroge son API avec la clé ci-dessus.', 'alesta' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Aucune liste en cache : saisissez l\'identifiant exact du modèle, ou cliquez sur « Rafraîchir la liste » après avoir enregistré votre clé.', 'alesta' ); ?>
					<?php endif; ?>
				</p>
			</div>

			<?php if ( $has_key ) : ?>
			<button type="button" class="button alesta-btn-delete" data-provider="<?php echo esc_attr( $provider ); ?>" style="color:#991b1b;border-color:#fca5a5;"><?php esc_html_e( 'Supprimer cette clé', 'alesta' ); ?></button>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Rend la page « Configuration ».
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'alesta' ) );
		}

		$provider    = Alesta_API::get_provider();
		$providers   = Alesta_API::get_providers();
		$has_key     = Alesta_API::has_stored_key( $provider );
		$can_encrypt = Alesta_Key_Vault::can_encrypt();
		?>
		<div class="wrap alesta-wrap" id="alesta-settings-wrap">

			<!-- En-tête -->
			<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:20px 24px;background:#1e3a5f;border-radius:8px;margin-bottom:20px;">
				<div style="display:flex;align-items:center;gap:12px;">
					<span class="dashicons dashicons-admin-settings" style="font-size:28px;width:28px;height:28px;color:#a0aec0;"></span>
					<div>
						<h1 style="color:#fff;margin:0;font-size:18px;"><?php esc_html_e( 'Configuration', 'alesta' ); ?></h1>
						<p style="color:#94a3b8;margin:0;font-size:13px;"><?php esc_html_e( 'Connexion à l\'API du fournisseur IA utilisé par les modules IA', 'alesta' ); ?></p>
					</div>
				</div>
				<span id="alesta-claude-badge" data-connected="<?php echo $has_key ? '1' : '0'; ?>" style="font-size:12px;padding:4px 12px;border-radius:20px;font-weight:500;<?php echo $has_key ? 'background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;' : 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;'; ?>">
					<?php echo $has_key ? '&#10003; ' . esc_html__( 'IA connectée', 'alesta' ) : '&#10007; ' . esc_html__( 'IA non configurée', 'alesta' ); ?>
				</span>
			</div>

			<div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:20px;align-items:start;">

				<!-- Colonne gauche : fournisseur + clés -->
				<div>
					<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-bottom:16px;">
						<label for="alesta-provider" style="display:block;font-weight:500;margin-bottom:6px;color:#374151;"><?php esc_html_e( 'Fournisseur IA', 'alesta' ); ?></label>
						<select id="alesta-provider" style="width:100%;max-width:420px;">
							<?php foreach ( $providers as $id => $label ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $provider, $id ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description" style="margin-top:6px;"><?php esc_html_e( 'Les modules IA utilisent le fournisseur sélectionné. Les deux clés sont conservées : vous pouvez basculer sans les ressaisir.', 'alesta' ); ?></p>
					</div>

					<?php if ( ! $can_encrypt ) : ?>
					<div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;padding:10px 12px;margin-bottom:16px;font-size:12px;color:#92400e;">
						<?php esc_html_e( 'Chiffrement indisponible : AUTH_KEY est absent ou trop court dans wp-config.php, ou OpenSSL ne supporte pas AES-256-GCM. La clé ne pourra pas être enregistrée de manière sécurisée.', 'alesta' ); ?>
					</div>
					<?php endif; ?>

					<?php
					foreach ( array_keys( $providers ) as $id ) {
						$this->render_provider_block( (string) $id );
					}
					?>

					<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;">
						<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
							<button type="button" id="alesta-btn-save" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'alesta' ); ?></button>
							<button type="button" id="alesta-btn-test" class="button"><?php esc_html_e( 'Tester la clé', 'alesta' ); ?></button>
						</div>
						<div id="alesta-settings-feedback" style="display:none;margin-top:14px;padding:10px 12px;border-radius:6px;font-size:13px;"></div>
					</div>
				</div>

				<!-- Colonne droite : confidentialité + budget -->
				<div>
					<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-bottom:16px;">
						<h3 style="margin:0 0 10px;font-size:14px;color:#111827;">
							<span class="dashicons dashicons-privacy" style="color:#6b7280;vertical-align:middle;margin-right:4px;"></span>
							<?php esc_html_e( 'Confidentialité', 'alesta' ); ?>
						</h3>
						<p style="font-size:13px;color:#374151;margin:0 0 8px;">
							<?php esc_html_e( 'Lorsque vous utilisez un module IA, le contenu concerné (titres, extraits d\'articles, pages) est envoyé à l\'API du fournisseur que vous avez sélectionné — Anthropic ou OpenAI. Aucune donnée ne transite par les serveurs d\'Alesta.', 'alesta' ); ?>
						</p>
						<p style="font-size:13px;color:#374151;margin:0 0 8px;">
							<?php esc_html_e( 'Vous restez responsable de l\'usage de votre clé et de sa facturation. N\'utilisez pas les modules IA sur des contenus que vous ne souhaitez pas transmettre à un tiers.', 'alesta' ); ?>
						</p>
						<p style="font-size:12px;margin:0 0 4px;">
							<a href="<?php echo esc_url( 'https://www.anthropic.com/legal/consumer-terms' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Conditions d\'Anthropic', 'alesta' ); ?></a>
							&nbsp;·&nbsp;
							<a href="<?php echo esc_url( 'https://www.anthropic.com/legal/privacy' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Confidentialité d\'Anthropic', 'alesta' ); ?></a>
						</p>
						<p style="font-size:12px;margin:0;">
							<a href="<?php echo esc_url( 'https://openai.com/policies/row-terms-of-use' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Conditions d\'OpenAI', 'alesta' ); ?></a>
							&nbsp;·&nbsp;
							<a href="<?php echo esc_url( 'https://openai.com/policies/row-privacy-policy' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Confidentialité d\'OpenAI', 'alesta' ); ?></a>
						</p>
					</div>

					<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;">
						<h3 style="margin:0 0 10px;font-size:14px;color:#111827;">
							<span class="dashicons dashicons-chart-area" style="color:#6b7280;vertical-align:middle;margin-right:4px;"></span>
							<?php esc_html_e( 'Maîtrise des coûts', 'alesta' ); ?>
						</h3>
						<p style="font-size:13px;color:#374151;margin:0 0 10px;">
							<?php esc_html_e( 'Chaque appel est comptabilisé (tokens, coût estimé). Définissez une limite mensuelle et une alerte email depuis la page Budget.', 'alesta' ); ?>
						</p>
						<p style="font-size:12px;color:#6b7280;margin:0 0 10px;">
							<?php esc_html_e( 'Le coût n\'est estimé que pour les modèles dont le tarif est connu du plugin. Pour les autres, les tokens sont comptés mais le coût affiché reste à 0 (« non estimé »).', 'alesta' ); ?>
						</p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=alesta-ai-budget' ) ); ?>" class="button"><?php esc_html_e( 'Ouvrir le Budget API', 'alesta' ); ?></a>
					</div>
				</div>

			</div>
		</div>
		<?php
	}
}
