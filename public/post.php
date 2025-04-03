<?php
// Include database connection
require_once(__DIR__ . '/includes/database.php');
require_once(__DIR__ . '/includes/functions.php');

// Connect to SQLite database
try {
    $db = getDatabase();
    
    // Simple session management
    session_start();
    
    // Get post ID from URL
    $postId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($postId <= 0) {
        header('Location: index.php');
        exit;
    }
    
    // Get post with user info
    $stmt = $db->prepare("
        SELECT 
            p.id, p.user_id, p.title, p.content, p.flair, p.image_url, p.video_url, 
            p.upvotes, p.downvotes, p.created_at,
            u.username, u.avatar
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        header('Location: index.php');
        exit;
    }
    
    // Handle comment submission
    $commentError = '';
    $commentSuccess = false;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
        if (!isset($_SESSION['user_id'])) {
            header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
        
        $content = $_POST['content'] ?? '';
        $parentId = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        
        if (empty($content)) {
            $commentError = "Comment cannot be empty";
        } else {
            // Insert comment
            $stmt = $db->prepare("
                INSERT INTO comments (post_id, user_id, content, parent_id) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$postId, $_SESSION['user_id'], $content, $parentId]);
            
            $commentSuccess = true;
            
            // Clear form and redirect to prevent resubmission
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
    
    // Handle vote actions
    if (isset($_GET['action']) && $_GET['action'] === 'vote' && isset($_GET['comment_id']) && isset($_GET['vote'])) {
        if (isset($_SESSION['user_id'])) {
            $commentId = $_GET['comment_id'];
            $voteType = ($_GET['vote'] == 1) ? 1 : -1;
            $userId = $_SESSION['user_id'];
            
            // Check if user already voted
            $stmt = $db->prepare("SELECT vote_type FROM votes WHERE user_id = ? AND comment_id = ?");
            $stmt->execute([$userId, $commentId]);
            $existingVote = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingVote) {
                if ($existingVote['vote_type'] === $voteType) {
                    // User is removing their vote
                    $stmt = $db->prepare("DELETE FROM votes WHERE user_id = ? AND comment_id = ?");
                    $stmt->execute([$userId, $commentId]);
                    
                    // Update comment vote counts
                    if ($voteType === 1) {
                        $db->prepare("UPDATE comments SET upvotes = upvotes - 1 WHERE id = ?")->execute([$commentId]);
                    } else {
                        $db->prepare("UPDATE comments SET downvotes = downvotes - 1 WHERE id = ?")->execute([$commentId]);
                    }
                } else {
                    // User is changing their vote
                    $stmt = $db->prepare("UPDATE votes SET vote_type = ? WHERE user_id = ? AND comment_id = ?");
                    $stmt->execute([$voteType, $userId, $commentId]);
                    
                    // Update comment vote counts
                    if ($voteType === 1) {
                        $db->prepare("UPDATE comments SET upvotes = upvotes + 1, downvotes = downvotes - 1 WHERE id = ?")->execute([$commentId]);
                    } else {
                        $db->prepare("UPDATE comments SET upvotes = upvotes - 1, downvotes = downvotes + 1 WHERE id = ?")->execute([$commentId]);
                    }
                }
            } else {
                // New vote
                $stmt = $db->prepare("INSERT INTO votes (user_id, comment_id, vote_type) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $commentId, $voteType]);
                
                // Update comment vote counts
                if ($voteType === 1) {
                    $db->prepare("UPDATE comments SET upvotes = upvotes + 1 WHERE id = ?")->execute([$commentId]);
                } else {
                    $db->prepare("UPDATE comments SET downvotes = downvotes + 1 WHERE id = ?")->execute([$commentId]);
                }
            }
            
            // Redirect back to the page to avoid reloading affecting the vote
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            // Redirect to login
            header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }
    
    // Get all comments for this post
    $stmt = $db->prepare("
        SELECT 
            c.id, c.user_id, c.content, c.parent_id, c.upvotes, c.downvotes, c.created_at,
            u.username, u.avatar
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.post_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$postId]);
    $commentsFlat = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize comments into a tree structure
    $comments = [];
    $commentMap = [];
    
    // First pass: create a map of all comments
    foreach ($commentsFlat as $comment) {
        $commentMap[$comment['id']] = $comment;
        $commentMap[$comment['id']]['replies'] = [];
    }
    
    // Second pass: build the tree structure
    foreach ($commentsFlat as $comment) {
        if ($comment['parent_id'] === null) {
            // This is a top-level comment
            $comments[] = &$commentMap[$comment['id']];
        } else {
            // This is a reply
            $commentMap[$comment['parent_id']]['replies'][] = &$commentMap[$comment['id']];
        }
    }
    
    // Get user votes if logged in
    $userVotes = [];
    if (isset($_SESSION['user_id'])) {
        // Get post vote
        $stmt = $db->prepare("SELECT vote_type FROM votes WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$_SESSION['user_id'], $postId]);
        $postVote = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($postVote) {
            $userVotes['post_' . $postId] = $postVote['vote_type'];
        }
        
        // Get comment votes
        $stmt = $db->prepare("SELECT comment_id, vote_type FROM votes WHERE user_id = ? AND comment_id IS NOT NULL");
        $stmt->execute([$_SESSION['user_id']]);
        while ($vote = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userVotes['comment_' . $vote['comment_id']] = $vote['vote_type'];
        }
    }
    
    // Function to render a comment and its replies recursively
    function renderComment($comment, $userVotes, $level = 0) {
        $id = $comment['id'];
        $isUpvoted = isset($userVotes['comment_' . $id]) && $userVotes['comment_' . $id] === 1;
        $isDownvoted = isset($userVotes['comment_' . $id]) && $userVotes['comment_' . $id] === -1;
        $indentStyle = $level > 0 ? 'margin-left: ' . ($level * 20) . 'px;' : '';
        
        echo '<div class="comment" style="' . $indentStyle . '" id="comment-' . $id . '">';
        echo '<div class="comment-sidebar">';
        echo '<div class="vote-buttons">';
        echo '<button class="upvote ' . ($isUpvoted ? 'active' : '') . '" ';
        echo 'onclick="location.href=\'?id=' . $comment['id'] . '&action=vote&comment_id=' . $id . '&vote=1#comment-' . $id . '\'">';
        echo '<i class="fas fa-arrow-up"></i></button>';
        echo '<span class="vote-count">' . ($comment['upvotes'] - $comment['downvotes']) . '</span>';
        echo '<button class="downvote ' . ($isDownvoted ? 'active' : '') . '" ';
        echo 'onclick="location.href=\'?id=' . $comment['id'] . '&action=vote&comment_id=' . $id . '&vote=-1#comment-' . $id . '\'">';
        echo '<i class="fas fa-arrow-down"></i></button>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="comment-content">';
        echo '<div class="comment-header">';
        echo '<div class="comment-meta">';
        echo '<span class="comment-author">';
        echo '<img src="assets/avatars/' . htmlspecialchars($comment['avatar']) . '" alt="Avatar" width="20" height="20" ';
        echo 'style="border-radius: 50%; vertical-align: middle;">';
        echo '<a href="user.php?id=' . $comment['user_id'] . '">' . htmlspecialchars($comment['username']) . '</a>';
        echo '</span>';
        echo '<span class="comment-time">' . formatRelativeTime($comment['created_at']) . '</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="comment-body">';
        // Process content to convert emote codes to images and handle basic formatting
        $processedContent = processEmotes(nl2br(htmlspecialchars($comment['content'])));
        echo $processedContent;
        echo '</div>';
        
        echo '<div class="comment-actions">';
        echo '<button class="reply-button" data-comment-id="' . $id . '">Reply</button>';
        echo '</div>';
        
        // Reply form (hidden by default)
        echo '<div class="reply-form" id="reply-form-' . $id . '" style="display: none;">';
        echo '<form method="POST">';
        echo '<input type="hidden" name="action" value="add_comment">';
        echo '<input type="hidden" name="parent_id" value="' . $id . '">';
        echo '<textarea name="content" class="reply-textarea" placeholder="Write your reply..."></textarea>';
        echo '<div style="text-align: right; margin-top: 10px;">';
        echo '<button type="button" class="cancel-reply" data-comment-id="' . $id . '">Cancel</button>';
        echo '<button type="submit" class="submit-reply">Reply</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        
        // Render replies
        if (!empty($comment['replies'])) {
            echo '<div class="comment-replies">';
            foreach ($comment['replies'] as $reply) {
                renderComment($reply, $userVotes, $level + 1);
            }
            echo '</div>';
        }
        
        echo '</div>'; // End comment-content
        echo '</div>'; // End comment
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
    <title><?php echo htmlspecialchars($post['title']); ?> - IP2∞</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Comments specific styles */
        .comment {
            display: flex;
            margin-bottom: 15px;
            padding: 10px;
            background-color: var(--post-bg);
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }
        
        .comment-sidebar {
            padding-right: 10px;
            min-width: 40px;
        }
        
        .comment-content {
            flex-grow: 1;
        }
        
        .comment-header {
            margin-bottom: 5px;
        }
        
        .comment-meta {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .comment-author {
            margin-right: 10px;
            font-weight: bold;
        }
        
        .comment-author a {
            color: var(--accent-secondary);
            text-decoration: none;
        }
        
        .comment-body {
            font-size: 14px;
            line-height: 1.5;
        }
        
        .comment-actions {
            margin-top: 8px;
        }
        
        .reply-button {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 12px;
            cursor: pointer;
            padding: 2px 5px;
        }
        
        .reply-button:hover {
            color: var(--accent-secondary);
        }
        
        .reply-textarea {
            width: 100%;
            padding: 10px;
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 4px;
            resize: vertical;
            min-height: 80px;
            font-family: Arial, sans-serif;
        }
        
        .reply-form {
            margin-top: 10px;
            margin-bottom: 15px;
            padding: 10px;
            background-color: var(--content-bg);
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }
        
        .cancel-reply, .submit-reply {
            padding: 5px 10px;
            margin-left: 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .cancel-reply {
            background-color: var(--background-color);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        
        .submit-reply {
            background-color: var(--accent-secondary);
            color: white;
            border: none;
        }
        
        .comment-replies {
            margin-top: 15px;
        }
        
        .single-post {
            max-width: 100%;
            margin-bottom: 20px;
        }
        
        .post-title {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .comments-title {
            margin: 30px 0 20px;
            color: var(--text-primary);
            font-size: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Banner image behind header -->
        <div class="banner">
            <img src="assets/images/buzz_banner.png" alt="IP2 Network Banner" class="banner-image">
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
            <main class="main-content" style="max-width: 100%;">
                <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
                
                <!-- Single post view -->
                <div class="single-post">
                    <div class="post">
                        <div class="post-sidebar">
                            <div class="vote-buttons">
                                <button class="upvote <?php echo (isset($userVotes['post_' . $post['id']]) && $userVotes['post_' . $post['id']] === 1) ? 'active' : ''; ?>" 
                                        onclick="location.href='index.php?action=vote&post_id=<?php echo $post['id']; ?>&vote=1'">
                                    <i class="fas fa-arrow-up"></i>
                                </button>
                                <span class="vote-count"><?php echo $post['upvotes'] - $post['downvotes']; ?></span>
                                <button class="downvote <?php echo (isset($userVotes['post_' . $post['id']]) && $userVotes['post_' . $post['id']] === -1) ? 'active' : ''; ?>"
                                        onclick="location.href='index.php?action=vote&post_id=<?php echo $post['id']; ?>&vote=-1'">
                                    <i class="fas fa-arrow-down"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="post-content">
                            <div class="post-header">
                                <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>
                                
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
                                    <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="Post image" style="max-width: 100%; margin-top: 15px;">
                                <?php endif; ?>
                                
                                <?php if (!empty($post['video_url'])): ?>
                                    <video controls style="max-width: 100%; margin-top: 15px;">
                                        <source src="<?php echo htmlspecialchars($post['video_url']); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php endif; ?>
                            </div>
                            
                            <div class="post-actions">
                                <a href="#" class="post-action active">
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
                </div>
                
                <!-- Comments section -->
                <h2 class="comments-title">Comments</h2>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Comment form -->
                    <div class="status-form">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_comment">
                            <textarea name="content" class="status-textarea" placeholder="Write a comment..."></textarea>
                            
                            <?php if ($commentError): ?>
                                <p style="color: var(--alert-color);"><?php echo htmlspecialchars($commentError); ?></p>
                            <?php endif; ?>
                            
                            <div class="status-footer">
                                <button type="submit" class="status-button">Post Comment</button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="status-form">
                        <p>Please <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" style="color: var(--accent-secondary);">login</a> to comment.</p>
                    </div>
                <?php endif; ?>
                
                <!-- Comments list -->
                <div class="comments-list">
                    <?php 
                        if (empty($comments)) {
                            echo '<p style="color: var(--text-secondary); padding: 15px; text-align: center;">No comments yet. Be the first to comment!</p>';
                        } else {
                            foreach ($comments as $comment) {
                                renderComment($comment, $userVotes);
                            }
                        }
                    ?>
                </div>
            </main>
        </div>
        
        <footer>
            <p>© <?php echo date('Y'); ?> IP2∞ (IP2.Social)</p>
        </footer>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Reply functionality
            const replyButtons = document.querySelectorAll('.reply-button');
            const cancelButtons = document.querySelectorAll('.cancel-reply');
            
            replyButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const commentId = this.getAttribute('data-comment-id');
                    const replyForm = document.getElementById('reply-form-' + commentId);
                    replyForm.style.display = 'block';
                    
                    // Focus the textarea
                    const textarea = replyForm.querySelector('textarea');
                    textarea.focus();
                });
            });
            
            cancelButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const commentId = this.getAttribute('data-comment-id');
                    const replyForm = document.getElementById('reply-form-' + commentId);
                    replyForm.style.display = 'none';
                });
            });
        });
    </script>
</body>
</html>
