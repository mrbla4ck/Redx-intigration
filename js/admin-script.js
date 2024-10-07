jQuery(document).ready(function($) {
    $('.redx-send-order').click(function(e) {
        e.preventDefault();

        // Disable the button immediately after the first click
        $(this).prop('disabled', true).text('Processing...');

        var button = $(this);
        var orderId = button.data('order-id');
        var nonce = button.data('nonce');

        $.ajax({
            url: redx_ajax_object.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'send_redx_order',
                order_id: orderId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Success: ' + response.data.message + ' Tracking ID: ' + response.data.tracking_id);
                    // Update the button text or hide it after successful operation
                    button.text('Sent to RedX').prop('disabled', true);
                } else {
                    alert('Error: ' + response.data.message);
                    // Optionally re-enable the button or handle error-specific actions
                    button.prop('disabled', false).text('Try Again');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('AJAX error: ' + textStatus + ' - ' + errorThrown);
                // Re-enable the button in case of AJAX failure
                button.prop('disabled', false).text('Send to RedX');
            }
        });
    });
});



jQuery(document).ready(function($) {
    $.ajax({
        url: redx_params.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'fetch_redx_zones',
            security: redx_params.nonce,
        },
        success: function(response) {
            if (response.success && response.data.areas) {
                var options = '<option value="">Select Delivery Zone</option>';
                $.each(response.data.areas, function(index, area) {
                    options += '<option value="' + area.id + '">' + area.name + '</option>';
                });
                $('select[name="shipping_delivery_zone"]').html(options);
            }
        }
    });
});

