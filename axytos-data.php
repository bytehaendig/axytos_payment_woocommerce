<?php

// TODO: combine create baseket data functions
function createBasketData($order) {
  return [
    "netTotal" => round($order->get_subtotal(), 2),
    "grossTotal" => round($order->get_total(), 2),
    "currency" => $order->get_currency(),
    "positions" => array_values(array_map(function($item) use (&$taxGroups) {
      $quantity = $item->get_quantity();
      $netPrice = $item->get_subtotal();
      $tax = $item->get_subtotal_tax();
      $grossPrice = $netPrice + $tax;
      $taxRate = ($grossPrice / $netPrice) - 1;
      $taxPercent = round($taxRate * 100, 1);
      return [
        "productId" => $item->get_product_id(),
        "productName" => $item->get_name(),
        // TODO: get real category name
        "productCategory" => "General",
        "quantity" => $quantity,
        "taxPercent" => $taxPercent,
        "netPricePerUnit" => $quantity > 0 ? round($netPrice / $quantity, 2) : 0,
        "grossPricePerUnit" => $quantity > 0 ? round($grossPrice / $quantity, 2) : 0,
        "netPositionTotal" => round($netPrice, 2),
        "grossPositionTotal" => round($grossPrice, 2),
      ];
    }, $order->get_items())),
  ];
}

function createInvoiceBasketData($order) {
  $taxGroups = [];
  return [
    "netTotal" => round($order->get_subtotal(), 2),
    "grossTotal" => round($order->get_total(), 2),
    "positions" => array_values(array_map(function($item) use (&$taxGroups) {
      $quantity = $item->get_quantity();
      $netPrice = $item->get_subtotal();
      $tax = $item->get_subtotal_tax();
      $grossPrice = $netPrice + $tax;
      $taxRate = ($grossPrice / $netPrice) - 1;
      $taxPercent = round($taxRate * 100, 1);
      if (!array_key_exists($taxPercent, $taxGroups)) {
        $taxGroups[$taxPercent] = [];
      }
      $taxGroups[$taxPercent][] = ["tax" => $tax, "value" => $netPrice];
      return [
        "productId" => $item->get_product_id(),
        "quantity" => $quantity,
        "taxPercent" => $taxPercent,
        "netPricePerUnit" => $quantity > 0 ? round($netPrice / $quantity, 2) : 0,
        "grossPricePerUnit" => $quantity > 0 ? round($grossPrice / $quantity, 2) : 0,
        "netPositionTotal" => round($netPrice, 2),
        "grossPositionTotal" => round($grossPrice, 2),
      ];
    }, $order->get_items())),
    "taxGroups" => array_map(function ($taxPercent, $taxes) {
      $valueToTax = array_reduce($taxes, function ($acc, $tax) {
        return $acc + $tax["value"];
      }, 0);
      $total = array_reduce($taxes, function ($acc, $tax) {
        return $acc + $tax["tax"];
      }, 0);
      return [
        "taxPercent" => $taxPercent,
        "valueToTax" => round($valueToTax, 2),
        "total" => round($total, 2),
      ];
    }, array_keys($taxGroups), $taxGroups),
  ];
}

function createRefundBasketData($order) {
  $taxGroups = [];
  return [
    "grossTotal" => $order->get_total(),
    "netTotal" => $order->get_subtotal(),
    "positions" => array_values(array_map(function ($item) use (&$taxGroups) {
      $netPrice = $item->get_subtotal();
      $tax = $item->get_subtotal_tax();
      $grossPrice = $netPrice + $tax;
      $taxRate = ($grossPrice / $netPrice) - 1;
      $taxPercent = round($taxRate * 100, 1);
      if (!array_key_exists($taxPercent, $taxGroups)) {
        $taxGroups[$taxPercent] = [];
      }
      $taxGroups[$taxPercent][] = ["tax" => $tax, "value" => $netPrice];
      return [
        "productId" => $item->get_product_id(),
        "netRefundTotal" => round($netPrice, 2),
        "grossRefundTotal" => round($grossPrice, 2),
      ];
    }, $order->get_items())),
    "taxGroups" => array_map(function ($taxPercent, $taxes) {
      $valueToTax = array_reduce($taxes, function ($acc, $tax) {
        return $acc + $tax["value"];
      }, 0);
      $total = array_reduce($taxes, function ($acc, $tax) {
        return $acc + $tax["tax"];
      }, 0);
      return [
        "taxPercent" => $taxPercent,
        "valueToTax" => round($valueToTax, 2),
        "total" => round($total, 2),
      ];
    }, array_keys($taxGroups), $taxGroups),
  ];
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
      "country" => $order->get_billing_country(),
      "addressLine1" => $order->get_billing_address_1(),
      "addressLine2" => $order->get_billing_address_2(),
    ],
    "deliveryAddress" => [
      "company" => $order->get_shipping_company(),
      "firstname" => $order->get_shipping_first_name(),
      "lastname" => $order->get_shipping_last_name(),
      "zipCode" => $order->get_shipping_postcode(),
      "city" => $order->get_shipping_city(),
      "country" => $order->get_shipping_country(),
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
  //data for confirm order
  $response_body = json_decode($order->get_meta('precheck_response'), true);
  $confirmData = [
    "customReference" => $order->get_order_number(),
    "externalOrderId" => $order->get_order_number(),
    "date" => date('c'),
    "orderPrecheckResponse" => $response_body
  ];
  return array_merge($orderData, $confirmData);
}

function createInvoiceData($order) {
  return [
    "externalorderId" => $order->get_order_number(),
    "externalInvoiceNumber" => $order->get_order_number(), 
    "externalInvoiceDisplayName" => sprintf("Invoice #%s", $order->get_order_number()),
    "externalSubOrderId" => "", 
    "date" => date('c', strtotime($order->get_date_created())), // Order creation date in ISO 8601
    "dueDateOffsetDays" => 14, 
    "basket" => createInvoiceBasketData($order),
  ];
}

function createShippingData($order) {
  return [
    "externalOrderId" => $order->get_order_number(),
    // TODO: clarify meaning of externalSubOrderId
    "externalSubOrderId" => $order->get_order_number(),
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
  $invoice_number = $order->get_meta('axytos_invoice_number');
  return [
    "externalOrderId" => $order->get_order_number(),
    "refundDate" => date('c'),
    "originalInvoiceNumber" => $invoice_number,
    "externalSubOrderId" => $order->get_order_number(),
    "basket" => createRefundBasketData($order),
  ];
}
