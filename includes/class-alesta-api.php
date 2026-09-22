<?php
/**
 * Alesta — Client API des fournisseurs IA (Anthropic Claude / OpenAI).
 *
 * Port Free de Alesta_AI_API (Pro). Même contrat public : le constructeur
 * lit la clé (Vault chiffré) et le modèle, ask() envoie un prompt et
 * retourne le texte, et les helpers statiques d'usage / budget alimentent
 * la page « Budget » du Free (Alesta_Admin_Budget).
 *
 * Différences avec la Pro :
 *   - les statistiques sont écrites dans les options lues par
 *     Alesta_Admin_Budget (alesta_token_usage, alesta_token_usage_monthly,
 *     alesta_token_usage_daily) et les réglages dans alesta_budget_settings ;
 *   - la clé API et le modèle utilisent EXACTEMENT les mêmes options que la
 *     Pro (alesta_ai_api_key_enc via Alesta_Key_Vault, alesta_ai_model) pour
 *     qu'un passage Free -> Pro conserve la configuration ;
 *   - pas de rate-limit ni de journal d'audit (Pro uniquement).
 *
 * Depuis la 1.9.0 le client sait dialoguer avec deux fournisseurs, au choix
 * de l'utilisateur (option alesta_ai_provider) : Anthropic (défaut, comportement
 * historique strictement inchangé) ou OpenAI. Le reste du plugin ne connaît
 * que ask() : le texte retourné et le tracking de tokens sont normalisés.
 *
 * @package Alesta
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alesta_API {

	/** Option WP (partagée avec la Pro) qui stocke le modèle Anthropic choisi. */
	const MODEL_OPTION = 'alesta_ai_model';

	/** Modèle Anthropic par défaut — identique à la Pro. */
	const DEFAULT_MODEL = 'claude-sonnet-4-5';

	/** Option WP qui stocke le fournisseur IA actif. */
	const PROVIDER_OPTION = 'alesta_ai_provider';

	/** Identifiant du fournisseur Anthropic (valeur par défaut). */
	const PROVIDER_ANTHROPIC = 'anthropic';

	/** Identifiant du fournisseur OpenAI (ChatGPT). */
	const PROVIDER_OPENAI = 'openai';

	/** Option WP qui stocke le modèle OpenAI choisi. */
	const OPENAI_MODEL_OPTION = 'alesta_ai_openai_model';

	/** Slug de la page « Configuration » (identique à la Pro). */
	const SETTINGS_PAGE = 'alesta-ai-settings';

	/** Préfixe des transients de liste de modèles (24 h). */
	const MODELS_TRANSIENT_PREFIX = 'alesta_models_';

	/** Options lues par Alesta_Admin_Budget (module Budget du Free). */
	const USAGE_OPTION   = 'alesta_token_usage';
	const MONTHLY_OPTION = 'alesta_token_usage_monthly';
	const DAILY_OPTION   = 'alesta_token_usage_daily';
	const BUDGET_OPTION  = 'alesta_budget_settings';

	/** Version d'API Anthropic envoyée dans l'en-tête. */
	const ANTHROPIC_VERSION = '2023-06-01';

	/** Endpoint « messages » d'Anthropic. */
	const ANTHROPIC_ENDPOINT = 'https://api.anthropic.com/v1/messages'; // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration

	/** Endpoint « models » d'Anthropic. */
	const ANTHROPIC_MODELS_ENDPOINT = 'https://api.anthropic.com/v1/models'; // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration

	/** Endpoint « chat completions » d'OpenAI. */
	const OPENAI_ENDPOINT = 'https://api.openai.com/v1/chat/completions'; // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration

	/** Endpoint « models » d'OpenAI. */
	const OPENAI_MODELS_ENDPOINT = 'https://api.openai.com/v1/models'; // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration

	/** @var string */
	private $api_key;

	/** @var string */
	private $model;

	/** @var string Fournisseur actif pour cette instance. */
	private $provider;

	/**
	 * Tarifs en $ par million de tokens (input / output) — même table que la Pro.
	 *
	 * @var array<string, array{input: float, output: float}>
	 */
	private static $default_pricing = array(
		'claude-opus-4'              => array( 'input' => 15.00, 'output' => 75.00 ),
		'claude-sonnet-4-5'          => array( 'input' => 3.00,  'output' => 15.00 ),
		'claude-3-5-sonnet-20241022' => array( 'input' => 3.00,  'output' => 15.00 ),
		'claude-3-5-haiku-20241022'  => array( 'input' => 0.80,  'output' => 4.00 ),
		'claude-haiku-4-5'           => array( 'input' => 0.80,  'output' => 4.00 ),
		'claude-3-haiku-20240307'    => array( 'input' => 0.25,  'output' => 1.25 ),
		'claude-3-opus-20240229'     => array( 'input' => 15.00, 'output' => 75.00 ),
	);

	/**
	 * Constructeur.
	 *
	 * Sans argument (contrat Pro) : lit le fournisseur actif, sa clé via
	 * Alesta_Key_Vault et son modèle. Les arguments optionnels servent
	 * uniquement au bouton « Tester la clé » (test AVANT enregistrement).
	 *
	 * @param string|null $api_key  Clé à utiliser à la place de celle stockée.
	 * @param string|null $model    Modèle à utiliser à la place de celui stocké.
	 * @param string|null $provider Fournisseur à utiliser à la place de celui stocké.
	 */
	public function __construct( $api_key = null, $model = null, $provider = null ) {
		$this->provider = is_string( $provider ) && '' !== trim( $provider )
			? self::normalize_provider( $provider )
			: self::get_provider();

		if ( is_string( $api_key ) && trim( $api_key ) !== '' ) {
			$this->api_key = trim( $api_key );
		} elseif ( class_exists( 'Alesta_Key_Vault' ) ) {
			$this->api_key = (string) ( Alesta_Key_Vault::get( self::key_slot( $this->provider ) ) ?? '' );
		} elseif ( self::PROVIDER_ANTHROPIC === $this->provider ) {
			$this->api_key = (string) get_option( 'alesta_ai_api_key', '' );
		} else {
			$this->api_key = '';
		}

		if ( is_string( $model ) && trim( $model ) !== '' ) {
			$this->model = trim( $model );
		} else {
			$this->model = self::get_model( $this->provider );
		}
	}

	// =========================================================================
	// FOURNISSEURS
	// =========================================================================

	/**
	 * Normalise un identifiant de fournisseur (inconnu => Anthropic).
	 *
	 * @param string $provider Identifiant brut.
	 * @return string
	 */
	public static function normalize_provider( string $provider ): string {
		return ( self::PROVIDER_OPENAI === $provider ) ? self::PROVIDER_OPENAI : self::PROVIDER_ANTHROPIC;
	}

	/**
	 * Fournisseur IA actuellement configuré.
	 *
	 * @return string
	 */
	public static function get_provider(): string {
		return self::normalize_provider( (string) get_option( self::PROVIDER_OPTION, self::PROVIDER_ANTHROPIC ) );
	}

	/**
	 * Libellés des fournisseurs pour l'interface.
	 *
	 * @return array<string, string> id => libellé
	 */
	public static function get_providers(): array {
		return array(
			self::PROVIDER_ANTHROPIC => __( 'Anthropic (Claude)', 'alesta' ),
			self::PROVIDER_OPENAI    => __( 'OpenAI (ChatGPT)', 'alesta' ),
		);
	}

	/**
	 * Fournisseur de cette instance.
	 *
	 * @return string
	 */
	public function get_instance_provider(): string {
		return $this->provider;
	}

	/**
	 * Slot du coffre-fort correspondant à un fournisseur.
	 *
	 * @param string $provider Fournisseur.
	 * @return string
	 */
	public static function key_slot( string $provider ): string {
		return ( self::PROVIDER_OPENAI === self::normalize_provider( $provider ) ) ? 'openai' : 'anthropic';
	}

	/**
	 * Indique si une clé est enregistrée pour un fournisseur donné
	 * (le fournisseur actif par défaut).
	 *
	 * @param string|null $provider Fournisseur, ou null pour l'actif.
	 * @return bool
	 */
	public static function has_stored_key( $provider = null ): bool {
		$provider = is_string( $provider ) && '' !== $provider ? self::normalize_provider( $provider ) : self::get_provider();
		if ( class_exists( 'Alesta_Key_Vault' ) ) {
			return Alesta_Key_Vault::has_key( self::key_slot( $provider ) );
		}
		return self::PROVIDER_ANTHROPIC === $provider && '' !== (string) get_option( 'alesta_ai_api_key', '' );
	}

	/**
	 * URL de la page « Configuration » (saisie de la clé API).
	 *
	 * @return string
	 */
	public static function settings_url(): string {
		return admin_url( 'admin.php?page=' . self::SETTINGS_PAGE );
	}

	/**
	 * Erreur standard « aucune clé API configurée », actionnable : elle nomme
	 * le chemin exact et transporte l'URL de la page Configuration.
	 *
	 * @return WP_Error
	 */
	public static function missing_key_error(): WP_Error {
		return new WP_Error(
			'no_api_key',
			sprintf(
				/* translators: %s: URL of the Alesta AI Configuration page */
				__( 'Aucune clé API n\'est configurée pour le fournisseur IA sélectionné. Saisissez-la dans Alesta AI &rarr; Configuration : %s', 'alesta' ),
				self::settings_url()
			),
			array( 'settings_url' => self::settings_url() )
		);
	}

	/**
	 * Erreur standard « aucun modèle choisi » (OpenAI sans modèle enregistré).
	 *
	 * @return WP_Error
	 */
	public static function missing_model_error(): WP_Error {
		return new WP_Error(
			'no_model',
			sprintf(
				/* translators: %s: URL of the Alesta AI Configuration page */
				__( 'Aucun modèle n\'est sélectionné pour ce fournisseur IA. Choisissez-en un dans Alesta AI &rarr; Configuration : %s', 'alesta' ),
				self::settings_url()
			),
			array( 'settings_url' => self::settings_url() )
		);
	}

	/**
	 * Convertit un WP_Error en charge utile AJAX exploitable par le JS :
	 * message + code + (si la configuration est en cause) settings_url.
	 *
	 * @param WP_Error|string $error Erreur ou message brut.
	 * @return array<string, string>
	 */
	public static function error_payload( $error ): array {
		if ( ! is_wp_error( $error ) ) {
			return array( 'message' => (string) $error );
		}

		$code    = (string) $error->get_error_code();
		$payload = array(
			'message' => $error->get_error_message(),
			'code'    => $code,
		);

		if ( in_array( $code, array( 'no_api_key', 'no_model' ), true ) ) {
			$payload['settings_url'] = self::settings_url();
		}

		return $payload;
	}

	/**
	 * Charge le petit script commun qui ajoute un bouton « Configurer la clé
	 * API » sous les erreurs de configuration renvoyées par l'AJAX.
	 * Idempotent : appelable depuis chaque module IA.
	 */
	public static function enqueue_key_notice(): void {
		if ( wp_script_is( 'alesta-key-notice', 'enqueued' ) || wp_script_is( 'alesta-key-notice', 'registered' ) ) {
			wp_enqueue_script( 'alesta-key-notice' );
			return;
		}

		wp_enqueue_script(
			'alesta-key-notice',
			plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/api-key-notice.js',
			array(),
			ALESTA_VERSION,
			true
		);
		wp_localize_script(
			'alesta-key-notice',
			'AlestaKeyNoticeCfg',
			array(
				'settings_url' => self::settings_url(),
				'configure'    => __( 'Configurer la clé API', 'alesta' ),
				'open_now'     => __( 'Ouvrir la page Configuration maintenant ?', 'alesta' ),
			)
		);
	}

	// =========================================================================
	// MODÈLES
	// =========================================================================

	/**
	 * Modèle actuellement configuré pour un fournisseur
	 * (option alesta_ai_model pour Anthropic, partagée avec la Pro).
	 *
	 * @param string|null $provider Fournisseur, ou null pour l'actif.
	 * @return string Chaîne vide possible côté OpenAI (aucun modèle choisi).
	 */
	public static function get_model( $provider = null ): string {
		$provider = is_string( $provider ) && '' !== $provider ? self::normalize_provider( $provider ) : self::get_provider();

		if ( self::PROVIDER_OPENAI === $provider ) {
			return trim( (string) get_option( self::OPENAI_MODEL_OPTION, '' ) );
		}

		$model = (string) get_option( self::MODEL_OPTION, self::DEFAULT_MODEL );
		return $model !== '' ? $model : self::DEFAULT_MODEL;
	}

	/**
	 * Liste des modèles proposés dans la page Configuration.
	 *
	 * Source : le cache des modèles réellement exposés par l'API du
	 * fournisseur (transient 24 h). À défaut, courte liste de repli côté
	 * Anthropic ; côté OpenAI la liste peut être vide (champ texte libre).
	 *
	 * @param string|null $provider Fournisseur, ou null pour l'actif.
	 * @return array<string, string> id => libellé
	 */
	public static function get_models( $provider = null ): array {
		$provider = is_string( $provider ) && '' !== $provider ? self::normalize_provider( $provider ) : self::get_provider();

		$cached = get_transient( self::MODELS_TRANSIENT_PREFIX . $provider );
		$ids    = is_array( $cached ) ? $cached : array();

		if ( empty( $ids ) && self::PROVIDER_ANTHROPIC === $provider ) {
			$ids = self::anthropic_fallback_models();
		}

		$models = array();
		foreach ( $ids as $id ) {
			$id = (string) $id;
			if ( '' !== $id ) {
				$models[ $id ] = $id;
			}
		}

		/**
		 * Filtre la liste des modèles proposés pour un fournisseur.
		 *
		 * @param array<string, string> $models   id => libellé.
		 * @param string                $provider Fournisseur concerné.
		 */
		return (array) apply_filters( 'alesta_ai_models', $models, $provider );
	}

	/**
	 * Liste de repli Anthropic (utilisée tant que l'appel /v1/models n'a pas
	 * abouti). Le modèle par défaut y figure toujours pour ne jamais casser
	 * un site déjà configuré.
	 *
	 * @return string[]
	 */
	private static function anthropic_fallback_models(): array {
		return array_values(
			array_unique(
				array(
					'claude-opus-5',
					'claude-sonnet-5',
					'claude-haiku-4-5',
					self::DEFAULT_MODEL,
				)
			)
		);
	}

	/**
	 * Interroge l'API du fournisseur pour lister ses modèles et met le
	 * résultat en cache 24 h.
	 *
	 * @param string $provider Fournisseur.
	 * @param string $api_key  Clé à utiliser (vide = clé stockée du fournisseur).
	 * @param bool   $force    True pour ignorer le cache.
	 * @return string[]|WP_Error Liste d'identifiants de modèles.
	 */
	public static function fetch_models( string $provider, string $api_key = '', bool $force = false ) {
		$provider  = self::normalize_provider( $provider );
		$transient = self::MODELS_TRANSIENT_PREFIX . $provider;

		if ( ! $force ) {
			$cached = get_transient( $transient );
			if ( is_array( $cached ) && ! empty( $cached ) ) {
				return $cached;
			}
		}

		$api_key = trim( $api_key );
		if ( '' === $api_key && class_exists( 'Alesta_Key_Vault' ) ) {
			$api_key = (string) ( Alesta_Key_Vault::get( self::key_slot( $provider ) ) ?? '' );
		}
		if ( '' === $api_key ) {
			return self::missing_key_error();
		}

		if ( self::PROVIDER_OPENAI === $provider ) {
			$url     = self::OPENAI_MODELS_ENDPOINT;
			$headers = array( 'Authorization' => 'Bearer ' . $api_key );
		} else {
			$url     = self::ANTHROPIC_MODELS_ENDPOINT . '?limit=100';
			$headers = array(
				'x-api-key'         => $api_key,
				'anthropic-version' => self::ANTHROPIC_VERSION,
			);
		}

		// Lecture de la liste des modèles disponibles pour la clé de l'utilisateur.
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		if ( 200 !== $code ) {
			return new WP_Error( 'api_error', self::extract_error_message( $body, $code ), array( 'status' => $code ) );
		}

		$ids = array();
		foreach ( (array) ( $body['data'] ?? array() ) as $entry ) {
			$id = is_array( $entry ) ? (string) ( $entry['id'] ?? '' ) : '';
			if ( '' === $id ) {
				continue;
			}
			if ( self::PROVIDER_OPENAI === $provider && ! self::openai_model_is_chat( $id ) ) {
				continue;
			}
			$ids[] = $id;
		}

		$ids = array_values( array_unique( $ids ) );
		sort( $ids );

		if ( empty( $ids ) ) {
			return new WP_Error( 'no_models', __( 'Le fournisseur n\'a retourné aucun modèle utilisable.', 'alesta' ) );
		}

		set_transient( $transient, $ids, DAY_IN_SECONDS );

		return $ids;
	}

	/**
	 * Écarte les modèles OpenAI qui ne répondent pas sur /chat/completions
	 * (audio, images, embeddings, modération…). Aucun identifiant n'est
	 * inventé : on filtre seulement ce que l'API a listé.
	 *
	 * @param string $id Identifiant de modèle.
	 * @return bool
	 */
	private static function openai_model_is_chat( string $id ): bool {
		$excluded = array( 'embedding', 'whisper', 'tts', 'dall-e', 'moderation', 'audio', 'image', 'transcribe', 'realtime', 'sora', 'codex', 'davinci', 'babbage' );
		foreach ( $excluded as $needle ) {
			if ( false !== strpos( $id, $needle ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Indique si une clé API est disponible pour cette instance.
	 *
	 * @return bool
	 */
	public function has_key(): bool {
		return $this->api_key !== '';
	}

	/**
	 * Envoie un message au fournisseur IA actif et retourne la réponse texte.
	 * Tracke automatiquement les tokens consommés.
	 *
	 * @param string $prompt     Prompt utilisateur.
	 * @param int    $max_tokens Nombre maximum de tokens en sortie.
	 * @return string|WP_Error
	 */
	public function ask( string $prompt, int $max_tokens = 1024 ) {
		if ( empty( $this->api_key ) ) {
			return self::missing_key_error();
		}

		if ( '' === $this->model ) {
			return self::missing_model_error();
		}

		// Blocage si le budget mensuel est atteint (même logique que la Pro).
		$budget = self::get_budget_settings();
		if ( $budget['monthly_limit'] > 0 && $budget['block_on_limit'] ) {
			$monthly    = get_option( self::MONTHLY_OPTION, array() );
			$month      = gmdate( 'Y-m' );
			$month_cost = is_array( $monthly ) ? (float) ( $monthly[ $month ]['cost'] ?? 0.0 ) : 0.0;
			if ( $month_cost >= $budget['monthly_limit'] ) {
				return new WP_Error(
					'budget_exceeded',
					sprintf(
						/* translators: 1: amount spent in USD, 2: monthly limit in USD */
						__( 'Budget API mensuel atteint (%1$.4f$ / %2$.2f$). Augmentez votre limite dans Alesta > Budget.', 'alesta' ),
						$month_cost,
						$budget['monthly_limit']
					)
				);
			}
		}

		$max_tokens = max( 1, $max_tokens );

		return ( self::PROVIDER_OPENAI === $this->provider )
			? $this->ask_openai( $prompt, $max_tokens )
			: $this->ask_anthropic( $prompt, $max_tokens );
	}

	/**
	 * Appel Anthropic /v1/messages (comportement historique).
	 *
	 * @param string $prompt     Prompt utilisateur.
	 * @param int    $max_tokens Nombre maximum de tokens en sortie.
	 * @return string|WP_Error
	 */
	private function ask_anthropic( string $prompt, int $max_tokens ) {
		// Appel direct à l'API du fournisseur : l'administrateur a explicitement
		// saisi SA propre clé (BYOK) et déclenché l'action depuis l'admin.
		$response = wp_remote_post(
			self::ANTHROPIC_ENDPOINT,
			array(
				'timeout' => 60,
				'headers' => array(
					'x-api-key'         => $this->api_key,
					'anthropic-version' => self::ANTHROPIC_VERSION,
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $this->model,
						'max_tokens' => $max_tokens,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		if ( 200 !== $code ) {
			return new WP_Error( 'api_error', self::extract_error_message( $body, $code ), array( 'status' => $code ) );
		}

		if ( ! empty( $body['usage'] ) && is_array( $body['usage'] ) ) {
			$this->track_usage(
				(int) ( $body['usage']['input_tokens'] ?? 0 ),
				(int) ( $body['usage']['output_tokens'] ?? 0 ),
				isset( $body['model'] ) && is_string( $body['model'] ) ? $body['model'] : $this->model
			);
		}

		return isset( $body['content'][0]['text'] ) && is_string( $body['content'][0]['text'] )
			? $body['content'][0]['text']
			: '';
	}

	/**
	 * Appel OpenAI /v1/chat/completions.
	 *
	 * Les modèles récents refusent « max_tokens » et exigent
	 * « max_completion_tokens », les plus anciens font l'inverse : on tente
	 * le paramètre moderne puis, sur un 400 explicite, on rejoue UNE fois
	 * avec l'autre nom.
	 *
	 * @param string $prompt     Prompt utilisateur.
	 * @param int    $max_tokens Nombre maximum de tokens en sortie.
	 * @return string|WP_Error
	 */
	private function ask_openai( string $prompt, int $max_tokens ) {
		$result = $this->openai_request( $prompt, $max_tokens, 'max_completion_tokens' );

		if ( is_wp_error( $result ) && 'api_error' === $result->get_error_code() ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
			if ( 400 === $status && preg_match( '/max_(completion_)?tokens/i', $result->get_error_message() ) ) {
				$result = $this->openai_request( $prompt, $max_tokens, 'max_tokens' );
			}
		}

		return $result;
	}

	/**
	 * Un appel OpenAI avec le nom de paramètre de longueur demandé.
	 *
	 * @param string $prompt      Prompt utilisateur.
	 * @param int    $max_tokens  Nombre maximum de tokens en sortie.
	 * @param string $tokens_key  'max_completion_tokens' ou 'max_tokens'.
	 * @return string|WP_Error
	 */
	private function openai_request( string $prompt, int $max_tokens, string $tokens_key ) {
		$payload = array(
			'model'    => $this->model,
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);
		$payload[ $tokens_key ] = $max_tokens;

		// Appel direct à l'API du fournisseur : l'administrateur a explicitement
		// saisi SA propre clé (BYOK) et déclenché l'action depuis l'admin.
		$response = wp_remote_post(
			self::OPENAI_ENDPOINT,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'content-type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		if ( 200 !== $code ) {
			return new WP_Error( 'api_error', self::extract_error_message( $body, $code ), array( 'status' => $code ) );
		}

		if ( ! empty( $body['usage'] ) && is_array( $body['usage'] ) ) {
			$this->track_usage(
				(int) ( $body['usage']['prompt_tokens'] ?? 0 ),
				(int) ( $body['usage']['completion_tokens'] ?? 0 ),
				isset( $body['model'] ) && is_string( $body['model'] ) ? $body['model'] : $this->model
			);
		}

		return isset( $body['choices'][0]['message']['content'] ) && is_string( $body['choices'][0]['message']['content'] )
			? $body['choices'][0]['message']['content']
			: '';
	}

	/**
	 * Extrait le message d'erreur d'une réponse JSON (format identique chez
	 * Anthropic et OpenAI : error.message).
	 *
	 * @param array $body Corps décodé.
	 * @param int   $code Code HTTP.
	 * @return string
	 */
	private static function extract_error_message( array $body, int $code ): string {
		if ( isset( $body['error']['message'] ) && is_string( $body['error']['message'] ) ) {
			return $body['error']['message'];
		}
		return sprintf(
			/* translators: %d: HTTP status code */
			__( 'Erreur API inconnue (HTTP %d).', 'alesta' ),
			$code
		);
	}

	/**
	 * Teste la connexion API avec un mini appel.
	 *
	 * @return true|WP_Error
	 */
	public function test_connection() {
		$result = $this->ask( 'Réponds uniquement "OK" en un seul mot.', 10 );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return true;
	}

	// =========================================================================
	// TRACKING — écrit dans les options lues par Alesta_Admin_Budget.
	// =========================================================================

	/**
	 * Accumule les tokens dans les options WordPress (global, mensuel, journalier).
	 *
	 * @param int    $input  Tokens entrants.
	 * @param int    $output Tokens sortants.
	 * @param string $model  Modèle réellement utilisé (retourné par l'API).
	 */
	private function track_usage( int $input, int $output, string $model ): void {
		$now = current_time( 'mysql' );

		// ── Global ──
		$usage = get_option( self::USAGE_OPTION, array() );
		if ( ! is_array( $usage ) ) {
			$usage = array();
		}
		$usage['total_input']  = (int) ( $usage['total_input'] ?? 0 ) + $input;
		$usage['total_output'] = (int) ( $usage['total_output'] ?? 0 ) + $output;
		// Alias lus par default_usage_stats() du module Budget.
		$usage['input_tokens']  = $usage['total_input'];
		$usage['output_tokens'] = $usage['total_output'];
		$usage['calls']         = (int) ( $usage['calls'] ?? 0 ) + 1;
		$usage['last_model']    = $model;
		$usage['last_call']     = $now;
		if ( empty( $usage['since'] ) ) {
			$usage['since'] = $now;
		}
		$usage['total_cost_usd'] = (float) ( $usage['total_cost_usd'] ?? 0.0 ) + self::compute_cost( $input, $output, $model );
		update_option( self::USAGE_OPTION, $usage, false );

		// ── Mensuel ──
		$month   = gmdate( 'Y-m' );
		$monthly = get_option( self::MONTHLY_OPTION, array() );
		if ( ! is_array( $monthly ) ) {
			$monthly = array();
		}
		if ( ! isset( $monthly[ $month ] ) || ! is_array( $monthly[ $month ] ) ) {
			$monthly[ $month ] = array();
		}
		$monthly[ $month ]['input']  = (int) ( $monthly[ $month ]['input'] ?? 0 ) + $input;
		$monthly[ $month ]['output'] = (int) ( $monthly[ $month ]['output'] ?? 0 ) + $output;
		$monthly[ $month ]['calls']  = (int) ( $monthly[ $month ]['calls'] ?? 0 ) + 1;
		$monthly[ $month ]['cost']   = (float) ( $monthly[ $month ]['cost'] ?? 0.0 ) + self::compute_cost( $input, $output, $model );

		// Alerte email si le seuil est atteint.
		$budget = self::get_budget_settings();
		if ( $budget['monthly_limit'] > 0 && ! empty( $budget['alert_email'] ) ) {
			$limit     = $budget['monthly_limit'];
			$threshold = $budget['alert_threshold'] / 100;
			$cost_now  = (float) $monthly[ $month ]['cost'];
			$sent_at   = (float) ( $monthly[ $month ]['alert_sent'] ?? 0 );
			if ( $cost_now >= $limit * $threshold && $sent_at < $limit * $threshold ) {
				$monthly[ $month ]['alert_sent'] = $cost_now;
				wp_mail(
					$budget['alert_email'],
					__( '[Alesta] Seuil de budget API atteint', 'alesta' ),
					sprintf(
						/* translators: 1: threshold percentage, 2: amount spent in USD, 3: monthly limit in USD */
						__( "Bonjour,\n\nVotre budget API mensuel Alesta a atteint %1\$d%% de la limite fixée.\n\nDépenses : %2\$.4f\$ sur %3\$.2f\$ autorisés.\n\nGérez votre budget depuis Alesta > Budget dans votre administration WordPress.", 'alesta' ),
						$budget['alert_threshold'],
						$cost_now,
						$limit
					)
				);
			}
		}
		update_option( self::MONTHLY_OPTION, $monthly, false );

		// ── Journalier (90 jours max) ──
		$day   = gmdate( 'Y-m-d' );
		$daily = get_option( self::DAILY_OPTION, array() );
		if ( ! is_array( $daily ) ) {
			$daily = array();
		}
		if ( count( $daily ) > 90 ) {
			ksort( $daily );
			$daily = array_slice( $daily, -90, null, true );
		}
		if ( ! isset( $daily[ $day ] ) || ! is_array( $daily[ $day ] ) ) {
			$daily[ $day ] = array();
		}
		$daily[ $day ]['input']  = (int) ( $daily[ $day ]['input'] ?? 0 ) + $input;
		$daily[ $day ]['output'] = (int) ( $daily[ $day ]['output'] ?? 0 ) + $output;
		$daily[ $day ]['calls']  = (int) ( $daily[ $day ]['calls'] ?? 0 ) + 1;
		$daily[ $day ]['cost']   = (float) ( $daily[ $day ]['cost'] ?? 0.0 ) + self::compute_cost( $input, $output, $model );
		update_option( self::DAILY_OPTION, $daily, false );
	}

	/**
	 * Table de tarifs applicable, filtrable pour couvrir un modèle absent de
	 * la table interne (nouveau Claude, modèle OpenAI…).
	 *
	 * @return array<string, array{input: float, output: float}>
	 */
	public static function get_pricing(): array {
		/**
		 * Filtre les tarifs ($ par million de tokens) utilisés pour estimer le coût.
		 *
		 * @param array<string, array{input: float, output: float}> $pricing Tarifs par modèle.
		 */
		$pricing = (array) apply_filters( 'alesta_ai_model_pricing', self::$default_pricing );
		return $pricing;
	}

	/**
	 * Tarif connu pour un modèle, ou null si le modèle n'est pas tarifé.
	 *
	 * @param string $model Modèle.
	 * @return array{input: float, output: float}|null
	 */
	private static function find_price( string $model ): ?array {
		if ( '' === $model ) {
			return null;
		}
		foreach ( self::get_pricing() as $key => $p ) {
			if ( ! is_array( $p ) || ! isset( $p['input'], $p['output'] ) ) {
				continue;
			}
			if ( strpos( $model, (string) $key ) !== false || strpos( (string) $key, $model ) !== false ) {
				return array(
					'input'  => (float) $p['input'],
					'output' => (float) $p['output'],
				);
			}
		}
		return null;
	}

	/**
	 * Indique si le coût d'un modèle peut être estimé (tarif connu).
	 *
	 * @param string $model Modèle.
	 * @return bool
	 */
	public static function has_pricing( string $model ): bool {
		return null !== self::find_price( $model );
	}

	/**
	 * Calcule le coût en USD pour des tokens donnés.
	 *
	 * Modèle sans tarif connu (OpenAI, nouveau Claude) : le coût vaut 0 —
	 * les tokens restent comptés, seule l'estimation monétaire est absente.
	 * Le filtre 'alesta_ai_model_pricing' permet de fournir ses propres tarifs.
	 *
	 * @param int    $input  Tokens entrants.
	 * @param int    $output Tokens sortants.
	 * @param string $model  Modèle.
	 * @return float
	 */
	public static function compute_cost( int $input, int $output, string $model ): float {
		$price = self::find_price( $model );
		if ( null === $price ) {
			return 0.0;
		}
		return round(
			( $input / 1000000 ) * $price['input'] +
			( $output / 1000000 ) * $price['output'],
			6
		);
	}

	// =========================================================================
	// STATS — helpers statiques (délégués par Alesta_Admin_Budget).
	// =========================================================================

	/**
	 * Statistiques d'usage cumulées.
	 *
	 * @return array
	 */
	public static function get_usage_stats(): array {
		$usage = get_option( self::USAGE_OPTION, array() );
		if ( ! is_array( $usage ) ) {
			$usage = array();
		}
		$in  = (int) ( $usage['total_input'] ?? ( $usage['input_tokens'] ?? 0 ) );
		$out = (int) ( $usage['total_output'] ?? ( $usage['output_tokens'] ?? 0 ) );
		return array(
			'total_input'    => $in,
			'total_output'   => $out,
			'input_tokens'   => $in,
			'output_tokens'  => $out,
			'calls'          => (int) ( $usage['calls'] ?? 0 ),
			'total_cost_usd' => (float) ( $usage['total_cost_usd'] ?? 0.0 ),
			'last_model'     => (string) ( $usage['last_model'] ?? '' ),
			'last_call'      => (string) ( $usage['last_call'] ?? '' ),
			'since'          => (string) ( $usage['since'] ?? '' ),
		);
	}

	/**
	 * Remet le compteur global à zéro.
	 */
	public static function reset_usage(): void {
		delete_option( self::USAGE_OPTION );
	}

	/**
	 * Remet à zéro uniquement le mois en cours (mensuel + jours du mois).
	 */
	public static function reset_monthly(): void {
		$month   = gmdate( 'Y-m' );
		$monthly = get_option( self::MONTHLY_OPTION, array() );
		if ( is_array( $monthly ) && isset( $monthly[ $month ] ) ) {
			unset( $monthly[ $month ] );
			update_option( self::MONTHLY_OPTION, $monthly, false );
		}
		$daily = get_option( self::DAILY_OPTION, array() );
		if ( is_array( $daily ) ) {
			foreach ( array_keys( $daily ) as $date ) {
				if ( strpos( (string) $date, $month ) === 0 ) {
					unset( $daily[ $date ] );
				}
			}
			update_option( self::DAILY_OPTION, $daily, false );
		}
	}

	/**
	 * Supprime tout l'historique de consommation.
	 */
	public static function reset_all(): void {
		delete_option( self::USAGE_OPTION );
		delete_option( self::MONTHLY_OPTION );
		delete_option( self::DAILY_OPTION );
	}

	/**
	 * Statistiques mensuelles (toutes les périodes), clé 'Y-m'.
	 *
	 * @return array
	 */
	public static function get_monthly_stats(): array {
		$m = get_option( self::MONTHLY_OPTION, array() );
		return is_array( $m ) ? $m : array();
	}

	/**
	 * Statistiques journalières (N derniers jours), clé 'Y-m-d'.
	 *
	 * @param int $days Nombre de jours.
	 * @return array
	 */
	public static function get_daily_stats( int $days = 30 ): array {
		$daily = get_option( self::DAILY_OPTION, array() );
		if ( ! is_array( $daily ) ) {
			return array();
		}
		ksort( $daily );
		if ( $days > 0 && count( $daily ) > $days ) {
			$daily = array_slice( $daily, -$days, null, true );
		}
		return $daily;
	}

	/**
	 * Réglages du budget (option alesta_budget_settings du module Budget).
	 *
	 * @return array{monthly_limit: float, alert_threshold: int, block_on_limit: bool, alert_email: string}
	 */
	public static function get_budget_settings(): array {
		$b = get_option( self::BUDGET_OPTION, array() );
		if ( ! is_array( $b ) ) {
			$b = array();
		}
		return array(
			'monthly_limit'   => (float) ( $b['monthly_limit'] ?? 0 ),
			'alert_threshold' => (int) ( $b['alert_threshold'] ?? 80 ),
			'block_on_limit'  => (bool) ( $b['block_on_limit'] ?? false ),
			'alert_email'     => (string) ( $b['alert_email'] ?? '' ),
		);
	}

	/**
	 * Sauvegarde les réglages du budget.
	 *
	 * @param array $data Données brutes.
	 */
	public static function save_budget_settings( array $data ): void {
		update_option(
			self::BUDGET_OPTION,
			array(
				'monthly_limit'   => max( 0, (float) ( $data['monthly_limit'] ?? 0 ) ),
				'alert_threshold' => max( 1, min( 100, (int) ( $data['alert_threshold'] ?? 80 ) ) ),
				'block_on_limit'  => ! empty( $data['block_on_limit'] ),
				'alert_email'     => sanitize_email( (string) ( $data['alert_email'] ?? '' ) ),
			),
			false
		);
	}
}
