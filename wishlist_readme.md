# Family Wishlist Application

A modern, secure family wishlist application built with PHP and MySQL, featuring unlimited categories, email notifications, and a beautiful Bootstrap 5 interface.

## Features

- **User Management**: Secure login system for family members (Carole and Ted)
- **Unlimited Wishlists**: Create as many wishlists as needed
- **Unlimited Categories**: Organize items with custom color-coded categories
- **Item Management**: Add items with:
  - Title and description
  - Product URLs
  - Priority levels (High, Medium, Low)
  - Optional pricing (with privacy control)
  - Category assignment
- **Sharing System**: Share wishlists with family members
- **Purchase Tracking**: Mark items as purchased
- **Email Notifications**: Automated notifications for:
  - Wishlist sharing
  - Item purchases
  - Welcome emails
- **Modern UI**: Beautiful, responsive design with:
  - Blue gradient backgrounds
  - Smooth animations
  - Mobile-friendly interface
  - Bootstrap 5 components

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server with mod_rewrite enabled
- SMTP server for email notifications (optional)

## Installation

### 1. Database Setup

1. Create a MySQL database for the application
2. Run the SQL script from `database-schema.sql` to create tables
3. Update the default user passwords in the database

### 2. Configuration

1. Copy `config.php` to a secure location outside your web root
2. Update the configuration values:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```

3. Configure email settings (for notifications):
   ```php
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_USERNAME', 'your_email@gmail.com');
   define('SMTP_PASSWORD', 'your_app_password');
   ```

4. Update site settings:
   ```php
   define('SITE_URL', 'https://yourdomain.com');
   ```

### 3. File Structure

Place files in your web directory:
```
/public_html/
├── index.php
├── dashboard.php
├── wishlist.php
├── categories.php
├── email-helper.php
├── .htaccess
└── /config/ (outside web root)
    └── config.php
```

### 4. Security Setup

1. Move `config.php` outside the web root
2. Update the require path in PHP files
3. Set proper file permissions:
   ```bash
   chmod 644 *.php
   chmod 755 directories
   ```
4. Enable HTTPS in production
5. Update `.htaccess` force HTTPS rule

### 5. Create Additional Files

You'll need to create these supporting files:
- `create-wishlist.php` - Handle wishlist creation
- `edit-wishlist.php` - Edit wishlist settings
- `add-item.php` - Add items to wishlists
- `edit-item.php` - Edit wishlist items
- `delete-item.php` - Delete items
- `mark-purchased.php` - Mark items as purchased
- `profile.php` - User profile page

## Default Users

The system comes with two default users:
- Username: `Carole` / Email: `carole@example.com`
- Username: `Ted` / Email: `ted@example.com`

**Important**: Change the default passwords immediately after installation!

## Email Queue

To process the email queue, set up a cron job:
```bash
*/5 * * * * php /path/to/email-helper.php
```

## Customization

### Colors
Update theme colors in `config.php`:
```php
define('PRIMARY_COLOR', '#007bff');
define('SECONDARY_COLOR', '#6c757d');
define('BACKGROUND_COLOR', '#e3f2fd');
```

### Categories
Default categories include:
- Electronics
- Books
- Home & Garden
- Clothing
- Hobbies
- Kitchen

Users can create unlimited custom categories with their own colors.

## Security Features

- CSRF token protection
- Prepared statements for SQL queries
- Password hashing with bcrypt
- Session security settings
- XSS protection
- Input sanitization
- Secure headers
- HTTPS enforcement (configurable)

## Database Schema

The application uses 6 main tables:
- `users` - User accounts
- `categories` - Item categories
- `wishlists` - Wishlist containers
- `wishlist_items` - Individual items
- `shared_wishlists` - Sharing permissions
- `email_notifications` - Email queue

## Support for Different Database Types

While the application is built for MySQL, you can adapt it for SQLite by:
1. Removing MySQL-specific syntax (AUTO_INCREMENT, ENUM)
2. Using INTEGER PRIMARY KEY for auto-increment in SQLite
3. Replacing ENUM with CHECK constraints
4. Adjusting date/time functions

## Troubleshooting

### Common Issues

1. **Login fails**: Check database connection and password hashing
2. **Emails not sending**: Verify SMTP settings and credentials
3. **404 errors**: Ensure mod_rewrite is enabled
4. **Permission errors**: Check file and directory permissions

### Debug Mode

Enable debug mode in `config.php` for development:
```php
define('DEBUG_MODE', true);
```

**Important**: Disable debug mode in production!

## Future Enhancements

- User registration system
- Password reset functionality
- Advanced search and filtering
- Item image uploads
- Price history tracking
- Mobile app API
- Export/import wishlists
- Social sharing features

## License

This application is provided as-is for personal/family use.

## Support

For issues or questions, please check the configuration settings and ensure all requirements are met.