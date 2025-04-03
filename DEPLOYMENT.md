# IP2∞Social.network Deployment Guide

This guide will help you set up IP2∞Social.network on your own server.

## Requirements

- PHP 7.4 or higher
- SQLite3 extension for PHP
- Web server (Apache, Nginx, etc.)

## Setup Steps

### 1. File Structure Setup

Clone or upload all project files to your web server's document root directory or a subdirectory.

```
/var/www/html/ip2social/  (or wherever your web server serves files from)
```

Make sure the following directories have write permissions for the web server user:
- `storage/sqlite/` (for the database)
- `public/assets/emotes/` (for emote uploads)
- `public/uploads/videos/` (for video uploads)
- `public/uploads/images/` (for image uploads)

```bash
chmod -R 755 public/assets storage
chmod -R 777 public/assets/emotes public/uploads
```

### 2. Database Setup

The system will automatically create the SQLite database file and necessary tables on first access.

The database will be created at:
```
storage/sqlite/forum.sqlite
```

### 3. Web Server Configuration

#### For Apache

Create or modify .htaccess in the root directory:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # Redirect all requests to public directory
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

#### For Nginx

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/html/ip2social/public;
    
    index index.php index.html;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock; # Adjust based on your PHP version
    }
}
```

### 4. User Setup

The default admin account is:
- Username: admin
- Password: admin123

For security, change the admin password after first login by accessing the profile page.

### 5. Customization

- **Emotes**: Access the Emotes page as admin to add custom emotes
- **Banner**: Replace the image at `public/assets/images/banner.png` with your own banner
- **Colors**: Edit `public/styles.css` to modify the color scheme

### 6. API Keys (Optional)

For YouTube streaming functionality, you'll need to set up a YouTube API key:

1. Go to the [Google Developer Console](https://console.developers.google.com/)
2. Create a new project
3. Enable the YouTube Data API v3
4. Create an API key
5. Add this key to a file named `.env` in the root directory:

```
YOUTUBE_API_KEY=your_api_key_here
```

Add this code to the beginning of your `index.php` to load the API key:

```php
<?php
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            putenv(sprintf('%s=%s', $name, $value));
        }
    }
}
```

## Troubleshooting

### Permissions Issues

If you encounter permission errors:

```bash
# For directory permissions
find . -type d -exec chmod 755 {} \;

# For writable directories
chmod -R 777 storage public/uploads public/assets/emotes
```

### Database Issues

If you need to reset the database, simply delete the database file:

```bash
rm storage/sqlite/forum.sqlite
```

The system will recreate it on the next access.
