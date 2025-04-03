<?php
// IP2 Micro Forum - Single Topic Discussion Board
require_once(__DIR__ . "/includes/functions.php");
require_once(__DIR__ . "/includes/database.php");

// Set forum title and description
$forumTitle = "IP2∞";
$forumSubtitle = "(IP2Infinity.network)";

// Connect to SQLite database
try {
    $db = getDatabase();
    
    // Initialize database schema
    initializeDatabase($db);
    
    // Seed some initial data if tables are empty
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        // Insert users
        $db->exec("INSERT INTO users (username, password, avatar, email, is_mod) 
                  VALUES ('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'avatar1.svg', 'admin@example.com', 1)");
        $db->exec("INSERT INTO users (username, password, avatar, email) 
                  VALUES ('404JesterNotFound', '" . password_hash('user123', PASSWORD_DEFAULT) . "', 'avatar2.svg', 'user1@example.com')");
        $db->exec("INSERT INTO users (username, password, avatar, email) 
                  VALUES ('Teh_Pwner', '" . password_hash('user123', PASSWORD_DEFAULT) . "', 'avatar3.svg', 'teh@example.com')");
        $db->exec("INSERT INTO users (username, password, avatar, email) 
                  VALUES ('STAX', '" . password_hash('user123', PASSWORD_DEFAULT) . "', 'avatar4.svg', 'stax@example.com')");
        $db->exec("INSERT INTO users (username, password, avatar, email) 
                  VALUES ('IP2mod', '" . password_hash('user123', PASSWORD_DEFAULT) . "', 'avatar5.svg', 'mod@example.com')");
        
        // Insert tags
        $db->exec("INSERT INTO tags (name) VALUES ('Breaking News')");
        $db->exec("INSERT INTO tags (name) VALUES ('IP2∞')");
        $db->exec("INSERT INTO tags (name) VALUES ('Edited')");
        $db->exec("INSERT INTO tags (name) VALUES ('CLIP')");
        $db->exec("INSERT INTO tags (name) VALUES ('Quality')");
        
        // Insert emotes
        $db->exec("INSERT INTO emotes (code, image_url) VALUES ('soychamp', 'emotes/soychamp.png')");
        $db->exec("INSERT INTO emotes (code, image_url) VALUES ('mushroom', 'emotes/mushroom.png')");
        $db->exec("INSERT INTO emotes (code, image_url) VALUES ('uncleben', 'emotes/uncleben.png')");
        $db->exec("INSERT INTO emotes (code, image_url) VALUES ('panzer', 'emotes/panzer.png')");
        $db->exec("INSERT INTO emotes (code, image_url) VALUES ('retard', 'emotes/retard.png')");
        $db->exec("INSERT INTO emotes (code, image_url) VALUES ('lfd_stool', 'emotes/lfd_stool.png')");
        $db->exec("INSERT INTO emotes (code, image_url) VALUES ('keem', 'emotes/keem.png')");
        
        // Insert some posts
        $db->exec("INSERT INTO posts (title, content, user_id, video_url, clip_id) 
            VALUES ('Video Host Alternatives', 
            'I found a couple new hosts, but they were either only 24hr or slow as fuck, the only new one I found that look even slightly decent was:\nhttps://qu.ax/\n\nIf anyone has any other video hosts they use, feel free to post them in the comments.\n\nEdit: Make sure you change the settings if using this new host https://pomf2.lain.la/f/o90maos.JPG', 
            3, 'https://example.com/video1.mp4', '12345')");
        
        // Add tags to post 1
        $db->exec("INSERT INTO post_tags (post_id, tag_id) VALUES (1, 1)"); // Breaking News
        
        // Add votes to post 1
        $db->exec("INSERT INTO votes (post_id, user_id, vote) VALUES (1, 2, 1)");
        $db->exec("INSERT INTO votes (post_id, user_id, vote) VALUES (1, 3, 1)");
        $db->exec("INSERT INTO votes (post_id, user_id, vote) VALUES (1, 4, 1)");
        
        // Add comments to post 1
        $db->exec("INSERT INTO comments (content, post_id, user_id) VALUES ('Thanks for sharing these alternatives!', 1, 2)");
        $db->exec("INSERT INTO comments (content, post_id, user_id) VALUES ('The qu.ax one works great for me', 1, 4)");
        
        // Insert post 2
        $db->exec("INSERT INTO posts (title, content, user_id, video_url, clip_id) 
            VALUES ('Johnny Somali punched in the head by angry Korean, calls him a \"filthy n*gger\"', 
            'This clip shows Johnny Somali getting punched after harassing locals.', 
            4, 'https://example.com/video2.mp4', '67890')");
        
        // Add tags to post 2
        $db->exec("INSERT INTO post_tags (post_id, tag_id) VALUES (2, 2)"); // IP2∞
        $db->exec("INSERT INTO post_tags (post_id, tag_id) VALUES (2, 4)"); // CLIP
        
        // Add votes to post 2
        $db->exec("INSERT INTO votes (post_id, user_id, vote) VALUES (2, 1, 1)");
        $db->exec("INSERT INTO votes (post_id, user_id, vote) VALUES (2, 2, 1)");
        $db->exec("INSERT INTO votes (post_id, user_id, vote) VALUES (2, 3, 1)");
        $db->exec("INSERT INTO votes (post_id, user_id, vote) VALUES (2, 5, 1)");
        
        // Insert post 3
        $db->exec("INSERT INTO posts (title, content, user_id, video_url, clip_id) 
            VALUES ('raid team six', 
            'The raid team is at it again with more content.', 
            5, 'https://example.com/video3.mp4', '13579')");
        
        // Add tags to post 3
        $db->exec("INSERT INTO post_tags (post_id, tag_id) VALUES (3, 4)"); // CLIP
        $db->exec("INSERT INTO post_tags (post_id, tag_id) VALUES (3, 5)"); // Quality
        
        // Add votes to post 3
        $db->exec("INSERT INTO votes (post_id, user_id, vote) VALUES (3, 1, 1)");
        $db->exec("INSERT INTO votes (post_id, user_id, vote) VALUES (3, 2, 1)");
        $db->exec("INSERT INTO votes (post_id, user_id, vote) VALUES (3, 4, 1)");
    }
    
    // Simple session management
    session_start();
    
    // Include status updates functionality
    require_once(__DIR__ . "/includes/status_updates.php");

    // Handle login
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $stmt = $db->prepare("SELECT id, username, password, avatar, is_mod FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['avatar'] = $user['avatar'];
            $_SESSION['is_mod'] = $user['is_mod'];
            header('Location: index.php');
            exit;
        } else {
            $loginError = "Invalid username or password";
        }
    }
    
    // Handle logout
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        session_unset();
        session_destroy();
        header('Location: index.php');
        exit;
    }
    
    // Handle new post creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_post') {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php');
            exit;
        }
        
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $video_url = $_POST['video_url'] ?? '';
        
        if ($title && $content) {
            $stmt = $db->prepare("INSERT INTO posts (title, content, user_id, video_url) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $content, $_SESSION['user_id'], $video_url]);
            
            // Add tags if provided
            $lastPostId = $db->lastInsertId();
            if (isset($_POST['tags']) && !empty($_POST['tags'])) {
                $tags = explode(',', $_POST['tags']);
                foreach ($tags as $tag) {
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
                        $stmt->execute([$lastPostId, $tagId]);
                    }
                }
            }
            
            header('Location: index.php');
            exit;
        }
    }
    
    // Handle status update creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_status') {
        if (!isset($_SESSION['user_id'])) {
            header('Location: login.php');
            exit;
        }
        
        $status_content = trim($_POST['status_content'] ?? '');
        
        if (!empty($status_content)) {
            $stmt = $db->prepare("INSERT INTO status_updates (content, user_id) VALUES (?, ?)");
            $stmt->execute([$status_content, $_SESSION['user_id']]);
            
            header('Location: index.php');
            exit;
        }
    }


    // Handle comment creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php');
            exit;
        }
        
        $post_id = $_POST['post_id'] ?? '';
        $content = $_POST['content'] ?? '';
        
        if ($post_id && $content) {
            $stmt = $db->prepare("INSERT INTO comments (content, post_id, user_id) VALUES (?, ?, ?)");
            $stmt->execute([$content, $post_id, $_SESSION['user_id']]);
            
            header('Location: index.php');
            exit;
        }
    }
    
    // Handle voting
    if (isset($_GET['action']) && $_GET['action'] === 'vote' && isset($_SESSION['user_id'])) {
        $post_id = $_GET['post_id'] ?? '';
        $vote = $_GET['vote'] ?? '';
        
        if ($post_id && ($vote == 1 || $vote == -1)) {
            // Check if user already voted on this post
            $stmt = $db->prepare("SELECT id, vote FROM votes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$post_id, $_SESSION['user_id']]);
            $existing_vote = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_vote) {
                // Update existing vote if different
                if ($existing_vote['vote'] != $vote) {
                    $stmt = $db->prepare("UPDATE votes SET vote = ? WHERE id = ?");
                    $stmt->execute([$vote, $existing_vote['id']]);
                }
            } else {
                // Create new vote
                $stmt = $db->prepare("INSERT INTO votes (post_id, user_id, vote) VALUES (?, ?, ?)");
                $stmt->execute([$post_id, $_SESSION['user_id'], $vote]);
            }
            
            // Redirect back to the post or home page
            if (isset($_GET['redirect'])) {
                header('Location: ' . $_GET['redirect']);
            } else {
                header('Location: index.php');
            }
            exit;
        }
    }
    
    // Helper function to get vote count
    function getVoteCount($db, $post_id) {
        $stmt = $db->prepare("SELECT SUM(vote) as vote_count FROM votes WHERE post_id = ?");
        $stmt->execute([$post_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['vote_count'] ?: 0;
    }
    
    // Helper function to get comment count
    function getCommentCount($db, $post_id) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ?");
        $stmt->execute([$post_id]);
        return $stmt->fetchColumn();
    }
    
    // Helper function to get user's vote on a post
    function getUserVote($db, $post_id, $user_id) {
        if (!$user_id) return 0;
        
        $stmt = $db->prepare("SELECT vote FROM votes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$post_id, $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['vote'] : 0;
    }
    
    // Helper function to get username by ID
    function getUsername($db, $user_id) {
        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['username'] : 'Unknown';
    }
    
    // Helper function to get user avatar
    function getUserAvatar($db, $user_id) {
        $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['avatar'] ? $result['avatar'] : 'default_avatar.svg';
    }
    
    // Helper function to get tags for a post
    function getPostTags($db, $post_id) {
        $stmt = $db->prepare("
            SELECT t.name 
            FROM tags t 
            JOIN post_tags pt ON t.id = pt.tag_id 
            WHERE pt.post_id = ?
        ");
        $stmt->execute([$post_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Format time difference
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

    // Get all emotes for the sidebar
    function getAllEmotes($db) {
        $stmt = $db->query("SELECT code, image_url FROM emotes ORDER BY code ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Handle sorting
    $sort = isset($_GET["sort"]) ? $_GET["sort"] : "new_posts";
    
    $sortSql = "";
    switch ($sort) {
        case "top_posts":
            $sortSql = "ORDER BY vote_count DESC, p.created_at DESC";
            break;
        case "hot_posts":
            $sortSql = "ORDER BY (vote_count * 5 + comment_count * 3) DESC, p.created_at DESC";
            break;
        case "new_posts":
        default:
            $sortSql = "$sortSql";
            break;
    }
    // Get posts
    $page = isset($_GET['page']) ? $_GET['page'] : 'home';
    
    if ($page === 'home') {
        $stmt = $db->query("
            SELECT p.*, u.username, u.avatar, (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count, (SELECT SUM(vote) FROM votes WHERE post_id = p.id) as vote_count 
            FROM posts p 
            JOIN users u ON p.user_id = u.id 
            $sortSql
        ");
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title><?php echo htmlspecialchars($forumTitle); ?> - Single Topic Discussion Board</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="js/main.js"></script>
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
                <h1><?php echo htmlspecialchars($forumTitle); ?> <?php echo htmlspecialchars($forumSubtitle); ?></h1>
            </div>
            
            <nav class="subreddit-nav">
                <ul class="nav-tabs">
                    <li class="nav-tab active"><a href="#">Timeline</a></li>
                    <li class="nav-tab"><a href="#">Members</a></li>
                    <li class="nav-tab"><a href="#">Links</a></li>
                    <li class="nav-tab"><a href="emotes.php">Emotes</a></li>
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
                <!-- Create post area -->
                <div class="create-post">
                    <div class="user-avatar">
                <div class="create-post-card">
                    <div class="post-user">
                        <img src="assets/avatars/<?php echo isset($_SESSION["avatar"]) ? htmlspecialchars($_SESSION["avatar"]) : "default_avatar.svg"; ?>" alt="User Avatar" class="user-avatar">
                    </div>
                    <div class="post-input">
                        <form method="post" action="index.php" id="status-form">
                            <input type="hidden" name="action" value="create_status">
                            <input type="text" name="status_content" placeholder="Update your status..." class="status-textbox">
                            <div class="post-actions">
                                <button type="submit" class="post-action-btn">Update Status</button>
                                <a href="create_post.php" class="post-action-btn create-post-btn">Create Post</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Post toolbar -->
                <div class="post-toolbar">
                    <div class="post-tools">
                        <a href="create_post.php" class="post-tool">
                            <i class="fas fa-pencil-alt"></i> New Post
                        </a>
                        <a href="create_post.php" class="post-tool flair-tool" id="flair-dropdown-toggle">
                            <i class="fas fa-tag"></i> Pick a Flair
                        </a>
                        <div class="flair-dropdown" id="flair-dropdown">
                            <div class="flair-dropdown-content">
                                <!-- Flairs will be loaded via JavaScript -->
                            </div>
                        </div>
                        <a href="#" class="post-tool"><i class="fas fa-image"></i></a>
                        <a href="#" class="post-tool"><i class="far fa-smile"></i></a>
                        <a href="#" class="post-tool"><i class="fas fa-gift"></i></a>
                        <a href="#" class="post-tool"><i class="fas fa-link"></i></a>
                        <a href="#" class="post-tool"><i class="fas fa-exclamation-triangle"></i></a>
                        <a href="#" class="post-tool"><i class="far fa-calendar"></i></a>
                        <a href="#" class="post-tool"><i class="fas fa-font"></i></a>
                    </div>
                </div>

                <!-- Sort options -->
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        const postTextbox = document.querySelector(".status-textbox");
                        const postTools = document.querySelectorAll(".post-tool");
                        
                        if (postTextbox) {
                            postTextbox.addEventListener("click", function() {
                                window.location.href = "create_post.php";
                            });
                        }
                        
                        postTools.forEach(tool => {
                            tool.addEventListener("click", function(e) {
                                e.preventDefault();
                                window.location.href = "create_post.php";
                            });
                        });
                        
                        // Sort functionality
                        const sortSelect = document.querySelector(".sort-select");
                        if (sortSelect) {
                            sortSelect.addEventListener("change", function() {
                                const sortValue = this.value;
                                window.location.href = `index.php?sort=${sortValue.toLowerCase().replace(" ", "_")}`;
                            });
                        }
                    });
                </script>
                <div class="sort-options">
                    <span>Sort by</span>
                    <select class="sort-select">
                        <?php
                            $sortOptions = [
                                "new_posts" => "New Posts",
                                "top_posts" => "Top Posts",
                                "hot_posts" => "Hot Posts"
                            ];
                            
                            foreach ($sortOptions as $value => $label): 
                        ?>
                            <option <?php echo $sort === $value ? "selected" : ""; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Posts list -->
                <div class="posts-list">
                    <?php if (isset($posts) && !empty($posts)): ?>
                        <?php foreach ($posts as $post): ?>
                            <?php 
                                $voteCount = getVoteCount($db, $post['id']);
                                $commentCount = getCommentCount($db, $post['id']);
                                $userVote = isset($_SESSION['user_id']) ? getUserVote($db, $post['id'], $_SESSION['user_id']) : 0;
                                $tags = getPostTags($db, $post['id']);
                            ?>
                            <div class="post">
                                <!-- Left vote sidebar -->
                                <div class="vote-sidebar">
                                    <a href="<?php echo isset($_SESSION['user_id']) ? "index.php?action=vote&post_id={$post['id']}&vote=1" : "#"; ?>" class="vote upvote <?php echo $userVote == 1 ? 'voted' : ''; ?>">
                                        <i class="fas fa-arrow-up"></i>
                                    </a>
                                    <span class="vote-count"><?php echo $voteCount; ?></span>
                                    <a href="<?php echo isset($_SESSION['user_id']) ? "index.php?action=vote&post_id={$post['id']}&vote=-1" : "#"; ?>" class="vote downvote <?php echo $userVote == -1 ? 'voted' : ''; ?>">
                                        <i class="fas fa-arrow-down"></i>
                                    </a>
                                </div>
                                
                                <!-- Post content -->
                                <div class="post-content">
                                    <div class="post-meta">
                                        <img src="assets/avatars/<?php echo $post['avatar'] ?: 'default_avatar.svg'; ?>" alt="User avatar" class="post-avatar">
                                        <div class="post-info">
                                            <div class="post-user">
                                                <a href="#" class="username"><?php echo htmlspecialchars($post['username']); ?></a>
                                                <span class="post-time"><?php echo timeAgo($post['created_at']); ?></span>
                                                <?php if (!empty($tags)): ?>
                                                    <div class="post-tags">
                                                        <?php foreach ($tags as $tag): ?>
                                                            <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="post-actions">
                                            <button class="more-button"><i class="fas fa-ellipsis-h"></i></button>
                                        </div>
                                    </div>
                                    
                                    <h2 class="post-title">
                                        <a href="index.php?page=post&id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                                    </h2>
                                    
                                    <div class="post-text">
                                        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                                    </div>
                                    
                                    <?php if ($post['video_url']): ?>
                                        <div class="post-media">
                                            <div class="video-container">
                                                <video controls>
                                                    <source src="<?php echo htmlspecialchars($post['video_url']); ?>" type="video/mp4">
                                                    Your browser does not support the video tag.
                                                </video>
                                                <div class="play-button">
                                                    <i class="fas fa-play"></i>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="post-footer">
                                        <a href="index.php?page=post&id=<?php echo $post['id']; ?>" class="post-action">
                                            <i class="far fa-comment-alt"></i>
                                            <span class="action-text"><?php echo $commentCount; ?> Comments</span>
                                        </a>
                                        <a href="#" class="post-action">
                                            <i class="fas fa-share"></i>
                                            <span class="action-text">Share</span>
                                        </a>
                                        <a href="#" class="post-action">
                                            <i class="far fa-bookmark"></i>
                                            <span class="action-text">Save</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-posts">No posts yet. Be the first to post!</div>
                    <?php endif; ?>
                </div>
            </main>

            <aside class="sidebar">
                <!-- Sidebar buttons -->
                <div class="sidebar-buttons">
                    <div class="button-row">
                        <a href="#" class="sidebar-button live-button"><span class="live-icon"></span>LIVE</a>
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
                    <p class="about-text">We've rebranded to Internet-Platform 2 Infinity (IP2∞), marking our new direction. After being banned from multiple platforms (Reddit, Saidit), and with IP2Always.win now a shell of its former self, we're here to rebuild and recapture what made us great.</p>
                    
                    <h4 class="section-title">Our Goals</h4>
                    <ul class="goals-list">
                        <li><span class="checkmark">✅</span> Bring back the witty, high-effort trolling that made this community legendary</li>
                        <li><span class="checkmark">✅</span> Create a space for real discussions, not just mindless spam</li>
                        <li><span class="checkmark">✅</span> Content is King. Community-voted streamer lists and content curation</li>
                    </ul>
                    
                    <h4 class="section-title">Rules</h4>
                    <ol class="rules-list">
                        <li>No Doxxing</li>
                        <li>No brigading other communities</li>
                        <li>No spamming</li>
                    </ol>
                    
                    <div class="notes-section">
                        <h4 class="section-title">Notes</h4>
                        <ul class="notes-list">
                            <li>Shitposting is fine, but make it worth reading</li>
                            <li>🗣 Freedom of speech is respected—but don't be an uneducated, brain-dead fool</li>
                        </ul>
                    </div>
                    
                    <h4 class="section-title">Moderators</h4>
                    <ul class="mod-list">
                        <li><strong>404JesterNotFound</strong> (Mastermind of the newly developed community)</li>
                        <li><strong>RubyOnRails</strong> (Moderator)</li>
                    </ul>
                </div>
                
                <!-- Emotes Box -->
                <div class="emotes-box">
                    <h2>Emotes</h2>
                    <div class="emotes-list">
                        <?php 
                        $emotes = getAllEmotes($db);
                        foreach ($emotes as $emote): 
                        ?>
                            <div class="emote">
                                <img src="<?php echo htmlspecialchars($emote['image_url']); ?>" alt="<?php echo htmlspecialchars($emote['code']); ?>">
                                <span class="emote-code"><?php echo htmlspecialchars($emote['code']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>
        </div>
        
        <footer>
            <p>© <?php echo date('Y'); ?> IP2∞Social.network</p>
        </footer>
    </div>
</body>
</html>
