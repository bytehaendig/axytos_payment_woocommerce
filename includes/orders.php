<?php

namespace Axytos\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once __DIR__ . '/AxytosActionHandler.php';

/**
 * Handle automatic actions when order status changes
 */
function handle_order_status_change( $order_id, $old_status, $new_status, $order ) {
	if ( ! $order instanceof \WC_Order ) {
		return;
	}

	if ( $order->get_payment_method() !== \AXYTOS_PAYMENT_ID ) {
		return;
	}

	// // Prevent infinite loops by checking if this is an automatic status change
	// $is_automatic_change = $order->get_meta("_axytos_processing_status_change");
	// if ($is_automatic_change) {
	// return;
	// }

	$actions = determine_status_actions( $old_status, $new_status );
	if ( empty( $actions ) ) {
		return;
	}

	// Mark as processing to prevent loops
	// $order->update_meta_data("_axytos_processing_status_change", true);
	// $order->save_meta_data();

	$action_handler = new AxytosActionHandler();
	foreach ( $actions as $action ) {
		$action_handler->addPendingAction( $order_id, $action );
	}
	$action_handler->processPendingActionsForOrder( $order_id );

	// Clear the processing flag
	// $order->delete_meta_data("_axytos_processing_status_change");
	// $order->save_meta_data();
}

/**
 * Determine which action to take based on status change
 */
function determine_status_actions( $old_status, $new_status ) {
	if ( $new_status === 'cancelled' ) {
		return array( 'cancel' );
	}

	if ( $new_status === 'processing' ) {
		// If transitioning from cancelled to processing, reverse the cancellation
		if ( $old_status === 'cancelled' ) {
			return array( 'reverse_cancel' );
		}
		return array( 'confirm' );
	}

	if ( $new_status === 'completed' ) {
		return array( 'shipped', 'invoice' );
	}

	if ( $new_status === 'refunded' ) {
		return array( 'refund' );
	}

	error_log(
		"Axytos: No action determined for order status change from '$old_status' to '$new_status'"
	);
	return array();
}

function bootstrap_orders() {
	// Hook order status changes with high priority to ensure it runs early
	add_action(
		'woocommerce_order_status_changed',
		__NAMESPACE__ . '\handle_order_status_change',
		5,
		4
	);
}
