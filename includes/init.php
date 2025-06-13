<?php

namespace Axytos\WooCommerce;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Load plugin text domain for translations
 */
function load_textdomain()
{
    load_plugin_textdomain(
        "axytos-wc",
        false,
        dirname(plugin_basename(__FILE__)) . "/languages/"
    );
}

/**
 * Initialize WooCommerce integration
 */
function initialize_woocommerce()
{
    if (!class_exists("WC_Payment_Gateway")) {
        return;
    }

    // Always load these (needed in multiple contexts)
    load_shared_functionality();

    // Load context-specific functionality
    if (is_admin() && !is_ajax_request()) {
        include_once plugin_dir_path(__FILE__) . "admin.php";
        bootstrap_admin();
    } elseif (!is_admin() || is_ajax_request() || is_rest_request()) {
        include_once plugin_dir_path(__FILE__) . "frontend.php";
        bootstrap_frontend();
    }
}

function bootstrap_gateway()
{
    require_once plugin_dir_path(__FILE__) . "AxytosPaymentGateway.php";
    add_filter(
        "woocommerce_payment_gateways",
        __NAMESPACE__ . "\add_gateway_class"
    );
}

function bootstrap_webhooks()
{
    require_once plugin_dir_path(__FILE__) . "AxytosWebhookHandler.php";
    $handler = new AxytosWebhookHandler();
    // Register the REST API endpoint for webhook
    add_action("rest_api_init", [$handler, "register_webhook_endpoint"]);
}

/**
 * Load functionality needed in both admin and frontend contexts
 */
function load_shared_functionality()
{
    bootstrap_gateway();
    bootstrap_webhooks();
    // AJAX handlers (needed for both frontend AJAX and admin AJAX)
    require_once plugin_dir_path(__FILE__) . "ajax.php";
    bootstrap_ajax();
    // Gateway filter (needed for checkout and admin order processing)
    require_once plugin_dir_path(__FILE__) . "payments.php";
    bootstrap_payments();
    // Order manager (needed for status changes in both contexts)
    require_once plugin_dir_path(__FILE__) . "orders.php";
    bootstrap_orders();
}

/**
 * Load admin-specific functionality
 */
function load_admin_functionality()
{
    // Load admin functions (order columns, metaboxes)

}

/**
 * Load frontend-specific functionality
 */
function load_frontend_functionality()
{
    // Load frontend functions (checkout scripts, thank you notices)

}

/**
 * Detect if this is an AJAX request
 */
function is_ajax_request()
{
    return defined("DOING_AJAX") && DOING_AJAX;
}

/**
 * Detect if this is a REST API request
 */
function is_rest_request()
{
    return defined("REST_REQUEST") && REST_REQUEST;
}

/**
 * Add Axytos gateway to WooCommerce payment gateways
 */
function add_gateway_class($gateways)
{
    $gateways[] = "Axytos\\WooCommerce\\AxytosPaymentGateway";
    return $gateways;
}

function bootstrap()
{
    // Initialize everything
    add_action("plugins_loaded", __NAMESPACE__ . "\load_textdomain", 1);
    add_action("plugins_loaded", __NAMESPACE__ . "\initialize_woocommerce");
}
