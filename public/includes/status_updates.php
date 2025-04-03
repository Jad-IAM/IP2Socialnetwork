<?php
// Database connection
$dbFile = __DIR__ . '/../../storage/sqlite/forum.sqlite';

// Connect to SQLite database
try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
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
