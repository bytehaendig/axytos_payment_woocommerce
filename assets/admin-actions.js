(function ($) {
    $(document).ready(function () {
        $('.axytos-action-button').on('click', function () {
            const $button = $(this);
            const orderId = $button.data('order-id');
            const actionType = $button.data('action');
            const nonce = AxytosActions.nonce;

            // Handle report_shipping action differently
            if (actionType === 'report_shipping') {
                const invoiceNumber = prompt(AxytosActions.i18n.invoice_prompt);
                
                if (invoiceNumber === null) {
                    // User cancelled the dialog
                    return;
                }
                
                if (!invoiceNumber || invoiceNumber.trim() === '') {
                    alert(AxytosActions.i18n.invoice_required);
                    return;
                }

                if (!confirm(AxytosActions.i18n.confirm_action_with_invoice.replace('%s', actionType).replace('%s', invoiceNumber))) {
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
                        action_type: actionType,
                        invoice_number: invoiceNumber.trim()
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
                        alert(AxytosActions.i18n.unexpected_error);
                    },
                    complete: function () {
                        $button.prop('disabled', false).text('Report Shipping');
                    }
                });
            } else {
                // Handle other actions as before
                if (!confirm(AxytosActions.i18n.confirm_action.replace('%s', actionType))) {
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
                        alert(AxytosActions.i18n.unexpected_error);
                    },
                    complete: function () {
                        $button.prop('disabled', false).text(actionType.charAt(0).toUpperCase() + actionType.slice(1));
                    }
                });
            }
        });
    });
})(jQuery);