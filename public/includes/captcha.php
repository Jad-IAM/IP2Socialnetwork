<?php
/**
 * Simple CAPTCHA generation and validation for IP2∞ forum
 */

/**
 * Generate a new CAPTCHA, store it in the session, and return the image data
 * 
 * @return string Base64 encoded CAPTCHA image
 */
function generateCaptcha() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Generate random string for CAPTCHA
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $captchaText = '';
    $length = 6; // Length of CAPTCHA text
    
    for ($i = 0; $i < $length; $i++) {
        $captchaText .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    // Store the CAPTCHA text in the session
    $_SESSION['captcha'] = $captchaText;
    
    // Create image
    $width = 200;
    $height = 60;
    $image = imagecreatetruecolor($width, $height);
    
    // Colors
    $bg = imagecolorallocate($image, 20, 20, 30); // Dark background
    $textColor = imagecolorallocate($image, 200, 100, 255); // Purple text
    $noiseColor = imagecolorallocate($image, 100, 50, 150); // Line color
    
    // Fill background
    imagefilledrectangle($image, 0, 0, $width, $height, $bg);
    
    // Add some noise (lines)
    for ($i = 0; $i < 6; $i++) {
        imageline(
            $image,
            rand(0, $width), rand(0, $height),
            rand(0, $width), rand(0, $height),
            $noiseColor
        );
    }
    
    // Add dots
    for ($i = 0; $i < 100; $i++) {
        imagesetpixel($image, rand(0, $width), rand(0, $height), $noiseColor);
    }
    
    // Add the text
    $fontSize = 22;
    $x = ($width - strlen($captchaText) * $fontSize) / 2;
    $y = ($height - $fontSize) / 2 + $fontSize;
    
    // Apply slight distortion to each character
    for ($i = 0; $i < strlen($captchaText); $i++) {
        // Randomize position slightly
        $xPos = $x + $i * $fontSize + rand(-3, 3);
        $yPos = $y + rand(-3, 3);
        
        // Add the character to the image
        imagechar($image, 5, $xPos, $yPos, $captchaText[$i], $textColor);
    }
    
    // Output the image to a buffer
    ob_start();
    imagepng($image);
    $imageData = ob_get_clean();
    
    // Free up memory
    imagedestroy($image);
    
    // Return the image as base64 encoded data
    return 'data:image/png;base64,' . base64_encode($imageData);
}

/**
 * Validate the CAPTCHA against the stored session value
 * 
 * @param string $userInput User submitted CAPTCHA text
 * @return bool True if CAPTCHA is valid, false otherwise
 */
function validateCaptcha($userInput) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if CAPTCHA is set in session
    if (!isset($_SESSION['captcha'])) {
        return false;
    }
    
    // Compare user input with stored CAPTCHA (case insensitive)
    $isValid = (strtolower($userInput) === strtolower($_SESSION['captcha']));
    
    // Clear the CAPTCHA from session to prevent reuse
    unset($_SESSION['captcha']);
    
    return $isValid;
}
?>
