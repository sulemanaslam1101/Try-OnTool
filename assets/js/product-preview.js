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

    console.log('Script loaded and jQuery is available:', !!window.jQuery);
    console.info("Try-On Tool — GPL-2.0-only — NO WARRANTY. See COPYING file for license.");

    /* --------------------------------------------------------------
     *  I18N helper – pull wp.i18n.__ so we can wrap UI strings.
     *  Falls back to identity to stay compatible on very old sites.
     * ------------------------------------------------------------ */
    const { __ } = ( window.wp && wp.i18n ) ? wp.i18n : { __: ( s ) => s };

    $(document).ready(function() {
        // Declare variable in outer scope to avoid ReferenceError in later debug logs
        let imgElement = null;

        console.log('WooTryOnTool Preview: JavaScript initialized');
        console.log('WooTryOnTool Preview Buttons found:', $('.woo-fashnai-preview-button').length);
        
        // Handle preview button click
        $('.woo-fashnai-preview-button').on('click', function(e) {
            console.log('WooTryOnTool Preview: Button clicked');
            e.preventDefault();
            
            const productId = $(this).data('product-id');
            const productImage = $(this).data('product-image');
            
            console.log('Product Image URL:', productImage);
            
            $('#product_id').val(productId);
            $('#product_image_url').val(productImage);
            
            $('#woo-fashnai-preview-modal').show();
            
            /* Auto-fetch previously uploaded images and pre-select the first one */
            if (wooFashnaiPreview.user_id && wooFashnaiPreview.user_id !== 0) {
                $.ajax({
                    url: wooFashnaiPreview.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_user_uploaded_images',
                        user_id: wooFashnaiPreview.user_id,
                        nonce: wooFashnaiPreview.nonce
                    },
                    success: function(res){
                        if(res.success && res.data.images.length){
                            // Pre-select the newest/first image
                            $('#saved_user_image_url').val(res.data.images[0]);
                        }
                    }
                });
            }
            
            // Assuming the image element ID hasn't changed (preview-product-image was not FashnAI specific)
            console.log('Setting image src to:', productImage);
            imgElement = document.getElementById('preview-product-image'); 
            console.log('Image element found:', imgElement !== null);
            
            if (imgElement && productImage) {
                imgElement.src = getProxyImageUrl(productImage);
                imgElement.style.display = 'block';
                console.log('Image src set:', imgElement.src);
                
                imgElement.onload = function() {
                    console.log('Product image loaded successfully');
                };
                
                imgElement.onerror = function() {
                    console.error('Failed to load product image');
                    imgElement.style.display = 'none';
                    $('.product-image-preview').append('<p class="error">' + __( 'Failed to load product image', 'woo-fashnai-preview' ) + '</p>');
                };
            } else {
                console.error('Image element #preview-product-image or product image URL not found');
                if (imgElement) imgElement.style.display = 'none';
                 $('.product-image-preview .error').remove();
                 $('.product-image-preview').append('<p class="error">' + __( 'No product image available', 'woo-fashnai-preview' ) + '</p>');
            }
        });

        // Handle modal close
        $('.woo-fashnai-preview-modal .close').on('click', function() {
            $('#woo-fashnai-preview-modal').hide();
            $('.product-image-preview .error').remove(); 
            // $('#preview-product-image').removeClass('image-error').show();
        });

        // Close modal when clicking outside
        $(window).on('click', function(e) {
            if ($(e.target).is('.woo-fashnai-preview-modal')) {
                $('.woo-fashnai-preview-modal').hide();
            }
        });

        // Handle image click to select and update the input field
        $(document).on('click', '.img-item img', function() {
            // Get the URL of the clicked image
            const selectedImageUrl = $(this).attr('src');
            
            // Update the hidden input field with the selected image URL
            $('#saved_user_image_url').val(selectedImageUrl);
            // Clear file input and remove required since we are using saved image
            $('#user_image').val('').prop('required', false);
            // Display name of selected image for user feedback
            $('#selected-photo-name').text( __( 'Selected photo:', 'woo-fashnai-preview' ) + ' ' + selectedImageUrl.split('/').pop() );
            console.log('Selected Image URL:', selectedImageUrl);
            console.log('Input field value set to:', $('#saved_user_image_url').val());
            
            // Provide feedback to the user (translated)
            alert( __( 'Photo selected! Click Generate Preview.', 'woo-fashnai-preview' ) );
            
            // Optionally, close the modal after selection
            // $('#uploaded-images-modal').hide();
        });

        // Handle form submission
        $('#woo-fashnai-preview-form').on('submit', function(e) {
            e.preventDefault();
            
            // Hide previous results and errors
            $('.preview-result').hide();
            $('.preview-error').hide();
            
            // Show loading indicator
            if (!$('.loading-indicator').length) {
                $('.preview-error').after('<div class="loading-indicator"><p>' + __( 'Generating image… This may take up to 60 seconds.', 'woo-fashnai-preview' ) + '</p><div class="spinner"></div></div>');
            }
            $('.loading-indicator').show();
            
            // Prepare form data
            const formData = new FormData(this);
            formData.append('action', 'woo_fashnai_generate_preview');
            formData.append('nonce', wooFashnaiPreview.nonce);
            
            // Add the selected image URL to the form data
            const selectedImageUrl = $('#saved_user_image_url').val();
            if (selectedImageUrl) {
                // Ensure the server receives the parameter name it expects
                formData.append('saved_user_image_url', selectedImageUrl);
            }
            
            // Disable the submit button and change its text
            const submitButton = $(this).find('button[type="submit"]');
            submitButton.prop('disabled', true).text(wooFashnaiPreview.i18n.processing);
            
            // Send AJAX request
            $.ajax({
                url: wooFashnaiPreview.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-WP-Nonce': wooFashnaiPreview.nonce
                },
                success: function(response) {
                    console.log('API Response:', response);
                    if (response.success) {
                        if (response.data && response.data.image_url) {
                            const img = new Image();
                            img.onload = function() {
                                console.log('Generated image loaded successfully');
                                $('.preview-image').html(`<img src="${response.data.image_url}" alt="AI Preview">`);
                                $('.preview-result').show();
                                $('.preview-error').hide();
                                
                                $('.download-preview').attr('data-url', response.data.image_url);
                            };
                            img.onerror = function() {
                                console.error('Failed to load generated image from: ' + response.data.image_url);
                                $('.preview-error')
                                    .show()
                                    .find('.error-message')
                                    .text('Generated image could not be loaded. Please try again.');
                            };
                            img.src = response.data.image_url + '?t=' + new Date().getTime();
                            console.log('Attempting to load image from:', img.src);
                        } else {
                            console.error('Success response, but image_url missing:', response);
                            $('.preview-error')
                                .show()
                                .find('.error-message')
                                .text(response.data.message || 'AI preview generated, but the result could not be retrieved.');
                        }
                    } else {
                        console.error('API Error Response:', response);
                        let errorMessage = wooFashnaiPreview.i18n.error;
                        if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                        $('.preview-error')
                            .show()
                            .find('.error-message')
                            .text(errorMessage);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr, status, error});
                    let errorText = wooFashnaiPreview.i18n.error + ': ' + error;
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorText = xhr.responseJSON.data.message;
                    }
                    $('.preview-error')
                        .show()
                        .find('.error-message')
                        .text(errorText);
                },
                complete: function() {
                    submitButton.prop('disabled', false).text(wooFashnaiPreview.i18n.success);
                    $('.loading-indicator').hide();
                }
            });
        });

        // Handle download button click
        $(document).on('click', '.download-preview', function() {
            const imageUrl = $(this).data('url');
            if (imageUrl) {
                fetch(imageUrl)
                .then(response => response.blob())
                .then(blob => {
                     const link = document.createElement('a');
                     link.href = URL.createObjectURL(blob);
                     link.download = 'tryontool-preview.jpg';
                     document.body.appendChild(link);
                     link.click();
                     document.body.removeChild(link);
                     URL.revokeObjectURL(link.href);
                })
                .catch(err => {
                     console.error('Error downloading image:', err);
                     const link = document.createElement('a');
                     link.href = imageUrl;
                     link.download = 'tryontool-preview.jpg';
                     link.target = '_blank';
                     document.body.appendChild(link);
                     link.click();
                     document.body.removeChild(link);
                     alert('Try On Tool Preview is not available. Please check your settings.');
                });
            }
        });

        // Handle save to account button click
        $(document).on('click', '.save-preview', function() {
            const imageUrl = $('.download-preview').data('url');
            if (!imageUrl) return;

            const data = {
                action: 'woo_fashnai_save_preview', // Reverted action
                nonce: wooFashnaiPreview.nonce,
                image_url: imageUrl,
                product_id: $('#product_id').val()
            };

            const saveButton = $(this);
            saveButton.text( __( 'Saving…', 'woo-fashnai-preview' ) ).prop('disabled', true);
            
            $.post(wooFashnaiPreview.ajaxurl, data, function(response) {
                if (response.success) {
                    alert(response.data.message || 'Preview saved successfully!');
                } else {
                    alert(response.data.message || 'Failed to save preview.');
                }
            }).fail(function() {
                alert('Error communicating with server to save preview.');
            }).always(function() {
                 saveButton.text('Save to My Account').prop('disabled', false);
            });
        });

        // Add click handler to inspect button data
        $('.woo-fashnai-preview-button').on('click', function() {
            console.log('Button Data Product ID:', $(this).data('product-id'));
            console.log('Button Data Product Image:', $(this).data('product-image'));

             if(typeof window.checkModalImage === 'function'){
                 setTimeout(window.checkModalImage, 500);
             }
        });

        // Check credits and update button state
        if (wooFashnaiPreview.credits <= 0) {
            $('.woo-fashnai-preview-button').prop('disabled', true);
            $('#woo-fashnai-preview-modal .preview-error .error-message').text(wooFashnaiPreview.i18n.out_of_credits).show();
        } else {
            $('.woo-fashnai-preview-button').prop('disabled', false);
        }

        $('#woo-fashnai-preview-modal .preview-error').hide();

        console.log('WooTryOnTool Debug: jQuery is available');
        console.log('WooTryOnTool Debug: Document ready fired');
        console.log('WooTryOnTool Debug: Button elements found:', $('.woo-tryontool-preview-button').length);
        console.log('Debug Image Element:', imgElement);
        console.log('Image src:', imgElement ? imgElement.src : 'No image element');
        console.log('Image displayed:', imgElement ? window.getComputedStyle(imgElement).display : 'No image element');
        console.log('Image width:', imgElement ? imgElement.offsetWidth : 'No image element');
        console.log('Image complete:', imgElement ? imgElement.complete : 'No image element');
        console.log('Forced image reload with:', imgElement.src);
        console.log('Button Data Product ID:', $(this).data('product-id'));
        console.log('Button Data Product Image:', $(this).data('product-image'));
        console.log('WooTryOnTool Debug: Window load event fired');
        console.log('WooTryOnTool Debug: Button found after page load');
        console.log('WooTryOnTool Debug: Button NOT found after page load');
        console.log('WooTryOnTool Debug: Modal found after page load');
        console.log('WooTryOnTool Debug: Modal NOT found after page load');

        if (wooFashnaiPreview.user_id && wooFashnaiPreview.user_id !== 0) {
            $('#view-uploaded-images').show();
        }

        $('#user_image').on('change', function(){
            $('#saved_user_image_url').val('');
            // Reinstate the required attribute when a new file is chosen
            if (this.files && this.files.length) {
                $(this).prop('required', true);
                // Show chosen file name
                $('#selected-photo-name').text( __( 'Selected photo:', 'woo-fashnai-preview' ) + ' ' + this.files[0].name );
            } else {
                $('#selected-photo-name').text('');
            }
        });
    });

    $(document).on('click', '#view-uploaded-images', function(e){
        e.preventDefault();
        $.ajax({
            url: wooFashnaiPreview.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_user_uploaded_images',
                user_id: wooFashnaiPreview.user_id,
                nonce: wooFashnaiPreview.nonce
            },
            success: function(res){
                if (res.success && res.data.images.length){
                    let html = '<div class="uploaded-images-grid">';
                    res.data.images.forEach(u=>{ html += `<div class="img-item"><img src="${getProxyImageUrl(u)}"/></div>`; });
                    html += '</div>';
                    let modal = document.getElementById('uploaded-images-modal');
                    if(!modal){
                        $('body').append(`<div id="uploaded-images-modal" class="woo-fashnai-preview-modal" style="display:none;"><div class="modal-content"><span class="close">&times;</span><h3>My Photos</h3><div class="images-wrap"></div></div></div>`);
                        modal = document.getElementById('uploaded-images-modal');
                        $(modal).on('click','.close',()=>$(modal).hide());
                        $(window).on('click',evt=>{ if(evt.target===modal){ $(modal).hide(); }});
                    }
                    $(modal).find('.images-wrap').html(html);
                    $(modal).show();
                    $(modal).off('click', '.img-item img').on('click','.img-item img',function(){
                        const selectedImageUrl = $(this).attr('src');
                        $('#saved_user_image_url').val(selectedImageUrl);
                        // Clear file input and remove required since we are using saved image
                        $('#user_image').val('').prop('required', false);
                        // Display name of selected image for user feedback
                        $('#selected-photo-name').text( __( 'Selected photo:', 'woo-fashnai-preview' ) + ' ' + selectedImageUrl.split('/').pop() );
                        $(modal).hide();
                        alert( __( 'Photo selected! Click Generate Preview.', 'woo-fashnai-preview' ) );
                    });
                } else {
                    alert( __( 'No saved images.', 'woo-fashnai-preview' ) );
                }
            },
            error: () => alert( __( 'Error fetching images', 'woo-fashnai-preview' ) )
        });
    });

    function getWasabiKeyFromUrl(url) {
        // Example: https://s3.eu-west-1.wasabisys.com/tryontool-uploads/3/1748501547_phplIusaY.jpg
        // Returns: 3/1748501547_phplIusaY.jpg
        var match = url.match(/wasabisys\.com\/[^/]+\/(.+)$/);
        return match ? match[1] : '';
    }

    // Helper: get proxy image URL
    function getProxyImageUrl(wasabiUrl) {
        var key = getWasabiKeyFromUrl(wasabiUrl);
        return '/wp-json/woo-tryontool/v1/wasabi-image?key=' + encodeURIComponent(key);
    }

})(jQuery);
