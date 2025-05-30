<?php

/*
Plugin Name: Axytos WooCommerce Payment Gateway
Description: Axytos Payment Gateway for WooCommerce.
Version: 0.12.6
Author: Bytehändig Software Manufaktur
Author URI: https://bytehaendig.de
Text Domain: axytos-wc
Domain Path: /languages/
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('AXYTOS_PLUGIN_VERSION', '0.12.6');
define('AXYTOS_PAYMENT_ID', 'axytoswc');

require_once __DIR__ . '/includes/actions.php';


add_action('woocommerce_blocks_loaded', 'rudr_gateway_block_support');
function rudr_gateway_block_support()
{
    if (! class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        error_log('--- Axytos Debug --- AbstractPaymentMethodType class not found.');
        return;
    }
    // here we're including our "gateway block support class"
    require_once __DIR__ . '/includes/AxytosBlocksGateway.php';

    // registering the PHP class we have just included
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new AxytosBlocksGateway());
        }
    );
}

function axytoswc_woocommerce_init()
{
    if (class_exists('WC_Payment_Gateway')) {
        // Add the gateway to WooCommerce
        function axytoswc_add_gateway_class($gateways)
        {
            $gateways[] = 'AxytosPaymentGateway';
            return $gateways;
        }
        add_filter('woocommerce_payment_gateways', 'axytoswc_add_gateway_class');
        // Enqueue custom JavaScript and CSS
        add_action('admin_enqueue_scripts', function () {
            wp_enqueue_script('axytos-admin-actions', plugin_dir_url(__FILE__) . '/assets/admin-actions.js', ['jquery'], '1.0', true);
            wp_localize_script('axytos-admin-actions', 'AxytosActions', [
              'ajax_url' => admin_url('admin-ajax.php'),
              'nonce'    => wp_create_nonce('axytos_action_nonce'),
            ]);
            wp_enqueue_style('axytos-admin-styles', plugin_dir_url(__FILE__) . '/assets/css/style.css', [], '1.0');
        });

        function axytos_add_agreement_link_to_gateway_description($description, $payment_id)
        {
            // Check if the current payment gateway is Axytos
            if (AXYTOS_PAYMENT_ID === $payment_id) {
                $agreement_text = get_option('woocommerce_axytoswc_settings')['PrecheckAgreeText'] ?? '';
                $description .= ' <br><a href="#" class="axytos-agreement-link">' . esc_html($agreement_text) . '</a>';
            }

            return $description;
        }

        add_filter('woocommerce_gateway_description', 'axytos_add_agreement_link_to_gateway_description', 10, 2);

        function axytos_enqueue_checkout_scripts()
        {
            if (is_checkout()) {
                wp_enqueue_style('axytos-admin-styles', plugin_dir_url(__FILE__) . '/assets/css/agreement_popup.css', [], '1.0');
                wp_enqueue_script('axytos-agreement', plugin_dir_url(__FILE__) . '/assets/axytos-agreement.js', ['jquery'], null, true);
                wp_localize_script('axytos-agreement', 'axytos_agreement', [
                  'ajax_url' => admin_url('admin-ajax.php'),
                  'nonce' => wp_create_nonce('axytos_agreement_nonce')
                ]);
            }
        }

        add_action('wp_enqueue_scripts', 'axytos_enqueue_checkout_scripts');

        require_once __DIR__ . '/includes/AxytosPaymentGateway.php';
    }
}
add_action('plugins_loaded', 'axytoswc_woocommerce_init');
