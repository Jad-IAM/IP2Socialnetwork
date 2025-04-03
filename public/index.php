<?php
// IP2 Micro Forum - Single Topic Discussion Board
$dbFile = __DIR__ . '/../storage/sqlite/forum.sqlite';
$dbDirectory = dirname($dbFile);

// Set forum title and description
$forumTitle = "IP2∞";
$forumSubtitle = "IP2Always";

// Make sure SQLite directory exists
if (!is_dir($dbDirectory)) {
    mkdir($dbDirectory, 0777, true);
}

// Connect to SQLite database
try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables if they don't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            email TEXT UNIQUE,
            avatar TEXT,
            is_mod INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            user_id INTEGER,
            image_url TEXT,
            video_url TEXT,
            clip_id TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        
        CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content TEXT NOT NULL,
            post_id INTEGER,
            user_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES posts(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        
        CREATE TABLE IF NOT EXISTS votes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER,
            user_id INTEGER,
            vote INTEGER NOT NULL, -- 1 for upvote, -1 for downvote
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES posts(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            UNIQUE(post_id, user_id)
        );

        CREATE TABLE IF NOT EXISTS tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS post_tags (
            post_id INTEGER,
            tag_id INTEGER,
            FOREIGN KEY (post_id) REFERENCES posts(id),
            FOREIGN KEY (tag_id) REFERENCES tags(id),
            PRIMARY KEY (post_id, tag_id)
        );
        
        CREATE TABLE IF NOT EXISTS emotes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            image_url TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    
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
        $stmt = $db->query("SELECT code, image_url FROM emotes ORDER BY code");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($forumTitle); ?></title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="banner">
                <picture>
                    <source srcset="assets/images/banner.svg" type="image/svg+xml">
                    <img src="assets/images/banner.png" alt="IP2 Network Banner" class="banner-image">
                </picture>
            </div>
            <div class="header-content">
                <div class="site-branding">
                    <h1 class="site-title"><?php echo htmlspecialchars($forumTitle); ?></h1>
                    <span class="site-subtitle"><?php echo htmlspecialchars($forumSubtitle); ?></span>
                </div>
                <div class="user-actions">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="#" class="star-button"><i class="fas fa-star"></i></a>
                        <div class="dropdown">
                            <button class="more-button"><i class="fas fa-ellipsis-h"></i></button>
                        </div>
                        <a href="#" class="member-button">Member</a>
                    <?php else: ?>
                        <a href="#" class="star-button"><i class="far fa-star"></i></a>
                        <div class="dropdown">
                            <button class="more-button"><i class="fas fa-ellipsis-h"></i></button>
                        </div>
                        <a href="index.php?page=login" class="login-button">Login</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <nav class="main-nav">
                <ul>
                    <li class="active"><a href="index.php">Timeline</a></li>
                    <li><a href="#">Members</a></li>
                    <li><a href="#">Links</a></li>
                </ul>
            </nav>
        </header>
        
        <main class="content">
            <div class="content-main">
                <!-- Post creation area -->
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="post-creation">
                    <div class="post-creation-header">
                        <img src="assets/avatars/<?php echo htmlspecialchars($_SESSION['avatar'] ?? 'default_avatar.svg'); ?>" class="user-avatar" alt="User Avatar">
                        <div class="post-type-selector">
                            <span>Post to <?php echo htmlspecialchars($forumTitle); ?> <i class="fas fa-caret-down"></i></span>
                        </div>
                        <div class="context-selector">
                            <span>Add context <i class="fas fa-caret-down"></i></span>
                        </div>
                    </div>
                    
                    <form method="POST" action="index.php" class="post-form">
                        <input type="hidden" name="action" value="create_post">
                        <textarea name="title" placeholder="What's on your mind?" required></textarea>
                        <input type="hidden" name="content" value="Post content here">
                        <input type="hidden" name="video_url" value="">
                        
                        <div class="post-actions">
                            <button type="button" class="media-btn" title="Add image"><i class="far fa-image"></i></button>
                            <button type="button" class="emoji-btn" title="Add emoji"><i class="far fa-smile"></i></button>
                            <button type="button" class="gif-btn" title="Add GIF"><i class="fas fa-gift"></i></button>
                            <button type="button" class="location-btn" title="Add location"><i class="fas fa-map-marker-alt"></i></button>
                            <button type="button" class="alert-btn" title="Add alert"><i class="fas fa-exclamation-triangle"></i></button>
                            <button type="button" class="event-btn" title="Add event"><i class="far fa-calendar-alt"></i></button>
                            <button type="button" class="format-btn" title="Format text"><i class="fas fa-text-height"></i></button>
                            <button type="submit" class="submit-btn">Post</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- Post sorting -->
                <div class="post-sorting">
                    <span>Sort by <strong>New Posts</strong> <i class="fas fa-caret-down"></i></span>
                </div>
                
                <!-- Posts list -->
                <div class="posts-list">
                    <?php
                    $stmt = $db->query("SELECT p.*, u.username FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");
                    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($posts as $post):
                        $vote_count = getVoteCount($db, $post['id']);
                        $comment_count = getCommentCount($db, $post['id']);
                        $user_vote = isset($_SESSION['user_id']) ? getUserVote($db, $post['id'], $_SESSION['user_id']) : 0;
                        $tags = getPostTags($db, $post['id']);
                    ?>
                    <div class="post">
                        <div class="vote-sidebar">
                            <div class="vote-count"><?php echo $vote_count; ?></div>
                            <a href="#" class="upvote-btn <?php echo $user_vote == 1 ? 'voted' : ''; ?>">
                                <i class="fas fa-arrow-up"></i>
                            </a>
                        </div>
                        <div class="post-content">
                            <div class="post-meta">
                                <?php if (!empty($post['clip_id'])): ?>
                                <span class="post-type">
                                    <i class="fas fa-play-circle"></i>
                                </span>
                                <?php endif; ?>
                                <span class="post-author">posted <?php echo timeAgo($post['created_at']); ?> ago by <a href="#"><?php echo htmlspecialchars($post['username']); ?></a></span>
                                <?php if (in_array('CLIP', $tags)): ?>
                                <span class="clip-tag">CLIP <i class="fas fa-play"></i></span>
                                <?php endif; ?>
                                <?php if (in_array('Quality', $tags)): ?>
                                <span class="quality-tag">QUALITY <i class="fas fa-fire"></i></span>
                                <?php endif; ?>
                            </div>
                            
                            <h2 class="post-title"><a href="#"><?php echo htmlspecialchars($post['title']); ?></a></h2>
                            
                            <div class="post-body">
                                <div class="post-text"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                                
                                <?php if (!empty($post['video_url'])): ?>
                                <div class="video-container">
                                    <div class="video-placeholder">
                                        <div class="play-button">
                                            <i class="fas fa-play"></i>
                                        </div>
                                        <div class="video-controls">
                                            <span class="video-time">0:00</span>
                                            <div class="video-progress">
                                                <div class="progress-bar"></div>
                                            </div>
                                            <div class="video-buttons">
                                                <button class="video-mute"><i class="fas fa-volume-mute"></i></button>
                                                <button class="video-fullscreen"><i class="fas fa-expand"></i></button>
                                                <button class="video-more"><i class="fas fa-ellipsis-v"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="post-actions">
                                <span class="comment-count"><?php echo $comment_count; ?> comments</span>
                                <button class="action-btn award-btn">award</button>
                                <button class="action-btn share-btn">share</button>
                                <button class="action-btn crosspost-btn">crosspost</button>
                                <button class="action-btn save-btn">save</button>
                                <button class="action-btn report-btn">report</button>
                                <button class="action-btn block-btn">block</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <aside class="sidebar">
                <!-- Buttons Section -->
                <div class="sidebar-buttons">
                    <div class="button-row">
                        <a href="#" class="sidebar-button live-button">LIVE</a>
                        <a href="#" class="sidebar-button clips-button">CLIPS</a>
                    </div>
                    <div class="button-row">
                        <a href="#" class="sidebar-button leaderboard-button">LEADERBOARD</a>
                        <a href="#" class="sidebar-button clipmaker-button">CLIP MAKER</a>
                    </div>
                    <div class="button-row">
                        <a href="#" class="sidebar-button videohosts-button">VIDEO HOSTS</a>
                        <a href="#" class="sidebar-button ocdfixer-button">OCD FIXER</a>
                    </div>
                </div>
                
                <!-- About Box -->
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
                
                <!-- Rules Box -->
                <div class="rules-box">
                    <h2>Rules</h2>
                    <ol class="rules-list">
                        <li>Don't be a normie.</li>
                    </ol>
                </div>
                
                <!-- Emotes Box -->
                <div class="emotes-box">
                    <h2>Emotes</h2>
                    <div class="emotes-description">
                        Emote | Code <code>//#emote:...</code>
                    </div>
                    
                    <div class="emotes-list">
                        <?php
                        $emotes = getAllEmotes($db);
                        foreach ($emotes as $emote):
                        ?>
                        <div class="emote-item">
                            <div class="emote-icon">•</div>
                            <div class="emote-code"><?php echo htmlspecialchars($emote['code']); ?></div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="more-emotes">
                            <a href="#">More Emotes</a>
                        </div>
                    </div>
                    
                    <div class="gif-emotes">
                        <a href="#">Gif Emotes</a>
                    </div>
                </div>
            </aside>
        </main>
        
        <footer>
            <div class="back-to-top">
                <a href="#top">Back to Top</a>
            </div>
        </footer>
    </div>
    
    <?php if (isset($_GET['page']) && $_GET['page'] === 'login'): ?>
    <div class="modal login-modal">
        <div class="modal-content">
            <h2>Login</h2>
            <?php if (isset($loginError)): ?>
                <div class="error"><?php echo $loginError; ?></div>
            <?php endif; ?>
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="login-submit">Login</button>
            </form>
            <p>
                <small>Demo accounts: admin/admin123 or 404JesterNotFound/user123</small>
            </p>
            <a href="index.php" class="close-modal">Cancel</a>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
