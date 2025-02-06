<?php

class ProcessPaymentTest extends WP_UnitTestCase {
    public function test_process_payment_success() {

        $gateway = $this->createMock( WC_Axytos_Payment_Gateway::class );

        $order = wc_create_order();

        $result = [
            'result' => 'success',
            'redirect' => 'http://axontech.pk',
        ];

        $gateway->expects( $this->once() )
        ->method( 'process_payment' )
        ->with( $order->get_id() )
        ->willReturn( $result );

        $actualResult = $gateway->process_payment( $order->get_id() );

        $this->assertArrayHasKey( 'result', $actualResult );
        $this->assertEquals( 'success', $actualResult[ 'result' ] );
        $this->assertArrayHasKey( 'redirect', $actualResult );
        $this->assertNotEmpty( $actualResult[ 'redirect' ] );
    }
}
