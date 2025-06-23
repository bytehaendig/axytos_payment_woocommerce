<?php

namespace Axytos\WooCommerce;

if (!defined("ABSPATH")) {
    exit();
}

require_once __DIR__ . "/AxytosActionHandler.php";

/**
 * Handle Axytos actions via AJAX
 */
function handle_ajax_action()
{
    // TODO: Verify nonce
    // check_ajax_referer('axytos_action_nonce', 'security');

    $order_id = absint($_POST["order_id"]);
    $action_type = sanitize_text_field($_POST["action_type"]);
    $invoice_number = isset($_POST["invoice_number"])
        ? sanitize_text_field($_POST["invoice_number"])
        : "";

    if (!$order_id || !$action_type) {
        wp_send_json_error([
            "message" => __("Invalid order or action.", "axytos-wc"),
        ]);
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(["message" => __("Order not found.", "axytos-wc")]);
    }

    if ($order->get_payment_method() !== \AXYTOS_PAYMENT_ID) {
        wp_send_json_error([
            "message" => __("Not an Axytos order.", "axytos-wc"),
        ]);
    }

    try {
        $action_handler = new AxytosActionHandler();

        switch ($action_type) {
            case "shipped":
                // Store invoice number if provided
                if (!empty($invoice_number)) {
                    $action_handler->setInvoiceNumber(
                        $order_id,
                        $invoice_number
                    );
                } else {
                    wp_send_json_error([
                        "message" => __(
                            "Invoice number is required for shipping report.",
                            "axytos-wc"
                        ),
                    ]);
                }
                // will trigger axytos shipping process (see orders.php)
                $order->update_status(
                    "completed",
                    __("Order shipped via Axytos action.", "axytos-wc")
                );
                break;
            // TODO: maybe get rid of 'cancel', 'refund' and 'confirm' actions - just use regular wooCommerce actions
            case "cancel":
                // will trigger axytos cancellation process (see orders.php)
                // will trigger axytos cancellation process (see orders.php)
                $order->update_status(
                    "cancelled",
                    __("Order cancelled via Axytos action.", "axytos-wc")
                );
                break;
            case "refund":
                // will trigger axytos refund process (see orders.php)
                $order->update_status(
                    "refunded",
                    __("Order refunded via Axytos action.", "axytos-wc")
                );
                break;
            case "confirm":
                // will trigger axytos confirmation process (see orders.php)
                $order->update_status(
                    "processing",
                    __("Order confirmed via Axytos action.", "axytos-wc")
                );
                break;
            default:
                wp_send_json_error([
                    "message" => __("Invalid action.", "axytos-wc"),
                ]);
        }

        wp_send_json_success([
            "message" => __("Action queued successfully.", "axytos-wc"),
        ]);
    } catch (\Exception $e) {
        wp_send_json_error([
            "message" =>
                __("Error processing action: ", "axytos-wc") . $e->getMessage(),
        ]);
    }
}

/**
 * Load agreement content via AJAX
 */
function load_agreement()
{
    if (
        !isset($_POST["nonce"]) ||
        !wp_verify_nonce($_POST["nonce"], "axytos_agreement_nonce")
    ) {
        wp_send_json_error(["message" => "Invalid nonce"]);
    }

    try {
        $gateway = new AxytosPaymentGateway();
        $agreement_content = $gateway->getAgreement();
        wp_send_json_success($agreement_content);
    } catch (\Exception $e) {
        wp_send_json_error(["message" => $e->getMessage()]);
    }
}

/**
 * Handle manual processing trigger via AJAX (admin only)
 */
function handle_manual_processing()
{
    if (!current_user_can("manage_woocommerce")) {
        wp_send_json_error([
            "message" => __("Insufficient permissions.", "axytos-wc"),
        ]);
    }

    try {
        $action_handler = new AxytosActionHandler();
        $result = $action_handler->processAllPendingActions();

        wp_send_json_success([
            "message" => sprintf(
                /* translators: 1: number of processed orders, 2: number of failed orders */
                __("Processed %1\$d orders, %2\$d failed.", "axytos-wc"),
                $result["processed"],
                $result["failed"]
            ),
            "processed" => $result["processed"],
            "failed" => $result["failed"],
        ]);
    } catch (\Exception $e) {
        wp_send_json_error([
            "message" =>
                __("Error processing actions: ", "axytos-wc") .
                $e->getMessage(),
        ]);
    }
}

function bootstrap_ajax()
{
    add_action("wp_ajax_axytos_action", __NAMESPACE__ . "\handle_ajax_action");
    add_action(
        "wp_ajax_nopriv_axytos_action",
        __NAMESPACE__ . "\handle_ajax_action"
    );
    add_action(
        "wp_ajax_load_axytos_agreement",
        __NAMESPACE__ . "\load_agreement"
    );
    add_action(
        "wp_ajax_nopriv_load_axytos_agreement",
        __NAMESPACE__ . "\load_agreement"
    );
    add_action(
        "wp_ajax_axytos_manual_processing",
        __NAMESPACE__ . "\handle_manual_processing"
    );
}
