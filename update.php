<?php

// Google Sheet CSV Link
$csv_url = "https://docs.google.com/spreadsheets/d/e/2PACX-1vSVw0kDdVp6p1ULaj_yRJk9HbWPpNtSOlA63Bj6VJmR0QB9hKJc_uyXJgOzQUDka4SPNXJ68SgGs39t/pub?gid=0&single=true&output=csv";

// Where to save processed attendance
$save_path = "data/attendance.csv";

if(!file_exists("data")) {
    mkdir("data", 0755, true); // Create the directory if it doesn't exist
}

// Fetch CSV
$data = @file_get_contents($csv_url); // Use @ to suppress warnings if fetch fails
if(!$data){
    die("Failed to load CSV from Google Sheets. Check link or internet connection.");
}

// Parse CSV to array
$rows = array_map("str_getcsv", explode("\n", trim($data)));
$header = array_shift($rows); // remove header from sheet

// Output CSV structure:
// Date | Time | Roll | Name | Dept | Year | Address | Status

$clean = [];
$seen = []; // to avoid duplicates

foreach($rows as $r) {

    if(count($r) < 6) continue; // skip empty or malformed rows

    $date = trim($r[0]);
    $time = trim($r[1]);
    $roll = trim($r[2]);
    $name = trim($r[3]);
    $dept = trim($r[4]);
    $year = trim($r[5]);
    $address = isset($r[6]) ? trim($r[6]) : "";

    $key = $roll . "_" . $date; // avoid duplicate entry same day

    if(!empty($roll) && !isset($seen[$key])){
        // Status rule: Late if after 12:30 PM
        $status = (strtotime($time) > strtotime("12:30 PM")) ? "Late" : "Present";

        $clean[] = [$date, $time, $roll, $name, $dept, $year, $address, $status];
        $seen[$key] = true;
    }
}

// Save cleaned CSV
$file = fopen($save_path, "w");
if(!$file) {
    die("Failed to open file for writing. Check permissions for the 'data' directory.");
}

fputcsv($file, ["Date","Time","Roll No","Name","Department","Year","Address","Status"]);
foreach($clean as $c){
    fputcsv($file, $c);
}
fclose($file);

echo "<script>alert('Attendance Updated Successfully!'); window.location.href='index.php';</script>";
exit;