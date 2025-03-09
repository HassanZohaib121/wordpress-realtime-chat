<?php
/**
 * The core plugin class.
 */
class Live_Support {

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     */
    protected $loader;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        require_once LIVE_SUPPORT_PLUGIN_DIR . 'includes/class-live-support-loader.php';
        $this->loader = new Live_Support_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     */
    private function define_admin_hooks() {
        $plugin_admin = new Live_Support_Admin();
        
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        $this->loader->add_action('wp_ajax_live_support_get_chats', $plugin_admin, 'ajax_get_chats');
        $this->loader->add_action('wp_ajax_live_support_get_messages', $plugin_admin, 'ajax_get_messages');
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     */
    private function define_public_hooks() {
        $plugin_public = new Live_Support_Public();
        
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        $this->loader->add_shortcode('live_chat', $plugin_public, 'shortcode_live_chat');
        $this->loader->add_action('wp_ajax_live_support_start_chat', $plugin_public, 'ajax_start_chat');
        $this->loader->add_action('wp_ajax_nopriv_live_support_start_chat', $plugin_public, 'ajax_start_chat');
        $this->loader->add_action('wp_ajax_live_support_send_message', $plugin_public, 'ajax_send_message');
        $this->loader->add_action('wp_ajax_nopriv_live_support_send_message', $plugin_public, 'ajax_send_message');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        $this->loader->run();
    }
}

