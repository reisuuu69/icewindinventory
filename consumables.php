<?php
/**
 * Icewind HVAC Inventory System - Consumables
 * Database: Google Sheets (Consumables tab)
 */

require_once 'config.php';
require_once 'functions.php';


check_auth();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = $success = '';

// ─── Export CSV ──────────────────────────────────────────────────
// Must run BEFORE any output (loading_screen, render_header)
if (isset($_GET['export'])) {
    $items = read_json(CONSUMABLES_FILE);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="consumables_export_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Item Name','Category','Unit of Measure','Stock Quantity','Reorder Level']);
    foreach ($items as $row)
        fputcsv($out, [$row['id'],$row['item_name'],$row['category'],$row['unit_of_measure'],$row['stock_quantity'],$row['reorder_level']]);
    fclose($out); exit;
}

// ─── Add ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $items = read_json(CONSUMABLES_FILE);
        $items[] = [
            'id'              => count($items) > 0 ? max(array_column($items,'id')) + 1 : 1,
            'item_name'       => trim($_POST['item_name'] ?? ''),
            'category'        => trim($_POST['category'] ?? ''),
            'unit_of_measure' => trim($_POST['unit_of_measure'] ?? ''),
            'stock_quantity'  => intval($_POST['stock_quantity'] ?? 0),
            'reorder_level'   => intval($_POST['reorder_level'] ?? 0),
        ];
        write_json(CONSUMABLES_FILE, $items);
        header("Location: consumables.php?success=added"); exit;
    }
}

// ─── Edit ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $items = read_json(CONSUMABLES_FILE);
        $id = intval($_POST['id'] ?? 0); $found = false;
        foreach ($items as &$item) {
            if ($item['id'] === $id) {
                $item['item_name']       = trim($_POST['item_name'] ?? '');
                $item['category']        = trim($_POST['category'] ?? '');
                $item['unit_of_measure'] = trim($_POST['unit_of_measure'] ?? '');
                $item['stock_quantity']  = intval($_POST['stock_quantity'] ?? 0);
                $item['reorder_level']   = intval($_POST['reorder_level'] ?? 0);
                $found = true; break;
            }
        } unset($item);
        if ($found) { write_json(CONSUMABLES_FILE, $items); header("Location: consumables.php?success=updated"); exit; }
        else $error = "Consumable not found.";
    }
}

// ─── Delete ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $id = intval($_POST['delete_id']);
        $items = array_values(array_filter(read_json(CONSUMABLES_FILE), fn($i) => $i['id'] !== $id));
        write_json(CONSUMABLES_FILE, $items);
        header("Location: consumables.php?success=deleted"); exit;
    }
}

// ─── Fetch & Filter ──────────────────────────────────────────────
$items  = read_json(CONSUMABLES_FILE);
$search = trim($_GET['search'] ?? '');
if ($search) {
    $items = array_filter($items, fn($i) =>
        stripos($i['item_name'], $search) !== false || stripos($i['category'], $search) !== false);
}
$success = $success ?: ($_GET['success'] ?? '');
$error   = $error   ?: ($_GET['error'] ?? '');

// ─── Output starts here (loading screen + header must come AFTER all header() calls)
require_once 'loading_screen.php';
render_header('Consumables');
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold">Consumables Stock</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary me-2"
                data-bs-toggle="modal" data-bs-target="#itemModal" onclick="resetForm()">
            <i data-lucide="plus" class="me-1"></i>Add New Item
        </button>
        <a href="?export=1" class="btn btn-sm btn-outline-secondary">
            <i data-lucide="download" class="me-1"></i>Export CSV
        </a>
    </div>
</div>

<?php if ($success === 'added'): ?><div class="alert alert-success alert-dismissible fade show">Consumable added successfully! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php elseif ($success === 'updated'): ?><div class="alert alert-success alert-dismissible fade show">Consumable updated successfully! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php elseif ($success === 'deleted'): ?><div class="alert alert-success alert-dismissible fade show">Consumable deleted successfully! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form class="row g-3" method="GET">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i data-lucide="search" style="width:18px;"></i></span>
                    <input type="text" class="form-control border-start-0" name="search"
                           placeholder="Search Item Name or Category..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100">Search</button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="px-4 py-3">Item Name</th>
                        <th>Category</th><th>Unit</th>
                        <th>Stock Qty</th><th>Reorder</th>
                        <th>Status</th><th class="text-end px-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No consumables found.</td></tr>
                <?php else: foreach ($items as $item):
                    $stock = intval($item['stock_quantity'] ?? 0);
                    $reorder = intval($item['reorder_level'] ?? 0);
                    $isLow = $stock <= $reorder; ?>
                    <tr>
                        <td class="px-4 fw-bold"><?= htmlspecialchars($item['item_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['category'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['unit_of_measure'] ?? '') ?></td>
                        <td><span class="fw-bold <?= $isLow ? 'text-danger' : 'text-success' ?>"><?= $stock ?></span></td>
                        <td><?= $reorder ?></td>
                        <td><?= $isLow ? '<span class="badge bg-danger">Low Stock</span>' : '<span class="badge bg-success">In Stock</span>' ?></td>
                        <td class="text-end px-4">
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick='editItem(<?= json_encode($item) ?>)'>
                                <i data-lucide="edit-2" style="width:14px;"></i>
                            </button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="delete_id"  value="<?= intval($item['id']) ?>">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete this consumable?')">
                                    <i data-lucide="trash-2" style="width:14px;"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalTitle">Add New Consumable</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action"     id="formAction" value="add">
                <input type="hidden" name="id"         id="itemId">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Item Name</label>
                        <input type="text" class="form-control" name="item_name" id="item_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category</label>
                        <input type="text" class="form-control" name="category" id="category" placeholder="e.g. Refrigerant, Pipe, Tape" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Unit of Measure</label>
                        <input type="text" class="form-control" name="unit_of_measure" id="unit_of_measure" placeholder="e.g. Tank, Roll, Box" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Stock Quantity</label>
                            <input type="number" class="form-control" name="stock_quantity" id="stock_quantity" min="0" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Reorder Level</label>
                            <input type="number" class="form-control" name="reorder_level" id="reorder_level" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Save Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('modalTitle').innerText  = 'Add New Consumable';
    document.getElementById('formAction').value      = 'add';
    document.getElementById('itemId').value          = '';
    document.getElementById('item_name').value       = '';
    document.getElementById('category').value        = '';
    document.getElementById('unit_of_measure').value = '';
    document.getElementById('stock_quantity').value  = '';
    document.getElementById('reorder_level').value   = '';
}
function editItem(item) {
    document.getElementById('modalTitle').innerText  = 'Edit Consumable';
    document.getElementById('formAction').value      = 'edit';
    document.getElementById('itemId').value          = item.id;
    document.getElementById('item_name').value       = item.item_name;
    document.getElementById('category').value        = item.category;
    document.getElementById('unit_of_measure').value = item.unit_of_measure;
    document.getElementById('stock_quantity').value  = item.stock_quantity;
    document.getElementById('reorder_level').value   = item.reorder_level;
    new bootstrap.Modal(document.getElementById('itemModal')).show();
}
</script>

<?php render_footer(); ?>