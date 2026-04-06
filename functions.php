<?php
/**
 * Icewind HVAC Inventory System
 */

require_once 'config.php';
require_once 'sheets.php';

// Headers must match EXACTLY the column names in your Google Sheet tabs
$SHEET_HEADERS = [
    'Inventory'   => ['id','aircon_type','brand_model','hp','model_number',
                      'supplier','status','franchise_price','subdealer_price',
                      'cash_price','card_price','created_at'],
    'Consumables' => ['id','item_name','category','unit_of_measure',
                      'stock_quantity','reorder_level'],
    'Accessories' => ['id','item_name','category',
                      'stock_quantity','reorder_level'],
    'Defectives'  => ['id','inventory_id','brand_model','aircon_type','hp',
                      'model_number','reason','resolution',
                      'reported_by','reported_at','notes'],
    'Transactions'=> ['id','type','item_type','item_id','item_name',
                      'quantity','recorded_by','recorded_at','notes'],
    'UnitHistory' => ['id','inventory_id','brand_model','aircon_type',
                      'event_type','field','old_value','new_value',
                      'changed_by','changed_at','notes'],
];

function read_json($sheetName) {
    return sheets_read($sheetName);
}

function write_json($sheetName, $data) {
    global $SHEET_HEADERS;
    $headers = $SHEET_HEADERS[$sheetName] ?? array_keys($data[0] ?? []);
    sheets_write($sheetName, $data, $headers);
    return true;
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

// ====================== UNIT HISTORY ======================

/**
 * Record a unit history event to the UnitHistory sheet.
 * event_type: added | status_change | price_edit | deleted | note
 */
function record_unit_history($inventory_id, $brand_model, $aircon_type, $event_type, $field, $old_value, $new_value, $notes = '') {
    global $SHEET_HEADERS;

    $existing = sheets_read('UnitHistory');

    $max_id = 0;
    foreach ($existing as $r) {
        if ((int)($r['id'] ?? 0) > $max_id) $max_id = (int)$r['id'];
    }

    $row = [
        'id'           => $max_id + 1,
        'inventory_id' => $inventory_id,
        'brand_model'  => $brand_model,
        'aircon_type'  => $aircon_type,
        'event_type'   => $event_type,
        'field'        => $field,
        'old_value'    => $old_value,
        'new_value'    => $new_value,
        'changed_by'   => $_SESSION['user']['username'] ?? 'system',
        'changed_at'   => date('Y-m-d H:i:s'),
        'notes'        => $notes,
    ];

    $existing[] = $row;
    sheets_write('UnitHistory', $existing, $SHEET_HEADERS['UnitHistory']);

    return $row;
}

// ====================== DEFECTIVE UNITS ======================

/**
 * Return all defective unit records from Google Sheets.
 */
function get_defectives() {
    return sheets_read('Defectives');
}

/**
 * Write the full defectives array back to the sheet.
 */
function write_defectives($rows) {
    global $SHEET_HEADERS;
    $headers = $SHEET_HEADERS['Defectives'];
    sheets_write('Defectives', $rows, $headers);
}

/**
 * Append a new defective record.
 * $data keys: inventory_id, brand_model, aircon_type, hp,
 *             model_number, reason, resolution, notes
 */
function record_defective($data) {
    $existing = get_defectives();

    $max_id = 0;
    foreach ($existing as $r) {
        if ((int)($r['id'] ?? 0) > $max_id) $max_id = (int)$r['id'];
    }

    $row = [
        'id'           => $max_id + 1,
        'inventory_id' => $data['inventory_id'] ?? '',
        'brand_model'  => $data['brand_model']  ?? '',
        'aircon_type'  => $data['aircon_type']  ?? '',
        'hp'           => $data['hp']           ?? '',
        'model_number' => $data['model_number'] ?? '',
        'reason'       => $data['reason']       ?? '',
        'resolution'   => $data['resolution']   ?? 'Pending',
        'reported_by'  => $_SESSION['user']['username'] ?? 'system',
        'reported_at'  => date('Y-m-d H:i:s'),
        'notes'        => $data['notes']        ?? '',
    ];

    $existing[] = $row;
    write_defectives($existing);
    return $row;
}

// ====================== RENDER FUNCTIONS ======================
function render_header($title = 'Icewind HVAC') {
    $current = basename($_SERVER['PHP_SELF']);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - Icewind HVAC</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="css/app.css" rel="stylesheet">
        <link href="css/loading_screen.css" rel="stylesheet">
        <script src="https://unpkg.com/lucide@latest"></script>
    </head>
    <body class="iw-theme">
    <?php if (isset($_SESSION['user'])): ?>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top iw-navbar shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand iw-brand" href="dashboard.php">
                <img src="logo.gif" alt="IceWind" class="iw-brand__logo" decoding="async">
            </a>
            <div class="ms-auto d-flex align-items-center">
                <span class="text-white me-3 d-none d-md-inline">
                    Welcome, <?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'Admin'); ?>
                </span>
                <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse shadow-sm">
                <div class="position-sticky pt-2">
                    <ul class="nav flex-column">

                        <!-- Dashboard -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current === 'dashboard.php' ? 'active' : ''; ?>"
                               href="dashboard.php">
                                <i data-lucide="layout-dashboard" class="me-2" style="width:16px;height:16px;"></i>
                                Dashboard
                            </a>
                        </li>

                        <!-- Inventory section -->
                        <li><div class="nav-section-label">Inventory</div></li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current === 'inventory.php' ? 'active' : ''; ?>"
                               href="inventory.php">
                                <i data-lucide="package" class="me-2" style="width:16px;height:16px;"></i>
                                Inventory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current === 'defective.php' ? 'active' : ''; ?>"
                               href="defective.php">
                                <i data-lucide="alert-triangle" class="me-2" style="width:16px;height:16px;"></i>
                                Defective Units
                            </a>
                        </li>

                        <!-- Stock section -->
                        <li><div class="nav-section-label">Stock</div></li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current === 'consumables.php' ? 'active' : ''; ?>"
                               href="consumables.php">
                                <i data-lucide="droplet" class="me-2" style="width:16px;height:16px;"></i>
                                Consumables
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current === 'accessories.php' ? 'active' : ''; ?>"
                               href="accessories.php">
                                <i data-lucide="settings" class="me-2" style="width:16px;height:16px;"></i>
                                Accessories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current === 'history.php' ? 'active' : ''; ?>"
                               href="history.php">
                                <i data-lucide="history" class="me-2" style="width:16px;height:16px;"></i>
                                Transaction History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current === 'unit_history.php' ? 'active' : ''; ?>"
                                href="unit_history.php">
                            <i data-lucide="clock" class="me-2" style="width:16px;height:16px;"></i>
                                 Unit History
                            </a>
                        </li>

                        <!-- Analytics section -->
                        <li><div class="nav-section-label">Analytics</div></li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current === 'reports.php' ? 'active' : ''; ?>"
                               href="reports.php">
                                <i data-lucide="bar-chart-2" class="me-2" style="width:16px;height:16px;"></i>
                                Reports
                            </a>
                        </li>

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