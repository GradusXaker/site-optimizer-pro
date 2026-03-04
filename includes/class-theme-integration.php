<?php
/**
 * Theme Integration Module
 * Интеграция плагина Site Optimizer с темой Hacker
 */

if (!defined('ABSPATH')) exit;

class SO_Theme_Integration {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // WebP поддержка для изображений
        add_filter('wp_get_attachment_image_src', array($this, 'webp_image_src'), 10, 4);
        add_filter('wp_calculate_image_srcset', array($this, 'webp_srcset'), 10, 5);

        // Admin bar индикатор
        add_action('admin_bar_menu', array($this, 'admin_bar_menu'), 100);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Widget для сайдбара
        add_action('widgets_init', array($this, 'register_widget'));

        // Shortcode для отображения статуса
        add_shortcode('site_health', array($this, 'health_shortcode'));
        add_shortcode('site_stats', array($this, 'stats_shortcode'));
    }

    /**
     * Замена URL изображений на WebP версии
     */
    public function webp_image_src($image, $attachment_id, $size, $icon) {
        if (!is_array($image) || empty($image[0])) {
            return $image;
        }

        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image[0]);

        // Проверяем существование WebP версии
        $webp_path = preg_replace('/\.[^.]+$/', '.webp', $file_path);

        if (file_exists($webp_path)) {
            $image[0] = preg_replace('/\.[^.]+$/', '.webp', $image[0]);
        }

        return $image;
    }

    /**
     * Добавление WebP в srcset
     */
    public function webp_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        if (!is_array($sources)) {
            return $sources;
        }

        $upload_dir = wp_upload_dir();

        foreach ($sources as $width => &$source) {
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $source['url']);
            $webp_path = preg_replace('/\.[^.]+$/', '.webp', $file_path);

            if (file_exists($webp_path)) {
                $source['url'] = preg_replace('/\.[^.]+$/', '.webp', $source['url']);
            }
        }

        return $sources;
    }

    /**
     * Добавление индикатора в admin bar
     */
    public function admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Получаем статистику
        $stats = get_option('so_stats', array());
        $images_optimized = isset($stats['images_optimized']) ? $stats['images_optimized'] : 0;
        $space_saved = isset($stats['space_saved']) ? size_format($stats['space_saved']) : '0 B';

        // Добавляем родительское меню
        $wp_admin_bar->add_node(array(
            'id' => 'site-optimizer',
            'title' => '<span class="ab-icon dashicons dashicons-performance"></span><span class="ab-label">Site Optimizer</span>',
            'href' => admin_url('admin.php?page=site-optimizer'),
            'meta' => array('class' => 'so-admin-bar')
        ));

        // Статистика
        $wp_admin_bar->add_node(array(
            'id' => 'so-stats',
            'title' => '📊 Изображений оптимизировано: ' . $images_optimized,
            'parent' => 'site-optimizer',
            'href' => false
        ));

        $wp_admin_bar->add_node(array(
            'id' => 'so-saved',
            'title' => '💾 Сэкономлено места: ' . $space_saved,
            'parent' => 'site-optimizer',
            'href' => false
        ));

        $wp_admin_bar->add_node(array(
            'id' => 'so-dashboard',
            'title' => '⚙️ Открыть панель управления',
            'parent' => 'site-optimizer',
            'href' => admin_url('admin.php?page=site-optimizer')
        ));

        // Быстрая оптимизация
        $wp_admin_bar->add_node(array(
            'id' => 'so-quick-optimize',
            'title' => '⚡ Быстрая оптимизация',
            'parent' => 'site-optimizer',
            'href' => wp_nonce_url(admin_url('admin-ajax.php?action=so_quick_optimize'), 'so_quick_optimize')
        ));
    }

    /**
     * Подключение frontend скриптов
     */
    public function enqueue_frontend_assets() {
        // Стили для WebP fallback
        wp_add_inline_style('hacker-style', '
            /* WebP fallback */
            picture {
                display: block;
                max-width: 100%;
                height: auto;
            }
            picture img {
                max-width: 100%;
                height: auto;
            }
        ');
    }

    /**
     * Регистрация виджета
     */
    public function register_widget() {
        register_widget('SO_Health_Widget');
    }

    /**
     * Shortcode для отображения статуса сайта
     */
    public function health_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show' => 'overall', // overall, detailed, critical
            'style' => 'minimal' // minimal, full
        ), $atts);

        if (!function_exists('SO_Site_Health::get_full_status')) {
            return '';
        }

        $status = SO_Site_Health::get_full_status();

        // Подсчёт общего статуса
        $critical = 0;
        $warning = 0;
        $good = 0;

        foreach ($status as $check) {
            if ($check['status'] === 'critical') $critical++;
            elseif ($check['status'] === 'warning') $warning++;
            else $good++;
        }

        $overall = 'good';
        if ($critical > 0) $overall = 'critical';
        elseif ($warning > 0) $overall = 'warning';

        $labels = array(
            'good' => '✓ Отличное состояние',
            'warning' => '⚠ Есть проблемы',
            'critical' => '✗ Требует внимания'
        );

        $classes = array(
            'good' => 'so-health-good',
            'warning' => 'so-health-warning',
            'critical' => 'so-health-critical'
        );

        if ($atts['show'] === 'overall') {
            return sprintf(
                '<div class="so-health-widget %s"><span class="so-health-status">%s</span></div>',
                $classes[$overall],
                $labels[$overall]
            );
        }

        if ($atts['show'] === 'detailed') {
            $html = '<div class="so-health-detailed ' . $classes[$overall] . '">';
            $html .= '<h4>Здоровье сайта</h4>';
            $html .= '<ul class="so-health-list">';

            foreach ($status as $key => $check) {
                $html .= sprintf(
                    '<li class="so-health-item status-%s"><strong>%s:</strong> %s <small>%s</small></li>',
                    $check['status'],
                    $check['label'],
                    $check['value'],
                    $check['message']
                );
            }

            $html .= '</ul></div>';
            return $html;
        }

        if ($atts['show'] === 'critical' && $critical > 0) {
            $html = '<div class="so-health-critical-alert">';
            $html .= '<strong>⚠ Критические проблемы:</strong>';
            $html .= '<ul>';

            foreach ($status as $check) {
                if ($check['status'] === 'critical') {
                    $html .= sprintf('<li>%s: %s</li>', $check['label'], $check['message']);
                }
            }

            $html .= '</ul></div>';
            return $html;
        }

        return '';
    }

    /**
     * Shortcode для отображения статистики
     */
    public function stats_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show' => 'all' // all, images, database
        ), $atts);

        $stats = get_option('so_stats', array());

        $html = '<div class="so-stats-widget">';

        if ($atts['show'] === 'all' || $atts['show'] === 'images') {
            $html .= '<div class="so-stat-row">';
            $html .= '<span class="so-stat-label">🖼️ Изображений оптимизировано:</span> ';
            $html .= '<span class="so-stat-value">' . (isset($stats['images_optimized']) ? $stats['images_optimized'] : 0) . '</span>';
            $html .= '</div>';

            $html .= '<div class="so-stat-row">';
            $html .= '<span class="so-stat-label">💾 Сэкономлено места:</span> ';
            $html .= '<span class="so-stat-value">' . (isset($stats['space_saved']) ? size_format($stats['space_saved']) : '0 B') . '</span>';
            $html .= '</div>';
        }

        if ($atts['show'] === 'all' || $atts['show'] === 'database') {
            $html .= '<div class="so-stat-row">';
            $html .= '<span class="so-stat-label">🗄️ Оптимизаций БД:</span> ';
            $html .= '<span class="so-stat-value">' . (isset($stats['db_optimizations']) ? $stats['db_optimizations'] : 0) . '</span>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }
}

/**
 * Widget для отображения здоровья сайта
 */
class SO_Health_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'so_health_widget',
            'Site Optimizer - Здоровье сайта',
            array('description' => 'Отображает статус здоровья вашего сайта')
        );
    }

    public function widget($args, $instance) {
        if (!class_exists('SO_Site_Health')) {
            return;
        }

        echo $args['before_widget'];

        $title = !empty($instance['title']) ? $instance['title'] : 'Здоровье сайта';
        echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];

        $status = SO_Site_Health::get_full_status();

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

        $labels = array(
            'good' => '✓ Всё отлично!',
            'warning' => '⚠ Есть проблемы',
            'critical' => '✗ Внимание!'
        );

        $classes = array(
            'good' => 'so-widget-good',
            'warning' => 'so-widget-warning',
            'critical' => 'so-widget-critical'
        );

        echo '<div class="so-health-widget-content ' . $classes[$overall] . '">';
        echo '<div class="so-overall-status">' . $labels[$overall] . '</div>';

        if (!empty($instance['show_details'])) {
            echo '<ul class="so-details-list">';
            foreach (array_slice($status, 0, 5) as $key => $check) {
                echo sprintf(
                    '<li class="status-%s"><strong>%s:</strong> %s</li>',
                    $check['status'],
                    $check['label'],
                    $check['value']
                );
            }
            echo '</ul>';
        }

        if (current_user_can('manage_options')) {
            echo '<a href="' . admin_url('admin.php?page=site-optimizer') . '" class="button button-small">Подробнее</a>';
        }

        echo '</div>';

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Здоровье сайта';
        $show_details = !empty($instance['show_details']) ? true : false;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Заголовок:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_details); ?>
                   id="<?php echo esc_attr($this->get_field_id('show_details')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_details')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_details')); ?>">Показывать детали</label>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['show_details'] = !empty($new_instance['show_details']) ? true : false;
        return $instance;
    }
}

// Инициализация
SO_Theme_Integration::get_instance();
