<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Starter_Plugin
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// Load WooCommerce first
	require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/woocommerce/woocommerce.php';
	
	// Then load our plugin
	require dirname( dirname( __FILE__ ) ) . '/axytos-wc-payment-gateway.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
