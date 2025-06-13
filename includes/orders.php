<?php

namespace Axytos\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

// Include required data functions
require_once __DIR__ . "/axytos-data.php";

/**
 * Handle automatic actions when order status changes
 */
function handle_order_status_change($order_id, $old_status, $new_status, $order)
{
    if (!$order instanceof \WC_Order) {
        return;
    }

    if ($order->get_payment_method() !== \AXYTOS_PAYMENT_ID) {
        return;
    }

    // Prevent infinite loops by checking if this is an automatic status change
    $is_automatic_change = $order->get_meta('_axytos_processing_status_change');
    if ($is_automatic_change) {
        return;
    }

    $action = determine_status_action($old_status, $new_status);
    if (empty($action)) {
        return;
    }

    // Mark as processing to prevent loops
    $order->update_meta_data('_axytos_processing_status_change', true);
    $order->save_meta_data();

    execute_status_action($order_id, $action, $old_status, $new_status);

    // Clear the processing flag
    $order->delete_meta_data('_axytos_processing_status_change');
    $order->save_meta_data();
}

/**
 * Determine which action to take based on status change
 */
function determine_status_action($old_status, $new_status)
{
    // Handle cancellation from various states
    if ($new_status === 'cancelled' && in_array($old_status, ['pending', 'on-hold', 'processing'])) {
        return 'cancel';
    }

    // Handle confirmation when moving to processing
    if ($new_status === 'processing' && in_array($old_status, ['pending', 'on-hold'])) {
        return 'confirm';
    }

    // Handle shipping report when completing order
    if ($new_status === 'completed' && in_array($old_status, ['processing', 'on-hold'])) {
        return 'report_shipping';
    }

    // Handle refund status
    if ($new_status === 'refunded' && in_array($old_status, ['processing', 'completed'])) {
        return 'refund';
    }

    return '';
}

/**
 * Execute the determined action directly instead of via AJAX
 */
function execute_status_action($order_id, $action, $old_status, $new_status)
{
    error_log("Axytos: Executing automatic action '$action' for order #$order_id (status: $old_status -> $new_status)");
    try {
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("Axytos: Order #$order_id not found for automatic $action action");
            return;
        }

        // Ensure the gateway class is available
        if (!class_exists('Axytos\\WooCommerce\\AxytosPaymentGateway')) {
            error_log("Axytos: AxytosPaymentGateway class not available for automatic $action action");
            return;
        }

        // Initialize gateway
        $gateway = new AxytosPaymentGateway();

        // Execute action directly
        $result = false;
        switch ($action) {
            case 'report_shipping':
                // For automatic shipping, don't require invoice number
                $result = execute_shipping_action($gateway, $order);
                break;
            case 'cancel':
                $result = execute_cancel_action($gateway, $order);
                break;
            case 'refund':
                $result = execute_refund_action($gateway, $order);
                break;
            case 'confirm':
                $result = execute_confirm_action($gateway, $order);
                break;
            default:
                error_log("Axytos: Unknown action '$action' for order #$order_id");
                return;
        }

        if ($result) {
            error_log("Axytos: Successfully executed $action for order #$order_id (status: $old_status -> $new_status)");

            // Add order note for tracking
            $order->add_order_note(
                sprintf(
                    __('Axytos action "%s" executed automatically due to status change from "%s" to "%s"', 'axytos-wc'),
                    $action,
                    $old_status,
                    $new_status
                )
            );
        } else {
            error_log("Axytos: Failed to execute $action for order #$order_id (status: $old_status -> $new_status)");
        }

    } catch (\Exception $e) {
        error_log("Axytos: Exception during automatic $action for order #$order_id: " . $e->getMessage());
    }
}

/**
 * Execute shipping action with error handling
 */
function execute_shipping_action($gateway, $order)
{
    $isShipped = $order->get_meta("axytos_shipped");
    if ($isShipped) {
        return true; // Already shipped
    }

    $result = $gateway->reportShipping($order);
    if ($result) {
        // Don't require invoice number for automatic shipping
        $gateway->createInvoice($order, '');
        return true;
    }
    return false;
}

/**
 * Execute cancel action with error handling
 */
function execute_cancel_action($gateway, $order)
{
    return $gateway->cancelOrder($order);
}

/**
 * Execute refund action with error handling
 */
function execute_refund_action($gateway, $order)
{
    return $gateway->refundOrder($order);
}

/**
 * Execute confirm action with error handling
 */
function execute_confirm_action($gateway, $order)
{
    return $gateway->confirmOrder($order);
}

function bootstrap_orders()
{
    // Hook order status changes with high priority to ensure it runs early
    add_action('woocommerce_order_status_changed', __NAMESPACE__ . '\handle_order_status_change', 5, 4);
}
