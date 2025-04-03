<?php
// Include database connection
require_once(__DIR__ . '/includes/database.php');
require_once(__DIR__ . '/includes/functions.php');

// Connect to SQLite database
try {
    $db = getDatabase();
    
    // Simple session management
    session_start();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    
    $postMessage = '';
    $postError = '';
    
    // Handle post creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_post') {
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $flair = $_POST['flair'] ?? '';
        
        if (empty($title)) {
            $postError = "Post title cannot be empty";
        } elseif (empty($content)) {
            $postError = "Post content cannot be empty";
        } else {
            // Handle image upload if present
            $imageUrl = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 10 * 1024 * 1024; // 10MB
                
                $fileType = $_FILES['image']['type'];
                $fileSize = $_FILES['image']['size'];
                $fileName = $_FILES['image']['name'];
                
                if (!in_array($fileType, $allowedTypes)) {
                    $postError = "Invalid image type. Only JPEG, PNG, GIF, and WEBP are allowed.";
                } elseif ($fileSize > $maxSize) {
                    $postError = "Image is too large. Maximum size is 10MB.";
                } else {
                    // Create uploads directory if it doesn't exist
                    $uploadDir = __DIR__ . '/assets/uploads/images';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $newFileName = generateRandomFilename($fileName, $extension);
                    $targetPath = $uploadDir . '/' . $newFileName;
                    
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                        $imageUrl = 'assets/uploads/images/' . $newFileName;
                    } else {
                        $postError = "Failed to upload image. Please try again.";
                    }
                }
            }
            
            if (empty($postError)) {
                // Insert into database
                $stmt = $db->prepare("INSERT INTO posts (user_id, title, content, flair, image_url) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $title, $content, $flair, $imageUrl]);
                
                $postMessage = "Post created successfully!";
                
                // Redirect after short delay
                header("refresh:2;url=index.php?status=posted");
            }
        }
    }
    
    // Get available flairs
    $flairsJson = file_get_contents(__DIR__ . '/get_flairs.php');
    $flairs = json_decode($flairsJson, true);
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post - IP2∞</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
                    <a href="profile.php" class="member-button"><?php echo htmlspecialchars($_SESSION['username']); ?></a>
                </div>
            </nav>
        </header>

        <div class="content-wrapper">
            <main class="main-content" style="max-width: 100%;">
                <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
                
                <div class="upload-container">
                    <h2 class="upload-title">Create New Post</h2>
                    
                    <?php if ($postError): ?>
                        <div class="auth-error">
                            <p><?php echo htmlspecialchars($postError); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($postMessage): ?>
                        <div class="auth-success">
                            <p><?php echo htmlspecialchars($postMessage); ?></p>
                            <p>Redirecting to homepage...</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$postMessage): ?>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="create_post">
                            
                            <div class="form-group">
                                <label for="title">Title</label>
                                <input type="text" id="title" name="title" required class="form-input">
                            </div>
                            
                            <div class="form-group">
                                <label for="flair">Flair</label>
                                <select id="flair" name="flair" class="form-input">
                                    <option value="">Select Flair (Optional)</option>
                                    <?php foreach ($flairs as $flair): ?>
                                        <option value="<?php echo htmlspecialchars($flair); ?>"><?php echo htmlspecialchars($flair); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="content">Content</label>
                                <textarea id="content" name="content" class="status-textarea" required></textarea>
                                <p class="form-help">
                                    <small>
                                        You can use #EmoteName for emotes. For example, #Kek, #Pog, #Pepe, etc.
                                    </small>
                                </p>
                            </div>
                            
                            <div class="form-group">
                                <label for="image">Image (Optional)</label>
                                <input type="file" id="image" name="image" accept="image/*" class="form-input">
                                <div id="image-preview" style="margin-top: 10px; display: none;">
                                    <img id="preview-img" src="" alt="Preview" style="max-width: 300px; max-height: 300px;">
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px; text-align: center;">
                                <button type="submit" class="auth-button">Create Post</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </main>
        </div>
        
        <footer>
            <p>© <?php echo date('Y'); ?> IP2∞ (IP2.Social)</p>
        </footer>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Image preview
            const imageInput = document.getElementById('image');
            const imagePreview = document.getElementById('image-preview');
            const previewImg = document.getElementById('preview-img');
            
            if (imageInput) {
                imageInput.addEventListener('change', function() {
                    const file = this.files[0];
                    
                    if (file) {
                        const reader = new FileReader();
                        
                        reader.addEventListener('load', function() {
                            previewImg.src = reader.result;
                            imagePreview.style.display = 'block';
                        });
                        
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
    </script>
</body>
</html>
