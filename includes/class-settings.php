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
 * Settings class for WooCommerce TryOnTool Preview
 */
class WooFashnaiPreview_Settings {
    /**
     * Initialize the class
     */
    public function __construct() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        // Add settings page
        add_action('admin_menu', array($this, 'add_settings_page'));
        // Register AJAX handlers for license validation
        add_action('wp_ajax_woo_fashnai_validate_license', array($this, 'ajax_validate_license_key'));
        // Consent records fetch (admin)
        add_action('wp_ajax_woo_fashnai_get_consents', array($this, 'ajax_get_consents'));
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'woo_fashnai_preview_options',
            'woo_fashnai_preview_enabled',
            array(
                'type' => 'boolean',
                'default' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            )
        );

        // Add License Key Setting
        register_setting(
            'woo_fashnai_preview_options',
            'woo_fashnai_license_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );

        // API key option removed – key is managed server-side on relay

        register_setting(
            'woo_fashnai_preview_options',
            'woo_fashnai_daily_credits', // Keep if you want a *client-side* visual limit (doesn't enforce server-side)
            array(
                'type'              => 'integer',
                'default'           => 0,
                'sanitize_callback' => 'absint',
            )
        );
         // Keep other settings like logged_in_only, allowed_roles etc. if needed
        register_setting(
            'woo_fashnai_preview_options',
            'woo_fashnai_logged_in_only',
            array(
                'type'              => 'boolean',
                'default'           => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            )
        );
        // ... other existing settings registrations ...
        register_setting(
            'woo_fashnai_preview_options',
            'woo_fashnai_allowed_roles',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_array_of_strings' ),
                'default'           => array(),
            )
        );

        register_setting(
            'woo_fashnai_preview_options',
            'woo_fashnai_allowed_user_ids',
            array(
                'type'              => 'string', // comma-separated list stored as string
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'woo_fashnai_preview_options',
            'woo_fashnai_required_user_tag',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'woo_fashnai_preview_options',
            'woo_fashnai_require_extra_consents',
            array(
                'type' => 'boolean',
                'sanitize_callback' => function($val) { return $val ? 1 : 0; },
                'default' => 0,
            )
        );
    }

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Try-On Tool Preview Settings', 'woo-fashnai-preview'),
            __('Try-On Tool Preview', 'woo-fashnai-preview'),
            'manage_options',
            'woo-fashnai-preview',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Render the settings page
     */
    public function render_settings_page() {
        require_once WOO_FASHNAI_PREVIEW_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }

    // Function to call license validation endpoint
    public function ajax_validate_license_key() {
        check_ajax_referer( 'fashnai_validate_license_nonce', 'nonce' );

        $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
        $site_url = home_url(); // Get current site URL

        if ( empty($license_key) ) {
            wp_send_json_error( array( 'message' => __('Please enter a license key.', 'woo-fashnai-preview') ) );
        }

        $request_args = array(
            'method' => 'POST',
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body' => wp_json_encode( array(
                'license_key' => $license_key,
                'site_url'    => $site_url,
             ) ),
            'timeout' => 30,
        );

        $response = wp_remote_post( FASHNAI_VALIDATE_ENDPOINT, $request_args );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => __('Error contacting validation server: ', 'woo-fashnai-preview') . $response->get_error_message() ) );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $response_code === 200 && isset($response_body['success']) && $response_body['success'] ) {
             // Optionally store validation status/info
             update_option('woo_fashnai_license_status', 'valid');
             update_option('woo_fashnai_license_expires', isset($response_body['expires']) ? $response_body['expires'] : '');
             update_option('woo_fashnai_license_credits', isset($response_body['credits']) ? $response_body['credits'] : '');
             update_option('woo_fashnai_plan_product_id', isset($response_body['plan_product_id']) ? $response_body['plan_product_id'] : '');

             wp_send_json_success( array(
                 'message' => __('License key is valid and active!', 'woo-fashnai-preview'),
                 'credits' => isset($response_body['credits']) ? $response_body['credits'] : 'N/A',
                 'expires' => isset($response_body['expires']) ? $response_body['expires'] : 'N/A',
                 'plan_product_id' => isset($response_body['plan_product_id']) ? $response_body['plan_product_id'] : null,
             ) );
        } else {
             update_option('woo_fashnai_license_status', 'invalid');
             update_option('woo_fashnai_license_expires', '');
             update_option('woo_fashnai_license_credits', '');
             update_option('woo_fashnai_plan_product_id', '');

             $error_message = __('License validation failed.', 'woo-fashnai-preview');
             if ( isset($response_body['message']) ) {
                 $error_message = $response_body['message'];
             } elseif( isset($response_body['code']) ) { // Use code from WP_Error response
                 $error_message .= ' (' . $response_body['code'] . ')';
             }
            wp_send_json_error( array( 'message' => $error_message ) );
        }
    }

    public function verify_settings() {
        error_log('WooTryOnTool Plugin Settings:');
        error_log('Enabled: ' . (get_option('woo_fashnai_preview_enabled') ? 'Yes' : 'No'));
    }

    public function sanitize_array_of_strings( $input ) {
        if ( is_array( $input ) ) {
            return array_map( 'sanitize_text_field', $input );
        }
        return array();
    }

    /**
     * AJAX: Return consent records for admin table
     */
    public function ajax_get_consents() {
        check_ajax_referer( 'fashnai_get_consents', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-fashnai-preview' ) ), 403 );
        }

        $consents = get_option( 'woo_fashnai_consents', array() );

        // Return as numerically-indexed array for easier JS loop
        $out = array_values( $consents );
        wp_send_json_success( $out );
    }
}
