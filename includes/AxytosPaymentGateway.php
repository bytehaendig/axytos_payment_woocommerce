<?php

namespace Axytos\WooCommerce;

require_once __DIR__ . "/AxytosApiClient.php";
require_once __DIR__ . "/axytos-data.php";
require_once __DIR__ . "/AxytosEncryptionService.php";

class AxytosPaymentGateway extends \WC_Payment_Gateway
{
    protected $client;

    /**
     * Encryption service for handling sensitive data
     *
     * @var AxytosEncryptionService
     */
    private $encryption_service;

    public function __construct()
    {
        $this->id = \AXYTOS_PAYMENT_ID;
        $this->icon = ""; // URL of the icon that will be displayed on the checkout page
        $this->has_fields = true;
        $this->method_title = __("Axytos", "axytos-wc");
        $this->method_description = __(
            "Payment gateway for Axytos.",
            "axytos-wc"
        );
        // Load the settings
        $this->encryption_service = new AxytosEncryptionService();
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option("title");
        $this->description = $this->get_option("description");
        $this->enabled = $this->get_option("enabled");
        $AxytosAPIKey = $this->get_option("AxytosAPIKey");
        $useSandbox = $this->get_option("useSandbox") == "yes";
        $this->client = new AxytosApiClient($AxytosAPIKey, $useSandbox);
        // Save settings
        add_action("woocommerce_update_options_payment_gateways_" . $this->id, [
            $this,
            "process_admin_options",
        ]);
        // Add filter for api-key encryption
        add_filter(
            "woocommerce_settings_api_sanitized_fields_" . $this->id,
            [$this, "encrypt_settings"],
            10,
            1
        );
        //Setting up the class for Blocks
        add_filter("woocommerce_payment_gateways", [
            $this,
            "add_gateway_to_block_checkout",
        ]);
    }

    public function encrypt_settings($settings)
    {
        return $this->encryption_service->encrypt_settings($settings);
    }

    // Get decrypted value when using get_option
    public function get_option($key, $empty_value = null)
    {
        $value = parent::get_option($key, $empty_value);
        if (
            in_array($key, AxytosEncryptionService::get_sensitive_keys()) &&
            !empty($value)
        ) {
            return $this->encryption_service->decrypt($value);
        }
        return $value;
    }

    public function add_gateway_to_block_checkout($gateways)
    {
        $options = get_option("woocommerce_dummy_settings", []);
        if (isset($options["hide_for_non_admin_users"])) {
            $hide_for_non_admin_users = $options["hide_for_non_admin_users"];
        } else {
            $hide_for_non_admin_users = "no";
        }
        if (
            ("yes" === $hide_for_non_admin_users &&
                current_user_can("manage_options")) ||
            "no" === $hide_for_non_admin_users
        ) {
            $gateways[] = "AxytosPaymentGateway";
        }
        return $gateways;
    }

    // Initialize form fields for the admin settings page
    public function init_form_fields()
    {
        $this->form_fields = [
            "enabled" => [
                "title" => __("Enable/Disable", "axytos-wc"),
                "type" => "checkbox",
                "label" => __("Enable Axytos Payment", "axytos-wc"),
                "default" => "yes",
            ],
            "title" => [
                "title" => __("Title", "axytos-wc"),
                "type" => "text",
                "description" => __(
                    "This controls the title which the user sees during checkout.",
                    "axytos-wc"
                ),
                "default" => __("Axytos", "axytos-wc"),
                "desc_tip" => true,
            ],
            "description" => [
                "title" => __("Description", "axytos-wc"),
                "type" => "textarea",
                "description" => __(
                    "This controls the description which the user sees during checkout.",
                    "axytos-wc"
                ),
                "default" => __("Pay using Axytos.", "axytos-wc"),
            ],
            // 'decision_code_s' => [
            //     'title' => __('Action on decision code "S"', 'axytos-wc'),
            //     'type' => 'select',
            //     'description' => __('Choose the action when decision code "S" is received.', 'axytos-wc'),
            //     'default' => 'disallow',
            //     'options' => [
            //         'disallow' => __('Disallow This Payment Method', 'axytos-wc'),
            //         // 'cancel' => __('Cancel Order', 'axytos-wc'),
            //         'on-hold' => __('Put Order On-hold', 'axytos-wc'),
            //         'proceed' => __('Proceed Order', 'axytos-wc'),
            //     ],
            //     'desc_tip' => true,
            //     'class' => 'axytos-hidden',
            // ],
            //
            // 'decision_code_r' => [
            // 'title' => __('Action on decision code "R"', 'axytos-wc'),
            // 'type' => 'select',
            // 'description' => __('Choose the action when decision code "R" is received.', 'axytos-wc'),
            // 'default' => 'on-hold',
            // 'options' => [
            //     'disallow' => __('Disallow This Payment Method', 'axytos-wc'),
            //     'cancel' => __('Cancel Order', 'axytos-wc'),
            //     'on-hold' => __('Put Order On-hold', 'axytos-wc'),
            //     'proceed' => __('Proceed Order', 'axytos-wc'),
            //   ],
            //   'desc_tip' => true,
            //   'class' => 'axytos-hidden',
            // ],
            // 'decision_code_u' => [
            //     'title' => __('Action on decision code "U"', 'axytos-wc'),
            //     'type' => 'select',
            //     'description' => __('Choose the action when decision code "U" is received.', 'axytos-wc'),
            //     'default' => 'proceed',
            //     'options' => [
            //         'disallow' => __('Disallow This Payment Method', 'axytos-wc'),
            //         'cancel' => __('Cancel Order', 'axytos-wc'),
            //         'on-hold' => __('Put Order On-hold', 'axytos-wc'),
            //         'proceed' => __('Proceed Order', 'axytos-wc'),
            //     ],
            //     'desc_tip' => true,
            //     'class' => 'axytos-hidden',
            // ],
            //
            "AxytosAPIKey" => [
                "title" => __("Axytos API Key", "axytos-wc"),
                "type" => "text",
                "description" => __(
                    "Enter your Axytos API Key for authentication.",
                    "axytos-wc"
                ),
                "default" => "",
                "desc_tip" => true,
            ],
            "useSandbox" => [
                "title" => __("Use API Sandbox", "axytos-wc"),
                "type" => "checkbox",
                "description" => __(
                    "Send API requests to the API sandbox for testing",
                    "axytos-wc"
                ),
                "default" => "no",
                "desc_tip" => true,
            ],
            "PrecheckAgreeText" => [
                "title" => __("Precheck Agreement Link Text", "axytos-wc"),
                "type" => "text",
                "description" => __(
                    "Enter text you want to as link to get agreement.",
                    "axytos-wc"
                ),
                "default" => __("click to see agreement", "axytos-wc"),
                "desc_tip" => true,
            ],
            "webhook_api_key" => [
                "title" => __("Webhook API Key", "axytos-wc"),
                "type" => "text",
                "description" => __(
                    "Enter a secure API key for webhook authentication. This key will be used to authenticate incoming webhook requests from your ERP system.",
                    "axytos-wc"
                ),
                "default" => "",
                "desc_tip" => true,
                "placeholder" => __(
                    "Generate a secure random key...",
                    "axytos-wc"
                ),
            ],
        ];
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $decision_code = $this->doPrecheck($order);
        $action = strtolower($decision_code) === "u" ? "proceed" : "disallow";
        switch ($action) {
            case "proceed":
                // this will trigger the order confirmation (see orders.php)
                $order->update_status(
                    "processing",
                    __("Axytos precheck accepted", "axytoswc")
                );
                return [
                    "result" => "success",
                    "redirect" => $this->get_return_url($order),
                ];
                break;
            case "disallow":
            default:
                $order_id = $order->get_id();
                set_transient("disable_axitos_for_" . $order_id, true, 600);
                $order->update_status(
                    "failed",
                    __("Axytos precheck declined", "axytoswc")
                );
                throw new \Exception(
                    __(
                        "This Payment Method is not allowed for this order. Please try a different payment method.",
                        "axytos-wc"
                    )
                );
        }
        return [];
    }

    public function doPrecheck($order)
    {
        $data = createPrecheckData($order);
        $response = $this->client->invoicePrecheck($data);
        if (is_wp_error($response)) {
            // wc_add_notice(__('Payment error: Could not connect to Axytos API.', 'axytos-wc'), 'error');
            throw new \Exception("Could not connect to Axytos API.");
            return [];
        }
        $order->update_meta_data("precheck_response", $response);
        $response_body = json_decode($response, true);
        $decision_code = $response_body["decision"];
        return $decision_code;
    }

    public function confirmOrder($order)
    {
        $confirm_data = createConfirmData($order);
        $confirm_response = $this->client->orderConfirm($confirm_data);
        if (is_wp_error($confirm_response)) {
            // wc_add_notice(__('Payment error: Could not confirm order with Axytos API.', 'axytos-wc'), 'error');
            throw new \Exception(
                "Could not confirm order with Axytos API."
            );
            return false;
        }
        $order->payment_complete();
        return true;
    }

    public function reportShipping($order)
    {
        $statusData = createShippingData($order);
        $result = $this->client->updateShippingStatus($statusData);
        if (is_wp_error($result)) {
            wp_send_json_error([
                "message" => __(
                    "Could not update report shipping.",
                    "axytos-wc"
                ),
            ]);
            return false;
        }
        $response_body = json_decode($result, true);
        if (isset($response_body["errors"])) {
            $msg =
                $response_body["errors"][""][0] ?? "Error Response from Axytos";
            wp_send_json_error(["message" => __($msg, "axytos-wc")]);
            return false;
        }
        return true;
    }

    public function createInvoice($order, $invoice_number = null)
    {
        $invoiceData = createInvoiceData($order, $invoice_number);
        $success = false;
        try {
            $result = $this->client->createInvoice($invoiceData);
            $success = true;
            $response_body = json_decode($result, true);
            if (isset($response_body["errors"])) {
                return false;
            }
        } catch (\Exception $e) {
            error_log(
                "Axytos API: could not create invoice: " . $e->getMessage()
            );
        }
        return $success;
    }

    public function refundOrder($order)
    {
        $refundData = createRefundData($order);
        $result = $this->client->refundOrder($refundData);
        if (is_wp_error($result)) {
            return false;
        }

        $response_body = json_decode($result, true);
        if (isset($response_body["errors"])) {
            return false;
        }

        return true;
    }

    public function cancelOrder($order)
    {
        $result = $this->client->cancelOrder($order->get_order_number());
        if (is_wp_error($result)) {
            return false;
        }

        $response_body = json_decode($result, true);
        if (isset($response_body["errors"])) {
            return false;
        }

        $order->save_meta_data();
        return true;
    }

    public function getAgreement()
    {
        return $this->client->getAgreement();
    }
}
