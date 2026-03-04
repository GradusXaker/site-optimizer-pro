<?php
/**
 * Image Optimizer Module
 * Сжатие изображений без потери качества и конвертация в WebP
 *
 * @package Site_Optimizer
 */

if (!defined('ABSPATH')) exit;

/**
 * Класс для оптимизации изображений
 */
class SO_Image_Optimizer {

    /**
     * @var int Качество сжатия (0-100)
     */
    private static $quality = 85;

    /**
     * @var bool Включена ли конвертация в WebP
     */
    private static $webp_enabled = true;

    /**
     * @var array Допустимые MIME типы для оптимизации
     */
    private static $allowed_mime_types = array(
        'image/jpeg',
        'image/png',
        'image/jpg',
        'image/gif',
        'image/webp'
    );

    /**
     * Инициализация настроек
     */
    public static function init() {
        $settings = get_option('so_settings', array());
        if (!empty($settings['image_quality'])) {
            self::$quality = (int) $settings['image_quality'];
        }
        if (isset($settings['enable_webp'])) {
            self::$webp_enabled = (bool) $settings['enable_webp'];
        }
    }

    /**
     * Оптимизация при загрузке изображения
     *
     * @param array $metadata Метаданные изображения
     * @param int   $attachment_id ID вложения
     * @return array
     */
    public static function optimize_on_upload($metadata, $attachment_id) {
        // Проверка на ошибки
        if (empty($metadata) || !isset($metadata['file'])) {
            return $metadata;
        }

        // Проверка: включена ли оптимизация
        $settings = get_option('so_settings', array());
        if (empty($settings['auto_optimize_on_upload'])) {
            return $metadata;
        }

        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $metadata['file'];

        // Проверка существования файла
        if (!file_exists($file_path)) {
            error_log('Site Optimizer: Файл не найден: ' . $file_path);
            return $metadata;
        }

        // Проверка прав на запись
        if (!is_writable($file_path)) {
            error_log('Site Optimizer: Нет прав на запись: ' . $file_path);
            return $metadata;
        }

        // Оптимизация основного изображения
        $opt_result = self::optimize_image($file_path);
        if ($opt_result) {
            self::create_webp($file_path);
        }

        // Оптимизация миниатюр
        if (isset($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size => $size_data) {
                if (!isset($size_data['file'])) continue;

                $size_file = pathinfo($metadata['file'], PATHINFO_DIRNAME) . '/' . $size_data['file'];
                $size_path = $upload_dir['basedir'] . '/' . $size_file;

                if (file_exists($size_path) && is_writable($size_path)) {
                    $thumb_result = self::optimize_image($size_path);
                    if ($thumb_result) {
                        self::create_webp($size_path);
                    }
                }
            }
        }

        // Обновление статистики
        if ($opt_result && isset($opt_result['saved'])) {
            self::update_stats(1, $opt_result['saved']);
        }

        return $metadata;
    }

    /**
     * Оптимизация изображения
     *
     * @param string $file_path Путь к файлу
     * @return array|false
     */
    public static function optimize_image($file_path) {
        // Проверка существования файла
        if (!file_exists($file_path)) {
            return false;
        }

        // Проверка прав на чтение
        if (!is_readable($file_path)) {
            error_log('Site Optimizer: Нет прав на чтение: ' . $file_path);
            return false;
        }

        // Получение MIME типа
        $mime_type = mime_content_type($file_path);
        if (!in_array($mime_type, self::$allowed_mime_types)) {
            return false;
        }

        $original_size = filesize($file_path);

        // Проверка: есть ли смысл оптимизировать (файлы меньше 10KB не оптимизируем)
        if ($original_size < 10240) {
            return false;
        }

        // Получение информации об изображении
        $img_info = @getimagesize($file_path);
        if (!$img_info) {
            error_log('Site Optimizer: Не удалось получить информацию об изображении: ' . $file_path);
            return false;
        }

        $width = $img_info[0];
        $height = $img_info[1];
        $type = $img_info[2];

        // Проверка: поддерживается ли тип изображения
        if (!in_array($type, array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP))) {
            return false;
        }

        // Проверка: доступно ли расширение GD
        if (!extension_loaded('gd')) {
            error_log('Site Optimizer: Расширение GD не доступно');
            return false;
        }

        // Создаём изображение из файла
        $image = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = @imagecreatefromjpeg($file_path);
                break;
            case IMAGETYPE_PNG:
                $image = @imagecreatefrompng($file_path);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $image = @imagecreatefromwebp($file_path);
                }
                break;
        }

        if (!$image) {
            error_log('Site Optimizer: Не удалось создать изображение: ' . $file_path);
            return false;
        }

        // Сохраняем с оптимизацией
        $saved_successfully = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                imageinterlace($image, true); // Progressive JPEG
                $saved_successfully = @imagejpeg($image, $file_path, self::$quality);
                break;
            case IMAGETYPE_PNG:
                // Сжатие PNG без потерь (уровень 6 - хороший баланс)
                $saved_successfully = @imagepng($image, $file_path, 6);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagewebp')) {
                    $saved_successfully = @imagewebp($image, $file_path, self::$quality);
                }
                break;
        }

        imagedestroy($image);

        if (!$saved_successfully) {
            error_log('Site Optimizer: Не удалось сохранить изображение: ' . $file_path);
            return false;
        }

        // Проверка результата
        clearstatcache();
        $new_size = filesize($file_path);
        $saved = $original_size - $new_size;

        if ($saved > 0) {
            return array(
                'original' => $original_size,
                'optimized' => $new_size,
                'saved' => $saved,
                'percent' => round(($saved / $original_size) * 100, 2)
            );
        }

        return false;
    }

    /**
     * Создание WebP версии изображения
     *
     * @param string $file_path Путь к файлу
     * @return string|false
     */
    public static function create_webp($file_path) {
        if (!self::$webp_enabled) {
            return false;
        }

        // Проверка: доступна ли функция создания WebP
        if (!function_exists('imagewebp')) {
            return false;
        }

        if (!file_exists($file_path)) {
            return false;
        }

        $mime_type = mime_content_type($file_path);
        if (!in_array($mime_type, array('image/jpeg', 'image/png', 'image/jpg'))) {
            return false;
        }

        $webp_path = preg_replace('/\.[^.]+$/', '.webp', $file_path);

        // Не создаём если WebP уже существует и новее оригинала
        if (file_exists($webp_path) && filemtime($webp_path) >= filemtime($file_path)) {
            return false;
        }

        // Проверка прав на запись в директорию
        $webp_dir = dirname($webp_path);
        if (!is_writable($webp_dir)) {
            error_log('Site Optimizer: Нет прав на запись в директорию: ' . $webp_dir);
            return false;
        }

        $img_info = @getimagesize($file_path);
        if (!$img_info) {
            return false;
        }

        $type = $img_info[2];

        // Создаём изображение
        $image = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = @imagecreatefromjpeg($file_path);
                break;
            case IMAGETYPE_PNG:
                $image = @imagecreatefrompng($file_path);
                // Сохраняем прозрачность для PNG
                if ($image) {
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                }
                break;
            default:
                return false;
        }

        if (!$image) {
            return false;
        }

        // Создаём WebP
        $result = @imagewebp($image, $webp_path, self::$quality);
        imagedestroy($image);

        if ($result && file_exists($webp_path)) {
            return $webp_path;
        }

        return false;
    }

    /**
     * Оптимизация всех изображений в библиотеке
     *
     * @return array
     */
    public static function optimize_all_images() {
        // Увеличиваем лимит времени выполнения
        set_time_limit(300);

        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => self::$allowed_mime_types,
            'posts_per_page' => -1,
            'post_status' => 'inherit',
            'orderby' => 'ID',
            'order' => 'ASC'
        );

        $attachments = get_posts($args);
        $results = array(
            'total' => count($attachments),
            'optimized' => 0,
            'webp_created' => 0,
            'space_saved' => 0,
            'errors' => 0
        );

        $upload_dir = wp_upload_dir();

        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);

            if (!$file_path || !file_exists($file_path)) {
                $results['errors']++;
                continue;
            }

            // Оптимизация основного файла
            $opt_result = self::optimize_image($file_path);
            if ($opt_result) {
                $results['optimized']++;
                $results['space_saved'] += $opt_result['saved'];

                // Создание WebP
                if (self::create_webp($file_path)) {
                    $results['webp_created']++;
                }
            }

            // Оптимизация миниатюр
            $metadata = wp_get_attachment_metadata($attachment->ID);
            if (isset($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size => $size_data) {
                    if (!isset($size_data['file'])) continue;

                    $size_file = pathinfo($file_path, PATHINFO_DIRNAME) . '/' . $size_data['file'];

                    if (file_exists($size_file)) {
                        $thumb_result = self::optimize_image($size_file);
                        if ($thumb_result) {
                            $results['optimized']++;
                            $results['space_saved'] += $thumb_result['saved'];

                            if (self::create_webp($size_file)) {
                                $results['webp_created']++;
                            }
                        }
                    }
                }
            }

            // Небольшая пауза чтобы не перегружать сервер
            if ($results['optimized'] % 10 === 0) {
                usleep(100000); // 100ms
            }
        }

        // Обновление статистики
        self::update_stats($results['optimized'], $results['space_saved']);

        return $results;
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
        if (get_transient('so_optimization_running')) {
            wp_send_json_error(array(
                'message' => __('Оптимизация уже выполняется', 'site-optimizer')
            ));
        }

        // Устанавливаем флаг выполнения
        set_transient('so_optimization_running', true, 300);

        try {
            $results = self::optimize_all_images();

            // Снимаем флаг
            delete_transient('so_optimization_running');

            wp_send_json_success(array(
                'message' => sprintf(
                    __('Оптимизировано: %d из %d изображений. WebP создано: %d. Сэкономлено: %s', 'site-optimizer'),
                    $results['optimized'],
                    $results['total'],
                    $results['webp_created'],
                    size_format($results['space_saved'])
                ),
                'results' => $results
            ));
        } catch (Exception $e) {
            delete_transient('so_optimization_running');

            wp_send_json_error(array(
                'message' => __('Ошибка оптимизации: ', 'site-optimizer') . $e->getMessage()
            ));
        }
    }

    /**
     * Обновление статистики
     *
     * @param int $images_count Количество оптимизированных изображений
     * @param int $size_saved Сэкономлено байт
     */
    private static function update_stats($images_count, $size_saved) {
        $stats = get_option('so_stats', array(
            'images_optimized' => 0,
            'space_saved' => 0,
            'optimizations_run' => 0
        ));

        $stats['images_optimized'] = isset($stats['images_optimized']) ? $stats['images_optimized'] + $images_count : $images_count;
        $stats['space_saved'] = isset($stats['space_saved']) ? $stats['space_saved'] + $size_saved : $size_saved;

        update_option('so_stats', $stats, false);
    }

    /**
     * Получить статистику
     *
     * @return array
     */
    public static function get_stats() {
        $stats = get_option('so_stats', array());

        // Подсчёт WebP файлов с кэшированием
        $cached_count = get_transient('so_webp_count');
        if ($cached_count !== false) {
            $stats['webp_count'] = (int) $cached_count;
            return $stats;
        }

        $upload_dir = wp_upload_dir();
        $webp_count = 0;

        if (is_dir($upload_dir['basedir'])) {
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($upload_dir['basedir'], RecursiveDirectoryIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'webp') {
                        $webp_count++;
                    }
                }
            } catch (Exception $e) {
                error_log('Site Optimizer: Ошибка подсчёта WebP файлов: ' . $e->getMessage());
            }
        }

        $stats['webp_count'] = $webp_count;

        // Кэшируем результат на 1 час
        set_transient('so_webp_count', $webp_count, HOUR_IN_SECONDS);

        return $stats;
    }

    /**
     * Проверка доступности функций оптимизации
     *
     * @return array
     */
    public static function check_requirements() {
        $requirements = array(
            'gd_loaded' => extension_loaded('gd'),
            'imagick_loaded' => extension_loaded('imagick'),
            'webp_supported' => function_exists('imagewebp'),
            'upload_writable' => is_writable(wp_upload_dir()['basedir'])
        );

        $requirements['can_optimize'] = $requirements['gd_loaded'] && $requirements['upload_writable'];

        return $requirements;
    }
}

// Инициализация
SO_Image_Optimizer::init();
