<?php
// Include database connection and functions
require_once(__DIR__ . '/includes/database.php');
require_once(__DIR__ . '/includes/functions.php');

// Connect to SQLite database
try {
    $db = getDatabase();
    
    // Simple session management
    session_start();
    
    // Handle POST and actions
    $statusMessage = '';
    $errorMessage = '';
    
    // Handle post status update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_status') {
        if (isset($_SESSION['user_id'])) {
            $content = $_POST['content'] ?? '';
            
            if (!empty($content)) {
                // Insert into database
                $stmt = $db->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
                $stmt->execute([$_SESSION['user_id'], $content]);
                
                $statusMessage = "Status update posted successfully!";
                
                // Redirect to avoid form resubmission
                header("Location: " . $_SERVER['PHP_SELF'] . "?status=posted");
                exit;
            } else {
                $errorMessage = "Status content cannot be empty";
            }
        } else {
            $errorMessage = "You must be logged in to post";
        }
    }
    
    // Handle vote actions
    if (isset($_GET['action']) && $_GET['action'] === 'vote' && isset($_GET['post_id']) && isset($_GET['vote'])) {
        if (isset($_SESSION['user_id'])) {
            $postId = $_GET['post_id'];
            $voteType = ($_GET['vote'] == 1) ? 1 : -1;
            $userId = $_SESSION['user_id'];
            
            // Check if user already voted
            $stmt = $db->prepare("SELECT vote_type FROM votes WHERE user_id = ? AND post_id = ?");
            $stmt->execute([$userId, $postId]);
            $existingVote = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingVote) {
                if ($existingVote['vote_type'] === $voteType) {
                    // User is removing their vote
                    $stmt = $db->prepare("DELETE FROM votes WHERE user_id = ? AND post_id = ?");
                    $stmt->execute([$userId, $postId]);
                    
                    // Update post vote counts
                    if ($voteType === 1) {
                        $db->prepare("UPDATE posts SET upvotes = upvotes - 1 WHERE id = ?")->execute([$postId]);
                    } else {
                        $db->prepare("UPDATE posts SET downvotes = downvotes - 1 WHERE id = ?")->execute([$postId]);
                    }
                } else {
                    // User is changing their vote
                    $stmt = $db->prepare("UPDATE votes SET vote_type = ? WHERE user_id = ? AND post_id = ?");
                    $stmt->execute([$voteType, $userId, $postId]);
                    
                    // Update post vote counts
                    if ($voteType === 1) {
                        $db->prepare("UPDATE posts SET upvotes = upvotes + 1, downvotes = downvotes - 1 WHERE id = ?")->execute([$postId]);
                    } else {
                        $db->prepare("UPDATE posts SET upvotes = upvotes - 1, downvotes = downvotes + 1 WHERE id = ?")->execute([$postId]);
                    }
                }
            } else {
                // New vote
                $stmt = $db->prepare("INSERT INTO votes (user_id, post_id, vote_type) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $postId, $voteType]);
                
                // Update post vote counts
                if ($voteType === 1) {
                    $db->prepare("UPDATE posts SET upvotes = upvotes + 1 WHERE id = ?")->execute([$postId]);
                } else {
                    $db->prepare("UPDATE posts SET downvotes = downvotes + 1 WHERE id = ?")->execute([$postId]);
                }
            }
            
            // Redirect back to the page to avoid reloading affecting the vote
            header("Location: " . $_SERVER['HTTP_REFERER'] ?? $_SERVER['PHP_SELF']);
            exit;
        } else {
            // Redirect to login
            header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }
    
    // Get posts for the feed (main timeline)
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $postsPerPage = 10;
    $offset = ($page - 1) * $postsPerPage;
    
    // Get total number of posts
    $totalPostsStmt = $db->query("SELECT COUNT(*) as count FROM posts");
    $totalPosts = $totalPostsStmt->fetch(PDO::FETCH_ASSOC)['count'];
    $totalPages = ceil($totalPosts / $postsPerPage);
    
    // Get posts with user info
    $stmt = $db->prepare("
        SELECT 
            p.id, p.user_id, p.title, p.content, p.flair, p.image_url, p.video_url, 
            p.upvotes, p.downvotes, p.created_at,
            u.username, u.avatar
        FROM posts p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$postsPerPage, $offset]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user votes if logged in
    $userVotes = [];
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT post_id, vote_type FROM votes WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        while ($vote = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userVotes[$vote['post_id']] = $vote['vote_type'];
        }
    }
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP2∞ (IP2.Social) - Timeline</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Banner image behind header -->
        <div class="banner">
            <img src="assets/images/buzz_banner.png" alt="IP2∞ Banner" class="banner-image">
        </div>
        
        <!-- Subreddit-style header -->
        <header class="subreddit-header">
            <div class="subreddit-title">
                <h1>IP2∞ (IP2.Social) (IP2Infinity.Social)</h1>
            </div>
            
            <nav class="subreddit-nav">
                <ul class="nav-tabs">
                    <li class="nav-tab"><a href="#" class="active">Timeline</a></li>
                    <li class="nav-tab"><a href="#">Members</a></li>
                    <li class="nav-tab"><a href="#">Links</a></li>
                </ul>
                
                <div class="nav-actions">
                    <a href="#" class="favorite-button"><i class="far fa-star"></i></a>
                    <div class="more-options">
                        <button class="more-button"><i class="fas fa-ellipsis-h"></i></button>
                    </div>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="profile.php" class="member-button"><?php echo htmlspecialchars($_SESSION['username']); ?></a>
                    <?php else: ?>
                        <a href="login.php" class="member-button">Login</a>
                    <?php endif; ?>
                </div>
            </nav>
        </header>

        <div class="content-wrapper">
            <main class="main-content">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Status update form -->
                    <div class="status-form">
                        <form method="POST">
                            <input type="hidden" name="action" value="post_status">
                            <textarea name="content" class="status-textarea" placeholder="What's on your mind?"></textarea>
                            
                            <?php if ($errorMessage): ?>
                                <p style="color: var(--alert-color);"><?php echo htmlspecialchars($errorMessage); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($statusMessage): ?>
                                <p style="color: var(--post-title);"><?php echo htmlspecialchars($statusMessage); ?></p>
                            <?php endif; ?>
                            
                            <div class="status-footer">
                                <div class="status-options">
                                    <button type="button" class="status-option" id="add-image">
                                        <i class="far fa-image"></i> Image
                                    </button>
                                    <button type="button" class="status-option" id="add-video">
                                        <i class="fas fa-video"></i> Video
                                    </button>
                                    <button type="button" class="status-option" id="add-flair">
                                        <i class="fas fa-tag"></i> Flair
                                    </button>
                                </div>
                                
                                <button type="submit" class="status-button">Post</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Add post button -->
                <a href="create_post.php" class="add-post-button">
                    <i class="fas fa-plus"></i> Create New Post
                </a>
                
                <!-- Sorting options -->
                <div class="sort-options">
                    <a href="#" class="sort-option active">
                        <i class="fas fa-fire"></i> Hot
                    </a>
                    <a href="#" class="sort-option">
                        <i class="fas fa-certificate"></i> New
                    </a>
                    <a href="#" class="sort-option">
                        <i class="fas fa-trophy"></i> Top
                    </a>
                    <a href="#" class="sort-option">
                        <i class="fas fa-chart-line"></i> Rising
                    </a>
                </div>
                
                <!-- Posts -->
                <?php foreach ($posts as $post): ?>
                    <div class="post">
                        <div class="post-sidebar">
                            <div class="vote-buttons">
                                <button class="upvote <?php echo (isset($userVotes[$post['id']]) && $userVotes[$post['id']] === 1) ? 'active' : ''; ?>" 
                                        onclick="location.href='?action=vote&post_id=<?php echo $post['id']; ?>&vote=1'">
                                    <i class="fas fa-arrow-up"></i>
                                </button>
                                <span class="vote-count"><?php echo $post['upvotes'] - $post['downvotes']; ?></span>
                                <button class="downvote <?php echo (isset($userVotes[$post['id']]) && $userVotes[$post['id']] === -1) ? 'active' : ''; ?>"
                                        onclick="location.href='?action=vote&post_id=<?php echo $post['id']; ?>&vote=-1'">
                                    <i class="fas fa-arrow-down"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="post-content">
                            <div class="post-header">
                                <?php if (!empty($post['title'])): ?>
                                    <h2 class="post-title">
                                        <a href="post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                                    </h2>
                                <?php endif; ?>
                                
                                <div class="post-meta">
                                    <?php if (!empty($post['flair'])): ?>
                                        <span class="post-flair"><?php echo htmlspecialchars($post['flair']); ?></span>
                                    <?php endif; ?>
                                    
                                    <span class="post-author">
                                        <img src="assets/avatars/<?php echo htmlspecialchars($post['avatar']); ?>" alt="Avatar" width="20" height="20" style="border-radius: 50%; vertical-align: middle;">
                                        <a href="user.php?id=<?php echo $post['user_id']; ?>"><?php echo htmlspecialchars($post['username']); ?></a>
                                    </span>
                                    
                                    <span class="post-time">
                                        <?php echo formatRelativeTime($post['created_at']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="post-body">
                                <?php 
                                    // Process content to convert emote codes to images and handle basic formatting
                                    $processedContent = processEmotes(nl2br(htmlspecialchars($post['content'])));
                                    echo $processedContent;
                                ?>
                                
                                <?php if (!empty($post['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="Post image">
                                <?php endif; ?>
                                
                                <?php if (!empty($post['video_url'])): ?>
                                    <video controls>
                                        <source src="<?php echo htmlspecialchars($post['video_url']); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php endif; ?>
                            </div>
                            
                            <div class="post-actions">
                                <a href="post.php?id=<?php echo $post['id']; ?>" class="post-action">
                                    <i class="far fa-comment-alt"></i> Comments
                                </a>
                                <a href="#" class="post-action">
                                    <i class="fas fa-share"></i> Share
                                </a>
                                <a href="#" class="post-action">
                                    <i class="far fa-bookmark"></i> Save
                                </a>
                                <a href="#" class="post-action">
                                    <i class="fas fa-flag"></i> Report
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="pagination-link">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="pagination-link <?php echo ($i === $page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="pagination-link">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
            
            <aside class="sidebar">
                <!-- About section -->
                <div class="sidebar-section">
                    <div class="sidebar-header">About IP2∞</div>
                    <div class="sidebar-content">
                        <p>IP2∞ is a focused discussion forum for all topics related to Internet personalities, livestreamers, and content creators.</p>
                        
                        <ul class="info-list">
                            <li>
                                <i class="fas fa-birthday-cake"></i> Created Apr 2, 2025
                            </li>
                            <li>
                                <i class="fas fa-users"></i> 2 members
                            </li>
                            <li>
                                <i class="fas fa-chart-line"></i> 5 online now
                            </li>
                        </ul>
                        
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="register.php" class="sidebar-button">Join IP2∞</a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Moderators -->
                <div class="sidebar-section">
                    <div class="sidebar-header">Moderators</div>
                    <div class="sidebar-content">
                        <div class="moderator">
                            <img src="assets/avatars/avatar1.svg" class="moderator-avatar" alt="Moderator">
                            <a href="#" class="moderator-name">admin</a>
                        </div>
                        
                        <a href="#" class="sidebar-button">View All Moderators</a>
                    </div>
                </div>
                
                <!-- Live Streams -->
                <div class="sidebar-section">
                    <div class="sidebar-header">Live Streams</div>
                    <div class="sidebar-content">
                        <p>No one is streaming right now.</p>
                        <a href="live.php" class="sidebar-button">All Streams</a>
                    </div>
                </div>
            </aside>
        </div>
        
        <footer>
            <p>© <?php echo date('Y'); ?> IP2∞ (IP2.Social)</p>
        </footer>
    </div>
    
    <script src="js/main.js"></script>
</body>
</html>
