<?php

class SettingsValidationTest extends WP_UnitTestCase {
    public function test_settings_save() {
        $gateway = new AxytosPaymentGateway();
        $gateway->update_option( 'title', 'Axytos Payment' );

        $this->assertEquals( 'Axytos Payment', $gateway->get_option( 'title' ) );
    }
}
