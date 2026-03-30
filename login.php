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
    <style>
        body { background: linear-gradient(135deg, #0056b3, #00b0ff); height: 100vh; }
        .login-card { max-width: 420px; margin: 100px auto; }
    </style>
</head>
<body>
<?php require_once 'loading_screen.php'; ?>
<div class="container">
    <div class="login-card card shadow">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <h2 class="fw-bold text-primary">Icewind HVAC</h2>
                <p class="text-muted">Inventory Management System</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="admin" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2">Login to Dashboard</button>
            </form>

        
        </div>
        <div class="card-footer text-center text-muted py-3">
            © 2026 Icewind HVAC Corporation
        </div>
    </div>
</div>
</body>
</html>