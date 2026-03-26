<?php
/**
 * Icewind HVAC Inventory System - Forgot Password
 */

require_once 'config.php';
require_once 'functions.php';

// PHPMailer Integration (Requires PHPMailer library to be present in /phpmailer folder)
/*
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
*/

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if ($email === ADMIN_EMAIL) {
        // In a real system, generate a token and store it in Google Sheets
        // For this lightweight version, we simulate sending a link
        $resetLink = APP_URL . "/reset.php?token=" . bin2hex(random_bytes(16));
        
        // Simulation of sending email
        $message = "A password reset link has been sent to your email (Simulated). <br><br> <a href='$resetLink' class='btn btn-sm btn-outline-primary'>Click here to reset (Demo)</a>";
        
        /* PHPMailer Implementation:
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            $mail->setFrom(SMTP_FROM, 'Icewind HVAC Admin');
            $mail->addAddress(ADMIN_EMAIL);

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - Icewind HVAC';
            $mail->Body    = "Click the link to reset your password: <a href='$resetLink'>$resetLink</a>";

            $mail->send();
            $message = "A password reset link has been sent to your email.";
        } catch (Exception $e) {
            $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
        */
    } else {
        $error = "Email address not recognized.";
    }
}

render_header('Forgot Password');
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow border-0 p-4">
                <div class="card-body">
                    <h4 class="card-title fw-bold mb-4">Reset Password</h4>
                    <p class="text-muted small mb-4">Enter your admin email address to receive a password reset link.</p>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success py-2 small"><?php echo $message; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form action="forgot.php" method="POST">
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i data-lucide="mail" style="width: 16px;"></i></span>
                                <input type="email" class="form-control border-start-0" name="email" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Send Reset Link</button>
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
