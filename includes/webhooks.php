<?
namespace Axytos\WooCommerce;

// Webhook handler (needed for REST API endpoint)
require_once plugin_dir_path(__FILE__) . "AxytosWebhookHandler.php";

$handler = new AxytosWebhookHandler();
add_action("rest_api_init", [$handler, "register_webhook_endpoint"]);
