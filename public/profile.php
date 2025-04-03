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
    
    $userId = $_SESSION['user_id'];
    $profileError = '';
    $profileSuccess = false;
    
    // Get user data
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die('User not found');
    }
    
    // Get user's posts count
    $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $postsCount = $stmt->fetchColumn();
    
    // Get user's comments count
    $stmt = $db->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
    $stmt->execute([$userId]);
    $commentsCount = $stmt->fetchColumn();
    
    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $email = $_POST['email'] ?? '';
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Update email
        if (!empty($email) && $email !== $user['email']) {
            // Check if email is already in use
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $profileError = "Email already in use by another account";
            } else {
                $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$email, $userId]);
                $profileSuccess = true;
            }
        }
        
        // Update password
        if (!empty($currentPassword) && !empty($newPassword)) {
            // Verify current password
            if (password_verify($currentPassword, $user['password'])) {
                if ($newPassword === $confirmPassword) {
                    if (strlen($newPassword) >= 6) {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashedPassword, $userId]);
                        $profileSuccess = true;
                    } else {
                        $profileError = "New password must be at least 6 characters";
                    }
                } else {
                    $profileError = "New passwords do not match";
                }
            } else {
                $profileError = "Current password is incorrect";
            }
        }
        
        // Handle avatar change
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "assets/avatars/";
            
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            $fileName = 'user_' . $userId . '_' . time() . '.' . pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $targetFilePath = $targetDir . $fileName;
            $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
            
            // Allow certain file formats
            $allowTypes = array('jpg', 'png', 'jpeg', 'gif', 'webp', 'svg');
            if (in_array(strtolower($fileType), $allowTypes)) {
                // Upload file to the server
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFilePath)) {
                    // Update avatar in database
                    $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->execute([$fileName, $userId]);
                    
                    // Update session avatar
                    $_SESSION['avatar'] = $fileName;
                    
                    $profileSuccess = true;
                } else {
                    $profileError = "Sorry, there was an error uploading your avatar.";
                }
            } else {
                $profileError = "Sorry, only JPG, JPEG, PNG, GIF, WEBP & SVG files are allowed.";
            }
        }
        
        // Reload user data after updates
        if ($profileSuccess) {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    // Get user's recent posts
    $stmt = $db->prepare("
        SELECT p.*, 
               (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
               (SELECT SUM(vote) FROM votes WHERE post_id = p.id) as vote_count
        FROM posts p 
        WHERE p.user_id = ? 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Helper function to format time difference
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . "s";
    } elseif ($diff < 3600) {
        return floor($diff / 60) . "m";
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . "h";
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . "d";
    } elseif ($diff < 2592000) {
        return floor($diff / 604800) . "w";
    } else {
        return date("M j", $time);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - IP2∞</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .profile-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .profile-header {
            background-color: var(--content-bg);
            border-radius: 4px;
            padding: 20px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin-right: 20px;
            overflow: hidden;
            border: 3px solid var(--accent-secondary);
            flex-shrink: 0;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-username {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--text-primary);
        }
        
        .profile-stats {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        
        .stat {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .stat-count {
            font-size: 18px;
            font-weight: bold;
            color: var(--accent-secondary);
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .profile-edit {
            background-color: var(--content-bg);
            border-radius: 4px;
            padding: 20px;
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
        
        .form-group input {
            width: 100%;
            padding: 10px;
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 4px;
        }
        
        .section-title {
            color: var(--accent-secondary);
            margin-bottom: 15px;
            font-size: 18px;
            padding-bottom: 5px;
            border-bottom: 1px solid var(--border-color);
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
        
        .profile-error {
            background-color: rgba(255, 0, 0, 0.1);
            border: 1px solid #8b0000;
            padding: 15px;
            margin-bottom: 20px;
            color: #ff6666;
            border-radius: 4px;
        }
        
        .profile-success {
            background-color: rgba(0, 128, 0, 0.1);
            border: 1px solid #006400;
            padding: 15px;
            margin-bottom: 20px;
            color: #00ff00;
            border-radius: 4px;
        }
        
        .profile-posts {
            background-color: var(--content-bg);
            border-radius: 4px;
            padding: 20px;
            border: 1px solid var(--border-color);
        }
        
        .post-item {
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .post-item:last-child {
            border-bottom: none;
        }
        
        .post-title {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .post-title a {
            color: var(--post-title);
            text-decoration: none;
        }
        
        .post-meta {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            gap: 10px;
        }
        
        .see-all {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: var(--accent-secondary);
            text-decoration: none;
            padding: 5px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }
        
        .see-all:hover {
            background-color: var(--hover-bg);
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
                <h1>IP2∞ (IP2.Social) (IP2Infinity.Social)</h1>
            </div>
            
            <nav class="subreddit-nav">
                <ul class="nav-tabs">
                    <li class="nav-tab"><a href="index.php">Timeline</a></li>
                    <li class="nav-tab"><a href="#">Members</a></li>
                    <li class="nav-tab"><a href="#">Links</a></li>
                </ul>
                
                <div class="nav-actions">
                    <a href="#" class="favorite-button"><i class="fas fa-star"></i></a>
                    <div class="more-options">
                        <button class="more-button"><i class="fas fa-ellipsis-h"></i></button>
                    </div>
                    <a href="#" class="member-button">Member</a>
                </div>
            </nav>
        </header>

        <div class="content-wrapper">
            <main class="main-content">
                <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
                
                <div class="profile-container">
                    <!-- Profile Header -->
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <img src="assets/avatars/<?php echo $user['avatar'] ?: 'default_avatar.svg'; ?>" alt="User avatar">
                        </div>
                        <div class="profile-info">
                            <div class="profile-username"><?php echo htmlspecialchars($user['username']); ?> <?php echo $user['is_mod'] ? '<span style="color:#ff4500;">[MOD]</span>' : ''; ?></div>
                            <div class="profile-created">Member since: <?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
                            <div class="profile-stats">
                                <div class="stat">
                                    <span class="stat-count"><?php echo $postsCount; ?></span>
                                    <span class="stat-label">Posts</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-count"><?php echo $commentsCount; ?></span>
                                    <span class="stat-label">Comments</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Posts -->
                    <div class="profile-posts">
                        <h3 class="section-title">Recent Posts</h3>
                        
                        <?php if (empty($recentPosts)): ?>
                            <p>You haven't created any posts yet.</p>
                        <?php else: ?>
                            <?php foreach ($recentPosts as $post): ?>
                                <div class="post-item">
                                    <div class="post-title">
                                        <a href="index.php?page=post&id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                                    </div>
                                    <div class="post-meta">
                                        <span><i class="fas fa-arrow-up"></i> <?php echo $post['vote_count'] ?: 0; ?></span>
                                        <span><i class="far fa-comment-alt"></i> <?php echo $post['comment_count']; ?></span>
                                        <span><i class="far fa-clock"></i> <?php echo timeAgo($post['created_at']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <a href="#" class="see-all">See All Posts</a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Profile Edit Form -->
                    <div class="profile-edit">
                        <h3 class="section-title">Edit Profile</h3>
                        
                        <?php if ($profileError): ?>
                            <div class="profile-error">
                                <p><?php echo htmlspecialchars($profileError); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($profileSuccess): ?>
                            <div class="profile-success">
                                <p>Profile updated successfully!</p>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="avatar">Change Avatar</label>
                                <input type="file" id="avatar" name="avatar" accept="image/*">
                            </div>
                            
                            <h4 class="section-title">Change Password</h4>
                            
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password">
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password">
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password">
                            </div>
                            
                            <button type="submit" class="submit-button">Update Profile</button>
                        </form>
                    </div>
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
                        <a href="emotes.php" class="sidebar-button">EMOTES</a>
                    </div>
                </div>
                
                <!-- Profile Actions -->
                <div class="about-box">
                    <h2>Profile Actions</h2>
                    
                    <ul class="notes-list">
                        <li><a href="index.php?page=create_post"><i class="fas fa-plus-circle"></i> Create New Post</a></li>
                        <li><a href="upload.php"><i class="fas fa-video"></i> Upload Video</a></li>
                        <li><a href="index.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </aside>
        </div>
        
        <footer>
            <p>© <?php echo date('Y'); ?> IP2∞ (IP2.Social)</p>
        </footer>
    </div>
</body>
</html>
