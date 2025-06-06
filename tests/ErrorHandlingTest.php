<?php

class ErrorHandlingTest extends WP_UnitTestCase {
    public function test_process_payment_error() {
        $gateway = $this->createMock( \Axytos\WooCommerce\AxytosPaymentGateway::class );

        $order = wc_create_order();

        $gateway->expects( $this->once() )
        ->method( 'process_payment' )
        ->with( $order->get_id() )
        ->willThrowException( new Exception( 'Payment processing failed.' ) );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Payment processing failed.' );

        $gateway->process_payment( $order->get_id() );
    }
}
