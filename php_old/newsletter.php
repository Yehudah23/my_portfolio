<?php
/**
 * Newsletter Subscription Handler
 * Handles newsletter signup with validation and email confirmation
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils.php';

handle_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response([
        'success' => false,
        'error' => 'Invalid request method'
    ], 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $email = sanitize_input($input['email'] ?? '');
    
    // Validate email
    if (empty($email)) {
        send_json_response([
            'success' => false,
            'error' => 'Email is required'
        ], 400);
    }
    
    if (!validate_email($email)) {
        send_json_response([
            'success' => false,
            'error' => 'Invalid email address'
        ], 400);
    }
    
    // Check rate limit
    if (!check_rate_limit(get_client_ip(), 3)) {
        send_json_response([
            'success' => false,
            'error' => 'Too many subscription attempts'
        ], 429);
    }
    
    // Save to file (or database)
    $subscribers_file = __DIR__ . '/data/subscribers.json';
    $subscribers = [];
    
    if (file_exists($subscribers_file)) {
        $content = file_get_contents($subscribers_file);
        $subscribers = json_decode($content, true) ?? [];
    }
    
    // Check if already subscribed
    if (in_array($email, array_column($subscribers, 'email'))) {
        send_json_response([
            'success' => false,
            'error' => 'This email is already subscribed'
        ], 400);
    }
    
    // Add subscriber
    $subscribers[] = [
        'email' => $email,
        'subscribed_at' => date('Y-m-d H:i:s'),
        'ip' => get_client_ip()
    ];
    
    file_put_contents($subscribers_file, json_encode($subscribers, JSON_PRETTY_PRINT));
    
    // Send confirmation email
    $subject = 'Newsletter Subscription Confirmation';
    $message = "Thank you for subscribing to my newsletter!\n\n";
    $message .= "You'll receive updates about my latest projects and blog posts.\n\n";
    $message .= "If you didn't subscribe, please ignore this email.\n";
    
    $headers = [];
    $headers[] = "From: " . FROM_EMAIL;
    $headers[] = "Reply-To: " . CONTACT_EMAIL;
    $headers[] = "Content-Type: text/plain; charset=UTF-8";
    
    mail($email, $subject, $message, implode("\r\n", $headers));
    
    log_message("New newsletter subscriber: $email");
    
    send_json_response([
        'success' => true,
        'message' => 'Successfully subscribed! Check your email for confirmation.'
    ]);
    
} catch (Exception $e) {
    log_message("Newsletter subscription error: " . $e->getMessage(), 'ERROR');
    send_json_response([
        'success' => false,
        'error' => 'An error occurred'
    ], 500);
}
