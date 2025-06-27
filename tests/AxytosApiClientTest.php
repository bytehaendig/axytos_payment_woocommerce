<?php

use Axytos\WooCommerce\AxytosApiClient;
use Axytos\WooCommerce\AxytosApiException;

class AxytosApiClientTest extends WP_UnitTestCase
{
    private $apiClient;
    private $testApiKey = 'test-api-key-12345';
    private $httpMockCallback;
    private $capturedRequests = [];

    public function setUp(): void
    {
        parent::setUp();
        
        // Mock WordPress functions
        if (!defined('AXYTOS_PLUGIN_VERSION')) {
            define('AXYTOS_PLUGIN_VERSION', '1.0.0');
        }
        if (!defined('WC_VERSION')) {
            define('WC_VERSION', '8.0.0');
        }
        
        // Remove any existing HTTP mock filters
        remove_all_filters('pre_http_request');
    }

    public function tearDown(): void
    {
        // Clean up HTTP mock filters
        remove_all_filters('pre_http_request');
        $this->httpMockCallback = null;
        $this->capturedRequests = [];
        
        parent::tearDown();
    }

    /**
     * Mock HTTP requests using WordPress HTTP API and capture request details
     */
    private function mockHttpRequest($statusCode, $body, $curlError = 0, $curlErrorMessage = '')
    {
        $this->httpMockCallback = function($preempt, $args, $url) use ($statusCode, $body, $curlError, $curlErrorMessage) {
            // Capture request details for verification
            $this->capturedRequests[] = [
                'url' => $url,
                'method' => $args['method'] ?? 'GET',
                'headers' => $args['headers'] ?? [],
                'body' => $args['body'] ?? null,
                'timeout' => $args['timeout'] ?? null,
                'user_agent' => $args['user-agent'] ?? null
            ];
            
            // Simulate cURL errors
            if ($curlError !== 0) {
                return new WP_Error($curlError, $curlErrorMessage);
            }
            
            return [
                'headers' => [],
                'body' => $body,
                'response' => [
                    'code' => $statusCode,
                    'message' => $this->getHttpStatusMessage($statusCode)
                ],
                'cookies' => [],
                'filename' => null
            ];
        };
        
        add_filter('pre_http_request', $this->httpMockCallback, 10, 3);
    }

    /**
     * Get the last captured request for verification
     */
    private function getLastRequest()
    {
        return end($this->capturedRequests);
    }

    /**
     * Assert that the last request matches expected parameters
     */
    private function assertLastRequestMatches($expectedUrl, $expectedMethod, $expectedData = null)
    {
        $lastRequest = $this->getLastRequest();
        
        $this->assertNotFalse($lastRequest, 'No HTTP request was captured');
        $this->assertStringEndsWith($expectedUrl, $lastRequest['url'], 'Request URL does not match');
        $this->assertEquals(strtoupper($expectedMethod), strtoupper($lastRequest['method']), 'Request method does not match');
        
        // Verify headers
        $this->assertArrayHasKey('X-API-Key', $lastRequest['headers'], 'API key header missing');
        $this->assertEquals($this->testApiKey, $lastRequest['headers']['X-API-Key'], 'API key does not match');
        $this->assertArrayHasKey('Content-Type', $lastRequest['headers'], 'Content-Type header missing');
        $this->assertEquals('application/json', $lastRequest['headers']['Content-Type'], 'Content-Type is not application/json');
        
        // Verify request body for POST requests
        if (strtoupper($expectedMethod) === 'POST' && $expectedData !== null) {
            $this->assertNotNull($lastRequest['body'], 'POST request body is missing');
            $decodedBody = json_decode($lastRequest['body'], true);
            $this->assertEquals($expectedData, $decodedBody, 'Request body does not match expected data');
        }
        
        // Verify User-Agent contains expected components
        $this->assertStringContainsString('AxytosWooCommercePlugin/', $lastRequest['user_agent'], 'User-Agent missing plugin info');
    }

    /**
     * Get HTTP status message for a given status code
     */
    private function getHttpStatusMessage($code)
    {
        $messages = [
            200 => 'OK',
            201 => 'Created',
            299 => 'Misc Success',
            300 => 'Multiple Choices',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable'
        ];
        
        return isset($messages[$code]) ? $messages[$code] : 'Unknown';
    }

    /**
     * Test constructor with sandbox environment (default)
     */
    public function test_constructor_sandbox_environment()
    {
        $client = new AxytosApiClient($this->testApiKey);
        
        // Use reflection to access private properties
        $reflection = new ReflectionClass($client);
        $baseUrlProperty = $reflection->getProperty('_BaseUrl');
        $baseUrlProperty->setAccessible(true);
        $apiKeyProperty = $reflection->getProperty('_AxytosAPIKey');
        $apiKeyProperty->setAccessible(true);
        
        $this->assertEquals('https://api-sandbox.axytos.com/api/v1', $baseUrlProperty->getValue($client));
        $this->assertEquals($this->testApiKey, $apiKeyProperty->getValue($client));
    }

    /**
     * Test constructor with production environment
     */
    public function test_constructor_production_environment()
    {
        $client = new AxytosApiClient($this->testApiKey, false);
        
        $reflection = new ReflectionClass($client);
        $baseUrlProperty = $reflection->getProperty('_BaseUrl');
        $baseUrlProperty->setAccessible(true);
        
        $this->assertEquals('https://api.axytos.com/api/v1', $baseUrlProperty->getValue($client));
    }

    /**
     * Test User-Agent generation
     */
    public function test_user_agent_generation()
    {
        $client = new AxytosApiClient($this->testApiKey);
        
        $reflection = new ReflectionClass($client);
        $userAgentProperty = $reflection->getProperty('_UserAgent');
        $userAgentProperty->setAccessible(true);
        $userAgent = $userAgentProperty->getValue($client);
        
        $this->assertStringContainsString('AxytosWooCommercePlugin/', $userAgent);
        $this->assertStringContainsString('PHP:', $userAgent);
        $this->assertStringContainsString('WP:', $userAgent);
        $this->assertStringContainsString('WC:', $userAgent);
    }

    /**
     * Data provider for API endpoint tests
     */
    public function apiEndpointProvider()
    {
        return [
            ['invoicePrecheck', '/Payments/invoice/order/precheck', 'POST'],
            ['orderConfirm', '/Payments/invoice/order/confirm', 'POST'],
            ['updateShippingStatus', '/Payments/invoice/order/reportshipping', 'POST'],
            ['returnItems', '/Payments/invoice/order/return', 'POST'],
            ['refundOrder', '/Payments/invoice/order/refund', 'POST'],
            ['createInvoice', '/Payments/invoice/order/createInvoice', 'POST'],
        ];
    }

    /**
     * Test successful API calls for POST endpoints with comprehensive verification
     *
     * @dataProvider apiEndpointProvider
     */
    public function test_api_endpoints_success($methodName, $expectedUrl, $expectedMethod)
    {
        // Mock HTTP request
        $this->mockHttpRequest(200, '{"success": true}');
        
        $client = new AxytosApiClient($this->testApiKey);
        $testData = ['test' => 'data'];
        
        $result = $client->$methodName($testData);
        
        // Verify response
        $this->assertEquals('{"success": true}', $result);
        
        // Verify the correct URL and method were used
        $this->assertLastRequestMatches($expectedUrl, $expectedMethod, $testData);
    }

    /**
     * Data provider for GET endpoint tests
     */
    public function getEndpointProvider()
    {
        return [
            ['getPaymentStatus', '/Payments/invoice/order/paymentstate/12345', 'GET', '12345', '{"status": "paid"}'],
            ['getAgreement', '/StaticContent/creditcheckagreement', 'GET', null, '{"agreement": "terms and conditions"}'],
        ];
    }

    /**
     * Test GET endpoints with URL parameter verification
     *
     * @dataProvider getEndpointProvider
     */
    public function test_get_endpoints_success($methodName, $expectedUrl, $expectedMethod, $parameter, $responseBody)
    {
        $this->mockHttpRequest(200, $responseBody);
        
        $client = new AxytosApiClient($this->testApiKey);
        
        if ($parameter !== null) {
            $result = $client->$methodName($parameter);
        } else {
            $result = $client->$methodName();
        }
        
        // Verify response
        $this->assertEquals($responseBody, $result);
        
        // Verify the correct URL and method were used
        $this->assertLastRequestMatches($expectedUrl, $expectedMethod);
    }

    /**
     * Test cancelOrder method (POST endpoint with order ID in URL)
     */
    public function test_cancel_order_with_url_parameter()
    {
        $this->mockHttpRequest(200, '{"cancelled": true}');
        
        $client = new AxytosApiClient($this->testApiKey);
        $orderId = '12345';
        
        $result = $client->cancelOrder($orderId);
        
        // Verify response
        $this->assertEquals('{"cancelled": true}', $result);
        
        // Verify the correct URL and method were used (POST with order ID in URL)
        $this->assertLastRequestMatches('/Payments/invoice/order/cancel/' . $orderId, 'POST');
    }

    /**
     * Data provider for HTTP error status codes
     */
    public function httpErrorStatusProvider()
    {
        return [
            [400, 'Bad Request'],
            [401, 'Unauthorized'],
            [403, 'Forbidden'],
            [404, 'Not Found'],
            [500, 'Internal Server Error'],
            [502, 'Bad Gateway'],
            [503, 'Service Unavailable'],
        ];
    }

    /**
     * Test error handling for various HTTP status codes
     *
     * @dataProvider httpErrorStatusProvider
     */
    public function test_http_error_handling($statusCode, $statusText)
    {
        $this->mockHttpRequest($statusCode, 'Error response');
        
        $client = new AxytosApiClient($this->testApiKey);
        
        $this->expectException(AxytosApiException::class);
        $this->expectExceptionMessage("HTTP error (Status-Code $statusCode)");
        
        try {
            $client->invoicePrecheck(['test' => 'data']);
        } catch (AxytosApiException $e) {
            $this->assertTrue($e->isHttpError(), 'Exception should be marked as HTTP error');
            $this->assertFalse($e->isConnectionError(), 'Exception should not be marked as connection error');
            throw $e; // Re-throw for expectException to catch
        }
    }

    /**
     * Test network timeout handling
     */
    public function test_network_timeout_handling()
    {
        // Mock HTTP request to return WP_Error (simulating network failure)
        $this->mockHttpRequest(0, false, 28, 'Operation timed out after 30000 milliseconds');
        
        $client = new AxytosApiClient($this->testApiKey);
        
        $this->expectException(AxytosApiException::class);
        $this->expectExceptionMessage('Connection error: Operation timed out after 30000 milliseconds');
        
        try {
            $client->invoicePrecheck(['test' => 'data']);
        } catch (AxytosApiException $e) {
            $this->assertTrue($e->isConnectionError(), 'Exception should be marked as connection error');
            $this->assertFalse($e->isHttpError(), 'Exception should not be marked as HTTP error');
            $context = $e->getErrorContext();
            $this->assertArrayHasKey('wp_error_code', $context, 'Error context should contain WP error code');
            $this->assertEquals(28, $context['wp_error_code'], 'WP error code should match');
            throw $e; // Re-throw for expectException to catch
        }
    }

    /**
     * Test connection failure scenarios
     */
    public function test_connection_failure_handling()
    {
        // Mock HTTP request to return WP_Error (simulating connection failure)
        $this->mockHttpRequest(0, false, 7, 'Could not connect to host');
        
        $client = new AxytosApiClient($this->testApiKey);
        
        $this->expectException(AxytosApiException::class);
        $this->expectExceptionMessage('Connection error: Could not connect to host');
        
        try {
            $client->invoicePrecheck(['test' => 'data']);
        } catch (AxytosApiException $e) {
            $this->assertTrue($e->isConnectionError(), 'Exception should be marked as connection error');
            $context = $e->getErrorContext();
            $this->assertArrayHasKey('wp_error_code', $context, 'Error context should contain WP error code');
            $this->assertEquals(7, $context['wp_error_code'], 'WP error code should match');
            throw $e; // Re-throw for expectException to catch
        }
    }

    /**
     * Test boundary status codes (199, 300)
     */
    public function test_boundary_status_codes()
    {
        // Test status 199 (should throw exception)
        $this->mockHttpRequest(199, 'Response');
        $client = new AxytosApiClient($this->testApiKey);
        
        $this->expectException(AxytosApiException::class);
        $this->expectExceptionMessage('HTTP error (Status-Code 199)');
        
        try {
            $client->invoicePrecheck(['test' => 'data']);
        } catch (AxytosApiException $e) {
            $this->assertTrue($e->isHttpError(), 'Exception should be marked as HTTP error');
            throw $e; // Re-throw for expectException to catch
        }
    }

    public function test_boundary_status_code_300()
    {
        // Test status 300 (should throw exception)
        $this->mockHttpRequest(300, 'Response');
        $client = new AxytosApiClient($this->testApiKey);
        
        $this->expectException(AxytosApiException::class);
        $this->expectExceptionMessage('HTTP error (Status-Code 300)');
        
        try {
            $client->invoicePrecheck(['test' => 'data']);
        } catch (AxytosApiException $e) {
            $this->assertTrue($e->isHttpError(), 'Exception should be marked as HTTP error');
            throw $e; // Re-throw for expectException to catch
        }
    }

    /**
     * Test successful status codes (200, 201, 299)
     */
    public function test_successful_status_codes()
    {
        $successCodes = [200, 201, 299];
        
        foreach ($successCodes as $code) {
            $this->mockHttpRequest($code, '{"success": true}');
            $client = new AxytosApiClient($this->testApiKey);
            
            $result = $client->invoicePrecheck(['test' => 'data']);
            $this->assertEquals('{"success": true}', $result);
        }
    }

    /**
     * Test with empty request data
     */
    public function test_empty_request_data()
    {
        $this->mockHttpRequest(200, '{"success": true}');
        
        $client = new AxytosApiClient($this->testApiKey);
        
        $result = $client->invoicePrecheck([]);
        $this->assertEquals('{"success": true}', $result);
    }

    /**
     * Test with null request data
     */
    public function test_null_request_data()
    {
        $this->mockHttpRequest(200, '{"success": true}');
        
        $client = new AxytosApiClient($this->testApiKey);
        
        $result = $client->invoicePrecheck(null);
        $this->assertEquals('{"success": true}', $result);
    }

    /**
     * Test with complex request data
     */
    public function test_complex_request_data()
    {
        $this->mockHttpRequest(200, '{"success": true}');
        
        $client = new AxytosApiClient($this->testApiKey);
        
        $complexData = [
            'order' => [
                'id' => 12345,
                'items' => [
                    ['name' => 'Product 1', 'price' => 29.99],
                    ['name' => 'Product 2', 'price' => 19.99]
                ],
                'customer' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com'
                ]
            ]
        ];
        
        $result = $client->invoicePrecheck($complexData);
        $this->assertEquals('{"success": true}', $result);
    }

    /**
     * Test order ID with different data types
     */
    public function test_order_id_data_types()
    {
        $this->mockHttpRequest(200, '{"success": true}');
        
        $client = new AxytosApiClient($this->testApiKey);
        
        // Test with string order ID
        $result = $client->getPaymentStatus('12345');
        $this->assertEquals('{"success": true}', $result);
        
        // Test with integer order ID
        $result = $client->getPaymentStatus(12345);
        $this->assertEquals('{"success": true}', $result);
        
        // Test with string order ID for cancel
        $result = $client->cancelOrder('67890');
        $this->assertEquals('{"success": true}', $result);
        
        // Test with integer order ID for cancel
        $result = $client->cancelOrder(67890);
        $this->assertEquals('{"success": true}', $result);
    }

}