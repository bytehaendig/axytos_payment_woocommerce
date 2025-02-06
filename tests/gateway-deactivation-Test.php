<?php

class Test_Gateway_Deactivation extends WP_UnitTestCase {
    public function test_gateway_deactivation() {
        deactivate_plugins( 'axytos-woocommerce-main/axytos-wc-payment-gateway.php' );
        $this->assertFalse( is_plugin_active( 'axytos-woocommerce-main/axytos-wc-payment-gateway.php' ) );
    }
}
