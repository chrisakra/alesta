<?php
/**
 * Alesta — SEO meta tags per post/page.
 *
 * Feature unique : editer le SEO title + meta description de chaque
 * page/article via une metabox, et exposer Open Graph basique.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alesta_Meta {

	const META_TITLE       = '_alesta_seo_title';
	const META_DESCRIPTION = '_alesta_seo_description';
	const NONCE_ACTION     = 'alesta_meta_save';
	const NONCE_FIELD      = 'alesta_meta_nonce';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_metabox' ) );
		add_action( 'save_post', array( __CLASS__, 'save_metabox' ), 10, 2 );
		add_filter( 'document_title_parts', array( __CLASS__, 'filter_title_parts' ) );
		add_action( 'wp_head', array( __CLASS__, 'output_meta_tags' ), 1 );
	}

	/**
	 * Ajoute la metabox sur tous les post types publics.
	 */
	public static function add_metabox() {
		$post_types = get_post_types( array( 'public' => true ) );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'alesta_seo',
				__( 'Alesta — SEO', 'alesta' ),
				array( __CLASS__, 'render_metabox' ),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Rend le formulaire de la metabox.
	 */
	public static function render_metabox( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		$title       = get_post_meta( $post->ID, self::META_TITLE, true );
		$description = get_post_meta( $post->ID, self::META_DESCRIPTION, true );
		?>
		<p>
			<label for="alesta_seo_title" style="display:block;font-weight:600;margin-bottom:4px;">
				<?php esc_html_e( 'Titre SEO', 'alesta' ); ?>
			</label>
			<input
				type="text"
				id="alesta_seo_title"
				name="alesta_seo_title"
				value="<?php echo esc_attr( $title ); ?>"
				maxlength="70"
				style="width:100%;"
				placeholder="<?php esc_attr_e( 'Laisser vide pour utiliser le titre par defaut', 'alesta' ); ?>"
			/>
			<span style="color:#666;font-size:12px;">
				<?php esc_html_e( 'Environ 60 caracteres recommandes.', 'alesta' ); ?>
			</span>
		</p>
		<p>
			<label for="alesta_seo_description" style="display:block;font-weight:600;margin-bottom:4px;">
				<?php esc_html_e( 'Meta description', 'alesta' ); ?>
			</label>
			<textarea
				id="alesta_seo_description"
				name="alesta_seo_description"
				rows="3"
				maxlength="200"
				style="width:100%;"
				placeholder="<?php esc_attr_e( 'Resume affiche par Google sous le titre de votre page.', 'alesta' ); ?>"
			><?php echo esc_textarea( $description ); ?></textarea>
			<span style="color:#666;font-size:12px;">
				<?php esc_html_e( 'Environ 155 caracteres recommandes.', 'alesta' ); ?>
			</span>
		</p>
		<?php
	}

	/**
	 * Sauvegarde la metabox (avec verification nonce + capability).
	 */
	public static function save_metabox( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$title = isset( $_POST['alesta_seo_title'] )
			? sanitize_text_field( wp_unslash( $_POST['alesta_seo_title'] ) )
			: '';
		$description = isset( $_POST['alesta_seo_description'] )
			? sanitize_textarea_field( wp_unslash( $_POST['alesta_seo_description'] ) )
			: '';

		if ( $title !== '' ) {
			update_post_meta( $post_id, self::META_TITLE, $title );
		} else {
			delete_post_meta( $post_id, self::META_TITLE );
		}

		if ( $description !== '' ) {
			update_post_meta( $post_id, self::META_DESCRIPTION, $description );
		} else {
			delete_post_meta( $post_id, self::META_DESCRIPTION );
		}
	}

	/**
	 * Override le titre du document si un titre SEO custom est defini.
	 */
	public static function filter_title_parts( $parts ) {
		if ( ! is_singular() ) {
			return $parts;
		}
		$custom = get_post_meta( get_queried_object_id(), self::META_TITLE, true );
		if ( $custom ) {
			$parts['title'] = $custom;
		}
		return $parts;
	}

	/**
	 * Injecte la meta description et Open Graph dans le <head>.
	 */
	public static function output_meta_tags() {
		if ( ! is_singular() ) {
			return;
		}
		$post_id     = get_queried_object_id();
		$title       = get_post_meta( $post_id, self::META_TITLE, true );
		$description = get_post_meta( $post_id, self::META_DESCRIPTION, true );

		if ( ! $title ) {
			$title = get_the_title( $post_id );
		}
		if ( ! $description ) {
			$excerpt = get_the_excerpt( $post_id );
			if ( $excerpt ) {
				$description = wp_trim_words( $excerpt, 30, '...' );
			}
		}

		if ( $description ) {
			printf(
				"<meta name=\"description\" content=\"%s\" />\n",
				esc_attr( $description )
			);
		}

		// Open Graph basique
		if ( $title ) {
			printf(
				"<meta property=\"og:title\" content=\"%s\" />\n",
				esc_attr( $title )
			);
		}
		if ( $description ) {
			printf(
				"<meta property=\"og:description\" content=\"%s\" />\n",
				esc_attr( $description )
			);
		}
		printf(
			"<meta property=\"og:url\" content=\"%s\" />\n",
			esc_url( get_permalink( $post_id ) )
		);
		printf(
			"<meta property=\"og:type\" content=\"%s\" />\n",
			esc_attr( is_singular( 'post' ) ? 'article' : 'website' )
		);

		// Twitter Card basique
		echo "<meta name=\"twitter:card\" content=\"summary\" />\n";
	}
}
