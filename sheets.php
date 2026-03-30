<?php
// sheets.php — Google Sheets helper for Icewind HVAC

require_once 'vendor/autoload.php';


define('SPREADSHEET_ID',   '1ZzgNIKPO-KjengN5hKyU1ef2a8eSYIgBzxH9wUinll4');
define('CREDENTIALS_FILE', __DIR__ . '/credentials.json');
define('CACHE_DIR',        __DIR__ . '/cache/');
define('CACHE_TTL',        30); // seconds — tune as needed

// Make sure cache dir exists
if (!is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

function get_sheets_service() {
    $client = new Google\Client();
    $client->setAuthConfig(CREDENTIALS_FILE);
    $client->addScope(Google\Service\Sheets::SPREADSHEETS);
    return new Google\Service\Sheets($client);
}

// ── Cache helpers ────────────────────────────────────────────────────────────

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

// ── Public API ───────────────────────────────────────────────────────────────

function sheets_read($sheetName) {
    $cached = cache_read($sheetName);
    if ($cached !== null) return $cached;

    $service  = get_sheets_service();
    $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $sheetName);
    $rows     = $response->getValues();

    if (empty($rows)) return [];

    $headers = array_shift($rows);
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