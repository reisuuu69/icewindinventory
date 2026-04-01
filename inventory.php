<?php 
session_start();
require_once 'config.php';
require_once 'functions.php';


check_auth();

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ─── Export CSV (kept here so button works) ──────────────────────
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
$items = read_json(INVENTORY_FILE);

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
        'id'             => generate_id($items),
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

    $items[] = $new_item;
    write_json(INVENTORY_FILE, $items);

    header("Location: inventory.php?success=added");
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
    $items = array_filter($items, fn($item) => $item['id'] != $id);
    $items = array_values($items);

    write_json(INVENTORY_FILE, $items);

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
                // Read header row
                $header = fgetcsv($handle);
                if (!$header) {
                    $import_error = 'CSV file appears to be empty.';
                } else {
                    // Normalize headers (lowercase, trim)
                    $header = array_map(fn($h) => strtolower(trim($h)), $header);

                    // Required columns
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
                        $mode           = trim($_POST['import_mode'] ?? 'append'); // append | replace

                        if ($mode === 'replace') {
                            $existing_items = [];
                        }

                        $valid_statuses = ['Available', 'Installed', 'Reserved'];

                        while (($row = fgetcsv($handle)) !== false) {
                            // Skip blank rows
                            if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) continue;

                            $row = array_pad($row, count($header), '');
                            $map = array_combine($header, $row);

                            $aircon_type = trim($map['aircon_type'] ?? '');
                            $brand_model = trim($map['brand_model'] ?? '');

                            // Skip row if required fields are empty
                            if (empty($aircon_type) || empty($brand_model)) {
                                $skipped++;
                                continue;
                            }

                            $status = trim($map['status'] ?? 'Available');
                            if (!in_array($status, $valid_statuses)) {
                                $status = 'Available';
                            }

                            $existing_items[] = [
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
                            $imported++;
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

$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';
$msg     = $_GET['msg'] ?? '';

// Output starts here — loading_screen MUST come after all header() calls
require_once 'loading_screen.php';
render_header('Inventory');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold">Inventory</h1>
    <div class="btn-toolbar mb-2 mb-md-0 gap-2">
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i data-lucide="plus" class="me-1"></i>Add New Item
        </button>
        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#importCsvModal">
            <i data-lucide="upload" class="me-1"></i>Import CSV
        </button>
        <a href="?export=1" class="btn btn-sm btn-outline-secondary">
            <i data-lucide="download" class="me-1"></i>Export CSV
        </a>
    </div>
</div>

<?php

?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($success === 'added'): ?>
    <div class="alert alert-success alert-dismissible fade show">
        Item successfully added!
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

<!-- TABLE -->
<div class="card shadow-sm border-0">
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
                        <th class="py-3">Customer Price (Bank/Credit Card)</th>
                        <th class="py-3">Created At</th>
                        <th class="px-3 py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="12" class="text-center py-5 text-muted">No inventory items found.</td></tr>
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

        <!-- Instructions -->
        <div class="alert alert-info py-3 mb-4">
          <h6 class="fw-bold mb-2"><i data-lucide="info" style="width:15px;" class="me-1"></i>Instructions</h6>
          <p class="small mb-2">Your CSV must include a header row. Required columns are marked <span class="text-danger fw-bold">*</span>. Column order doesn't matter — headers are matched by name.</p>
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

          <!-- Preview panel -->
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
/* ── Confirm Replace mode ───────────────────────── */
function confirmReplace(radio) {
    if (!confirm('Replace mode will DELETE all existing inventory items and replace them with the CSV data.\n\nAre you sure?')) {
        document.getElementById('mode_append').checked = true;
    }
}

/* ── CSV client-side preview ────────────────────── */
function previewCsv(input) {
    const submitBtn  = document.getElementById('importSubmitBtn');
    const previewWrap = document.getElementById('csvPreviewWrap');
    const previewTable = document.getElementById('csvPreviewTable');
    const rowCount   = document.getElementById('csvRowCount');

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

        // Simple CSV parse (handles quoted fields)
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

        // Validate required headers
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