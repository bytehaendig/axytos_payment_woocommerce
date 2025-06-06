<?php

namespace Axytos\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle Axytos actions via AJAX
 */
function handle_ajax_action()
{
    // TODO: Verify nonce
    // check_ajax_referer('axytos_action_nonce', 'security');
    
    $order_id = absint($_POST['order_id']);
    $action_type = sanitize_text_field($_POST['action_type']);
    $invoice_number = isset($_POST['invoice_number']) ? sanitize_text_field($_POST['invoice_number']) : '';
    $is_manual = isset($_POST['manual']) ? (bool) $_POST['manual'] : true;
    
    if (!$order_id || !$action_type) {
        wp_send_json_error(['message' => __('Invalid order or action.', 'axytos-wc')]);
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(['message' => __('Order not found.', 'axytos-wc')]);
    }

    if ($order->get_payment_method() !== \AXYTOS_PAYMENT_ID) {
        wp_send_json_error(['message' => __('Not an Axytos order.', 'axytos-wc')]);
    }

    try {
        $gateway = new AxytosPaymentGateway();
        
        switch ($action_type) {
            case 'report_shipping':
                if ($is_manual && empty($invoice_number)) {
                    wp_send_json_error(['message' => __('Invoice number is required for shipping report.', 'axytos-wc')]);
                }
                $gateway->actionReportShipping($order, $invoice_number);
                break;
            case 'cancel':
                $gateway->actionCancel($order);
                break;
            case 'refund':
                $gateway->actionRefund($order);
                break;
            case 'confirm':
                $gateway->actionConfirm($order);
                break;
            default:
                wp_send_json_error(['message' => __('Invalid action.', 'axytos-wc')]);
        }
        
        wp_send_json_success(['message' => __('Action completed successfully.', 'axytos-wc')]);
        
    } catch (\Exception $e) {
        wp_send_json_error(['message' => __('Error processing action: ', 'axytos-wc') . $e->getMessage()]);
    }
}

/**
 * Load agreement content via AJAX
 */
function load_agreement()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'axytos_agreement_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }

    try {
        $gateway = new AxytosPaymentGateway();
        $agreement_content = $gateway->getAgreement();
        wp_send_json_success($agreement_content);
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// Hook AJAX handlers
add_action('wp_ajax_axytos_action', __NAMESPACE__ . '\handle_ajax_action');
add_action('wp_ajax_nopriv_axytos_action', __NAMESPACE__ . '\handle_ajax_action');

add_action('wp_ajax_load_axytos_agreement', __NAMESPACE__ . '\load_agreement');
add_action('wp_ajax_nopriv_load_axytos_agreement', __NAMESPACE__ . '\load_agreement');