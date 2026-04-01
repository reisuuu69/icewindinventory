<?php
/**
 * Icewind HVAC Inventory System - Reports
 * Provides printable/exportable reports for:
 *  - Inventory summary (by status, type)
 *  - Consumables stock report
 *  - Accessories stock report
 *  - Transaction summary
 */

require_once 'config.php';
require_once 'functions.php';

check_auth();

// ─── CSV Export routing ───────────────────────────────────────────
$export = $_GET['export'] ?? '';

if ($export === 'inventory') {
    $data = read_json(INVENTORY_FILE);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Aircon Type','Brand/Model','HP','Model Number','Supplier','Status',
                   'Franchise Price','Subdealer Price','Cash Price','Card Price','Created At']);
    foreach ($data as $r) {
        fputcsv($out, [$r['id']??'',$r['aircon_type']??'',$r['brand_model']??'',$r['hp']??'',
                       $r['model_number']??'',$r['supplier']??'',$r['status']??'',
                       $r['franchise_price']??'',$r['subdealer_price']??'',
                       $r['cash_price']??'',$r['card_price']??'',$r['created_at']??'']);
    }
    fclose($out); exit;
}

if ($export === 'consumables') {
    $data = read_json(CONSUMABLES_FILE);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="consumables_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Item Name','Category','Unit','Stock Qty','Reorder Level','Status']);
    foreach ($data as $r) {
        $status = (int)($r['stock_quantity']??0) <= (int)($r['reorder_level']??0) ? 'Low Stock' : 'In Stock';
        fputcsv($out, [$r['id']??'',$r['item_name']??'',$r['category']??'',$r['unit_of_measure']??'',
                       $r['stock_quantity']??'',$r['reorder_level']??'',$status]);
    }
    fclose($out); exit;
}

if ($export === 'accessories') {
    $data = read_json(ACCESSORIES_FILE);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="accessories_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Item Name','Category','Stock Qty','Reorder Level','Status']);
    foreach ($data as $r) {
        $status = (int)($r['stock_quantity']??0) <= (int)($r['reorder_level']??0) ? 'Low' : 'OK';
        fputcsv($out, [$r['id']??'',$r['item_name']??'',$r['category']??'',
                       $r['stock_quantity']??'',$r['reorder_level']??'',$status]);
    }
    fclose($out); exit;
}

if ($export === 'transactions') {
    $data = get_transactions(null, null, 5000);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transactions_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Date','Type','Item Type','Item Name','Qty','Recorded By','Notes']);
    foreach ($data as $r) {
        fputcsv($out, [$r['id']??'',$r['recorded_at']??'',ucfirst($r['type']??''),
                       ucfirst($r['item_type']??''),$r['item_name']??'',$r['quantity']??'',
                       $r['recorded_by']??'',$r['notes']??'']);
    }
    fclose($out); exit;
}

if ($export === 'defectives') {
    $data = get_defectives();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="defectives_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Inventory ID','Brand/Model','Aircon Type','HP','Model No.',
                   'Reason','Resolution','Reported By','Reported At','Notes']);
    foreach ($data as $r) {
        fputcsv($out, [$r['id']??'',$r['inventory_id']??'',$r['brand_model']??'',
                       $r['aircon_type']??'',$r['hp']??'',$r['model_number']??'',
                       $r['reason']??'',$r['resolution']??'',$r['reported_by']??'',
                       $r['reported_at']??'',$r['notes']??'']);
    }
    fclose($out); exit;
}

// ─── Load all data for reports ────────────────────────────────────
$inventory     = read_json(INVENTORY_FILE);
$consumables   = read_json(CONSUMABLES_FILE);
$accessories   = read_json(ACCESSORIES_FILE);
$transactions  = get_transactions(null, null, 5000);
$defectives    = get_defectives();

// ── Inventory stats ───────────────────────────────────────────────
$inv_by_status = [];
$inv_by_type   = [];
foreach ($inventory as $i) {
    $s = $i['status'] ?? 'Unknown';
    $t = $i['aircon_type'] ?? 'Unknown';
    $inv_by_status[$s] = ($inv_by_status[$s] ?? 0) + 1;
    $inv_by_type[$t]   = ($inv_by_type[$t]   ?? 0) + 1;
}
arsort($inv_by_type);

// ── Consumables stats ─────────────────────────────────────────────
$cons_low      = array_filter($consumables, fn($i) => (int)($i['stock_quantity']??0) <= (int)($i['reorder_level']??0));
$cons_ok       = array_filter($consumables, fn($i) => (int)($i['stock_quantity']??0) >  (int)($i['reorder_level']??0));
$cons_by_cat   = [];
foreach ($consumables as $i) {
    $c = $i['category'] ?? 'Uncategorized';
    $cons_by_cat[$c] = ($cons_by_cat[$c] ?? 0) + 1;
}
arsort($cons_by_cat);

// ── Accessories stats ─────────────────────────────────────────────
$acc_low       = array_filter($accessories, fn($i) => (int)($i['stock_quantity']??0) <= (int)($i['reorder_level']??0));
$acc_ok        = array_filter($accessories, fn($i) => (int)($i['stock_quantity']??0) >  (int)($i['reorder_level']??0));
$acc_by_cat    = [];
foreach ($accessories as $i) {
    $c = $i['category'] ?? 'Uncategorized';
    $acc_by_cat[$c] = ($acc_by_cat[$c] ?? 0) + 1;
}
arsort($acc_by_cat);

// ── Transaction stats ─────────────────────────────────────────────
$txn_releases  = array_filter($transactions, fn($r) => ($r['type']??'') === 'release');
$txn_returns   = array_filter($transactions, fn($r) => ($r['type']??'') === 'return');
$txn_by_item   = [];
foreach ($transactions as $t) {
    $name = $t['item_name'] ?? 'Unknown';
    if (!isset($txn_by_item[$name])) $txn_by_item[$name] = ['release'=>0,'return'=>0];
    $txn_by_item[$name][$t['type'] ?? 'release']++;
}
arsort($txn_by_item);
$top_items = array_slice($txn_by_item, 0, 10, true);

// ── Defectives stats ──────────────────────────────────────────────
$def_by_res = [];
foreach ($defectives as $d) {
    $r = $d['resolution'] ?? 'Pending';
    $def_by_res[$r] = ($def_by_res[$r] ?? 0) + 1;
}

// ── Monthly transaction trend (last 6 months) ─────────────────────
$monthly = [];
for ($m = 5; $m >= 0; $m--) {
    $key = date('Y-m', strtotime("-{$m} months"));
    $monthly[$key] = ['release'=>0,'return'=>0,'label'=>date('M Y', strtotime("-{$m} months"))];
}
foreach ($transactions as $t) {
    $key = date('Y-m', strtotime($t['recorded_at'] ?? ''));
    if (isset($monthly[$key])) {
        $type = $t['type'] ?? 'release';
        if ($type === 'release' || $type === 'return') {
            $monthly[$key][$type]++;
        }
    }
}

require_once 'loading_screen.php';
render_header('Reports');
?>

<style>
.report-section { margin-bottom: 2.5rem; }
.report-section h2 { font-size: 1rem; font-weight: 700; letter-spacing: -0.01em; margin-bottom: 1rem; }
.stat-mini { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px 20px; }
.stat-mini .num { font-size:1.75rem; font-weight:700; letter-spacing:-0.04em; line-height:1; }
.stat-mini .lbl { font-size:0.75rem; color:#94a3b8; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin-top:4px; }
.bar-row { display:flex; align-items:center; gap:10px; margin-bottom:6px; }
.bar-label { width:140px; font-size:0.8rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex-shrink:0; }
.bar-track { flex:1; height:8px; background:#f1f5f9; border-radius:4px; overflow:hidden; }
.bar-fill  { height:100%; border-radius:4px; }
.bar-val   { width:36px; text-align:right; font-size:0.8rem; font-weight:600; color:#475569; flex-shrink:0; }
@media print {
    .sidebar, .navbar, .btn-toolbar, nav.col-md-3 { display:none !important; }
    main.col-md-9 { max-width:100%; flex:0 0 100%; }
    .no-print { display:none !important; }
}
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2 fw-bold mb-0"><i data-lucide="bar-chart-2" class="me-2"></i>Reports</h1>
        <p class="text-muted small mb-0">Generated: <?= date('F j, Y \a\t g:i A') ?></p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 gap-2 no-print">
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i data-lucide="download" class="me-1"></i>Export CSV
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="?export=inventory">   Inventory</a></li>
                <li><a class="dropdown-item" href="?export=consumables"> Consumables</a></li>
                <li><a class="dropdown-item" href="?export=accessories"> Accessories</a></li>
                <li><a class="dropdown-item" href="?export=transactions">Transactions</a></li>
                <li><a class="dropdown-item" href="?export=defectives">  Defective Units</a></li>
            </ul>
        </div>
        <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
            <i data-lucide="printer" class="me-1"></i>Print
        </button>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- INVENTORY REPORT                                               -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="report-section">
    <h2><i data-lucide="box" class="me-1" style="width:16px;"></i> Inventory Report</h2>
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="num"><?= count($inventory) ?></div>
                <div class="lbl">Total Units</div>
            </div>
        </div>
        <?php foreach (['Available'=>'success','Installed'=>'secondary','Reserved'=>'warning','Defective'=>'danger'] as $s=>$c): ?>
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="num text-<?= $c ?>"><?= $inv_by_status[$s] ?? 0 ?></div>
                <div class="lbl"><?= $s ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Units by Aircon Type</h6>
                    <?php
                    $max_type = max(array_values($inv_by_type) ?: [1]);
                    foreach ($inv_by_type as $type => $cnt):
                        $pct = round(($cnt / $max_type) * 100);
                    ?>
                    <div class="bar-row">
                        <span class="bar-label" title="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></span>
                        <div class="bar-track"><div class="bar-fill bg-primary" style="width:<?= $pct ?>%"></div></div>
                        <span class="bar-val"><?= $cnt ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($inv_by_type)): ?><p class="text-muted small">No data.</p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="bg-light">
                            <tr><th class="px-3 py-2">Brand / Model</th><th>HP</th><th>Status</th><th>Supplier</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($inventory, 0, 15) as $i): ?>
                            <tr>
                                <td class="px-3 fw-semibold small"><?= htmlspecialchars($i['brand_model']??'') ?></td>
                                <td class="small"><?= htmlspecialchars($i['hp']??'') ?></td>
                                <td>
                                    <?php $sc = match($i['status']??'') {
                                        'Available'=>'success','Installed'=>'secondary',
                                        'Reserved'=>'warning','Defective'=>'danger',default=>'light'}; ?>
                                    <span class="badge bg-<?= $sc ?>"><?= htmlspecialchars($i['status']??'') ?></span>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($i['supplier']??'') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($inventory) > 15): ?>
                            <tr><td colspan="4" class="text-center text-muted small py-2">
                                + <?= count($inventory) - 15 ?> more — <a href="inventory.php">View all</a>
                            </td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<hr>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- CONSUMABLES REPORT                                             -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="report-section">
    <h2><i data-lucide="droplet" class="me-1" style="width:16px;"></i> Consumables Report</h2>
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-mini"><div class="num"><?= count($consumables) ?></div><div class="lbl">Total Items</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini"><div class="num text-success"><?= count($cons_ok) ?></div><div class="lbl">In Stock</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini"><div class="num text-danger"><?= count($cons_low) ?></div><div class="lbl">Low / Out of Stock</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="num"><?= array_sum(array_column($consumables, 'stock_quantity')) ?></div>
                <div class="lbl">Total Units on Hand</div>
            </div>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">By Category</h6>
                    <?php
                    $max_cc = max(array_values($cons_by_cat) ?: [1]);
                    foreach ($cons_by_cat as $cat => $cnt):
                        $pct = round(($cnt / $max_cc) * 100);
                    ?>
                    <div class="bar-row">
                        <span class="bar-label" title="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></span>
                        <div class="bar-track"><div class="bar-fill bg-info" style="width:<?= $pct ?>%"></div></div>
                        <span class="bar-val"><?= $cnt ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($cons_by_cat)): ?><p class="text-muted small">No data.</p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-3 py-2">Item Name</th>
                                <th>Category</th><th>Unit</th>
                                <th>Stock</th><th>Reorder</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        // Sort: low stock first
                        usort($consumables, fn($a,$b) =>
                            ((int)($a['stock_quantity']??0) - (int)($a['reorder_level']??0)) -
                            ((int)($b['stock_quantity']??0) - (int)($b['reorder_level']??0))
                        );
                        foreach ($consumables as $i):
                            $isLow = (int)($i['stock_quantity']??0) <= (int)($i['reorder_level']??0);
                        ?>
                            <tr>
                                <td class="px-3 fw-semibold small"><?= htmlspecialchars($i['item_name']??'') ?></td>
                                <td class="small"><?= htmlspecialchars($i['category']??'') ?></td>
                                <td class="small"><?= htmlspecialchars($i['unit_of_measure']??'') ?></td>
                                <td class="fw-bold <?= $isLow ? 'text-danger' : 'text-success' ?> small"><?= intval($i['stock_quantity']??0) ?></td>
                                <td class="small text-muted"><?= intval($i['reorder_level']??0) ?></td>
                                <td><?= $isLow ? '<span class="badge bg-danger">Low</span>' : '<span class="badge bg-success">OK</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($consumables)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No consumables.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<hr>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- ACCESSORIES REPORT                                             -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="report-section">
    <h2><i data-lucide="settings" class="me-1" style="width:16px;"></i> Accessories Report</h2>
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-mini"><div class="num"><?= count($accessories) ?></div><div class="lbl">Total Items</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini"><div class="num text-success"><?= count($acc_ok) ?></div><div class="lbl">OK</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini"><div class="num text-danger"><?= count($acc_low) ?></div><div class="lbl">Low Stock</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="num"><?= array_sum(array_column($accessories, 'stock_quantity')) ?></div>
                <div class="lbl">Total Units on Hand</div>
            </div>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">By Category</h6>
                    <?php
                    $max_ac = max(array_values($acc_by_cat) ?: [1]);
                    foreach ($acc_by_cat as $cat => $cnt):
                        $pct = round(($cnt / $max_ac) * 100);
                    ?>
                    <div class="bar-row">
                        <span class="bar-label" title="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#7c3aed;"></div></div>
                        <span class="bar-val"><?= $cnt ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($acc_by_cat)): ?><p class="text-muted small">No data.</p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-3 py-2">Item Name</th>
                                <th>Category</th><th>Stock</th><th>Reorder</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        usort($accessories, fn($a,$b) =>
                            ((int)($a['stock_quantity']??0) - (int)($a['reorder_level']??0)) -
                            ((int)($b['stock_quantity']??0) - (int)($b['reorder_level']??0))
                        );
                        foreach ($accessories as $i):
                            $isLow = (int)($i['stock_quantity']??0) <= (int)($i['reorder_level']??0);
                        ?>
                            <tr>
                                <td class="px-3 fw-semibold small"><?= htmlspecialchars($i['item_name']??'') ?></td>
                                <td class="small"><?= htmlspecialchars($i['category']??'') ?></td>
                                <td class="fw-bold <?= $isLow ? 'text-danger' : 'text-success' ?> small"><?= intval($i['stock_quantity']??0) ?></td>
                                <td class="small text-muted"><?= intval($i['reorder_level']??0) ?></td>
                                <td><?= $isLow ? '<span class="badge bg-danger">Low</span>' : '<span class="badge bg-success">OK</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($accessories)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No accessories.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<hr>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- TRANSACTION SUMMARY                                            -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="report-section">
    <h2><i data-lucide="activity" class="me-1" style="width:16px;"></i> Transaction Summary</h2>
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-mini"><div class="num"><?= count($transactions) ?></div><div class="lbl">Total Transactions</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="num text-success"><?= count($txn_releases) ?></div>
                <div class="lbl">Releases</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="num text-info"><?= count($txn_returns) ?></div>
                <div class="lbl">Returns</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="num text-danger"><?= count($defectives) ?></div>
                <div class="lbl">Defective Units</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Monthly trend -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Monthly Activity (Last 6 Months)</h6>
                    <?php foreach ($monthly as $key => $m): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span><?= $m['label'] ?></span>
                            <span>R:<?= $m['release'] ?> / Rt:<?= $m['return'] ?></span>
                        </div>
                        <div class="bar-track" style="height:12px;">
                            <?php
                            $maxM = max(1, max(array_map(fn($x) => $x['release'] + $x['return'], $monthly)));
                            $rPct = round(($m['release'] / $maxM) * 100);
                            $rtPct = round(($m['return'] / $maxM) * 100);
                            ?>
                            <div style="height:100%;width:<?= $rPct ?>%;background:#16a34a;float:left;border-radius:4px 0 0 4px;"></div>
                            <div style="height:100%;width:<?= $rtPct ?>%;background:#0ea5e9;float:left;border-radius:0 4px 4px 0;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="d-flex gap-3 mt-2 small">
                        <span><span style="display:inline-block;width:10px;height:10px;background:#16a34a;border-radius:2px;"></span> Releases</span>
                        <span><span style="display:inline-block;width:10px;height:10px;background:#0ea5e9;border-radius:2px;"></span> Returns</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top items by activity -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Top Items by Transaction Volume</h6>
                    <?php if (empty($top_items)): ?>
                        <p class="text-muted small">No transaction data yet.</p>
                    <?php else:
                        $max_txn = max(array_map(fn($v) => $v['release'] + $v['return'], $top_items));
                        foreach ($top_items as $name => $counts):
                            $total = $counts['release'] + $counts['return'];
                            $pct   = round(($total / max(1, $max_txn)) * 100);
                    ?>
                    <div class="bar-row">
                        <span class="bar-label" title="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></span>
                        <div class="bar-track"><div class="bar-fill bg-warning" style="width:<?= $pct ?>%"></div></div>
                        <span class="bar-val"><?= $total ?></span>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<hr>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- DEFECTIVES SUMMARY                                             -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<?php if (!empty($defectives)): ?>
<div class="report-section">
    <h2><i data-lucide="alert-triangle" class="me-1 text-danger" style="width:16px;"></i> Defective Units Summary</h2>
    <div class="row g-3 mb-3">
        <?php
        $def_cfg = ['Pending'=>'danger','Under Repair'=>'warning','Repaired'=>'success',
                    'Written Off'=>'secondary','Returned to Supplier'=>'info'];
        foreach ($def_cfg as $label => $color): ?>
        <div class="col-6 col-md-2">
            <div class="stat-mini text-center">
                <div class="num text-<?= $color ?>"><?= $def_by_res[$label] ?? 0 ?></div>
                <div class="lbl"><?= $label ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="px-3 py-2">Brand/Model</th>
                        <th>Type</th><th>HP</th><th>Reason</th><th>Resolution</th><th>Reported At</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($defectives as $d):
                    $res = $d['resolution'] ?? 'Pending';
                    $bc  = $def_cfg[$res] ?? 'secondary';
                ?>
                    <tr>
                        <td class="px-3 fw-semibold small"><?= htmlspecialchars($d['brand_model']??'') ?></td>
                        <td class="small"><?= htmlspecialchars($d['aircon_type']??'') ?></td>
                        <td class="small"><?= htmlspecialchars($d['hp']??'') ?></td>
                        <td class="small"><?= htmlspecialchars($d['reason']??'') ?></td>
                        <td><span class="badge bg-<?= $bc ?>"><?= htmlspecialchars($res) ?></span></td>
                        <td class="small text-muted"><?= htmlspecialchars($d['reported_at']??'') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php render_footer(); ?>