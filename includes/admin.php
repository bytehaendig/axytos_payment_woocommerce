<?php

namespace Axytos\WooCommerce;

use Automattic\WooCommerce\Utilities\OrderUtil;

if (!defined("ABSPATH")) {
    exit();
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
            "invoice_prompt" => __(
                "Please enter the invoice number:",
                "axytos-wc"
            ),
            "invoice_required" => __(
                "Invoice number is required for shipping report.",
                "axytos-wc"
            ),
            "confirm_action" => __(
                /* translators: %s: action name (e.g., 'report', 'cancel') */
                "Are you sure you want to %s this order?",
                "axytos-wc"
            ),
            "confirm_action_with_invoice" => __(
                /* translators: 1: action name (e.g., 'report', 'cancel'), 2: invoice number */
                "Are you sure you want to %1\$s this order with invoice number: %2\$s?",
                "axytos-wc"
            ),
            "unexpected_error" => __(
                "An unexpected error occurred. Please try again.",
                "axytos-wc"
            ),
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

    if ($order->get_payment_method() !== \AXYTOS_PAYMENT_ID) {
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

    if ($order->get_payment_method() !== \AXYTOS_PAYMENT_ID) {
        echo "<p>" .
            __("No Axytos actions available for this order.", "axytos-wc") .
            "</p>";
        return;
    }

    render_pending_actions_status($order);
    render_done_actions_status($order);
    render_action_buttons($order);
}

/**
 * Render pending actions status
 */
function render_pending_actions_status($order)
{
    require_once plugin_dir_path(__FILE__) . "AxytosActionHandler.php";

    $action_handler = new AxytosActionHandler();
    $pending_actions = $action_handler->getPendingActions($order);

    if (empty($pending_actions)) {
        echo '<div class="axytos-pending-status" style="margin-bottom: 15px;">';
        echo '<span style="color: #00a32a;">✓ ' .
            __("No pending actions", "axytos-wc") .
            "</span>";
        echo "</div>";
        return;
    }

    $pending_count = 0;
    $failed_count = 0;

    foreach ($pending_actions as $action) {
        if (empty($action["failed_at"])) {
            $pending_count++;
        } else {
            $failed_count++;
        }
    }

    echo '<div class="axytos-pending-status" style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-left: 4px solid #00a0d2;">';
    echo '<h4 style="margin: 0 0 10px 0;">' .
        __("Pending Axytos Actions", "axytos-wc") .
        "</h4>";

    echo '<div style="margin-top: 10px;">';
    foreach ($pending_actions as $action) {
        $status_text = format_action_status($action, $action_handler);

        if (empty($action["failed_at"])) {
            $status_color = "#00a32a";
        } else {
            // Check if action is broken (exceeded max retries)
            if ($action_handler->isBroken($action)) {
                $status_color = "#d63638"; // Red for broken actions
            } else {
                $status_color = "#ff8c00"; // Orange for retryable actions
            }
        }

        $failed_at = !empty($action["failed_at"]) ? $action["failed_at"] : $action["created_at"];
        $failed_time = format_action_time($failed_at);

        echo '<div style="margin: 3px 0; font-size: 12px; display: flex; justify-content: space-between; align-items: flex-start;">';
        echo '<div>';
        echo '<span style="font-weight: bold;">' .
            esc_html($action["action"]) .
            "</span> ";
        echo '<span style="color: #666;">' .
            esc_html($failed_time) .
            "</span>";
        echo '<br/><span style="color: ' .
            $status_color .
            ';">(' .
            $status_text .
            ")</span> ";


        echo '</div>';
        echo "</div>";
    }
    echo "</div>";

    echo "</div>";
}

/**
 * Render done actions status
 */
function render_done_actions_status($order)
{
    require_once plugin_dir_path(__FILE__) . "AxytosActionHandler.php";

    $action_handler = new AxytosActionHandler();
    $done_actions = $action_handler->getDoneActions($order);

    if (empty($done_actions)) {
        return;
    }

    echo '<div class="axytos-done-status" style="margin-bottom: 15px; padding: 10px; background: #f0f8ff; border-left: 4px solid #00a32a;">';
    echo '<h4 style="margin: 0 0 10px 0;">' .
        __("Completed Axytos Actions", "axytos-wc") .
        "</h4>";


    echo '<div style="margin-top: 10px;">';
    foreach ($done_actions as $action) {
        $processed_at = !empty($action["processed_at"]) ? $action["processed_at"] : $action["created_at"];
        $processed_time = format_action_time($processed_at);

        echo '<div style="margin: 3px 0; font-size: 12px;">';
        echo '<span style="font-weight: bold; color: #00a32a;">' .
            esc_html($action["action"]) .
            "</span> ";
        echo '<span style="color: #666;">' .
            esc_html($processed_time) .
            "</span>";

        // Show additional data if available
        if (!empty($action["data"])) {
            $additional_info = [];
            foreach ($action["data"] as $key => $value) {
                if (!empty($value)) {
                    $additional_info[] = "$key: $value";
                }
            }
            if (!empty($additional_info)) {
                echo '<br><span style="color: #666; font-size: 11px; margin-left: 10px;">' .
                    esc_html(implode(", ", $additional_info)) .
                    "</span>";
            }
        }
        echo "</div>";
    }
    echo "</div>";

    echo "</div>";
}

/**
 * Format action time for display
 */
function format_action_time($time_string)
{
    if (empty($time_string)) {
        return __("Unknown", "axytos-wc");
    }

    // Parse UTC timestamp and convert to local time for display
    try {
        $date = new \DateTime($time_string, new \DateTimeZone('UTC'));
        $date->setTimezone(wp_timezone());
        return $date->format(get_option("date_format") . " " . get_option("time_format"));
    } catch (Exception $e) {
        return esc_html($time_string);
    }
}

/**
 * Format action status display text
 */
function format_action_status($action, $action_handler)
{
    if (empty($action["failed_at"])) {
        return __("pending", "axytos-wc");
    }

    if ($action_handler->isBroken($action)) {
        return sprintf(
            /* translators: %d: number of failed attempts */
            __("failed %dx, broken", "axytos-wc"),
            $action["failed_count"]
        );
    } else {
        return sprintf(
            /* translators: %d: number of failed attempts */
            __("failed %dx, will retry", "axytos-wc"),
            $action["failed_count"]
        );
    }
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
            '" data-action="shipped">' .
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
    } elseif ($order_status === "cancelled") {
        echo '<div class="axytos-action-buttons-wrapper">';
        echo '<button class="button axytos-action-button" data-order-id="' .
            esc_attr($order_id) .
            '" data-action="reverse_cancel">' .
            __("Reverse Cancel", "axytos-wc") .
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
    if (!$screen || strpos($screen->id, "wc-settings") === false) {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Add generate button for webhook API key
        if ($('#woocommerce_axytoswc_webhook_api_key').length) {
            var $webhookKeyField = $('#woocommerce_axytoswc_webhook_api_key');
            var $generateBtn = $('<button type="button" class="button button-primary" style="margin-left: 10px;"><?php echo esc_js(
                __("Generate Secure Key", "axytos-wc")
            ); ?></button>');
            
            var $infoBtn = $('<button type="button" class="button axytos-info-btn" style="margin-left: 10px; background: #6c757d; border-color: #6c757d; color: white;"><?php echo esc_js(
                __("Webhook Information", "axytos-wc")
            ); ?></button>');

            $webhookKeyField.after($infoBtn).after($generateBtn);

            $generateBtn.on('click', function(e) {
                e.preventDefault();

                // Generate a secure random key
                var key = generateSecureKey(64);
                $webhookKeyField.val(key);

                // Trigger events to enable the submit button
                $webhookKeyField.trigger('input').trigger('change').trigger('keyup');

                // Show confirmation
                var $notice = $('<div class="notice notice-success inline" style="margin: 10px 0;"><p><?php echo esc_js(
                    __(
                        "Secure webhook API key generated. Make sure to save your settings.",
                        "axytos-wc"
                    )
                ); ?></p></div>');
                $webhookKeyField.closest('tr').after($('<tr><td colspan="2"></td></tr>').find('td').append($notice).end());

                // Remove notice after 5 seconds
                setTimeout(function() {
                    $notice.fadeOut(function() {
                        $(this).closest('tr').remove();
                    });
                }, 5000);
            });

            // Add webhook information modal functionality
            $infoBtn.on('click', function(e) {
                e.preventDefault();
                openWebhookInfoModal();
            });

            // Create and show webhook information modal
            function openWebhookInfoModal() {
                var webhookUrl = '<?php echo esc_js(
                    rest_url("axytos/v1/order-update")
                ); ?>';
                
                var modalHtml =
                    '<div id="axytos-webhook-modal" class="axytos-modal">' +
                        '<div class="axytos-modal-content">' +
                            '<div class="axytos-modal-header">' +
                                '<h2><?php echo esc_js(
                                    __("Webhook Endpoint Information", "axytos-wc")
                                ); ?></h2>' +
                                '<span class="axytos-modal-close">&times;</span>' +
                            '</div>' +
                            '<div class="axytos-modal-body">' +
                                '<div class="axytos-webhook-info-content">' +
                                    '<div class="axytos-webhook-description">' +
                                        '<p><?php echo esc_js(
                                            __("This webhook enables seamless integration between external systems (such as ERP software) and the Axytos plugin. It allows your systems to automatically notify the plugin about order status changes and provide critical information like invoice numbers.", "axytos-wc")
                                        ); ?></p>' +
                                        '<p><?php echo esc_js(
                                            __("Invoice numbers are particularly important for Axytos payment processing, making this webhook an essential tool for automating data flow between your ERP system, WooCommerce, and the Axytos payment provider.", "axytos-wc")
                                        ); ?></p>' +
                                    '</div>' +
                                    '<h3><?php echo esc_js(
                                        __("General Information", "axytos-wc")
                                    ); ?></h3>' +
                                    '<div class="axytos-info-section">' +
                                        '<p><strong><?php echo esc_js(
                                            __("URL:", "axytos-wc")
                                        ); ?></strong></p>' +
                                        '<p><code class="axytos-webhook-url">' + webhookUrl + '</code></p>' +
                                        '<p><strong><?php echo esc_js(
                                            __("Method:", "axytos-wc")
                                        ); ?></strong> POST</p>' +
                                        '<p><strong><?php echo esc_js(
                                            __("Headers:", "axytos-wc")
                                        ); ?></strong></p>' +
                                        '<ul class="axytos-header-list">' +
                                            '<li><code>X-Axytos-Webhook-Key: &lt;<?php echo esc_js(
                                                __("your webhook key", "axytos-wc")
                                            ); ?>&gt;</code></li>' +
                                            '<li><code>Content-Type: application/json</code></li>' +
                                        '</ul>' +
                                        '<p><strong><?php echo esc_js(
                                            __("Body:", "axytos-wc")
                                        ); ?></strong></p>' +
                                        '<p><?php echo esc_js(
                                            __("JSON payload containing order status updates with fields:", "axytos-wc")
                                        ); ?></p>' +
                                        '<ul class="axytos-body-fields">' +
                                            '<li><code>order_id</code> - <?php echo esc_js(
                                                __("The WooCommerce order ID (required)", "axytos-wc")
                                            ); ?></li>' +
                                            '<li><code>new_status</code> - <?php echo esc_js(
                                                __("The new order status (required)", "axytos-wc")
                                            ); ?></li>' +
                                            '<li><code>curr_status</code> - <?php echo esc_js(
                                                __("The current order status (optional)", "axytos-wc")
                                            ); ?></li>' +
                                            '<li><code>invoice_number</code> - <?php echo esc_js(
                                                __("Invoice number (optional)", "axytos-wc")
                                            ); ?></li>' +
                                            '<li><code>tracking_number</code> - <?php echo esc_js(
                                                __("Tracking number (optional)", "axytos-wc")
                                            ); ?></li>' +
                                        '</ul>' +
                                    '</div>' +
                                    '<h3><?php echo esc_js(
                                        __("Example", "axytos-wc")
                                    ); ?></h3>' +
                                    '<div class="axytos-info-section">' +
                                        '<h4><?php echo esc_js(
                                            __("Curl", "axytos-wc")
                                        ); ?></h4>' +
                                        '<pre class="axytos-code-example"><code>' +
                                        'curl -X POST "' + webhookUrl + '" \\\n' +
                                        '  -H "X-Axytos-Webhook-Key: your_webhook_key_here" \\\n' +
                                        '  -H "Content-Type: application/json" \\\n' +
                                        '  -d \'{\n' +
                                        '    "order_id": 12345,\n' +
                                        '    "new_status": "shipped",\n' +
                                        '    "curr_status": "processing",    // optional\n' +
                                        '    "invoice_number": "INV-2024-001",    // optional\n' +
                                        '    "tracking_number": "1Z999AA1234567890"  // optional\n' +
                                        '  }\'' +
                                        '</code></pre>' +
                                        '<h4><?php echo esc_js(
                                            __("Python (requests)", "axytos-wc")
                                        ); ?></h4>' +
                                        '<pre class="axytos-code-example"><code>' +
                                        'import requests\n' +
                                        'import json\n\n' +
                                        'url = "' + webhookUrl + '"\n' +
                                        'headers = {\n' +
                                        '    "X-Axytos-Webhook-Key": "your_webhook_key_here",\n' +
                                        '    "Content-Type": "application/json"\n' +
                                        '}\n' +
                                        'data = {\n' +
                                        '    "order_id": 12345,  # required\n' +
                                        '    "new_status": "shipped",  # required\n' +
                                        '    "curr_status": "processing",  # optional\n' +
                                        '    "invoice_number": "INV-2024-001",  # optional\n' +
                                        '    "tracking_number": "1Z999AA1234567890"  # optional\n' +
                                        '}\n\n' +
                                        'response = requests.post(url, headers=headers, json=data)\n' +
                                        'print(f"Status Code: {response.status_code}")\n' +
                                        'print(f"Response: {response.text}")' +
                                        '</code></pre>' +
                                        '<h4><?php echo esc_js(
                                            __("PHP", "axytos-wc")
                                        ); ?></h4>' +
                                        '<pre class="axytos-code-example"><code>' +
                                        '&lt;?php\n' +
                                        '$url = "' + webhookUrl + '";\n' +
                                        '$data = [\n' +
                                        '    "order_id" => 12345,  // required\n' +
                                        '    "new_status" => "shipped",  // required\n' +
                                        '    "curr_status" => "processing",  // optional\n' +
                                        '    "invoice_number" => "INV-2024-001",  // optional\n' +
                                        '    "tracking_number" => "1Z999AA1234567890"  // optional\n' +
                                        '];\n\n' +
                                        '$ch = curl_init();\n' +
                                        'curl_setopt($ch, CURLOPT_URL, $url);\n' +
                                        'curl_setopt($ch, CURLOPT_POST, true);\n' +
                                        'curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));\n' +
                                        'curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);\n' +
                                        'curl_setopt($ch, CURLOPT_HTTPHEADER, [\n' +
                                        '    "X-Axytos-Webhook-Key: your_webhook_key_here",\n' +
                                        '    "Content-Type: application/json"\n' +
                                        ']);\n\n' +
                                        '$response = curl_exec($ch);\n' +
                                        '$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);\n' +
                                        '$error = curl_error($ch);\n' +
                                        'curl_close($ch);\n\n' +
                                        'if ($error) {\n' +
                                        '    echo "cURL Error: " . $error . "\\n";\n' +
                                        '} else {\n' +
                                        '    echo "Status Code: " . $http_code . "\\n";\n' +
                                        '    echo "Response: " . $response . "\\n";\n' +
                                        '}' +
                                        '</code></pre>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>';

                // Remove existing modal if any
                $('#axytos-webhook-modal').remove();
                
                // Add modal to body
                $('body').append(modalHtml);
                
                // Show modal
                $('#axytos-webhook-modal').show();
                
                // Close modal handlers
                $('.axytos-modal-close, #axytos-webhook-modal').on('click', function(e) {
                    if (e.target === this) {
                        $('#axytos-webhook-modal').hide().remove();
                    }
                });
                
                // Close on escape key
                $(document).on('keydown.axytos-modal', function(e) {
                    if (e.keyCode === 27) { // ESC key
                        $('#axytos-webhook-modal').hide().remove();
                        $(document).off('keydown.axytos-modal');
                    }
                });
            }
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
    /* Modal Styles */
    .axytos-modal {
        display: none;
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .axytos-modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 0;
        border: 1px solid #ccc;
        border-radius: 4px;
        width: 95%;
        max-width: 800px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .axytos-modal-header {
        padding: 20px;
        background-color: #f9f9f9;
        border-bottom: 1px solid #eee;
        border-radius: 4px 4px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .axytos-modal-header h2 {
        margin: 0;
        font-size: 18px;
        color: #333;
    }

    .axytos-modal-close {
        color: #666;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
    }

    .axytos-modal-close:hover,
    .axytos-modal-close:focus {
        color: #000;
    }

    .axytos-modal-body {
        padding: 20px;
    }

    .axytos-webhook-info-content p {
        margin: 10px 0;
    }

    .axytos-webhook-info-content strong {
        color: #333;
    }

    .axytos-webhook-description {
        background: #f0f8ff;
        padding: 15px;
        border-radius: 5px;
        border-left: 4px solid #0073aa;
        margin-bottom: 20px;
    }

    .axytos-webhook-description p {
        margin: 0;
        line-height: 1.6;
        color: #333;
    }

    .axytos-webhook-url {
        background: #f1f1f1;
        padding: 8px 12px;
        border-radius: 3px;
        font-family: monospace;
        word-break: break-all;
        display: block;
        margin: 5px 0;
        border: 1px solid #ddd;
    }

    .axytos-webhook-info-content h3 {
        color: #333;
        font-size: 16px;
        margin: 20px 0 10px 0;
        padding-bottom: 5px;
        border-bottom: 2px solid #0073aa;
    }

    .axytos-webhook-info-content h4 {
        color: #555;
        font-size: 14px;
        margin: 15px 0 8px 0;
    }

    .axytos-info-section {
        margin-bottom: 20px;
    }

    .axytos-header-list,
    .axytos-body-fields {
        margin: 10px 0;
        padding-left: 20px;
    }

    .axytos-header-list li,
    .axytos-body-fields li {
        margin: 5px 0;
        font-family: inherit;
    }

    .axytos-header-list code,
    .axytos-body-fields code {
        background: #f8f8f8;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: monospace;
        font-size: 13px;
        border: 1px solid #e1e1e1;
    }

    .axytos-code-example {
        background: #2d3748;
        color: #e2e8f0;
        padding: 15px;
        border-radius: 5px;
        overflow-x: auto;
        margin: 10px 0;
        border: 1px solid #4a5568;
    }

    .axytos-code-example code {
        font-family: 'Courier New', Consolas, monospace;
        font-size: 13px;
        line-height: 1.4;
        white-space: pre;
        color: inherit;
        background: none;
        padding: 0;
        border: none;
    }

    .axytos-modal-content {
        max-height: 90vh;
        overflow-y: auto;
    }

    /* Webhook info button hover effect */
    .axytos-info-btn:hover {
        background: #5a6268 !important;
        border-color: #5a6268 !important;
    }

    /* Responsive spacing for webhook buttons */
    @media (max-width: 782px) {
        #woocommerce_axytoswc_webhook_api_key + .button {
            margin-top: 0.5em;
            margin-left: 0 !important;
        }
        
        #woocommerce_axytoswc_webhook_api_key + .button + .button {
            margin-top: 0.5em;
            margin-left: 10px !important;
        }
    }
    </style>
    <?php
}

function bootstrap_admin()
{
    // Enqueue admin scripts and styles
    add_action(
        "admin_enqueue_scripts",
        __NAMESPACE__ . '\enqueue_admin_assets'
    );

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

    // Pending actions management
    add_action("admin_menu", __NAMESPACE__ . "\add_axytos_status_menu", 70);
}

/**
 * Get count of orders with broken actions
 */
function get_orders_with_broken_actions_count()
{
    require_once plugin_dir_path(__FILE__) . "AxytosActionHandler.php";

    $action_handler = new AxytosActionHandler();
    return $action_handler->getOrdersWithBrokenActionsCount();
}

/**
 * Add pending actions management menu
 */
function add_axytos_status_menu()
{
    // Get count of orders with broken actions for the red bubble
    $broken_count = get_orders_with_broken_actions_count();

    $menu_title = __("Axytos Status", "axytos-wc");
    if ($broken_count > 0) {
        $menu_title .= ' <span class="awaiting-mod">' . $broken_count . '</span>';
    }

    add_submenu_page(
        "woocommerce",
        __("Axytos Status", "axytos-wc"),
        $menu_title,
        "manage_woocommerce",
        "axytos-pending-actions",
        __NAMESPACE__ . '\render_pending_actions_page'
    );
}

/**
 * Render pending actions management page
 */
function render_pending_actions_page()
{
    require_once plugin_dir_path(__FILE__) . "AxytosActionHandler.php";
    require_once plugin_dir_path(__FILE__) . "cron.php";

    $action_handler = new AxytosActionHandler();

    // Handle manual processing trigger
    if (
        isset($_POST["manual_process"]) &&
        wp_verify_nonce($_POST["_wpnonce"], "axytos_manual_process")
    ) {
        $result = AxytosScheduler::process_pending_actions_with_logging();
        echo '<div class="notice notice-success"><p>' .
            sprintf(
                /* translators: 1: number of processed orders, 2: number of failed orders */
                __("Processed %1\$d orders, %2\$d failed.", "axytos-wc"),
                $result["processed"],
                $result["failed"]
            ) .
            "</p></div>";
    }

    // Handle retry broken actions form submission
    if (
        isset($_POST["retry_broken_actions"]) &&
        isset($_POST["order_id"]) &&
        wp_verify_nonce($_POST["_wpnonce"], "axytos_retry_broken_actions")
    ) {
        $order_id = intval($_POST["order_id"]);
        $order = wc_get_order($order_id);

        if ($order && $order->get_payment_method() === \AXYTOS_PAYMENT_ID) {
            try {
                $result = $action_handler->retryBrokenActionsForOrder($order_id);
                if ($result && is_array($result)) {
                    if ($result["processed"] > 0) {
                        echo '<div class="notice notice-success"><p>' .
                            sprintf(
                                /* translators: 1: number of processed actions, 2: order ID */
                                __("Successfully retried %1\$d broken actions for order #%2\$d.", "axytos-wc"),
                                $result["processed"],
                                $order_id
                            ) .
                            "</p></div>";
                    } else {
                        echo '<div class="notice notice-warning"><p>' .
                            sprintf(
                                /* translators: 1: number of failed actions, 2: order ID */
                                __("Retry attempt completed for order #%2\$d, but %1\$d actions still failed.", "axytos-wc"),
                                $result["failed"],
                                $order_id
                            ) .
                            "</p></div>";
                    }
                } else {
                    echo '<div class="notice notice-error"><p>' .
                        __("Failed to retry actions - invalid order or no broken actions found.", "axytos-wc") .
                        "</p></div>";
                }
            } catch (Exception $e) {
                echo '<div class="notice notice-error"><p>' .
                    sprintf(
                        /* translators: %s: error message */
                        __("Error retrying actions: %s", "axytos-wc"),
                        $e->getMessage()
                    ) .
                    "</p></div>";
            }
        } else {
            echo '<div class="notice notice-error"><p>' .
                __("Invalid order or not an Axytos order.", "axytos-wc") .
                "</p></div>";
        }
    }

    // Handle remove failed action form submission
    if (
        isset($_POST["remove_failed_action"]) &&
        isset($_POST["order_id"]) &&
        isset($_POST["action_name"]) &&
        wp_verify_nonce($_POST["_wpnonce"], "axytos_remove_failed_action")
    ) {
        $order_id = intval($_POST["order_id"]);
        $action_name = sanitize_text_field($_POST["action_name"]);
        $order = wc_get_order($order_id);

        if ($order && $order->get_payment_method() === \AXYTOS_PAYMENT_ID) {
            try {
                $result = $action_handler->removeFailedAction($order_id, $action_name);
                if ($result) {
                    echo '<div class="notice notice-success"><p>' .
                        sprintf(
                            /* translators: 1: action name, 2: order ID */
                            __("Successfully removed failed action '%1\$s' from order #%2\$d.", "axytos-wc"),
                            $action_name,
                            $order_id
                        ) .
                        "</p></div>";
                } else {
                    echo '<div class="notice notice-error"><p>' .
                        sprintf(
                            /* translators: 1: action name, 2: order ID */
                            __("Failed to remove action '%1\$s' from order #%2\$d. Action may not exist or is not broken.", "axytos-wc"),
                            $action_name,
                            $order_id
                        ) .
                        "</p></div>";
                }
            } catch (Exception $e) {
                echo '<div class="notice notice-error"><p>' .
                    sprintf(
                        /* translators: %s: error message */
                        __("Error removing action: %s", "axytos-wc"),
                        $e->getMessage()
                    ) .
                    "</p></div>";
            }
        } else {
            echo '<div class="notice notice-error"><p>' .
                __("Invalid order or not an Axytos order.", "axytos-wc") .
                "</p></div>";
        }
    }

    // Get orders with pending actions
    $order_ids = $action_handler->getOrdersWithPendingActions(100);
    $next_scheduled = AxytosScheduler::get_next_scheduled_times();
    $last_processing_run = AxytosScheduler::get_last_processing_run();
    $cron_health = AxytosScheduler::get_cron_health();
    ?>
    <div class="wrap">
        <h1><?php echo __("Axytos Pending Actions", "axytos-wc"); ?></h1>

        <div class="card">
            <h2><?php echo __("Cron Status", "axytos-wc"); ?></h2>
            
            <table class="form-table" style="margin-top: 0;">
                <tr>
                    <th style="width: 200px; padding: 8px 10px;"><?php echo __("Cron Status", "axytos-wc"); ?></th>
                    <td style="padding: 8px 10px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="color: <?php echo $cron_health['is_working'] ? '#46b450' : '#dc3232'; ?>; font-weight: 600;">
                                <?php echo $cron_health['is_working'] ? __('✓ Working', 'axytos-wc') : __('⚠ Issue Detected', 'axytos-wc'); ?>
                            </span>
                            <span style="padding: 2px 8px; background: #f0f0f1; border-radius: 12px; font-size: 0.85em; color: #666;">
                                <?php echo $cron_health['cron_type'] === 'external' ? __('External Cron', 'axytos-wc') : __('Traffic-Based', 'axytos-wc'); ?>
                            </span>
                        </div>
                        <?php if (!empty($cron_health['message'])): ?>
                            <div style="margin-top: 5px; font-size: 0.9em; color: #666;">
                                <?php echo esc_html($cron_health['message']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($cron_health['recommendation'])): ?>
                            <div style="margin-top: 5px; font-size: 0.85em; color: #666; font-style: italic;">
                                <?php echo esc_html($cron_health['recommendation']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th style="padding: 8px 10px;"><?php echo __("Last Processing Run", "axytos-wc"); ?></th>
                    <td style="padding: 8px 10px;">
                        <?php if ($last_processing_run) {
                            echo wp_date(
                                get_option("date_format") . " " . get_option("time_format"),
                                $last_processing_run
                            );
                        } else {
                            echo __("Never", "axytos-wc");
                        } ?>
                    </td>
                </tr>
                <tr>
                    <th style="padding: 8px 10px;"><?php echo __("Next Processing Run", "axytos-wc"); ?></th>
                    <td style="padding: 8px 10px;">
                        <?php if ($next_scheduled["process_pending"]) {
                            echo wp_date(
                                get_option("date_format") . " " . get_option("time_format"),
                                $next_scheduled["process_pending"]
                            );
                        } else {
                            echo __("Not scheduled", "axytos-wc");
                        } ?>
                    </td>
                </tr>
            </table>


            <form method="post">
                <?php wp_nonce_field("axytos_manual_process"); ?>
                <input type="submit" name="manual_process" class="button button-primary"
                       value="<?php echo __(
                           "Process All Pending Actions Now",
                           "axytos-wc"
                       ); ?>" />
            </form>
        </div>

        <div class="card">
            <h2><?php echo __(
                "Orders with Pending Actions",
                "axytos-wc"
            ); ?></h2>
            <?php if (empty($order_ids)): ?>
                <p><?php echo __(
                    "No orders with pending actions found.",
                    "axytos-wc"
                ); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo __("Order ID", "axytos-wc"); ?></th>
                            <th><?php echo __("Status", "axytos-wc"); ?></th>
                            <th><?php echo __(
                                "Pending Actions",
                                "axytos-wc"
                            ); ?></th>
                            <th><?php echo __(
                                "Retry Actions",
                                "axytos-wc"
                            ); ?></th>
                            <th><?php echo __(
                                "Broken Actions",
                                "axytos-wc"
                            ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_ids as $order_id):

                            $order = wc_get_order($order_id);
                            if (!$order) {
                                continue;
                            }

                            $pending_actions = $action_handler->getPendingActions(
                                $order
                            );
                            $pending_count = 0;
                            $retry_count = 0;
                            $broken_count = 0;
                            $pending_list = [];
                            $retry_list = [];
                            $broken_list = [];

                            foreach ($pending_actions as $action) {
                                if (empty($action["failed_at"])) {
                                    $pending_count++;
                                    $pending_list[] =
                                        $action["action"] .
                                        " (" .
                                        $action["created_at"] .
                                        ")";
                                } else {
                                    $status_text = format_action_status($action, $action_handler);
                                    // Check if action is broken (exceeded max retries)
                                    if ($action_handler->isBroken($action)) {
                                        $broken_count++;
                                        $broken_list[] = [
                                            'text' => $action["action"] . " (" . $status_text . ")",
                                            'action' => $action["action"],
                                            'failed_at' => $action["failed_at"],
                                            'failed_at_formatted' => format_action_time($action["failed_at"]),
                                            'failed_count' => $action["failed_count"],
                                            'fail_reason' => isset($action["fail_reason"]) ? $action["fail_reason"] : ''
                                        ];
                                    } else {
                                        $retry_count++;
                                        $retry_list[] = [
                                            'text' => $action["action"] . " (" . $status_text . ")",
                                            'action' => $action["action"],
                                            'failed_at' => $action["failed_at"],
                                            'failed_at_formatted' => format_action_time($action["failed_at"]),
                                            'failed_count' => $action["failed_count"],
                                            'fail_reason' => isset($action["fail_reason"]) ? $action["fail_reason"] : ''
                                        ];
                                    }
                                }
                            }
                            ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_post_link(
                                    $order_id
                                ); ?>">#<?php echo $order_id; ?></a>
                            </td>
                            <td><?php echo $order->get_status(); ?></td>
                            <td>
                                <?php if ($pending_count > 0): ?>
                                    <div style="font-size: 13px;">
                                        <?php echo implode(
                                            "<br>",
                                            $pending_list
                                        ); ?>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($retry_count > 0): ?>
                                    <div style="font-size: 13px; color: #ff8c00;">
                                        <?php foreach ($retry_list as $retry_item): ?>
                                            <div style="margin: 3px 0;">
                                                <?php echo esc_html($retry_item['text']); ?>
                                                <?php if (!empty($retry_item['fail_reason'])): ?>
                                                    <button type="button" class="button button-small axytos-error-details-btn"
                                                            data-order-id="<?php echo esc_attr($order_id); ?>"
                                                            data-errors="<?php echo esc_attr(json_encode([$retry_item])); ?>"
                                                            style="background: #6c757d; color: white; border-color: #6c757d; margin-left: 5px; font-size: 10px; padding: 2px 6px;">
                                                        <?php echo __("View Error", "axytos-wc"); ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($broken_count > 0): ?>
                                    <div style="font-size: 13px; color: #d63638;">
                                        <?php foreach ($broken_list as $broken_item): ?>
                                            <div style="margin: 3px 0;">
                                                <?php echo esc_html($broken_item['text']); ?>
                                                <?php if (!empty($broken_item['fail_reason'])): ?>
                                                    <button type="button" class="button button-small axytos-error-details-btn"
                                                            data-order-id="<?php echo esc_attr($order_id); ?>"
                                                            data-errors="<?php echo esc_attr(json_encode([$broken_item])); ?>"
                                                            style="background: #6c757d; color: white; border-color: #6c757d; margin-left: 5px; font-size: 10px; padding: 2px 6px;">
                                                        <?php echo __("View Error", "axytos-wc"); ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($broken_count > 0): ?>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <form method="post" style="margin: 0;">
                                            <?php wp_nonce_field("axytos_retry_broken_actions"); ?>
                                            <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>" />
                                            <input type="submit" name="retry_broken_actions" class="button button-small axytos-action-btn"
                                                style="font-size: 10px; padding: 2px 6px; color: #00a32a; border-color: #00a32a; width: 150px;"
                                                value="<?php echo esc_attr(__("Retry broken actions", "axytos-wc")); ?>"
                                                title="<?php echo esc_attr(__("Retry all broken actions for this order", "axytos-wc")); ?>"
                                                onclick="return confirm('<?php echo esc_js(__("Are you sure you want to retry all broken actions for this order? This will attempt to process them immediately.", "axytos-wc")); ?>');" />
                                        </form>
                                        <?php foreach ($pending_actions as $action): ?>
                                            <?php if ($action_handler->isBroken($action)): ?>
                                                <form method="post" style="margin: 0;">
                                                    <?php wp_nonce_field("axytos_remove_failed_action"); ?>
                                                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>" />
                                                    <input type="hidden" name="action_name" value="<?php echo esc_attr($action["action"]); ?>" />
                                                    <input type="submit" name="remove_failed_action" class="button button-small axytos-action-btn"
                                                        style="font-size: 10px; padding: 2px 6px; color: #d63638; border-color: #d63638; width: 150px;"
                                                        value="<?php echo esc_attr(__("Remove broken action", "axytos-wc")); ?>"
                                                        title="<?php echo esc_attr(__("Remove this permanently failed action", "axytos-wc")); ?>"
                                                        onclick="return confirm('<?php echo esc_js(sprintf(__("Are you sure you want to remove the failed '%s' action? You are responsible for communicating the necessary changes to Axytos to ensure that further processing of the order is guaranteed. This action cannot be undone.", "axytos-wc"), $action["action"])); ?>');" />
                                                </form>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                        endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Error Details Modal -->
    <div id="axytos-error-modal" class="axytos-modal" style="display: none;">
        <div class="axytos-modal-content">
            <div class="axytos-modal-header">
                <h2><?php echo __("Error Details", "axytos-wc"); ?></h2>
                <span class="axytos-modal-close">&times;</span>
            </div>
            <div class="axytos-modal-body">
                <div id="axytos-error-content">
                    <!-- Error content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Handle error details button clicks
        $('.axytos-error-details-btn').on('click', function(e) {
            e.preventDefault();
            
            var orderId = $(this).data('order-id');
            var errors = $(this).data('errors');
            
            if (!errors || errors.length === 0) {
                return;
            }
            
            // Build error content HTML
            var errorHtml = '<div class="axytos-error-list">';
            errorHtml += '<h3><?php echo esc_js(__("Order", "axytos-wc")); ?> #' + orderId + '</h3>';
            
            for (var i = 0; i < errors.length; i++) {
                var error = errors[i];
                
                // Use the pre-formatted date from PHP that matches WordPress format
                var formattedDate = error.failed_at_formatted || error.failed_at;
                
                errorHtml += '<div class="axytos-error-item">';
                errorHtml += '<div class="axytos-error-header">';
                errorHtml += '<h4>' + error.action + '</h4>';
                errorHtml += '<span class="axytos-error-meta">';
                errorHtml += '<?php echo esc_js(__("failed at", "axytos-wc")); ?> ' + formattedDate;
                errorHtml += '</span>';
                errorHtml += '</div>';
                errorHtml += '<div class="axytos-error-message">';
                errorHtml += '<strong><?php echo esc_js(__("Error:", "axytos-wc")); ?></strong><br>';
                errorHtml += '<code>' + $('<div>').text(error.fail_reason).html() + '</code>';
                errorHtml += '</div>';
                errorHtml += '</div>';
            }
            
            errorHtml += '</div>';
            
            // Populate modal content
            $('#axytos-error-content').html(errorHtml);
            
            // Show modal
            $('#axytos-error-modal').show();
        });
        
        // Close modal handlers
        $('.axytos-modal-close, #axytos-error-modal').on('click', function(e) {
            if (e.target === this) {
                $('#axytos-error-modal').hide();
            }
        });
        
        // Close on escape key
        $(document).on('keydown.axytos-error-modal', function(e) {
            if (e.keyCode === 27) { // ESC key
                $('#axytos-error-modal').hide();
                $(document).off('keydown.axytos-error-modal');
            }
        });
    });
    </script>

    <style>
    /* Error Modal Styles */
    .axytos-modal {
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .axytos-modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 0;
        border: 1px solid #ccc;
        border-radius: 4px;
        width: 95%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .axytos-modal-header {
        padding: 20px;
        background-color: #f9f9f9;
        border-bottom: 1px solid #eee;
        border-radius: 4px 4px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .axytos-modal-header h2 {
        margin: 0;
        font-size: 18px;
        color: #333;
    }

    .axytos-modal-close {
        color: #666;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
    }

    .axytos-modal-close:hover,
    .axytos-modal-close:focus {
        color: #000;
    }

    .axytos-modal-body {
        padding: 20px;
    }

    .axytos-error-list h3 {
        margin: 0 0 20px 0;
        color: #333;
        font-size: 16px;
        border-bottom: 2px solid #d63638;
        padding-bottom: 5px;
    }

    .axytos-error-item {
        margin-bottom: 20px;
        padding: 15px;
        background: #f9f9f9;
        border-left: 4px solid #d63638;
        border-radius: 0 4px 4px 0;
    }

    .axytos-error-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }

    .axytos-error-header h4 {
        margin: 0;
        color: #d63638;
        font-size: 14px;
        font-weight: bold;
    }

    .axytos-error-meta {
        font-size: 12px;
        color: #666;
        font-style: italic;
    }

    .axytos-error-message {
        margin-top: 10px;
    }

    .axytos-error-message strong {
        color: #333;
        font-size: 13px;
    }

    .axytos-error-message code {
        display: block;
        background: #fff;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 3px;
        font-family: 'Courier New', Consolas, monospace;
        font-size: 12px;
        line-height: 1.4;
        color: #d63638;
        margin-top: 5px;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    /* Responsive adjustments */
    @media (max-width: 782px) {
        .axytos-modal-content {
            margin: 2% auto;
            width: 98%;
        }
        
        .axytos-error-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .axytos-error-meta {
            margin-top: 5px;
        }
    }
    </style>
    <?php
}
