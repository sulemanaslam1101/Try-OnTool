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
(function($) {
    'use strict';

    /* --------------------------------------------------------------------
     *  I18N: pull the translation helper from wp.i18n.  If the handle is
     *  missing (unlikely on the front-end) fall back to identity to avoid
     *  fatal ReferenceErrors in older WP versions.
     * ------------------------------------------------------------------ */
    const { __ } = ( window.wp && wp.i18n ) ? wp.i18n : { __: ( s ) => s };

    $(document).ready(function() {
        // Handle test form submission
        $('#woo-fashnai-test-form').on('submit', function(e) {
            e.preventDefault();
            
            // Reset result and error containers
            $('#woo-fashnai-result').hide().find('.result-content').empty();
            $('#woo-fashnai-error').hide().find('.error-content').empty();
            
            // Create FormData object for file upload
            var formData = new FormData(this);
            formData.append('action', 'woo_fashnai_test_api');
            formData.append('nonce', wooFashnaiPreview.nonce);
            
            // Show loading state
            $(this).find('button[type="submit"]').text( __( 'Processing…', 'woo-fashnai-preview' ) ).prop('disabled', true);
            
            // Send AJAX request
            $.ajax({
                url: wooFashnaiPreview.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Display success message and response data
                        $('#woo-fashnai-result')
                            .show()
                            .find('.result-content')
                            .html('<pre>' + JSON.stringify(response.data, null, 2) + '</pre>');
                    } else {
                        // Display error message
                        $('#woo-fashnai-error')
                            .show()
                            .find('.error-content')
                            .html('<p>' + (response.data.message || __( 'An unknown error occurred.', 'woo-fashnai-preview' ) ) + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    // Display error message
                    let errorText = __( 'Ajax error:', 'woo-fashnai-preview' ) + ' ' + error;
                     if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorText = xhr.responseJSON.data.message;
                     }
                    $('#woo-fashnai-error')
                        .show()
                        .find('.error-content')
                        .html('<p>' + errorText + '</p>');
                },
                complete: function() {
                    // Reset button state
                    $('#woo-fashnai-test-form')
                        .find('button[type="submit"]')
                        .text( __( 'Generate Preview', 'woo-fashnai-preview' ) )
                        .prop('disabled', false);
                }
            });
        });
    });
})(jQuery); 