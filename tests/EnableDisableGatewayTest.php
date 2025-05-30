<?php

class EnableDisableGatewayTest extends WP_UnitTestCase {
    public function test_enable_gateway() {
        update_option( 'woocommerce_axytoswc_settings', [ 'enabled' => 'yes' ] );
        $gateway = new AxytosPaymentGateway();

        $this->assertEquals( 'yes', $gateway->get_option( 'enabled' ) );
    }

    public function test_disable_gateway() {
        update_option( 'woocommerce_axytoswc_settings', [ 'enabled' => 'no' ] );
        $gateway = new AxytosPaymentGateway();

        $this->assertEquals( 'no', $gateway->get_option( 'enabled' ) );
    }
}
