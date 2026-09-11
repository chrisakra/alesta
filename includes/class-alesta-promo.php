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

	const PRO_URL = 'https://www.alesta-ai.com/tarifs.html#tarifs';

	/**
	 * Render the promo page for a given feature.
	 *
	 * @param string $feature_name Displayed as the page title.
	 * @param string $feature_desc Optional lead paragraph.
	 * @param string $icon         UTF-8 emoji shown above the title.
	 * @param string $tier         'solo' → available in Solo & Pro plans,
	 *                             'pro'  → available in Pro only. The 'solo'
	 *                             wording is used when the feature is part of
	 *                             both tiers so the user knows the smallest
	 *                             plan that unlocks it. Defaults to 'solo'.
	 */
	public static function render( $feature_name, $feature_desc = '', $icon = '\xE2\x9C\xA8', $tier = 'solo' ) {
		wp_enqueue_style(
			'alesta-pro-promo',
			plugin_dir_url( ALESTA_PLUGIN_FILE ) . 'assets/pro-promo.css',
			array(),
			ALESTA_VERSION
		);
		$is_pro_only = ( $tier === 'pro' );
		$tier_note   = $is_pro_only
			? __( 'Disponible dans le plan Pro.', 'alesta' )
			: __( 'Disponible dans les plans Solo et Pro.', 'alesta' );
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
						<?php esc_html_e( 'Cette fonctionnalité fait partie d\'Alesta AI Pro, une extension distincte distribuée en dehors du dépôt WordPress.org.', 'alesta' ); ?>
						<br>
						<strong><?php echo esc_html( $tier_note ); ?></strong>
					</p>
					<a href="<?php echo esc_url( self::PRO_URL ); ?>" class="alesta-pro-promo-btn" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Découvrir Alesta AI Pro', 'alesta' ); ?> &rarr;
					</a>
					<p class="alesta-pro-promo-note">
						<?php esc_html_e( 'Vous serez redirigé vers alesta-ai.com.', 'alesta' ); ?>
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
