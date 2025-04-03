<?php
// Database connection from index.php
$dbFile = __DIR__ . '/../storage/sqlite/forum.sqlite';
$dbDirectory = dirname($dbFile);

// Make sure SQLite directory exists
if (!is_dir($dbDirectory)) {
    mkdir($dbDirectory, 0777, true);
}

// Create uploads directory if it doesn't exist
$uploadsDir = __DIR__ . '/uploads/videos';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

// Connect to SQLite database
try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Simple session management
    session_start();
    
    $uploadSuccess = false;
    $uploadError = '';
    $uploadedFiles = [];
    
    // Handle video upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_video') {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        
        // Check if user is logged in
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        // Handle files
        if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
            // First check if the videos table exists, if not create it
            $db->exec("
                CREATE TABLE IF NOT EXISTS videos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    description TEXT,
                    filename TEXT NOT NULL,
                    original_filename TEXT NOT NULL,
                    file_size INTEGER NOT NULL,
                    file_type TEXT NOT NULL,
                    url TEXT NOT NULL,
                    user_id INTEGER,
                    views INTEGER DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                );
            ");
            
            $filesCount = count($_FILES['files']['name']);
            $maxFileSize = 100 * 1024 * 1024; // 100MB
            $allowedTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-matroska', 'video/x-flv'];
            
            for ($i = 0; $i < $filesCount; $i++) {
                // Check for upload errors
                if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
                    $errorMessages = [
                        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
                        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
                        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
                    ];
                    $uploadError = 'Upload error: ' . ($errorMessages[$_FILES['files']['error'][$i]] ?? 'Unknown error');
                    continue;
                }
                
                // Check file size
                if ($_FILES['files']['size'][$i] > $maxFileSize) {
                    $uploadError = 'File is too large (max 100MB)';
                    continue;
                }
                
                // Check file type
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $fileType = $finfo->file($_FILES['files']['tmp_name'][$i]);
                
                if (!in_array($fileType, $allowedTypes)) {
                    $uploadError = 'Invalid file type. Allowed types: mp4, webm, ogg, mov, mkv, flv';
                    continue;
                }
                
                // Generate unique filename
                $originalFilename = $_FILES['files']['name'][$i];
                $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
                $newFilename = uniqid('video_') . '_' . time() . '.' . $extension;
                $targetPath = $uploadsDir . '/' . $newFilename;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $targetPath)) {
                    // Generate URL
                    $fileUrl = 'uploads/videos/' . $newFilename;
                    
                    // Store in database
                    $stmt = $db->prepare("
                        INSERT INTO videos (title, description, filename, original_filename, file_size, file_type, url, user_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $fileTitle = !empty($title) ? $title : pathinfo($originalFilename, PATHINFO_FILENAME);
                    $stmt->execute([
                        $fileTitle,
                        $description,
                        $newFilename,
                        $originalFilename,
                        $_FILES['files']['size'][$i],
                        $fileType,
                        $fileUrl,
                        $userId
                    ]);
                    
                    $videoId = $db->lastInsertId();
                    
                    // Add to uploaded files array
                    $uploadedFiles[] = [
                        'id' => $videoId,
                        'name' => $originalFilename,
                        'size' => $_FILES['files']['size'][$i],
                        'url' => $fileUrl,
                        'embed_code' => '<video width="640" height="360" controls><source src="' . $fileUrl . '" type="' . $fileType . '"></video>'
                    ];
                    
                    $uploadSuccess = true;
                } else {
                    $uploadError = 'Failed to move uploaded file';
                }
            }
        } else {
            $uploadError = 'No files were uploaded';
        }
    }
    
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
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
        .pomf-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: var(--content-bg);
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }
        
        .pomf-title {
            text-align: center;
            color: var(--accent-secondary);
            margin-bottom: 25px;
            font-size: 28px;
        }
        
        .pomf-description {
            text-align: center;
            margin-bottom: 25px;
            color: var(--text-secondary);
        }
        
        .pomf-upload-zone {
            background-color: var(--background-color);
            border: 2px dashed var(--accent-secondary);
            padding: 40px;
            text-align: center;
            cursor: pointer;
            margin-bottom: 20px;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        
        .pomf-upload-zone:hover {
            background-color: rgba(138, 43, 226, 0.1);
        }
        
        .pomf-icon {
            font-size: 48px;
            color: var(--accent-secondary);
            margin-bottom: 15px;
        }
        
        .pomf-text {
            color: var(--text-primary);
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .pomf-subtext {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .pomf-form {
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-primary);
            font-weight: bold;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 4px;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .submit-button {
            background-color: var(--accent-secondary);
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: bold;
            border-radius: 4px;
            width: 100%;
        }
        
        .submit-button:hover {
            opacity: 0.9;
        }
        
        .upload-success {
            background-color: rgba(0, 128, 0, 0.1);
            border: 1px solid #006400;
            padding: 15px;
            margin-bottom: 20px;
            color: #00ff00;
            border-radius: 4px;
        }
        
        .upload-error {
            background-color: rgba(255, 0, 0, 0.1);
            border: 1px solid #8b0000;
            padding: 15px;
            margin-bottom: 20px;
            color: #ff6666;
            border-radius: 4px;
        }
        
        .file-list {
            margin-top: 15px;
        }
        
        .file-item {
            background-color: var(--background-color);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }
        
        .file-item h3 {
            margin-top: 0;
            color: var(--post-title);
        }
        
        .file-meta {
            margin: 10px 0;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .file-urls {
            margin-top: 15px;
            background-color: #000;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            word-break: break-all;
        }
        
        .file-url {
            display: block;
            margin-bottom: 5px;
            color: var(--accent-secondary);
        }
        
        .file-url strong {
            color: var(--text-primary);
            margin-right: 5px;
        }
        
        .pomf-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
            text-align: center;
            color: var(--text-secondary);
            font-size: 12px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--accent-secondary);
        }
        
        #file-input {
            display: none;
        }
        
        .file-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        
        .file-action-button {
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .file-action-button:hover {
            background-color: var(--hover-bg);
        }
        
        .file-action-primary {
            background-color: var(--accent-secondary);
            color: white;
        }
        
        .thumbnail-preview {
            width: 100%;
            max-height: 200px;
            object-fit: contain;
            margin-top: 10px;
            border-radius: 4px;
            background-color: #000;
        }
    </style>
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
                <h1>IP2∞ (IP2Infinity.network)</h1>
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
                
                <div class="pomf-container">
                    <h1 class="pomf-title">IP2∞ Video Upload</h1>
                    <p class="pomf-description">Upload videos and share them with the IP2 community</p>
                    
                    <?php if ($uploadError): ?>
                        <div class="upload-error">
                            <h3>Upload Error</h3>
                            <p><?php echo htmlspecialchars($uploadError); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($uploadSuccess): ?>
                        <div class="upload-success">
                            <h3>Upload Successful!</h3>
                            <p>Your files have been uploaded successfully.</p>
                        </div>
                        
                        <div class="file-list">
                            <?php foreach ($uploadedFiles as $file): ?>
                                <div class="file-item">
                                    <h3><?php echo htmlspecialchars($file['name']); ?></h3>
                                    <div class="file-meta">
                                        <span><i class="fas fa-file-video"></i> <?php echo formatFileSize($file['size']); ?></span>
                                    </div>
                                    
                                    <div class="file-urls">
                                        <div class="file-url"><strong>URL:</strong> <a href="<?php echo $file['url']; ?>" target="_blank"><?php echo htmlspecialchars($file['url']); ?></a></div>
                                        <div class="file-url"><strong>Embed:</strong> <?php echo htmlspecialchars($file['embed_code']); ?></div>
                                    </div>
                                    
                                    <div class="file-actions">
                                        <a href="<?php echo $file['url']; ?>" download class="file-action-button"><i class="fas fa-download"></i> Download</a>
                                        <a href="index.php?page=create_post&video_url=<?php echo urlencode($file['url']); ?>" class="file-action-button file-action-primary"><i class="fas fa-share"></i> Create Post</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data" class="pomf-form">
                            <input type="hidden" name="action" value="upload_video">
                            
                            <div class="pomf-upload-zone" id="drop-zone" onclick="document.getElementById('file-input').click()">
                                <div class="pomf-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="pomf-text">Drag & drop videos here or click to browse</div>
                                <div class="pomf-subtext">Max file size: 100MB. Supported formats: MP4, WebM, MOV, MKV, FLV</div>
                                <input type="file" id="file-input" name="files[]" accept="video/*" multiple>
                            </div>
                            
                            <div class="form-group">
                                <label for="title">Title (Optional)</label>
                                <input type="text" id="title" name="title" placeholder="Give your upload a title">
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description (Optional)</label>
                                <textarea id="description" name="description" placeholder="Add a description"></textarea>
                            </div>
                            
                            <button type="submit" class="submit-button">Upload Files</button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="pomf-footer">
                        <p>Files uploaded to IP2∞ are subject to our <a href="#">Terms of Service</a>. Do not upload illegal content.</p>
                    </div>
                </div>
            </main>

            <aside class="sidebar">
                <!-- Sidebar buttons -->
                <div class="sidebar-buttons">
                    <div class="button-row">
                        <a href="live.php" class="sidebar-button live-button"><span class="live-icon">⚫</span> LIVE</a>
                        <a href="#" class="sidebar-button leaderboard-button">LEADERBOARD</a>
                    </div>
                    <div class="button-row">
                        <a href="upload.php" class="sidebar-button upload-button active">UPLOAD VIDEO</a>
                    </div>
                </div>
                
                <!-- About Box -->
                <div class="about-box">
                    <h2>File Sharing Rules</h2>
                    
                    <h4 class="section-title">Allowed Content</h4>
                    <ul class="rules-list">
                        <li>Stream clips/highlights</li>
                        <li>Original content</li>
                        <li>Memes and edits</li>
                    </ul>
                    
                    <h4 class="section-title">Prohibited Content</h4>
                    <ul class="rules-list">
                        <li>Illegal material</li>
                        <li>DMCA-protected full movies/shows</li>
                        <li>Malware/viruses</li>
                    </ul>
                    
                    <h4 class="section-title">File Limits</h4>
                    <p>Maximum file size: 100MB per file</p>
                    <p>Allowed types: MP4, WebM, MOV, MKV, FLV</p>
                </div>
            </aside>
        </div>
        
        <footer>
            <p>© <?php echo date('Y'); ?> IP2∞ Network</p>
        </footer>
    </div>

    <script>
        // Drag and drop functionality
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropZone.style.backgroundColor = 'rgba(138, 43, 226, 0.1)';
            dropZone.style.borderColor = 'var(--accent-primary)';
        }
        
        function unhighlight() {
            dropZone.style.backgroundColor = 'var(--background-color)';
            dropZone.style.borderColor = 'var(--accent-secondary)';
        }
        
        dropZone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            
            // Show selected files
            updateFileInfo();
        }
        
        fileInput.addEventListener('change', updateFileInfo);
        
        function updateFileInfo() {
            const files = fileInput.files;
            let fileInfo = '';
            
            if (files.length > 0) {
                fileInfo = `<div class="pomf-text">${files.length} file(s) selected</div>`;
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    fileInfo += `<div class="pomf-subtext">${file.name} (${formatSize(file.size)})</div>`;
                }
                
                dropZone.innerHTML = `
                    <div class="pomf-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    ${fileInfo}
                    <div class="pomf-subtext">Click to change selection</div>
                `;
            }
        }
        
        function formatSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else {
                return bytes + ' bytes';
            }
        }
    </script>
</body>
</html>
