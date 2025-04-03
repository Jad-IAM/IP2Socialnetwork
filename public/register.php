<?php
// Include database connection and captcha
require_once(__DIR__ . '/includes/database.php');
require_once(__DIR__ . '/includes/captcha.php');

// Connect to SQLite database
try {
    $db = getDatabase();
    
    // Simple session management
    session_start();
    
    $registerError = '';
    $registerSuccess = false;
    
    // Handle register
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $email = $_POST['email'] ?? '';
        $captchaInput = $_POST['captcha'] ?? '';
        
        // Validate captcha
        if (!validateCaptcha($captchaInput)) {
            $registerError = "Invalid CAPTCHA. Please try again.";
        } elseif (strlen($username) < 3) {
            $registerError = "Username must be at least 3 characters";
        } elseif (strlen($password) < 6) {
            $registerError = "Password must be at least 6 characters";
        } else {
            // Check if username already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $registerError = "Username already taken";
            } else {
                // Check if email already exists
                if (!empty($email)) {
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $registerError = "Email already in use";
                    }
                }
                
                if (empty($registerError)) {
                    // Generate random avatar number
                    $avatarNum = rand(1, 5);
                    $avatar = "avatar{$avatarNum}.svg";
                    
                    // Create user
                    $stmt = $db->prepare("INSERT INTO users (username, password, email, avatar) VALUES (?, ?, ?, ?)");
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->execute([$username, $hashedPassword, $email, $avatar]);
                    
                    $registerSuccess = true;
                }
            }
        }
    }
    
    // Generate a new CAPTCHA image
    $captchaImage = generateCaptcha();
    
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join IP2∞ - Register</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .auth-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .auth-box {
            background-color: var(--content-bg);
            border-radius: 4px;
            padding: 20px;
            border: 1px solid var(--border-color);
        }
        
        .auth-title {
            color: var(--accent-secondary);
            margin-bottom: 20px;
            font-size: 24px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
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
        
        .form-group input {
            width: 100%;
            padding: 10px;
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 4px;
        }
        
        .auth-button {
            background-color: var(--accent-secondary);
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: bold;
            border-radius: 4px;
            width: 100%;
        }
        
        .auth-button:hover {
            opacity: 0.9;
        }
        
        .auth-error {
            background-color: rgba(255, 0, 0, 0.1);
            border: 1px solid #8b0000;
            padding: 15px;
            margin-bottom: 20px;
            color: #ff6666;
            border-radius: 4px;
        }
        
        .auth-success {
            background-color: rgba(0, 128, 0, 0.1);
            border: 1px solid #006400;
            padding: 15px;
            margin-bottom: 20px;
            color: #00ff00;
            border-radius: 4px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--accent-secondary);
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .form-footer {
            margin-top: 20px;
            text-align: center;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .captcha-container {
            margin-bottom: 20px;
        }
        
        .captcha-image {
            display: block;
            margin-bottom: 10px;
            width: 200px;
            height: 60px;
            border-radius: 4px;
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
                    <a href="login.php" class="member-button">Login</a>
                </div>
            </nav>
        </header>

        <div class="content-wrapper">
            <main class="main-content" style="max-width: 100%;">
                <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
                <a href="login.php" class="back-link" style="margin-left: 20px;"><i class="fas fa-sign-in-alt"></i> Login</a>
                
                <div class="auth-container">
                    <!-- Register Form -->
                    <div class="auth-box">
                        <h2 class="auth-title">Join IP2∞</h2>
                        
                        <?php if ($registerError): ?>
                            <div class="auth-error">
                                <p><?php echo htmlspecialchars($registerError); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($registerSuccess): ?>
                            <div class="auth-success">
                                <h3>Registration Successful!</h3>
                                <p>Your account has been created. You can now <a href="login.php" style="color: #00ff00; text-decoration: underline;">login</a> with your credentials.</p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$registerSuccess): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="register">
                                
                                <div class="form-group">
                                    <label for="register-username">Username</label>
                                    <input type="text" id="register-username" name="username" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="register-email">Email (optional)</label>
                                    <input type="email" id="register-email" name="email">
                                </div>
                                
                                <div class="form-group">
                                    <label for="register-password">Password</label>
                                    <input type="password" id="register-password" name="password" required>
                                </div>
                                
                                <div class="captcha-container">
                                    <label for="captcha">Verification Code (CAPTCHA)</label>
                                    <img src="<?php echo $captchaImage; ?>" alt="CAPTCHA" class="captcha-image">
                                    <input type="text" id="captcha" name="captcha" placeholder="Enter the code shown above" required>
                                </div>
                                
                                <button type="submit" class="auth-button">Create Account</button>
                                
                                <div class="form-footer">
                                    <p>By creating an account, you agree to our Terms of Service and Privacy Policy.</p>
                                    <p>Already have an account? <a href="login.php" style="color: var(--accent-secondary);">Login here</a></p>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
        
        <footer>
            <p>© <?php echo date('Y'); ?> IP2∞ (IP2.Social)</p>
        </footer>
    </div>
</body>
</html>
