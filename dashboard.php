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
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ─── Reset & base ─────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --blue:        #2563eb;
    --blue-light:  #eff6ff;
    --blue-border: #bfdbfe;
    --red:         #dc2626;
    --red-light:   #fef2f2;
    --red-border:  #fecaca;
    --green:       #16a34a;
    --green-light: #f0fdf4;
    --amber:       #d97706;
    --text-1:      #0f172a;
    --text-2:      #475569;
    --text-3:      #94a3b8;
    --border:      #e2e8f0;
    --surface:     #ffffff;
    --bg:          #f8fafc;
    --font:        'Plus Jakarta Sans', system-ui, sans-serif;
}

body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--text-1);
    font-size: 14px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}

/* ─── Layout ───────────────────────────────────────────────── */
.page {
    max-width: 1120px;
    margin: 0 auto;
    padding: 40px 32px 64px;
}

/* ─── Page title row ───────────────────────────────────────── */
.page-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 32px;
    gap: 16px;
}

.page-head h1 {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -0.025em;
    color: var(--text-1);
    line-height: 1.2;
}

.page-head p {
    font-size: 13px;
    color: var(--text-2);
    margin-top: 4px;
}

.page-date {
    font-size: 12px;
    color: var(--text-3);
    font-weight: 500;
    padding-top: 4px;
    white-space: nowrap;
}

/* ─── Stat grid ────────────────────────────────────────────── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1px;
    background: var(--border);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 32px;
}

.stat-cell {
    background: var(--surface);
    padding: 24px 24px 20px;
    display: flex;
    flex-direction: column;
    gap: 0;
}

.stat-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    margin-bottom: 16px;
    background: var(--bg);
    color: var(--text-2);
    flex-shrink: 0;
}

.stat-icon i { width: 16px; height: 16px; }

.stat-icon--alert {
    background: var(--red-light);
    color: var(--red);
}

.stat-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-3);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 6px;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    letter-spacing: -0.04em;
    color: var(--text-1);
    line-height: 1;
    margin-bottom: 8px;
}

.stat-value--alert { color: var(--red); }

.stat-meta {
    font-size: 12px;
    color: var(--text-3);
    font-weight: 400;
}

/* ─── Section head ─────────────────────────────────────────── */
.section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.section-head h2 {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-1);
    letter-spacing: -0.01em;
}

.badge-count {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--red-light);
    color: var(--red);
    border: 1px solid var(--red-border);
}

.badge-count--ok {
    background: var(--green-light);
    color: var(--green);
    border-color: #bbf7d0;
}

/* ─── Alert panel ──────────────────────────────────────────── */
.alert-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 32px;
}

/* ─── Table ────────────────────────────────────────────────── */
.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--text-3);
    padding: 10px 20px;
    background: var(--bg);
    border-bottom: 1px solid var(--border);
    text-align: left;
}

.data-table td {
    padding: 13px 20px;
    font-size: 13px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    color: var(--text-1);
}

.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover td { background: var(--bg); }

/* Item name */
.item-name { font-weight: 500; }

/* Type tag */
.tag {
    display: inline-block;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.03em;
    padding: 2px 7px;
    border-radius: 4px;
    border: 1px solid;
}

.tag--cons {
    color: var(--blue);
    background: var(--blue-light);
    border-color: var(--blue-border);
}

.tag--acc {
    color: #7c3aed;
    background: #f5f3ff;
    border-color: #ddd6fe;
}

/* Stock column */
.stock-col {
    display: flex;
    align-items: center;
    gap: 10px;
}

.mini-bar {
    width: 64px;
    height: 4px;
    background: var(--border);
    border-radius: 2px;
    overflow: hidden;
    flex-shrink: 0;
}

.mini-bar__fill {
    height: 100%;
    background: var(--red);
    border-radius: 2px;
}

.stock-num {
    font-size: 13px;
    font-weight: 600;
    color: var(--red);
    font-variant-numeric: tabular-nums;
    min-width: 20px;
}

.reorder-num {
    font-size: 12px;
    color: var(--text-3);
    font-variant-numeric: tabular-nums;
}

/* ─── Empty state ──────────────────────────────────────────── */
.empty-state {
    padding: 40px 20px;
    text-align: center;
}

.empty-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--green-light);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    color: var(--green);
}

.empty-icon i { width: 20px; height: 20px; }

.empty-state h3 {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-1);
    margin-bottom: 4px;
}

.empty-state p {
    font-size: 13px;
    color: var(--text-3);
}

/* ─── Nav strip ────────────────────────────────────────────── */
.nav-strip {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.nav-link-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-2);
    background: var(--surface);
    border: 1px solid var(--border);
    text-decoration: none;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}

.nav-link-item i { width: 14px; height: 14px; }

.nav-link-item:hover {
    color: var(--blue);
    border-color: var(--blue-border);
    background: var(--blue-light);
    text-decoration: none;
}

/* ─── Divider ──────────────────────────────────────────────── */
.divider {
    border: none;
    border-top: 1px solid var(--border);
    margin: 28px 0;
}

/* ─── Responsive ───────────────────────────────────────────── */
@media (max-width: 900px) {
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
    .stat-cell:first-child  { border-radius: 11px 0 0 0; }
    .stat-cell:nth-child(2) { border-radius: 0 11px 0 0; }
    .stat-cell:nth-child(3) { border-radius: 0 0 0 11px; }
    .stat-cell:last-child   { border-radius: 0 0 11px 0; }
}

@media (max-width: 600px) {
    .page { padding: 24px 16px 48px; }
    .stat-grid { grid-template-columns: 1fr 1fr; }
    .stat-value { font-size: 26px; }
    .page-head { flex-direction: column; }
    .data-table th { display: none; }
    .data-table td { display: block; border-bottom: none; padding: 6px 16px; }
    .data-table tr { display: block; border-bottom: 1px solid var(--border); padding: 10px 0; }
    .data-table tbody tr:last-child { border-bottom: none; }
}
</style>


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