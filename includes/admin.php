<?php

namespace Axytos\WooCommerce;

use Automattic\WooCommerce\Utilities\OrderUtil;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Axytos Actions column to order list
 */
function add_order_column($columns)
{
    $columns['axytos_actions'] = __('Axytos Actions', 'axytos-wc');
    return $columns;
}

/**
 * Render content for Axytos Actions column
 */
function render_order_column($column, $order)
{
    if ($column !== 'axytos_actions') {
        return;
    }

    $order = is_a($order, 'WC_Order') ? $order : wc_get_order($order);
    
    if ($order->get_payment_method() !== AXYTOS_PAYMENT_ID) {
        echo __('N/A', 'axytos-wc');
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
        'axytos_actions_metabox',
        __('Axytos Actions', 'axytos-wc'),
        __NAMESPACE__ . '\render_order_metabox',
        OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id('shop-order') : 'shop_order',
        'side',
        'default'
    );
}

/**
 * Render metabox content
 */
function render_order_metabox($post)
{
    $order = wc_get_order($post->ID);
    if (!$order) {
        echo '<p>' . __('Order not found.', 'axytos-wc') . '</p>';
        return;
    }

    if ($order->get_payment_method() !== AXYTOS_PAYMENT_ID) {
        echo '<p>' . __('No Axytos actions available for this order.', 'axytos-wc') . '</p>';
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
    
    if (!in_array($order_status, ['completed', 'cancelled', 'refunded'])) {
        echo '<div class="axytos-action-buttons-wrapper">';
        echo '<button class="button axytos-action-button" data-order-id="' . esc_attr($order_id) . '" data-action="report_shipping">' . __('Report Shipping', 'axytos-wc') . '</button>';
        echo '<button class="button axytos-action-button" data-order-id="' . esc_attr($order_id) . '" data-action="cancel">' . __('Cancel', 'axytos-wc') . '</button>';
        echo '</div>';
    } elseif ($order_status === 'completed') {
        echo '<div class="axytos-action-buttons-wrapper">';
        echo '<button class="button axytos-action-button" data-order-id="' . esc_attr($order_id) . '" data-action="refund">' . __('Refund', 'axytos-wc') . '</button>';
        echo '</div>';
    }
}

// Hook admin functionality
// HPOS enabled
add_filter('manage_woocommerce_page_wc-orders_columns', __NAMESPACE__ . '\add_order_column', 20);
add_action('manage_woocommerce_page_wc-orders_custom_column', __NAMESPACE__ . '\render_order_column', 20, 2);

// HPOS disabled
add_filter('manage_edit-shop_order_columns', __NAMESPACE__ . '\add_order_column', 20);
add_action('manage_shop_order_posts_custom_column', __NAMESPACE__ . '\render_order_column', 20, 2);

// Metaboxes
add_action('add_meta_boxes', __NAMESPACE__ . '\add_order_metabox');