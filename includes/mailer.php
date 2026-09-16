<?php
// =============================================
// EMAIL FUNCTIONS (Using PHP mail())
// For production, use PHPMailer + SMTP
// =============================================

/**
 * Send email using PHP mail()
 * For production, install PHPMailer: composer require phpmailer/phpmailer
 */
function sendEmail($to, $subject, $body, $isHtml = true) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: " . ($isHtml ? "text/html" : "text/plain") . "; charset=UTF-8\r\n";
    $headers .= "From: " . SITE_NAME . " <" . SITE_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SITE_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return @mail($to, $subject, $body, $headers);
}

/**
 * Notify a user about a completed system update.
 */
function sendUpdateNotification($email, $name, $subject, $message) {
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $safeName = htmlspecialchars($name ?: 'there', ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $body = "
    <html>
    <body style='font-family: Arial, sans-serif; background: #f3f4f6; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; padding: 30px;'>
            <h2 style='color: #4f46e5;'>" . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . "</h2>
            <p>Hi <strong>$safeName</strong>,</p>
            <p>$safeMessage</p>
            <p style='color: #6b7280; font-size: 14px;'>This update was made in " . htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') . ".</p>
        </div>
    </body>
    </html>
    ";

    return sendEmail($email, $subject . ' - ' . SITE_NAME, $body);
}

/**
 * Notify all active administrators.
 */
function notifyAdministrators($subject, $message) {
    global $conn;

    $result = mysqli_query($conn, "SELECT full_name, email FROM users WHERE role = 'admin' AND status = 'active'");
    if (!$result) {
        return false;
    }

    $sent = false;
    while ($admin = mysqli_fetch_assoc($result)) {
        $sent = sendUpdateNotification($admin['email'], $admin['full_name'], $subject, $message) || $sent;
    }

    return $sent;
}

/**
 * Send verification email
 */
function sendVerificationEmail($user) {
    $link = BASE_URL . "verify.php?token=" . $user['verification_token'];
    
    $subject = "Verify Your Email - " . SITE_NAME;
    
    $body = "
    <html>
    <body style='font-family: Arial, sans-serif; background: #f3f4f6; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; padding: 30px;'>
            <h2 style='color: #4f46e5;'>Welcome to " . SITE_NAME . "!</h2>
            <p>Hi <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
            <p>Thank you for registering. Please verify your email address by clicking the button below:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$link' style='background: #4f46e5; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;'>
                    ✓ Verify Email
                </a>
            </div>
            <p style='color: #6b7280; font-size: 14px;'>Or copy this link:</p>
            <p style='color: #6b7280; font-size: 12px; word-break: break-all;'>$link</p>
            <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;'>
            <p style='color: #9ca3af; font-size: 12px;'>
                If you didn't create this account, you can ignore this email.
            </p>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($user) {
    $link = BASE_URL . "reset_password.php?token=" . $user['reset_token'];
    
    $subject = "Reset Your Password - " . SITE_NAME;
    
    $body = "
    <html>
    <body style='font-family: Arial, sans-serif; background: #f3f4f6; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; padding: 30px;'>
            <h2 style='color: #4f46e5;'>Password Reset Request</h2>
            <p>Hi <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
            <p>We received a request to reset your password. Click the button below to create a new password:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$link' style='background: #4f46e5; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;'>
                    🔐 Reset Password
                </a>
            </div>
            <p style='color: #6b7280; font-size: 14px;'>This link expires in 1 hour.</p>
            <p style='color: #6b7280; font-size: 12px; word-break: break-all;'>$link</p>
            <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;'>
            <p style='color: #9ca3af; font-size: 12px;'>
                If you didn't request this, ignore this email. Your password won't change.
            </p>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($user['email'], $subject, $body);
}
?>