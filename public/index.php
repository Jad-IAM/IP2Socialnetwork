<?php
// Simple PHP forum using SQLite
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
    
    // Create tables if they don't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            email TEXT UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            user_id INTEGER,
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
    ");
    
    // Seed some initial data if tables are empty
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        // Insert a test user
        $db->exec("INSERT INTO users (username, password, email) VALUES ('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin@example.com')");
        $db->exec("INSERT INTO users (username, password, email) VALUES ('user1', '" . password_hash('user123', PASSWORD_DEFAULT) . "', 'user1@example.com')");
        
        // Insert some test posts
        $db->exec("INSERT INTO posts (title, content, user_id) VALUES ('Welcome to our forum', 'This is the first post on our new forum. Feel free to comment and discuss!', 1)");
        $db->exec("INSERT INTO posts (title, content, user_id) VALUES ('Tips for new users', 'Here are some tips to get started with our forum.', 1)");
        $db->exec("INSERT INTO posts (title, content, user_id) VALUES ('My introduction', 'Hello everyone! I am new here and excited to join the community.', 2)");
        
        // Insert some test comments
        $db->exec("INSERT INTO comments (content, post_id, user_id) VALUES ('Great to have this forum up and running!', 1, 2)");
        $db->exec("INSERT INTO comments (content, post_id, user_id) VALUES ('Thanks for the welcome!', 1, 2)");
        $db->exec("INSERT INTO comments (content, post_id, user_id) VALUES ('These tips are very helpful.', 2, 2)");
    }
    
    // Simple session management
    session_start();
    
    // Handle login
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
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
        
        if ($title && $content) {
            $stmt = $db->prepare("INSERT INTO posts (title, content, user_id) VALUES (?, ?, ?)");
            $stmt->execute([$title, $content, $_SESSION['user_id']]);
            header('Location: index.php');
            exit;
        }
    }
    
    // Handle new comment creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_comment') {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php');
            exit;
        }
        
        $content = $_POST['content'] ?? '';
        $post_id = $_POST['post_id'] ?? '';
        
        if ($content && $post_id) {
            $stmt = $db->prepare("INSERT INTO comments (content, post_id, user_id) VALUES (?, ?, ?)");
            $stmt->execute([$content, $post_id, $_SESSION['user_id']]);
            header('Location: index.php?post=' . $post_id);
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
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Helper function to get post vote count
function getVoteCount($db, $post_id) {
    $stmt = $db->prepare("SELECT SUM(vote) as vote_count FROM votes WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['vote_count'] ?: 0;
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

// CSS Styles
$styles = <<<CSS
body {
    font-family: Arial, sans-serif;
    line-height: 1.6;
    color: #333;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}
h1, h2, h3 {
    color: #444;
}
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.post {
    background: #f9f9f9;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    border-left: 5px solid #007bff;
}
.post-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}
.post-title {
    margin: 0;
    font-size: 1.4em;
}
.post-meta {
    font-size: 0.8em;
    color: #666;
    margin-bottom: 10px;
}
.post-content {
    margin-bottom: 15px;
}
.comment {
    background: #f5f5f5;
    padding: 10px;
    margin: 10px 0;
    border-radius: 3px;
    border-left: 3px solid #28a745;
}
.comment-meta {
    font-size: 0.8em;
    color: #666;
    margin-bottom: 5px;
}
.form-group {
    margin-bottom: 15px;
}
label {
    display: block;
    margin-bottom: 5px;
}
input[type="text"],
input[type="password"],
textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
}
button, .button {
    background: #007bff;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 3px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
button:hover, .button:hover {
    background: #0069d9;
}
.login-form {
    max-width: 400px;
    margin: 0 auto;
}
.error {
    color: red;
    margin-bottom: 10px;
}
.vote-buttons {
    display: flex;
    align-items: center;
    gap: 10px;
}
.vote-count {
    font-weight: bold;
}
.upvote, .downvote {
    text-decoration: none;
    padding: 3px 8px;
    border-radius: 3px;
    display: inline-block;
}
.upvote {
    background: #28a745;
    color: white;
}
.downvote {
    background: #dc3545;
    color: white;
}
.upvoted {
    background: #1e7e34;
}
.downvoted {
    background: #bd2130;
}
CSS;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple PHP Forum</title>
    <style><?php echo $styles; ?></style>
</head>
<body>
    <div class="header">
        <h1>Simple PHP Forum</h1>
        <div>
            <?php if (isset($_SESSION['user_id'])): ?>
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> | 
                <a href="index.php?action=logout">Logout</a>
            <?php else: ?>
                <a href="index.php?page=login">Login</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    // Display login form
    if (isset($_GET['page']) && $_GET['page'] === 'login'):
    ?>
        <div class="login-form">
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
                <button type="submit">Login</button>
            </form>
            <p>
                <small>Demo accounts: admin/admin123 or user1/user123</small>
            </p>
        </div>
        
    <?php
    // Display new post form
    elseif (isset($_GET['page']) && $_GET['page'] === 'new_post' && isset($_SESSION['user_id'])):
    ?>
        <h2>Create New Post</h2>
        <form method="POST" action="index.php">
            <input type="hidden" name="action" value="create_post">
            <div class="form-group">
                <label for="title">Title:</label>
                <input type="text" id="title" name="title" required>
            </div>
            <div class="form-group">
                <label for="content">Content:</label>
                <textarea id="content" name="content" rows="6" required></textarea>
            </div>
            <button type="submit">Create Post</button>
            <a href="index.php" class="button" style="background: #6c757d;">Cancel</a>
        </form>
        
    <?php
    // Display single post with comments
    elseif (isset($_GET['post'])):
        $post_id = $_GET['post'];
        $stmt = $db->prepare("SELECT p.*, u.username FROM posts p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($post):
            $vote_count = getVoteCount($db, $post['id']);
            $user_vote = isset($_SESSION['user_id']) ? getUserVote($db, $post['id'], $_SESSION['user_id']) : 0;
    ?>
        <div class="post">
            <div class="post-header">
                <h2 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h2>
                <div class="vote-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="index.php?action=vote&post_id=<?php echo $post['id']; ?>&vote=1&redirect=<?php echo urlencode("index.php?post={$post['id']}"); ?>" 
                           class="upvote <?php echo $user_vote == 1 ? 'upvoted' : ''; ?>">+</a>
                        <span class="vote-count"><?php echo $vote_count; ?></span>
                        <a href="index.php?action=vote&post_id=<?php echo $post['id']; ?>&vote=-1&redirect=<?php echo urlencode("index.php?post={$post['id']}"); ?>" 
                           class="downvote <?php echo $user_vote == -1 ? 'downvoted' : ''; ?>">-</a>
                    <?php else: ?>
                        <span class="vote-count"><?php echo $vote_count; ?> votes</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="post-meta">
                Posted by <?php echo htmlspecialchars($post['username']); ?> on 
                <?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?>
            </div>
            <div class="post-content">
                <?php echo nl2br(htmlspecialchars($post['content'])); ?>
            </div>
            
            <h3>Comments</h3>
            <?php
            $stmt = $db->prepare("SELECT c.*, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? ORDER BY c.created_at ASC");
            $stmt->execute([$post_id]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($comments) > 0):
                foreach ($comments as $comment):
            ?>
                <div class="comment">
                    <div class="comment-meta">
                        <?php echo htmlspecialchars($comment['username']); ?> commented on 
                        <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                    </div>
                    <div class="comment-content">
                        <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                    </div>
                </div>
            <?php
                endforeach;
            else:
            ?>
                <p>No comments yet.</p>
            <?php
            endif;
            
            // Comment form for logged-in users
            if (isset($_SESSION['user_id'])):
            ?>
                <h4>Add a Comment</h4>
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="create_comment">
                    <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                    <div class="form-group">
                        <textarea name="content" rows="3" required></textarea>
                    </div>
                    <button type="submit">Submit Comment</button>
                </form>
            <?php else: ?>
                <p><a href="index.php?page=login">Login to comment</a></p>
            <?php endif; ?>
            
            <p><a href="index.php">Back to Forum</a></p>
        </div>
    <?php
        else:
            echo "<p>Post not found.</p>";
            echo "<p><a href='index.php'>Back to Forum</a></p>";
        endif;
        
    // Display forum homepage with list of posts
    else:
    ?>
        <?php if (isset($_SESSION['user_id'])): ?>
            <p>
                <a href="index.php?page=new_post" class="button">Create New Post</a>
            </p>
        <?php endif; ?>
        
        <h2>Recent Posts</h2>
        
        <?php
        $stmt = $db->query("SELECT p.*, u.username, (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($posts) > 0):
            foreach ($posts as $post):
                $vote_count = getVoteCount($db, $post['id']);
                $user_vote = isset($_SESSION['user_id']) ? getUserVote($db, $post['id'], $_SESSION['user_id']) : 0;
        ?>
            <div class="post">
                <div class="post-header">
                    <h3 class="post-title">
                        <a href="index.php?post=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                    </h3>
                    <div class="vote-buttons">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="index.php?action=vote&post_id=<?php echo $post['id']; ?>&vote=1" 
                               class="upvote <?php echo $user_vote == 1 ? 'upvoted' : ''; ?>">+</a>
                            <span class="vote-count"><?php echo $vote_count; ?></span>
                            <a href="index.php?action=vote&post_id=<?php echo $post['id']; ?>&vote=-1" 
                               class="downvote <?php echo $user_vote == -1 ? 'downvoted' : ''; ?>">-</a>
                        <?php else: ?>
                            <span class="vote-count"><?php echo $vote_count; ?> votes</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="post-meta">
                    Posted by <?php echo htmlspecialchars($post['username']); ?> on 
                    <?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?> | 
                    <?php echo $post['comment_count']; ?> comment<?php echo $post['comment_count'] != 1 ? 's' : ''; ?>
                </div>
                <div class="post-content">
                    <?php 
                    // Show a preview of the content
                    $preview = substr(strip_tags($post['content']), 0, 200);
                    echo nl2br(htmlspecialchars($preview));
                    if (strlen($post['content']) > 200) echo '...';
                    ?>
                </div>
                <p>
                    <a href="index.php?post=<?php echo $post['id']; ?>">Read more &raquo;</a>
                </p>
            </div>
        <?php
            endforeach;
        else:
        ?>
            <p>No posts found.</p>
        <?php endif; ?>
        
    <?php endif; ?>
</body>
</html>
