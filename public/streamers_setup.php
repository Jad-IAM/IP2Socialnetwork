<?php
// One-time script to set up streamers data
$dbFile = __DIR__ . '/../storage/sqlite/forum.sqlite';
$dbDirectory = dirname($dbFile);

// Make sure SQLite directory exists
if (!is_dir($dbDirectory)) {
    mkdir($dbDirectory, 0777, true);
}

// Connect to SQLite database
try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
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
    
    // Check if we have streamers already
    $stmt = $db->query("SELECT COUNT(*) FROM streamers");
    if ($stmt->fetchColumn() == 0) {
        // Insert sample streamers
        $streamers = [
            ['username' => 'ice_poseidon', 'display_name' => 'Ice Poseidon', 'platform' => 'kick', 'platform_id' => 'iceposeidon', 'featured' => 1],
            ['username' => 'johnny_somali', 'display_name' => 'Johnny Somali', 'platform' => 'kick', 'platform_id' => 'johnnysomali', 'featured' => 1],
            ['username' => 'sam_pepper', 'display_name' => 'Sam', 'platform' => 'kick', 'platform_id' => 'sampepper', 'featured' => 1],
            ['username' => 'lolcow', 'display_name' => 'LolcowLive', 'platform' => 'youtube', 'platform_id' => 'UCtLgwxMjRGXnKRJkUhC_JyQ', 'featured' => 1],
            ['username' => 'hyphonix', 'display_name' => 'Hyphonix', 'platform' => 'youtube', 'platform_id' => 'UCaFpm67qMk1Z1g9nRFSFL_A', 'featured' => 1],
            ['username' => 'smokeNscan', 'display_name' => 'Smoke N\' Scan', 'platform' => 'youtube', 'platform_id' => 'UCvqS9JIpXLZk2mPeJyXADFA', 'featured' => 1],
            ['username' => 'tazo', 'display_name' => 'Tazo', 'platform' => 'kick', 'platform_id' => 'tazotodamax', 'featured' => 0],
            ['username' => 'jidion', 'display_name' => 'Jidion', 'platform' => 'kick', 'platform_id' => 'jidion', 'featured' => 0],
            ['username' => 'mando', 'display_name' => 'Mando', 'platform' => 'kick', 'platform_id' => 'realworldmando', 'featured' => 0],
            ['username' => 'ebz', 'display_name' => 'EBZ', 'platform' => 'youtube', 'platform_id' => 'UCkR8ndH0NypMYtVYARnQ-_g', 'featured' => 0],
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
        
        echo "Streamers data successfully initialized!";
    } else {
        echo "Streamers data already exists.";
    }
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
