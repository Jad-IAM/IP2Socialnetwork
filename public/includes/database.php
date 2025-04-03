<?php
/**
 * Database connection handler for IP2∞ forum
 */

/**
 * Get database connection
 * 
 * @return PDO Database connection object
 */
function getDatabase() {
    static $db = null;
    
    if ($db === null) {
        try {
            // Database file path
            $dbPath = __DIR__ . '/../db/forum.db';
            $dbDir = dirname($dbPath);
            
            // Create database directory if it doesn't exist
            if (!file_exists($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            // Connect to SQLite database
            $db = new PDO('sqlite:' . $dbPath);
            
            // Set error mode
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Enable foreign keys
            $db->exec('PRAGMA foreign_keys = ON');
            
            // Set busy timeout to avoid database is locked errors
            $db->exec('PRAGMA busy_timeout = 30000');
            
            // Initialize database if tables don't exist
            initializeDatabase($db);
            
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    
    return $db;
}

/**
 * Initialize database with required tables if they don't exist
 * 
 * @param PDO $db Database connection
 */
function initializeDatabase($db) {
    try {
        // Check if users table exists
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        
        if (!$result->fetch()) {
            // Create users table
            $db->exec('
                CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE,
                    password TEXT NOT NULL,
                    email TEXT,
                    avatar TEXT DEFAULT "avatar1.svg",
                    bio TEXT,
                    is_mod INTEGER DEFAULT 0,
                    is_banned INTEGER DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ');
            
            // Create posts table
            $db->exec('
                CREATE TABLE posts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    title TEXT,
                    content TEXT NOT NULL,
                    flair TEXT,
                    image_url TEXT,
                    video_url TEXT,
                    upvotes INTEGER DEFAULT 0,
                    downvotes INTEGER DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ');
            
            // Create comments table
            $db->exec('
                CREATE TABLE comments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    post_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    content TEXT NOT NULL,
                    parent_id INTEGER,
                    upvotes INTEGER DEFAULT 0,
                    downvotes INTEGER DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (post_id) REFERENCES posts(id),
                    FOREIGN KEY (user_id) REFERENCES users(id),
                    FOREIGN KEY (parent_id) REFERENCES comments(id)
                )
            ');
            
            // Create votes table
            $db->exec('
                CREATE TABLE votes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    post_id INTEGER,
                    comment_id INTEGER,
                    vote_type INTEGER NOT NULL, -- 1 for upvote, -1 for downvote
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id),
                    FOREIGN KEY (post_id) REFERENCES posts(id),
                    FOREIGN KEY (comment_id) REFERENCES comments(id),
                    UNIQUE(user_id, post_id, comment_id)
                )
            ');
            
            // Create emotes table
            $db->exec('
                CREATE TABLE emotes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT UNIQUE NOT NULL,
                    image_url TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ');
            
            // Add default emotes
            $defaultEmotes = [
                ['name' => 'Kek', 'image_url' => 'assets/emotes/kek.png'],
                ['name' => 'Rage', 'image_url' => 'assets/emotes/rage.png'],
                ['name' => 'Pepe', 'image_url' => 'assets/emotes/pepe.png'],
                ['name' => 'Sadge', 'image_url' => 'assets/emotes/sadge.png'],
                ['name' => 'Pog', 'image_url' => 'assets/emotes/pog.png'],
                ['name' => 'Yikes', 'image_url' => 'assets/emotes/yikes.png']
            ];
            
            $stmt = $db->prepare('INSERT INTO emotes (name, image_url) VALUES (?, ?)');
            foreach ($defaultEmotes as $emote) {
                $stmt->execute([$emote['name'], $emote['image_url']]);
            }
            
            // Create streams table for tracking live streams
            $db->exec('
                CREATE TABLE streams (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    platform TEXT NOT NULL,
                    channel_id TEXT NOT NULL,
                    is_live INTEGER DEFAULT 0,
                    title TEXT,
                    viewers INTEGER DEFAULT 0,
                    thumbnail TEXT,
                    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id),
                    UNIQUE(user_id, platform)
                )
            ');
            
            // Add default admin user
            $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $db->exec("INSERT INTO users (username, password, is_mod) VALUES ('admin', '$hashedPassword', 1)");
            
            // Add a default regular user
            $hashedUserPassword = password_hash('user123', PASSWORD_DEFAULT);
            $db->exec("INSERT INTO users (username, password, avatar) VALUES ('404JesterNotFound', '$hashedUserPassword', 'avatar2.svg')");
            
            // Add some sample posts
            $db->exec("
                INSERT INTO posts (user_id, title, content, flair) 
                VALUES 
                (1, 'Welcome to IP2∞', 'Welcome to our new forum! This is a place to discuss all things IP2 related. Feel free to share content, videos, or just chat with the community.', 'Announcement'),
                (2, 'Testing video uploads #Pog', 'Just uploaded my first video here! The quality is pretty good. #Pog has anyone else tried this feature yet?', 'Question')
            ");
        }
        
    } catch (PDOException $e) {
        die('Database initialization failed: ' . $e->getMessage());
    }
}
?>
