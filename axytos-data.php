<?php

function createBasketData($order, $withTaxGroups = false) {
  $data = [
    "netTotal" => round($order->get_subtotal(), 2),
    "grossTotal" => round($order->get_total(), 2),
    "currency" => $order->get_currency(),
    "positions" => array_values(array_map(function($item) {
      $quantity = $item->get_quantity();
      $netPrice = $item->get_subtotal();
      $tax = $item->get_subtotal_tax();
      $grossPrice = $netPrice + $tax;
      $taxRate = ($grossPrice / $netPrice) - 1;
      return [
        "productId" => $item->get_product_id(),
        "productName" => $item->get_name(),
        "productCategory" => "General",
        "quantity" => $quantity,
        "taxPercent" => round($taxRate * 100, 1),
        "netPricePerUnit" => $quantity > 0 ? round($netPrice / $quantity, 2) : 0,
        "grossPricePerUnit" => $quantity > 0 ? round($grossPrice / $quantity, 2) : 0,
        "netPositionTotal" => round($netPrice, 2),
        "grossPositionTotal" => round($grossPrice, 2),
      ];
    }, $order->get_items())),
  ];
  if ($withTaxGroups) {
    $data["taxGroups"] = [
      [
        "taxPercent" => $order->get_total_tax() > 0 ? round($order->get_total_tax() / $order->get_subtotal() * 100, 2) : 0,
        "valueToTax" => (float) $order->get_subtotal(),
        "total" => (float) $order->get_total_tax()
      ]
    ];
  }
  return $data;
}

function createOrderData($order) {
  return [
    "personalData" => [
      "externalCustomerId" => (string) $order->get_user_id(),
      "language" => get_locale(),
      "email" => $order->get_billing_email(),
      "mobilePhoneNumber" => $order->get_billing_phone(),
    ],
    "invoiceAddress" => [
      "company" => $order->get_billing_company(),
      "firstname" => $order->get_billing_first_name(),
      "lastname" => $order->get_billing_last_name(),
      "zipCode" => $order->get_billing_postcode(),
      "city" => $order->get_billing_city(),
      // TODO: hardcoded ??
      "country" => "DE",
      "addressLine1" => $order->get_billing_address_1(),
      "addressLine2" => $order->get_billing_address_2(),
    ],
    "deliveryAddress" => [
      "company" => $order->get_shipping_company(),
      "firstname" => $order->get_shipping_first_name(),
      "lastname" => $order->get_shipping_last_name(),
      "zipCode" => $order->get_shipping_postcode() ?: "00000",
      "city" => $order->get_shipping_city() ?: "Unknown",
      "country" => $order->get_shipping_country() ?: "DE",
      "addressLine1" => $order->get_shipping_address_1(),
      "addressLine2" => $order->get_shipping_address_2(),
    ],
    "basket" => createBasketData($order),
  ];
}

function createPrecheckData($order) {
  $orderData = createOrderData($order);
  $precheckData = [
    "requestMode" => "SingleStep",
    "customReference" => $order->get_order_number(),
    "paymentTypeSecurity" => "S", // Include this field
    "selectedPaymentType" => "", // Include this field
    "proofOfInterest" => "AAE", // Include this field
  ];
  return array_merge($orderData, $precheckData);
}

function createConfirmData($order) {
  $orderData = createOrderData($order);
  $unique_id = $order->get_meta('unique_id');
  //data for confirm order
  $response_body = json_decode($order->get_meta('precheck_response'), true);
  $confirmData = [
    "customReference" => $order->get_order_number(),
    "externalOrderId" => $unique_id,
    "date" => date('c'),
    "orderPrecheckResponse" => $response_body
  ];
  return array_merge($orderData, $confirmData);
}

function createInvoiceData($order) {
  $unique_id = $order->get_meta('unique_id');
  return [
    "externalorderId" => $unique_id,
    "externalInvoiceNumber" => $order->get_order_number(), 
    "externalInvoiceDisplayName" => sprintf("Invoice #%s", $order->get_order_number()),
    "externalSubOrderId" => "", 
    "date" => date('c', strtotime($order->get_date_created())), // Order creation date in ISO 8601
    "dueDateOffsetDays" => 14, 
    "basket" => createBasketData($order, true),
  ];
}

function createShippingData($order) {
  $unique_id = $order->get_meta('unique_id');
  $order_id = $order->get_id();
  return [
    "externalOrderId" => $unique_id,
    "externalSubOrderId" => $order_id,
    "basketPositions" => array_values(array_map(function ($item) {
      return [
        "productId" => $item->get_product_id(),
        "quantity" => $item->get_quantity(),
      ];
    }, $order->get_items())),
    "shippingDate" => date('c'),
  ];
}

function createRefundData($order) {
  $unique_id = $order->get_meta('unique_id');
  $invoice_number = $order->get_meta('axytos_invoice_number');
  $order_id = $order->get_id();
  return [
    "externalOrderId" => $unique_id,
    "refundDate" => date('c'),
    "originalInvoiceNumber" => $invoice_number,
    "externalSubOrderId" => $order_id,
    "basket" => [
      "grossTotal" => $order->get_total(),
      "netTotal" => $order->get_subtotal(),
      "positions" => array_values(array_map(function ($item) {
        return [
          "productId" => $item->get_product_id(),
          "netRefundTotal" => $item->get_total() - $item->get_total_tax(),
          "grossRefundTotal" => $item->get_total(),
        ];
      }, $order->get_items())),
      "taxGroups" => [],
    ],
  ];
}
