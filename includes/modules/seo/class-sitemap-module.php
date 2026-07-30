<?php
defined('ABSPATH') || exit;

class Alesta_Sitemap_Module {

    /**
     * Returns the absolute path to sitemap.xml at the WordPress root.
     * Uses get_home_path() (official WP helper) instead of ABSPATH directly.
     */
    private static function sitemap_path(): string {
        if ( ! function_exists( 'get_home_path' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
        }
        return get_home_path() . 'sitemap.xml';
    }
    const LAST_GEN_KEY       = 'alesta_sitemap_last_gen';
    const LAST_PING_KEY      = 'alesta_sitemap_last_ping';
    const OPTIONS_KEY        = 'alesta_sitemap_options';
    const AUTO_REGEN_KEY     = 'alesta_sitemap_auto_regen';
    const DISABLE_NATIVE_KEY = 'alesta_sitemap_disable_native';
    const DEBOUNCE_KEY       = 'alesta_sitemap_regen_debounce';

    public function __construct() {
        add_action('wp_ajax_alesta_sitemap_read',          [$this, 'ajax_read']);
        add_action('wp_ajax_alesta_sitemap_generate',      [$this, 'ajax_generate']);
        add_action('wp_ajax_alesta_sitemap_ping',          [$this, 'ajax_ping']);
        add_action('wp_ajax_alesta_sitemap_delete',        [$this, 'ajax_delete']);
        add_action('wp_ajax_alesta_sitemap_save_settings', [$this, 'ajax_save_settings']);

        // Régénération automatique
        if (get_option(self::AUTO_REGEN_KEY, false)) {
            add_action('transition_post_status', [$this, 'on_post_change'], 10, 3);
            add_action('delete_post',            [$this, 'maybe_schedule_regen']);
            add_action('created_term',           [$this, 'maybe_schedule_regen']);
            add_action('edited_term',            [$this, 'maybe_schedule_regen']);
            add_action('delete_term',            [$this, 'maybe_schedule_regen']);
        }

        // Désactivation sitemaps natifs
        if (get_option(self::DISABLE_NATIVE_KEY, false)) {
            add_filter('wp_sitemaps_enabled', '__return_false');
            add_action('template_redirect',      [$this, 'block_native_sitemaps'], 1);
        }
    }

    // =========================================================================
    // DÉSACTIVATION SITEMAPS NATIFS (WP core + Yoast)
    // =========================================================================

    public function block_native_sitemaps(): void {
        // Yoast utilise le query var 'sitemap' pour ses sitemaps
        if (!empty(get_query_var('sitemap'))) {
            status_header(404);
            nocache_headers();
            exit;
        }
        // Yoast sitemap index
        if (!empty(get_query_var('sitemap-stylesheet'))) {
            status_header(404);
            nocache_headers();
            exit;
        }
    }

    // =========================================================================
    // AUTO-RÉGÉNÉRATION
    // =========================================================================

    public function on_post_change(string $new_status, string $old_status, \WP_Post $post): void {
        $watched = get_post_types(['public' => true]);
        if (!in_array($post->post_type, $watched, true)) return;
        if ($new_status === $old_status && $new_status !== 'publish') return;
        if (defined('WP_IMPORTING') && WP_IMPORTING) return;

        $this->maybe_schedule_regen();
    }

    public function maybe_schedule_regen(): void {
        // Debounce : une seule régénération toutes les 5 minutes max
        if (get_transient(self::DEBOUNCE_KEY)) return;
        // Ne régénère que si le fichier existe déjà (= l'utilisateur l'a configuré)
        if (!file_exists(self::sitemap_path())) return;

        set_transient(self::DEBOUNCE_KEY, 1, 5 * MINUTE_IN_SECONDS);
        $this->regenerate_silent();
    }

    public function regenerate_silent(): void {
        if (!$this->can_write()) return;
        $opts = $this->get_saved_options();
        $xml = $this->build_xml($opts);
        file_put_contents(self::sitemap_path(), $xml); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
        update_option(self::LAST_GEN_KEY, current_time('mysql'));
    }

    // =========================================================================
    // OPTIONS
    // =========================================================================

    private function default_options(): array {
        $post_types = ['post', 'page'];
        $taxonomies = ['category', 'post_tag'];

        if (post_type_exists('product')) {
            $post_types[] = 'product';
            $taxonomies[] = 'product_cat';
            $taxonomies[] = 'product_tag';
        }

        return [
            'post_types'         => $post_types,
            'include_images'     => true,
            'include_videos'     => true,
            'include_taxonomies' => true,
            'taxonomies'         => $taxonomies,
            'include_authors'    => false,
        ];
    }

    private function get_saved_options(): array {
        $saved = get_option(self::OPTIONS_KEY, []);
        return wp_parse_args($saved, $this->default_options());
    }

    private function sanitize_options(array $raw): array {
        $available_pt  = array_keys($this->get_available_post_types());
        $available_tax = array_keys($this->get_available_taxonomies());

        $post_types = [];
        if (!empty($raw['post_types']) && is_array($raw['post_types'])) {
            foreach ($raw['post_types'] as $pt) {
                $pt = sanitize_key($pt);
                if (in_array($pt, $available_pt, true)) {
                    $post_types[] = $pt;
                }
            }
        }

        $taxonomies = [];
        if (!empty($raw['taxonomies']) && is_array($raw['taxonomies'])) {
            foreach ($raw['taxonomies'] as $tax) {
                $tax = sanitize_key($tax);
                if (in_array($tax, $available_tax, true)) {
                    $taxonomies[] = $tax;
                }
            }
        }

        return [
            'post_types'         => $post_types ?: ['page'],
            'include_images'     => !empty($raw['include_images']),
            'include_videos'     => !empty($raw['include_videos']),
            'include_taxonomies' => !empty($raw['include_taxonomies']),
            'taxonomies'         => $taxonomies,
            'include_authors'    => !empty($raw['include_authors']),
        ];
    }

    // =========================================================================
    // DISCOVERY
    // =========================================================================

    private function get_available_post_types(): array {
        $result = [];

        $native = ['post' => 'Articles', 'page' => 'Pages'];
        foreach ($native as $slug => $label) {
            $count = (int) wp_count_posts($slug)->publish;
            $result[$slug] = ['label' => $label, 'count' => $count];
        }

        if (post_type_exists('product')) {
            $result['product'] = [
                'label' => 'Produits',
                'count' => (int) (wp_count_posts('product')->publish ?? 0),
            ];
        }

        $cpts = get_post_types(['public' => true, '_builtin' => false], 'objects');
        foreach ($cpts as $cpt) {
            if (in_array($cpt->name, ['product', 'attachment'], true)) continue;
            $result[$cpt->name] = [
                'label' => $cpt->label,
                'count' => (int) wp_count_posts($cpt->name)->publish,
            ];
        }

        return $result;
    }

    private function get_available_taxonomies(): array {
        $result = [];
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $skip = ['post_format', 'nav_menu', 'link_category', 'wp_theme', 'wp_template_part_area'];

        foreach ($taxonomies as $tax) {
            if (in_array($tax->name, $skip, true)) continue;
            $count = wp_count_terms(['taxonomy' => $tax->name, 'hide_empty' => true]);
            if (is_wp_error($count)) $count = 0;
            $result[$tax->name] = ['label' => $tax->label, 'count' => (int) $count];
        }

        return $result;
    }

    private function get_authors_count(): int {
        $authors = get_users(['who' => 'authors', 'has_published_posts' => true, 'fields' => 'ids']);
        return count($authors);
    }

    // =========================================================================
    // GÉNÉRATION XML
    // =========================================================================

    private function can_write(): bool {
        $path = self::sitemap_path(); // get_home_path() already loaded inside
        if (!file_exists($path)) {
            return is_writable( get_home_path() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
        }
        return is_writable($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
    }

    /**
     * Detecte les videos dans un post : YouTube, Vimeo, MP4/WebM auto-heberges.
     * Retourne un tableau de [thumbnail_loc, title, description, content_loc?, player_loc?]
     */
    private function get_post_videos(int $post_id): array {
        $videos  = [];
        $seen    = [];
        $post    = get_post($post_id);
        if (!$post) return $videos;

        $content     = $post->post_content;
        $title       = wp_strip_all_tags($post->post_title);
        $description = wp_trim_words(wp_strip_all_tags($post->post_content ?: $post->post_excerpt), 40, '...');
        if (empty($description)) $description = $title;

        // Miniature par defaut = image a la une du post
        $default_thumb = get_the_post_thumbnail_url($post_id, 'large') ?: '';

        // ── YouTube (iframe embed, watch?v=, youtu.be, block Gutenberg) ──────
        preg_match_all(
            '#(?:youtube\.com/(?:embed/|watch\?(?:[^"\'<\s]*&)?v=)|youtu\.be/)([a-zA-Z0-9_-]{11})#',
            $content,
            $yt
        );
        foreach ($yt[1] as $vid_id) {
            if (in_array('yt_' . $vid_id, $seen, true)) continue;
            $seen[] = 'yt_' . $vid_id;
            $videos[] = [
                'player_loc'   => 'https://www.youtube.com/embed/' . $vid_id,
                'thumbnail_loc'=> 'https://img.youtube.com/vi/' . $vid_id . '/maxresdefault.jpg',
                'title'        => $title,
                'description'  => $description,
            ];
        }

        // ── Vimeo (iframes embed) ─────────────────────────────────────────────
        preg_match_all(
            '#vimeo\.com/(?:video/)?([0-9]+)#',
            $content,
            $vimeo
        );
        foreach ($vimeo[1] as $vid_id) {
            if (in_array('vm_' . $vid_id, $seen, true)) continue;
            $seen[] = 'vm_' . $vid_id;
            // Tenter de récupérer la miniature Vimeo via oEmbed (sans cle API)
            $thumb = $default_thumb;
            $oembed = wp_remote_get(
                'https://vimeo.com/api/oembed.json?url=' . rawurlencode('https://vimeo.com/' . $vid_id) . '&width=640',
                ['timeout' => 5, 'sslverify' => false]
            );
            if (!is_wp_error($oembed)) {
                $data  = json_decode(wp_remote_retrieve_body($oembed), true);
                $thumb = !empty($data['thumbnail_url']) ? $data['thumbnail_url'] : $thumb;
            }
            $videos[] = [
                'player_loc'   => 'https://player.vimeo.com/video/' . $vid_id,
                'thumbnail_loc'=> $thumb,
                'title'        => $title,
                'description'  => $description,
            ];
        }

        // ── Videos auto-hebergees (balises <video> et <source>) ──────────────
        preg_match_all(
            '#<(?:video|source)[^>]+src=["\']([^"\']+\.(?:mp4|webm|ogv|ogg))["\']#i',
            $content,
            $self
        );
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        foreach ($self[1] as $src) {
            if (empty($src)) continue;
            if ($src[0] === '/') $src = home_url($src);
            if (strpos($src, 'http') !== 0) continue;
            $host = wp_parse_url($src, PHP_URL_HOST);
            if (!$host || $host !== $site_host) continue;
            if (in_array('self_' . $src, $seen, true)) continue;
            $seen[]   = 'self_' . $src;
            $videos[] = [
                'content_loc'  => $src,
                'thumbnail_loc'=> $default_thumb,
                'title'        => $title,
                'description'  => $description,
            ];
        }

        // ── Videos attachees au post (pièces jointes vidéo WordPress) ────────
        $attached = get_posts([
            'post_type'      => 'attachment',
            'post_parent'    => $post_id,
            'post_mime_type' => ['video/mp4', 'video/webm', 'video/ogg'],
            'posts_per_page' => 5,
            'fields'         => 'ids',
        ]);
        foreach ($attached as $att_id) {
            $src = wp_get_attachment_url($att_id);
            if (!$src || in_array('self_' . $src, $seen, true)) continue;
            $seen[]   = 'self_' . $src;
            $att_thumb = get_the_post_thumbnail_url($att_id, 'large') ?: $default_thumb;
            $videos[] = [
                'content_loc'  => $src,
                'thumbnail_loc'=> $att_thumb ?: $default_thumb,
                'title'        => get_the_title($att_id) ?: $title,
                'description'  => $description,
            ];
        }

        return $videos;
    }

    private function url_entry(string $loc, string $lastmod, string $changefreq, string $priority, array $images = [], array $videos = []): string {
        $lines   = [];
        $lines[] = '  <url>';
        $lines[] = '    <loc>' . esc_url($loc) . '</loc>';
        $lines[] = '    <lastmod>' . esc_html($lastmod) . '</lastmod>';
        $lines[] = '    <changefreq>' . esc_html($changefreq) . '</changefreq>';
        $lines[] = '    <priority>' . esc_html($priority) . '</priority>';

        foreach (array_slice($images, 0, 1000) as $img) {
            if (empty($img['url'])) continue;
            // esc_url() retourne '' pour une URL invalide — on skip pour eviter <image:loc></image:loc>
            $escaped_img_url = esc_url($img['url']);
            if (empty($escaped_img_url)) continue;
            // S'assurer que l'URL est absolue (http/https) — Google exige des URLs absolues
            if (strpos($escaped_img_url, 'http') !== 0) continue;
            $lines[] = '    <image:image>';
            $lines[] = '      <image:loc>' . $escaped_img_url . '</image:loc>';
            if (!empty($img['title'])) {
                $lines[] = '      <image:title>' . esc_html(mb_substr($img['title'], 0, 200)) . '</image:title>';
            }
            if (!empty($img['caption'])) {
                $lines[] = '      <image:caption>' . esc_html(mb_substr($img['caption'], 0, 200)) . '</image:caption>';
            }
            $lines[] = '    </image:image>';
        }

        // Videos
        foreach (array_slice($videos, 0, 5) as $vid) {
            $thumb = esc_url($vid['thumbnail_loc'] ?? '');
            if (empty($thumb)) continue;
            $lines[] = '    <video:video>';
            $lines[] = '      <video:thumbnail_loc>' . $thumb . '</video:thumbnail_loc>';
            $lines[] = '      <video:title>' . esc_html(mb_substr($vid['title'] ?? '', 0, 100)) . '</video:title>';
            $lines[] = '      <video:description>' . esc_html(mb_substr($vid['description'] ?? '', 0, 2048)) . '</video:description>';
            if (!empty($vid['content_loc'])) {
                $lines[] = '      <video:content_loc>' . esc_url($vid['content_loc']) . '</video:content_loc>';
            } elseif (!empty($vid['player_loc'])) {
                $lines[] = '      <video:player_loc>' . esc_url($vid['player_loc']) . '</video:player_loc>';
            }
            $lines[] = '    </video:video>';
        }

        $lines[] = '  </url>';
        return implode("\n", $lines);
    }

    private function get_post_images(int $post_id): array {
        $images = [];
        $seen   = [];

        $thumb_id = get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            $url = wp_get_attachment_url($thumb_id);
            if ($url && !in_array($url, $seen, true)) {
                $seen[]   = $url;
                $images[] = ['url' => $url, 'title' => get_the_title($thumb_id), 'caption' => wp_get_attachment_caption($thumb_id)];
            }
        }

        if (post_type_exists('product')) {
            $gallery_ids = get_post_meta($post_id, '_product_image_gallery', true);
            if (!empty($gallery_ids)) {
                foreach (explode(',', $gallery_ids) as $img_id) {
                    $img_id = (int) trim($img_id);
                    if (!$img_id) continue;
                    $url = wp_get_attachment_url($img_id);
                    if ($url && !in_array($url, $seen, true)) {
                        $seen[]   = $url;
                        $images[] = ['url' => $url, 'title' => get_the_title($img_id), 'caption' => wp_get_attachment_caption($img_id)];
                    }
                }
            }
        }

        $content = get_post_field('post_content', $post_id);
        if (!empty($content)) {
            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*/i', $content, $matches);
            $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
            foreach ($matches[1] as $src) {
                if (empty($src)) continue;
                if (strpos($src, 'data:') === 0) continue;
                // Convertir les URLs relatives en absolues
                if ($src[0] === '/') {
                    $src = home_url($src);
                }
                // N'accepter que les URLs http/https absolues
                if (strpos($src, 'http') !== 0) continue;
                // Restreindre au domaine du site
                $img_host = wp_parse_url($src, PHP_URL_HOST);
                if (!$img_host || $img_host !== $site_host) continue;
                // Verifier que l'URL pointe vers un fichier image (extension connue)
                $path = wp_parse_url($src, PHP_URL_PATH);
                if ($path && !preg_match('/\.(jpe?g|png|gif|webp|svg|avif)$/i', $path)) continue;
                if (!in_array($src, $seen, true)) {
                    $seen[]   = $src;
                    $att_id   = attachment_url_to_postid($src);
                    $images[] = [
                        'url'     => $src,
                        'title'   => $att_id ? get_the_title($att_id) : '',
                        'caption' => $att_id ? wp_get_attachment_caption($att_id) : '',
                    ];
                }
            }
        }

        return $images;
    }

    private function build_xml(array $opts): string {
        $use_images = !empty($opts['include_images']);
        $use_videos = !empty($opts['include_videos']);

        $ns = 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        if ($use_images) {
            $ns .= "\n        xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\"";
        }
        if ($use_videos) {
            $ns .= "\n        xmlns:video=\"http://www.google.com/schemas/sitemap-video/1.1\"";
        }

        $lines   = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<!-- Sitemap genere par Alesta v' . ALESTA_VERSION . ' (https://www.alesta-computer.com) -->';
        $lines[] = '<!-- Mis a jour le : ' . current_time('Y-m-d H:i:s') . ' -->';
        $lines[] = '<urlset ' . $ns . '>';

        // Homepage
        $home_images = $use_images ? $this->get_post_images((int) get_option('page_on_front')) : [];
        $lines[] = $this->url_entry(home_url('/'), current_time('Y-m-d'), 'weekly', '1.0', $home_images);

        // Post types
        if (!empty($opts['post_types'])) {
            $items = get_posts([
                'post_type'              => $opts['post_types'],
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'orderby'                => 'modified',
                'order'                  => 'DESC',
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
                'no_found_rows'          => true,
            ]);

            foreach ($items as $item) {
                $type = $item->post_type;
                if ($type === 'page') {
                    $priority = '0.8'; $changefreq = 'yearly';
                } elseif ($type === 'product') {
                    $priority = '0.7'; $changefreq = 'weekly';
                } else {
                    $priority = '0.6'; $changefreq = 'monthly';
                }
                $images  = $use_images ? $this->get_post_images($item->ID) : [];
                $videos  = $use_videos ? $this->get_post_videos($item->ID) : [];
                $lines[] = $this->url_entry(
                    get_permalink($item->ID),
                    gmdate('Y-m-d', strtotime($item->post_modified)),
                    $changefreq, $priority, $images, $videos
                );
            }
        }

        // Taxonomies
        if (!empty($opts['include_taxonomies']) && !empty($opts['taxonomies'])) {
            foreach ($opts['taxonomies'] as $taxonomy) {
                $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 0]);
                if (is_wp_error($terms)) continue;
                foreach ($terms as $term) {
                    $term_url = get_term_link($term);
                    if (is_wp_error($term_url)) continue;
                    $changefreq = in_array($taxonomy, ['product_cat', 'category'], true) ? 'weekly'  : 'monthly';
                    $priority   = in_array($taxonomy, ['product_cat', 'category'], true) ? '0.6' : '0.4';
                    $lines[] = $this->url_entry($term_url, current_time('Y-m-d'), $changefreq, $priority);
                }
            }
        }

        // Auteurs
        if (!empty($opts['include_authors'])) {
            $authors = get_users(['who' => 'authors', 'has_published_posts' => true, 'fields' => ['ID']]);
            foreach ($authors as $author) {
                $lines[] = $this->url_entry(get_author_posts_url($author->ID), current_time('Y-m-d'), 'monthly', '0.4');
            }
        }

        $lines[] = '</urlset>';
        return implode("\n", $lines) . "\n";
    }

    private function count_urls(array $opts): array {
        $counts = ['homepage' => 1, 'total' => 1];

        foreach ($opts['post_types'] ?? [] as $pt) {
            $n = (int) wp_count_posts($pt)->publish;
            $counts[$pt] = $n;
            $counts['total'] += $n;
        }

        $counts['terms'] = 0;
        if (!empty($opts['include_taxonomies'])) {
            foreach ($opts['taxonomies'] ?? [] as $tax) {
                $n = (int) wp_count_terms(['taxonomy' => $tax, 'hide_empty' => true]);
                $counts['terms'] += $n;
            }
            $counts['total'] += $counts['terms'];
        }

        $counts['authors'] = 0;
        if (!empty($opts['include_authors'])) {
            $counts['authors'] = $this->get_authors_count();
            $counts['total']  += $counts['authors'];
        }

        return $counts;
    }

    // =========================================================================
    // AJAX : Lire l'état
    // =========================================================================
    public function ajax_read(): void {
        check_ajax_referer('alesta_sitemap_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $saved_opts = $this->get_saved_options();

        $yoast_active = defined('WPSEO_VERSION');

        wp_send_json_success([
            'exists'         => file_exists(self::sitemap_path()),
            'can_write'      => $this->can_write(),
            'size'           => file_exists(self::sitemap_path()) ? filesize(self::sitemap_path()) : 0,
            'last_gen'       => get_option(self::LAST_GEN_KEY, ''),
            'last_ping'      => get_option(self::LAST_PING_KEY, ''),
            'url'            => home_url('/sitemap.xml'),
            'wp_native'      => home_url('/wp-sitemap.xml'),
            'yoast_active'   => $yoast_active,
            'available'      => [
                'post_types' => $this->get_available_post_types(),
                'taxonomies' => $this->get_available_taxonomies(),
                'authors'    => $this->get_authors_count(),
            ],
            'saved_options'  => $saved_opts,
            'counts'         => $this->count_urls($saved_opts),
            'auto_regen'     => (bool) get_option(self::AUTO_REGEN_KEY, false),
            'disable_native' => (bool) get_option(self::DISABLE_NATIVE_KEY, false),
        ]);
    }

    // =========================================================================
    // AJAX : Générer le sitemap
    // =========================================================================
    public function ajax_generate(): void {
        check_ajax_referer('alesta_sitemap_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        if (!$this->can_write()) {
            wp_send_json_error(['message' => 'Le dossier racine n\'est pas accessible en écriture.']);
        }

        $raw_opts = [];
        // The frontend POSTs a JSON-encoded options tree in $_POST['options'].
        // We wp_unslash() then json_decode(), and EVERY field is then sanitised
        // by $this->sanitize_options() — sanitize_key + whitelist for post_types
        // and taxonomies, booleans coerced via !empty(). This is the documented
        // WP.org pattern for structured AJAX payloads.
        // The guard reads $_POST['options'] as a raw JSON string. wp_unslash is
        // applied; sanitize_text_field would strip the JSON braces so we cannot
        // sanitize at the string level — every decoded field is sanitised
        // individually via sanitize_options() below.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- nonce verified above via check_ajax_referer(); JSON guarded with isset+is_string, decoded then sanitised field-by-field via sanitize_options()
        if ( ! empty( $_POST['options'] ) && is_string( wp_unslash( $_POST['options'] ) ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- nonce verified above; JSON decoded then sanitised field-by-field via sanitize_options() below
            $decoded = json_decode( wp_unslash( $_POST['options'] ), true );
            if (is_array($decoded)) {
                $raw_opts = $decoded;
            }
        }
        $opts = $this->sanitize_options($raw_opts ?: $this->get_saved_options());
        update_option(self::OPTIONS_KEY, $opts);

        $xml    = $this->build_xml($opts);
        $result = file_put_contents(self::sitemap_path(), $xml); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents

        if ($result === false) {
            wp_send_json_error(['message' => 'Échec de l\'écriture du sitemap.xml.']);
        }

        update_option(self::LAST_GEN_KEY, current_time('mysql'));
        $counts = $this->count_urls($opts);

        wp_send_json_success([
            'message'  => 'sitemap.xml genere avec succes (' . $counts['total'] . ' URLs).',
            'size'     => filesize(self::sitemap_path()),
            'last_gen' => get_option(self::LAST_GEN_KEY),
            'counts'   => $counts,
            'options'  => $opts,
        ]);
    }

    // =========================================================================
    // AJAX : Enregistrer les paramètres avancés
    // =========================================================================
    public function ajax_save_settings(): void {
        check_ajax_referer('alesta_sitemap_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        $auto_regen     = ! empty( sanitize_text_field( wp_unslash( $_POST['auto_regen']     ?? '' ) ) );
        $disable_native = ! empty( sanitize_text_field( wp_unslash( $_POST['disable_native'] ?? '' ) ) );

        update_option(self::AUTO_REGEN_KEY,     $auto_regen     ? '1' : '');
        update_option(self::DISABLE_NATIVE_KEY, $disable_native ? '1' : '');

        wp_send_json_success([
            'auto_regen'     => $auto_regen,
            'disable_native' => $disable_native,
        ]);
    }

    // =========================================================================
    // AJAX : Notifier Google & Bing
    // =========================================================================
    public function ajax_ping(): void {
        check_ajax_referer('alesta_sitemap_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        if (!file_exists(self::sitemap_path())) {
            wp_send_json_error(['message' => 'Le sitemap.xml n\'existe pas encore. Generez-le d\'abord.']);
        }

        $sitemap_url = home_url('/sitemap.xml');
        $results     = [];

        $resp = wp_remote_get($sitemap_url, ['timeout' => 10]);
        if (is_wp_error($resp)) {
            $results[] = ['engine' => 'Verification acces public', 'status' => 'error', 'message' => $resp->get_error_message(), 'link' => ''];
        } else {
            $code = wp_remote_retrieve_response_code($resp);
            $results[] = [
                'engine'  => 'Acces public sitemap.xml',
                'status'  => ($code === 200) ? 'ok' : 'error',
                'message' => 'HTTP ' . $code . ($code === 200 ? ' — fichier accessible' : ' — fichier inaccessible'),
                'link'    => '',
            ];
        }

        $results[] = [
            'engine'  => 'Google Search Console',
            'status'  => 'info',
            'message' => 'Soumettre manuellement via Search Console',
            'link'    => 'https://search.google.com/search-console/sitemaps?resource_id=' . urlencode(home_url('/')),
        ];
        $results[] = [
            'engine'  => 'Bing Webmaster Tools',
            'status'  => 'info',
            'message' => 'Soumettre manuellement via Bing Webmaster',
            'link'    => 'https://www.bing.com/webmasters/sitemaps?siteUrl=' . urlencode(home_url('/')),
        ];

        update_option(self::LAST_PING_KEY, current_time('mysql'));

        wp_send_json_success([
            'results'     => $results,
            'last_ping'   => get_option(self::LAST_PING_KEY),
            'sitemap_url' => $sitemap_url,
        ]);
    }

    // =========================================================================
    // AJAX : Supprimer le fichier
    // =========================================================================
    public function ajax_delete(): void {
        check_ajax_referer('alesta_sitemap_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();

        if (!file_exists(self::sitemap_path())) {
            wp_send_json_error(['message' => 'Le sitemap.xml n\'existe pas.']);
        }

        wp_delete_file(self::sitemap_path());
        if (file_exists(self::sitemap_path())) {
            wp_send_json_error(['message' => 'Impossible de supprimer le sitemap.xml. Vérifiez les permissions.']);
        }

        delete_option(self::LAST_GEN_KEY);
        wp_send_json_success(['message' => 'sitemap.xml supprimé avec succès.']);
    }
}
