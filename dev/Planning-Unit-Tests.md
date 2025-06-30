# Unit Test Plan for Axytos WooCommerce Plugin

## Executive Summary

This document outlines a comprehensive plan for expanding unit test coverage of the Axytos WooCommerce plugin. Based on analysis of the current codebase, we identified significant gaps in test coverage, particularly around core business logic, API communication, and security features.

## Current State Analysis

### Existing Test Coverage (7 files)

The plugin currently has basic test coverage with the following files:

- **`AdminButtonsTest.php`** - Tests admin action button visibility based on order status
- **`ApiWorkflowTest.php`** - Basic payment processing workflow (currently disabled with `xtest_`)
- **`EnableDisableGatewayTest.php`** - Gateway enable/disable functionality
- **`GatewayAvailabilityTest.php`** - Gateway availability checks
- **`GatewayInitializationTest.php`** - Basic gateway initialization and settings
- **`PaymentGatewayRegistrationTest.php`** - Gateway registration with WooCommerce
- **`SettingsValidationTest.php`** - Settings validation logic

### Codebase Structure Analysis

The plugin consists of several key components:

#### Core Classes
1. **`AxytosPaymentGateway.php`** - Main WooCommerce payment gateway implementation
2. **`AxytosApiClient.php`** - HTTP client for Axytos API communication
3. **`AxytosWebhookHandler.php`** - Secure REST API endpoint for ERP webhooks
4. **`AxytosActionHandler.php`** - Asynchronous action queue with retry logic
5. **`AxytosEncryptionService.php`** - Centralized encryption/decryption service
6. **`AxytosBlocksGateway.php`** - WooCommerce Blocks checkout integration

#### Supporting Files
- **`admin.php`** - Admin UI functionality and order management
- **`ajax.php`** - AJAX handlers for frontend/admin interactions
- **`cron.php`** - Cron scheduling and background processing
- **`axytos-data.php`** - Data transformation functions
- **`orders.php`** - Order lifecycle hooks
- **`payments.php`** - Payment processing hooks
- **`frontend.php`** - Frontend functionality

## Identified Testing Gaps

### Critical Untested Components

1. **API Communication** - No tests for HTTP requests, error handling, or environment switching
2. **Security Features** - Webhook authentication, rate limiting, and encryption not tested
3. **Business Logic** - Payment decision logic, retry mechanisms, and state management untested
4. **Integration Points** - WooCommerce Blocks, admin interfaces, and AJAX handlers lack coverage
5. **Background Processing** - Cron jobs, action queues, and batch processing not tested

### Risk Assessment

- **High Risk**: API client failures could break payment processing
- **High Risk**: Webhook security vulnerabilities could expose sensitive data
- **Medium Risk**: Action handler failures could lead to inconsistent order states
- **Medium Risk**: Encryption service failures could expose API credentials

## Recommended Test Implementation Plan

### Phase 1: Core Business Logic Tests (Priority 1)

#### 1. `AxytosApiClientTest.php`
**Purpose**: Test HTTP communication with Axytos API
**Key Test Areas**:
- HTTP request/response handling for all endpoints (`invoicePrecheck`, `orderConfirm`, etc.)
- Environment switching between sandbox and production
- Error handling for various HTTP status codes
- User-Agent generation and request authentication
- Network timeout and connection failure scenarios

#### 2. `AxytosPaymentGatewayTest.php`
**Purpose**: Test core payment gateway functionality
**Key Test Areas**:
- Payment decision logic handling (U/S/R response codes)
- Order processing workflow (`process_payment`, `doPrecheck`, `confirmOrder`)
- Settings encryption/decryption integration
- WooCommerce gateway interface compliance
- Payment method availability checks

#### 3. `AxytosActionHandlerTest.php`
**Purpose**: Test asynchronous action processing
**Key Test Areas**:
- Action queue management (add, process, retry operations)
- Retry logic with exponential backoff (10min intervals, 3 max retries)
- State transitions (pending → done/failed)
- Batch processing with memory management
- Error handling and admin email notifications

#### 4. `AxytosEncryptionServiceTest.php`
**Purpose**: Test data encryption/decryption
**Key Test Areas**:
- AES-256-CBC encryption correctness
- Bulk settings encryption/decryption
- Graceful fallback when OpenSSL unavailable
- Random IV generation and key management
- Sensitive data identification

### Phase 2: Integration & Security Tests (Priority 2)

#### 5. `AxytosWebhookHandlerTest.php`
**Purpose**: Test webhook security and processing
**Key Test Areas**:
- Multi-layer authentication (API key validation, rate limiting)
- Payload validation and sanitization
- ERP status mapping to WooCommerce statuses
- Order status conflict detection
- Comprehensive logging and error handling

#### 6. `AxytosBlocksGatewayTest.php`
**Purpose**: Test WooCommerce Blocks integration
**Key Test Areas**:
- Gateway initialization and settings management
- Script asset loading and dependency management
- Frontend data serialization for JavaScript
- Payment method availability in block checkout
- Internationalization support

### Phase 3: Supporting Functionality Tests (Priority 3)

#### 7. `AdminFunctionalityTest.php`
**Purpose**: Test admin interface components
**Key Test Areas**:
- Order list column rendering and action button visibility
- Metabox content generation for order edit pages
- Pending/done actions status display
- Admin page rendering and manual processing triggers
- Asset enqueueing and localization

#### 8. `AjaxHandlersTest.php`
**Purpose**: Test AJAX endpoint functionality
**Key Test Areas**:
- Action processing (shipped, cancel, refund operations)
- Agreement content loading
- Manual processing triggers for administrators
- Nonce verification and security validation
- Error response handling

#### 9. `CronSchedulingTest.php`
**Purpose**: Test background processing
**Key Test Areas**:
- Cron event scheduling and management
- Processing job execution and logging
- Timestamp recording and retrieval
- Event cleanup on plugin deactivation
- Custom cron schedule registration

#### 10. `DataTransformationTest.php`
**Purpose**: Test data conversion functions
**Key Test Areas**:
- Order data conversion for API requests
- Address and customer data mapping
- Product information extraction and formatting
- Currency and amount calculations
- Data validation and sanitization

### Phase 4: Lifecycle & Hook Tests (Priority 4)

#### 11. `OrderLifecycleTest.php`
**Purpose**: Test WooCommerce integration hooks
**Key Test Areas**:
- Order status change hook handling
- Automatic action triggering on status transitions
- Status transition validation and conflict resolution
- Integration with WooCommerce order events
- Custom order status registration

#### 12. `PaymentProcessingTest.php`
**Purpose**: Test payment workflow integration
**Key Test Areas**:
- Payment method validation and availability
- Gateway availability checks based on configuration
- Checkout process integration and form handling
- Error handling during payment processing
- Session management and order creation

#### 13. `FrontendIntegrationTest.php`
**Purpose**: Test customer-facing functionality
**Key Test Areas**:
- Agreement popup functionality and content loading
- Frontend script loading and initialization
- Checkout form integration and validation
- User interaction handling and feedback
- Mobile and accessibility considerations

### Phase 5: Edge Cases & Error Handling (Priority 5)

#### 14. `ErrorHandlingTest.php`
**Purpose**: Test error scenarios and recovery
**Key Test Areas**:
- API communication failures and timeouts
- Network connectivity issues
- Invalid data handling and validation
- Graceful degradation when services unavailable
- Error logging and user notification

#### 15. `ConfigurationTest.php`
**Purpose**: Test plugin configuration and setup
**Key Test Areas**:
- Settings validation and sanitization
- Environment configuration (sandbox/production)
- Plugin activation and deactivation hooks
- Database schema and option management
- Compatibility checks and requirements

## Implementation Guidelines

### Testing Best Practices

1. **Mock External Dependencies**
   - Use WordPress test framework mocks for WP functions
   - Mock HTTP requests to prevent external API calls during tests
   - Mock file system operations and database interactions

2. **Data Providers**
   - Use PHPUnit data providers for testing multiple scenarios
   - Test boundary values and edge cases
   - Include both valid and invalid input combinations

3. **Security Testing**
   - Verify nonce validation in AJAX handlers
   - Test capability checks for admin functions
   - Validate input sanitization and output escaping

4. **Error Path Testing**
   - Test both success and failure scenarios
   - Verify proper error handling and user feedback
   - Ensure graceful degradation when services fail

5. **Integration Testing**
   - Test WooCommerce hook integration
   - Verify WordPress action/filter compatibility
   - Test database operations and data persistence

### Test Structure Recommendations

```php
class ExampleTest extends WP_UnitTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Setup test environment
    }
    
    protected function tearDown(): void {
        // Clean up after tests
        parent::tearDown();
    }
    
    /**
     * @dataProvider providerTestData
     */
    public function testFunctionality($input, $expected) {
        // Test implementation
    }
    
    public function providerTestData() {
        return [
            // Test cases
        ];
    }
}
```

## Success Metrics

### Coverage Goals
- **90%+ code coverage** for core business logic classes
- **80%+ code coverage** for supporting functionality
- **100% coverage** for security-critical functions

### Quality Metrics
- All tests must pass consistently
- No external dependencies in unit tests
- Fast test execution (< 30 seconds for full suite)
- Clear test documentation and naming

## Timeline Estimation

- **Phase 1**: 2-3 weeks (Core business logic)
- **Phase 2**: 1-2 weeks (Integration & security)
- **Phase 3**: 2-3 weeks (Supporting functionality)
- **Phase 4**: 1-2 weeks (Lifecycle & hooks)
- **Phase 5**: 1 week (Edge cases & configuration)

**Total Estimated Time**: 7-11 weeks

## Conclusion

This comprehensive testing plan addresses the current gaps in test coverage while prioritizing the most critical components first. Implementation of these tests will significantly improve code reliability, reduce regression risks, and provide confidence for future development and maintenance activities.

The phased approach allows for incremental progress while delivering value early through testing of core business logic. Each phase builds upon the previous one, creating a robust test foundation that supports the plugin's continued evolution.