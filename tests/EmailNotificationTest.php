<?php

class EmailNotificationTest extends WP_UnitTestCase {
    public function test_custom_email_notification() {
        $order = wc_create_order();
        $order->set_payment_method( 'axytoswc' );
        $order->save();

        do_action( 'woocommerce_order_status_on-hold', $order->get_id() );
        $this->assertTrue( has_action( 'woocommerce_email_order_details' ) );
    }
}

