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
<div class="wrap">
       <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
       <form method="post" action="options.php">
           <?php
           settings_fields('woo_fashnai_preview_options');
           do_settings_sections('woo_fashnai_preview_options');
           $license_status = get_option('woo_fashnai_license_status', 'unknown');
           $license_expires = get_option('woo_fashnai_license_expires', '');
           $license_credits = get_option('woo_fashnai_license_credits', '');
           $plan_product_id = get_option('woo_fashnai_plan_product_id', '');
           $show_on_demand_initially = ($license_status === 'valid' && $license_credits !== '' && (int)$license_credits <= 0);
           ?>
           <table class="form-table">
               <tr>
                   <th scope="row">
                       <label for="woo_fashnai_preview_enabled">
                           <?php _e('Enable Try-On Tool Preview', 'woo-fashnai-preview'); ?>
                       </label>
                   </th>
                   <td>
                       <input type="checkbox" id="woo_fashnai_preview_enabled" 
                              name="woo_fashnai_preview_enabled" 
                              value="1" 
                              <?php checked(get_option('woo_fashnai_preview_enabled'), 1); ?>>
                       <p class="description"><?php _e('Master switch for the plugin functionality.', 'woo-fashnai-preview'); ?></p>
                   </td>
               </tr>
               <tr>
                   <th scope="row">
                       <label for="woo_fashnai_license_key">
                           <?php _e('License Key', 'woo-fashnai-preview'); ?>
                       </label>
                   </th>
                   <td>
                       <input type="text" id="woo_fashnai_license_key"
                              name="woo_fashnai_license_key"
                              class="regular-text"
                              value="<?php echo esc_attr(get_option('woo_fashnai_license_key')); ?>">
                       <button type="button" id="validate-license-key" class="button button-secondary" style="margin-left: 10px;">
                           <?php _e('Validate Key', 'woo-fashnai-preview'); ?>
                       </button>
                       <p class="description">
                           <?php _e('Enter the license key you received via email after purchase.', 'woo-fashnai-preview'); ?>
                       </p>
                       <div id="license-status" style="margin-top: 10px;">
                           <?php if ($license_status === 'valid'): ?>
                               <p style="color: green;"><strong><?php _e('Status:', 'woo-fashnai-preview'); ?></strong> <?php _e('Active', 'woo-fashnai-preview'); ?>
                                   <?php if($license_expires) printf(__(' (Expires: %s)'), esc_html($license_expires)); ?>
                                   <?php if($license_credits !== '') printf(__(' | Credits: %s'), esc_html($license_credits)); ?>
                               </p>
                           <?php elseif ($license_status === 'invalid'): ?>
                                <p style="color: red;"><strong><?php _e('Status:', 'woo-fashnai-preview'); ?></strong> <?php _e('Invalid or Expired', 'woo-fashnai-preview'); ?></p>
                            <?php else: ?>
                                <p><strong><?php _e('Status:', 'woo-fashnai-preview'); ?></strong> <?php _e('Unknown (Please validate)', 'woo-fashnai-preview'); ?></p>
                            <?php endif; ?>
                       </div>
                        <div id="license-validation-result" style="margin-top: 10px; padding: 10px; display: none;"></div>
                   </td>
               </tr>
               <tr>
                   <th scope="row">
                       <?php _e('Purchase Plans', 'woo-fashnai-preview'); ?>
                   </th>
                   <td>
                       <p style="margin-bottom: 10px;">
                           <?php _e('Need to purchase a plan?', 'woo-fashnai-preview'); ?>
                       </p>
                       <a href="https://tryontool.com/plans-2" target="_blank" class="button button-primary" style="text-decoration: none;">
                           <?php _e('Visit Try-On Tool Website', 'woo-fashnai-preview'); ?>
                       </a>
                       <p class="description">
                           <?php _e('Browse our plans and purchase additional credits for your Try-On Tool plugin.', 'woo-fashnai-preview'); ?>
                       </p>
                   </td>
               </tr>
               <tr id="on-demand-credits-row" style="<?php echo $show_on_demand_initially ? '' : 'display: none;'; ?>">
                    <th scope="row">
                        <?php _e('Buy Credits', 'woo-fashnai-preview'); ?>
                    </th>
                    <td>
                        <?php
                            // --- Build credit pack options (static pricing) ---
                            $credit_packs = array(
                                60  => array( 'id' => defined('FASHNAI_CREDIT_PACK_100_PRODUCT_ID')  ? FASHNAI_CREDIT_PACK_100_PRODUCT_ID  : 3515, 'price' => 5.99  ),
                                120  => array( 'id' => defined('FASHNAI_CREDIT_PACK_200_PRODUCT_ID')  ? FASHNAI_CREDIT_PACK_200_PRODUCT_ID  : 3516, 'price' => 11.99  ),
                                240  => array( 'id' => defined('FASHNAI_CREDIT_PACK_300_PRODUCT_ID')  ? FASHNAI_CREDIT_PACK_300_PRODUCT_ID  : 3517, 'price' => 23.99 ),
                                /*
                                400  => array( 'id' => defined('FASHNAI_CREDIT_PACK_400_PRODUCT_ID')  ? FASHNAI_CREDIT_PACK_400_PRODUCT_ID  : 3518, 'price' => 170 ),
                                500  => array( 'id' => defined('FASHNAI_CREDIT_PACK_500_PRODUCT_ID')  ? FASHNAI_CREDIT_PACK_500_PRODUCT_ID  : 3519, 'price' => 210 ),
                                600  => array( 'id' => defined('FASHNAI_CREDIT_PACK_600_PRODUCT_ID')  ? FASHNAI_CREDIT_PACK_600_PRODUCT_ID  : 3520, 'price' => 250 ),
                                700  => array( 'id' => defined('FASHNAI_CREDIT_PACK_700_PRODUCT_ID')  ? FASHNAI_CREDIT_PACK_700_PRODUCT_ID  : 3521, 'price' => 290 ),
                                800  => array( 'id' => defined('FASHNAI_CREDIT_PACK_800_PRODUCT_ID')  ? FASHNAI_CREDIT_PACK_800_PRODUCT_ID  : 3522, 'price' => 330 ),
                                900  => array( 'id' => defined('FASHNAI_CREDIT_PACK_900_PRODUCT_ID')  ? FASHNAI_CREDIT_PACK_900_PRODUCT_ID  : 3523, 'price' => 370 ),
                                1000 => array( 'id' => defined('FASHNAI_CREDIT_PACK_1000_PRODUCT_ID') ? FASHNAI_CREDIT_PACK_1000_PRODUCT_ID : 3524, 'price' => 410 ),
                                1100 => array( 'id' => defined('FASHNAI_CREDIT_PACK_1100_PRODUCT_ID') ? FASHNAI_CREDIT_PACK_1100_PRODUCT_ID : 3525, 'price' => 450 ),
                                1200 => array( 'id' => defined('FASHNAI_CREDIT_PACK_1200_PRODUCT_ID') ? FASHNAI_CREDIT_PACK_1200_PRODUCT_ID : 3526, 'price' => 490 ),
                                */
                            );
                        ?>

                        <div id="credit-pack-buttons" style="margin-bottom:10px; display:flex; gap:10px; flex-wrap:wrap;">
                            <button type="button" class="credit-pack-option button" data-credits="60">60</button>
                            <button type="button" class="credit-pack-option button" data-credits="120">120</button>
                            <button type="button" class="credit-pack-option button" data-credits="240">240</button>
                        </div>

                        <!--
                        <div id="custom-credit-selector" style="display:flex; align-items:center; gap:5px; margin-bottom:10px;">
                            <button type="button" id="custom-credit-minus" class="button">&minus;</button>
                            <input type="text" id="custom-credit-value" value="100" readonly style="width:80px; text-align:center;" />
                            <button type="button" id="custom-credit-plus" class="button">+</button>
                            <span class="description" style="margin-left:8px;"><?php _e('Custom amount (multiples of 100)', 'woo-fashnai-preview'); ?></span>
                        </div>
                        -->

                        <div id="credit-pack-selected-display" style="text-align:center; margin-bottom:10px;">
                            <span id="selected-credits"></span> credits — <span id="selected-price"></span>
                        </div>
                        <button type="button" id="buy-on-demand-credits" class="button button-primary">
                            <?php _e('Buy On-Demand Credits', 'woo-fashnai-preview'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Choose a credit bundle then click "Buy" to proceed to checkout.', 'woo-fashnai-preview'); ?>
                        </p>
                    </td>
               </tr>
               <tr>
                   <th scope="row">
                       <label for="woo_fashnai_daily_credits">
                           <?php _e('Daily Credits Per User (Visual Only)', 'woo-fashnai-preview'); ?>
                       </label>
                   </th>
                   <td>
                       <input type="number" id="woo_fashnai_daily_credits"
                              name="woo_fashnai_daily_credits"
                              class="small-text"
                              value="<?php echo esc_attr(get_option('woo_fashnai_daily_credits', 0)); ?>" min="0">
                       <p class="description">
                           <?php _e('Optional: Set a visual daily limit reminder for users. Actual credits are managed by the server.', 'woo-fashnai-preview'); ?>
                       </p>
                   </td>
               </tr>
               <tr>
                   <th scope="row">
                       <label for="woo_fashnai_logged_in_only">
                           <?php _e('Restrict to Logged-in Users', 'woo-fashnai-preview'); ?>
                       </label>
                   </th>
                   <td>
                       <input type="checkbox" id="woo_fashnai_logged_in_only"
                              name="woo_fashnai_logged_in_only" value="1"
                              <?php checked(get_option('woo_fashnai_logged_in_only'), 1); ?>>
                       <p class="description">
                           <?php _e('Enable this to show the Try-On button only to logged-in customers.', 'woo-fashnai-preview'); ?>
                       </p>
                   </td>
               </tr>
               <tr>
                   <th scope="row">
                       <label for="woo_fashnai_allowed_roles">
                           <?php _e('Allowed User Roles', 'woo-fashnai-preview'); ?>
                       </label>
                   </th>
                   <td>
                       <?php $all_roles = wp_roles()->roles; $selected_roles = (array) get_option('woo_fashnai_allowed_roles', array()); ?>
                       <select id="woo_fashnai_allowed_roles" name="woo_fashnai_allowed_roles[]" multiple size="4">
                           <?php foreach ($all_roles as $role_key => $role) : ?>
                               <option value="<?php echo esc_attr($role_key); ?>" <?php selected(in_array($role_key, $selected_roles), true); ?>>
                                   <?php echo esc_html($role['name']); ?>
                               </option>
                           <?php endforeach; ?>
                       </select>
                       <p class="description">
                           <?php _e('Leave empty to allow all roles (if logged-in restriction applies).', 'woo-fashnai-preview'); ?>
                       </p>
                   </td>
               </tr>
               <tr>
                   <th scope="row">
                       <label for="woo_fashnai_allowed_user_ids">
                           <?php _e('Specific Allowed User IDs', 'woo-fashnai-preview'); ?>
                       </label>
                   </th>
                   <td>
                       <textarea id="woo_fashnai_allowed_user_ids" name="woo_fashnai_allowed_user_ids" rows="3" cols="50" class="large-text code"><?php echo esc_textarea(get_option('woo_fashnai_allowed_user_ids', '')); ?></textarea>
                       <p class="description">
                           <?php _e('Comma-separated list of WordPress user IDs that can access the Try-On feature (overrides role setting). Leave empty to disable.', 'woo-fashnai-preview'); ?>
                       </p>
                   </td>
               </tr>
               <tr>
                   <th scope="row">
                       <label for="woo_fashnai_required_user_tag">
                           <?php _e('Required User Tag (Meta Key: woo_tryontool_user_tag)', 'woo-fashnai-preview'); ?>
                       </label>
                   </th>
                   <td>
                        <input type="text" id="woo_fashnai_required_user_tag"
                            name="woo_fashnai_required_user_tag"
                            class="regular-text"
                            value="<?php echo esc_attr(get_option('woo_fashnai_required_user_tag', '')); ?>">
                       <p class="description">
                           <?php _e('If set, only users with this exact value in their `woo_tryontool_user_tag` user meta field can use the feature.', 'woo-fashnai-preview'); ?>
                       </p>
                   </td>
               </tr>
               <tr>
                   <th scope="row">
                       <label for="woo_fashnai_require_extra_consents">
                           <?php _e('Require Terms/Refund Consent on First Use', 'woo-fashnai-preview'); ?>
                       </label>
                   </th>
                   <td>
                       <input type="checkbox" id="woo_fashnai_require_extra_consents"
                              name="woo_fashnai_require_extra_consents" value="1"
                              <?php checked(get_option('woo_fashnai_require_extra_consents'), 1); ?>>
                       <p class="description">
                           <?php _e('If enabled, users must agree to Terms and Refund Policy on first use.', 'woo-fashnai-preview'); ?>
                       </p>
                   </td>
               </tr>
               <!-- Records of Consent -->
               <tr>
                    <th scope="row"><?php _e('Records of Consent', 'woo-fashnai-preview'); ?></th>
                    <td>
                        <button type="button" id="view-consent-records" class="button">
                            <?php _e('View Records', 'woo-fashnai-preview'); ?>
                        </button>
                        <p class="description"><?php _e('View user consent records for image processing.', 'woo-fashnai-preview'); ?></p>
                    </td>
               </tr>
           </table>
           <?php submit_button(); ?>
       </form>

       <!-- Consent Records Modal -->
       <div id="consent-records-modal" style="display:none; position:fixed; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:999999;">
            <div class="modal-content" style="background:#fff; padding:20px; max-width:700px; margin:5% auto; position:relative; max-height:80%; overflow-y:auto;">
                <span class="close-consent-modal" style="position:absolute; top:10px; right:15px; font-size:24px; cursor:pointer;">&times;</span>
                <h2><?php _e('User Consent Records','woo-fashnai-preview'); ?></h2>
                <div class="modal-body"></div>
            </div>
       </div>

       <script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {

    /* ----------------------------------------------------------------
     *  CREDIT‑PACK UI
     * ---------------------------------------------------------------- */
    const creditPacks  = JSON.parse('<?php echo wp_json_encode($credit_packs); ?>');

    /* element refs */
    const quickButtons = document.querySelectorAll('.credit-pack-option');
    const plusBtn      = document.getElementById('custom-credit-plus');   // may be null
    const minusBtn     = document.getElementById('custom-credit-minus');  // may be null
    const customInput  = document.getElementById('custom-credit-value');  // may be null
    const creditsLbl   = document.getElementById('selected-credits');
    const priceLbl     = document.getElementById('selected-price');
    const buyBtn       = document.getElementById('buy-on-demand-credits');

    let selectedCredits = 60;     // default pack

    function updateDisplay () {
        if (!creditPacks[selectedCredits]) { return; }
        creditsLbl.textContent      = selectedCredits;
        priceLbl.textContent        = '£' + creditPacks[selectedCredits].price;
        buyBtn.dataset.productId    = creditPacks[selectedCredits].id;
        if (customInput) { customInput.value = selectedCredits; }

        /* highlight quick buttons */
        quickButtons.forEach(btn => {
            btn.classList.toggle('button-primary',
                parseInt(btn.dataset.credits, 10) === selectedCredits);
        });
    }

    /* quick‑pick buttons */
    quickButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            selectedCredits = parseInt(btn.dataset.credits, 10);
            updateDisplay();
        });
    });

    /* --------------------------------------------------------------
     *  PLUS / MINUS HANDLERS (disabled – kept only for future use)
     * -------------------------------------------------------------- */
    /*
    if (plusBtn && minusBtn && customInput) {
        plusBtn.addEventListener('click', () => {
            if (selectedCredits < 240) {      // highest pack button
                selectedCredits += 60;
                updateDisplay();
            }
        });

        minusBtn.addEventListener('click', () => {
            if (selectedCredits > 60) {       // lowest pack button
                selectedCredits -= 60;
                updateDisplay();
            }
        });
    }
    */

    /* initialise */
    updateDisplay();

    /* buy‑button click */
    buyBtn.addEventListener('click', e => {
        e.preventDefault();
        const pid = buyBtn.dataset.productId;
        if (!pid) {
            alert('<?php echo esc_js(__('Please select a credit pack first.', 'woo-fashnai-preview')); ?>');
            return;
        }
        window.location.href = 'http://tryontool.com/checkout/?add-to-cart=' + pid;
    });



    /* ----------------------------------------------------------------
     *  LICENSE VALIDATION  (unchanged)
     * ---------------------------------------------------------------- */
    const $ = jQuery;

    function validateLicense () {
        var licenseKey    = $('#woo_fashnai_license_key').val();
        var resultDiv     = $('#license-validation-result');
        var statusDiv     = $('#license-status');
        var validateBtn   = $('#validate-license-key');

        if (!licenseKey) { return; }

        validateBtn.prop('disabled', true)
                   .text('<?php _e('Validating…', 'woo-fashnai-preview'); ?>');
        resultDiv.removeClass('notice-success notice-error').hide().empty();
        statusDiv.html('<p><strong><?php _e('Status:', 'woo-fashnai-preview'); ?></strong> <?php _e('Checking…', 'woo-fashnai-preview'); ?></p>');
        $('#on-demand-credits-row').hide();

        $.post(ajaxurl, {
            action      : 'woo_fashnai_validate_license',
            nonce       : '<?php echo wp_create_nonce('fashnai_validate_license_nonce'); ?>',
            license_key : licenseKey
        }, function (response) {

            if (response.success) {
                resultDiv.addClass('notice notice-success')
                         .html('<p>' + response.data.message + '</p>').show();
                statusDiv.html(
                    '<p style="color:green;"><strong><?php _e('Status:', 'woo-fashnai-preview'); ?></strong> <?php _e('Active', 'woo-fashnai-preview'); ?>' +
                    (response.data.expires  ? ' (Expires: ' + response.data.expires  + ')' : '') +
                    (response.data.credits !== undefined ? ' | Credits: ' + response.data.credits : '') +
                    '</p>'
                );

                var buyRow = $('#on-demand-credits-row');
                if (parseInt(response.data.credits, 10) <= 0) { buyRow.show(); } else { buyRow.hide(); }

            } else {
                resultDiv.addClass('notice notice-error')
                         .html('<p>' + response.data.message + '</p>').show();
                statusDiv.html(
                    '<p style="color:red;"><strong><?php _e('Status:', 'woo-fashnai-preview'); ?></strong> <?php _e('Invalid or Expired', 'woo-fashnai-preview'); ?></p>'
                );
                $('#on-demand-credits-row').hide();
            }

        }).fail(function () {
            resultDiv.addClass('notice notice-error')
                     .html('<p><?php _e('AJAX error validating license.', 'woo-fashnai-preview'); ?></p>').show();
            statusDiv.html(
                '<p style="color:red;"><strong><?php _e('Status:', 'woo-fashnai-preview'); ?></strong> <?php _e('Validation Error', 'woo-fashnai-preview'); ?></p>'
            );
            $('#on-demand-credits-row').hide();

        }).always(function () {
            validateBtn.prop('disabled', false)
                       .text('<?php _e('Validate Key', 'woo-fashnai-preview'); ?>');
        });
    }

    validateLicense();
    $('#validate-license-key').on('click', e => { e.preventDefault(); validateLicense(); });

    /* ----------------------------------------------------------------
     *  CONSENT RECORDS MODAL  (unchanged)
     * ---------------------------------------------------------------- */
    var consentNonce = '<?php echo wp_create_nonce('fashnai_get_consents'); ?>';

    $('#view-consent-records').on('click', function () {
        var modal = $('#consent-records-modal');
        modal.find('.modal-body').html('<p><?php _e('Loading…', 'woo-fashnai-preview'); ?></p>');
        modal.show();

        $.post(ajaxurl, {
            action : 'woo_fashnai_get_consents',
            nonce  : consentNonce
        }, function (res) {
            if (res.success) {
                var html = '<table class="widefat fixed striped"><thead><tr><th>User&nbsp;ID</th><th>Email</th><th>Consent&nbsp;Given</th><th>Last&nbsp;Login</th></tr></thead><tbody>';
                if (res.data.length) {
                    res.data.forEach(function (r) {
                        var consent = r.consent_timestamp || r.timestamp || '';
                        html += '<tr><td>' + r.user_id + '</td><td>' + r.email + '</td><td>' + consent + '</td><td>' + (r.last_login || '') + '</td></tr>';
                    });
                } else {
                    html += '<tr><td colspan="4"><?php _e('No records found.', 'woo-fashnai-preview'); ?></td></tr>';
                }
                html += '</tbody></table>';
                modal.find('.modal-body').html(html);
            } else {
                modal.find('.modal-body').html('<p>' + (res.data && res.data.message ? res.data.message : 'Error') + '</p>');
            }
        }).fail(function () {
            modal.find('.modal-body').html('<p><?php _e('Ajax error.', 'woo-fashnai-preview'); ?></p>');
        });
    });

    $(document).on('click', '.close-consent-modal', function () { $('#consent-records-modal').hide(); });
    $(window).on('click', function (e) {
        if (e.target === document.getElementById('consent-records-modal')) {
            $('#consent-records-modal').hide();
        }
         });

 });

 </script>
   </div>

   <p style="margin-top:2em;font-size:smaller;">
       Try-On Tool is Free Software, licensed under the GNU GPL v2 — NO WARRANTY. 
       <a href="<?php echo plugin_dir_url( dirname( dirname( __FILE__ ) ) ); ?>COPYING.txt" target="_blank">View License</a>
   </p>