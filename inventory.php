<?php 
session_start();
require_once 'config.php';
require_once 'functions.php';

check_auth();

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ─── Export CSV ──────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $data = read_json(INVENTORY_FILE);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inventory_export_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','aircon_type','brand_model','hp','model_number','supplier','status',
                   'franchise_price','subdealer_price','cash_price','card_price','created_at']);
    foreach ($data as $r) {
        fputcsv($out, [
            $r['id']??'',$r['aircon_type']??'',$r['brand_model']??'',$r['hp']??'',
            $r['model_number']??'',$r['supplier']??'',$r['status']??'',
            $r['franchise_price']??'',$r['subdealer_price']??'',
            $r['cash_price']??'',$r['card_price']??'',$r['created_at']??''
        ]);
    }
    fclose($out);
    exit;
}

// Load inventory
$all_items = read_json(INVENTORY_FILE);

// =========================
// ADD ITEM
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {

    $error = null;

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } else {
        $aircon_type      = trim($_POST['aircon_type']);
        $brand_model      = trim($_POST['brand_model']);
        $hp               = trim($_POST['hp']);
        $model_number     = trim($_POST['model_number']);
        $supplier         = trim($_POST['supplier']);
        $status           = trim($_POST['status']);
        $franchise_price  = floatval($_POST['franchise_price']);
        $subdealer_price  = floatval($_POST['subdealer_price']);
        $cash_price       = floatval($_POST['cash_price']);
        $card_price       = floatval($_POST['card_price']);

        if (empty($aircon_type) || empty($brand_model)) {
            $error = 'Aircon Type and Brand/Model are required.';
        }

        if (!$error && !in_array($status, ['Available', 'Installed', 'Reserved'])) {
            $error = 'Invalid status selected.';
        }
    }

    if ($error) {
        header("Location: inventory.php?error=" . urlencode($error));
        exit;
    }

    $new_item = [
        'id'             => generate_id($all_items),
        'aircon_type'    => $aircon_type,
        'brand_model'    => $brand_model,
        'hp'             => $hp,
        'model_number'   => $model_number,
        'supplier'       => $supplier,
        'status'         => $status,
        'franchise_price'=> $franchise_price,
        'subdealer_price'=> $subdealer_price,
        'cash_price'     => $cash_price,
        'card_price'     => $card_price,
        'created_at'     => date('Y-m-d H:i:s'),
    ];

    $all_items[] = $new_item;
    write_json(INVENTORY_FILE, $all_items);

    // Log to unit history
    record_unit_history(
        $new_item['id'],
        $brand_model,
        $aircon_type,
        'added',
        'status',
        '',
        $status,
        'Unit added to inventory'
    );

    header("Location: inventory.php?success=added");
    exit;
}

// =========================
// UPDATE STATUS
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        header("Location: inventory.php?error=" . urlencode('Invalid CSRF token'));
        exit;
    }

    $inv_id     = intval($_POST['inventory_id'] ?? 0);
    $new_status = trim($_POST['new_status'] ?? '');
    $note_text  = trim($_POST['status_note'] ?? '');
    $allowed    = ['Available', 'Installed', 'Reserved', 'Defective'];

    if (!$inv_id || !in_array($new_status, $allowed)) {
        header("Location: inventory.php?error=" . urlencode('Invalid unit or status.'));
        exit;
    }

    $inv        = read_json(INVENTORY_FILE);
    $old_status = '';
    $unit       = null;

    foreach ($inv as &$i) {
        if ((int)$i['id'] === $inv_id) {
            $old_status = $i['status'] ?? '';
            $unit       = $i;
            $i['status'] = $new_status;
            break;
        }
    } unset($i);

    if (!$unit) {
        header("Location: inventory.php?error=" . urlencode('Unit not found.'));
        exit;
    }

    if ($old_status === $new_status) {
        header("Location: inventory.php?error=" . urlencode('Status is already set to ' . $new_status . '.'));
        exit;
    }

    write_json(INVENTORY_FILE, $inv);

    // Log to unit history
    record_unit_history(
        $inv_id,
        $unit['brand_model'] ?? '',
        $unit['aircon_type'] ?? '',
        'status_change',
        'status',
        $old_status,
        $new_status,
        $note_text
    );

    header("Location: inventory.php?success=status_updated");
    exit;
}

// =========================
// DELETE ITEM
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {

    $error = null;

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    }

    if ($error) {
        header("Location: inventory.php?error=" . urlencode($error));
        exit;
    }

    $id = intval($_POST['delete_id']);

    // Find item before deleting for history
    $deleted_unit = null;
    foreach ($all_items as $i) {
        if ((int)$i['id'] === $id) { $deleted_unit = $i; break; }
    }

    $all_items = array_filter($all_items, fn($item) => $item['id'] != $id);
    $all_items = array_values($all_items);

    write_json(INVENTORY_FILE, $all_items);

    // Log deletion
    if ($deleted_unit) {
        record_unit_history(
            $id,
            $deleted_unit['brand_model'] ?? '',
            $deleted_unit['aircon_type'] ?? '',
            'deleted',
            'status',
            $deleted_unit['status'] ?? '',
            '',
            'Unit removed from inventory'
        );
    }

    header("Location: inventory.php?success=deleted");
    exit;
}

// =========================
// IMPORT CSV
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_csv') {

    $import_error   = null;
    $import_success = null;

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $import_error = 'Invalid CSRF token.';
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $import_error = 'Please select a valid CSV file to upload.';
    } else {
        $file     = $_FILES['csv_file']['tmp_name'];
        $mimetype = mime_content_type($file);
        $ext      = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));

        if ($ext !== 'csv' && !in_array($mimetype, ['text/csv', 'text/plain', 'application/vnd.ms-excel'])) {
            $import_error = 'Only CSV files are allowed.';
        } else {
            $handle = fopen($file, 'r');
            if (!$handle) {
                $import_error = 'Could not open the uploaded file.';
            } else {
                $header = fgetcsv($handle);
                if (!$header) {
                    $import_error = 'CSV file appears to be empty.';
                } else {
                    $header = array_map(fn($h) => strtolower(trim($h)), $header);
                    $required = ['aircon_type', 'brand_model'];
                    $missing  = array_diff($required, $header);

                    if (!empty($missing)) {
                        $import_error = 'CSV is missing required columns: ' . implode(', ', $missing) . '. '
                            . 'Expected headers: aircon_type, brand_model, hp, model_number, supplier, status, '
                            . 'franchise_price, subdealer_price, cash_price, card_price';
                    } else {
                        $existing_items = read_json(INVENTORY_FILE);
                        $imported       = 0;
                        $skipped        = 0;
                        $mode           = trim($_POST['import_mode'] ?? 'append');

                        if ($mode === 'replace') {
                            $existing_items = [];
                        }

                        $valid_statuses = ['Available', 'Installed', 'Reserved'];

                        while (($row = fgetcsv($handle)) !== false) {
                            if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) continue;

                            $row = array_pad($row, count($header), '');
                            $map = array_combine($header, $row);

                            $aircon_type = trim($map['aircon_type'] ?? '');
                            $brand_model = trim($map['brand_model'] ?? '');

                            if (empty($aircon_type) || empty($brand_model)) {
                                $skipped++;
                                continue;
                            }

                            $status = trim($map['status'] ?? 'Available');
                            if (!in_array($status, $valid_statuses)) {
                                $status = 'Available';
                            }

                            $new_item = [
                                'id'              => generate_id($existing_items),
                                'aircon_type'     => $aircon_type,
                                'brand_model'     => $brand_model,
                                'hp'              => trim($map['hp'] ?? ''),
                                'model_number'    => trim($map['model_number'] ?? ''),
                                'supplier'        => trim($map['supplier'] ?? ''),
                                'status'          => $status,
                                'franchise_price' => floatval($map['franchise_price'] ?? 0),
                                'subdealer_price' => floatval($map['subdealer_price'] ?? 0),
                                'cash_price'      => floatval($map['cash_price'] ?? 0),
                                'card_price'      => floatval($map['card_price'] ?? 0),
                                'created_at'      => date('Y-m-d H:i:s'),
                            ];

                            $existing_items[] = $new_item;
                            $imported++;

                            // Log each imported unit
                            record_unit_history(
                                $new_item['id'],
                                $brand_model,
                                $aircon_type,
                                'added',
                                'status',
                                '',
                                $status,
                                'Imported via CSV'
                            );
                        }

                        fclose($handle);
                        write_json(INVENTORY_FILE, $existing_items);

                        $msg = "Successfully imported {$imported} item(s).";
                        if ($skipped > 0) $msg .= " {$skipped} row(s) were skipped (missing required fields).";
                        header("Location: inventory.php?success=imported&msg=" . urlencode($msg));
                        exit;
                    }
                }
                if ($handle) fclose($handle);
            }
        }
    }

    if ($import_error) {
        header("Location: inventory.php?error=" . urlencode($import_error));
        exit;
    }
}

// ─── Search & Filter ─────────────────────────────────────────────
$search          = trim($_GET['search']      ?? '');
$filter_status   = trim($_GET['status']      ?? '');
$filter_type     = trim($_GET['aircon_type'] ?? '');
$filter_supplier = trim($_GET['supplier']    ?? '');

$filtered = $all_items;

if ($search) {
    $filtered = array_filter($filtered, fn($i) =>
        stripos($i['brand_model']  ?? '', $search) !== false ||
        stripos($i['model_number'] ?? '', $search) !== false ||
        stripos($i['supplier']     ?? '', $search) !== false ||
        stripos($i['aircon_type']  ?? '', $search) !== false
    );
}
if ($filter_status) {
    $filtered = array_filter($filtered, fn($i) => ($i['status'] ?? '') === $filter_status);
}
if ($filter_type) {
    $filtered = array_filter($filtered, fn($i) =>
        stripos($i['aircon_type'] ?? '', $filter_type) !== false
    );
}
if ($filter_supplier) {
    $filtered = array_filter($filtered, fn($i) =>
        stripos($i['supplier'] ?? '', $filter_supplier) !== false
    );
}

$filtered = array_values($filtered);

// ─── Build unique filter options from full list ───────────────────
$all_types     = array_unique(array_filter(array_column($all_items, 'aircon_type')));
$all_suppliers = array_unique(array_filter(array_column($all_items, 'supplier')));
sort($all_types);
sort($all_suppliers);

// ─── Pagination ───────────────────────────────────────────────────
$per_page    = 10;
$total_items = count($filtered);
$total_pages = max(1, ceil($total_items / $per_page));
$page        = max(1, min((int)($_GET['page'] ?? 1), $total_pages));
$items       = array_slice($filtered, ($page - 1) * $per_page, $per_page);

// Helper: build query string preserving filters
function inv_qs($overrides = []) {
    $base = [
        'search'      => $_GET['search']      ?? '',
        'status'      => $_GET['status']      ?? '',
        'aircon_type' => $_GET['aircon_type'] ?? '',
        'supplier'    => $_GET['supplier']    ?? '',
        'page'        => $_GET['page']        ?? 1,
    ];
    return '?' . http_build_query(array_filter(array_merge($base, $overrides), fn($v) => $v !== ''));
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';
$msg     = $_GET['msg']     ?? '';

require_once 'loading_screen.php';
render_header('Inventory');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold">Inventory</h1>
    <div class="btn-toolbar mb-2 mb-md-0 gap-2">
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i data-lucide="plus" class="me-1"></i>Add New Item
        </button>
        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
            <i data-lucide="refresh-cw" class="me-1"></i>Update Status
        </button>
        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#importCsvModal">
            <i data-lucide="upload" class="me-1"></i>Import CSV
        </button>
        <a href="?export=1" class="btn btn-sm btn-outline-secondary">
            <i data-lucide="download" class="me-1"></i>Export CSV
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($success === 'added'): ?>
    <div class="alert alert-success alert-dismissible fade show">
        Item successfully added! <a href="unit_history.php" class="alert-link ms-1">View in Unit History →</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($success === 'status_updated'): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i data-lucide="check-circle" style="width:15px;" class="me-1"></i>
        Status updated successfully! <a href="unit_history.php" class="alert-link ms-1">View audit trail →</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($success === 'deleted'): ?>
    <div class="alert alert-success alert-dismissible fade show">
        Item successfully deleted!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($success === 'imported'): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i data-lucide="check-circle" style="width:16px;" class="me-1"></i>
        <?= htmlspecialchars($msg ?: 'CSV imported successfully!') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ─── Search & Filters ─────────────────────────────────────── -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET" id="filterForm">
            <!-- Search -->
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i data-lucide="search" style="width:15px;height:15px;"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" name="search"
                           placeholder="Brand, model, supplier…"
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <!-- Status -->
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach (['Available','Installed','Reserved','Defective'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Aircon Type -->
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Aircon Type</label>
                <select name="aircon_type" class="form-select">
                    <option value="">All Types</option>
                    <?php foreach ($all_types as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>" <?= $filter_type === $t ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Supplier -->
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Supplier</label>
                <select name="supplier" class="form-select">
                    <option value="">All Suppliers</option>
                    <?php foreach ($all_suppliers as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $filter_supplier === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Buttons -->
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill">
                    <i data-lucide="filter" style="width:14px;" class="me-1"></i>Filter
                </button>
                <?php if ($search || $filter_status || $filter_type || $filter_supplier): ?>
                <a href="inventory.php" class="btn btn-outline-secondary" title="Clear filters">
                    <i data-lucide="x" style="width:14px;"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Active filter tags -->
        <?php if ($search || $filter_status || $filter_type || $filter_supplier): ?>
        <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-muted">Active filters:</span>
            <?php if ($search): ?>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle">
                    Search: <?= htmlspecialchars($search) ?>
                    <a href="<?= inv_qs(['search'=>'','page'=>1]) ?>" class="text-primary ms-1 text-decoration-none">×</a>
                </span>
            <?php endif; ?>
            <?php if ($filter_status): ?>
                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle">
                    Status: <?= htmlspecialchars($filter_status) ?>
                    <a href="<?= inv_qs(['status'=>'','page'=>1]) ?>" class="text-secondary ms-1 text-decoration-none">×</a>
                </span>
            <?php endif; ?>
            <?php if ($filter_type): ?>
                <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle">
                    Type: <?= htmlspecialchars($filter_type) ?>
                    <a href="<?= inv_qs(['aircon_type'=>'','page'=>1]) ?>" class="text-info ms-1 text-decoration-none">×</a>
                </span>
            <?php endif; ?>
            <?php if ($filter_supplier): ?>
                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle">
                    Supplier: <?= htmlspecialchars($filter_supplier) ?>
                    <a href="<?= inv_qs(['supplier'=>'','page'=>1]) ?>" class="text-warning ms-1 text-decoration-none">×</a>
                </span>
            <?php endif; ?>
            <span class="small text-muted ms-1">— <?= $total_items ?> result<?= $total_items !== 1 ? 's' : '' ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ─── Table ────────────────────────────────────────────────── -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2 px-4">
        <span class="text-muted small">
            Showing
            <strong><?= count($items) > 0 ? ($page - 1) * $per_page + 1 : 0 ?>–<?= min($page * $per_page, $total_items) ?></strong>
            of <strong><?= $total_items ?></strong> item<?= $total_items !== 1 ? 's' : '' ?>
            <?php if ($total_items !== count($all_items)): ?>
                <span class="text-muted">(<?= count($all_items) ?> total)</span>
            <?php endif; ?>
        </span>
        <span class="text-muted small">Page <?= $page ?> of <?= $total_pages ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="px-3 py-3">Aircon Type</th>
                        <th class="py-3">Brand/Model</th>
                        <th class="py-3">HP</th>
                        <th class="py-3">Model Number</th>
                        <th class="py-3">Supplier</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Franchise Price</th>
                        <th class="py-3">Subdealer Price</th>
                        <th class="py-3">Customer Price (Cash)</th>
                        <th class="py-3">Customer Price (Card)</th>
                        <th class="py-3">Created At</th>
                        <th class="px-3 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="12" class="text-center py-5 text-muted">
                            <?php if ($search || $filter_status || $filter_type || $filter_supplier): ?>
                                <i data-lucide="search-x" style="width:32px;height:32px;" class="mb-2 d-block mx-auto opacity-25"></i>
                                No items match your current filters.
                                <a href="inventory.php" class="d-block mt-1 small">Clear filters</a>
                            <?php else: ?>
                                No inventory items found.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="px-3 fw-bold"><?= htmlspecialchars($item['aircon_type'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['brand_model'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['hp'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['model_number'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['supplier'] ?? '') ?></td>
                        <td>
                            <?php
                                $statusColor = match($item['status'] ?? '') {
                                    'Available' => 'success',
                                    'Installed' => 'secondary',
                                    'Reserved'  => 'warning',
                                    'Defective' => 'danger',
                                    default     => 'light'
                                };
                            ?>
                            <span class="badge bg-<?= $statusColor ?>">
                                <?= htmlspecialchars($item['status'] ?? '') ?>
                            </span>
                        </td>
                        <td>₱<?= number_format((float)($item['franchise_price'] ?? 0), 2) ?></td>
                        <td>₱<?= number_format((float)($item['subdealer_price'] ?? 0), 2) ?></td>
                        <td>₱<?= number_format((float)($item['cash_price'] ?? 0), 2) ?></td>
                        <td>₱<?= number_format((float)($item['card_price'] ?? 0), 2) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($item['created_at'] ?? '') ?></td>
                        <td class="px-3 text-end">
                            <!-- Quick Update Status button per row -->
                            <button type="button"
                                    class="btn btn-sm btn-outline-warning me-1"
                                    title="Update Status"
                                    onclick='openUpdateStatus(<?= json_encode([
                                        "id"          => (int)$item["id"],
                                        "brand_model" => $item["brand_model"] ?? "",
                                        "aircon_type" => $item["aircon_type"] ?? "",
                                        "hp"          => $item["hp"] ?? "",
                                        "status"      => $item["status"] ?? "",
                                    ]) ?>)'>
                                <i data-lucide="refresh-cw" style="width:13px;"></i>
                            </button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Delete this item?')">
                                    <i data-lucide="trash-2" style="width:14px;"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ─── Pagination ──────────────────────────────────────── -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3 px-4">
        <span class="text-muted small">
            <?= $total_items ?> item<?= $total_items !== 1 ? 's' : '' ?> &bull; <?= $per_page ?> per page
        </span>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= inv_qs(['page' => 1]) ?>" title="First">«</a>
                </li>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= inv_qs(['page' => $page - 1]) ?>">‹</a>
                </li>
                <?php
                $window_start = max(1, $page - 2);
                $window_end   = min($total_pages, $page + 2);
                if ($window_start > 1): ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif;
                for ($p = $window_start; $p <= $window_end; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= inv_qs(['page' => $p]) ?>"><?= $p ?></a>
                </li>
                <?php endfor;
                if ($window_end < $total_pages): ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= inv_qs(['page' => $page + 1]) ?>">›</a>
                </li>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= inv_qs(['page' => $total_pages]) ?>" title="Last">»</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- ADD MODAL                                                      -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addItemModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold">Add New Aircon Item</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Aircon Type <span class="text-danger">*</span></label>
                    <input type="text" name="aircon_type" class="form-control"
                           placeholder="e.g. Inverter, Window, Split" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Brand / Model <span class="text-danger">*</span></label>
                    <input type="text" name="brand_model" class="form-control"
                           placeholder="e.g. Carrier Split Type" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">HP</label>
                    <input type="text" name="hp" class="form-control" placeholder="e.g. 1.5">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Model Number</label>
                    <input type="text" name="model_number" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Supplier</label>
                    <input type="text" name="supplier" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="Available">Available</option>
                        <option value="Installed">Installed</option>
                        <option value="Reserved">Reserved</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Franchise Price (₱)</label>
                    <input type="number" step="0.01" min="0" name="franchise_price" class="form-control" placeholder="0.00">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Subdealer Price (₱)</label>
                    <input type="number" step="0.01" min="0" name="subdealer_price" class="form-control" placeholder="0.00">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Customer Price - Cash (₱)</label>
                    <input type="number" step="0.01" min="0" name="cash_price" class="form-control" placeholder="0.00">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Customer Price - Bank/Credit Card (₱)</label>
                    <input type="number" step="0.01" min="0" name="card_price" class="form-control" placeholder="0.00">
                </div>
            </div>
            <div class="modal-footer border-0 px-0 pb-0 mt-3">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">Save Item</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- UPDATE STATUS MODAL                                            -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title fw-bold">
            <i data-lucide="refresh-cw" class="me-2" style="width:16px;"></i>Update Unit Status
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action"       value="update_status">
        <input type="hidden" name="csrf_token"   value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="inventory_id" id="us_inventory_id">
        <div class="modal-body p-4">

            <!-- Unit info display (populated by JS when opened from row) -->
            <div id="us_unit_info" class="alert alert-light border py-2 mb-3" style="display:none;">
                <div class="fw-semibold small" id="us_unit_label"></div>
                <div class="small text-muted" id="us_unit_sub"></div>
            </div>

            <!-- Dropdown (shown when opened from header button) -->
            <div id="us_select_wrap" class="mb-3">
                <label class="form-label fw-semibold">Select Unit <span class="text-danger">*</span></label>
                <select name="inventory_id" id="us_unit_select" class="form-select"
                        onchange="updateStatusBadge(this)">
                    <option value="">— Choose a unit —</option>
                    <?php foreach ($all_items as $inv_item): ?>
                    <option value="<?= (int)$inv_item['id'] ?>"
                            data-status="<?= htmlspecialchars($inv_item['status'] ?? '') ?>"
                            data-label="<?= htmlspecialchars(($inv_item['brand_model'] ?? '') . ' · ' . ($inv_item['aircon_type'] ?? '') . ($inv_item['hp'] ? ' ' . $inv_item['hp'] . 'HP' : '')) ?>">
                        #<?= (int)$inv_item['id'] ?> — <?= htmlspecialchars($inv_item['brand_model'] ?? '') ?>
                        (<?= htmlspecialchars($inv_item['aircon_type'] ?? '') ?>
                        <?= htmlspecialchars($inv_item['hp'] ?? '') ?>HP)
                        · <?= htmlspecialchars($inv_item['status'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3" id="us_current_status_row" style="display:none;">
                <label class="form-label fw-semibold small text-muted">Current Status</label><br>
                <span id="us_current_badge" class="badge fs-6">—</span>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">New Status <span class="text-danger">*</span></label>
                <select name="new_status" id="us_new_status" class="form-select" required>
                    <option value="">— Select new status —</option>
                    <option value="Available">Available</option>
                    <option value="Installed">Installed</option>
                    <option value="Reserved">Reserved</option>
                    <option value="Defective">Defective</option>
                </select>
            </div>

            <div class="mb-0">
                <label class="form-label fw-semibold">Reason / Note <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" class="form-control" name="status_note"
                       placeholder="e.g. Installed at client site, returned from job…">
            </div>

            <div class="alert alert-warning py-2 small mt-3 mb-0">
                <i data-lucide="info" style="width:13px;" class="me-1"></i>
                This change will be logged to the <a href="unit_history.php" class="alert-link">Unit History</a> audit trail.
            </div>
        </div>
        <div class="modal-footer border-0">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning px-4 fw-semibold">Update Status</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- IMPORT CSV MODAL                                               -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="importCsvModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i data-lucide="upload" class="me-2" style="width:18px;"></i>Import Inventory from CSV</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="alert alert-info py-3 mb-4">
          <h6 class="fw-bold mb-2"><i data-lucide="info" style="width:15px;" class="me-1"></i>Instructions</h6>
          <p class="small mb-2">Your CSV must include a header row. Required columns are marked <span class="text-danger fw-bold">*</span>.</p>
          <div class="row g-2 small">
            <div class="col-md-6">
              <strong>Required:</strong>
              <ul class="mb-0 ps-3">
                <li><code>aircon_type</code> <span class="text-danger">*</span></li>
                <li><code>brand_model</code> <span class="text-danger">*</span></li>
              </ul>
            </div>
            <div class="col-md-6">
              <strong>Optional:</strong>
              <ul class="mb-0 ps-3">
                <li><code>hp</code>, <code>model_number</code>, <code>supplier</code></li>
                <li><code>status</code> — Available / Installed / Reserved</li>
                <li><code>franchise_price</code>, <code>subdealer_price</code></li>
                <li><code>cash_price</code>, <code>card_price</code></li>
              </ul>
            </div>
          </div>
          <div class="mt-2">
            <a href="?export=1" class="small text-decoration-none">
              <i data-lucide="download" style="width:13px;" class="me-1"></i>Download current inventory as template
            </a>
          </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="importForm">
          <input type="hidden" name="action"     value="import_csv">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

          <div class="mb-3">
            <label class="form-label fw-semibold">Select CSV File <span class="text-danger">*</span></label>
            <input type="file" name="csv_file" id="csv_file" class="form-control"
                   accept=".csv,text/csv" required
                   onchange="previewCsv(this)">
            <div class="form-text">Accepted format: <code>.csv</code> with UTF-8 encoding.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Import Mode</label>
            <div class="d-flex gap-4">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="import_mode" id="mode_append" value="append" checked>
                <label class="form-check-label" for="mode_append">
                  <strong>Append</strong> — add rows to existing inventory
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="import_mode" id="mode_replace" value="replace"
                       onclick="confirmReplace(this)">
                <label class="form-check-label" for="mode_replace">
                  <strong>Replace all</strong> — <span class="text-danger">clears existing inventory first</span>
                </label>
              </div>
            </div>
          </div>

          <div id="csvPreviewWrap" style="display:none;">
            <label class="form-label fw-semibold">Preview (first 5 rows)</label>
            <div class="table-responsive border rounded" style="max-height:220px;overflow-y:auto;">
              <table class="table table-sm table-bordered mb-0 small" id="csvPreviewTable"></table>
            </div>
            <p class="small text-muted mt-1" id="csvRowCount"></p>
          </div>

          <div class="modal-footer border-0 px-0 pb-0 mt-3">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success px-4" id="importSubmitBtn" disabled>
              <i data-lucide="upload" style="width:15px;" class="me-1"></i>Import
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// ── Update Status Modal logic ─────────────────────────────────────

const STATUS_COLORS = {
    'Available': 'bg-success',
    'Installed': 'bg-secondary',
    'Reserved':  'bg-warning text-dark',
    'Defective': 'bg-danger',
};

/**
 * Open Update Status modal pre-filled from a row button.
 */
function openUpdateStatus(item) {
    // Hide the dropdown — we know which unit
    document.getElementById('us_select_wrap').style.display  = 'none';
    document.getElementById('us_unit_info').style.display    = 'block';
    document.getElementById('us_current_status_row').style.display = 'block';

    document.getElementById('us_inventory_id').value = item.id;
    document.getElementById('us_unit_select').value  = ''; // clear dropdown

    document.getElementById('us_unit_label').textContent =
        item.brand_model + (item.aircon_type ? '  ·  ' + item.aircon_type : '') +
        (item.hp ? '  ' + item.hp + 'HP' : '');
    document.getElementById('us_unit_sub').textContent = 'Unit #' + item.id;

    const badge = document.getElementById('us_current_badge');
    badge.className = 'badge fs-6 ' + (STATUS_COLORS[item.status] || 'bg-light text-dark');
    badge.textContent = item.status || '—';

    // Reset new status select
    document.getElementById('us_new_status').value = '';

    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}

/**
 * Called when the header "Update Status" button is used (no unit pre-selected).
 * The dropdown is shown instead.
 */
document.getElementById('updateStatusModal').addEventListener('show.bs.modal', function (e) {
    // Only reset if NOT triggered by openUpdateStatus (which sets us_inventory_id)
    if (!document.getElementById('us_inventory_id').value) {
        document.getElementById('us_select_wrap').style.display  = 'block';
        document.getElementById('us_unit_info').style.display    = 'none';
        document.getElementById('us_current_status_row').style.display = 'none';
        document.getElementById('us_unit_select').value = '';
        document.getElementById('us_new_status').value  = '';
    }
});

document.getElementById('updateStatusModal').addEventListener('hidden.bs.modal', function () {
    // Full reset on close
    document.getElementById('us_inventory_id').value = '';
    document.getElementById('us_select_wrap').style.display  = 'block';
    document.getElementById('us_unit_info').style.display    = 'none';
    document.getElementById('us_current_status_row').style.display = 'none';
    document.getElementById('us_unit_select').value = '';
    document.getElementById('us_new_status').value  = '';
    lucide.createIcons();
});

/**
 * Sync the hidden input + badge when user picks from the dropdown.
 */
function updateStatusBadge(select) {
    const opt = select.options[select.selectedIndex];
    const st  = opt.getAttribute('data-status') || '';
    const id  = select.value;

    document.getElementById('us_inventory_id').value = id;

    const row   = document.getElementById('us_current_status_row');
    const badge = document.getElementById('us_current_badge');
    if (st && id) {
        badge.className   = 'badge fs-6 ' + (STATUS_COLORS[st] || 'bg-light text-dark');
        badge.textContent = st;
        row.style.display = 'block';
    } else {
        row.style.display = 'none';
    }
}

// ── CSV Import ────────────────────────────────────────────────────
function confirmReplace(radio) {
    if (!confirm('Replace mode will DELETE all existing inventory items and replace them with the CSV data.\n\nAre you sure?')) {
        document.getElementById('mode_append').checked = true;
    }
}

function previewCsv(input) {
    const submitBtn    = document.getElementById('importSubmitBtn');
    const previewWrap  = document.getElementById('csvPreviewWrap');
    const previewTable = document.getElementById('csvPreviewTable');
    const rowCount     = document.getElementById('csvRowCount');

    submitBtn.disabled = true;
    previewWrap.style.display = 'none';
    previewTable.innerHTML = '';

    const file = input.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const text  = e.target.result;
        const lines = text.split(/\r?\n/).filter(l => l.trim() !== '');

        if (lines.length < 2) {
            rowCount.textContent = 'File appears empty or has no data rows.';
            previewWrap.style.display = 'block';
            return;
        }

        function parseCsvLine(line) {
            const result = [];
            let cur = '', inQ = false;
            for (let i = 0; i < line.length; i++) {
                const ch = line[i];
                if (ch === '"') { inQ = !inQ; }
                else if (ch === ',' && !inQ) { result.push(cur.trim()); cur = ''; }
                else { cur += ch; }
            }
            result.push(cur.trim());
            return result;
        }

        const headers   = parseCsvLine(lines[0]);
        const dataLines = lines.slice(1);
        const preview   = dataLines.slice(0, 5);
        const required  = ['aircon_type', 'brand_model'];
        const normHead  = headers.map(h => h.toLowerCase().trim());
        const missing   = required.filter(r => !normHead.includes(r));

        let html = '<thead class="bg-light"><tr>';
        headers.forEach(h => { html += `<th class="px-2 py-1">${h}</th>`; });
        html += '</tr></thead><tbody>';
        preview.forEach(line => {
            const cols = parseCsvLine(line);
            html += '<tr>';
            cols.forEach(c => { html += `<td class="px-2 py-1">${c}</td>`; });
            html += '</tr>';
        });
        html += '</tbody>';

        previewTable.innerHTML = html;
        previewWrap.style.display = 'block';

        const totalDataRows = dataLines.filter(l => l.trim() !== '').length;
        rowCount.textContent = `${totalDataRows} data row(s) detected.`;
        lucide.createIcons();

        if (missing.length > 0) {
            rowCount.innerHTML += ` <span class="text-danger fw-bold">Missing required columns: ${missing.join(', ')}</span>`;
            submitBtn.disabled = true;
        } else {
            submitBtn.disabled = false;
        }
    };

    reader.readAsText(file, 'UTF-8');
}
</script>

<?php render_footer(); ?>