<?php

namespace Axytos\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add JavaScript to refresh checkout on error
 */
function add_checkout_error_handler()
{
    if (!is_checkout()) {
        return;
    }
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

/**
 * Show notice on thank you page for on-hold orders
 */
function show_thankyou_notice($order_id)
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

// Hook frontend functionality
add_action('wp_footer', __NAMESPACE__ . '\add_checkout_error_handler');
add_action('woocommerce_thankyou', __NAMESPACE__ . '\show_thankyou_notice', 10, 1);