<?php
// Database connection from index.php
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
    
    $uploadSuccess = false;
    $uploadError = '';
    
    // Handle video upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_video') {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php');
            exit;
        }
        
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        
        // This is a simplified version - in a real implementation we would:
        // 1. Validate and save the uploaded file
        // 2. Process the video (compression, format validation)
        // 3. Generate a unique URL for embedding
        
        if (!empty($title) && !empty($description)) {
            // For now, just generate a mock video URL
            $videoUrl = 'https://example.com/videos/' . time() . '-' . rand(1000, 9999) . '.mp4';
            
            // Save video info to database
            $stmt = $db->prepare("INSERT INTO videos (title, description, url, user_id, created_at) 
                                VALUES (?, ?, ?, ?, datetime('now'))");
            
            try {
                // First check if the videos table exists, if not create it
                $db->exec("
                    CREATE TABLE IF NOT EXISTS videos (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        title TEXT NOT NULL,
                        description TEXT NOT NULL,
                        url TEXT NOT NULL,
                        user_id INTEGER,
                        views INTEGER DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id)
                    );
                ");
                
                $stmt->execute([$title, $description, $videoUrl, $_SESSION['user_id']]);
                $uploadSuccess = true;
                $embedCode = '<iframe src="' . htmlspecialchars($videoUrl) . '" width="640" height="360" frameborder="0" allowfullscreen></iframe>';
            } catch (Exception $e) {
                $uploadError = 'Database error: ' . $e->getMessage();
            }
        } else {
            $uploadError = 'Please fill in all required fields';
        }
    }
    
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Get username by ID
function getUsername($db, $user_id) {
    $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['username'] : 'Unknown';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Video - IP2∞</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .upload-container {
            background-color: var(--content-bg);
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-primary);
            font-weight: bold;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 4px;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .submit-button {
            background-color: var(--accent-secondary);
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: bold;
            border-radius: 4px;
        }
        
        .submit-button:hover {
            opacity: 0.9;
        }
        
        .upload-success {
            background-color: rgba(0, 128, 0, 0.1);
            border: 1px solid #006400;
            padding: 15px;
            margin-bottom: 20px;
            color: #00ff00;
            border-radius: 4px;
        }
        
        .upload-error {
            background-color: rgba(255, 0, 0, 0.1);
            border: 1px solid #8b0000;
            padding: 15px;
            margin-bottom: 20px;
            color: #ff6666;
            border-radius: 4px;
        }
        
        .embed-code {
            background-color: var(--background-color);
            padding: 15px;
            border: 1px solid var(--border-color);
            margin-top: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
            border-radius: 4px;
        }
        
        .page-title {
            color: var(--accent-secondary);
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--accent-secondary);
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .help-text {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Banner image behind header -->
        <div class="banner">
            <img src="assets/images/banner.png" alt="IP2 Network Banner" class="banner-image">
        </div>
        
        <!-- Subreddit-style header -->
        <header class="subreddit-header">
            <div class="subreddit-title">
                <h1>IP2∞ (IP2Infinity.network)</h1>
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
                        <a href="#" class="member-button">Member</a>
                    <?php else: ?>
                        <a href="#" class="favorite-button"><i class="far fa-star"></i></a>
                        <div class="more-options">
                            <button class="more-button"><i class="fas fa-ellipsis-h"></i></button>
                        </div>
                        <a href="index.php?page=login" class="member-button">Login</a>
                    <?php endif; ?>
                </div>
            </nav>
        </header>

        <div class="content-wrapper">
            <main class="main-content">
                <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
                
                <div class="upload-container">
                    <h1 class="page-title">Upload Video</h1>
                    
                    <?php if ($uploadSuccess): ?>
                        <div class="upload-success">
                            <h3>Upload Successful!</h3>
                            <p>Your video has been uploaded successfully. Use the embed code below to share your video.</p>
                            <div class="embed-code"><?php echo htmlspecialchars($embedCode); ?></div>
                            <p>Or you can create a post with this video: <a href="index.php?page=create_post&video_url=<?php echo urlencode($videoUrl); ?>" class="submit-button" style="display: inline-block; margin-top: 10px;">Create Post</a></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($uploadError): ?>
                        <div class="upload-error">
                            <h3>Upload Error</h3>
                            <p><?php echo htmlspecialchars($uploadError); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$uploadSuccess): ?>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_video">
                            
                            <div class="form-group">
                                <label for="title">Video Title*</label>
                                <input type="text" id="title" name="title" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description*</label>
                                <textarea id="description" name="description" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="video_file">Video File*</label>
                                <input type="file" id="video_file" name="video_file" accept="video/*" required>
                                <p class="help-text">Max file size: 100MB. Supported formats: MP4, WebM, MOV</p>
                            </div>
                            
                            <div class="form-group">
                                <label for="thumbnail">Thumbnail (Optional)</label>
                                <input type="file" id="thumbnail" name="thumbnail" accept="image/*">
                            </div>
                            
                            <div class="form-group">
                                <label for="tags">Tags (Comma separated)</label>
                                <input type="text" id="tags" name="tags" placeholder="e.g. clip, quality, breaking news">
                            </div>
                            
                            <button type="submit" class="submit-button">Upload Video</button>
                        </form>
                    <?php endif; ?>
                </div>
            </main>

            <aside class="sidebar">
                <!-- Sidebar buttons -->
                <div class="sidebar-buttons">
                    <div class="button-row">
                        <a href="#" class="sidebar-button live-button"><span class="live-icon">⚫</span> LIVE</a>
                        <a href="#" class="sidebar-button leaderboard-button">LEADERBOARD</a>
                    </div>
                    <div class="button-row">
                        <a href="upload.php" class="sidebar-button upload-button">UPLOAD VIDEO</a>
                    </div>
                </div>
                
                <!-- About Box -->
                <div class="about-box">
                    <h2>About</h2>
                    <div class="about-header">
                        <h3>Internet-Platform 2 Infinity (IP2∞)</h3>
                        <p class="about-subtitle">Formerly Ice Poseidon 2</p>
                    </div>
                    <hr>
                    
                    <h4 class="section-title">Welcoming Newcomers</h4>
                    <p class="about-text">This is a fresh start—new platform, new people, better community. Our community originally formed around the controversial live streamer Ice Poseidon, but evolved into a decentralized network of IRL streamers, pranksters, and content creators.</p>
                    
                    <h4 class="section-title">Our Goals</h4>
                    <ul class="goals-list">
                        <li><span class="checkmark">✅</span> Bring back the witty, high-effort trolling that made this community legendary</li>
                        <li><span class="checkmark">✅</span> Create a space for real discussions, not just mindless spam</li>
                        <li><span class="checkmark">✅</span> Content is King. Community-voted streamer lists and content curation</li>
                    </ul>
                    
                    <h4 class="section-title">Moderators</h4>
                    <ul class="mod-list">
                        <li><strong>404JesterNotFound</strong></li>
                        <li><strong>RubyOnRails</strong></li>
                    </ul>
                </div>
            </aside>
        </div>
        
        <footer>
            <p>© <?php echo date('Y'); ?> IP2∞ Network</p>
        </footer>
    </div>
</body>
</html>
