# Axytos WooCommerce Payment Gateway

A WooCommerce payment gateway plugin that integrates with the Axytos payment platform.

## Description

This plugin adds Axytos as a payment method to your WooCommerce store. Axytos provides secure payment processing for online merchants.

## Requirements

- WordPress 6.0 or higher
- WooCommerce 9.0 or higher
- PHP 7.4 or higher

## Installation

1. Upload the plugin files to the `/wp-content/plugins/axytos-woocommerce` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WooCommerce > Settings > Payments
4. Find "Axytos" in the payment methods list and click "Set up"
5. Configure your Axytos API credentials and settings

## Configuration

After installation, you'll need to:

1. Obtain API credentials from Axytos
2. Configure the payment gateway settings in WooCommerce
3. Set up webhook authentication (optional)
4. Test the integration before going live

## Webhook Integration

This plugin provides a webhook endpoint for external systems (like ERP systems) to update order statuses automatically:

- **Endpoint**: `/wp-json/axytos/v1/order-update`
- **Authentication**: Configure webhook API key in payment settings
- **Functionality**: Allows external systems to update order statuses, add invoice numbers, and tracking information

The webhook is particularly important for feeding invoice numbers from your ERP system back into the plugin, which then communicates this information to Axytos. This ensures proper invoice tracking and payment reconciliation.
It also enables automatic synchronization between your ERP system and WooCommerce orders.

## Support

For technical support and documentation, please contact your Axytos representative or refer to the Axytos merchant portal.

## Developer Documentation

For developers working on this plugin, see [DEVELOPER.md](DEVELOPER.md) for technical documentation and architecture details.
