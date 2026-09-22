<?php
defined('ABSPATH') || exit;

/**
 * SEO Meta Box — panneau dans l'éditeur WordPress (Alesta Free).
 * Fonctionne dans Gutenberg ET Classic Editor.
 * Écrit dans les champs _alesta_* (+ miroir Yoast / RankMath si présents).
 *
 * Porté depuis Alesta AI Pro (Alesta_AI_SEO_Meta_Box) — mêmes clés post meta.
 * Quand le Pro est actif (Alesta_AI_SEO_Meta_Box), le loader ne doit pas
 * instancier cette classe.
 */
class Alesta_SEO_Meta_Box {

    const NONCE_SAVE = 'alesta_seo_mb_save';
    const NONCE_AJAX = 'alesta_seo_mb';

    /** @var bool Hooks enregistrés une seule fois. */
    private static $hooked = false;

    public function __construct() {
        if ( self::$hooked ) {
            return;
        }
        self::$hooked = true;

        add_action('add_meta_boxes',                  [$this, 'register']);
        add_action('save_post',                       [$this, 'save'], 10, 2);
        add_action('admin_enqueue_scripts',           [$this, 'enqueue']);
        add_action('wp_ajax_alesta_seo_mb_generate',  [$this, 'ajax_generate_ai']);
    }

    // =========================================================================
    // ENREGISTREMENT
    // =========================================================================

    public function register(): void {
        foreach ( $this->get_post_types() as $type ) {
            add_meta_box(
                'alesta-seo-mb',
                "\xF0\x9F\x94\x8D " . __('SEO — Alesta', 'alesta'),
                [$this, 'render'],
                $type,
                'normal',
                'high'
            );
        }
    }

    private function get_post_types(): array {
        $public = get_post_types(['public' => true], 'names');
        unset($public['attachment']);
        return array_values($public);
    }

    // =========================================================================
    // ASSETS
    // =========================================================================

    public function enqueue( string $hook ): void {
        if ( ! in_array($hook, ['post.php', 'post-new.php'], true) ) {
            return;
        }
        $screen = get_current_screen();
        if ( ! $screen || ! in_array($screen->post_type, $this->get_post_types(), true) ) {
            return;
        }

        $url = plugin_dir_url(ALESTA_PLUGIN_FILE);
        wp_enqueue_style('alesta-seo-mb', $url . 'assets/seo-meta-box.css', [], ALESTA_VERSION);
        wp_enqueue_media(); // Médiathèque pour l'image OG.
        if ( class_exists('Alesta_API') ) {
            Alesta_API::enqueue_key_notice();
        }
        wp_enqueue_script('alesta-seo-mb', $url . 'assets/seo-meta-box.js', ['jquery'], ALESTA_VERSION, true);
        wp_localize_script('alesta-seo-mb', 'AlestaSeoMB', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(self::NONCE_AJAX),
            'post_id'  => get_the_ID(),
            'home_url' => home_url('/'),
            'i18n'     => [
                'no_title'     => __('Titre SEO non défini', 'alesta'),
                'no_desc'      => __('Aucune méta description — définissez-en une ci-dessous.', 'alesta'),
                'choose_image' => __('Choisir l\'image Open Graph', 'alesta'),
                'use_image'    => __('Utiliser cette image', 'alesta'),
                'change_image' => __('Changer l\'image', 'alesta'),
                'pick_image'   => __('Choisir une image', 'alesta'),
                'remove'       => __('Supprimer', 'alesta'),
                'suggestions'  => __('Suggestions Claude — cochez ce que vous souhaitez appliquer', 'alesta'),
                'prop_title'   => __('TITRE SEO PROPOSÉ', 'alesta'),
                'prop_desc'    => __('MÉTA DESCRIPTION PROPOSÉE', 'alesta'),
                'prop_kw'      => __('MOT-CLÉ PRINCIPAL PROPOSÉ', 'alesta'),
                'apply'        => __('Appliquer la sélection', 'alesta'),
                'dismiss'      => __('Ignorer', 'alesta'),
                'applied'      => __('Appliqué !', 'alesta'),
                'error'        => __('Erreur.', 'alesta'),
                'network'      => __('Erreur réseau.', 'alesta'),
            ],
        ]);
    }

    // =========================================================================
    // RENDU DE LA META BOX
    // =========================================================================

    public function render( WP_Post $post ): void {
        wp_nonce_field(self::NONCE_SAVE, 'alesta_seo_mb_nonce');

        $f         = $this->get_fields($post->ID);
        $permalink = get_permalink($post->ID) ?: home_url('/');
        $slug      = str_replace(home_url('/'), '', $permalink);
        $robots    = $f['robots'];
        $noindex   = strpos($robots, 'noindex')  !== false;
        $nofollow  = strpos($robots, 'nofollow') !== false;
        ?>
        <div id="alesta-seo-mb" class="alesta-seo-mb">

            <!-- ── Onglets ── -->
            <div class="aseo-tabs">
                <button type="button" class="aseo-tab active" data-target="aseo-pane-main"><?php esc_html_e('Général', 'alesta'); ?></button>
                <button type="button" class="aseo-tab"        data-target="aseo-pane-social"><?php esc_html_e('Réseaux sociaux', 'alesta'); ?></button>
                <button type="button" class="aseo-tab"        data-target="aseo-pane-advanced"><?php esc_html_e('Avancé', 'alesta'); ?></button>
            </div>

            <!-- ── PANE : Général ── -->
            <div class="aseo-pane active" id="aseo-pane-main">

                <!-- Aperçu Google SERP -->
                <div class="aseo-serp-preview">
                    <div class="aseo-serp-label"><?php esc_html_e('Aperçu dans Google', 'alesta'); ?></div>
                    <div class="aseo-serp-box">
                        <div class="aseo-serp-url"><?php echo esc_html( home_url('/') . ltrim($slug, '/') ); ?></div>
                        <div class="aseo-serp-title" id="serp-title-preview">
                            <?php echo esc_html( $f['seo_title'] ?: $post->post_title ); ?>
                        </div>
                        <div class="aseo-serp-desc" id="serp-desc-preview">
                            <?php echo esc_html( $f['meta_description'] ?: __('Aucune méta description — définissez-en une ci-dessous.', 'alesta') ); ?>
                        </div>
                    </div>
                </div>

                <!-- Titre SEO -->
                <div class="aseo-field">
                    <label class="aseo-label" for="aseo-seo-title">
                        <?php esc_html_e('Titre SEO', 'alesta'); ?>
                        <span class="aseo-hint"><?php esc_html_e('Affiché dans les onglets et résultats Google', 'alesta'); ?></span>
                    </label>
                    <div class="aseo-input-wrap">
                        <input type="text" id="aseo-seo-title" name="alesta_seo_title"
                               value="<?php echo esc_attr($f['seo_title']); ?>"
                               placeholder="<?php echo esc_attr($post->post_title); ?>"
                               class="aseo-input" maxlength="100" />
                        <div class="aseo-counter">
                            <span id="aseo-title-count">0</span>/60
                            <span class="aseo-bar-wrap"><span class="aseo-bar" id="aseo-title-bar"></span></span>
                        </div>
                    </div>
                </div>

                <!-- Meta Description -->
                <div class="aseo-field">
                    <label class="aseo-label" for="aseo-meta-desc">
                        <?php esc_html_e('Méta description', 'alesta'); ?>
                        <span class="aseo-hint"><?php esc_html_e('Résumé affiché sous le titre dans Google (140-160 caractères)', 'alesta'); ?></span>
                    </label>
                    <div class="aseo-input-wrap">
                        <textarea id="aseo-meta-desc" name="alesta_meta_description"
                                  class="aseo-textarea" rows="3" maxlength="320"><?php echo esc_textarea($f['meta_description']); ?></textarea>
                        <div class="aseo-counter">
                            <span id="aseo-desc-count">0</span>/160
                            <span class="aseo-bar-wrap"><span class="aseo-bar" id="aseo-desc-bar"></span></span>
                        </div>
                    </div>
                </div>

                <!-- Mot-clé principal -->
                <div class="aseo-field aseo-field-half">
                    <label class="aseo-label" for="aseo-focus-kw"><?php esc_html_e('Mot-clé principal', 'alesta'); ?></label>
                    <input type="text" id="aseo-focus-kw" name="alesta_focus_keyword"
                           value="<?php echo esc_attr($f['focus_keyword']); ?>"
                           placeholder="<?php esc_attr_e('ex : chaussures running homme', 'alesta'); ?>"
                           class="aseo-input" />
                </div>

                <!-- Bouton IA -->
                <div class="aseo-field aseo-ai-row">
                    <button type="button" id="aseo-btn-ai" class="button">
                        &#129302; <?php esc_html_e('Générer avec Claude', 'alesta'); ?>
                    </button>
                    <span class="spinner" id="aseo-ai-spinner" style="float:none;margin:0 0 0 8px;"></span>
                    <span id="aseo-ai-msg" class="aseo-ai-msg"></span>
                </div>

            </div>

            <!-- ── PANE : Réseaux sociaux ── -->
            <div class="aseo-pane" id="aseo-pane-social">

                <div class="aseo-social-group">
                    <div class="aseo-social-header">&#128216; <?php esc_html_e('Facebook / Open Graph', 'alesta'); ?></div>

                    <div class="aseo-field">
                        <label class="aseo-label" for="aseo-og-title"><?php esc_html_e('Titre OG', 'alesta'); ?></label>
                        <input type="text" id="aseo-og-title" name="alesta_og_title"
                               value="<?php echo esc_attr($f['og_title']); ?>"
                               placeholder="<?php esc_attr_e('Laissez vide pour utiliser le titre SEO', 'alesta'); ?>"
                               class="aseo-input" />
                    </div>
                    <div class="aseo-field">
                        <label class="aseo-label" for="aseo-og-desc"><?php esc_html_e('Description OG', 'alesta'); ?></label>
                        <textarea id="aseo-og-desc" name="alesta_og_desc"
                                  class="aseo-textarea" rows="2"
                                  placeholder="<?php esc_attr_e('Laissez vide pour utiliser la méta description', 'alesta'); ?>"><?php echo esc_textarea($f['og_desc']); ?></textarea>
                    </div>
                    <div class="aseo-field">
                        <label class="aseo-label"><?php esc_html_e('Image OG', 'alesta'); ?></label>
                        <div class="aseo-img-picker">
                            <input type="hidden" id="aseo-og-image-id" name="alesta_og_image_id"
                                   value="<?php echo esc_attr($f['og_image_id']); ?>" />
                            <div id="aseo-og-image-preview" class="aseo-img-preview">
                                <?php
                                if ( $f['og_image_id'] ) {
                                    $src = wp_get_attachment_image_src($f['og_image_id'], 'thumbnail');
                                    if ( $src ) {
                                        echo '<img src="' . esc_url($src[0]) . '" alt="" />';
                                    }
                                }
                                ?>
                            </div>
                            <div class="aseo-img-actions">
                                <button type="button" id="aseo-og-image-btn" class="button button-small">
                                    <?php echo esc_html( $f['og_image_id'] ? __('Changer l\'image', 'alesta') : __('Choisir une image', 'alesta') ); ?>
                                </button>
                                <?php if ( $f['og_image_id'] ) : ?>
                                <button type="button" id="aseo-og-image-remove" class="button button-small"><?php esc_html_e('Supprimer', 'alesta'); ?></button>
                                <?php endif; ?>
                            </div>
                            <p class="aseo-hint-block"><?php esc_html_e('Idéalement 1200×630 px. Utilise l\'image mise en avant si non définie.', 'alesta'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="aseo-social-group">
                    <div class="aseo-social-header">&#128038; <?php esc_html_e('Twitter / X', 'alesta'); ?></div>
                    <div class="aseo-field">
                        <label class="aseo-label" for="aseo-tw-title"><?php esc_html_e('Titre Twitter', 'alesta'); ?></label>
                        <input type="text" id="aseo-tw-title" name="alesta_twitter_title"
                               value="<?php echo esc_attr($f['twitter_title']); ?>"
                               placeholder="<?php esc_attr_e('Laissez vide pour utiliser le titre SEO', 'alesta'); ?>"
                               class="aseo-input" />
                    </div>
                    <div class="aseo-field">
                        <label class="aseo-label" for="aseo-tw-desc"><?php esc_html_e('Description Twitter', 'alesta'); ?></label>
                        <textarea id="aseo-tw-desc" name="alesta_twitter_desc"
                                  class="aseo-textarea" rows="2"
                                  placeholder="<?php esc_attr_e('Laissez vide pour utiliser la méta description', 'alesta'); ?>"><?php echo esc_textarea($f['twitter_desc']); ?></textarea>
                    </div>
                </div>

            </div>

            <!-- ── PANE : Avancé ── -->
            <div class="aseo-pane" id="aseo-pane-advanced">

                <!-- Robots -->
                <div class="aseo-field">
                    <label class="aseo-label"><?php esc_html_e('Indexation', 'alesta'); ?></label>
                    <div class="aseo-robots-row">
                        <label class="aseo-checkbox-label">
                            <input type="checkbox" name="alesta_noindex"  value="1" <?php checked($noindex); ?> id="aseo-noindex" />
                            <span><?php esc_html_e('noindex — ne pas indexer cette page', 'alesta'); ?></span>
                        </label>
                        <label class="aseo-checkbox-label">
                            <input type="checkbox" name="alesta_nofollow" value="1" <?php checked($nofollow); ?> id="aseo-nofollow" />
                            <span><?php esc_html_e('nofollow — ne pas suivre les liens', 'alesta'); ?></span>
                        </label>
                    </div>
                    <p class="aseo-hint-block"><?php esc_html_e('Par défaut : index, follow (Google indexe et suit les liens).', 'alesta'); ?></p>
                </div>

                <!-- Canonical -->
                <div class="aseo-field">
                    <label class="aseo-label" for="aseo-canonical">
                        <?php esc_html_e('URL canonique', 'alesta'); ?>
                        <span class="aseo-hint"><?php esc_html_e('Laissez vide pour utiliser l\'URL de la page', 'alesta'); ?></span>
                    </label>
                    <input type="url" id="aseo-canonical" name="alesta_canonical"
                           value="<?php echo esc_attr($f['canonical']); ?>"
                           placeholder="<?php echo esc_attr($permalink); ?>"
                           class="aseo-input" />
                </div>

                <!-- Mots-clés secondaires -->
                <div class="aseo-field">
                    <label class="aseo-label" for="aseo-kw-extra">
                        <?php esc_html_e('Mots-clés secondaires', 'alesta'); ?>
                        <span class="aseo-hint"><?php esc_html_e('Séparés par des virgules', 'alesta'); ?></span>
                    </label>
                    <input type="text" id="aseo-kw-extra" name="alesta_keywords_extra"
                           value="<?php echo esc_attr($f['keywords_extra']); ?>"
                           placeholder="<?php esc_attr_e('chaussures sport, running trail, semelle amortissante', 'alesta'); ?>"
                           class="aseo-input" />
                </div>

            </div>

        </div>
        <?php
    }

    // =========================================================================
    // LECTURE DES CHAMPS
    // =========================================================================

    private function get_fields( int $post_id ): array {
        $robots = get_post_meta($post_id, '_alesta_robots', true) ?: 'index,follow';

        // Fallback depuis Yoast si les champs Alesta sont vides.
        $title = get_post_meta($post_id, '_alesta_seo_title', true);
        if ( ! $title && defined('WPSEO_VERSION') ) {
            $title = get_post_meta($post_id, '_yoast_wpseo_title', true) ?: '';
        }
        $desc = get_post_meta($post_id, '_alesta_meta_description', true);
        if ( ! $desc && defined('WPSEO_VERSION') ) {
            $desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true) ?: '';
        }

        return [
            'seo_title'        => (string) $title,
            'meta_description' => (string) $desc,
            'focus_keyword'    => (string) ( get_post_meta($post_id, '_alesta_focus_keyword',  true) ?: '' ),
            'og_title'         => (string) ( get_post_meta($post_id, '_alesta_og_title',       true) ?: '' ),
            'og_desc'          => (string) ( get_post_meta($post_id, '_alesta_og_desc',        true) ?: '' ),
            'og_image_id'      => (int) get_post_meta($post_id, '_alesta_og_image_id', true),
            'twitter_title'    => (string) ( get_post_meta($post_id, '_alesta_twitter_title',  true) ?: '' ),
            'twitter_desc'     => (string) ( get_post_meta($post_id, '_alesta_twitter_desc',   true) ?: '' ),
            'robots'           => (string) $robots,
            'canonical'        => (string) ( get_post_meta($post_id, '_alesta_canonical',      true) ?: '' ),
            'keywords_extra'   => (string) ( get_post_meta($post_id, '_alesta_keywords_extra', true) ?: '' ),
        ];
    }

    // =========================================================================
    // SAUVEGARDE
    // =========================================================================

    public function save( int $post_id, WP_Post $post ): void {
        if ( ! isset($_POST['alesta_seo_mb_nonce']) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_key(wp_unslash($_POST['alesta_seo_mb_nonce'])), self::NONCE_SAVE ) ) {
            return;
        }
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can('edit_post', $post_id) ) {
            return;
        }
        if ( ! in_array($post->post_type, $this->get_post_types(), true) ) {
            return;
        }

        $title     = sanitize_text_field(     isset($_POST['alesta_seo_title'])        ? wp_unslash($_POST['alesta_seo_title'])        : '' );
        $desc      = sanitize_textarea_field( isset($_POST['alesta_meta_description']) ? wp_unslash($_POST['alesta_meta_description']) : '' );
        $focus_kw  = sanitize_text_field(     isset($_POST['alesta_focus_keyword'])    ? wp_unslash($_POST['alesta_focus_keyword'])    : '' );
        $og_title  = sanitize_text_field(     isset($_POST['alesta_og_title'])         ? wp_unslash($_POST['alesta_og_title'])         : '' );
        $og_desc   = sanitize_textarea_field( isset($_POST['alesta_og_desc'])          ? wp_unslash($_POST['alesta_og_desc'])          : '' );
        $og_img_id = isset($_POST['alesta_og_image_id']) ? absint(wp_unslash($_POST['alesta_og_image_id'])) : 0;
        $tw_title  = sanitize_text_field(     isset($_POST['alesta_twitter_title'])    ? wp_unslash($_POST['alesta_twitter_title'])    : '' );
        $tw_desc   = sanitize_textarea_field( isset($_POST['alesta_twitter_desc'])     ? wp_unslash($_POST['alesta_twitter_desc'])     : '' );
        $canonical = esc_url_raw(             isset($_POST['alesta_canonical'])        ? wp_unslash($_POST['alesta_canonical'])        : '' );
        $kw_extra  = sanitize_text_field(     isset($_POST['alesta_keywords_extra'])   ? wp_unslash($_POST['alesta_keywords_extra'])   : '' );
        $noindex   = ! empty($_POST['alesta_noindex'])  ? 'noindex'  : 'index';
        $nofollow  = ! empty($_POST['alesta_nofollow']) ? 'nofollow' : 'follow';
        $robots    = $noindex . ',' . $nofollow;

        // ── Champs Alesta natifs ──
        update_post_meta($post_id, '_alesta_seo_title',        $title);
        update_post_meta($post_id, '_alesta_meta_description', $desc);
        update_post_meta($post_id, '_alesta_focus_keyword',    $focus_kw);
        update_post_meta($post_id, '_alesta_og_title',         $og_title);
        update_post_meta($post_id, '_alesta_og_desc',          $og_desc);
        update_post_meta($post_id, '_alesta_og_image_id',      $og_img_id);
        update_post_meta($post_id, '_alesta_twitter_title',    $tw_title);
        update_post_meta($post_id, '_alesta_twitter_desc',     $tw_desc);
        update_post_meta($post_id, '_alesta_canonical',        $canonical);
        update_post_meta($post_id, '_alesta_keywords_extra',   $kw_extra);
        update_post_meta($post_id, '_alesta_robots',           $robots);

        // ── Miroir Yoast (si actif) ──
        if ( defined('WPSEO_VERSION') ) {
            update_post_meta($post_id, '_yoast_wpseo_title',                 $title);
            update_post_meta($post_id, '_yoast_wpseo_metadesc',              $desc);
            update_post_meta($post_id, '_yoast_wpseo_focuskw',               $focus_kw);
            update_post_meta($post_id, '_yoast_wpseo_opengraph-title',       $og_title);
            update_post_meta($post_id, '_yoast_wpseo_opengraph-description', $og_desc);
            update_post_meta($post_id, '_yoast_wpseo_twitter-title',         $tw_title);
            update_post_meta($post_id, '_yoast_wpseo_twitter-description',   $tw_desc);
            if ( $canonical ) {
                update_post_meta($post_id, '_yoast_wpseo_canonical', $canonical);
            }
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', $noindex === 'noindex' ? '1' : '0');
        }

        // ── Miroir RankMath (si actif) ──
        if ( defined('RANK_MATH_VERSION') ) {
            update_post_meta($post_id, 'rank_math_title',                $title);
            update_post_meta($post_id, 'rank_math_description',          $desc);
            update_post_meta($post_id, 'rank_math_focus_keyword',        $focus_kw);
            update_post_meta($post_id, 'rank_math_facebook_title',       $og_title);
            update_post_meta($post_id, 'rank_math_facebook_description', $og_desc);
            update_post_meta($post_id, 'rank_math_twitter_title',        $tw_title);
            update_post_meta($post_id, 'rank_math_twitter_description',  $tw_desc);
            if ( $canonical ) {
                update_post_meta($post_id, 'rank_math_canonical_url', $canonical);
            }
        }
    }

    // =========================================================================
    // AJAX — Générer avec Claude
    // =========================================================================

    public function ajax_generate_ai(): void {
        check_ajax_referer(self::NONCE_AJAX, 'nonce');

        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $keyword = sanitize_text_field( isset($_POST['keyword']) ? wp_unslash($_POST['keyword']) : '' );
        if ( ! $post_id ) {
            wp_send_json_error(['message' => __('ID de post manquant.', 'alesta')]);
        }
        if ( ! current_user_can('edit_post', $post_id) ) {
            wp_send_json_error(['message' => __('Accès refusé.', 'alesta')]);
        }
        if ( ! class_exists('Alesta_Meta_Module') ) {
            wp_send_json_error(['message' => __('Module Title & Meta indisponible.', 'alesta')]);
        }

        $module = new Alesta_Meta_Module();
        $result = $module->generate_for_post($post_id, [
            'keyword'  => $keyword,
            'lang'     => 'fr',
            'tone'     => 'professionnel',
            'length'   => 'standard',
            'add_site' => false,
        ]);

        if ( is_wp_error($result) ) {
            wp_send_json_error(Alesta_API::error_payload($result));
        }

        wp_send_json_success([
            'title'   => (string) ($result['titles'][0]     ?? ''),
            'desc'    => (string) ($result['metas'][0]      ?? ''),
            'keyword' => (string) ($result['keyword_main']  ?? ''),
        ]);
    }
}
