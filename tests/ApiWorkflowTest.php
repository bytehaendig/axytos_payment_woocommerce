<?php

class ApiWorkFlowTest extends WP_UnitTestCase {

	public function xtest_process_payment_success() {

		$gateway = new \Axytos\WooCommerce\AxytosPaymentGateway();

		$product = $this->create_sample_product_and_add_to_cart();

		$order    = wc_create_order();
		$order_id = $order->get_id();

		$order->set_billing_first_name( 'John' );
		$order->set_billing_last_name( 'Doe' );
		$order->set_billing_address_1( '123 Main St' );
		$order->set_billing_address_2( 'Apt 4B' );
		$order->set_billing_city( 'Anytown' );
		$order->set_billing_postcode( '56789' );
		$order->set_billing_country( 'DE' );
		$order->set_billing_email( 'john.doe@example.com' );
		$order->set_billing_phone( '+491234567890' );

		$order->set_shipping_first_name( 'John' );
		$order->set_shipping_last_name( 'Doe' );
		$order->set_shipping_address_1( '123 Main St' );
		$order->set_shipping_address_2( 'Apt 4B' );
		$order->set_shipping_city( 'Anytown' );
		$order->set_shipping_postcode( '56789' );
		$order->set_shipping_country( 'DE' );

		$order->update_meta_data( 'unique_id', '12345' );
		$order->set_status( 'pending' );
		$order->set_total( 50 );
		// causing error
		// $order->calculate_totals();

		$items = WC()->cart->get_cart();
		foreach ( $items as $cart_item ) {
			$product_id = $cart_item['product_id'];
			$quantity   = $cart_item['quantity'];
			$order->add_product( wc_get_product( $product_id ), $quantity );
		}
		// $order->calculate_totals();
		$order->save();

		WC()->session = new WC_Session_Handler();
		// WC()->session->set_customer_session_cookie( true );
		WC()->session->set( 'order_awaiting_payment', $order_id );

		$response = $gateway->process_payment( $order_id );
		$this->assertStringContainsString( 'order-received=', $response['redirect'] );
	}

	private function create_sample_product_and_add_to_cart() {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_regular_price( 49.99 );
		$product->set_stock_quantity( 5 );
		$product->set_manage_stock( true );
		$product->set_status( 'publish' );
		$product_id = $product->save();
		if ( $product_id ) {
			WC()->cart->add_to_cart( $product_id, 1 );
		}
		return $product;
	}
}
