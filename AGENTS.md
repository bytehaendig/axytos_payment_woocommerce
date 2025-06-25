# Axytos WooCommerce Plugin - Agent Guidelines

## Commands

### Testing
- `mise run unit-tests` - Run all PHPUnit tests
- `ddev exec -d /var/www/html/wp-content/plugins/axytos-woocommerce vendor/bin/phpunit tests/SpecificTest.php` - Run single test
- `ddev exec -d /var/www/html/wp-content/plugins/axytos-woocommerce vendor/bin/phpunit --filter testMethodName` - Run specific test method

### Linting & Code Quality
- `ddev exec -d /var/www/html/wp-content/plugins/axytos-woocommerce vendor/bin/phpcs` - Check coding standards
- Uses WordPress Coding Standards via `.phpcs.xml.dist`

### Development
- Uses `ddev` for local WordPress environment
- Uses `mise` for task management (see `mise.toml`)

## Code Style

### PHP
- Follow WordPress Coding Standards (configured in `.phpcs.xml.dist`)
- Use `Axytos\WooCommerce` namespace for all classes
- PSR-4 autoloading structure in `/includes/` directory
- Prefix global functions/variables with `axytos_` or similar
- WordPress i18n functions with `axytos-wc` text domain
- Use `require_once` for file includes
- Class names in PascalCase (e.g. `AxytosPaymentGateway`)
- Method visibility: public/protected/private as appropriate
- DocBlocks for all classes and methods

### File Organization
- Main plugin file: `axytos-woocommerce.php`
- Classes in `/includes/` directory with descriptive names
- Tests in `/tests/` directory with `*Test.php` suffix
- Assets in `/assets/` (CSS/JS)
