<?php
/**
 * Frontend functionality for Axytos WooCommerce plugin.
 *
 * @package Axytos\WooCommerce
 */

namespace Axytos\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue frontend scripts and styles for checkout
 */
function enqueue_frontend_assets() {
	// Only load on checkout page
	if ( ! is_checkout() ) {
		return;
	}

	wp_enqueue_style(
		'axytos-agreement-popup',
		plugin_dir_url( __DIR__ ) . '/assets/css/agreement_popup.css',
		array(),
		AXYTOS_PLUGIN_VERSION
	);

	wp_enqueue_script(
		'axytos-agreement',
		plugin_dir_url( __DIR__ ) . '/assets/axytos-agreement.js',
		array( 'jquery' ),
		AXYTOS_PLUGIN_VERSION,
		true
	);

	wp_localize_script(
		'axytos-agreement',
		'axytos_agreement',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'axytos_agreement_nonce' ),
		)
	);
}

/**
 * Add agreement link to gateway description
 */
function add_agreement_link_to_gateway_description( $description, $payment_id ) {
	if ( \AXYTOS_PAYMENT_ID !== $payment_id ) {
		return $description;
	}

	$settings       = get_option( 'woocommerce_axytoswc_settings', array() );
	$agreement_text = $settings['PrecheckAgreeText'] ?? '';

	if ( ! empty( $agreement_text ) ) {
		$description .=
			' <br><a href="#" class="axytos-agreement-link">' .
			esc_html( $agreement_text ) .
			'</a>';
	}

	return $description;
}

/**
 * Add JavaScript to refresh checkout on error
 */
function add_checkout_error_handler() {
	if ( ! is_checkout() ) {
		return;
	}
	?>
	<script>
	jQuery(document).ready(function ($) {
		$(document.body).on('checkout_error', function () {
			$(document.body).trigger('update_checkout');
		});
	});
	</script>
	<?php
}

/**
 * Show notice on thank you page for on-hold orders
 */
function show_thankyou_notice( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	if ( $order->get_payment_method() === \AXYTOS_PAYMENT_ID && $order->get_status() === 'on-hold' ) {
		echo '<div class="woocommerce-notice woocommerce-info woocommerce-notice--info woocommerce-thankyou-notice">';
		echo __( 'Order on-hold, waiting for admin approval.', 'axytos-wc' );
		echo '</div>';
	}
}

/**
 * Add WooCommerce Blocks support
 */
function add_blocks_support() {
	if (
		! class_exists(
			'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType'
		)
	) {
		error_log(
			'--- Axytos Debug --- AbstractPaymentMethodType class not found.'
		);
		return;
	}

	require_once plugin_dir_path( __FILE__ ) . 'AxytosBlocksGateway.php';

	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function (
			\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry
		) {
			$payment_method_registry->register( new AxytosBlocksGateway() );
		}
	);
}

function bootstrap_frontend() {
	// Enqueue frontend scripts and styles
	add_action(
		'wp_enqueue_scripts',
		__NAMESPACE__ . '\enqueue_frontend_assets'
	);

	// Add agreement link to gateway description
	add_filter(
		'woocommerce_gateway_description',
		__NAMESPACE__ . '\add_agreement_link_to_gateway_description',
		10,
		2
	);
	// Hook frontend functionality
	add_action( 'wp_footer', __NAMESPACE__ . '\add_checkout_error_handler' );
	add_action( 'woocommerce_thankyou', __NAMESPACE__ . '\show_thankyou_notice', 10, 1 );
	add_action( 'woocommerce_blocks_loaded', __NAMESPACE__ . '\add_blocks_support' );
}

