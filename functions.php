<?php
/**
 * Icewind HVAC Inventory System 
 */

// REMOVE or COMMENT this line:
// session_start();

// Load config
require_once 'config.php';
// ====================== JSON DATABASE FUNCTIONS ======================
function read_json($filename) {
    if (!file_exists($filename)) {
        return [];
    }
    $json = file_get_contents($filename);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function write_json($filename, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filename, $json) !== false;
}

// ====================== AUTH & HELPER FUNCTIONS ======================
function check_auth() {
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit();
    }
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

// Simple ID generator
function generate_id($data) {
    if (empty($data)) return 1;
    $max_id = 0;
    foreach ($data as $item) {
        if (isset($item['id']) && $item['id'] > $max_id) {
            $max_id = $item['id'];
        }
    }
    return $max_id + 1;
}

// ====================== RENDER FUNCTIONS (meron ka na ito) ======================
function render_header($title = 'Icewind HVAC') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $title; ?> - Icewind HVAC</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://unpkg.com/lucide@latest"></script>
        <style>
            :root { --hvac-blue: #0056b3; --hvac-light-blue: #e7f1ff; }
            body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
            .navbar { background-color: var(--hvac-blue); }
            .sidebar { min-height: calc(100vh - 56px); background-color: white; border-right: 1px solid #dee2e6; }
            .sidebar .nav-link { color: #333; padding: 1rem; border-bottom: 1px solid #f1f1f1; transition: all 0.2s; }
            .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: var(--hvac-light-blue); color: var(--hvac-blue); }
            .card-stat { border-left: 4px solid var(--hvac-blue); }
            .btn-primary { background-color: var(--hvac-blue); border-color: var(--hvac-blue); }
            .table-hover tbody tr:hover { background-color: #f1f8ff; }
        </style>
    </head>
    <body>
    <?php if (isset($_SESSION['user'])): ?>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i data-lucide="wind" class="me-2"></i>Icewind HVAC
            </a>
            <div class="ms-auto d-flex align-items-center">
                <span class="text-white me-3 d-none d-md-inline">Welcome, <?php echo htmlspecialchars($_SESSION['user']['username']); ?></span>
                <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse shadow-sm">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php"><i data-lucide="layout-dashboard" class="me-2"></i>Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>" href="inventory.php"><i data-lucide="package" class="me-2"></i>Inventory</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'consumables.php' ? 'active' : ''; ?>" href="consumables.php"><i data-lucide="droplet" class="me-2"></i>Consumables</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'accessories.php' ? 'active' : ''; ?>" href="accessories.php"><i data-lucide="settings" class="me-2"></i>Accessories</a></li>
                    </ul>
                </div>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <?php endif;
}

function render_footer() {
    ?>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>lucide.createIcons();</script>
    </body>
    </html>
    <?php
}

function export_to_csv($filename, $data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $output = fopen('php://output', 'w');
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit();
}
?>