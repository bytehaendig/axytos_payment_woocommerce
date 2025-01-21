<?php

class AxytosApiClient
{
    private $AxytosAPIKey;
    private $BaseUrl;

    public function __construct($AxytosAPIKey, $useSandbox = true)
    {
        $this->AxytosAPIKey = $AxytosAPIKey;
        // TODO: switch between sandbox and production
        $this->BaseUrl = $useSandbox ? 'https://api-sandbox.axytos.com/api/v1' : 'https://api.axytos.com/api/v1';
    }
    private function makeRequest($url, $method = 'GET', $data = [])
    {
        $headers = [
            'Content-type: application/json',
            'accept: application/json',
            'X-API-Key: '.$this->AxytosAPIKey,
        ];

        $ch = curl_init($this->BaseUrl . $url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status < 200 || $status >= 300) {
            // TODO: better error handling
            throw new Exception('Error in communication with Axytos');    
        }

        return $response;
    }
    public function invoicePrecheck($requestData)
    {
        $apiUrl = '/Payments/invoice/order/precheck';
        $response = $this->makeRequest($apiUrl, 'POST', $requestData);
        return $response;
    }
    public function orderConfirm($requestData)
    {
        $apiUrl = '/Payments/invoice/order/confirm';
        $response = $this->makeRequest($apiUrl, 'POST', $requestData);
        return $response;
    }
    public function updateShippingStatus($requestData)
    {
        $apiUrl = '/Payments/invoice/order/reportshipping';
        $response = $this->makeRequest($apiUrl, 'POST', $requestData);
        return $response;
    }
    public function returnItems($requestData)
    {
        $apiUrl = '/Payments/invoice/order/return';
        $response = $this->makeRequest($apiUrl, 'POST', $requestData);
        return $response;
    }
    public function refundOrder($requestData)
    {
        $apiUrl = '/Payments/invoice/order/refund';
        $response = $this->makeRequest($apiUrl, 'POST', $requestData);
        return $response;
    }
    public function createInvoice($requestData)
    {
        $apiUrl = '/Payments/invoice/order/createInvoice';
        $response = $this->makeRequest($apiUrl, 'POST', $requestData);
        return $response;
    }
    public function getPaymentStatus($orderID)
    {
        $apiUrl = '/Payments/invoice/order/paymentstate/' . $orderID;
        $response = $this->makeRequest($apiUrl);
        return $response;
    }
    public function cancelOrder($orderID)
    {
        $apiUrl = '/Payments/invoice/order/cancel/' . $orderID;
        $response = $this->makeRequest($apiUrl, 'POST');
        return $response;
    }
    public function getAgreement()
    {
        $apiUrl = '/StaticContent/creditcheckagreement';
        $response = $this->makeRequest($apiUrl);
        return $response;
    }
}

$AxytosAPIKey = getenv('AXYTOS_API_KEY');
$AxytosClient = new AxytosApiClient($AxytosAPIKey);

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


?>
