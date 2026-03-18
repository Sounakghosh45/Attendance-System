<?php
require 'db.php';

$admin_username = 'admin';
$admin_email = 'admin@example.com';
$admin_password = 'Password123!';

try {
    echo "Checking if admin account exists...\n";

    $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    if (!$check) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $check->bind_param("ss", $admin_username, $admin_email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "Default admin account already exists.\n";
    } else {
        echo "Creating default admin account...\n";
        $insert = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $insert->bind_param("sss", $admin_username, $admin_email, $admin_password);
        
        if ($insert->execute()) {
            echo "Default admin account created successfully.\n";
            echo "Username: $admin_username\n";
            echo "Password: $admin_password\n";
        } else {
            echo "Failed to create default admin account. Error: " . $conn->error . "\n";
        }
        $check->close();
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
