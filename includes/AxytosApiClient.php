<?php

namespace Axytos\WooCommerce;

/**
 * Axytos API Client for WooCommerce Plugin
 *
 * Handles all communication with the Axytos payment API including
 * invoice prechecks, order confirmations, shipping updates, returns,
 * refunds, and payment status inquiries.
 */
class AxytosApiClient
{
    /**
     * The API key for authentication with Axytos services
     *
     * @var string
     */
    private $_AxytosAPIKey;

    /**
     * The base URL for the Axytos API (sandbox or production)
     *
     * @var string
     */
    private $_BaseUrl;

    /**
     * The User-Agent string sent with API requests
     *
     * @var string
     */
    private $_UserAgent;

    /**
     * Initialize the Axytos API client
     *
     * @param string $AxytosAPIKey The API key for authentication
     * @param bool $useSandbox Whether to use sandbox environment (default: true)
     */
    public function __construct($AxytosAPIKey, $useSandbox = true)
    {
        $this->_AxytosAPIKey = $AxytosAPIKey;
        $this->_BaseUrl = $useSandbox
            ? "https://api-sandbox.axytos.com/api/v1"
            : "https://api.axytos.com/api/v1";
        $this->_UserAgent = $this->makeUserAgent();
    }

    /**
     * Generate a User-Agent string with plugin and environment information
     *
     * @return string The formatted User-Agent string
     */
    private function makeUserAgent()
    {
        $pluginVersion = \AXYTOS_PLUGIN_VERSION;
        $phpVersion = phpversion();
        $wpVersion = get_bloginfo("version");
        $wcVersion = \WC_VERSION;
        $userAgent = "AxytosWooCommercePlugin/$pluginVersion (PHP:$phpVersion WP:$wpVersion WC:$wcVersion)";
        return $userAgent;
    }

    /**
     * Make an HTTP request to the Axytos API
     *
     * @param string $url The API endpoint URL (relative to base URL)
     * @param string $method The HTTP method (GET, POST, etc.)
     * @param array $data The request payload for POST requests
     * @return string The API response body
     * @throws Exception When API returns non-2xx status code
     */
    private function makeRequest($url, $method = "GET", $data = []): string|bool
    {
        $headers = [
            "Content-type: application/json",
            "accept: application/json",
            "X-API-Key: " . $this->_AxytosAPIKey,
            "User-Agent: " . $this->_UserAgent,
        ];

        $ch = curl_init($this->_BaseUrl . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 second connection timeout
        if ($method === "POST") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Handle cURL errors
        if ($curl_errno !== 0) {
            throw new \Exception("Connection error: " . $curl_error . " (cURL error code: $curl_errno)");
        }
        
        // Handle HTTP errors
        if ($status < 200 || $status >= 300) {
            $error_message = "HTTP error (Status-Code $status)";
            
            // Try to extract error details from response
            if ($response && is_string($response)) {
                $response_data = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($response_data['errors'])) {
                    // Handle Axytos API error format
                    if (is_array($response_data['errors'])) {
                        $error_details = [];
                        foreach ($response_data['errors'] as $field => $messages) {
                            if (is_array($messages)) {
                                $error_details[] = $field . ': ' . implode(', ', $messages);
                            } else {
                                $error_details[] = $messages;
                            }
                        }
                        if (!empty($error_details)) {
                            $error_message .= " - " . implode('; ', $error_details);
                        }
                    }
                } elseif ($response_data && isset($response_data['message'])) {
                    $error_message .= " - " . $response_data['message'];
                }
            }
            
            throw new \Exception($error_message);
        }
        
        return $response;
    }

    /**
     * Perform a precheck for invoice payment eligibility
     *
     * @param array $requestData The order data for precheck validation
     * @return string JSON response from the API
     * @throws Exception When API communication fails
     */
    public function invoicePrecheck($requestData)
    {
        $apiUrl = "/Payments/invoice/order/precheck";
        $response = $this->makeRequest($apiUrl, "POST", $requestData);
        return $response;
    }

    /**
     * Confirm an order after successful precheck
     *
     * @param array $requestData The order confirmation data
     * @return string JSON response from the API
     * @throws Exception When API communication fails
     */
    public function orderConfirm($requestData)
    {
        $apiUrl = "/Payments/invoice/order/confirm";
        $response = $this->makeRequest($apiUrl, "POST", $requestData);
        return $response;
    }

    /**
     * Update the shipping status of an order
     *
     * @param array $requestData The shipping status update data
     * @return string JSON response from the API
     * @throws Exception When API communication fails
     */
    public function updateShippingStatus($requestData)
    {
        $apiUrl = "/Payments/invoice/order/reportshipping";
        $response = $this->makeRequest($apiUrl, "POST", $requestData);
        return $response;
    }

    /**
     * Process return of items from an order
     *
     * @param array $requestData The return items data
     * @return string JSON response from the API
     * @throws Exception When API communication fails
     */
    public function returnItems($requestData)
    {
        $apiUrl = "/Payments/invoice/order/return";
        $response = $this->makeRequest($apiUrl, "POST", $requestData);
        return $response;
    }

    /**
     * Process a refund for an order
     *
     * @param array $requestData The refund data
     * @return string JSON response from the API
     * @throws Exception When API communication fails
     */
    public function refundOrder($requestData)
    {
        $apiUrl = "/Payments/invoice/order/refund";
        $response = $this->makeRequest($apiUrl, "POST", $requestData);
        return $response;
    }

    /**
     * Create an invoice for an order
     *
     * @param array $requestData The invoice creation data
     * @return string JSON response from the API
     * @throws Exception When API communication fails
     */
    public function createInvoice($requestData)
    {
        $apiUrl = "/Payments/invoice/order/createInvoice";
        $response = $this->makeRequest($apiUrl, "POST", $requestData);
        return $response;
    }

    /**
     * Get the current payment status of an order
     *
     * @param string|int $orderID The order identifier
     * @return string JSON response containing payment status
     * @throws Exception When API communication fails
     */
    public function getPaymentStatus($orderID)
    {
        $apiUrl = "/Payments/invoice/order/paymentstate/" . $orderID;
        $response = $this->makeRequest($apiUrl);
        return $response;
    }

    /**
     * Cancel an order
     *
     * @param string|int $orderID The order identifier to cancel
     * @return string JSON response from the API
     * @throws Exception When API communication fails
     */
    public function cancelOrder($orderID)
    {
        $apiUrl = "/Payments/invoice/order/cancel/" . $orderID;
        $response = $this->makeRequest($apiUrl, "POST");
        return $response;
    }

    /**
     * Get the credit check agreement content
     *
     * @return string JSON response containing agreement content
     * @throws Exception When API communication fails
     */
    public function getAgreement()
    {
        $apiUrl = "/StaticContent/creditcheckagreement";
        $response = $this->makeRequest($apiUrl);
        return $response;
    }
}
