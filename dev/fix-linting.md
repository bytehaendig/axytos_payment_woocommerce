# Axytos WooCommerce Plugin - Remaining Lint Fixes

## Progress Summary

✅ **COMPLETED - High Priority Issues (357 errors fixed)**
- File headers and documentation added to all includes/ files
- Class documentation added to all classes
- Function documentation with @param and @return tags added
- Member variable documentation added to class properties

**Current Status**: 680 errors and 45 warnings remaining (down from 1,037 errors)

## Remaining Tasks by Priority

### **Medium Priority - WordPress Coding Standards (Estimated: ~200-300 errors)**

#### 1. Output Escaping (Security)
- **Issue**: All output needs escaping functions like `esc_html()`, `esc_attr()`
- **Example**: `echo $variable` → `echo esc_html( $variable )`
- **Files**: Most includes/ files, especially admin.php, frontend.php
- **Impact**: Security vulnerability if not fixed

#### 2. Yoda Conditions
- **Issue**: Comparisons should be `'value' === $variable` instead of `$variable === 'value'`
- **Example**: `$status === 'completed'` → `'completed' === $status`
- **Files**: All includes/ files
- **Impact**: WordPress coding standard compliance

#### 3. Translator Comments
- **Issue**: Strings with placeholders need `/* translators: */` comments
- **Example**: 
  ```php
  /* translators: %s: order number */
  __( 'Order %s processed', 'axytos-wc' )
  ```
- **Files**: admin.php, frontend.php, AxytosPaymentGateway.php

#### 4. Loose Comparisons
- **Issue**: Use `===` instead of `==`
- **Example**: `$value == 'yes'` → `$value === 'yes'`
- **Files**: AxytosPaymentGateway.php, others

### **Medium Priority - Naming Conventions (Estimated: ~100-150 errors)**

#### 1. Variable Naming (snake_case)
- **Issue**: Variables should be `snake_case` instead of `camelCase`
- **Examples**:
  - `$AxytosAPIKey` → `$axytos_api_key`
  - `$useSandbox` → `$use_sandbox`
  - `$userFriendlyException` → `$user_friendly_exception`
- **Files**: AxytosPaymentGateway.php, test-exception-chaining.php

#### 2. File Naming (Optional - Breaking Change)
- **Issue**: Class files should be lowercase with hyphens and `class-` prefix
- **Examples**:
  - `AxytosPaymentGateway.php` → `class-axytos-payment-gateway.php`
  - `AxytosApiClient.php` → `class-axytos-api-client.php`
- **Note**: This is a breaking change that would require updating all require_once statements
- **Recommendation**: Skip this unless specifically required

### **Low Priority - Comment Formatting (Estimated: ~50-100 errors)**

#### 1. Inline Comment Punctuation
- **Issue**: Comments must end with `.`, `!`, or `?`
- **Examples**:
  - `// Load settings` → `// Load settings.`
  - `// Save settings` → `// Save settings.`
- **Files**: All includes/ files

### **Low Priority - Test Files (Estimated: ~200+ errors)**

#### 1. Test Documentation
- **Issue**: Missing file headers, class docs, function docs in test files
- **Files**: All files in tests/ directory
- **Note**: Less critical since these are not production code

#### 2. Test Naming Conventions
- **Issue**: Test functions and variables don't follow WordPress standards
- **Note**: Can be addressed later as tests are not user-facing

## Recommended Implementation Order

### Phase 1: Security & Critical Standards (High Impact)
1. **Output Escaping** - Fix security vulnerabilities first
2. **Translator Comments** - Fix internationalization issues
3. **Yoda Conditions** - WordPress standard compliance

### Phase 2: Code Quality (Medium Impact)
1. **Variable Naming** - Improve code readability
2. **Loose Comparisons** - Better type safety
3. **Inline Comment Punctuation** - Code style consistency

### Phase 3: Optional (Low Impact)
1. **File Naming** - Only if specifically required (breaking change)
2. **Test Files** - Can be addressed in separate effort

## Implementation Commands

### Check specific error types:
```bash
# Check output escaping errors
./vendor/bin/phpcs --standard=.phpcs.xml.dist includes/ --sniffs=WordPress.Security.EscapeOutput

# Check Yoda condition errors  
./vendor/bin/phpcs --standard=.phpcs.xml.dist includes/ --sniffs=WordPress.PHP.YodaConditions

# Check variable naming errors
./vendor/bin/phpcs --standard=.phpcs.xml.dist includes/ --sniffs=WordPress.NamingConventions.ValidVariableName
```

### Auto-fix what's possible:
```bash
# Fix automatically fixable issues
./vendor/bin/phpcbf --standard=.phpcs.xml.dist includes/

# Check progress
./vendor/bin/phpcs --standard=.phpcs.xml.dist includes/ --report=summary
```

## Expected Final Results

After completing all medium priority fixes:
- **Target**: ~200-400 errors remaining (mostly test files and optional file naming)
- **Core plugin files**: Should have minimal errors
- **Production readiness**: High - all security and critical standards addressed

## Notes

- Focus on `includes/` directory first as these are the core plugin files
- Test files can be addressed separately as they're not production code
- File naming changes would require updating all require_once statements
- Some errors in wp-tests-dir/ are from WordPress core test files and can be ignored