<?php
/**
 * The public-facing functionality of the plugin.
 */
class Live_Support_Public {

    /**
     * Register the stylesheets for the public-facing side of the site.
     */
    public function enqueue_styles() {
        wp_enqueue_style('live-support-public', LIVE_SUPPORT_PLUGIN_URL . 'public/css/live-support-public.css', array(), LIVE_SUPPORT_VERSION, 'all');
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     */
    public function enqueue_scripts() {
        wp_enqueue_script('live-support-public', LIVE_SUPPORT_PLUGIN_URL . 'public/js/live-support-public.js', array('jquery'), LIVE_SUPPORT_VERSION, false);
        
        // Add WebSocket connection data
        $options = get_option('live_support_options');
        $protocol = is_ssl() ? 'wss://' : 'ws://';
        $host = parse_url(home_url(), PHP_URL_HOST);
        $port = isset($options['websocket_port']) ? $options['websocket_port'] : '8080';
        
        wp_localize_script('live-support-public', 'LiveSupportPublic', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'websocket_url' => $protocol . $host . ':' . $port,
            'nonce' => wp_create_nonce('live-support-public-nonce'),
            'current_user_id' => get_current_user_id(),
            'current_user_name' => wp_get_current_user()->display_name,
            'is_logged_in' => is_user_logged_in()
        ));
    }

    /**
     * Register the shortcode for the chat widget.
     */
    public function shortcode_live_chat($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Live Support', 'live-support-chat'),
            'button_text' => __('Chat with us', 'live-support-chat'),
            'welcome_message' => __('Welcome! How can we help you today?', 'live-support-chat')
        ), $atts, 'live_chat');
        
        ob_start();
        include LIVE_SUPPORT_PLUGIN_DIR . 'public/partials/live-support-public-chat-widget.php';
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for starting a chat.
     */
    public function ajax_start_chat() {
        check_ajax_referer('live-support-public-nonce', 'nonce');
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (empty($name) || empty($email)) {
            wp_send_json_error('Name and email are required');
        }
        
        $user_id = get_current_user_id();
        
        $db = new Live_Support_DB();
        $chat_id = $db->create_chat($user_id, $name, $email);
        
        if ($chat_id) {
            // Add initial system message
            $options = get_option('live_support_options');
            $welcome_message = isset($_POST['welcome_message']) ? sanitize_text_field($_POST['welcome_message']) : __('Welcome! How can we help you today?', 'live-support-chat');
            
            $db->add_message($chat_id, 0, true, $welcome_message);
            
            wp_send_json_success(array(
                'chat_id' => $chat_id,
                'welcome_message' => $welcome_message
            ));
        } else {
            wp_send_json_error('Failed to create chat');
        }
    }
    
    /**
     * AJAX handler for sending a message.
     */
    public function ajax_send_message() {
        check_ajax_referer('live-support-public-nonce', 'nonce');
        
        $chat_id = isset($_POST['chat_id']) ? intval($_POST['chat_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        
        if (!$chat_id || empty($message)) {
            wp_send_json_error('Chat ID and message are required');
        }
        
        $user_id = get_current_user_id();
        
        $db = new Live_Support_DB();
        $message_id = $db->add_message($chat_id, $user_id, false, $message);
        
        if ($message_id) {
            wp_send_json_success(array(
                'message_id' => $message_id
            ));
        } else {
            wp_send_json_error('Failed to send message');
        }
    }
}

