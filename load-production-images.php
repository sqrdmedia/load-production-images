<?php
/**
 * Plugin Name: Load Production Images
 * Plugin URI: https://joeljenkins.me/plugins
 * Description: Load images from your production server on local development or staging environments without having to download or sync gigabytes of images. <a href="/wp-admin/options-general.php?page=load-production-images">Manage Settings</a>
 * Version: 1.4.0
 * Author: Joel Jenkins
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add settings menu
add_action('admin_menu', 'lpi_add_admin_menu');
function lpi_add_admin_menu() {
    add_options_page(
        'Load Production Images',
        'Load Production Images',
        'manage_options',
        'load-production-images',
        'lpi_settings_page'
    );
}

// Register settings
add_action('admin_init', 'lpi_settings_init');
function lpi_settings_init() {
    register_setting('lpi_settings', 'lpi_options');
    
    add_settings_section(
        'lpi_settings_section',
        '',
        'lpi_settings_section_callback',
        'lpi_settings'
    );
    
    add_settings_field(
        'enabled',
        'Enable Image Loading',
        'lpi_enabled_render',
        'lpi_settings',
        'lpi_settings_section'
    );
    
    add_settings_field(
        'live_site_url',
        'Production Server URL',
        'lpi_live_site_url_render',
        'lpi_settings',
        'lpi_settings_section'
    );
    
    add_settings_field(
        'is_multisite',
        'Production Server is Multisite',
        'lpi_is_multisite_render',
        'lpi_settings',
        'lpi_settings_section'
    );
    
    add_settings_field(
        'multisite_id',
        'Multisite ID',
        'lpi_multisite_id_render',
        'lpi_settings',
        'lpi_settings_section'
    );
}

function lpi_settings_section_callback() {
    ?>
    <div style="background: #f0f0f1; border-left: 4px solid #2271b1; padding: 15px 20px; margin: 20px 0;">
        <p style="margin: 0 0 16px 0; font-size: 14px;">Load images from your production server on local development or staging environments without having to download or sync gigabytes of images.</p>
        <p style="margin: 0; font-size: 14px;">Plugin will not work if uploaded and activated on the production server. That would get ugly. I got your back.</p>
    </div>
    <?php
}

function lpi_enabled_render() {
    $options = get_option('lpi_options');
    $enabled = isset($options['enabled']) ? $options['enabled'] : false;
    ?>
    <label class="lpi-toggle-switch">
        <input type="checkbox" name="lpi_options[enabled]" value="1" <?php checked($enabled, 1); ?> id="lpi-enabled-checkbox" />
        <span class="lpi-slider"></span>
    </label>
    <span style="margin-left: 10px; font-weight: 500;" id="lpi-status-text">
        <?php echo $enabled ? 'Active' : 'Inactive'; ?>
    </span>
    <p class="description">Enable or disable production image loading without deactivating the plugin.</p>
    
    <style>
    .lpi-toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
        vertical-align: middle;
    }
    
    .lpi-toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .lpi-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .3s;
        border-radius: 24px;
    }
    
    .lpi-slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
    }
    
    .lpi-toggle-switch input:checked + .lpi-slider {
        background-color: #2271b1;
    }
    
    .lpi-toggle-switch input:checked + .lpi-slider:before {
        transform: translateX(26px);
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('#lpi-enabled-checkbox').change(function() {
            $('#lpi-status-text').text($(this).is(':checked') ? 'Active' : 'Inactive');
        });
    });
    </script>
    <?php
}

function lpi_live_site_url_render() {
    $options = get_option('lpi_options');
    $value = isset($options['live_site_url']) ? $options['live_site_url'] : '';
    ?>
    <input type="url" name="lpi_options[live_site_url]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="https://example.com" />
    <p class="description">The URL of your production server to load images from (e.g., https://example.com)</p>
    <?php
}

function lpi_is_multisite_render() {
    $options = get_option('lpi_options');
    $checked = isset($options['is_multisite']) ? $options['is_multisite'] : false;
    ?>
    <label>
        <input type="checkbox" name="lpi_options[is_multisite]" value="1" <?php checked($checked, 1); ?> id="lpi-multisite-checkbox" />
        Check if your production server is a WordPress Multisite installation
    </label>
    <?php
}

function lpi_multisite_id_render() {
    $options = get_option('lpi_options');
    $value = isset($options['multisite_id']) ? $options['multisite_id'] : '';
    $is_multisite = isset($options['is_multisite']) ? $options['is_multisite'] : false;
    ?>
    <div id="lpi-multisite-container" style="<?php echo $is_multisite ? '' : 'display:none;'; ?>">
        <input type="number" name="lpi_options[multisite_id]" value="<?php echo esc_attr($value); ?>" class="small-text" id="lpi-multisite-id" min="1" />
        <p class="description">Enter the site ID for multisite installations (e.g., 2 for /uploads/sites/2/)</p>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#lpi-multisite-checkbox').change(function() {
            if ($(this).is(':checked')) {
                $('#lpi-multisite-container').show();
            } else {
                $('#lpi-multisite-container').hide();
            }
        });
    });
    </script>
    <?php
}

function lpi_settings_page() {
    $options = get_option('lpi_options');
    $enabled = isset($options['enabled']) ? $options['enabled'] : false;
    $live_site_url = isset($options['live_site_url']) ? $options['live_site_url'] : '';
    $is_multisite = isset($options['is_multisite']) ? $options['is_multisite'] : false;
    $multisite_id = isset($options['multisite_id']) ? $options['multisite_id'] : '';
    
    // Check if we're on the production site
    $current_site_url = rtrim(home_url(), '/');
    $is_production = ($current_site_url === $live_site_url);
    ?>
    <div class="wrap">
        <h1>Load Production Images</h1>
        
        <div style="background: white; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,0.04); max-width: 800px;">
            <form action="options.php" method="post">
                <?php
                settings_fields('lpi_settings');
                do_settings_sections('lpi_settings');
                submit_button();
                ?>
            </form>
        </div>
        
        <div style="margin-top: 30px; max-width: 800px;">
            <?php if ($is_production && $enabled && $live_site_url): ?>
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 20px;">
                    <h3 style="margin-top: 0; color: #856404;">
                        Status: Disabled on Production
                    </h3>
                    <p style="margin-bottom: 5px;"><strong>Current Site:</strong> <?php echo esc_html($current_site_url); ?></p>
                    <p style="margin-bottom: 5px;"><strong>Production Server:</strong> <?php echo esc_html($live_site_url); ?></p>
                    <p style="margin-bottom: 0; font-size: 13px; color: #856404;">The plugin is automatically disabled because you're on the production site. This prevents infinite loops.</p>
                </div>
            <?php else: ?>
                <div style="background: <?php echo $enabled && $live_site_url && !$is_production ? '#d4edda' : '#f8f9fa'; ?>; border: 1px solid <?php echo $enabled && $live_site_url && !$is_production ? '#c3e6cb' : '#dee2e6'; ?>; border-radius: 4px; padding: 20px;">
                    <h3 style="margin-top: 0; color: <?php echo $enabled && $live_site_url && !$is_production ? '#155724' : '#495057'; ?>;">
                        Status: <?php echo $enabled && $live_site_url && !$is_production ? 'Active' : 'Inactive'; ?>
                    </h3>
                    <?php if ($enabled && $live_site_url && !$is_production): ?>
                        <p style="margin-bottom: 5px;"><strong>Production Server:</strong> <?php echo esc_html($live_site_url); ?></p>
                        <?php if ($is_multisite && $multisite_id): ?>
                            <p style="margin-bottom: 5px;"><strong>Multisite Path:</strong> /uploads/sites/<?php echo esc_html($multisite_id); ?>/</p>
                        <?php endif; ?>
                        <p style="margin-bottom: 0; font-size: 13px; color: #155724;">All image requests are being redirected to the production server.</p>
                    <?php elseif (!$enabled): ?>
                        <p style="margin-bottom: 0; color: #6c757d;">Image loading is disabled. Enable it using the toggle above.</p>
                    <?php else: ?>
                        <p style="margin-bottom: 0; color: #856404;">Please enter a Production Server URL to start loading images.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Get settings and check if enabled
$options = get_option('lpi_options');
$enabled = isset($options['enabled']) ? $options['enabled'] : false;
$live_site_url = isset($options['live_site_url']) ? rtrim($options['live_site_url'], '/') : '';

// Get current site URL for comparison
$current_site_url = rtrim(home_url(), '/');

// Check if we're on the production site
$is_production = ($current_site_url === $live_site_url);

// Only run if enabled AND live site URL is set AND we're NOT on the production site
if ($enabled && $live_site_url && !$is_production) {
    
    $is_multisite = isset($options['is_multisite']) ? $options['is_multisite'] : false;
    $multisite_id = isset($options['multisite_id']) ? $options['multisite_id'] : '';
    
    // Build the uploads path
    $uploads_path = '/wp-content/uploads';
    if ($is_multisite && $multisite_id) {
        $uploads_path = '/wp-content/uploads/sites/' . $multisite_id;
    }
    
    // Define constants for use in functions
    if (!defined('LPI_LIVE_SITE_URL')) {
        define('LPI_LIVE_SITE_URL', $live_site_url);
    }
    if (!defined('LPI_UPLOADS_PATH')) {
        define('LPI_UPLOADS_PATH', $uploads_path);
    }
    
    // Get current site URL for replacement
    $current_site_url = home_url();
    
    // Filter upload directory URL
    add_filter('upload_dir', 'lpi_local_upload_dir_to_live');
    function lpi_local_upload_dir_to_live($uploads) {
        global $current_site_url;
        
        $uploads['baseurl'] = LPI_LIVE_SITE_URL . LPI_UPLOADS_PATH;
        
        // Replace current site URL with production site
        $uploads['url'] = str_replace(
            $current_site_url . '/wp-content/uploads',
            LPI_LIVE_SITE_URL . LPI_UPLOADS_PATH,
            $uploads['url']
        );
        
        return $uploads;
    }
    
    // Filter attachment URL
    add_filter('wp_get_attachment_url', 'lpi_local_attachment_url_to_live', 10, 2);
    function lpi_local_attachment_url_to_live($url, $attachment_id) {
        global $current_site_url;
        
        // Replace current site URL with production site
        $url = str_replace(
            $current_site_url . '/wp-content/uploads',
            LPI_LIVE_SITE_URL . LPI_UPLOADS_PATH,
            $url
        );
        
        return $url;
    }
    
    // Filter image srcset
    add_filter('wp_calculate_image_srcset', 'lpi_local_srcset_to_live', 10, 5);
    function lpi_local_srcset_to_live($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        global $current_site_url;
        
        if (is_array($sources)) {
            foreach ($sources as &$source) {
                // Replace current site URL with production site
                $source['url'] = str_replace(
                    $current_site_url . '/wp-content/uploads',
                    LPI_LIVE_SITE_URL . LPI_UPLOADS_PATH,
                    $source['url']
                );
            }
        }
        return $sources;
    }
    
    // Filter content to replace image URLs
    add_filter('the_content', 'lpi_local_content_image_urls_to_live', 999);
    add_filter('post_thumbnail_html', 'lpi_local_content_image_urls_to_live', 999);
    add_filter('get_avatar', 'lpi_local_content_image_urls_to_live', 999);
    add_filter('widget_text', 'lpi_local_content_image_urls_to_live', 999);
    add_filter('wp_get_attachment_image', 'lpi_local_content_image_urls_to_live', 999);
    function lpi_local_content_image_urls_to_live($content) {
        global $current_site_url;
        
        // Replace current site URL with production site
        $content = str_replace(
            $current_site_url . '/wp-content/uploads',
            LPI_LIVE_SITE_URL . LPI_UPLOADS_PATH,
            $content
        );
        
        return $content;
    }
    
    // Filter image metadata
    add_filter('wp_get_attachment_image_src', 'lpi_attachment_image_src', 10, 4);
    function lpi_attachment_image_src($image, $attachment_id, $size, $icon) {
        global $current_site_url;
        
        if (is_array($image) && isset($image[0])) {
            // Replace current site URL with production site
            $image[0] = str_replace(
                $current_site_url . '/wp-content/uploads',
                LPI_LIVE_SITE_URL . LPI_UPLOADS_PATH,
                $image[0]
            );
        }
        return $image;
    }
    
    // Add admin notice
    add_action('admin_notices', 'lpi_admin_notice');
    function lpi_admin_notice() {
        // Only show on admin pages, not on settings page
        $screen = get_current_screen();
        if ($screen && $screen->id === 'settings_page_load-production-images') {
            return;
        }
        
        $multisite_info = '';
        if (defined('LPI_UPLOADS_PATH') && strpos(LPI_UPLOADS_PATH, '/sites/') !== false) {
            $multisite_info = ' (Multisite: ' . LPI_UPLOADS_PATH . ')';
        }
        ?>
        <div class="notice notice-info is-dismissible" style="max-width: 600px;">
            <p><strong>Load Production Images:</strong> Active - Loading from <?php echo esc_html(LPI_LIVE_SITE_URL) . $multisite_info; ?></p>
        </div>
        <?php
    }
}

// Add admin notice for production site detection
if ($enabled && $live_site_url && $is_production) {
    add_action('admin_notices', 'lpi_production_notice');
    function lpi_production_notice() {
        // Only show on admin pages, not on settings page
        $screen = get_current_screen();
        if ($screen && $screen->id === 'settings_page_load-production-images') {
            return;
        }
        ?>
        <div class="notice notice-warning is-dismissible" style="max-width: 600px;">
            <p><strong>Load Production Images:</strong> Disabled automatically - You're on the production site.</p>
        </div>
        <?php
    }
}