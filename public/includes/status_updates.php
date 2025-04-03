<?php
// Include database connection
require_once(__DIR__ . '/database.php');

// Connect to SQLite database
try {
    $db = getDatabase();
    
    // Create status_updates table if it doesn't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS status_updates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content TEXT NOT NULL,
            user_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
    ");
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>
