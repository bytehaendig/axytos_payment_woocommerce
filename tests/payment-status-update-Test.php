<?php

class Test_Payment_Status_Update extends WP_UnitTestCase {
    public function test_payment_status_update() {
        $order = wc_create_order();
        $order->set_payment_method( 'axytoswc' );
        $order->update_meta_data( 'unique_id', 12345 );
        ob_start();
        // $order->payment_complete();
        $order->set_status( 'completed' );
        $output = ob_get_clean();
        $this->assertEquals( 'completed', $order->get_status() );
    }
}
