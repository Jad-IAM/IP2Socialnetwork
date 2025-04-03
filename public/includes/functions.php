<?php
/**
 * Common functions for the site
 */

// Include emotes class
require_once(__DIR__ . '/emotes.php');

/**
 * Format a timestamp to a human-readable format
 * 
 * @param string $timestamp The timestamp to format
 * @return string Formatted timestamp
 */
function formatTimestamp($timestamp) {
    $date = new DateTime($timestamp);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    } elseif ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    } elseif ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'just now';
    }
}

/**
 * Parse and format content with emotes and basic formatting
 * 
 * @param string $content Raw content
 * @param PDO $db Database connection
 * @return string Formatted content
 */
function formatContent($content, $db) {
        /**
         * Parse emotes in titles
         */
        function formatTitle($title, $db) {
            return Emotes::parseEmotes($title, $db);
        }
    if (empty($content)) {
        return '';
    }
    
    // Parse emotes
    $content = Emotes::parseEmotes($content, $db);
    
    // Simple markdown-like formatting
    
    // Bold text
    $content = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $content);
    
    // Italic text
    $content = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $content);
    
    // Links
    $content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/s', '<a href="$2" target="_blank">$1</a>', $content);
    
    // Auto-link URLs
    $content = preg_replace('/(https?:\/\/[^\s<]+)/i', '<a href="$1" target="_blank">$1</a>', $content);
    
    // Convert newlines to <br>
    $content = nl2br($content);
    
    return $content;
}
