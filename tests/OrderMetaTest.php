<?php

class OrderMetaTest extends WP_UnitTestCase {
    public function test_order_meta() {
        $order = wc_create_order();
        $order->update_meta_data( 'unique_id', '12345' );
        $order->save();

        $this->assertEquals( '12345', $order->get_meta( 'unique_id' ) );
    }
}
