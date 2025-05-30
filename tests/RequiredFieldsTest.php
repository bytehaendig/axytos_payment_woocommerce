<?php

class RequiredFieldValidationTest extends WP_UnitTestCase {
    public function test_required_fields_validation() {
        $gateway = $this->createMock( AxytosPaymentGateway::class );
        $order = wc_create_order();

        $gateway->expects( $this->once() )
        ->method( 'process_payment' )
        ->with( $order->get_id() )
        ->willThrowException( new Exception( 'Required fields missing.' ) );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Required fields missing.' );

        $gateway->process_payment( $order->get_id() );
    }
}
