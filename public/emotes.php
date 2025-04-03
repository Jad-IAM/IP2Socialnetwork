<?php
// Include database connection
require_once(__DIR__ . '/includes/database.php');

// Connect to SQLite database
try {
    $db = getDatabase();
    
    // Simple session management
    session_start();
    
    // Only admin can access emote management
    $isAdmin = isset($_SESSION['user_id']) && $_SESSION['username'] === 'admin';
    
    // Create emotes table if it doesn't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS emotes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            file_path TEXT NOT NULL,
            width INTEGER DEFAULT 24,
            height INTEGER DEFAULT 24,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Check if we have default emotes
    $stmt = $db->query("SELECT COUNT(*) FROM emotes");
    $emoteCount = $stmt->fetchColumn();
    
    // Add default emotes if none exist
    if ($emoteCount == 0) {
        $defaultEmotes = [
            ['name' => 'Pepe', 'file_path' => 'assets/emotes/pepe.png'],
            ['name' => 'PepeHands', 'file_path' => 'assets/emotes/pepehands.png'],
            ['name' => 'PepeSmile', 'file_path' => 'assets/emotes/pepesmile.png'],
            ['name' => 'KEKW', 'file_path' => 'assets/emotes/kekw.png'],
            ['name' => 'HYPERS', 'file_path' => 'assets/emotes/hypers.png']
        ];
        
        $stmt = $db->prepare("INSERT INTO emotes (name, file_path) VALUES (?, ?)");
        
        foreach ($defaultEmotes as $emote) {
            $stmt->execute([$emote['name'], $emote['file_path']]);
        }
    }
    
    // Process form submission for adding a new emote
    $successMessage = '';
    $errorMessage = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // Only allow admin to add/delete emotes
        if (!$isAdmin) {
            $errorMessage = 'Only administrators can manage emotes';
        } else {
            if ($_POST['action'] === 'add_emote') {
                $name = trim($_POST['name'] ?? '');
                
                // Validate emote name (alphanumeric only)
                if (empty($name) || !preg_match('/^[a-zA-Z0-9]+$/', $name)) {
                    $errorMessage = 'Invalid emote name. Use only letters and numbers.';
                } elseif (!isset($_FILES['emote_file']) || $_FILES['emote_file']['error'] !== UPLOAD_ERR_OK) {
                    $errorMessage = 'Error uploading file';
                } else {
                    $uploadedFile = $_FILES['emote_file'];
                    
                    // Validate file type
                    $allowedTypes = ['image/png', 'image/jpeg', 'image/gif'];
                    $fileType = mime_content_type($uploadedFile['tmp_name']);
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        $errorMessage = 'Invalid file type. Only PNG, JPEG, and GIF are allowed.';
                    } else {
                        // Generate file path
                        $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
                        $fileName = strtolower($name) . '.' . $extension;
                        $filePath = 'assets/emotes/' . $fileName;
                        $fullPath = __DIR__ . '/' . $filePath;
                        
                        // Move uploaded file
                        if (move_uploaded_file($uploadedFile['tmp_name'], $fullPath)) {
                            try {
                                // Add to database
                                $stmt = $db->prepare("INSERT INTO emotes (name, file_path) VALUES (?, ?)");
                                $stmt->execute([$name, $filePath]);
                                
                                $successMessage = 'Emote added successfully!';
                            } catch (PDOException $e) {
                                $errorMessage = 'Error adding emote: ' . $e->getMessage();
                                // Delete the uploaded file if database insertion fails
                                @unlink($fullPath);
                            }
                        } else {
                            $errorMessage = 'Failed to save emote file';
                        }
                    }
                }
            } elseif ($_POST['action'] === 'delete_emote' && isset($_POST['emote_id'])) {
                $emoteId = $_POST['emote_id'];
                
                try {
                    // Get file path before deletion
                    $stmt = $db->prepare("SELECT file_path FROM emotes WHERE id = ?");
                    $stmt->execute([$emoteId]);
                    $filePath = $stmt->fetchColumn();
                    
                    // Delete from database
                    $stmt = $db->prepare("DELETE FROM emotes WHERE id = ?");
                    $stmt->execute([$emoteId]);
                    
                    // Delete file
                    if ($filePath) {
                        $fullPath = __DIR__ . '/' . $filePath;
                        @unlink($fullPath);
                    }
                    
                    $successMessage = 'Emote deleted successfully!';
                } catch (PDOException $e) {
                    $errorMessage = 'Error deleting emote: ' . $e->getMessage();
                }
            }
        }
    }
    
    // Get all emotes
    $stmt = $db->query("SELECT * FROM emotes ORDER BY name ASC");
    $emotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emotes - IP2∞Social.network</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .emotes-container {
            max-width: 800px;
            margin: 20px auto;
            background-color: var(--content-bg);
            border-radius: 4px;
            border: 1px solid var(--border-color);
            padding: 20px;
        }
        
        .emotes-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: var(--accent-secondary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }
        
        .emotes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .emote-item {
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 10px;
            text-align: center;
            transition: transform 0.2s;
        }
        
        .emote-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .emote-image {
            width: 48px;
            height: 48px;
            object-fit: contain;
            margin: 0 auto 10px;
            display: block;
        }
        
        .emote-name {
            font-weight: bold;
            color: var(--text-primary);
            font-size: 14px;
            margin-bottom: 5px;
            word-break: break-word;
        }
        
        .emote-code {
            font-family: monospace;
            background-color: rgba(0, 0, 0, 0.2);
            padding: 3px 5px;
            border-radius: 3px;
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .emote-actions {
            margin-top: 10px;
            display: flex;
            justify-content: center;
        }
        
        .emote-delete {
            background-color: rgba(255, 0, 0, 0.1);
            color: #ff6666;
            border: 1px solid #8b0000;
            border-radius: 3px;
            padding: 2px 5px;
            font-size: 12px;
            cursor: pointer;
        }
        
        .emote-delete:hover {
            background-color: rgba(255, 0, 0, 0.2);
        }
        
        .add-emote-form {
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .form-title {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--accent-secondary);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-primary);
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 8px;
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 4px;
        }
        
        .submit-button {
            background-color: var(--accent-secondary);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .submit-button:hover {
            opacity: 0.9;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--accent-secondary);
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .success-message {
            background-color: rgba(0, 128, 0, 0.1);
            border: 1px solid #006400;
            padding: 15px;
            margin-bottom: 20px;
            color: #00ff00;
            border-radius: 4px;
        }
        
        .error-message {
            background-color: rgba(255, 0, 0, 0.1);
            border: 1px solid #8b0000;
            padding: 15px;
            margin-bottom: 20px;
            color: #ff6666;
            border-radius: 4px;
        }
        
        .usage-instructions {
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .usage-instructions h3 {
            color: var(--accent-secondary);
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .usage-instructions p {
            margin-bottom: 10px;
        }
        
        .usage-instructions code {
            font-family: monospace;
            background-color: rgba(0, 0, 0, 0.2);
            padding: 2px 5px;
            border-radius: 3px;
        }
        
        .usage-instructions .example {
            display: flex;
            align-items: center;
            margin-top: 10px;
            background-color: rgba(0, 0, 0, 0.1);
            padding: 10px;
            border-radius: 4px;
        }
        
        .usage-instructions .example code {
            margin-right: 10px;
        }
    </style>
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
                
                <div class="emotes-container">
                    <h2 class="emotes-title">IP2∞ Emotes</h2>
                    
                    <?php if ($successMessage): ?>
                        <div class="success-message">
                            <?php echo htmlspecialchars($successMessage); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($errorMessage): ?>
                        <div class="error-message">
                            <?php echo htmlspecialchars($errorMessage); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="usage-instructions">
                        <h3>How to Use Emotes</h3>
                        <p>Use emotes in your posts and comments with the syntax: <code>#/EmoteName</code></p>
                        
                        <div class="example">
                            <code>I can't believe he did that! #/PepeHands</code>
                            <div>I can't believe he did that! <img src="assets/emotes/pepehands.png" alt="PepeHands" width="24" height="24" style="vertical-align: middle;"></div>
                        </div>
                        
                        <div class="example">
                            <code>This stream is so funny #/KEKW</code>
                            <div>This stream is so funny <img src="assets/emotes/kekw.png" alt="KEKW" width="24" height="24" style="vertical-align: middle;"></div>
                        </div>
                    </div>
                    
                    <h3>Available Emotes</h3>
                    <div class="emotes-grid">
                        <?php foreach ($emotes as $emote): ?>
                            <div class="emote-item">
                                <img src="<?php echo htmlspecialchars($emote['file_path']); ?>" alt="<?php echo htmlspecialchars($emote['name']); ?>" class="emote-image">
                                <div class="emote-name"><?php echo htmlspecialchars($emote['name']); ?></div>
                                <div class="emote-code">#/<?php echo htmlspecialchars($emote['name']); ?></div>
                                
                                <?php if ($isAdmin): ?>
                                    <div class="emote-actions">
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="delete_emote">
                                            <input type="hidden" name="emote_id" value="<?php echo $emote['id']; ?>">
                                            <button type="submit" class="emote-delete" onclick="return confirm('Are you sure you want to delete this emote?');">Delete</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($isAdmin): ?>
                        <div class="add-emote-form">
                            <h3 class="form-title">Add New Emote</h3>
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="add_emote">
                                
                                <div class="form-group">
                                    <label for="name">Emote Name</label>
                                    <input type="text" id="name" name="name" class="form-control" required pattern="[a-zA-Z0-9]+" placeholder="EmoteName (letters and numbers only)">
                                </div>
                                
                                <div class="form-group">
                                    <label for="emote_file">Emote Image</label>
                                    <input type="file" id="emote_file" name="emote_file" class="form-control" required accept="image/png,image/jpeg,image/gif">
                                    <small style="color: var(--text-secondary);">Recommended size: 32x32 pixels. PNG with transparency works best.</small>
                                </div>
                                
                                <button type="submit" class="submit-button">Add Emote</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </main>

            <aside class="sidebar">
                <!-- Sidebar buttons -->
                <div class="sidebar-buttons">
                    <div class="button-row">
                        <a href="live.php" class="sidebar-button live-button"><span class="live-icon"></span>LIVE</a>
                        <a href="#" class="sidebar-button leaderboard-button">LEADERBOARD</a>
                    </div>
                    <div class="button-row">
                        <a href="upload.php" class="sidebar-button upload-button">UPLOAD VIDEO</a>
                    </div>
                </div>
                
                <!-- About Box -->
                <div class="about-box">
                    <h2>About Emotes</h2>
                    
                    <h4 class="section-title">Using Emotes</h4>
                    <p class="about-text">Add emotes to your posts and comments with the <code>#/EmoteName</code> syntax.</p>
                    
                    <h4 class="section-title">Examples</h4>
                    <ul class="rules-list">
                        <li>#/Pepe</li>
                        <li>#/KEKW</li>
                        <li>#/PepeHands</li>
                    </ul>
                    
                    <?php if ($isAdmin): ?>
                        <h4 class="section-title">Admin Controls</h4>
                        <p>As an admin, you can add and delete emotes.</p>
                    <?php else: ?>
                        <h4 class="section-title">Add Emotes</h4>
                        <p>Only admins can add new emotes to the site.</p>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
        
        <footer>
            <p>© <?php echo date('Y'); ?> IP2∞Social.network</p>
        </footer>
    </div>
</body>
</html>
