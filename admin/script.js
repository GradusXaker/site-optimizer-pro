/**
 * Site Optimizer Admin Scripts
 */

(function($) {
    'use strict';
    
    // Show progress
    function showProgress(text) {
        $('#so-progress').show();
        $('.so-progress-text').text(text || 'Выполняется...');
        $('.so-progress-fill').css('width', '30%');
        $('#so-results').hide().removeClass('success error');
    }
    
    // Hide progress
    function hideProgress() {
        $('#so-progress').hide();
        $('.so-progress-fill').css('width', '0%');
    }
    
    // Show result
    function showResult(message, type) {
        $('#so-results')
            .text(message)
            .show()
            .removeClass('success error')
            .addClass(type);
    }
    
    // Update progress bar
    function updateProgress(percent) {
        $('.so-progress-fill').css('width', percent + '%');
    }
    
    // Optimize Images
    $('#so-optimize-images').on('click', function() {
        if (!confirm('Запустить оптимизацию всех изображений? Это может занять несколько минут.')) {
            return;
        }
        
        showProgress('Оптимизация изображений...');
        
        $.ajax({
            url: so_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'so_optimize_images',
                nonce: so_ajax.nonce
            },
            success: function(response) {
                hideProgress();
                if (response.success) {
                    showResult(response.data.message, 'success');
                    // Обновить статистику
                    loadHealthStatus();
                } else {
                    showResult('Ошибка: ' + (response.data || 'Неизвестная ошибка'), 'error');
                }
            },
            error: function() {
                hideProgress();
                showResult('Произошла ошибка при выполнении запроса', 'error');
            }
        });
    });
    
    // Optimize Database
    $('#so-optimize-database').on('click', function() {
        if (!confirm('Запустить оптимизацию базы данных? Будут удалены: ревизии, мусор, транзиенты.')) {
            return;
        }
        
        showProgress('Оптимизация базы данных...');
        
        $.ajax({
            url: so_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'so_optimize_database',
                nonce: so_ajax.nonce
            },
            success: function(response) {
                hideProgress();
                if (response.success) {
                    showResult(response.data.message, 'success');
                    // Обновить статистику
                    loadHealthStatus();
                } else {
                    showResult('Ошибка: ' + (response.data || 'Неизвестная ошибка'), 'error');
                }
            },
            error: function() {
                hideProgress();
                showResult('Произошла ошибка при выполнении запроса', 'error');
            }
        });
    });
    
    // Purge Transients
    $('#so-purge-transients').on('click', function() {
        if (!confirm('Удалить все транзиенты? Это безопасно, но может временно замедлить сайт.')) {
            return;
        }
        
        showProgress('Очистка транзиентов...');
        
        $.ajax({
            url: so_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'so_purge_transients',
                nonce: so_ajax.nonce
            },
            success: function(response) {
                hideProgress();
                if (response.success) {
                    showResult(response.data.message, 'success');
                    loadHealthStatus();
                } else {
                    showResult('Ошибка: ' + (response.data || 'Неизвестная ошибка'), 'error');
                }
            },
            error: function() {
                hideProgress();
                showResult('Произошла ошибка при выполнении запроса', 'error');
            }
        });
    });
    
    // Refresh Health Status
    $('#so-refresh-health').on('click', function() {
        loadHealthStatus();
    });
    
    // Load Health Status
    function loadHealthStatus() {
        $.ajax({
            url: so_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'so_get_health_status',
                nonce: so_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayHealthStatus(response.data);
                }
            },
            error: function() {
                console.error('Failed to load health status');
            }
        });
    }
    
    // Display Health Status
    function displayHealthStatus(data) {
        // Overall status
        var overallEl = $('#so-health-overall');
        overallEl.removeClass('so-health-good so-health-warning so-health-critical');
        
        var overallText = '';
        var overallIcon = '';
        
        if (data.overall === 'good') {
            overallText = 'Отличное состояние!';
            overallIcon = '✓';
        } else if (data.overall === 'warning') {
            overallText = 'Есть проблемы (' + data.warning_count + ')';
            overallIcon = '⚠';
        } else {
            overallText = 'Требует внимания!';
            overallIcon = '✗';
        }
        
        overallEl.addClass('so-health-' + data.overall);
        overallEl.find('.so-health-indicator').text(overallIcon);
        overallEl.find('.so-health-text').text(overallText);
        
        // Health grid
        var gridHtml = '';
        
        for (var key in data.status) {
            var item = data.status[key];
            var statusClass = 'status-' + item.status;
            
            gridHtml += '<div class="so-health-item ' + statusClass + '">';
            gridHtml += '<div class="so-health-item-label">' + item.label + '</div>';
            gridHtml += '<div class="so-health-item-value">' + item.value + '</div>';
            gridHtml += '<div class="so-health-item-message">' + item.message + '</div>';
            gridHtml += '</div>';
        }
        
        $('#so-health-grid').html(gridHtml);
    }
    
    // Load health status on page load
    $(document).ready(function() {
        loadHealthStatus();
    });
    
})(jQuery);
