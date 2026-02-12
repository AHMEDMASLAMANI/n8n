<?php
/**
 * Plugin Name: Adobe Stock Metadata Injector (Private)
 * Description: Private admin-only plugin for AI-generated Adobe Stock metadata with XMP/IPTC injection.
 * Version: 0.2.0
 * Author: Private
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!defined('ASM_PLUGIN_FILE')) {
	define('ASM_PLUGIN_FILE', __FILE__);
}
if (!defined('ASM_PLUGIN_DIR')) {
	define('ASM_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

require_once ASM_PLUGIN_DIR . 'includes/class-asm-settings.php';
require_once ASM_PLUGIN_DIR . 'includes/class-asm-validator.php';
require_once ASM_PLUGIN_DIR . 'includes/class-asm-json.php';
require_once ASM_PLUGIN_DIR . 'includes/class-asm-provider-interface.php';
require_once ASM_PLUGIN_DIR . 'includes/class-asm-provider-openai.php';
require_once ASM_PLUGIN_DIR . 'includes/class-asm-provider-gemini.php';
require_once ASM_PLUGIN_DIR . 'includes/class-asm-provider-openrouter.php';
require_once ASM_PLUGIN_DIR . 'includes/class-asm-injector.php';
require_once ASM_PLUGIN_DIR . 'includes/class-asm-queue.php';
require_once ASM_PLUGIN_DIR . 'includes/class-asm-admin.php';

register_activation_hook(__FILE__, static function () {
	if (!wp_next_scheduled('asm_process_queue_event')) {
		wp_schedule_event(time() + 30, 'minute', 'asm_process_queue_event');
	}
});

register_deactivation_hook(__FILE__, static function () {
	wp_clear_scheduled_hook('asm_process_queue_event');
});

add_filter('cron_schedules', static function ($schedules) {
	if (!isset($schedules['minute'])) {
		$schedules['minute'] = [
			'interval' => 60,
			'display' => __('Every Minute', 'asm'),
		];
	}
	return $schedules;
});

add_action('plugins_loaded', static function () {
	$settings = new ASM_Settings();
	$validator = new ASM_Validator($settings);
	$injector = new ASM_Injector($settings);
	$queue = new ASM_Queue($settings, $validator, $injector);
	new ASM_Admin($settings, $queue);
	add_action('asm_process_queue_event', [$queue, 'process_next_job']);
});
