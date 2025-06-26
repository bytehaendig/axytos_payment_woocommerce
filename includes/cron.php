<?php

namespace Axytos\WooCommerce;

if (!defined("ABSPATH")) {
    exit();
}

require_once __DIR__ . "/AxytosActionHandler.php";

/**
 * Handle Axytos cron jobs for processing pending actions
 */
// TODO: move to own file
class AxytosScheduler
{
    const CRON_HOOK = "axytos_process_pending_actions";

    /**
     * Initialize cron functionality
     */
    public static function init()
    {
        add_action(self::CRON_HOOK, [__CLASS__, "process_pending_actions"]);

        // Schedule recurring events
        add_action("wp", [__CLASS__, "schedule_events"]);

        // Clean up scheduled events on deactivation
        register_deactivation_hook(
            plugin_dir_path(dirname(__FILE__)) . "axytos-woocommerce.php",
            [__CLASS__, "clear_scheduled_events"]
        );
    }

    /**
     * Schedule recurring cron events
     */
    public static function schedule_events()
    {
        // Schedule main processing job (every 15 minutes)
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), "axytos_actions", self::CRON_HOOK);
        }
    }

    /**
     * Process pending actions cron job
     */
    public static function process_pending_actions()
    {
        self::process_pending_actions_with_logging();
    }

    /**
     * Process pending actions and record timestamp (shared by cron and manual processing)
     */
    public static function process_pending_actions_with_logging()
    {
        $handler = new AxytosActionHandler();

        try {
            // Record the start time as timestamp
            update_option('axytos_last_processing_run', time());
            
            $result = $handler->processAllPendingActions();

            if ($result["processed"] > 0 || $result["failed"] > 0) {
                error_log(
                    "Axytos cron: Processed {$result["processed"]} orders, {$result["failed"]} failed"
                );
            }

            return $result;
        } catch (\Exception $e) {
            error_log(
                "Axytos cron: Exception during pending actions processing: " .
                    $e->getMessage()
            );
            return ["processed" => 0, "failed" => 0];
        }
    }



    /**
     * Clear all scheduled events (used on plugin deactivation)
     */
    public static function clear_scheduled_events()
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Get next scheduled run times (for admin display)
     */
    public static function get_next_scheduled_times()
    {
        return [
            "process_pending" => wp_next_scheduled(self::CRON_HOOK),
        ];
    }

    /**
     * Get last processing run time
     */
    public static function get_last_processing_run()
    {
        return get_option('axytos_last_processing_run', false);
    }

    /**
     * Manually trigger processing (for admin use)
     */
    public static function trigger_manual_processing()
    {
        if (!current_user_can("manage_woocommerce")) {
            return false;
        }

        self::process_pending_actions();
        return true;
    }
}

/**
 * Add custom cron schedule for 15 minutes
 */
function add_cron_schedules($schedules)
{
    $schedules["axytos_actions"] = [
        "interval" => 120 * MINUTE_IN_SECONDS,
        "display" => "Axytos Actions",
    ];

    return $schedules;
}

/**
 * Bootstrap cron functionality
 */
function bootstrap_cron()
{
    add_filter("cron_schedules", __NAMESPACE__ . "\add_cron_schedules");
    AxytosScheduler::init();
}
