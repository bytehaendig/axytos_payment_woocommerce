<?php

// Create test helper function for column_html
function column_html( $column, $order ) {
	return \Axytos\WooCommerce\render_order_column( $column, $order );
}

function crate_order_with_status( $status ) {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
	$order              = wc_create_order();
	$order->set_status( $status );
	$order->set_payment_method( $available_gateways[ AXYTOS_PAYMENT_ID ] );
	$order->save();
	return $order;
}

class AdminButtonsTest extends WP_UnitTestCase {

	public function test_admin_buttons_visibility_processing() {
		$order = crate_order_with_status( 'processing' );

		ob_start();
		column_html( 'axytos_actions', $order );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'data-action="shipped"', $output );
	}

	public function test_admin_buttons_visibility_completed() {
		$order = crate_order_with_status( 'completed' );

		ob_start();
		column_html( 'axytos_actions', $order );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'data-action="refund"', $output );
	}

	public function test_admin_buttons_visibility_cancelled() {
		$order = crate_order_with_status( 'cancelled' );

		ob_start();
		column_html( 'axytos_actions', $order );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'data-action="reverse_cancel"', $output, 'Reverse cancel button should be present for cancelled orders.' );
		$this->assertStringNotContainsString( 'data-action="shipped"', $output, 'Shipped button should not be present for cancelled orders.' );
		$this->assertStringNotContainsString( 'data-action="cancel"', $output, 'Cancel button should not be present for cancelled orders.' );
	}
}
