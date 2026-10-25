<?php
/**
 * Plugin Name: Ai0 Content Refresh
 * Description: Improve existing pages and posts with OpenAI, Search Console priorities and a complete change history.
 * Version: 1.5.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Ai0
 * License: GPL-2.0-or-later
 * Plugin URI: https://github.com/andersburn/ai0-content-refresh
 * Update URI: https://github.com/andersburn/ai0-content-refresh
 * Domain Path: /languages
 * Text Domain: ai0-content-refresh
 */

defined('ABSPATH') || exit;
define('AI0R_VERSION', '1.5.0');
define('AI0R_FILE', __FILE__);
define('AI0R_DIR', __DIR__ . '/');
require_once AI0R_DIR . 'includes/class-store.php';
require_once AI0R_DIR . 'includes/class-media.php';
require_once AI0R_DIR . 'includes/class-content.php';
require_once AI0R_DIR . 'includes/class-exchange.php';
require_once AI0R_DIR . 'includes/class-cost.php';
require_once AI0R_DIR . 'includes/class-google.php';
require_once AI0R_DIR . 'includes/class-plugin.php';
require_once AI0R_DIR . 'includes/class-admin.php';
require_once AI0R_DIR . 'includes/class-updater.php';

add_action('init', static function () {
    load_plugin_textdomain('ai0-content-refresh', false, dirname(plugin_basename(AI0R_FILE)) . '/languages');
}, 0);
AI0R_Updater::boot();

register_activation_hook(__FILE__, ['AI0R_Store', 'install']);
register_deactivation_hook(__FILE__, static function () {
    wp_clear_scheduled_hook('ai0r_tick');
    wp_clear_scheduled_hook('ai0r_google_sync');
    wp_clear_scheduled_hook('ai0r_cleanup');
    wp_clear_scheduled_hook('ai0r_exchange_sync');
});
add_action('plugins_loaded', static function () {
    if (get_option('ai0r_version') !== AI0R_VERSION) {
        AI0R_Store::install();
    }
    AI0R_Plugin::boot();
    AI0R_Admin::boot();
});
