<?php
/**
 * The admin-specific functionality of the plugin.
 */
class Live_Support_Admin {

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style('live-support-admin', LIVE_SUPPORT_PLUGIN_URL . 'admin/css/live-support-admin.css', array(), LIVE_SUPPORT_VERSION, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'live-support') === false) {
            return;
        }
        
        wp_enqueue_script('live-support-admin', LIVE_SUPPORT_PLUGIN_URL . 'admin/js/live-support-admin.js', array('jquery'), LIVE_SUPPORT_VERSION, false);
        
        // Add WebSocket connection data
        $options = get_option('live_support_options');
        $protocol = is_ssl() ? 'wss://' : 'ws://';
        $host = parse_url(home_url(), PHP_URL_HOST);
        $port = isset($options['websocket_port']) ? $options['websocket_port'] : '8080';
        
        // Add this line to debug the WebSocket URL
        error_log('WebSocket URL: ' . $protocol . $host . ':' . $port);
        
        // Initialize the LiveSupportAdmin object with all required properties
        $admin_data = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'websocket_url' => $protocol . $host . ':' . $port,
            'nonce' => wp_create_nonce('live-support-admin-nonce'),
            'current_user_id' => get_current_user_id(),
            'current_user_name' => wp_get_current_user()->display_name,
            'debug_info' => array(
                'is_ssl' => is_ssl(),
                'home_url' => home_url(),
                'parsed_host' => $host,
                'configured_port' => $port,
                'options' => $options
            )
        );
        
        // Add a small script to ensure the object is available before the main script runs
        wp_add_inline_script('live-support-admin', 'window.LiveSupportAdmin = ' . json_encode($admin_data) . ';', 'before');
    }

    /**
     * Add menu items to the admin dashboard.
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Live Support', 'live-support-chat'),
            __('Live Support', 'live-support-chat'),
            'manage_options',
            'live-support',
            array($this, 'display_dashboard_page'),
            'dashicons-format-chat',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'live-support',
            __('Dashboard', 'live-support-chat'),
            __('Dashboard', 'live-support-chat'),
            'manage_options',
            'live-support',
            array($this, 'display_dashboard_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'live-support',
            __('Settings', 'live-support-chat'),
            __('Settings', 'live-support-chat'),
            'manage_options',
            'live-support-settings',
            array($this, 'display_settings_page')
        );
        
        // History submenu
        add_submenu_page(
            'live-support',
            __('Chat History', 'live-support-chat'),
            __('Chat History', 'live-support-chat'),
            'manage_options',
            'live-support-history',
            array($this, 'display_history_page')
        );
    }

    /**
     * Display the dashboard page.
     */
    public function display_dashboard_page() {
        include_once LIVE_SUPPORT_PLUGIN_DIR . 'admin/partials/live-support-admin-dashboard.php';
    }

    /**
     * Display the settings page.
     */
    public function display_settings_page() {
        include_once LIVE_SUPPORT_PLUGIN_DIR . 'admin/partials/live-support-admin-settings.php';
    }

    /**
     * Display the history page.
     */
    public function display_history_page() {
        include_once LIVE_SUPPORT_PLUGIN_DIR . 'admin/partials/live-support-admin-history.php';
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting('live_support_options', 'live_support_options');
        
        add_settings_section(
            'live_support_websocket_settings',
            __('WebSocket Settings', 'live-support-chat'),
            array($this, 'websocket_settings_callback'),
            'live_support_options'
        );
        
        add_settings_field(
            'websocket_port',
            __('WebSocket Port', 'live-support-chat'),
            array($this, 'websocket_port_callback'),
            'live_support_options',
            'live_support_websocket_settings'
        );
        
        add_settings_field(
            'allowed_origins',
            __('Allowed Origins', 'live-support-chat'),
            array($this, 'allowed_origins_callback'),
            'live_support_options',
            'live_support_websocket_settings'
        );
        
        // Chat settings section
        add_settings_section(
            'live_support_chat_settings',
            __('Chat Settings', 'live-support-chat'),
            array($this, 'chat_settings_callback'),
            'live_support_options'
        );
        
        add_settings_field(
            'enable_history',
            __('Enable Chat History', 'live-support-chat'),
            array($this, 'enable_history_callback'),
            'live_support_options',
            'live_support_chat_settings'
        );
        
        add_settings_field(
            'agent_roles',
            __('Support Agent Roles', 'live-support-chat'),
            array($this, 'agent_roles_callback'),
            'live_support_options',
            'live_support_chat_settings'
        );
    }
    
    /**
     * WebSocket settings section callback.
     */
    public function websocket_settings_callback() {
        echo '<p>' . __('Configure the WebSocket server settings.', 'live-support-chat') . '</p>';
    }
    
    /**
     * WebSocket port field callback.
     */
    public function websocket_port_callback() {
        $options = get_option('live_support_options');
        $port = isset($options['websocket_port']) ? $options['websocket_port'] : '8080';
        
        echo '<input type="text" id="websocket_port" name="live_support_options[websocket_port]" value="' . esc_attr($port) . '" />';
        echo '<p class="description">' . __('The port number for the WebSocket server. Default is 8080.', 'live-support-chat') . '</p>';
    }
    
    /**
     * Allowed origins field callback.
     */
    public function allowed_origins_callback() {
        $options = get_option('live_support_options');
        $origins = isset($options['allowed_origins']) ? $options['allowed_origins'] : home_url();
        
        echo '<input type="text" id="allowed_origins" name="live_support_options[allowed_origins]" value="' . esc_attr($origins) . '" class="regular-text" />';
        echo '<p class="description">' . __('Comma-separated list of allowed origins for WebSocket connections. Default is your site URL.', 'live-support-chat') . '</p>';
    }
    
    /**
     * Chat settings section callback.
     */
    public function chat_settings_callback() {
        echo '<p>' . __('Configure the chat functionality settings.', 'live-support-chat') . '</p>';
    }
    
    /**
     * Enable history field callback.
     */
    public function enable_history_callback() {
        $options = get_option('live_support_options');
        $enabled = isset($options['enable_history']) ? $options['enable_history'] : 'yes';
        
        echo '<select id="enable_history" name="live_support_options[enable_history]">';
        echo '<option value="yes" ' . selected($enabled, 'yes', false) . '>' . __('Yes', 'live-support-chat') . '</option>';
        echo '<option value="no" ' . selected($enabled, 'no', false) . '>' . __('No', 'live-support-chat') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Enable storing chat history in the database.', 'live-support-chat') . '</p>';
    }
    
    /**
     * Agent roles field callback.
     */
    public function agent_roles_callback() {
        $options = get_option('live_support_options');
        $agent_roles = isset($options['agent_roles']) ? $options['agent_roles'] : array('administrator', 'editor');
        
        $roles = get_editable_roles();
        
        foreach ($roles as $role_id => $role) {
            $checked = in_array($role_id, $agent_roles) ? 'checked="checked"' : '';
            echo '<label><input type="checkbox" name="live_support_options[agent_roles][]" value="' . esc_attr($role_id) . '" ' . $checked . ' /> ' . esc_html($role['name']) . '</label><br />';
        }
        
        echo '<p class="description">' . __('Select which user roles can act as support agents.', 'live-support-chat') . '</p>';
    }
    
    /**
     * AJAX handler for getting active chats.
     */
    public function ajax_get_chats() {
        check_ajax_referer('live-support-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $db = new Live_Support_DB();
        $chats = $db->get_active_chats();
        
        wp_send_json_success($chats);
    }
    
    /**
     * AJAX handler for getting chat messages.
     */
    public function ajax_get_messages() {
        check_ajax_referer('live-support-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $chat_id = isset($_POST['chat_id']) ? intval($_POST['chat_id']) : 0;
        
        if (!$chat_id) {
            wp_send_json_error('Invalid chat ID');
        }
        
        $db = new Live_Support_DB();
        $messages = $db->get_chat_messages($chat_id);
        
        wp_send_json_success($messages);
    }
}

