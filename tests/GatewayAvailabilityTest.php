<?php

class GatewayAvailabilityTest extends WP_UnitTestCase
{
    public function test_gateway_availability()
    {

        $product = $this->create_sample_product_and_add_to_cart();
        $order = wc_create_order();
        $order_id = $order->get_id();

        WC()->session = new WC_Session_Handler();
        WC()->session->set('order_awaiting_payment', $order_id);

        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        $this->assertArrayHasKey('axytoswc', $available_gateways, 'Axytos gateway should be available initially.');

        set_transient('disable_axitos_for_' . $order_id, true, 60 * 60);

        $available_gateways = apply_filters('woocommerce_available_payment_gateways', WC()->payment_gateways()->get_available_payment_gateways());

        $this->assertArrayNotHasKey('axytoswc', $available_gateways, 'Axytos gateway should not be available for this order after disabling.');
    }

    private function create_sample_product_and_add_to_cart()
    {
        $product = new WC_Product_Simple();
        $product->set_name('Test Product');
        $product->set_regular_price(49.99);
        $product->set_stock_quantity(5);
        $product->set_manage_stock(true);
        $product->set_status('publish');
        $product_id = $product->save();

        if ($product_id) {
            WC()->cart->add_to_cart($product_id, 1);
        }
        return $product;
    }
}
