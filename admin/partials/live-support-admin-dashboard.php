<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="live-support-dashboard">
        <div class="live-support-sidebar">
            <div class="live-support-status">
                <h2><?php _e('Server Status', 'live-support-chat'); ?></h2>
                <div id="server-status" class="server-status">
                    <span class="status-indicator disconnected"></span>
                    <?php _e('Connected', 'live-support-chat'); ?>
                </div>
                <!-- Find the connect button and add an inline onclick attribute as a fallback -->
                <!-- <button id="connect-server" class="button button-primary" 
                onclick="console.log('Button clicked via inline handler'); if(typeof connectButtonClicked === 'function') connectButtonClicked();"> -->
                <?php 
                // _e('Connect', 'live-support-chat'); 
                ?>
            <!-- </button> -->
          </div>
          
            <div class="live-support-active-chats">
                <h2><?php _e('Active Chats', 'live-support-chat'); ?></h2>
                <ul id="active-chats-list" class="active-chats-list">
                    <li class="no-chats"><?php _e('No active chats', 'live-support-chat'); ?></li>
                </ul>
            </div>
        </div>
        
        <div class="live-support-chat-area">
            <div id="chat-placeholder" class="chat-placeholder">
                <p><?php _e('Select a chat from the sidebar to start responding', 'live-support-chat'); ?></p>
            </div>
            
            <div id="chat-container" class="chat-container" style="display: none;">
                <div class="chat-header">
                    <h2 id="chat-user-name"></h2>
                    <div class="chat-actions">
                        <button id="close-chat" class="button"><?php _e('Close Chat', 'live-support-chat'); ?></button>
                    </div>
                </div>
                
                <div id="chat-messages" class="chat-messages"></div>
                
                <div class="chat-input">
                    <div id="typing-indicator" class="typing-indicator" style="display: none;">
                        <?php _e('User is typing...', 'live-support-chat'); ?>
                    </div>
                    <textarea id="message-input" placeholder="<?php esc_attr_e('Type your message...', 'live-support-chat'); ?>"></textarea>
                    <button id="send-message" class="button button-primary"><?php _e('Send', 'live-support-chat'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add this script at the bottom of the file, just before the closing </div> -->
<script>
// Direct button click handler as a fallback
function connectButtonClicked() {
    console.log("Connect button clicked via global function");
    if (window.LiveSupportAdmin && window.LiveSupportAdmin.connectToServer) {
        window.LiveSupportAdmin.connectToServer();
    } else {
        console.error("LiveSupportAdmin object or connectToServer function not available");
        alert("Connection error: LiveSupportAdmin not properly initialized. Check console for details.");
    }
}

// Add this to ensure jQuery is ready and the button exists
jQuery(document).ready(function($) {
    console.log("Document ready, checking for connect button");
    var $connectBtn = $("#connect-server");
    
    if ($connectBtn.length) {
        console.log("Connect button found, attaching click handler");
        $connectBtn.on("click", function(e) {
            console.log("Connect button clicked via jQuery handler");
            e.preventDefault();
            connectButtonClicked();
        });
    } else {
        console.error("Connect button not found in DOM");
    }
});
</script>

