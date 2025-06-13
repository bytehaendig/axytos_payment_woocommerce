<?php

namespace Axytos\WooCommerce;

if (!defined("ABSPATH")) {
    exit();
}

// Include required data functions
require_once __DIR__ . "/axytos-data.php";
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

    execute_status_action($order_id, $action, $old_status, $new_status);

    // Clear the processing flag
    $order->delete_meta_data("_axytos_processing_status_change");
    $order->save_meta_data();
}

/**
 * Determine which action to take based on status change
 */
function determine_status_action($old_status, $new_status)
{
    // Handle cancellation from various states
    if (
        $new_status === "cancelled" &&
        in_array($old_status, ["pending", "on-hold", "processing"])
    ) {
        return "cancel";
    }

    // Handle confirmation when moving to processing
    if (
        $new_status === "processing" &&
        in_array($old_status, ["pending", "on-hold"])
    ) {
        return "confirm";
    }

    // Handle shipping report when completing order
    if (
        $new_status === "completed" &&
        in_array($old_status, ["processing", "on-hold"])
    ) {
        return "report_shipping";
    }

    // Handle refund status
    if (
        $new_status === "refunded" &&
        in_array($old_status, ["processing", "completed"])
    ) {
        return "refund";
    }

    return "";
}

/**
 * Execute the determined action using the action handler for robustness
 */
function execute_status_action($order_id, $action, $old_status, $new_status)
{
    error_log(
        "Axytos: Adding pending action '$action' for order #$order_id (status: $old_status -> $new_status)"
    );
    try {
        $action_handler = new AxytosActionHandler();

        // Map action names to match the action handler
        $mapped_action = $action;
        // TODO: rename
        if ($action === "report_shipping") {
            $mapped_action = "shipped";
        }

        $success = $action_handler->addPendingAction($order_id, $mapped_action);

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
