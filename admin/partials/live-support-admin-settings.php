<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('live_support_options');
        do_settings_sections('live_support_options');
        submit_button();
        ?>
    </form>
    
    <div class="live-support-server-instructions">
        <h2><?php _e('WebSocket Server Instructions', 'live-support-chat'); ?></h2>
        <p><?php _e('To run the WebSocket server, you need to execute the following command on your server:', 'live-support-chat'); ?></p>
        
        <?php
        $upload_dir = wp_upload_dir();
        $server_file = $upload_dir['basedir'] . '/live-support/server.php';
        ?>
        
        <code>php <?php echo esc_html($server_file); ?></code>
        
        <p><?php _e('You can run this command in the background using:', 'live-support-chat'); ?></p>
        
        <code>nohup php <?php echo esc_html($server_file); ?> > /dev/null 2>&1 &</code>
        
        <p><?php _e('For production use, consider setting up a service or using a process manager like Supervisor.', 'live-support-chat'); ?></p>
        
        <h3><?php _e('Composer Dependencies', 'live-support-chat'); ?></h3>
        <p><?php _e('The WebSocket server requires Ratchet PHP. Install it using Composer:', 'live-support-chat'); ?></p>
        
        <code>composer require cboden/ratchet</code>
        
        <h3><?php _e('Troubleshooting Connection Issues', 'live-support-chat'); ?></h3>
        <p><?php _e('If you\'re having trouble connecting to the WebSocket server, check the following:', 'live-support-chat'); ?></p>
        
        <ol>
            <li><?php _e('Make sure the server is running. You should see "Live Support Server started!" in the console.', 'live-support-chat'); ?></li>
            <li><?php _e('Check that the port (default: 8080) is open in your firewall.', 'live-support-chat'); ?></li>
            <li><?php _e('If your site uses HTTPS, you may need to use a secure WebSocket connection (WSS).', 'live-support-chat'); ?></li>
            <li><?php _e('For WSS, you\'ll need to set up a proxy like Nginx or Apache to handle the SSL termination.', 'live-support-chat'); ?></li>
        </ol>
        
        <h4><?php _e('Example Nginx Configuration for WSS', 'live-support-chat'); ?></h4>
        <code>
# WebSocket proxy for Live Support Chat
server {
    listen 443 ssl;
    server_name your-domain.com;
    
    ssl_certificate /path/to/your/certificate.crt;
    ssl_certificate_key /path/to/your/private.key;
    
    location /wss/ {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
        </code>
        
        <h4><?php _e('Testing the WebSocket Connection', 'live-support-chat'); ?></h4>
        <p><?php _e('You can test your WebSocket connection using the following HTML code:', 'live-support-chat'); ?></p>
        
        <code>
&lt;!DOCTYPE html&gt;
&lt;html&gt;
&lt;head&gt;
    &lt;title&gt;WebSocket Test&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;h2&gt;WebSocket Test&lt;/h2&gt;
    &lt;div id="output"&gt;&lt;/div&gt;
    
    &lt;script&gt;
        const output = document.getElementById('output');
        const wsUrl = '<?php echo (is_ssl() ? 'wss://' : 'ws://') . parse_url(home_url(), PHP_URL_HOST) . ':' . (isset($options['websocket_port']) ? $options['websocket_port'] : '8080'); ?>';
        
        output.innerHTML += 'Connecting to: ' + wsUrl + '&lt;br&gt;';
        
        const socket = new WebSocket(wsUrl);
        
        socket.onopen = function(e) {
            output.innerHTML += 'Connection established!&lt;br&gt;';
        };
        
        socket.onmessage = function(event) {
            output.innerHTML += 'Received: ' + event.data + '&lt;br&gt;';
        };
        
        socket.onclose = function(event) {
            if (event.wasClean) {
                output.innerHTML += 'Connection closed cleanly&lt;br&gt;';
            } else {
                output.innerHTML += 'Connection died&lt;br&gt;';
            }
            output.innerHTML += 'Code: ' + event.code + ' Reason: ' + event.reason + '&lt;br&gt;';
        };
        
        socket.onerror = function(error) {
            output.innerHTML += 'Error: ' + error.message + '&lt;br&gt;';
        };
    &lt;/script&gt;
&lt;/body&gt;
&lt;/html&gt;
        </code>
    </div>
</div>

