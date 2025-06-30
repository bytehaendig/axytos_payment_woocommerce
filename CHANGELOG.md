# Changelog

All notable changes to the Axytos WooCommerce Plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-XX-XX

### Added
- **Order Reactivation**: You can now reactivate cancelled orders directly from the Axytos interface
- **Webhook Integration**: Automatic status updates via webhooks for improved order synchronization
- **Background Processing**: Axytos actions now run automatically in the background using WordPress cron jobs
- **Error Retry Functionality**: Failed actions can now be retried directly from the admin interface
- **Invoice Number Entry**: When reporting shipments, you can now enter specific invoice numbers
- **WordPress Cron Monitoring**: The plugin now checks and displays the status of WordPress cron functionality
- **Enhanced Error Display**: Errors that occur during actions are now saved and clearly displayed to administrators

### Improved
- **Action Management**: Split the 'complete' action into separate 'invoice' and 'shipped' actions for better control
- **User Interface**: Significantly improved the display and management of Axytos actions in the admin area
- **Status Monitoring**: Enhanced the Axytos status tab with better information and controls
- **Confirmation Messages**: Improved user feedback with clearer confirmation and status messages
- **Translation Support**: Updated and improved internationalization throughout the plugin
- **Webhook Settings**: Enhanced webhook configuration options with better information and controls

### Fixed
- **Modal Scrolling**: Fixed an issue where scrolling was not possible after closing the agreement modal
- **HTML Security**: Improved HTML escaping for better security
- **Error Handling**: More robust error management throughout the plugin
- **Translation Accuracy**: Adjusted and corrected various translations

### Technical Improvements
- **API Communication**: Switched from cURL to WordPress native HTTP functions for better compatibility
- **Code Structure**: Significant refactoring for better maintainability and performance
- **Security**: Enhanced security measures including better HTML escaping and input validation
- **User-Agent Headers**: Added proper User-Agent headers for API communications

### Changed
- **Version**: Major version bump to 1.0.0 reflecting the maturity and stability of the plugin
- **Action Names**: Renamed 'report_shipped' action to 'shipped' for consistency

---

## Previous Versions

For changes prior to v1.0.0, please refer to the git commit history or contact support.