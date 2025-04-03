<?php
/**
 * Database connection helper
 * Provides centralized connection to SQLite database with improved locking handling
 */

/**
 * Get a database connection with proper configuration for SQLite
 * 
 * @return PDO Database connection
 */
function getDatabase() {
    $dbFile = __DIR__ . '/../../storage/sqlite/forum.sqlite';
    $dbDirectory = dirname($dbFile);
    
    // Make sure SQLite directory exists
    if (!is_dir($dbDirectory)) {
        mkdir($dbDirectory, 0777, true);
    }
    
    try {
        // Set SQLite connection with timeout and busy timeout to handle locking
        // SQLITE_OPEN_READWRITE | SQLITE_OPEN_CREATE | SQLITE_OPEN_FULLMUTEX | SQLITE_OPEN_SHAREDCACHE
        $dsn = 'sqlite:' . $dbFile;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 60, // 60 second timeout
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        // Create the database connection
        $db = new PDO($dsn, null, null, $options);
        
        // Make sure we use WAL mode for better concurrency
        $db->exec('PRAGMA journal_mode=WAL;');
        
        // Set busy timeout to 5000ms (5 seconds)
        $db->exec('PRAGMA busy_timeout=5000;');
        
        // Enable foreign keys
        $db->exec('PRAGMA foreign_keys=ON;');
        
        return $db;
    } catch (PDOException $e) {
        // Log the error
        error_log('Database connection failed: ' . $e->getMessage());
        
        // Rethrow the exception
        throw $e;
    }
}

/**
 * Runs database schema initialization and defaults as needed
 * 
 * @param PDO $db Database connection
 */
function initializeDatabase($db) {
    try {
        // Create tables if they don't exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                email TEXT UNIQUE,
                avatar TEXT,
                is_mod INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                user_id INTEGER,
                image_url TEXT,
                video_url TEXT,
                clip_id TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
            
            CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                content TEXT NOT NULL,
                post_id INTEGER,
                user_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (post_id) REFERENCES posts(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
            
            CREATE TABLE IF NOT EXISTS votes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER,
                user_id INTEGER,
                comment_id INTEGER,
                vote_type INTEGER, -- 1 for upvote, -1 for downvote
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (post_id) REFERENCES posts(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (comment_id) REFERENCES comments(id)
            );
            
            CREATE TABLE IF NOT EXISTS emotes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                file_path TEXT NOT NULL,
                width INTEGER DEFAULT 24,
                height INTEGER DEFAULT 24,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                color TEXT NOT NULL,
                is_default INTEGER DEFAULT 0
            );
            
            CREATE TABLE IF NOT EXISTS post_tags (
                post_id INTEGER NOT NULL,
                tag_id INTEGER NOT NULL,
                PRIMARY KEY (post_id, tag_id),
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
            );
            
            CREATE TABLE IF NOT EXISTS status_updates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ");
    } catch (PDOException $e) {
        error_log('Database initialization failed: ' . $e->getMessage());
        throw $e;
    }
}
?>
