# Axytos WooCommerce Plugin - Agent Guidelines

## Commands

### Development

- Uses `ddev` for local WordPress environment
- Uses `mise` for task management (see `mise.toml`)

### Testing

- `mise run unit-tests` - Run all PHPUnit tests
- `mise run unit-tests tests/SpecificTest.php` - Run single test
- `mise run unit-tests --filter testMethodName` - Run specific test method

### Linting & Code Quality

- `mise run lint` - Check coding standards
- Uses WordPress Coding Standards via `.phpcs.xml.dist`

### General

- always ignore backup files ( filename ends with `~`)

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
- DocBlocks for all classes and methods if appropriate

### File Organization

- Main plugin file: `axytos-woocommerce.php`
- Classes in `/includes/` directory with descriptive names
- Tests in `/tests/` directory with `*Test.php` suffix
- Assets in `/assets/` (CSS/JS)
