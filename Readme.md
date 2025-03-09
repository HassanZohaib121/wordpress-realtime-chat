# Live Support Chat Plugin for WordPress  

This WordPress plugin enables real-time customer support using a WebSocket-based chat system.  

## 📌 Features  
- Floating chat button with a popup chat window for frontend users  
- Real-time messaging using WebSockets  
- Secure WebSocket server using PHP (Ratchet)  
- WordPress admin panel for support agents to respond  
- AJAX fallback for non-WebSocket browsers  

---

## 🛠️ Installation  

### **1. Upload and Activate the Plugin**  
1. Download the plugin files.  
2. install the plugin from the WordPress admin panel.
3. Activate the plugin from the WordPress admin panel.  

### **2. Configure the Plugin**  
1. Navigate to **Live Support Chat → Settings** in the WordPress admin panel.  
2. Set the WebSocket server URL (e.g., `https://yourdomain.com`).  
3. Enable or disable chat history logging.  

## **3. Add Widget to Your Website**  
Use [live_chat] shortcode to add the chat widget to your Page.

---

## 🚀 Running the WebSocket Server  

### **1. Move `server.php` to the following directory:**  

wp-content/uploads/live-support/

### **Start the WebSocket Server**  
Run the following command in your terminal:  
```sh
php wp-content/uploads/live-support/server.php
```

To run it in the background, use:
```sh
nohup php wp-content/uploads/live-support/server.php > /dev/null 2>&1 &
```

For production use, consider setting up a service or using a process manager like Supervisor.

---

## 📝 Changelog  
**1.0.0**  
- Initial release


## 🔧 Troubleshooting

### 1. WebSocket Server Not Connecting

    Ensure the server is running and accessible.
    Check for firewall rules blocking port 8080.
    Verify that allow_url_fopen is enabled in php.ini.

### 2. Chat Widget Not Appearing

    Ensure the plugin is activated.
    chech the shortcode is used correctly.
    Check for JavaScript errors in the browser console.
    Clear the WordPress cache and refresh the page.

---

## 📝 License  
[GNU General Public License v2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)    
