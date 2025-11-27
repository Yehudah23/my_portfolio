<?php
/**
 * Contact Form Handler
 * Handles contact form submissions with validation, rate limiting, and email sending
 */

// Load configuration and utilities
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils.php';

// Handle CORS
handle_cors();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response([
        'success' => false,
        'error' => 'Invalid request method. Only POST is allowed.'
    ], 405);
}

try {
    // Get and decode JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        send_json_response([
            'success' => false,
            'error' => 'Invalid JSON data'
        ], 400);
    }
    
    // Extract and sanitize input data
    $name = sanitize_input($input['name'] ?? '');
    $email = sanitize_input($input['email'] ?? '');
    $subject = sanitize_input($input['subject'] ?? 'Contact Form Submission');
    $message = sanitize_input($input['message'] ?? '');
    $honeypot = $input['website'] ?? ''; // Honeypot field
    
    // Validation errors array
    $errors = [];
    
    // Check honeypot
    if (!check_honeypot($honeypot)) {
        log_message("Honeypot triggered from IP: " . get_client_ip(), 'WARNING');
        send_json_response([
            'success' => false,
            'error' => 'Spam detected'
        ], 400);
    }
    
    // Validate name
    if (empty($name)) {
        $errors[] = 'Name is required';
    } elseif (!validate_length($name, MIN_NAME_LENGTH, MAX_NAME_LENGTH)) {
        $errors[] = 'Name must be between ' . MIN_NAME_LENGTH . ' and ' . MAX_NAME_LENGTH . ' characters';
    }
    
    // Validate email
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!validate_email($email)) {
        $errors[] = 'Invalid email address';
    }
    
    // Validate subject
    if (empty($subject)) {
        $errors[] = 'Subject is required';
    }
    
    // Validate message
    if (empty($message)) {
        $errors[] = 'Message is required';
    } elseif (!validate_length($message, MIN_MESSAGE_LENGTH, MAX_MESSAGE_LENGTH)) {
        $errors[] = 'Message must be between ' . MIN_MESSAGE_LENGTH . ' and ' . MAX_MESSAGE_LENGTH . ' characters';
    }
    
    // Return validation errors if any
    if (!empty($errors)) {
        send_json_response([
            'success' => false,
            'errors' => $errors
        ], 400);
    }
    
    // Check rate limit
    $client_ip = get_client_ip();
    if (!check_rate_limit($client_ip, MAX_REQUESTS_PER_HOUR)) {
        log_message("Rate limit exceeded for IP: $client_ip", 'WARNING');
        send_json_response([
            'success' => false,
            'error' => 'Too many requests. Please try again later.'
        ], 429);
    }
    
    // Send email
    $email_sent = send_contact_email($name, $email, $subject, $message);
    
    // Save to database (if configured)
    $db_saved = save_contact_to_db($name, $email, $subject, $message);
    if (!$db_saved) {
        log_message("Warning: failed to save contact to DB for $email", 'WARNING');
    }
    
    if ($email_sent) {
        log_message("Contact form submitted successfully by $email");
        send_json_response([
            'success' => true,
            'message' => 'Your message has been sent successfully! I will get back to you soon.'
        ], 200);
    } else {
        log_message("Failed to send email for submission by $email", 'ERROR');
        send_json_response([
            'success' => false,
            'error' => 'Failed to send email. Please try again later or contact me directly.'
        ], 500);
    }
    
} catch (Exception $e) {
    log_message("Exception in contact form: " . $e->getMessage(), 'ERROR');
    send_json_response([
        'success' => false,
        'error' => 'An unexpected error occurred. Please try again later.'
    ], 500);
}
 