# Site Optimizer Pro

**Version:** 1.1.0  
**Requires WordPress:** 5.0+  
**Requires PHP:** 7.2+  
**License:** GPL v2 or later

Comprehensive WordPress optimization solution: image compression, database optimization, site health monitoring.

## 📋 Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Settings](#settings)
- [Hooks and API](#hooks-and-api)
- [FAQ](#faq)
- [Support](#support)

---

## ✨ Features

### 🖼️ Image Optimization
- Automatic compression on upload
- WebP conversion
- Bulk optimization of existing images
- Support for JPEG, PNG, GIF, WebP
- PNG transparency preservation
- All thumbnail sizes optimization

### 🗄️ Database Optimization
- Post revisions cleanup
- Trash deletion
- Transients cleanup
- Spam comments removal
- Orphaned metadata cleanup
- MySQL table optimization

### 📊 Site Health Monitoring
- PHP and WordPress version check
- Memory usage monitoring
- Disk space control
- Security checks
- Caching status
- WP-Cron monitoring

### ⚡ Automation
- Daily automatic optimization
- Weekly health checks
- Critical issues notifications
- Quick optimize from admin bar

---

## 📦 Requirements

### Minimum
- WordPress 5.0 or higher
- PHP 7.2 or higher
- MySQL 5.6+ / MariaDB 10.1+
- GD extension

### Recommended
- PHP 8.0+
- Imagick extension (for better image optimization)
- Redis or Memcached (for object caching)
- Minimum 256MB memory

---

## 🔧 Installation

### Automatic Installation
1. Download the plugin archive
2. In WordPress admin, go to **Plugins → Add New**
3. Click **Upload Plugin** and select the archive
4. Activate the plugin

### Manual Installation
1. Upload `site-optimizer` folder to `/wp-content/plugins/`
2. Activate the plugin in **Plugins** menu

### Via WP-CLI
```bash
wp plugin install site-optimizer --activate
```

---

## 📖 Usage

### Quick Start

1. After activation, go to **Tools → Site Optimizer**
2. Check site status on **Health** tab
3. Run image optimization
4. Perform database cleanup

### Image Optimization

#### Automatic Optimization
Images are optimized automatically on upload via media library.

#### Bulk Optimization
1. Go to **Tools → Site Optimizer → Images**
2. Click **Optimize All Images**
3. Wait for the process to complete

#### WebP Creation
WebP versions are created automatically for all optimized images.

### Database Optimization

1. Go to **Tools → Site Optimizer → Database**
2. Select cleanup type or click **Full Cleanup**
3. Confirm the action

> ⚠️ **Warning:** Backup your database before optimization!

### Health Monitoring

1. Go to **Tools → Site Optimizer → Health**
2. Review all components status
3. Fix any found issues

---

## ⚙️ Settings

The plugin uses the following options (stored in `wp_options`):

### so_settings
```php
array(
    'enable_image_optimization' => true,     // Enable image optimization
    'enable_webp' => true,                   // Enable WebP creation
    'enable_database_cleanup' => true,       // Enable DB cleanup
    'enable_cron' => true,                   // Enable Cron tasks
    'image_quality' => 85,                   // Compression quality (1-100)
    'auto_optimize_on_upload' => true        // Auto-optimize on upload
)
```

### so_stats
```php
array(
    'images_optimized' => 0,      // Total images optimized
    'space_saved' => 0,           // Space saved (bytes)
    'optimizations_run' => 0,     // Number of optimization runs
    'db_optimizations' => 0,      // Number of DB optimizations
    'last_optimization' => 0,     // Last optimization timestamp
    'last_health_check' => 0      // Last health check timestamp
)
```

---

## 🎯 Hooks and API

### Cron Hooks
```php
// Daily optimization
add_action('so_daily_optimization', 'your_function');

// Weekly health check
add_action('so_weekly_health_check', 'your_function');
```

### AJAX Actions
```php
// Image optimization
do_action('wp_ajax_so_optimize_images');

// Database optimization
do_action('wp_ajax_so_optimize_database');

// Get health status
do_action('wp_ajax_so_get_health_status');

// Purge transients
do_action('wp_ajax_so_purge_transients');

// Quick optimize
do_action('wp_ajax_so_quick_optimize');
```

### Class Methods

```php
// Image Optimization
SO_Image_Optimizer::optimize_all_images();
SO_Image_Optimizer::optimize_image($file_path);
SO_Image_Optimizer::create_webp($file_path);
SO_Image_Optimizer::get_stats();

// Database
SO_Database_Optimizer::full_cleanup();
SO_Database_Optimizer::optimize_tables();
SO_Database_Optimizer::clean_post_revisions();
SO_Database_Optimizer::clean_trash();
SO_Database_Optimizer::clean_transients();
SO_Database_Optimizer::get_stats();

// Site Health
SO_Site_Health::get_full_status();
SO_Site_Health::check_php_version();
SO_Site_Health::check_memory_usage();
SO_Site_Health::check_disk_space();
```

---

## ❓ FAQ

### Is it safe to delete post revisions?
Yes, revisions are only for change history. It's recommended to keep the last 3-5 revisions.

### Can I disable WebP creation?
Yes, via filter:
```php
add_filter('so_enable_webp', '__return_false');
```

### How to exclude certain images from optimization?
Use filter:
```php
add_filter('so_skip_image_optimization', function($skip, $attachment_id) {
    $meta = wp_get_attachment_metadata($attachment_id);
    return ($meta['filesize'] < 10240); // Skip files < 10KB
}, 10, 2);
```

### Does the plugin slow down image loading?
No, optimization happens on upload. Optimized images load faster.

### How often to run database optimization?
Recommended: weekly for active sites, monthly for low-activity sites.

---

## 🐛 Troubleshooting

### "Not enough memory" error
Increase memory limit in `wp-config.php`:
```php
define('WP_MEMORY_LIMIT', '256M');
```

### WebP files not created
Check for GD extension with WebP support:
```php
var_dump(function_exists('imagewebp'));
```

### Database optimization error
Check MySQL user permissions for `OPTIMIZE TABLE`.

---

## 📝 Changelog

### 1.1.0
- ✅ Added minimum PHP and WordPress version checks
- ✅ Added uninstall.php for clean removal
- ✅ Improved error handling
- ✅ Added localization (Russian/English)
- ✅ Added admin bar menu
- ✅ Removed duplicate wp-optimizer plugin

### 1.0.0
- Initial release

---

## 📧 Support

If you have questions or issues:
1. Check the documentation
2. Enable WordPress debug mode
3. Check PHP error logs

---

## 📄 License

GPL v2 or later

---

## 👨‍💻 For Developers

### Plugin Structure
```
site-optimizer/
├── site-optimizer.php      # Main plugin file
├── uninstall.php           # Uninstall script
├── admin/
│   ├── class-admin-page.php
│   ├── style.css
│   └── script.js
├── includes/
│   ├── class-image-optimizer.php
│   ├── class-database-optimizer.php
│   ├── class-site-health.php
│   └── class-theme-integration.php
└── languages/              # Translation files
```

### Adding Localization
```php
__('Text to translate', 'site-optimizer')
```

### Testing
```bash
# Check code against WordPress standards
phpcs --standard=WordPress site-optimizer/

# Run tests
phpunit
```
