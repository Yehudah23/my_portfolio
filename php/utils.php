<?php
/**
 * Utility functions for the portfolio PHP backend
 * This file contains helper functions used throughout the application
 */

/**
 * Sanitize user input to prevent XSS attacks
 * Removes extra whitespace, slashes, and converts special characters to HTML entities
 * 
 * @param string $data - The input string to sanitize
 * @return string - The sanitized string safe for display
 */
function sanitize_input($data) {
    $data = trim($data);                                    // Remove extra spaces
    $data = stripslashes($data);                            // Remove backslashes
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');  // Convert special chars to HTML entities
    return $data;
}

/**
 * Validate if a string is a valid email address
 * Uses PHP's built-in email validation filter
 * 
 * @param string $email - The email address to validate
 * @return bool - True if valid, false otherwise
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check if a string length is within acceptable range
 * Useful for form validation
 * 
 * @param string $string - The string to check
 * @param int $min - Minimum allowed length
 * @param int $max - Maximum allowed length
 * @return bool - True if length is valid, false otherwise
 */
function validate_length($string, $min, $max) {
    $length = strlen($string);
    return $length >= $min && $length <= $max;
}

/**
 * Get the real IP address of the client
 * Checks various headers to handle proxies and load balancers
 * 
 * @return string - The client's IP address
 */
function get_client_ip() {
    // Check various possible IP headers (for proxy/load balancer support)
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
 * Rate limiting function to prevent spam and abuse
 * Tracks request counts per IP address within a time window
 * 
 * @param string $ip - The IP address to check
 * @param int $max_requests - Maximum number of requests allowed (default: 5)
 * @param int $time_window - Time window in seconds (default: 3600 = 1 hour)
 * @return bool - True if request is allowed, false if rate limit exceeded
 */
function check_rate_limit($ip, $max_requests = 5, $time_window = 3600) {
    // Skip rate limiting if disabled in config
    if (!ENABLE_RATE_LIMITING) {
        return true;
    }
    
    $rate_limit_file = RATE_LIMIT_FILE;
    $data = [];
    
    // Load existing rate limit data
    if (file_exists($rate_limit_file)) {
        $content = file_get_contents($rate_limit_file);
        $data = json_decode($content, true) ?? [];
    }
    
    $current_time = time();
    
    // Clean up old entries that are outside the time window
    foreach ($data as $stored_ip => $timestamps) {
        $data[$stored_ip] = array_filter($timestamps, function($timestamp) use ($current_time, $time_window) {
            return ($current_time - $timestamp) < $time_window;
        });
        // Remove IP if no recent requests
        if (empty($data[$stored_ip])) {
            unset($data[$stored_ip]);
        }
    }
    
    // Initialize array for current IP if not exists
    if (!isset($data[$ip])) {
        $data[$ip] = [];
    }
    
    // Check if rate limit exceeded
    if (count($data[$ip]) >= $max_requests) {
        return false; // Too many requests
    }
    
    // Add current timestamp for this request
    $data[$ip][] = $current_time;
    
    // Save updated rate limit data
    file_put_contents($rate_limit_file, json_encode($data));
    
    return true; // Request allowed
}

/**
 * Log messages to a file for debugging and monitoring
 * Includes timestamp, log level, IP address, and message
 * 
 * @param string $message - The message to log
 * @param string $level - Log level (INFO, WARNING, ERROR)
 */
function log_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $ip = get_client_ip();
    $log_entry = "[$timestamp] [$level] [IP: $ip] $message" . PHP_EOL;
    
    // Write to log file if configured
    if (defined('LOG_FILE')) {
        file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
    }
}

/**
 * Send a JSON response and terminate script execution
 * Sets proper HTTP status code and content type header
 * 
 * @param array $data - Data to send as JSON
 * @param int $status_code - HTTP status code (default: 200)
 */
function send_json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit; // Stop script execution
}

/**
 * Handle Cross-Origin Resource Sharing (CORS) headers
 * Allows frontend applications from different domains to access the API
 * Responds to preflight OPTIONS requests
 */
function handle_cors() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    // Check if origin is in allowed list
    if (in_array($origin, ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        // Allow all origins (less secure, use in development only)
        header('Access-Control-Allow-Origin: *');
    }
    
    // Specify allowed HTTP methods
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    // Specify allowed headers
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    // Cache preflight request for 1 hour
    header('Access-Control-Max-Age: 3600');
    
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204); // No content
        exit;
    }
}

/**
 * Check honeypot field for spam prevention
 * Bots typically fill all form fields, while humans leave honeypot empty
 * 
 * @param string $honeypot_value - Value of the honeypot field
 * @return bool - True if legitimate (field is empty), false if spam detected
 */
function check_honeypot($honeypot_value) {
    // Skip check if honeypot is disabled
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
    // Check if PHPMailer should be used
    if (defined('USE_PHPMAILER') && USE_PHPMAILER) {
        return send_email_phpmailer($name, $email, $subject, $message);
    }
    
    // Fallback to PHP mail() function
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
 * Send email using PHPMailer with SMTP
 */
function send_email_phpmailer($name, $email, $subject, $message) {
    // Load PHPMailer
    require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
    
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress(CONTACT_EMAIL);
        $mail->addReplyTo($email, $name);
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = SUBJECT_PREFIX . $subject;
        
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
        
        $mail->Body = $email_body;
        
        // Send email
        $mail->send();
        log_message("Email sent successfully via PHPMailer to " . CONTACT_EMAIL . " from $email");
        return true;
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        log_message("PHPMailer Error: {$mail->ErrorInfo}", 'ERROR');
        return false;
    }
}

/**
 * Save contact to database (optional)
 */
function save_contact_to_db($name, $email, $subject, $message) {
    try {
        // Use MySQLi connection wrapper
        require_once __DIR__ . '/db_mysqli.php';
        $mysqli = get_mysqli_connection(true);

        $ip = get_client_ip();
        $stmt = $mysqli->prepare("INSERT INTO contacts (name, email, subject, message, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt === false) {
            throw new Exception('Prepare failed: ' . $mysqli->error);
        }
        $stmt->bind_param('sssss', $name, $email, $subject, $message, $ip);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        $stmt->close();
        $mysqli->close();
        log_message("Contact saved to database: $email");
        return true;
    } catch (Exception $e) {
        log_message("Database error: " . $e->getMessage(), 'ERROR');
        return false;
    }
}
