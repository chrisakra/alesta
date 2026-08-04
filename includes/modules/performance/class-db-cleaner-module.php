<?php
defined('ABSPATH') || exit;

/**
 * Nettoyeur BDD — Logique métier (Alesta)
 * Toutes les méthodes sont statiques : analyse + nettoyage par catégorie.
 */
class Alesta_DB_Cleaner_Module {

    // =========================================================================
    // CATÉGORIES DISPONIBLES
    // =========================================================================

    /**
     * Retourne la liste des catégories avec libellé et icône.
     *
     * @return array<string, array{label:string, icon:string, description:string}>
     */
    public static function categories(): array {
        return [
            'revisions'          => [
                'label'       => 'Révisions d\'articles',
                'icon'        => '📝',
                'description' => 'Historique des versions sauvegardées automatiquement.',
            ],
            'auto_drafts'        => [
                'label'       => 'Brouillons automatiques',
                'icon'        => '🗒️',
                'description' => 'Sauvegardes temporaires créées par WordPress.',
            ],
            'trashed_posts'      => [
                'label'       => 'Articles / pages dans la corbeille',
                'icon'        => '🗑️',
                'description' => 'Contenus supprimés en attente de suppression définitive.',
            ],
            'spam_comments'      => [
                'label'       => 'Commentaires spam',
                'icon'        => '🚫',
                'description' => 'Commentaires marqués comme spam.',
            ],
            'trashed_comments'   => [
                'label'       => 'Commentaires dans la corbeille',
                'icon'        => '💬',
                'description' => 'Commentaires supprimés en attente de suppression définitive.',
            ],
            'expired_transients' => [
                'label'       => 'Transients expirés',
                'icon'        => '⏰',
                'description' => 'Données temporaires en cache dont la durée de vie est dépassée.',
            ],
            'orphan_postmeta'    => [
                'label'       => 'Métadonnées orphelines (posts)',
                'icon'        => '🔗',
                'description' => 'Métadonnées liées à des articles qui n\'existent plus.',
            ],
            'orphan_commentmeta' => [
                'label'       => 'Métadonnées orphelines (commentaires)',
                'icon'        => '🔗',
                'description' => 'Métadonnées liées à des commentaires qui n\'existent plus.',
            ],
            'pingbacks'          => [
                'label'       => 'Pingbacks & Trackbacks',
                'icon'        => '📡',
                'description' => 'Notifications automatiques entre blogs.',
            ],
        ];
    }

    // =========================================================================
    // ANALYSE
    // =========================================================================

    /**
     * Analyse toutes les catégories et retourne les compteurs + tailles estimées.
     *
     * @return array<string, array{count:int, size_kb:float}>
     */
    public static function analyze(): array {
        $result = [];
        foreach (array_keys(self::categories()) as $key) {
            $result[$key] = self::count($key);
        }
        return $result;
    }

    /**
     * Compte les éléments d'une catégorie et estime leur taille en Ko.
     *
     * @return array{count:int, size_kb:float}
     */
    public static function count( string $category ): array {
        global $wpdb;

        switch ($category) {

            case 'revisions':
                $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
                );
                $size  = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    "SELECT COALESCE(SUM(LENGTH(post_content)+LENGTH(post_title)+LENGTH(post_excerpt)),0)
                     FROM {$wpdb->posts} WHERE post_type = 'revision'"
                );
                break;

            case 'auto_drafts':
                $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"
                );
                $size  = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    "SELECT COALESCE(SUM(LENGTH(post_content)+LENGTH(post_title)),0)
                     FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"
                );
                break;

            case 'trashed_posts':
                $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'"
                );
                $size  = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    "SELECT COALESCE(SUM(LENGTH(post_content)+LENGTH(post_title)),0)
                     FROM {$wpdb->posts} WHERE post_status = 'trash'"
                );
                break;

            case 'spam_comments':
                $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'"
                );
                $size = 0;
                break;

            case 'trashed_comments':
                $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'"
                );
                $size = 0;
                break;

            case 'expired_transients':
                $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->options}
                         WHERE option_name LIKE %s AND option_value < %d",
                        '_transient_timeout_%',
                        time()
                    )
                );
                $size = 0;
                break;

            case 'orphan_postmeta':
                $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                     LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE p.ID IS NULL"
                );
                $size = 0;
                break;

            case 'orphan_commentmeta':
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- requête de nettoyage BDD, mise en cache non pertinente
                $count = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm
                     LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
                     WHERE c.comment_ID IS NULL"
                );
                $size = 0;
                break;

            case 'pingbacks':
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- requête de nettoyage BDD, mise en cache non pertinente
                $count = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->comments}
                     WHERE comment_type IN ('pingback','trackback')"
                );
                $size = 0;
                break;

            default:
                $count = 0;
                $size  = 0;
        }

        return [
            'count'   => $count,
            'size_kb' => $size > 0 ? round($size / 1024, 1) : 0.0,
        ];
    }

    // =========================================================================
    // NETTOYAGE
    // =========================================================================

    /**
     * Nettoie une catégorie et retourne le nombre d'éléments supprimés.
     */
    public static function clean( string $category ): int {
        global $wpdb;

        switch ($category) {

            case 'revisions':
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- requête de nettoyage BDD, mise en cache non pertinente
                $ids = $wpdb->get_col(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'"
                );
                $count = 0;
                foreach ($ids as $id) {
                    wp_delete_post_revision( (int) $id );
                    $count++;
                }
                return $count;

            case 'auto_drafts':
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- requête de nettoyage BDD, mise en cache non pertinente
                $ids = $wpdb->get_col(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"
                );
                $count = 0;
                foreach ($ids as $id) {
                    wp_delete_post( (int) $id, true );
                    $count++;
                }
                return $count;

            case 'trashed_posts':
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- requête de nettoyage BDD, mise en cache non pertinente
                $ids = $wpdb->get_col(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash'"
                );
                $count = 0;
                foreach ($ids as $id) {
                    wp_delete_post( (int) $id, true );
                    $count++;
                }
                return $count;

            case 'spam_comments':
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- requête de nettoyage BDD, mise en cache non pertinente
                $ids = $wpdb->get_col(
                    "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'spam'"
                );
                $count = 0;
                foreach ($ids as $id) {
                    wp_delete_comment( (int) $id, true );
                    $count++;
                }
                return $count;

            case 'trashed_comments':
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- requête de nettoyage BDD, mise en cache non pertinente
                $ids = $wpdb->get_col(
                    "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'trash'"
                );
                $count = 0;
                foreach ($ids as $id) {
                    wp_delete_comment( (int) $id, true );
                    $count++;
                }
                return $count;

            case 'expired_transients':
                /* Récupérer les noms des transients expirés, puis supprimer via l'API */
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- requête de nettoyage BDD, mise en cache non pertinente
                $transient_names = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT REPLACE(option_name, '_transient_timeout_', '')
                         FROM {$wpdb->options}
                         WHERE option_name LIKE %s AND option_value < %d",
                        '_transient_timeout_%',
                        time()
                    )
                );
                $count = 0;
                foreach ($transient_names as $name) {
                    delete_transient($name);
                    $count++;
                }
                return $count;

            case 'orphan_postmeta':
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- requête de nettoyage BDD, mise en cache non pertinente
                $deleted = $wpdb->query(
                    "DELETE pm FROM {$wpdb->postmeta} pm
                     LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE p.ID IS NULL"
                );
                return $deleted === false ? 0 : (int) $deleted;

            case 'orphan_commentmeta':
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- requête de nettoyage BDD, mise en cache non pertinente
                $deleted = $wpdb->query(
                    "DELETE cm FROM {$wpdb->commentmeta} cm
                     LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
                     WHERE c.comment_ID IS NULL"
                );
                return $deleted === false ? 0 : (int) $deleted;

            case 'pingbacks':
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- requête de nettoyage BDD, mise en cache non pertinente
                $ids = $wpdb->get_col(
                    "SELECT comment_ID FROM {$wpdb->comments}
                     WHERE comment_type IN ('pingback','trackback')"
                );
                $count = 0;
                foreach ($ids as $id) {
                    wp_delete_comment( (int) $id, true );
                    $count++;
                }
                return $count;

            default:
                return 0;
        }
    }

    /**
     * Nettoie toutes les catégories et retourne le détail.
     *
     * @return array<string, int>
     */
    public static function clean_all(): array {
        $results = [];
        foreach (array_keys(self::categories()) as $key) {
            $results[$key] = self::clean($key);
        }
        return $results;
    }
}
