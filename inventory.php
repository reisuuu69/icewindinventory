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
        $serial = trim($_POST['serial_number']);
        $category = trim($_POST['category']);
        $brand = trim($_POST['brand_model']);
        $hp = trim($_POST['hp']);
        $discounted = floatval($_POST['discounted_price']);
        $regular = floatval($_POST['regular_price']);
        $supplier = trim($_POST['supplier']);
        $status = trim($_POST['status']);

        if (empty($serial) || empty($category) || empty($brand)) {
            $error = 'Required fields are missing';
        } elseif (!preg_match('/^[A-Za-z0-9\-]+$/', $serial)) {
            $error = 'Invalid serial number format (use letters, numbers, and hyphens only)';
        } else {
            foreach ($items as $item) {
                if ($item['serial_number'] === $serial) {
                    $error = 'Serial number already exists in inventory';
                    break;
                }
            }
        }

        if (!$error && !in_array($status, ['Available', 'Installed'])) {
            $error = 'Invalid status selected';
        }
    }

    if ($error) {
        header("Location: inventory.php?error=" . urlencode($error));
        exit;
    }

    $savings = $regular - $discounted;

    $new_item = [
        'id' => generate_id($items),
        'serial_number' => $serial,
        'category' => $category,
        'brand_model' => $brand,
        'hp' => $hp,
        'discounted_price' => $discounted,
        'regular_price' => $regular,
        'savings' => $savings,
        'supplier' => $supplier,
        'status' => $status,
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => $_SESSION['user_id']
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
    } elseif (($_SESSION['role'] ?? '') !== 'admin') {
        $error = 'You do not have permission to delete items';
    }

    if ($error) {
        header("Location: inventory.php?error=" . urlencode($error));
        exit;
    }

    $id = intval($_POST['delete_id']);

    $items = array_filter($items, function($item) use ($id) {
        return $item['id'] != $id;
    });

    $items = array_values($items);

    write_json(INVENTORY_FILE, $items);

    header("Location: inventory.php?success=deleted");
    exit;
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';
?>

<?php render_header('Inventory'); ?>

<h2 class="mb-4">Inventory Management</h2>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($success === 'added'): ?>
    <div class="alert alert-success">Item successfully added!</div>
<?php elseif ($success === 'deleted'): ?>
    <div class="alert alert-success">Item successfully deleted!</div>
<?php endif; ?>

<!-- ADD BUTTON -->
<button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addItemModal">
    + Add New Item
</button>

<!-- TABLE -->
<table class="table table-hover table-bordered">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Serial</th>
            <th>Category</th>
            <th>Brand/Model</th>
            <th>HP</th>
            <th>Discounted</th>
            <th>Regular</th>
            <th>Savings</th>
            <th>Supplier</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
            <td><?= $item['id'] ?></td>
            <td><?= htmlspecialchars($item['serial_number']) ?></td>
            <td><?= htmlspecialchars($item['category']) ?></td>
            <td><?= htmlspecialchars($item['brand_model']) ?></td>
            <td><?= htmlspecialchars($item['hp'] ?? '') ?></td>

            <td>₱<?= number_format($item['discounted_price'], 2) ?></td>
            <td>₱<?= number_format($item['regular_price'], 2) ?></td>
            <td>₱<?= number_format($item['savings'], 2) ?></td>

            <td><?= htmlspecialchars($item['supplier']) ?></td>

            <td>
                <span class="badge bg-<?= ($item['status'] === 'Available') ? 'success' : 'secondary' ?>">
                    <?= $item['status'] ?>
                </span>
            </td>

            <td>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button class="btn btn-sm btn-danger"
                        onclick="return confirm('Delete this item?')">
                        Delete
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- MODAL -->
<div class="modal fade" id="addItemModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Add New Aircon Item</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="row g-3">

                <div class="col-md-4">
                    <label>Serial Number</label>
                    <input type="text" name="serial_number" class="form-control" required>
                </div>

                <div class="col-md-4">
                    <label>Category</label>
                    <input type="text" name="category" class="form-control" required>
                </div>

                <div class="col-md-4">
                    <label>Brand / Model</label>
                    <input type="text" name="brand_model" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label>HP</label>
                    <input type="text" name="hp" class="form-control">
                </div>

                <div class="col-md-3">
                    <label>Discounted Price (₱)</label>
                    <input type="number" step="0.01" name="discounted_price" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label>Regular Price (₱)</label>
                    <input type="number" step="0.01" name="regular_price" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label>Supplier</label>
                    <input type="text" name="supplier" class="form-control">
                </div>

                <div class="col-md-3">
                    <label>Status</label>
                    <select name="status" class="form-select" required>
                        <option value="Available">Available</option>
                        <option value="Installed">Installed</option>
                    </select>
                </div>

            </div>

            <button type="submit" class="btn btn-primary mt-3 w-100">
                Save Item
            </button>
        </form>
      </div>

    </div>
  </div>
</div>

<?php render_footer(); ?>