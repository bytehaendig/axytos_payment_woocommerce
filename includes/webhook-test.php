<?php

namespace Axytos\WooCommerce;

/**
 * Axytos Webhook Test Utility
 *
 * This file provides a simple test utility for the Axytos webhook endpoint.
 * It can be used for development and debugging purposes.
 *
 * Usage: Access this file directly via browser or run via WP-CLI
 * Example: https://yoursite.com/wp-content/plugins/axytos-woocommerce/includes/webhook-test.php?action=test
 */

// Prevent direct access in production
// if (!defined('WP_DEBUG') || !WP_DEBUG) {
//     wp_die('This test utility is only available when WP_DEBUG is enabled.');
// }

// Load WordPress
if (!defined("ABSPATH")) {
    // Try to find WordPress root
    $wp_root = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
    if (file_exists($wp_root . "/wp-config.php")) {
        require_once $wp_root . "/wp-config.php";
    } else {
        die(
            "WordPress not found. Please ensure this file is in the correct plugin directory."
        );
    }
}

/**
 * Test the webhook endpoint
 */
function axytos_test_webhook()
{
    $action = $_GET["action"] ?? "info";

    switch ($action) {
        case "test":
            test_webhook_request();
            break;
        case "generate-key":
            generate_test_api_key();
            break;
        case "info":
        default:
            show_webhook_info();
            break;
    }
}

/**
 * Show webhook endpoint information
 */
function show_webhook_info()
{
    $webhook_url = rest_url("axytos/v1/order-update");
    $gateway_settings = get_option(
        "woocommerce_" . \AXYTOS_PAYMENT_ID . "_settings",
        []
    );
    $has_api_key = !empty($gateway_settings["webhook_api_key"]);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Axytos Webhook Test Utility</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .container { max-width: 800px; }
            .success { color: green; background: #f0fff0; padding: 10px; border: 1px solid green; }
            .error { color: red; background: #fff0f0; padding: 10px; border: 1px solid red; }
            .info { color: blue; background: #f0f0ff; padding: 10px; border: 1px solid blue; }
            code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
            pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
            .button { background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; display: inline-block; margin: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Axytos Webhook Test Utility</h1>

            <div class="info">
                <strong>Webhook Endpoint:</strong> <code><?php echo esc_html(
                    $webhook_url
                ); ?></code><br>
                <strong>Method:</strong> POST<br>
                <strong>Authentication:</strong> X-Axytos-Webhook-Key header<br>
                <strong>API Key Configured:</strong> <?php echo $has_api_key
                    ? "✅ Yes"
                    : "❌ No"; ?>
            </div>

            <h2>Test Actions</h2>
            <a href="?action=test" class="button">Test Webhook Request</a>
            <a href="?action=generate-key" class="button">Generate Test API Key</a>

            <h2>Example Payload</h2>
            <pre>{
    "order_id": 123,
    "curr_status": "processing",
    "new_status": "shipped",
    "invoice_number": "INV-2023-001",
    "tracking_number": "1Z999AA1234567890"
}</pre>

            <h2>cURL Example</h2>
            <pre>curl -X POST "<?php echo esc_html($webhook_url); ?>" \
  -H "Content-Type: application/json" \
  -H "X-Axytos-Webhook-Key: YOUR_WEBHOOK_API_KEY" \
  -d '{
    "order_id": 123,
    "curr_status": "processing",
    "new_status": "shipped",
    "invoice_number": "INV-2023-001"
  }'</pre>

            <h2>PHP Example</h2>
            <pre>$webhook_url = '<?php echo esc_html($webhook_url); ?>';
$api_key = 'YOUR_WEBHOOK_API_KEY';

$data = [
    'order_id' => 123,
    'curr_status' => 'processing',
    'new_status' => 'shipped',
    'invoice_number' => 'INV-2023-001'
];

$response = wp_remote_post($webhook_url, [
    'headers' => [
        'Content-Type' => 'application/json',
        'X-Axytos-Webhook-Key' => $api_key
    ],
    'body' => json_encode($data)
]);

$body = wp_remote_retrieve_body($response);
$status_code = wp_remote_retrieve_response_code($response);

echo "Status: {$status_code}\n";
echo "Response: {$body}\n";</pre>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Test webhook with a sample request
 */
function test_webhook_request()
{
    $gateway_settings = get_option(
        "woocommerce_" . \AXYTOS_PAYMENT_ID . "_settings",
        []
    );
    $webhook_api_key = "";

    // Get the webhook API key (decrypt if needed)
    if (!empty($gateway_settings["webhook_api_key"])) {
        $gateway = new AxytosPaymentGateway();
        $webhook_api_key = $gateway->get_option("webhook_api_key");
    }

    if (empty($webhook_api_key)) {
        echo '<div class="error">❌ Webhook API key is not configured. Please set it in WooCommerce > Settings > Payments > Axytos.</div>';
        echo '<a href="?action=info" class="button">Back to Info</a>';
        return;
    }

    // Find a test order (preferably one with Axytos payment method)
    $test_order = null;
    $orders = wc_get_orders([
        "limit" => 10,
        "payment_method" => \AXYTOS_PAYMENT_ID,
        "status" => ["processing", "on-hold"],
    ]);

    if (!empty($orders)) {
        $test_order = $orders[0];
    } else {
        // Fallback to any order
        $orders = wc_get_orders(["limit" => 1]);
        if (!empty($orders)) {
            $test_order = $orders[0];
        }
    }

    if (!$test_order) {
        echo '<div class="error">❌ No orders found for testing. Create at least one order first.</div>';
        echo '<a href="?action=info" class="button">Back to Info</a>';
        return;
    }

    // Prepare test data
    $webhook_url = rest_url("axytos/v1/order-update");
    $test_data = [
        "order_id" => $test_order->get_id(),
        "curr_status" => $test_order->get_status(),
        "new_status" => "shipped",
        "invoice_number" => "TEST-INV-" . time(),
        "tracking_number" => "TEST-TRACK-" . time(),
    ];

    // Make the webhook request
    $response = wp_remote_post($webhook_url, [
        "headers" => [
            "Content-Type" => "application/json",
            "X-Axytos-Webhook-Key" => $webhook_api_key,
        ],
        "body" => json_encode($test_data),
        "timeout" => 30,
    ]);

    $status_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $is_success = $status_code >= 200 && $status_code < 300;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Webhook Test Results</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .container { max-width: 800px; }
            .success { color: green; background: #f0fff0; padding: 10px; border: 1px solid green; }
            .error { color: red; background: #fff0f0; padding: 10px; border: 1px solid red; }
            pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
            .button { background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; display: inline-block; margin: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Webhook Test Results</h1>

            <div class="<?php echo $is_success ? "success" : "error"; ?>">
                <?php echo $is_success
                    ? "✅ Test Successful"
                    : "❌ Test Failed"; ?>
                <br><strong>Status Code:</strong> <?php echo esc_html(
                    $status_code
                ); ?>
            </div>

            <h2>Request Data</h2>
            <pre><?php echo esc_html(
                json_encode($test_data, JSON_PRETTY_PRINT)
            ); ?></pre>

            <h2>Response</h2>
            <pre><?php echo esc_html($response_body); ?></pre>

            <?php if (is_wp_error($response)): ?>
            <h2>Error Details</h2>
            <div class="error">
                <strong>Error:</strong> <?php echo esc_html(
                    $response->get_error_message()
                ); ?>
            </div>
            <?php endif; ?>

            <a href="?action=info" class="button">Back to Info</a>
            <a href="?action=test" class="button">Run Test Again</a>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Generate a test API key
 */
function generate_test_api_key()
{
    $api_key = wp_generate_password(64, true, true); ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Generated Test API Key</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .container { max-width: 800px; }
            .success { color: green; background: #f0fff0; padding: 10px; border: 1px solid green; }
            code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; font-size: 14px; word-break: break-all; }
            .button { background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; display: inline-block; margin: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Generated Test API Key</h1>

            <div class="success">
                ✅ Secure API key generated successfully!
            </div>

            <h2>Your API Key</h2>
            <p><code><?php echo esc_html($api_key); ?></code></p>

            <p><strong>Note:</strong> Save this key in your WooCommerce settings at:<br>
            WooCommerce > Settings > Payments > Axytos > Webhook API Key</p>

            <a href="?action=info" class="button">Back to Info</a>
            <a href="<?php echo admin_url(
                "admin.php?page=wc-settings&tab=checkout&section=axytoswc"
            ); ?>" class="button">Go to Settings</a>
        </div>
    </body>
    </html>
    <?php
}

// Run the test utility
axytos_test_webhook();
