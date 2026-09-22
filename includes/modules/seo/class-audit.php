<?php
defined('ABSPATH') || exit;

/**
 * Audit SEO du site — moteur (Alesta Free).
 *
 * Analyse SEO (title, meta, H1/H2), images sans alt, contenu trop court,
 * liens cassés et règles de performance statiques. Génération de
 * suggestions via Claude (clé API de l'utilisateur) à la demande.
 *
 * Porté depuis Alesta AI Pro (Alesta_AI_Audit). Mêmes clés post meta.
 */
class Alesta_Audit {

    /** @var Alesta_API|null */
    private $api = null;

    // Seuils.
    const MIN_CONTENT_WORDS  = 300;   // mots minimum par page/article
    const MIN_TITLE_LENGTH   = 30;    // caractères
    const MAX_TITLE_LENGTH   = 60;
    const MIN_META_LENGTH    = 120;
    const MAX_META_LENGTH    = 160;
    const LINK_CHECK_TIMEOUT = 8;     // secondes par lien

    /**
     * Client Claude (lazy) ou WP_Error si indisponible.
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

    // ─────────────────────────────────────────────────────────────────────────
    // POINT D'ENTRÉE PRINCIPAL
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Lance l'audit complet du site.
     *
     * @param array $options { post_types[], checks[] }
     */
    public function run(array $options = []): array {
        $post_types = $options['post_types'] ?? ['page', 'post'];
        $checks     = $options['checks']     ?? ['seo', 'images', 'content', 'links', 'performance'];

        $posts   = $this->get_all_posts($post_types);
        $results = [
            'summary'     => [],
            'seo'         => [],
            'images'      => [],
            'content'     => [],
            'links'       => [],
            'performance' => [],
            'total_posts' => count($posts),
        ];

        foreach ($posts as $post) {
            if ( in_array('seo',         $checks, true) ) { $this->audit_seo($post, $results['seo']); }
            if ( in_array('images',      $checks, true) ) { $this->audit_images($post, $results['images']); }
            if ( in_array('content',     $checks, true) ) { $this->audit_content($post, $results['content']); }
            if ( in_array('links',       $checks, true) ) { $this->audit_links($post, $results['links']); }
            if ( in_array('performance', $checks, true) ) { $this->audit_performance($post, $results['performance']); }
        }

        $results['summary'] = self::build_summary($results);
        return $results;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AUDIT SEO
    // ─────────────────────────────────────────────────────────────────────────

    public function audit_seo(WP_Post $post, array &$out): void {
        $issues = [];
        $score  = 100;

        $title = $this->get_seo_title($post);
        $meta  = $this->get_meta_description($post);
        $h1s   = $this->extract_headings($post->post_content, 'h1');
        $h2s   = $this->extract_headings($post->post_content, 'h2');

        // Title.
        if ( empty($title) ) {
            $issues[] = ['type' => 'error', 'msg' => __('Title tag absent', 'alesta')];
            $score -= 25;
        } elseif ( mb_strlen($title) < self::MIN_TITLE_LENGTH ) {
            /* translators: 1: number of characters, 2: minimum */
            $issues[] = ['type' => 'warning', 'msg' => sprintf(__('Title trop court (%1$d car.) - minimum %2$d', 'alesta'), mb_strlen($title), self::MIN_TITLE_LENGTH)];
            $score -= 10;
        } elseif ( mb_strlen($title) > self::MAX_TITLE_LENGTH ) {
            /* translators: 1: number of characters, 2: maximum */
            $issues[] = ['type' => 'warning', 'msg' => sprintf(__('Title trop long (%1$d car.) - maximum %2$d', 'alesta'), mb_strlen($title), self::MAX_TITLE_LENGTH)];
            $score -= 10;
        }

        // Meta description.
        if ( empty($meta) ) {
            $issues[] = ['type' => 'error', 'msg' => __('Meta description absente', 'alesta')];
            $score -= 25;
        } elseif ( mb_strlen($meta) < self::MIN_META_LENGTH ) {
            /* translators: %d: number of characters */
            $issues[] = ['type' => 'warning', 'msg' => sprintf(__('Meta trop courte (%d car.)', 'alesta'), mb_strlen($meta))];
            $score -= 10;
        } elseif ( mb_strlen($meta) > self::MAX_META_LENGTH ) {
            /* translators: %d: number of characters */
            $issues[] = ['type' => 'warning', 'msg' => sprintf(__('Meta trop longue (%d car.)', 'alesta'), mb_strlen($meta))];
            $score -= 10;
        }

        // H1.
        if ( count($h1s) === 0 ) {
            $issues[] = ['type' => 'error', 'msg' => __('Pas de balise H1', 'alesta')];
            $score -= 20;
        } elseif ( count($h1s) > 1 ) {
            /* translators: %d: number of H1 tags */
            $issues[] = ['type' => 'warning', 'msg' => sprintf(__('%d balises H1 - idéalement une seule', 'alesta'), count($h1s))];
            $score -= 10;
        }

        // H2.
        if ( count($h2s) === 0 && str_word_count(wp_strip_all_tags($post->post_content)) > 200 ) {
            $issues[] = ['type' => 'info', 'msg' => __('Aucun H2 - structurer le contenu est recommandé', 'alesta')];
            $score -= 5;
        }

        $out[] = [
            'post_id'   => $post->ID,
            'title'     => $post->post_title,
            'url'       => get_permalink($post->ID),
            'type'      => $post->post_type,
            'score'     => max(0, $score),
            'seo_title' => $title,
            'meta_desc' => $meta,
            'h1_count'  => count($h1s),
            'h2_count'  => count($h2s),
            'issues'    => $issues,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AUDIT IMAGES
    // ─────────────────────────────────────────────────────────────────────────

    private function audit_images(WP_Post $post, array &$out): void {
        preg_match_all('/<img[^>]+>/i', $post->post_content, $matches);
        $images      = $matches[0] ?? [];
        $missing_alt = [];

        foreach ($images as $img) {
            preg_match('#alt=["\']+([^"\'^]*?)["\']+#i', $img, $alt_match);
            $alt = trim($alt_match[1] ?? '');
            if ( $alt === '' ) {
                preg_match('#src=["\']+([^"\'^]+?)["\']+#i', $img, $src_match);
                $missing_alt[] = $src_match[1] ?? __('image inconnue', 'alesta');
            }
        }

        if ( count($images) > 0 ) {
            $out[] = [
                'post_id'      => $post->ID,
                'title'        => $post->post_title,
                'url'          => get_permalink($post->ID),
                'type'         => $post->post_type,
                'total_images' => count($images),
                'missing_alt'  => count($missing_alt),
                'missing_srcs' => array_slice($missing_alt, 0, 5),
                'status'       => count($missing_alt) === 0 ? 'ok' : (count($missing_alt) > 3 ? 'error' : 'warning'),
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AUDIT CONTENU
    // ─────────────────────────────────────────────────────────────────────────

    private function audit_content(WP_Post $post, array &$out): void {
        $text       = wp_strip_all_tags($post->post_content);
        $word_count = str_word_count($text);
        $issues     = [];

        if ( $word_count === 0 ) {
            $issues[] = ['type' => 'error', 'msg' => __('Page vide - aucun contenu', 'alesta')];
        } elseif ( $word_count < self::MIN_CONTENT_WORDS ) {
            /* translators: 1: word count, 2: recommended minimum */
            $issues[] = ['type' => 'warning', 'msg' => sprintf(__('%1$d mots - contenu trop court (minimum recommandé : %2$d)', 'alesta'), $word_count, self::MIN_CONTENT_WORDS)];
        }

        // Détection basique de contenu dupliqué (même extrait).
        if ( ! empty($post->post_excerpt) ) {
            $similar_posts = get_posts([
                's'              => substr($post->post_excerpt, 0, 50),
                'post__not_in'   => [$post->ID], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- exclusion du post courant
                'posts_per_page' => 1,
                'post_type'      => ['page', 'post'],
                'post_status'    => 'publish',
            ]);
            if ( ! empty($similar_posts) ) {
                /* translators: %s: title of the similar post */
                $issues[] = ['type' => 'info', 'msg' => sprintf(__('Contenu potentiellement similaire à "%s"', 'alesta'), $similar_posts[0]->post_title)];
            }
        }

        $out[] = [
            'post_id'    => $post->ID,
            'title'      => $post->post_title,
            'url'        => get_permalink($post->ID),
            'type'       => $post->post_type,
            'word_count' => $word_count,
            'status'     => $word_count === 0 ? 'error' : ($word_count < self::MIN_CONTENT_WORDS ? 'warning' : 'ok'),
            'issues'     => $issues,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AUDIT LIENS CASSÉS
    // ─────────────────────────────────────────────────────────────────────────

    private function audit_links(WP_Post $post, array &$out): void {
        preg_match_all('#<a[^>]+href=["\']+([^"\'^#][^"\']*?)["\']+[^>]*>#i', $post->post_content, $matches);
        $links  = array_unique($matches[1] ?? []);
        $broken = [];
        $ok     = 0;

        foreach ($links as $url) {
            // Ignorer mailto, tel, ancres, javascript.
            if ( preg_match('/^(mailto:|tel:|#|javascript:)/i', $url) ) {
                continue;
            }

            // URL absolue si relative.
            if ( ! preg_match('/^https?:\/\//i', $url) ) {
                $url = home_url($url);
            }

            $response = wp_remote_head($url, [
                'timeout'     => self::LINK_CHECK_TIMEOUT,
                'sslverify'   => false,
                'redirection' => 5,
                'user-agent'  => 'Alesta-LinkChecker/1.0',
            ]);

            if ( is_wp_error($response) ) {
                $broken[] = ['url' => $url, 'code' => 0, 'reason' => $response->get_error_message()];
            } else {
                $code = wp_remote_retrieve_response_code($response);
                if ( $code >= 400 ) {
                    $broken[] = ['url' => $url, 'code' => $code, 'reason' => 'HTTP ' . $code];
                } else {
                    $ok++;
                }
            }
        }

        if ( count($links) > 0 ) {
            $out[] = [
                'post_id'     => $post->ID,
                'title'       => $post->post_title,
                'url'         => get_permalink($post->ID),
                'type'        => $post->post_type,
                'total_links' => count($links),
                'ok_links'    => $ok,
                'broken'      => $broken,
                'status'      => empty($broken) ? 'ok' : (count($broken) > 2 ? 'error' : 'warning'),
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AUDIT PERFORMANCE (règles statiques)
    // ─────────────────────────────────────────────────────────────────────────

    private function audit_performance(WP_Post $post, array &$out): void {
        $issues  = [];
        $content = $post->post_content;

        // Images sans dimensions déclarées.
        preg_match_all('/<img(?![^>]*(width|height))[^>]+>/i', $content, $m);
        if ( ! empty($m[0]) ) {
            /* translators: %d: number of images */
            $issues[] = ['type' => 'warning', 'msg' => sprintf(__('%d image(s) sans attributs width/height (risque de layout shift)', 'alesta'), count($m[0]))];
        }

        // Iframes YouTube non lazy.
        preg_match_all('/<iframe[^>]+youtube[^>]*>/i', $content, $yt);
        foreach ($yt[0] as $iframe) {
            if ( stripos($iframe, 'loading="lazy"') === false ) {
                $issues[] = ['type' => 'info', 'msg' => __('Iframe YouTube sans loading="lazy"', 'alesta')];
                break;
            }
        }

        // Scripts inline lourds.
        preg_match_all('/<script[^>]*>(.*?)<\/script>/si', $content, $scripts);
        foreach ($scripts[1] as $script) {
            if ( strlen($script) > 2000 ) {
                /* translators: %s: size in KB */
                $issues[] = ['type' => 'info', 'msg' => sprintf(__('Script inline volumineux détecté (%s Ko) - externaliser recommandé', 'alesta'), round(strlen($script) / 1000, 1))];
                break;
            }
        }

        // Contenu très lourd.
        if ( strlen($content) > 100000 ) {
            /* translators: %d: size in KB */
            $issues[] = ['type' => 'warning', 'msg' => sprintf(__('Page très volumineuse (%d Ko brut) - penser à paginer', 'alesta'), round(strlen($content) / 1000))];
        }

        if ( ! empty($issues) ) {
            $out[] = [
                'post_id' => $post->ID,
                'title'   => $post->post_title,
                'url'     => get_permalink($post->ID),
                'type'    => $post->post_type,
                'issues'  => $issues,
                'status'  => 'warning',
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GÉNÉRATION DE SUGGESTIONS CLAUDE (à la demande, 1 post)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array|WP_Error
     */
    public function generate_seo_suggestions(int $post_id) {
        $post = get_post($post_id);
        if ( ! $post ) {
            return new WP_Error('not_found', __('Contenu introuvable.', 'alesta'));
        }
        $api = $this->api();
        if ( is_wp_error($api) ) {
            return $api;
        }

        $content = wp_strip_all_tags($post->post_content);
        $excerpt = mb_substr($content, 0, 1500);

        $seo_title     = $this->get_seo_title($post);
        $json_example  = '{"title_suggestions":["titre 1 (50-60 car.)","titre 2","titre 3"],';
        $json_example .= '"meta_suggestions":["meta 1 (120-160 car.)","meta 2","meta 3"],';
        $json_example .= '"h1_suggestion":"balise H1","h2_suggestions":["H2 1","H2 2","H2 3"],';
        $json_example .= '"keyword_main":"mot-cle principal","keywords_secondary":["kw2","kw3","kw4","kw5"],';
        $json_example .= '"content_advice":"conseil en 1 phrase"}';

        $prompt = "Tu es un expert SEO francophone. Analyse ce contenu WordPress et genere des suggestions.\n\n"
                . "Titre actuel : " . $post->post_title . "\n"
                . "Type : " . $post->post_type . "\n"
                . "Title SEO actuel : " . $seo_title . "\n"
                . "Contenu (extrait) :\n" . $excerpt . "\n\n"
                . "Regles :\n"
                . "- 3 titres differents (angles/accroches variees), chacun entre 50 et 60 caracteres si possible.\n"
                . "- 3 meta descriptions differentes (1 factuelle, 1 avec call-to-action, 1 avec benefices), chacune entre 120 et 160 caracteres.\n"
                . "- keyword_main = LE mot-cle principal le plus pertinent (1 seul).\n"
                . "- keywords_secondary = 3 a 5 variantes / cooccurrences proches.\n\n"
                . "Genere uniquement ce JSON (sans markdown ni backticks) :\n"
                . $json_example;

        $response = $api->ask($prompt, 1000);
        if ( is_wp_error($response) ) {
            return $response;
        }

        if ( preg_match('/\{.+\}/s', (string) $response, $m) ) {
            $data = json_decode($m[0], true);
            if ( is_array($data) ) {
                // Rétrocompat : ancien format "meta_description" (chaîne simple).
                if ( empty($data['meta_suggestions']) && ! empty($data['meta_description']) ) {
                    $data['meta_suggestions'] = [ $data['meta_description'] ];
                }
                return $data;
            }
        }
        return new WP_Error('parse_error', __('Réponse Claude invalide.', 'alesta'));
    }

    /**
     * Applique title + meta (+ focus keyword optionnel) sur un post.
     * Délègue à Alesta_Meta_Module::apply_to_post() (historique, Yoast,
     * RankMath, AIOSEO, rapport) quand il est chargé ; sinon écriture directe.
     */
    public function apply_seo_fields(int $post_id, string $title, string $meta, string $keyword = ''): bool {
        if ( class_exists('Alesta_Meta_Module') ) {
            $module = new Alesta_Meta_Module();
            $fields = ['title' => $title, 'meta' => $meta];
            if ( $keyword !== '' ) {
                $fields['keyword'] = $keyword;
            } else {
                $fields['keyword'] = (string) get_post_meta($post_id, '_alesta_focus_keyword', true);
            }
            return $module->apply_to_post($post_id, $fields);
        }

        // Natif Alesta.
        update_post_meta($post_id, '_alesta_seo_title',        sanitize_text_field($title));
        update_post_meta($post_id, '_alesta_meta_description', sanitize_text_field($meta));
        if ( $keyword !== '' ) {
            update_post_meta($post_id, '_alesta_focus_keyword', sanitize_text_field($keyword));
        }

        // Yoast SEO.
        if ( defined('WPSEO_VERSION') ) {
            update_post_meta($post_id, '_yoast_wpseo_title',    sanitize_text_field($title));
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($meta));
            if ( $keyword !== '' ) {
                update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($keyword));
            }
        }

        // RankMath.
        if ( defined('RANK_MATH_VERSION') ) {
            update_post_meta($post_id, 'rank_math_title',       sanitize_text_field($title));
            update_post_meta($post_id, 'rank_math_description', sanitize_text_field($meta));
            if ( $keyword !== '' ) {
                update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($keyword));
            }
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function get_all_posts(array $post_types): array {
        $post_types = array_values(array_filter($post_types, 'post_type_exists'));
        if ( empty($post_types) ) {
            return [];
        }
        return get_posts([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
    }

    private function get_seo_title(WP_Post $post): string {
        // 1. Alesta (source principale).
        $t = get_post_meta($post->ID, '_alesta_seo_title', true);
        if ( $t ) {
            return (string) $t;
        }
        // 2. RankMath.
        if ( defined('RANK_MATH_VERSION') ) {
            $t = get_post_meta($post->ID, 'rank_math_title', true);
            if ( $t ) {
                return (string) $t;
            }
        }
        // 3. Yoast.
        if ( defined('WPSEO_VERSION') ) {
            $t = get_post_meta($post->ID, '_yoast_wpseo_title', true);
            if ( $t ) {
                return (string) $t;
            }
        }
        // 4. Titre natif WP.
        return (string) $post->post_title;
    }

    private function get_meta_description(WP_Post $post): string {
        $m = get_post_meta($post->ID, '_alesta_meta_description', true);
        if ( $m ) {
            return (string) $m;
        }
        if ( defined('RANK_MATH_VERSION') ) {
            $m = get_post_meta($post->ID, 'rank_math_description', true);
            if ( $m ) {
                return (string) $m;
            }
        }
        if ( defined('WPSEO_VERSION') ) {
            $m = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
            if ( $m ) {
                return (string) $m;
            }
        }
        return (string) ($post->post_excerpt ?? '');
    }

    private function extract_headings(string $content, string $tag): array {
        preg_match_all('/<' . $tag . '[^>]*>(.*?)<\/' . $tag . '>/si', $content, $matches);
        return array_map('wp_strip_all_tags', $matches[1] ?? []);
    }

    /**
     * Résumé global : erreurs / avertissements comptés par issue (SEO +
     * contenu + performance + liens), score = moyenne des scores SEO.
     * Public et statique pour être réutilisé par le ré-audit ciblé.
     */
    public static function build_summary(array $results): array {
        $errors   = 0;
        $warnings = 0;
        $ok       = 0;

        foreach (['seo', 'content', 'performance', 'links'] as $check) {
            if ( empty($results[$check]) || ! is_array($results[$check]) ) {
                continue;
            }
            foreach ($results[$check] as $item) {
                if ( ! empty($item['issues']) && is_array($item['issues']) ) {
                    foreach ($item['issues'] as $i) {
                        $t = $i['type'] ?? 'info';
                        if ( $t === 'error' ) {
                            $errors++;
                        } elseif ( $t === 'warning' ) {
                            $warnings++;
                        }
                    }
                }
            }
        }
        foreach (($results['seo'] ?? []) as $item) {
            if ( empty($item['issues']) ) {
                $ok++;
            }
        }

        $seo_scores = array_column($results['seo'] ?? [], 'score');
        $avg_score  = count($seo_scores) ? (int) round(array_sum($seo_scores) / count($seo_scores)) : 0;

        $broken_total = 0;
        foreach ($results['links'] ?? [] as $l) {
            $broken_total += count($l['broken'] ?? []);
        }

        $missing_alt_total = 0;
        foreach ($results['images'] ?? [] as $i) {
            $missing_alt_total += (int) ($i['missing_alt'] ?? 0);
        }

        return [
            'avg_seo_score' => $avg_score,
            'errors'        => $errors,
            'warnings'      => $warnings,
            'ok'            => $ok,
            'broken_links'  => $broken_total,
            'missing_alt'   => $missing_alt_total,
            'global_score'  => $avg_score,
        ];
    }
}
