<?php
/**
 * IP2∞Social.network Installation Script
 * 
 * Run this script once to set up the forum. It will:
 * 1. Create necessary directories
 * 2. Initialize the database and tables
 * 3. Create a default admin user
 */

echo "IP2∞Social.network Installation\n";
echo "==============================\n\n";

// Create directories
$directories = [
    'storage/sqlite',
    'public/uploads/videos',
    'public/uploads/images',
    'public/assets/emotes',
];

echo "Creating directories...\n";
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo "✓ Created: $dir\n";
        } else {
            echo "✗ Failed to create: $dir\n";
        }
    } else {
        echo "✓ Already exists: $dir\n";
    }
}

// Set permissions
echo "\nSetting directory permissions...\n";
foreach ($directories as $dir) {
    if (chmod($dir, 0777)) {
        echo "✓ Set permissions for: $dir\n";
    } else {
        echo "✗ Failed to set permissions for: $dir\n";
    }
}

// Initialize database
echo "\nInitializing database...\n";
$dbFile = __DIR__ . '/storage/sqlite/forum.sqlite';
$dbExists = file_exists($dbFile);

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if (!$dbExists) {
        echo "Creating database tables...\n";
        
        // Create users table
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                email TEXT UNIQUE,
                avatar TEXT,
                bio TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "✓ Created users table\n";
        
        // Create posts table
        $db->exec("
            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                content TEXT,
                link TEXT,
                video_url TEXT,
                user_id INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        echo "✓ Created posts table\n";
        
        // Create votes table
        $db->exec("
            CREATE TABLE IF NOT EXISTS votes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                vote INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (post_id) REFERENCES posts(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                UNIQUE(post_id, user_id)
            )
        ");
        echo "✓ Created votes table\n";
        
        // Create comments table
        $db->exec("
            CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                parent_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (post_id) REFERENCES posts(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (parent_id) REFERENCES comments(id)
            )
        ");
        echo "✓ Created comments table\n";
        
        // Create tags table
        $db->exec("
            CREATE TABLE IF NOT EXISTS tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                color TEXT NOT NULL,
                is_default INTEGER DEFAULT 0
            )
        ");
        echo "✓ Created tags table\n";
        
        // Create post_tags table
        $db->exec("
            CREATE TABLE IF NOT EXISTS post_tags (
                post_id INTEGER NOT NULL,
                tag_id INTEGER NOT NULL,
                PRIMARY KEY (post_id, tag_id),
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
            )
        ");
        echo "✓ Created post_tags table\n";
        
        // Create videos table
        $db->exec("
            CREATE TABLE IF NOT EXISTS videos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                filename TEXT NOT NULL,
                original_filename TEXT NOT NULL,
                file_size INTEGER NOT NULL,
                file_type TEXT NOT NULL,
                url TEXT NOT NULL,
                user_id INTEGER,
                views INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        echo "✓ Created videos table\n";
        
        // Create streamers table
        $db->exec("
            CREATE TABLE IF NOT EXISTS streamers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                display_name TEXT NOT NULL,
                platform TEXT NOT NULL,
                platform_id TEXT,
                thumbnail_url TEXT,
                status TEXT DEFAULT 'offline',
                viewer_count INTEGER DEFAULT 0,
                title TEXT,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                featured INTEGER DEFAULT 0
            )
        ");
        echo "✓ Created streamers table\n";
        
        // Create emotes table
        $db->exec("
            CREATE TABLE IF NOT EXISTS emotes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                file_path TEXT NOT NULL,
                width INTEGER DEFAULT 24,
                height INTEGER DEFAULT 24,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "✓ Created emotes table\n";
        
        // Insert default admin user
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
        $stmt->execute(['admin', $adminPassword, 'admin@example.com']);
        echo "✓ Created admin user (username: admin, password: admin123)\n";
        
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
        echo "✓ Added default post tags/flairs\n";
        
        // Insert default streamers
        $streamers = [
            ['username' => 'ice_poseidon', 'display_name' => 'Ice Poseidon', 'platform' => 'kick', 'platform_id' => 'iceposeidon', 'featured' => 1],
            ['username' => 'johnny_somali', 'display_name' => 'Johnny Somali', 'platform' => 'kick', 'platform_id' => 'johnnysomali', 'featured' => 1],
            ['username' => 'sam_pepper', 'display_name' => 'Sam', 'platform' => 'kick', 'platform_id' => 'sampepper', 'featured' => 1],
            ['username' => 'lolcow', 'display_name' => 'LolcowLive', 'platform' => 'youtube', 'platform_id' => 'UCtLgwxMjRGXnKRJkUhC_JyQ', 'featured' => 1],
            ['username' => 'hyphonix', 'display_name' => 'Hyphonix', 'platform' => 'youtube', 'platform_id' => 'UCaFpm67qMk1Z1g9nRFSFL_A', 'featured' => 1],
        ];
        
        $stmt = $db->prepare("
            INSERT INTO streamers (username, display_name, platform, platform_id, featured)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($streamers as $streamer) {
            $stmt->execute([
                $streamer['username'],
                $streamer['display_name'],
                $streamer['platform'],
                $streamer['platform_id'],
                $streamer['featured']
            ]);
        }
        echo "✓ Added default streamers\n";
        
        echo "\nDatabase initialized successfully!\n";
    } else {
        echo "Database already exists. Skipping initialization.\n";
    }
    
    echo "\nInstallation completed successfully!\n";
    echo "\nYou can now access the forum at: http://localhost:5000/ (or your configured domain)\n";
    echo "Login with username 'admin' and password 'admin123'\n";
    
} catch (PDOException $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
