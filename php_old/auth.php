<?php
/**
 * Authentication Handler
 * Handles admin login and session management
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin-config.php';
require_once __DIR__ . '/../utils.php';

// Start session
session_name(SESSION_NAME);
session_start();

handle_cors();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handle_login();
        break;
    case 'logout':
        handle_logout();
        break;
    case 'check':
        check_auth();
        break;
    default:
        send_json_response(['error' => 'Invalid action'], 400);
}

/**
 * Handle login
 */
function handle_login() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_response(['error' => 'Invalid request method'], 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        send_json_response([
            'success' => false,
            'error' => 'Username and password are required'
        ], 400);
    }
    
    // Verify credentials
    if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        
        log_message("Admin login successful: $username");
        
        send_json_response([
            'success' => true,
            'message' => 'Login successful',
            'username' => $username
        ]);
    } else {
        log_message("Failed login attempt for username: $username", 'WARNING');
        
        send_json_response([
            'success' => false,
            'error' => 'Invalid username or password'
        ], 401);
    }
}

/**
 * Handle logout
 */
function handle_logout() {
    session_destroy();
    log_message("Admin logged out");
    
    send_json_response([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}

/**
 * Check authentication status
 */
function check_auth() {
    if (is_authenticated()) {
        send_json_response([
            'authenticated' => true,
            'username' => $_SESSION['admin_username'] ?? ''
        ]);
    } else {
        send_json_response([
            'authenticated' => false
        ], 401);
    }
}

/**
 * Check if user is authenticated
 */
function is_authenticated() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['login_time'])) {
        $elapsed = time() - $_SESSION['login_time'];
        if ($elapsed > SESSION_TIMEOUT) {
            session_destroy();
            return false;
        }
        // Refresh session time
        $_SESSION['login_time'] = time();
    }
    
    return true;
}

/**
 * Require authentication (use in other scripts)
 */
function require_auth() {
    session_name(SESSION_NAME);
    session_start();
    
    if (!is_authenticated()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized. Please login.']);
        exit;
    }
}
