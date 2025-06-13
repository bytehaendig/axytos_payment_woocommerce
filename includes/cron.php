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
    const CLEANUP_HOOK = "axytos_cleanup_old_actions";

    /**
     * Initialize cron functionality
     */
    public static function init()
    {
        add_action(self::CRON_HOOK, [__CLASS__, "process_pending_actions"]);
        add_action(self::CLEANUP_HOOK, [__CLASS__, "cleanup_old_actions"]);

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
            wp_schedule_event(time(), "axytos_15min", self::CRON_HOOK);
        }

        // Schedule cleanup job (daily)
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time(), "daily", self::CLEANUP_HOOK);
        }
    }

    /**
     * Process pending actions cron job
     */
    public static function process_pending_actions()
    {
        $handler = new AxytosActionHandler();

        try {
            $result = $handler->processAllPendingActions();

            if ($result["processed"] > 0 || $result["failed"] > 0) {
                error_log(
                    "Axytos cron: Processed {$result["processed"]} orders, {$result["failed"]} failed"
                );
            }
        } catch (\Exception $e) {
            error_log(
                "Axytos cron: Exception during pending actions processing: " .
                    $e->getMessage()
            );
        }
    }

    /**
     * Cleanup old failed actions cron job
     */
    public static function cleanup_old_actions()
    {
        $handler = new AxytosActionHandler();

        try {
            $cleaned = $handler->cleanupOldFailedActions(30);

            if ($cleaned > 0) {
                error_log(
                    "Axytos cron: Cleaned up old failed actions from $cleaned orders"
                );
            }
        } catch (\Exception $e) {
            error_log(
                "Axytos cron: Exception during cleanup: " . $e->getMessage()
            );
        }
    }

    /**
     * Clear all scheduled events (used on plugin deactivation)
     */
    public static function clear_scheduled_events()
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::CLEANUP_HOOK);
    }

    /**
     * Get next scheduled run times (for admin display)
     */
    public static function get_next_scheduled_times()
    {
        return [
            "process_pending" => wp_next_scheduled(self::CRON_HOOK),
            "cleanup_old" => wp_next_scheduled(self::CLEANUP_HOOK),
        ];
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
    $schedules["axytos_15min"] = [
        "interval" => 15 * MINUTE_IN_SECONDS,
        "display" => __("Every 15 Minutes (Axytos)", "axytos-wc"),
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
