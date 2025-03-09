<?php
/**
 * Database operations for the Live Support plugin.
 */
class Live_Support_DB {

    /**
     * Create the necessary database tables.
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Chat sessions table
        $table_chats = $wpdb->prefix . 'live_support_chats';
        $sql_chats = "CREATE TABLE $table_chats (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            ip_address varchar(45) NOT NULL,
            user_agent text NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Chat messages table
        $table_messages = $wpdb->prefix . 'live_support_messages';
        $sql_messages = "CREATE TABLE $table_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            chat_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            is_agent tinyint(1) NOT NULL DEFAULT 0,
            message text NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY chat_id (chat_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_chats);
        dbDelta($sql_messages);
    }
    
    /**
     * Create a new chat session.
     */
    public function create_chat($user_id, $name, $email) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'live_support_chats';
        $now = current_time('mysql');
        
        $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id ? $user_id : null,
                'name' => sanitize_text_field($name),
                'email' => sanitize_email($email),
                'status' => 'active',
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'created_at' => $now,
                'updated_at' => $now
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Add a message to a chat.
     */
    public function add_message($chat_id, $user_id, $is_agent, $message) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'live_support_messages';
        $now = current_time('mysql');
        
        $wpdb->insert(
            $table,
            array(
                'chat_id' => $chat_id,
                'user_id' => $user_id ? $user_id : null,
                'is_agent' => $is_agent ? 1 : 0,
                'message' => sanitize_textarea_field($message),
                'created_at' => $now
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );
        
        // Update the chat's updated_at timestamp
        $this->update_chat_timestamp($chat_id);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update a chat's timestamp.
     */
    private function update_chat_timestamp($chat_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'live_support_chats';
        $now = current_time('mysql');
        
        $wpdb->update(
            $table,
            array('updated_at' => $now),
            array('id' => $chat_id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Get active chats.
     */
    public function get_active_chats() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'live_support_chats';
        
        $sql = "SELECT * FROM $table WHERE status = 'active' ORDER BY updated_at DESC";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get messages for a chat.
     */
    public function get_chat_messages($chat_id) {
        global $wpdb;
        
        $table_messages = $wpdb->prefix . 'live_support_messages';
        $table_users = $wpdb->users;
        
        $sql = $wpdb->prepare(
            "SELECT m.*, u.display_name 
            FROM $table_messages m 
            LEFT JOIN $table_users u ON m.user_id = u.ID 
            WHERE m.chat_id = %d 
            ORDER BY m.created_at ASC",
            $chat_id
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Close a chat.
     */
    public function close_chat($chat_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'live_support_chats';
        
        $wpdb->update(
            $table,
            array('status' => 'closed'),
            array('id' => $chat_id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Clean up old chat history.
     */
    public function cleanup_old_chats($days = 30) {
        global $wpdb;
        
        $table_chats = $wpdb->prefix . 'live_support_chats';
        $table_messages = $wpdb->prefix . 'live_support_messages';
        
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        // Get old chat IDs
        $old_chats = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM $table_chats WHERE status = 'closed' AND updated_at < %s",
                $date
            )
        );
        
        if (!empty($old_chats)) {
            $ids = implode(',', array_map('intval', $old_chats));
            
            // Delete messages first
            $wpdb->query("DELETE FROM $table_messages WHERE chat_id IN ($ids)");
            
            // Then delete chats
            $wpdb->query("DELETE FROM $table_chats WHERE id IN ($ids)");
        }
    }
}

