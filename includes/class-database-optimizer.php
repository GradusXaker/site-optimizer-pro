<?php
/**
 * Database Optimizer Module
 * Оптимизация базы данных: очистка мусора, оптимизация таблиц
 *
 * @package Site_Optimizer
 */

if (!defined('ABSPATH')) exit;

/**
 * Класс для оптимизации базы данных
 */
class SO_Database_Optimizer {

    /**
     * @var int Лимит записей для обработки за один раз
     */
    private static $batch_limit = 1000;

    /**
     * Оптимизация всех таблиц базы данных
     *
     * @return array
     */
    public static function optimize_tables() {
        global $wpdb;

        $results = array(
            'optimized' => 0,
            'saved_bytes' => 0,
            'tables' => array(),
            'errors' => array()
        );

        // Получаем список таблиц WordPress
        $tables = $wpdb->get_results("SHOW TABLES FROM `" . DB_NAME . "` LIKE '{$wpdb->prefix}%'");

        if (empty($tables)) {
            return $results;
        }

        foreach ($tables as $table) {
            $table_name = array_values((array)$table)[0];

            // Дополнительная проверка префикса
            if (strpos($table_name, $wpdb->prefix) !== 0) {
                continue;
            }

            // Проверяем существование таблицы
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            if (!$exists) {
                continue;
            }

            // Оптимизация таблицы
            $optimize = $wpdb->get_row("OPTIMIZE TABLE `$table_name`");

            if ($optimize) {
                $results['optimized']++;
                $results['tables'][] = $table_name;

                // Логируем ошибки если есть
                if (isset($optimize->Msg_text) && strpos($optimize->Msg_text, 'OK') === false) {
                    $results['errors'][] = array(
                        'table' => $table_name,
                        'message' => $optimize->Msg_text
                    );
                    error_log('Site Optimizer: Ошибка оптимизации таблицы ' . $table_name . ': ' . $optimize->Msg_text);
                }
            }
        }

        return $results;
    }

    /**
     * Очистка ревизий записей
     *
     * @param int $limit Количество сохраняемых ревизий на запись
     * @return array
     */
    public static function clean_post_revisions($limit = 3) {
        global $wpdb;

        $deleted = 0;

        // Получаем все записи у которых есть ревизии
        $parents = $wpdb->get_col("
            SELECT DISTINCT post_parent
            FROM $wpdb->posts
            WHERE post_type = 'revision'
            AND post_parent > 0
        ");

        foreach ($parents as $parent_id) {
            // Получаем количество ревизий для этой записи
            $count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM $wpdb->posts
                WHERE post_type = 'revision'
                AND post_parent = %d
            ", $parent_id));

            // Если ревизий больше чем лимит, удаляем старые
            if ($count > $limit) {
                $revisions_to_delete = $wpdb->get_col($wpdb->prepare("
                    SELECT ID FROM $wpdb->posts
                    WHERE post_type = 'revision'
                    AND post_parent = %d
                    ORDER BY post_date DESC
                    LIMIT %d, 999999
                ", $parent_id, $limit));

                foreach ($revisions_to_delete as $revision_id) {
                    if (wp_delete_post($revision_id, true)) {
                        $deleted++;
                    }
                }
            }
        }

        return array('deleted' => $deleted);
    }

    /**
     * Очистка корзины (trash)
     *
     * @return array
     */
    public static function clean_trash() {
        global $wpdb;

        $deleted = 0;

        // Получаем все записи в корзине
        $trash = $wpdb->get_results("
            SELECT ID, post_type FROM $wpdb->posts
            WHERE post_status = 'trash'
        ");

        foreach ($trash as $item) {
            if (wp_delete_post($item->ID, true)) {
                $deleted++;
            }
        }

        // Очистка комментариев в корзине
        $comments_trash = $wpdb->get_col("
            SELECT comment_ID FROM $wpdb->comments
            WHERE comment_approved = 'trash'
        ");

        foreach ($comments_trash as $comment_id) {
            if (wp_delete_comment($comment_id, true)) {
                $deleted++;
            }
        }

        return array('deleted' => $deleted);
    }

    /**
     * Очистка транзиентов
     *
     * @return array
     */
    public static function clean_transients() {
        global $wpdb;

        $deleted = 0;

        // Обычные транзиенты
        $deleted += $wpdb->query("
            DELETE FROM $wpdb->options
            WHERE option_name LIKE '_transient_%'
            AND option_name NOT LIKE '_transient_timeout_%'
        ");

        // Транзиенты сайта
        $deleted += $wpdb->query("
            DELETE FROM $wpdb->options
            WHERE option_name LIKE '_site_transient_%'
            AND option_name NOT LIKE '_site_transient_timeout_%'
        ");

        // Просроченные транзиенты
        $deleted += $wpdb->query("
            DELETE a, b FROM $wpdb->options a, $wpdb->options b
            WHERE a.option_name LIKE '_transient_timeout_%'
            AND b.option_name = CONCAT('_transient_', SUBSTRING(a.option_name, 17))
            AND a.option_value < UNIX_TIMESTAMP()
        ");

        // Просроченные транзиенты сайта
        $deleted += $wpdb->query("
            DELETE a, b FROM $wpdb->options a, $wpdb->options b
            WHERE a.option_name LIKE '_site_transient_timeout_%'
            AND b.option_name = CONCAT('_site_transient_', SUBSTRING(a.option_name, 21))
            AND a.option_value < UNIX_TIMESTAMP()
        ");

        return array('deleted' => $deleted);
    }

    /**
     * Очистка спам-комментариев
     *
     * @param int $days Хранить спам комментариев (дней)
     * @return array
     */
    public static function clean_spam_comments($days = 15) {
        global $wpdb;

        $deleted = 0;

        // Получаем спам комментарии старше указанного количества дней
        $spam = $wpdb->get_col($wpdb->prepare("
            SELECT comment_ID FROM $wpdb->comments
            WHERE comment_approved = 'spam'
            AND comment_date_gmt < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));

        foreach ($spam as $comment_id) {
            if (wp_delete_comment($comment_id, true)) {
                $deleted++;
            }
        }

        return array('deleted' => $deleted);
    }

    /**
     * Очистка записей в черновиках авто-сохранения
     *
     * @return array
     */
    public static function clean_autosave_drafts() {
        global $wpdb;

        $deleted = $wpdb->query("
            DELETE FROM $wpdb->posts
            WHERE post_type = 'revision'
            AND post_name LIKE '%autosave%'
        ");

        return array('deleted' => $deleted);
    }

    /**
     * Очистка таблицы комментариев (удалённые мета-данные)
     *
     * @return array
     */
    public static function clean_comment_meta_orphans() {
        global $wpdb;

        $deleted = $wpdb->query("
            DELETE FROM $wpdb->commentmeta
            WHERE comment_id NOT IN (SELECT comment_id FROM $wpdb->comments)
        ");

        return array('deleted' => $deleted);
    }

    /**
     * Очистка таблицы постов (удалённые мета-данные)
     *
     * @return array
     */
    public static function clean_post_meta_orphans() {
        global $wpdb;

        $deleted = $wpdb->query("
            DELETE FROM $wpdb->postmeta
            WHERE post_id NOT IN (SELECT ID FROM $wpdb->posts)
        ");

        return array('deleted' => $deleted);
    }

    /**
     * Оптимизация таблицы postmeta (удаление пустых записей)
     *
     * @return array
     */
    public static function clean_empty_postmeta() {
        global $wpdb;

        $deleted = $wpdb->query("
            DELETE FROM $wpdb->postmeta
            WHERE meta_value = '' OR meta_value IS NULL
        ");

        return array('deleted' => $deleted);
    }

    /**
     * Очистка таблицы termmeta
     *
     * @return array
     */
    public static function clean_term_meta_orphans() {
        global $wpdb;

        $deleted = $wpdb->query("
            DELETE FROM $wpdb->termmeta
            WHERE term_id NOT IN (SELECT term_id FROM $wpdb->terms)
        ");

        return array('deleted' => $deleted);
    }

    /**
     * Очистка пользовательских мета-данных
     *
     * @return array
     */
    public static function clean_user_meta_orphans() {
        global $wpdb;

        $deleted = $wpdb->query("
            DELETE FROM $wpdb->usermeta
            WHERE user_id NOT IN (SELECT ID FROM $wpdb->users)
        ");

        return array('deleted' => $deleted);
    }

    /**
     * Полная очистка базы данных
     *
     * @return array
     */
    public static function full_cleanup() {
        $results = array(
            'revisions' => self::clean_post_revisions(),
            'trash' => self::clean_trash(),
            'transients' => self::clean_transients(),
            'spam' => self::clean_spam_comments(),
            'autosave' => self::clean_autosave_drafts(),
            'comment_meta_orphans' => self::clean_comment_meta_orphans(),
            'post_meta_orphans' => self::clean_post_meta_orphans(),
            'empty_postmeta' => self::clean_empty_postmeta(),
            'term_meta_orphans' => self::clean_term_meta_orphans(),
            'user_meta_orphans' => self::clean_user_meta_orphans()
        );

        $total_deleted = 0;
        foreach ($results as $result) {
            if (isset($result['deleted'])) {
                $total_deleted += $result['deleted'];
            }
        }

        $results['total_deleted'] = $total_deleted;

        return $results;
    }

    /**
     * Получить статистику базы данных
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;

        $stats = array();

        // Количество записей по типам
        $posts = $wpdb->get_results("
            SELECT post_type, COUNT(*) as count
            FROM $wpdb->posts
            GROUP BY post_type
        ");

        foreach ($posts as $post) {
            $stats['posts_' . $post->post_type] = (int) $post->count;
        }

        // Комментарии
        $stats['comments'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments");
        $stats['spam_comments'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = 'spam'");
        $stats['pending_comments'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = '0'");

        // Пользователи
        $stats['users'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users");

        // Опции
        $stats['options'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->options");

        // Транзиенты
        $stats['transients'] = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM $wpdb->options
            WHERE option_name LIKE '%_transient_%'
        ");

        // Ревизии
        $stats['revisions'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'revision'");

        // Записи в корзине
        $stats['trash'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'trash'");

        // Размер базы
        $result = $wpdb->get_row("
            SELECT
                SUM(data_length + index_length) AS size,
                SUM(data_length) AS data,
                SUM(index_length) AS indexes,
                SUM(data_free) AS free
            FROM information_schema.TABLES
            WHERE table_schema = '" . DB_NAME . "'
        ");

        $stats['database_size'] = $result ? (int) $result->size : 0;
        $stats['database_data'] = $result ? (int) $result->data : 0;
        $stats['database_indexes'] = $result ? (int) $result->indexes : 0;
        $stats['database_free'] = $result ? (int) $result->free : 0;

        // Количество таблиц
        $stats['tables_count'] = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE table_schema = '" . DB_NAME . "'
        ");

        return $stats;
    }

    /**
     * AJAX обработчик оптимизации
     */
    public static function ajax_optimize() {
        // Проверка nonce
        check_ajax_referer('so_admin_nonce', 'nonce');

        // Проверка прав доступа
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Недостаточно прав для выполнения операции', 'site-optimizer')
            ));
        }

        // Проверка: не выполняется ли уже оптимизация
        if (get_transient('so_db_optimization_running')) {
            wp_send_json_error(array(
                'message' => __('Оптимизация БД уже выполняется', 'site-optimizer')
            ));
        }

        // Устанавливаем флаг выполнения
        set_transient('so_db_optimization_running', true, 300);

        try {
            // Полная очистка
            $cleanup = self::full_cleanup();

            // Оптимизация таблиц
            $optimize = self::optimize_tables();

            // Снимаем флаг
            delete_transient('so_db_optimization_running');

            // Обновление статистики
            $stats = get_option('so_stats', array());
            $stats['db_optimizations'] = isset($stats['db_optimizations']) ? $stats['db_optimizations'] + 1 : 1;
            update_option('so_stats', $stats, false);

            wp_send_json_success(array(
                'message' => sprintf(
                    __('Очистка завершена! Удалено записей: %d, Таблиц оптимизировано: %d', 'site-optimizer'),
                    $cleanup['total_deleted'],
                    $optimize['optimized']
                ),
                'cleanup' => $cleanup,
                'optimize' => $optimize
            ));
        } catch (Exception $e) {
            delete_transient('so_db_optimization_running');

            wp_send_json_error(array(
                'message' => __('Ошибка оптимизации БД: ', 'site-optimizer') . $e->getMessage()
            ));
        }
    }

    /**
     * Проверка требований для оптимизации БД
     *
     * @return array
     */
    public static function check_requirements() {
        global $wpdb;

        $requirements = array(
            'db_connection' => false,
            'db_permissions' => array(
                'select' => false,
                'delete' => false,
                'optimize' => false
            )
        );

        // Проверка подключения к БД
        if ($wpdb->dbh) {
            $requirements['db_connection'] = true;
        }

        // Проверка прав SELECT
        $test = $wpdb->get_var("SELECT 1");
        if ($test !== null) {
            $requirements['db_permissions']['select'] = true;
        }

        // Проверка прав DELETE (тестовая запись)
        $test_table = $wpdb->prefix . 'so_test';
        $wpdb->query("CREATE TEMPORARY TABLE `$test_table` (id INT)");
        $wpdb->query("INSERT INTO `$test_table` VALUES (1)");
        $delete_result = $wpdb->query("DELETE FROM `$test_table` WHERE id = 1");
        $requirements['db_permissions']['delete'] = ($delete_result !== false);

        // Проверка прав OPTIMIZE
        $optimize_result = $wpdb->get_row("OPTIMIZE TABLE `$test_table`");
        $requirements['db_permissions']['optimize'] = ($optimize_result !== null);

        $requirements['can_optimize'] = $requirements['db_connection']
            && $requirements['db_permissions']['select']
            && $requirements['db_permissions']['delete']
            && $requirements['db_permissions']['optimize'];

        return $requirements;
    }
}
