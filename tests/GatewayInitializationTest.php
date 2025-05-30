<?php

class GatewayInitializationTest extends WP_UnitTestCase {

    public function test_gateway_initialization() {
        $gateway = new AxytosPaymentGateway();
        $this->assertEquals( 'yes', $gateway->get_option( 'enabled' ), 'Axytos gateway should be enabled by default.' );
        $this->assertEquals( 'Axytos', $gateway->get_option( 'title' ), 'Axytos gateway title should be "Axytos".' );
    }

    public function test_axytos_gateway_in_available_gateways() {
        $available_gateways = WC()->payment_gateways()->payment_gateways();

        $this->assertNotEmpty( $available_gateways, 'No payment gateways found.' );

        $axytos_found = false;

        foreach ( $available_gateways as $gateway ) {
            if ( $gateway->id === 'axytoswc' ) {
                $axytos_found = true;

                $this->assertEquals( 'yes', $gateway->get_option( 'enabled' ), 'Axytos gateway should be enabled.' );
                $this->assertEquals( 'Axytos', $gateway->get_option( 'title' ), 'Axytos gateway title should be "Axytos".' );
            }
        }

        $this->assertTrue( $axytos_found, 'Axytos payment gateway is not found in the available gateways.' );
    }
}
