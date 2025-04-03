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
    
    // Redirect to login if not logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?redirect=' . urlencode('create_post.php'));
        exit;
    }
    
    // Make sure tags table exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            color TEXT NOT NULL,
            is_default INTEGER DEFAULT 0
        )
    ");
    
    // Make sure post_tags table exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS post_tags (
            post_id INTEGER NOT NULL,
            tag_id INTEGER NOT NULL,
            PRIMARY KEY (post_id, tag_id),
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
        )
    ");
    
    // Insert default tags if they don't exist
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
    
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM tags WHERE name = ?");
    $insertStmt = $db->prepare("INSERT INTO tags (name, color, is_default) VALUES (?, ?, ?)");
    
    foreach ($defaultTags as $tag) {
        $checkStmt->execute([$tag['name']]);
        if ($checkStmt->fetchColumn() == 0) {
            $insertStmt->execute([$tag['name'], $tag['color'], $tag['is_default']]);
        }
    }
    
    // Get all available tags
    $tagsStmt = $db->query("SELECT * FROM tags ORDER BY is_default DESC, name ASC");
    $tags = $tagsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if video URL is provided from upload page
    $videoUrl = isset($_GET['video_url']) ? $_GET['video_url'] : '';
    
    // Process form submission
    $successMessage = '';
    $errorMessage = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_post') {
        // Handle AJAX requests
        $isAjax = !empty($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) == "xmlhttprequest";

        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $videoUrl = trim($_POST['video_url'] ?? '');
        $selectedTags = isset($_POST['tags']) && is_array($_POST['tags']) ? $_POST['tags'] : [];
        
        if (empty($title)) {
            $errorMessage = 'Title is required';
        } elseif (strlen($title) < 3) {
            $errorMessage = 'Title must be at least 3 characters long';
        } elseif (empty($content) && empty($link) && empty($videoUrl)) {
            $errorMessage = 'Post must have either content, a link, or a video';
        } else {
            try {
                $db->beginTransaction();
                
                // Create post
                $stmt = $db->prepare("
                    INSERT INTO posts (title, content, link, video_url, user_id, created_at)
                    VALUES (?, ?, ?, ?, ?, datetime('now'))
                ");
                
                $stmt->execute([
                    $title,
                    $content,
                    $link ?: null,
                    $videoUrl ?: null,
                    $_SESSION['user_id']
                ]);
                
                $postId = $db->lastInsertId();
                
                // Add tags
                if (!empty($selectedTags)) {
                    $tagStmt = $db->prepare("
                        INSERT INTO post_tags (post_id, tag_id)
                        VALUES (?, ?)
                    ");
                    
                    foreach ($selectedTags as $tagId) {
                        $tagStmt->execute([$postId, $tagId]);
                    }
                }
                
                $db->commit();
                
                $successMessage = 'Post created successfully!';
                
                // Redirect to the main page after a short delay
                header('refresh:2;url=index.php');
                // If AJAX request, return JSON response instead of redirecting
                if ($isAjax) {
                    header("Content-Type: application/json");
                    echo json_encode(["success" => true, "message" => "Post created successfully!"]);
                    exit;
                }
            } catch (PDOException $e) {
                $db->rollBack();
                $errorMessage = 'Error creating post: ' . $e->getMessage();
                // If AJAX request, return JSON response
                if ($isAjax) {
                    header("Content-Type: application/json");
                    echo json_encode(["success" => false, "message" => "Error creating post: " . $e->getMessage()]);
                    exit;
                }
            }
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
    <title>Create Post - IP2∞Social.network</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .create-post-container {
            max-width: 800px;
            margin: 20px auto;
            background-color: var(--content-bg);
            border-radius: 4px;
            border: 1px solid var(--border-color);
            padding: 20px;
        }
        
        .create-post-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: var(--accent-secondary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 4px;
        }
        
        .form-control:focus {
            border-color: var(--accent-secondary);
            outline: none;
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .submit-button {
            background-color: var(--accent-secondary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .submit-button:hover {
            opacity: 0.9;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--accent-secondary);
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .success-message {
            background-color: rgba(0, 128, 0, 0.1);
            border: 1px solid #006400;
            padding: 15px;
            margin-bottom: 20px;
            color: #00ff00;
            border-radius: 4px;
        }
        
        .error-message {
            background-color: rgba(255, 0, 0, 0.1);
            border: 1px solid #8b0000;
            padding: 15px;
            margin-bottom: 20px;
            color: #ff6666;
            border-radius: 4px;
        }
        
        .tag-selection {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .tag-item {
            display: flex;
            align-items: center;
        }
        
        .tag-checkbox {
            display: none;
        }
        
        .tag-label {
            padding: 5px 10px;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 12px;
            font-weight: bold;
            color: white;
            display: inline-block;
            text-transform: uppercase;
        }
        
        .tag-checkbox:checked + .tag-label {
            box-shadow: 0 0 0 2px white, 0 0 0 4px var(--accent-secondary);
        }
        
        .tabs {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .tab {
            padding: 10px 15px;
            cursor: pointer;
            color: var(--text-secondary);
            position: relative;
            margin-right: 15px;
        }
        
        .tab:hover {
            color: var(--text-primary);
        }
        
        .tab.active {
            color: var(--accent-secondary);
            font-weight: bold;
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--accent-secondary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .video-preview {
            margin-top: 10px;
            max-width: 100%;
            border-radius: 4px;
            overflow: hidden;
        }
        
        video {
            max-width: 100%;
            border-radius: 4px;
        }
        
        .format-hint {
            margin-top: 5px;
            color: var(--text-secondary);
            font-size: 12px;
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
                
                <div class="create-post-container">
                    <h2 class="create-post-title">Create a New Post</h2>
                    
                    <?php if ($successMessage): ?>
                        <div class="success-message">
                            <?php echo htmlspecialchars($successMessage); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($errorMessage): ?>
                        <div class="error-message">
                            <?php echo htmlspecialchars($errorMessage); ?>
                        </div>
                    <?php endif; ?>
                    
<form method="POST" action="" id="post-form">
                        <input type="hidden" name="action" value="create_post">
                        
                        <div class="form-group">
                            <p class="format-hint">You can add emotes to your title with #/EmoteName syntax - <a href="emotes.php">See available emotes</a></p>
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title" class="form-control" required minlength="3">
                        </div>
                        
                        <div class="form-group">
                            <p class="format-hint">You can add emotes to your title with #/EmoteName syntax - <a href="emotes.php">See available emotes</a></p>
                            <label>Post Type</label>
                            <div class="tabs">
                                <div class="tab active" data-tab="text-post">Text Post</div>
                                <div class="tab" data-tab="link-post">Link Post</div>
                                <div class="tab" data-tab="video-post">Video Post</div>
                            </div>
                            
                            <div id="text-post" class="tab-content active">
                                <textarea id="content" name="content" class="form-control" placeholder="Write your post content here..."></textarea>
                                <p class="format-hint">You can use basic formatting: **bold**, *italic*, [link text](url)</p>
                            </div>
                            
                            <div id="link-post" class="tab-content">
                                <input type="text" id="link" name="link" class="form-control" placeholder="Enter URL here...">
                                <p class="format-hint">Enter a valid URL including http:// or https://</p>
                            </div>
                            
                            <div id="video-post" class="tab-content">
                                <input type="text" id="video_url" name="video_url" class="form-control" placeholder="Enter video URL here..." value="<?php echo htmlspecialchars($videoUrl); ?>">
                                <p class="format-hint">Enter a local video URL or YouTube/Kick embed URL</p>
                                
                                <?php if (!empty($videoUrl)): ?>
                                    <div class="video-preview">
                                        <video controls>
                                            <source src="<?php echo htmlspecialchars($videoUrl); ?>" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <p class="format-hint">You can add emotes to your title with #/EmoteName syntax - <a href="emotes.php">See available emotes</a></p>
                            <label>Pick a Flair</label>
                            <div class="flair-selection dropdown-style">
                                <select name="tags[]" class="form-control flair-select">
                                    <option value="">Select a Flair (Optional)</option>
                                    <?php foreach ($tags as $tag): ?>
                                        <option value="<?php echo $tag["id"]; ?>" data-color="<?php echo htmlspecialchars($tag["color"]); ?>">
                                            <?php echo htmlspecialchars($tag["name"]); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="submit-button">Create Post</button>
                    </form>
                </div>
            </main>

            <aside class="sidebar">
                <!-- Sidebar buttons -->
                <div class="sidebar-buttons">
                    <div class="button-row">
                        <a href="live.php" class="sidebar-button live-button"><span class="live-icon"></span>LIVE</a>
                        <a href="#" class="sidebar-button leaderboard-button">LEADERBOARD</a>
                    </div>
                    <div class="button-row">
                        <a href="upload.php" class="sidebar-button upload-button">UPLOAD VIDEO</a>
                    </div>
                </div>
                
                <!-- About Box -->
                <div class="about-box">
                    <h2>Posting Guidelines</h2>
                    
                    <h4 class="section-title">Post Types</h4>
                    <ul class="rules-list">
                        <li>Text posts for discussions</li>
                        <li>Link posts for external content</li>
                        <li>Video posts for media</li>
                    </ul>
                    
                    <h4 class="section-title">Pick a Flair</h4>
                    <ul class="rules-list">
                        <li>Add relevant tags to your post</li>
                        <li>Breaking News for important updates</li>
                        <li>Content for clips and highlights</li>
                        <li>Meme for funny content</li>
                    </ul>
                    
                    <h4 class="section-title">Rules</h4>
                    <p>Be respectful and follow IP2 community guidelines.</p>
                </div>
            </aside>
        </div>
        
        <footer>
            <p>© <?php echo date('Y'); ?> IP2∞Social.network</p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to current tab and content
                    this.classList.add('active');
                    document.getElementById(this.getAttribute('data-tab')).classList.add('active');
                });
            });
            
            // If video URL is already provided, switch to video tab
            const videoUrl = document.getElementById('video_url').value;
            if (videoUrl) {
                // Find the video tab and click it
                const videoTab = document.querySelector('[data-tab="video-post"]');
                if (videoTab) {
                    videoTab.click();
                }
            }
        });
    </script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const flairSelect = document.querySelector(".flair-select");
    if (flairSelect) {
        flairSelect.addEventListener("change", function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const color = selectedOption.getAttribute("data-color");
                this.style.backgroundColor = color;
                this.style.color = "white";
            } else {
                this.style.backgroundColor = "";
                this.style.color = "";
            }
        });
    }
});
</script>
</body>
</html>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const flairSelect = document.querySelector(".flair-select");
    if (flairSelect) {
        flairSelect.addEventListener("change", function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const color = selectedOption.getAttribute("data-color");
                this.style.backgroundColor = color;
                this.style.color = "white";
            } else {
                this.style.backgroundColor = "";
                this.style.color = "";
            }
        });
    }
});
</script>
