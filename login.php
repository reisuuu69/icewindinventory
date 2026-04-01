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
 require_once 'loading_screen.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.9.6/lottie.min.js"></script>

<style>
    #animationContainer {
        width: 250px;
        height: 300px;
        margin: 0 auto 50px auto;
    }
</style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Icewind HVAC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
    min-height: 100vh;
    margin: 0;
    background: linear-gradient(135deg, #0f172a, #1e293b, #0ea5e9);
    background-size: 200% 200%;
    animation: gradientMove 12s ease infinite;

    display: flex;
}

/* 🔥 NEW: Split Layout Wrapper */
.main-wrapper {
    display: flex;
    width: 100%;
    height: 100vh;
}

/* LEFT SIDE (Animation) */
.animation-side {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* RIGHT SIDE (Login Centered) */
.form-side {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Gradient animation */
@keyframes gradientMove {
    0% { background-position: 0% }
    50% { background-position: 100% }
    100% { background-position: 0% }
}

/* REMOVE old container behavior */
.container {
    display: none;
}

/* Glass Card */
.login-card {
    width: 100%;
    max-width: 400px;
    border-radius: 20px;
    backdrop-filter: blur(18px);
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255,255,255,0.12);
    box-shadow: 
        0 25px 60px rgba(0,0,0,0.5),
        inset 0 0 20px rgba(255,255,255,0.05);
    animation: fadeUp 0.8s ease;
}

/* Entrance animation */
@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.card-body {
    color: #fff;
}

/* Logo */
.logo {
    width: 240px;
    height: auto;
    object-fit: contain;
    filter: drop-shadow(0 0 12px rgba(14,165,233,0.5));
}

/* Inputs */
.form-control {
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.08);
    padding: 12px;
    background: rgba(255,255,255,0.08);
    color: #fff;
    transition: 0.3s;
}

.form-control:focus {
    background: rgba(255,255,255,0.15);
    box-shadow: 0 0 0 2px #0ea5e9, 0 0 15px rgba(14,165,233,0.5);
}

/* Button */
.btn-primary {
    background: linear-gradient(135deg, #0ea5e9, #2563eb);
    border: none;
    border-radius: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px) scale(1.01);
    box-shadow: 0 10px 30px rgba(14,165,233,0.4);
}

/* Error */
.alert-danger {
    border-radius: 12px;
    background: rgba(239, 68, 68, 0.15);
    color: #fecaca;
    border: 1px solid rgba(239,68,68,0.3);
}

/* Footer */
.card-footer {
    background: transparent;
    border-top: 1px solid rgba(255,255,255,0.08);
    color: #94a3b8 !important;
}

/* Links */
a {
    color: #7dd3fc;
}

/* 🔥 Animation size (LEFT SIDE NOW) */
#animationContainer {
    width: 500px;
    height: 400px;
}

/* 📱 Responsive */
@media (max-width: 768px) {
    .main-wrapper {
        flex-direction: column;
    }

    .animation-side {
        height: 400px;
    }

    #animationContainer {
        width: 300px;
        height: 300px;
    }
}
    </style>
</head>
<body>
<div class="main-wrapper">

    <!-- LEFT: Animation -->
    <div class="animation-side">
        <div id="animationContainer"></div>
    </div>

    <!-- RIGHT: Login -->
    <div class="form-side">
        <div class="login-card card shadow">
            <div class="card-body p-5">

                <div class="text-center mb-4">
                    <img src="logo.gif" alt="Icewind HVAC Logo" class="logo mb-3">
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">
                        Login to Dashboard
                    </button>
                </form>

                <div class="text-center mt-3">
                    <small><a href="forgot.php">Forgot Password?</a></small>
                </div>

            </div>

            <div class="card-footer text-center text-muted py-3">
                © 2026 Icewind HVAC Corporation
            </div>
        </div>
    </div>

</div>

<script>
lottie.loadAnimation({
    container: document.getElementById('animationContainer'),
    renderer: 'svg',
    loop: true,
    autoplay: true,
    path: 'https://assets2.lottiefiles.com/packages/lf20_3rwasyjy.json'
});
</script>

</body>
</html>