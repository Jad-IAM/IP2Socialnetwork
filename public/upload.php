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
    
    $uploadMessage = '';
    $uploadError = '';
    $videoFile = null;
    
    // Handle file upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_video') {
        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            // Check file type and size
            $allowedTypes = ['video/mp4', 'video/webm', 'video/ogg'];
            $maxSize = 100 * 1024 * 1024; // 100MB
            
            $fileType = $_FILES['video']['type'];
            $fileSize = $_FILES['video']['size'];
            $fileName = $_FILES['video']['name'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $uploadError = "Invalid file type. Only MP4, WebM, and OGG videos are allowed.";
            } elseif ($fileSize > $maxSize) {
                $uploadError = "File is too large. Maximum size is 100MB.";
            } else {
                // Create uploads directory if it doesn't exist
                $uploadDir = __DIR__ . '/assets/uploads/videos';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = generateRandomFilename($fileName, $extension);
                $targetPath = $uploadDir . '/' . $newFileName;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['video']['tmp_name'], $targetPath)) {
                    $videoUrl = 'assets/uploads/videos/' . $newFileName;
                    
                    // Handle optional title and description
                    $title = isset($_POST['title']) ? sanitizeString($_POST['title'], 100) : '';
                    $description = isset($_POST['description']) ? sanitizeString($_POST['description'], 1000) : '';
                    
                    // Insert into database
                    $stmt = $db->prepare("INSERT INTO posts (user_id, title, content, video_url, flair) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $title, $description, $videoUrl, 'Video']);
                    
                    $uploadMessage = "Video uploaded successfully!";
                    $redirectUrl = "index.php?status=uploaded";
                    
                    // Redirect after short delay
                    header("refresh:2;url=$redirectUrl");
                } else {
                    $uploadError = "Failed to upload file. Please try again.";
                }
            }
        } else {
            $uploadError = "No file selected or upload error occurred.";
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
    <title>Upload Video - IP2∞</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .progress-container {
            margin-top: 20px;
            display: none;
        }
        
        #upload-progress {
            width: 100%;
            height: 25px;
            border-radius: 4px;
            overflow: hidden;
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
        }
        
        #progress-bar {
            height: 100%;
            background-color: var(--accent-secondary);
            width: 0%;
            transition: width 0.3s;
            text-align: center;
            line-height: 25px;
            color: #fff;
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
                
                <div class="upload-container">
                    <h2 class="upload-title">Upload Video</h2>
                    
                    <?php if ($uploadError): ?>
                        <div class="auth-error">
                            <p><?php echo htmlspecialchars($uploadError); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($uploadMessage): ?>
                        <div class="auth-success">
                            <p><?php echo htmlspecialchars($uploadMessage); ?></p>
                            <p>Redirecting to homepage...</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$uploadMessage): ?>
                        <div class="drop-area" id="drop-area">
                            <div class="drop-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="drop-message">Drag & drop video here</div>
                            <div>or</div>
                            <form id="file-form" class="upload-form" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_video">
                                <input type="file" id="file-input" name="video" class="file-input" accept="video/*">
                                <div class="progress-container" id="progress-container">
                                    <div id="upload-progress">
                                        <div id="progress-bar">0%</div>
                                    </div>
                                </div>
                                
                                <div class="upload-preview" id="preview-container">
                                    <video id="preview-video" controls></video>
                                </div>
                                
                                <div class="form-group" style="margin-top: 20px;">
                                    <label for="title">Title (optional)</label>
                                    <input type="text" id="title" name="title" class="form-input">
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">Description (optional)</label>
                                    <textarea id="description" name="description" class="status-textarea" style="min-height: 80px;"></textarea>
                                </div>
                                
                                <button type="submit" class="status-button" id="upload-button" disabled>Upload Video</button>
                            </form>
                        </div>
                        
                        <div class="file-info" id="file-info"></div>
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
            const dropArea = document.getElementById('drop-area');
            const fileInput = document.getElementById('file-input');
            const uploadButton = document.getElementById('upload-button');
            const previewContainer = document.getElementById('preview-container');
            const previewVideo = document.getElementById('preview-video');
            const fileInfo = document.getElementById('file-info');
            const progressContainer = document.getElementById('progress-container');
            const progressBar = document.getElementById('progress-bar');
            const fileForm = document.getElementById('file-form');
            
            // Prevent default drag behaviors
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });
            
            // Highlight drop area when item is dragged over it
            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, unhighlight, false);
            });
            
            // Handle dropped files
            dropArea.addEventListener('drop', handleDrop, false);
            
            // Handle selected files through file input
            fileInput.addEventListener('change', handleFiles, false);
            
            // Handle form submission
            fileForm.addEventListener('submit', function(e) {
                if (fileInput.files.length === 0) {
                    e.preventDefault();
                    alert('Please select a video file to upload.');
                    return false;
                }
                
                // Display progress bar
                progressContainer.style.display = 'block';
                
                // Disable submit button to prevent multiple submissions
                uploadButton.disabled = true;
                uploadButton.textContent = 'Uploading...';
                
                // Display upload progress
                const xhr = new XMLHttpRequest();
                const formData = new FormData(fileForm);
                
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        progressBar.style.width = percentComplete + '%';
                        progressBar.textContent = percentComplete + '%';
                    }
                }, false);
                
                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        progressBar.style.width = '100%';
                        progressBar.textContent = 'Upload Complete!';
                        window.location.href = 'index.php?status=uploaded';
                    } else {
                        alert('An error occurred during the upload. Please try again.');
                        uploadButton.disabled = false;
                        uploadButton.textContent = 'Upload Video';
                    }
                });
                
                xhr.addEventListener('error', function() {
                    alert('An error occurred during the upload. Please try again.');
                    uploadButton.disabled = false;
                    uploadButton.textContent = 'Upload Video';
                });
                
                xhr.open('POST', 'upload.php', true);
                xhr.send(formData);
                
                // Prevent default form submission
                e.preventDefault();
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            function highlight() {
                dropArea.classList.add('highlight');
            }
            
            function unhighlight() {
                dropArea.classList.remove('highlight');
            }
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                handleFiles({target: {files: files}});
            }
            
            function handleFiles(e) {
                const files = e.target.files;
                
                if (files.length > 0) {
                    const file = files[0];
                    
                    // Check if file is video
                    if (!file.type.match('video.*')) {
                        alert('Please select a video file.');
                        return;
                    }
                    
                    // Display file info
                    const size = (file.size / (1024 * 1024)).toFixed(2);
                    fileInfo.textContent = `File: ${file.name} (${size} MB)`;
                    
                    // Preview video
                    const objectUrl = URL.createObjectURL(file);
                    previewVideo.src = objectUrl;
                    previewContainer.style.display = 'block';
                    
                    // Enable upload button
                    uploadButton.disabled = false;
                }
            }
        });
    </script>
</body>
</html>
