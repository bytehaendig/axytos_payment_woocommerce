<?php
use Automattic\WooCommerce\Utilities\OrderUtil;

function axytos_textdomain()
{
    load_plugin_textdomain('axytos-wc', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'axytos_textdomain', 1);

// Remove Axytos from gateways if denied by admin
add_filter('woocommerce_available_payment_gateways', function ($available_gateways) {
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
});

//refresh the checkout on error
add_action('wp_footer', function () {
    if (is_checkout()) {
        ?>
    <script>
    jQuery(document).ready(function ($) {
      $(document.body).on('checkout_error', function () {
        $(document.body).trigger('update_checkout');
      });
    });
    </script>
    <?php
    }
});

//message on thankyou page in case of on-hold
add_action('woocommerce_thankyou', 'axytos_thankyou_notice', 10, 1);

function axytos_thankyou_notice($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    if ($order->get_payment_method() === AXYTOS_PAYMENT_ID && $order->get_status() === 'on-hold') {
        echo '<div class="woocommerce-notice woocommerce-info woocommerce-notice--info woocommerce-thankyou-notice">';
        echo __('Order on-hold, waiting for admin approval.', 'axytos-wc');
        echo '</div>';
    }
}

// Add the 'Axytos Actions' column on Orders View page

//if HPOS is enabled
add_filter('manage_woocommerce_page_wc-orders_columns', 'column_adder', 20);
//if HPOS is disabled
add_filter('manage_edit-shop_order_columns', 'column_adder', 20);
function column_adder($columns)
{
    $columns['axytos_actions'] = __('Axytos Actions', 'axytos-wc');
    return $columns;
};

// Populate the 'Axytos Actions' column with buttons

//if HPOS is enabled
add_action('manage_woocommerce_page_wc-orders_custom_column', 'column_html', 20, 2);
//if HPOS is disabled
add_action('manage_shop_order_posts_custom_column', 'column_html', 20, 2);
function column_html($column, $order)
{
    if ($column === 'axytos_actions') {
        $order = is_a($order, 'WC_Order') ? $order : wc_get_order($order);
        if ($order->get_payment_method() === AXYTOS_PAYMENT_ID) {
            $nonce = wp_create_nonce('axytos_action_nonce');
            $order_status = $order->get_status();
            // Only show buttons if the order is neither completed nor cancelled
            if (!in_array($order_status, ['completed', 'cancelled', 'refunded'])) {
                echo '
          <div class="axytos-action-buttons-wrapper">
          <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="report_shipping">' . __('Report Shipping', 'axytos-wc') . '</button>
          <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="cancel">' . __('Cancel', 'axytos-wc') . '</button>
          </div>';
            } elseif ($order_status === 'completed') {
                echo '<div class="axytos-action-buttons-wrapper">
          <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="refund">' . __('Refund', 'axytos-wc') . '</button>
          </div>';
            } else {
                // For cancelled orders, show nothing
                echo '';
            }
        } else {
            echo __('N/A', 'axytos-wc');
        }
    }
}

// Send Requests on Order Status Change
add_action('woocommerce_order_status_changed', 'handle_axytos_status_change', 10, 4);

function handle_axytos_status_change($order_id, $old_status, $new_status, $order)
{
    if (!$order instanceof WC_Order) {
        return;
    }
    if (!$order->get_payment_method() === AXYTOS_PAYMENT_ID) {
        return;
    }
    $endpoint_url = site_url('/wp-admin/admin-ajax.php');
    $action = '';
    if ($old_status === 'processing' && $new_status === 'cancelled') {
        $action = 'cancel';
    } elseif ($old_status === 'on-hold' && $new_status === 'processing') {
        $action = 'confirm';
    } elseif ($new_status === 'completed') {
        $action = 'report_shipping';
    }
    if (empty($action)) {
        return;
    }
    $response = wp_remote_post($endpoint_url, [
      'timeout' => 60,
      'body' => [
        'action' => 'axytos_action',
        'order_id' => $order_id,
        'action_type' => $action,
        'security' => wp_create_nonce('axytos_action_nonce'),
      ],
    ]);
    if (is_wp_error($response)) {
        error_log("Axytos action ($action) failed for order #$order_id: " . $response->get_error_message());
    } else {
        error_log("Axytos action ($action) successfully triggered for order #$order_id.");
    }
}

// Add a metabox for Axytos Actions on the order edit page
add_action('add_meta_boxes', 'add_axytos_actions_metabox');
function add_axytos_actions_metabox()
{
    add_meta_box(
        'axytos_actions_metabox',
        __('Axytos Actions', 'axytos-wc'),
        'render_axytos_actions_metabox',
        OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id('shop-order') : 'shop_order', // Post type: WooCommerce Orders
        'side',       // Position: Side column
        'default'     // Priority: Default
    );
}

// Render the Axytos Actions metabox content
function render_axytos_actions_metabox($post)
{
    $order = wc_get_order($post->ID);
    if (!$order) {
        echo '<p>' . __('Order not found.', 'axytos-wc') . '</p>';
        return;
    }
    if ($order->get_payment_method() === AXYTOS_PAYMENT_ID) {
        $nonce = wp_create_nonce('axytos_action_nonce');
        $order_status = $order->get_status();
        if (!in_array($order_status, ['completed', 'cancelled'])) {
            echo '
        <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="report_shipping">' . __('Report Shipping', 'axytos-wc') . '</button>
        <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="cancel">' . __('Cancel', 'axytos-wc') . '</button>
        <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="refund">' . __('Refund', 'axytos-wc') . '</button>';
        } elseif ($order_status === 'completed') {
            echo '<div class="axytos-action-buttons-wrapper">
        <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="refund">' . __('Refund', 'axytos-wc') . '</button>
        </div>';
        } else {
            // For cancelled orders, show nothing
            echo '';
        }
    } else {
        echo '<p>' . __('No Axytos actions available for this order.', 'axytos-wc') . '</p>';
    }
}

//Endpoint for statuses
add_action('wp_ajax_axytos_action', 'handle_axitos_action');
add_action('wp_ajax_nopriv_axytos_action', 'handle_axitos_action');

function handle_axitos_action()
{
    // TODO: Verify nonce
    // check_ajax_referer('axytos_action_nonce', 'security');
    $axyos_gateway_obj = new AxytosPaymentGateway();
    $order_id = absint($_POST['order_id']);
    $action_type = sanitize_text_field($_POST['action_type']);
    if (!$order_id || !$action_type) {
        wp_send_json_error(['message' => __('Invalid order or action.', 'axytos-wc')]);
    }
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(['message' => __('Order not found.', 'axytos-wc')]);
    }
    if ($order->get_payment_method() !== AXYTOS_PAYMENT_ID) {
        wp_send_json_error(['message' => __('Not an Axytos order.', 'axytos-wc')]);
    }
    try {
        switch ($action_type) {
            case 'report_shipping':
                $axyos_gateway_obj->actionReportShipping($order);
                break;

            case 'cancel':
                $axyos_gateway_obj->actionCancel($order);
                break;

            case 'refund':
                $axyos_gateway_obj->actionRefund($order);
                break;

            case 'confirm':
                $axyos_gateway_obj->actionConfirm($order);
                // no break
            default:
                wp_send_json_error(['message' => __('Invalid action.', 'axytos-wc')]);
        }
    } catch (Exception $e) {
        wp_send_json_error(['message' => __('Error processing action: ', 'axytos-wc') . $e->getMessage()]);
    }
}

function axytos_load_agreement()
{
    // Verify the nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'axytos_agreement_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }
    $axyos_gateway_obj = new AxytosPaymentGateway();
    $agreement_content = $axyos_gateway_obj->getAgreement();
    wp_send_json_success($agreement_content);
}
add_action('wp_ajax_load_axytos_agreement', 'axytos_load_agreement');
add_action('wp_ajax_nopriv_load_axytos_agreement', 'axytos_load_agreement');


// Check if WooCommerce is active and load the gateway only if it is
