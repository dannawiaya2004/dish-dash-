<?php
require 'db.php';

session_start();

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set the response content type to JSON
    header('Content-Type: application/json');

    $API_KEY = getenv('OPENROUTER_API_KEY') ?: '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';

    // Read the raw POST data from the request and decode the JSON into an associative array
    $data = json_decode(file_get_contents('php://input'), true);

    // Get the prompt from the decoded data and trim whitespace
    $prompt = trim($data['prompt'] ?? '');

    // If the prompt is empty, return an error as JSON
    if (empty($prompt)) {
        echo json_encode(['error' => 'No input received']);
        exit;
    }

    // Build the request payload for OpenRouter API
    $postData = [
        'model' => 'gpt-4o-mini', // Specify the model to use
        'messages' => [
            // System message sets the assistant’s behavior
            ['role' => 'system', 'content' => "You are a helpful AI chef assistant for Dish Dash. Provide detailed cooking advice, recipe suggestions, and meal planning help. Be friendly and professional."],
            // User message contains the prompt sent by the user
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.7, // Controls randomness of output (0 = deterministic, 1 = creative)
        'max_tokens' => 500, // Limits the length of the AI's reply
    ];

    // Initialize cURL session
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://openrouter.ai/api/v1/chat/completions', // API endpoint URL
        CURLOPT_RETURNTRANSFER => true, // Return the response as a string
        CURLOPT_POST => true, // This is a POST request
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json', // Sending JSON format
            'Authorization: Bearer ' . $API_KEY, // API key authorization
        ],
        CURLOPT_POSTFIELDS => json_encode($postData), // JSON-encoded request data
        CURLOPT_TIMEOUT => 30, // Max wait time for the request
    ]);

    // Execute the cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP response status code
    $error = curl_error($ch); // Get cURL error if any
    curl_close($ch); // Close the cURL session

    // If a network error occurred, return it as JSON
    if ($error) {
        echo json_encode(['error' => 'Network error: ' . $error]);
        exit;
    }

    // If HTTP response code is not 200, handle it as an API error
    if ($httpCode !== 200) {
        $errMsg = 'API request failed';
        if ($response) {
            $json = json_decode($response, true); // Try decoding error message from response
            if (isset($json['error']['message'])) {
                $errMsg = $json['error']['message'];
            }
        }
        echo json_encode(['error' => $errMsg . " (HTTP $httpCode)"]);
        exit;
    }

    // Decode the successful API response
    $resData = json_decode($response, true);

    // If JSON decoding fails, return an error
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['error' => 'Invalid JSON response from API']);
        exit;
    }

    // If the expected message content is missing, return an error
    if (!isset($resData['choices'][0]['message']['content'])) {
        echo json_encode(['error' => 'Unexpected API response structure']);
        exit;
    }

    // Send the AI-generated reply back to the frontend as JSON
    echo json_encode(['reply' => $resData['choices'][0]['message']['content']]);
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Dish Dash AI Chef Assistant</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root {
    --primary: #f77f00;
    --primary-light: rgba(247, 127, 0, 0.1);
    --secondary: #2a9d8f;
    --error: #e63946;
    --bg: #fff8f0;
    --card: #ffffff;
    --text: #333333;
    --sidebar-width: 280px;
    --sidebar-collapsed-width: 80px;
  }
  
  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: var(--bg);
    color: var(--text);
    margin: 0;
    padding: 0;
    line-height: 1.6;
    display: flex;
    min-height: 100vh;
    transition: all 0.3s ease;
  }
  
  /* Sidebar Styles */
  .sidebar {
    width: var(--sidebar-width);
    background: white;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    transition: all 0.3s ease;
    z-index: 1000;
  }
  
  .sidebar-collapsed .sidebar {
    width: var(--sidebar-collapsed-width);
  }
  
  .sidebar-header {
    padding: 1.5rem 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
  }
  
  .sidebar-brand {
    display: flex;
    align-items: center;
    text-decoration: none;
    color: var(--text);
    font-weight: 700;
    font-size: 1.25rem;
    white-space: nowrap;
  }
  
  .sidebar-brand img {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    margin-right: 10px;
    transition: all 0.3s ease;
  }
  
  .sidebar-collapsed .sidebar-brand span {
    display: none;
  }
  
  .toggle-btn {
    background: none;
    border: none;
    color: #666;
    font-size: 1.25rem;
    cursor: pointer;
    transition: all 0.3s ease;
  }
  
  .sidebar-collapsed .toggle-btn {
    transform: rotate(180deg);
    margin: 0 auto;
  }
  
  .sidebar-menu {
    padding: 1rem 0;
  }
  
  .nav-item {
    position: relative;
    margin: 0.25rem 0.5rem;
  }
  
  .nav-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: #555;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
    white-space: nowrap;
  }
  
  .nav-link:hover {
    background: var(--primary-light);
    color: var(--primary);
  }
  
  .nav-link.active {
    background: var(--primary-light);
    color: var(--primary);
    font-weight: 600;
  }
  
  .nav-link i {
    font-size: 1.1rem;
    margin-right: 12px;
    width: 24px;
    text-align: center;
  }
  
  .sidebar-collapsed .nav-link span {
    display: none;
  }
  
  .sidebar-collapsed .nav-link {
    justify-content: center;
    padding: 0.75rem 0;
  }
  
  .sidebar-collapsed .nav-link i {
    margin-right: 0;
  }
  
  .sidebar-footer {
    padding: 1rem;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
    position: absolute;
    bottom: 0;
    width: 100%;
  }
  
  .user-profile {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
  }
  
  .user-profile:hover {
    background: rgba(0, 0, 0, 0.05);
  }
  
  .user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #eee;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
    overflow: hidden;
  }
  
  .user-info {
    flex: 1;
  }
  
  .user-name {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 0.1rem;
  }
  
  .user-role {
    font-size: 0.75rem;
    color: #777;
  }
  
  .sidebar-collapsed .user-info,
  .sidebar-collapsed .user-role {
    display: none;
  }
  
  /* Main Content Styles */
  .main-content {
    flex: 1;
    margin-left: var(--sidebar-width);
    transition: all 0.3s ease;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }
  
  .sidebar-collapsed .main-content {
    margin-left: var(--sidebar-collapsed-width);
  }
  
  .chat-container {
    flex: 1;
    max-width: 800px;
    margin: 2rem auto;
    background: var(--card);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: calc(100vh - 4rem);
    width: 90%;
  }
  
  .chat-header {
    background: var(--primary);
    color: white;
    padding: 15px 20px;
    font-size: 1.2rem;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  
  #chat-box {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 15px;
  }
  
  .message {
    max-width: 80%;
    padding: 12px 16px;
    border-radius: 18px;
    line-height: 1.5;
    position: relative;
    animation: fadeIn 0.3s ease;
  }
  
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }
  
  .user-message {
    align-self: flex-end;
    background: var(--primary);
    color: white;
    border-bottom-right-radius: 4px;
  }
  
  .bot-message {
    align-self: flex-start;
    background: #f0f0f0;
    color: var(--text);
    border-bottom-left-radius: 4px;
  }
  
  .error-message {
    align-self: center;
    background: var(--error);
    color: white;
    text-align: center;
    max-width: 90%;
  }
  
  .chat-form {
    display: flex;
    padding: 15px;
    border-top: 1px solid #eee;
    background: white;
  }
  
  #user-input {
    flex: 1;
    padding: 12px 15px;
    border: 2px solid #ddd;
    border-radius: 25px;
    font-size: 1rem;
    outline: none;
    transition: border 0.3s;
  }
  
  #user-input:focus {
    border-color: var(--primary);
  }
  
  #send-button {
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    margin-left: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.3s;
  }
  
  #send-button:hover {
    background: #e67300;
  }
  
  #send-button:disabled {
    background: #ccc;
    cursor: not-allowed;
  }
  
  .typing-indicator {
    align-self: flex-start;
    color: #666;
    font-style: italic;
    padding: 10px 20px;
    display: none;
  }
  
  @media (max-width: 992px) {
    .sidebar {
      transform: translateX(-100%);
    }
    
    .sidebar-collapsed .sidebar {
      transform: translateX(0);
      width: var(--sidebar-collapsed-width);
    }
    
    .main-content {
      margin-left: 0;
    }
    
    .sidebar-show .sidebar {
      transform: translateX(0);
      width: var(--sidebar-width);
    }
    
    .sidebar-show .main-content {
      margin-left: 0;
    }
    
    .mobile-menu-btn {
      display: block;
      position: fixed;
      top: 1rem;
      left: 1rem;
      z-index: 999;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }
  }
  
  @media (max-width: 600px) {
    .chat-container {
      height: calc(100vh - 2rem);
      margin: 1rem auto;
      width: 95%;
    }
    
    .message {
      max-width: 90%;
    }
  }
</style>
</head>
<body>
<!-- Sidebar Navigation -->
<div class="sidebar">
  <div class="sidebar-header">
    <a href="main.php" class="sidebar-brand">
      <img src="Recipe Book Logo.jpeg" alt="DishDash Logo">
      <span>DishDash</span>
    </a>
    <button class="toggle-btn" id="toggle-sidebar">
      <i class="fas fa-chevron-left"></i>
    </button>
  </div>
  
  <div class="sidebar-menu">
    <div class="nav-item">
      <a href="main.php" class="nav-link">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
    </div>
    <div class="nav-item">
      <a href="allRecipes.php" class="nav-link">
        <i class="fas fa-book"></i>
        <span>Recipes</span>
      </a>
    </div>
    <div class="nav-item">
      <a href="fridge.php" class="nav-link ">
        <i class="fas fa-ice-cream"></i>
        <span>My Fridge</span>
      </a>
    </div>
    <div class="nav-item">
      <a href="weekly_plan.php" class="nav-link">
        <i class="fas fa-calendar-alt"></i>
        <span>Meal Planner</span>
      </a>
    </div>
    <div class="nav-item">
    <a href="chatbot.php" class="nav-link active">
      <i class="fas fa-robot"></i>
      <span>Chef Assistant</span>
    </a>
  </div>
    <div class="nav-item">
      <a href="favorites.php" class="nav-link">
        <i class="fas fa-heart"></i>
        <span>Favorites</span>
      </a>
    </div>
  </div>

</div>

<!-- Mobile Menu Button (hidden on desktop) -->
<button class="mobile-menu-btn" id="mobile-menu-btn" style="display: none;">
  <i class="fas fa-bars"></i>
</button>

<!-- Main Content -->
<div class="main-content" id="main-content">
  <div class="chat-container">
    <div class="chat-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <path d="M16 10a4 4 0 0 1-8 0"></path>
      </svg>
      Dish Dash AI Chef Assistant
    </div>
    <div id="chat-box"></div>
    <div class="typing-indicator" id="typing-indicator">Chef is thinking...</div>
    <form class="chat-form" id="chat-form">
      <input type="text" id="user-input" placeholder="Ask about recipes, ingredients, or cooking techniques..." autocomplete="off" required />
      <button type="submit" id="send-button" aria-label="Send message">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="22" y1="2" x2="11" y2="13"></line>
          <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
        </svg>
      </button>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // DOM Elements
  const chatForm = document.getElementById('chat-form');
  const userInput = document.getElementById('user-input');
  const chatBox = document.getElementById('chat-box');
  const typingIndicator = document.getElementById('typing-indicator');
  const sendButton = document.getElementById('send-button');
  const toggleSidebarBtn = document.getElementById('toggle-sidebar');
  const mobileMenuBtn = document.getElementById('mobile-menu-btn');
  const mainContent = document.getElementById('main-content');
  const sidebar = document.querySelector('.sidebar');
  
  // Toggle sidebar collapse/expand
  function toggleSidebar() {
    document.body.classList.toggle('sidebar-collapsed');
    localStorage.setItem('sidebarCollapsed', document.body.classList.contains('sidebar-collapsed'));
  }
  
  // Toggle mobile sidebar
  function toggleMobileSidebar() {
    document.body.classList.toggle('sidebar-show');
  }
  
  // Initialize sidebar state
  function initSidebar() {
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
      document.body.classList.add('sidebar-collapsed');
    }
    
    // Show/hide mobile menu button based on screen size
    function checkScreenSize() {
      if (window.innerWidth <= 992) {
        mobileMenuBtn.style.display = 'block';
        document.body.classList.remove('sidebar-collapsed');
      } else {
        mobileMenuBtn.style.display = 'none';
        document.body.classList.remove('sidebar-show');
      }
    }
    
    window.addEventListener('resize', checkScreenSize);
    checkScreenSize();
  }
  
  // Chat functionality
  function initChat() {
    window.addEventListener('DOMContentLoaded', () => {
      addMessage('bot', "Hello! I'm your AI Chef Assistant. How can I help you with cooking today? You can ask me about recipes, ingredient substitutions, meal planning, or cooking techniques.");
    });
    
    chatForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const message = userInput.value.trim();
      if (!message) return;
      
      addMessage('user', message);
      userInput.value = '';
      sendButton.disabled = true;
      
      typingIndicator.style.display = 'block';
      chatBox.scrollTop = chatBox.scrollHeight;
      
      try {
        const response = await fetch('', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ prompt: message }),
        });
        
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        
        const data = await response.json();
        
        if (data.error) {
          addMessage('error', data.error);
        } else {
          addMessage('bot', data.reply);
        }
      } catch (error) {
        addMessage('error', "Sorry, I'm having trouble connecting to the chef knowledge. Please try again later.");
        console.error(error);
      } finally {
        typingIndicator.style.display = 'none';
        sendButton.disabled = false;
        userInput.focus();
      }
    });
  }
  
  // Add message to chat
  function addMessage(type, text) {
    const messageElement = document.createElement('div');
    
    if (type === 'user') {
      messageElement.className = 'message user-message';
      messageElement.innerHTML = `<div>${text}</div><div class="message-time">${getCurrentTime()}</div>`;
    } else if (type === 'bot') {
      messageElement.className = 'message bot-message';
      messageElement.innerHTML = `<div>${text.replace(/\n/g, '<br>')}</div><div class="message-time">${getCurrentTime()}</div>`;
    } else if (type === 'error') {
      messageElement.className = 'message error-message';
      messageElement.textContent = text;
    }
    
    chatBox.appendChild(messageElement);
    chatBox.scrollTop = chatBox.scrollHeight;
  }
  
  // Get current time in HH:MM format
  function getCurrentTime() {
    const now = new Date();
    return now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
  }
  
  // Event listeners
  toggleSidebarBtn.addEventListener('click', toggleSidebar);
  mobileMenuBtn.addEventListener('click', toggleMobileSidebar);
  
  // Initialize
  initSidebar();
  initChat();
</script>
</body>
</html>