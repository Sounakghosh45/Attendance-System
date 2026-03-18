<?php
// student_action.php
session_start();
include 'db.php';
include 'sync_engine.php'; // <--- LOAD THE ENGINE

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    // --- ADD STUDENT ---
    if ($action == 'add') {
        $roll = $_POST['roll_no'];
        $name = $_POST['name'];
        $dept = $_POST['department'];
        $year = $_POST['year'];
        $addr = $_POST['address'];
        $pass = "12345"; // Default Password

        $check = $conn->query("SELECT roll_no FROM students WHERE roll_no = '$roll'");
        if ($check->num_rows > 0) {
            $_SESSION['msg'] = "Error: Roll Number already exists!";
            $_SESSION['msg_type'] = "danger";
        } else {
            // 1. Insert into DB (Ensure 'password' column exists in MySQL table!)
            $stmt = $conn->prepare("INSERT INTO students (roll_no, name, department, year, address, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $roll, $name, $dept, $year, $addr, $pass);
            
            if ($stmt->execute()) {
                // 2. Sync to Google Sheet
                // Format: [Roll, Name, Dept, Year, Addr, Pass]
                $sheetData = [$roll, $name, $dept, $year, $addr, $pass];
                google_add_student($sheetData);

                $_SESSION['msg'] = "Student added & synced to Sheet!";
                $_SESSION['msg_type'] = "success";
            } else {
                $_SESSION['msg'] = "DB Error: " . $conn->error;
                $_SESSION['msg_type'] = "danger";
            }
        }
    } 
    
    // --- EDIT STUDENT ---
    elseif ($action == 'edit') {
        $roll = $_POST['roll_no'];
        $name = $_POST['name'];
        $dept = $_POST['department'];
        $year = $_POST['year'];
        $addr = $_POST['address'];

        // Get existing password so we don't overwrite it with blank
        $res = $conn->query("SELECT password FROM students WHERE roll_no='$roll'");
        $row = $res->fetch_assoc();
        $pass = $row['password'] ?? "12345";

        // 1. Update DB
        $stmt = $conn->prepare("UPDATE students SET name=?, department=?, year=?, address=? WHERE roll_no=?");
        $stmt->bind_param("sssss", $name, $dept, $year, $addr, $roll);
        
        if ($stmt->execute()) {
            // 2. Sync to Google Sheet
            $sheetData = [$roll, $name, $dept, $year, $addr, $pass];
            google_update_student($roll, $sheetData);

            $_SESSION['msg'] = "Student updated & synced!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['msg'] = "DB Error: " . $conn->error;
            $_SESSION['msg_type'] = "danger";
        }
    } 
    
    // --- DELETE STUDENT ---
    elseif ($action == 'delete') {
        $roll = $_POST['roll_no'];

        // 1. Delete from DB
        $stmt = $conn->prepare("DELETE FROM students WHERE roll_no=?");
        $stmt->bind_param("s", $roll);
        
        if ($stmt->execute()) {
            // 2. Delete from Google Sheet
            google_delete_student($roll);

            $_SESSION['msg'] = "Student deleted from DB & Sheet.";
            $_SESSION['msg_type'] = "warning";
        } else {
            $_SESSION['msg'] = "DB Error: " . $conn->error;
            $_SESSION['msg_type'] = "danger";
        }
    }

    header("Location: index.php#students");
    exit;
}
?>