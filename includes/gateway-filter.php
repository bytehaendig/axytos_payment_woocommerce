<?php

namespace Axytos\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Remove Axytos from gateways if denied by admin
 */
function filter_available_gateways($available_gateways)
{
    // Check if order awaiting payment exists in session
    if (WC()->session && $order_id = WC()->session->get('order_awaiting_payment') ?? WC()->session->get('store_api_draft_order')) {
        // Check if transient is set to disable Axytos
        if (get_transient('disable_axitos_for_' . $order_id)) {
            $chosen_payment_method = AXYTOS_PAYMENT_ID;
            if (isset($available_gateways[$chosen_payment_method])) {
                unset($available_gateways[$chosen_payment_method]);
            }
        }
    }
    return $available_gateways;
}

// Hook gateway filter
add_filter('woocommerce_available_payment_gateways', __NAMESPACE__ . '\filter_available_gateways');