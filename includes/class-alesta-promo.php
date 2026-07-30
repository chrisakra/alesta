<?php
/**
 * Alesta — "Available in Alesta AI Pro" promotional page.
 *
 * Renders a static informational page for features that live in the
 * separate Alesta AI Pro extension (distributed outside WordPress.org).
 * No license check, no payment flow, no remote calls.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alesta_Promo {

	const PRO_URL = 'https://www.alesta-ai.com/tarifs.html';

	/**
	 * Render the promo page for a given feature.
	 */
	public static function render( $feature_name, $feature_desc = '', $icon = '\xE2\x9C\xA8' ) {
		wp_enqueue_style(
			'alesta-pro-promo',
			plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/pro-promo.css',
			array(),
			ALESTA_VERSION
		);
		?>
		<div class="wrap alesta-wrap">
			<div class="alesta-pro-promo-wrap">
				<div class="alesta-pro-promo-card">
					<div class="alesta-pro-promo-badge">Alesta AI Pro</div>
					<div class="alesta-pro-promo-icon"><?php echo esc_html( $icon ); ?></div>
					<h2 class="alesta-pro-promo-title"><?php echo esc_html( $feature_name ); ?></h2>
					<?php if ( $feature_desc ) : ?>
					<p class="alesta-pro-promo-desc"><?php echo esc_html( $feature_desc ); ?></p>
					<?php endif; ?>
					<p class="alesta-pro-promo-info">
						<?php esc_html_e( 'This feature is part of Alesta AI Pro, a separate extension distributed outside the WordPress.org repository.', 'alesta' ); ?>
					</p>
					<a href="<?php echo esc_url( self::PRO_URL ); ?>" class="alesta-pro-promo-btn" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Discover Alesta AI Pro', 'alesta' ); ?> &rarr;
					</a>
					<p class="alesta-pro-promo-note">
						<?php esc_html_e( 'You will be redirected to alesta-ai.com.', 'alesta' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Small pill HTML shown next to feature names on the dashboard cards.
	 *
	 * @param string $tier 'solo' (light) or 'pro' (filled).
	 */
	public static function dashboard_badge( $tier = 'solo' ) {
		$class = $tier === 'pro' ? 'alesta-pro-badge alesta-pro-badge--pro' : 'alesta-pro-badge';
		$label = $tier === 'pro' ? 'Pro' : 'Solo';
		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}
}
