<?php
// ==================== ICEWIND HVAC INVENTORY - LOGIN ====================
session_start();

require_once 'config.php';
require_once 'functions.php';


// Redirect to dashboard if already logged in
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}

// Handle login form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD)) {
        $_SESSION['user'] = [
            'username' => $username,
            'role' => 'admin'
        ];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Incorrect username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Icewind HVAC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/login.css" rel="stylesheet">
    <link href="css/loading_screen.css" rel="stylesheet">
</head>
<body class="iw-login-page">
<?php require_once 'loading_screen.php'; ?>
<div class="container iw-login-wrap">
    <div class="login-card">
        <div class="login-card__inner">
            <div class="text-center mb-4">
                <div class="iw-login-brand">
                    <img src="logo.gif" alt="IceWind" class="iw-login-brand__logo" decoding="async">
                    <div class="iw-login-brand__tag">Inventory System</div>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger iw-login-alert"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label iw-login-label">Username</label>
                    <input type="text" name="username" class="form-control iw-login-input" value="admin" required>
                </div>
                <div class="mb-3">
                    <label class="form-label iw-login-label">Password</label>
                    <input type="password" name="password" class="form-control iw-login-input" required>
                </div>
                <button type="submit" class="btn btn-primary iw-btn-gradient w-100 py-2 rounded-pill">Login to Dashboard</button>
            </form>
        </div>
        <div class="login-card__footer">
            © 2026 Icewind HVAC Corporation
        </div>
    </div>
</div>
</body>
</html>