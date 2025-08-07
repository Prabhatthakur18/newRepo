$(document).on('click', '.wssc-add-btn', function(e) {
    e.preventDefault();
    
    var button = $(this);
    var productId = button.data('product-id');
    var originalText = button.html();
    
    // Disable button and show loading
    button.prop('disabled', true).html('Adding...');
    
    var ajaxData = {
        action: 'wssc_add_to_cart',
        product_id: productId,
        quantity: 1,
        nonce: wsscAjax.nonce
    };
    
    $.ajax({
        url: wsscAjax.url,
        type: 'POST',
        data: ajaxData,
        success: function(response) {
            if (response.success) {
                // Update cart count
                $('.cart-contents-count').text(response.data.cart_count);
                
                // Update quantity badge
                var card = button.closest('.wssc-product-card');
                var badge = card.find('.wssc-qty-badge');
                var currentQty = badge.length ? parseInt(badge.text()) : 0;
                var newQty = currentQty + 1;
                
                if (badge.length) {
                    badge.text(newQty);
                } else {
                    card.append('<span class="wssc-qty-badge">' + newQty + '</span>');
                }
                
                // Show success message
                showToast('✅ Product added to cart!', 'success');
                
                // Update cart fragments
                $(document.body).trigger('wc_fragment_refresh');
            } else {
                showToast('❌ Failed to add product', 'error');
            }
        },
        error: function() {
            showToast('❌ Error adding product', 'error');
        },
        complete: function() {
            // Re-enable button
            button.prop('disabled', false).html(originalText);
        }
    });
});

// Toast function
function showToast(message, type) {
    var toast = $('<div class="wssc-toast ' + type + '">' + message + '</div>');
    $('body').append(toast);
    
    setTimeout(function() {
        toast.fadeOut(400, function() {
            toast.remove();
        });
    }, 4000);
}