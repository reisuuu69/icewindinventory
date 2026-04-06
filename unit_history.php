<?php
/**
 * Icewind HVAC Inventory System - Unit History
 * Full audit trail: additions, status changes, price edits, deletions, notes.
 * Status updates are performed in inventory.php and logged here automatically.
 */

require_once 'config.php';
require_once 'functions.php';

check_auth();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = $success = '';

// ─── Export CSV ───────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $rows = sheets_read('UnitHistory');

    $fe = trim($_GET['event_type'] ?? '');
    $fs = trim($_GET['search']     ?? '');
    $ff = trim($_GET['date_from']  ?? '');
    $ft = trim($_GET['date_to']    ?? '');

    if ($fe) $rows = array_filter($rows, fn($r) => ($r['event_type'] ?? '') === $fe);
    if ($fs) $rows = array_filter($rows, fn($r) =>
        stripos($r['brand_model'] ?? '', $fs) !== false ||
        stripos($r['aircon_type'] ?? '', $fs) !== false ||
        stripos($r['new_value']   ?? '', $fs) !== false ||
        stripos($r['notes']       ?? '', $fs) !== false
    );
    if ($ff) { $from = strtotime($ff . ' 00:00:00'); $rows = array_filter($rows, fn($r) => strtotime($r['changed_at'] ?? '0') >= $from); }
    if ($ft) { $to   = strtotime($ft . ' 23:59:59'); $rows = array_filter($rows, fn($r) => strtotime($r['changed_at'] ?? '0') <= $to);   }

    usort($rows, fn($a, $b) => strtotime($b['changed_at'] ?? '0') - strtotime($a['changed_at'] ?? '0'));

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="unit_history_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Date & Time','Unit ID','Brand/Model','Aircon Type','Event','Field','Old Value','New Value','Changed By','Notes']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id']           ?? '',
            $r['changed_at']   ?? '',
            $r['inventory_id'] ?? '',
            $r['brand_model']  ?? '',
            $r['aircon_type']  ?? '',
            ucwords(str_replace('_', ' ', $r['event_type'] ?? '')),
            $r['field']        ?? '',
            $r['old_value']    ?? '',
            $r['new_value']    ?? '',
            $r['changed_by']   ?? '',
            $r['notes']        ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ─── Add Manual Note ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_note') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $inv_id    = intval($_POST['inventory_id'] ?? 0);
        $note_text = trim($_POST['note_text'] ?? '');

        if (!$inv_id || !$note_text) {
            $error = 'Please select a unit and enter a note.';
        } else {
            $inv  = read_json(INVENTORY_FILE);
            $unit = null;
            foreach ($inv as $i) {
                if ((int)$i['id'] === $inv_id) { $unit = $i; break; }
            }
            if (!$unit) {
                $error = 'Unit not found.';
            } else {
                record_unit_history(
                    $inv_id,
                    $unit['brand_model'] ?? '',
                    $unit['aircon_type'] ?? '',
                    'note',
                    'notes',
                    '',
                    $note_text,
                    $note_text
                );
                header('Location: unit_history.php?success=note_added');
                exit;
            }
        }
    }
}

// ─── Filters & Pagination ─────────────────────────────────────────
$filter_event  = trim($_GET['event_type'] ?? '');
$filter_search = trim($_GET['search']     ?? '');
$filter_from   = trim($_GET['date_from']  ?? '');
$filter_to     = trim($_GET['date_to']    ?? '');
$filter_unit   = intval($_GET['unit_id']  ?? 0);
$page          = max(1, intval($_GET['page'] ?? 1));
$per_page      = 25;

$all_rows = sheets_read('UnitHistory');
usort($all_rows, fn($a, $b) => strtotime($b['changed_at'] ?? '0') - strtotime($a['changed_at'] ?? '0'));

// Apply filters
if ($filter_event)  $all_rows = array_filter($all_rows, fn($r) => ($r['event_type'] ?? '') === $filter_event);
if ($filter_unit)   $all_rows = array_filter($all_rows, fn($r) => (int)($r['inventory_id'] ?? 0) === $filter_unit);
if ($filter_search) $all_rows = array_filter($all_rows, fn($r) =>
    stripos($r['brand_model'] ?? '', $filter_search) !== false ||
    stripos($r['aircon_type'] ?? '', $filter_search) !== false ||
    stripos($r['new_value']   ?? '', $filter_search) !== false ||
    stripos($r['notes']       ?? '', $filter_search) !== false
);
if ($filter_from) { $from = strtotime($filter_from . ' 00:00:00'); $all_rows = array_filter($all_rows, fn($r) => strtotime($r['changed_at'] ?? '0') >= $from); }
if ($filter_to)   { $to   = strtotime($filter_to   . ' 23:59:59'); $all_rows = array_filter($all_rows, fn($r) => strtotime($r['changed_at'] ?? '0') <= $to);   }

$all_rows    = array_values($all_rows);
$total_rows  = count($all_rows);
$total_pages = max(1, ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$rows        = array_slice($all_rows, ($page - 1) * $per_page, $per_page);

// Summary counts (always from full unfiltered sheet)
$all_for_stats = sheets_read('UnitHistory');
$stat_counts = ['added' => 0, 'status_change' => 0, 'price_edit' => 0, 'deleted' => 0, 'note' => 0];
foreach ($all_for_stats as $r) {
    $t = $r['event_type'] ?? '';
    if (isset($stat_counts[$t])) $stat_counts[$t]++;
}

// All inventory items for the Add Note dropdown
$inventory = read_json(INVENTORY_FILE);

// Build QS helper
function uhs_qs($overrides = []) {
    $base = [
        'event_type' => $_GET['event_type'] ?? '',
        'search'     => $_GET['search']     ?? '',
        'date_from'  => $_GET['date_from']  ?? '',
        'date_to'    => $_GET['date_to']    ?? '',
        'unit_id'    => $_GET['unit_id']    ?? '',
        'page'       => $_GET['page']       ?? 1,
    ];
    return '?' . http_build_query(array_merge($base, $overrides));
}

$success = $success ?: ($_GET['success'] ?? '');

require_once 'loading_screen.php';
render_header('Unit History');
?>

<link href="css/unit_history.css" rel="stylesheet">

<!-- ── Page Header ─────────────────────────────────────────── -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2 fw-bold mb-0">
            <i data-lucide="clock" class="me-2 text-primary" style="width:22px;height:22px;"></i>Unit History
        </h1>
        <p class="text-muted small mb-0 mt-1">
            Full audit trail of every unit's lifecycle —
            <span class="text-success fw-semibold">additions</span>,
            <span class="text-warning fw-semibold">status changes</span>,
            <span class="text-purple fw-semibold">price edits</span>,
            <span class="text-danger fw-semibold">deletions</span>, and
            <span class="text-info fw-semibold">notes</span>.
            Status changes are made in <a href="inventory.php">Inventory</a>.
        </p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary"
                data-bs-toggle="modal" data-bs-target="#addNoteModal">
            <i data-lucide="message-square-plus" class="me-1" style="width:14px;"></i>Add Note
        </button>
        <a href="inventory.php" class="btn btn-sm btn-outline-warning">
            <i data-lucide="refresh-cw" class="me-1" style="width:14px;"></i>Update Status
        </a>
        <a href="<?= uhs_qs(['export' => 1, 'page' => '']) ?>" class="btn btn-sm btn-outline-secondary">
            <i data-lucide="download" class="me-1" style="width:14px;"></i>Export CSV
        </a>
    </div>
</div>

<!-- ── Alerts ──────────────────────────────────────────────── -->
<?php if ($success === 'note_added'): ?>
    <div class="alert alert-success alert-dismissible fade show py-2">
        <i data-lucide="check-circle" style="width:14px;" class="me-1"></i> Note recorded successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show py-2">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ── Info banner linking to inventory for status changes ─── -->
<div class="alert alert-light border d-flex align-items-center gap-3 py-2 mb-4">
    <i data-lucide="info" style="width:18px;height:18px;color:#0056b3;flex-shrink:0;"></i>
    <div class="small">
        <strong>Want to update a unit's status?</strong>
        Go to <a href="inventory.php" class="fw-semibold">Inventory</a> and click the
        <span class="badge bg-warning text-dark" style="font-size:11px;">
            <i data-lucide="refresh-cw" style="width:10px;"></i> Update Status
        </span>
        button next to any unit. Changes will automatically appear in this audit trail.
    </div>
</div>

<!-- ── Stat Cards ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <?php
    $stat_cfg = [
        'added'         => ['#16a34a', 'plus-circle',   'Units Added'],
        'status_change' => ['#ca8a04', 'activity',      'Status Changes'],
        'price_edit'    => ['#7c3aed', 'tag',           'Price Edits'],
        'deleted'       => ['#dc2626', 'trash-2',       'Deletions'],
        'note'          => ['#0284c7', 'message-square','Notes'],
    ];
    foreach ($stat_cfg as $key => [$color, $icon, $label]):
    ?>
    <div class="col-6 col-md">
        <a href="<?= uhs_qs(['event_type' => $key, 'page' => 1]) ?>"
           class="text-decoration-none"
           title="Filter by <?= $label ?>">
            <div class="stat-hist <?= $filter_event === $key ? 'border-2' : '' ?>"
                 style="<?= $filter_event === $key ? 'border-color:' . $color . ';' : '' ?>">
                <div style="color:<?= $color ?>;margin-bottom:10px;">
                    <i data-lucide="<?= $icon ?>" style="width:18px;height:18px;"></i>
                </div>
                <div class="num" style="color:<?= $color ?>"><?= $stat_counts[$key] ?></div>
                <div class="lbl"><?= $label ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
    <div class="col-6 col-md">
        <a href="unit_history.php" class="text-decoration-none" title="Show all events">
            <div class="stat-hist <?= !$filter_event ? 'border-2' : '' ?>">
                <div style="color:#0f172a;margin-bottom:10px;">
                    <i data-lucide="layers" style="width:18px;height:18px;"></i>
                </div>
                <div class="num"><?= array_sum($stat_counts) ?></div>
                <div class="lbl">Total Events</div>
            </div>
        </a>
    </div>
</div>

<!-- ── Filters ─────────────────────────────────────────────── -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i data-lucide="search" style="width:14px;"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" name="search"
                           placeholder="Brand, type, value, note…" value="<?= htmlspecialchars($filter_search) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select name="event_type" class="form-select">
                    <option value="">All Events</option>
                    <option value="added"         <?= $filter_event === 'added'         ? 'selected' : '' ?>>Added</option>
                    <option value="status_change" <?= $filter_event === 'status_change' ? 'selected' : '' ?>>Status Change</option>
                    <option value="price_edit"    <?= $filter_event === 'price_edit'    ? 'selected' : '' ?>>Price Edit</option>
                    <option value="deleted"       <?= $filter_event === 'deleted'       ? 'selected' : '' ?>>Deleted</option>
                    <option value="note"          <?= $filter_event === 'note'          ? 'selected' : '' ?>>Note</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="unit_id" class="form-select">
                    <option value="">All Units</option>
                    <?php foreach ($inventory as $inv_item): ?>
                    <option value="<?= (int)$inv_item['id'] ?>"
                            <?= $filter_unit === (int)$inv_item['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars(($inv_item['brand_model'] ?? '—') . ' #' . $inv_item['id']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_from"
                       value="<?= htmlspecialchars($filter_from) ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_to"
                       value="<?= htmlspecialchars($filter_to) ?>">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-outline-primary flex-fill">Go</button>
                <?php if ($filter_event || $filter_search || $filter_from || $filter_to || $filter_unit): ?>
                <a href="unit_history.php" class="btn btn-outline-secondary" title="Clear">
                    <i data-lucide="x" style="width:13px;"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($filter_event || $filter_search || $filter_from || $filter_to || $filter_unit): ?>
        <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-muted">Active filters:</span>
            <?php if ($filter_search): ?>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle">
                    Search: <?= htmlspecialchars($filter_search) ?>
                    <a href="<?= uhs_qs(['search'=>'','page'=>1]) ?>" class="text-primary ms-1 text-decoration-none">×</a>
                </span>
            <?php endif; ?>
            <?php if ($filter_event): ?>
                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle">
                    Event: <?= ucwords(str_replace('_',' ',$filter_event)) ?>
                    <a href="<?= uhs_qs(['event_type'=>'','page'=>1]) ?>" class="text-warning ms-1 text-decoration-none">×</a>
                </span>
            <?php endif; ?>
            <?php if ($filter_unit): ?>
                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle">
                    Unit #<?= $filter_unit ?>
                    <a href="<?= uhs_qs(['unit_id'=>'','page'=>1]) ?>" class="text-secondary ms-1 text-decoration-none">×</a>
                </span>
            <?php endif; ?>
            <span class="small text-muted ms-1">— <?= $total_rows ?> event<?= $total_rows !== 1 ? 's' : '' ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── History Table ───────────────────────────────────────── -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2 px-4">
        <span class="text-muted small">
            Showing <strong><?= count($rows) > 0 ? ($page-1)*$per_page+1 : 0 ?>–<?= min($page*$per_page, $total_rows) ?></strong>
            of <strong><?= $total_rows ?></strong> event<?= $total_rows !== 1 ? 's' : '' ?>
        </span>
        <span class="text-muted small">Page <?= $page ?> of <?= $total_pages ?></span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="px-4 py-3" style="width:155px;">Date &amp; Time</th>
                        <th style="width:120px;">Event</th>
                        <th>Unit</th>
                        <th>Field</th>
                        <th>Change</th>
                        <th>Notes</th>
                        <th style="width:110px;">Changed By</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <div style="margin-bottom:8px;">
                                <i data-lucide="inbox" style="width:32px;height:32px;opacity:0.2;"></i>
                            </div>
                            <?php if ($filter_event || $filter_search || $filter_from || $filter_to || $filter_unit): ?>
                                No events match your current filters.
                                <a href="unit_history.php" class="d-block mt-1 small">Clear filters</a>
                            <?php else: ?>
                                No unit history recorded yet.<br>
                                <span class="small text-muted">
                                    History is logged automatically when units are added, status-updated, or deleted via
                                    <a href="inventory.php">Inventory</a>.
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r):
                    $evt       = $r['event_type'] ?? 'note';
                    $dot_class = 'dot-' . $evt;
                    $badge_cls = 'event-' . $evt;
                    $evt_label = ucwords(str_replace('_', ' ', $evt));
                    $evt_icon  = match($evt) {
                        'added'         => 'plus-circle',
                        'status_change' => 'activity',
                        'price_edit'    => 'tag',
                        'deleted'       => 'trash-2',
                        'note'          => 'message-square',
                        default         => 'circle',
                    };
                ?>
                    <tr>
                        <td class="px-4">
                            <div class="small text-muted"><?= date('M d, Y', strtotime($r['changed_at'] ?? '')) ?></div>
                            <div class="small fw-semibold" style="font-variant-numeric:tabular-nums;">
                                <?= date('g:i A', strtotime($r['changed_at'] ?? '')) ?>
                            </div>
                        </td>
                        <td>
                            <span class="event-badge <?= $badge_cls ?>">
                                <i data-lucide="<?= $evt_icon ?>" style="width:10px;height:10px;"></i>
                                <?= $evt_label ?>
                            </span>
                        </td>
                        <td>
                            <div class="unit-chip">
                                <span class="<?= $dot_class ?> timeline-dot"></span>
                                <span title="Unit #<?= htmlspecialchars($r['inventory_id'] ?? '') ?>">
                                    <?= htmlspecialchars($r['brand_model'] ?? '—') ?>
                                </span>
                            </div>
                            <div class="small text-muted mt-1" style="padding-left:16px;">
                                <?= htmlspecialchars($r['aircon_type'] ?? '') ?>
                                <?php if ($r['inventory_id'] ?? ''): ?>
                                    · <a href="inventory.php" class="text-muted" style="font-size:11px;">#<?= htmlspecialchars($r['inventory_id']) ?></a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($r['field'] ?? ''): ?>
                                <code class="small" style="background:#f1f5f9;padding:2px 6px;border-radius:4px;color:#475569;">
                                    <?= htmlspecialchars($r['field']) ?>
                                </code>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $old = $r['old_value'] ?? '';
                            $new = $r['new_value'] ?? '';
                            if ($old !== '' && $new !== '' && $evt !== 'note'):
                            ?>
                                <span class="diff-old"><?= htmlspecialchars($old) ?></span>
                                <span class="diff-arrow">→</span>
                                <span class="diff-new"><?= htmlspecialchars($new) ?></span>
                            <?php elseif ($new !== ''): ?>
                                <span class="diff-new"><?= htmlspecialchars($new) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small" style="max-width:200px;">
                            <?= htmlspecialchars($r['notes'] ?? '') ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <div style="width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#0056b3,#0ea5e9);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <span style="font-size:9px;color:#fff;font-weight:700;text-transform:uppercase;">
                                        <?= strtoupper(substr($r['changed_by'] ?? '?', 0, 1)) ?>
                                    </span>
                                </div>
                                <span class="small"><?= htmlspecialchars($r['changed_by'] ?? '') ?></span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3 px-4">
        <span class="text-muted small"><?= $total_rows ?> events &bull; <?= $per_page ?> per page</span>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= uhs_qs(['page' => 1]) ?>">«</a>
                </li>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= uhs_qs(['page' => $page - 1]) ?>">‹</a>
                </li>
                <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= uhs_qs(['page' => $p]) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= uhs_qs(['page' => $page + 1]) ?>">›</a>
                </li>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= uhs_qs(['page' => $total_pages]) ?>">»</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- ADD NOTE MODAL                                             -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i data-lucide="message-square-plus" class="me-2" style="width:16px;"></i>Add Note to Unit
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action"     value="add_note">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Unit <span class="text-danger">*</span></label>
                        <select name="inventory_id" class="form-select" required>
                            <option value="">— Choose a unit —</option>
                            <?php foreach ($inventory as $inv_item): ?>
                            <option value="<?= (int)$inv_item['id'] ?>">
                                #<?= (int)$inv_item['id'] ?> — <?= htmlspecialchars($inv_item['brand_model'] ?? '') ?>
                                (<?= htmlspecialchars($inv_item['aircon_type'] ?? '') ?>
                                <?= htmlspecialchars($inv_item['hp'] ?? '') ?>HP)
                                · <?= htmlspecialchars($inv_item['status'] ?? '') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Note <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="note_text" rows="3"
                                  placeholder="e.g. Delivered to client, under warranty claim, scheduled for inspection…"
                                  required></textarea>
                    </div>
                    <div class="alert alert-info py-2 small mb-0">
                        <i data-lucide="info" style="width:13px;" class="me-1"></i>
                        Notes appear in this audit trail and are included in CSV exports.
                        To update a unit's status, use the
                        <a href="inventory.php" class="alert-link">Inventory</a> page.
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Add Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>lucide.createIcons();</script>

<?php render_footer(); ?>