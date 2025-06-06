<?php

namespace Axytos\WooCommerce;

use Automattic\WooCommerce\Utilities\OrderUtil;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Add Axytos Actions column to order list
 */
function add_order_column($columns)
{
    $columns["axytos_actions"] = __("Axytos Actions", "axytos-wc");
    return $columns;
}

/**
 * Render content for Axytos Actions column
 */
function render_order_column($column, $order)
{
    if ($column !== "axytos_actions") {
        return;
    }

    $order = is_a($order, "WC_Order") ? $order : wc_get_order($order);

    if ($order->get_payment_method() !== AXYTOS_PAYMENT_ID) {
        echo __("N/A", "axytos-wc");
        return;
    }

    render_action_buttons($order);
}

/**
 * Add metabox to order edit page
 */
function add_order_metabox()
{
    add_meta_box(
        "axytos_actions_metabox",
        __("Axytos Actions", "axytos-wc"),
        __NAMESPACE__ . '\render_order_metabox',
        OrderUtil::custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id("shop-order")
            : "shop_order",
        "side",
        "default"
    );
}

/**
 * Render metabox content
 */
function render_order_metabox($post_or_order)
{
    // Handle both HPOS and legacy post-based orders
    if (is_a($post_or_order, "WC_Order")) {
        // It's already an order object
        $order = $post_or_order;
    } elseif (method_exists($post_or_order, "ID")) {
        // It's a post object, get the order
        $order = wc_get_order($post_or_order->ID);
    }
    if (!$order) {
        echo "<p>" . __("Order not found.", "axytos-wc") . "</p>";
        return;
    }

    if ($order->get_payment_method() !== AXYTOS_PAYMENT_ID) {
        echo "<p>" .
            __("No Axytos actions available for this order.", "axytos-wc") .
            "</p>";
        return;
    }

    render_action_buttons($order);
}

/**
 * Render action buttons based on order status
 */
function render_action_buttons($order)
{
    $order_status = $order->get_status();
    $order_id = $order->get_id();

    if (!in_array($order_status, ["completed", "cancelled", "refunded"])) {
        echo '<div class="axytos-action-buttons-wrapper">';
        echo '<button class="button axytos-action-button" data-order-id="' .
            esc_attr($order_id) .
            '" data-action="report_shipping">' .
            __("Report Shipping", "axytos-wc") .
            "</button>";
        echo '<button class="button axytos-action-button" data-order-id="' .
            esc_attr($order_id) .
            '" data-action="cancel">' .
            __("Cancel", "axytos-wc") .
            "</button>";
        echo "</div>";
    } elseif ($order_status === "completed") {
        echo '<div class="axytos-action-buttons-wrapper">';
        echo '<button class="button axytos-action-button" data-order-id="' .
            esc_attr($order_id) .
            '" data-action="refund">' .
            __("Refund", "axytos-wc") .
            "</button>";
        echo "</div>";
    }
}

/**
 * Add webhook configuration section to gateway settings
 */
function add_webhook_settings_script()
{
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'wc-settings') === false) {
        return;
    }

    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Add generate button for webhook API key
        if ($('#woocommerce_axytoswc_webhook_api_key').length) {
            var $webhookKeyField = $('#woocommerce_axytoswc_webhook_api_key');
            var $generateBtn = $('<button type="button" class="button button-secondary" style="margin-left: 10px;"><?php echo esc_js(__('Generate Secure Key', 'axytos-wc')); ?></button>');
            
            $webhookKeyField.after($generateBtn);
            
            $generateBtn.on('click', function(e) {
                e.preventDefault();
                
                // Generate a secure random key
                var key = generateSecureKey(64);
                $webhookKeyField.val(key);
                
                // Show confirmation
                var $notice = $('<div class="notice notice-success inline" style="margin: 10px 0;"><p><?php echo esc_js(__('Secure webhook API key generated. Make sure to save your settings.', 'axytos-wc')); ?></p></div>');
                $webhookKeyField.closest('tr').after($('<tr><td colspan="2"></td></tr>').find('td').append($notice).end());
                
                // Remove notice after 5 seconds
                setTimeout(function() {
                    $notice.fadeOut(function() {
                        $(this).closest('tr').remove();
                    });
                }, 5000);
            });
            
            // Add webhook endpoint info
            var webhookUrl = '<?php echo esc_js(rest_url('axytos/v1/order-update')); ?>';
            var $infoHtml = $(
                '<div class="axytos-webhook-info" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #00a0d2;">' +
                '<strong><?php echo esc_js(__('Webhook Endpoint Information:', 'axytos-wc')); ?></strong><br>' +
                '<strong><?php echo esc_js(__('URL:', 'axytos-wc')); ?></strong> <code>' + webhookUrl + '</code><br>' +
                '<strong><?php echo esc_js(__('Method:', 'axytos-wc')); ?></strong> POST<br>' +
                '<strong><?php echo esc_js(__('Authentication:', 'axytos-wc')); ?></strong> <?php echo esc_js(__('Send API key in X-Axytos-Webhook-Key header', 'axytos-wc')); ?><br>' +
                '<strong><?php echo esc_js(__('Content-Type:', 'axytos-wc')); ?></strong> application/json' +
                '</div>'
            );
            $webhookKeyField.closest('tr').after($('<tr><td colspan="2"></td></tr>').find('td').append($infoHtml).end());
        }
        
        function generateSecureKey(length) {
            var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
            var key = '';
            
            // Use crypto.getRandomValues if available, fallback to Math.random
            if (window.crypto && window.crypto.getRandomValues) {
                var array = new Uint8Array(length);
                window.crypto.getRandomValues(array);
                for (var i = 0; i < length; i++) {
                    key += chars[array[i] % chars.length];
                }
            } else {
                for (var i = 0; i < length; i++) {
                    key += chars[Math.floor(Math.random() * chars.length)];
                }
            }
            
            return key;
        }
    });
    </script>
    <style>
    .axytos-webhook-info code {
        background: #f1f1f1;
        padding: 2px 4px;
        border-radius: 3px;
        font-family: monospace;
        word-break: break-all;
    }
    </style>
    <?php
}

// Hook admin functionality
// HPOS enabled
add_filter(
    "manage_woocommerce_page_wc-orders_columns",
    __NAMESPACE__ . "\add_order_column",
    20
);
add_action(
    "manage_woocommerce_page_wc-orders_custom_column",
    __NAMESPACE__ . '\render_order_column',
    20,
    2
);

// HPOS disabled
add_filter(
    "manage_edit-shop_order_columns",
    __NAMESPACE__ . "\add_order_column",
    20
);
add_action(
    "manage_shop_order_posts_custom_column",
    __NAMESPACE__ . '\render_order_column',
    20,
    2
);

// Metaboxes
add_action("add_meta_boxes", __NAMESPACE__ . "\add_order_metabox");

// Webhook admin scripts
add_action("admin_footer", __NAMESPACE__ . "\add_webhook_settings_script");
