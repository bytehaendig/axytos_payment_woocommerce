<?php

namespace Axytos\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle automatic actions when order status changes
 */
function handle_order_status_change($order_id, $old_status, $new_status, $order)
{
    if (!$order instanceof \WC_Order) {
        return;
    }

    if ($order->get_payment_method() !== AXYTOS_PAYMENT_ID) {
        return;
    }

    $action = determine_status_action($old_status, $new_status);
    if (empty($action)) {
        return;
    }

    execute_status_action($order_id, $action);
}

/**
 * Determine which action to take based on status change
 */
function determine_status_action($old_status, $new_status)
{
    if ($old_status === 'processing' && $new_status === 'cancelled') {
        return 'cancel';
    } elseif ($old_status === 'on-hold' && $new_status === 'processing') {
        return 'confirm';
    } elseif ($new_status === 'completed') {
        return 'report_shipping';
    }

    return '';
}

/**
 * Execute the determined action
 */
function execute_status_action($order_id, $action)
{
    $endpoint_url = site_url('/wp-admin/admin-ajax.php');
    
    $response = wp_remote_post($endpoint_url, [
        'timeout' => 60,
        'body' => [
            'action' => 'axytos_action',
            'order_id' => $order_id,
            'action_type' => $action,
            'security' => wp_create_nonce('axytos_action_nonce'),
            'manual' => false,  // Mark as automatic status change
        ],
    ]);

    if (is_wp_error($response)) {
        error_log("Axytos action ($action) failed for order #$order_id: " . $response->get_error_message());
    } else {
        error_log("Axytos action ($action) successfully triggered for order #$order_id.");
    }
}

// Hook order status changes
add_action('woocommerce_order_status_changed', __NAMESPACE__ . '\handle_order_status_change', 10, 4);