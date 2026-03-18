<?php
session_start();
include 'db.php'; // Include your database connection

// Protect this page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("You must be logged in to download reports.");
}

date_default_timezone_set('Asia/Kolkata');

// Get the date from the URL (e.g., download.php?date=11/11/2025)
// If no date is set, default to today
$selected_date_str = $_GET['date'] ?? date('m/d/Y');
$selected_date_sql = date('Y-m-d', strtotime($selected_date_str));

// Set headers to force a CSV download
$filename = "attendance_report_" . $selected_date_sql . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// This one query gets ALL students and JOINS their attendance for the SELECTED day
$sql = "
    SELECT 
        s.roll_no, s.name, s.department, s.year, s.address,
        a.attendance_date, a.attendance_time, a.status
    FROM 
        students s
    LEFT JOIN 
        attendance_log a ON s.roll_no = a.student_roll AND a.attendance_date = ?
    ORDER BY
        s.roll_no
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $selected_date_sql);
$stmt->execute();
$result = $stmt->get_result();

// Open the output stream
$output = fopen('php://output', 'w');

// Write the header row
fputcsv($output, ["Date", "Time", "Roll No", "Name", "Department", "Year", "Address", "Status"]);

// Loop through the database results and write each row
while ($row = $result->fetch_assoc()) {
    $status = $row['status'] ?? "Absent"; // Default to Absent if no log exists
    
    $csv_row = [
        $row['attendance_date'] ? date('m/d/Y', strtotime($row['attendance_date'])) : "N/A",
        $row['attendance_time'] ? date('h:i A', strtotime($row['attendance_time'])) : "N/A",
        $row['roll_no'],
        $row['name'],
        $row['department'],
        $row['year'],
        $row['address'],
        $status
    ];
    fputcsv($output, $csv_row);
}

$stmt->close();
$conn->close();
fclose($output);
exit;
?>