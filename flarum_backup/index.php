<?php
require_once 'functions.php';

// Initialize session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle user identification
if (!isset($_SESSION['username'])) {
    if (isset($_POST['set_username'])) {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        if ($username && strlen(trim($username)) > 0) {
            $_SESSION['username'] = $username;
            saveUser($username);
        }
    }
}

// Logout functionality
if (isset($_GET['logout'])) {
    unset($_SESSION['username']);
    header('Location: index.php');
    exit;
}

// Load posts
$posts = getPosts();

// Sort posts by score (upvotes - downvotes) in descending order
usort($posts, function($a, $b) {
    return ($b['upvotes'] - $b['downvotes']) - ($a['upvotes'] - $a['downvotes']);
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroForum</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <div class="header-container">
            <h1>MicroForum</h1>
            <div class="user-info">
                <?php if (isset($_SESSION['username'])): ?>
                    <p>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> 
                    (<a href="?logout=1">Logout</a>)</p>
                <?php else: ?>
                    <form method="post" action="" class="username-form">
                        <input type="text" name="username" placeholder="Choose a username" required>
                        <button type="submit" name="set_username">Set Username</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <div class="content">
            <?php if (isset($_SESSION['username'])): ?>
                <div class="post-form-container">
                    <h2>Start a New Discussion</h2>
                    <form method="post" action="post.php" class="post-form">
                        <input type="text" name="title" placeholder="Title" required>
                        <textarea name="content" placeholder="What's on your mind?" required></textarea>
                        <input type="hidden" name="parent_id" value="0">
                        <button type="submit">Post</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="posts-container">
                <h2>Recent Discussions</h2>
                <?php if (empty($posts)): ?>
                    <p class="empty-state">No discussions yet. Be the first to post!</p>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <?php if ($post['parent_id'] == 0): // Only show top-level posts ?>
                            <div class="post">
                                <div class="vote-container">
                                    <form method="post" action="vote.php">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                        <input type="hidden" name="vote" value="up">
                                        <button type="submit" class="vote-button">▲</button>
                                    </form>
                                    <span class="vote-count"><?php echo $post['upvotes'] - $post['downvotes']; ?></span>
                                    <form method="post" action="vote.php">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                        <input type="hidden" name="vote" value="down">
                                        <button type="submit" class="vote-button">▼</button>
                                    </form>
                                </div>
                                <div class="post-content">
                                    <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                                    <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                    <div class="post-meta">
                                        <span>Posted by <?php echo htmlspecialchars($post['username']); ?></span>
                                        <span><?php echo formatTime($post['timestamp']); ?></span>
                                    </div>
                                    
                                    <?php if (isset($_SESSION['username'])): ?>
                                        <div class="reply-form-container">
                                            <form method="post" action="post.php" class="reply-form">
                                                <textarea name="content" placeholder="Write a reply..." required></textarea>
                                                <input type="hidden" name="parent_id" value="<?php echo $post['id']; ?>">
                                                <button type="submit">Reply</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php displayReplies($posts, $post['id']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> MicroForum - A Simple PHP Discussion Forum</p>
    </footer>
</body>
</html>
