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
                "Are you sure you want to %s this order?",
                "axytos-wc"
            ),
            "confirm_action_with_invoice" => __(
                "Are you sure you want to %s this order with invoice number: %s?",
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

    if ($pending_count > 0) {
        echo '<p style="margin: 5px 0;"><strong>' .
            sprintf(__("Pending: %d", "axytos-wc"), $pending_count) .
            "</strong></p>";
    }

    if ($failed_count > 0) {
        echo '<p style="margin: 5px 0; color: #d63638;"><strong>' .
            sprintf(__("Failed: %d", "axytos-wc"), $failed_count) .
            "</strong></p>";
    }

    echo '<div style="margin-top: 10px;">';
    foreach ($pending_actions as $action) {
        $status_color = empty($action["failed_at"]) ? "#00a32a" : "#d63638";
        $status_text = empty($action["failed_at"])
            ? __("pending", "axytos-wc")
            : sprintf(__("failed (%dx)", "axytos-wc"), $action["failed_count"]);

        echo '<div style="margin: 3px 0; font-size: 12px;">';
        echo '<span style="font-weight: bold;">' .
            esc_html($action["action"]) .
            "</span> ";
        echo '<span style="color: ' .
            $status_color .
            ';">(' .
            $status_text .
            ")</span> ";
        echo '<span style="color: #666;">' .
            esc_html($action["created_at"]) .
            "</span>";
        echo "</div>";
    }
    echo "</div>";

    echo "</div>";
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
    if (!$screen || strpos($screen->id, "wc-settings") === false) {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Add generate button for webhook API key
        if ($('#woocommerce_axytoswc_webhook_api_key').length) {
            var $webhookKeyField = $('#woocommerce_axytoswc_webhook_api_key');
            var $generateBtn = $('<button type="button" class="button button-secondary" style="margin-left: 10px;"><?php echo esc_js(
                __("Generate Secure Key", "axytos-wc")
            ); ?></button>');

            $webhookKeyField.after($generateBtn);

            $generateBtn.on('click', function(e) {
                e.preventDefault();

                // Generate a secure random key
                var key = generateSecureKey(64);
                $webhookKeyField.val(key);

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

            // Add webhook endpoint info
            var webhookUrl = '<?php echo esc_js(
                rest_url("axytos/v1/order-update")
            ); ?>';
            var $infoHtml = $(
                '<div class="axytos-webhook-info" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #00a0d2;">' +
                '<strong><?php echo esc_js(
                    __("Webhook Endpoint Information:", "axytos-wc")
                ); ?></strong><br>' +
                '<strong><?php echo esc_js(
                    __("URL:", "axytos-wc")
                ); ?></strong> <code>' + webhookUrl + '</code><br>' +
                '<strong><?php echo esc_js(
                    __("Method:", "axytos-wc")
                ); ?></strong> POST<br>' +
                '<strong><?php echo esc_js(
                    __("Authentication:", "axytos-wc")
                ); ?></strong> <?php echo esc_js(
    __("Send API key in X-Axytos-Webhook-Key header", "axytos-wc")
); ?><br>' +
                '<strong><?php echo esc_js(
                    __("Content-Type:", "axytos-wc")
                ); ?></strong> application/json' +
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
    add_action("admin_menu", __NAMESPACE__ . "\add_pending_actions_menu");
}

/**
 * Add pending actions management menu
 */
function add_pending_actions_menu()
{
    add_submenu_page(
        "woocommerce",
        __("Axytos Pending Actions", "axytos-wc"),
        __("Axytos Pending Actions", "axytos-wc"),
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
        $result = $action_handler->processAllPendingActions();
        echo '<div class="notice notice-success"><p>' .
            sprintf(
                __("Processed %d orders, %d failed.", "axytos-wc"),
                $result["processed"],
                $result["failed"]
            ) .
            "</p></div>";
    }

    // Get orders with pending actions
    $order_ids = $action_handler->getOrdersWithPendingActions(100);
    $next_scheduled = AxytosScheduler::get_next_scheduled_times();
    ?>
    <div class="wrap">
        <h1><?php echo __("Axytos Pending Actions", "axytos-wc"); ?></h1>

        <div class="card">
            <h2><?php echo __("Cron Status", "axytos-wc"); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php echo __(
                        "Next Processing Run",
                        "axytos-wc"
                    ); ?></th>
                    <td>
                        <?php if ($next_scheduled["process_pending"]) {
                            echo date(
                                "Y-m-d H:i:s",
                                $next_scheduled["process_pending"]
                            );
                        } else {
                            echo __("Not scheduled", "axytos-wc");
                        } ?>
                    </td>
                </tr>
                <tr>
                    <th><?php echo __("Next Cleanup Run", "axytos-wc"); ?></th>
                    <td>
                        <?php if ($next_scheduled["cleanup_old"]) {
                            echo date(
                                "Y-m-d H:i:s",
                                $next_scheduled["cleanup_old"]
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
                                "Failed Actions",
                                "axytos-wc"
                            ); ?></th>
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
                            $failed_count = 0;
                            $pending_list = [];
                            $failed_list = [];

                            foreach ($pending_actions as $action) {
                                if (empty($action["failed_at"])) {
                                    $pending_count++;
                                    $pending_list[] =
                                        $action["action"] .
                                        " (" .
                                        $action["created_at"] .
                                        ")";
                                } else {
                                    $failed_count++;
                                    $failed_list[] =
                                        $action["action"] .
                                        " (failed " .
                                        $action["failed_count"] .
                                        "x)";
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
                                    <span class="count"><?php echo $pending_count; ?></span>
                                    <div style="font-size: 11px; color: #666;">
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
                                <?php if ($failed_count > 0): ?>
                                    <span class="count" style="color: #d63638;"><?php echo $failed_count; ?></span>
                                    <div style="font-size: 11px; color: #666;">
                                        <?php echo implode(
                                            "<br>",
                                            $failed_list
                                        ); ?>
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
    <?php
}
