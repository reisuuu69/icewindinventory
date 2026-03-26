<?php
// sheets.php — Google Sheets helper for Icewind HVAC

require_once 'vendor/autoload.php';

define('SPREADSHEET_ID', 'YOUR_SPREADSHEET_ID_HERE'); // from the URL
define('CREDENTIALS_FILE', __DIR__ . '/credentials.json');

function get_sheets_service() {
    $client = new Google\Client();
    $client->setAuthConfig(CREDENTIALS_FILE);
    $client->addScope(Google\Service\Sheets::SPREADSHEETS);
    return new Google\Service\Sheets($client);
}

/**
 * Read all rows from a sheet tab as array of associative arrays
 * First row is treated as headers
 */
function sheets_read($sheetName) {
    $service = get_sheets_service();
    $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $sheetName);
    $rows = $response->getValues();

    if (empty($rows)) return [];

    $headers = array_shift($rows); // first row = column names
    $result = [];
    foreach ($rows as $row) {
        // Pad short rows to match header count
        $row = array_pad($row, count($headers), '');
        $result[] = array_combine($headers, $row);
    }
    return $result;
}

/**
 * Overwrite entire sheet with new data (keeps header row)
 */
function sheets_write($sheetName, $data, $headers) {
    $service = get_sheets_service();

    $values = [$headers]; // header row
    foreach ($data as $item) {
        $row = [];
        foreach ($headers as $col) {
            $row[] = $item[$col] ?? '';
        }
        $values[] = $row;
    }

    // Clear sheet first
    $service->spreadsheets_values->clear(
        SPREADSHEET_ID,
        $sheetName,
        new Google\Service\Sheets\ClearValuesRequest()
    );

    // Write new data
    $body = new Google\Service\Sheets\ValueRange(['values' => $values]);
    $service->spreadsheets_values->update(
        SPREADSHEET_ID,
        $sheetName . '!A1',
        $body,
        ['valueInputOption' => 'RAW']
    );
}