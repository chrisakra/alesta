<?php
/**
 * Résolution fiable de l'adresse IP du client.
 *
 * REMOTE_ADDR est la seule valeur non falsifiable. Les en-têtes de transfert
 * (X-Forwarded-For, CF-Connecting-IP, X-Real-IP…) sont contrôlés par le client :
 * ne s'y fier que si la requête nous parvient réellement via un proxy déclaré
 * de confiance, sinon un anonyme usurpe n'importe quelle IP (ALESTA-06) —
 * contournement de l'anti-force-brute, bannissement de l'administrateur, et
 * journaux d'audit faussés.
 *
 * Site derrière un reverse-proxy / CDN — à déclarer dans wp-config.php :
 *   define( 'ALESTA_TRUSTED_PROXIES', '10.0.0.0/8,192.168.0.0/16' );
 *   define( 'ALESTA_TRUSTED_PROXY_HEADER', 'HTTP_X_FORWARDED_FOR' ); // ou HTTP_CF_CONNECTING_IP
 *
 * @package Alesta
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alesta_Net' ) ) {

	/**
	 * Utilitaires réseau partagés (Free et Pro : classe gardée par class_exists).
	 */
	class Alesta_Net {

		/**
		 * Adresse IP du client, non usurpable par défaut.
		 */
		public static function client_ip(): string {
			$remote = isset( $_SERVER['REMOTE_ADDR'] )
				? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
				: '';
			$remote = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';

			$trusted = defined( 'ALESTA_TRUSTED_PROXIES' )
				? array_filter( array_map( 'trim', explode( ',', (string) ALESTA_TRUSTED_PROXIES ) ) )
				: array();

			// Pas de proxy de confiance déclaré, ou la requête n'arrive pas par
			// l'un d'eux : REMOTE_ADDR fait foi, aucun en-tête n'est pris en compte.
			if ( '' === $remote || empty( $trusted ) || ! self::ip_in_ranges( $remote, $trusted ) ) {
				return $remote;
			}

			$header = defined( 'ALESTA_TRUSTED_PROXY_HEADER' )
				? (string) ALESTA_TRUSTED_PROXY_HEADER
				: 'HTTP_X_FORWARDED_FOR';
			if ( empty( $_SERVER[ $header ] ) ) {
				return $remote;
			}

			// « client, proxy1, proxy2 » : on retient la dernière IP qui n'est
			// pas elle-même un proxy de confiance, en lisant de droite à gauche.
			$raw   = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
			$parts = array_reverse( array_map( 'trim', explode( ',', $raw ) ) );
			foreach ( $parts as $ip ) {
				if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					continue;
				}
				if ( self::ip_in_ranges( $ip, $trusted ) ) {
					continue;
				}
				return $ip;
			}
			return $remote;
		}

		/**
		 * $ip appartient-elle à l'une des plages ? (IP exactes ou notation CIDR)
		 */
		public static function ip_in_ranges( string $ip, array $ranges ): bool {
			foreach ( $ranges as $range ) {
				if ( self::ip_in_range( $ip, $range ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Test d'appartenance d'une IP à une plage, IPv4 et IPv6.
		 */
		private static function ip_in_range( string $ip, string $range ): bool {
			if ( false === strpos( $range, '/' ) ) {
				return $ip === $range;
			}

			list( $subnet, $bits ) = explode( '/', $range, 2 );
			$bits = (int) $bits;

			$ip_bin     = @inet_pton( $ip );     // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$subnet_bin = @inet_pton( $subnet ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( false === $ip_bin || false === $subnet_bin ) {
				return false;
			}
			// Familles différentes (IPv4 vs IPv6) : jamais de correspondance.
			if ( strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
				return false;
			}

			$bytes = intdiv( $bits, 8 );
			$rem   = $bits % 8;
			if ( $bytes > 0 && 0 !== substr_compare( $ip_bin, $subnet_bin, 0, $bytes ) ) {
				return false;
			}
			if ( 0 === $rem ) {
				return true;
			}
			$mask = chr( ( 0xff << ( 8 - $rem ) ) & 0xff );
			return ( $ip_bin[ $bytes ] & $mask ) === ( $subnet_bin[ $bytes ] & $mask );
		}

		/**
		 * Plages réseau internes à ne jamais atteindre en sortie (anti-SSRF).
		 */
		private static function private_ranges(): array {
			return array(
				'0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
				'169.254.0.0/16', '172.16.0.0/12', '192.168.0.0/16', '192.0.0.0/24',
				'::1/128', 'fc00::/7', 'fe80::/10', '::ffff:0:0/96',
			);
		}

		/**
		 * URL de sortie sûre ? Schéma http/https, port 80/443, et hôte qui ne
		 * résout pas vers une plage interne (bloque 169.254.169.254, le LAN, le
		 * loopback…). Un hôte non résolu est refusé. Anti-SSRF (ALESTA-10).
		 */
		public static function is_safe_remote_url( string $url ): bool {
			$p = wp_parse_url( $url );
			if ( empty( $p['scheme'] ) || empty( $p['host'] ) ) {
				return false;
			}
			$scheme = strtolower( $p['scheme'] );
			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				return false;
			}
			$port = isset( $p['port'] ) ? (int) $p['port'] : ( 'https' === $scheme ? 443 : 80 );
			if ( ! in_array( $port, array( 80, 443 ), true ) ) {
				return false;
			}

			$host = $p['host'];
			$ips  = array();
			if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
				$ips[] = $host;
			} else {
				$recs = function_exists( 'dns_get_record' ) ? @dns_get_record( $host, DNS_A | DNS_AAAA ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( is_array( $recs ) ) {
					foreach ( $recs as $r ) {
						if ( ! empty( $r['ip'] ) ) {
							$ips[] = $r['ip'];
						}
						if ( ! empty( $r['ipv6'] ) ) {
							$ips[] = $r['ipv6'];
						}
					}
				}
				if ( empty( $ips ) && function_exists( 'gethostbynamel' ) ) {
					$v4 = @gethostbynamel( $host ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					if ( is_array( $v4 ) ) {
						$ips = $v4;
					}
				}
			}
			if ( empty( $ips ) ) {
				return false; // hôte non résolu : on refuse par principe.
			}
			$ranges = self::private_ranges();
			foreach ( $ips as $ip ) {
				if ( self::ip_in_ranges( $ip, $ranges ) ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * L'URL vise-t-elle le site lui-même ? (même hôte que home_url).
		 */
		public static function is_same_host( string $url ): bool {
			$h = wp_parse_url( $url, PHP_URL_HOST );
			$home = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
			return $h && $home && strtolower( $h ) === strtolower( $home );
		}
	}
}
