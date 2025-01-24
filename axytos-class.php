<?php

class AxytosApiClient
{
    private $AxytosAPIKey;
    private $BaseUrl;

    public function __construct($AxytosAPIKey, $useSandbox = true)
    {
        $this->AxytosAPIKey = $AxytosAPIKey;
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

