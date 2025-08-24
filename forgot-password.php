<?php
// Use Composer's autoloader
require_once 'vendor/autoload.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include your database configuration
require_once 'config.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT prn FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $otp = rand(100000, 999999);
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_expires_at'] = time() + 600; // OTP is valid for 10 minutes
            $_SESSION['reset_email'] = $email;

            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'aabbuse2@gmail.com'; // <-- REPLACE with your Gmail address
                $mail->Password   = 'bjws vwnw ptpm agir'; // <-- REPLACE with your Gmail App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                // Recipients
                $mail->setFrom('your_gmail_email@gmail.com', 'Attendance System');
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Your Password Reset OTP';
                $mail->Body    = "Hi,<br><br>Your One-Time Password (OTP) for resetting your password is: <b>$otp</b><br><br>This OTP is valid for the next 10 minutes.";

                $mail->send();
                header("Location: verify-otp.php"); // Redirect to the OTP verification page
                exit;

            } catch (Exception $e) {
                // Generic error for security
                $message = "There was an issue sending the email. Please try again later.";
            }
        } else {
            // For security, don't reveal if the email exists or not.
            // Just redirect as if it were successful.
            header("Location: verify-otp.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Using the same responsive CSS as the login page */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f6; min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        .container { display: flex; width: 100%; max-width: 1000px; min-height: 600px; background-color: #fff; border-radius: 15px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .form-panel { flex: 1.2; padding: 50px; display: flex; flex-direction: column; justify-content: center; }
        .form-panel h2 { font-size: 26px; font-weight: 700; color: #333; margin-bottom: 8px; }
        .form-panel .subtitle { color: #666; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; color: #555; margin-bottom: 8px; font-weight: 600; }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .submit-btn { width: 100%; padding: 15px; border: none; border-radius: 8px; background-color: #4a69bd; color: #fff; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 10px; }
        .footer-links { text-align: center; margin-top: 25px; font-size: 14px; }
        .footer-links a { color: #4a69bd; text-decoration: none; font-weight: 600; }
        .info-panel { flex: 1; background: linear-gradient(to bottom right, #4a69bd, #1e3a5f); color: #fff; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px; text-align: center; }
        .info-panel h1 { font-size: 32px; font-weight: 700; margin-bottom: 15px; }
        .info-panel p { font-size: 16px; line-height: 1.6; max-width: 300px; opacity: 0.9; }
        .alert-error { padding: 12px; margin-bottom: 20px; border-radius: 8px; background-color: #f8d7da; color: #721c24; }
        
        @media (max-width: 920px) { .info-panel { display: none; } .container { max-width: 500px; } }
        @media (max-width: 500px) { body { align-items: flex-start; } .container { min-height: 100vh; border-radius: 0; box-shadow: none; } .form-panel { padding: 40px 25px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-panel">
            <h2>Forgot Your Password?</h2>
            <p class="subtitle">No worries. Enter your registered email and we'll send you a 6-digit OTP to reset it.</p>
            <?php if (!empty($message)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form action="forgot-password.php" method="post" novalidate>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <button type="submit" class="submit-btn">Send OTP</button>
            </form>
            <div class="footer-links">
                <a href="index.php">&larr; Back to Login</a>
            </div>
        </div>
        <div class="info-panel">
            <h1>Password Recovery</h1>
            <p>A quick and secure way to regain access to your account.</p>
        </div>
    </div>
</body>
</html>
