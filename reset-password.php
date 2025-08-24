<?php
require_once 'config.php';
$message = "";
$success = false;

// Check if OTP was verified, otherwise redirect back to the start
if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
    header("Location: forgot-password.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
    } else {
        // Hash the new password securely
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update the user's password in the database
        $email = $_SESSION['reset_email'];
        $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt_update->bind_param("ss", $hashed_password, $email);
        
        if ($stmt_update->execute()) {
            $message = "Your password has been successfully reset! You can now log in.";
            $success = true;
            // Clean up session variables after successful reset
            unset($_SESSION['otp_verified'], $_SESSION['reset_email'], $_SESSION['otp']);
        } else {
            $message = "An error occurred. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* This CSS is identical to login.php for perfect consistency */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f6; min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        .container { display: flex; width: 100%; max-width: 1000px; min-height: 600px; background-color: #fff; border-radius: 15px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .form-panel { flex: 1.2; padding: 50px; display: flex; flex-direction: column; justify-content: center; }
        .form-panel h2 { font-size: 26px; font-weight: 700; color: #333; margin-bottom: 8px; }
        .form-panel .subtitle { color: #666; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; position: relative; }
        .form-group label { display: block; font-size: 13px; color: #555; margin-bottom: 8px; font-weight: 600; }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; font-family: 'Poppins', sans-serif; padding-right: 50px; }
        .password-toggle { position: absolute; top: 38px; right: 10px; background: none; border: none; cursor: pointer; color: #888; font-size: 12px; font-weight: 600; }
        .submit-btn { width: 100%; padding: 15px; border: none; border-radius: 8px; background-color: #4a69bd; color: #fff; font-size: 16px; font-weight: 700; cursor: pointer; transition: background-color 0.3s; margin-top: 10px; }
        .submit-btn:hover { background-color: #3b5998; }
        .footer-links { text-align: center; margin-top: 25px; font-size: 14px; }
        .footer-links a { color: #4a69bd; text-decoration: none; font-weight: 600; }
        .info-panel { flex: 1; background: linear-gradient(to bottom right, #4a69bd, #1e3a5f); color: #fff; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px; text-align: center; }
        .info-panel h1 { font-size: 32px; font-weight: 700; margin-bottom: 15px; line-height: 1.3; }
        .info-panel p { font-size: 16px; line-height: 1.6; max-width: 300px; opacity: 0.9; }
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 8px; font-size: 14px; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-error { background-color: #f8d7da; color: #721c24; }
        
        @media (max-width: 920px) { .info-panel { display: none; } .container { max-width: 500px; } }
        @media (max-width: 500px) { body { align-items: flex-start; padding:0; } .container { min-height: 100vh; width:100%; max-width:100%; border-radius: 0; box-shadow: none; } .form-panel { padding: 40px 25px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-panel">
            <h2>Set a New Password</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <div class="footer-links">
                    <a href="index.php">Proceed to Login</a>
                </div>
            <?php else: ?>
                <p class="subtitle">Your new password must be at least 6 characters long.</p>
                <?php if (!empty($message)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <form action="reset-password.php" method="post" novalidate>
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password', this)">Show</button>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">Show</button>
                    </div>
                    <button type="submit" class="submit-btn">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="info-panel">
            <h1>Almost There!</h1>
            <p>Secure your account with a new, strong password and get back to managing attendance.</p>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, toggleButton) {
            const passwordField = document.getElementById(fieldId);
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            toggleButton.textContent = type === 'password' ? 'Show' : 'Hide';
        }
    </script>
</body>
</html>
