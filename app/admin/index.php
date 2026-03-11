<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getConnection();
    $contact = $_POST['contact_number'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare(
        "SELECT u.*, ut.type_name FROM tbl_user u
         JOIN tbl_user_type ut ON u.user_type_id = ut.user_type_id
         WHERE u.contact_number = ? AND ut.type_name = 'Admin'"
    );
    $stmt->bind_param("s", $contact);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['user_id'];
            $_SESSION['admin_name'] = $admin['fname'] . ' ' . $admin['lname'];
            $_SESSION['admin_type'] = $admin['type_name'];
            header('Location: dashboard.php');
            exit;
        }
    }
    $error = 'Invalid credentials or not an admin account.';
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RiceGuard Admin — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #1B5E20 0%, #4CAF50 50%, #81C784 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 24px;
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1B5E20, #4CAF50);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .logo-circle i { font-size: 36px; color: white; }
        h1 { text-align: center; font-size: 28px; font-weight: 800; color: #1A1A2E; }
        .subtitle { text-align: center; color: #666; margin: 8px 0 30px; font-size: 14px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; color: #444; margin-bottom: 6px; }
        .input-wrap {
            display: flex;
            align-items: center;
            background: #F5F7F5;
            border: 1px solid #E0E5E0;
            border-radius: 12px;
            padding: 0 16px;
            height: 50px;
            transition: border-color 0.2s;
        }
        .input-wrap:focus-within { border-color: #4CAF50; background: white; }
        .input-wrap i { color: #999; margin-right: 10px; font-size: 18px; }
        .input-wrap input {
            flex: 1;
            border: none;
            background: none;
            outline: none;
            font-size: 14px;
            font-family: inherit;
            color: #333;
        }
        .error-msg {
            background: #FFEBEE;
            color: #C62828;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .login-btn {
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, #1B5E20, #4CAF50);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            margin-top: 8px;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }
        .login-btn:active { transform: translateY(0); }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo-circle"><i class="ri-leaf-fill"></i></div>
        <h1>RiceGuard</h1>
        <p class="subtitle">Admin Dashboard Login</p>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><i class="ri-error-warning-fill"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Contact Number</label>
                <div class="input-wrap">
                    <i class="ri-phone-fill"></i>
                    <input type="text" name="contact_number" placeholder="Enter your contact number" required>
                </div>
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="input-wrap">
                    <i class="ri-lock-fill"></i>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
            </div>
            <button type="submit" class="login-btn">Sign In</button>
        </form>
    </div>
</body>
</html>
