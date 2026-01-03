<?php
/**
 * Newsletter Subscription Handler
 * Processes newsletter signups with:
 * - Email validation
 * - Duplicate subscription checking
 * - Rate limiting to prevent abuse
 * - Confirmation email sending
 * - Subscriber data storage in JSON file
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

// Enable CORS for frontend API access
handle_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response([
        'success' => false,
        'error' => 'Invalid request method'
    ], 405);
}

try {
    // Get JSON input from request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Extract and sanitize email address
    $email = sanitize_input($input['email'] ?? '');
    
    // Validate: Check if email is provided
    if (empty($email)) {
        send_json_response([
            'success' => false,
            'error' => 'Email is required'
        ], 400);
    }
    
    // Validate: Check if email format is correct
    if (!validate_email($email)) {
        send_json_response([
            'success' => false,
            'error' => 'Invalid email address'
        ], 400);
    }
    
    // Rate limiting: Allow max 3 subscription attempts per hour per IP
    if (!check_rate_limit(get_client_ip(), 3)) {
        send_json_response([
            'success' => false,
            'error' => 'Too many subscription attempts'
        ], 429);
    }
    
    // Load existing subscribers from file
    $subscribers_file = dirname(__DIR__) . '/data/subscribers.json';
    $subscribers = [];
    
    if (file_exists($subscribers_file)) {
        $content = file_get_contents($subscribers_file);
        $subscribers = json_decode($content, true) ?? [];
    }
    
    // Check if email is already subscribed
    if (in_array($email, array_column($subscribers, 'email'))) {
        send_json_response([
            'success' => false,
            'error' => 'This email is already subscribed'
        ], 400);
    }
    
    // Add new subscriber with timestamp and IP
    $subscribers[] = [
        'email' => $email,
        'subscribed_at' => date('Y-m-d H:i:s'),
        'ip' => get_client_ip()
    ];
    
    // Save updated subscribers list to file
    file_put_contents($subscribers_file, json_encode($subscribers, JSON_PRETTY_PRINT));
    
    // Send confirmation email to subscriber
    $subject = 'Newsletter Subscription Confirmation';
    $message = "Thank you for subscribing to my newsletter!\n\n";
    $message .= "You'll receive updates about my latest projects and blog posts.\n\n";
    $message .= "If you didn't subscribe, please ignore this email.\n";
    
    // Set email headers
    $headers = [];
    $headers[] = "From: " . FROM_EMAIL;
    $headers[] = "Reply-To: " . CONTACT_EMAIL;
    $headers[] = "Content-Type: text/plain; charset=UTF-8";
    
    // Send confirmation email using PHP mail()
    mail($email, $subject, $message, implode("\r\n", $headers));
    
    // Log successful subscription
    log_message("New newsletter subscriber: $email");
    
    // Send success response to frontend
    send_json_response([
        'success' => true,
        'message' => 'Successfully subscribed! Check your email for confirmation.'
    ]);
    
} catch (Exception $e) {
    // Log any errors that occur
    log_message("Newsletter subscription error: " . $e->getMessage(), 'ERROR');
    send_json_response([
        'success' => false,
        'error' => 'An error occurred'
    ], 500);
}
