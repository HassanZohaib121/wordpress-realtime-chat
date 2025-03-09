var LiveSupportAdmin = LiveSupportAdmin || {}
;(($) => {
  // WebSocket connection
  let socket = null
  let currentChatId = null
  let isTyping = false
  let typingTimer = null
  let connectionAttempts = 0
  const MAX_RECONNECT_ATTEMPTS = 5

  // DOM elements
  let $serverStatus,
    $connectButton,
    $activeChats,
    $chatPlaceholder,
    $chatContainer,
    $chatUserName,
    $chatMessages,
    $messageInput,
    $sendButton,
    $closeChat,
    $typingIndicator

  // Initialize
  function init() {
    console.log("Initializing LiveSupportAdmin...")

    // Initialize DOM elements
    $serverStatus = $("#server-status")
    $connectButton = $("#connect-server")
    $activeChats = $("#active-chats-list")
    $chatPlaceholder = $("#chat-placeholder")
    $chatContainer = $("#chat-container")
    $chatUserName = $("#chat-user-name")
    $chatMessages = $("#chat-messages")
    $messageInput = $("#message-input")
    $sendButton = $("#send-message")
    $closeChat = $("#close-chat")
    $typingIndicator = $("#typing-indicator")

    // Check if elements exist
    if (!$serverStatus.length) {
      console.error("Server status element not found")
    }

    if (!$connectButton.length) {
      console.error("Connect button not found")
    } else {
      console.log("Connect button found")
    }

    bindEvents()

    // Auto-connect if on dashboard page
    if ($serverStatus.length) {
      console.log("Server status element found, auto-connecting...")
      connectToServer()
    }
  }

  // Bind events
  function bindEvents() {
    console.log("Binding events...")

    if ($connectButton.length) {
      console.log("Attaching click handler to connect button")
      $connectButton.off("click").on("click", (e) => {
        e.preventDefault()
        console.log("Connect button clicked")
        if (socket && socket.readyState !== WebSocket.CLOSED) {
          console.log("Closing existing connection")
          socket.close()
          updateServerStatus("disconnected")
          $connectButton.text("Connect")
        } else {
          console.log("Initiating new connection")
          connectToServer()
        }
      })
    } else {
      console.error("Cannot bind click event - connect button not found")
    }

    if ($sendButton.length) $sendButton.on("click", sendMessage)
    if ($closeChat.length) $closeChat.on("click", closeCurrentChat)

    if ($messageInput.length) {
      $messageInput.on("keydown", (e) => {
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
    }
  }

  // Connect to WebSocket server
  function connectToServer() {
    console.log("Starting connection attempt...")
    console.log("LiveSupportAdmin object:", window.LiveSupportAdmin)

    if (!window.LiveSupportAdmin || !window.LiveSupportAdmin.websocket_url) {
      console.error("Missing websocket_url in LiveSupportAdmin object")
      return
    }

    if (socket && socket.readyState !== WebSocket.CLOSED) {
      socket.close()
    }

    updateServerStatus("connecting")
    if ($connectButton.length) {
      $connectButton.prop("disabled", true).text("Connecting...")
    }

    // Display connection details for debugging
    const wsUrl = window.LiveSupportAdmin.websocket_url
   

    try {
      console.log("Connecting to: " + wsUrl)
      socket = new WebSocket(wsUrl)

      socket.onopen = () => {
        console.log("WebSocket connection established")
        updateServerStatus("connected")
        if ($connectButton.length) {
          $connectButton.prop("disabled", false).text("Disconnect")
        }
        connectionAttempts = 0


        // Register as agent
        socket.send(
          JSON.stringify({
            action: "register",
            type: "agent",
            agent_id: window.LiveSupportAdmin.current_user_id,
          }),
        )

        // Load active chats
        loadActiveChats()
      }

      socket.onmessage = (event) => {
        console.log("Received message:", event.data)
        try {
          const data = JSON.parse(event.data)
          console.log("Parsed message data:", data)
          handleSocketMessage(event)
        } catch (e) {
          console.error("Error parsing message:", e)

        }
      }

      socket.onclose = (event) => {
        console.log("WebSocket connection closed", event)
        updateServerStatus("disconnected")
        if ($connectButton.length) {
          $connectButton.prop("disabled", false).text("Connect")
        }

        // Try to reconnect if not manually closed
        if (connectionAttempts < MAX_RECONNECT_ATTEMPTS) {
          connectionAttempts++
          setTimeout(connectToServer, 3000)
        }
      }

      socket.onerror = (error) => {
        console.error("WebSocket error:", error)
      }
    } catch (e) {
      console.error("WebSocket connection error:", e)
      updateServerStatus("disconnected")
      if ($connectButton.length) {
        $connectButton.prop("disabled", false).text("Connect")
      }

      // Try alternative connection if this is the first attempt
      if (connectionAttempts < MAX_RECONNECT_ATTEMPTS) {
        connectionAttempts++
        setTimeout(connectToServer, 3000)
      }
    }
  }

  // Update server status indicator
  function updateServerStatus(status) {
    if ($serverStatus) {
      $serverStatus.find(".status-indicator").removeClass("connected disconnected connecting").addClass(status)

      let statusText = "Unknown"
      switch (status) {
        case "connected":
          statusText = "Connected"
          break
        case "disconnected":
          statusText = "Disconnected"
          break
        case "connecting":
          statusText = "Connecting..."
          break
      }

      $serverStatus.find(".status-indicator").next().text(statusText)
    }
  }

  // Load active chats
  function loadActiveChats() {
    $.ajax({
      url: LiveSupportAdmin.ajaxurl,
      type: "POST",
      data: {
        action: "live_support_get_chats",
        nonce: LiveSupportAdmin.nonce,
      },
      success: (response) => {
        if (response.success && response.data) {
          updateChatsList(response.data)
        }
      },
    })
  }

  // Update chats list
  function updateChatsList(chats) {
    $activeChats.empty()

    if (chats.length === 0) {
      $activeChats.append('<li class="no-chats">No active chats</li>')
      return
    }

    chats.forEach((chat) => {
      const $chatItem = $("<li>")
        .attr("data-chat-id", chat.id)
        .addClass("chat-item")
        .html(
          '<span class="chat-item-name">' +
            chat.name +
            "</span>" +
            '<span class="chat-item-email">' +
            chat.email +
            "</span>" +
            '<span class="chat-item-time">' +
            formatDate(chat.created_at) +
            "</span>",
        )
        .on("click", () => {
          openChat(chat.id, chat.name)
        })

      $activeChats.append($chatItem)
    })

    // If we have a current chat open, keep it selected
    if (currentChatId) {
      $activeChats.find('[data-chat-id="' + currentChatId + '"]').addClass("active")
    }
  }

  // Handle WebSocket messages
  function handleSocketMessage(event) {
    try {
      const data = JSON.parse(event.data)
      console.log("Processing message:", data)

      switch (data.action) {
        case "active_chats":
          console.log("Received active chats:", data.chats)
          updateChatsList(data.chats)
          break

        case "new_chat":
          console.log("Received new chat:", data.chat)
          addNewChat(data.chat)
          break

        case "message":
          console.log("Received message for chat:", data.chat_id)
          handleNewMessage(data)
          break

        case "typing":
          handleTypingIndicator(data)
          break

        case "chat_closed":
          console.log("Chat closed:", data.chat_id)
          handleChatClosed(data.chat_id)
          break

        case "user_disconnected":
          console.log("User disconnected from chat:", data.chat_id)
          handleUserDisconnected(data.chat_id)
          break

        default:
          console.log("Unknown action received:", data.action)
          break
      }
    } catch (e) {
      console.error("Error parsing WebSocket message:", e)
    }
  }

  // Add a new chat to the list
  function addNewChat(chat) {
    // Remove "no chats" message if present
    $activeChats.find(".no-chats").remove()

    // Check if chat already exists
    const $existingChat = $activeChats.find('[data-chat-id="' + chat.id + '"]')
    if ($existingChat.length) {
      return
    }

    const $chatItemNew = $("<li>")
      .attr("data-chat-id", chat.id)
      .addClass("chat-item")
      .html(
        '<span class="chat-item-name">' +
          chat.name +
          "</span>" +
          '<span class="chat-item-email">' +
          chat.email +
          "</span>" +
          '<span class="chat-item-time">' +
          formatDate(chat.created_at) +
          "</span>" +
          '<span class="chat-item-unread">New</span>',
      )
      .on("click", () => {
        openChat(chat.id, chat.name)
      })

    $activeChats.prepend($chatItemNew)
  }

  // Open a chat
  function openChat(chatId, userName) {
    currentChatId = chatId

    // Update UI
    $activeChats.find("li").removeClass("active")
    $activeChats.find('[data-chat-id="' + chatId + '"]').addClass("active")
    $activeChats.find('[data-chat-id="' + chatId + '"] .chat-item-unread').remove()

    $chatPlaceholder.hide()
    $chatContainer.show()
    $chatUserName.text(userName)
    $chatMessages.empty()

    // Load chat messages
    $.ajax({
      url: LiveSupportAdmin.ajaxurl,
      type: "POST",
      data: {
        action: "live_support_get_messages",
        nonce: LiveSupportAdmin.nonce,
        chat_id: chatId,
      },
      success: (response) => {
        if (response.success && response.data) {
          response.data.forEach((message) => {
            appendMessage(message)
          })

          // Scroll to bottom
          scrollToBottom()
        }
      },
    })
  }

  // Send a message
  function sendMessage() {
    const message = $messageInput.val().trim()

    if (!message || !currentChatId || !socket || socket.readyState !== WebSocket.OPEN) {
      return
    }

    const messageData = {
      action: "message",
      chat_id: currentChatId,
      message: message,
      is_agent: true,
      user_id: LiveSupportAdmin.current_user_id,
    }

    socket.send(JSON.stringify(messageData))

    // Clear input
    $messageInput.val("")

    // Reset typing indicator
    isTyping = false
    clearTimeout(typingTimer)
  }

  // Send typing status
  function sendTypingStatus(isTyping) {
    if (!currentChatId || !socket || socket.readyState !== WebSocket.OPEN) {
      return
    }

    const typingData = {
      action: "typing",
      chat_id: currentChatId,
      is_typing: isTyping,
      is_agent: true,
    }

    socket.send(JSON.stringify(typingData))
  }

  // Handle new message
  function handleNewMessage(data) {
    // If this is for our current chat, append it
    if (data.chat_id === currentChatId) {
      appendMessage(data)
      scrollToBottom()
    } else {
      // Otherwise, show unread indicator
      const $chatItem = $activeChats.find('[data-chat-id="' + data.chat_id + '"]')

      if ($chatItem.length && !$chatItem.find(".chat-item-unread").length) {
        $chatItem.append('<span class="chat-item-unread">1</span>')
      }
    }
  }

  // Append message to chat
  function appendMessage(message) {
    const isAgent = message.is_agent == 1
    const messageClass = isAgent ? "agent-message" : "user-message"
    const sender = isAgent ? message.display_name || "Support Agent" : $chatUserName.text()
    const time = formatTime(message.created_at || new Date())

    const $message = $("<div>")
      .addClass("chat-message " + messageClass)
      .html(
        '<div class="message-header">' +
          '<span class="message-sender">' +
          sender +
          "</span>" +
          '<span class="message-time">' +
          time +
          "</span>" +
          "</div>" +
          '<div class="message-content">' +
          message.message +
          "</div>",
      )

    $chatMessages.append($message)
  }

  // Handle typing indicator
  function handleTypingIndicator(data) {
    if (data.chat_id !== currentChatId) {
      return
    }

    if (data.is_typing && !data.is_agent) {
      $typingIndicator.show()
    } else {
      $typingIndicator.hide()
    }
  }

  // Close current chat
  function closeCurrentChat() {
    if (!currentChatId || !socket || socket.readyState !== WebSocket.OPEN) {
      return
    }

    if (!confirm("Are you sure you want to close this chat?")) {
      return
    }

    const closeData = {
      action: "close",
      chat_id: currentChatId,
    }

    socket.send(JSON.stringify(closeData))
  }

  // Handle chat closed
  function handleChatClosed(chatId) {
    const $chatItem = $activeChats.find('[data-chat-id="' + chatId + '"]')

    if ($chatItem.length) {
      $chatItem.remove()
    }

    if (chatId === currentChatId) {
      currentChatId = null
      $chatContainer.hide()
      $chatPlaceholder.show()
    }

    // If no more chats, show "no chats" message
    if ($activeChats.find("li").length === 0) {
      $activeChats.append('<li class="no-chats">No active chats</li>')
    }
  }

  // Handle user disconnected
  function handleUserDisconnected(chatId) {
    const $chatItem = $activeChats.find('[data-chat-id="' + chatId + '"]')

    if ($chatItem.length) {
      $chatItem.addClass("disconnected")

      if (chatId === currentChatId) {
        appendSystemMessage("User has disconnected")
      }
    }
  }

  // Append system message
  function appendSystemMessage(message) {
    const $message = $("<div>")
      .addClass("chat-message system-message")
      .html('<div class="message-content">' + message + "</div>")

    $chatMessages.append($message)
    scrollToBottom()
  }

  // Scroll chat to bottom
  function scrollToBottom() {
    $chatMessages.scrollTop($chatMessages[0].scrollHeight)
  }

  // Format date
  function formatDate(dateStr) {
    const date = new Date(dateStr)
    return date.toLocaleDateString() + " " + date.toLocaleTimeString()
  }

  // Format time
  function formatTime(dateStr) {
    const date = new Date(dateStr)
    return date.toLocaleTimeString()
  }

  // Make connectToServer available globally
  window.LiveSupportAdmin.connectToServer = connectToServer

  // Initialize on document ready
  $(document).ready(() => {
    console.log("Document ready in LiveSupportAdmin.js")
    init()
  })
})(jQuery)

