<?php

namespace Axytos\WooCommerce;

if (!defined("ABSPATH")) {
    exit();
}

require_once __DIR__ . "/AxytosActionHandler.php";

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
    $is_automatic_change = $order->get_meta("_axytos_processing_status_change");
    if ($is_automatic_change) {
        return;
    }

    $action = determine_status_action($old_status, $new_status);
    if (empty($action)) {
        return;
    }

    // Mark as processing to prevent loops
    $order->update_meta_data("_axytos_processing_status_change", true);
    $order->save_meta_data();

    queue_status_action($order_id, $action, $old_status, $new_status);

    // Clear the processing flag
    $order->delete_meta_data("_axytos_processing_status_change");
    $order->save_meta_data();
}

/**
 * Determine which action to take based on status change
 */
function determine_status_action($old_status, $new_status)
{
    if ($new_status === "cancelled") {
        return "cancel";
    }

    if ($new_status === "processing") {
        return "confirm";
    }

    if ($new_status === "completed") {
        return "complete";
    }

    if ($new_status === "refunded") {
        return "refund";
    }

    error_log(
        "Axytos: No action determined for order status change from '$old_status' to '$new_status'"
    );
    return "";
}

/**
 * Execute the determined action using the action handler for robustness
 */
function queue_status_action($order_id, $action, $old_status, $new_status)
{
    try {
        $action_handler = new AxytosActionHandler();
        $success = $action_handler->addPendingAction($order_id, $action);
        if ($success) {
            error_log(
                "Axytos: Successfully queued $action for order #$order_id (status: $old_status -> $new_status)"
            );
        } else {
            // TODO: what to do here?
            error_log(
                "Axytos: Failed to queue $action for order #$order_id (status: $old_status -> $new_status)"
            );
        }
    } catch (\Exception $e) {
        // TODO: what to do here?
        error_log(
            "Axytos: Exception while queueing $action for order #$order_id: " .
                $e->getMessage()
        );
    }
}

function bootstrap_orders()
{
    // Hook order status changes with high priority to ensure it runs early
    add_action(
        "woocommerce_order_status_changed",
        __NAMESPACE__ . "\handle_order_status_change",
        5,
        4
    );
}
