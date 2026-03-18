<?php
require __DIR__ . '/vendor/autoload.php';
include 'db.php'; // Include database connection
session_start();

// Protect this page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("You must be logged in to do this.");
}

/* * ==================================================================
 * !!! --- EDIT THESE 3 VARIABLES --- !!!
 * ==================================================================
 */
$spreadsheet_id = '12CjQMQj1ieNZCHW-uLc6ebIWZR1d2M0QF70avGHBlMM'; 
$sheet_name = 'Sheet1'; 
$master_list_url = "https://docs.google.com/spreadsheets/d/e/2PACX-1vSVw0kDdVp6p1ULaj_yRJk9HbWPpNtSOlA63Bj6VJmR0QB9hKJc_uyXJgOzQUDka4SPNXJ68SgGs39t/pub?gid=269816878&single=true&output=csv";
/*
 * ==================================================================
 */

date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // [NEW RULE] Check the time *first*
    $cutoff_time = strtotime("7:00 PM");
    $current_time = time();

    if ($current_time > $cutoff_time) {
        // It's after 7 PM. Stop everything.
        echo "<script>
            alert('Manual attendance is closed for today. Submissions are only allowed before 7:00 PM.');
            window.location.href = 'index.php#manual';
        </script>";
        exit; // Stop the script
    }
    // [END OF NEW RULE]


    // --- 1. Get Master List (If time is OK) ---
    $data = @file_get_contents($master_list_url);
    if (!$data) {
        die("Error: Could not fetch Master List. Check the URL in submit-manual.php");
    }
    $rows = array_map("str_getcsv", explode("\n", trim($data)));
    array_shift($rows); // remove header
    $master_list = [];
    foreach($rows as $r) {
        $master_list[trim($r[0])] = $r; // Keyed by Roll No
    }

    // --- 2. Process Form Data ---
    $statuses = $_POST['status'] ?? [];
    $all_rolls = $_POST['student_roll'] ?? [];
    
    $google_rows_to_append = [];
    $db_rows_to_insert = [];

    $date_google = date("m/d/Y"); // Google Sheet format
    $date_sql = date("Y-m-d");   // SQL format
    $time_google = date("h:i A") . " (Manual)";
    $time_sql = date("H:i:s");
    
    foreach ($all_rolls as $roll) {
        if (isset($statuses[$roll]) && isset($master_list[$roll])) {
            $student = $master_list[$roll];
            
            // Row for Google Sheets
            $google_rows_to_append[] = [
                $date_google, $time_google, $roll,
                $student[1] ?? '', $student[2] ?? '', $student[3] ?? '', $student[4] ?? ''
            ];
            
            // Row for Local Database
            $db_rows_to_insert[] = [
                'roll' => $roll,
                'date' => $date_sql,
                'time' => $time_sql,
                'status' => 'Present' // Manual entries are always 'Present'
            ];
        }
    }
    
    $message_google = "No students sent to Google Sheets.";
    $message_db = "No students saved to local DB.";

    // --- 3. Send to Google Sheets ---
    if (!empty($google_rows_to_append)) {
        try {
            $client = new Google_Client();
            $client->setAuthConfig('service-key.json');
            $client->addScope(Google_Service_Sheets::SPREADSHEETS);
            $service = new Google_Service_Sheets($client);
            
            $body = new Google_Service_Sheets_ValueRange(['values' => $google_rows_to_append]);
            $params = ['valueInputOption' => 'USER_ENTERED'];
            
            $service->spreadsheets_values->append($spreadsheet_id, $sheet_name, $body, $params);
            $message_google = count($google_rows_to_append) . " students sent to Google Sheets.";
        } catch (Exception $e) {
            $message_google = "Error writing to Google Sheet: " . $e->getMessage();
        }
    }
    
    // --- 4. Send to Local Database ---
    if (!empty($db_rows_to_insert)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO attendance_log (student_roll, attendance_date, attendance_time, status) VALUES (?, ?, ?, ?)");
        $inserted_count = 0;
        foreach ($db_rows_to_insert as $row) {
            $stmt->bind_param("ssss", $row['roll'], $row['date'], $row['time'], $row['status']);
            if($stmt->execute()) {
                $inserted_count++;
            }
        }
        $stmt->close();
        $message_db = "$inserted_count students saved to local database.";
    }
    $conn->close();

    // --- 5. Redirect Back ---
    $final_message = "Google Sheets: " . $message_google . "\\nLocal DB: " . $message_db;
    echo "<script>alert('" . addslashes($final_message) . "'); window.location.href='index.php#manual';</script>";
    exit;
}
?>