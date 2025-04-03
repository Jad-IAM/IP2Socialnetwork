<?php
/**
 * Simple mailer class for IP2∞ forum
 */
class Mailer {
    private $fromEmail;
    private $fromName;
    
    /**
     * Constructor
     * 
     * @param string $fromEmail From email address
     * @param string $fromName From name
     */
    public function __construct($fromEmail = 'noreply@ip2infinity.social', $fromName = 'IP2∞ Forum') {
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }
    
    /**
     * Send email
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string $plainBody Plain text version of the body
     * @return bool True if email sent successfully, false otherwise
     */
    public function send($to, $subject, $body, $plainBody = '') {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . $this->fromEmail,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // If we're in a production environment, send the email
        if ($_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1') {
            return mail($to, $subject, $body, implode("\r\n", $headers));
        }
        
        // For development, log the email instead of sending it
        $logFile = __DIR__ . '/mail_log.txt';
        $logContent = "=== Email Log: " . date('Y-m-d H:i:s') . " ===\n";
        $logContent .= "To: $to\n";
        $logContent .= "Subject: $subject\n";
        $logContent .= "Headers: \n" . implode("\n", $headers) . "\n";
        $logContent .= "Body: \n$body\n";
        $logContent .= "Plain Body: \n$plainBody\n";
        $logContent .= "=============================================\n\n";
        
        file_put_contents($logFile, $logContent, FILE_APPEND);
        
        return true;
    }
    
    /**
     * Send verification email
     * 
     * @param string $to Recipient email
     * @param string $username Username
     * @param string $verificationToken Verification token
     * @return bool True if email sent successfully, false otherwise
     */
    public function sendVerificationEmail($to, $username, $verificationToken) {
        $subject = 'Verify your IP2∞ Forum account';
        
        $verificationUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/verify.php?token=' . $verificationToken;
        
        $body = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #8a2be2; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background-color: #f9f9f9; }
                    .button { display: inline-block; background-color: #8a2be2; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px; }
                    .footer { font-size: 12px; color: #777; margin-top: 20px; text-align: center; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>Welcome to IP2∞ Forum!</h1>
                    </div>
                    <div class="content">
                        <p>Hello ' . htmlspecialchars($username) . ',</p>
                        <p>Thank you for registering at IP2∞ Forum. To complete your registration, please verify your email address by clicking the button below:</p>
                        <p style="text-align: center;">
                            <a href="' . $verificationUrl . '" class="button">Verify Email</a>
                        </p>
                        <p>If the button doesn\'t work, you can also copy and paste the following link into your browser:</p>
                        <p>' . $verificationUrl . '</p>
                        <p>This link will expire in 24 hours.</p>
                        <p>If you did not register for an account, you can ignore this email.</p>
                    </div>
                    <div class="footer">
                        <p>© ' . date('Y') . ' IP2∞ Forum. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ';
        
        $plainBody = "Hello " . $username . ",\n\n";
        $plainBody .= "Thank you for registering at IP2∞ Forum. To complete your registration, please verify your email address by visiting the following link:\n\n";
        $plainBody .= $verificationUrl . "\n\n";
        $plainBody .= "This link will expire in 24 hours.\n\n";
        $plainBody .= "If you did not register for an account, you can ignore this email.\n\n";
        $plainBody .= "© " . date('Y') . " IP2∞ Forum. All rights reserved.";
        
        return $this->send($to, $subject, $body, $plainBody);
    }
    
    /**
     * Send password reset email
     * 
     * @param string $to Recipient email
     * @param string $username Username
     * @param string $resetToken Reset token
     * @return bool True if email sent successfully, false otherwise
     */
    public function sendPasswordResetEmail($to, $username, $resetToken) {
        $subject = 'Reset your IP2∞ Forum password';
        
        $resetUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/reset_password.php?token=' . $resetToken;
        
        $body = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #8a2be2; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background-color: #f9f9f9; }
                    .button { display: inline-block; background-color: #8a2be2; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px; }
                    .footer { font-size: 12px; color: #777; margin-top: 20px; text-align: center; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>IP2∞ Forum Password Reset</h1>
                    </div>
                    <div class="content">
                        <p>Hello ' . htmlspecialchars($username) . ',</p>
                        <p>We received a request to reset your password for your IP2∞ Forum account. To reset your password, please click the button below:</p>
                        <p style="text-align: center;">
                            <a href="' . $resetUrl . '" class="button">Reset Password</a>
                        </p>
                        <p>If the button doesn\'t work, you can also copy and paste the following link into your browser:</p>
                        <p>' . $resetUrl . '</p>
                        <p>This link will expire in 1 hour.</p>
                        <p>If you did not request a password reset, you can ignore this email.</p>
                    </div>
                    <div class="footer">
                        <p>© ' . date('Y') . ' IP2∞ Forum. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ';
        
        $plainBody = "Hello " . $username . ",\n\n";
        $plainBody .= "We received a request to reset your password for your IP2∞ Forum account. To reset your password, please visit the following link:\n\n";
        $plainBody .= $resetUrl . "\n\n";
        $plainBody .= "This link will expire in 1 hour.\n\n";
        $plainBody .= "If you did not request a password reset, you can ignore this email.\n\n";
        $plainBody .= "© " . date('Y') . " IP2∞ Forum. All rights reserved.";
        
        return $this->send($to, $subject, $body, $plainBody);
    }
    
    /**
     * Send private message notification
     * 
     * @param string $to Recipient email
     * @param string $username Recipient username
     * @param string $senderUsername Sender username
     * @return bool True if email sent successfully, false otherwise
     */
    public function sendPrivateMessageNotification($to, $username, $senderUsername) {
        $subject = 'New private message on IP2∞ Forum';
        
        $messageUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/messages.php';
        
        $body = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #8a2be2; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background-color: #f9f9f9; }
                    .button { display: inline-block; background-color: #8a2be2; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px; }
                    .footer { font-size: 12px; color: #777; margin-top: 20px; text-align: center; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>New Private Message</h1>
                    </div>
                    <div class="content">
                        <p>Hello ' . htmlspecialchars($username) . ',</p>
                        <p>You have received a new private message from <strong>' . htmlspecialchars($senderUsername) . '</strong> on IP2∞ Forum.</p>
                        <p style="text-align: center;">
                            <a href="' . $messageUrl . '" class="button">View Message</a>
                        </p>
                        <p>If the button doesn\'t work, you can also copy and paste the following link into your browser:</p>
                        <p>' . $messageUrl . '</p>
                    </div>
                    <div class="footer">
                        <p>© ' . date('Y') . ' IP2∞ Forum. All rights reserved.</p>
                        <p>You can manage your email preferences in your <a href="https://' . $_SERVER['HTTP_HOST'] . '/profile.php">account settings</a>.</p>
                    </div>
                </div>
            </body>
            </html>
        ';
        
        $plainBody = "Hello " . $username . ",\n\n";
        $plainBody .= "You have received a new private message from " . $senderUsername . " on IP2∞ Forum.\n\n";
        $plainBody .= "To view this message, please visit:\n";
        $plainBody .= $messageUrl . "\n\n";
        $plainBody .= "© " . date('Y') . " IP2∞ Forum. All rights reserved.\n";
        $plainBody .= "You can manage your email preferences in your account settings: https://" . $_SERVER['HTTP_HOST'] . "/profile.php";
        
        return $this->send($to, $subject, $body, $plainBody);
    }
}
?>
