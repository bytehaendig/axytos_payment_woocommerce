<?php
/**
 * Axytos API Client for WooCommerce integration.
 *
 * @package Axytos\WooCommerce
 */

namespace Axytos\WooCommerce;

require_once __DIR__ . '/AxytosApiException.php';

/**
 * Axytos API Client for WooCommerce Plugin
 *
 * Handles all communication with the Axytos payment API including
 * invoice prechecks, order confirmations, shipping updates, returns,
 * refunds, and payment status inquiries.
 */
class AxytosApiClient {

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
	 * @param bool   $useSandbox Whether to use sandbox environment (default: true)
	 */
	public function __construct( $AxytosAPIKey, $useSandbox = true ) {
		$this->_AxytosAPIKey = $AxytosAPIKey;
		$this->_BaseUrl      = $useSandbox
			? 'https://api-sandbox.axytos.com/api/v1'
			: 'https://api.axytos.com/api/v1';
		$this->_UserAgent    = $this->makeUserAgent();
	}

	/**
	 * Generate a User-Agent string with plugin and environment information
	 *
	 * @return string The formatted User-Agent string
	 */
	private function makeUserAgent() {
		$pluginVersion = \AXYTOS_PLUGIN_VERSION;
		$phpVersion    = phpversion();
		$wpVersion     = get_bloginfo( 'version' );
		$wcVersion     = \WC_VERSION;
		$userAgent     = "AxytosWooCommercePlugin/$pluginVersion (PHP:$phpVersion WP:$wpVersion WC:$wcVersion)";
		return $userAgent;
	}

	/**
	 * Make an HTTP request to the Axytos API using WordPress HTTP API
	 *
	 * @param string $url The API endpoint URL (relative to base URL)
	 * @param string $method The HTTP method (GET, POST, etc.)
	 * @param array  $data The request payload for POST requests
	 * @return string The API response body
	 * @throws AxytosApiException When API returns non-2xx status code or connection fails
	 */
	private function makeRequest( $url, $method = 'GET', $data = array() ): string|bool {
		$args = array(
			'method'      => strtoupper( $method ),
			'timeout'     => 30,
			'redirection' => 5,
			'httpversion' => '1.1',
			'user-agent'  => $this->_UserAgent,
			'headers'     => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'X-API-Key'    => $this->_AxytosAPIKey,
			),
			'sslverify'   => true,
			'blocking'    => true,
		);

		// Add request body for POST requests
		if ( $method === 'POST' && ! empty( $data ) ) {
			$args['body'] = json_encode( $data );
		}

		// Make the HTTP request using WordPress HTTP API
		$response = wp_remote_request( $this->_BaseUrl . $url, $args );

		// Handle WordPress HTTP API errors
		if ( is_wp_error( $response ) ) {
			$error_message = 'Connection error: ' . $response->get_error_message();
			$error_code    = $response->get_error_code();
			throw AxytosApiException::connectionError(
				$error_message . " (Error code: $error_code)",
				array(
					'wp_error_code'    => $error_code,
					'wp_error_message' => $response->get_error_message(),
					'url'              => $this->_BaseUrl . $url,
				)
			);
		}

		// Get response details
		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		// Handle HTTP errors
		if ( $status < 200 || $status >= 300 ) {
			$error_message = "HTTP error (Status-Code $status)";

			// Try to extract error details from response
			if ( $body && is_string( $body ) ) {
				$response_data = json_decode( $body, true );
				if ( json_last_error() === JSON_ERROR_NONE && isset( $response_data['errors'] ) ) {
					// Handle Axytos API error format
					if ( is_array( $response_data['errors'] ) ) {
						$error_details = array();
						foreach ( $response_data['errors'] as $field => $messages ) {
							if ( is_array( $messages ) ) {
								$error_details[] = $field . ': ' . implode( ', ', $messages );
							} else {
								$error_details[] = $messages;
							}
						}
						if ( ! empty( $error_details ) ) {
							$error_message .= ' - ' . implode( '; ', $error_details );
						}
					}
				} elseif ( $response_data && isset( $response_data['message'] ) ) {
					$error_message .= ' - ' . $response_data['message'];
				}
			}

			throw AxytosApiException::httpError(
				$error_message,
				array(
					'status_code'   => $status,
					'response_body' => $body,
					'url'           => $this->_BaseUrl . $url,
				)
			);
		}

		return $body;
	}

	/**
	 * Perform a precheck for invoice payment eligibility
	 *
	 * @param array $requestData The order data for precheck validation
	 * @return string JSON response from the API
	 * @throws AxytosApiException When API communication fails
	 */
	public function invoicePrecheck( $requestData ) {
		$apiUrl   = '/Payments/invoice/order/precheck';
		$response = $this->makeRequest( $apiUrl, 'POST', $requestData );
		return $response;
	}

	/**
	 * Confirm an order after successful precheck
	 *
	 * @param array $requestData The order confirmation data
	 * @return string JSON response from the API
	 * @throws AxytosApiException When API communication fails
	 */
	public function orderConfirm( $requestData ) {
		$apiUrl   = '/Payments/invoice/order/confirm';
		$response = $this->makeRequest( $apiUrl, 'POST', $requestData );
		return $response;
	}

	/**
	 * Update the shipping status of an order
	 *
	 * @param array $requestData The shipping status update data
	 * @return string JSON response from the API
	 * @throws AxytosApiException When API communication fails
	 */
	public function updateShippingStatus( $requestData ) {
		$apiUrl   = '/Payments/invoice/order/reportshipping';
		$response = $this->makeRequest( $apiUrl, 'POST', $requestData );
		return $response;
	}

	/**
	 * Process return of items from an order
	 *
	 * @param array $requestData The return items data
	 * @return string JSON response from the API
	 * @throws AxytosApiException When API communication fails
	 */
	public function returnItems( $requestData ) {
		$apiUrl   = '/Payments/invoice/order/return';
		$response = $this->makeRequest( $apiUrl, 'POST', $requestData );
		return $response;
	}

	/**
	 * Process a refund for an order
	 *
	 * @param array $requestData The refund data
	 * @return string JSON response from the API
	 * @throws AxytosApiException When API communication fails
	 */
	public function refundOrder( $requestData ) {
		$apiUrl   = '/Payments/invoice/order/refund';
		$response = $this->makeRequest( $apiUrl, 'POST', $requestData );
		return $response;
	}

	/**
	 * Create an invoice for an order
	 *
	 * @param array $requestData The invoice creation data
	 * @return string JSON response from the API
	 * @throws AxytosApiException When API communication fails
	 */
	public function createInvoice( $requestData ) {
		$apiUrl   = '/Payments/invoice/order/createInvoice';
		$response = $this->makeRequest( $apiUrl, 'POST', $requestData );
		return $response;
	}

	/**
	 * Get the current payment status of an order
	 *
	 * @param string|int $orderID The order identifier
	 * @return string JSON response containing payment status
	 * @throws AxytosApiException When API communication fails
	 */
	public function getPaymentStatus( $orderID ) {
		$apiUrl   = '/Payments/invoice/order/paymentstate/' . $orderID;
		$response = $this->makeRequest( $apiUrl );
		return $response;
	}

	/**
	 * Cancel an order
	 *
	 * @param string|int $orderID The order identifier to cancel
	 * @return string JSON response from the API
	 * @throws AxytosApiException When API communication fails
	 */
	public function cancelOrder( $orderID ) {
		$apiUrl   = '/Payments/invoice/order/cancel/' . $orderID;
		$response = $this->makeRequest( $apiUrl, 'POST' );
		return $response;
	}

	/**
	 * Reverse Cancellation of an order
	 *
	 * @param string|int $orderID The order identifier to cancel
	 * @return string JSON response from the API
	 * @throws AxytosApiException When API communication fails
	 */
	public function reverseCancelOrder( $orderID ) {
		$apiUrl      = '/Payments/invoice/order/reverseCancellation';
		$requestData = array( 'externalOrderId' => $orderID );
		$response    = $this->makeRequest( $apiUrl, 'POST', $requestData );
		return $response;
	}

	/**
	 * Get the credit check agreement content
	 *
	 * @return string JSON response containing agreement content
	 * @throws AxytosApiException When API communication fails
	 */
	public function getAgreement() {
		$apiUrl   = '/StaticContent/creditcheckagreement';
		$response = $this->makeRequest( $apiUrl );
		return $response;
	}
}
