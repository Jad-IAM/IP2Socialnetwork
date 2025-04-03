<?php
header('Content-Type: application/json');

// Include database connection
require_once(__DIR__ . '/includes/database.php');

try {
    $db = getDatabase();
    
    // Check if tags table exists
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='tags'");
    if (!$stmt->fetch()) {
        // Create tags table if it doesn't exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                color TEXT NOT NULL,
                is_default INTEGER DEFAULT 0
            )
        ");
        
        // Insert default tags
        $defaultTags = [
            ['name' => 'BREAKING NEWS', 'color' => '#ff0000', 'is_default' => 1],
            ['name' => 'CONTENT', 'color' => '#4caf50', 'is_default' => 1],
            ['name' => 'HIGHLIGHT', 'color' => '#ff9800', 'is_default' => 1],
            ['name' => 'DRAMA', 'color' => '#e91e63', 'is_default' => 1],
            ['name' => 'MEME', 'color' => '#9c27b0', 'is_default' => 1],
            ['name' => 'DISCUSSION', 'color' => '#2196f3', 'is_default' => 1],
            ['name' => 'TRENDING', 'color' => '#00bcd4', 'is_default' => 1],
            ['name' => 'CLIP', 'color' => '#607d8b', 'is_default' => 1],
        ];
        
        $stmt = $db->prepare("INSERT INTO tags (name, color, is_default) VALUES (?, ?, ?)");
        
        foreach ($defaultTags as $tag) {
            $stmt->execute([$tag['name'], $tag['color'], $tag['is_default']]);
        }
    }
    
    // Get all tags
    $stmt = $db->query("SELECT * FROM tags ORDER BY is_default DESC, name ASC");
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($tags);
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
