<?php
/**
 * Utility functions for the portfolio PHP backend
 */

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email address
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate string length
 */
function validate_length($string, $min, $max) {
    $length = strlen($string);
    return $length >= $min && $length <= $max;
}

/**
 * Get client IP address
 */
function get_client_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Check rate limit
 */
function check_rate_limit($ip, $max_requests = 5, $time_window = 3600) {
    if (!ENABLE_RATE_LIMITING) {
        return true;
    }
    
    $rate_limit_file = RATE_LIMIT_FILE;
    $data = [];
    
    if (file_exists($rate_limit_file)) {
        $content = file_get_contents($rate_limit_file);
        $data = json_decode($content, true) ?? [];
    }
    
    $current_time = time();
    
    // Clean old entries
    foreach ($data as $stored_ip => $timestamps) {
        $data[$stored_ip] = array_filter($timestamps, function($timestamp) use ($current_time, $time_window) {
            return ($current_time - $timestamp) < $time_window;
        });
        if (empty($data[$stored_ip])) {
            unset($data[$stored_ip]);
        }
    }
    
    // Check current IP
    if (!isset($data[$ip])) {
        $data[$ip] = [];
    }
    
    if (count($data[$ip]) >= $max_requests) {
        return false;
    }
    
    // Add current timestamp
    $data[$ip][] = $current_time;
    
    // Save updated data
    file_put_contents($rate_limit_file, json_encode($data));
    
    return true;
}

/**
 * Log message
 */
function log_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $ip = get_client_ip();
    $log_entry = "[$timestamp] [$level] [IP: $ip] $message" . PHP_EOL;
    
    if (defined('LOG_FILE')) {
        file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
    }
}

/**
 * Send JSON response
 */
function send_json_response($data, $status_code = 200) {
    http_response_code($status_code);
    // Ensure CORS credentials header is present for allowed origins
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (defined('ALLOWED_ORIGINS') && is_array(ALLOWED_ORIGINS) && in_array($origin, ALLOWED_ORIGINS)) {
        header('Access-Control-Allow-Credentials: true');
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Handle CORS
 */
function handle_cors() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // If origin is explicitly allowed, echo it and allow credentials.
    if (defined('ALLOWED_ORIGINS') && is_array(ALLOWED_ORIGINS) && in_array($origin, ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
    } else {
        // Fallback to permissive wildcard for non-browser clients. Do NOT allow credentials with wildcard.
        header('Access-Control-Allow-Origin: *');
    }

    // Allow common methods and headers used by browser frontends
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token');
    header('Access-Control-Max-Age: 3600');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        // Short-circuit preflight
        http_response_code(204);
        exit;
    }
}

/**
 * Validate honeypot (anti-spam)
 */
function check_honeypot($honeypot_value) {
    if (!ENABLE_HONEYPOT) {
        return true;
    }
    // Honeypot field should be empty (bots typically fill all fields)
    return empty($honeypot_value);
}

/**
 * Send email with proper formatting
 */
function send_contact_email($name, $email, $subject, $message) {
    $to = CONTACT_EMAIL;
    $email_subject = SUBJECT_PREFIX . $subject;
    
    // Create email body
    $email_body = "You have received a new message from your portfolio contact form.\n\n";
    $email_body .= "Here are the details:\n\n";
    $email_body .= "Name: $name\n";
    $email_body .= "Email: $email\n";
    $email_body .= "Subject: $subject\n\n";
    $email_body .= "Message:\n$message\n\n";
    $email_body .= "---\n";
    $email_body .= "Sent from: " . $_SERVER['HTTP_HOST'] . "\n";
    $email_body .= "IP Address: " . get_client_ip() . "\n";
    $email_body .= "Time: " . date('Y-m-d H:i:s') . "\n";
    
    // Email headers
    $headers = [];
    $headers[] = "From: " . FROM_EMAIL;
    $headers[] = "Reply-To: $email";
    $headers[] = "X-Mailer: PHP/" . phpversion();
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";
    
    // Send email
    $result = mail($to, $email_subject, $email_body, implode("\r\n", $headers));
    
    if ($result) {
        log_message("Email sent successfully to $to from $email");
    } else {
        log_message("Failed to send email to $to from $email", 'ERROR');
    }
    
    return $result;
}

/**
 * Save contact to database (optional)
 */
function save_contact_to_db($name, $email, $subject, $message) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $pdo->prepare("
            INSERT INTO contacts (name, email, subject, message, ip_address, created_at) 
            VALUES (:name, :email, :subject, :message, :ip, NOW())
        ");
        
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':subject' => $subject,
            ':message' => $message,
            ':ip' => get_client_ip()
        ]);
        
        log_message("Contact saved to database: $email");
        return true;
    } catch (PDOException $e) {
        log_message("Database error: " . $e->getMessage(), 'ERROR');
        return false;
    }
}
