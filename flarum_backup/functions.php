<?php
// File paths
define('POSTS_FILE', 'data/posts.json');
define('USERS_FILE', 'data/users.json');

// Create data directory if it doesn't exist
if (!file_exists('data')) {
    mkdir('data', 0755, true);
}

// Initialize posts file if it doesn't exist
if (!file_exists(POSTS_FILE)) {
    file_put_contents(POSTS_FILE, '[]');
}

// Initialize users file if it doesn't exist
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, '[]');
}

/**
 * Get all posts from the JSON file
 * @return array Array of posts
 */
function getPosts() {
    if (file_exists(POSTS_FILE)) {
        $postsJson = file_get_contents(POSTS_FILE);
        return json_decode($postsJson, true) ?: [];
    }
    return [];
}

/**
 * Save posts to the JSON file
 * @param array $posts Array of posts to save
 * @return bool Success status
 */
function savePosts($posts) {
    return file_put_contents(POSTS_FILE, json_encode($posts, JSON_PRETTY_PRINT));
}

/**
 * Get all users from the JSON file
 * @return array Array of users
 */
function getUsers() {
    if (file_exists(USERS_FILE)) {
        $usersJson = file_get_contents(USERS_FILE);
        return json_decode($usersJson, true) ?: [];
    }
    return [];
}

/**
 * Save a user to the JSON file
 * @param string $username Username to save
 * @return bool Success status
 */
function saveUser($username) {
    $users = getUsers();
    if (!in_array($username, $users)) {
        $users[] = $username;
        return file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
    }
    return true;
}

/**
 * Add a new post or reply
 * @param string $title Post title (optional for replies)
 * @param string $content Post content
 * @param string $username Author username
 * @param int $parentId Parent post ID (0 for top-level posts)
 * @return bool Success status
 */
function addPost($title, $content, $username, $parentId = 0) {
    $posts = getPosts();
    $newId = count($posts) > 0 ? max(array_column($posts, 'id')) + 1 : 1;
    
    $post = [
        'id' => $newId,
        'parent_id' => $parentId,
        'title' => $title,
        'content' => $content,
        'username' => $username,
        'timestamp' => time(),
        'upvotes' => 0,
        'downvotes' => 0,
        'voters' => [] // Track who voted on this post
    ];
    
    $posts[] = $post;
    return savePosts($posts);
}

/**
 * Vote on a post
 * @param int $postId Post ID
 * @param string $username Username of voter
 * @param string $voteType 'up' or 'down'
 * @return bool Success status
 */
function votePost($postId, $username, $voteType) {
    $posts = getPosts();
    
    foreach ($posts as &$post) {
        if ($post['id'] == $postId) {
            // Initialize voters array if not present
            if (!isset($post['voters'])) {
                $post['voters'] = [];
            }
            
            // Check if user already voted
            $previousVote = null;
            if (isset($post['voters'][$username])) {
                $previousVote = $post['voters'][$username];
            }
            
            // Update votes
            if ($previousVote === $voteType) {
                // Cancel vote if same type
                if ($voteType === 'up') {
                    $post['upvotes']--;
                } else {
                    $post['downvotes']--;
                }
                unset($post['voters'][$username]);
            } else {
                // Remove previous vote if exists
                if ($previousVote === 'up') {
                    $post['upvotes']--;
                } elseif ($previousVote === 'down') {
                    $post['downvotes']--;
                }
                
                // Add new vote
                if ($voteType === 'up') {
                    $post['upvotes']++;
                } else {
                    $post['downvotes']++;
                }
                $post['voters'][$username] = $voteType;
            }
            
            return savePosts($posts);
        }
    }
    
    return false;
}

/**
 * Format timestamp to human-readable format
 * @param int $timestamp Unix timestamp
 * @return string Formatted time
 */
function formatTime($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = round($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = round($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = round($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date("F j, Y", $timestamp);
    }
}

/**
 * Display replies for a post
 * @param array $posts All posts
 * @param int $parentId Parent post ID
 * @param int $level Nesting level (for indentation)
 */
function displayReplies($posts, $parentId, $level = 1) {
    // Find direct replies to this post
    $replies = array_filter($posts, function($post) use ($parentId) {
        return $post['parent_id'] == $parentId;
    });
    
    // Sort replies by score (upvotes - downvotes) in descending order
    usort($replies, function($a, $b) {
        return ($b['upvotes'] - $b['downvotes']) - ($a['upvotes'] - $a['downvotes']);
    });
    
    if (count($replies) > 0): ?>
        <div class="replies" style="margin-left: <?php echo $level * 20; ?>px;">
            <?php foreach ($replies as $reply): ?>
                <div class="reply">
                    <div class="vote-container">
                        <form method="post" action="vote.php">
                            <input type="hidden" name="post_id" value="<?php echo $reply['id']; ?>">
                            <input type="hidden" name="vote" value="up">
                            <button type="submit" class="vote-button">▲</button>
                        </form>
                        <span class="vote-count"><?php echo $reply['upvotes'] - $reply['downvotes']; ?></span>
                        <form method="post" action="vote.php">
                            <input type="hidden" name="post_id" value="<?php echo $reply['id']; ?>">
                            <input type="hidden" name="vote" value="down">
                            <button type="submit" class="vote-button">▼</button>
                        </form>
                    </div>
                    <div class="reply-content">
                        <p><?php echo nl2br(htmlspecialchars($reply['content'])); ?></p>
                        <div class="post-meta">
                            <span>Reply by <?php echo htmlspecialchars($reply['username']); ?></span>
                            <span><?php echo formatTime($reply['timestamp']); ?></span>
                        </div>
                        
                        <?php if (isset($_SESSION['username'])): ?>
                            <div class="reply-form-container">
                                <form method="post" action="post.php" class="reply-form">
                                    <textarea name="content" placeholder="Write a reply..." required></textarea>
                                    <input type="hidden" name="parent_id" value="<?php echo $reply['id']; ?>">
                                    <button type="submit">Reply</button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <?php displayReplies($posts, $reply['id'], $level + 1); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif;
}
