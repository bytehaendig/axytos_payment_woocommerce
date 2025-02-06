<?php

class PaymentGatewayRegistrationTest extends WP_UnitTestCase {
    public function test_gateway_registration() {
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        $this->assertArrayHasKey( 'axytoswc', $gateways );
        $this->assertInstanceOf( 'WC_Axytos_Payment_Gateway', $gateways[ 'axytoswc' ] );
    }
}
