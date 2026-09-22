<?php
/**
 * Alesta — Coffre-fort des clés API des fournisseurs IA.
 *
 * Port Free de Alesta_API_Key_Vault (Pro). Stockage chiffré at-rest de la
 * clé API en AES-256-GCM avec AUTH_KEY (constante de wp-config.php), sous
 * la MÊME option que la Pro (`alesta_ai_api_key_enc`) : la clé saisie dans
 * le Free est directement réutilisée par la Pro après upgrade, et inversement.
 *
 * Depuis la 1.9.0 le coffre gère un « slot » par fournisseur IA :
 *   - 'anthropic' (défaut) : options historiques, strictement inchangées ;
 *   - 'openai'             : option `alesta_ai_openai_key_enc`.
 * Les deux clés coexistent : changer de fournisseur n'efface pas l'autre.
 *
 * Migration douce : si une option legacy plaintext existe encore
 * (`alesta_ai_api_key` ou `alesta_ai_anthropic_api_key`), elle est lue, réécrite
 * chiffrée, puis supprimée.
 *
 * Format du blob (base64) : IV (12) || TAG (16) || CIPHERTEXT.
 *
 * @package Alesta
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alesta_Key_Vault {

	/** Option WP qui stocke le blob chiffré (base64) — partagée avec la Pro. */
	const OPT_ENC = 'alesta_ai_api_key_enc';

	/** Options legacy plaintext à migrer (mêmes noms que la Pro). */
	const OPT_LEGACY_V14 = 'alesta_ai_api_key';
	const OPT_LEGACY_OLD = 'alesta_ai_anthropic_api_key';

	/** Slot par défaut : la clé Anthropic (comportement historique). */
	const SLOT_ANTHROPIC = 'anthropic';

	/** Slot de la clé OpenAI (ChatGPT). */
	const SLOT_OPENAI = 'openai';

	/** Option WP qui stocke le blob chiffré de la clé OpenAI. */
	const OPT_ENC_OPENAI = 'alesta_ai_openai_key_enc';

	/** Option de repli plaintext OpenAI (hébergement sans OpenSSL/GCM). */
	const OPT_LEGACY_OPENAI = 'alesta_ai_openai_key';

	/** Marqueur de migration (même nom que la Pro). */
	const OPT_MIGRATION_SOURCE = 'alesta_ai_vault_migrated_from';

	/** Algorithme : AES-256 en mode GCM (authenticated encryption). */
	const CIPHER = 'aes-256-gcm';

	/** Taille IV pour GCM (12 bytes recommandés par NIST). */
	const IV_LEN = 12;

	/** Taille tag d'authentification GCM (16 bytes standard). */
	const TAG_LEN = 16;

	// =========================================================================
	// API publique
	// =========================================================================

	/**
	 * Stocke (ou met à jour) la clé API d'un fournisseur en la chiffrant.
	 *
	 * @param string $key  Clé en clair (ex. "sk-ant-api03-..." ou "sk-...").
	 * @param string $slot Slot de fournisseur ('anthropic' par défaut, 'openai').
	 * @return bool True si écriture réussie.
	 */
	public static function set( string $key, string $slot = self::SLOT_ANTHROPIC ): bool {
		$slot = self::normalize_slot( $slot );
		$key  = trim( $key );
		if ( '' === $key ) {
			return self::delete( $slot );
		}

		$secret = self::get_encryption_key();
		if ( null === $secret ) {
			// wp-config.php sans AUTH_KEY robuste : on refuse d'écrire en clair.
			return false;
		}

		$opts = self::slot_options( $slot );

		if ( ! function_exists( 'openssl_encrypt' ) || ! self::cipher_available() ) {
			// Hébergement sans OpenSSL/GCM : fallback plaintext legacy plutôt qu'aucun stockage.
			return (bool) update_option( $opts['legacy'][0], $key );
		}

		$iv        = random_bytes( self::IV_LEN );
		$tag       = '';
		$encrypted = openssl_encrypt(
			$key,
			self::CIPHER,
			$secret,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			self::TAG_LEN
		);

		if ( false === $encrypted ) {
			return false;
		}

		// IV aléatoire : le blob change à chaque set(), update_option() renvoie donc un vrai résultat.
		$blob = base64_encode( $iv . $tag . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- stockage binaire d'un blob chiffré.
		$ok   = (bool) update_option( $opts['enc'], $blob );

		if ( $ok ) {
			foreach ( $opts['legacy'] as $legacy_opt ) {
				delete_option( $legacy_opt );
			}
		}

		return $ok;
	}

	/**
	 * Récupère la clé API en clair (déchiffrée à la volée).
	 * Migre au passage une éventuelle clé legacy plaintext.
	 *
	 * @param string $slot Slot de fournisseur ('anthropic' par défaut, 'openai').
	 * @return string|null Clé, ou null si absente / déchiffrement impossible.
	 */
	public static function get( string $slot = self::SLOT_ANTHROPIC ): ?string {
		$slot = self::normalize_slot( $slot );
		$opts = self::slot_options( $slot );

		$blob_b64 = get_option( $opts['enc'], '' );
		if ( is_string( $blob_b64 ) && '' !== $blob_b64 ) {
			return self::decrypt_blob( $blob_b64 );
		}

		foreach ( $opts['legacy'] as $opt ) {
			$legacy = get_option( $opt, '' );
			if ( is_string( $legacy ) && '' !== $legacy ) {
				if ( self::set( $legacy, $slot ) && self::SLOT_ANTHROPIC === $slot ) {
					update_option( self::OPT_MIGRATION_SOURCE, ( self::OPT_LEGACY_V14 === $opt ) ? 'v14' : 'old', false );
				}
				return $legacy;
			}
		}

		return null;
	}

	/**
	 * Indique si une clé est configurée (sans la déchiffrer).
	 *
	 * @param string $slot Slot de fournisseur ('anthropic' par défaut, 'openai').
	 * @return bool
	 */
	public static function has_key( string $slot = self::SLOT_ANTHROPIC ): bool {
		$opts = self::slot_options( self::normalize_slot( $slot ) );
		foreach ( array_merge( array( $opts['enc'] ), $opts['legacy'] ) as $opt ) {
			$v = get_option( $opt, '' );
			if ( is_string( $v ) && '' !== $v ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Version masquée de la clé pour affichage UI : préfixe + puces + 4 derniers caractères.
	 * Ex. "sk-ant-••••••••••••ab12".
	 *
	 * @param string $slot Slot de fournisseur ('anthropic' par défaut, 'openai').
	 * @return string|null
	 */
	public static function get_masked( string $slot = self::SLOT_ANTHROPIC ): ?string {
		$key = self::get_raw_for_mask( self::normalize_slot( $slot ) );
		if ( null === $key || strlen( $key ) < 12 ) {
			return null;
		}
		$prefix = substr( $key, 0, 7 );
		$suffix = substr( $key, -4 );
		return $prefix . str_repeat( "\xE2\x80\xA2", 12 ) . $suffix;
	}

	/**
	 * Supprime la clé d'un slot (chiffrée + legacy + marqueur de migration).
	 *
	 * @param string $slot Slot de fournisseur ('anthropic' par défaut, 'openai').
	 * @return bool True si au moins une option a été supprimée.
	 */
	public static function delete( string $slot = self::SLOT_ANTHROPIC ): bool {
		$slot    = self::normalize_slot( $slot );
		$opts    = self::slot_options( $slot );
		$deleted = false;
		foreach ( array_merge( array( $opts['enc'] ), $opts['legacy'] ) as $opt ) {
			$deleted = delete_option( $opt ) || $deleted;
		}
		if ( self::SLOT_ANTHROPIC === $slot ) {
			delete_option( self::OPT_MIGRATION_SOURCE );
		}
		return $deleted;
	}

	/**
	 * Liste des slots gérés par le coffre.
	 *
	 * @return string[]
	 */
	public static function get_slots(): array {
		return array( self::SLOT_ANTHROPIC, self::SLOT_OPENAI );
	}

	/**
	 * Indique si le chiffrement est possible sur cet hébergement
	 * (AUTH_KEY robuste + OpenSSL avec AES-256-GCM).
	 *
	 * @return bool
	 */
	public static function can_encrypt(): bool {
		return null !== self::get_encryption_key() && function_exists( 'openssl_encrypt' ) && self::cipher_available();
	}

	// =========================================================================
	// Internes
	// =========================================================================

	/**
	 * Normalise un nom de slot (tout slot inconnu retombe sur 'anthropic').
	 *
	 * @param string $slot Slot demandé.
	 * @return string
	 */
	private static function normalize_slot( string $slot ): string {
		return ( self::SLOT_OPENAI === $slot ) ? self::SLOT_OPENAI : self::SLOT_ANTHROPIC;
	}

	/**
	 * Options WordPress associées à un slot.
	 *
	 * @param string $slot Slot normalisé.
	 * @return array{enc: string, legacy: string[]}
	 */
	private static function slot_options( string $slot ): array {
		if ( self::SLOT_OPENAI === $slot ) {
			return array(
				'enc'    => self::OPT_ENC_OPENAI,
				'legacy' => array( self::OPT_LEGACY_OPENAI ),
			);
		}
		return array(
			'enc'    => self::OPT_ENC,
			'legacy' => array( self::OPT_LEGACY_V14, self::OPT_LEGACY_OLD ),
		);
	}

	/**
	 * Vérifie que le cipher GCM est supporté par l'OpenSSL de l'hébergeur.
	 *
	 * @return bool
	 */
	private static function cipher_available(): bool {
		if ( ! function_exists( 'openssl_get_cipher_methods' ) ) {
			return false;
		}
		return in_array( self::CIPHER, array_map( 'strtolower', openssl_get_cipher_methods() ), true );
	}

	/**
	 * Déchiffre un blob base64 ; null si corrompu ou AUTH_KEY indisponible.
	 *
	 * @param string $blob_b64 Blob base64.
	 * @return string|null
	 */
	private static function decrypt_blob( string $blob_b64 ): ?string {
		if ( '' === $blob_b64 || ! function_exists( 'openssl_decrypt' ) ) {
			return null;
		}

		$blob = base64_decode( $blob_b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- lecture d'un blob chiffré.
		if ( false === $blob || strlen( $blob ) < ( self::IV_LEN + self::TAG_LEN + 1 ) ) {
			return null;
		}

		$secret = self::get_encryption_key();
		if ( null === $secret ) {
			return null;
		}

		$iv         = substr( $blob, 0, self::IV_LEN );
		$tag        = substr( $blob, self::IV_LEN, self::TAG_LEN );
		$ciphertext = substr( $blob, self::IV_LEN + self::TAG_LEN );

		$key = openssl_decrypt( $ciphertext, self::CIPHER, $secret, OPENSSL_RAW_DATA, $iv, $tag );

		return ( false === $key ) ? null : $key;
	}

	/**
	 * Dérive la clé de chiffrement (32 bytes) depuis AUTH_KEY via sha256.
	 *
	 * @return string|null
	 */
	private static function get_encryption_key(): ?string {
		if ( ! defined( 'AUTH_KEY' ) ) {
			return null;
		}
		$auth = (string) AUTH_KEY;
		if ( strlen( $auth ) < 32 ) {
			return null;
		}
		if ( strpos( $auth, 'put your unique phrase here' ) !== false ) {
			return null;
		}
		return hash( 'sha256', $auth, true );
	}

	/**
	 * Lecture brute pour get_masked() — sans migration.
	 *
	 * @param string $slot Slot normalisé.
	 * @return string|null
	 */
	private static function get_raw_for_mask( string $slot ): ?string {
		$opts     = self::slot_options( $slot );
		$blob_b64 = get_option( $opts['enc'], '' );
		if ( is_string( $blob_b64 ) && '' !== $blob_b64 ) {
			return self::decrypt_blob( $blob_b64 );
		}
		foreach ( $opts['legacy'] as $opt ) {
			$legacy = get_option( $opt, '' );
			if ( is_string( $legacy ) && '' !== $legacy ) {
				return $legacy;
			}
		}
		return null;
	}
}
