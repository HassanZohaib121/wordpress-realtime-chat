<div class="live-support-widget" data-title="<?php echo esc_attr($atts['title']); ?>" data-welcome-message="<?php echo esc_attr($atts['welcome_message']); ?>">
    <button class="live-support-button">
        <span class="live-support-button-text"><?php echo esc_html($atts['button_text']); ?></span>
        <span class="live-support-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
        </span>
    </button>
    
    <div class="live-support-popup">
        <div class="live-support-header">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            <button class="live-support-close" aria-label="Close chat">&times;</button>
        </div>
        
        <div class="live-support-body">
            <div class="live-support-messages"></div>
            
            <div class="live-support-typing-indicator" style="display: none;">
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-text"><?php _e('Agent is typing...', 'live-support-chat'); ?></span>
            </div>
            
            <div class="live-support-start-form">
                <p><?php _e('Please fill out the form below to start chatting with our support team.', 'live-support-chat'); ?></p>
                
                <div class="live-support-form-group">
                    <label for="live-support-name"><?php _e('Name', 'live-support-chat'); ?></label>
                    <input type="text" id="live-support-name" name="name" required>
                </div>
                
                <div class="live-support-form-group">
                    <label for="live-support-email"><?php _e('Email', 'live-support-chat'); ?></label>
                    <input type="email" id="live-support-email" name="email" required>
                </div>
                
                <button class="live-support-start-chat"><?php _e('Start Chat', 'live-support-chat'); ?></button>
            </div>
            
            <div class="live-support-chat-form" style="display: none;">
                <textarea id="live-support-message" placeholder="<?php esc_attr_e('Type your message...', 'live-support-chat'); ?>"></textarea>
                <button class="live-support-send">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </div>
        </div>
    </div>
</div>

