<?php
/*
Plugin Name: Axytos WooCommerce Payment Gateway
Description: Axytos Payment Gateway for WooCommerce.
Version: 0.11
Author: Bytehändig Software Manufaktur
Author URI: https://bytehaendig.de
Text Domain: axytos-wc
Domain Path: /languages/
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
  exit;
}

define('AXYTOS_PAYMENT_ID', 'axytoswc');

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
      $chosen_payment_method = AXYTOS_PAYMENT_ID;
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
  if ($order->get_payment_method() === AXYTOS_PAYMENT_ID && $order->get_status() === 'on-hold') {
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
    if ($order->get_payment_method() === AXYTOS_PAYMENT_ID) {
      $nonce = wp_create_nonce('axytos_action_nonce');
      $order_status = $order->get_status();
      // Only show buttons if the order is neither completed nor cancelled
      if (!in_array($order_status, ['completed', 'cancelled', 'refunded'])) {
        echo '
          <div class="axytos-action-buttons-wrapper">
          <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="report_shipping">' . __('Report Shipping', 'axytos-wc') . '</button>
          <button class="button axytos-action-button" data-order-id="' . esc_attr($order->get_id()) . '" data-action="cancel">' . __('Cancel', 'axytos-wc') . '</button>
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
  if (!$order->get_payment_method() === AXYTOS_PAYMENT_ID) {
    error_log("Order did not use Axytos payment: #" .  $order_id);
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
  if ($order->get_payment_method() === AXYTOS_PAYMENT_ID) {
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
  // TODO: Verify nonce
  // check_ajax_referer('axytos_action_nonce', 'security');
  $axyos_gateway_obj = new WC_Axytos_Payment_Gateway();
  $order_id = absint($_POST['order_id']);
  $action_type = sanitize_text_field($_POST['action_type']);
  if (!$order_id || !$action_type) {
    wp_send_json_error(['message' => __('Invalid order or action.', 'axytos-wc')]);
  }
  $order = wc_get_order($order_id);
  if (!$order) {
    wp_send_json_error(['message' => __('Order not found.', 'axytos-wc')]);
  }
  if ($order->get_payment_method() !== AXYTOS_PAYMENT_ID) {
    wp_send_json_error(['message' => __('Not an Axytos order.', 'axytos-wc')]);
  }
  try {
    switch ($action_type) {
      case 'report_shipping':
        $axyos_gateway_obj->actionReportShipping($order);
        break;

      case 'cancel':
        $axyos_gateway_obj->actionCancel($order);
        break;

      case 'refund':
        $axyos_gateway_obj->actionRefund($order);
        break;

      case 'confirm':
        $axyos_gateway_obj->actionConfirm($order);
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
  $agreement_content = $axyos_gateway_obj->getAgreement();
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
      if (AXYTOS_PAYMENT_ID === $payment_id) {
        $agreement_text = get_option('woocommerce_axytoswc_settings')['PrecheckAgreeText'] ?? '';
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
      protected $client;
      private $encryption_key;

      public function __construct() {
        require_once(__DIR__ . '/axytos-class.php');
        require_once(__DIR__ . '/axytos-data.php');

        $this->id = AXYTOS_PAYMENT_ID;
        $this->icon = ''; // URL of the icon that will be displayed on the checkout page
        $this->has_fields = true;
        $this->method_title = __('Axytos', 'axytos-wc');
        $this->method_description = __('Payment gateway for Axytos.', 'axytos-wc');
        // Load the settings
        $this->encryption_key = wp_salt('auth');
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $AxytosAPIKey = $this->get_option('AxytosAPIKey');
        $useSandbox = $this->get_option('useSandbox');
        $this->client = new AxytosApiClient($AxytosAPIKey, $useSandbox);
        // Save settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        // Add filter for api-key encryption
        add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, array($this, 'encrypt_settings'), 10, 1);
        //Setting up the class for Blocks
        add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateway_to_block_checkout' ] );
      }

      public function encrypt_settings($settings) {
        if (!empty($settings['AxytosAPIKey'])) {
          $settings['AxytosAPIKey'] = $this->encrypt($settings['AxytosAPIKey']);
        }
        return $settings;
      }

      // Get decrypted value when using get_option
      public function get_option($key, $empty_value = null) {
          $value = parent::get_option($key, $empty_value);
          if ($key === 'AxytosAPIKey' && !empty($value)) {
              return $this->decrypt($value);
          }
          return $value;
      }

      private function encrypt($value) {
        if (empty($value)) return '';

        $method = 'aes-256-cbc';
        $ivlen = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivlen);

        $encrypted = openssl_encrypt(
          $value,
          $method,
          $this->encryption_key,
          0,
          $iv
        );

        return base64_encode($iv . $encrypted);
      }

      private function decrypt($encrypted_value) {
        if (empty($encrypted_value)) return '';

        $encrypted_value = base64_decode($encrypted_value);
        $method = 'aes-256-cbc';
        $ivlen = openssl_cipher_iv_length($method);

        $iv = substr($encrypted_value, 0, $ivlen);
        $encrypted = substr($encrypted_value, $ivlen);

        return openssl_decrypt(
          $encrypted,
          $method,
          $this->encryption_key,
          0,
          $iv
        );
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
        $decision_code = $this->doPrecheck($order);
        // $action = $this->get_option('decision_code_' . strtolower($decision_code));
        $action = strtolower($decision_code) === "u" ? 'proceed' : 'disallow';
        switch ($action) {
          case 'proceed':
            if ($this->confirmOrder($order)) {
              return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
              ];
            }
            break;

          // case 'cancel':
          //     $order->update_status('cancelled', __('Order cancelled based on Axytos decision.', 'axytos-wc'));
          //     // wc_add_notice(__('Order cancelled based on Axytos decision.', 'axytos-wc'), 'error');
          //     throw new Exception('Order cancelled based on Axytos decision.');    
          //     return [];
          //
          // case 'on-hold':
          //     $unique_id = base64_encode($order->get_id() . mt_rand(0, 999) . microtime(true));
          //     $order->update_meta_data( 'unique_id', $unique_id );
          //     $order->update_status('on-hold', __('Order on-hold based on Axytos decision.', 'axytos-wc'));
          //     // wc_add_notice(__('Order on-hold based on Axytos decision.', 'axytos-wc'), 'success');
          //     // $order->payment_complete();
          //         return [
          //         'result' => 'success',
          //         'redirect' => $this->get_return_url($order),
          //     ];
          //
          //     break;
          //
          case 'disallow':
          default:
            $order_id = $order->get_id(); 
            set_transient('disable_axitos_for_' . $order_id, true, 600);

            throw new Exception(__('This Payment Method is not allowed for this order. Please try a different payment method.', 'axytos-wc'));
        }
        return [];
      }

      function actionReportShipping($order) {
        // Report_shipping is called when admin clicks the button or when order changes state to 'completed'.
        // Since report_shipping also switches state to 'completed', we need to check if it has already been reported.
        $isShipped = $order->get_meta('axytos_shipped');
        $ok = true;
        if (!$isShipped) {
          $ok = $this->reportShipping($order);
          if ($ok) {
            $this->createInvoice($order);
          }
        }
        if ($ok) {
          $order->update_status(
            'completed', __('Order completed.', 'axytos-wc'), true // Notify customer (optional)
          );
          // TODO: refactor error handling
          wp_send_json_success(['message' => __('Shipping status reported successfully.', 'axytos-wc')]);
        }
        return true;
      }

      function actionCancel($order) {
        // same as with report_shipping - cancel is called when admin clicks the button or when order changes state to 'cancelled'.
        $isCanceled = $order->get_meta('axytos_canceled');
        if (!$isCanceled) {
          $result = $this->client->cancelOrder($order->get_order_number());
          // TODO: fix error handling
          if (is_wp_error($result)) {
            wp_send_json_error(['message' => __('Could not cancel order.', 'axytos-wc')]);
            return false;
          }
          $response_body = json_decode($result, true);
          if (isset($response_body['errors'])) {
            $msg = $response_body['errors']['orderStatus'][0];
            wp_send_json_error(['message' => __($msg, 'axytos-wc')]);
            return false;

          }
          $order->update_meta_data('axytos_canceled', true );
        }
        $order->update_status('cancelled', __('Order cancelled.', 'axytos-wc'));
        wp_send_json_success(['message' => __('Order canceled successfully.', 'axytos-wc')]);
        return true;
      }

      function actionRefund($order) {
        $refundData = createRefundData($order);
        $result = $this->client->refundOrder($refundData);
        if (is_wp_error($result)) {
          wp_send_json_error(['message' => __('Could not refund order.', 'axytos-wc')]);
          return false;
        }
        $response_body = json_decode($result, true);
        if (isset($response_body['errors'])) {
          $msg = $response_body['errors']['externalSubOrderId'][0] ?? $response_body['errors'][""][0];
          wp_send_json_error(['message' => __(strval($msg), 'axytos-wc')]);
          return false;

        }
        $order->update_status('refunded', __('Order refunded based on Axytos decision.', 'axytos-wc'));
        wp_send_json_success(['message' => __('Order refunded successfully.', 'axytos-wc')]);
        return true;
      }

      function actionConfirm($order) {
        return $this->confirmOrder($order);
      }

      function doPrecheck($order) {
        $data = createPrecheckData($order);
        $response = $this->client->invoicePrecheck($data);
        if (is_wp_error($response)) {
          // wc_add_notice(__('Payment error: Could not connect to Axytos API.', 'axytos-wc'), 'error');
          throw new Exception('Could not connect to Axytos API.');    
          return [];
        }
        $order->update_meta_data('precheck_response', $response);
        $response_body = json_decode($response, true);
        $decision_code = $response_body['decision'];
        return $decision_code;
      }

      function confirmOrder($order) {
        $confirm_data = createConfirmData($order);
        $confirm_response = $this->client->orderConfirm($confirm_data);
        if (is_wp_error($confirm_response)) {
          // wc_add_notice(__('Payment error: Could not confirm order with Axytos API.', 'axytos-wc'), 'error');
          throw new Exception('Could not confirm order with Axytos API.');    
          return false;
        }
        $order->payment_complete();
        return true;
      }

      function reportShipping($order) {
        $statusData = createShippingData($order);
        $result = $this->client->updateShippingStatus($statusData);
        if (is_wp_error($result)) {
          wp_send_json_error(['message' => __('Could not update report shipping.', 'axytos-wc')]);
          return false;
        }
        $response_body = json_decode($result, true);
        if (isset($response_body['errors'])) {
          $msg = $response_body['errors'][""][0] ?? 'Error Response from Axytos';
          wp_send_json_error(['message' => __($msg, 'axytos-wc')]);
          return false;
        }
        $order->update_meta_data( 'axytos_shipped', true );
        return true;
      }

      function createInvoice($order) {
        $invoiceData = createInvoiceData($order);
        $success = false;
        try {
          $invoice_response = $this->client->createInvoice($invoiceData);
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

      function getAgreement() {
        return $this->client->getAgreement();
      }
    }
  }
}
add_action('plugins_loaded', 'axytoswc_woocommerce_init');
