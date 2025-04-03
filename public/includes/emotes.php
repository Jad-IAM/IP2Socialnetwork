<?php
/**
 * Emotes utility class to handle parsing and replacing emote codes in content
 */
class Emotes {
    /**
     * Get all available emotes from the database
     * 
     * @param PDO $db Database connection
     * @return array Array of emotes with name and file_path
     */
    public static function getAllEmotes($db) {
        try {
            $stmt = $db->query("SELECT * FROM emotes ORDER BY name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // If there's an error or table doesn't exist, return empty array
            return [];
        }
    }
    
    /**
     * Parse content and replace emote codes with image tags
     * 
     * @param string $content The content to parse
     * @param PDO $db Database connection
     * @return string The parsed content with emote codes replaced with images
     */
    public static function parseEmotes($content, $db) {
        if (empty($content)) {
            return $content;
        }
        
        // Get all emotes
        $emotes = self::getAllEmotes($db);
        
        // If no emotes found, return original content
        if (empty($emotes)) {
            return $content;
        }
        
        // Build regex pattern for all emotes
        $pattern = '/#\/([a-zA-Z0-9]+)/';
        
        // Replace emote codes with images
        return preg_replace_callback($pattern, function($matches) use ($emotes) {
            $emoteName = $matches[1];
            
            // Find matching emote
            foreach ($emotes as $emote) {
                if (strcasecmp($emote['name'], $emoteName) === 0) {
                    // Return image tag for the emote
                    return '<img src="' . htmlspecialchars($emote['file_path']) . '" alt="' . htmlspecialchars($emote['name']) . '" class="emote" width="' . ($emote['width'] ?? 24) . '" height="' . ($emote['height'] ?? 24) . '" title="' . htmlspecialchars($emote['name']) . '">';
                }
            }
            
            // If no match found, return original code
            return $matches[0];
        }, $content);
    }
}
