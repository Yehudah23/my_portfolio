# Contact Form Setup with PHPMailer

## Overview
Your portfolio contact form is now configured to use PHPMailer with Gmail SMTP for reliable email delivery.

## Current Configuration

**Recipient Email:** judahk065@gmail.com
- All contact form submissions will be sent to this email
- SMTP authentication uses this email as the sender

## Setup Instructions

### 1. Enable Gmail App Password

Since you're using Gmail, you need to create an App Password:

1. Go to your Google Account: https://myaccount.google.com/
2. Navigate to **Security**
3. Enable **2-Step Verification** (if not already enabled)
4. Go to **App Passwords**: https://myaccount.google.com/apppasswords
5. Generate a new app password for "Mail"
6. Copy the generated 16-character password

### 2. Update Configuration

Edit `/opt/lampp/htdocs/myportfolio/config.php`:

```php
define('SMTP_PASSWORD', 'your-16-char-app-password-here');
```

Replace `'your-app-password'` with the app password you generated.

### 3. Test the Contact Form

1. Start your Angular development server
2. Navigate to the contact section
3. Fill out and submit the form
4. Check judahk065@gmail.com for the message

## Files Modified

- ✅ `config.php` - Added SMTP configuration
- ✅ `utils.php` - Added PHPMailer email function
- ✅ `contact.php` - Contact form handler (already working)
- ✅ `vendor/phpmailer/` - PHPMailer library installed

## Alternative SMTP Providers

If you want to use a different email provider, update these settings in `config.php`:

### Gmail (Current)
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
```

### Outlook/Hotmail
```php
define('SMTP_HOST', 'smtp-mail.outlook.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
```

### Yahoo Mail
```php
define('SMTP_HOST', 'smtp.mail.yahoo.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
```

## Troubleshooting

### Email Not Sending?
1. Check `logs/contact.log` for error messages
2. Verify your app password is correct
3. Ensure 2-Step Verification is enabled on your Google account
4. Check that port 587 is not blocked by your firewall

### Testing SMTP Connection
Create a test file to verify SMTP:

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

$result = send_email_phpmailer(
    'Test User',
    'test@example.com',
    'Test Subject',
    'This is a test message'
);

echo $result ? 'Email sent successfully!' : 'Failed to send email';
?>
```

## Security Notes

- Never commit your SMTP password to version control
- Use environment variables for production
- Keep your app password secure
- Regularly rotate your app passwords

## API Endpoint

Your Angular app should POST to:
- **URL:** `http://localhost/myportfolio/contact.php`
- **Method:** POST
- **Content-Type:** application/json
- **Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "subject": "Hello",
  "message": "Your message here"
}
```

## Response Format

**Success:**
```json
{
  "success": true,
  "message": "Your message has been sent successfully! I will get back to you soon."
}
```

**Error:**
```json
{
  "success": false,
  "error": "Error message here"
}
```
