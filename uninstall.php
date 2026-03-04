<?php
/**
 * Site Optimizer Pro Uninstall
 * 
 * Полное удаление плагина и всех его данных
 * 
 * @package Site_Optimizer
 */

// Проверка: вызван ли файл из WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Проверка прав доступа
if (!current_user_can('delete_plugins')) {
    exit;
}

/**
 * Рекурсивное удаление директории
 *
 * @param string $dir
 */
function so_delete_directory($dir) {
    if (!is_dir($dir)) return;

    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? so_delete_directory($path) : unlink($path);
    }
    rmdir($dir);
}

// ==========================================
// 1. Удаляем все опции плагина
// ==========================================
delete_option('so_stats');
delete_option('so_settings');
delete_option('so_activation_time');
delete_option('so_db_version');

// ==========================================
// 2. Очищаем запланированные Cron события
// ==========================================
wp_clear_scheduled_hook('so_daily_optimization');
wp_clear_scheduled_hook('so_weekly_health_check');

// ==========================================
// 3. Удаляем папку с оптимизированными изображениями
// ==========================================
$upload_dir = wp_upload_dir();
$so_dir = $upload_dir['basedir'] . '/site-optimizer';

if (is_dir($so_dir)) {
    so_delete_directory($so_dir);
}

// ==========================================
// 4. Очищаем транзиенты плагина
// ==========================================
delete_transient('so_health_status');
delete_transient('so_optimization_status');
delete_transient('so_image_stats');
delete_transient('so_db_stats');

// ==========================================
// 5. Очищаем кэш плагина (если есть)
// ==========================================
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// ==========================================
// 6. Логирование удаления (опционально)
// ==========================================
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Site Optimizer Pro: Plugin uninstalled. All data removed.');
}
