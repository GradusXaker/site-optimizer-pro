<?php
/**
 * Plugin Name: Site Optimizer Pro
 * Plugin URI: https://example.com/site-optimizer
 * Description: Комплексная оптимизация сайта: сжатие изображений без потерь, мониторинг здоровья сайта, оптимизация базы данных
 * Version: 1.1.0
 * Author: Admin
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: site-optimizer
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) exit;

/**
 * Проверка минимальных требований
 */
define('SO_MIN_PHP_VERSION', '7.2');
define('SO_MIN_WP_VERSION', '5.0');

/**
 * Проверка версии PHP
 */
if (version_compare(PHP_VERSION, SO_MIN_PHP_VERSION, '<')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>' . sprintf(
            __('Site Optimizer Pro требует PHP версии %s или выше. Ваша версия: %s', 'site-optimizer'),
            SO_MIN_PHP_VERSION,
            PHP_VERSION
        ) . '</p></div>';
    });
    return;
}

/**
 * Проверка версии WordPress
 */
global $wp_version;
if (version_compare($wp_version, SO_MIN_WP_VERSION, '<')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>' . sprintf(
            __('Site Optimizer Pro требует WordPress версии %s или выше. Ваша версия: %s', 'site-optimizer'),
            SO_MIN_WP_VERSION,
            $wp_version
        ) . '</p></div>';
    });
    return;
}

/**
 * Проверка расширения GD
 */
if (!extension_loaded('gd')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>' . __('Site Optimizer Pro требует расширения GD для оптимизации изображений.', 'site-optimizer') . '</p></div>';
    });
}

/**
 * Проверка расширения Imagick (опционально, но рекомендуется)
 */
if (!extension_loaded('imagick')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-warning"><p>' . __('Site Optimizer Pro: расширение Imagick не найдено. Рекомендуется установить для лучшей оптимизации изображений.', 'site-optimizer') . '</p></div>';
    });
}

// Определение путей и URL
define('SO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SO_VERSION', '1.1.0');
define('SO_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Загрузка текстового домена для локализации
 */
add_action('plugins_loaded', function() {
    load_plugin_textdomain('site-optimizer', false, dirname(SO_PLUGIN_BASENAME) . '/languages');
});

// Подключение модулей
require_once SO_PLUGIN_PATH . 'includes/class-image-optimizer.php';
require_once SO_PLUGIN_PATH . 'includes/class-site-health.php';
require_once SO_PLUGIN_PATH . 'includes/class-database-optimizer.php';
require_once SO_PLUGIN_PATH . 'includes/class-theme-integration.php';
require_once SO_PLUGIN_PATH . 'admin/class-admin-page.php';

/**
 * Главный класс плагина
 */
class Site_Optimizer {

    /**
     * @var Site_Optimizer|null
     */
    private static $instance = null;

    /**
     * Получить экземпляр класса
     *
     * @return Site_Optimizer
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Конструктор (приватный для синглтона)
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Инициализация хуков
     */
    private function init_hooks() {
        // Хуки активации/деактивации
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('Site_Optimizer', 'uninstall'));

        // Админ-меню
        add_action('admin_menu', array('SO_Admin_Page', 'add_admin_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);

        // AJAX обработчики
        add_action('wp_ajax_so_optimize_images', array('SO_Image_Optimizer', 'ajax_optimize'));
        add_action('wp_ajax_so_optimize_database', array('SO_Database_Optimizer', 'ajax_optimize'));
        add_action('wp_ajax_so_get_health_status', array('SO_Site_Health', 'ajax_get_status'));
        add_action('wp_ajax_so_purge_transients', array($this, 'ajax_purge_transients'));
        add_action('wp_ajax_so_quick_optimize', array($this, 'ajax_quick_optimize'));

        // Cron хуки
        add_action('so_daily_optimization', array($this, 'scheduled_optimization'));

        // Хуки оптимизации изображений
        add_filter('wp_generate_attachment_metadata', array('SO_Image_Optimizer', 'optimize_on_upload'), 10, 2);

        // Ссылка "Настройки" на странице плагинов
        add_filter('plugin_action_links_' . SO_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));

        // Еженедельная проверка здоровья сайта
        add_action('so_weekly_health_check', array($this, 'weekly_health_check'));
    }

    /**
     * Активация плагина
     */
    public function activate() {
        // Создаём папку для оптимизированных изображений
        $upload_dir = wp_upload_dir();
        $so_dir = $upload_dir['basedir'] . '/site-optimizer';
        if (!file_exists($so_dir)) {
            wp_mkdir_p($so_dir);
        }

        // Планируем ежедневную оптимизацию
        if (!wp_next_scheduled('so_daily_optimization')) {
            wp_schedule_event(time(), 'daily', 'so_daily_optimization');
        }

        // Планируем еженедельную проверку здоровья
        if (!wp_next_scheduled('so_weekly_health_check')) {
            wp_schedule_event(time(), 'weekly', 'so_weekly_health_check');
        }

        // Сохраняем время активации
        update_option('so_activation_time', time());

        // Инициализируем статистику
        $stats = get_option('so_stats');
        if (!is_array($stats)) {
            update_option('so_stats', array(
                'images_optimized' => 0,
                'space_saved' => 0,
                'optimizations_run' => 0,
                'db_optimizations' => 0,
                'last_optimization' => 0,
                'last_health_check' => 0
            ));
        }

        // Устанавливаем опции по умолчанию
        add_option('so_settings', array(
            'enable_image_optimization' => true,
            'enable_webp' => true,
            'enable_database_cleanup' => true,
            'enable_cron' => true,
            'image_quality' => 85,
            'auto_optimize_on_upload' => true
        ));

        // Флешим rewrite rules на случай чего
        flush_rewrite_rules();
    }

    /**
     * Деактивация плагина
     */
    public function deactivate() {
        // Очищаем запланированные события
        wp_clear_scheduled_hook('so_daily_optimization');
        wp_clear_scheduled_hook('so_weekly_health_check');

        // Сохраняем текущую статистику перед деактивацией
        $stats = get_option('so_stats');
        if (is_array($stats)) {
            $stats['deactivated_at'] = time();
            update_option('so_stats', $stats);
        }
    }

    /**
     * Удаление плагина (полная очистка)
     */
    public static function uninstall() {
        // Удаляем все опции
        delete_option('so_stats');
        delete_option('so_settings');
        delete_option('so_activation_time');

        // Очищаем запланированные события
        wp_clear_scheduled_hook('so_daily_optimization');
        wp_clear_scheduled_hook('so_weekly_health_check');

        // Удаляем папку с оптимизированными изображениями
        $upload_dir = wp_upload_dir();
        $so_dir = $upload_dir['basedir'] . '/site-optimizer';
        if (is_dir($so_dir)) {
            self::delete_directory($so_dir);
        }

        // Очищаем транзиенты плагина
        delete_transient('so_health_status');
        delete_transient('so_optimization_status');
    }

    /**
     * Рекурсивное удаление директории
     *
     * @param string $dir
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::delete_directory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Запланированная оптимизация
     */
    public function scheduled_optimization() {
        $settings = get_option('so_settings', array());

        // Оптимизация изображений
        if (!empty($settings['enable_image_optimization'])) {
            SO_Image_Optimizer::optimize_all_images();
        }

        // Оптимизация базы данных
        if (!empty($settings['enable_database_cleanup'])) {
            SO_Database_Optimizer::optimize_tables();
            SO_Database_Optimizer::clean_post_revisions();
            SO_Database_Optimizer::clean_trash();
        }

        // Обновление статистики
        $stats = get_option('so_stats', array());
        $stats['optimizations_run'] = isset($stats['optimizations_run']) ? $stats['optimizations_run'] + 1 : 1;
        $stats['last_optimization'] = time();
        update_option('so_stats', $stats);
    }

    /**
     * Еженедельная проверка здоровья
     */
    public function weekly_health_check() {
        $status = SO_Site_Health::get_full_status();

        // Проверяем критические проблемы
        $critical_issues = array();
        foreach ($status as $key => $check) {
            if (isset($check['status']) && $check['status'] === 'critical') {
                $critical_issues[] = $check['label'];
            }
        }

        // Отправляем уведомление администратору если есть критические проблемы
        if (!empty($critical_issues) && function_exists('wp_mail')) {
            $admin_email = get_option('admin_email');
            $subject = sprintf(__('Site Optimizer: Критические проблемы на сайте %s', 'site-optimizer'), get_bloginfo('name'));
            $message = sprintf(
                __("Обнаружены следующие критические проблемы:\n\n%s\n\nПожалуйста, проверьте панель управления Site Optimizer Pro.", 'site-optimizer'),
                implode("\n- ", $critical_issues)
            );
            wp_mail($admin_email, $subject, $message);
        }

        // Обновляем статистику
        $stats = get_option('so_stats', array());
        $stats['last_health_check'] = time();
        update_option('so_stats', $stats);
    }

    /**
     * Добавление ссылки на настройки в списке плагинов
     *
     * @param array $links
     * @return array
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('tools.php?page=site-optimizer') . '">' . __('Настройки', 'site-optimizer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Добавление меню в админ-бар
     *
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) return;

        // Основное меню
        $wp_admin_bar->add_node(array(
            'id' => 'site-optimizer',
            'title' => '<span class="ab-icon dashicons dashicons-performance"></span><span class="ab-label">' . __('Site Optimizer', 'site-optimizer') . '</span>',
            'href' => admin_url('tools.php?page=site-optimizer'),
            'meta' => array('class' => 'site-optimizer-menu')
        ));

        // Быстрая оптимизация
        $wp_admin_bar->add_node(array(
            'id' => 'so-quick-optimize',
            'parent' => 'site-optimizer',
            'title' => __('Быстрая оптимизация', 'site-optimizer'),
            'href' => wp_nonce_url(admin_url('admin-ajax.php?action=so_quick_optimize'), 'so_quick_optimize'),
            'meta' => array('target' => '_self')
        ));

        // Статус сайта
        $wp_admin_bar->add_node(array(
            'id' => 'so-site-status',
            'parent' => 'site-optimizer',
            'title' => __('Статус сайта', 'site-optimizer'),
            'href' => admin_url('tools.php?page=site-optimizer#health')
        ));

        // Настройки
        $wp_admin_bar->add_node(array(
            'id' => 'so-settings',
            'parent' => 'site-optimizer',
            'title' => __('Настройки', 'site-optimizer'),
            'href' => admin_url('tools.php?page=site-optimizer#settings')
        ));
    }

    /**
     * Подключение стилей и скриптов в админке
     *
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'site-optimizer') === false) return;

        wp_enqueue_style(
            'so-admin-css',
            SO_PLUGIN_URL . 'admin/style.css',
            array(),
            SO_VERSION
        );

        wp_enqueue_script(
            'so-admin-js',
            SO_PLUGIN_URL . 'admin/script.js',
            array('jquery'),
            SO_VERSION,
            true
        );

        wp_localize_script('so-admin-js', 'so_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('so_admin_nonce'),
            'quick_optimize_nonce' => wp_create_nonce('so_quick_optimize'),
            'strings' => array(
                'confirm_optimize' => __('Вы уверены, что хотите запустить оптимизацию?', 'site-optimizer'),
                'optimizing' => __('Оптимизация...', 'site-optimizer'),
                'completed' => __('Оптимизация завершена!', 'site-optimizer'),
                'error' => __('Произошла ошибка', 'site-optimizer')
            )
        ));
    }

    /**
     * AJAX обработчик очистки транзиентов
     */
    public function ajax_purge_transients() {
        check_ajax_referer('so_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'site-optimizer')));
        }

        global $wpdb;
        $deleted = $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_%' OR option_name LIKE '%_site_transient_%'");

        wp_send_json_success(array(
            'message' => sprintf(__('Удалено транзиентов: %d', 'site-optimizer'), $deleted),
            'deleted' => $deleted
        ));
    }

    /**
     * AJAX обработчик быстрой оптимизации
     */
    public function ajax_quick_optimize() {
        check_ajax_referer('so_quick_optimize', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'site-optimizer')));
        }

        $results = array(
            'images' => null,
            'database' => null
        );

        $settings = get_option('so_settings', array());

        // Оптимизация изображений
        if (!empty($settings['enable_image_optimization'])) {
            $results['images'] = SO_Image_Optimizer::optimize_all_images();
        }

        // Очистка БД
        if (!empty($settings['enable_database_cleanup'])) {
            SO_Database_Optimizer::full_cleanup();
            $results['database'] = SO_Database_Optimizer::optimize_tables();
        }

        // Обновление статистики
        $stats = get_option('so_stats', array());
        $stats['optimizations_run'] = isset($stats['optimizations_run']) ? $stats['optimizations_run'] + 1 : 1;
        $stats['last_optimization'] = time();
        update_option('so_stats', $stats);

        wp_send_json_success(array(
            'message' => __('Оптимизация успешно завершена!', 'site-optimizer'),
            'results' => $results,
            'stats' => $stats
        ));
    }
}

// Инициализация плагина
Site_Optimizer::get_instance();
