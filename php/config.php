<?php


// Email Configuration
define('CONTACT_EMAIL', 'judahk065@gmail.com'); // Replace with your email
define('FROM_EMAIL', 'noreply@yourdomain.com'); // Replace with your domain email
define('SUBJECT_PREFIX', '[Portfolio Contact] ');

// SMTP Configuration for PHPMailer
define('SMTP_HOST', 'smtp.gmail.com'); // SMTP server (e.g., smtp.gmail.com for Gmail)
define('SMTP_PORT', 587); // SMTP port (587 for TLS, 465 for SSL)
define('SMTP_ENCRYPTION', 'tls'); // Encryption type: 'tls' or 'ssl'
define('SMTP_USERNAME', 'judahk065@gmail.com'); // Your SMTP username (usually your email)
define('SMTP_PASSWORD', 'omkbsbcprxwfdpgv'); // Your SMTP password or app-specific password
define('SMTP_FROM_NAME', 'Portfolio Contact Form'); // Sender name
define('USE_PHPMAILER', true); // Set to true to use PHPMailer, false to use PHP mail()

// Security Settings
define('MAX_REQUESTS_PER_HOUR', 5); // Maximum contact form submissions per IP per hour
define('ENABLE_RATE_LIMITING', true);
define('ENABLE_HONEYPOT', true); // Enable honeypot anti-spam field

// Validation Rules
define('MIN_NAME_LENGTH', 2);
define('MAX_NAME_LENGTH', 100);
define('MIN_MESSAGE_LENGTH', 10);
define('MAX_MESSAGE_LENGTH', 5000);

// CORS Settings - Allow these domains to access the API
define('ALLOWED_ORIGINS', [
    'http://localhost:4200',        // Angular development server
    'http://127.0.0.1:4200',        // Angular dev (IP address)
    'http://localhost:8000',        // Alternative local server
    'http://localhost',             // General localhost
    'http://127.0.0.1',            // General localhost (IP)
    'https://yourdomain.com'       // Production domain (update this!)
]);

// Database Configuration (optional - for storing contact submissions)
define('DB_HOST', 'localhost');
define('DB_NAME', 'my_portfolio');
define('DB_USER', 'root');
define('DB_PASS', '');

// File Paths - Store data in root directories for easier access
define('LOG_FILE', dirname(__DIR__) . '/logs/contact.log');
define('RATE_LIMIT_FILE', dirname(__DIR__) . '/data/rate_limits.json');

// Create necessary directories if they don't exist
$root_dir = dirname(__DIR__);
if (!file_exists($root_dir . '/logs')) {
    mkdir($root_dir . '/logs', 0755, true);
}
if (!file_exists($root_dir . '/data')) {
    mkdir($root_dir . '/data', 0755, true);
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
