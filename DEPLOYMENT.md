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

## Backblaze B2 CDN Integration

To optimize bandwidth and resources, you can integrate with Backblaze B2 CDN for file storage:

### 1. Set Up Backblaze B2

1. Create a Backblaze account at [backblaze.com](https://www.backblaze.com/)
2. Create a new B2 bucket for your media files
3. Set the bucket to public
4. Generate application keys for your bucket

### 2. Configure PHP for Backblaze B2 Integration

Add this code to your `config.php` file:

```php
<?php
// Backblaze B2 Configuration
define('B2_ENABLED', true); // Set to false to use local storage instead
define('B2_BUCKET_NAME', 'your-bucket-name');
define('B2_APPLICATION_KEY_ID', 'your-application-key-id');
define('B2_APPLICATION_KEY', 'your-application-key');
define('B2_BUCKET_URL', 'https://f002.backblazeb2.com/file/your-bucket-name/'); // Update with your bucket URL
```

### 3. B2 Upload Integration

Create a file called `includes/b2_upload.php`:

```php
<?php
/**
 * Backblaze B2 Upload Helper
 */
function uploadToB2($localFilePath, $b2FileName) {
    if (!defined('B2_ENABLED') || !B2_ENABLED) {
        return false; // B2 integration not enabled
    }
    
    // Prepare the authentication
    $credentials = base64_encode(B2_APPLICATION_KEY_ID . ':' . B2_APPLICATION_KEY);
    
    // Get authorization token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.backblazeb2.com/b2api/v2/b2_authorize_account');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Basic ' . $credentials
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('B2 Auth Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    $authData = json_decode($response, true);
    
    if (!isset($authData['authorizationToken']) || !isset($authData['apiUrl'])) {
        error_log('B2 Auth Error: Invalid response');
        return false;
    }
    
    // Get upload URL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $authData['apiUrl'] . '/b2api/v2/b2_get_upload_url');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: ' . $authData['authorizationToken']
    ));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
        'bucketId' => B2_BUCKET_ID
    )));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('B2 Upload URL Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    $uploadData = json_decode($response, true);
    
    if (!isset($uploadData['uploadUrl']) || !isset($uploadData['authorizationToken'])) {
        error_log('B2 Upload URL Error: Invalid response');
        return false;
    }
    
    // Upload the file
    $fileContent = file_get_contents($localFilePath);
    $contentSha1 = sha1_file($localFilePath);
    $contentType = mime_content_type($localFilePath);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $uploadData['uploadUrl']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: ' . $uploadData['authorizationToken'],
        'Content-Type: ' . $contentType,
        'Content-Length: ' . filesize($localFilePath),
        'X-Bz-File-Name: ' . urlencode($b2FileName),
        'X-Bz-Content-Sha1: ' . $contentSha1
    ));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('B2 Upload Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    $fileData = json_decode($response, true);
    
    if (!isset($fileData['fileId'])) {
        error_log('B2 Upload Error: Invalid response');
        return false;
    }
    
    // Return the CDN URL for the file
    return B2_BUCKET_URL . $b2FileName;
}
```

### 4. Modify Upload Handling

Update your upload handling code to use B2 when enabled. Example:

```php
// In upload.php, replace the existing file storage code
if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $targetPath)) {
    // Generate URL (either local or B2)
    $fileUrl = 'uploads/videos/' . $newFilename;
    
    // If B2 is enabled, try to upload to B2
    if (defined('B2_ENABLED') && B2_ENABLED) {
        require_once(__DIR__ . '/includes/b2_upload.php');
        $b2Url = uploadToB2($targetPath, 'videos/' . $newFilename);
        
        if ($b2Url !== false) {
            // Use B2 URL instead of local
            $fileUrl = $b2Url;
            
            // Optionally delete the local file to save space
            @unlink($targetPath);
        }
    }
    
    // Continue with database insertion using $fileUrl
    // ...
}
```

### 5. Bandwidth Optimization

To prevent hotlinking and bandwidth abuse:

1. Add this to your .htaccess file:

```apache
# Prevent hotlinking
<IfModule mod_rewrite.c>
    RewriteEngine on
    RewriteCond %{HTTP_REFERER} !^$
    RewriteCond %{HTTP_REFERER} !^https?://(.+\.)?yourdomain\.com/ [NC]
    RewriteRule \.(jpe?g|png|gif|mp4|webm)$ - [NC,F,L]
</IfModule>
```

2. For Backblaze B2, set up a Cloudflare CDN in front of your B2 bucket:
   - Sign up for a free Cloudflare account
   - Add your domain
   - Create a CNAME record for a subdomain (e.g., cdn.yourdomain.com) pointing to your B2 bucket URL
   - Use Cloudflare's Hotlink Protection feature
