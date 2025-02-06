<?php

class UniqueIdTest extends WP_UnitTestCase {
    public function test_thankyou_notice_display() {
        $order = wc_create_order();
        $order->set_payment_method( 'axytoswc' );
        // $order->set_status( 'on-hold' );
        //uncomment to fail the test
        // $order->update_meta_data( 'unique_id', '12345' );
        $order->save();

        $unique_id = $order->get_meta( 'unique_id' );

        ob_start();

        if ( empty( $unique_id ) ) {
            echo 'No unique ID found for order #' . $order->get_id();
        }

        $output = ob_get_clean();

        $this->assertStringContainsString( 'No unique ID found for order #' . $order->get_id(), $output );
    }
}
