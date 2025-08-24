<?php
// The password you want to create a hash for
$password = 'admin007';

// Generate a new, secure hash using your server's PHP
$hash = password_hash($password, PASSWORD_DEFAULT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Hash Generator</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { width: 100%; max-width: 800px; background: #fff; padding: 40px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        h1 { text-align: center; margin-bottom: 20px; }
        .hash-output {
            background-color: #e9ecef;
            padding: 20px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 16px;
            word-wrap: break-word;
            border: 1px solid #dee2e6;
            text-align: center;
        }
        p { margin-top: 15px; text-align: center; color: #555; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Generated Hash for 'admin007'</h1>
        <p>Copy the entire hash string below. It is guaranteed to be correct for your system.</p>
        <div class="hash-output">
            <?php echo htmlspecialchars($hash); ?>
        </div>
    </div>
</body>
</html>
