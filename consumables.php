<?php
/**
 * Icewind HVAC Inventory System - Consumables
 * Database: consumables.json
 */

require_once 'config.php';
require_once 'functions.php';

check_auth();

$error = '';
$success = '';

// ─── JSON Helpers ────────────────────────────────────────────────
$jsonFile = __DIR__ . '/consumables.json';

function loadConsumables($file) {
    if (!file_exists($file)) {
        file_put_contents($file, json_encode([]));
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveConsumables($file, $data) {
    return file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT)) !== false;
}

// ─── Export to CSV ───────────────────────────────────────────────
if (isset($_GET['export'])) {
    $items = loadConsumables($jsonFile);
    $filename = 'consumables_export_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Item Name', 'Category', 'Unit of Measure', 'Stock Quantity', 'Reorder Level']);
    foreach ($items as $item) {
        fputcsv($out, [
            $item['id'],
            $item['item_name'],
            $item['category'],
            $item['unit_of_measure'],
            $item['stock_quantity'],
            $item['reorder_level'],
        ]);
    }
    fclose($out);
    exit;
}

// ─── Add / Edit ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $items = loadConsumables($jsonFile);

    $item_name    = trim($_POST['item_name'] ?? '');
    $category     = trim($_POST['category'] ?? '');
    $uom          = trim($_POST['unit_of_measure'] ?? '');
    $stock        = intval($_POST['stock_quantity'] ?? 0);
    $reorder      = intval($_POST['reorder_level'] ?? 0);

    if ($_POST['action'] === 'add') {
        // Generate new unique ID
        $newId = count($items) > 0 ? max(array_column($items, 'id')) + 1 : 1;

        $items[] = [
            'id'              => $newId,
            'item_name'       => $item_name,
            'category'        => $category,
            'unit_of_measure' => $uom,
            'stock_quantity'  => $stock,
            'reorder_level'   => $reorder,
        ];

        if (saveConsumables($jsonFile, $items))
            $success = "Consumable added successfully!";
        else
            $error = "Failed to save consumable.";

    } elseif ($_POST['action'] === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $found = false;
        foreach ($items as &$item) {
            if ($item['id'] === $id) {
                $item['item_name']       = $item_name;
                $item['category']        = $category;
                $item['unit_of_measure'] = $uom;
                $item['stock_quantity']  = $stock;
                $item['reorder_level']   = $reorder;
                $found = true;
                break;
            }
        }
        unset($item);

        if ($found) {
            if (saveConsumables($jsonFile, $items))
                $success = "Consumable updated successfully!";
            else
                $error = "Failed to update consumable.";
        } else {
            $error = "Consumable not found.";
        }
    }
}

// ─── Delete ──────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $items = loadConsumables($jsonFile);
    $deleteId = intval($_GET['delete']);
    $items = array_filter($items, fn($item) => $item['id'] !== $deleteId);
    if (saveConsumables($jsonFile, $items))
        $success = "Consumable deleted successfully!";
    else
        $error = "Failed to delete consumable.";
}

// ─── Fetch & Filter ──────────────────────────────────────────────
$items  = loadConsumables($jsonFile);
$search = trim($_GET['search'] ?? '');

if ($search) {
    $items = array_filter($items, function($item) use ($search) {
        return stripos($item['item_name'], $search) !== false
            || stripos($item['category'],  $search) !== false;
    });
}

render_header('Consumables');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold">Consumables Stock</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary me-2"
                data-bs-toggle="modal" data-bs-target="#itemModal"
                onclick="resetForm()">
            <i data-lucide="plus" class="me-1"></i>Add New Item
        </button>
        <a href="?export=1" class="btn btn-sm btn-outline-secondary">
            <i data-lucide="download" class="me-1"></i>Export CSV
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Search -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form class="row g-3" method="GET">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i data-lucide="search" style="width:18px;"></i>
                    </span>
                    <input type="text" class="form-control border-start-0"
                           name="search"
                           placeholder="Search Item Name or Category..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Search</button>
            </div>
        </form>
    </div>
</div>

<!-- Consumables Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="px-4 py-3">Item Name</th>
                        <th class="py-3">Category</th>
                        <th class="py-3">Unit</th>
                        <th class="py-3">Stock Quantity</th>
                        <th class="py-3">Reorder Level</th>
                        <th class="py-3">Status</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                No consumables found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php
                                $stock   = intval($item['stock_quantity'] ?? 0);
                                $reorder = intval($item['reorder_level']  ?? 0);
                                $isLow   = $stock <= $reorder;
                            ?>
                            <tr>
                                <td class="px-4 py-3 fw-bold">
                                    <?php echo htmlspecialchars($item['item_name'] ?? ''); ?>
                                </td>
                                <td class="py-3">
                                    <?php echo htmlspecialchars($item['category'] ?? ''); ?>
                                </td>
                                <td class="py-3">
                                    <?php echo htmlspecialchars($item['unit_of_measure'] ?? ''); ?>
                                </td>
                                <td class="py-3">
                                    <span class="fw-bold <?php echo $isLow ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo $stock; ?>
                                    </span>
                                </td>
                                <td class="py-3"><?php echo $reorder; ?></td>
                                <td class="py-3">
                                    <?php if ($isLow): ?>
                                        <span class="badge bg-danger">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1"
                                            onclick='editItem(<?php echo json_encode($item); ?>)'>
                                        <i data-lucide="edit-2" style="width:14px;"></i>
                                    </button>
                                    <a href="?delete=<?php echo intval($item['id']); ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Delete this consumable?')">
                                        <i data-lucide="trash-2" style="width:14px;"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalTitle">Add New Consumable</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="consumables.php" method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id"     id="itemId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Item Name</label>
                        <input type="text" class="form-control" name="item_name"
                               id="item_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category</label>
                        <input type="text" class="form-control" name="category"
                               id="category"
                               placeholder="e.g. Refrigerant, Pipe, Tape" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Unit of Measure</label>
                        <input type="text" class="form-control" name="unit_of_measure"
                               id="unit_of_measure"
                               placeholder="e.g. Tank, Roll, Box" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Stock Quantity</label>
                            <input type="number" class="form-control" name="stock_quantity"
                                   id="stock_quantity" min="0" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Reorder Level</label>
                            <input type="number" class="form-control" name="reorder_level"
                                   id="reorder_level" min="0" required>
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