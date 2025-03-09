/**
 * Public JavaScript for Live Support Chat
 */
var LiveSupportPublic = LiveSupportPublic || {}
;(($) => {
  // WebSocket connection
  let socket = null
  let chatId = null
  let isTyping = false
  let typingTimer = null

  // DOM elements
  let $widget, $button, $popup, $messages, $startForm, $chatForm, $closeButton, $typingIndicator

  // Initialize
  function init() {
    $widget = $(".live-support-widget")

    if (!$widget.length) {
      return
    }

    $button = $widget.find(".live-support-button")
    $popup = $widget.find(".live-support-popup")
    $messages = $widget.find(".live-support-messages")
    $startForm = $widget.find(".live-support-start-form")
    $chatForm = $widget.find(".live-support-chat-form")
    $closeButton = $widget.find(".live-support-close")
    $typingIndicator = $widget.find(".live-support-typing-indicator")

    bindEvents()

    // Check for saved chat session
    checkSavedSession()
  }

  // Check for saved chat session
  function checkSavedSession() {
    const savedChatId = localStorage.getItem("live_support_chat_id")
    const savedName = localStorage.getItem("live_support_name")
    const savedEmail = localStorage.getItem("live_support_email")

    if (savedChatId && savedName && savedEmail) {
      chatId = savedChatId

      // Pre-fill form fields
      $widget.find("#live-support-name").val(savedName)
      $widget.find("#live-support-email").val(savedEmail)

      // If chat was active recently, auto-open it
      const lastActivity = localStorage.getItem("live_support_last_activity")
      if (lastActivity && Date.now() - Number.parseInt(lastActivity) < 3600000) {
        // 1 hour
        openChatWindow()
        $startForm.hide()
        $chatForm.show()

        // Load previous messages
        loadPreviousMessages()
      }
    }
  }

  // Load previous messages from localStorage
  function loadPreviousMessages() {
    const messages = JSON.parse(localStorage.getItem("live_support_messages") || "[]")

    messages.forEach((msg) => {
      appendMessage(msg.message, msg.type, new Date(msg.time))
    })

    // Connect to WebSocket server
    connectToServer()
  }

  // Save message to localStorage
  function saveMessageToStorage(message, type) {
    const messages = JSON.parse(localStorage.getItem("live_support_messages") || "[]")

    messages.push({
      message: message,
      type: type,
      time: new Date().toISOString(),
    })

    // Keep only last 50 messages
    if (messages.length > 50) {
      messages.shift()
    }

    localStorage.setItem("live_support_messages", JSON.stringify(messages))
    localStorage.setItem("live_support_last_activity", Date.now().toString())
  }

  // Bind events
  function bindEvents() {
    $button.on("click", toggleChatWindow)
    $closeButton.on("click", closeChatWindow)

    $widget.find(".live-support-start-chat").on("click", startChat)
    $widget.find(".live-support-send").on("click", sendMessage)

    $widget.find("#live-support-message").on("keydown", (e) => {
      // Send on Enter (but not with Shift)
      if (e.keyCode === 13 && !e.shiftKey) {
        e.preventDefault()
        sendMessage()
      }

      // Handle typing indicator
      if (!isTyping) {
        isTyping = true
        sendTypingStatus(true)
      }

      // Clear previous timer
      clearTimeout(typingTimer)

      // Set new timer
      typingTimer = setTimeout(() => {
        isTyping = false
        sendTypingStatus(false)
      }, 2000)
    })

    // Auto-resize textarea
    $widget.find("#live-support-message").on("input", function () {
      this.style.height = "auto"
      this.style.height = this.scrollHeight + "px"
    })
  }

  // Toggle chat window
  function toggleChatWindow() {
    if ($popup.hasClass("active")) {
      closeChatWindow()
    } else {
      openChatWindow()
    }
  }

  // Open chat window
  function openChatWindow() {
    $popup.addClass("active")

    // If we already have a chat ID, connect to WebSocket
    if (chatId) {
      connectToServer()
    }
  }

  // Close chat window
  function closeChatWindow() {
    $popup.removeClass("active")

    // Disconnect WebSocket
    if (socket) {
      socket.close()
      socket = null
    }
  }

  // Start a new chat
  function startChat() {
    const name = $widget.find("#live-support-name").val().trim()
    const email = $widget.find("#live-support-email").val().trim()

    if (!name || !email) {
      alert("Please fill out all fields")
      return
    }

    // Validate email
    if (!isValidEmail(email)) {
      alert("Please enter a valid email address")
      return
    }

    $.ajax({
      url: LiveSupportPublic.ajaxurl,
      type: "POST",
      data: {
        action: "live_support_start_chat",
        nonce: LiveSupportPublic.nonce,
        name: name,
        email: email,
        welcome_message: $widget.data("welcome-message"),
      },
      success: (response) => {
        if (response.success && response.data) {
          chatId = response.data.chat_id

          // Save session info
          localStorage.setItem("live_support_chat_id", chatId)
          localStorage.setItem("live_support_name", name)
          localStorage.setItem("live_support_email", email)
          localStorage.setItem("live_support_last_activity", Date.now().toString())

          // Show chat form, hide start form
          $startForm.hide()
          $chatForm.show()

          // Connect to WebSocket server
          connectToServer()

          // Add welcome message
          appendMessage(response.data.welcome_message, "agent")
          saveMessageToStorage(response.data.welcome_message, "agent")
        }
      },
      error: () => {
        alert("Error starting chat. Please try again.")
      },
    })
  }

  // Connect to WebSocket server
  function connectToServer() {
    if (socket) {
      return
    }

    try {
      socket = new WebSocket(LiveSupportPublic.websocket_url)

      socket.onopen = () => {
        // Register as user
        socket.send(
          JSON.stringify({
            action: "register",
            type: "user",
            chat_id: chatId,
          }),
        )
      }

      socket.onmessage = (event) => {
        handleSocketMessage(event)
      }

      socket.onclose = () => {
        appendSystemMessage("Connection closed. Please refresh the page to reconnect.")
      }

      socket.onerror = (error) => {
        console.error("WebSocket error:", error)
        appendSystemMessage("Connection error. Please try again later.")
      }
    } catch (e) {
      console.error("WebSocket connection error:", e)
      appendSystemMessage("Unable to connect to chat server. Please try again later.")
    }
  }

  // Handle WebSocket messages
  function handleSocketMessage(event) {
    try {
      const data = JSON.parse(event.data)

      switch (data.action) {
        case "message":
          if (data.is_agent) {
            appendMessage(data.message, "agent")
            saveMessageToStorage(data.message, "agent")

            // If chat window is closed, show notification
            if (!$popup.hasClass("active")) {
              showNotification("New message from support", data.message)
            }
          }
          break

        case "typing":
          if (data.is_agent) {
            $typingIndicator.toggle(data.is_typing)
          }
          break

        case "chat_closed":
          handleChatClosed()
          break
      }
    } catch (e) {
      console.error("Error parsing WebSocket message:", e)
    }
  }

  // Show browser notification
  function showNotification(title, message) {
    // Check if browser supports notifications
    if (!("Notification" in window)) {
      return
    }

    // Check if permission is granted
    if (Notification.permission === "granted") {
      createNotification(title, message)
    }
    // Otherwise, ask for permission
    else if (Notification.permission !== "denied") {
      Notification.requestPermission().then((permission) => {
        if (permission === "granted") {
          createNotification(title, message)
        }
      })
    }
  }

  // Create notification
  function createNotification(title, message) {
    const notification = new Notification(title, {
      body: message,
      icon: "/wp-content/plugins/live-support-chat/public/images/chat-icon.png",
    })

    notification.onclick = function () {
      window.focus()
      openChatWindow()
      this.close()
    }
  }

  // Send a message
  function sendMessage() {
    const $messageInput = $widget.find("#live-support-message")
    const message = $messageInput.val().trim()

    if (!message || !chatId) {
      return
    }

    // Send via AJAX for database storage
    $.ajax({
      url: LiveSupportPublic.ajaxurl,
      type: "POST",
      data: {
        action: "live_support_send_message",
        nonce: LiveSupportPublic.nonce,
        chat_id: chatId,
        message: message,
      },
      success: (response) => {
        if (response.success) {
          // Send via WebSocket for real-time
          if (socket && socket.readyState === WebSocket.OPEN) {
            socket.send(
              JSON.stringify({
                action: "message",
                chat_id: chatId,
                message: message,
                is_agent: false,
                user_id: LiveSupportPublic.current_user_id,
              }),
            )
          }

          // Append to chat
          appendMessage(message, "user")
          saveMessageToStorage(message, "user")

          // Clear input and reset height
          $messageInput.val("")
          $messageInput.css("height", "")

          // Reset typing indicator
          isTyping = false
          clearTimeout(typingTimer)

          // Update last activity
          localStorage.setItem("live_support_last_activity", Date.now().toString())
        }
      },
    })
  }

  // Send typing status
  function sendTypingStatus(isTyping) {
    if (!chatId || !socket || socket.readyState !== WebSocket.OPEN) {
      return
    }

    socket.send(
      JSON.stringify({
        action: "typing",
        chat_id: chatId,
        is_typing: isTyping,
        is_agent: false,
      }),
    )
  }

  // Append message to chat
  function appendMessage(message, type, timestamp = new Date()) {
    const $message = $("<div>")
      .addClass("live-support-message " + type)
      .text(message)

    const $time = $("<span>").addClass("live-support-message-time").text(formatTime(timestamp))

    $message.append($time)
    $messages.append($message)

    // Scroll to bottom
    scrollToBottom()
  }

  // Append system message
  function appendSystemMessage(message) {
    const $message = $("<div>").addClass("live-support-message system").text(message)

    $messages.append($message)
    scrollToBottom()
  }

  // Handle chat closed
  function handleChatClosed() {
    appendSystemMessage("This chat has been closed by the support agent.")
    saveMessageToStorage("This chat has been closed by the support agent.", "system")

    // Disable chat form
    $widget.find("#live-support-message").prop("disabled", true)
    $widget.find(".live-support-send").prop("disabled", true)

    // Close WebSocket
    if (socket) {
      socket.close()
      socket = null
    }

    // Clear saved session
    localStorage.removeItem("live_support_chat_id")
    localStorage.removeItem("live_support_last_activity")
  }

  // Scroll chat to bottom
  function scrollToBottom() {
    $messages.scrollTop($messages[0].scrollHeight)
  }

  // Format time
  function formatTime(date) {
    return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
  }

  // Validate email
  function isValidEmail(email) {
    const re =
      /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
    return re.test(email)
  }

  // Initialize on document ready
  $(document).ready(init)
})(jQuery)

