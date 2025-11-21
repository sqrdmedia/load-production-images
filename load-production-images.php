<?php
/**
 * Plugin Name: Load Production Images
 * Plugin URI: https://joeljenkins.me/plugins
 * Description: Load images from your production server on local development or staging environments without having to download or sync gigabytes of images. <a href="/wp-admin/options-general.php?page=load-production-images">Manage Settings</a>
 * Version: 1.6.0
 * Author: Joel Jenkins
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class Load_Production_Images {

    /**
     * Plugin options
     */
    private $options;

    /**
     * Production site URL
     */
    private $live_site_url;

    /**
     * Current site URL
     */
    private $current_site_url;

    /**
     * Uploads path
     */
    private $uploads_path;

    /**
     * Whether currently on production site
     */
    private $is_production;

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->options = get_option('lpi_options', array());
        $this->current_site_url = rtrim(home_url(), '/');
        $this->live_site_url = isset($this->options['live_site_url'])
            ? rtrim($this->options['live_site_url'], '/')
            : '';
        $this->is_production = ($this->current_site_url === $this->live_site_url);

        // Build uploads path
        $is_multisite = isset($this->options['is_multisite']) ? $this->options['is_multisite'] : false;
        $multisite_id = isset($this->options['multisite_id']) ? $this->options['multisite_id'] : '';

        $this->uploads_path = '/wp-content/uploads';
        if ($is_multisite && $multisite_id) {
            $this->uploads_path = '/wp-content/uploads/sites/' . intval($multisite_id);
        }

        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));

        // Initialize image loading if conditions are met
        $this->maybe_init_image_loading();
    }

    /**
     * Initialize image loading if enabled and not on production
     */
    private function maybe_init_image_loading() {
        $enabled = isset($this->options['enabled']) ? $this->options['enabled'] : false;

        if ($enabled && $this->live_site_url && !$this->is_production) {
            // Filter upload directory URL
            add_filter('upload_dir', array($this, 'filter_upload_dir'));

            // Filter attachment URL
            add_filter('wp_get_attachment_url', array($this, 'filter_attachment_url'), 10, 2);

            // Filter image srcset
            add_filter('wp_calculate_image_srcset', array($this, 'filter_srcset'), 10, 5);

            // Filter content to replace image URLs
            add_filter('the_content', array($this, 'filter_content_urls'), 999);
            add_filter('post_thumbnail_html', array($this, 'filter_content_urls'), 999);
            add_filter('get_avatar', array($this, 'filter_content_urls'), 999);
            add_filter('widget_text', array($this, 'filter_content_urls'), 999);
            add_filter('wp_get_attachment_image', array($this, 'filter_content_urls'), 999);

            // Filter image metadata
            add_filter('wp_get_attachment_image_src', array($this, 'filter_image_src'), 10, 4);

            // Admin notice for active state
            add_action('admin_notices', array($this, 'admin_notice_active'));

            // Add local fallback script if enabled
            $enable_fallback = isset($this->options['enable_local_fallback']) ? $this->options['enable_local_fallback'] : false;
            if ($enable_fallback) {
                add_action('wp_footer', array($this, 'output_fallback_script'), 999);
                add_action('admin_footer', array($this, 'output_fallback_script'), 999);
            }
        } elseif ($enabled && $this->live_site_url && $this->is_production) {
            // Admin notice for production site
            add_action('admin_notices', array($this, 'admin_notice_production'));
        }
    }

    /**
     * Replace local upload URL with production URL
     */
    private function replace_upload_url($url) {
        return str_replace(
            $this->current_site_url . '/wp-content/uploads',
            $this->live_site_url . $this->uploads_path,
            $url
        );
    }

    /**
     * Filter upload directory
     */
    public function filter_upload_dir($uploads) {
        $uploads['baseurl'] = $this->live_site_url . $this->uploads_path;
        $uploads['url'] = $this->replace_upload_url($uploads['url']);
        return $uploads;
    }

    /**
     * Filter attachment URL
     */
    public function filter_attachment_url($url, $attachment_id) {
        return $this->replace_upload_url($url);
    }

    /**
     * Filter image srcset
     */
    public function filter_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        if (is_array($sources)) {
            foreach ($sources as &$source) {
                $source['url'] = $this->replace_upload_url($source['url']);
            }
        }
        return $sources;
    }

    /**
     * Filter content URLs
     */
    public function filter_content_urls($content) {
        return $this->replace_upload_url($content);
    }

    /**
     * Filter image src
     */
    public function filter_image_src($image, $attachment_id, $size, $icon) {
        if (is_array($image) && isset($image[0])) {
            $image[0] = $this->replace_upload_url($image[0]);
        }
        return $image;
    }

    /**
     * Admin notice when active
     */
    public function admin_notice_active() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'settings_page_load-production-images') {
            return;
        }

        $multisite_info = '';
        if (strpos($this->uploads_path, '/sites/') !== false) {
            $multisite_info = ' (Multisite: ' . esc_html($this->uploads_path) . ')';
        }
        ?>
        <div class="notice notice-info is-dismissible" style="max-width: 600px;">
            <p><strong>Load Production Images:</strong> Active - Loading from <?php echo esc_html($this->live_site_url) . $multisite_info; ?></p>
        </div>
        <?php
    }

    /**
     * Admin notice when on production
     */
    public function admin_notice_production() {
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

    /**
     * Output fallback script for local images
     */
    public function output_fallback_script() {
        ?>
        <script>
        (function() {
            var prodUrl = <?php echo wp_json_encode($this->live_site_url . $this->uploads_path); ?>;
            var localUrl = <?php echo wp_json_encode($this->current_site_url . '/wp-content/uploads'); ?>;

            function handleImageError(img) {
                if (img.dataset.lpiFallbackTried) return;
                img.dataset.lpiFallbackTried = 'true';

                var src = img.getAttribute('src');
                if (src && src.indexOf(prodUrl) !== -1) {
                    img.src = src.replace(prodUrl, localUrl);
                }

                var srcset = img.getAttribute('srcset');
                if (srcset && srcset.indexOf(prodUrl) !== -1) {
                    img.srcset = srcset.split(',').map(function(entry) {
                        return entry.replace(prodUrl, localUrl);
                    }).join(',');
                }
            }

            // Handle errors on existing images
            document.querySelectorAll('img').forEach(function(img) {
                img.addEventListener('error', function() {
                    handleImageError(this);
                });

                // Check if image already failed (cached error)
                if (img.complete && img.naturalHeight === 0 && img.src) {
                    handleImageError(img);
                }
            });

            // Handle dynamically added images
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeName === 'IMG') {
                            node.addEventListener('error', function() {
                                handleImageError(this);
                            });
                        }
                        if (node.querySelectorAll) {
                            node.querySelectorAll('img').forEach(function(img) {
                                img.addEventListener('error', function() {
                                    handleImageError(this);
                                });
                            });
                        }
                    });
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        })();
        </script>
        <?php
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'Load Production Images',
            'Load Production Images',
            'manage_options',
            'load-production-images',
            array($this, 'settings_page')
        );
    }

    /**
     * Initialize settings
     */
    public function settings_init() {
        register_setting(
            'lpi_settings',
            'lpi_options',
            array(
                'sanitize_callback' => array($this, 'sanitize_options'),
            )
        );

        add_settings_section(
            'lpi_settings_section',
            '',
            array($this, 'settings_section_callback'),
            'lpi_settings'
        );

        add_settings_field(
            'enabled',
            'Enable Image Loading',
            array($this, 'render_enabled_field'),
            'lpi_settings',
            'lpi_settings_section'
        );

        add_settings_field(
            'live_site_url',
            'Production Server URL',
            array($this, 'render_live_site_url_field'),
            'lpi_settings',
            'lpi_settings_section'
        );

        add_settings_field(
            'is_multisite',
            'Production Server is Multisite',
            array($this, 'render_is_multisite_field'),
            'lpi_settings',
            'lpi_settings_section'
        );

        add_settings_field(
            'multisite_id',
            'Multisite ID',
            array($this, 'render_multisite_id_field'),
            'lpi_settings',
            'lpi_settings_section'
        );

        add_settings_field(
            'enable_local_fallback',
            'Local Fallback',
            array($this, 'render_local_fallback_field'),
            'lpi_settings',
            'lpi_settings_section'
        );
    }

    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        $sanitized = array();

        // Sanitize enabled checkbox
        $sanitized['enabled'] = isset($input['enabled']) ? 1 : 0;

        // Sanitize and validate URL
        if (isset($input['live_site_url'])) {
            $url = esc_url_raw(trim($input['live_site_url']));
            // Remove trailing slash for consistency
            $sanitized['live_site_url'] = rtrim($url, '/');
        } else {
            $sanitized['live_site_url'] = '';
        }

        // Sanitize multisite checkbox
        $sanitized['is_multisite'] = isset($input['is_multisite']) ? 1 : 0;

        // Sanitize multisite ID (must be positive integer)
        if (isset($input['multisite_id'])) {
            $sanitized['multisite_id'] = absint($input['multisite_id']);
        } else {
            $sanitized['multisite_id'] = '';
        }

        // Sanitize local fallback checkbox
        $sanitized['enable_local_fallback'] = isset($input['enable_local_fallback']) ? 1 : 0;

        return $sanitized;
    }

    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        ?>
        <div style="background: #f0f0f1; border-left: 4px solid #2271b1; padding: 15px 20px; margin: 20px 0;">
            <p style="margin: 0 0 16px 0; font-size: 14px;">Load images from your production server on local development or staging environments without having to download or sync gigabytes of images.</p>
            <p style="margin: 0; font-size: 14px;">Plugin will not work if uploaded and activated on the production server. That would get ugly. I got your back.</p>
        </div>
        <?php
    }

    /**
     * Render enabled field
     */
    public function render_enabled_field() {
        $enabled = isset($this->options['enabled']) ? $this->options['enabled'] : false;
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

    /**
     * Render live site URL field
     */
    public function render_live_site_url_field() {
        $value = isset($this->options['live_site_url']) ? $this->options['live_site_url'] : '';
        ?>
        <input type="url" name="lpi_options[live_site_url]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="https://example.com" />
        <p class="description">The URL of your production server to load images from (e.g., https://example.com)</p>
        <?php
    }

    /**
     * Render is multisite field
     */
    public function render_is_multisite_field() {
        $checked = isset($this->options['is_multisite']) ? $this->options['is_multisite'] : false;
        ?>
        <label>
            <input type="checkbox" name="lpi_options[is_multisite]" value="1" <?php checked($checked, 1); ?> id="lpi-multisite-checkbox" />
            Check if your production server is a WordPress Multisite installation
        </label>
        <?php
    }

    /**
     * Render multisite ID field
     */
    public function render_multisite_id_field() {
        $value = isset($this->options['multisite_id']) ? $this->options['multisite_id'] : '';
        $is_multisite = isset($this->options['is_multisite']) ? $this->options['is_multisite'] : false;
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

    /**
     * Render local fallback field
     */
    public function render_local_fallback_field() {
        $checked = isset($this->options['enable_local_fallback']) ? $this->options['enable_local_fallback'] : false;
        ?>
        <label>
            <input type="checkbox" name="lpi_options[enable_local_fallback]" value="1" <?php checked($checked, 1); ?> />
            Fall back to local images if production returns 404
        </label>
        <p class="description">When enabled, if an image doesn't exist on the production server, it will automatically try to load from your local environment instead.</p>
        <?php
    }

    /**
     * Render settings page
     */
    public function settings_page() {
        $enabled = isset($this->options['enabled']) ? $this->options['enabled'] : false;
        $live_site_url = isset($this->options['live_site_url']) ? $this->options['live_site_url'] : '';
        $is_multisite = isset($this->options['is_multisite']) ? $this->options['is_multisite'] : false;
        $multisite_id = isset($this->options['multisite_id']) ? $this->options['multisite_id'] : '';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

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
                <?php if ($this->is_production && $enabled && $live_site_url): ?>
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 20px;">
                        <h3 style="margin-top: 0; color: #856404;">
                            Status: Disabled on Production
                        </h3>
                        <p style="margin-bottom: 5px;"><strong>Current Site:</strong> <?php echo esc_html($this->current_site_url); ?></p>
                        <p style="margin-bottom: 5px;"><strong>Production Server:</strong> <?php echo esc_html($live_site_url); ?></p>
                        <p style="margin-bottom: 0; font-size: 13px; color: #856404;">The plugin is automatically disabled because you're on the production site. This prevents infinite loops.</p>
                    </div>
                <?php else: ?>
                    <div style="background: <?php echo $enabled && $live_site_url && !$this->is_production ? '#d4edda' : '#f8f9fa'; ?>; border: 1px solid <?php echo $enabled && $live_site_url && !$this->is_production ? '#c3e6cb' : '#dee2e6'; ?>; border-radius: 4px; padding: 20px;">
                        <h3 style="margin-top: 0; color: <?php echo $enabled && $live_site_url && !$this->is_production ? '#155724' : '#495057'; ?>;">
                            Status: <?php echo $enabled && $live_site_url && !$this->is_production ? 'Active' : 'Inactive'; ?>
                        </h3>
                        <?php
                        $enable_fallback = isset($this->options['enable_local_fallback']) ? $this->options['enable_local_fallback'] : false;
                        if ($enabled && $live_site_url && !$this->is_production): ?>
                            <p style="margin-bottom: 5px;"><strong>Production Server:</strong> <?php echo esc_html($live_site_url); ?></p>
                            <?php if ($is_multisite && $multisite_id): ?>
                                <p style="margin-bottom: 5px;"><strong>Multisite Path:</strong> <?php echo esc_html('/uploads/sites/' . $multisite_id . '/'); ?></p>
                            <?php endif; ?>
                            <?php if ($enable_fallback): ?>
                                <p style="margin-bottom: 5px;"><strong>Local Fallback:</strong> Enabled</p>
                            <?php endif; ?>
                            <p style="margin-bottom: 0; font-size: 13px; color: #155724;">All image requests are being redirected to the production server.<?php echo $enable_fallback ? ' Images not found on production will fall back to local.' : ''; ?></p>
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
}

// Initialize the plugin
Load_Production_Images::get_instance();
