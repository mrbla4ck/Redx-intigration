jQuery(document).ready(function($) {
    $(document).on('click', '.update-categories', function(e) {
        e.preventDefault();

        var button = $(this);
        var orderId = button.data('order-id');
        var trackingId = $('input[name="tracking_id_' + orderId + '"]').val();

        if (!trackingId) {
            alert('Please enter a tracking ID.');
            return;
        }

        button.text('Processing...').prop('disabled', true);

        $.ajax({
            url: redx_ajax_object.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'update_redx_categories',
                nonce: redx_ajax_object.nonce, // Use the nonce passed from PHP
                order_id: orderId,
                tracking_id: trackingId
            },
            success: function(response) {
                if (response.success) {
                    button.text('Categories Updated').prop('disabled', true);
                    alert('Success: Category updated successfully.');
                } else {
                    button.prop('disabled', false).text('Update Categories');
                    alert('Failed: ' + (response.data && response.data.message ? response.data.message : 'The operation could not be completed.'));
                }
            },
            error: function(xhr, status, error) {
                button.prop('disabled', false).text('Update Categories');
                alert('AJAX error: ' + error);
            }
        });
    });
});
