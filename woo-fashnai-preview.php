<?php
/**
 * A WooCommerce plugin that allows users to virtually try on clothing and accessories.
 *
 * @package Try-On Tool
 * @copyright 2025 DataDove LTD
 * @license GPL-2.0-only
 *
 * This file is part of Try-On Tool.
 * 
 * Try-On Tool is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 2 only.
 * 
 * Try-On Tool is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */
/**
 * Plugin Name: Try-On Tool
 * Description: Connect WooCommerce with Try-On Tool for AI-generated virtual try-on previews
 * Version: 1.1.0
 * Author: DataDove
 * Text Domain: woo-fashnai-preview
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */
// Modified by DataDove LTD on 2025-08-07

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('WOO_FASHNAI_PREVIEW_VERSION', '1.1.0');
define('WOO_FASHNAI_PREVIEW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_FASHNAI_PREVIEW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FASHNAI_RELAY_ENDPOINT', 'https://tryontool.com/wp-json/fashnai/v1/preview');
define('FASHNAI_VALIDATE_ENDPOINT', 'https://tryontool.com/wp-json/fashnai/v1/validate-license');
define('WOO_FASHNAI_INACTIVITY_WINDOW', YEAR_IN_SECONDS);
// Primary text-domain constant (used throughout PHP & JS)
define('WOO_FASHNAI_TEXTDOMAIN', 'woo-fashnai-preview');

/**
 * Check if WooCommerce is active
 */
function woo_fashnai_preview_check_woocommerce() {
    if (!in_array(
        'woocommerce/woocommerce.php',
        apply_filters('active_plugins', get_option('active_plugins'))
    )) {
        add_action('admin_notices', 'woo_fashnai_preview_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * WooCommerce missing notice
 */
function woo_fashnai_preview_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('WooCommerce Try-On Tool Preview requires WooCommerce to be installed and activated!', 'woo-fashnai-preview'); ?></p>
    </div>
    <?php
}

// Add debug logging
add_action('init', function() {
    static $already_run = false;
    if ($already_run) {
        return;
    }
    $already_run = true;
    error_log('WooTryOnTool Plugin: Initializing');
});

class WooFashnaiPreview {
    private static $instance = null;

    private function __construct() {
        // Add debug logging
        error_log('WooTryOnTool Plugin: Inside init function');
        
        // Check if WooCommerce is active
        if (!woo_fashnai_preview_check_woocommerce()) {
            error_log('WooTryOnTool Plugin: WooCommerce not active');
            return;
        }

        // Load text domain for translation
        load_plugin_textdomain(
            'woo-fashnai-preview',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );

        // Load classes
        require_once WOO_FASHNAI_PREVIEW_PLUGIN_DIR . 'includes/class-wasabi-client.php';
        require_once WOO_FASHNAI_PREVIEW_PLUGIN_DIR . 'includes/class-api-handler.php';
        require_once WOO_FASHNAI_PREVIEW_PLUGIN_DIR . 'includes/class-settings.php';
        require_once WOO_FASHNAI_PREVIEW_PLUGIN_DIR . 'includes/class-shortcode.php';
        require_once WOO_FASHNAI_PREVIEW_PLUGIN_DIR . 'includes/class-product-button.php';

        // Initialize classes
        $settings = new WooFashnaiPreview_Settings();
        $shortcode = new WooFashnaiPreview_Shortcode();
        $product_button = new WooFashnaiPreview_Product_Button();
        
        // Add debug logging
        error_log('WooTryOnTool Plugin: Classes initialized');
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

// Initialize the plugin
add_action('plugins_loaded', ['WooFashnaiPreview', 'get_instance']);

// Register activation hook
register_activation_hook(__FILE__, 'woo_fashnai_preview_activate');

/**
 * Plugin activation
 */
function woo_fashnai_preview_activate() {
    // Add default options
    add_option('woo_fashnai_preview_enabled', false);
    add_option('woo_fashnai_daily_credits', 0);
    add_option('woo_fashnai_logged_in_only', false);
    add_option('woo_fashnai_allowed_roles', array());
    add_option('woo_fashnai_allowed_user_ids', '');
    add_option('woo_fashnai_required_user_tag', '');
}

add_action('rest_api_init', function () {
    register_rest_route('woo-tryontool/v1', '/wasabi-image', array(
        'methods' => 'GET',
        'callback' => function ($request) {
            $key = $request->get_param('key');
            if (!$key) {
                return new WP_Error('no_key', 'Missing key', array('status' => 400));
            }

            require_once __DIR__ . '/includes/class-wasabi-client.php';
            $s3 = WooFashnai_Wasabi::client();
            $bucket = WooFashnai_Wasabi::bucket();

            try {
                $result = $s3->getObject([
                    'Bucket' => $bucket,
                    'Key'    => $key,
                ]);
                $imageData = $result['Body'];
            } catch (Exception $e) {
                return new WP_Error('not_found', 'Image not found', array('status' => 404));
            }

            // Save to temp file for type detection/conversion
            $tmpFile = tempnam(sys_get_temp_dir(), 'wasabiimg');
            file_put_contents($tmpFile, $imageData);

            $imageType = @exif_imagetype($tmpFile);
            if ($imageType !== IMAGETYPE_JPEG) {
                // Convert to JPEG
                switch ($imageType) {
                    case IMAGETYPE_PNG:
                        $src = imagecreatefrompng($tmpFile);
                        break;
                    case IMAGETYPE_GIF:
                        $src = imagecreatefromgif($tmpFile);
                        break;
                    case IMAGETYPE_WEBP:
                        $src = function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmpFile) : false;
                        break;
                    default:
                        $src = imagecreatefromstring(file_get_contents($tmpFile));
                }
                if ($src) {
                    header('Content-Type: image/jpeg');
                    imagejpeg($src, null, 90);
                    imagedestroy($src);
                    @unlink($tmpFile);
                    exit;
                }
            }
            // If already JPEG, just output
            header('Content-Type: image/jpeg');
            readfile($tmpFile);
            @unlink($tmpFile);
            exit;
        },
        'permission_callback' => '__return_true', // Public access
    ));
});

// Update Wasabi client calls to use server API
function woo_fashnai_upload_image_to_wasabi($image_path) {
    $response = wp_remote_post('http://yourserver.com/wp-json/tryontool/v1/wasabi/upload', array(
        'body' => array('image_path' => $image_path),
    ));
    return $response;
}

function woo_fashnai_delete_image_from_wasabi($image_key) {
    $response = wp_remote_post('http://yourserver.com/wp-json/tryontool/v1/wasabi/delete', array(
        'body' => array('image_key' => $image_key),
    ));
    return $response;
}

function woo_fashnai_list_images_from_wasabi($user_id) {
    $response = wp_remote_post('http://yourserver.com/wp-json/tryontool/v1/wasabi/list', array(
        'body' => array('user_id' => $user_id),
    ));

    if (is_wp_error($response)) {
        error_log('Error fetching images: ' . $response->get_error_message());
        return new WP_Error('request_failed', 'Error fetching images', array('status' => 500));
    }

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    if (isset($data['success']) && $data['success']) {
        return $data['images'];
    } else {
        error_log('Error fetching images: ' . $response_body);
        return new WP_Error('request_failed', 'Error fetching images', array('status' => 500));
    }
}

// Add custom cron schedule for every 10 minutes
add_filter('cron_schedules', function($schedules){
    if(!isset($schedules['ten_minutes'])){
        $schedules['ten_minutes'] = array(
            'interval' => 10 * MINUTE_IN_SECONDS,
            'display'  => 'Every 10 Minutes'
        );
    }
    return $schedules;
});

// Schedule the cron job for deleting expired images
add_action('init', function() {
    if (!wp_next_scheduled('woo_fashnai_delete_expired_images')) {
        wp_schedule_event(time(), 'ten_minutes', 'woo_fashnai_delete_expired_images');
    }
});

// Hook into the cron job
add_action('woo_fashnai_delete_expired_images', function() {
    global $wpdb;
    error_log('Checking for expired image transients...');
    $transients = $wpdb->get_results("SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_woo_fashnai_image_deletion_%'");
    foreach ($transients as $transient) {
        $transient_key = str_replace('_transient_', '', $transient->option_name);
        $data = get_transient($transient_key);
        if ($data) {
            $ts = $data['timestamp'] ?? ($data['upload_time'] ?? false);
            if ($ts && (time() - $ts) > YEAR_IN_SECONDS) {
                error_log('Deleting image with key: ' . ($data['key'] ?? ''));
                WooFashnai_Wasabi::delete($data['key'] ?? '');
                delete_transient($transient_key);
            }
        } else {
            // Transient expired, nothing to clean
            delete_option($transient->option_name);
        }
    }
});

/* -------------------------------------------------------------------------
 * LOG-OUT BASED IMAGE PURGE FLOW
 * -------------------------------------------------------------------------
 * 1. When a user logs OUT we create a   woo_fashnai_pending_delete_{ID}
 *    transient with the current timestamp.
 * 2. If they log back in **before** the inactivity window we delete that
 *    transient – their images are safe.
 * 3. A cron job (see below) checks these transients every 10 min and, for
 *    any older than the window, deletes **all** images for that account.
 */

add_action( 'wp_logout', 'woo_fashnai_mark_user_logout' );
function woo_fashnai_mark_user_logout() {
    $uid = get_current_user_id();
    if ( $uid ) {
        set_transient( 'woo_fashnai_pending_delete_' . $uid, time(), WOO_FASHNAI_INACTIVITY_WINDOW + ( 10 * MINUTE_IN_SECONDS ) );
    }
}

add_action( 'wp_login', 'woo_fashnai_clear_pending_deletion', 10, 2 );
function woo_fashnai_clear_pending_deletion( $user_login, $wp_user ) {
    $uid = $wp_user->ID;
    delete_transient( 'woo_fashnai_pending_delete_' . $uid );

    // Record last-login timestamp
    $now_mysql = current_time( 'mysql' );
    update_user_meta( $uid, 'woo_fashnai_last_login', $now_mysql );

    // Update central consent registry (if any)
    $consents = get_option( 'woo_fashnai_consents', array() );
    if ( isset( $consents[ $uid ] ) ) {
        $consents[ $uid ]['last_login'] = $now_mysql;
    } else {
        $consents[ $uid ] = array(
            'user_id'          => $uid,
            'email'            => $wp_user->user_email,
            'consent_timestamp'=> '',
            'last_login'       => $now_mysql,
        );
    }
    update_option( 'woo_fashnai_consents', $consents, false );
}

// -------------------------------------------------------------------------
//  CRON: CLEAN UP IMAGES FOR INACTIVE USERS
// -------------------------------------------------------------------------

add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'woo_fashnai_cleanup_inactive_users' ) ) {
        wp_schedule_event( time(), 'ten_minutes', 'woo_fashnai_cleanup_inactive_users' );
    }
} );

add_action( 'woo_fashnai_cleanup_inactive_users', function () {
    // Delegate to the static helper inside the Product Button class so that
    // the deletion logic lives in a single place.
    if ( class_exists( 'WooFashnaiPreview_Product_Button' ) ) {
        WooFashnaiPreview_Product_Button::check_and_delete_images();
    }
} );

/* -------------------------------------------------------------------------
 *  I18N: Load translations as early as possible and alias legacy domain
 * ------------------------------------------------------------------------- */

add_action( 'plugins_loaded', 'woo_fashnai_preview_load_textdomain', 0 );
function woo_fashnai_preview_load_textdomain() {
    load_plugin_textdomain(
        WOO_FASHNAI_TEXTDOMAIN,
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages/'
    );

    // Legacy compatibility: mirror strings to the old text-domain so that
    // any calls still using "woo-tryontool-preview" resolve correctly.
    global $l10n;
    if ( isset( $l10n[ WOO_FASHNAI_TEXTDOMAIN ] ) ) {
        $l10n['woo-tryontool-preview'] = $l10n[ WOO_FASHNAI_TEXTDOMAIN ];
    }
} 