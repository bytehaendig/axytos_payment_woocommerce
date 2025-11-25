<?php
/**
 * Data formatting functions for Axytos API integration.
 *
 * @package Axytos\WooCommerce
 */

namespace Axytos\WooCommerce;

/**
 * Create basket data for Axytos API.
 *
 * @param \WC_Order $order The order to create basket data for.
 * @param string    $style The style of basket data ('order', 'invoice', 'refund').
 * @return array Formatted basket data.
 */
function createBasketData( $order, $style = 'order' ) {
	assert( $style === 'order' || $style === 'invoice' || $style === 'refund' );
	$with_tax_groups = false;
	if ( $style === 'invoice' || $style === 'refund' ) {
		$with_tax_groups = true;
	}
	$groups     = array();
	$tax        = $order->get_total_tax();
	$grossTotal = $order->get_total();
	$netTotal   = $grossTotal - $tax;
	$positions  = array_values(
		array_map(
			function ( $item ) use ( &$groups, $style, $order ) {
				$quantity       = $item->get_quantity();
				$netPrice       = $order->get_item_total( $item, false );
				$tax            = $order->get_item_tax( $item );
				$grossPrice     = $order->get_item_total( $item, true );
				$taxRate        = ! $netPrice ? 0 : ( $grossPrice / $netPrice ) - 1;
				$taxPercent     = round( $taxRate * 100, 1 );
				$lineNetPrice   = $order->get_line_total( $item, false );
				$lineGrossPrice = $order->get_line_total( $item, true );
				$productId      = $item->get_type() === 'line_item' ? $item->get_product_id() : 0;
				if ( ! array_key_exists( $taxPercent, $groups ) ) {
					$groups[ $taxPercent ] = array();
				}
				$groups[ $taxPercent ][] = array(
					'tax'   => $tax,
					'value' => $netPrice,
				);
				if ( $style === 'invoice' ) {
					return array(
						'productId'          => $productId,
						'quantity'           => $quantity,
						'taxPercent'         => $taxPercent,
						'netPricePerUnit'    => $netPrice,
						'grossPricePerUnit'  => $grossPrice,
						'netPositionTotal'   => $lineNetPrice,
						'grossPositionTotal' => $lineGrossPrice,
					);
				}
				if ( $style === 'refund' ) {
					return array(
						'productId'        => $productId,
						'netRefundTotal'   => $lineNetPrice,
						'grossRefundTotal' => $lineGrossPrice,
					);
				}
				return array(
					'productId'          => $productId,
					'productName'        => $item->get_name(),
					// TODO: get real category name
					'productCategory'    => 'General',
					'quantity'           => $quantity,
					'taxPercent'         => $taxPercent,
					'netPricePerUnit'    => $netPrice,
					'grossPricePerUnit'  => $grossPrice,
					'netPositionTotal'   => $lineNetPrice,
					'grossPositionTotal' => $lineGrossPrice,
				);
			},
			$order->get_items( array( 'line_item', 'shipping', 'fee' ) )
		)
	);
	$result     = array(
		'netTotal'   => $netTotal,
		'grossTotal' => $grossTotal,
		'positions'  => $positions,
	);
	if ( $with_tax_groups ) {
		$taxGroups = array();
		foreach ( $groups as $taxPercent => $taxes ) {
			$valueToTax = array_reduce(
				$taxes,
				function ( $acc, $tax ) {
					return $acc + $tax['value'];
				},
				0
			);
			$total      = array_reduce(
				$taxes,
				function ( $acc, $tax ) {
					return $acc + $tax['tax'];
				},
				0
			);
			if ( $total ) {
				$taxGroups[] = array(
					'taxPercent' => $taxPercent,
					'valueToTax' => round( $valueToTax, 2 ),
					'total'      => round( $total, 2 ),
				);
			}
		}
		$result['taxGroups'] = $taxGroups;
	}
	if ( $style === 'order' ) {
		$result['currency'] = $order->get_currency();
	}
	return $result;
}

function createOrderData( $order ) {
	$customerId = $order->get_user_id();
	if ( $customerId === 0 ) {
		$customerId = $order->get_billing_email();
	}
	return array(
		'personalData'    => array(
			'externalCustomerId' => (string) $customerId,
			'language'           => get_locale(),
			'email'              => $order->get_billing_email(),
			'mobilePhoneNumber'  => $order->get_billing_phone(),
		),
		'invoiceAddress'  => array(
			'company'      => $order->get_billing_company(),
			'firstname'    => $order->get_billing_first_name(),
			'lastname'     => $order->get_billing_last_name(),
			'zipCode'      => $order->get_billing_postcode(),
			'city'         => $order->get_billing_city(),
			'country'      => $order->get_billing_country(),
			'addressLine1' => $order->get_billing_address_1(),
			'addressLine2' => $order->get_billing_address_2(),
		),
		'deliveryAddress' => array(
			'company'      => $order->get_shipping_company(),
			'firstname'    => $order->get_shipping_first_name(),
			'lastname'     => $order->get_shipping_last_name(),
			'zipCode'      => $order->get_shipping_postcode(),
			'city'         => $order->get_shipping_city(),
			'country'      => $order->get_shipping_country(),
			'addressLine1' => $order->get_shipping_address_1(),
			'addressLine2' => $order->get_shipping_address_2(),
		),
		'basket'          => createBasketData( $order, 'order' ),
	);
}

function createPrecheckData( $order ) {
	$orderData    = createOrderData( $order );
	$precheckData = array(
		'requestMode'         => 'SingleStep',
		'paymentTypeSecurity' => 'U', // Include this field
		'selectedPaymentType' => '', // Include this field
		'proofOfInterest'     => 'AAE', // Include this field
	);
	return array_merge( $orderData, $precheckData );
}

function createConfirmData( $order ) {
	$orderData = createOrderData( $order );
	// data for confirm order
	$response_body = json_decode( $order->get_meta( 'precheck_response' ), true );
	$confirmData   = array(
		'externalOrderId'       => $order->get_order_number(),
		'date'                  => date( 'c' ),
		'orderPrecheckResponse' => $response_body,
	);
	return array_merge( $orderData, $confirmData );
}

function createInvoiceData( $order, $invoice_number = null ) {
	$externalInvoiceNumber = $invoice_number ?: 'ORDER:' . $order->get_order_number();

	return array(
		'externalOrderId'            => $order->get_order_number(),
		'externalInvoiceNumber'      => $externalInvoiceNumber,
		// TODO: clarify meaning of externalInvoiceDisplayName
		'externalInvoiceDisplayName' => $externalInvoiceNumber,
		'externalSubOrderId'         => '',
		'date'                       => date( 'c' ), // Order creation date in ISO 8601
		'dueDateOffsetDays'          => 14,
		'basket'                     => createBasketData( $order, 'invoice' ),
	);
}

function createShippingData( $order ) {
	return array(
		'externalOrderId'    => $order->get_order_number(),
		// TODO: clarify meaning of externalSubOrderId
		'externalSubOrderId' => '',
		'basketPositions'    => array_values(
			array_map(
				function ( $item ) {
					return array(
						'productId' => $item->get_product_id(),
						'quantity'  => $item->get_quantity(),
					);
				},
				$order->get_items()
			)
		),
		'shippingDate'       => date( 'c' ),
	);
}

function createRefundData( $order ) {
	$invoice_number = $order->get_meta( 'axytos_ext_invoice_nr' );
	if ( empty( $invoice_number ) ) {
		// backward compatibility
		$invoice_number = $order->get_meta( 'axytos_invoice_number' );
	}
	return array(
		'externalOrderId'       => $order->get_order_number(),
		'refundDate'            => date( 'c' ),
		'originalInvoiceNumber' => $invoice_number,
		'externalSubOrderId'    => '',
		'basket'                => createBasketData( $order, 'refund' ),
	);
}
