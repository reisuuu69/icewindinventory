<?php
/**
 * Icewind HVAC Inventory System - Transaction History
 * Shows release/return history for consumables and accessories,
 * with date-range filter, item filter, and type filter.
 */

require_once 'config.php';
require_once 'functions.php';

check_auth();

// ─── Export CSV ───────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $rows = get_transactions(null, null, 5000);

    $filter_type      = trim($_GET['item_type'] ?? '');
    $filter_txn_type  = trim($_GET['txn_type']  ?? '');
    $filter_search    = trim($_GET['search']     ?? '');
    $filter_date_from = trim($_GET['date_from']  ?? '');
    $filter_date_to   = trim($_GET['date_to']    ?? '');

    if ($filter_type)      $rows = array_filter($rows, fn($r) => ($r['item_type'] ?? '') === $filter_type);
    if ($filter_txn_type)  $rows = array_filter($rows, fn($r) => ($r['type']      ?? '') === $filter_txn_type);
    if ($filter_search)    $rows = array_filter($rows, fn($r) =>
        stripos($r['item_name'] ?? '', $filter_search) !== false ||
        stripos($r['notes']     ?? '', $filter_search) !== false
    );
    if ($filter_date_from) {
        $from = strtotime($filter_date_from . ' 00:00:00');
        $rows = array_filter($rows, fn($r) => strtotime($r['recorded_at'] ?? '0') >= $from);
    }
    if ($filter_date_to) {
        $to = strtotime($filter_date_to . ' 23:59:59');
        $rows = array_filter($rows, fn($r) => strtotime($r['recorded_at'] ?? '0') <= $to);
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transaction_history_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Date & Time','Transaction Type','Item Type','Item Name','Quantity','Recorded By','Notes']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id']          ?? '',
            $r['recorded_at'] ?? '',
            ucfirst($r['type']      ?? ''),
            ucfirst($r['item_type'] ?? ''),
            $r['item_name']   ?? '',
            $r['quantity']    ?? '',
            $r['recorded_by'] ?? '',
            $r['notes']       ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ─── Fetch with filters ───────────────────────────────────────────
$filter_type      = trim($_GET['item_type'] ?? '');
$filter_txn_type  = trim($_GET['txn_type']  ?? '');
$filter_search    = trim($_GET['search']    ?? '');
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to   = trim($_GET['date_to']   ?? '');
$page             = max(1, intval($_GET['page'] ?? 1));
$per_page         = 30;

$all_rows = get_transactions(null, null, 5000);

if ($filter_type)     $all_rows = array_filter($all_rows, fn($r) => ($r['item_type'] ?? '') === $filter_type);
if ($filter_txn_type) $all_rows = array_filter($all_rows, fn($r) => ($r['type']      ?? '') === $filter_txn_type);
if ($filter_search)   $all_rows = array_filter($all_rows, fn($r) =>
    stripos($r['item_name'] ?? '', $filter_search) !== false ||
    stripos($r['notes']     ?? '', $filter_search) !== false
);
if ($filter_date_from) {
    $from = strtotime($filter_date_from . ' 00:00:00');
    $all_rows = array_filter($all_rows, fn($r) => strtotime($r['recorded_at'] ?? '0') >= $from);
}
if ($filter_date_to) {
    $to = strtotime($filter_date_to . ' 23:59:59');
    $all_rows = array_filter($all_rows, fn($r) => strtotime($r['recorded_at'] ?? '0') <= $to);
}

$all_rows   = array_values($all_rows);
$total_rows = count($all_rows);
$total_pages = max(1, ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$rows        = array_slice($all_rows, ($page - 1) * $per_page, $per_page);

// Summary counts for current filter set
$total_releases = count(array_filter($all_rows, fn($r) => ($r['type'] ?? '') === 'release'));
$total_returns  = count(array_filter($all_rows, fn($r) => ($r['type'] ?? '') === 'return'));
$total_qty_out  = array_sum(array_column(
    array_filter($all_rows, fn($r) => ($r['type'] ?? '') === 'release'), 'quantity'));
$total_qty_in   = array_sum(array_column(
    array_filter($all_rows, fn($r) => ($r['type'] ?? '') === 'return'),  'quantity'));

// Build query string helper (for pagination links)
function build_qs($overrides = []) {
    $base = [
        'item_type'  => $_GET['item_type']  ?? '',
        'txn_type'   => $_GET['txn_type']   ?? '',
        'search'     => $_GET['search']     ?? '',
        'date_from'  => $_GET['date_from']  ?? '',
        'date_to'    => $_GET['date_to']    ?? '',
        'page'       => $_GET['page']       ?? 1,
    ];
    return '?' . http_build_query(array_merge($base, $overrides));
}

require_once 'loading_screen.php';
render_header('History');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold"><i data-lucide="history" class="me-2"></i>Transaction History</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="<?= build_qs(['export' => 1, 'page' => '']) ?>" class="btn btn-sm btn-outline-secondary">
            <i data-lucide="download" class="me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-primary mb-1"><i data-lucide="list" style="width:22px;height:22px;"></i></div>
            <div class="fw-bold fs-4"><?= $total_rows ?></div>
            <div class="small text-muted">Total Transactions</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-success mb-1"><i data-lucide="arrow-up-circle" style="width:22px;height:22px;"></i></div>
            <div class="fw-bold fs-4"><?= $total_releases ?></div>
            <div class="small text-muted">Releases (<?= $total_qty_out ?> units)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-info mb-1"><i data-lucide="arrow-down-circle" style="width:22px;height:22px;"></i></div>
            <div class="fw-bold fs-4"><?= $total_returns ?></div>
            <div class="small text-muted">Returns (<?= $total_qty_in ?> units)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-warning mb-1"><i data-lucide="trending-down" style="width:22px;height:22px;"></i></div>
            <div class="fw-bold fs-4"><?= $total_qty_out - $total_qty_in ?></div>
            <div class="small text-muted">Net Units Out</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form class="row g-2" method="GET">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i data-lucide="search" style="width:15px;"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" name="search"
                           placeholder="Item name or notes…" value="<?= htmlspecialchars($filter_search) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select name="item_type" class="form-select">
                    <option value="">All Item Types</option>
                    <option value="consumable" <?= $filter_type === 'consumable' ? 'selected' : '' ?>>Consumable</option>
                    <option value="accessory"  <?= $filter_type === 'accessory'  ? 'selected' : '' ?>>Accessory</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="txn_type" class="form-select">
                    <option value="">All Types</option>
                    <option value="release" <?= $filter_txn_type === 'release' ? 'selected' : '' ?>>Release</option>
                    <option value="return"  <?= $filter_txn_type === 'return'  ? 'selected' : '' ?>>Return</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_from"
                       value="<?= htmlspecialchars($filter_date_from) ?>" placeholder="From date">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_to"
                       value="<?= htmlspecialchars($filter_date_to) ?>" placeholder="To date">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-outline-primary w-100">Go</button>
            </div>
        </form>
        <?php if ($filter_search || $filter_type || $filter_txn_type || $filter_date_from || $filter_date_to): ?>
        <div class="mt-2">
            <a href="history.php" class="small text-muted">
                <i data-lucide="x" style="width:12px;"></i> Clear filters
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2 px-4">
        <span class="text-muted small">
            Showing <?= count($rows) ?> of <?= $total_rows ?> transactions
            (Page <?= $page ?> of <?= $total_pages ?>)
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="px-4 py-3">Date &amp; Time</th>
                        <th>Txn Type</th>
                        <th>Item Type</th>
                        <th>Item Name</th>
                        <th>Qty</th>
                        <th>Recorded By</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No transactions match your filters.</td></tr>
                <?php else: foreach ($rows as $r):
                    $isRelease = ($r['type'] ?? '') === 'release';
                ?>
                    <tr>
                        <td class="px-4 text-muted small">
                            <?= htmlspecialchars(date('M d, Y g:i A', strtotime($r['recorded_at'] ?? ''))) ?>
                        </td>
                        <td>
                            <?php if ($isRelease): ?>
                                <span class="badge bg-success">
                                    <i data-lucide="arrow-up" style="width:11px;"></i> Release
                                </span>
                            <?php else: ?>
                                <span class="badge bg-info">
                                    <i data-lucide="arrow-down" style="width:11px;"></i> Return
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (($r['item_type'] ?? '') === 'consumable'): ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle">Consumable</span>
                            <?php else: ?>
                                <span class="badge bg-purple bg-opacity-10 text-purple border" style="color:#7c3aed;border-color:#ddd6fe;background:#f5f3ff;">Accessory</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold"><?= htmlspecialchars($r['item_name'] ?? '') ?></td>
                        <td>
                            <span class="fw-bold <?= $isRelease ? 'text-success' : 'text-info' ?>">
                                <?= $isRelease ? '-' : '+' ?><?= intval($r['quantity'] ?? 0) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['recorded_by'] ?? '') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($r['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-center py-3">
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= build_qs(['page' => $page - 1]) ?>">‹</a>
                </li>
                <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= build_qs(['page' => $p]) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= build_qs(['page' => $page + 1]) ?>">›</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php render_footer(); ?>