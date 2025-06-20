# Axytos WooCommerce Payment Gateway Developer Documentation

This document provides a technical overview of the Axytos WooCommerce Payment Gateway plugin, intended for developers who will be working on the codebase.

## Project Structure

The plugin is organized into the following directories:

*   **`axytos-woocommerce.php`**: The main plugin file and entry point.
*   **`includes/`**: Contains the core PHP logic of the plugin, organized into several files based on functionality.
*   **`assets/`**: Contains all frontend assets, including JavaScript and CSS files.
*   **`languages/`**: Contains translation files.
*   **`tests/`**: Contains the PHPUnit test suite.

## Plugin Architecture

The plugin follows a modular architecture, with different functionalities separated into their own files.

### Entry Point

The plugin's execution starts in `axytos-woocommerce.php`. This file defines some constants and then calls the `bootstrap()` function in `includes/init.php`.

### Initialization (`includes/init.php`)

The `init.php` file is responsible for initializing the plugin. It loads the text domain for translations and then hooks into the `plugins_loaded` action to initialize the WooCommerce integration.

The core of the initialization process is a series of `bootstrap` functions that register the payment gateway, webhooks, AJAX handlers, and other components. It also conditionally loads admin or frontend-specific code based on the current context.

### Core Components (`includes/`)

The `includes/` directory contains the following key components:

*   **`AxytosPaymentGateway.php`**: The main payment gateway class. It extends `WC_Payment_Gateway` and handles payment processing, settings, and communication with the Axytos API.
*   **`AxytosApiClient.php`**: A client for the Axytos API. It handles all the direct communication with the Axytos servers.
*   **`AxytosWebhookHandler.php`**: Handles incoming webhooks from Axytos to update order statuses.
*   **`AxytosEncryptionService.php`**: Handles encryption and decryption of sensitive data like API keys.
*   **`axytos-data.php`**: Contains functions for creating data structures to be sent to the Axytos API.
*   **`ajax.php`**: Handles all AJAX requests, both from the frontend and the admin area.
*   **`payments.php`**: Filters the available payment gateways based on certain conditions.
*   **`orders.php`**: Handles actions related to order status changes.
*   **`cron.php`**: Sets up and manages cron jobs for the plugin.
*   **`admin.php`**: Handles all admin-specific functionality, like adding metaboxes and columns to the order list.
*   **`frontend.php`**: Handles all frontend-specific functionality, like enqueuing scripts and styles.

### Frontend Components (`assets/`)

The `assets/` directory contains the following frontend components:

*   **`admin-actions.js`**: JavaScript for the admin-side action buttons on the order list and order edit pages. Handles AJAX requests for shipping, cancelling, and refunding orders.
*   **`axytos-agreement.js`**: JavaScript for the frontend agreement modal. It fetches the agreement content via AJAX and displays it in a modal window.
*   **`blocks-support.js`**: Provides support for the WooCommerce Blocks checkout.
*   **`css/agreement_popup.css`**: Styles for the agreement modal.
*   **`css/style.css`**: General styles for the plugin's admin interface.

### Testing (`tests/`)

The `tests/` directory contains a comprehensive suite of PHPUnit tests for the plugin. The tests cover a wide range of functionality, including:
*   Admin button visibility and actions
*   API workflow
*   Checkout behavior
*   Email notifications
*   Enabling and disabling the gateway
*   Error handling
*   Gateway availability and deactivation
*   Gateway initialization
*   Order meta data
*   Payment gateway registration
*   Payment status updates
*   Processing payments
*   Required fields validation
*   Script enqueuing
*   Settings validation
*   Unique ID generation

The tests are well-structured and provide good coverage of the plugin's functionality. This indicates a high level of code quality and a commitment to stability. To run the tests, use the provided `phpunit.xml.dist` file as a template and configure your test environment.

## Development Workflow

1.  **Branching**: Create a new branch for each new feature or bug fix.
2.  **Coding**: Follow the existing code style and conventions.
3.  **Testing**: Write or update tests for any new or changed functionality.
4.  **Linting**: Run the linter to ensure code quality.
5.  **Pull Request**: Open a pull request for review.

## Key Concepts

*   **Payment Gateway**: The core of the plugin is the `AxytosPaymentGateway` class, which integrates with WooCommerce to provide the Axytos payment method.
*   **API Client**: The `AxytosApiClient` class is used to communicate with the Axytos API.
*   **Webhooks**: The `AxytosWebhookHandler` class is used to handle incoming webhooks from Axytos.
*   **Encryption**: The `AxytosEncryptionService` class is used to encrypt and decrypt sensitive data.
*   **AJAX**: The plugin uses AJAX to handle various actions, such as admin actions and fetching the agreement content.
*   **Cron**: The plugin uses cron jobs to process pending actions.

This documentation should provide a good starting point for any developer working on the Axytos WooCommerce Payment Gateway plugin. For more detailed information, please refer to the source code and the inline comments.

***

## In-depth Topics

### Cron based retries

**Overview:** A robust payment system ensures no data loss even if the Axytos API is temporarily unavailable, using a queue-based approach with retry logic and monitoring.

**Key Features:**
- **Pending Actions Queue:** JSON-based action storage in order meta-data for confirm, shipped, cancel, and refund actions. Includes deduplication and sequential processing.
- **Robust Error Handling:** Failed actions are timestamped, retried, and automatically cleaned up after 30 days. Comprehensive logging to WooCommerce logs.
- **Additional Data Management:** Stores invoice numbers, tracking numbers, and ERP data.
- **WordPress Cron Integration:** Processes pending actions every 15 minutes, with daily cleanup. Manual trigger available.
- **Admin Interface Enhancements:** Dedicated "Pending Actions Management Page" and enhancements to "Order Edit Page" for visibility and control.
- **Comprehensive Monitoring:** Integrates with WooCommerce logger for "axytos-action-handler" source and adds order notes.

**Modified Files:**
- `includes/AxytosActionHandler.php`: Core action management.
- `includes/cron.php`: WordPress cron jobs.
- `includes/ajax.php`: Queues actions instead of immediate API calls.
- `includes/orders.php`: Queues actions on status changes.
- `includes/AxytosWebhookHandler.php`: Enhanced webhook processing, stores ERP data.
- `includes/init.php`: Loads new components and cron.
- `includes/admin.php`: Enhanced admin interface.

**Data Structure (Pending Actions):**
```json
[
  {
    "action": "shipped",
    "created_at": "2023-10-01T12:00:00Z",
    "failed_at": null,
    "failed_count": 0,
    "data": {
      "invoice_number": "INV-123"
    }
  }
]
```
**Additional Meta-Data Keys:** `_axytos_pending`, `_axytos_ext_last_update`, `axytos_ext_invoice_nr`, `axytos_ext_tracking_nr`.

**Benefits:** Zero data loss, automatic recovery, full observability, manual control, backward compatibility, graceful degradation.

**Testing:** Comprehensive test suite in `test-robust-system.php` covering queueing, data storage, cron, cleanup, and admin interface.

**Monitoring:** Check WooCommerce logs ("axytos-action-handler") and "WooCommerce → Axytos Pending Actions" admin page.

### Webhook Endpoint (REST API)

**Overview:** Enables secure communication between ERP systems and WooCommerce for automatic order status updates, invoice, and shipment synchronization.

**Endpoint Details:**
- **URL**: `/wp-json/axytos/v1/order-update`
- **Method**: `POST`
- **Content-Type**: `application/json`
- **Authentication**: API Key via `X-Axytos-Webhook-Key` HTTP header. Configure in **WooCommerce > Settings > Payments > Axytos**.

**Request Format:**
- **Required:** `order_id` (integer), `new_status` (string)
- **Optional:** `curr_status` (string), `invoice_number` (string), `tracking_number` (string)

**Example Request:**
```json
{
    "order_id": 12345,
    "curr_status": "processing",
    "new_status": "shipped",
    "invoice_number": "INV-2023-001",
    "tracking_number": "1Z999AA1234567890"
}
```

**Response Format:**
- **Success (200 OK):** `{"success": true, "message": "..."}`
- **Errors:** Invalid API Key (401/403), Order Not Found (404), Status Conflict (409), Validation Error (400), Server Error (500).

**Status Mapping:** Automatically maps ERP statuses to WooCommerce. Customizable via `axytos_webhook_status_mapping` filter.

**Security Features:** API Key authentication (`hash_equals()` for comparison), input validation, order verification, status conflict detection, comprehensive logging.

**Logging:** All webhook activity logged to WooCommerce logs (filter by 'axytos-webhook').

**Order Data Storage:** Additional ERP data stored in order meta fields: `axytos_ext_invoice_nr`, `axytos_ext_tracking_nr`, `_axytos_ext_last_update`. Also added to order notes.

**Testing:** Test utility available at `/wp-content/plugins/axytos-woocommerce/includes/webhook-test.php` when `WP_DEBUG` is enabled.

**Error Handling:** Comprehensive handling for authentication, validation, business logic, and system errors. All errors logged.

**Best Practices:** Secure API keys, HTTPS only, retry logic (exponential backoff), idempotency, monitoring, thorough testing.

**Troubleshooting:** Consult WooCommerce logs (filter by 'axytos-webhook') and the webhook test utility (`/wp-content/plugins/axytos-woocommerce/includes/webhook-test.php` when `WP_DEBUG` is enabled) for debugging authentication (401/403), order not found (404), validation (400), and status conflict (409) errors.
