<?php
// Include necessary files
require_once(__DIR__ . "/includes/functions.php");
require_once(__DIR__ . "/includes/status_updates.php");

// Simple session management
session_start();

// Get status updates for the past week
try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get statuses from the past week, ordered by newest first
    $stmt = $db->query("
        SELECT s.*, u.username, u.avatar
        FROM status_updates s
        JOIN users u ON s.user_id = u.id
        WHERE s.created_at >= datetime('now', '-7 days')
        ORDER BY s.created_at DESC
    ");
    
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Status Updates - IP2∞Social.network</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="js/main.js"></script>
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
                    <li class="nav-tab active"><a href="statuses.php">Weekly Updates</a></li>
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
                <div class="page-title">
                    <h2>Weekly Status Updates</h2>
                    <p class="subtitle">See what everyone's been up to this week</p>
                </div>
                
                <!-- Status updates list -->
                <div class="status-list">
                    <?php if (empty($statuses)): ?>
                        <div class="empty-state">
                            <i class="fas fa-comment-dots empty-icon"></i>
                            <p>No status updates from the past week.</p>
                            <a href="index.php" class="btn">Share what's on your mind</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($statuses as $status): ?>
                            <div class="status-card">
                                <div class="status-avatar">
                                    <img src="assets/avatars/<?php echo htmlspecialchars($status['avatar'] ?: 'default_avatar.svg'); ?>" alt="User Avatar">
                                </div>
                                <div class="status-content">
                                    <div class="status-header">
                                        <span class="status-username"><?php echo htmlspecialchars($status['username']); ?></span>
                                        <span class="status-time"><?php echo timeAgo($status['created_at']); ?></span>
                                    </div>
                                    <div class="status-text">
                                        <?php 
                                        // Process content to display emotes
                                        $content = htmlspecialchars($status['content']);
                                        $content = preg_replace_callback('/#\/(\w+)/', function($matches) {
                                            return '<img src="emotes/' . $matches[1] . '.png" alt="' . $matches[1] . '" class="inline-emote">';
                                        }, $content);
                                        echo $content;
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
            
            <?php include 'includes/sidebar.php'; ?>
        </div>
    </div>
</body>
</html>
