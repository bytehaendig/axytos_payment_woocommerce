<?php

add_action( 'woocommerce_blocks_loaded', 'rudr_gateway_block_support' );

function rudr_gateway_block_support() {
  if( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
    error_log('--- Axytos Debug --- AbstractPaymentMethodType class not found.');
    return;
  }
  if( ! file_exists(  __DIR__ . '/axytos-wc-blocks-payment-gateway.php' ) ) {
    error_log('--- Axytos Debug --- axytos-wc-blocks-payment-gateway file not found.');
    return;
  }

  // here we're including our "gateway block support class"
  require_once __DIR__ . '/axytos-wc-blocks-payment-gateway.php';

  // registering the PHP class we have just included
  add_action(
    'woocommerce_blocks_payment_method_type_registration',
    function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
      $payment_method_registry->register( new WC_Axytos_Blocks_Gateway() );
    }
  );
}
