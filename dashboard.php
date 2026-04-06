<?php
// ==================== ICEWIND HVAC DASHBOARD ====================
session_start();

require_once 'config.php';
require_once 'functions.php';


check_auth();

$inventory   = read_json(INVENTORY_FILE);
$consumables = read_json(CONSUMABLES_FILE);
$accessories = read_json(ACCESSORIES_FILE);

$total_inventory   = count($inventory);
$total_consumables = count($consumables);
$total_accessories = count($accessories);

$low_stock       = 0;
$low_stock_items = [];

foreach ($consumables as $item) {
    if (isset($item['stock_quantity'], $item['reorder_level']) &&
        $item['stock_quantity'] <= $item['reorder_level']) {
        $low_stock++;
        $low_stock_items[] = [
            'name'    => $item['item_name'] ?? '—',
            'type'    => 'Consumable',
            'stock'   => (int)$item['stock_quantity'],
            'reorder' => (int)$item['reorder_level'],
        ];
    }
}
foreach ($accessories as $item) {
    if (isset($item['stock_quantity'], $item['reorder_level']) &&
        $item['stock_quantity'] <= $item['reorder_level']) {
        $low_stock++;
        $low_stock_items[] = [
            'name'    => $item['item_name'] ?? '—',
            'type'    => 'Accessory',
            'stock'   => (int)$item['stock_quantity'],
            'reorder' => (int)$item['reorder_level'],
        ];
    }
}

// Fetch recent transactions
$recent_transactions = get_transactions(null, null, 10);
?>
<?php
require_once 'loading_screen.php';
render_header('Dashboard');
?>
<link href="css/dashboard.css" rel="stylesheet">


<div class="page">

    <!-- Page head -->
    <div class="page-head">
        <div>
            <h1>Dashboard</h1>
            <p>Icewind HVAC — Inventory overview</p>
        </div>
        <span class="page-date" id="js-date"></span>
    </div>

    <!-- Stat grid -->
    <div class="stat-grid">
        <div class="stat-cell">
            <div class="stat-icon">
                <i data-lucide="box"></i>
            </div>
            <div class="stat-label">Inventory</div>
            <div class="stat-value"><?= $total_inventory ?></div>
            <div class="stat-meta">AC units &amp; equipment</div>
        </div>
        <div class="stat-cell">
            <div class="stat-icon">
                <i data-lucide="package"></i>
            </div>
            <div class="stat-label">Consumables</div>
            <div class="stat-value"><?= $total_consumables ?></div>
            <div class="stat-meta">Refrigerants &amp; supplies</div>
        </div>
        <div class="stat-cell">
            <div class="stat-icon">
                <i data-lucide="wrench"></i>
            </div>
            <div class="stat-label">Accessories</div>
            <div class="stat-value"><?= $total_accessories ?></div>
            <div class="stat-meta">Tools &amp; spare parts</div>
        </div>
        <div class="stat-cell">
            <div class="stat-icon <?= $low_stock > 0 ? 'stat-icon--alert' : '' ?>">
                <i data-lucide="<?= $low_stock > 0 ? 'alert-triangle' : 'shield-check' ?>"></i>
            </div>
            <div class="stat-label">Low Stock</div>
            <div class="stat-value <?= $low_stock > 0 ? 'stat-value--alert' : '' ?>"><?= $low_stock ?></div>
            <div class="stat-meta"><?= $low_stock > 0 ? 'Need reorder' : 'All levels healthy' ?></div>
        </div>
    </div>

    <!-- Low stock section -->
    <div class="section-head">
        <h2>Stock alerts</h2>
        <?php if ($low_stock > 0): ?>
            <span class="badge-count"><?= $low_stock ?> item<?= $low_stock !== 1 ? 's' : '' ?> below threshold</span>
        <?php else: ?>
            <span class="badge-count badge-count--ok">All good</span>
        <?php endif; ?>
    </div>

    <div class="alert-panel">
        <?php if (empty($low_stock_items)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i data-lucide="shield-check"></i>
                </div>
                <h3>All stock levels are healthy</h3>
                <p>No items are currently below their reorder threshold.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item name</th>
                        <th>Type</th>
                        <th>Current stock</th>
                        <th>Reorder at</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($low_stock_items as $row):
                    $pct = $row['reorder'] > 0
                        ? min(100, round(($row['stock'] / $row['reorder']) * 100))
                        : 0;
                ?>
                    <tr>
                        <td><span class="item-name"><?= htmlspecialchars($row['name']) ?></span></td>
                        <td>
                            <span class="tag <?= $row['type'] === 'Consumable' ? 'tag--cons' : 'tag--acc' ?>">
                                <?= $row['type'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="stock-col">
                                <div class="mini-bar">
                                    <div class="mini-bar__fill" style="width:<?= $pct ?>%"></div>
                                </div>
                                <span class="stock-num"><?= $row['stock'] ?></span>
                            </div>
                        </td>
                        <td><span class="reorder-num"><?= $row['reorder'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <hr class="divider">

    <!-- Recent transactions section -->
    <div class="section-head">
        <h2>Recent transactions</h2>
        <span class="badge-count"><?= count($recent_transactions) ?> transaction<?= count($recent_transactions) !== 1 ? 's' : '' ?></span>
    </div>

    <div class="alert-panel">
        <?php if (empty($recent_transactions)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i data-lucide="history"></i>
                </div>
                <h3>No transactions yet</h3>
                <p>Releases and returns will appear here.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent_transactions as $txn): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('M d, g:i A', strtotime($txn['recorded_at'] ?? ''))) ?></td>
                        <td>
                            <span class="tag <?= ($txn['type'] ?? '') === 'release' ? 'tag--cons' : 'tag--acc' ?>">
                                <?= ucfirst($txn['type'] ?? '') ?>
                            </span>
                        </td>
                        <td><span class="item-name"><?= htmlspecialchars($txn['item_name'] ?? '—') ?></span></td>
                        <td><strong><?= htmlspecialchars($txn['quantity'] ?? '0') ?></strong></td>
                        <td><?= htmlspecialchars($txn['recorded_by'] ?? 'Unknown') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <hr class="divider">
    <div class="nav-strip">
        <a href="inventory.php"   class="nav-link-item"><i data-lucide="box"></i>     Inventory</a>
        <a href="consumables.php" class="nav-link-item"><i data-lucide="package"></i> Consumables</a>
        <a href="accessories.php" class="nav-link-item"><i data-lucide="wrench"></i>  Accessories</a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
lucide.createIcons();
const d = new Date();
document.getElementById('js-date').textContent =
    d.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
</script>

<?php render_footer(); ?>