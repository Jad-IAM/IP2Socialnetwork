<?php
/**
 * Common functions for IP2∞ forum
 */

/**
 * Process content to convert emote codes to images
 * Formats: #EmoteName or [emote:EmoteName]
 * 
 * @param string $content The content to process
 * @return string The processed content with emote codes replaced with images
 */
function processEmotes($content) {
    // Connect to database
    $db = getDatabase();
    
    // Get all emotes from database
    $stmt = $db->query("SELECT name, image_url FROM emotes");
    $emotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process emotes in format #EmoteName
    foreach ($emotes as $emote) {
        $emoteName = $emote['name'];
        $emoteUrl = $emote['image_url'];
        
        // Replace #EmoteName with emote image
        $pattern = '/#' . preg_quote($emoteName, '/') . '\b/i';
        $replacement = '<img src="' . htmlspecialchars($emoteUrl) . '" alt="' . htmlspecialchars($emoteName) . '" class="emote" title="' . htmlspecialchars($emoteName) . '">';
        $content = preg_replace($pattern, $replacement, $content);
        
        // Also replace [emote:EmoteName] format
        $pattern = '/\[emote:' . preg_quote($emoteName, '/') . '\]/i';
        $content = preg_replace($pattern, $replacement, $content);
    }
    
    return $content;
}

/**
 * Format timestamp to relative time (e.g. "2 hours ago")
 * 
 * @param string $timestamp The timestamp to format
 * @return string The formatted relative time
 */
function formatRelativeTime($timestamp) {
    $timestamp = strtotime($timestamp);
    $current = time();
    $diff = $current - $timestamp;
    
    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
    } else {
        return date("M j, Y", $timestamp);
    }
}

/**
 * Create directory if it doesn't exist
 * 
 * @param string $directory The directory path to create
 * @return bool True if directory exists or was created successfully, false otherwise
 */
function createDirectory($directory) {
    if (!file_exists($directory)) {
        return mkdir($directory, 0755, true);
    }
    return true;
}

/**
 * Generate random filename for uploaded files
 * 
 * @param string $originalName The original filename
 * @param string $extension The file extension
 * @return string A random filename with the given extension
 */
function generateRandomFilename($originalName, $extension) {
    $randomString = substr(md5(uniqid(mt_rand(), true)), 0, 10);
    $timestamp = time();
    $originalBaseName = pathinfo($originalName, PATHINFO_FILENAME);
    // Sanitize original filename
    $originalBaseName = preg_replace('/[^a-zA-Z0-9]/', '', $originalBaseName);
    $originalBaseName = substr($originalBaseName, 0, 20); // Limit length
    
    return $timestamp . '_' . $randomString . '_' . $originalBaseName . '.' . $extension;
}

/**
 * Check if file has allowed extension
 * 
 * @param string $filename The filename to check
 * @param array $allowedExtensions Array of allowed extensions
 * @return bool True if file has allowed extension, false otherwise
 */
function hasAllowedExtension($filename, $allowedExtensions) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $allowedExtensions);
}

/**
 * Sanitize and limit string length
 * 
 * @param string $string The string to sanitize
 * @param int $maxLength Maximum allowed length
 * @return string The sanitized string
 */
function sanitizeString($string, $maxLength = 255) {
    // Remove HTML tags and trim whitespace
    $string = trim(strip_tags($string));
    
    // Limit length
    if (strlen($string) > $maxLength) {
        $string = substr($string, 0, $maxLength);
    }
    
    return $string;
}

/**
 * Create avatar directories and default avatars if they don't exist
 */
function ensureAvatarsExist() {
    $avatarDir = __DIR__ . '/../assets/avatars';
    
    // Create avatar directory if it doesn't exist
    if (!file_exists($avatarDir)) {
        mkdir($avatarDir, 0755, true);
    }
    
    // Create default SVG avatars if they don't exist
    $defaultColors = [
        '#bb3fff', // Purple
        '#ff5733', // Orange
        '#33ff57', // Green
        '#3357ff', // Blue
        '#ff33f5'  // Pink
    ];
    
    for ($i = 1; $i <= 5; $i++) {
        $avatarFile = $avatarDir . '/avatar' . $i . '.svg';
        
        if (!file_exists($avatarFile)) {
            $color = $defaultColors[$i - 1];
            $svg = '<?xml version="1.0" encoding="UTF-8"?>';
            $svg .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">';
            $svg .= '<circle cx="50" cy="50" r="50" fill="' . $color . '"/>';
            $svg .= '<text x="50" y="65" font-family="Arial" font-size="45" text-anchor="middle" fill="white">' . chr(64 + $i) . '</text>';
            $svg .= '</svg>';
            
            file_put_contents($avatarFile, $svg);
        }
    }
}

/**
 * Create emote directories and ensure they exist
 */
function ensureEmotesExist() {
    $emoteDir = __DIR__ . '/../assets/emotes';
    
    // Create emote directory if it doesn't exist
    if (!file_exists($emoteDir)) {
        mkdir($emoteDir, 0755, true);
    }
    
    // Check if default emotes exist, if not, create placeholders
    $defaultEmotes = ['kek', 'rage', 'pepe', 'sadge', 'pog', 'yikes'];
    
    foreach ($defaultEmotes as $emote) {
        $emoteFile = $emoteDir . '/' . $emote . '.png';
        
        if (!file_exists($emoteFile)) {
            createPlaceholderEmote($emoteFile, $emote);
        }
    }
}

/**
 * Create a placeholder emote image
 * 
 * @param string $filename The path to create the placeholder
 * @param string $emoteName The name of the emote
 */
function createPlaceholderEmote($filename, $emoteName) {
    // Create a simple SVG and convert to PNG
    // Since we can't rely on GD conversion in all environments, we'll create a basic PNG
    
    $img = imagecreatetruecolor(28, 28);
    $bg = imagecolorallocate($img, 50, 50, 50);
    $text = imagecolorallocate($img, 255, 255, 255);
    
    // Fill background
    imagefilledrectangle($img, 0, 0, 28, 28, $bg);
    
    // Add text (first letter of emote name)
    imagestring($img, 2, 10, 7, substr($emoteName, 0, 1), $text);
    
    // Save as PNG
    imagepng($img, $filename);
    imagedestroy($img);
}

/**
 * Ensure necessary directories exist for the forum
 */
function ensureDirectoriesExist() {
    // Array of directories to create
    $directories = [
        __DIR__ . '/../assets',
        __DIR__ . '/../assets/images',
        __DIR__ . '/../assets/avatars',
        __DIR__ . '/../assets/emotes',
        __DIR__ . '/../assets/uploads',
        __DIR__ . '/../assets/uploads/images',
        __DIR__ . '/../assets/uploads/videos',
        __DIR__ . '/../db'
    ];
    
    // Create each directory if it doesn't exist
    foreach ($directories as $directory) {
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
    }
    
    // Ensure the avatars and emotes exist
    ensureAvatarsExist();
    ensureEmotesExist();
}

// Call this function to ensure all necessary directories exist
ensureDirectoriesExist();
?>
