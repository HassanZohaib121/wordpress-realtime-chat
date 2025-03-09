<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="live-support-history">
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get">
                    <input type="hidden" name="page" value="live-support-history">
                    
                    <label for="filter-date" class="screen-reader-text"><?php _e('Filter by date', 'live-support-chat'); ?></label>
                    <input type="date" id="filter-date" name="date" value="<?php echo isset($_GET['date']) ? esc_attr($_GET['date']) : ''; ?>">
                    
                    <label for="filter-status" class="screen-reader-text"><?php _e('Filter by status', 'live-support-chat'); ?></label>
                    <select id="filter-status" name="status">
                        <option value=""><?php _e('All statuses', 'live-support-chat'); ?></option>
                        <option value="active" <?php selected(isset($_GET['status']) && $_GET['status'] === 'active'); ?>><?php _e('Active', 'live-support-chat'); ?></option>
                        <option value="closed" <?php selected(isset($_GET['status']) && $_GET['status'] === 'closed'); ?>><?php _e('Closed', 'live-support-chat'); ?></option>
                    </select>
                    
                    <?php submit_button(__('Filter', 'live-support-chat'), 'action', 'filter', false); ?>
                </form>
            </div>
            <br class="clear">
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-id"><?php _e('ID', 'live-support-chat'); ?></th>
                    <th scope="col" class="manage-column column-name"><?php _e('Name', 'live-support-chat'); ?></th>
                    <th scope="col" class="manage-column column-email"><?php _e('Email', 'live-support-chat'); ?></th>
                    <th scope="col" class="manage-column column-status"><?php _e('Status', 'live-support-chat'); ?></th>
                    <th scope="col" class="manage-column column-date"><?php _e('Date', 'live-support-chat'); ?></th>
                    <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'live-support-chat'); ?></th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php
                global $wpdb;
                $table = $wpdb->prefix . 'live_support_chats';
                
                $where = array();
                $values = array();
                
                if (isset($_GET['date']) && !empty($_GET['date'])) {
                    $date = sanitize_text_field($_GET['date']);
                    $where[] = "DATE(created_at) = %s";
                    $values[] = $date;
                }
                
                if (isset($_GET['status']) && !empty($_GET['status'])) {
                    $status = sanitize_text_field($_GET['status']);
                    $where[] = "status = %s";
                    $values[] = $status;
                }
                
                $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
                
                $query = "SELECT * FROM $table $where_clause ORDER BY created_at DESC LIMIT 50";
                
                if (!empty($values)) {
                    $query = $wpdb->prepare($query, $values);
                }
                
                $chats = $wpdb->get_results($query);
                
                if (empty($chats)) {
                    echo '<tr><td colspan="6">' . __('No chat history found.', 'live-support-chat') . '</td></tr>';
                } else {
                    foreach ($chats as $chat) {
                        ?>
                        <tr>
                            <td class="column-id"><?php echo esc_html($chat->id); ?></td>
                            <td class="column-name"><?php echo esc_html($chat->name); ?></td>
                            <td class="column-email"><?php echo esc_html($chat->email); ?></td>
                            <td class="column-status">
                                <span class="status-<?php echo esc_attr($chat->status); ?>">
                                    <?php echo esc_html(ucfirst($chat->status)); ?>
                                </span>
                            </td>
                            <td class="column-date">
                                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($chat->created_at))); ?>
                            </td>
                            <td class="column-actions">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=live-support-history&view=chat&id=' . $chat->id)); ?>" class="button button-small">
                                    <?php _e('View', 'live-support-chat'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <?php
    // View single chat history
    if (isset($_GET['view']) && $_GET['view'] === 'chat' && isset($_GET['id'])) {
        $chat_id = intval($_GET['id']);
        
        $chat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $chat_id
        ));
        
        if ($chat) {
            $messages_table = $wpdb->prefix . 'live_support_messages';
            $messages = $wpdb->get_results($wpdb->prepare(
                "SELECT m.*, u.display_name 
                FROM $messages_table m 
                LEFT JOIN $wpdb->users u ON m.user_id = u.ID 
                WHERE m.chat_id = %d 
                ORDER BY m.created_at ASC",
                $chat_id
            ));
            ?>
            <div class="live-support-chat-history">
                <h2><?php printf(__('Chat with %s', 'live-support-chat'), esc_html($chat->name)); ?></h2>
                <p>
                    <strong><?php _e('Email:', 'live-support-chat'); ?></strong> <?php echo esc_html($chat->email); ?><br>
                    <strong><?php _e('Date:', 'live-support-chat'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($chat->created_at))); ?><br>
                    <strong><?php _e('Status:', 'live-support-chat'); ?></strong> <?php echo esc_html(ucfirst($chat->status)); ?>
                </p>
                
                <div class="chat-history-messages">
                    <?php
                    if (empty($messages)) {
                        echo '<p>' . __('No messages in this chat.', 'live-support-chat') . '</p>';
                    } else {
                        foreach ($messages as $message) {
                            $is_agent = (bool) $message->is_agent;
                            $name = $is_agent ? ($message->display_name ?: __('Support Agent', 'live-support-chat')) : $chat->name;
                            $class = $is_agent ? 'agent-message' : 'user-message';
                            ?>
                            <div class="chat-message <?php echo esc_attr($class); ?>">
                                <div class="message-header">
                                    <span class="message-sender"><?php echo esc_html($name); ?></span>
                                    <span class="message-time"><?php echo esc_html(date_i18n(get_option('time_format'), strtotime($message->created_at))); ?></span>
                                </div>
                                <div class="message-content"><?php echo esc_html($message->message); ?></div>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>
            <?php
        }
    }
    ?>
</div>

