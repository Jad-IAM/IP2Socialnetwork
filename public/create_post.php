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
    
    // Redirect if not logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    
    $postError = '';
    $postSuccess = false;
    $videoUrl = isset($_GET['video_url']) ? $_GET['video_url'] : '';
    
    // Get all tags for the dropdown
    $stmt = $db->query("SELECT id, name FROM tags ORDER BY name ASC");
    $allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle post creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_post') {
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $selectedTags = $_POST['tags'] ?? [];
        $customTags = $_POST['custom_tags'] ?? '';
        $videoUrl = $_POST['video_url'] ?? '';
        
        // Handle image upload
        $imageUrl = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "uploads/images/";
            
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            $fileName = time() . '_' . basename($_FILES['image']['name']);
            $targetFilePath = $targetDir . $fileName;
            $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
            
            // Allow certain file formats
            $allowTypes = array('jpg', 'png', 'jpeg', 'gif', 'webp');
            if (in_array(strtolower($fileType), $allowTypes)) {
                // Upload file to the server
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                    $imageUrl = $targetFilePath;
                } else {
                    $postError = "Sorry, there was an error uploading your image.";
                }
            } else {
                $postError = "Sorry, only JPG, JPEG, PNG, GIF, & WEBP files are allowed.";
            }
        }
        
        if (!$postError) {
            if (empty($title)) {
                $postError = "Post title is required";
            } elseif (empty($content)) {
                $postError = "Post content is required";
            } else {
                // Create post
                $stmt = $db->prepare("INSERT INTO posts (title, content, user_id, image_url, video_url, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))");
                $stmt->execute([$title, $content, $_SESSION['user_id'], $imageUrl, $videoUrl]);
                $postId = $db->lastInsertId();
                
                // Add selected tags
                if (!empty($selectedTags)) {
                    foreach ($selectedTags as $tagId) {
                        $stmt = $db->prepare("INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)");
                        $stmt->execute([$postId, $tagId]);
                    }
                }
                
                // Process custom tags
                if (!empty($customTags)) {
                    $tagsList = explode(',', $customTags);
                    foreach ($tagsList as $tag) {
                        $tag = trim($tag);
                        if (!empty($tag)) {
                            // Check if tag exists
                            $stmt = $db->prepare("SELECT id FROM tags WHERE name = ?");
                            $stmt->execute([$tag]);
                            $tagId = $stmt->fetchColumn();
                            
                            if (!$tagId) {
                                // Create new tag
                                $stmt = $db->prepare("INSERT INTO tags (name) VALUES (?)");
                                $stmt->execute([$tag]);
                                $tagId = $db->lastInsertId();
                            }
                            
                            // Add tag to post
                            $stmt = $db->prepare("INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)");
                            $stmt->execute([$postId, $tagId]);
                        }
                    }
                }
                
                $postSuccess = true;
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
    <title>Create Post - IP2∞</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .create-post-container {
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
        
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px;
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 4px;
        }
        
        .form-group select[multiple] {
            height: 150px;
        }
        
        .form-group textarea {
            min-height: 150px;
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
        
        .post-error {
            background-color: rgba(255, 0, 0, 0.1);
            border: 1px solid #8b0000;
            padding: 15px;
            margin-bottom: 20px;
            color: #ff6666;
            border-radius: 4px;
        }
        
        .post-success {
            background-color: rgba(0, 128, 0, 0.1);
            border: 1px solid #006400;
            padding: 15px;
            margin-bottom: 20px;
            color: #00ff00;
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

        .tag-options {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .tag-preset {
            background-color: var(--accent-secondary);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            cursor: pointer;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .tag-preset:hover {
            opacity: 0.8;
        }

        /* Different colors for tag types */
        .tag-preset.breaking {
            background-color: #e53935;
        }
        
        .tag-preset.content {
            background-color: #43a047;
        }
        
        .tag-preset.meta {
            background-color: #1e88e5;
        }
        
        .tag-preset.drama {
            background-color: #fb8c00;
        }
        
        .tag-preset.highlight {
            background-color: #8e24aa;
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
                    <h1 class="page-title">Create New Post</h1>
                    
                    <?php if ($postError): ?>
                        <div class="post-error">
                            <p><?php echo htmlspecialchars($postError); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($postSuccess): ?>
                        <div class="post-success">
                            <h3>Post Created Successfully!</h3>
                            <p>Your post has been published to the community.</p>
                            <p><a href="index.php" class="back-link">Go to Home</a></p>
                        </div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="create_post">
                            
                            <div class="form-group">
                                <label for="title">Post Title*</label>
                                <input type="text" id="title" name="title" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="content">Content*</label>
                                <textarea id="content" name="content" required></textarea>
                                <p class="help-text">Tip: Use green text by starting a line with &gt;</p>
                            </div>
                            
                            <div class="form-group">
                                <label for="image">Image (Optional)</label>
                                <input type="file" id="image" name="image" accept="image/*">
                            </div>
                            
                            <?php if (!empty($videoUrl)): ?>
                                <div class="form-group">
                                    <label>Video URL</label>
                                    <input type="text" name="video_url" value="<?php echo htmlspecialchars($videoUrl); ?>" readonly>
                                    <p class="help-text">This video URL was pre-filled from your upload.</p>
                                </div>
                            <?php else: ?>
                                <div class="form-group">
                                    <label for="video_url">Video URL (Optional)</label>
                                    <input type="text" id="video_url" name="video_url" placeholder="e.g., https://example.com/your-video.mp4">
                                    <p class="help-text">You can also use our <a href="upload.php">Upload Video</a> page.</p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label>Post Flairs/Tags</label>
                                <div class="tag-options">
                                    <span onclick="addTag('Breaking News')" class="tag-preset breaking">Breaking News</span>
                                    <span onclick="addTag('CLIP')" class="tag-preset content">CLIP</span>
                                    <span onclick="addTag('Quality')" class="tag-preset highlight">Quality</span>
                                    <span onclick="addTag('Drama')" class="tag-preset drama">Drama</span>
                                    <span onclick="addTag('Meta')" class="tag-preset meta">Meta</span>
                                    <span onclick="addTag('Edited')" class="tag-preset content">Edited</span>
                                    <span onclick="addTag('IP2∞')" class="tag-preset highlight">IP2∞</span>
                                </div>
                                
                                <select id="tags" name="tags[]" multiple>
                                    <?php foreach ($allTags as $tag): ?>
                                        <option value="<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="help-text">Hold Ctrl/Cmd to select multiple tags</p>
                            </div>
                            
                            <div class="form-group">
                                <label for="custom_tags">Custom Tags (comma separated)</label>
                                <input type="text" id="custom_tags" name="custom_tags" placeholder="e.g., live, fail, drama">
                            </div>
                            
                            <button type="submit" class="submit-button">Create Post</button>
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
                
                <!-- Post Guidelines -->
                <div class="about-box">
                    <h2>Posting Guidelines</h2>
                    
                    <h4 class="section-title">Post Types</h4>
                    <ul class="rules-list">
                        <li>Clips from streams</li>
                        <li>Breaking news & updates</li>
                        <li>Community highlights</li>
                        <li>Content creator discussions</li>
                    </ul>
                    
                    <h4 class="section-title">Tips</h4>
                    <ul class="notes-list">
                        <li>Use appropriate flairs/tags</li>
                        <li>Be creative with titles</li>
                        <li>Include timestamps for longer clips</li>
                        <li>Quality > Quantity</li>
                    </ul>
                </div>
            </aside>
        </div>
        
        <footer>
            <p>© <?php echo date('Y'); ?> IP2∞ Network</p>
        </footer>
    </div>

    <script>
        function addTag(tagName) {
            const select = document.getElementById('tags');
            
            // Look for the tag in the options
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].text === tagName) {
                    select.options[i].selected = true;
                    return;
                }
            }
            
            // If tag not found in select, add to custom tags
            const customTagsInput = document.getElementById('custom_tags');
            if (customTagsInput.value) {
                customTagsInput.value += ', ' + tagName;
            } else {
                customTagsInput.value = tagName;
            }
        }
    </script>
</body>
</html>
