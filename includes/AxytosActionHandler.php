<?php

namespace Axytos\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once __DIR__ . '/AxytosPaymentGateway.php';
require_once __DIR__ . '/axytos-data.php';

/**
 * Handles pending Axytos actions with retry logic for robustness
 */
class AxytosActionHandler {

	public const META_KEY_PENDING         = '_axytos_pending';
	public const META_KEY_DONE            = '_axytos_done';
	public const META_KEY_BROKEN          = '_axytos_broken';
	public const META_KEY_INVOICE_NUMBER  = 'axytos_ext_invoice_nr';
	public const META_KEY_TRACKING_NUMBER = 'axytos_ext_tracking_nr';
	public const MAX_RETRIES              = 3;

	private $gateway;
	private $logger;

	public function __construct() {
		$this->gateway = new AxytosPaymentGateway();
		$this->logger  = wc_get_logger();
	}

	/**
	 * Add a pending action to the order
	 */
	public function addPendingAction( $order_id, $action, $additional_data = array() ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== \AXYTOS_PAYMENT_ID ) {
			return false;
		}

		$pending_actions = $this->getPendingActions( $order );

		// Check if this action is already pending
		foreach ( $pending_actions as $pending_action ) {
			if ( $pending_action['action'] === $action ) {
				return true; // Action already pending
			}
		}

		$new_action = array(
			'action'       => $action,
			'created_at'   => gmdate( 'c' ),
			'failed_at'    => null,
			'failed_count' => 0,
			'fail_reason'  => null,
			'data'         => $additional_data,
		);

		$pending_actions[] = $new_action;
		$order->update_meta_data( self::META_KEY_PENDING, $pending_actions );

		// Update broken status whenever pending actions change
		$this->updateBrokenStatus( $order, $pending_actions );

		// TODO: is save_meta_data needed?
		$order->save_meta_data();

		$this->log(
			"Added pending action '$action' for order #$order_id",
			'info'
		);

		return true;
	}

	/**
	 * Get pending actions for an order
	 */
	public function getPendingActions( $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return array();
		}

		$pending_actions = $order->get_meta( self::META_KEY_PENDING );
		return is_array( $pending_actions ) ? $pending_actions : array();
	}

	/**
	 * Get completed actions for an order
	 */
	public function getDoneActions( $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return array();
		}

		$done_actions = $order->get_meta( self::META_KEY_DONE );
		return is_array( $done_actions ) ? $done_actions : array();
	}

	/**
	 * Move a successful action to the done actions list
	 */
	private function moveActionToDone( $order, $action_data ) {
		$done_actions = $this->getDoneActions( $order );

		// Add processed_at timestamp
		$action_data['processed_at'] = gmdate( 'c' );

		$done_actions[] = $action_data;

		$order->update_meta_data( self::META_KEY_DONE, $done_actions );
		// Note: save_meta_data() will be called by the caller
	}

	/**
	 * Get all actions (pending + done) for an order
	 */
	public function getAllActions( $order ) {
		return array(
			'pending' => $this->getPendingActions( $order ),
			'done'    => $this->getDoneActions( $order ),
		);
	}

	/**
	 * Process all pending actions for an order
	 */
	public function processPendingActionsForOrder( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== \AXYTOS_PAYMENT_ID ) {
			return false;
		}

		$pending_actions = $this->getPendingActions( $order );
		if ( empty( $pending_actions ) ) {
			return true;
		}

		// Check if any action has exceeded retry limit - if so, skip this order
		foreach ( $pending_actions as $action_data ) {
			if ( $this->isBroken( $action_data ) ) {
				$this->log(
					"Order #{$order_id} has actions that exceeded max retries, skipping processing",
					'warning'
				);
				return false;
			}
		}

		$updated = false;
		foreach ( $pending_actions as $index => $action_data ) {
			$result = $this->processAction( $order, $action_data );

			if ( $result['success'] ) {
				// Move successful action to done actions
				$this->moveActionToDone( $order, $action_data );
				unset( $pending_actions[ $index ] );
				$updated = true;

				$this->addOrderNote( $order, $action_data );
				$this->log(
					"Successfully processed action '{$action_data["action"]}' for order #$order_id",
					'info'
				);
			} else {
				// Mark as failed
				$pending_actions[ $index ]['failed_at']    = gmdate( 'c' );
				$pending_actions[ $index ]['failed_count'] =
					( $action_data['failed_count'] ?? 0 ) + 1;
				$pending_actions[ $index ]['fail_reason']  = $result['error_message'] ?? 'Unknown error';
				$updated                                   = true;

				$failed_count = $pending_actions[ $index ]['failed_count'];
				$this->log(
					"Failed to process action '{$action_data["action"]}' for order #$order_id (attempt #{$failed_count}): {$result['error_message']}",
					'error'
				);

				// Check if max retries reached
				if ( $failed_count >= self::MAX_RETRIES ) {
					$this->handleMaxRetriesExceeded( $order, $action_data );
					// Don't process any more actions for this order
					break;
				}

				// Stop processing further actions for this order
				break;
			}
		}

		if ( $updated ) {
			$this->updatePendingActions( $order, $pending_actions );
		}

		return true;
	}

	/**
	 * Process a single action
	 */
	private function processAction( $order, $action_data ) {
		try {
			switch ( $action_data['action'] ) {
				case 'confirm':
					return $this->processConfirmAction( $order, $action_data );

				case 'shipped':
					return $this->processShippedAction( $order, $action_data );

				case 'invoice':
					return $this->processInvoiceAction( $order, $action_data );

				case 'cancel':
					return $this->processCancelAction( $order, $action_data );

				case 'reverse_cancel':
					return $this->processReverseCancelAction( $order, $action_data );

				case 'refund':
					return $this->processRefundAction( $order, $action_data );

				default:
					$this->log(
						"Unknown action type: {$action_data["action"]}",
						'error'
					);
					return array(
						'success'       => false,
						'error_message' => "Unknown action type: {$action_data["action"]}",
					);
			}
		} catch ( \Exception $e ) {
			$error_message = $this->categorizeError( $e );
			$this->log(
				"Exception processing action '{$action_data["action"]}' for order #{$order->get_id()}: {$e->getMessage()}",
				'error'
			);
			return array(
				'success'       => false,
				'error_message' => $error_message,
			);
		}
	}

	/**
	 * Categorize error messages for better user understanding
	 */
	private function categorizeError( \Exception $e ) {
		$message = $e->getMessage();

		// Check for HTTP status codes in the message
		if ( preg_match( '/Status-Code (\d+)/', $message, $matches ) ) {
			$status_code = intval( $matches[1] );

			switch ( $status_code ) {
				case 400:
					return 'Validation error (400): Invalid request data - ' . $message;
				case 401:
					return 'Authentication error (401): Invalid API key or credentials';
				case 403:
					return 'Authorization error (403): Access denied';
				case 404:
					return 'Not found error (404): Resource not found';
				case 500:
					return 'Server error (500): Internal server error at Axytos';
				case 502:
					return 'Bad gateway (502): Axytos service temporarily unavailable';
				case 503:
					return 'Service unavailable (503): Axytos API temporarily down';
				case 504:
					return 'Gateway timeout (504): Request to Axytos timed out';
				default:
					return "HTTP error ({$status_code}): " . $message;
			}
		}

		// Check for common connection errors
		if ( stripos( $message, 'curl' ) !== false || stripos( $message, 'connection' ) !== false ) {
			return 'Connection error: Unable to connect to Axytos API - ' . $message;
		}

		// Check for timeout errors
		if ( stripos( $message, 'timeout' ) !== false ) {
			return 'Timeout error: Request to Axytos API timed out';
		}

		// Check for SSL/TLS errors
		if ( stripos( $message, 'ssl' ) !== false || stripos( $message, 'certificate' ) !== false ) {
			return 'SSL/Certificate error: ' . $message;
		}

		// Default case
		return 'API error: ' . $message;
	}

	/**
	 * Process confirm action
	 */
	private function processConfirmAction( $order, $action_data ) {
		try {
			$success = $this->gateway->confirmOrder( $order );
			return array(
				'success'       => $success,
				'error_message' => $success ? null : 'Order confirmation failed',
			);
		} catch ( \Exception $e ) {
			return array(
				'success'       => false,
				'error_message' => $this->categorizeError( $e ),
			);
		}
	}

	/**
	 * Process shipped action
	 */
	private function processShippedAction( $order, $action_data ) {
		try {
			$success = $this->gateway->reportShipping( $order );
			return array(
				'success'       => $success,
				'error_message' => $success ? null : 'Shipping report failed',
			);
		} catch ( \Exception $e ) {
			return array(
				'success'       => false,
				'error_message' => $this->categorizeError( $e ),
			);
		}
	}

	/**
	 * Process invoice action
	 */
	private function processInvoiceAction( $order, $action_data ) {
		try {
			// Get invoice number from meta-data or action data
			$invoice_number = $order->get_meta( self::META_KEY_INVOICE_NUMBER );
			// Create invoice (this can succeed even if invoice number is empty)
			$success = $this->gateway->createInvoice( $order, $invoice_number );
			return array(
				'success'       => $success,
				'error_message' => $success ? null : 'Invoice creation failed',
			);
		} catch ( \Exception $e ) {
			return array(
				'success'       => false,
				'error_message' => $this->categorizeError( $e ),
			);
		}
	}

	/**
	 * Process cancel action
	 */
	private function processCancelAction( $order, $action_data ) {
		try {
			$success = $this->gateway->cancelOrder( $order );
			return array(
				'success'       => $success,
				'error_message' => $success ? null : 'Order cancellation failed',
			);
		} catch ( \Exception $e ) {
			return array(
				'success'       => false,
				'error_message' => $this->categorizeError( $e ),
			);
		}
	}

	/**
	 * Process reverse cancel action
	 */
	private function processReverseCancelAction( $order, $action_data ) {
		try {
			$success = $this->gateway->reverseCancelOrder( $order );
			return array(
				'success'       => $success,
				'error_message' => $success ? null : 'Order reverse cancellation failed',
			);
		} catch ( \Exception $e ) {
			return array(
				'success'       => false,
				'error_message' => $this->categorizeError( $e ),
			);
		}
	}

	/**
	 * Process refund action
	 */
	private function processRefundAction( $order, $action_data ) {
		try {
			$success = $this->gateway->refundOrder( $order );
			return array(
				'success'       => $success,
				'error_message' => $success ? null : 'Order refund failed',
			);
		} catch ( \Exception $e ) {
			return array(
				'success'       => false,
				'error_message' => $this->categorizeError( $e ),
			);
		}
	}

	/**
	 * Add order note for successful action
	 */
	private function addOrderNote( $order, $action_data ) {
		// TODO: include invoice number in note even if it's not within $action_data
		$action     = $action_data['action'];
		$created_at = $action_data['created_at'];

		$note = sprintf(
			/* translators: 1: action name, 2: queue timestamp */
			__(
				'Axytos action "%1$s" processed successfully',
				'axytos-wc'
			),
			$action,
			$created_at
		);

		// Add additional data to note if available
		if ( ! empty( $action_data['data'] ) ) {
			$additional_info = array();
			foreach ( $action_data['data'] as $key => $value ) {
				if ( ! empty( $value ) ) {
					$additional_info[] = "$key: $value";
				}
			}
			if ( ! empty( $additional_info ) ) {
				$note .= ' (' . implode( ', ', $additional_info ) . ')';
			}
		}

		$order->add_order_note( $note );
	}

	/**
	 * Store invoice number for later use in shipping action
	 */
	public function setInvoiceNumber( $order_id, $invoice_number ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
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
	public function setTrackingNumber( $order_id, $tracking_number ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
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
	public function getOrdersWithPendingActions( $limit = 50 ) {
		$orders = wc_get_orders(
			array(
				'limit'          => $limit,
				'meta_key'       => self::META_KEY_PENDING,
				'meta_compare'   => 'EXISTS',
				'payment_method' => \AXYTOS_PAYMENT_ID,
				'return'         => 'ids',
			)
		);

		return $orders;
	}

	/**
	 * Get all orders with broken actions
	 */
	public function getOrdersWithBrokenActions( $limit = 50 ) {
		$orders = wc_get_orders(
			array(
				'limit'          => $limit,
				'meta_key'       => self::META_KEY_BROKEN,
				'meta_compare'   => 'EXISTS',
				'payment_method' => \AXYTOS_PAYMENT_ID,
				'return'         => 'ids',
			)
		);

		return $orders;
	}

	/**
	 * Get count of orders with broken actions
	 */
	public function getOrdersWithBrokenActionsCount() {
		$orders = wc_get_orders(
			array(
				'limit'          => -1, // Get all
				'meta_key'       => self::META_KEY_BROKEN,
				'meta_compare'   => 'EXISTS',
				'payment_method' => \AXYTOS_PAYMENT_ID,
				'return'         => 'ids',
			)
		);

		return count( $orders );
	}

	/**
	 * Process pending actions for all orders
	 */
	// TODO: maybe limit per cron run
	public function processAllPendingActions() {
		$processed_count = 0;
		$failed_count    = 0;
		$offset          = 0;
		$batch_size      = 50;

		do {
			// Get orders in batches to avoid memory issues with large datasets
			$order_ids = wc_get_orders(
				array(
					'limit'          => $batch_size,
					'offset'         => $offset,
					'meta_key'       => self::META_KEY_PENDING,
					'meta_compare'   => 'EXISTS',
					'payment_method' => \AXYTOS_PAYMENT_ID,
					'return'         => 'ids',
				)
			);

			foreach ( $order_ids as $order_id ) {
				try {
					// Skip orders that have actions exceeding retry limits
					$order = wc_get_order( $order_id );
					if ( $order && $this->hasActionsExceedingRetryLimit( $order ) ) {
						continue;
					}

					$success = $this->processPendingActionsForOrder( $order_id );
					if ( $success ) {
						++$processed_count;
					} else {
						++$failed_count;
					}
				} catch ( \Exception $e ) {
					++$failed_count;
					$this->log(
						"Exception during processing of pending actions for order #$order_id: {$e->getMessage()}",
						'error'
					);
				}
			}

			$offset += $batch_size;
		} while ( count( $order_ids ) === $batch_size );

		if ( $processed_count > 0 || $failed_count > 0 ) {
			$this->log(
				"Processed pending actions for $processed_count orders, $failed_count failed",
				'info'
			);
		}

		return array(
			'processed' => $processed_count,
			'failed'    => $failed_count,
		);
	}



	/**
	 * Handle when an action exceeds maximum retry limit
	 */
	private function handleMaxRetriesExceeded( $order, $action_data ) {
		$order_id = $order->get_id();
		$action   = $action_data['action'];

		// Log the error
		$this->log(
			"Action '{$action}' for order #{$order_id} exceeded max retries (" . self::MAX_RETRIES . ')',
			'critical'
		);

		// Notify shop admin
		$this->notifyShopAdmin( $order, $action_data );

		// Add order note
		$order->add_order_note(
			sprintf(
			/* translators: 1: action name, 2: number of retries */
				__( 'Axytos action "%1$s" failed permanently after %2$d retries. Order requires manual attention.', 'axytos-wc' ),
				$action,
				self::MAX_RETRIES
			)
		);
	}

	/**
	 * Send notification email to shop admin
	 */
	private function notifyShopAdmin( $order, $action_data ) {
		$admin_email = get_option( 'admin_email' );
		$order_id    = $order->get_id();
		$action      = $action_data['action'];

		$subject = sprintf(
			/* translators: 1: site name, 2: order number */
			__( '[%1$s] Axytos Payment Action Failed - Order #%2$s', 'axytos-wc' ),
			get_bloginfo( 'name' ),
			$order_id
		);

		$message = sprintf(
			/* translators: 1: order number, 2: action name, 3: max retries, 4: order edit URL */
			__( "An Axytos payment action has failed permanently and requires manual attention.\n\nOrder: #%1\$s\nAction: %2\$s\nMax retries reached: %3\$d\n\nPlease check the order in your WooCommerce admin and contact Axytos support if needed.\n\nOrder URL: %4\$s", 'axytos-wc' ),
			$order_id,
			$action,
			self::MAX_RETRIES,
			admin_url( "post.php?post={$order_id}&action=edit" )
		);

		wp_mail( $admin_email, $subject, $message );

		$this->log(
			"Admin notification sent for failed action '{$action}' on order #{$order_id}",
			'info'
		);
	}

	/**
	 * Check if an action is broken (has exceeded max retry count)
	 */
	public function isBroken( $action ) {
		return ( $action['failed_count'] ?? 0 ) >= self::MAX_RETRIES;
	}

	/**
	 * Check if order has actions that have exceeded max retry count
	 */
	private function hasActionsExceedingRetryLimit( $order ) {
		$pending_actions = $this->getPendingActions( $order );
		foreach ( $pending_actions as $action_data ) {
			if ( $this->isBroken( $action_data ) ) {
				return true;
			}
		}
		return false;
	}


	/**
	 * Update pending actions array and save to order meta
	 */
	private function updatePendingActions( $order, $pending_actions ) {
		// Re-index array to maintain sequential indices
		$pending_actions = array_values( $pending_actions );

		if ( empty( $pending_actions ) ) {
			$order->delete_meta_data( self::META_KEY_PENDING );
		} else {
			$order->update_meta_data( self::META_KEY_PENDING, $pending_actions );
		}

		// Update broken status whenever pending actions change
		$this->updateBrokenStatus( $order, $pending_actions );

		$order->save_meta_data();
	}

	/**
	 * Update the broken status meta-data based on pending actions
	 */
	private function updateBrokenStatus( $order, $pending_actions = null ) {
		if ( $pending_actions === null ) {
			$pending_actions = $this->getPendingActions( $order );
		}

		$has_broken_actions = false;

		// Check if any pending action is broken
		foreach ( $pending_actions as $action ) {
			if ( $this->isBroken( $action ) ) {
				$has_broken_actions = true;
				break;
			}
		}

		if ( $has_broken_actions ) {
			$order->update_meta_data( self::META_KEY_BROKEN, true );
		} else {
			$order->delete_meta_data( self::META_KEY_BROKEN );
		}
	}

	/**
	 * Remove a specific failed pending action
	 */
	public function removeFailedAction( $order_id, $action_name ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== \AXYTOS_PAYMENT_ID ) {
			return false;
		}

		$pending_actions = $this->getPendingActions( $order );
		if ( empty( $pending_actions ) ) {
			return false;
		}

		$updated = false;
		foreach ( $pending_actions as $index => $action_data ) {
			// Only remove broken actions (those that have exceeded max retries)
			if ( $action_data['action'] === $action_name && $this->isBroken( $action_data ) ) {
				unset( $pending_actions[ $index ] );
				$updated = true;

				// Add order note about the removal
				$order->add_order_note(
					sprintf(
						/* translators: %s: action name */
						__( 'Failed Axytos action "%s" was manually removed by admin.', 'axytos-wc' ),
						$action_name
					)
				);

				$this->log(
					"Manually removed failed action '$action_name' for order #$order_id",
					'info'
				);
				break;
			}
		}

		if ( $updated ) {
			$this->updatePendingActions( $order, $pending_actions );
			return true;
		}

		return false;
	}

	/**
	 * Retry broken actions for a specific order
	 * This processes broken actions even if they have exceeded max retries
	 */
	public function retryBrokenActionsForOrder( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== \AXYTOS_PAYMENT_ID ) {
			return false;
		}

		$pending_actions = $this->getPendingActions( $order );
		if ( empty( $pending_actions ) ) {
			return true;
		}

		$updated         = false;
		$processed_count = 0;
		$failed_count    = 0;

		foreach ( $pending_actions as $index => $action_data ) {
			// Only process broken actions and only if no other actions before
			if ( ! $this->isBroken( $action_data ) ) {
				break;
			}

			$this->log(
				"Retrying broken action '{$action_data["action"]}' for order #$order_id",
				'info'
			);

			$result = $this->processAction( $order, $action_data );

			if ( $result['success'] ) {
				// Move successful action to done actions
				$this->moveActionToDone( $order, $action_data );
				unset( $pending_actions[ $index ] );
				$updated = true;
				++$processed_count;

				$this->addOrderNote( $order, $action_data );
				$this->log(
					"Successfully retried broken action '{$action_data["action"]}' for order #$order_id",
					'info'
				);

				// Add specific note about manual retry
				$order->add_order_note(
					sprintf(
						/* translators: %s: action name */
						__( 'Broken Axytos action "%s" was manually retried and succeeded.', 'axytos-wc' ),
						$action_data['action']
					)
				);
			} else {
				// Reset failed count and timestamp for another retry cycle
				$pending_actions[ $index ]['failed_at']    = gmdate( 'c' );
				$pending_actions[ $index ]['failed_count'] = 1; // Reset to 1 for new retry cycle
				$pending_actions[ $index ]['fail_reason']  = $result['error_message'] ?? 'Unknown error during manual retry';
				$updated                                   = true;
				++$failed_count;

				$this->log(
					"Manual retry of broken action '{$action_data["action"]}' for order #$order_id failed: {$result['error_message']}, resetting for new retry cycle",
					'warning'
				);

				// Add note about failed retry
				$order->add_order_note(
					sprintf(
						/* translators: 1: action name, 2: error message */
						__( 'Manual retry of broken Axytos action "%1$s" failed: %2$s. Action reset for new retry cycle.', 'axytos-wc' ),
						$action_data['action'],
						$result['error_message'] ?? 'Unknown error'
					)
				);

				// Stop processing further actions if this one failed
				break;
			}
		}

		if ( $updated ) {
			$this->updatePendingActions( $order, $pending_actions );
		}

		return array(
			'processed'    => $processed_count,
			'failed'       => $failed_count,
			'total_broken' => count( array_filter( $this->getPendingActions( $order ), array( $this, 'isBroken' ) ) ),
		);
	}

	/**
	 * Log message with context
	 */
	private function log( $message, $level = 'info' ) {
		$this->logger->log(
			$level,
			$message,
			array(
				'source' => 'axytos-action-handler',
			)
		);
	}
}
