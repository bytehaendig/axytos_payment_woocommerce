<?php

namespace Axytos\WooCommerce;

require_once __DIR__ . '/AxytosApiClient.php';
require_once __DIR__ . '/AxytosApiException.php';
require_once __DIR__ . '/axytos-data.php';
require_once __DIR__ . '/AxytosEncryptionService.php';

class AxytosPaymentGateway extends \WC_Payment_Gateway {

	protected $client;

	/**
	 * Encryption service for handling sensitive data
	 *
	 * @var AxytosEncryptionService
	 */
	private $encryption_service;

	public function __construct() {
		$this->id                 = \AXYTOS_PAYMENT_ID;
		$this->icon               = ''; // URL of the icon that will be displayed on the checkout page
		$this->has_fields         = true;
		$this->method_title       = __( 'Axytos', 'axytos-wc' );
		$this->method_description = __(
			'Payment gateway for Axytos.',
			'axytos-wc'
		);
		// Load the settings
		$this->encryption_service = new AxytosEncryptionService();
		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		$AxytosAPIKey      = $this->get_option( 'AxytosAPIKey' );
		$useSandbox        = $this->get_option( 'useSandbox' ) == 'yes';
		$this->client      = new AxytosApiClient( $AxytosAPIKey, $useSandbox );
		// Save settings
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);
		// Add filter for api-key encryption
		add_filter(
			'woocommerce_settings_api_sanitized_fields_' . $this->id,
			array( $this, 'encrypt_settings' ),
			10,
			1
		);
		// Setting up the class for Blocks
		add_filter(
			'woocommerce_payment_gateways',
			array(
				$this,
				'add_gateway_to_block_checkout',
			)
		);
	}

	public function encrypt_settings( $settings ) {
		return $this->encryption_service->encrypt_settings( $settings );
	}

	// Get decrypted value when using get_option
	public function get_option( $key, $empty_value = null ) {
		$value = parent::get_option( $key, $empty_value );
		if (
			in_array( $key, AxytosEncryptionService::get_sensitive_keys() ) &&
			! empty( $value )
		) {
			return $this->encryption_service->decrypt( $value );
		}
		return $value;
	}

	public function add_gateway_to_block_checkout( $gateways ) {
		$options = get_option( 'woocommerce_dummy_settings', array() );
		if ( isset( $options['hide_for_non_admin_users'] ) ) {
			$hide_for_non_admin_users = $options['hide_for_non_admin_users'];
		} else {
			$hide_for_non_admin_users = 'no';
		}
		if (
			( 'yes' === $hide_for_non_admin_users &&
				current_user_can( 'manage_options' ) ) ||
			'no' === $hide_for_non_admin_users
		) {
			$gateways[] = 'AxytosPaymentGateway';
		}
		return $gateways;
	}

	// Initialize form fields for the admin settings page
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'           => array(
				'title'   => __( 'Enable/Disable', 'axytos-wc' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Axytos Payment', 'axytos-wc' ),
				'default' => 'yes',
			),
			'title'             => array(
				'title'       => __( 'Title', 'axytos-wc' ),
				'type'        => 'text',
				'description' => __(
					'This controls the title which the user sees during checkout.',
					'axytos-wc'
				),
				'default'     => __( 'Axytos', 'axytos-wc' ),
				'desc_tip'    => true,
			),
			'description'       => array(
				'title'       => __( 'Description', 'axytos-wc' ),
				'type'        => 'textarea',
				'description' => __(
					'This controls the description which the user sees during checkout.',
					'axytos-wc'
				),
				'default'     => __( 'Pay using Axytos.', 'axytos-wc' ),
			),
			// 'decision_code_s' => [
			// 'title' => __('Action on decision code "S"', 'axytos-wc'),
			// 'type' => 'select',
			// 'description' => __('Choose the action when decision code "S" is received.', 'axytos-wc'),
			// 'default' => 'disallow',
			// 'options' => [
			// 'disallow' => __('Disallow This Payment Method', 'axytos-wc'),
			// 'cancel' => __('Cancel Order', 'axytos-wc'),
			// 'on-hold' => __('Put Order On-hold', 'axytos-wc'),
			// 'proceed' => __('Proceed Order', 'axytos-wc'),
			// ],
			// 'desc_tip' => true,
			// 'class' => 'axytos-hidden',
			// ],
			//
			// 'decision_code_r' => [
			// 'title' => __('Action on decision code "R"', 'axytos-wc'),
			// 'type' => 'select',
			// 'description' => __('Choose the action when decision code "R" is received.', 'axytos-wc'),
			// 'default' => 'on-hold',
			// 'options' => [
			// 'disallow' => __('Disallow This Payment Method', 'axytos-wc'),
			// 'cancel' => __('Cancel Order', 'axytos-wc'),
			// 'on-hold' => __('Put Order On-hold', 'axytos-wc'),
			// 'proceed' => __('Proceed Order', 'axytos-wc'),
			// ],
			// 'desc_tip' => true,
			// 'class' => 'axytos-hidden',
			// ],
			// 'decision_code_u' => [
			// 'title' => __('Action on decision code "U"', 'axytos-wc'),
			// 'type' => 'select',
			// 'description' => __('Choose the action when decision code "U" is received.', 'axytos-wc'),
			// 'default' => 'proceed',
			// 'options' => [
			// 'disallow' => __('Disallow This Payment Method', 'axytos-wc'),
			// 'cancel' => __('Cancel Order', 'axytos-wc'),
			// 'on-hold' => __('Put Order On-hold', 'axytos-wc'),
			// 'proceed' => __('Proceed Order', 'axytos-wc'),
			// ],
			// 'desc_tip' => true,
			// 'class' => 'axytos-hidden',
			// ],
			//
			'PrecheckAgreeText' => array(
				'title'       => __( 'Precheck Agreement Link Text', 'axytos-wc' ),
				'type'        => 'text',
				'description' => __(
					'Enter text you want to as link to get agreement.',
					'axytos-wc'
				),
				'default'     => __( 'click to see agreement', 'axytos-wc' ),
				'desc_tip'    => true,
			),
			'AxytosAPIKey'      => array(
				'title'       => __( 'Axytos API Key', 'axytos-wc' ),
				'type'        => 'text',
				'description' => __(
					'Enter your Axytos API Key for authentication.',
					'axytos-wc'
				),
				'default'     => '',
				'desc_tip'    => true,
			),
			'useSandbox'        => array(
				'title'       => __( 'Use API Sandbox', 'axytos-wc' ),
				'type'        => 'checkbox',
				'description' => __(
					'Send API requests to the API sandbox for testing',
					'axytos-wc'
				),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'webhook_api_key'   => array(
				'title'       => __( 'Webhook API Key', 'axytos-wc' ),
				'type'        => 'text',
				'description' => __(
					'Enter a secure API key for webhook authentication. This key will be used to authenticate incoming webhook requests from your ERP system.',
					'axytos-wc'
				),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => __(
					'Generate a secure random key...',
					'axytos-wc'
				),
			),
		);
	}

	public function process_payment( $order_id ) {
		$order         = wc_get_order( $order_id );
		$decision_code = $this->doPrecheck( $order );
		$action        = strtolower( $decision_code ) === 'u' ? 'proceed' : 'disallow';
		switch ( $action ) {
			case 'proceed':
				// this will trigger the order confirmation (see orders.php)
				$order->update_status(
					'processing',
					__( 'Axytos precheck accepted', 'axytos-wc' )
				);
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
				break;
			case 'disallow':
			default:
				$order_id = $order->get_id();
				set_transient( 'disable_axitos_for_' . $order_id, true, 600 );
				$order->update_status(
					'failed',
					__( 'Axytos precheck declined', 'axytos-wc' )
				);
				throw new \Exception(
					__(
						'This Payment Method is not allowed for this order. Please try a different payment method.',
						'axytos-wc'
					)
				);
		}
		return array();
	}

	public function doPrecheck( $order ) {
		try {
			$data     = createPrecheckData( $order );
			$response = $this->client->invoicePrecheck( $data );
			$order->update_meta_data( 'precheck_response', $response );
			$response_body = json_decode( $response, true );
			$decision_code = $response_body['decision'];
			return $decision_code;
		} catch ( AxytosApiException $e ) {
			// Re-throw with user-friendly message for API connection errors
			if ( $e->isConnectionError() ) {
				throw new \Exception( __( 'Could not connect to Axytos API.', 'axytos-wc' ), 0, $e );
			}
			// Re-throw other API errors as-is
			throw $e;
		}
	}

	public function confirmOrder( $order ) {
		try {
			$confirm_data     = createConfirmData( $order );
			$confirm_response = $this->client->orderConfirm( $confirm_data );

			// Check for API errors in response
			$response_body = json_decode( $confirm_response, true );
			if ( isset( $response_body['errors'] ) ) {
				$error_msg = $this->formatApiErrors( $response_body['errors'] );
				throw new \Exception( 'Order confirmation failed: ' . $error_msg );
			}

			$order->payment_complete();
			return true;
		} catch ( AxytosApiException $e ) {
			// Provide user-friendly message for API connection errors
			if ( $e->isConnectionError() ) {
				throw new \Exception( __( 'Could not confirm order with Axytos API.', 'axytos-wc' ), 0, $e );
			}
			// Re-throw with more context for other API errors
			throw new \Exception( 'Order confirmation failed: ' . $e->getMessage(), 0, $e );
		} catch ( \Exception $e ) {
			// Re-throw with more context for other errors
			throw new \Exception( 'Order confirmation failed: ' . $e->getMessage(), 0, $e );
		}
	}

	public function reportShipping( $order ) {
		try {
			$statusData = createShippingData( $order );
			$result     = $this->client->updateShippingStatus( $statusData );

			$response_body = json_decode( $result, true );
			if ( isset( $response_body['errors'] ) ) {
				$error_msg = $this->formatApiErrors( $response_body['errors'] );
				throw new \Exception( 'Shipping report failed: ' . $error_msg );
			}
			return true;
		} catch ( AxytosApiException $e ) {
			// Provide user-friendly message for API connection errors
			if ( $e->isConnectionError() ) {
				throw new \Exception( __( 'Could not update report shipping.', 'axytos-wc' ), 0, $e );
			}
			// Re-throw with more context for other API errors
			throw new \Exception( 'Shipping report failed: ' . $e->getMessage(), 0, $e );
		} catch ( \Exception $e ) {
			// Re-throw with more context for other errors
			throw new \Exception( 'Shipping report failed: ' . $e->getMessage(), 0, $e );
		}
	}

	public function createInvoice( $order, $invoice_number = null ) {
		try {
			$invoiceData = createInvoiceData( $order, $invoice_number );
			$result      = $this->client->createInvoice( $invoiceData );

			$response_body = json_decode( $result, true );
			if ( isset( $response_body['errors'] ) ) {
				$error_msg = $this->formatApiErrors( $response_body['errors'] );
				throw new \Exception( 'Invoice creation failed: ' . $error_msg );
			}
			return true;
		} catch ( \Exception $e ) {
			// Re-throw with more context
			throw new \Exception( 'Invoice creation failed: ' . $e->getMessage(), 0, $e );
		}
	}

	public function refundOrder( $order ) {
		try {
			$refundData = createRefundData( $order );
			$result     = $this->client->refundOrder( $refundData );

			$response_body = json_decode( $result, true );
			if ( isset( $response_body['errors'] ) ) {
				$error_msg = $this->formatApiErrors( $response_body['errors'] );
				throw new \Exception( 'Refund failed: ' . $error_msg );
			}

			return true;
		} catch ( AxytosApiException $e ) {
			// Provide user-friendly message for API connection errors
			if ( $e->isConnectionError() ) {
				throw new \Exception( __( 'Could not process refund with Axytos API.', 'axytos-wc' ), 0, $e );
			}
			// Re-throw with more context for other API errors
			throw new \Exception( 'Refund failed: ' . $e->getMessage(), 0, $e );
		} catch ( \Exception $e ) {
			// Re-throw with more context for other errors
			throw new \Exception( 'Refund failed: ' . $e->getMessage(), 0, $e );
		}
	}

	public function cancelOrder( $order ) {
		try {
			$result = $this->client->cancelOrder( $order->get_order_number() );

			$response_body = json_decode( $result, true );
			if ( isset( $response_body['errors'] ) ) {
				$error_msg = $this->formatApiErrors( $response_body['errors'] );
				throw new \Exception( 'Order cancellation failed: ' . $error_msg );
			}

			$order->save_meta_data();
			return true;
		} catch ( AxytosApiException $e ) {
			// Provide user-friendly message for API connection errors
			if ( $e->isConnectionError() ) {
				throw new \Exception( __( 'Could not cancel order with Axytos API.', 'axytos-wc' ), 0, $e );
			}
			// Re-throw with more context for other API errors
			throw new \Exception( 'Order cancellation failed: ' . $e->getMessage(), 0, $e );
		} catch ( \Exception $e ) {
			// Re-throw with more context for other errors
			throw new \Exception( 'Order cancellation failed: ' . $e->getMessage(), 0, $e );
		}
	}

	public function reverseCancelOrder( $order ) {
		try {
			$result = $this->client->reverseCancelOrder( $order->get_order_number() );

			$response_body = json_decode( $result, true );
			if ( isset( $response_body['errors'] ) ) {
				$error_msg = $this->formatApiErrors( $response_body['errors'] );
				throw new \Exception( 'Order reverse cancellation failed: ' . $error_msg );
			}

			$order->save_meta_data();
			return true;
		} catch ( AxytosApiException $e ) {
			// Provide user-friendly message for API connection errors
			if ( $e->isConnectionError() ) {
				throw new \Exception( __( 'Could not reverse cancel order with Axytos API.', 'axytos-wc' ), 0, $e );
			}
			// Re-throw with more context for other API errors
			throw new \Exception( 'Order reverse cancellation failed: ' . $e->getMessage(), 0, $e );
		} catch ( \Exception $e ) {
			// Re-throw with more context for other errors
			throw new \Exception( 'Order reverse cancellation failed: ' . $e->getMessage(), 0, $e );
		}
	}

	public function getAgreement() {
		return $this->client->getAgreement();
	}

	/**
	 * Format API errors from Axytos response for better readability
	 */
	private function formatApiErrors( $errors ) {
		if ( is_string( $errors ) ) {
			return $errors;
		}

		if ( is_array( $errors ) ) {
			$error_messages = array();
			foreach ( $errors as $field => $messages ) {
				if ( is_array( $messages ) ) {
					foreach ( $messages as $message ) {
						if ( ! empty( $field ) && $field !== '' ) {
							$error_messages[] = $field . ': ' . $message;
						} else {
							$error_messages[] = $message;
						}
					}
				} elseif ( ! empty( $field ) && $field !== '' ) {
						$error_messages[] = $field . ': ' . $messages;
				} else {
					$error_messages[] = $messages;
				}
			}
			return implode( '; ', $error_messages );
		}

		return __( 'Unknown API error', 'axytos-wc' );
	}
}
