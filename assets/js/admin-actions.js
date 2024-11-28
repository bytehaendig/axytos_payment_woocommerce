(function ($) {
    $(document).ready(function () {
        $('.axytos-action-button').on('click', function () {
            const $button = $(this);
            const orderId = $button.data('order-id');
            const actionType = $button.data('action');
            const nonce = AxytosActions.nonce;

            if (!confirm(`Are you sure you want to ${actionType} this order?`)) {
                return;
            }

            $button.prop('disabled', true).text('Processing...');

            $.ajax({
                url: AxytosActions.ajax_url,
                type: 'POST',
                data: {
                    action: 'axytos_action',
                    security: nonce,
                    order_id: orderId,
                    action_type: actionType
                },
                success: function (response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);

                    }
                },
                error: function () {
                    alert('An unexpected error occurred. Please try again.');
                },
                complete: function () {
                    $button.prop('disabled', false).text(actionType.charAt(0).toUpperCase() + actionType.slice(1));
                }
            });
            
        });
    });
})(jQuery);