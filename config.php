<?php
/**
 * Icewind HVAC Inventory System - Configuration
 */

// Google Sheets Configuration
define('INVENTORY_FILE', 'inventory.json');
define('CONSUMABLES_FILE', 'consumables.json');
define('ACCESSORIES_FILE', 'accessories.json');

// Admin credentials
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', password_hash('admin123', PASSWORD_DEFAULT));   // default password
define('ADMIN_PASSWORD_HASH', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); 
define('ADMIN_EMAIL', 'admin@icewindhvac.com');

// SMTP Configuration (for PHPMailer)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_FROM', 'noreply@icewindhvac.com');

// App URL
define('APP_URL', 'http://localhost/icewind-inventory');

// Session Start
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>