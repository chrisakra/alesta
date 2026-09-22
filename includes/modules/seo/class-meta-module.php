<?php
defined('ABSPATH') || exit;

/**
 * Title & Meta IA — moteur (Alesta Free).
 *
 * Analyse les balises title / meta description de toutes les pages,
 * génère des suggestions via Claude (clé API Anthropic de l'utilisateur)
 * et les applique sur les champs natifs Alesta + Yoast / RankMath / AIOSEO.
 *
 * Porté depuis Alesta AI Pro (Alesta_AI_Meta_Module). Les clés de données
 * (options + post meta) sont IDENTIQUES à celles du Pro pour qu'une
 * migration Free -> Pro conserve tout.
 *
 * Quand le Pro est actif (Alesta_AI_Meta_Module présent), le loader du
 * Free ne doit PAS instancier cette classe.
 */
class Alesta_Meta_Module {

    /** @var Alesta_API|null */
    private $api = null;

    // Options WP — mêmes clés que le Pro.
    const REPORT_KEY  = 'alesta_ai_meta_report';
    const HISTORY_KEY = 'alesta_ai_meta_history';
    const TOKENS_KEY  = 'alesta_ai_tokens_used';

    // Nonce propre au Free (distinct du Pro).
    const NONCE = 'alesta_meta_nonce';

    // Clés Yoast SEO (wp_postmeta).
    const YOAST_TITLE   = '_yoast_wpseo_title';
    const YOAST_META    = '_yoast_wpseo_metadesc';
    const YOAST_FOCUS   = '_yoast_wpseo_focuskw';
    const YOAST_OG_T    = '_yoast_wpseo_opengraph-title';
    const YOAST_OG_D    = '_yoast_wpseo_opengraph-description';
    const YOAST_TW_T    = '_yoast_wpseo_twitter-title';
    const YOAST_TW_D    = '_yoast_wpseo_twitter-description';
    const YOAST_NOINDEX = '_yoast_wpseo_meta-robots-noindex';
    const YOAST_CANON   = '_yoast_wpseo_canonical';

    public static function yoast_active(): bool {
        return defined('WPSEO_VERSION');
    }

    public static function rankmath_active(): bool {
        return defined('RANK_MATH_VERSION');
    }

    /** @var bool Les hooks ne sont enregistrés qu'une seule fois (instances utilitaires possibles). */
    private static $hooked = false;

    public function __construct() {
        if ( self::$hooked ) {
            return;
        }
        self::$hooked = true;

        // Actions AJAX : préfixe alesta_meta_* avec des noms distincts de ceux
        // du Pro (alesta_meta_run_report, ...) pour ne jamais entrer en collision.
        add_action('wp_ajax_alesta_meta_report',          [$this, 'ajax_run_report']);
        add_action('wp_ajax_alesta_meta_gen_one',         [$this, 'ajax_generate_one']);
        add_action('wp_ajax_alesta_meta_gen_batch',       [$this, 'ajax_generate_batch']);
        add_action('wp_ajax_alesta_meta_apply_fields',    [$this, 'ajax_apply']);
        add_action('wp_ajax_alesta_meta_revert_post',     [$this, 'ajax_revert']);
        add_action('wp_ajax_alesta_meta_csv',             [$this, 'ajax_export_csv']);
        add_action('wp_ajax_alesta_meta_dismiss_report',  [$this, 'ajax_dismiss']);
        add_action('wp_ajax_alesta_meta_fields',          [$this, 'ajax_get_seo_fields']);
        add_action('wp_ajax_alesta_meta_manual_save',     [$this, 'ajax_save_manual']);

        // Front : priorité 1 = avant le thème et Elementor (priorité défaut = 10).
        add_action('wp_head', [$this, 'output_meta_tags'], 1);
        // pre_get_document_title retourne la chaîne complète — WP n'ajoute pas le nom du site.
        add_filter('pre_get_document_title', [$this, 'filter_title_tag'], 5);
        // Canonical personnalisée + robots (noindex / nofollow) via les API natives WP.
        add_filter('get_canonical_url', [$this, 'filter_canonical_url'], 10, 2);
        add_filter('wp_robots',         [$this, 'filter_robots']);
    }

    // =========================================================================
    // CLIENT API (lazy)
    // =========================================================================

    /**
     * Retourne le client Claude ou un WP_Error si la classe n'est pas chargée.
     *
     * @return Alesta_API|WP_Error
     */
    private function api() {
        if ( $this->api instanceof Alesta_API ) {
            return $this->api;
        }
        if ( ! class_exists('Alesta_API') ) {
            return new WP_Error('no_api', __('Client API Alesta indisponible.', 'alesta'));
        }
        $this->api = new Alesta_API();
        return $this->api;
    }

    // =========================================================================
    // RAPPORT (sans appel Claude)
    // =========================================================================

    public function build_report(array $post_types = ['page', 'post', 'product']): array {
        $post_types = array_values(array_filter($post_types, 'post_type_exists'));
        if ( empty($post_types) ) {
            $post_types = ['page', 'post'];
        }

        $posts = get_posts([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $items       = [];
        $summary     = ['total' => 0, 'missing' => 0, 'too_short' => 0, 'too_long' => 0, 'duplicate' => 0, 'ok' => 0];
        $titles_seen = [];

        foreach ($posts as $post) {
            $title = $this->get_stored_title($post->ID) ?: $post->post_title;
            $meta  = $this->get_stored_meta($post->ID);

            $title_len = mb_strlen($title);
            $meta_len  = mb_strlen($meta);

            $title_status = 'ok';
            $meta_status  = 'ok';
            $issues       = [];

            // Analyse du title.
            if ( empty(trim($title)) ) {
                $title_status = 'missing';
                $issues[] = ['type' => 'error', 'field' => 'title', 'msg' => __('Title absent', 'alesta')];
            } elseif ( $title_len < 30 ) {
                $title_status = 'short';
                /* translators: %d: number of characters */
                $issues[] = ['type' => 'warning', 'field' => 'title', 'msg' => sprintf(__('Title trop court (%d car.)', 'alesta'), $title_len)];
            } elseif ( $title_len > 60 ) {
                $title_status = 'long';
                /* translators: %d: number of characters */
                $issues[] = ['type' => 'warning', 'field' => 'title', 'msg' => sprintf(__('Title trop long (%d car.)', 'alesta'), $title_len)];
            }

            // Analyse de la meta description.
            if ( empty(trim($meta)) ) {
                $meta_status = 'missing';
                $issues[] = ['type' => 'error', 'field' => 'meta', 'msg' => __('Meta description absente', 'alesta')];
            } elseif ( $meta_len < 120 ) {
                $meta_status = 'short';
                /* translators: %d: number of characters */
                $issues[] = ['type' => 'warning', 'field' => 'meta', 'msg' => sprintf(__('Meta trop courte (%d car.)', 'alesta'), $meta_len)];
            } elseif ( $meta_len > 160 ) {
                $meta_status = 'long';
                /* translators: %d: number of characters */
                $issues[] = ['type' => 'warning', 'field' => 'meta', 'msg' => sprintf(__('Meta trop longue (%d car.)', 'alesta'), $meta_len)];
            }

            // Doublons de title.
            $title_key = strtolower(trim($title));
            if ( isset($titles_seen[$title_key]) ) {
                /* translators: %s: title of the other page */
                $issues[] = ['type' => 'warning', 'field' => 'title', 'msg' => sprintf(__('Title en doublon avec "%s"', 'alesta'), $titles_seen[$title_key])];
                $title_status = 'duplicate';
                $summary['duplicate']++;
            } else {
                $titles_seen[$title_key] = $post->post_title;
            }

            // Statut global.
            $has_error     = $title_status === 'missing' || $meta_status === 'missing';
            $has_warning   = in_array($title_status, ['short', 'long', 'duplicate'], true) || in_array($meta_status, ['short', 'long'], true);
            $global_status = $has_error ? 'error' : ($has_warning ? 'warning' : 'ok');

            // Score.
            $score = 100;
            if ( $title_status === 'missing' )    { $score -= 40; }
            elseif ( $title_status === 'short' )  { $score -= 15; }
            elseif ( $title_status === 'long' )   { $score -= 10; }
            if ( $meta_status === 'missing' )     { $score -= 40; }
            elseif ( $meta_status === 'short' )   { $score -= 10; }
            elseif ( $meta_status === 'long' )    { $score -= 10; }
            $score = max(0, $score);

            $summary['total']++;
            $summary[ $global_status === 'error' ? 'missing' : ($global_status === 'warning' ? 'too_short' : 'ok') ]++;

            $items[] = [
                'post_id'      => $post->ID,
                'post_type'    => $post->post_type,
                'post_title'   => $post->post_title,
                'url'          => get_permalink($post->ID),
                'title'        => $title,
                'title_len'    => $title_len,
                'title_status' => $title_status,
                'meta'         => $meta,
                'meta_len'     => $meta_len,
                'meta_status'  => $meta_status,
                'score'        => $score,
                'status'       => $global_status,
                'issues'       => $issues,
                'generated'    => (bool) get_post_meta($post->ID, '_alesta_seo_title', true),
                'applied'      => (bool) get_post_meta($post->ID, '_alesta_meta_applied', true),
            ];
        }

        $report = [
            'generated_at' => current_time('mysql'),
            'post_types'   => $post_types,
            'summary'      => $summary,
            'items'        => $items,
            'tokens_used'  => (int) get_option(self::TOKENS_KEY, 0),
        ];

        update_option(self::REPORT_KEY, $report, false);
        return $report;
    }

    // =========================================================================
    // GÉNÉRATION CLAUDE
    // =========================================================================

    /**
     * Génère 3 titles + 3 metas + mots-clés pour un post.
     *
     * @return array|WP_Error
     */
    public function generate_for_post(int $post_id, array $options = []) {
        $post = get_post($post_id);
        if ( ! $post ) {
            return new WP_Error('not_found', __('Contenu introuvable.', 'alesta'));
        }

        $api = $this->api();
        if ( is_wp_error($api) ) {
            return $api;
        }

        $content = wp_strip_all_tags($post->post_content);
        $excerpt = mb_substr($content, 0, 1200);

        $tone     = $options['tone']      ?? 'professionnel';
        $lang     = $options['lang']      ?? 'fr';
        $length   = $options['length']    ?? 'standard';
        $keyword  = $options['keyword']   ?? '';
        $site     = $options['site_name'] ?? get_bloginfo('name');
        $add_site = ! empty($options['add_site']);

        if ( $length === 'short' ) {
            $len_guide = '45-55 caracteres';
        } elseif ( $length === 'long' ) {
            $len_guide = '60-70 caracteres';
        } else {
            $len_guide = '55-60 caracteres';
        }

        $kw_instruction   = $keyword  ? 'Le mot-cle principal a inclure absolument : "' . $keyword . '".' : '';
        $site_instruction = $add_site ? 'Ajouter " | ' . $site . '" a la fin du title.' : '';
        $lang_map         = ['fr' => 'francais', 'en' => 'anglais', 'es' => 'espagnol', 'de' => 'allemand', 'it' => 'italien'];
        $lang_label       = $lang_map[$lang] ?? 'francais';

        $json_structure  = '{"titles":["titre 1","titre 2","titre 3"],';
        $json_structure .= '"metas":["meta 1","meta 2","meta 3"],';
        $json_structure .= '"keyword_main":"mot cle principal",';
        $json_structure .= '"keywords_secondary":["kw2","kw3"],';
        $json_structure .= '"advice":"conseil en 1 phrase"}';

        $prompt = "Tu es un expert SEO. Genere des suggestions de title et meta description.\n\n"
                . "Page : " . $post->post_title . "\n"
                . "Type : " . $post->post_type . "\n"
                . "Langue : " . $lang_label . "\n"
                . "Ton : " . $tone . "\n"
                . "Longueur title cible : " . $len_guide . "\n"
                . "Meta description : 140-155 caracteres\n"
                . $kw_instruction . "\n"
                . $site_instruction . "\n"
                . "Contenu :\n" . $excerpt . "\n\n"
                . "Retourne uniquement ce JSON (sans markdown) :\n"
                . $json_structure;

        $response = $api->ask($prompt, 800);
        if ( is_wp_error($response) ) {
            return $response;
        }

        if ( preg_match('/\{.+\}/s', (string) $response, $m) ) {
            $data = json_decode($m[0], true);
            if ( is_array($data) ) {
                // Compteur approximatif (4 caractères ~ 1 token).
                $tokens = (int) (strlen($prompt) / 4) + (int) (strlen($response) / 4);
                $this->add_tokens($tokens);

                // Sauvegarder les suggestions (pas encore appliquées).
                update_post_meta($post_id, '_alesta_meta_suggestions', $data);
                update_post_meta($post_id, '_alesta_meta_generated_at', current_time('mysql'));
                return $data;
            }
        }
        return new WP_Error('parse_error', __('Réponse Claude invalide.', 'alesta'));
    }

    /**
     * Applique tous les champs SEO sur un post.
     *
     * $fields = [
     *   'title'          => string  Title <title> et moteurs de recherche
     *   'meta'           => string  Meta description
     *   'keyword'        => string  Mot-clé principal (focus keyword)
     *   'keywords_extra' => string  Mots-clés secondaires séparés par virgule
     *   'og_title'       => string  Titre OpenGraph
     *   'og_desc'        => string  Description OpenGraph
     *   'twitter_title'  => string  Titre Twitter/X
     *   'twitter_desc'   => string  Description Twitter/X
     *   'robots'         => string  index,follow | noindex,nofollow | ...
     *   'canonical'      => string  URL canonique si différente
     * ]
     */
    public function apply_to_post(int $post_id, array $fields): bool {
        $title          = sanitize_text_field($fields['title']          ?? '');
        $meta           = sanitize_text_field($fields['meta']           ?? '');
        $keyword        = sanitize_text_field($fields['keyword']        ?? '');
        $keywords_extra = sanitize_text_field($fields['keywords_extra'] ?? '');
        $og_title       = sanitize_text_field($fields['og_title']       ?? $title);
        $og_desc        = sanitize_text_field($fields['og_desc']        ?? $meta);
        $tw_title       = sanitize_text_field($fields['twitter_title']  ?? $title);
        $tw_desc        = sanitize_text_field($fields['twitter_desc']   ?? $meta);
        $robots         = $this->sanitize_robots($fields['robots']      ?? 'index,follow');
        $canonical      = esc_url_raw($fields['canonical']              ?? '');

        // Historique (pour "Annuler").
        $old     = $this->get_all_seo_fields($post_id);
        $history = get_option(self::HISTORY_KEY, []);
        if ( ! is_array($history) ) {
            $history = [];
        }
        $history[] = [
            'post_id'    => $post_id,
            'post_title' => get_the_title($post_id),
            'old'        => $old,
            'new'        => $fields,
            'applied_at' => current_time('mysql'),
        ];
        if ( count($history) > 100 ) {
            $history = array_slice($history, -100);
        }
        update_option(self::HISTORY_KEY, $history, false);

        // ── Stockage natif Alesta ─────────────────────────────────────────
        update_post_meta($post_id, '_alesta_seo_title',        $title);
        update_post_meta($post_id, '_alesta_meta_description', $meta);
        update_post_meta($post_id, '_alesta_focus_keyword',    $keyword);
        update_post_meta($post_id, '_alesta_keywords_extra',   $keywords_extra);
        update_post_meta($post_id, '_alesta_og_title',         $og_title);
        update_post_meta($post_id, '_alesta_og_desc',          $og_desc);
        update_post_meta($post_id, '_alesta_twitter_title',    $tw_title);
        update_post_meta($post_id, '_alesta_twitter_desc',     $tw_desc);
        update_post_meta($post_id, '_alesta_robots',           $robots);
        update_post_meta($post_id, '_alesta_canonical',        $canonical);
        update_post_meta($post_id, '_alesta_meta_applied',     1);

        // ── Yoast SEO ────────────────────────────────────────────────────
        if ( self::yoast_active() ) {
            update_post_meta($post_id, self::YOAST_TITLE, $title);
            update_post_meta($post_id, self::YOAST_META,  $meta);
            update_post_meta($post_id, self::YOAST_FOCUS, $keyword);
            update_post_meta($post_id, self::YOAST_OG_T,  $og_title);
            update_post_meta($post_id, self::YOAST_OG_D,  $og_desc);
            update_post_meta($post_id, self::YOAST_TW_T,  $tw_title);
            update_post_meta($post_id, self::YOAST_TW_D,  $tw_desc);
            if ( $canonical ) {
                update_post_meta($post_id, self::YOAST_CANON, $canonical);
            }
            $noindex = ( strpos($robots, 'noindex') !== false ) ? '1' : '0';
            update_post_meta($post_id, self::YOAST_NOINDEX, $noindex);
        }

        // ── RankMath ─────────────────────────────────────────────────────
        if ( self::rankmath_active() ) {
            update_post_meta($post_id, 'rank_math_title',                $title);
            update_post_meta($post_id, 'rank_math_description',          $meta);
            update_post_meta($post_id, 'rank_math_focus_keyword',        $keyword);
            update_post_meta($post_id, 'rank_math_facebook_title',       $og_title);
            update_post_meta($post_id, 'rank_math_facebook_description', $og_desc);
            update_post_meta($post_id, 'rank_math_twitter_title',        $tw_title);
            update_post_meta($post_id, 'rank_math_twitter_description',  $tw_desc);
            if ( $canonical ) {
                update_post_meta($post_id, 'rank_math_canonical_url', $canonical);
            }
            update_post_meta($post_id, 'rank_math_robots', explode(',', $robots));
        }

        // ── All in One SEO ────────────────────────────────────────────────
        if ( defined('AIOSEO_VERSION') ) {
            global $wpdb;
            $table = $wpdb->prefix . 'aioseo_posts';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table tierce AIOSEO, vérification d'existence
            if ( $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table tierce AIOSEO
                $existing = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . esc_sql($table) . ' WHERE post_id = %d', $post_id)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- nom de table échappé via esc_sql()
                $data = [
                    'post_id'             => $post_id,
                    'title'               => $title,
                    'description'         => $meta,
                    'keywords'            => $keyword,
                    'og_title'            => $og_title,
                    'og_description'      => $og_desc,
                    'twitter_title'       => $tw_title,
                    'twitter_description' => $tw_desc,
                    'updated'             => current_time('mysql'),
                ];
                if ( $existing ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table tierce AIOSEO
                    $wpdb->update($table, $data, ['post_id' => $post_id]);
                } else {
                    $data['created'] = current_time('mysql');
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- table tierce AIOSEO
                    $wpdb->insert($table, $data);
                }
            }
        }

        // Mettre à jour l'item correspondant dans le rapport en cache.
        $this->patch_report_item($post_id, $title, $meta);

        return true;
    }

    /**
     * Normalise une valeur robots vers l'une des 4 combinaisons autorisées.
     */
    private function sanitize_robots($robots): string {
        $robots  = strtolower(sanitize_text_field((string) $robots));
        $allowed = ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'];
        return in_array($robots, $allowed, true) ? $robots : 'index,follow';
    }

    /**
     * Met à jour un item du rapport en cache après un apply, pour que les
     * champs inline soient corrects après rechargement sans régénération.
     */
    private function patch_report_item(int $post_id, string $title, string $meta): void {
        $report = get_option(self::REPORT_KEY);
        if ( ! is_array($report) || empty($report['items']) ) {
            return;
        }
        $title_len = mb_strlen($title);
        $meta_len  = mb_strlen($meta);
        foreach ($report['items'] as &$item) {
            if ( (int) ($item['post_id'] ?? 0) !== $post_id ) {
                continue;
            }
            $item['title']        = $title;
            $item['title_len']    = $title_len;
            $item['title_status'] = $title_len === 0 ? 'missing' : ($title_len < 30 ? 'short' : ($title_len > 60 ? 'long' : 'ok'));
            $item['meta']         = $meta;
            $item['meta_len']     = $meta_len;
            $item['meta_status']  = $meta_len === 0 ? 'missing' : ($meta_len < 120 ? 'short' : ($meta_len > 160 ? 'long' : 'ok'));
            $item['applied']      = true;
            // Recalcul simple du score.
            $s = 100;
            if ( $item['title_status'] === 'missing' ) { $s -= 40; } elseif ( in_array($item['title_status'], ['short', 'long'], true) ) { $s -= 15; }
            if ( $item['meta_status']  === 'missing' ) { $s -= 40; } elseif ( in_array($item['meta_status'],  ['short', 'long'], true) ) { $s -= 10; }
            $item['score']  = max(0, $s);
            $item['status'] = ( $item['title_status'] === 'missing' || $item['meta_status'] === 'missing' ) ? 'error'
                : ( ( $item['title_status'] !== 'ok' || $item['meta_status'] !== 'ok' ) ? 'warning' : 'ok' );
            break;
        }
        unset($item);
        update_option(self::REPORT_KEY, $report, false);
    }

    /**
     * Récupère tous les champs SEO actuels d'un post.
     */
    public function get_all_seo_fields(int $post_id): array {
        return [
            'title'          => $this->get_stored_title($post_id),
            'meta'           => $this->get_stored_meta($post_id),
            'keyword'        => $this->get_stored_field($post_id, '_alesta_focus_keyword', self::YOAST_FOCUS, 'rank_math_focus_keyword'),
            'keywords_extra' => get_post_meta($post_id, '_alesta_keywords_extra', true) ?: '',
            'og_title'       => $this->get_stored_field($post_id, '_alesta_og_title',      self::YOAST_OG_T, 'rank_math_facebook_title'),
            'og_desc'        => $this->get_stored_field($post_id, '_alesta_og_desc',       self::YOAST_OG_D, 'rank_math_facebook_description'),
            'twitter_title'  => $this->get_stored_field($post_id, '_alesta_twitter_title', self::YOAST_TW_T, 'rank_math_twitter_title'),
            'twitter_desc'   => $this->get_stored_field($post_id, '_alesta_twitter_desc',  self::YOAST_TW_D, 'rank_math_twitter_description'),
            'robots'         => get_post_meta($post_id, '_alesta_robots', true)    ?: 'index,follow',
            'canonical'      => get_post_meta($post_id, '_alesta_canonical', true) ?: '',
        ];
    }

    private function get_stored_field(int $post_id, string $alesta_key, string $yoast_key, string $rm_key): string {
        if ( self::yoast_active() ) {
            $v = get_post_meta($post_id, $yoast_key, true);
            if ( $v ) {
                return (string) $v;
            }
        }
        if ( self::rankmath_active() ) {
            $v = get_post_meta($post_id, $rm_key, true);
            if ( $v ) {
                return (string) $v;
            }
        }
        return (string) ( get_post_meta($post_id, $alesta_key, true) ?: '' );
    }

    /**
     * Restaure les valeurs précédentes depuis l'historique.
     */
    public function revert_post(int $post_id): bool {
        $history = get_option(self::HISTORY_KEY, []);
        if ( ! is_array($history) ) {
            return false;
        }
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if ( (int) ($history[$i]['post_id'] ?? 0) !== $post_id ) {
                continue;
            }
            $entry = $history[$i];
            if ( ! empty($entry['old']) && is_array($entry['old']) ) {
                $old = $entry['old'];
            } else {
                // Format historique legacy (old_title / old_meta).
                $old = [
                    'title' => (string) ($entry['old_title'] ?? ''),
                    'meta'  => (string) ($entry['old_meta']  ?? ''),
                ];
            }
            $this->apply_to_post($post_id, $old);
            // apply_to_post() a ajouté une entrée d'historique : on la retire
            // ainsi que celle restaurée pour ne pas boucler sur "Annuler".
            $history = get_option(self::HISTORY_KEY, []);
            if ( is_array($history) ) {
                array_pop($history);
                array_splice($history, $i, 1);
                update_option(self::HISTORY_KEY, $history, false);
            }
            delete_post_meta($post_id, '_alesta_meta_applied');
            $this->patch_report_item($post_id, (string) ($old['title'] ?? ''), (string) ($old['meta'] ?? ''));
            $this->patch_report_applied($post_id, false);
            return true;
        }
        return false;
    }

    private function patch_report_applied(int $post_id, bool $applied): void {
        $report = get_option(self::REPORT_KEY);
        if ( ! is_array($report) || empty($report['items']) ) {
            return;
        }
        foreach ($report['items'] as &$item) {
            if ( (int) ($item['post_id'] ?? 0) === $post_id ) {
                $item['applied'] = $applied;
                break;
            }
        }
        unset($item);
        update_option(self::REPORT_KEY, $report, false);
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================

    /**
     * Vérifie la capacité admin (le nonce est vérifié inline dans chaque
     * handler pour rester lisible par les sniffs Plugin Check).
     */
    private function require_admin(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => __('Accès refusé.', 'alesta')]);
        }
    }

    /**
     * Lit les options de génération envoyées en POST.
     * Le nonce est vérifié par le handler appelant (check_ajax_referer).
     */
    private function read_options(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce vérifié dans le handler AJAX appelant
        return [
            'tone'     => sanitize_text_field( isset($_POST['tone'])    ? wp_unslash($_POST['tone'])    : 'professionnel' ),
            'lang'     => sanitize_key(        isset($_POST['lang'])    ? wp_unslash($_POST['lang'])    : 'fr' ),
            'length'   => sanitize_key(        isset($_POST['length'])  ? wp_unslash($_POST['length'])  : 'standard' ),
            'keyword'  => sanitize_text_field( isset($_POST['keyword']) ? wp_unslash($_POST['keyword']) : '' ),
            'add_site' => ! empty($_POST['add_site']),
        ];
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public function ajax_run_report(): void {
        check_ajax_referer(self::NONCE, 'nonce');
        $this->require_admin();
        $types = isset($_POST['post_types']) ? array_map('sanitize_key', (array) wp_unslash($_POST['post_types'])) : ['page', 'post'];
        $report = $this->build_report($types);
        wp_send_json_success($report);
    }

    public function ajax_generate_one(): void {
        check_ajax_referer(self::NONCE, 'nonce');
        $this->require_admin();
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if ( ! $post_id ) {
            wp_send_json_error(['message' => __('ID manquant.', 'alesta')]);
        }
        $result = $this->generate_for_post($post_id, $this->read_options());
        if ( is_wp_error($result) ) {
            wp_send_json_error(Alesta_API::error_payload($result));
        }
        wp_send_json_success($result);
    }

    public function ajax_generate_batch(): void {
        check_ajax_referer(self::NONCE, 'nonce');
        $this->require_admin();
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if ( ! $post_id ) {
            wp_send_json_error(['message' => __('ID manquant.', 'alesta')]);
        }
        // Mode batch : génère ET applique directement.
        $result = $this->generate_for_post($post_id, $this->read_options());
        if ( is_wp_error($result) ) {
            wp_send_json_error(Alesta_API::error_payload($result));
        }
        $title = (string) ($result['titles'][0] ?? '');
        $meta  = (string) ($result['metas'][0]  ?? '');
        if ( $title && $meta ) {
            $this->apply_to_post($post_id, [
                'title'   => $title,
                'meta'    => $meta,
                'keyword' => (string) ($result['keyword_main'] ?? ''),
            ]);
        }
        wp_send_json_success(['post_id' => $post_id, 'title' => $title, 'meta' => $meta]);
    }

    public function ajax_apply(): void {
        check_ajax_referer(self::NONCE, 'nonce');
        $this->require_admin();
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if ( ! $post_id ) {
            wp_send_json_error(['message' => __('ID manquant.', 'alesta')]);
        }
        $fields = [
            'title'          => sanitize_text_field( isset($_POST['title'])          ? wp_unslash($_POST['title'])          : '' ),
            'meta'           => sanitize_text_field( isset($_POST['meta'])           ? wp_unslash($_POST['meta'])           : '' ),
            'keyword'        => sanitize_text_field( isset($_POST['keyword'])        ? wp_unslash($_POST['keyword'])        : '' ),
            'keywords_extra' => sanitize_text_field( isset($_POST['keywords_extra']) ? wp_unslash($_POST['keywords_extra']) : '' ),
            'og_title'       => sanitize_text_field( isset($_POST['og_title'])       ? wp_unslash($_POST['og_title'])       : '' ),
            'og_desc'        => sanitize_text_field( isset($_POST['og_desc'])        ? wp_unslash($_POST['og_desc'])        : '' ),
            'twitter_title'  => sanitize_text_field( isset($_POST['twitter_title'])  ? wp_unslash($_POST['twitter_title'])  : '' ),
            'twitter_desc'   => sanitize_text_field( isset($_POST['twitter_desc'])   ? wp_unslash($_POST['twitter_desc'])   : '' ),
            'robots'         => sanitize_text_field( isset($_POST['robots'])         ? wp_unslash($_POST['robots'])         : 'index,follow' ),
            'canonical'      => esc_url_raw(         isset($_POST['canonical'])      ? wp_unslash($_POST['canonical'])      : '' ),
        ];
        $this->apply_to_post($post_id, $fields);
        wp_send_json_success([
            'message'  => __('Tous les champs SEO appliqués.', 'alesta'),
            'yoast'    => self::yoast_active(),
            'rankmath' => self::rankmath_active(),
            'target'   => self::target_label(),
        ]);
    }

    public function ajax_save_manual(): void {
        check_ajax_referer(self::NONCE, 'nonce');
        $this->require_admin();
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if ( ! $post_id ) {
            wp_send_json_error(['message' => __('ID manquant.', 'alesta')]);
        }
        $title = sanitize_text_field(     isset($_POST['title']) ? wp_unslash($_POST['title']) : '' );
        $meta  = sanitize_textarea_field( isset($_POST['meta'])  ? wp_unslash($_POST['meta'])  : '' );

        $fields = [
            'title'         => $title,
            'meta'          => $meta,
            'keyword'       => sanitize_text_field( isset($_POST['keyword']) ? wp_unslash($_POST['keyword']) : '' ),
            'og_title'      => $title,
            'og_desc'       => $meta,
            'twitter_title' => $title,
            'twitter_desc'  => $meta,
            'robots'        => 'index,follow',
            'canonical'     => '',
        ];
        $this->apply_to_post($post_id, $fields);

        wp_send_json_success([
            'message' => __('Sauvegarde réussie.', 'alesta'),
            'yoast'   => self::yoast_active(),
            'target'  => self::target_label(),
        ]);
    }

    public function ajax_get_seo_fields(): void {
        check_ajax_referer(self::NONCE, 'nonce');
        $this->require_admin();
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if ( ! $post_id ) {
            wp_send_json_error(['message' => __('ID manquant.', 'alesta')]);
        }
        wp_send_json_success($this->get_all_seo_fields($post_id));
    }

    public function ajax_revert(): void {
        check_ajax_referer(self::NONCE, 'nonce');
        $this->require_admin();
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if ( $post_id && $this->revert_post($post_id) ) {
            wp_send_json_success();
        }
        wp_send_json_error(['message' => __('Aucun historique trouvé.', 'alesta')]);
    }

    public function ajax_export_csv(): void {
        check_ajax_referer(self::NONCE, 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__('Accès refusé.', 'alesta') );
        }
        $report = get_option(self::REPORT_KEY, []);
        if ( ! is_array($report) || empty($report['items']) ) {
            wp_die( esc_html__('Aucun rapport.', 'alesta') );
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="alesta-meta-report-' . gmdate('Y-m-d') . '.csv"');
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- flux php://output, pas le système de fichiers
        $out = fopen('php://output', 'w');
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- flux php://output
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel.
        fputcsv($out, ['ID', 'Type', 'Titre WP', 'URL', 'Title SEO', 'Long. title', 'Statut title', 'Meta', 'Long. meta', 'Statut meta', 'Score'], ';');
        foreach ($report['items'] as $item) {
            fputcsv($out, [
                $item['post_id'], $item['post_type'], $item['post_title'], $item['url'],
                $item['title'], $item['title_len'], $item['title_status'],
                $item['meta'],  $item['meta_len'],  $item['meta_status'],
                $item['score'],
            ], ';');
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- flux php://output
        fclose($out);
        exit;
    }

    public function ajax_dismiss(): void {
        check_ajax_referer(self::NONCE, 'nonce');
        $this->require_admin();
        delete_option(self::REPORT_KEY);
        wp_send_json_success();
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Libellé de la destination d'écriture (Yoast / RankMath / Alesta).
     */
    public static function target_label(): string {
        if ( self::yoast_active() ) {
            return 'Yoast SEO';
        }
        if ( self::rankmath_active() ) {
            return 'RankMath';
        }
        return 'Alesta';
    }

    private function get_stored_title(int $post_id): string {
        if ( self::yoast_active() ) {
            $t = get_post_meta($post_id, self::YOAST_TITLE, true);
            if ( $t ) {
                return (string) $t;
            }
        }
        if ( self::rankmath_active() ) {
            $t = get_post_meta($post_id, 'rank_math_title', true);
            if ( $t ) {
                return (string) $t;
            }
        }
        return (string) ( get_post_meta($post_id, '_alesta_seo_title', true) ?: '' );
    }

    private function get_stored_meta(int $post_id): string {
        if ( self::yoast_active() ) {
            $m = get_post_meta($post_id, self::YOAST_META, true);
            if ( $m ) {
                return (string) $m;
            }
        }
        if ( self::rankmath_active() ) {
            $m = get_post_meta($post_id, 'rank_math_description', true);
            if ( $m ) {
                return (string) $m;
            }
        }
        return (string) ( get_post_meta($post_id, '_alesta_meta_description', true) ?: '' );
    }

    private function add_tokens(int $tokens): void {
        $current = (int) get_option(self::TOKENS_KEY, 0);
        update_option(self::TOKENS_KEY, $current + $tokens, false);
    }

    // =========================================================================
    // FRONT — balises <head>
    // =========================================================================

    /**
     * Un plugin SEO tiers actif gère lui-même le <head> : on n'interfère pas.
     */
    private static function third_party_seo_active(): bool {
        return self::yoast_active() || self::rankmath_active() || defined('AIOSEO_VERSION');
    }

    public function output_meta_tags(): void {
        if ( ! is_singular() || self::third_party_seo_active() ) {
            return;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return;
        }
        $meta = (string) get_post_meta($post_id, '_alesta_meta_description', true);

        // og:image — image personnalisée > image mise en avant.
        $og_img_id = (int) get_post_meta($post_id, '_alesta_og_image_id', true);
        if ( ! $og_img_id ) {
            $og_img_id = (int) get_post_thumbnail_id($post_id);
        }
        $og_img = $og_img_id ? wp_get_attachment_image_src($og_img_id, 'large') : false;

        $og_title = get_post_meta($post_id, '_alesta_og_title', true)
                 ?: get_post_meta($post_id, '_alesta_seo_title', true)
                 ?: get_the_title($post_id);
        $og_desc  = get_post_meta($post_id, '_alesta_og_desc', true) ?: $meta;
        $tw_title = get_post_meta($post_id, '_alesta_twitter_title', true) ?: $og_title;
        $tw_desc  = get_post_meta($post_id, '_alesta_twitter_desc', true)  ?: $og_desc;

        // Injection directe dans wp_head (priorité 1, avant le thème). En
        // s'exécutant en premier, notre meta description précède celle du
        // thème ; les moteurs utilisent la première balise trouvée.
        echo "\n<!-- Alesta SEO -->\n";
        if ( $meta ) {
            echo '<meta name="description" content="' . esc_attr($meta) . '">' . "\n";
        }
        echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
        echo '<meta property="og:type" content="' . esc_attr( is_front_page() ? 'website' : 'article' ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( (string) get_permalink($post_id) ) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo('name') ) . '">' . "\n";
        if ( $og_desc ) {
            echo '<meta property="og:description" content="' . esc_attr($og_desc) . '">' . "\n";
        }
        if ( $og_img ) {
            echo '<meta property="og:image" content="' . esc_url($og_img[0]) . '">' . "\n";
            echo '<meta property="og:image:width" content="' . esc_attr( (string) $og_img[1] ) . '">' . "\n";
            echo '<meta property="og:image:height" content="' . esc_attr( (string) $og_img[2] ) . '">' . "\n";
            echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        } else {
            echo '<meta name="twitter:card" content="summary">' . "\n";
        }
        echo '<meta name="twitter:title" content="' . esc_attr($tw_title) . '">' . "\n";
        if ( $tw_desc ) {
            echo '<meta name="twitter:description" content="' . esc_attr($tw_desc) . '">' . "\n";
        }
        echo "<!-- /Alesta SEO -->\n";
    }

    public function filter_title_tag( $title ) {
        if ( self::third_party_seo_active() ) {
            return $title;
        }
        if ( is_singular() ) {
            $t = get_post_meta(get_the_ID(), '_alesta_seo_title', true);
            if ( $t ) {
                return (string) $t; // Titre complet, sans que WP ajoute le nom du site.
            }
        }
        return $title;
    }

    /**
     * Canonical personnalisée (champ _alesta_canonical) via l'API native WP.
     */
    public function filter_canonical_url( $canonical_url, $post ) {
        if ( self::third_party_seo_active() || ! $post instanceof WP_Post ) {
            return $canonical_url;
        }
        $custom = get_post_meta($post->ID, '_alesta_canonical', true);
        return $custom ? esc_url_raw((string) $custom) : $canonical_url;
    }

    /**
     * noindex / nofollow par page via wp_robots (WP >= 5.7).
     */
    public function filter_robots( array $robots ): array {
        if ( ! is_singular() || self::third_party_seo_active() ) {
            return $robots;
        }
        $value = (string) get_post_meta(get_the_ID(), '_alesta_robots', true);
        if ( ! $value || $value === 'index,follow' ) {
            return $robots;
        }
        if ( strpos($value, 'noindex') !== false ) {
            $robots['noindex'] = true;
            unset($robots['max-image-preview']);
        }
        if ( strpos($value, 'nofollow') !== false ) {
            $robots['nofollow'] = true;
        }
        return $robots;
    }
}
