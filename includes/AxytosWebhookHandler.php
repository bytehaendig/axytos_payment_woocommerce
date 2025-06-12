<?php

namespace Axytos\WooCommerce;

use WP_Error;
use WP_REST_Response;

if (!defined("ABSPATH")) {
    exit();
}

require_once __DIR__ . "/AxytosEncryptionService.php";

/**
 * Axytos Webhook Handler
 *
 * Handles incoming webhook requests from ERP systems to update order status
 * and related data in WooCommerce orders.
 */
class AxytosWebhookHandler
{
    /**
     * The webhook API key for authentication
     *
     * @var string
     */
    private $webhook_api_key;

    /**
     * WooCommerce logger instance
     *
     * @var WC_Logger
     */
    private $logger;

    /**
     * Encryption service for handling sensitive data
     *
     * @var AxytosEncryptionService
     */
    private $encryption_service;

    /**
     * Rate limit for webhook requests per minute per IP
     *
     * @var int
     */
    private $rate_limit = 100;

    /**
     * Initialize the webhook handler
     */
    public function __construct()
    {
        $this->logger = wc_get_logger();
        $this->encryption_service = new AxytosEncryptionService();
        $this->load_settings();
    }

    /**
     * Load webhook settings from the payment gateway configuration
     */
    private function load_settings(): void
    {
        $gateway_settings = get_option(
            "woocommerce_" . \AXYTOS_PAYMENT_ID . "_settings",
            []
        );
        $encrypted_key = $gateway_settings["webhook_api_key"] ?? "";
        $this->webhook_api_key = $this->encryption_service->decrypt(
            $encrypted_key
        );
    }

    /**
     * Register the REST API endpoint for webhook
     */
    public static function register_webhook_endpoint(): void
    {
        register_rest_route("axytos/v1", "/order-update", [
            "methods" => "POST",
            "callback" => [$this, "handle_webhook_request"],
            "permission_callback" => [$this, "authenticate_webhook_request"],
            "args" => [
                "order_id" => [
                    "required" => true,
                    "type" => "integer",
                    "description" => "The WooCommerce Order ID",
                    "validate_callback" => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                ],
                "curr_status" => [
                    "required" => false,
                    "type" => "string",
                    "description" => "The current order status",
                    "sanitize_callback" => "sanitize_text_field",
                ],
                "new_status" => [
                    "required" => true,
                    "type" => "string",
                    "description" => "The new order status from ERP",
                    "sanitize_callback" => "sanitize_text_field",
                ],
                "invoice_number" => [
                    "required" => false,
                    "type" => "string",
                    "description" => "Invoice number from ERP",
                    "sanitize_callback" => "sanitize_text_field",
                ],
                "tracking_number" => [
                    "required" => false,
                    "type" => "string",
                    "description" => "Tracking number for shipment",
                    "sanitize_callback" => "sanitize_text_field",
                ],
            ],
        ]);
    }

    /**
     * Authenticate webhook request using API key
     *
     * @param WP_REST_Request $request The REST request object
     * @return bool|WP_Error True if authenticated, WP_Error otherwise
     */
    public function authenticate_webhook_request($request): bool|WP_Error
    {
        // Rate limiting - check if too many requests from same IP
        $client_ip = $this->get_client_ip();
        if ($this->is_rate_limited($client_ip)) {
            $this->log_webhook_activity("ERROR", "Rate limit exceeded", [
                "ip" => $client_ip,
                "user_agent" => $request->get_header("user-agent"),
            ]);
            return new \WP_Error(
                "rate_limit_exceeded",
                __("Too many requests. Please try again later.", "axytos-wc"),
                ["status" => 429]
            );
        }

        // Check if webhook API key is configured
        if (empty($this->webhook_api_key)) {
            $this->log_webhook_activity(
                "ERROR",
                "Webhook API key not configured",
                [
                    "ip" => $client_ip,
                    "user_agent" => $request->get_header("user-agent"),
                ]
            );
            return new \WP_Error(
                "webhook_not_configured",
                __("Webhook endpoint not properly configured.", "axytos-wc"),
                ["status" => 500]
            );
        }

        // Validate Content-Type header
        $content_type = $request->get_content_type();
        if (
            !$content_type ||
            !in_array($content_type["value"], [
                "application/json",
                "text/plain",
            ])
        ) {
            $this->log_webhook_activity("ERROR", "Invalid content type", [
                "ip" => $client_ip,
                "content_type" => $content_type["value"] ?? "none",
                "user_agent" => $request->get_header("user-agent"),
            ]);
            return new \WP_Error(
                "invalid_content_type",
                __("Content-Type must be application/json.", "axytos-wc"),
                ["status" => 400]
            );
        }

        // Get the API key from the custom header
        $provided_key = $request->get_header("X-Axytos-Webhook-Key");

        if (empty($provided_key)) {
            $this->record_failed_attempt($client_ip);
            $this->log_webhook_activity(
                "ERROR",
                "Missing webhook API key in request",
                [
                    "ip" => $client_ip,
                    "user_agent" => $request->get_header("user-agent"),
                ]
            );
            return new \WP_Error(
                "missing_api_key",
                __(
                    "API key is required in X-Axytos-Webhook-Key header.",
                    "axytos-wc"
                ),
                ["status" => 401]
            );
        }

        // Validate API key length (minimum security requirement)
        if (strlen($provided_key) < 32) {
            $this->record_failed_attempt($client_ip);
            $this->log_webhook_activity("ERROR", "API key too short", [
                "ip" => $client_ip,
                "user_agent" => $request->get_header("user-agent"),
                "key_length" => strlen($provided_key),
            ]);
            return new \WP_Error(
                "invalid_api_key",
                __("Invalid API key provided.", "axytos-wc"),
                ["status" => 403]
            );
        }

        // Validate the API key using hash_equals to prevent timing attacks
        if (!hash_equals($this->webhook_api_key, $provided_key)) {
            $this->record_failed_attempt($client_ip);
            $this->log_webhook_activity(
                "ERROR",
                "Invalid webhook API key provided",
                [
                    "ip" => $client_ip,
                    "user_agent" => $request->get_header("user-agent"),
                    "provided_key_length" => strlen($provided_key),
                ]
            );
            return new \WP_Error(
                "invalid_api_key",
                __("Invalid API key provided.", "axytos-wc"),
                ["status" => 403]
            );
        }

        // Clear any previous failed attempts on successful authentication
        $this->clear_failed_attempts($client_ip);

        return true;
    }

    /**
     * Handle the incoming webhook request
     *
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response The response object
     */
    public function handle_webhook_request($request): WP_REST_Response
    {
        $order_id = $request->get_param("order_id");
        $curr_status = $request->get_param("curr_status");
        $new_status = $request->get_param("new_status");
        $invoice_number = $request->get_param("invoice_number");
        $tracking_number = $request->get_param("tracking_number");

        // Log the incoming request
        $this->log_webhook_activity("INFO", "Webhook request received", [
            "order_id" => $order_id,
            "curr_status" => $curr_status,
            "new_status" => $new_status,
            "invoice_number" => $invoice_number,
            "tracking_number" => $tracking_number,
            "ip" => $this->get_client_ip(),
        ]);

        try {
            // Validate request payload size (prevent large payload attacks)
            $raw_body = $request->get_body();
            if (strlen($raw_body) > 10240) {
                // 10KB limit
                $error_message = __("Request payload too large.", "axytos-wc");
                $this->log_webhook_activity("ERROR", $error_message, [
                    "order_id" => $order_id,
                    "payload_size" => strlen($raw_body),
                ]);

                return new \WP_REST_Response(
                    [
                        "success" => false,
                        "message" => $error_message,
                    ],
                    413
                );
            }

            // Additional JSON validation
            if (!empty($raw_body)) {
                $decoded = json_decode($raw_body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $error_message = __("Invalid JSON payload.", "axytos-wc");
                    $this->log_webhook_activity("ERROR", $error_message, [
                        "order_id" => $order_id,
                        "json_error" => json_last_error_msg(),
                    ]);

                    return new \WP_REST_Response(
                        [
                            "success" => false,
                            "message" => $error_message,
                        ],
                        400
                    );
                }
            }

            // Get the WooCommerce order
            $order = wc_get_order($order_id);

            if (!$order) {
                $error_message = sprintf(
                    __("Order with ID %d not found.", "axytos-wc"),
                    $order_id
                );
                $this->log_webhook_activity("ERROR", $error_message, [
                    "order_id" => $order_id,
                ]);

                return new \WP_REST_Response(
                    [
                        "success" => false,
                        "message" => $error_message,
                    ],
                    404
                );
            }

            // Verify the order uses Axytos payment method
            if ($order->get_payment_method() !== \AXYTOS_PAYMENT_ID) {
                $error_message = sprintf(
                    __(
                        "Order %d does not use Axytos payment method.",
                        "axytos-wc"
                    ),
                    $order_id
                );
                $this->log_webhook_activity("ERROR", $error_message, [
                    "order_id" => $order_id,
                    "payment_method" => $order->get_payment_method(),
                ]);

                return new \WP_REST_Response(
                    [
                        "success" => false,
                        "message" => $error_message,
                    ],
                    400
                );
            }

            // Check current status to prevent overwriting newer data
            $current_order_status = $order->get_status();
            if (
                !empty($curr_status) &&
                $current_order_status !== $curr_status
            ) {
                $warning_message = sprintf(
                    __(
                        "Current order status (%s) does not match expected status (%s). Update may be outdated.",
                        "axytos-wc"
                    ),
                    $current_order_status,
                    $curr_status
                );
                $this->log_webhook_activity("WARNING", $warning_message, [
                    "order_id" => $order_id,
                    "current_status" => $current_order_status,
                    "expected_status" => $curr_status,
                    "new_status" => $new_status,
                ]);

                return new \WP_REST_Response(
                    [
                        "success" => false,
                        "message" => $warning_message,
                    ],
                    409
                );
            }

            // Process the order update
            $this->process_order_update(
                $order,
                $new_status,
                $invoice_number,
                $tracking_number
            );

            $success_message = sprintf(
                __("Order %d updated successfully.", "axytos-wc"),
                $order_id
            );
            $this->log_webhook_activity("INFO", $success_message, [
                "order_id" => $order_id,
                "old_status" => $current_order_status,
                "new_status" => $new_status,
            ]);

            return new \WP_REST_Response(
                [
                    "success" => true,
                    "message" => $success_message,
                ],
                200
            );
        } catch (\Exception $e) {
            $error_message = sprintf(
                __("Error processing webhook for order %d: %s", "axytos-wc"),
                $order_id,
                $e->getMessage()
            );

            $this->log_webhook_activity("ERROR", $error_message, [
                "order_id" => $order_id,
                "exception" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);

            return new \WP_REST_Response(
                [
                    "success" => false,
                    "message" => $error_message,
                ],
                500
            );
        }
    }

    /**
     * Process the order update based on the new status and additional data
     *
     * @param WC_Order $order The WooCommerce order object
     * @param string $new_status The new status from ERP
     * @param string|null $invoice_number Optional invoice number
     * @param string|null $tracking_number Optional tracking number
     */
    private function process_order_update(
        $order,
        $new_status,
        $invoice_number = null,
        $tracking_number = null
    ): void {
        $order_id = $order->get_id();
        $note_parts = [];

        // Map ERP status to WooCommerce status if needed
        $wc_status = $this->map_erp_status_to_wc_status($new_status);
        // Validate that the mapped status is a valid WooCommerce status
        if (!$this->is_valid_wc_status($wc_status)) {
            $this->log_webhook_activity("ERROR", "Invalid WooCommerce status", [
                "order_id" => $order_id,
                "erp_status" => $new_status,
                "invalid_wc_status" => $wc_status,
            ]);
            throw new \Exception(
                sprintf(
                    __(
                        "Invalid WooCommerce status '%s' mapped from ERP status '%s'",
                        "axytos-wc"
                    ),
                    $wc_status,
                    $new_status
                )
            );
        }
        $updated = false;
        if (!empty($invoice_number)) {
            $note_parts[] = sprintf(
                __("Invoice: %s", "axytos-wc"),
                $invoice_number
            );
            $order->update_meta_data(
                "_axytos_erp_invoice_number",
                sanitize_text_field($invoice_number)
            );
            $updated = true;
        }

        if (!empty($tracking_number)) {
            $note_parts[] = sprintf(
                __("Tracking: %s", "axytos-wc"),
                $tracking_number
            );
            $order->update_meta_data(
                "_axytos_erp_tracking_number",
                sanitize_text_field($tracking_number)
            );
            $updated = true;
        }

        if ($updated) {
            // Add timestamp for the ERP update
            $order->update_meta_data(
                "_axytos_erp_last_update",
                current_time("mysql")
            );
        }

        // Update the order status if mapped status is different from current
        if ($wc_status && $wc_status !== $order->get_status()) {
            $note_first = sprintf(
                __(
                    "Order status updated from %s to %s via ERP webhook.",
                    "axytos-wc"
                ),
                $order->get_status(),
                $wc_status
            );
            array_unshift($note_parts, $note_first);
            // Add private order note
            $order_note = implode(".\n", $note_parts) . ".";
            $order->add_order_note($order_note, false, false);
        } else {
            $note_first = __("ERP added information", "axytos-wc");
            array_unshift($note_parts, $note_first);
            // Add private order note
            $order_note = implode(".\n", $note_parts) . ".";
            $order->add_order_note($order_note, false, false);
        }

        // Save the order
        $order->save();

        $this->log_webhook_activity("INFO", "Order updated successfully", [
            "order_id" => $order_id,
            "new_status" => $new_status,
            "wc_status" => $wc_status,
            "invoice_number" => $invoice_number,
            "tracking_number" => $tracking_number,
        ]);
    }

    /**
     * Map ERP status to WooCommerce order status
     *
     * @param string $erp_status The status from ERP system
     * @return string|null The corresponding WooCommerce status or null if no mapping needed
     */
    private function map_erp_status_to_wc_status($erp_status)
    {
        $status_mapping = [
            "shipped" => "completed",
            "invoiced" => "processing",
            "cancelled" => "cancelled",
            "refunded" => "refunded",
            "on-hold" => "on-hold",
            "pending" => "pending",
            "processing" => "processing",
            "completed" => "completed",
        ];

        $erp_status_lower = strtolower($erp_status);

        // Apply filters to allow customization of status mapping
        $status_mapping = apply_filters(
            "axytos_webhook_status_mapping",
            $status_mapping
        );

        $mapped_status = isset($status_mapping[$erp_status_lower])
            ? $status_mapping[$erp_status_lower]
            : $erp_status_lower;
        return $mapped_status;
    }

    /**
     * Validate if a status is a valid WooCommerce order status
     *
     * @param string $status The status to validate
     * @return bool True if valid, false otherwise
     */
    private function is_valid_wc_status($status)
    {
        // if (empty($status)) {
        //     return false;
        // }

        // Get all available WooCommerce order statuses
        $valid_statuses = wc_get_order_statuses();

        // WooCommerce statuses are prefixed with 'wc-', so we need to check both formats
        $status_with_prefix = "wc-" . $status;

        // Check if the status exists in the valid statuses array (either with or without prefix)
        return array_key_exists($status_with_prefix, $valid_statuses) ||
            in_array($status, array_keys($valid_statuses));
    }

    /**
     * Log webhook activity using WooCommerce logger
     *
     * @param string $level Log level (INFO, WARNING, ERROR)
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function log_webhook_activity($level, $message, $context = []): void
    {
        $context_string = !empty($context)
            ? " | Context: " . wp_json_encode($context)
            : "";
        $log_message = "[Axytos Webhook] {$message}{$context_string}";

        switch (strtoupper($level)) {
            case "ERROR":
                $this->logger->error($log_message, [
                    "source" => "axytos-webhook",
                ]);
                break;
            case "WARNING":
                $this->logger->warning($log_message, [
                    "source" => "axytos-webhook",
                ]);
                break;
            case "INFO":
            default:
                $this->logger->info($log_message, [
                    "source" => "axytos-webhook",
                ]);
                break;
        }
    }

    /**
     * Check if IP is rate limited
     *
     * @param string $ip The client IP address
     * @return bool True if rate limited
     */
    private function is_rate_limited($ip)
    {
        $transient_key = "axytos_webhook_rate_limit_" . md5($ip);
        $attempts = get_transient($transient_key);

        // Allow configurable number of requests per minute per IP
        return $attempts && $attempts >= $this->rate_limit;
    }

    /**
     * Record failed authentication attempt
     *
     * @param string $ip The client IP address
     */
    private function record_failed_attempt($ip): void
    {
        $transient_key = "axytos_webhook_rate_limit_" . md5($ip);
        $attempts = get_transient($transient_key) ?: 0;
        $attempts++;

        // Set transient for 1 minute
        set_transient($transient_key, $attempts, 60);
    }

    /**
     * Clear failed authentication attempts
     *
     * @param string $ip The client IP address
     */
    private function clear_failed_attempts($ip): void
    {
        $transient_key = "axytos_webhook_rate_limit_" . md5($ip);
        delete_transient($transient_key);
    }

    /**
     * Get the client IP address
     *
     * @return string The client IP address
     */
    private function get_client_ip()
    {
        $ip_keys = [
            "HTTP_CF_CONNECTING_IP",
            "HTTP_X_FORWARDED_FOR",
            "HTTP_X_FORWARDED",
            "HTTP_X_CLUSTER_CLIENT_IP",
            "HTTP_FORWARDED_FOR",
            "HTTP_FORWARDED",
            "REMOTE_ADDR",
        ];

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip_list = explode(",", $_SERVER[$key]);
                $ip = trim($ip_list[0]);

                if (
                    filter_var(
                        $ip,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                    ) !== false
                ) {
                    return $ip;
                }
            }
        }

        return $_SERVER["REMOTE_ADDR"] ?? "Unknown";
    }
}
