<?php
// Database connection
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
    
    // Simple session management
    session_start();
    
    // Check if streamers table exists, if not redirect to setup
    $tablesQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='streamers'");
    if (!$tablesQuery->fetch()) {
        header('Location: streamers_setup.php');
        exit;
    }
    
    // Function to update YouTube streams
    function updateYouTubeStreams($db, $apiKey) {
        // Get all YouTube streamers
        $stmt = $db->query("SELECT id, platform_id FROM streamers WHERE platform = 'youtube'");
        $youtubeStreamers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($youtubeStreamers)) {
            return [];
        }
        
        // Get channel IDs
        $channelIds = array_column($youtubeStreamers, 'platform_id');
        $channelIdsStr = implode(',', $channelIds);
        
        // Check which channels are live
        $apiUrl = "https://www.googleapis.com/youtube/v3/search?part=snippet&eventType=live&type=video&channelId=" . 
                   $channelIdsStr . "&key=" . $apiKey;
        
        // Fetch data from API
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $apiUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        
        if ($err) {
            error_log("YouTube API Error: " . $err);
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['items'])) {
            error_log("Invalid YouTube API response: " . $response);
            return false;
        }
        
        // Process live streams
        $liveStreamers = [];
        foreach ($data['items'] as $item) {
            $channelId = $item['snippet']['channelId'];
            $videoId = $item['id']['videoId'];
            $title = $item['snippet']['title'];
            $thumbnail = $item['snippet']['thumbnails']['medium']['url'];
            
            // Find streamer index
            $streamerKey = array_search($channelId, array_column($youtubeStreamers, 'platform_id'));
            if ($streamerKey !== false) {
                $streamerId = $youtubeStreamers[$streamerKey]['id'];
                
                // Get view count for this live stream
                $videoUrl = "https://www.googleapis.com/youtube/v3/videos?part=statistics,liveStreamingDetails&id=" . 
                             $videoId . "&key=" . $apiKey;
                
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $videoUrl);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                $videoResponse = curl_exec($curl);
                curl_close($curl);
                
                $videoData = json_decode($videoResponse, true);
                $viewCount = 0;
                
                if (isset($videoData['items'][0]['liveStreamingDetails']['concurrentViewers'])) {
                    $viewCount = intval($videoData['items'][0]['liveStreamingDetails']['concurrentViewers']);
                }
                
                // Update database
                $stmt = $db->prepare("
                    UPDATE streamers 
                    SET status = 'online', 
                        title = ?, 
                        thumbnail_url = ?,
                        viewer_count = ?,
                        last_updated = datetime('now')
                    WHERE id = ?
                ");
                $stmt->execute([$title, $thumbnail, $viewCount, $streamerId]);
                
                $liveStreamers[] = $streamerId;
            }
        }
        
        // Set all non-live YouTube streamers to offline
        if (!empty($liveStreamers)) {
            $placeholders = str_repeat('?,', count($liveStreamers) - 1) . '?';
            $stmt = $db->prepare("
                UPDATE streamers 
                SET status = 'offline',
                    viewer_count = 0,
                    last_updated = datetime('now')
                WHERE platform = 'youtube' 
                AND id NOT IN ($placeholders)
            ");
            $stmt->execute($liveStreamers);
        } else {
            $stmt = $db->prepare("
                UPDATE streamers 
                SET status = 'offline',
                    viewer_count = 0,
                    last_updated = datetime('now')
                WHERE platform = 'youtube'
            ");
            $stmt->execute();
        }
        
        return true;
    }
    
    // Function to update Kick streams
    function updateKickStreams($db) {
        // Get all Kick streamers
        $stmt = $db->query("SELECT id, platform_id FROM streamers WHERE platform = 'kick'");
        $kickStreamers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($kickStreamers)) {
            return [];
        }
        
        $liveStreamers = [];
        
        // Kick doesn't have a public API, but we can simulate checking statuses
        // In a real implementation, you'd need to use their API or parse their website
        foreach ($kickStreamers as $streamer) {
            // For demonstration, we'll use a check against Kick's channel page
            $channelUrl = "https://kick.com/api/v1/channels/" . $streamer['platform_id'];
            
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $channelUrl);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            
            if ($err) {
                error_log("Kick API Error: " . $err);
                continue;
            }
            
            $data = json_decode($response, true);
            
            if (!is_array($data)) {
                error_log("Invalid Kick API response for " . $streamer['platform_id']);
                continue;
            }
            
            // Check if the streamer is live
            $isLive = isset($data['livestream']) && $data['livestream'] !== null;
            $status = $isLive ? 'online' : 'offline';
            $viewCount = $isLive && isset($data['livestream']['viewer_count']) ? $data['livestream']['viewer_count'] : 0;
            $title = $isLive && isset($data['livestream']['session_title']) ? $data['livestream']['session_title'] : '';
            $thumbnail = $isLive && isset($data['livestream']['thumbnail']['url']) ? $data['livestream']['thumbnail']['url'] : '';
            
            if (!$thumbnail && isset($data['user']['profile_pic'])) {
                $thumbnail = $data['user']['profile_pic'];
            }
            
            // Update database
            $stmt = $db->prepare("
                UPDATE streamers 
                SET status = ?, 
                    title = ?, 
                    thumbnail_url = ?,
                    viewer_count = ?,
                    last_updated = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$status, $title, $thumbnail, $viewCount, $streamer['id']]);
            
            if ($isLive) {
                $liveStreamers[] = $streamer['id'];
            }
        }
        
        return true;
    }
    
    // Check if we need to refresh streamer data (every 5 minutes)
    $stmt = $db->query("SELECT MAX(last_updated) as last_update FROM streamers");
    $lastUpdate = $stmt->fetchColumn();
    $needsUpdate = !$lastUpdate || (time() - strtotime($lastUpdate) > 300);
    
    if ($needsUpdate) {
        // YouTube API key - in a real application, this should be stored securely
        // For this example, we'll check if an API key is provided in the environment
        $youtubeApiKey = getenv('YOUTUBE_API_KEY') ?: '';
        
        if (!empty($youtubeApiKey)) {
            updateYouTubeStreams($db, $youtubeApiKey);
        }
        
        // Update Kick streams
        updateKickStreams($db);
    }
    
    // Get all streamers
    $stmt = $db->query("
        SELECT * FROM streamers 
        ORDER BY 
            CASE WHEN status = 'online' THEN 0 ELSE 1 END, 
            featured DESC,
            viewer_count DESC
    ");
    $streamers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count online and offline streamers
    $onlineCount = 0;
    $offlineCount = 0;
    
    foreach ($streamers as $streamer) {
        if ($streamer['status'] === 'online') {
            $onlineCount++;
        } else {
            $offlineCount++;
        }
    }
    
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Streamers - IP2∞Social.network</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .streamers-container {
            margin-bottom: 20px;
            color: var(--text-primary);
        }
        
        .streamers-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background-color: var(--content-bg);
            border-radius: 4px 4px 0 0;
            border: 1px solid var(--border-color);
            border-bottom: none;
        }
        
        .status-counts {
            display: flex;
            gap: 15px;
        }
        
        .status-count {
            font-weight: bold;
        }
        
        .online-count {
            color: #4caf50;
        }
        
        .offline-count {
            color: #9e9e9e;
        }
        
        .streamers-list {
            background-color: var(--content-bg);
            border-radius: 0 0 4px 4px;
            border: 1px solid var(--border-color);
        }
        
        .streamer-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s;
        }
        
        .streamer-item:last-child {
            border-bottom: none;
        }
        
        .streamer-item:hover {
            background-color: var(--hover-bg);
        }
        
        .streamer-thumbnail {
            width: 60px;
            height: 60px;
            border-radius: 4px;
            margin-right: 15px;
            object-fit: cover;
            background-color: #000;
        }
        
        .streamer-info {
            flex: 1;
        }
        
        .streamer-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .streamer-title {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 500px;
        }
        
        .streamer-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .viewers-count {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .platform-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .platform-youtube {
            background-color: #f00;
            color: #fff;
        }
        
        .platform-kick {
            background-color: #53fc18;
            color: #000;
        }
        
        .streamer-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            margin-left: 15px;
        }
        
        .viewers-count {
            color: #ff9800;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .watch-button {
            background-color: var(--accent-secondary);
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        
        .watch-button:hover {
            opacity: 0.9;
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
            display: inline-block;
        }
        
        .status-online {
            background-color: #4caf50;
            box-shadow: 0 0 5px #4caf50;
        }
        
        .status-offline {
            background-color: #9e9e9e;
        }
        
        .no-streamers {
            padding: 20px;
            text-align: center;
            color: var(--text-secondary);
        }
        
        .live-banner {
            text-align: center;
            padding: 20px 0;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .live-text {
            font-size: 36px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 10px;
            display: inline-block;
            position: relative;
            color: white;
        }
        
        .live-dot {
            display: inline-block;
            width: 20px;
            height: 20px;
            background-color: #f00;
            border-radius: 50%;
            margin-left: 10px;
            position: relative;
            top: -3px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 0, 0, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(255, 0, 0, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 0, 0, 0);
            }
        }
        
        .streamers-toggle {
            display: flex;
            margin-bottom: 20px;
        }
        
        .toggle-button {
            flex: 1;
            padding: 10px;
            text-align: center;
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            cursor: pointer;
        }
        
        .toggle-button:first-child {
            border-radius: 4px 0 0 4px;
        }
        
        .toggle-button:last-child {
            border-radius: 0 4px 4px 0;
        }
        
        .toggle-button.active {
            background-color: var(--accent-secondary);
            color: white;
            border-color: var(--accent-secondary);
        }
        
        .streamers-section {
            display: none;
        }
        
        .streamers-section.active {
            display: block;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--accent-secondary);
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .refresh-button {
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .refresh-button:hover {
            background-color: var(--hover-bg);
        }
        
        .last-updated {
            font-size: 12px;
            color: var(--text-secondary);
            text-align: right;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Banner image behind header -->
        <div class="banner">
            <img src="assets/images/banner.png" alt="IP2 Banner" class="banner-image">
        </div>
        
        <!-- Subreddit-style header -->
        <header class="subreddit-header">
            <div class="subreddit-title">
                <h1>IP2∞Social.network</h1>
            </div>
            
            <nav class="subreddit-nav">
                <ul class="nav-tabs">
                    <li class="nav-tab"><a href="index.php">Timeline</a></li>
                    <li class="nav-tab"><a href="#">Members</a></li>
                    <li class="nav-tab"><a href="#">Links</a></li>
                </ul>
                
                <div class="nav-actions">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="#" class="favorite-button"><i class="fas fa-star"></i></a>
                        <div class="more-options">
                            <button class="more-button"><i class="fas fa-ellipsis-h"></i></button>
                        </div>
                        <a href="profile.php" class="member-button"><?php echo htmlspecialchars($_SESSION['username']); ?></a>
                    <?php else: ?>
                        <a href="#" class="favorite-button"><i class="far fa-star"></i></a>
                        <div class="more-options">
                            <button class="more-button"><i class="fas fa-ellipsis-h"></i></button>
                        </div>
                        <a href="login.php" class="member-button">Login</a>
                    <?php endif; ?>
                </div>
            </nav>
        </header>

        <div class="content-wrapper">
            <main class="main-content">
                <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
                
                <div class="live-banner">
                    <div class="live-text">LIVE <span class="live-dot"></span></div>
                </div>
                
                <div class="streamers-container">
                    <div class="streamers-header">
                        <div class="status-counts">
                            <span class="status-count online-count">Online: <?php echo $onlineCount; ?></span>
                            <span class="status-count offline-count">Offline: <?php echo $offlineCount; ?></span>
                        </div>
                        
                        <div>
                            <div class="last-updated">
                                Last updated: <?php echo !empty($lastUpdate) ? date('M j, Y g:i A', strtotime($lastUpdate)) : 'Never'; ?>
                            </div>
                            <a href="?refresh=1" class="refresh-button">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </a>
                        </div>
                    </div>

                    <div class="streamers-toggle">
                        <div class="toggle-button active" data-target="all-streamers">All Streamers</div>
                        <div class="toggle-button" data-target="online-streamers">Online</div>
                        <div class="toggle-button" data-target="youtube-streamers">YouTube</div>
                        <div class="toggle-button" data-target="kick-streamers">Kick</div>
                    </div>
                    
                    <!-- All Streamers -->
                    <div id="all-streamers" class="streamers-section active">
                        <div class="streamers-list">
                            <?php if (empty($streamers)): ?>
                                <div class="no-streamers">No streamers found</div>
                            <?php else: ?>
                                <?php foreach ($streamers as $streamer): ?>
                                    <div class="streamer-item">
                                        <img src="<?php echo !empty($streamer['thumbnail_url']) ? htmlspecialchars($streamer['thumbnail_url']) : 'assets/images/default_thumbnail.jpg'; ?>" alt="<?php echo htmlspecialchars($streamer['display_name']); ?>" class="streamer-thumbnail">
                                        
                                        <div class="streamer-info">
                                            <div class="streamer-name">
                                                <span class="status-indicator <?php echo $streamer['status'] === 'online' ? 'status-online' : 'status-offline'; ?>"></span>
                                                <?php echo htmlspecialchars($streamer['display_name']); ?>
                                                <span class="platform-badge platform-<?php echo strtolower($streamer['platform']); ?>"><?php echo strtoupper($streamer['platform']); ?></span>
                                            </div>
                                            
                                            <?php if ($streamer['status'] === 'online' && !empty($streamer['title'])): ?>
                                                <div class="streamer-title"><?php echo htmlspecialchars($streamer['title']); ?></div>
                                            <?php endif; ?>
                                            
                                            <div class="streamer-meta">
                                                <?php if ($streamer['status'] === 'online'): ?>
                                                    <span class="status-text">LIVE</span>
                                                <?php else: ?>
                                                    <span class="status-text">OFFLINE</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="streamer-actions">
                                            <?php if ($streamer['status'] === 'online'): ?>
                                                <div class="viewers-count">
                                                    <i class="fas fa-eye"></i> <?php echo number_format($streamer['viewer_count']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($streamer['platform'] === 'youtube'): ?>
                                                <a href="https://youtube.com/channel/<?php echo urlencode($streamer['platform_id']); ?>/live" target="_blank" class="watch-button">Watch</a>
                                            <?php elseif ($streamer['platform'] === 'kick'): ?>
                                                <a href="https://kick.com/<?php echo urlencode($streamer['platform_id']); ?>" target="_blank" class="watch-button">Watch</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Online Streamers -->
                    <div id="online-streamers" class="streamers-section">
                        <div class="streamers-list">
                            <?php
                            $hasOnline = false;
                            foreach ($streamers as $streamer) {
                                if ($streamer['status'] === 'online') {
                                    $hasOnline = true;
                                    ?>
                                    <div class="streamer-item">
                                        <img src="<?php echo !empty($streamer['thumbnail_url']) ? htmlspecialchars($streamer['thumbnail_url']) : 'assets/images/default_thumbnail.jpg'; ?>" alt="<?php echo htmlspecialchars($streamer['display_name']); ?>" class="streamer-thumbnail">
                                        
                                        <div class="streamer-info">
                                            <div class="streamer-name">
                                                <span class="status-indicator status-online"></span>
                                                <?php echo htmlspecialchars($streamer['display_name']); ?>
                                                <span class="platform-badge platform-<?php echo strtolower($streamer['platform']); ?>"><?php echo strtoupper($streamer['platform']); ?></span>
                                            </div>
                                            
                                            <?php if (!empty($streamer['title'])): ?>
                                                <div class="streamer-title"><?php echo htmlspecialchars($streamer['title']); ?></div>
                                            <?php endif; ?>
                                            
                                            <div class="streamer-meta">
                                                <span class="status-text">LIVE</span>
                                            </div>
                                        </div>
                                        
                                        <div class="streamer-actions">
                                            <div class="viewers-count">
                                                <i class="fas fa-eye"></i> <?php echo number_format($streamer['viewer_count']); ?>
                                            </div>
                                            
                                            <?php if ($streamer['platform'] === 'youtube'): ?>
                                                <a href="https://youtube.com/channel/<?php echo urlencode($streamer['platform_id']); ?>/live" target="_blank" class="watch-button">Watch</a>
                                            <?php elseif ($streamer['platform'] === 'kick'): ?>
                                                <a href="https://kick.com/<?php echo urlencode($streamer['platform_id']); ?>" target="_blank" class="watch-button">Watch</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                            
                            if (!$hasOnline) {
                                echo '<div class="no-streamers">No streamers are currently live</div>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- YouTube Streamers -->
                    <div id="youtube-streamers" class="streamers-section">
                        <div class="streamers-list">
                            <?php
                            $hasYoutube = false;
                            foreach ($streamers as $streamer) {
                                if ($streamer['platform'] === 'youtube') {
                                    $hasYoutube = true;
                                    ?>
                                    <div class="streamer-item">
                                        <img src="<?php echo !empty($streamer['thumbnail_url']) ? htmlspecialchars($streamer['thumbnail_url']) : 'assets/images/default_thumbnail.jpg'; ?>" alt="<?php echo htmlspecialchars($streamer['display_name']); ?>" class="streamer-thumbnail">
                                        
                                        <div class="streamer-info">
                                            <div class="streamer-name">
                                                <span class="status-indicator <?php echo $streamer['status'] === 'online' ? 'status-online' : 'status-offline'; ?>"></span>
                                                <?php echo htmlspecialchars($streamer['display_name']); ?>
                                                <span class="platform-badge platform-youtube">YOUTUBE</span>
                                            </div>
                                            
                                            <?php if ($streamer['status'] === 'online' && !empty($streamer['title'])): ?>
                                                <div class="streamer-title"><?php echo htmlspecialchars($streamer['title']); ?></div>
                                            <?php endif; ?>
                                            
                                            <div class="streamer-meta">
                                                <?php if ($streamer['status'] === 'online'): ?>
                                                    <span class="status-text">LIVE</span>
                                                <?php else: ?>
                                                    <span class="status-text">OFFLINE</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="streamer-actions">
                                            <?php if ($streamer['status'] === 'online'): ?>
                                                <div class="viewers-count">
                                                    <i class="fas fa-eye"></i> <?php echo number_format($streamer['viewer_count']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <a href="https://youtube.com/channel/<?php echo urlencode($streamer['platform_id']); ?>/live" target="_blank" class="watch-button">Watch</a>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                            
                            if (!$hasYoutube) {
                                echo '<div class="no-streamers">No YouTube streamers found</div>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Kick Streamers -->
                    <div id="kick-streamers" class="streamers-section">
                        <div class="streamers-list">
                            <?php
                            $hasKick = false;
                            foreach ($streamers as $streamer) {
                                if ($streamer['platform'] === 'kick') {
                                    $hasKick = true;
                                    ?>
                                    <div class="streamer-item">
                                        <img src="<?php echo !empty($streamer['thumbnail_url']) ? htmlspecialchars($streamer['thumbnail_url']) : 'assets/images/default_thumbnail.jpg'; ?>" alt="<?php echo htmlspecialchars($streamer['display_name']); ?>" class="streamer-thumbnail">
                                        
                                        <div class="streamer-info">
                                            <div class="streamer-name">
                                                <span class="status-indicator <?php echo $streamer['status'] === 'online' ? 'status-online' : 'status-offline'; ?>"></span>
                                                <?php echo htmlspecialchars($streamer['display_name']); ?>
                                                <span class="platform-badge platform-kick">KICK</span>
                                            </div>
                                            
                                            <?php if ($streamer['status'] === 'online' && !empty($streamer['title'])): ?>
                                                <div class="streamer-title"><?php echo htmlspecialchars($streamer['title']); ?></div>
                                            <?php endif; ?>
                                            
                                            <div class="streamer-meta">
                                                <?php if ($streamer['status'] === 'online'): ?>
                                                    <span class="status-text">LIVE</span>
                                                <?php else: ?>
                                                    <span class="status-text">OFFLINE</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="streamer-actions">
                                            <?php if ($streamer['status'] === 'online'): ?>
                                                <div class="viewers-count">
                                                    <i class="fas fa-eye"></i> <?php echo number_format($streamer['viewer_count']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <a href="https://kick.com/<?php echo urlencode($streamer['platform_id']); ?>" target="_blank" class="watch-button">Watch</a>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                            
                            if (!$hasKick) {
                                echo '<div class="no-streamers">No Kick streamers found</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </main>

            <aside class="sidebar">
                <!-- Sidebar buttons -->
                <div class="sidebar-buttons">
                    <div class="button-row">
                        <a href="live.php" class="sidebar-button live-button active"><span class="live-icon">⚫</span> LIVE</a>
                        <a href="#" class="sidebar-button leaderboard-button">LEADERBOARD</a>
                    </div>
                    <div class="button-row">
                        <a href="upload.php" class="sidebar-button upload-button">UPLOAD VIDEO</a>
                        <a href="emotes.php" class="sidebar-button">EMOTES</a>
                    </div>
                </div>
                
                <!-- About Box -->
                <div class="about-box">
                    <h2>About Live Page</h2>
                    
                    <h4 class="section-title">Content Creators</h4>
                    <p class="about-text">This page shows all active IP2 streamers. See who's live on YouTube and Kick.</p>
                    
                    <h4 class="section-title">Missing a Streamer?</h4>
                    <p class="about-text">If we're missing a streamer, suggest them to be added by contacting a mod.</p>
                    
                    <h4 class="section-title">Features</h4>
                    <ul class="goals-list">
                        <li><span class="checkmark">✅</span> Real-time stream status</li>
                        <li><span class="checkmark">✅</span> Live viewer counts</li>
                        <li><span class="checkmark">✅</span> Direct links to streams</li>
                    </ul>
                </div>
            </aside>
        </div>
        
        <footer>
            <p>© <?php echo date('Y'); ?> IP2∞Social.network</p>
        </footer>
    </div>

    <script>
        // Toggle between streamer sections
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButtons = document.querySelectorAll('.toggle-button');
            
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    toggleButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Hide all sections
                    document.querySelectorAll('.streamers-section').forEach(section => {
                        section.classList.remove('active');
                    });
                    
                    // Show target section
                    const targetId = this.getAttribute('data-target');
                    document.getElementById(targetId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>
