<?php
/**
 * Project root configuration
 * This mirrors `php/config.php` but lives at project root so root scripts can include it.
 */

// Email Configuration
define('CONTACT_EMAIL', 'your-email@example.com'); // Replace with your email
define('FROM_EMAIL', 'noreply@yourdomain.com'); // Replace with your domain email
define('SUBJECT_PREFIX', '[Portfolio Contact] ');

// Security Settings
define('MAX_REQUESTS_PER_HOUR', 5); // Maximum contact form submissions per IP per hour
define('ENABLE_RATE_LIMITING', true);
define('ENABLE_HONEYPOT', true); // Enable honeypot anti-spam field

// Validation Rules
define('MIN_NAME_LENGTH', 2);
define('MAX_NAME_LENGTH', 100);
define('MIN_MESSAGE_LENGTH', 10);
define('MAX_MESSAGE_LENGTH', 5000);

// CORS Settings
define('ALLOWED_ORIGINS', [
    'http://localhost:4200',
    'http://127.0.0.1:4200',
    'http://localhost:8000',
    'http://localhost',
    'http://127.0.0.1',
    'https://yourdomain.com' // Add your production domain
]);

// Database Configuration (use my_portfolio as requested)
define('DB_HOST', 'localhost');
define('DB_NAME', 'my_portfolio');
define('DB_USER', 'root');
define('DB_PASS', '');

// File Paths
define('LOG_FILE', __DIR__ . '/logs/contact.log');
define('RATE_LIMIT_FILE', __DIR__ . '/data/rate_limits.json');

// Create necessary directories
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}

return [
    'contact_email' => CONTACT_EMAIL,
    'from_email' => FROM_EMAIL,
    'subject_prefix' => SUBJECT_PREFIX,
    'max_requests_per_hour' => MAX_REQUESTS_PER_HOUR,
    'enable_rate_limiting' => ENABLE_RATE_LIMITING,
    'enable_honeypot' => ENABLE_HONEYPOT,
    'allowed_origins' => ALLOWED_ORIGINS
];
