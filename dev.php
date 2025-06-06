<?php

require_once __DIR__ . '/axytos-class.php';


$AxytosAPIKey = getenv('AXYTOS_API_KEY');
$AxytosClient = new \Axytos\WooCommerce\AxytosApiClient($AxytosAPIKey, true);

$result = "{}";
// Precheck request
$data = [
    "requestMode" => "SingleStep",
    "customReference" => "string",
    "personalData" => [
        "externalCustomerId" => "string",
        "language" => "string",
        "dateOfBirth" => "2004-11-17T09:30:16.562Z",
        "gender" => "M",
        "email" => "string",
        "fixNetPhoneNumber" => "string",
        "mobilePhoneNumber" => "string",
        "company" => [
            "number" => "string",
            "legalForm" => "string",
            "uid" => "string",
            "foundationDate" => "2014-11-17T09:30:16.562Z"
        ]
    ],
    "proofOfInterest" => "AAE",
    "selectedPaymentType" => "string",
    "paymentTypeSecurity" => "S",
    "invoiceAddress" => [
        "company" => "string",
        "salutation" => "string",
        "firstname" => "string",
        "lastname" => "string",
        "zipCode" => "12345",
        "city" => "string",
        "region" => "string",
        "country" => "AT",
        "vatId" => "string",
        "addressLine1" => "string",
        "addressLine2" => "string",
        "addressLine3" => "string",
        "addressLine4" => "string"
    ],
    "deliveryAddress" => [
        "salutation" => "string",
        "company" => "string",
        "firstname" => "string",
        "lastname" => "string",
        "zipCode" => "string",
        "city" => "string",
        "region" => "string",
        "country" => "AT",
        "vatId" => "string",
        "addressLine1" => "string",
        "addressLine2" => "string",
        "addressLine3" => "string",
        "addressLine4" => "string"
    ],
    "basket" => [
        "netTotal" => 0.001,
        "grossTotal" => 0.01,
        "currency" => "string",
        "positions" => [
            [
                "productId" => "string",
                "productName" => "string",
                "productCategory" => "string",
                "quantity" => 0,
                "taxPercent" => 0,
                "netPricePerUnit" => 0,
                "grossPricePerUnit" => 0,
                "netPositionTotal" => 0,
                "grossPositionTotal" => 0
            ]
        ]
    ]
];
// $result = $AxytosClient->invoicePrecheck($data);

//stored a precheck response
// $result = '{
//   "approvedPaymentTypeSecurities": [
//     "S",
//     "U"
//   ],
//   "processId": "1132901",
//   "decision": "U",
//   "transactionMetadata": {
//     "transactionId": "T-AXY-2024-11-15T13:25:07-9852E278-1DB6-4523-B874-694B8BAAD0EC",
//     "transactionInfoSignature": "J93ehEdlxk/WxIV1u/Yhd5Rj1H3WPyTrK3880M7uk8A=",
//     "transactionTimestamp": "2024-11-15T13:25:07Z",
//     "transactionExpirationTimestamp": "2024-11-15T13:40:07Z"
//   },
//   "step": "Order 1/1",
//   "riskTaker": "CoveragePartner"
// }';

// Order Confirm
$confirmdata = [
     "requestMode" => "SingleStep",
    "customReference" => "string",
    "personalData" => [
        "externalCustomerId" => "string",
        "language" => "string",
        "dateOfBirth" => "2004-11-17T09:30:16.562Z",
        "gender" => "M",
        "email" => "string",
        "fixNetPhoneNumber" => "string",
        "mobilePhoneNumber" => "string",
        "company" => [
            "number" => "string",
            "legalForm" => "string",
            "uid" => "string",
            "foundationDate" => "2014-11-17T09:30:16.562Z"
        ]
    ],
    "proofOfInterest" => "AAE",
    "selectedPaymentType" => "string",
    "paymentTypeSecurity" => "S",
    "invoiceAddress" => [
        "company" => "string",
        "salutation" => "string",
        "firstname" => "string",
        "lastname" => "string",
        "zipCode" => "12345",
        "city" => "string",
        "region" => "string",
        "country" => "AT",
        "vatId" => "string",
        "addressLine1" => "string",
        "addressLine2" => "string",
        "addressLine3" => "string",
        "addressLine4" => "string"
    ],
    "deliveryAddress" => [
        "salutation" => "string",
        "company" => "string",
        "firstname" => "string",
        "lastname" => "string",
        "zipCode" => "string",
        "city" => "string",
        "region" => "string",
        "country" => "AT",
        "vatId" => "string",
        "addressLine1" => "string",
        "addressLine2" => "string",
        "addressLine3" => "string",
        "addressLine4" => "string"
    ],
    "basket" => [
        "netTotal" => 0.001,
        "grossTotal" => 0.01,
        "currency" => "string",
        "positions" => [
            [
                "productId" => "string",
                "productName" => "string",
                "productCategory" => "string",
                "quantity" => 0,
                "taxPercent" => 0,
                "netPricePerUnit" => 0,
                "grossPricePerUnit" => 0,
                "netPositionTotal" => 0,
                "grossPositionTotal" => 0
            ]
        ]
    ],
    "orderPrecheckResponse" => json_decode($result)
];
// $result = $AxytosClient->orderConfirm($confirmdata);


// Create new Invoice
$orderData = [
    "externalOrderId" => "string",
    "externalInvoiceNumber" => "string",
    "externalInvoiceDisplayName" => "string",
    "externalSubOrderId" => "string",
    "date" => "2024-11-09T07:53:09.567Z",
    "dueDateOffsetDays" => 0,
    "basket" => [
        "grossTotal" => 0,
        "netTotal" => 0,
        "positions" => [
            [
                "productId" => "string",
                "quantity" => 0,
                "taxPercent" => 0,
                "netPricePerUnit" => 0,
                "grossPricePerUnit" => 0,
                "netPositionTotal" => 0,
                "grossPositionTotal" => 0
            ]
        ],
        "taxGroups" => [
            [
                "taxPercent" => 0,
                "valueToTax" => 0,
                "total" => 0
            ]
        ]
    ]
];
// $result = $AxytosClient->createInvoice($orderData);

// Update Shipping status
$statusData = [
    "externalOrderId" => "string",
    "externalSubOrderId" => "string",
    "basketPositions" => [
        [
            "productId" => "string",
            "quantity" => 0
        ]
    ],
    "shippingDate" => "2024-11-09T08:03:18.933Z"
];
// $result = $AxytosClient->updateShippingStatus($statusData);

// Return Product
$returnData = [
    "externalOrderId" => "string",
    "externalSubOrderId" => "string",
    "basketPositions" => [
        [
            "productId" => "string",
            "quantity" => 0
        ]
    ],
    "shippingDate" => "2024-11-09T08:03:18.933Z"
];
// $result = $AxytosClient->returnItems($returnData);

// Refund Order
$refundData = [
    "externalOrderId" => "string",
    "refundDate" => "2024-11-20T14:56:44.751Z",
    "originalInvoiceNumber" => "string",
    "externalSubOrderId" => "string",
    "basket" => [
        "grossTotal" => 0.01,
        "netTotal" => 0.001,
        "positions" => [
            [
                "productId" => "string",
                "netRefundTotal" => 0,
                "grossRefundTotal" => 0
            ]
        ],
        "taxGroups" => [
            [
                "taxPercent" => 0,
                "valueToTax" => 0,
                "total" => 0
            ]
        ]
    ]
];

// $result = $AxytosClient->refundOrder($refundData);

$orderID = "string";
// $result = $AxytosClient->getPaymentStatus($orderID);
// $result = $AxytosClient->cancelOrder($orderID);

