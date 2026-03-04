<?php
/**
 * Site Health Monitor Module
 * Мониторинг здоровья сайта: PHP версии, памяти, диска, базы данных и т.д.
 */

if (!defined('ABSPATH')) exit;

class SO_Site_Health {
    
    /**
     * Получить полный статус здоровья сайта
     */
    public static function get_full_status() {
        return array(
            'php_version' => self::check_php_version(),
            'memory_usage' => self::check_memory_usage(),
            'disk_space' => self::check_disk_space(),
            'database_size' => self::check_database_size(),
            'post_count' => self::check_post_count(),
            'plugin_count' => self::check_plugin_count(),
            'theme_status' => self::check_theme_status(),
            'wp_version' => self::check_wp_version(),
            'debug_mode' => self::check_debug_mode(),
            'object_cache' => self::check_object_cache(),
            'cron_status' => self::check_cron_status(),
            'security' => self::check_security()
        );
    }
    
    /**
     * Проверка версии PHP
     */
    public static function check_php_version() {
        $current = PHP_VERSION;
        $recommended = '7.4';
        $minimum = '7.0';
        
        $status = 'good';
        $message = '';
        
        if (version_compare($current, $minimum, '<')) {
            $status = 'critical';
            $message = 'Критически старая версия PHP!';
        } elseif (version_compare($current, $recommended, '<')) {
            $status = 'warning';
            $message = 'Рекомендуется обновить PHP';
        } else {
            $message = 'Версия PHP актуальна';
        }
        
        return array(
            'label' => 'Версия PHP',
            'value' => $current,
            'status' => $status,
            'message' => $message,
            'recommended' => $recommended . '+'
        );
    }
    
    /**
     * Проверка использования памяти
     */
    public static function check_memory_usage() {
        $memory_limit = ini_get('memory_limit');
        $memory_used = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        
        $limit_bytes = self::parse_memory_limit($memory_limit);
        $usage_percent = ($memory_used / $limit_bytes) * 100;
        
        $status = 'good';
        if ($usage_percent > 80) {
            $status = 'critical';
        } elseif ($usage_percent > 60) {
            $status = 'warning';
        }
        
        return array(
            'label' => 'Использование памяти',
            'value' => size_format($memory_used) . ' / ' . $memory_limit,
            'status' => $status,
            'message' => sprintf('Использовано: %.1f%%', $usage_percent),
            'peak' => size_format($memory_peak)
        );
    }
    
    /**
     * Проверка дискового пространства
     */
    public static function check_disk_space() {
        $upload_dir = wp_upload_dir();
        $path = $upload_dir['basedir'];
        
        if (!@disk_free_space($path)) {
            return array(
                'label' => 'Дисковое пространство',
                'value' => 'Неизвестно',
                'status' => 'info',
                'message' => 'Не удалось определить'
            );
        }
        
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);
        $used = $total - $free;
        $percent_free = ($free / $total) * 100;
        
        $status = 'good';
        if ($percent_free < 10) {
            $status = 'critical';
        } elseif ($percent_free < 20) {
            $status = 'warning';
        }
        
        return array(
            'label' => 'Дисковое пространство',
            'value' => sprintf('%s свободно из %s', size_format($free), size_format($total)),
            'status' => $status,
            'message' => sprintf('Занято: %.1f%%', 100 - $percent_free),
            'free' => $free,
            'used' => $used
        );
    }
    
    /**
     * Проверка размера базы данных
     */
    public static function check_database_size() {
        global $wpdb;
        
        $result = $wpdb->get_row("
            SELECT 
                SUM(data_length + index_length) AS size,
                SUM(data_length) AS data,
                SUM(index_length) AS indexes
            FROM information_schema.TABLES
            WHERE table_schema = '" . DB_NAME . "'
        ");
        
        $size = $result ? $result->size : 0;
        $data = $result ? $result->data : 0;
        $indexes = $result ? $result->indexes : 0;
        
        $status = 'good';
        if ($size > 500 * 1024 * 1024) {
            $status = 'warning';
        }
        if ($size > 1024 * 1024 * 1024) {
            $status = 'critical';
        }
        
        return array(
            'label' => 'Размер базы данных',
            'value' => size_format($size),
            'status' => $status,
            'message' => sprintf('Данные: %s, Индексы: %s', size_format($data), size_format($indexes))
        );
    }
    
    /**
     * Проверка количества записей
     */
    public static function check_post_count() {
        $counts = wp_count_posts();
        $total = array_sum((array)$counts);
        
        $revisions = wp_count_posts('revision');
        $revision_count = $revisions ? array_sum((array)$revisions) : 0;
        
        $status = 'good';
        if ($revision_count > $total * 2) {
            $status = 'warning';
        }
        
        return array(
            'label' => 'Записи',
            'value' => sprintf('Всего: %d, Черновики: %d', $total, $counts->draft),
            'status' => $status,
            'message' => sprintf('Ревизий: %d', $revision_count),
            'revisions' => $revision_count
        );
    }
    
    /**
     * Проверка плагинов
     */
    public static function check_plugin_count() {
        $plugins = get_plugins();
        $active_plugins = get_option('active_plugins');
        
        $inactive_count = count($plugins) - count($active_plugins);
        
        $status = 'good';
        if ($inactive_count > 10) {
            $status = 'warning';
        }
        
        return array(
            'label' => 'Плагины',
            'value' => sprintf('Активно: %d / %d', count($active_plugins), count($plugins)),
            'status' => $status,
            'message' => sprintf('Неактивных: %d', $inactive_count)
        );
    }
    
    /**
     * Проверка темы
     */
    public static function check_theme_status() {
        $theme = wp_get_theme();
        $parent_theme = $theme->parent();
        
        $status = 'good';
        $message = 'Тема активна';
        
        if (!$theme->exists()) {
            $status = 'critical';
            $message = 'Тема не найдена!';
        }
        
        return array(
            'label' => 'Тема',
            'value' => $theme->get('Name') . ' ' . $theme->get('Version'),
            'status' => $status,
            'message' => $message,
            'author' => $theme->get('Author')
        );
    }
    
    /**
     * Проверка версии WordPress
     */
    public static function check_wp_version() {
        global $wp_version;

        $status = 'good';
        $message = 'Версия актуальна';

        // Проверяем наличие функции (доступна только в админке)
        if (function_exists('get_core_updates')) {
            $core_updates = get_core_updates();

            if ($core_updates && isset($core_updates[0]->response) && $core_updates[0]->response === 'latest') {
                $message = 'Версия актуальна';
            } elseif ($core_updates && isset($core_updates[0]->response) && $core_updates[0]->response === 'upgrade') {
                $status = 'warning';
                $message = 'Доступно обновление: ' . $core_updates[0]->version;
            }
        }

        return array(
            'label' => 'WordPress',
            'value' => $wp_version,
            'status' => $status,
            'message' => $message
        );
    }
    
    /**
     * Проверка режима отладки
     */
    public static function check_debug_mode() {
        $debug = defined('WP_DEBUG') && WP_DEBUG;
        
        $status = 'good';
        $message = 'Отключён';
        
        if ($debug) {
            $status = 'warning';
            $message = 'Включён (не рекомендуется на продакшене)';
        }
        
        return array(
            'label' => 'Режим отладки',
            'value' => $debug ? 'Включён' : 'Выключен',
            'status' => $status,
            'message' => $message
        );
    }
    
    /**
     * Проверка объектного кэша
     */
    public static function check_object_cache() {
        $has_object_cache = wp_using_ext_object_cache();
        
        $status = $has_object_cache ? 'good' : 'info';
        $message = $has_object_cache ? 'Redis/Memcached активен' : 'Рекомендуется настроить';
        
        return array(
            'label' => 'Объектный кэш',
            'value' => $has_object_cache ? 'Включён' : 'Выключен',
            'status' => $status,
            'message' => $message
        );
    }
    
    /**
     * Проверка WP-Cron
     */
    public static function check_cron_status() {
        $crons = _get_cron_array();
        $cron_count = 0;
        
        if ($crons) {
            foreach ($crons as $timestamp => $events) {
                $cron_count += count($events);
            }
        }
        
        $status = 'good';
        $message = sprintf('Запланировано событий: %d', $cron_count);
        
        if ($cron_count > 100) {
            $status = 'warning';
            $message = 'Слишком много задач в очереди';
        }
        
        return array(
            'label' => 'WP-Cron',
            'value' => $cron_count . ' событий',
            'status' => $status,
            'message' => $message
        );
    }
    
    /**
     * Проверка безопасности
     */
    public static function check_security() {
        $issues = array();
        $status = 'good';
        
        // Проверка логина admin
        $admin_exists = username_exists('admin');
        if ($admin_exists) {
            $issues[] = 'Существует пользователь "admin"';
            $status = 'warning';
        }
        
        // Проверка версии PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $issues[] = 'Устаревшая версия PHP';
            $status = 'warning';
        }
        
        return array(
            'label' => 'Безопасность',
            'value' => empty($issues) ? 'Нет проблем' : 'Найдены проблемы',
            'status' => $status,
            'message' => empty($issues) ? 'Всё в порядке' : implode(', ', $issues),
            'issues' => $issues
        );
    }
    
    /**
     * AJAX обработчик получения статуса
     */
    public static function ajax_get_status() {
        check_ajax_referer('so_admin_nonce', 'nonce');
        
        $status = self::get_full_status();
        
        // Подсчёт общего статуса
        $critical = 0;
        $warning = 0;
        
        foreach ($status as $check) {
            if ($check['status'] === 'critical') $critical++;
            if ($check['status'] === 'warning') $warning++;
        }
        
        $overall = 'good';
        if ($critical > 0) $overall = 'critical';
        elseif ($warning > 0) $overall = 'warning';
        
        wp_send_json_success(array(
            'status' => $status,
            'overall' => $overall,
            'critical_count' => $critical,
            'warning_count' => $warning
        ));
    }
    
    /**
     * Парсинг лимита памяти
     */
    private static function parse_memory_limit($limit) {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        
        switch ($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }
        
        return $limit;
    }
}
