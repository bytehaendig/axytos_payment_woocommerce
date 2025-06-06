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
 * Add WooCommerce Blocks support
 */
function add_blocks_support()
{
    if (
        !class_exists(
            "Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType"
        )
    ) {
        error_log(
            "--- Axytos Debug --- AbstractPaymentMethodType class not found."
        );
        return;
    }

    require_once plugin_dir_path(__FILE__) . "AxytosBlocksGateway.php";

    add_action("woocommerce_blocks_payment_method_type_registration", function (
        \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry
    ) {
        $payment_method_registry->register(new \AxytosBlocksGateway());
    });
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
        load_admin_functionality();
    } elseif (!is_admin() || is_ajax_request() || is_rest_request()) {
        load_frontend_functionality();
    }
}

/**
 * Load functionality needed in both admin and frontend contexts
 */
function load_shared_functionality()
{
    // Load the main gateway class (needed for both admin and frontend)
    require_once plugin_dir_path(__FILE__) . "AxytosPaymentGateway.php";
    // Add the gateway to WooCommerce (needed for both admin and frontend)
    add_filter(
        "woocommerce_payment_gateways",
        __NAMESPACE__ . "\add_gateway_class"
    );

    // AJAX handlers (needed for both frontend AJAX and admin AJAX)
    require_once plugin_dir_path(__FILE__) . "ajax.php";
    // Gateway filter (needed for checkout and admin order processing)
    require_once plugin_dir_path(__FILE__) . "gateway-filter.php";
    // Order manager (needed for status changes in both contexts)
    require_once plugin_dir_path(__FILE__) . "order-manager.php";
    // Webhook handler (needed for REST API endpoint)
    require_once plugin_dir_path(__FILE__) . "AxytosWebhookHandler.php";
    
    // Initialize webhook handler
    new AxytosWebhookHandler();
}

/**
 * Load admin-specific functionality
 */
function load_admin_functionality()
{
    // Load admin functions (order columns, metaboxes)
    require_once plugin_dir_path(__FILE__) . "admin.php";

    // Enqueue admin scripts and styles
    add_action(
        "admin_enqueue_scripts",
        __NAMESPACE__ . '\enqueue_admin_assets'
    );
}

/**
 * Load frontend-specific functionality
 */
function load_frontend_functionality()
{
    // Load frontend functions (checkout scripts, thank you notices)
    require_once plugin_dir_path(__FILE__) . "frontend.php";

    // Enqueue frontend scripts and styles
    add_action(
        "wp_enqueue_scripts",
        __NAMESPACE__ . '\enqueue_frontend_assets'
    );

    // Add agreement link to gateway description
    add_filter(
        "woocommerce_gateway_description",
        __NAMESPACE__ . "\add_agreement_link_to_gateway_description",
        10,
        2
    );
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
    $gateways[] = "AxytosPaymentGateway";
    return $gateways;
}

/**
 * Enqueue admin scripts and styles
 */
function enqueue_admin_assets()
{
    wp_enqueue_script(
        "axytos-admin-actions",
        plugin_dir_url(dirname(__FILE__)) . "/assets/admin-actions.js",
        ["jquery"],
        AXYTOS_PLUGIN_VERSION,
        true
    );

    wp_localize_script("axytos-admin-actions", "AxytosActions", [
        "ajax_url" => admin_url("admin-ajax.php"),
        "nonce" => wp_create_nonce("axytos_action_nonce"),
        "i18n" => [
            "invoice_prompt" => __("Please enter the invoice number:", "axytos-wc"),
            "invoice_required" => __("Invoice number is required for shipping report.", "axytos-wc"),
            "confirm_action" => __("Are you sure you want to %s this order?", "axytos-wc"),
            "confirm_action_with_invoice" => __("Are you sure you want to %s this order with invoice number: %s?", "axytos-wc"),
            "unexpected_error" => __("An unexpected error occurred. Please try again.", "axytos-wc"),
        ],
    ]);

    wp_enqueue_style(
        "axytos-admin-styles",
        plugin_dir_url(dirname(__FILE__)) . "/assets/css/style.css",
        [],
        AXYTOS_PLUGIN_VERSION
    );
}

/**
 * Enqueue frontend scripts and styles for checkout
 */
function enqueue_frontend_assets()
{
    // Only load on checkout page
    if (!is_checkout()) {
        return;
    }

    wp_enqueue_style(
        "axytos-agreement-popup",
        plugin_dir_url(dirname(__FILE__)) . "/assets/css/agreement_popup.css",
        [],
        AXYTOS_PLUGIN_VERSION
    );

    wp_enqueue_script(
        "axytos-agreement",
        plugin_dir_url(dirname(__FILE__)) . "/assets/axytos-agreement.js",
        ["jquery"],
        AXYTOS_PLUGIN_VERSION,
        true
    );

    wp_localize_script("axytos-agreement", "axytos_agreement", [
        "ajax_url" => admin_url("admin-ajax.php"),
        "nonce" => wp_create_nonce("axytos_agreement_nonce"),
    ]);
}

/**
 * Add agreement link to gateway description
 */
function add_agreement_link_to_gateway_description($description, $payment_id)
{
    if (AXYTOS_PAYMENT_ID !== $payment_id) {
        return $description;
    }

    $settings = get_option("woocommerce_axytoswc_settings", []);
    $agreement_text = $settings["PrecheckAgreeText"] ?? "";

    if (!empty($agreement_text)) {
        $description .=
            ' <br><a href="#" class="axytos-agreement-link">' .
            esc_html($agreement_text) .
            "</a>";
    }

    return $description;
}

// Initialize everything
add_action("plugins_loaded", __NAMESPACE__ . "\load_textdomain", 1);
add_action("plugins_loaded", __NAMESPACE__ . "\initialize_woocommerce");
add_action("woocommerce_blocks_loaded", __NAMESPACE__ . "\add_blocks_support");
