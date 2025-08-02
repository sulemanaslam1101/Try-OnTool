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
 * Handle the product page button display and functionality for TryOnTool
 */
class WooFashnaiPreview_Product_Button {
    /**
     * Initialize the class
     */
    public function __construct() {
        // Add debug logging
        error_log('WooTryOnTool Plugin: Product Button class constructed');
        
        // Try different hooks to ensure button display
        add_action('woocommerce_after_add_to_cart_button', array($this, 'add_preview_button'), 10);
        
        // Add modal template to footer
        add_action('wp_footer', array($this, 'add_modal_template'));
        
        // Add debugging JavaScript to header
        add_action('wp_head', array($this, 'add_debug_script'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Add AJAX handlers
        add_action('wp_ajax_woo_fashnai_generate_preview', array($this, 'handle_preview_generation'));
        add_action('wp_ajax_nopriv_woo_fashnai_generate_preview', array($this, 'handle_preview_generation'));
    }

    /**
     * Add the preview button to product page
     */
    public function add_preview_button() {
        // Add debug logging
        error_log('WooTryOnTool Plugin: Attempting to add preview button');
        
        if (!is_product()) {
            error_log('WooTryOnTool Plugin: Not on product page');
            return;
        }

        if ( ! get_option( 'woo_fashnai_preview_enabled' ) ) {
            error_log('WooTryOnTool Plugin: Plugin not enabled');
            return;
        }

        if ( empty( get_option( 'woo_fashnai_license_key' ) ) ) {
            // error_log('WooTryOnTool Plugin: License key not configured.');
            // return;
        }

        global $product;
        if (!$product) {
            error_log('WooTryOnTool Plugin: No product found');
            return;
        }

        $product_image_id = $product->get_image_id();
        error_log('WooTryOnTool Plugin: Product image ID: ' . var_export($product_image_id, true));
        
        $product_image_url = '';
        
        if ($product_image_id) {
            $product_image_url = wp_get_attachment_url($product_image_id);
        } else {
            $gallery_image_ids = $product->get_gallery_image_ids();
            if (!empty($gallery_image_ids)) {
                $product_image_url = wp_get_attachment_url($gallery_image_ids[0]);
            }
        }
        
        if (empty($product_image_url)) {
            $product_image_url = wc_placeholder_img_src('woocommerce_single');
            error_log('WooTryOnTool Plugin: Using placeholder image');
        }

        error_log('WooTryOnTool Plugin: Product image URL: ' . $product_image_url);
        
        if (empty($product_image_url)) {
            error_log('WooTryOnTool Plugin: No product image found, even after fallbacks');
            return;
        }

        if ( ! $this->current_user_can_use_feature() ) {
            error_log('WooTryOnTool Plugin: Current user not permitted to use Try-On feature');
            return;
        }

        error_log('WooTryOnTool Plugin: Outputting button');
        ?>
        <script>console.log('Product Image URL in HTML:', <?php echo json_encode($product_image_url); ?>);</script>
        
        <button type="button" 
                class="button alt woo-fashnai-preview-button" 
                data-product-id="<?php echo esc_attr($product->get_id()); ?>"
                data-product-image="<?php echo esc_url($product_image_url); ?>"
                style="margin-left: 10px;">
            <?php _e('Try-On Tool Preview', 'woo-fashnai-preview'); ?>
        </button>
        <?php
    }

    private function current_user_can_use_feature() {
        $logged_in_only   = (bool) get_option( 'woo_fashnai_logged_in_only' );
        $allowed_roles    = (array) get_option( 'woo_fashnai_allowed_roles', array() );
        $allowed_user_ids = array_filter(array_map('absint', explode(',', (string) get_option('woo_fashnai_allowed_user_ids', ''))));
        $required_tag     = trim( (string) get_option( 'woo_fashnai_required_user_tag', '' ) );

        if ( ! is_user_logged_in() ) {
            if ( $logged_in_only ) {
                return false;
            }
            return empty( $allowed_roles ) && empty( $allowed_user_ids ) && $required_tag === '';
        }

        $user_id  = get_current_user_id();
        $user_obj = wp_get_current_user();

        if ( ! empty( $allowed_user_ids ) && ! in_array( $user_id, $allowed_user_ids, true ) ) {
            return false;
        }

        if ( ! empty( $allowed_roles ) ) {
            $user_roles = (array) $user_obj->roles;
            $matched    = array_intersect( $user_roles, $allowed_roles );
            if ( empty( $matched ) ) {
                return false;
            }
        }

        if ( $required_tag !== '' ) {
            $user_tag = get_user_meta( $user_id, 'woo_fashnai_user_tag', true );
            if ( $user_tag !== $required_tag ) {
                return false;
            }
        }

        return true;
    }

    public function add_modal_template() {
        if (!is_product()) {
            return;
        }

        if ( ! $this->current_user_can_use_feature() ) {
            return;
        }

        if ( ! get_option( 'woo_fashnai_preview_enabled' ) ) {
            return;
        }

        global $product;

        /* ------------------------------------------------------------------
         *  USER CONSENT HANDLING
         * ------------------------------------------------------------------
         *  We only ask for explicit consent once.  After the user has ticked
         *  the checkbox we store a time-stamp in user_meta so the checkbox
         *  never shows again.  (Guest users fall back to the front-end
         *  required attribute – no server-side record is stored.)
         * ------------------------------------------------------------------ */

        $require_consent = true;
        if ( is_user_logged_in() ) {
            $require_consent = ! (bool) get_user_meta( get_current_user_id(), 'woo_fashnai_user_consent', true );
        }
        $product_image_url = '';
        if ($product) {
            $product_image_id = $product->get_image_id();
            if ($product_image_id) {
                $product_image_url = wp_get_attachment_url($product_image_id);
            } else {
                $gallery_image_ids = $product->get_gallery_image_ids();
                if (!empty($gallery_image_ids)) {
                    $product_image_url = wp_get_attachment_url($gallery_image_ids[0]);
                }
            }
            
            if (empty($product_image_url)) {
                $product_image_url = wc_placeholder_img_src('woocommerce_single');
            }
        }

        ?>
        <script>console.log('Modal template rendering started');</script>
        <div id="woo-fashnai-preview-modal" class="woo-fashnai-preview-modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2><?php _e('Try On with AI Preview', 'woo-fashnai-preview'); ?></h2>

                <!-- Product image preview removed as per user request -->
                
                <?php if ( is_user_logged_in() ) : ?>
                <button id="view-uploaded-images" type="button" class="button" style="margin-bottom:10px;">
                    <?php _e('My Uploaded Images', 'woo-fashnai-preview'); ?>
                </button>
                <?php endif; ?>
                
                <form id="woo-fashnai-preview-form" enctype="multipart/form-data">
                    <div class="form-field">
                        <label for="user_image">
                            <?php _e('Upload Your Photo:', 'woo-fashnai-preview'); ?>
                        </label>
                        <input type="file" 
                               id="user_image" 
                               name="user_image" 
                               accept="image/*" 
                               required>
                        <p class="description">
                            <?php _e('Use clear, sharp, front-facing images (no blur, text, or side views).<br> Fix Issues: Refresh, re-upload, or click "Try Again."', 'woo-fashnai-preview'); ?>
                        </p>
                        <p id="selected-photo-name" class="selected-photo-name" style="margin-top:5px; font-style:italic; color:#555;"></p>

                        <?php if ( $require_consent ) : ?>
                        <div class="form-field" style="margin-top:15px;">
                            <label>
                                <input type="checkbox" id="user_consent" name="user_consent" required>
                                <?php _e('I consent to the processing of my uploaded images for generating previews.', 'woo-fashnai-preview'); ?>
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php
                    $require_extra_consents = get_option('woo_fashnai_require_extra_consents');
                    $terms_consent = is_user_logged_in() ? get_user_meta(get_current_user_id(), 'woo_fashnai_terms_consent', true) : false;
                    $refund_consent = is_user_logged_in() ? get_user_meta(get_current_user_id(), 'woo_fashnai_refund_consent', true) : false;
                    ?>
                    <?php if ($require_extra_consents && is_user_logged_in() && (!$terms_consent || !$refund_consent)) : ?>
                        <div class="form-field" style="margin-top:15px;">
                            <?php if (!$terms_consent): ?>
                            <label>
                                <input type="checkbox" id="terms_consent" name="terms_consent" required>
                                <?php _e('I agree to the Terms of Use and Privacy Policy', 'woo-fashnai-preview'); ?>
                            </label>
                            <?php endif; ?>
                            <?php if (!$refund_consent): ?>
                            <label>
                                <input type="checkbox" id="refund_consent" name="refund_consent" required>
                                <?php _e('I understand that previews may be inaccurate, and agree to abide by the Refund Policy.', 'woo-fashnai-preview'); ?>
                            </label>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" id="product_image_url" name="product_image_url" value="<?php echo esc_attr($product_image_url); ?>">
                    <input type="hidden" id="product_id" name="product_id" value="<?php echo $product ? esc_attr($product->get_id()) : ''; ?>">
                    <input type="hidden" id="saved_user_image_url" name="saved_user_image_url" value="">
                    
                    <div class="form-submit">
                        <button type="submit" class="button alt">
                            <?php _e('Generate Preview', 'woo-fashnai-preview'); ?>
                        </button>
                    </div>
                </form>

                <div class="preview-result" style="display: none;">
                    <h3><?php _e('Your AI Preview', 'woo-fashnai-preview'); ?></h3>
                    <div class="preview-image"></div>
                    <div class="preview-actions">
                        <button class="button download-preview">
                            <?php _e('Download Preview', 'woo-fashnai-preview'); ?>
                        </button>
                    </div>
                </div>

                <div class="preview-error" style="display: none;">
                    <p class="error-message"></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function enqueue_assets() {
        if (!is_product()) {
            return;
        }

        if ( ! get_option( 'woo_fashnai_preview_enabled' ) ) {
            return;
        }

        wp_enqueue_style(
            'woo-fashnai-preview-product',
            WOO_FASHNAI_PREVIEW_PLUGIN_URL . 'assets/css/product-preview.css',
            array(),
            WOO_FASHNAI_PREVIEW_VERSION
        );

        wp_enqueue_script(
            'woo-fashnai-preview-product',
            WOO_FASHNAI_PREVIEW_PLUGIN_URL . 'assets/js/product-preview.js',
            array( 'jquery', 'wp-i18n' ),
            WOO_FASHNAI_PREVIEW_VERSION,
            true
        );

        wp_localize_script(
            'woo-fashnai-preview-product',
            'wooFashnaiPreview',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('woo_fashnai_preview_nonce'),
                'credits' => get_user_credits(),
                'user_id' => get_current_user_id(),
                'i18n' => array(
                    'processing' => __('Generating preview...', 'woo-fashnai-preview'),
                    'error' => __('Error generating preview', 'woo-fashnai-preview'),
                    'success' => __('Generate Preview', 'woo-fashnai-preview'),
                    'out_of_credits' => __('You are out of credits. Please purchase more to continue.', 'woo-fashnai-preview')
                )
            )
        );
    }

    public function handle_preview_generation() {
        error_log('WooTryOnTool Plugin: Preview generation request received');
        
        error_log('WooTryOnTool Plugin: POST data: ' . print_r($_POST, true));
        error_log('WooTryOnTool Plugin: FILES data: ' . print_r($_FILES, true));
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'woo_fashnai_preview_nonce')) {
            error_log('WooTryOnTool Plugin: Nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed', 'woo-tryontool-preview')));
        }

        /* ─────────────────────────────────────────────────────────
         *  CONSENT ENFORCEMENT
         * ---------------------------------------------------------
         *  We require each logged-in user to provide explicit
         *  consent before the first preview generation.  The time-
         *  stamp is stored in user_meta and updated on subsequent
         *  generations so we can display the "last consent" date in
         *  the admin back-end if needed.
         * ──────────────────────────────────────────────────────── */

        if ( is_user_logged_in() ) {
            $uid          = get_current_user_id();
            $existing_ts  = get_user_meta( $uid, 'woo_fashnai_user_consent', true );

            if ( ! $existing_ts ) {
                // Ensure checkbox ticked for very first preview.
                if ( empty( $_POST['user_consent'] ) ) {
                    wp_send_json_error( array( 'message' => __( 'Please provide consent before generating a preview.', 'woo-fashnai-preview' ) ) );
                }

                // Record consent *once* – do NOT overwrite later.
                $now_mysql = current_time( 'mysql' );
                update_user_meta( $uid, 'woo_fashnai_user_consent', $now_mysql );

                // Site-wide consent registry
                $consents   = get_option( 'woo_fashnai_consents', array() );
                $user_obj   = wp_get_current_user();
                $consents[ $uid ] = array(
                    'user_id'          => $uid,
                    'email'            => $user_obj ? $user_obj->user_email : '',
                    'consent_timestamp'=> $now_mysql,
                    // last_login added via the wp_login hook
                );
                update_option( 'woo_fashnai_consents', $consents, false );
            }
        }

        $require_extra_consents = get_option('woo_fashnai_require_extra_consents');
        if ($require_extra_consents && is_user_logged_in()) {
            $uid = get_current_user_id();
            $terms_consent = get_user_meta($uid, 'woo_fashnai_terms_consent', true);
            $refund_consent = get_user_meta($uid, 'woo_fashnai_refund_consent', true);

            if (!$terms_consent && empty($_POST['terms_consent'])) {
                wp_send_json_error(array('message' => __('You must agree to the Terms of Use and Privacy Policy.', 'woo-fashnai-preview')));
            }
            if (!$refund_consent && empty($_POST['refund_consent'])) {
                wp_send_json_error(array('message' => __('You must agree to the Refund Policy.', 'woo-fashnai-preview')));
            }
            if (!$terms_consent && !empty($_POST['terms_consent'])) {
                update_user_meta($uid, 'woo_fashnai_terms_consent', current_time('mysql'));
            }
            if (!$refund_consent && !empty($_POST['refund_consent'])) {
                update_user_meta($uid, 'woo_fashnai_refund_consent', current_time('mysql'));
            }
        }

        // Determine source of user image: fresh upload or previously saved URL
        $using_saved_image = false;

        if (!isset($_FILES['user_image']) || $_FILES['user_image']['error'] !== UPLOAD_ERR_OK) {
            // No fresh upload – try saved image URL
            if (!empty($_POST['saved_user_image_url'])) {
                $remote_url = esc_url_raw(home_url($_POST['saved_user_image_url']));
                error_log('Attempting to download image from full URL: ' . $remote_url);
                
                if (!function_exists('download_url')) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                }
                
                $tmp_file = download_url($remote_url, 60); // Increase timeout if needed

                if (is_wp_error($tmp_file)) {
                    error_log('Error downloading image: ' . $tmp_file->get_error_message());
                    wp_send_json_error(array('message' => __('Could not retrieve saved image. Please upload again.', 'woo-tryontool-preview')));
                }

                // Craft a pseudo $_FILES entry so later code works the same
                $_FILES['user_image'] = array(
                    'name' => wp_basename($remote_url),
                    'full_path' => wp_basename($remote_url),
                    'type' => 'image/jpeg',
                    'tmp_name' => $tmp_file,
                    'error' => 0,
                    'size' => filesize($tmp_file),
                );

                $using_saved_image = true;
            } else {
                wp_send_json_error(array('message' => __('Please upload an image', 'woo-tryontool-preview')));
            }
        }

        if (empty($_POST['product_image_url'])) {
            error_log('WooTryOnTool Plugin: Product image URL is missing');
            wp_send_json_error(array('message' => __('Product image URL is missing', 'woo-tryontool-preview')));
        }

        if ( ! $this->current_user_can_use_feature() ) {
            error_log('WooTryOnTool Plugin: User not permitted – access control');
            wp_send_json_error(array( 'message' => __( 'You are not allowed to use this feature.', 'woo-tryontool-preview' ) ) );
        }

        $daily_limit = absint(get_option('woo_fashnai_daily_credits', 0));
        if ($daily_limit > 0 && is_user_logged_in()) {
            $user_id       = get_current_user_id();
            $today         = date('Y-m-d');
            $meta_key_date = 'woo_fashnai_daily_date';
            $meta_key_used = 'woo_fashnai_daily_used';

            $stored_date = get_user_meta($user_id, $meta_key_date, true);
            $used        = intval(get_user_meta($user_id, $meta_key_used, true));

            if ($stored_date !== $today) {
                update_user_meta($user_id, $meta_key_date, $today);
                $used = 0;
                update_user_meta($user_id, $meta_key_used, 0);
            }

            if ($used >= $daily_limit) {
                error_log('WooTryOnTool Plugin: Daily limit reached for user ' . $user_id);
                wp_send_json_error(array('message' => __('Daily quota exceeded, please try again tomorrow.', 'woo-tryontool-preview')));
            }
        }

        $uploaded_file_path = $_FILES['user_image']['tmp_name'];
        $image_type = exif_imagetype($uploaded_file_path);

        if ($image_type !== IMAGETYPE_JPEG && $image_type !== IMAGETYPE_PNG) {
            $uploaded_file_path = $this->convert_to_jpeg($uploaded_file_path);
        }

        // Save permanent copy for logged-in users (only when fresh upload)
        if (is_user_logged_in() && !$using_saved_image) {
            $user_id = get_current_user_id();
            $url = WooFashnai_Wasabi::upload( $user_id, $uploaded_file_path );
            // NO user-meta needed any more
        }

        try {
            $api_handler = new WooFashnaiPreview_API_Handler();
            
            $product_category = '';
            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            if ($product_id > 0) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $category_ids = $product->get_category_ids();
                    if (!empty($category_ids)) {
                        $primary_cat_id = $category_ids[0];
                        $term = get_term($primary_cat_id, 'product_cat');
                        if ($term && !is_wp_error($term)) {
                            $product_category = strtolower($term->name);
                        }
                    }
                    if (empty($product_category)) {
                        $product_category = $product->get_type(); 
                    }
                }
            }
            error_log('WooTryOnTool Plugin: Determined product category/type: ' . $product_category);
            
            // Map WooCommerce category to TryOnTool allowed values
            $mapped_category = 'auto'; 
            if ($product_category) {
                $cat_lc = strtolower($product_category);
                $tops_keywords = array('top', 'shirt', 't-shirt', 'blouse', 'sweater', 'hoodie', 'jacket', 'coat');
                $bottoms_keywords = array('pant', 'trouser', 'jean', 'skirt', 'short', 'bottom');
                $onepiece_keywords = array('dress', 'jumpsuit', 'overall', 'onesie', 'one-piece');

                foreach ($tops_keywords as $kw) {
                    if (strpos($cat_lc, $kw) !== false) {
                        $mapped_category = 'tops';
                        break;
                    }
                }
                if ($mapped_category === 'auto') {
                    foreach ($bottoms_keywords as $kw) {
                        if (strpos($cat_lc, $kw) !== false) {
                            $mapped_category = 'bottoms';
                            break;
                        }
                    }
                }
                if ($mapped_category === 'auto') {
                    foreach ($onepiece_keywords as $kw) {
                        if (strpos($cat_lc, $kw) !== false) {
                            $mapped_category = 'one-pieces';
                            break;
                        }
                    }
                }
            }
            error_log('WooTryOnTool Plugin: Mapped category to TryOnTool value: ' . $mapped_category);
            
            error_log('WooTryOnTool Plugin: Calling API with product image: ' . $_POST['product_image_url']);
            
            $response = $api_handler->generate_preview(
                $uploaded_file_path,
                $_POST['product_image_url'],
                $mapped_category,
                array()
            );

            error_log('WooTryOnTool Plugin: API response: ' . print_r($response, true));

            if (is_wp_error($response)) {
                error_log('WooTryOnTool Plugin: API error: ' . $response->get_error_message());
                
                $upload_dir = wp_upload_dir();
                $debug_dir = $upload_dir['basedir'] . '/woo-fashnai-debug';
                if (!file_exists($debug_dir)) {
                    wp_mkdir_p($debug_dir);
                }
                
                $error_data = array(
                    'message' => $response->get_error_message(),
                    'code' => $response->get_error_code(),
                    'data' => $response->get_error_data()
                );
                
                file_put_contents(
                    $debug_dir . '/error-' . time() . '.json',
                    json_encode($error_data, JSON_PRETTY_PRINT)
                );
                
                $original_error_message = $response->get_error_message();
                if (strpos(strtolower($original_error_message), 'segmentation failed') !== false || strpos(strtolower($original_error_message), 'try-on failed') !== false) {
                    $user_friendly_message = __('Could not generate the try-on preview. The user or product image might not be suitable. Try a different photo.', 'woo-tryontool-preview');
                } else if (strpos(strtolower($original_error_message), 'timeout') !== false) {
                     $user_friendly_message = __('The request timed out. Please try again later.', 'woo-tryontool-preview');
                } else { 
                    $user_friendly_message = $original_error_message; 
                }
                
                wp_send_json_error(array(
                    'message' => $user_friendly_message,
                    'debug_code' => $response->get_error_code()
                ));
            }
            
            if (isset($response['image_url']) && !empty($response['image_url'])) {
                 error_log('WooTryOnTool Plugin: Found image URL in response: ' . $response['image_url']);

                $daily_limit = absint(get_option('woo_fashnai_daily_credits', 0));
                if ($daily_limit > 0 && is_user_logged_in()) {
                    $user_id       = get_current_user_id();
                    $meta_key_used = 'woo_fashnai_daily_used';
                    $used          = intval(get_user_meta($user_id, $meta_key_used, true));
                    update_user_meta($user_id, $meta_key_used, $used + 1);
                }

                wp_send_json_success(array(
                    'image_url' => $response['image_url'],
                    'message' => __('Preview generated successfully', 'woo-tryontool-preview')
                ));
            } else {
                error_log('WooTryOnTool Plugin: Success response received, but image_url missing or empty. Response: ' . print_r($response, true));
                wp_send_json_error(array(
                     'message' => __('AI preview generated, but the result could not be retrieved. Please try again.', 'woo-tryontool-preview')
                ));
            }

        } catch (Exception $e) {
            error_log('WooTryOnTool Plugin: Exception: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An unexpected error occurred: ', 'woo-tryontool-preview') . $e->getMessage()
            ));
        }
    }

    private function convert_to_jpeg($file_path) {
        $image_type = exif_imagetype($file_path);
        $src_img = false;

        switch ($image_type) {
            case IMAGETYPE_GIF:
                $src_img = imagecreatefromgif($file_path);
                break;
            case IMAGETYPE_PNG:
                $src_img = imagecreatefrompng($file_path);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $src_img = imagecreatefromwebp($file_path);
                }
                break;
            case false:
                // Types like HEIC/HEIF may return false. Try Imagick if available.
                if (class_exists('Imagick')) {
                    try {
                        $imagick = new Imagick($file_path);
                        $imagick->setImageFormat('jpg');
                        $src_img = imagecreatefromstring($imagick->getImageBlob());
                        $imagick->clear();
                        $imagick->destroy();
                    } catch (Exception $e) {
                        $src_img = false;
                    }
                }
                break;
            default:
                $src_img = imagecreatefromstring(file_get_contents($file_path));
                break;
        }

        if ($src_img) {
            $upload_dir = wp_upload_dir();
            $jpeg_file = $upload_dir['basedir'] . '/woo-fashnai-temp/' . uniqid('converted_') . '.jpg';

            // Ensure temp dir exists
            if (!file_exists(dirname($jpeg_file))) {
                wp_mkdir_p(dirname($jpeg_file));
            }

            // Convert to JPEG
            imagejpeg($src_img, $jpeg_file, 90);
            imagedestroy($src_img);

            return $jpeg_file;
        }

        return $file_path; // Return original if conversion fails
    }

    public function add_debug_script() {
        if (!is_product()) {
            return;
        }
        
        ?>
        <script type="text/javascript">
            console.log('WooTryOnTool Debug: Script loaded in head');
            
            function checkJQuery() {
                if (window.jQuery) {
                    console.log('WooTryOnTool Debug: jQuery is available');
                    jQuery(document).ready(function($) {
                        console.log('WooTryOnTool Debug: Document ready fired');
                        console.log('WooTryOnTool Debug: Button elements found:', $('.woo-tryontool-preview-button').length);
                        
                        window.checkModalImage = function() {
                            var imgElement = document.getElementById('preview-product-image');
                            console.log('Debug Image Element:', imgElement);
                            console.log('Image src:', imgElement ? imgElement.src : 'No image element');
                            console.log('Image displayed:', imgElement ? window.getComputedStyle(imgElement).display : 'No image element');
                            console.log('Image width:', imgElement ? imgElement.offsetWidth : 'No image element');
                            console.log('Image complete:', imgElement ? imgElement.complete : 'No image element');
                            
                            if (imgElement && !imgElement.complete) {
                                var currentSrc = imgElement.src;
                                if (currentSrc && currentSrc.indexOf('?') === -1) {
                                    imgElement.src = currentSrc + '?t=' + new Date().getTime();
                                    console.log('Forced image reload with:', imgElement.src);
                                }
                            }
                        };
                        
                        $('.woo-tryontool-preview-button').on('click', function() {
                            console.log('Button Data Product ID:', $(this).data('product-id'));
                            console.log('Button Data Product Image:', $(this).data('product-image'));
                            
                            setTimeout(window.checkModalImage, 500);
                        });

                        if (wooFashnaiPreview.credits <= 0) {
                            $('.woo-tryontool-preview-button').prop('disabled', true);
                            $('#woo-tryontool-preview-modal .preview-error .error-message').text(wooFashnaiPreview.i18n.out_of_credits).show();
                        } else {
                            $('.woo-tryontool-preview-button').prop('disabled', false);
                        }

                        $('#woo-tryontool-preview-modal .preview-error .error-message').hide();
                    });
                } else {
                    setTimeout(checkJQuery, 100);
                }
            }
            checkJQuery();
            
            window.addEventListener('load', function() {
                console.log('WooTryOnTool Debug: Window load event fired');
                
                if (document.querySelector('.woo-tryontool-preview-button')) {
                    console.log('WooTryOnTool Debug: Button found after page load');
                } else {
                    console.log('WooTryOnTool Debug: Button NOT found after page load');
                }
                
                if (document.getElementById('woo-tryontool-preview-modal')) {
                    console.log('WooTryOnTool Debug: Modal found after page load');
                } else {
                    console.log('WooTryOnTool Debug: Modal NOT found after page load');
                }
            });
        </script>
        <?php
    }

    // Add a function to store image URL and timestamp in a transient
    private function store_image_for_deletion($user_id, $image_url) {
        // Register a transient for this specific image (allow multiple per user)
        $transient_key = 'woo_fashnai_image_deletion_' . md5($image_url);
        $data = array('user_id' => $user_id, 'image_url' => $image_url, 'timestamp' => time());
        set_transient($transient_key, $data, 0);
        error_log('WooTryOnTool: Transient set for image deletion -> ' . $transient_key);

        // Handle cases where the timestamp might be missing
        if ($data) {
            $ts = $data['timestamp'] ?? ($data['upload_time'] ?? false);
            if ($ts && (time() - $ts) > YEAR_IN_SECONDS) {
                // Delete the image
                self::delete_image($data['user_id'], $data['image_url'] ?? '');
                // Delete the transient
                delete_transient($transient_key);
            }
        }
    }

    // Make the check_and_delete_images method static
    public static function check_and_delete_images() {
        if ( ! defined( 'WOO_FASHNAI_INACTIVITY_WINDOW' ) ) {
            return; // safety guard – constant should be defined in main plugin file
        }

        global $wpdb;
        $prefix = '_transient_woo_fashnai_pending_delete_';
        $rows   = $wpdb->get_results( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix . '%' ) );

        foreach ( $rows as $row ) {
            $transient_key = str_replace( '_transient_', '', $row->option_name );
            $logout_time   = get_transient( $transient_key );

            // Extract the user-ID from the transient name
            $user_id = intval( str_replace( 'woo_fashnai_pending_delete_', '', $transient_key ) );
            if ( ! $user_id ) {
                continue;
            }

            // If the transient has vanished (false) *or* exceeded the inactivity window
            if ( ! $logout_time || ( time() - intval( $logout_time ) ) > WOO_FASHNAI_INACTIVITY_WINDOW ) {
                // Initialise Wasabi client so bucket is set
                WooFashnai_Wasabi::client();

                // Fetch & delete every object that belongs to this user
                $images = WooFashnai_Wasabi::list_user_images( $user_id );
                foreach ( $images as $url ) {
                    self::delete_image( $user_id, $url );
                }

                // Finally, forget the last-activity transient
                delete_transient( $transient_key );
            }
        }
    }

    // Make the delete_image method static
    private static function delete_image($user_id, $image_url) {
        if ( empty( $image_url ) ) {
            return;
        }

        // Ensure Wasabi client is initialised so that ::bucket() has a value
        WooFashnai_Wasabi::client();

        $bucket = WooFashnai_Wasabi::bucket();

        // Extract the S3 object key from the full URL.  We support both
        // …/bucket-name/key  and any custom Wasabi region endpoints.
        $pattern = '#https?://[^/]+/' . preg_quote( $bucket, '#' ) . '/#';
        $key     = preg_replace( $pattern, '', $image_url );

        // Fallback: if the regex failed, fall back to the legacy str_replace.
        if ( $key === $image_url ) {
            $key = str_replace( 'https://s3.eu-west-1.wasabisys.com/' . $bucket . '/', '', $image_url );
        }

        if ( $key && $key !== $image_url ) {
            WooFashnai_Wasabi::delete( $key );
            error_log( 'WooTryOnTool: deleted ' . $key . ' for user ' . $user_id );
        } else {
            error_log( 'WooTryOnTool: could not parse Wasabi key from URL ' . $image_url );
        }

        // No user-meta to update – list() pulls directly from Wasabi.
    }
}

function get_user_credits() {
    $credits = get_option('woo_fashnai_license_credits', 0);
    return $credits;
}

// Add helper functions for storing images in user meta
function woo_fashnai_get_user_uploaded_images($user_id) {
    return WooFashnai_Wasabi::list_user_images($user_id);
}

function woo_fashnai_save_uploaded_image_url($user_id, $url) {
    // No longer needed – images live in Wasabi only
}

add_action('wp_ajax_get_user_uploaded_images', function() {
    check_ajax_referer('woo_fashnai_preview_nonce', 'nonce');
    $uid = absint($_POST['user_id'] ?? 0);
    if ( ! $uid ) {
        wp_send_json_error();
    }
    $imgs = woo_fashnai_get_user_uploaded_images($uid);
    error_log('WooTryOnTool: Returning '.count($imgs).' saved images for user '.$uid);
    wp_send_json_success(array('images' => $imgs));
});

// Allow non-logged-in visitors to fetch their uploads (they'll just get an empty list).
add_action('wp_ajax_nopriv_get_user_uploaded_images', function() {
    check_ajax_referer('woo_fashnai_preview_nonce', 'nonce');
    wp_send_json_success(array('images' => array()));
});

// Add the action hook for deleting the image
add_action('woo_fashnai_delete_user_image', function($user_id, $image_url) {
    error_log('Attempting to delete image for user ' . $user_id . ' and image ' . $image_url);
    // Get the user's uploaded images
    $images = woo_fashnai_get_user_uploaded_images($user_id);
    
    // Remove the image URL from the user's meta data
    $images = array_filter($images, function($url) use ($image_url) {
        return $url !== $image_url;
    });
    // Reindex array to maintain sequential keys so JSON encoding gives JS array
    $images = array_values($images);
    WooFashnai_Wasabi::update_user_images($user_id, $images);
    
    // Delete the image file from the server
    $upload_dir = wp_upload_dir();
    $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
    if (file_exists($file_path)) {
        unlink($file_path);
        error_log('Deleted image file at ' . $file_path);
    } else {
        error_log('Image file not found at ' . $file_path);
    }
});

// Adjust the hook to call the static method
add_action('init', array('WooFashnaiPreview_Product_Button', 'check_and_delete_images'));
