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
 * Test shortcode for TryOnTool API integration
 */
class WooFashnaiPreview_Shortcode {
    /**
     * Initialize the class
     */
    public function __construct() {
        add_shortcode('woo_fashnai_test', array($this, 'render_test_form'));
        add_action('wp_ajax_woo_fashnai_test_api', array($this, 'handle_test_submission'));
        add_action('wp_ajax_nopriv_woo_fashnai_test_api', array($this, 'handle_test_submission'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue necessary scripts and styles for the shortcode
     */
    public function enqueue_scripts() {
        // Check if the shortcode is active before enqueueing?
        wp_enqueue_style(
            'woo-fashnai-preview-style',
            WOO_FASHNAI_PREVIEW_PLUGIN_URL . 'assets/css/woo-fashnai-preview.css',
            array(),
            WOO_FASHNAI_PREVIEW_VERSION
        );

        wp_enqueue_script(
            'woo-fashnai-preview-script',
            WOO_FASHNAI_PREVIEW_PLUGIN_URL . 'assets/js/woo-fashnai-preview.js',
            array( 'jquery', 'wp-i18n' ),
            WOO_FASHNAI_PREVIEW_VERSION,
            true
        );

        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'woo-fashnai-preview-script', WOO_FASHNAI_TEXTDOMAIN, WOO_FASHNAI_PREVIEW_PLUGIN_DIR . 'languages' );
        }

        wp_localize_script(
            'woo-fashnai-preview-script',
            'wooFashnaiPreview',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('woo_fashnai_preview_nonce'),
            )
        );
    }

    /**
     * Render the test form shortcode
     */
    public function render_test_form() {
        // Check if plugin is enabled
        if (!get_option('woo_fashnai_preview_enabled')) {
            return '<p>' . __('Try-On Tool Preview is not available.', 'woo-fashnai-preview') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="woo-fashnai-preview-test-form">
            <h3><?php _e('Test Try-On Tool API Integration', 'woo-fashnai-preview'); ?></h3>
            
            <form id="woo-fashnai-test-form" enctype="multipart/form-data">
                <div class="form-field">
                    <label for="user_image"><?php _e('Upload Your Image:', 'woo-fashnai-preview'); ?></label>
                    <input type="file" id="user_image" name="user_image" accept="image/jpeg,image/png" required>
                </div>
                
                <div class="form-field">
                    <label for="product_image_url"><?php _e('Product Image URL:', 'woo-fashnai-preview'); ?></label>
                    <input type="url" id="product_image_url" name="product_image_url" required>
                </div>
                
                <div class="form-submit">
                    <button type="submit" class="button"><?php _e('Generate Preview', 'woo-fashnai-preview'); ?></button>
                </div>
            </form>
            
            <div id="woo-fashnai-result" style="display: none;">
                <h4><?php _e('Result:', 'woo-fashnai-preview'); ?></h4>
                <div class="result-content"></div>
            </div>
            
            <div id="woo-fashnai-error" style="display: none;">
                <h4><?php _e('Error:', 'woo-fashnai-preview'); ?></h4>
                <div class="error-content"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle form submission
     */
    public function handle_test_submission() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'woo_fashnai_preview_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'woo-fashnai-preview')));
        }

        // Check if plugin is enabled
        if (!get_option('woo_fashnai_preview_enabled')) {
            wp_send_json_error(array('message' => __('Try-On Tool Preview is currently disabled', 'woo-fashnai-preview')));
        }

        // Check for file upload
        if (!isset($_FILES['user_image']) || $_FILES['user_image']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('User image upload failed', 'woo-fashnai-preview')));
        }

        // Check for product image URL
        if (empty($_POST['product_image_url'])) {
            wp_send_json_error(array('message' => __('Product image URL is required', 'woo-fashnai-preview')));
        }

        // Validate and store uploaded file
        $uploaded_file = $_FILES['user_image'];
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/woo-fashnai-temp';
        
        // Create temp directory if not exists
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Generate unique filename
        $filename = wp_unique_filename($temp_dir, $uploaded_file['name']);
        $file_path = $temp_dir . '/' . $filename;
        
        // Move uploaded file to temp directory
        if (!move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
            wp_send_json_error(array('message' => __('Failed to save uploaded file', 'woo-tryontool-preview')));
        }

        // Get product image URL
        $product_image_url = esc_url_raw($_POST['product_image_url']);

        // Call the API handler
        $api_handler = new WooFashnaiPreview_API_Handler(); // Updated class name
        $response = $api_handler->generate_preview($file_path, $product_image_url, '', array()); // Pass empty array for options

        // Delete temp file regardless of result
        @unlink($file_path);

        // Handle response
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => __('Try On Tool API Error: ', 'woo-fashnai-preview') . $response->get_error_message(),
                'details' => $response->get_error_data(),
            ));
        }

        // Process successful response
        wp_send_json_success(array(
            'message' => __('Successfully generated preview', 'woo-tryontool-preview'),
            'data' => $response,
        ));
    }
}
