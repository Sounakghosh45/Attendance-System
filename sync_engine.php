<?php
// sync_engine.php
require __DIR__ . '/vendor/autoload.php';

// --- CONFIGURATION ---
define('SPREADSHEET_ID', '12CjQMQj1ieNZCHW-uLc6ebIWZR1d2M0QF70avGHBlMM'); 
define('CREDENTIALS_PATH', __DIR__ . '/service-key.json'); 

// !!! --- CRITICAL SETTINGS FROM YOUR URL --- !!!
define('SHEET_NAME', 'Sheet2');      // The name of the tab
define('SHEET_GID', 269816878);      // The ID from your URL (gid=269816878)
// !!! ------------------------------------ !!!

function getGoogleService() {
    $client = new Google\Client();
    $client->setApplicationName('Upathita Sync');
    $client->setScopes([Google\Service\Sheets::SPREADSHEETS]);
    $client->setAuthConfig(CREDENTIALS_PATH);
    return new Google\Service\Sheets($client);
}

// HELPER: Find Row Number by Roll No
function findRowByRoll($service, $rollNo) {
    // Search in Sheet2
    $range = SHEET_NAME . '!A:A'; 
    $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $range);
    $values = $response->getValues();
    
    if (empty($values)) return -1;
    
    foreach ($values as $index => $row) {
        // Check Column A (Index 0)
        if (isset($row[0]) && trim($row[0]) == $rollNo) {
            return $index + 1; // Return 1-based Row Number
        }
    }
    return -1;
}

// 1. ADD STUDENT TO SHEET
function google_add_student($data) {
    // Expected $data: [Roll, Name, Dept, Year, Addr, Password]
    $service = getGoogleService();
    $body = new Google\Service\Sheets\ValueRange(['values' => [$data]]);
    $params = ['valueInputOption' => 'USER_ENTERED'];
    
    try {
        // FIXED: Now writes to 'Sheet2' instead of 'Sheet1'
        $service->spreadsheets_values->append(SPREADSHEET_ID, SHEET_NAME, $body, $params);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// 2. UPDATE STUDENT IN SHEET
function google_update_student($rollNo, $data) {
    $service = getGoogleService();
    $row = findRowByRoll($service, $rollNo);
    
    if ($row > 0) {
        // Update Columns A to F on 'Sheet2'
        $range = SHEET_NAME . "!A{$row}:F{$row}"; 
        $body = new Google\Service\Sheets\ValueRange(['values' => [$data]]);
        $params = ['valueInputOption' => 'USER_ENTERED'];
        
        try {
            $service->spreadsheets_values->update(SPREADSHEET_ID, $range, $body, $params);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    return false;
}

// 3. DELETE STUDENT FROM SHEET
function google_delete_student($rollNo) {
    $service = getGoogleService();
    $row = findRowByRoll($service, $rollNo);
    
    if ($row > 0) {
        // FIXED: Uses the correct GID (269816878) instead of 0
        $sheetId = SHEET_GID; 
        
        $request = new Google\Service\Sheets\Request([
            'deleteDimension' => [
                'range' => [
                    'sheetId' => $sheetId,
                    'dimension' => 'ROWS',
                    'startIndex' => $row - 1, 
                    'endIndex' => $row
                ]
            ]
        ]);
        
        $batch = new Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
            'requests' => [$request]
        ]);
        
        try {
            $service->spreadsheets->batchUpdate(SPREADSHEET_ID, $batch);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    return false;
}
?>