<?php
/*
Plugin Name: Axytos WooCommerce Payment Gateway
Description: Axytos Payment Gateway for WooCommerce.
Version: 0.9
Author: Bytehändig Software Manufaktur
Author URI: https://bytehaendig.de
Text Domain: axytos-wc
Domain Path: /languages/
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/blocks-support.php';


use Automattic\WooCommerce\Utilities\OrderUtil;

function axytos_textdomain() {
	load_plugin_textdomain( 'axytos-wc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action('plugins_loaded', 'axytos_textdomain', 1);

// Remove Axytos from gateways if denied by admin
add_filter('woocommerce_available_payment_gateways', function ($available_gateways) {

    // Check if order awaiting payment exists in session
    if (WC()->session && $order_id = WC()->session->get('order_awaiting_payment') ?? WC()->session->get('store_api_draft_order')) {
        // Check if transient is set to disable Axytos
        if (get_transient('disable_axitos_for_' . $order_id)) {
            $chosen_payment_method = 'axytoswc';
            if (isset($available_gateways[$chosen_payment_method])) {
                unset($available_gateways[$chosen_payment_method]);
            }
        }
    }
    return $available_gateways;
});

//refresh the checkout on error
add_action('wp_footer', function () {
    if (is_checkout()) {
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
});

//message on thankyou page in case of on-hold
add_action('woocommerce_thankyou', 'axytos_thankyou_notice', 10, 1);

function axytos_thankyou_notice($order_id) {
    $order = wc_get_order($order_id);

    if (!$order) {
        return;
    }
    if ($order->get_payment_method() === 'axytoswc' && $order->get_status() === 'on-hold') {
        echo '<div class="woocommerce-notice woocommerce-info woocommerce-notice--info woocommerce-thankyou-notice">';
        echo __('Order on-hold, waiting for admin approval.', 'axytos-wc');
        echo '</div>';
    }
}



// Add the 'Axytos Actions' column on Orders View page

//if HPOS is enabled
add_filter('manage_woocommerce_page_wc-orders_columns', 'column_adder' ,20);
//if HPOS is disabled
add_filter('manage_edit-shop_order_columns', 'column_adder' ,20);
 function column_adder($columns) {
    $columns['axytos_actions'] = __('Axytos Actions', 'axytos-wc');
    return $columns;
};

// Populate the 'Axytos Actions' column with buttons

//if HPOS is enabled
add_action('manage_woocommerce_page_wc-orders_custom_column', 'column_html',20, 2);
//if HPOS is disabled
add_action('manage_shop_order_posts_custom_column', 'column_html',20, 2);
function column_html($column, $order) {
    if ($column === 'axytos_actions') {
        $order = is_a($order, 'WC_Order') ? $order : wc_get_order($order);

        $unique_id = $order->get_meta('unique_id');

        if ($unique_id) {
            $nonce = wp_create_nonce('axytos_action_nonce');
            $order_status = $order->get_status();

            // Only show buttons if the order is neither completed nor cancelled
            if (!in_array($order_status, ['completed', 'cancelled', 'refunded'])) {
                echo '
                <div class="axytos-action-buttons-wrapper">
                    <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="report_shipping">' . __('Report Shipping', 'axytos-wc') . '</button>
                    <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="cancel">' . __('Cancel', 'axytos-wc') . '</button>
                    <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="refund">' . __('Refund', 'axytos-wc') . '</button>
                </div>';
            } elseif ($order_status === 'completed') {
                echo '<div class="axytos-action-buttons-wrapper">
                    <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="refund">' . __('Refund', 'axytos-wc') . '</button>
                </div>';
            } else {
                // For cancelled orders, show nothing
                echo '';
            }
        } else {
            echo __('N/A', 'axytos-wc');
        }
    }
}



// Send Requests on Order Status Change
add_action('woocommerce_order_status_changed', 'handle_axytos_status_change', 10, 4);

function handle_axytos_status_change($order_id, $old_status, $new_status, $order) {
    if (!$order instanceof WC_Order) {
        return;
    }

    $unique_id = $order->get_meta('unique_id');
    if (!$unique_id) {
        error_log("No unique ID found for order #" .  $order_id);
        return;
    }
    

    $endpoint_url = site_url('/wp-admin/admin-ajax.php');

    $action = '';
    if ($old_status === 'processing' && $new_status === 'cancelled') {
        $action = 'cancel';
    }  elseif ($old_status === 'on-hold' && $new_status === 'processing') {
        $action = 'confirm';
    } elseif ($new_status === 'completed') {
        $action = 'report_shipping';
    }

    if (empty($action)) {
        return;
    }

    $response = wp_remote_post($endpoint_url, [
        'timeout' => 60,
        'body' => [
            'action' => 'axytos_action',
            'order_id' => $order_id,
            'action_type' => $action,
            'security' => wp_create_nonce('axytos_action_nonce'),
        ],
    ]);

    if (is_wp_error($response)) {
        error_log("Axytos action ($action) failed for order #$order_id: " . $response->get_error_message());
    } else {
        error_log("Axytos action ($action) successfully triggered for order #$order_id.");
    }
}

// Add a metabox for Axytos Actions on the order edit page
add_action('add_meta_boxes', 'add_axytos_actions_metabox');
function add_axytos_actions_metabox() {
    
    add_meta_box(
        'axytos_actions_metabox',
        __('Axytos Actions', 'axytos-wc'),
        'render_axytos_actions_metabox',
        OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop-order' ):'shop_order', // Post type: WooCommerce Orders
        'side',       // Position: Side column
        'default'     // Priority: Default
    );
}

// Render the Axytos Actions metabox content
function render_axytos_actions_metabox($post) {
    
    $order = wc_get_order($post->ID);

    if (!$order) {
        echo '<p>' . __('Order not found.', 'axytos-wc') . '</p>';
        return;
    }

    $unique_id = $order->get_meta('unique_id');

    if ($unique_id) {
        $nonce = wp_create_nonce('axytos_action_nonce');
        $order_status = $order->get_status();
        
        if (!in_array($order_status, ['completed', 'cancelled'])) {
        echo '
        <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="report_shipping">' . __('Report Shipping', 'axytos-wc') . '</button>
        <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="cancel">' . __('Cancel', 'axytos-wc') . '</button>
        <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="refund">' . __('Refund', 'axytos-wc') . '</button>';
    }
    elseif ($order_status === 'completed') {
                echo '<div class="axytos-action-buttons-wrapper">
                    <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="refund">' . __('Refund', 'axytos-wc') . '</button>
                </div>';
    } 
    else {
                // For cancelled orders, show nothing
                echo '';
    }
    
    
    }
    else {
        echo '<p>' . __('No Axytos actions available for this order.', 'axytos-wc') . '</p>';
    }
}

//Endpoint for statuses
add_action('wp_ajax_axytos_action', 'handle_axitos_action');
add_action('wp_ajax_nopriv_axytos_action', 'handle_axitos_action');

function handle_axitos_action () {
    // Verify nonce
    // check_ajax_referer('axytos_action_nonce', 'security');
    
    $axyos_gateway_obj = new WC_Axytos_Payment_Gateway();
    $api = $axyos_gateway_obj->AxytosAPIKey;
    $sandbox = $axyos_gateway_obj->useSandbox;
    $AxytosClient = new AxytosApiClient($api, $sandbox);

    $order_id = absint($_POST['order_id']);
    $action_type = sanitize_text_field($_POST['action_type']);

    if (!$order_id || !$action_type) {
        wp_send_json_error(['message' => __('Invalid order or action.', 'axytos-wc')]);
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(['message' => __('Order not found.', 'axytos-wc')]);
    }

    $unique_id = $order->get_meta('unique_id');
    $invoice_number = $order->get_meta('axytos_invoice_number');

    if (!$unique_id) {
        wp_send_json_error(['message' => __('Axytos unique ID not found.', 'axytos-wc')]);
    }

    try {
        switch ($action_type) {
            case 'report_shipping':
                $statusData = [
                    "externalOrderId" => $unique_id,
                    "externalSubOrderId" => $order_id,
                    "basketPositions" => array_values(array_map(function ($item) {
                        return [
                            "productId" => $item->get_product_id(),
                            "quantity" => $item->get_quantity(),
                        ];
                    }, $order->get_items())),
                    "shippingDate" => date('c'),
                ];
                $result = $AxytosClient->updateShippingStatus($statusData);
                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => __('Could not update report shipping.', 'axytos-wc')]);
                    return;
                }

                $response_body = json_decode($result, true);
                if (isset($response_body['errors'])) {
                    $msg = $response_body['errors'][""][0] ?? 'Error Response from Axytos';
                    wp_send_json_error(['message' => __($msg, 'axytos-wc')]);
                    return;

                }

                $order->update_status(
                    'completed', 
                    __('Order completed based on Axytos decision.', 'axytos-wc'), 
                    true // Notify customer (optional)
                );

                wp_send_json_success(['message' => __('Shipping status reported successfully.', 'axytos-wc')]);

                break;

            case 'cancel':
                $result = $AxytosClient->cancelOrder($unique_id);
                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => __('Could not cancel order.', 'axytos-wc')]);
                    return;
                }
                $response_body = json_decode($result, true);
                if (isset($response_body['errors'])) {
                    $msg = $response_body['errors']['orderStatus'][0];
                    wp_send_json_error(['message' => __($msg, 'axytos-wc')]);
                    return;

                }
                $order->update_status('cancelled', __('Order cancelled based on Axytos decision.', 'axytos-wc'));
                wp_send_json_success(['message' => __('Order canceled successfully.', 'axytos-wc')]);

                break;

            case 'refund':
                $refundData = [
                    "externalOrderId" => $unique_id,
                    "refundDate" => date('c'),
                    "originalInvoiceNumber" => $invoice_number,
                    "externalSubOrderId" => $order_id,
                    "basket" => [
                        "grossTotal" => $order->get_total(),
                        "netTotal" => $order->get_subtotal(),
                        "positions" => array_values(array_map(function ($item) {
                            return [
                                "productId" => $item->get_product_id(),
                                "netRefundTotal" => $item->get_total() - $item->get_total_tax(),
                                "grossRefundTotal" => $item->get_total(),
                            ];
                        }, $order->get_items())),
                        "taxGroups" => [],
                    ],
                ];
                $result = $AxytosClient->refundOrder($refundData);
                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => __('Could not refund order.', 'axytos-wc')]);
                    return;
                }
                $response_body = json_decode($result, true);
                if (isset($response_body['errors'])) {
                    $msg = $response_body['errors']['externalSubOrderId'][0] ?? $response_body['errors'][""][0];
                    wp_send_json_error(['message' => __(strval($msg), 'axytos-wc')]);
                    return;

                }
                $order->update_status('refunded', __('Order refunded based on Axytos decision.', 'axytos-wc'));
                wp_send_json_success(['message' => __('Order refunded successfully.', 'axytos-wc')]);
                break;
            
                case 'confirm':
                    //data for confirm order
                    $response_body = json_decode($order->get_meta('precheck_response'), true);
                            
                    $confirm_data = [
                        "customReference" => $order->get_order_number(),
                        "externalOrderId" => $unique_id,
                        "date" => date('c'),
                        "personalData" => [
                                "externalCustomerId" => (string) $order->get_user_id(),
                                "language" => get_locale(),
                                "email" => $order->get_billing_email(),
                                "mobilePhoneNumber" => $order->get_billing_phone(),
                            ],
                        "invoiceAddress" => [
                            "company" => $order->get_billing_company(),
                            "firstname" => $order->get_billing_first_name(),
                            "lastname" => $order->get_billing_last_name(),
                            "zipCode" => $order->get_billing_postcode(),
                            "city" => $order->get_billing_city(),
                            "country" => "DE",
                            "addressLine1" => $order->get_billing_address_1(),
                            "addressLine2" => $order->get_billing_address_2(),
                        ],
                        "deliveryAddress" => [
                            "company" => $order->get_shipping_company(),
                            "firstname" => $order->get_shipping_first_name(),
                            "lastname" => $order->get_shipping_last_name(),
                            "zipCode" => $order->get_shipping_postcode() ?: "00000",
                            "city" => $order->get_shipping_city() ?: "Unknown",
                            "country" => $order->get_shipping_country() ?: "DE",
                            "addressLine1" => $order->get_shipping_address_1(),
                            "addressLine2" => $order->get_shipping_address_2(),
                        ],
                        "basket" => [
                            "netTotal" => round($order->get_subtotal(), 2),
                            "grossTotal" => round($order->get_total(), 2),
                            "currency" => $order->get_currency(),
                            "positions" => array_values(array_map(function($item) {
                                $quantity = $item->get_quantity();
                                $grossPrice = $item->get_total();
                                $taxRate = 0.01; // Adjust based on tax settings
                                $netPrice = $grossPrice / (1 + $taxRate);
                                return [
                                    "productId" => $item->get_product_id(),
                                    "productName" => $item->get_name(),
                                    "productCategory" => "General",
                                    "quantity" => $quantity,
                                    "taxPercent" => $taxRate * 100,
                                    "netPricePerUnit" => $quantity > 0 ? round($netPrice / $quantity, 2) : 0,
                                    "grossPricePerUnit" => $quantity > 0 ? round($grossPrice / $quantity, 2) : 0,
                                    "netPositionTotal" => round($netPrice, 2),
                                    "grossPositionTotal" => round($grossPrice, 2),
                                ];
                            }, $order->get_items()))
                        ],

                        "orderPrecheckResponse" => $response_body
                    ];

        $confirm_response = $AxytosClient->orderConfirm($confirm_data);
        
        if (is_wp_error($confirm_response)) {
            // wc_add_notice(__('Payment error: Could not confirm order with Axytos API.', 'axytos-wc'), 'error');
            throw new Exception('Could not confirm order with Axytos API.');    
            return [];
        }
            default:
                wp_send_json_error(['message' => __('Invalid action.', 'axytos-wc')]);
        }
    } catch (Exception $e) {
        wp_send_json_error(['message' => __('Error processing action: ', 'axytos-wc') . $e->getMessage()]);
    }
}

function axytos_load_agreement() {
    // Verify the nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'axytos_agreement_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }
    $axyos_gateway_obj = new WC_Axytos_Payment_Gateway();
    $api = $axyos_gateway_obj->AxytosAPIKey;
    $sandbox = $axyos_gateway_obj->useSandbox;
    $AxytosClient = new AxytosApiClient($api, $sandbox);
    $agreement_content = $AxytosClient->getAgreement();
    wp_send_json_success($agreement_content);
}
add_action('wp_ajax_load_axytos_agreement', 'axytos_load_agreement');
add_action('wp_ajax_nopriv_load_axytos_agreement', 'axytos_load_agreement');

// Check if WooCommerce is active and load the gateway only if it is
function axytoswc_woocommerce_init() {
    if (class_exists('WC_Payment_Gateway')) {
        // Add the gateway to WooCommerce
        function axytoswc_add_gateway_class($gateways) {
            $gateways[] = 'WC_Axytos_Payment_Gateway';
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
        function axytos_add_agreement_link_to_gateway_description($description, $payment_id) {
            // Check if the current payment gateway is Axytos
            if ('axytoswc' === $payment_id) {
                $agreement_text = get_option('woocommerce_axytoswc_settings')['PrecheckAgreeText'];
                $description .= ' <br><a href="#" class="axytos-agreement-link">' . esc_html($agreement_text) . '</a>';
            }
        
            return $description;
        }
        add_filter('woocommerce_gateway_description', 'axytos_add_agreement_link_to_gateway_description', 10, 2);
        
        function axytos_enqueue_checkout_scripts() {
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
        // Define the payment gateway class
        class WC_Axytos_Payment_Gateway extends WC_Payment_Gateway {
            
            

            public function __construct() {
                
                require_once(__DIR__ . '/axytos-class.php');

                
                $this->id = 'axytoswc';
                $this->icon = ''; // URL of the icon that will be displayed on the checkout page
                $this->has_fields = true;
                $this->method_title = __('Axytos', 'axytos-wc');
                $this->method_description = __('Payment gateway for Axytos.', 'axytos-wc');

                // Load the settings
                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->enabled = $this->get_option('enabled');
                $this->AxytosAPIKey = $this->get_option('AxytosAPIKey');
                $this->useSandbox = $this->get_option('useSandbox');

                // Save settings
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
                
                //Setting up the class for Blocks
                add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateway_to_block_checkout' ] );

            }
            
            function add_gateway_to_block_checkout( $gateways ) {
                    $options = get_option( 'woocommerce_dummy_settings', array() );
            
                    if ( isset( $options['hide_for_non_admin_users'] ) ) {
                        $hide_for_non_admin_users = $options['hide_for_non_admin_users'];
                    } else {
                        $hide_for_non_admin_users = 'no';
                    }
            
                    if ( ( 'yes' === $hide_for_non_admin_users && current_user_can( 'manage_options' ) ) || 'no' === $hide_for_non_admin_users ) {
                        $gateways[] = 'WC_Axytos_Payment_Gateway';
                    }
                    return $gateways;
                }

            // Initialize form fields for the admin settings page
            public function init_form_fields() {
                $this->form_fields = [
                    'enabled' => [
                        'title' => __('Enable/Disable', 'axytos-wc'),
                        'type' => 'checkbox',
                        'label' => __('Enable Axytos Payment', 'axytos-wc'),
                        'default' => 'yes'
                    ],
                    'title' => [
                        'title' => __('Title', 'axytos-wc'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'axytos-wc'),
                        'default' => __('Axytos', 'axytos-wc'),
                        'desc_tip' => true,
                    ],
                    'description' => [
                        'title' => __('Description', 'axytos-wc'),
                        'type' => 'textarea',
                        'description' => __('This controls the description which the user sees during checkout.', 'axytos-wc'),
                        'default' => __('Pay using Axytos.', 'axytos-wc'),
                    ],
                    // 'decision_code_s' => [
                    //     'title' => __('Action on decision code "S"', 'axytos-wc'),
                    //     'type' => 'select',
                    //     'description' => __('Choose the action when decision code "S" is received.', 'axytos-wc'),
                    //     'default' => 'disallow',
                    //     'options' => [
                    //         'disallow' => __('Disallow This Payment Method', 'axytos-wc'),
                    //         // 'cancel' => __('Cancel Order', 'axytos-wc'),
                    //         'on-hold' => __('Put Order On-hold', 'axytos-wc'),
                    //         'proceed' => __('Proceed Order', 'axytos-wc'),
                    //     ],
                    //     'desc_tip' => true,
                    //     'class' => 'axytos-hidden',
                    // ],
                    //
                    // 'decision_code_r' => [
                    // 'title' => __('Action on decision code "R"', 'axytos-wc'),
                    // 'type' => 'select',
                    // 'description' => __('Choose the action when decision code "R" is received.', 'axytos-wc'),
                    // 'default' => 'on-hold',
                    // 'options' => [
                    //     'disallow' => __('Disallow This Payment Method', 'axytos-wc'),
                    //     'cancel' => __('Cancel Order', 'axytos-wc'),
                    //     'on-hold' => __('Put Order On-hold', 'axytos-wc'),
                    //     'proceed' => __('Proceed Order', 'axytos-wc'),
                    //   ],
                    //   'desc_tip' => true,
                    //   'class' => 'axytos-hidden',
                    // ],
                    // 'decision_code_u' => [
                    //     'title' => __('Action on decision code "U"', 'axytos-wc'),
                    //     'type' => 'select',
                    //     'description' => __('Choose the action when decision code "U" is received.', 'axytos-wc'),
                    //     'default' => 'proceed',
                    //     'options' => [
                    //         'disallow' => __('Disallow This Payment Method', 'axytos-wc'),
                    //         'cancel' => __('Cancel Order', 'axytos-wc'),
                    //         'on-hold' => __('Put Order On-hold', 'axytos-wc'),
                    //         'proceed' => __('Proceed Order', 'axytos-wc'),
                    //     ],
                    //     'desc_tip' => true,
                    //     'class' => 'axytos-hidden',
                    // ],
                    //
                    'AxytosAPIKey' => [
                        'title' => __('Axytos API Key', 'axytos-wc'),
                        'type' => 'text',
                        'description' => __('Enter your Axytos API Key for authentication.', 'axytos-wc'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'useSandbox' => [
                        'title' => __('Use API Sandbox', 'axytos-wc'),
                        'type' => 'checkbox',
                        'description' => __('Send API requests to the API sandbox for testing', 'axytos-wc'),
                        'default' => 'no',
                        'desc_tip' => true,
                    ],
                    'PrecheckAgreeText' => [
                        'title' => __('Precheck Agreement Link Text', 'axytos-wc'),
                        'type' => 'text',
                        'description' => __('Enter text you want to as link to get agreement.', 'axytos-wc'),
                        'default' => __('click to see agreement', 'axytos-wc'),
                        'desc_tip' => true,
                    ]
                ];
            }




            public function process_payment($order_id) {

                $order = wc_get_order($order_id);

                $AxytosClient = new AxytosApiClient($this->AxytosAPIKey, $this->useSandbox);
                // Data for Precheck
                $data = [
                    "requestMode" => "SingleStep",
                    "customReference" => $order->get_order_number(),
                    "personalData" => [
                        "externalCustomerId" => (string) $order->get_user_id(),
                        "language" => get_locale(),
                        "email" => $order->get_billing_email(),
                        "mobilePhoneNumber" => $order->get_billing_phone(),
                    ],
                    "paymentTypeSecurity" => "S", // Include this field
                    "selectedPaymentType" => "", // Include this field
                    "proofOfInterest" => "AAE", // Include this field
                    "invoiceAddress" => [
                        "company" => $order->get_billing_company(),
                        "firstname" => $order->get_billing_first_name(),
                        "lastname" => $order->get_billing_last_name(),
                        "zipCode" => $order->get_billing_postcode(),
                        "city" => $order->get_billing_city(),
                        "country" => "DE",
                        "addressLine1" => $order->get_billing_address_1(),
                        "addressLine2" => $order->get_billing_address_2(),
                    ],
                    "deliveryAddress" => [
                        "company" => $order->get_shipping_company(),
                        "firstname" => $order->get_shipping_first_name(),
                        "lastname" => $order->get_shipping_last_name(),
                        "zipCode" => $order->get_shipping_postcode() ?: "00000",
                        "city" => $order->get_shipping_city() ?: "Unknown",
                        "country" => $order->get_shipping_country() ?: "DE",
                        "addressLine1" => $order->get_shipping_address_1(),
                        "addressLine2" => $order->get_shipping_address_2(),
                    ],
                    "basket" => [
                        "netTotal" => round($order->get_subtotal(), 2),
                        "grossTotal" => round($order->get_total(), 2),
                        "currency" => $order->get_currency(),
                        "positions" => array_values(array_map(function($item) {
                            $quantity = $item->get_quantity();
                            $grossPrice = $item->get_total();
                            $taxRate = 0.01; // Adjust based on tax settings
                            $netPrice = $grossPrice / (1 + $taxRate);
                            return [
                                "productId" => $item->get_product_id(),
                                "productName" => $item->get_name(),
                                "productCategory" => "General",
                                "quantity" => $quantity,
                                "taxPercent" => $taxRate * 100,
                                "netPricePerUnit" => $quantity > 0 ? round($netPrice / $quantity, 2) : 0,
                                "grossPricePerUnit" => $quantity > 0 ? round($grossPrice / $quantity, 2) : 0,
                                "netPositionTotal" => round($netPrice, 2),
                                "grossPositionTotal" => round($grossPrice, 2),
                            ];
                        }, $order->get_items()))
                    ]
                ];
                
                $response = $AxytosClient->invoicePrecheck($data);

                if (is_wp_error($response)) {
                    // wc_add_notice(__('Payment error: Could not connect to Axytos API.', 'axytos-wc'), 'error');
                    throw new Exception('Could not connect to Axytos API.');    
                    return [];
                }

                $order->update_meta_data('precheck_response', $response);
        
                $response_body = json_decode($response, true);
                

                if (isset($response_body['decision'])) {
                    
                    
                    $decision_code = $response_body['decision'];
                    // $action = $this->get_option('decision_code_' . strtolower($decision_code));
                    $action = strtolower($decision_code) === "u" ? 'proceed' : 'disallow';
                    
                    //   $order_id = $order->get_id();
                    //     if (get_transient('disable_axitos_for_' . $order_id)) {
                    //         delete_transient('disable_axitos_for_' . $order_id);
                    //     }
                    
                    switch ($action) {
                        case 'proceed':

                            $unique_id = base64_encode($order->get_id() . mt_rand(0, 999) . microtime(true));
                            $order->update_meta_data( 'unique_id', $unique_id );
                            
                            //data for confirm order
                            
                            $confirm_data = [
                                            "customReference" => $order->get_order_number(),
                                            "externalOrderId" => $unique_id,
                                            "date" => date('c'),
                                            "personalData" => [
                                                    "externalCustomerId" => (string) $order->get_user_id(),
                                                    "language" => get_locale(),
                                                    "email" => $order->get_billing_email(),
                                                    "mobilePhoneNumber" => $order->get_billing_phone(),
                                                ],
                                            "invoiceAddress" => [
                                                "company" => $order->get_billing_company(),
                                                "firstname" => $order->get_billing_first_name(),
                                                "lastname" => $order->get_billing_last_name(),
                                                "zipCode" => $order->get_billing_postcode(),
                                                "city" => $order->get_billing_city(),
                                                "country" => "DE",
                                                "addressLine1" => $order->get_billing_address_1(),
                                                "addressLine2" => $order->get_billing_address_2(),
                                            ],
                                            "deliveryAddress" => [
                                                "company" => $order->get_shipping_company(),
                                                "firstname" => $order->get_shipping_first_name(),
                                                "lastname" => $order->get_shipping_last_name(),
                                                "zipCode" => $order->get_shipping_postcode() ?: "00000",
                                                "city" => $order->get_shipping_city() ?: "Unknown",
                                                "country" => $order->get_shipping_country() ?: "DE",
                                                "addressLine1" => $order->get_shipping_address_1(),
                                                "addressLine2" => $order->get_shipping_address_2(),
                                            ],
                                            "basket" => [
                                                "netTotal" => round($order->get_subtotal(), 2),
                                                "grossTotal" => round($order->get_total(), 2),
                                                "currency" => $order->get_currency(),
                                                "positions" => array_values(array_map(function($item) {
                                                    $quantity = $item->get_quantity();
                                                    $grossPrice = $item->get_total();
                                                    $taxRate = 0.01; // Adjust based on tax settings
                                                    $netPrice = $grossPrice / (1 + $taxRate);
                                                    return [
                                                        "productId" => $item->get_product_id(),
                                                        "productName" => $item->get_name(),
                                                        "productCategory" => "General",
                                                        "quantity" => $quantity,
                                                        "taxPercent" => $taxRate * 100,
                                                        "netPricePerUnit" => $quantity > 0 ? round($netPrice / $quantity, 2) : 0,
                                                        "grossPricePerUnit" => $quantity > 0 ? round($grossPrice / $quantity, 2) : 0,
                                                        "netPositionTotal" => round($netPrice, 2),
                                                        "grossPositionTotal" => round($grossPrice, 2),
                                                    ];
                                                }, $order->get_items()))
                                            ],

                                            "orderPrecheckResponse" => $response_body
                                        ];

                            $confirm_response = $AxytosClient->orderConfirm($confirm_data);
                            
                            if (is_wp_error($confirm_response)) {
                                // wc_add_notice(__('Payment error: Could not confirm order with Axytos API.', 'axytos-wc'), 'error');
                                throw new Exception('Could not confirm order with Axytos API.');    
                                return [];
                            }
                            
                            
                            $order->payment_complete();
                            
                                return [
                                'result' => 'success',
                                'redirect' => $this->get_return_url($order),
                            ];

                            break;
            
                        case 'cancel':
                            $order->update_status('cancelled', __('Order cancelled based on Axytos decision.', 'axytos-wc'));
                            // wc_add_notice(__('Order cancelled based on Axytos decision.', 'axytos-wc'), 'error');
                            throw new Exception('Order cancelled based on Axytos decision.');    
                            return [];
            
                        case 'on-hold':

                            $unique_id = base64_encode($order->get_id() . mt_rand(0, 999) . microtime(true));
                            $order->update_meta_data( 'unique_id', $unique_id );
                            $order->update_status('on-hold', __('Order on-hold based on Axytos decision.', 'axytos-wc'));
                            // wc_add_notice(__('Order on-hold based on Axytos decision.', 'axytos-wc'), 'success');
                            // $order->payment_complete();
                                return [
                                'result' => 'success',
                                'redirect' => $this->get_return_url($order),
                            ];

                            break;
            
                        case 'disallow':
                        default:
                            // wc_add_notice(__('This Payment Method is not allowed for this order. Please try a different payment method.', 'axytos-wc'), 'error');
                        
                            
                            $order_id = $order->get_id(); 
                            set_transient('disable_axitos_for_' . $order_id, true, 3600);
                            
                            throw new Exception(__('This Payment Method is not allowed for this order. Please try a different payment method.', 'axytos-wc'));
                            return [];



                    }
                }
                elseif (isset($response_body['errors'])){
                    //print_r($response_body); exit;

                        // wc_add_notice(__('Decision not found, contact administrator.', 'axytos-wc'), 'error');
                        throw new Exception('Decision not found, contact administrator.');

                    }

                    return [];
                
                
            }


      // TODO: refactor - make AxytosClient a property of WC_Axytos_Payment_Gateway
      function createInvoice($order, $AxytosClient) {
        $unique_id = $order->get_meta('unique_id');
        $invoiceData = [
          "externalorderId" => $unique_id,
          "externalInvoiceNumber" => $order->get_order_number(), 
          "externalInvoiceDisplayName" => sprintf("Invoice #%s", $order->get_order_number()),
          "externalSubOrderId" => "", 
          "date" => date('c', strtotime($order->get_date_created())), // Order creation date in ISO 8601
          "dueDateOffsetDays" => 14, 
          "basket" => [
            "grossTotal" => (float) $order->get_total(), 
            "netTotal" => (float) $order->get_subtotal(), 
            "positions" => array_values(array_map(function ($item) {
              $quantity = $item->get_quantity();
              $grossPrice = (float) $item->get_total();
              $taxRate = (float) $item->get_tax_class() ?: 0.0; // Retrieve the tax rate, default 0
              $netPrice = $grossPrice / (1 + $taxRate / 100);
              return [
                "productId" => $item->get_product_id(),
                "quantity" => $quantity,
                "taxPercent" => $taxRate,
                "netPricePerUnit" => $quantity > 0 ? round($netPrice / $quantity, 2) : 0,
                "grossPricePerUnit" => $quantity > 0 ? round($grossPrice / $quantity, 2) : 0,
                "netPositionTotal" => round($netPrice, 2),
                "grossPositionTotal" => round($grossPrice, 2)
              ];
            }, $order->get_items())), 
            "taxGroups" => [
              [
                "taxPercent" => $order->get_total_tax() > 0 ? round($order->get_total_tax() / $order->get_subtotal() * 100, 2) : 0,
                "valueToTax" => (float) $order->get_subtotal(),
                "total" => (float) $order->get_total_tax()
              ]
            ]
          ]
        ];

        $success = false;
        try {
          $invoice_response = $AxytosClient->createInvoice($invoiceData);
          $invoice_number = json_decode($invoice_response, true)['invoiceNumber']  ?? null;
          if (empty($invoice_number)){
              $invoice_number = null;
              error_log("Axytos API: 'invoiceNumber' not found in the response. Response: " . $invoice_response);
          }
          
          $order->update_meta_data( 'axytos_invoice_number', $invoice_number );
          $success = true;
        } catch (Exception $e) {
          error_log("Axytos API: could not create invoice: " . $e->getMessage());
        }
        return $success;
      }



        }
    }
}
add_action('plugins_loaded', 'axytoswc_woocommerce_init');
