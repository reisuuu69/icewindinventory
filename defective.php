<?php
/**
 * Icewind HVAC Inventory System - Defective Units
 * Tracks inventory items marked as defective, with reason, resolution, and status.
 * Google Sheets tab: "Defectives"
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
    $rows = get_defectives();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="defective_units_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Inventory ID','Brand/Model','Aircon Type','HP','Serial / Model No.',
                   'Reason','Resolution Status','Reported By','Reported At','Notes']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id']          ?? '',
            $r['inventory_id'] ?? '',
            $r['brand_model'] ?? '',
            $r['aircon_type'] ?? '',
            $r['hp']          ?? '',
            $r['model_number'] ?? '',
            $r['reason']      ?? '',
            $r['resolution']  ?? '',
            $r['reported_by'] ?? '',
            $r['reported_at'] ?? '',
            $r['notes']       ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ─── Mark item as defective (POST action=mark) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $inventory_id = intval($_POST['inventory_id'] ?? 0);
        $reason       = trim($_POST['reason'] ?? '');
        $notes        = trim($_POST['notes'] ?? '');

        if (!$inventory_id || !$reason) {
            $error = 'Please select an item and provide a reason.';
        } else {
            // Load the inventory item for denormalized info
            $inv   = read_json(INVENTORY_FILE);
            $found = null;
            foreach ($inv as $it) {
                if ((int)$it['id'] === $inventory_id) { $found = $it; break; }
            }
            if (!$found) {
                $error = 'Inventory item not found.';
            } else {
                // Update status in Inventory sheet to "Defective"
                foreach ($inv as &$it) {
                    if ((int)$it['id'] === $inventory_id) {
                        $it['status'] = 'Defective';
                        break;
                    }
                } unset($it);
                write_json(INVENTORY_FILE, $inv);

                // Record defective entry
                record_defective([
                    'inventory_id' => $inventory_id,
                    'brand_model'  => $found['brand_model']  ?? '',
                    'aircon_type'  => $found['aircon_type']  ?? '',
                    'hp'           => $found['hp']           ?? '',
                    'model_number' => $found['model_number'] ?? '',
                    'reason'       => $reason,
                    'resolution'   => 'Pending',
                    'notes'        => $notes,
                ]);

                header('Location: defective.php?success=marked'); exit;
            }
        }
    }
}

// ─── Update resolution status (POST action=resolve) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resolve') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $defective_id = intval($_POST['defective_id'] ?? 0);
        $resolution   = trim($_POST['resolution'] ?? '');
        $allowed      = ['Pending', 'Under Repair', 'Repaired', 'Written Off', 'Returned to Supplier'];

        if (!in_array($resolution, $allowed)) {
            $error = 'Invalid resolution status.';
        } else {
            $rows = get_defectives();
            $updated = false;
            foreach ($rows as &$row) {
                if ((int)$row['id'] === $defective_id) {
                    $row['resolution'] = $resolution;

                    // If repaired, restore inventory status to Available
                    if ($resolution === 'Repaired') {
                        $inv = read_json(INVENTORY_FILE);
                        foreach ($inv as &$it) {
                            if ((int)$it['id'] === (int)$row['inventory_id']) {
                                $it['status'] = 'Available';
                                break;
                            }
                        } unset($it);
                        write_json(INVENTORY_FILE, $inv);
                    }

                    $updated = true;
                    break;
                }
            } unset($row);

            if ($updated) {
                write_defectives($rows);
                header('Location: defective.php?success=resolved'); exit;
            } else {
                $error = 'Defective record not found.';
            }
        }
    }
}

// ─── Delete defective record ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_defective_id'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $id   = intval($_POST['delete_defective_id']);
        $rows = array_values(array_filter(get_defectives(), fn($r) => (int)$r['id'] !== $id));
        write_defectives($rows);
        header('Location: defective.php?success=deleted'); exit;
    }
}

// ─── Fetch data ───────────────────────────────────────────────────
$defectives = get_defectives();
$inventory  = read_json(INVENTORY_FILE);

// Available items for the "mark defective" dropdown
$available_items = array_filter($inventory, fn($i) =>
    in_array($i['status'] ?? '', ['Available', 'Reserved', 'Installed'])
);

// Filters
$filter_resolution = trim($_GET['resolution'] ?? '');
$filter_search     = trim($_GET['search'] ?? '');

$display = $defectives;
if ($filter_resolution) {
    $display = array_filter($display, fn($r) => ($r['resolution'] ?? '') === $filter_resolution);
}
if ($filter_search) {
    $display = array_filter($display, fn($r) =>
        stripos($r['brand_model'] ?? '', $filter_search) !== false ||
        stripos($r['reason']      ?? '', $filter_search) !== false ||
        stripos($r['aircon_type'] ?? '', $filter_search) !== false
    );
}

$success = $success ?: ($_GET['success'] ?? '');

require_once 'loading_screen.php';
render_header('Defective Units');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold"><i data-lucide="alert-triangle" class="me-2 text-danger"></i>Defective Units</h1>
    <div class="btn-toolbar mb-2 mb-md-0 gap-2">
        <button type="button" class="btn btn-sm btn-danger"
                data-bs-toggle="modal" data-bs-target="#markDefectiveModal">
            <i data-lucide="x-circle" class="me-1"></i>Mark Item Defective
        </button>
        <a href="?export=1" class="btn btn-sm btn-outline-secondary">
            <i data-lucide="download" class="me-1"></i>Export CSV
        </a>
    </div>
</div>

<?php if ($success === 'marked'):  ?><div class="alert alert-warning alert-dismissible fade show">Item marked as defective. Inventory status updated. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php elseif ($success === 'resolved'): ?><div class="alert alert-success alert-dismissible fade show">Resolution status updated successfully! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php elseif ($success === 'deleted'): ?><div class="alert alert-success alert-dismissible fade show">Defective record removed. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Summary cards -->
<?php
$counts = ['Pending'=>0,'Under Repair'=>0,'Repaired'=>0,'Written Off'=>0,'Returned to Supplier'=>0];
foreach ($defectives as $d) {
    $res = $d['resolution'] ?? 'Pending';
    if (isset($counts[$res])) $counts[$res]++;
}
?>
<div class="row g-3 mb-4">
    <?php
    $card_cfg = [
        'Pending'              => ['danger',  'clock'],
        'Under Repair'         => ['warning', 'tool'],
        'Repaired'             => ['success', 'check-circle'],
        'Written Off'          => ['secondary','x-square'],
        'Returned to Supplier' => ['info',    'truck'],
    ];
    foreach ($card_cfg as $label => [$color, $icon]): ?>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="text-<?= $color ?> mb-1"><i data-lucide="<?= $icon ?>" style="width:22px;height:22px;"></i></div>
            <div class="fw-bold fs-4 text-<?= $color ?>"><?= $counts[$label] ?></div>
            <div class="small text-muted"><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form class="row g-2" method="GET">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i data-lucide="search" style="width:16px;"></i></span>
                    <input type="text" class="form-control border-start-0" name="search"
                           placeholder="Search brand, type, reason…" value="<?= htmlspecialchars($filter_search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="resolution" class="form-select">
                    <option value="">All Resolutions</option>
                    <?php foreach (array_keys($card_cfg) as $opt): ?>
                    <option value="<?= $opt ?>" <?= $filter_resolution === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="px-4 py-3">Brand / Model</th>
                        <th>Type</th>
                        <th>HP</th>
                        <th>Model No.</th>
                        <th>Reason</th>
                        <th>Resolution</th>
                        <th>Reported By</th>
                        <th>Reported At</th>
                        <th>Notes</th>
                        <th class="text-end px-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($display)): ?>
                    <tr><td colspan="10" class="text-center py-5 text-muted">No defective records found.</td></tr>
                <?php else: foreach ($display as $d):
                    $res = $d['resolution'] ?? 'Pending';
                    $badge = match($res) {
                        'Pending'              => 'danger',
                        'Under Repair'         => 'warning',
                        'Repaired'             => 'success',
                        'Written Off'          => 'secondary',
                        'Returned to Supplier' => 'info',
                        default                => 'light',
                    };
                ?>
                    <tr>
                        <td class="px-4 fw-bold"><?= htmlspecialchars($d['brand_model'] ?? '') ?></td>
                        <td><?= htmlspecialchars($d['aircon_type'] ?? '') ?></td>
                        <td><?= htmlspecialchars($d['hp'] ?? '') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($d['model_number'] ?? '') ?></td>
                        <td><?= htmlspecialchars($d['reason'] ?? '') ?></td>
                        <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($res) ?></span></td>
                        <td><?= htmlspecialchars($d['reported_by'] ?? '') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($d['reported_at'] ?? '') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($d['notes'] ?? '') ?></td>
                        <td class="text-end px-4">
                            <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                    onclick='openResolveModal(<?= json_encode($d) ?>)'>
                                <i data-lucide="edit-2" style="width:13px;"></i> Update
                            </button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="delete_defective_id" value="<?= (int)($d['id'] ?? 0) ?>">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Remove this defective record?')">
                                    <i data-lucide="trash-2" style="width:13px;"></i>
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

<!-- Mark Defective Modal -->
<div class="modal fade" id="markDefectiveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">Mark Item as Defective</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action"     value="mark">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Inventory Item <span class="text-danger">*</span></label>
                        <select name="inventory_id" class="form-select" required>
                            <option value=""> Choose item </option>
                            <?php foreach ($available_items as $it): ?>
                            <option value="<?= (int)$it['id'] ?>">
                                <?= htmlspecialchars($it['brand_model'] . ' (' . $it['aircon_type'] . ' ' . $it['hp'] . 'HP) ' . $it['status']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason for Defect <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="reason"
                               placeholder="e.g. Compressor failure, Refrigerant leak…" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Additional Notes</label>
                        <textarea class="form-control" name="notes" rows="2"
                                  placeholder="e.g. Job site, customer name, technician…"></textarea>
                    </div>
                    <div class="alert alert-warning py-2 small mb-0">
                        <i data-lucide="info" style="width:14px;" class="me-1"></i>
                        The item's status in Inventory will be changed to <strong>Defective</strong>.
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">Mark as Defective</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Resolve/Update Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Update Resolution Status</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action"       value="resolve">
                <input type="hidden" name="defective_id" id="resolve_defective_id">
                <input type="hidden" name="csrf_token"   value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body p-4">
                    <p class="fw-semibold mb-1">Item: <span id="resolve_item_name" class="fw-normal"></span></p>
                    <p class="text-muted small mb-3">Reason: <span id="resolve_reason"></span></p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Resolution Status</label>
                        <select name="resolution" id="resolve_resolution" class="form-select" required>
                            <option value="Pending">Pending</option>
                            <option value="Under Repair">Under Repair</option>
                            <option value="Repaired">Repaired restore to Available</option>
                            <option value="Written Off">Written Off</option>
                            <option value="Returned to Supplier">Returned to Supplier</option>
                        </select>
                    </div>
                    <div class="alert alert-info py-2 small mb-0">
                        <i data-lucide="info" style="width:14px;" class="me-1"></i>
                        Selecting <strong>Repaired</strong> will restore the unit's inventory status to <strong>Available</strong>.
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openResolveModal(d) {
    document.getElementById('resolve_defective_id').value = d.id;
    document.getElementById('resolve_item_name').textContent = d.brand_model + ' ' + d.aircon_type;
    document.getElementById('resolve_reason').textContent = d.reason;
    document.getElementById('resolve_resolution').value = d.resolution || 'Pending';
    new bootstrap.Modal(document.getElementById('resolveModal')).show();
}
</script>

<?php render_footer(); ?>