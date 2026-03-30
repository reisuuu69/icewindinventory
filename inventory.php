<?php 
session_start();
require_once 'config.php';
require_once 'functions.php';


check_auth();

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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

$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';

// Output starts here — loading_screen MUST come after all header() calls
require_once 'loading_screen.php';
render_header('Inventory');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold">Inventory</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i data-lucide="plus" class="me-1"></i>Add New Item
        </button>
    </div>
</div>

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

<!-- ADD MODAL -->
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

<?php render_footer(); ?>