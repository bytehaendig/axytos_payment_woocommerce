<?php
/**
 * Payment gateway functionality for Axytos WooCommerce plugin.
 *
 * @package Axytos\WooCommerce
 */

namespace Axytos\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove Axytos from gateways if denied by admin
 */
function filter_available_gateways( $available_gateways ) {
	// Check if order awaiting payment exists in session
	if ( WC()->session && $order_id = WC()->session->get( 'order_awaiting_payment' ) ?? WC()->session->get( 'store_api_draft_order' ) ) {
		// Check if transient is set to disable Axytos
		if ( get_transient( 'disable_axitos_for_' . $order_id ) ) {
			$axytos_payment_id = \AXYTOS_PAYMENT_ID;
			if ( isset( $available_gateways[ $axytos_payment_id ] ) ) {
				unset( $available_gateways[ $axytos_payment_id ] );
			}
		}
	}
	return $available_gateways;
}

function bootstrap_payments() {
	add_filter( 'woocommerce_available_payment_gateways', __NAMESPACE__ . '\filter_available_gateways' );
}
