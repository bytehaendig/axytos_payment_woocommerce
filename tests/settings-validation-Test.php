<?php

class Test_Settings_Validation extends WP_UnitTestCase {
    public function test_settings_save() {
        $gateway = new WC_Axytos_Payment_Gateway();
        $gateway->update_option( 'title', 'Axytos Payment' );

        $this->assertEquals( 'Axytos Payment', $gateway->get_option( 'title' ) );
    }
}
