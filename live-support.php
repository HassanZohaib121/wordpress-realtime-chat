<?php
/**
 * Plugin Name: Live Support Chat
 * Plugin URI: https://example.com/plugins/live-support-chat
 * Description: Real-time customer support using WebSockets
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: live-support-chat
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('LIVE_SUPPORT_VERSION', '1.0.0');
define('LIVE_SUPPORT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LIVE_SUPPORT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once LIVE_SUPPORT_PLUGIN_DIR . 'includes/class-live-support.php';
require_once LIVE_SUPPORT_PLUGIN_DIR . 'admin/class-live-support-admin.php';
require_once LIVE_SUPPORT_PLUGIN_DIR . 'public/class-live-support-public.php';
require_once LIVE_SUPPORT_PLUGIN_DIR . 'includes/class-live-support-db.php';

// Activation and deactivation hooks
register_activation_hook(__FILE__, 'live_support_activate');
register_deactivation_hook(__FILE__, 'live_support_deactivate');

/**
 * Plugin activation function
 */
function live_support_activate() {
    // Create database tables
    require_once LIVE_SUPPORT_PLUGIN_DIR . 'includes/class-live-support-db.php';
    $db = new Live_Support_DB();
    $db->create_tables();
    
    // Set default options
    $default_options = array(
        'websocket_port' => '8080',
        'allowed_origins' => home_url(),
        'enable_history' => 'yes',
        'agent_roles' => array('administrator', 'editor')
    );
    
    add_option('live_support_options', $default_options);
    
    // Create uploads directory for the WebSocket server
    $upload_dir = wp_upload_dir();
    $websocket_dir = $upload_dir['basedir'] . '/live-support';
    
    if (!file_exists($websocket_dir)) {
        wp_mkdir_p($websocket_dir);
    }
    
    // Create WebSocket server file
    $server_file = $websocket_dir . '/server.php';
    if (!file_exists($server_file)) {
        $server_content = file_get_contents(LIVE_SUPPORT_PLUGIN_DIR . 'includes/server-template.php');
        file_put_contents($server_file, $server_content);
    }
}

/**
 * Plugin deactivation function
 */
function live_support_deactivate() {
    // Clear any scheduled events
    wp_clear_scheduled_hook('live_support_cleanup_history');
}

/**
 * Initialize the plugin
 */
function run_live_support() {
    $plugin = new Live_Support();
    $plugin->run();
}

run_live_support();

