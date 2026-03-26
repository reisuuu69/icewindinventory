<?php
/**
 * Icewind HVAC Inventory System - Reset Password
 */

require_once 'config.php';
require_once 'functions.php';

$token = $_GET['token'] ?? '';
$auth = read_json(DB_AUTH);

if (!$token || $auth['reset_token'] !== $token || time() > $auth['token_expiry']) {
    die("Invalid or expired reset token.");
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === $confirmPassword) {
        $auth['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $auth['reset_token'] = null;
        $auth['token_expiry'] = null;
        write_json(DB_AUTH, $auth);
        
        $message = "Password reset successful! <br><br> <a href='login.php' class='btn btn-sm btn-primary'>Go to Login</a>";
    } else {
        $error = "Passwords do not match.";
    }
}

render_header('Reset Password');
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow border-0 p-4">
                <div class="card-body">
                    <h4 class="card-title fw-bold mb-4">New Password</h4>
                    <p class="text-muted small mb-4">Enter your new password below.</p>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success py-2 small"><?php echo $message; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form action="reset.php?token=<?php echo $token; ?>" method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i data-lucide="lock" style="width: 16px;"></i></span>
                                <input type="password" class="form-control border-start-0" name="new_password" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i data-lucide="lock" style="width: 16px;"></i></span>
                                <input type="password" class="form-control border-start-0" name="confirm_password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Update Password</button>
                    </form>
                    <div class="text-center mt-4">
                        <a href="login.php" class="small text-decoration-none">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
