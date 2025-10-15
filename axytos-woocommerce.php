<?php
/**
 * Plugin Name: Axytos WooCommerce Payment Gateway
 * Description: Axytos Payment Gateway for WooCommerce.
 * Version: 1.0.3
 * Author: Bytehändig Software Manufaktur
 * Author URI: https://bytehaendig.de
 * Text Domain: axytos-wc
 * Domain Path: /languages
 *
 * @package Axytos\WooCommerce
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

define( 'AXYTOS_PLUGIN_VERSION', '1.0.3' );
define( 'AXYTOS_PAYMENT_ID', 'axytoswc' );

// Load all plugin functionality.
require_once __DIR__ . '/includes/init.php';
Axytos\WooCommerce\bootstrap();
