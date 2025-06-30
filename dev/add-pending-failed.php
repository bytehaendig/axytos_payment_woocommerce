<?php
/**
 * Quick and dirty web interface to add a failed pending action for testing
 * Usage: Access via browser: /wp-content/plugins/axytos-woocommerce/add-pending-failed.php?order_id=932&action=shipped
 */

// Load WordPress
$wp_load_path = __DIR__ . '/../../../wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
	die( 'Error: Could not find wp-load.php' );
}

require_once $wp_load_path;

// Load our classes
require_once __DIR__ . '/includes/AxytosActionHandler.php';

use Axytos\WooCommerce\AxytosActionHandler;

// Check if WooCommerce is active
if ( ! function_exists( 'wc_get_order' ) ) {
	die( 'Error: WooCommerce is not active' );
}

// Simple security check - only allow if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
	die( 'Error: Access denied. Admin privileges required.' );
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>Add Failed Pending Action - Testing Tool</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 40px; }
		.form-container { max-width: 500px; background: #f9f9f9; padding: 20px; border-radius: 5px; }
		.result { margin-top: 20px; padding: 15px; border-radius: 5px; }
		.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
		.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
		input, select, button { padding: 8px; margin: 5px 0; width: 100%; box-sizing: border-box; }
		button { background: #0073aa; color: white; border: none; cursor: pointer; }
		button:hover { background: #005a87; }
		.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
	</style>
</head>
<body>
	<h1>Add Failed Pending Action - Testing Tool</h1>
	
	<div class="info">
		<strong>Purpose:</strong> This tool adds pending actions with different states for testing purposes.<br>
		<strong>failed_count 0:</strong> Fresh pending action<br>
		<strong>failed_count 1-2:</strong> Retry-able failed actions<br>
		<strong>failed_count 3+:</strong> Broken actions (show remove button)
	</div>

	<div class="form-container">
		<form method="GET">
			<label for="order_id">Order ID:</label>
			<input type="number" id="order_id" name="order_id" value="<?php echo esc_attr( $_GET['order_id'] ?? '' ); ?>" required>
			
			<label for="action">Action Name:</label>
			<select id="action" name="action" required>
				<option value="">Select action...</option>
				<option value="confirm" <?php selected( $_GET['action'] ?? '', 'confirm' ); ?>>confirm</option>
				<option value="shipped" <?php selected( $_GET['action'] ?? '', 'shipped' ); ?>>shipped</option>
				<option value="invoice" <?php selected( $_GET['action'] ?? '', 'invoice' ); ?>>invoice</option>
				<option value="cancel" <?php selected( $_GET['action'] ?? '', 'cancel' ); ?>>cancel</option>
				<option value="refund" <?php selected( $_GET['action'] ?? '', 'refund' ); ?>>refund</option>
			</select>
			
			<label for="failed_count">Failed Count:</label>
			<select id="failed_count" name="failed_count" required>
				<option value="">Select failed count...</option>
				<option value="0" <?php selected( $_GET['failed_count'] ?? '', '0' ); ?>>0 - Fresh pending action</option>
				<option value="1" <?php selected( $_GET['failed_count'] ?? '', '1' ); ?>>1 - Failed once (retry-able)</option>
				<option value="2" <?php selected( $_GET['failed_count'] ?? '', '2' ); ?>>2 - Failed twice (retry-able)</option>
				<option value="3" <?php selected( $_GET['failed_count'] ?? '', '3' ); ?>>3 - Failed 3x (broken, shows remove button)</option>
				<option value="4" <?php selected( $_GET['failed_count'] ?? '', '4' ); ?>>4 - Failed 4x (broken, shows remove button)</option>
			</select>
			
			<button type="submit">Add Pending Action</button>
		</form>
	</div>

	<?php
	// Process the form if submitted
	if ( isset( $_GET['order_id'] ) && isset( $_GET['action'] ) && isset( $_GET['failed_count'] ) ) {
		$order_id     = intval( $_GET['order_id'] );
		$action_name  = sanitize_text_field( $_GET['action'] );
		$failed_count = intval( $_GET['failed_count'] );

		echo '<div class="result">';

		if ( $order_id <= 0 ) {
			echo '<div class="error">Error: Invalid order ID</div>';
		} elseif ( empty( $action_name ) ) {
			echo '<div class="error">Error: Action name is required</div>';
		} elseif ( $failed_count < 0 || $failed_count > 10 ) {
			echo '<div class="error">Error: Invalid failed count</div>';
		} else {
			// Get the order
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				echo '<div class="error">Error: Order #' . $order_id . ' not found</div>';
			} else {
				try {
					// Get current pending actions
					$action_handler  = new AxytosActionHandler();
					$pending_actions = $action_handler->getPendingActions( $order );

					// Check if action already exists
					$action_exists = false;
					foreach ( $pending_actions as $existing_action ) {
						if ( $existing_action['action'] === $action_name ) {
							$action_exists = true;
							break;
						}
					}

					if ( $action_exists ) {
						echo '<div class="error">Error: Action "' . esc_html( $action_name ) . '" already exists for order #' . $order_id . '</div>';
					} else {
						// Create action with specified failed_count
						$new_action = array(
							'action'       => $action_name,
							'created_at'   => gmdate( 'c' ),
							'failed_at'    => $failed_count > 0 ? gmdate( 'c' ) : null, // Only set failed_at if failed_count > 0
							'failed_count' => $failed_count,
							'data'         => array(),
						);

						// Add to pending actions
						$pending_actions[] = $new_action;

						// Save to order
						$order->update_meta_data( AxytosActionHandler::META_KEY_PENDING, $pending_actions );

						// Update broken status meta-data if this action is broken
						$has_broken_actions = false;
						foreach ( $pending_actions as $action ) {
							if ( ( $action['failed_count'] ?? 0 ) >= AxytosActionHandler::MAX_RETRIES ) {
								$has_broken_actions = true;
								break;
							}
						}

						if ( $has_broken_actions ) {
							$order->update_meta_data( AxytosActionHandler::META_KEY_BROKEN, true );
						} else {
							$order->delete_meta_data( AxytosActionHandler::META_KEY_BROKEN );
						}

						$order->save_meta_data();

						// Determine action state for display
						$action_state     = '';
						$will_show_remove = false;
						if ( $failed_count == 0 ) {
							$action_state = 'Fresh pending action';
						} elseif ( $failed_count < AxytosActionHandler::MAX_RETRIES ) {
							$action_state = 'Retry-able failed action';
						} else {
							$action_state     = 'Broken action (will show remove button)';
							$will_show_remove = true;
						}

						// Add order note
						$order->add_order_note(
							sprintf(
								'Test: Added action "%s" with failed_count=%d (%s) for testing purposes',
								$action_name,
								$failed_count,
								$action_state
							)
						);

						echo '<div class="success">';
						echo '<strong>✓ Successfully added action "' . esc_html( $action_name ) . '" to order #' . $order_id . '</strong><br>';
						echo '• failed_count: ' . $failed_count . ' (MAX_RETRIES=' . AxytosActionHandler::MAX_RETRIES . ')<br>';
						echo '• State: ' . esc_html( $action_state ) . '<br>';
						if ( $failed_count > 0 ) {
							echo '• failed_at: ' . esc_html( $new_action['failed_at'] ) . '<br>';
						}
						if ( $will_show_remove ) {
							echo '• This action will show a "Remove" button in the admin interface<br>';
						}
						echo '• <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">View Order in Admin →</a>';
						echo '</div>';
					}
				} catch ( Exception $e ) {
					echo '<div class="error">Error: ' . esc_html( $e->getMessage() ) . '</div>';
				}
			}
		}

		echo '</div>';
	}
	?>

	<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc; color: #666; font-size: 12px;">
		<strong>Note:</strong> This is a testing tool. Use only in development/staging environments.
	</div>
</body>
</html>