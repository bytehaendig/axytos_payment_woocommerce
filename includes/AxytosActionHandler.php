<?php

namespace Axytos\WooCommerce;

if (!defined("ABSPATH")) {
    exit();
}

require_once __DIR__ . "/AxytosPaymentGateway.php";
require_once __DIR__ . "/axytos-data.php";

/**
 * Handles pending Axytos actions with retry logic for robustness
 */
class AxytosActionHandler
{
    const META_KEY_PENDING = "_axytos_pending";
    const META_KEY_DONE = "_axytos_done";
    const META_KEY_INVOICE_NUMBER = "axytos_ext_invoice_nr";
    const META_KEY_TRACKING_NUMBER = "axytos_ext_tracking_nr";
    const RETRY_INTERVAL = 60 * 10; // Default 10 min
    const MAX_RETRIES = 3;

    private $gateway;
    private $logger;

    public function __construct()
    {
        $this->gateway = new AxytosPaymentGateway();
        $this->logger = wc_get_logger();
        $this->registerCustomOrderStatus();
    }

    /**
     * Add a pending action to the order
     */
    public function addPendingAction($order_id, $action, $additional_data = [])
    {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== \AXYTOS_PAYMENT_ID) {
            return false;
        }

        $pending_actions = $this->getPendingActions($order);

        // Check if this action is already pending
        foreach ($pending_actions as $pending_action) {
            if ($pending_action["action"] === $action) {
                return true; // Action already pending
            }
        }

        $new_action = [
            "action" => $action,
            "created_at" => gmdate("c"),
            "failed_at" => null,
            "failed_count" => 0,
            "data" => $additional_data,
        ];

        $pending_actions[] = $new_action;
        $order->update_meta_data(self::META_KEY_PENDING, $pending_actions);
        // TODO: is save_meta_data needed?
        $order->save_meta_data();

        $this->log(
            "Added pending action '$action' for order #$order_id",
            "info"
        );

        // Immediately process pending actions for this order
        $this->processPendingActionsForOrder($order_id);

        return true;
    }

    /**
     * Get pending actions for an order
     */
    public function getPendingActions($order)
    {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order) {
            return [];
        }

        $pending_actions = $order->get_meta(self::META_KEY_PENDING);
        return is_array($pending_actions) ? $pending_actions : [];
    }

    /**
     * Get completed actions for an order
     */
    public function getDoneActions($order)
    {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order) {
            return [];
        }

        $done_actions = $order->get_meta(self::META_KEY_DONE);
        return is_array($done_actions) ? $done_actions : [];
    }

    /**
     * Move a successful action to the done actions list
     */
    private function moveActionToDone($order, $action_data)
    {
        $done_actions = $this->getDoneActions($order);
        
        // Add processed_at timestamp
        $action_data['processed_at'] = gmdate('c');
        
        $done_actions[] = $action_data;
        
        $order->update_meta_data(self::META_KEY_DONE, $done_actions);
        // Note: save_meta_data() will be called by the caller
    }

    /**
     * Get all actions (pending + done) for an order
     */
    public function getAllActions($order)
    {
        return [
            'pending' => $this->getPendingActions($order),
            'done' => $this->getDoneActions($order)
        ];
    }

    /**
     * Process all pending actions for an order
     */
    public function processPendingActionsForOrder($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== \AXYTOS_PAYMENT_ID) {
            return false;
        }

        $pending_actions = $this->getPendingActions($order);
        if (empty($pending_actions)) {
            return true;
        }

        // Check if any action has exceeded retry limit - if so, skip this order
        foreach ($pending_actions as $action_data) {
            if (($action_data["failed_count"] ?? 0) >= self::MAX_RETRIES) {
                $this->log(
                    "Order #{$order_id} has actions that exceeded max retries, skipping processing",
                    "warning"
                );
                return false;
            }
        }

        $updated = false;
        foreach ($pending_actions as $index => $action_data) {
            // Check if this action failed and if retry interval has passed
            if (!empty($action_data["failed_at"])) {
                $retry_interval = self::RETRY_INTERVAL;
                $failed_time = strtotime($action_data["failed_at"]);
                $current_time = current_time("timestamp");

                if ($current_time - $failed_time < $retry_interval) {
                    // Not enough time has passed for retry, stop processing this order
                    break;
                }
            }

            $success = $this->processAction($order, $action_data);

            if ($success) {
                // Move successful action to done actions
                $this->moveActionToDone($order, $action_data);
                unset($pending_actions[$index]);
                $updated = true;

                $this->addOrderNote($order, $action_data);
                $this->log(
                    "Successfully processed action '{$action_data["action"]}' for order #$order_id",
                    "info"
                );
            } else {
                // Mark as failed
                $pending_actions[$index]["failed_at"] = gmdate("c");
                $pending_actions[$index]["failed_count"] =
                    ($action_data["failed_count"] ?? 0) + 1;
                $updated = true;

                $failed_count = $pending_actions[$index]["failed_count"];
                $this->log(
                    "Failed to process action '{$action_data["action"]}' for order #$order_id (attempt #{$failed_count})",
                    "error"
                );

                // Check if max retries reached
                if ($failed_count >= self::MAX_RETRIES) {
                    $this->handleMaxRetriesExceeded($order, $action_data);
                    // Don't process any more actions for this order
                    break;
                }

                // Stop processing further actions for this order
                break;
            }
        }

        if ($updated) {
            // Re-index array to maintain sequential indices
            $pending_actions = array_values($pending_actions);

            if (empty($pending_actions)) {
                $order->delete_meta_data(self::META_KEY_PENDING);
            } else {
                $order->update_meta_data(
                    self::META_KEY_PENDING,
                    $pending_actions
                );
            }
            $order->save_meta_data();
        }

        return true;
    }

    /**
     * Process a single action
     */
    private function processAction($order, $action_data)
    {
        try {
            switch ($action_data["action"]) {
                case "confirm":
                    return $this->processConfirmAction($order, $action_data);

                case "complete":
                    return $this->processCompleteAction($order, $action_data);

                case "cancel":
                    return $this->processCancelAction($order, $action_data);

                case "refund":
                    return $this->processRefundAction($order, $action_data);

                default:
                    $this->log(
                        "Unknown action type: {$action_data["action"]}",
                        "error"
                    );
                    return false;
            }
        } catch (\Exception $e) {
            $this->log(
                "Exception processing action '{$action_data["action"]}' for order #{$order->get_id()}: {$e->getMessage()}",
                "error"
            );
            return false;
        }
    }

    /**
     * Process confirm action
     */
    private function processConfirmAction($order, $action_data)
    {
        return $this->gateway->confirmOrder($order);
    }

    /**
     * Process complete action
     */
    private function processCompleteAction($order, $action_data)
    {
        // TODO: split into two actions one for shipped and invoiced
        // Report shipping first
        $shipping_success = $this->gateway->reportShipping($order);
        if (!$shipping_success) {
            return false;
        }

        // Get invoice number from meta-data or action data
        $invoice_number = $order->get_meta(self::META_KEY_INVOICE_NUMBER);
        if (
            empty($invoice_number) &&
            !empty($action_data["data"]["invoice_number"])
        ) {
            $invoice_number = $action_data["data"]["invoice_number"];
        }

        // Create invoice (this can succeed even if invoice number is empty)
        $invoice_success = $this->gateway->createInvoice(
            $order,
            $invoice_number
        );

        // Consider shipping successful even if invoice creation fails
        // Invoice creation failure is logged in the gateway method
        return true;
    }

    /**
     * Process cancel action
     */
    private function processCancelAction($order, $action_data)
    {
        return $this->gateway->cancelOrder($order);
    }

    /**
     * Process refund action
     */
    private function processRefundAction($order, $action_data)
    {
        return $this->gateway->refundOrder($order);
    }

    /**
     * Add order note for successful action
     */
    private function addOrderNote($order, $action_data)
    {
        // TODO: include invoice number in note even if it's not within $action_data
        $action = $action_data["action"];
        $created_at = $action_data["created_at"];

        $note = sprintf(
            __(
                'Axytos action "%s" processed successfully (queued at %s)',
                "axytos-wc"
            ),
            $action,
            $created_at
        );

        // Add additional data to note if available
        if (!empty($action_data["data"])) {
            $additional_info = [];
            foreach ($action_data["data"] as $key => $value) {
                if (!empty($value)) {
                    $additional_info[] = "$key: $value";
                }
            }
            if (!empty($additional_info)) {
                $note .= " (" . implode(", ", $additional_info) . ")";
            }
        }

        $order->add_order_note($note);
    }

    /**
     * Store invoice number for later use in shipping action
     */
    public function setInvoiceNumber($order_id, $invoice_number)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $order->update_meta_data(
            self::META_KEY_INVOICE_NUMBER,
            $invoice_number
        );
        $order->save_meta_data();
        return true;
    }

    /**
     * Store tracking number for later use
     */
    public function setTrackingNumber($order_id, $tracking_number)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $order->update_meta_data(
            self::META_KEY_TRACKING_NUMBER,
            $tracking_number
        );
        $order->save_meta_data();
        return true;
    }

    /**
     * Get all orders with pending actions
     */
    public function getOrdersWithPendingActions($limit = 50)
    {
        $orders = wc_get_orders([
            "limit" => $limit,
            "meta_key" => self::META_KEY_PENDING,
            "meta_compare" => "EXISTS",
            "payment_method" => \AXYTOS_PAYMENT_ID,
            "return" => "ids",
        ]);

        return $orders;
    }

    /**
     * Process pending actions for all orders
     */
    // TODO: maybe limit per cron run
    public function processAllPendingActions()
    {
        $processed_count = 0;
        $failed_count = 0;
        $offset = 0;
        $batch_size = 50;

        do {
            // Get orders in batches to avoid memory issues with large datasets
            $order_ids = wc_get_orders([
                "limit" => $batch_size,
                "offset" => $offset,
                "meta_key" => self::META_KEY_PENDING,
                "meta_compare" => "EXISTS",
                "payment_method" => \AXYTOS_PAYMENT_ID,
                "return" => "ids",
            ]);

            foreach ($order_ids as $order_id) {
                try {
                    // Skip orders that have actions exceeding retry limits
                    $order = wc_get_order($order_id);
                    if ($order && $this->hasActionsExceedingRetryLimit($order)) {
                        continue;
                    }
                    
                    $success = $this->processPendingActionsForOrder($order_id);
                    if ($success) {
                        $processed_count++;
                    } else {
                        $failed_count++;
                    }
                } catch (\Exception $e) {
                    $failed_count++;
                    $this->log(
                        "Exception during processing of pending actions for order #$order_id: {$e->getMessage()}",
                        "error"
                    );
                }
            }

            $offset += $batch_size;
        } while (count($order_ids) === $batch_size);

        if ($processed_count > 0 || $failed_count > 0) {
            $this->log(
                "Processed pending actions for $processed_count orders, $failed_count failed",
                "info"
            );
        }

        return ["processed" => $processed_count, "failed" => $failed_count];
    }

    /**
     * Clean up old failed actions (optional maintenance)
     */
    // TODO: only retry for X times
    public function cleanupOldFailedActions($days_old = 30)
    {
        $cutoff_date = date("c", strtotime("-$days_old days"));
        $order_ids = $this->getOrdersWithPendingActions(200);
        $cleaned_count = 0;

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            $pending_actions = $this->getPendingActions($order);
            $original_count = count($pending_actions);

            // Remove old failed actions
            $pending_actions = array_filter($pending_actions, function (
                $action
            ) use ($cutoff_date) {
                return empty($action["failed_at"]) ||
                    $action["failed_at"] > $cutoff_date;
            });

            if (count($pending_actions) < $original_count) {
                $pending_actions = array_values($pending_actions);
                $order->update_meta_data(
                    self::META_KEY_PENDING,
                    $pending_actions
                );
                $order->save_meta_data();
                $cleaned_count++;
            }
        }

        if ($cleaned_count > 0) {
            $this->log(
                "Cleaned up old failed actions from $cleaned_count orders",
                "info"
            );
        }

        return $cleaned_count;
    }

    /**
     * Handle when an action exceeds maximum retry limit
     */
    private function handleMaxRetriesExceeded($order, $action_data)
    {
        $order_id = $order->get_id();
        $action = $action_data["action"];
        
        // Change order status to axytos_error
        $order->update_status('axytos-error', sprintf(
            __('Axytos action "%s" failed after %d retries', 'axytos-wc'),
            $action,
            self::MAX_RETRIES
        ));

        // Log the error
        $this->log(
            "Action '{$action}' for order #{$order_id} exceeded max retries (" . self::MAX_RETRIES . "), setting order to error status",
            "critical"
        );

        // Notify shop admin
        $this->notifyShopAdmin($order, $action_data);

        // Add order note
        $order->add_order_note(sprintf(
            __('Axytos action "%s" failed permanently after %d retries. Order requires manual attention.', 'axytos-wc'),
            $action,
            self::MAX_RETRIES
        ));
    }

    /**
     * Send notification email to shop admin
     */
    private function notifyShopAdmin($order, $action_data)
    {
        $admin_email = get_option('admin_email');
        $order_id = $order->get_id();
        $action = $action_data["action"];
        
        $subject = sprintf(
            __('[%s] Axytos Payment Action Failed - Order #%s', 'axytos-wc'),
            get_bloginfo('name'),
            $order_id
        );
        
        $message = sprintf(
            __("An Axytos payment action has failed permanently and requires manual attention.\n\nOrder: #%s\nAction: %s\nMax retries reached: %d\n\nPlease check the order in your WooCommerce admin and contact Axytos support if needed.\n\nOrder URL: %s", 'axytos-wc'),
            $order_id,
            $action,
            self::MAX_RETRIES,
            admin_url("post.php?post={$order_id}&action=edit")
        );
        
        wp_mail($admin_email, $subject, $message);
        
        $this->log(
            "Admin notification sent for failed action '{$action}' on order #{$order_id}",
            "info"
        );
    }

    /**
     * Check if order has actions that have exceeded max retry count
     */
    private function hasActionsExceedingRetryLimit($order)
    {
        $pending_actions = $this->getPendingActions($order);
        foreach ($pending_actions as $action_data) {
            if (($action_data["failed_count"] ?? 0) >= self::MAX_RETRIES) {
                return true;
            }
        }
        return false;
    }

    /**
     * Register custom order status
     */
    private function registerCustomOrderStatus()
    {
        add_action('init', function() {
            register_post_status('wc-axytos-error', array(
                'label' => __('Axytos Error', 'axytos-wc'),
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop('Axytos Error <span class="count">(%s)</span>', 'Axytos Error <span class="count">(%s)</span>', 'axytos-wc')
            ));
        });

        add_filter('wc_order_statuses', function($order_statuses) {
            $order_statuses['wc-axytos-error'] = __('Axytos Error', 'axytos-wc');
            return $order_statuses;
        });
    }

    /**
     * Log message with context
     */
    private function log($message, $level = "info")
    {
        $this->logger->log($level, $message, [
            "source" => "axytos-action-handler",
        ]);
    }
}
