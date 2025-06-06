# Axytos WooCommerce Webhook Endpoint

This document describes the webhook endpoint implementation for the Axytos WooCommerce plugin, which allows external ERP systems to notify WooCommerce of order status changes and provide related data.

## Overview

The webhook endpoint enables secure communication between your ERP system and WooCommerce, allowing automatic order status updates, invoice number tracking, and shipment information synchronization.

## Endpoint Details

- **URL**: `/wp-json/axytos/v1/order-update`
- **Method**: `POST`
- **Content-Type**: `application/json`
- **Authentication**: API Key via custom header

## Authentication

The endpoint uses API key authentication for security:

1. Configure a webhook API key in WooCommerce Settings
2. Send the API key in the `X-Axytos-Webhook-Key` HTTP header
3. All requests without valid API keys will be rejected with 401/403 status codes

### Setting up Authentication

1. Go to **WooCommerce > Settings > Payments > Axytos**
2. Find the **Webhook API Key** field
3. Click **Generate Secure Key** or enter your own secure key
4. Save the settings
5. Use this key in your ERP system's webhook requests

## Request Format

### Required Parameters

- `order_id` (integer): The WooCommerce Order ID
- `new_status` (string): The new status from the ERP system

### Optional Parameters

- `curr_status` (string): The current order status (used for validation)
- `invoice_number` (string): Invoice number from ERP
- `tracking_number` (string): Tracking number for shipment

### Example Request

```json
{
    "order_id": 12345,
    "curr_status": "processing",
    "new_status": "shipped",
    "invoice_number": "INV-2023-001",
    "tracking_number": "1Z999AA1234567890"
}
```

## Response Format

### Success Response (200 OK)

```json
{
    "success": true,
    "message": "Order 12345 updated successfully."
}
```

### Error Responses

#### Invalid API Key (401/403)

```json
{
    "success": false,
    "message": "Invalid API key provided."
}
```

#### Order Not Found (404)

```json
{
    "success": false,
    "message": "Order with ID 12345 not found."
}
```

#### Status Conflict (409)

```json
{
    "success": false,
    "message": "Current order status (completed) does not match expected status (processing). Update may be outdated."
}
```

#### Validation Error (400)

```json
{
    "success": false,
    "message": "Order 12345 does not use Axytos payment method."
}
```

#### Server Error (500)

```json
{
    "success": false,
    "message": "Error processing webhook for order 12345: [error details]"
}
```

## Status Mapping

The webhook automatically maps ERP statuses to WooCommerce order statuses:

| ERP Status | WooCommerce Status |
|------------|-------------------|
| shipped    | completed         |
| invoiced   | processing        |
| cancelled  | cancelled         |
| refunded   | refunded          |
| on-hold    | on-hold          |
| pending    | pending          |
| processing | processing       |
| completed  | completed        |

You can customize this mapping using the `axytos_webhook_status_mapping` filter:

```php
add_filter('axytos_webhook_status_mapping', function($mapping) {
    $mapping['custom_status'] = 'wc-custom-status';
    return $mapping;
});
```

## Implementation Examples

### cURL Example

```bash
curl -X POST "https://yoursite.com/wp-json/axytos/v1/order-update" \
  -H "Content-Type: application/json" \
  -H "X-Axytos-Webhook-Key: your-webhook-api-key" \
  -d '{
    "order_id": 12345,
    "curr_status": "processing",
    "new_status": "shipped",
    "invoice_number": "INV-2023-001",
    "tracking_number": "1Z999AA1234567890"
  }'
```

### PHP Example

```php
$webhook_url = 'https://yoursite.com/wp-json/axytos/v1/order-update';
$api_key = 'your-webhook-api-key';

$data = [
    'order_id' => 12345,
    'curr_status' => 'processing',
    'new_status' => 'shipped',
    'invoice_number' => 'INV-2023-001',
    'tracking_number' => '1Z999AA1234567890'
];

$response = wp_remote_post($webhook_url, [
    'headers' => [
        'Content-Type' => 'application/json',
        'X-Axytos-Webhook-Key' => $api_key
    ],
    'body' => json_encode($data),
    'timeout' => 30
]);

$status_code = wp_remote_retrieve_response_code($response);
$body = wp_remote_retrieve_body($response);

if ($status_code === 200) {
    echo "Success: " . $body;
} else {
    echo "Error ({$status_code}): " . $body;
}
```

### JavaScript/Node.js Example

```javascript
const axios = require('axios');

const webhookUrl = 'https://yoursite.com/wp-json/axytos/v1/order-update';
const apiKey = 'your-webhook-api-key';

const data = {
    order_id: 12345,
    curr_status: 'processing',
    new_status: 'shipped',
    invoice_number: 'INV-2023-001',
    tracking_number: '1Z999AA1234567890'
};

axios.post(webhookUrl, data, {
    headers: {
        'Content-Type': 'application/json',
        'X-Axytos-Webhook-Key': apiKey
    }
})
.then(response => {
    console.log('Success:', response.data);
})
.catch(error => {
    console.error('Error:', error.response?.data || error.message);
});
```

## Security Features

- **API Key Authentication**: All requests must include a valid API key
- **Timing Attack Protection**: Uses `hash_equals()` for secure key comparison
- **Input Validation**: All parameters are validated and sanitized
- **Order Verification**: Only Axytos orders can be updated via webhook
- **Status Conflict Detection**: Prevents overwriting newer order data
- **Comprehensive Logging**: All webhook activity is logged for security monitoring

## Logging

All webhook activity is logged using WooCommerce's logging system:

- **Success**: Order updates, status changes
- **Warnings**: Status conflicts, unexpected conditions  
- **Errors**: Authentication failures, validation errors, processing errors

View logs at: **WooCommerce > Status > Logs** (filter by 'axytos-webhook')

## Order Data Storage

The webhook stores additional ERP data in order meta fields:

- `_axytos_erp_invoice_number`: Invoice number from ERP
- `_axytos_erp_tracking_number`: Tracking number from ERP
- `_axytos_erp_last_update`: Timestamp of last ERP update

This data is also added to order notes for visibility in the admin interface.

## Testing

A test utility is available for development and debugging:

1. Ensure `WP_DEBUG` is enabled
2. Access: `/wp-content/plugins/axytos-woocommerce/includes/webhook-test.php`
3. Use the interface to test webhook requests and generate API keys

**Note**: The test utility is only available when `WP_DEBUG` is enabled for security.

## Error Handling

The webhook implements comprehensive error handling:

1. **Authentication Errors**: Invalid or missing API keys
2. **Validation Errors**: Missing required parameters, invalid order IDs
3. **Business Logic Errors**: Order not found, wrong payment method, status conflicts
4. **System Errors**: Database issues, unexpected exceptions

All errors are logged with context information for debugging.

## Best Practices

1. **Secure API Keys**: Use strong, randomly generated API keys (64+ characters)
2. **HTTPS Only**: Always use HTTPS for webhook requests in production
3. **Retry Logic**: Implement exponential backoff for failed requests
4. **Idempotency**: Handle duplicate webhook calls gracefully
5. **Monitoring**: Monitor webhook logs for errors and security issues
6. **Testing**: Test webhook integration thoroughly before production deployment

## Troubleshooting

### Common Issues

**401/403 Authentication Error**
- Verify API key is correctly configured in WooCommerce settings
- Ensure API key is sent in `X-Axytos-Webhook-Key` header
- Check for typos in the API key

**404 Order Not Found**
- Verify the order ID exists in WooCommerce
- Ensure you're using the correct WooCommerce order ID (not order number)

**400 Invalid Payment Method**
- The order must use the Axytos payment method
- Only Axytos orders can be updated via webhook

**409 Status Conflict**
- The current order status doesn't match the expected `curr_status`
- This prevents overwriting newer order updates
- Check if the order was updated elsewhere

### Debug Steps

1. Check WooCommerce logs for detailed error information
2. Verify webhook URL is accessible (test with simple GET request)
3. Test with the built-in test utility
4. Validate JSON payload format
5. Confirm API key configuration

## Support

For technical support or questions about the webhook implementation:

1. Check the WooCommerce logs for detailed error information
2. Use the test utility to diagnose issues
3. Review this documentation for common solutions
4. Contact the plugin support team with specific error details