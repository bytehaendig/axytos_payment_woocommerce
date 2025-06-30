<?php

namespace Axytos\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once __DIR__ . '/AxytosActionHandler.php';

/**
 * Handle Axytos cron jobs for processing pending actions
 */
// TODO: move to own file
class AxytosScheduler {

	const CRON_HOOK = 'axytos_process_pending_actions';

	/**
	 * Initialize cron functionality
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_pending_actions' ) );

		// Schedule recurring events
		add_action( 'wp', array( __CLASS__, 'schedule_events' ) );

		// Clean up scheduled events on deactivation
		register_deactivation_hook(
			plugin_dir_path( __DIR__ ) . 'axytos-woocommerce.php',
			array( __CLASS__, 'clear_scheduled_events' )
		);
	}

	/**
	 * Schedule recurring cron events
	 */
	public static function schedule_events() {
		// Schedule main processing job (every 15 minutes)
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'axytos_actions', self::CRON_HOOK );
		}
	}

	/**
	 * Process pending actions cron job
	 */
	public static function process_pending_actions() {
		self::process_pending_actions_with_logging();
	}

	/**
	 * Process pending actions and record timestamp (shared by cron and manual processing)
	 */
	public static function process_pending_actions_with_logging() {
		$handler = new AxytosActionHandler();

		try {
			// Record the start time as timestamp
			update_option( 'axytos_last_processing_run', time() );

			$result = $handler->processAllPendingActions();

			if ( $result['processed'] > 0 || $result['failed'] > 0 ) {
				error_log(
					"Axytos cron: Processed {$result["processed"]} orders, {$result["failed"]} failed"
				);
			}

			return $result;
		} catch ( \Exception $e ) {
			error_log(
				'Axytos cron: Exception during pending actions processing: ' .
					$e->getMessage()
			);
			return array(
				'processed' => 0,
				'failed'    => 0,
			);
		}
	}



	/**
	 * Clear all scheduled events (used on plugin deactivation)
	 */
	public static function clear_scheduled_events() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Get next scheduled run times (for admin display)
	 */
	public static function get_next_scheduled_times() {
		return array(
			'process_pending' => wp_next_scheduled( self::CRON_HOOK ),
		);
	}

	/**
	 * Get last processing run time
	 */
	public static function get_last_processing_run() {
		return get_option( 'axytos_last_processing_run', false );
	}

	/**
	 * Manually trigger processing (for admin use)
	 */
	public static function trigger_manual_processing() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		self::process_pending_actions();
		return true;
	}

	/**
	 * Check if WP cron is working properly
	 */
	public static function is_cron_working() {
		$last_run       = self::get_last_processing_run();
		$next_scheduled = wp_next_scheduled( self::CRON_HOOK );

		// If never run and cron is scheduled, it might be working (new install)
		if ( ! $last_run && $next_scheduled ) {
			return true;
		}

		// If no last run and no scheduled event, cron is broken
		if ( ! $last_run && ! $next_scheduled ) {
			return false;
		}

		// Check if last run was more than 3 intervals ago (6 hours)
		$max_delay    = 3 * 120 * MINUTE_IN_SECONDS; // 3 * 2 hours
		$current_time = current_time( 'timestamp' );

		return ( $current_time - $last_run ) <= $max_delay;
	}

	/**
	 * Get cron health status with details
	 */
	public static function get_cron_health() {
		$is_working       = self::is_cron_working();
		$last_run         = self::get_last_processing_run();
		$next_scheduled   = wp_next_scheduled( self::CRON_HOOK );
		$current_time     = current_time( 'timestamp' );
		$wp_cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

		$status = array(
			'is_working'       => $is_working,
			'last_run'         => $last_run,
			'next_scheduled'   => $next_scheduled,
			'wp_cron_disabled' => $wp_cron_disabled,
			'cron_type'        => $wp_cron_disabled ? 'external' : 'traffic-based',
			'message'          => '',
			'recommendation'   => '',
		);

		if ( $wp_cron_disabled ) {
			if ( $is_working ) {
				$status['message'] = __( 'External cron is configured and working properly.', 'axytos-wc' );
			} else {
				$status['message']        = __( 'External cron is configured but not working. Check your server cron job.', 'axytos-wc' );
				$status['recommendation'] = __( 'Ensure your server cron calls: wget -q -O - ' . home_url( '/wp-cron.php' ) . ' every 5 minutes.', 'axytos-wc' );
			}
		} elseif ( ! $next_scheduled ) {
				$status['message'] = __( 'No cron job scheduled. Try deactivating and reactivating the plugin.', 'axytos-wc' );
		} elseif ( ! $is_working && $last_run ) {
			$hours_since              = round( ( $current_time - $last_run ) / HOUR_IN_SECONDS, 1 );
			$status['message']        = sprintf(
				__( 'Traffic-based cron appears stuck. Last run was %s hours ago.', 'axytos-wc' ),
				$hours_since
			);
			$status['recommendation'] = __( 'Consider switching to external cron for better reliability, especially on low-traffic sites.', 'axytos-wc' );
		} elseif ( ! $last_run ) {
			$status['message']        = __( 'Traffic-based cron has never run. This may be normal for a new installation or low-traffic site.', 'axytos-wc' );
			$status['recommendation'] = __( 'For reliable processing, consider configuring external cron.', 'axytos-wc' );
		} else {
			$status['message'] = __( 'Traffic-based cron is working normally.', 'axytos-wc' );
		}

		return $status;
	}
}

/**
 * Add custom cron schedule for 15 minutes
 */
function add_cron_schedules( $schedules ) {
	$schedules['axytos_actions'] = array(
		'interval' => 120 * MINUTE_IN_SECONDS,
		'display'  => 'Axytos Actions',
	);

	return $schedules;
}

/**
 * Bootstrap cron functionality
 */
function bootstrap_cron() {
	add_filter( 'cron_schedules', __NAMESPACE__ . '\add_cron_schedules' );
	AxytosScheduler::init();
}
