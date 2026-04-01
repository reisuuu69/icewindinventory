<?php
// sheets.php — Google Sheets helper for Icewind HVAC

require_once 'vendor/autoload.php';

define('SPREADSHEET_ID',   '1ZzgNIKPO-KjengN5hKyU1ef2a8eSYIgBzxH9wUinll4');
define('CREDENTIALS_FILE', __DIR__ . '/credentials.json');
define('CACHE_DIR',        __DIR__ . '/cache/');
define('CACHE_TTL',        30);

if (!is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

function get_sheets_service() {
    $client = new Google\Client();
    $client->setAuthConfig(CREDENTIALS_FILE);
    $client->addScope(Google\Service\Sheets::SPREADSHEETS);
    return new Google\Service\Sheets($client);
}

function cache_path($sheetName) {
    return CACHE_DIR . 'sheet_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $sheetName) . '.json';
}

function cache_read($sheetName) {
    $path = cache_path($sheetName);
    if (!file_exists($path)) return null;
    if ((time() - filemtime($path)) > CACHE_TTL) return null;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function cache_write($sheetName, $data) {
    file_put_contents(cache_path($sheetName), json_encode($data), LOCK_EX);
}

function cache_invalidate($sheetName) {
    $path = cache_path($sheetName);
    if (file_exists($path)) unlink($path);
}

function normalize_sheet_header($header) {
    return strtolower(trim((string)$header));
}

function sheets_read($sheetName) {
    $cached = cache_read($sheetName);
    if ($cached !== null) return $cached;

    $service  = get_sheets_service();
    $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $sheetName);
    $rows     = $response->getValues();

    if (empty($rows)) return [];

    $headers = array_map('normalize_sheet_header', array_shift($rows));
    $result  = [];
    foreach ($rows as $row) {
        $row      = array_pad($row, count($headers), '');
        $result[] = array_combine($headers, $row);
    }

    cache_write($sheetName, $result);
    return $result;
}

function sheets_write($sheetName, $data, $headers) {
    $service = get_sheets_service();

    $values = [$headers];
    foreach ($data as $item) {
        $row = [];
        foreach ($headers as $col) {
            $row[] = $item[$col] ?? '';
        }
        $values[] = $row;
    }

    $service->spreadsheets_values->clear(
        SPREADSHEET_ID,
        $sheetName,
        new Google\Service\Sheets\ClearValuesRequest()
    );

    $body = new Google\Service\Sheets\ValueRange(['values' => $values]);
    $service->spreadsheets_values->update(
        SPREADSHEET_ID,
        $sheetName . '!A1',
        $body,
        ['valueInputOption' => 'RAW']
    );

    cache_invalidate($sheetName);
}

function col_to_letter($index) {
    $letter = '';
    $index++;
    while ($index > 0) {
        $index--;
        $letter = chr(65 + ($index % 26)) . $letter;
        $index  = intdiv($index, 26);
    }
    return $letter;
}

function do_transaction($txn_type, $item_type, $item_id, $item_name, $quantity, $notes = '') {

    if (!in_array($txn_type, ['release', 'return'])) {
        return ['success' => false, 'error' => 'Invalid transaction type.'];
    }
    if ((int)$quantity <= 0) {
        return ['success' => false, 'error' => 'Quantity must be greater than 0.'];
    }

    $sheetName = ($item_type === 'consumable') ? 'Consumables' : 'Accessories';
    $lookupId  = trim((string)$item_id);

    $service  = get_sheets_service();
    $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $sheetName);
    $rows     = $response->getValues();

    if (empty($rows)) {
        return ['success' => false, 'error' => "Sheet '{$sheetName}' is empty."];
    }

    $rawHeaders  = $rows[0];
    $normHeaders = array_map(fn($h) => strtolower(trim((string)$h)), $rawHeaders);

    $idColIdx    = array_search('id',             $normHeaders);
    $stockColIdx = array_search('stock_quantity', $normHeaders);

    if ($idColIdx === false) {
        return ['success' => false, 'error' =>
            "Header 'id' not found in {$sheetName}. Headers: " . implode(', ', $rawHeaders)
        ];
    }
    if ($stockColIdx === false) {
        return ['success' => false, 'error' =>
            "Header 'stock_quantity' not found in {$sheetName}. Headers: " . implode(', ', $rawHeaders)
        ];
    }

    $targetRowIndex = null;
    $current_stock  = 0;

    // Normalize id for comparison (handles string "4" vs int 4 from Sheets)
    $lookupIdNorm = is_numeric($lookupId) ? (string)(int)$lookupId : $lookupId;

    for ($i = 1; $i < count($rows); $i++) {
        $row         = array_pad($rows[$i], count($normHeaders), '');
        $sheetId     = trim((string)$row[$idColIdx]);
        $sheetIdNorm = is_numeric($sheetId) ? (string)(int)$sheetId : $sheetId;
        if ($sheetId === $lookupId || $sheetIdNorm === $lookupIdNorm) {
            $current_stock  = (int)$row[$stockColIdx];
            $targetRowIndex = $i;
            break;
        }
    }

    // Fallback: match by item_name if id lookup failed (handles missing/blank id column)
    if ($targetRowIndex === null && !empty($item_name)) {
        $nameColIdx = array_search('item_name', $normHeaders);
        if ($nameColIdx !== false) {
            for ($i = 1; $i < count($rows); $i++) {
                $row = array_pad($rows[$i], count($normHeaders), '');
                if (trim((string)$row[$nameColIdx]) === trim($item_name)) {
                    $current_stock  = (int)$row[$stockColIdx];
                    $targetRowIndex = $i;
                    break;
                }
            }
        }
    }

    if ($targetRowIndex === null) {
        $allIds = [];
        for ($i = 1; $i < count($rows); $i++) {
            $r = array_pad($rows[$i], count($normHeaders), '');
            $allIds[] = '"' . trim((string)$r[$idColIdx]) . '"';
        }
        return ['success' => false, 'error' =>
            "Item id=\"{$lookupId}\" not found in {$sheetName}. IDs present: [" . implode(', ', $allIds) . "]"
        ];
    }

    if ($txn_type === 'release') {
        if ($current_stock < (int)$quantity) {
            return ['success' => false, 'error' =>
                "Not enough stock. Available: {$current_stock}, requested: {$quantity}"
            ];
        }
        $new_stock = $current_stock - (int)$quantity;
    } else {
        $new_stock = $current_stock + (int)$quantity;
    }

    $colLetter = col_to_letter($stockColIdx);
    $sheetRow  = $targetRowIndex + 1;
    $range     = "{$sheetName}!{$colLetter}{$sheetRow}";

    $body = new Google\Service\Sheets\ValueRange(['values' => [[(string)$new_stock]]]);
    $service->spreadsheets_values->update(
        SPREADSHEET_ID,
        $range,
        $body,
        ['valueInputOption' => 'RAW']
    );

    cache_invalidate($sheetName);

    record_transaction($txn_type, $item_type, $item_id, $item_name, $quantity, $notes);

    return ['success' => true, 'error' => '', 'new_stock' => $new_stock];
}

function record_transaction($type, $item_type, $item_id, $item_name, $quantity, $notes = '') {
    $transactions = sheets_read('Transactions');

    $max_id = 0;
    foreach ($transactions as $txn) {
        if (isset($txn['id']) && (int)$txn['id'] > $max_id) {
            $max_id = (int)$txn['id'];
        }
    }

    $transaction = [
        'id'          => $max_id + 1,
        'type'        => $type,
        'item_type'   => $item_type,
        'item_id'     => $item_id,
        'item_name'   => $item_name,
        'quantity'    => $quantity,
        'recorded_by' => $_SESSION['user']['username'] ?? 'system',
        'recorded_at' => date('Y-m-d H:i:s'),
        'notes'       => $notes,
    ];

    $transactions[] = $transaction;
    sheets_write(
        'Transactions',
        $transactions,
        ['id','type','item_type','item_id','item_name','quantity','recorded_by','recorded_at','notes']
    );

    return $transaction;
}

function get_transactions($item_type = null, $item_id = null, $limit = 50) {
    $transactions = sheets_read('Transactions');

    if ($item_type) {
        $transactions = array_filter($transactions, fn($t) => ($t['item_type'] ?? '') === $item_type);
    }
    if ($item_id !== null) {
        $transactions = array_filter($transactions, fn($t) => (int)($t['item_id'] ?? -1) === (int)$item_id);
    }

    usort($transactions, fn($a, $b) =>
        strtotime($b['recorded_at'] ?? '0') - strtotime($a['recorded_at'] ?? '0')
    );

    return array_slice(array_values($transactions), 0, $limit);
}
