# Axytos WooCommerce Plugin - Agent Guidelines

## Commands

- uses 'ddev' for local development
- uses 'mise' for task management

### Testing
- `mise run unit-tests` - Run PHPUnit tests

### Linting & Code Quality
TBD
## Code Style

### PHP
- Follow WordPress Coding Standards (configured in `.phpcs.xml.dist`)
- Use `Axytos\WooCommerce` namespace for all classes
- PSR-4 autoloading structure in `/includes/` directory
- Prefix global functions/variables appropriately
- WordPress i18n functions with `axytos-wc` text domain

### File Organization
- Main plugin file: `axytos-woocommerce.php`
- Classes in `/includes/` directory with descriptive names (e.g. `AxytosPaymentGateway.php`)
- Tests in `/tests/` directory with `*Test.php` suffix
- Assets in `/assets/` (CSS/JS)
