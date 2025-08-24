<?php
require_once 'config.php';
$message = "";

// Check if the user should be on this page
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot-password.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_otp = $_POST['otp'] ?? '';
    
    if (empty($submitted_otp)) {
        $message = "Please enter the OTP.";
    } elseif (!isset($_SESSION['otp']) || time() > $_SESSION['otp_expires_at']) {
        $message = "The OTP has expired. Please request a new one.";
        unset($_SESSION['otp'], $_SESSION['otp_expires_at'], $_SESSION['reset_email']);
    } elseif ($submitted_otp != $_SESSION['otp']) {
        $message = "Invalid OTP. Please try again.";
    } else {
        $_SESSION['otp_verified'] = true;
        unset($_SESSION['otp'], $_SESSION['otp_expires_at']); // Clean up
        header("Location: reset-password.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- IMPROVED CSS --- */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 450px;
            background: #fff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        /* Typography */
        h2 {
            text-align: center;
            margin-bottom: 10px;
            font-weight: 700;
            color: #333;
        }
        p.subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 15px;
            line-height: 1.5;
        }

        /* Form styling */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            text-align: center;
            margin-bottom: 12px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }
        
        /* Specific style for the OTP input field */
        .otp-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 12px;
            padding-left: 27px; /* Fine-tunes centering due to letter-spacing */
        }
        .otp-input:focus {
            border-color: #4a69bd;
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 105, 189, 0.2);
        }

        /* Complete and consistent button styling */
        .submit-btn {
            width: 100%;
            padding: 14px 15px; /* Consistent with other pages */
            font-size: 15px;     /* Consistent with other pages */
            border: 1px solid transparent;
            border-radius: 8px;
            background-color: #4a69bd;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .submit-btn:hover {
            background-color: #3b5998;
        }

        /* Alert box for errors */
        .alert-error {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Link styling */
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        .login-link a {
            color: #4a69bd;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Enter Your OTP</h2>
        <p class="subtitle">A 6-digit code has been sent to the email address:<br><b><?php echo htmlspecialchars($_SESSION['reset_email']); ?></b></p>
        
        <?php if (!empty($message)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <form action="verify-otp.php" method="post" novalidate>
            <div class="form-group">
                <label for="otp">One-Time Password</label>
                <input type="text" id="otp" name="otp" class="otp-input" required maxlength="6" autocomplete="one-time-code">
            </div>
            <button type="submit" class="submit-btn">Verify & Proceed</button>
        </form>
        
        <p class="login-link"><a href="forgot-password.php">Request a new code</a></p>
    </div>
</body>
</html>
