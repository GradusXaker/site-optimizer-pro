<?php
/**
 * Admin Page Module
 * Страница настроек плагина в админ-панели
 */

if (!defined('ABSPATH')) exit;

class SO_Admin_Page {
    
    /**
     * Добавление страницы в меню
     */
    public static function add_admin_page() {
        add_menu_page(
            'Site Optimizer',
            'Site Optimizer',
            'manage_options',
            'site-optimizer',
            array(__CLASS__, 'render_page'),
            'dashicons-performance',
            80
        );
        
        add_submenu_page(
            'site-optimizer',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'site-optimizer',
            array(__CLASS__, 'render_page')
        );
        
        add_submenu_page(
            'site-optimizer',
            'Настройки',
            'Настройки',
            'manage_options',
            'site-optimizer-settings',
            array(__CLASS__, 'render_settings')
        );
    }
    
    /**
     * Рендер главной страницы
     */
    public static function render_page() {
        $stats = get_option('so_stats', array(
            'images_optimized' => 0,
            'space_saved' => 0,
            'optimizations_run' => 0
        ));
        
        $db_stats = SO_Database_Optimizer::get_stats();
        $img_stats = SO_Image_Optimizer::get_stats();
        ?>
        <div class="wrap so-dashboard">
            <h1>🚀 Site Optimizer Pro</h1>
            
            <div class="so-cards">
                <!-- Быстрые действия -->
                <div class="so-card so-card-actions">
                    <h2>⚡ Быстрые действия</h2>
                    
                    <div class="so-action-buttons">
                        <button class="button button-primary button-hero" id="so-optimize-images">
                            🖼️ Оптимизировать изображения
                        </button>
                        
                        <button class="button button-primary button-hero" id="so-optimize-database">
                            🗄️ Оптимизировать базу данных
                        </button>
                        
                        <button class="button button-secondary button-hero" id="so-purge-transients">
                            🧹 Очистить транзиенты
                        </button>
                        
                        <button class="button button-secondary button-hero" id="so-refresh-health">
                            📊 Обновить статус
                        </button>
                    </div>
                    
                    <div class="so-progress" id="so-progress" style="display:none;">
                        <div class="so-progress-bar">
                            <div class="so-progress-fill"></div>
                        </div>
                        <p class="so-progress-text">Выполняется...</p>
                    </div>
                    
                    <div class="so-results" id="so-results"></div>
                </div>
                
                <!-- Статистика -->
                <div class="so-card so-card-stats">
                    <h2>📈 Статистика</h2>
                    
                    <div class="so-stats-grid">
                        <div class="so-stat-item">
                            <span class="so-stat-value"><?php echo isset($img_stats['images_optimized']) ? $img_stats['images_optimized'] : 0; ?></span>
                            <span class="so-stat-label">Изображений оптимизировано</span>
                        </div>
                        
                        <div class="so-stat-item">
                            <span class="so-stat-value"><?php echo isset($img_stats['webp_count']) ? $img_stats['webp_count'] : 0; ?></span>
                            <span class="so-stat-label">WebP создано</span>
                        </div>
                        
                        <div class="so-stat-item">
                            <span class="so-stat-value"><?php echo isset($stats['space_saved']) ? size_format($stats['space_saved']) : '0 B'; ?></span>
                            <span class="so-stat-label">Места сэкономлено</span>
                        </div>
                        
                        <div class="so-stat-item">
                            <span class="so-stat-value"><?php echo isset($stats['optimizations_run']) ? $stats['optimizations_run'] : 0; ?></span>
                            <span class="so-stat-label">Запусков оптимизации</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Здоровье сайта -->
            <div class="so-card so-card-health">
                <h2>💚 Здоровье сайта</h2>
                
                <div class="so-health-overall" id="so-health-overall">
                    <span class="so-health-indicator so-health-good">✓</span>
                    <span class="so-health-text">Загрузка...</span>
                </div>
                
                <div class="so-health-grid" id="so-health-grid">
                    <!-- Будет заполнено через AJAX -->
                </div>
            </div>
            
            <!-- Статистика БД -->
            <div class="so-card so-card-db">
                <h2>🗄️ Статистика базы данных</h2>
                
                <div class="so-db-stats">
                    <div class="so-db-stat">
                        <strong>Записей:</strong> 
                        <?php echo isset($db_stats['posts_post']) ? $db_stats['posts_post'] : 0; ?>
                    </div>
                    <div class="so-db-stat">
                        <strong>Страниц:</strong> 
                        <?php echo isset($db_stats['posts_page']) ? $db_stats['posts_page'] : 0; ?>
                    </div>
                    <div class="so-db-stat">
                        <strong>Комментариев:</strong> 
                        <?php echo isset($db_stats['comments']) ? $db_stats['comments'] : 0; ?>
                    </div>
                    <div class="so-db-stat">
                        <strong>Пользователей:</strong> 
                        <?php echo isset($db_stats['users']) ? $db_stats['users'] : 0; ?>
                    </div>
                    <div class="so-db-stat">
                        <strong>Транзиентов:</strong> 
                        <?php echo isset($db_stats['transients']) ? $db_stats['transients'] : 0; ?>
                    </div>
                    <div class="so-db-stat">
                        <strong>Размер БД:</strong> 
                        <?php echo isset($db_stats['database_size']) ? size_format($db_stats['database_size']) : 'N/A'; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Рендер страницы настроек
     */
    public static function render_settings() {
        $settings = get_option('so_settings', array(
            'webp_enabled' => 1,
            'quality' => 85,
            'auto_optimize' => 1,
            'cleanup_on_activation' => 1
        ));
        
        if (isset($_POST['so_save_settings'])) {
            check_admin_referer('so_settings_nonce');
            
            $settings = array(
                'webp_enabled' => isset($_POST['webp_enabled']) ? 1 : 0,
                'quality' => intval($_POST['quality']),
                'auto_optimize' => isset($_POST['auto_optimize']) ? 1 : 0,
                'cleanup_on_activation' => isset($_POST['cleanup_on_activation']) ? 1 : 0
            );
            
            update_option('so_settings', $settings);
            echo '<div class="notice notice-success"><p>Настройки сохранены!</p></div>';
        }
        ?>
        <div class="wrap so-settings">
            <h1>⚙️ Настройки Site Optimizer</h1>
            
            <form method="post" class="so-settings-form">
                <?php wp_nonce_field('so_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="webp_enabled">Создавать WebP</label></th>
                        <td>
                            <input type="checkbox" id="webp_enabled" name="webp_enabled" value="1" 
                                   <?php checked($settings['webp_enabled'], 1); ?>>
                            <p class="description">Автоматически создавать WebP версии изображений</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="quality">Качество сжатия</label></th>
                        <td>
                            <input type="range" id="quality" name="quality" min="60" max="100" 
                                   value="<?php echo esc_attr($settings['quality']); ?>" 
                                   style="width: 200px;">
                            <span id="quality-value"><?php echo esc_attr($settings['quality']); ?>%</span>
                            <p class="description">Рекомендуется 80-90% для оптимального баланса</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="auto_optimize">Авто-оптимизация</label></th>
                        <td>
                            <input type="checkbox" id="auto_optimize" name="auto_optimize" value="1" 
                                   <?php checked($settings['auto_optimize'], 1); ?>>
                            <p class="description">Ежедневная автоматическая оптимизация</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="cleanup_on_activation">Очистка при активации</label></th>
                        <td>
                            <input type="checkbox" id="cleanup_on_activation" name="cleanup_on_activation" value="1" 
                                   <?php checked($settings['cleanup_on_activation'], 1); ?>>
                            <p class="description">Автоматическая очистка БД при включении плагина</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Сохранить настройки', 'primary', 'so_save_settings'); ?>
            </form>
            
            <hr>
            
            <h2>📊 Информация о плагине</h2>
            <table class="widefat">
                <tr>
                    <th>Версия</th>
                    <td>1.0.0</td>
                </tr>
                <tr>
                    <th>Путь</th>
                    <td><?php echo SO_PLUGIN_PATH; ?></td>
                </tr>
                <tr>
                    <th>Активирован</th>
                    <td><?php echo date('Y-m-d H:i:s', get_option('so_activation_time', time())); ?></td>
                </tr>
            </table>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#quality').on('input', function() {
                $('#quality-value').text($(this).val() + '%');
            });
        });
        </script>
        <?php
    }
}
