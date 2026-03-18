<?php
session_start();
include 'db.php'; // Connects to 'rfid_dashboard'
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('Asia/Kolkata');
$today_sql = date('Y-m-d');
$rows_affected = 0;

// This powerful query reads from your admin DB (rfid_dashboard)
// and inserts the final report into your student DB (student_db).
// This is your 11 PM logic.
$sql = "
    INSERT INTO student_db.attendance_history (student_roll, report_date, final_status)
    SELECT 
        s.roll_no, 
        ? AS report_date, 
        IFNULL(a.status, 'Absent') AS final_status
    FROM 
        rfid_dashboard.students s
    LEFT JOIN 
        rfid_dashboard.attendance_log a ON s.roll_no = a.student_roll AND a.attendance_date = ?
    ON DUPLICATE KEY UPDATE
        final_status = VALUES(final_status)
";
// The 'ON DUPLICATE KEY UPDATE' part ensures that if you run the
// script twice, it just updates the record, not create a new one.
// It will NEVER delete old history.

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $today_sql, $today_sql); 
$stmt->execute();

$rows_affected = $stmt->affected_rows;
$stmt->close();
$conn->close();

$message = "End of Day Report for $today_sql has been finalized. $rows_affected student records were saved to the student history database.";
echo "<script>
    alert('" . addslashes($message) . "');
    window.location.href = 'index.php';
</script>";
?>