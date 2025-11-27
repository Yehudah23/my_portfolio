# PHP Backend for Portfolio

This folder contains the PHP backend for the portfolio project.

## Files Overview

### Core Files
- **`config.php`** - Configuration settings (email, database, security)
- **`utils.php`** - Utility functions (validation, sanitization, logging)
- **`contact.php`** - Contact form handler with email sending
- **`api.php`** - API endpoints for dynamic content (projects, skills, testimonials)
- **`setup-db.php`** - Database setup script (optional)

## Setup Instructions

### 1. Configure Settings

Edit `config.php` and update the following:

```php
// Your email address to receive contact form submissions
define('CONTACT_EMAIL', 'your-email@example.com');

// Email that will appear as sender
define('FROM_EMAIL', 'noreply@yourdomain.com');

// Add your production domain
define('ALLOWED_ORIGINS', [
    'http://localhost:4200',
    'https://yourdomain.com'
]);
```

### 2. Start PHP Server

From the project root, run:

```bash
npm run php
```

Or manually:

```bash
php -S localhost:8000 -t php
```

### 3. Test Contact Form

The contact form will POST to: `http://localhost:8000/contact.php`

Make sure your Angular service is configured to use this endpoint.

### 4. Setup Database (Optional)

If you want to store contact submissions in a database:

1. Update database credentials in `config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'portfolio');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

2. Run the setup script:
   ```bash
   curl http://localhost:8000/setup-db.php
   ```

3. Uncomment this line in `contact.php`:
   ```php
   save_contact_to_db($name, $email, $subject, $message);
   ```

## API Endpoints

### Contact Form
- **URL**: `POST /contact.php`
- **Body**:
  ```json
  {
    "name": "John Doe",
    "email": "john@example.com",
    "subject": "Hello",
    "message": "Your message here"
  }
  ```
- **Response**:
  ```json
  {
    "success": true,
    "message": "Your message has been sent successfully!"
  }
  ```

### Get Projects
- **URL**: `GET /api.php?resource=projects`
- **Response**:
  ```json
  {
    "success": true,
    "data": [...]
  }
  ```

### Get Skills
- **URL**: `GET /api.php?resource=skills`

### Get Testimonials
- **URL**: `GET /api.php?resource=testimonials`

## Features

### Security
- ✅ CORS headers configured
- ✅ Rate limiting (5 requests per hour per IP)
- ✅ Honeypot anti-spam field
- ✅ Input sanitization and validation
- ✅ XSS protection
- ✅ SQL injection protection (prepared statements)

### Validation
- Name: 2-100 characters
- Email: Valid email format
- Message: 10-5000 characters
- All fields are required

### Logging
- All submissions are logged to `logs/contact.log`
- Includes timestamp, IP address, and status
- Error tracking for debugging

### Rate Limiting
- Maximum 5 submissions per IP per hour
- Rate limit data stored in `data/rate_limits.json`
- Automatic cleanup of old entries

## Email Configuration

### Using PHP mail() function
The default setup uses PHP's built-in `mail()` function. This requires your server to have sendmail or a similar MTA configured.

### Using SMTP (Recommended for Production)
For better deliverability, consider using PHPMailer or SwiftMailer with SMTP:

```bash
composer require phpmailer/phpmailer
```

Then update `utils.php` to use PHPMailer instead of `mail()`.

## Folder Structure

```
php/
├── config.php          # Configuration
├── utils.php           # Utility functions
├── contact.php         # Contact form handler
├── api.php             # API endpoints
├── setup-db.php        # Database setup
├── logs/               # Log files
│   └── contact.log
└── data/               # Rate limit data
    └── rate_limits.json
```

## Testing

### Test Contact Form
```bash
curl -X POST http://localhost:8000/contact.php \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "subject": "Test Subject",
    "message": "This is a test message from the API"
  }'
```

### Test API Endpoints
```bash
# Get projects
curl http://localhost:8000/api.php?resource=projects

# Get skills
curl http://localhost:8000/api.php?resource=skills

# Get testimonials
curl http://localhost:8000/api.php?resource=testimonials
```

## Production Deployment

1. Upload PHP files to your server
2. Update `config.php` with production settings
3. Ensure proper file permissions:
   ```bash
   chmod 755 php/
   chmod 644 php/*.php
   chmod 755 php/logs php/data
   chmod 666 php/logs/contact.log
   ```
4. Configure your web server (Apache/Nginx) to serve PHP files
5. Enable HTTPS for secure communication
6. Update CORS allowed origins

## Troubleshooting

### Emails not sending
- Check PHP `mail()` configuration
- Verify sendmail is installed: `which sendmail`
- Check email logs: `tail -f php/logs/contact.log`
- Consider using SMTP instead

### Rate limiting not working
- Ensure `php/data/` directory is writable
- Check file permissions: `ls -la php/data/`

### Database connection issues
- Verify MySQL credentials in `config.php`
- Ensure MySQL service is running
- Check if database exists: `mysql -u root -p`

## Angular Integration

Update your Angular contact service to use the PHP endpoint:

```typescript
// In src/app/services/api.service.ts
private apiUrl = 'http://localhost:8000/contact.php';

submitContact(data: any) {
  return this.http.post(this.apiUrl, data);
}
```

Don't forget to update the URL for production!
