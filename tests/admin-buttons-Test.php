<?php

class Test_Admin_Buttons extends WP_UnitTestCase {

    public function test_admin_buttons_visibility_processing() {
        $order = wc_create_order();
        $order->update_meta_data( 'unique_id', '12345' );
        $order->set_status( 'processing' );
        $order->save();

        ob_start();
        column_html( 'axytos_actions', $order );
        $output = ob_get_clean();
        $this->assertStringContainsString( 'data-action="report_shipping"', $output );
    }

    public function test_admin_buttons_visibility_completed() {
        $order = wc_create_order();
        $order->update_meta_data( 'unique_id', '12345' );
        $order->set_status( 'completed' );
        // $order->save();

        ob_start();
        column_html( 'axytos_actions', $order );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'data-action="refund"', $output );
    }

    public function test_admin_buttons_visibility_cancelled() {
        $order = wc_create_order();
        $order->update_meta_data( 'unique_id', '12345' );
        $order->set_status( 'cancelled' );
        $order->save();

        ob_start();
        column_html( 'axytos_actions', $order );
        $output = ob_get_clean();

        $this->assertStringNotContainsString( 'data-action="', $output, 'Action buttons should not be present for cancelled orders.' );
    }

}
