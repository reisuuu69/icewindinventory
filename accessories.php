<?php
/**
 * Icewind HVAC Inventory System - Accessories (JSON Version)
 */

require_once 'config.php';
require_once 'functions.php';

check_auth();

$error = '';
$success = '';

// =======================
// JSON CONFIG
// =======================
if (!defined('ACCESSORIES_FILE')) {
    define('ACCESSORIES_FILE', 'accessories.json');
}

// Load JSON
function loadAccessories() {
    if (!file_exists(ACCESSORIES_FILE)) return [];
    $data = json_decode(file_get_contents(ACCESSORIES_FILE), true);
    return $data ? $data : [];
}

// Save JSON
function saveAccessories($data) {
    file_put_contents(ACCESSORIES_FILE, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

// Generate ID
function generateId($data) {
    $ids = array_column($data, 'id');
    return $ids ? max($ids) + 1 : 1;
}

// =======================
// EXPORT CSV
// =======================
if (isset($_GET['export'])) {
    $data = loadAccessories();

    $csvData = [];
    foreach ($data as $item) {
        $csvData[] = [
            $item['id'],
            $item['item_name'],
            $item['category'],
            $item['stock_quantity'],
            $item['reorder_level']
        ];
    }

    export_to_csv('accessories_export_' . date('Y-m-d'), $csvData);
}

// =======================
// ADD / EDIT
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $data = loadAccessories();

    $id = $_POST['id'] ?? generateId($data);

    $newItem = [
        'id' => $id,
        'item_name' => $_POST['item_name'],
        'category' => $_POST['category'],
        'stock_quantity' => (int)$_POST['stock_quantity'],
        'reorder_level' => (int)$_POST['reorder_level']
    ];

    if ($_POST['action'] === 'add') {
        $newItem['id'] = generateId($data);
        $data[] = $newItem;
        $success = "Accessory added successfully!";
    }

    elseif ($_POST['action'] === 'edit') {
        foreach ($data as &$item) {
            if ($item['id'] == $id) {
                $item = $newItem;
                break;
            }
        }
        $success = "Accessory updated successfully!";
    }

    saveAccessories($data);
}

// =======================
// DELETE
// =======================
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $data = loadAccessories();

    $data = array_filter($data, function($item) use ($id) {
        return $item['id'] != $id;
    });

    saveAccessories(array_values($data));
    $success = "Accessory deleted successfully!";
}

// =======================
// FETCH DATA
// =======================
$items = loadAccessories();

// =======================
// SEARCH
// =======================
$search = $_GET['search'] ?? '';
if ($search) {
    $items = array_filter($items, function($item) use ($search) {
        return stripos($item['item_name'], $search) !== false ||
               stripos($item['category'], $search) !== false;
    });
}

render_header('Accessories');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold">Accessories Stock</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#itemModal" onclick="resetForm()">
            <i data-lucide="plus" class="me-1"></i>Add New Item
        </button>
        <a href="?export=1" class="btn btn-sm btn-outline-secondary">
            <i data-lucide="download" class="me-1"></i>Export CSV
        </a>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?php echo $success; ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?php echo $error; ?>
</div>
<?php endif; ?>

<!-- SEARCH -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form class="row g-3" method="GET">
            <div class="col-md-10">
                <input type="text" class="form-control" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100">Search</button>
            </div>
        </form>
    </div>
</div>

<!-- TABLE -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="px-4 py-3">Item Name</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Reorder</th>
                    <th>Status</th>
                    <th class="text-end px-4">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($items)): ?>
                <tr><td colspan="6" class="text-center py-5">No data</td></tr>
            <?php else: ?>
                <?php foreach ($items as $item): 
                    $stock = $item['stock_quantity'];
                    $reorder = $item['reorder_level'];
                    $isLow = $stock <= $reorder;
                ?>
                <tr>
                    <td class="px-4 fw-bold"><?php echo $item['item_name']; ?></td>
                    <td><?php echo $item['category']; ?></td>
                    <td class="<?php echo $isLow ? 'text-danger' : 'text-success'; ?>">
                        <?php echo $stock; ?>
                    </td>
                    <td><?php echo $reorder; ?></td>
                    <td>
                        <?php echo $isLow 
                            ? '<span class="badge bg-danger">Low</span>' 
                            : '<span class="badge bg-success">OK</span>'; ?>
                    </td>
                    <td class="text-end px-4">
                        <button class="btn btn-sm btn-primary" onclick='editItem(<?php echo json_encode($item); ?>)'>Edit</button>
                        <a href="?delete=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL -->
<div class="modal fade" id="itemModal">
<div class="modal-dialog">
<div class="modal-content">
<form method="POST">
<input type="hidden" name="action" id="formAction">
<input type="hidden" name="id" id="itemId">

<div class="modal-body">
<input class="form-control mb-2" name="item_name" id="item_name" placeholder="Item Name" required>
<input class="form-control mb-2" name="category" id="category" placeholder="Category" required>
<input class="form-control mb-2" type="number" name="stock_quantity" id="stock_quantity" placeholder="Stock">
<input class="form-control mb-2" type="number" name="reorder_level" id="reorder_level" placeholder="Reorder">
</div>

<div class="modal-footer">
<button class="btn btn-primary">Save</button>
</div>

</form>
</div>
</div>
</div>

<script>
function resetForm(){
    formAction.value='add';
    itemId.value='';
    item_name.value='';
    category.value='';
    stock_quantity.value='';
    reorder_level.value='';
}

function editItem(item){
    formAction.value='edit';
    itemId.value=item.id;
    item_name.value=item.item_name;
    category.value=item.category;
    stock_quantity.value=item.stock_quantity;
    reorder_level.value=item.reorder_level;

    new bootstrap.Modal(document.getElementById('itemModal')).show();
}
</script>

<?php render_footer(); ?>