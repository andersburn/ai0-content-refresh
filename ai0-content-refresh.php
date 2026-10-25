<?php
/**
 * Plugin Name: Ai0 Content Refresh
 * Description: Forbedrer eksisterende sider og indlæg med OpenAI, Search Console-prioritering og fuld ændringshistorik.
 * Version: 1.3.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Ai0
 * License: GPL-2.0-or-later
 * Text Domain: ai0-content-refresh
 */

defined('ABSPATH') || exit;
define('AI0R_VERSION', '1.3.0');
define('AI0R_FILE', __FILE__);
define('AI0R_DIR', __DIR__ . '/');
require_once AI0R_DIR . 'includes/class-store.php';
require_once AI0R_DIR . 'includes/class-content.php';
require_once AI0R_DIR . 'includes/class-exchange.php';
require_once AI0R_DIR . 'includes/class-cost.php';
require_once AI0R_DIR . 'includes/class-google.php';
require_once AI0R_DIR . 'includes/class-plugin.php';
require_once AI0R_DIR . 'includes/class-admin.php';

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
