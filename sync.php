<?php
session_start();
include 'db.php'; 

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// !!! --- EDIT THESE LINKS --- !!!
$rfid_log_url = "https://docs.google.com/spreadsheets/d/e/2PACX-1vSVw0kDdVp6p1ULaj_yRJk9HbWPpNtSOlA63Bj6VJmR0QB9hKJc_uyXJgOzQUDka4SPNXJ68SgGs39t/pub?gid=0&single=true&output=csv";
$master_list_url = "https://docs.google.com/spreadsheets/d/e/2PACX-1vSVw0kDdVp6p1ULaj_yRJk9HbWPpNtSOlA63Bj6VJmR0QB9hKJc_uyXJgOzQUDka4SPNXJ68SgGs39t/pub?gid=269816878&single=true&output=csv";
// !!! --- END EDIT --- !!!

set_time_limit(300); 

/* Helper Functions */
function fetch_csv($url) {
    if (strpos($url, 'docs.google.com') === false) return [];
    $data = @file_get_contents($url);
    if ($data === false || empty($data)) return [];
    $rows = array_map("str_getcsv", explode("\n", trim($data)));
    array_shift($rows); 
    return $rows;
}

function parse_date_to_sql($date_string) {
    $ts = strtotime($date_string);
    return ($ts) ? date('Y-m-d', $ts) : false;
}

function parse_time_to_sql($time_string) {
    $ts = strtotime($time_string);
    return ($ts) ? date('H:i:s', $ts) : null;
}

$synced_students = 0;
$synced_logs = 0;
$errors = [];

/*
 * ========================================
 * 1. SYNC MASTER STUDENT LIST (SAFE VERSION)
 * ========================================
 */
$master_rows = fetch_csv($master_list_url);

// --- CRITICAL CHANGE: Only Truncate if we got data! ---
if (!empty($master_rows) && count($master_rows) > 0) {
    
    // Disable Foreign Keys temporarily to allow Truncate
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $conn->query("TRUNCATE TABLE students"); 
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    // Insert with Password column
    $stmt = $conn->prepare("INSERT INTO students (roll_no, name, department, year, address, password) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($master_rows as $row) {
        if (count($row) < 4) continue;
        $roll = trim($row[0]);
        if (empty($roll)) continue;
        
        $name = trim($row[1]);
        $dept = trim($row[2]);
        $year = trim($row[3]);
        $addr = trim($row[4] ?? "");
        $pass = trim($row[5] ?? "12345"); // Fallback password
        
        $stmt->bind_param("ssssss", $roll, $name, $dept, $year, $addr, $pass);
        $stmt->execute();
        $synced_students++;
    }
    $stmt->close();
} else {
    $errors[] = "Master List Sync Skipped: Google Sheet returned empty.";
}


/*
 * ========================================
 * 2. SYNC RFID ATTENDANCE LOG
 * ========================================
 */
$rfid_rows = fetch_csv($rfid_log_url);

if (!empty($rfid_rows)) {
    $processed_today = []; 

    $stmt = $conn->prepare("INSERT IGNORE INTO attendance_log (student_roll, attendance_date, attendance_time, status) VALUES (?, ?, ?, ?)");
    
    foreach ($rfid_rows as $row) {
        if (count($row) < 3) continue; 
        
        $roll = trim($row[2]); 
        if (empty($roll)) continue;
        
        $date_sql = parse_date_to_sql(trim($row[0]));
        $time_sql = parse_time_to_sql(trim($row[1]));
        
        if (!$date_sql || !$time_sql) continue;

        $unique_key = $roll . "_" . $date_sql;
        if (isset($processed_today[$unique_key])) continue; 
        $processed_today[$unique_key] = true;

        $time_obj = strtotime(trim($row[1]));
        $status = ($time_obj > strtotime("12:30 PM")) ? "Late" : "Present";
        
        $stmt->bind_param("ssss", $roll, $date_sql, $time_sql, $status);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $synced_logs++;
            }
        }
    }
    $stmt->close();
} else {
    $errors[] = "Attendance Log Sync Skipped: Google Sheet returned empty.";
}

$conn->close();

$message = "Sync Complete!\\nStudents Loaded: $synced_students\\nNew Logs Added: $synced_logs";
if (!empty($errors)) {
    $message .= "\\nErrors: " . implode(", ", $errors);
}

echo "<script>
    alert('" . addslashes($message) . "');
    window.location.href = 'index.php';
</script>";
?>